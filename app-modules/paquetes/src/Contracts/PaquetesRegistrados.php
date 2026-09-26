<?php

namespace DescargaSat\Paquetes\Contracts;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Hay Paquetes Pendientes nuevos de una Solicitud, listos para descargarse.
 */
final class PaquetesRegistrados
{
    use Dispatchable;

    public function __construct(public readonly int $solicitudId) {}
}
