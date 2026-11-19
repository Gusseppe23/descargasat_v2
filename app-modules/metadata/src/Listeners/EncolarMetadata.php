<?php

namespace DescargaSat\Metadata\Listeners;

use DescargaSat\Metadata\Jobs\GuardarMetadata;
use DescargaSat\Paquetes\Contracts\PaqueteExtraido;

/**
 * Encola la lectura de la Metadata de cada Paquete Extraído, una vez confirmada la transacción.
 */
class EncolarMetadata
{
    public function handle(PaqueteExtraido $evento): void
    {
        GuardarMetadata::dispatch($evento->paqueteId, $evento->carpeta)->afterCommit();
    }
}
