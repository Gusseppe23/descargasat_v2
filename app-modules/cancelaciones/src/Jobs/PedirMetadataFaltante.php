<?php

namespace DescargaSat\Cancelaciones\Jobs;

use DescargaSat\Cancelaciones\Contracts\EstadoCancelacion;
use DescargaSat\Cancelaciones\Models\CancelacionPendiente;
use DescargaSat\Metadata\Contracts\MetadataCfdi;
use DescargaSat\Solicitudes\Contracts\NoSePudoPresentarSolicitud;
use DescargaSat\Solicitudes\Contracts\Solicitudes;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Después de una Revisión de cancelaciones, pide por UUID la Metadata de las Cancelaciones Por responder que no la tienen.
 */
class PedirMetadataFaltante implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct()
    {
        $this->onConnection('database');
    }

    public function handle(MetadataCfdi $metadataCfdi, Solicitudes $solicitudes): void
    {
        $porResponder = CancelacionPendiente::where('estado', EstadoCancelacion::PorResponder)->orderBy('id')->pluck('uuid')->all();

        $porPedir = $solicitudes->porPedirPorUuid($metadataCfdi->sinMetadata($porResponder));

        foreach (array_slice($porPedir, 0, max(0, (int) config('sat.metadata.maximo_por_revision'))) as $uuid) {
            try {
                $solicitudes->presentarPorUuid($uuid);
            } catch (NoSePudoPresentarSolicitud $error) {
                // Sin Fiel activa o sin respuesta del SAT, los demás tampoco saldrían; se piden en la siguiente Revisión.
                Log::warning("No se pidió la Metadata de las Cancelaciones pendientes: {$error->getMessage()}");

                return;
            }
        }
    }
}
