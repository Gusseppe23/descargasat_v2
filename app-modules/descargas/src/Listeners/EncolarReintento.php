<?php

namespace DescargaSat\Descargas\Listeners;

use DescargaSat\Descargas\Jobs\DescargarPaquete;
use DescargaSat\Paquetes\Contracts\PaqueteReintentado;

/**
 * Encola de nuevo la descarga de un Paquete que alguien pidió reintentar.
 */
class EncolarReintento
{
    public function handle(PaqueteReintentado $evento): void
    {
        DescargarPaquete::dispatch($evento->paqueteId);
    }
}
