<?php

use DescargaSat\Cancelaciones\Contracts\EstadoCancelacion;
use DescargaSat\Cancelaciones\Models\CancelacionPendiente;
use DescargaSat\Cancelaciones\Models\RevisionDeCancelaciones;
use DescargaSat\SatAutenticacion\Contracts\WebClientCancelaciones;
use Illuminate\Console\Scheduling\Event as ScheduleEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Tests\Support\CertificadoDePrueba;
use Tests\Support\CsdsFalsos;
use Tests\Support\WebClientFalso;

const PRIMER_UUID = 'AE4B0CA8-8116-4699-8E20-77B6E9C507A2';
const SEGUNDO_UUID = 'D92516E5-A881-423C-B40F-384008DFE03F';

/**
 * Sustituye el cliente web del servicio de cancelaciones y responde, en orden, con los fixtures indicados.
 */
function satRevisaCancelaciones(string ...$fixtures): WebClientFalso
{
    $webClient = new WebClientFalso;

    foreach ($fixtures as $fixture) {
        $webClient->responder($fixture);
    }

    app()->instance(WebClientCancelaciones::class, $webClient);

    return $webClient;
}

test('registers the pendientes reported by the sat as por responder', function () {
    $this->freezeTime();
    CsdsFalsos::usar();
    satRevisaCancelaciones('cancelaciones-pendientes.xml');

    $this->artisan('sat:cancelaciones')->assertSuccessful();

    expect(CancelacionPendiente::orderBy('uuid')->get()->map(fn (CancelacionPendiente $cancelacion): array => [
        $cancelacion->uuid,
        $cancelacion->estado,
        $cancelacion->primera_deteccion_el->toDateTimeString(),
        $cancelacion->ultima_deteccion_el->toDateTimeString(),
    ])->all())->toBe([
        [PRIMER_UUID, EstadoCancelacion::PorResponder, now()->toDateTimeString(), now()->toDateTimeString()],
        [SEGUNDO_UUID, EstadoCancelacion::PorResponder, now()->toDateTimeString(), now()->toDateTimeString()],
    ]);
});

test('keeps the revision with the code of the sat and how many pendientes it reported', function () {
    CsdsFalsos::usar();
    satRevisaCancelaciones('cancelaciones-pendientes.xml');

    $this->artisan('sat:cancelaciones')->assertSuccessful();

    expect(RevisionDeCancelaciones::sole()->only('csd_id', 'codigo_sat', 'pendientes_reportadas', 'problema'))->toBe([
        'csd_id' => 1,
        'codigo_sat' => '1100',
        'pendientes_reportadas' => 2,
        'problema' => null,
    ]);
});

test('reviewing again does not duplicate and only moves the last detection', function () {
    $this->freezeTime();
    CsdsFalsos::usar();
    satRevisaCancelaciones('cancelaciones-pendientes.xml', 'cancelaciones-pendientes.xml');
    $primeraRevision = now()->toDateTimeString();

    $this->artisan('sat:cancelaciones')->assertSuccessful();
    $this->travel(30)->minutes();
    $this->artisan('sat:cancelaciones')->assertSuccessful();

    $cancelacion = CancelacionPendiente::where('uuid', PRIMER_UUID)->sole();

    expect(CancelacionPendiente::count())->toBe(2)
        ->and($cancelacion->estado)->toBe(EstadoCancelacion::PorResponder)
        ->and($cancelacion->primera_deteccion_el->toDateTimeString())->toBe($primeraRevision)
        ->and($cancelacion->ultima_deteccion_el->toDateTimeString())->toBe(now()->toDateTimeString());
});

test('marks as retirada the por responder ones the sat no longer reports', function (string $respuesta, array $siguenPorResponder) {
    CsdsFalsos::usar();
    satRevisaCancelaciones($respuesta);
    CancelacionPendiente::factory()->create(['uuid' => PRIMER_UUID]);
    $ausente = CancelacionPendiente::factory()->create(['uuid' => '11111111-2222-3333-4444-555555555555']);

    $this->artisan('sat:cancelaciones')->assertSuccessful();

    expect($ausente->fresh()->estado)->toBe(EstadoCancelacion::Retirada)
        ->and(CancelacionPendiente::where('estado', EstadoCancelacion::PorResponder)->orderBy('uuid')->pluck('uuid')->all())->toBe($siguenPorResponder);
})->with([
    'list with pendientes (1100)' => ['cancelaciones-pendientes.xml', [PRIMER_UUID, SEGUNDO_UUID]],
    'no solicitudes for the rfc (1101)' => ['cancelaciones-sin-solicitudes.xml', []],
]);

test('does not retire anything when the sat has no peticiones right now', function () {
    CsdsFalsos::usar();
    satRevisaCancelaciones('cancelaciones-sin-peticiones-por-ahora.xml');
    $cancelacion = CancelacionPendiente::factory()->create();

    $this->artisan('sat:cancelaciones')->assertSuccessful();

    expect($cancelacion->fresh()->estado)->toBe(EstadoCancelacion::PorResponder)
        ->and(RevisionDeCancelaciones::sole()->problema)->toBeNull();
});

test('only retires cancelaciones that are por responder', function (EstadoCancelacion $estado) {
    CsdsFalsos::usar();
    satRevisaCancelaciones('cancelaciones-sin-solicitudes.xml');
    $cancelacion = CancelacionPendiente::factory()->enEstado($estado)->create();

    $this->artisan('sat:cancelaciones')->assertSuccessful();

    expect($cancelacion->fresh()->estado)->toBe($estado);
})->with([
    EstadoCancelacion::Enviando,
    EstadoCancelacion::EnvioIncierto,
    EstadoCancelacion::AceptacionEnviada,
    EstadoCancelacion::RechazoEnviado,
]);

test('a retirada that the sat reports again goes back to por responder', function () {
    CsdsFalsos::usar();
    satRevisaCancelaciones('cancelaciones-pendientes.xml');
    $cancelacion = CancelacionPendiente::factory()->enEstado(EstadoCancelacion::Retirada)->create(['uuid' => PRIMER_UUID]);

    $this->artisan('sat:cancelaciones')->assertSuccessful();

    expect($cancelacion->fresh()->estado)->toBe(EstadoCancelacion::PorResponder);
});

test('without an active csd does not contact the sat and keeps the problem', function () {
    CsdsFalsos::usar(hayActivo: false);
    $webClient = satRevisaCancelaciones();
    $cancelacion = CancelacionPendiente::factory()->create();

    $this->artisan('sat:cancelaciones')->assertSuccessful();

    expect($webClient->peticiones)->toBeEmpty()
        ->and($cancelacion->fresh()->estado)->toBe(EstadoCancelacion::PorResponder)
        ->and(RevisionDeCancelaciones::sole()->problema)->toBe('No hay un CSD activo. Activa uno en la pantalla CSD.');
});

test('with an expired csd does not contact the sat and keeps the problem', function () {
    $certificado = CertificadoDePrueba::csd();
    CsdsFalsos::usar($certificado);
    $webClient = satRevisaCancelaciones();
    $this->travel(5)->years();

    $this->artisan('sat:cancelaciones')->assertSuccessful();

    expect($webClient->peticiones)->toBeEmpty()
        ->and(RevisionDeCancelaciones::sole()->problema)->toBe('El CSD 30001000000500003417 venció el '.$certificado->vigenteHasta->format('d/m/Y').'.');
});

test('keeps the problem and changes nothing when the sat does not answer well', function () {
    CsdsFalsos::usar();
    $webClient = satRevisaCancelaciones();
    $webClient->fallarCon('cancelaciones-fault.xml');
    $cancelacion = CancelacionPendiente::factory()->create();

    $this->artisan('sat:cancelaciones')->assertSuccessful();

    expect($cancelacion->fresh()->estado)->toBe(EstadoCancelacion::PorResponder)
        ->and(RevisionDeCancelaciones::sole()->problema)->toBe('El SAT respondió con un error SOAP: An error occurred when verifying security for the message.');
});

test('an unknown code is kept as the problem and retires nothing', function () {
    CsdsFalsos::usar();
    satRevisaCancelaciones('cancelaciones-codigo-desconocido.xml');
    $cancelacion = CancelacionPendiente::factory()->create();

    $this->artisan('sat:cancelaciones')->assertSuccessful();

    expect($cancelacion->fresh()->estado)->toBe(EstadoCancelacion::PorResponder)
        ->and(RevisionDeCancelaciones::sole()->only('codigo_sat', 'problema'))->toBe([
            'codigo_sat' => '1999',
            'problema' => 'El SAT respondió un código desconocido: 1999.',
        ]);
});

test('does not contact the sat while another revision is running', function () {
    CsdsFalsos::usar();
    $webClient = satRevisaCancelaciones();
    Cache::lock('cancelaciones:revision', 60)->get();

    $this->artisan('sat:cancelaciones')->assertSuccessful();

    expect($webClient->peticiones)->toBeEmpty()
        ->and(RevisionDeCancelaciones::count())->toBe(0);
});

test('is scheduled every thirty minutes by default without overlapping runs', function () {
    $evento = collect(app(Schedule::class)->events())
        ->first(fn (ScheduleEvent $evento): bool => str_contains((string) $evento->command, 'sat:cancelaciones'));

    expect($evento)->not->toBeNull()
        ->and($evento->expression)->toBe('*/30 * * * *')
        ->and($evento->withoutOverlapping)->toBeTrue();
});

test('is scheduled with the configured frequency', function () {
    config(['sat.cancelaciones.cada_minutos' => 15]);
    app()->forgetInstance(Schedule::class);

    $evento = collect(app(Schedule::class)->events())
        ->first(fn (ScheduleEvent $evento): bool => str_contains((string) $evento->command, 'sat:cancelaciones'));

    expect($evento->expression)->toBe('*/15 * * * *');
});
