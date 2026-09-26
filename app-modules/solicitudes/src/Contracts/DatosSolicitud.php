<?php

namespace DescargaSat\Solicitudes\Contracts;

use Carbon\CarbonImmutable;
use DescargaSat\SatAutenticacion\Contracts\Servicio;

/**
 * Lo que otros módulos necesitan de una Solicitud para hablar con el SAT sobre ella.
 */
final readonly class DatosSolicitud
{
    public function __construct(
        public int $id,
        public int $fielId,
        public Servicio $servicio,
        public string $idSolicitudSat,
        public CarbonImmutable $presentadaEl,
    ) {}
}
