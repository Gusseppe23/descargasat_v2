<?php

use DescargaSat\Paquetes\Contracts\EstadoPaquete;
use DescargaSat\Paquetes\Contracts\PaquetesRegistrados;
use DescargaSat\Paquetes\Models\Paquete;
use DescargaSat\Solicitudes\Contracts\EstadoSolicitud;
use DescargaSat\Solicitudes\Models\Solicitud;
use Illuminate\Console\Scheduling\Event as ScheduleEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use PhpCfdi\SatWsDescargaMasiva\WebClient\WebClientInterface;
use Tests\Support\FielesFalsas;
use Tests\Support\WebClientFalso;

/**
 * Sustituye el cliente web del SAT: primero responde la autenticación y luego lo indicado.
 */
function satVerifica(string ...$fixtures): WebClientFalso
{
    $webClient = (new WebClientFalso)->responder('autenticacion.xml');

    foreach ($fixtures as $fixture) {
        $webClient->responder($fixture);
    }

    app()->instance(WebClientInterface::class, $webClient);

    return $webClient;
}

test('moves the solicitud to the state reported by the sat', function (string $respuesta, EstadoSolicitud $esperado) {
    FielesFalsas::usar();
    satVerifica($respuesta);
    $solicitud = Solicitud::factory()->create(['fiel_id' => 1]);

    $this->artisan('sat:verificar')->assertSuccessful();

    expect($solicitud->fresh()->estado)->toBe($esperado);
})->with([
    'still accepted' => ['verificacion-aceptada.xml', EstadoSolicitud::Aceptada],
    'in progress' => ['verificacion-en-proceso.xml', EstadoSolicitud::EnProceso],
    'failed' => ['verificacion-error.xml', EstadoSolicitud::Error],
    'rejected' => ['verificacion-rechazada.xml', EstadoSolicitud::Rechazada],
    'expired' => ['verificacion-vencida.xml', EstadoSolicitud::Vencida],
]);

test('keeps the explanation of the sat when the solicitud fails or is rejected', function (string $respuesta, string $explicacion) {
    FielesFalsas::usar();
    satVerifica($respuesta);
    $solicitud = Solicitud::factory()->create(['fiel_id' => 1]);

    $this->artisan('sat:verificar')->assertSuccessful();

    expect($solicitud->fresh()->mensaje_sat)->toBe($explicacion);
})->with([
    'failed' => ['verificacion-error.xml', 'Tope máximo: Indica que se está superando el tope máximo de CFDI o Metadata'],
    'rejected' => ['verificacion-rechazada.xml', 'Se agotó las solicitudes de por vida: Máximo para solicitudes con los mismos parámetros'],
]);

test('registers the paquetes of a terminada solicitud as pendientes and announces them', function () {
    FielesFalsas::usar();
    satVerifica('verificacion-terminada.xml');
    $solicitud = Solicitud::factory()->create(['fiel_id' => 1]);
    Event::fake([PaquetesRegistrados::class]);

    $this->artisan('sat:verificar')->assertSuccessful();

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Terminada)
        ->and(Paquete::where('solicitud_id', $solicitud->id)->orderBy('id')->get(['id_paquete_sat', 'estado'])->toArray())->toBe([
            ['id_paquete_sat' => '4E80345D-917F-40BB-A98F-4A73939343C5_01', 'estado' => EstadoPaquete::Pendiente->value],
            ['id_paquete_sat' => '4E80345D-917F-40BB-A98F-4A73939343C5_02', 'estado' => EstadoPaquete::Pendiente->value],
        ]);
    Event::assertDispatched(PaquetesRegistrados::class, fn (PaquetesRegistrados $evento): bool => $evento->solicitudId === $solicitud->id);
});

test('marks a solicitud finished without paquetes as sin resultados', function () {
    FielesFalsas::usar();
    satVerifica('verificacion-sin-resultados.xml');
    $solicitud = Solicitud::factory()->create(['fiel_id' => 1]);
    Event::fake([PaquetesRegistrados::class]);

    $this->artisan('sat:verificar')->assertSuccessful();

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::SinResultados);
    Event::assertNotDispatched(PaquetesRegistrados::class);
});

test('marks the solicitud as error when its fiel is no longer valid', function () {
    FielesFalsas::usar();
    $sat = new WebClientFalso;
    app()->instance(WebClientInterface::class, $sat);
    $this->travel(5)->years();
    $solicitud = Solicitud::factory()->create(['fiel_id' => 1]);

    $this->artisan('sat:verificar')->assertSuccessful();

    $solicitud->refresh();
    expect($solicitud->estado)->toBe(EstadoSolicitud::Error)
        ->and($solicitud->mensaje_sat)->toStartWith('La Fiel 30001000000500003416 venció el')
        ->and($sat->peticiones)->toBeEmpty();
});

test('keeps the state and records the problem when the sat does not answer properly', function (Closure $sat, string $problema) {
    FielesFalsas::usar();
    $this->freezeTime();
    app()->instance(WebClientInterface::class, $sat());
    $solicitud = Solicitud::factory()->create(['fiel_id' => 1]);

    $this->artisan('sat:verificar')->assertSuccessful();

    $solicitud->refresh();
    expect($solicitud->estado)->toBe(EstadoSolicitud::Aceptada)
        ->and($solicitud->verificada_el?->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($solicitud->ultimo_problema)->toStartWith($problema);
})->with([
    'no connection' => [fn () => (new WebClientFalso)->sinConexion(), 'No se pudo contactar al SAT'],
    'error code' => [
        fn () => (new WebClientFalso)->responder('autenticacion.xml')->responder('verificacion-usuario-no-valido.xml'),
        'El SAT respondió 300: Usuario No Válido',
    ],
]);

test('records the time of a successful verification and clears the last problem', function () {
    FielesFalsas::usar();
    $this->freezeTime();
    satVerifica('verificacion-en-proceso.xml');
    $solicitud = Solicitud::factory()->create(['fiel_id' => 1, 'ultimo_problema' => 'No se pudo contactar al SAT']);

    $this->artisan('sat:verificar')->assertSuccessful();

    $solicitud->refresh();
    expect($solicitud->verificada_el?->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($solicitud->ultimo_problema)->toBeNull();
});

test('abandons a solicitud without a final state 72 hours after it was presented', function (Closure $sat) {
    FielesFalsas::usar();
    $this->freezeTime();
    app()->instance(WebClientInterface::class, $sat());
    $solicitud = Solicitud::factory()->create(['fiel_id' => 1, 'created_at' => now()->subHours(72)->subMinute()]);

    $this->artisan('sat:verificar')->assertSuccessful();

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Abandonada);
})->with([
    'still in progress' => [fn () => (new WebClientFalso)->responder('autenticacion.xml')->responder('verificacion-en-proceso.xml')],
    'no answer' => [fn () => (new WebClientFalso)->sinConexion()],
]);

test('does not abandon a solicitud that the sat finished after 72 hours', function () {
    FielesFalsas::usar();
    $this->freezeTime();
    satVerifica('verificacion-terminada.xml');
    $solicitud = Solicitud::factory()->create(['fiel_id' => 1, 'created_at' => now()->subHours(73)]);

    $this->artisan('sat:verificar')->assertSuccessful();

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Terminada);
});

test('does not ask the sat about solicitudes that already reached a final state', function () {
    FielesFalsas::usar();
    $sat = new WebClientFalso;
    app()->instance(WebClientInterface::class, $sat);
    Solicitud::factory()->count(6)->sequence(
        ['estado' => EstadoSolicitud::Terminada],
        ['estado' => EstadoSolicitud::Descargada],
        ['estado' => EstadoSolicitud::SinResultados],
        ['estado' => EstadoSolicitud::Error],
        ['estado' => EstadoSolicitud::Vencida],
        ['estado' => EstadoSolicitud::Abandonada],
    )->create(['fiel_id' => 1]);
    Solicitud::factory()->rechazada()->create(['fiel_id' => 1]);

    $this->artisan('sat:verificar')->assertSuccessful();

    expect($sat->peticiones)->toBeEmpty();
});

test('is scheduled every five minutes without overlapping runs', function () {
    $evento = collect(app(Schedule::class)->events())
        ->first(fn (ScheduleEvent $evento): bool => str_contains((string) $evento->command, 'sat:verificar'));

    expect($evento)->not->toBeNull()
        ->and($evento->expression)->toBe('*/5 * * * *')
        ->and($evento->withoutOverlapping)->toBeTrue();
});
