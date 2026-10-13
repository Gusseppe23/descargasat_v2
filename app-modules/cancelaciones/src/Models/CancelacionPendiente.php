<?php

namespace DescargaSat\Cancelaciones\Models;

use Carbon\CarbonImmutable;
use DescargaSat\Cancelaciones\Contracts\EstadoCancelacion;
use DescargaSat\Cancelaciones\Database\Factories\CancelacionPendienteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property EstadoCancelacion $estado
 * @property CarbonImmutable $primera_deteccion_el
 * @property CarbonImmutable $ultima_deteccion_el
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, EnvioDeRespuesta> $envios
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

    /**
     * @return HasMany<EnvioDeRespuesta, $this>
     */
    public function envios(): HasMany
    {
        return $this->hasMany(EnvioDeRespuesta::class, 'cancelacion_pendiente_id');
    }

    /**
     * Si se le puede enviar una Respuesta de cancelación (ADR-0006): siempre que esté Por responder, y desde
     * Envío incierto solo si una Revisión de cancelaciones posterior al último envío la sigue mostrando.
     */
    public function puedeResponderse(): bool
    {
        if ($this->estado === EstadoCancelacion::PorResponder) {
            return true;
        }

        if ($this->estado !== EstadoCancelacion::EnvioIncierto) {
            return false;
        }

        $ultimoEnvio = $this->envios()->latest('id')->first();

        if ($ultimoEnvio === null) {
            return false;
        }

        $terminoEl = $ultimoEnvio->terminado_el ?? $ultimoEnvio->created_at;

        return $terminoEl !== null && $this->ultima_deteccion_el->greaterThan($terminoEl);
    }
}
