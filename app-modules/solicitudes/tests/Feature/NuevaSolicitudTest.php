<?php

use App\Models\User;
use DescargaSat\Solicitudes\Models\Solicitud;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PhpCfdi\SatWsDescargaMasiva\WebClient\WebClientInterface;
use Tests\Support\FielesFalsas;
use Tests\Support\WebClientFalso;

/**
 * El formulario lleno con una solicitud válida de marzo de 2026: CFDI emitidos en XML.
 *
 * @param  array<string, string>  $cambios
 */
function formularioLleno(array $cambios = []): Testable
{
    $formulario = Livewire::test('solicitudes::nueva');

    foreach ([
        'servicio' => 'cfdi',
        'fechaInicio' => '2026-03-01',
        'fechaFin' => '2026-03-31',
        'tipoDescarga' => 'emitidos',
        'tipoSolicitud' => 'xml',
        'tipoComprobante' => '',
        'estadoComprobante' => 'todos',
        ...$cambios,
    ] as $campo => $valor) {
        $formulario->set($campo, $valor);
    }

    return $formulario;
}

/**
 * Sustituye el cliente web del SAT: primero responde la autenticación y luego lo indicado.
 */
function satResponde(string ...$fixtures): WebClientFalso
{
    $webClient = (new WebClientFalso)->responder('autenticacion.xml');

    foreach ($fixtures as $fixture) {
        $webClient->responder($fixture);
    }

    app()->instance(WebClientInterface::class, $webClient);

    return $webClient;
}

test('guests are redirected to the login page', function () {
    $this->get('/solicitudes/nueva')->assertRedirect(route('login'));
});

test('blocks the form when there is no active fiel', function () {
    FielesFalsas::usar(hayActiva: false);

    $this->actingAs(User::factory()->create());

    Livewire::test('solicitudes::nueva')
        ->assertSee('Activa una Fiel para presentar solicitudes.')
        ->call('presentar')
        ->assertHasErrors(['fiel' => 'Activa una Fiel para presentar solicitudes.']);
});

test('presents the solicitud to the sat and saves it as aceptada', function () {
    FielesFalsas::usar();
    $sat = satResponde('solicitud-emitidos-aceptada.xml');
    $usuario = User::factory()->create();

    $this->actingAs($usuario);

    formularioLleno()
        ->call('presentar')
        ->assertHasNoErrors()
        ->assertRedirect(route('solicitudes.index'));

    $peticion = $sat->peticiones[1];
    expect($peticion->getUri())->toBe('https://cfdidescargamasivasolicitud.clouda.sat.gob.mx/SolicitaDescargaService.svc')
        ->and($peticion->getBody())
        ->toContain('FechaInicial="2026-03-01T00:00:00"')
        ->toContain('FechaFinal="2026-03-31T23:59:59"')
        ->toContain('TipoSolicitud="CFDI"');

    $this->assertDatabaseHas('solicitudes', [
        'fiel_id' => 1,
        'presentada_por_id' => $usuario->id,
        'servicio' => 'cfdi',
        'fecha_inicio' => '2026-03-01 00:00:00',
        'fecha_fin' => '2026-03-31 00:00:00',
        'tipo_descarga' => 'emitidos',
        'tipo_solicitud' => 'xml',
        'tipo_comprobante' => null,
        'estado_comprobante' => 'todos',
        'id_solicitud_sat' => '4e80345d-917f-40bb-a98f-4a73939343c5',
        'estado' => 'aceptada',
        'codigo_sat' => 5000,
    ]);
});

test('saves the solicitud as rechazada with the reason given by the sat', function () {
    FielesFalsas::usar();
    satResponde('solicitud-emitidos-rechazada.xml');

    $this->actingAs(User::factory()->create());

    formularioLleno()
        ->call('presentar')
        ->assertHasNoErrors()
        ->assertRedirect(route('solicitudes.index'));

    $this->assertDatabaseHas('solicitudes', [
        'id_solicitud_sat' => null,
        'estado' => 'rechazada',
        'codigo_sat' => 5002,
        'mensaje_sat' => 'Se han agotado las solicitudes de por vida',
    ]);
});

test('saves nothing and keeps the form when the sat does not answer', function () {
    FielesFalsas::usar();
    app()->instance(WebClientInterface::class, (new WebClientFalso)->sinConexion());

    $this->actingAs(User::factory()->create());

    formularioLleno()
        ->call('presentar')
        ->assertHasErrors('sat')
        ->assertSee('No se pudo contactar al SAT')
        ->assertSet('fechaInicio', '2026-03-01')
        ->assertNoRedirect();

    $this->assertDatabaseEmpty('solicitudes');
});

test('rejects an invalid period without contacting the sat', function (Closure $fechas, string $campo, string $mensaje) {
    $this->freezeTime();
    FielesFalsas::usar();
    $sat = new WebClientFalso;
    app()->instance(WebClientInterface::class, $sat);

    $this->actingAs(User::factory()->create());

    formularioLleno($fechas())
        ->call('presentar')
        ->assertHasErrors([$campo => $mensaje]);

    expect($sat->peticiones)->toBeEmpty();
})->with([
    'end before start' => [
        fn () => ['fechaInicio' => '2026-03-31', 'fechaFin' => '2026-03-01'],
        'fechaFin',
        'La fecha final no puede ser anterior a la inicial.',
    ],
    'start more than six years ago' => [
        fn () => ['fechaInicio' => now()->subYears(6)->subDay()->toDateString(), 'fechaFin' => now()->subYears(5)->toDateString()],
        'fechaInicio',
        'El SAT solo permite pedir comprobantes de los últimos 6 años.',
    ],
    'end in the future' => [
        fn () => ['fechaInicio' => now()->subDays(3)->toDateString(), 'fechaFin' => now()->addDay()->toDateString()],
        'fechaFin',
        'La fecha final no puede ser futura.',
    ],
]);

test('sends the current time as the end when the period ends today', function () {
    FielesFalsas::usar();
    $this->freezeTime();
    $sat = satResponde('solicitud-emitidos-aceptada.xml');

    $this->actingAs(User::factory()->create());

    formularioLleno(['fechaInicio' => now()->subDays(5)->toDateString(), 'fechaFin' => now()->toDateString()])
        ->call('presentar')
        ->assertHasNoErrors();

    expect($sat->peticiones[1]->getBody())->toContain('FechaFinal="'.now()->format('Y-m-d\TH:i:s').'"');
});

test('always asks for vigentes when requesting received xml', function () {
    FielesFalsas::usar();
    $sat = satResponde('solicitud-recibidos-aceptada.xml');

    $this->actingAs(User::factory()->create());

    formularioLleno(['tipoDescarga' => 'recibidos', 'tipoSolicitud' => 'xml', 'estadoComprobante' => 'todos'])
        ->call('presentar')
        ->assertHasNoErrors();

    expect($sat->peticiones[1]->getBody())->toContain('EstadoComprobante="Vigente"');
    $this->assertDatabaseHas('solicitudes', ['tipo_descarga' => 'recibidos', 'estado_comprobante' => 'vigentes']);
});

test('presents retenciones to its own service without a tipo de comprobante', function () {
    FielesFalsas::usar();
    $sat = satResponde('solicitud-emitidos-aceptada.xml');

    $this->actingAs(User::factory()->create());

    formularioLleno(['servicio' => 'retenciones', 'tipoComprobante' => 'I'])
        ->call('presentar')
        ->assertHasNoErrors();

    expect($sat->peticiones[1]->getUri())->toBe('https://retendescargamasivasolicitud.clouda.sat.gob.mx/SolicitaDescargaService.svc')
        ->and($sat->peticiones[1]->getBody())->not->toContain('TipoComprobante="I"');
    $this->assertDatabaseHas('solicitudes', ['servicio' => 'retenciones', 'tipo_comprobante' => null]);
});

test('warns about a duplicated solicitud without contacting the sat', function () {
    FielesFalsas::usar();
    $sat = new WebClientFalso;
    app()->instance(WebClientInterface::class, $sat);
    // La fábrica usa los mismos parámetros que formularioLleno().
    $anterior = Solicitud::factory()->create(['created_at' => '2026-03-03 10:00:00']);

    $this->actingAs(User::factory()->create());

    formularioLleno()
        ->call('presentar')
        ->assertSee('Ya presentaste esta solicitud el 03/03/2026.')
        ->assertSee(route('solicitudes.index').'#solicitud-'.$anterior->id)
        ->assertSee('Presentar de todos modos')
        ->assertNoRedirect();

    expect($sat->peticiones)->toBeEmpty()
        ->and(Solicitud::count())->toBe(1);
});

test('presents a duplicated solicitud when the user confirms it', function () {
    FielesFalsas::usar();
    $sat = satResponde('solicitud-emitidos-aceptada.xml');
    Solicitud::factory()->create();

    $this->actingAs(User::factory()->create());

    formularioLleno()
        ->call('presentar')
        ->call('presentar', confirmarDuplicada: true)
        ->assertHasNoErrors()
        ->assertRedirect(route('solicitudes.index'));

    expect($sat->peticiones)->toHaveCount(2)
        ->and(Solicitud::count())->toBe(2);
});

test('does not count a rechazada solicitud as a duplicate', function () {
    FielesFalsas::usar();
    satResponde('solicitud-emitidos-aceptada.xml');
    Solicitud::factory()->rechazada()->create();

    $this->actingAs(User::factory()->create());

    formularioLleno()
        ->call('presentar')
        ->assertHasNoErrors()
        ->assertRedirect(route('solicitudes.index'));
});

test('shows why the solicitud cannot be presented when the active fiel expired', function () {
    FielesFalsas::usar();
    $sat = new WebClientFalso;
    app()->instance(WebClientInterface::class, $sat);
    $this->travel(5)->years();

    $this->actingAs(User::factory()->create());

    formularioLleno(['fechaInicio' => now()->subMonth()->toDateString(), 'fechaFin' => now()->subDay()->toDateString()])
        ->call('presentar')
        ->assertHasErrors('fiel')
        ->assertSee('venció el');

    expect($sat->peticiones)->toBeEmpty();
    $this->assertDatabaseEmpty('solicitudes');
});
