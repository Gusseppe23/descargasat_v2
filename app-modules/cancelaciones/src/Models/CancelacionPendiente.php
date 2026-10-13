<?php

namespace DescargaSat\Cancelaciones\Models;

use Carbon\CarbonImmutable;
use DescargaSat\Cancelaciones\Contracts\EstadoCancelacion;
use DescargaSat\Cancelaciones\Database\Factories\CancelacionPendienteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $uuid
 * @property EstadoCancelacion $estado
 * @property CarbonImmutable $primera_deteccion_el
 * @property CarbonImmutable $ultima_deteccion_el
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Table('cancelaciones_pendientes')]
#[Fillable(['uuid', 'estado', 'primera_deteccion_el', 'ultima_deteccion_el'])]
class CancelacionPendiente extends Model
{
    /** @use HasFactory<CancelacionPendienteFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoCancelacion::class,
            'primera_deteccion_el' => 'datetime',
            'ultima_deteccion_el' => 'datetime',
        ];
    }
}
