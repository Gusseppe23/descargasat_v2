<?php

use App\Models\User;
use DescargaSat\Cancelaciones\Contracts\Cancelaciones;
use DescargaSat\Cancelaciones\Contracts\EstadoCancelacion;
use DescargaSat\Cancelaciones\Jobs\RevisarCancelacionesAhora;
use DescargaSat\Cancelaciones\Models\CancelacionPendiente;
use DescargaSat\Cancelaciones\Models\RevisionDeCancelaciones;
use DescargaSat\SatAutenticacion\Contracts\WebClientCancelaciones;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\CsdsFalsos;
use Tests\Support\WebClientFalso;

test('revisar ahora queues the revision on the database queue and shows it is running', function () {
    Queue::fake();
    $this->actingAs(User::factory()->create());

    Livewire::test('cancelaciones::lista')
        ->assertSee('Revisar ahora')
        ->call('revisarAhora')
        ->assertSee('Revisión en curso…')
        ->assertDontSee('Revisar ahora');

    Queue::assertPushed(RevisarCancelacionesAhora::class, fn (RevisarCancelacionesAhora $job): bool => $job->connection === 'database');
});

test('clicking again while it is pending does not queue another revision', function () {
    Queue::fake();
    $this->actingAs(User::factory()->create());

    Livewire::test('cancelaciones::lista')
        ->call('revisarAhora')
        ->call('revisarAhora');

    Queue::assertPushed(RevisarCancelacionesAhora::class, 1);
});

test('the queued job runs the revision and clears the running mark', function () {
    CsdsFalsos::usar();
    $webClient = (new WebClientFalso)->responder('cancelaciones-pendientes.xml');
    app()->instance(WebClientCancelaciones::class, $webClient);
    Cache::put(RevisarCancelacionesAhora::MARCA_EN_CURSO, true);

    app()->call([new RevisarCancelacionesAhora, 'handle']);

    expect(CancelacionPendiente::count())->toBe(2)
        ->and(RevisionDeCancelaciones::count())->toBe(1)
        ->and(RevisarCancelacionesAhora::enCurso())->toBeFalse();
});

test('the job does not run a second revision while the scheduled one holds the lock', function () {
    CsdsFalsos::usar();
    $webClient = new WebClientFalso;
    app()->instance(WebClientCancelaciones::class, $webClient);
    Cache::lock('cancelaciones:revision', 60)->get();
    Cache::put(RevisarCancelacionesAhora::MARCA_EN_CURSO, true);

    app()->call([new RevisarCancelacionesAhora, 'handle']);

    expect($webClient->peticiones)->toBeEmpty()
        ->and(RevisionDeCancelaciones::count())->toBe(0)
        ->and(RevisarCancelacionesAhora::enCurso())->toBeFalse();
});

test('filters combine state and the range of the first detection', function () {
    CancelacionPendiente::factory()->create(['uuid' => 'AAAAAAAA-0000-0000-0000-000000000001', 'primera_deteccion_el' => '2026-10-05 10:00:00']);
    CancelacionPendiente::factory()->create(['uuid' => 'AAAAAAAA-0000-0000-0000-000000000002', 'primera_deteccion_el' => '2026-10-10 23:59:00']);
    CancelacionPendiente::factory()->enEstado(EstadoCancelacion::Retirada)->create(['uuid' => 'AAAAAAAA-0000-0000-0000-000000000003', 'primera_deteccion_el' => '2026-10-07 09:00:00']);
    CancelacionPendiente::factory()->create(['uuid' => 'AAAAAAAA-0000-0000-0000-000000000004', 'primera_deteccion_el' => '2026-10-11 00:00:00']);
    $this->actingAs(User::factory()->create());

    Livewire::test('cancelaciones::lista')
        ->set('estado', EstadoCancelacion::PorResponder->value)
        ->set('desde', '2026-10-05')
        ->set('hasta', '2026-10-10')
        ->assertSee(['AAAAAAAA-0000-0000-0000-000000000001', 'AAAAAAAA-0000-0000-0000-000000000002'])
        ->assertDontSee(['AAAAAAAA-0000-0000-0000-000000000003', 'AAAAAAAA-0000-0000-0000-000000000004'])
        ->call('limpiarFiltros')
        ->assertSee(['AAAAAAAA-0000-0000-0000-000000000003', 'AAAAAAAA-0000-0000-0000-000000000004']);
});

test('searches by the full uuid or a fragment of it in any case', function () {
    CancelacionPendiente::factory()->create(['uuid' => 'AE4B0CA8-8116-4699-8E20-77B6E9C507A2']);
    CancelacionPendiente::factory()->create(['uuid' => 'D92516E5-A881-423C-B40F-384008DFE03F']);
    $this->actingAs(User::factory()->create());

    Livewire::test('cancelaciones::lista')
        ->set('uuid', 'ae4b0ca8-8116-4699-8e20-77b6e9c507a2')
        ->assertSee('AE4B0CA8-8116-4699-8E20-77B6E9C507A2')
        ->assertDontSee('D92516E5-A881-423C-B40F-384008DFE03F')
        ->set('uuid', ' 77b6e9 ')
        ->assertSee('AE4B0CA8-8116-4699-8E20-77B6E9C507A2')
        ->assertDontSee('D92516E5-A881-423C-B40F-384008DFE03F');
});

test('the uuid search combines with the state filter', function () {
    CancelacionPendiente::factory()->create(['uuid' => 'BBBBBBBB-0000-0000-0000-000000000001']);
    CancelacionPendiente::factory()->enEstado(EstadoCancelacion::Retirada)->create(['uuid' => 'BBBBBBBB-0000-0000-0000-000000000002']);
    CancelacionPendiente::factory()->create(['uuid' => 'CCCCCCCC-0000-0000-0000-000000000003']);
    $this->actingAs(User::factory()->create());

    Livewire::test('cancelaciones::lista')
        ->set('uuid', 'bbbbbbbb')
        ->set('estado', EstadoCancelacion::Retirada->value)
        ->assertSee('BBBBBBBB-0000-0000-0000-000000000002')
        ->assertDontSee(['BBBBBBBB-0000-0000-0000-000000000001', 'CCCCCCCC-0000-0000-0000-000000000003']);
});

test('a uuid search without matches says there are no cancelaciones pendientes', function () {
    CancelacionPendiente::factory()->create(['uuid' => 'AE4B0CA8-8116-4699-8E20-77B6E9C507A2']);
    $this->actingAs(User::factory()->create());

    Livewire::test('cancelaciones::lista')
        ->set('uuid', 'FFFFFFFF')
        ->assertSee('No hay Cancelaciones pendientes.')
        ->assertDontSee('AE4B0CA8-8116-4699-8E20-77B6E9C507A2');
});

test('searching by uuid goes back to the first page', function () {
    foreach (range(1, 25) as $numero) {
        CancelacionPendiente::factory()->create([
            'uuid' => sprintf('AAAAAAAA-0000-0000-0000-%012d', $numero),
            'ultima_deteccion_el' => now()->subDays(30)->addDays($numero),
        ]);
    }
    $this->actingAs(User::factory()->create());

    Livewire::test('cancelaciones::lista')
        ->call('gotoPage', 2)
        ->set('uuid', 'aaaaaaaa')
        ->assertSee('AAAAAAAA-0000-0000-0000-000000000025')
        ->assertDontSee('AAAAAAAA-0000-0000-0000-000000000005');
});

test('quitar filtros appears with a uuid search and clears it', function () {
    CancelacionPendiente::factory()->create(['uuid' => 'AE4B0CA8-8116-4699-8E20-77B6E9C507A2']);
    CancelacionPendiente::factory()->create(['uuid' => 'D92516E5-A881-423C-B40F-384008DFE03F']);
    $this->actingAs(User::factory()->create());

    Livewire::test('cancelaciones::lista')
        ->assertDontSee('Quitar filtros')
        ->set('uuid', 'AE4B0CA8')
        ->assertSee('Quitar filtros')
        ->call('limpiarFiltros')
        ->assertSet('uuid', '')
        ->assertSee(['AE4B0CA8-8116-4699-8E20-77B6E9C507A2', 'D92516E5-A881-423C-B40F-384008DFE03F']);
});

test('counts the cancelaciones por responder', function () {
    CancelacionPendiente::factory()->count(2)->create();
    CancelacionPendiente::factory()->enEstado(EstadoCancelacion::Retirada)->create();
    CancelacionPendiente::factory()->enEstado(EstadoCancelacion::EnvioIncierto)->create();

    expect(app(Cancelaciones::class)->porResponder())->toBe(2);
});

test('the sidebar shows how many are por responder and nothing when there are none', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertOk()->assertDontSee('data-flux-navlist-badge>', escape: false);

    CancelacionPendiente::factory()->count(3)->create();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder([route('cancelaciones.index'), 'data-flux-navlist-badge>3</span>'], escape: false);
});
