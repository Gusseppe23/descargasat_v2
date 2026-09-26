<?php

namespace DescargaSat\Paquetes\Contracts;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un Paquete Fallido volvió a Pendiente y hay que descargarlo de nuevo.
 */
final class PaqueteReintentado
{
    use Dispatchable;

    public function __construct(public readonly int $paqueteId) {}
}
