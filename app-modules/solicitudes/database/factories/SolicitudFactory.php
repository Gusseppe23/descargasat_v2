<?php

namespace DescargaSat\Solicitudes\Database\Factories;

use App\Models\User;
use DescargaSat\SatAutenticacion\Contracts\Servicio;
use DescargaSat\Solicitudes\Enums\EstadoComprobante;
use DescargaSat\Solicitudes\Enums\EstadoSolicitud;
use DescargaSat\Solicitudes\Enums\TipoDescarga;
use DescargaSat\Solicitudes\Enums\TipoSolicitud;
use DescargaSat\Solicitudes\Models\Solicitud;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Solicitud>
 */
class SolicitudFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fiel_id' => 1,
            'presentada_por_id' => User::factory(),
            'servicio' => Servicio::Cfdi,
            'fecha_inicio' => '2026-03-01',
            'fecha_fin' => '2026-03-31',
            'tipo_descarga' => TipoDescarga::Emitidos,
            'tipo_solicitud' => TipoSolicitud::Xml,
            'tipo_comprobante' => null,
            'estado_comprobante' => EstadoComprobante::Todos,
            'id_solicitud_sat' => fake()->uuid(),
            'estado' => EstadoSolicitud::Aceptada,
            'codigo_sat' => 5000,
            'mensaje_sat' => 'Solicitud Aceptada',
        ];
    }

    /**
     * Solicitud que el SAT no aceptó al presentarla.
     */
    public function rechazada(): static
    {
        return $this->state(fn (): array => [
            'id_solicitud_sat' => null,
            'estado' => EstadoSolicitud::Rechazada,
            'codigo_sat' => 5002,
            'mensaje_sat' => 'Se han agotado las solicitudes de por vida',
        ]);
    }
}
