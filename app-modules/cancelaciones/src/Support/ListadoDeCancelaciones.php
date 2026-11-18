<?php

namespace DescargaSat\Cancelaciones\Support;

use Carbon\CarbonImmutable;
use DescargaSat\Cancelaciones\Contracts\EstadoCancelacion;
use DescargaSat\Cancelaciones\Models\CancelacionPendiente;
use Illuminate\Database\Eloquent\Builder;

/**
 * Consulta de la lista de Cancelaciones pendientes con los filtros de la pantalla.
 * Los valores inválidos se ignoran.
 */
class ListadoDeCancelaciones
{
    public function __construct(
        private string $estado = '',
        private string $desde = '',
        private string $hasta = '',
    ) {}

    /**
     * Las Cancelaciones pendientes filtradas, de la detectada más recientemente a la más antigua.
     *
     * @return Builder<CancelacionPendiente>
     */
    public function consulta(): Builder
    {
        $estado = EstadoCancelacion::tryFrom($this->estado);
        $desde = $this->fecha($this->desde);
        $hasta = $this->fecha($this->hasta);

        return CancelacionPendiente::query()
            ->when($estado, fn (Builder $consulta) => $consulta->where('estado', $estado))
            ->when($desde, fn (Builder $consulta) => $consulta->where('primera_deteccion_el', '>=', $desde?->startOfDay()))
            ->when($hasta, fn (Builder $consulta) => $consulta->where('primera_deteccion_el', '<=', $hasta?->endOfDay()))
            ->orderByDesc('ultima_deteccion_el')
            ->orderByDesc('id');
    }

    private function fecha(string $valor): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $valor) ?: null;
    }
}
