<?php

namespace DescargaSat\Metadata\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Metadata de CFDI de un UUID, tal como la entregó el SAT en el último Paquete de Metadata que lo incluyó.
 *
 * @property int $id
 * @property string $uuid
 * @property string $rfc_emisor
 * @property string|null $nombre_emisor
 * @property string $rfc_receptor
 * @property string|null $nombre_receptor
 * @property string|null $rfc_pac
 * @property CarbonImmutable $fecha_emision
 * @property CarbonImmutable|null $fecha_certificacion_sat
 * @property string $monto
 * @property string $efecto
 * @property string $estatus
 * @property CarbonImmutable|null $fecha_cancelacion
 * @property int $paquete_id
 */
class MetadataDeCfdi extends Model
{
    protected $table = 'metadata_cfdi';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_emision' => 'immutable_datetime',
            'fecha_certificacion_sat' => 'immutable_datetime',
            'fecha_cancelacion' => 'immutable_datetime',
            'monto' => 'decimal:2',
        ];
    }
}
