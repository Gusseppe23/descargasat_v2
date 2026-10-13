<?php

namespace DescargaSat\Cancelaciones\Actions;

use App\Models\User;
use DescargaSat\Cancelaciones\Contracts\EstadoCancelacion;
use DescargaSat\Cancelaciones\Jobs\EnviarRespuesta;
use DescargaSat\Cancelaciones\Models\CancelacionPendiente;
use DescargaSat\Cancelaciones\Models\EnvioDeRespuesta;
use DescargaSat\Csd\Contracts\Csds;
use DescargaSat\SatAutenticacion\Contracts\Respuesta;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResponderCancelacion
{
    public function __construct(private Csds $csds) {}

    /**
     * Deja la Cancelación pendiente Enviando y encola el Envío de respuesta con el CSD activo.
     *
     * El usuario confirma escribiendo el UUID. El cambio a Enviando se hace con un candado por UUID, así que
     * dos clics seguidos no encolan dos envíos.
     *
     * @throws ValidationException
     */
    public function __invoke(CancelacionPendiente $cancelacion, Respuesta $respuesta, string $uuidEscrito, User $usuario): EnvioDeRespuesta
    {
        if (strcasecmp(trim($uuidEscrito), $cancelacion->uuid) !== 0) {
            throw ValidationException::withMessages(['uuidEscrito' => 'Escribe el UUID tal como aparece para confirmar.']);
        }

        $csd = $this->csds->activo()
            ?? throw ValidationException::withMessages(['respuesta' => 'No hay un CSD activo. Activa uno en la pantalla CSD.']);

        $envio = Cache::lock("cancelaciones:responder:{$cancelacion->uuid}", 10)->get(function () use ($cancelacion, $respuesta, $usuario, $csd): ?EnvioDeRespuesta {
            $cancelacion->refresh();

            if (! $cancelacion->puedeResponderse()) {
                return null;
            }

            return DB::transaction(function () use ($cancelacion, $respuesta, $usuario, $csd): EnvioDeRespuesta {
                $cancelacion->update(['estado' => EstadoCancelacion::Enviando]);

                return $cancelacion->envios()->create([
                    'respuesta' => $respuesta,
                    'usuario_id' => $usuario->id,
                    'csd_id' => $csd->id,
                ]);
            });
        });

        if (! $envio instanceof EnvioDeRespuesta) {
            throw ValidationException::withMessages(['respuesta' => $this->porQueNoSePuede($cancelacion)]);
        }

        EnviarRespuesta::dispatch($envio->id);

        return $envio;
    }

    private function porQueNoSePuede(CancelacionPendiente $cancelacion): string
    {
        return match ($cancelacion->estado) {
            EstadoCancelacion::Enviando => 'Ya se está enviando una Respuesta de cancelación.',
            EstadoCancelacion::EnvioIncierto => 'Solo se puede volver a enviar cuando una Revisión de cancelaciones posterior al envío la siga mostrando pendiente.',
            default => "Esta cancelación ya no se puede responder: está en {$cancelacion->estado->etiqueta()}.",
        };
    }
}
