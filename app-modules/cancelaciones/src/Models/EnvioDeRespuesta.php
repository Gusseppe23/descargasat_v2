<?php

namespace DescargaSat\Cancelaciones\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use DescargaSat\Cancelaciones\Contracts\ResultadoDeEnvio;
use DescargaSat\Cancelaciones\Database\Factories\EnvioDeRespuestaFactory;
use DescargaSat\SatAutenticacion\Contracts\Respuesta;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un Envío de respuesta: quién respondió, qué, con qué CSD y cómo terminó, con su evidencia en disco.
 *
 * @property int $id
 * @property int $cancelacion_pendiente_id
 * @property Respuesta $respuesta
 * @property int|null $usuario_id
 * @property int $csd_id
 * @property ResultadoDeEnvio|null $resultado
 * @property string|null $codigo_sat
 * @property string|null $estatus_uuid
 * @property string|null $problema
 * @property string|null $ruta_enviado
 * @property string|null $ruta_recibido
 * @property CarbonImmutable|null $terminado_el
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read CancelacionPendiente $cancelacion
 * @property-read User|null $usuario
 */
#[Table('envios_de_respuesta')]
#[Fillable(['cancelacion_pendiente_id', 'respuesta', 'usuario_id', 'csd_id', 'resultado', 'codigo_sat', 'estatus_uuid', 'problema', 'ruta_enviado', 'ruta_recibido', 'terminado_el'])]
class EnvioDeRespuesta extends Model
{
    /** @use HasFactory<EnvioDeRespuestaFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'respuesta' => Respuesta::class,
            'resultado' => ResultadoDeEnvio::class,
            'terminado_el' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CancelacionPendiente, $this>
     */
    public function cancelacion(): BelongsTo
    {
        return $this->belongsTo(CancelacionPendiente::class, 'cancelacion_pendiente_id');
    }

    /**
     * Usuario que decidió la Respuesta de cancelación.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Ruta en el disco local donde se guarda la evidencia indicada ("enviado" o "recibido").
     */
    public function rutaDeEvidencia(string $archivo): string
    {
        return "cancelaciones/{$this->cancelacion->uuid}/envio-{$this->id}-{$archivo}.xml";
    }
}
