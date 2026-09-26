<?php

namespace DescargaSat\Solicitudes\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use DescargaSat\SatAutenticacion\Contracts\Servicio;
use DescargaSat\Solicitudes\Contracts\EstadoSolicitud;
use DescargaSat\Solicitudes\Database\Factories\SolicitudFactory;
use DescargaSat\Solicitudes\Enums\EstadoComprobante;
use DescargaSat\Solicitudes\Enums\TipoComprobante;
use DescargaSat\Solicitudes\Enums\TipoDescarga;
use DescargaSat\Solicitudes\Enums\TipoSolicitud;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $fiel_id
 * @property int|null $presentada_por_id
 * @property Servicio $servicio
 * @property CarbonImmutable $fecha_inicio
 * @property CarbonImmutable $fecha_fin
 * @property TipoDescarga $tipo_descarga
 * @property TipoSolicitud $tipo_solicitud
 * @property TipoComprobante|null $tipo_comprobante
 * @property EstadoComprobante $estado_comprobante
 * @property string|null $id_solicitud_sat
 * @property EstadoSolicitud $estado
 * @property int|null $codigo_sat
 * @property string|null $mensaje_sat
 * @property CarbonImmutable|null $verificada_el
 * @property string|null $ultimo_problema
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $presentadaPor
 */
#[Table('solicitudes')]
#[Fillable([
    'fiel_id', 'presentada_por_id', 'servicio', 'fecha_inicio', 'fecha_fin', 'tipo_descarga', 'tipo_solicitud',
    'tipo_comprobante', 'estado_comprobante', 'id_solicitud_sat', 'estado', 'codigo_sat', 'mensaje_sat', 'verificada_el', 'ultimo_problema',
])]
class Solicitud extends Model
{
    /** @use HasFactory<SolicitudFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'servicio' => Servicio::class,
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'tipo_descarga' => TipoDescarga::class,
            'tipo_solicitud' => TipoSolicitud::class,
            'tipo_comprobante' => TipoComprobante::class,
            'estado_comprobante' => EstadoComprobante::class,
            'estado' => EstadoSolicitud::class,
            'codigo_sat' => 'integer',
            'verificada_el' => 'datetime',
        ];
    }

    /**
     * Usuario que presentó la Solicitud.
     *
     * @return BelongsTo<User, $this>
     */
    public function presentadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presentada_por_id');
    }
}
