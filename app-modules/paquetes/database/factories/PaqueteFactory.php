<?php

namespace DescargaSat\Paquetes\Database\Factories;

use DescargaSat\Paquetes\Contracts\EstadoPaquete;
use DescargaSat\Paquetes\Models\Paquete;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paquete>
 */
class PaqueteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'solicitud_id' => 1,
            'id_paquete_sat' => strtoupper(fake()->uuid()).'_01',
            'estado' => EstadoPaquete::Pendiente,
        ];
    }

    /**
     * Paquete ya descargado y extraído.
     */
    public function extraido(): static
    {
        return $this->state(fn (): array => ['estado' => EstadoPaquete::Extraido]);
    }
}
