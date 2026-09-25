<?php

namespace DescargaSat\Fiel\Models;

use App\Models\User;
use DescargaSat\Fiel\Database\Factories\FielFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $rfc
 * @property string $razon_social
 * @property string $numero_certificado
 * @property Carbon $vigente_desde
 * @property Carbon $vigente_hasta
 * @property bool $activa
 * @property string $ruta_cer
 * @property string $ruta_key
 * @property string $contrasena
 * @property int|null $subida_por_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $subidaPor
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
}
