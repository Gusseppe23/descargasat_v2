<?php

namespace DescargaSat\Cancelaciones\Database\Factories;

use DescargaSat\Cancelaciones\Models\RevisionDeCancelaciones;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RevisionDeCancelaciones>
 */
class RevisionDeCancelacionesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'csd_id' => 1,
            'codigo_sat' => '1101',
            'pendientes_reportadas' => 0,
            'problema' => null,
        ];
    }

    /**
     * Revisión que no pudo terminar por el problema indicado.
     */
    public function conProblema(string $problema): static
    {
        return $this->state(fn (): array => [
            'codigo_sat' => null,
            'pendientes_reportadas' => null,
            'problema' => $problema,
        ]);
    }
}
