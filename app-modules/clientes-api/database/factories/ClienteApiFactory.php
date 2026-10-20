<?php

namespace DescargaSat\ClientesApi\Database\Factories;

use DescargaSat\ClientesApi\Models\ClienteApi;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClienteApi>
 */
class ClienteApiFactory extends Factory
{
    protected $model = ClienteApi::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => 'ca_'.Str::random(24),
            'nombre' => fake()->company(),
            'client_secret' => Hash::make(Str::random(40)),
            'activo' => true,
        ];
    }

    /**
     * Cliente API desactivado.
     */
    public function inactivo(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}
