<?php

namespace DescargaSat\Csd\Database\Factories;

use App\Models\User;
use DescargaSat\Csd\Models\Csd;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Csd>
 */
class CsdFactory extends Factory
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
            'ruta_cer' => 'csd/'.fake()->uuid().'.cer',
            'ruta_key' => 'csd/'.fake()->uuid().'.key',
            'contrasena' => '12345678a',
            'subido_por_id' => User::factory(),
        ];
    }

    /**
     * El CSD activo.
     */
    public function activo(): static
    {
        return $this->state(fn (): array => ['activo' => true]);
    }

    /**
     * CSD que ya dejó de estar vigente.
     */
    public function vencido(): static
    {
        return $this->state(fn (): array => [
            'vigente_desde' => now()->subYears(4),
            'vigente_hasta' => now()->subDay(),
        ]);
    }

    /**
     * CSD emitido a otro RFC.
     */
    public function deRfc(string $rfc): static
    {
        return $this->state(fn (): array => ['rfc' => $rfc]);
    }
}
