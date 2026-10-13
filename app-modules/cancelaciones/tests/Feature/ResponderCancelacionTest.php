<?php

use App\Models\User;
use DescargaSat\Cancelaciones\Contracts\EstadoCancelacion;
use DescargaSat\Cancelaciones\Jobs\EnviarRespuesta;
use DescargaSat\Cancelaciones\Models\CancelacionPendiente;
use DescargaSat\Cancelaciones\Models\EnvioDeRespuesta;
use DescargaSat\SatAutenticacion\Contracts\Respuesta;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\CsdsFalsos;

test('guests are redirected to the login page', function () {
    $cancelacion = CancelacionPendiente::factory()->create();

    $this->get(route('cancelaciones.detalle', $cancelacion))->assertRedirect(route('login'));
});

test('the confirmation shows the uuid and warns that it cannot be undone', function () {
    $cancelacion = CancelacionPendiente::factory()->create(['uuid' => 'AE4B0CA8-8116-4699-8E20-77B6E9C507A2']);
    $this->actingAs(User::factory()->create());

    Livewire::test('cancelaciones::detalle', ['cancelacion' => $cancelacion])
        ->call('elegir', 'Aceptacion')
        ->assertSet('respuesta', 'Aceptacion')
        ->assertSee(['AE4B0CA8-8116-4699-8E20-77B6E9C507A2', 'La Respuesta de cancelación se envía al SAT y no se puede deshacer.', 'Escribe el UUID para confirmar']);
});

test('nothing is queued unless the uuid is typed', function (string $uuidEscrito, string $error) {
    Queue::fake();
    CsdsFalsos::usar();
    $cancelacion = CancelacionPendiente::factory()->create(['uuid' => 'AE4B0CA8-8116-4699-8E20-77B6E9C507A2']);
    $this->actingAs(User::factory()->create());

    Livewire::test('cancelaciones::detalle', ['cancelacion' => $cancelacion])
        ->call('elegir', 'Rechazo')
        ->set('uuidEscrito', $uuidEscrito)
        ->call('responder')
        ->assertHasErrors(['uuidEscrito' => $error]);

    Queue::assertNothingPushed();
    expect($cancelacion->fresh()->estado)->toBe(EstadoCancelacion::PorResponder)
        ->and(EnvioDeRespuesta::count())->toBe(0);
})->with([
    'empty' => ['', 'required'],
    'another uuid' => ['D92516E5-A881-423C-B40F-384008DFE03F', 'Escribe el UUID tal como aparece para confirmar.'],
]);

test('confirming queues the envio and records who answered what', function (Respuesta $respuesta) {
    Queue::fake();
    CsdsFalsos::usar();
    $cancelacion = CancelacionPendiente::factory()->create(['uuid' => 'AE4B0CA8-8116-4699-8E20-77B6E9C507A2']);
    $usuario = User::factory()->create();
    $this->actingAs($usuario);

    Livewire::test('cancelaciones::detalle', ['cancelacion' => $cancelacion])
        ->call('elegir', $respuesta->value)
        ->set('uuidEscrito', 'ae4b0ca8-8116-4699-8e20-77b6e9c507a2')
        ->call('responder')
        ->assertHasNoErrors();

    $envio = EnvioDeRespuesta::sole();

    expect($cancelacion->fresh()->estado)->toBe(EstadoCancelacion::Enviando)
        ->and($envio->only('cancelacion_pendiente_id', 'usuario_id', 'csd_id', 'resultado'))->toBe([
            'cancelacion_pendiente_id' => $cancelacion->id,
            'usuario_id' => $usuario->id,
            'csd_id' => 1,
            'resultado' => null,
        ])
        ->and($envio->respuesta)->toBe($respuesta)
        ->and($envio->created_at)->not->toBeNull();
    Queue::assertPushed(EnviarRespuesta::class, fn (EnviarRespuesta $job): bool => $job->envioId === $envio->id && $job->connection === 'database');
})->with([Respuesta::Aceptacion, Respuesta::Rechazo]);

test('two clicks in a row queue a single envio', function () {
    Queue::fake();
    CsdsFalsos::usar();
    $cancelacion = CancelacionPendiente::factory()->create(['uuid' => 'AE4B0CA8-8116-4699-8E20-77B6E9C507A2']);
    $this->actingAs(User::factory()->create());

    $pantalla = Livewire::test('cancelaciones::detalle', ['cancelacion' => $cancelacion])
        ->set('respuesta', 'Rechazo')
        ->set('uuidEscrito', $cancelacion->uuid)
        ->call('responder')
        ->set('respuesta', 'Rechazo')
        ->set('uuidEscrito', $cancelacion->uuid)
        ->call('responder');

    $pantalla->assertHasErrors(['respuesta' => 'Ya se está enviando una Respuesta de cancelación.']);
    Queue::assertPushed(EnviarRespuesta::class, 1);
    expect(EnvioDeRespuesta::count())->toBe(1);
});

test('cannot answer without an active csd', function () {
    Queue::fake();
    CsdsFalsos::usar(hayActivo: false);
    $cancelacion = CancelacionPendiente::factory()->create();
    $this->actingAs(User::factory()->create());

    Livewire::test('cancelaciones::detalle', ['cancelacion' => $cancelacion])
        ->set('respuesta', 'Aceptacion')
        ->set('uuidEscrito', $cancelacion->uuid)
        ->call('responder')
        ->assertHasErrors(['respuesta' => 'No hay un CSD activo. Activa uno en la pantalla CSD.']);

    Queue::assertNothingPushed();
});

test('cannot answer a cancelacion that already has a final state or was retirada', function (EstadoCancelacion $estado) {
    Queue::fake();
    CsdsFalsos::usar();
    $cancelacion = CancelacionPendiente::factory()->enEstado($estado)->create();
    $this->actingAs(User::factory()->create());

    Livewire::test('cancelaciones::detalle', ['cancelacion' => $cancelacion])
        ->assertDontSee('Aceptar la cancelación')
        ->set('respuesta', 'Aceptacion')
        ->set('uuidEscrito', $cancelacion->uuid)
        ->call('responder')
        ->assertHasErrors('respuesta');

    Queue::assertNothingPushed();
    expect($cancelacion->fresh()->estado)->toBe($estado);
})->with([
    EstadoCancelacion::AceptacionEnviada,
    EstadoCancelacion::RechazoEnviado,
    EstadoCancelacion::Retirada,
]);

test('an envio incierto can only be sent again after a later revision still shows it', function () {
    Queue::fake();
    CsdsFalsos::usar();
    $this->freezeTime();
    $envio = EnvioDeRespuesta::factory()->incierto()->create();
    $cancelacion = $envio->cancelacion;
    $cancelacion->update(['estado' => EstadoCancelacion::EnvioIncierto, 'ultima_deteccion_el' => now()->subMinute()]);
    $this->actingAs(User::factory()->create());

    Livewire::test('cancelaciones::detalle', ['cancelacion' => $cancelacion])
        ->assertSee('Se podrá volver a enviar cuando una Revisión de cancelaciones posterior al envío la siga mostrando pendiente.')
        ->set('respuesta', 'Rechazo')
        ->set('uuidEscrito', $cancelacion->uuid)
        ->call('responder')
        ->assertHasErrors(['respuesta' => 'Solo se puede volver a enviar cuando una Revisión de cancelaciones posterior al envío la siga mostrando pendiente.']);

    Queue::assertNothingPushed();

    $this->travel(30)->minutes();
    $cancelacion->update(['ultima_deteccion_el' => now()]);

    Livewire::test('cancelaciones::detalle', ['cancelacion' => $cancelacion->fresh()])
        ->set('respuesta', 'Rechazo')
        ->set('uuidEscrito', $cancelacion->uuid)
        ->call('responder')
        ->assertHasNoErrors();

    Queue::assertPushed(EnviarRespuesta::class, 1);
    expect($cancelacion->fresh()->estado)->toBe(EstadoCancelacion::Enviando);
});

test('the list links each cancelacion to its detail', function () {
    $cancelacion = CancelacionPendiente::factory()->create();
    $this->actingAs(User::factory()->create());

    Livewire::test('cancelaciones::lista')
        ->assertSee(route('cancelaciones.detalle', $cancelacion));
});
