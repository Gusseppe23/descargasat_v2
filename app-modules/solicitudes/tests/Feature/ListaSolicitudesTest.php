<?php

use App\Models\User;
use DescargaSat\SatAutenticacion\Contracts\Servicio;
use DescargaSat\Solicitudes\Enums\EstadoComprobante;
use DescargaSat\Solicitudes\Enums\TipoComprobante;
use DescargaSat\Solicitudes\Enums\TipoDescarga;
use DescargaSat\Solicitudes\Enums\TipoSolicitud;
use DescargaSat\Solicitudes\Models\Solicitud;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get('/solicitudes')->assertRedirect(route('login'));
});

test('lists the solicitudes with their parameters, state and who presented them', function () {
    $aceptada = Solicitud::factory()
        ->for(User::factory()->create(['name' => 'Ana López']), 'presentadaPor')
        ->create([
            'servicio' => Servicio::Cfdi,
            'fecha_inicio' => '2026-03-01',
            'fecha_fin' => '2026-03-31',
            'tipo_descarga' => TipoDescarga::Recibidos,
            'tipo_solicitud' => TipoSolicitud::Metadata,
            'tipo_comprobante' => TipoComprobante::Nomina,
            'estado_comprobante' => EstadoComprobante::Cancelados,
            'created_at' => '2026-04-02 09:30:00',
        ]);
    Solicitud::factory()->rechazada()->create();

    $this->actingAs(User::factory()->create());

    Livewire::test('solicitudes::lista')
        ->assertSeeHtml('id="solicitud-'.$aceptada->id.'"')
        ->assertSee([
            '02/04/2026 09:30',
            'CFDI',
            '01/03/2026 – 31/03/2026',
            'Recibidos',
            'Metadata',
            'Nómina',
            'Cancelados',
            'Aceptada',
            'Ana López',
            'Rechazada',
            'Se han agotado las solicitudes de por vida',
        ]);
});
