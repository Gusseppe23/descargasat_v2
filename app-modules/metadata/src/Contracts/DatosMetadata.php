<?php

namespace DescargaSat\Metadata\Contracts;

use Carbon\CarbonImmutable;

/**
 * Metadata de CFDI de un UUID, tal como la entregó el SAT.
 */
final readonly class DatosMetadata
{
    /**
     * @param  string  $monto  Importe con dos decimales, sin formato (ej. "1250.00").
     * @param  string  $efecto  EfectoComprobante: I, E, T, N o P.
     * @param  string|null  $estatus  1 vigente, 0 cancelado; null si solo se tiene el XML, que no lo trae.
     */
    public function __construct(
        public string $uuid,
        public string $rfcEmisor,
        public ?string $nombreEmisor,
        public string $rfcReceptor,
        public ?string $nombreReceptor,
        public ?string $rfcPac,
        public CarbonImmutable $fechaEmision,
        public ?CarbonImmutable $fechaCertificacionSat,
        public string $monto,
        public string $efecto,
        public ?string $estatus,
        public ?CarbonImmutable $fechaCancelacion,
    ) {}
}
