<?php

use App\Models\User;
use DescargaSat\Descargas\Jobs\DescargarPaquete;
use DescargaSat\Paquetes\Contracts\EstadoPaquete;
use DescargaSat\Paquetes\Models\Paquete;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

test('lists the paquetes of the solicitud with their state and the actions that apply', function () {
    $extraido = Paquete::factory()->extraido()->create(['solicitud_id' => 5, 'id_paquete_sat' => 'ABC_01']);
    $fallido = Paquete::factory()->fallido()->create(['solicitud_id' => 5, 'id_paquete_sat' => 'ABC_02']);
    $pendiente = Paquete::factory()->create(['solicitud_id' => 5, 'id_paquete_sat' => 'ABC_03']);
    Paquete::factory()->create(['solicitud_id' => 6, 'id_paquete_sat' => 'XYZ_01']);

    $this->actingAs(User::factory()->create());

    Livewire::test('paquetes::de-solicitud', ['solicitudId' => 5])
        ->assertSee(['ABC_01', 'Extraído', 'ABC_02', 'Fallido', 'No se pudo contactar al SAT (Error connecting).', 'ABC_03', 'Pendiente'])
        ->assertDontSee('XYZ_01')
        ->assertSeeHtml(route('paquetes.zip', $extraido))
        ->assertDontSeeHtml(route('paquetes.zip', $pendiente))
        ->assertSeeHtml('wire:click="reintentar('.$fallido->id.')"')
        ->assertDontSeeHtml('wire:click="reintentar('.$extraido->id.')"')
        ->assertDontSeeHtml('wire:click="reintentar('.$pendiente->id.')"');
});

test('retrying a fallido paquete puts it back as pendiente and queues only its download', function () {
    $fallido = Paquete::factory()->fallido()->create(['solicitud_id' => 5]);
    Paquete::factory()->create(['solicitud_id' => 5]);
    Queue::fake([DescargarPaquete::class]);

    $this->actingAs(User::factory()->create());

    Livewire::test('paquetes::de-solicitud', ['solicitudId' => 5])
        ->call('reintentar', $fallido->id)
        ->assertHasNoErrors();

    $fallido->refresh();
    expect($fallido->estado)->toBe(EstadoPaquete::Pendiente)
        ->and($fallido->ultimo_problema)->toBeNull();
    Queue::assertPushed(DescargarPaquete::class, 1);
    Queue::assertPushed(DescargarPaquete::class, fn (DescargarPaquete $job): bool => $job->paqueteId === $fallido->id);
});

test('rejects retrying a paquete that is not fallido', function () {
    $extraido = Paquete::factory()->extraido()->create(['solicitud_id' => 5]);
    Queue::fake([DescargarPaquete::class]);

    $this->actingAs(User::factory()->create());

    Livewire::test('paquetes::de-solicitud', ['solicitudId' => 5])
        ->call('reintentar', $extraido->id)
        ->assertHasErrors(['paquete' => 'Solo se pueden reintentar Paquetes fallidos.']);

    expect($extraido->fresh()->estado)->toBe(EstadoPaquete::Extraido);
    Queue::assertNothingPushed();
});

test('refreshes itself every 15 seconds only while a paquete is being downloaded', function (EstadoPaquete $estado, bool $refresca) {
    Paquete::factory()->create(['solicitud_id' => 5, 'estado' => $estado]);
    Paquete::factory()->extraido()->create(['solicitud_id' => 5]);

    $this->actingAs(User::factory()->create());

    $componente = Livewire::test('paquetes::de-solicitud', ['solicitudId' => 5]);

    $refresca ? $componente->assertSeeHtml('wire:poll.15s') : $componente->assertDontSeeHtml('wire:poll');
})->with([
    'pendiente' => [EstadoPaquete::Pendiente, true],
    'descargado' => [EstadoPaquete::Descargado, true],
    'extraido' => [EstadoPaquete::Extraido, false],
    'fallido' => [EstadoPaquete::Fallido, false],
]);
