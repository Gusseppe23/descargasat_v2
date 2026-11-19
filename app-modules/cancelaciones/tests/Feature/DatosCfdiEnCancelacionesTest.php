<?php

use App\Models\User;
use DescargaSat\Cancelaciones\Models\CancelacionPendiente;
use DescargaSat\Metadata\Models\MetadataDeCfdi;
use Livewire\Livewire;

const CANCELACION_CON_DATOS = 'AE4B0CA8-8116-4699-8E20-77B6E9C507A2';
const CANCELACION_SIN_DATOS = 'D92516E5-A881-423C-B40F-384008DFE03F';

/**
 * Una Cancelación pendiente con Metadata de CFDI y otra sin ella, la primera detectada más recientemente.
 */
function cancelacionesConYSinDatos(): CancelacionPendiente
{
    $conDatos = CancelacionPendiente::factory()->create(['uuid' => CANCELACION_CON_DATOS, 'ultima_deteccion_el' => now()]);
    CancelacionPendiente::factory()->create(['uuid' => CANCELACION_SIN_DATOS, 'ultima_deteccion_el' => now()->subDay()]);

    MetadataDeCfdi::create([
        'uuid' => CANCELACION_CON_DATOS,
        'rfc_emisor' => 'EKU9003173C9',
        'nombre_emisor' => 'ESCUELA KEMPER URGATE',
        'rfc_receptor' => 'XAXX010101000',
        'fecha_emision' => '2026-11-01 10:15:30',
        'monto' => '1250.00',
        'efecto' => 'I',
        'estatus' => '1',
        'paquete_id' => 1,
    ]);

    return $conDatos;
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('the list shows the emisor, importe and fecha de emision, and a dash without metadata', function () {
    cancelacionesConYSinDatos();

    Livewire::test('cancelaciones::lista')
        ->assertSeeInOrder(['RFC emisor', 'Nombre emisor', 'Importe', 'Fecha de emisión'])
        ->assertSeeInOrder([CANCELACION_CON_DATOS, 'EKU9003173C9', 'ESCUELA KEMPER URGATE', '$1,250.00', '01/11/2026', CANCELACION_SIN_DATOS, '—', '—', '—', '—']);
});

test('the detail shows the emisor, importe and fecha de emision', function () {
    $conDatos = cancelacionesConYSinDatos();

    Livewire::test('cancelaciones::detalle', ['cancelacion' => $conDatos])
        ->assertSeeInOrder(['RFC emisor', 'EKU9003173C9', 'Nombre emisor', 'ESCUELA KEMPER URGATE', 'Importe', '$1,250.00', 'Fecha de emisión', '01/11/2026']);
});

test('the detail shows a dash for each dato without metadata', function () {
    cancelacionesConYSinDatos();

    Livewire::test('cancelaciones::detalle', ['cancelacion' => CancelacionPendiente::where('uuid', CANCELACION_SIN_DATOS)->sole()])
        ->assertSeeInOrder(['RFC emisor', '—', 'Nombre emisor', '—', 'Importe', '—', 'Fecha de emisión', '—']);
});
