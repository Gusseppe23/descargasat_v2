<?php

namespace DescargaSat\Paquetes\Contracts;

/**
 * Datos públicos de un Paquete.
 */
final readonly class DatosPaquete
{
    public function __construct(
        public int $id,
        public int $solicitudId,
        public string $idPaqueteSat,
        public EstadoPaquete $estado,
    ) {}
}
