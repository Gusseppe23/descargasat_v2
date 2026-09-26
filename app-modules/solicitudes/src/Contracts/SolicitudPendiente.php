<?php

namespace DescargaSat\Solicitudes\Contracts;

use Carbon\CarbonImmutable;
use DescargaSat\SatAutenticacion\Contracts\Servicio;

/**
 * Una Solicitud que todavía no tiene estado final, con lo necesario para verificarla.
 */
final readonly class SolicitudPendiente
{
    public function __construct(
        public int $id,
        public int $fielId,
        public Servicio $servicio,
        public string $idSolicitudSat,
        public CarbonImmutable $presentadaEl,
    ) {}
}
