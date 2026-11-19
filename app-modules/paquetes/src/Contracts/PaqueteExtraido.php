<?php

namespace DescargaSat\Paquetes\Contracts;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * El Contenido de un Paquete ya está en su carpeta del disco local y el Paquete quedó Extraído.
 */
final class PaqueteExtraido
{
    use Dispatchable;

    public function __construct(
        public readonly int $paqueteId,
        public readonly string $carpeta,
    ) {}
}
