<?php

namespace DescargaSat\Fiel\Database\Factories;

use App\Models\User;
use DescargaSat\Fiel\Models\Fiel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fiel>
 */
class FielFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rfc' => 'EKU9003173C9',
            'razon_social' => 'ESCUELA KEMPER URGATE',
            'numero_certificado' => fake()->unique()->numerify('3000100000050000####'),
            'vigente_desde' => now()->subYear(),
            'vigente_hasta' => now()->addYears(3),
            'ruta_cer' => 'fiel/'.fake()->uuid().'.cer',
            'ruta_key' => 'fiel/'.fake()->uuid().'.key',
            'contrasena' => '12345678a',
            'subida_por_id' => User::factory(),
        ];
    }

    /**
     * Fiel emitida a otro RFC.
     */
    public function deRfc(string $rfc): static
    {
        return $this->state(fn (): array => ['rfc' => $rfc]);
    }
}
