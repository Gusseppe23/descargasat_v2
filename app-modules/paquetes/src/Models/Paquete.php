<?php

namespace DescargaSat\Paquetes\Models;

use Carbon\CarbonImmutable;
use DescargaSat\Paquetes\Contracts\EstadoPaquete;
use DescargaSat\Paquetes\Database\Factories\PaqueteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $solicitud_id
 * @property string $id_paquete_sat
 * @property EstadoPaquete $estado
 * @property string|null $ultimo_problema
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['solicitud_id', 'id_paquete_sat', 'estado', 'ultimo_problema'])]
class Paquete extends Model
{
    /** @use HasFactory<PaqueteFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoPaquete::class,
        ];
    }
}
