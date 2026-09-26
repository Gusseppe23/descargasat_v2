<?php

use App\Models\User;
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

test('refreshes itself every 15 seconds only while the solicitud is in progress', function (EstadoSolicitud $estado, bool $refresca) {
    $solicitud = Solicitud::factory()->create(['estado' => $estado]);

    $this->actingAs(User::factory()->create());

    $detalle = Livewire::test('solicitudes::detalle', ['solicitud' => $solicitud]);

    $refresca ? $detalle->assertSeeHtml('wire:poll.15s') : $detalle->assertDontSeeHtml('wire:poll');
})->with([
    'en proceso' => [EstadoSolicitud::EnProceso, true],
    'terminada' => [EstadoSolicitud::Terminada, true],
    'descargada' => [EstadoSolicitud::Descargada, false],
    'vencida' => [EstadoSolicitud::Vencida, false],
]);
