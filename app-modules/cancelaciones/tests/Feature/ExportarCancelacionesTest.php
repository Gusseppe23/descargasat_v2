<?php

use App\Models\User;
use DescargaSat\Cancelaciones\Contracts\EstadoCancelacion;
use DescargaSat\Cancelaciones\Models\CancelacionPendiente;
use Livewire\Livewire;

/**
 * El CSV esperado: BOM de UTF-8, encabezados y las filas dadas.
 */
function csvDeCancelaciones(string ...$filas): string
{
    return "\u{FEFF}".'UUID,Estado,"Detectada por primera vez","Última detección"'."\n".implode('', $filas);
}

test('exporting an empty list downloads only the headers', function () {
    $this->travelTo('2026-11-18 14:05:00');
    $this->actingAs(User::factory()->create());

    Livewire::test('cancelaciones::lista')
        ->call('exportar')
        ->assertFileDownloaded('cancelaciones-20261118-1405.csv', csvDeCancelaciones());
});

test('exports every filtered row, not only the visible page', function () {
    foreach (range(1, 22) as $dia) {
        CancelacionPendiente::factory()->enEstado(EstadoCancelacion::AceptacionEnviada)->create([
            'uuid' => sprintf('AAAAAAAA-0000-0000-0000-%012d', $dia),
            'primera_deteccion_el' => '2026-10-01 09:00:00',
            'ultima_deteccion_el' => sprintf('2026-10-%02d 08:30:15', $dia),
        ]);
    }
    CancelacionPendiente::factory()->enEstado(EstadoCancelacion::Retirada)->create(['uuid' => 'AAAAAAAA-0000-0000-0000-000000000099', 'primera_deteccion_el' => '2026-10-01 09:00:00']);
    CancelacionPendiente::factory()->enEstado(EstadoCancelacion::AceptacionEnviada)->create(['uuid' => 'AAAAAAAA-0000-0000-0000-000000000098', 'primera_deteccion_el' => '2026-09-30 23:59:59']);
    CancelacionPendiente::factory()->enEstado(EstadoCancelacion::AceptacionEnviada)->create(['uuid' => 'CCCCCCCC-0000-0000-0000-000000000001', 'primera_deteccion_el' => '2026-10-01 09:00:00']);
    $this->travelTo('2026-11-18 14:05:00');
    $this->actingAs(User::factory()->create());

    $filas = array_map(
        fn (int $dia): string => sprintf('AAAAAAAA-0000-0000-0000-%012d,"Aceptación enviada","2026-10-01 09:00:00","2026-10-%02d 08:30:15"', $dia, $dia)."\n",
        range(22, 1),
    );

    Livewire::test('cancelaciones::lista')
        ->set('estado', EstadoCancelacion::AceptacionEnviada->value)
        ->set('desde', '2026-10-01')
        ->set('hasta', '2026-10-01')
        ->set('uuid', 'aaaaaaaa')
        ->call('exportar')
        ->assertFileDownloaded('cancelaciones-20261118-1405.csv', csvDeCancelaciones(...$filas));
});
