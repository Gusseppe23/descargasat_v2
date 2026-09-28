<?php

use App\Models\User;
use DescargaSat\Paquetes\Contracts\EstadoPaquete;
use DescargaSat\Paquetes\Contracts\Paquetes;
use DescargaSat\Paquetes\Models\Paquete;
use DescargaSat\Solicitudes\Contracts\EstadoSolicitud;
use DescargaSat\Solicitudes\Models\Solicitud;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $solicitud = Solicitud::factory()->create();

    $this->get(route('solicitudes.show', $solicitud))->assertRedirect(route('login'));
});

test('shows the solicitud with its last verification attempt and embeds its paquetes', function () {
    $solicitud = Solicitud::factory()->create([
        'estado' => EstadoSolicitud::EnProceso,
        'verificada_el' => '2026-04-02 10:35:00',
        'ultimo_problema' => 'No se pudo contactar al SAT (Error connecting).',
    ]);

    $this->actingAs(User::factory()->create());

    Livewire::test('solicitudes::detalle', ['solicitud' => $solicitud])
        ->assertSee([
            '01/03/2026 – 31/03/2026',
            'Emitidos',
            'XML',
            'En proceso',
            $solicitud->id_solicitud_sat,
            'Último intento 02/04/2026 10:35',
            'No se pudo contactar al SAT (Error connecting).',
        ])
        ->assertSeeLivewire('paquetes::de-solicitud');
});

test('refreshes itself every 15 seconds only while the solicitud or its paquetes are in progress', function (EstadoSolicitud $estado, array $estadosPaquetes, bool $refresca) {
    $solicitud = Solicitud::factory()->create(['estado' => $estado]);
    foreach ($estadosPaquetes as $estadoPaquete) {
        Paquete::factory()->create(['solicitud_id' => $solicitud->id, 'estado' => $estadoPaquete]);
    }

    $this->actingAs(User::factory()->create());

    $detalle = Livewire::test('solicitudes::detalle', ['solicitud' => $solicitud]);

    $refresca ? $detalle->assertSeeHtml('wire:poll.15s') : $detalle->assertDontSeeHtml('wire:poll');
})->with([
    'en proceso' => [EstadoSolicitud::EnProceso, [], true],
    'terminada con un paquete pendiente' => [EstadoSolicitud::Terminada, [EstadoPaquete::Extraido, EstadoPaquete::Pendiente], true],
    'terminada con un paquete descargado' => [EstadoSolicitud::Terminada, [EstadoPaquete::Descargado], true],
    'terminada con paquetes extraidos o fallidos' => [EstadoSolicitud::Terminada, [EstadoPaquete::Extraido, EstadoPaquete::Fallido], false],
    'descargada' => [EstadoSolicitud::Descargada, [EstadoPaquete::Extraido], false],
    'vencida' => [EstadoSolicitud::Vencida, [], false],
]);

test('starts refreshing again when one of its paquetes is retried', function () {
    $solicitud = Solicitud::factory()->create(['estado' => EstadoSolicitud::Terminada]);
    $paquete = Paquete::factory()->fallido()->create(['solicitud_id' => $solicitud->id]);

    $this->actingAs(User::factory()->create());

    $detalle = Livewire::test('solicitudes::detalle', ['solicitud' => $solicitud])
        ->assertDontSeeHtml('wire:poll');

    expect($detalle->effects['listeners'] ?? [])->toContain('paquete-reintentado');

    $paquete->update(['estado' => EstadoPaquete::Pendiente]);

    $detalle->dispatch('paquete-reintentado')
        ->assertSeeHtml('wire:poll.15s');
});

test('keeps refreshing when the download finishes while it is being drawn', function () {
    $solicitud = Solicitud::factory()->create(['estado' => EstadoSolicitud::Terminada]);
    $this->mock(Paquetes::class)
        ->shouldReceive('conDescargaEnCurso')
        ->andReturnUsing(function () use ($solicitud): array {
            Solicitud::whereKey($solicitud->id)->update(['estado' => EstadoSolicitud::Descargada]);

            return [];
        });

    $this->actingAs(User::factory()->create());

    Livewire::test('solicitudes::detalle', ['solicitud' => $solicitud])
        ->assertSeeHtml('wire:poll.15s');
});
