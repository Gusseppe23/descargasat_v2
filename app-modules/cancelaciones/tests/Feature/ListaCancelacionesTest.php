<?php

use App\Models\User;
use DescargaSat\Cancelaciones\Contracts\EstadoCancelacion;
use DescargaSat\Cancelaciones\Models\CancelacionPendiente;
use DescargaSat\Cancelaciones\Models\RevisionDeCancelaciones;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get('/cancelaciones')->assertRedirect(route('login'));
});

test('lists the cancelaciones pendientes with their state', function () {
    CancelacionPendiente::factory()->create(['uuid' => 'AE4B0CA8-8116-4699-8E20-77B6E9C507A2']);
    CancelacionPendiente::factory()->enEstado(EstadoCancelacion::Retirada)->create(['uuid' => 'D92516E5-A881-423C-B40F-384008DFE03F']);

    $this->actingAs(User::factory()->create());

    Livewire::test('cancelaciones::lista')
        ->assertSee(['AE4B0CA8-8116-4699-8E20-77B6E9C507A2', 'Por responder', 'D92516E5-A881-423C-B40F-384008DFE03F', 'Retirada']);
});

test('shows 20 cancelaciones per page and the rest on the next page', function () {
    foreach (range(1, 25) as $numero) {
        CancelacionPendiente::factory()->create([
            'uuid' => sprintf('AAAAAAAA-0000-0000-0000-%012d', $numero),
            'ultima_deteccion_el' => now()->subDays(30)->addDays($numero),
        ]);
    }
    $this->actingAs(User::factory()->create());

    Livewire::test('cancelaciones::lista')
        ->assertSee(['AAAAAAAA-0000-0000-0000-000000000025', 'AAAAAAAA-0000-0000-0000-000000000006'])
        ->assertDontSee('AAAAAAAA-0000-0000-0000-000000000005')
        ->call('gotoPage', 2)
        ->assertSee(['AAAAAAAA-0000-0000-0000-000000000005', 'AAAAAAAA-0000-0000-0000-000000000001'])
        ->assertDontSee('AAAAAAAA-0000-0000-0000-000000000006');
});

test('changing or clearing a filter goes back to the first page', function () {
    foreach (range(1, 25) as $numero) {
        CancelacionPendiente::factory()->create([
            'uuid' => sprintf('AAAAAAAA-0000-0000-0000-%012d', $numero),
            'ultima_deteccion_el' => now()->subDays(30)->addDays($numero),
        ]);
    }
    $this->actingAs(User::factory()->create());

    Livewire::test('cancelaciones::lista')
        ->call('gotoPage', 2)
        ->set('estado', EstadoCancelacion::PorResponder->value)
        ->assertSee('AAAAAAAA-0000-0000-0000-000000000025')
        ->assertDontSee('AAAAAAAA-0000-0000-0000-000000000005')
        ->call('gotoPage', 2)
        ->call('limpiarFiltros')
        ->assertSee('AAAAAAAA-0000-0000-0000-000000000025')
        ->assertDontSee('AAAAAAAA-0000-0000-0000-000000000005');
});

test('says when there are no cancelaciones pendientes and no revision yet', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('cancelaciones::lista')
        ->assertSee(['No hay Cancelaciones pendientes.', 'Todavía no se ha hecho ninguna Revisión de cancelaciones.']);
});

test('shows the problem of the last revision', function () {
    RevisionDeCancelaciones::factory()->conProblema('Un problema anterior.')->create();
    RevisionDeCancelaciones::factory()->conProblema('No hay un CSD activo. Activa uno en la pantalla CSD.')->create();

    $this->actingAs(User::factory()->create());

    Livewire::test('cancelaciones::lista')
        ->assertSee('No hay un CSD activo. Activa uno en la pantalla CSD.')
        ->assertDontSee('Un problema anterior.');
});

test('the sidebar links to the cancelaciones page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('cancelaciones.index'))
        ->assertOk()
        ->assertSee('href="'.route('cancelaciones.index').'"', escape: false);
});
