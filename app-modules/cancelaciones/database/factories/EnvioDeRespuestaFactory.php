<?php

namespace DescargaSat\Cancelaciones\Database\Factories;

use App\Models\User;
use DescargaSat\Cancelaciones\Contracts\EstadoCancelacion;
use DescargaSat\Cancelaciones\Contracts\ResultadoDeEnvio;
use DescargaSat\Cancelaciones\Models\CancelacionPendiente;
use DescargaSat\Cancelaciones\Models\EnvioDeRespuesta;
use DescargaSat\SatAutenticacion\Contracts\Respuesta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnvioDeRespuesta>
 */
class EnvioDeRespuestaFactory extends Factory
{
    /**
     * Envío en curso: la Cancelación pendiente está Enviando.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cancelacion_pendiente_id' => CancelacionPendiente::factory()->enEstado(EstadoCancelacion::Enviando),
            'respuesta' => Respuesta::Rechazo,
            'usuario_id' => User::factory(),
            'csd_id' => 1,
        ];
    }

    /**
     * Envío que terminó sin Acuse de respuesta.
     */
    public function incierto(): static
    {
        return $this->state(fn (): array => [
            'resultado' => ResultadoDeEnvio::Incierto,
            'problema' => 'No se pudo contactar al SAT.',
            'terminado_el' => now(),
        ]);
    }
}
