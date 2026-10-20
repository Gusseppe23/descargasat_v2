<?php

namespace DescargaSat\ClientesApi\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use DescargaSat\ClientesApi\Database\Factories\ClienteApiFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * @property int $id
 * @property string $client_id
 * @property string $nombre
 * @property string $client_secret
 * @property bool $activo
 * @property int|null $creado_por_id
 * @property CarbonImmutable|null $ultimo_uso_el
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $creadoPor
 */
#[Table('clientes_api')]
#[Fillable(['client_id', 'nombre', 'client_secret', 'activo', 'creado_por_id'])]
#[Hidden(['client_secret'])]
class ClienteApi extends Model implements AuthenticatableContract, JWTSubject
{
    use Authenticatable;

    /** @use HasFactory<ClienteApiFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'ultimo_uso_el' => 'datetime',
        ];
    }

    /**
     * El guard "api" compara contra esta columna, no contra "password".
     */
    public function getAuthPasswordName(): string
    {
        return 'client_secret';
    }

    /**
     * Usuario que dio de alta este Cliente API.
     *
     * @return BelongsTo<User, $this>
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return ['client_id' => $this->client_id];
    }
}
