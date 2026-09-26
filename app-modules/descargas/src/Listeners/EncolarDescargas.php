<?php

namespace DescargaSat\Descargas\Listeners;

use DescargaSat\Descargas\Jobs\DescargarPaquete;
use DescargaSat\Paquetes\Contracts\Paquetes;
use DescargaSat\Paquetes\Contracts\PaquetesRegistrados;

/**
 * Encola la descarga de cada Paquete Pendiente en cuanto se registra.
 */
class EncolarDescargas
{
    public function __construct(private Paquetes $paquetes) {}

    public function handle(PaquetesRegistrados $evento): void
    {
        foreach ($this->paquetes->pendientesDe($evento->solicitudId) as $paquete) {
            DescargarPaquete::dispatch($paquete->id);
        }
    }
}
