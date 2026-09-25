<?php

namespace DescargaSat\Fiel\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use DescargaSat\Fiel\Database\Factories\FielFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $rfc
 * @property string $razon_social
 * @property string $numero_certificado
 * @property CarbonImmutable $vigente_desde
 * @property CarbonImmutable $vigente_hasta
 * @property bool $activa
 * @property string $ruta_cer
 * @property string $ruta_key
 * @property string $contrasena
 * @property int|null $subida_por_id
 * @property int|null $estado_cambiado_por_id
 * @property CarbonImmutable|null $estado_cambiado_el
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $subidaPor
 * @property-read User|null $estadoCambiadoPor
 */
#[Table('fieles')]
#[Fillable(['rfc', 'razon_social', 'numero_certificado', 'vigente_desde', 'vigente_hasta', 'ruta_cer', 'ruta_key', 'contrasena', 'subida_por_id'])]
#[Hidden(['ruta_cer', 'ruta_key', 'contrasena'])]
class Fiel extends Model
{
    /** @use HasFactory<FielFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vigente_desde' => 'datetime',
            'vigente_hasta' => 'datetime',
            'activa' => 'boolean',
            'estado_cambiado_el' => 'datetime',
            'contrasena' => 'encrypted',
        ];
    }

    /**
     * Usuario que subió esta Fiel.
     *
     * @return BelongsTo<User, $this>
     */
    public function subidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subida_por_id');
    }

    /**
     * Usuario que activó o desactivó esta Fiel por última vez.
     *
     * @return BelongsTo<User, $this>
     */
    public function estadoCambiadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estado_cambiado_por_id');
    }
}
