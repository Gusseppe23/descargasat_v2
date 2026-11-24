<?php

use App\Models\User;
use DescargaSat\Cancelaciones\Contracts\EstadoCancelacion;
use DescargaSat\Cancelaciones\Contracts\ResultadoDeEnvio;
use DescargaSat\Cancelaciones\Jobs\EnviarRespuesta;
use DescargaSat\Cancelaciones\Models\CancelacionPendiente;
use DescargaSat\Cancelaciones\Models\EnvioDeRespuesta;
use DescargaSat\SatAutenticacion\Contracts\Respuesta;
use DescargaSat\SatAutenticacion\Contracts\WebClientCancelaciones;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\CertificadoDePrueba;
use Tests\Support\CsdsFalsos;
use Tests\Support\WebClientFalso;

const UUID_DEL_ENVIO = 'AE4B0CA8-8116-4699-8E20-77B6E9C507A2';

/**
 * Un Envío de respuesta en curso del UUID de prueba, con el SAT sustituido por el cliente devuelto.
 *
 * @return array{0: EnvioDeRespuesta, 1: WebClientFalso}
 */
function envioEnCurso(Respuesta $respuesta = Respuesta::Rechazo): array
{
    Storage::fake('local');
    CsdsFalsos::usar();
    $webClient = new WebClientFalso;
    app()->instance(WebClientCancelaciones::class, $webClient);

    $cancelacion = CancelacionPendiente::factory()->enEstado(EstadoCancelacion::Enviando)->create(['uuid' => UUID_DEL_ENVIO]);
    $envio = EnvioDeRespuesta::factory()->for($cancelacion, 'cancelacion')->create(['respuesta' => $respuesta]);

    return [$envio, $webClient];
}

function enviar(EnvioDeRespuesta $envio): void
{
    app()->call([new EnviarRespuesta($envio->id), 'handle']);
}

test('runs on the database queue with a single attempt and a timeout', function () {
    $job = new EnviarRespuesta(1);

    expect($job->connection)->toBe('database')
        ->and($job->tries)->toBe(1)
        ->and($job->timeout)->toBe(120)
        ->and($job->failOnTimeout)->toBeTrue();
});

test('an acuse sealed by the sat finishes the cancelacion and keeps the evidence', function (Respuesta $respuesta, EstadoCancelacion $estado) {
    [$envio, $webClient] = envioEnCurso($respuesta);
    $webClient->responderAcuse(UUID_DEL_ENVIO, $respuesta->value, codigo: '1000', estatus: '1005');

    enviar($envio);

    $envio->refresh();

    expect($envio->cancelacion->estado)->toBe($estado)
        ->and($envio->resultado)->toBe(ResultadoDeEnvio::AcuseRecibido)
        ->and($envio->codigo_sat)->toBe('1000')
        ->and($envio->estatus_uuid)->toBe('1005')
        ->and($envio->problema)->toBeNull()
        ->and($envio->terminado_el)->not->toBeNull()
        ->and(Storage::disk('local')->get($envio->ruta_enviado))->toBe($webClient->peticiones[0]->getBody())
        ->and(Storage::disk('local')->get($envio->ruta_recibido))->toContain('Id="SelloSAT"')
        ->and($envio->ruta_recibido)->toBe('cancelaciones/'.UUID_DEL_ENVIO."/envio-{$envio->id}-recibido.xml");
})->with([
    'aceptacion' => [Respuesta::Aceptacion, EstadoCancelacion::AceptacionEnviada],
    'rechazo' => [Respuesta::Rechazo, EstadoCancelacion::RechazoEnviado],
]);

test('an error code of the sat sends it back to por responder with the problem', function (string $codigo, string $problema) {
    [$envio, $webClient] = envioEnCurso();
    $webClient->responderAcuse(UUID_DEL_ENVIO, 'Rechazo', codigo: $codigo, estatus: $codigo, sellado: false);

    enviar($envio);

    $envio->refresh();

    expect($envio->cancelacion->estado)->toBe(EstadoCancelacion::PorResponder)
        ->and($envio->resultado)->toBe(ResultadoDeEnvio::NoProcesada)
        ->and($envio->codigo_sat)->toBe($codigo)
        ->and($envio->problema)->toBe($problema)
        ->and($envio->ruta_recibido)->not->toBeNull();
})->with([
    '2002' => ['2002', 'El SAT respondió 2002: El folio no pertenece al receptor.'],
    '30002' => ['30002', 'El SAT respondió 30002: Sello mal formado o inválido.'],
    '30005' => ['30005', 'El SAT respondió 30005: Certificado no válido para entes públicos.'],
]);

test('without an acuse it is left as envio incierto and is not retried', function (Closure $preparar, string $problema, bool $guardaLoEnviado) {
    [$envio, $webClient] = envioEnCurso();
    $preparar($webClient);
    Queue::fake();

    enviar($envio);

    $envio->refresh();

    expect($envio->cancelacion->estado)->toBe(EstadoCancelacion::EnvioIncierto)
        ->and($envio->resultado)->toBe(ResultadoDeEnvio::Incierto)
        ->and($envio->problema)->toBe($problema)
        ->and($envio->ruta_enviado !== null)->toBe($guardaLoEnviado)
        ->and($webClient->peticiones)->toHaveCount(1);
    Queue::assertNothingPushed();
})->with([
    'soap fault' => [
        fn (WebClientFalso $webClient) => $webClient->fallarCon('cancelaciones-fault.xml'),
        'El SAT respondió con un error SOAP: An error occurred when verifying security for the message.',
        true,
    ],
    'empty answer' => [
        fn (WebClientFalso $webClient) => $webClient->responderTexto('', 502),
        'El SAT respondió vacío (HTTP 502).',
        true,
    ],
    'no connection' => [
        fn (WebClientFalso $webClient) => $webClient->sinConexion(),
        'No se pudo contactar al SAT (Error connecting to https://cancelaciones.sat.invalid/AceptacionRechazoServiceEP.svc).',
        true,
    ],
    'code 1000 without the seal of the sat' => [
        fn (WebClientFalso $webClient) => $webClient->responderAcuse(UUID_DEL_ENVIO, 'Rechazo', sellado: false),
        'El SAT respondió 1000 pero sin SelloSAT, así que no hay Acuse de respuesta.',
        true,
    ],
]);

test('a crashed worker leaves it as envio incierto', function () {
    [$envio] = envioEnCurso();

    (new EnviarRespuesta($envio->id))->failed(new RuntimeException('Worker caído'));

    $envio->refresh();

    expect($envio->cancelacion->estado)->toBe(EstadoCancelacion::EnvioIncierto)
        ->and($envio->resultado)->toBe(ResultadoDeEnvio::Incierto)
        ->and($envio->problema)->toBe('El envío se interrumpió: Worker caído');
});

test('a failure after the acuse does not overwrite the result', function () {
    [$envio, $webClient] = envioEnCurso();
    $webClient->responderAcuse(UUID_DEL_ENVIO, 'Rechazo');
    enviar($envio);

    (new EnviarRespuesta($envio->id))->failed(new RuntimeException('Tarde'));

    expect($envio->fresh()->cancelacion->estado)->toBe(EstadoCancelacion::RechazoEnviado);
});

test('with an expired csd nothing is sent and it goes back to por responder', function () {
    [$envio, $webClient] = envioEnCurso();
    CsdsFalsos::usar(CertificadoDePrueba::csd());
    $this->travel(5)->years();

    enviar($envio);

    $envio->refresh();

    expect($webClient->peticiones)->toBeEmpty()
        ->and($envio->resultado)->toBe(ResultadoDeEnvio::NoProcesada)
        ->and($envio->cancelacion->estado)->toBe(EstadoCancelacion::PorResponder)
        ->and($envio->problema)->toStartWith('El CSD 30001000000500003417 venció el');
});

test('the detail shows the history of envios and offers the evidence only to download', function () {
    [$envio, $webClient] = envioEnCurso();
    $envio->usuario->update(['name' => 'Ana López']);
    $webClient->responderAcuse(UUID_DEL_ENVIO, 'Rechazo');
    enviar($envio);
    $this->actingAs(User::factory()->create());

    Livewire::test('cancelaciones::detalle', ['cancelacion' => $envio->cancelacion->fresh()])
        ->assertSee(['Ana López', 'Rechazo', 'Acuse recibido', '1000', 'EstatusUUID 1005'])
        ->assertSee(route('cancelaciones.evidencia', [$envio, 'enviado']))
        ->assertSee(route('cancelaciones.evidencia', [$envio, 'recibido']))
        ->assertDontSee('Borrar');
});

test('the evidence is downloaded only with a session', function () {
    [$envio, $webClient] = envioEnCurso();
    $webClient->responderAcuse(UUID_DEL_ENVIO, 'Rechazo');
    enviar($envio);
    $url = route('cancelaciones.evidencia', [$envio, 'recibido']);

    $this->get($url)->assertRedirect(route('login'));

    $respuesta = $this->actingAs(User::factory()->create())->get($url);

    $respuesta->assertOk()->assertDownload(UUID_DEL_ENVIO."-envio-{$envio->id}-recibido.xml");
    expect($respuesta->streamedContent())->toContain('Id="SelloSAT"');
});

test('downloading evidence that does not exist answers not found', function () {
    [$envio] = envioEnCurso();

    $this->actingAs(User::factory()->create())
        ->get(route('cancelaciones.evidencia', [$envio, 'recibido']))
        ->assertNotFound();
});
