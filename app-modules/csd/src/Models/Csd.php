<?php

namespace DescargaSat\Csd\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use DescargaSat\Csd\Database\Factories\CsdFactory;
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
 * @property bool $activo
 * @property string $ruta_cer
 * @property string $ruta_key
 * @property string $contrasena
 * @property int|null $subido_por_id
 * @property int|null $estado_cambiado_por_id
 * @property CarbonImmutable|null $estado_cambiado_el
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $subidoPor
 * @property-read User|null $estadoCambiadoPor
 */
#[Table('csds')]
#[Fillable(['rfc', 'razon_social', 'numero_certificado', 'vigente_desde', 'vigente_hasta', 'ruta_cer', 'ruta_key', 'contrasena', 'subido_por_id'])]
#[Hidden(['ruta_cer', 'ruta_key', 'contrasena'])]
class Csd extends Model
{
    /** @use HasFactory<CsdFactory> */
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
            'activo' => 'boolean',
            'estado_cambiado_el' => 'datetime',
            'contrasena' => 'encrypted',
        ];
    }

    /**
     * Usuario que subió este CSD.
     *
     * @return BelongsTo<User, $this>
     */
    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por_id');
    }

    /**
     * Usuario que activó o desactivó este CSD por última vez.
     *
     * @return BelongsTo<User, $this>
     */
    public function estadoCambiadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estado_cambiado_por_id');
    }
}
