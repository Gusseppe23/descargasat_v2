<?php

namespace DescargaSat\Cancelaciones\Database\Factories;

use DescargaSat\Cancelaciones\Contracts\EstadoCancelacion;
use DescargaSat\Cancelaciones\Models\CancelacionPendiente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CancelacionPendiente>
 */
class CancelacionPendienteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => strtoupper(fake()->unique()->uuid()),
            'estado' => EstadoCancelacion::PorResponder,
            'primera_deteccion_el' => now()->subDay(),
            'ultima_deteccion_el' => now()->subDay(),
        ];
    }

    /**
     * Cancelación pendiente en el estado indicado.
     */
    public function enEstado(EstadoCancelacion $estado): static
    {
        return $this->state(fn (): array => ['estado' => $estado]);
    }
}
