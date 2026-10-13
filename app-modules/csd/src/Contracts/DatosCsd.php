<?php

namespace DescargaSat\Csd\Contracts;

use Carbon\CarbonImmutable;

/**
 * Datos públicos de un CSD. Nunca incluye la contraseña ni los archivos.
 */
final readonly class DatosCsd
{
    public function __construct(
        public int $id,
        public string $rfc,
        public string $razonSocial,
        public string $numeroCertificado,
        public CarbonImmutable $vigenteDesde,
        public CarbonImmutable $vigenteHasta,
        public bool $activo,
    ) {}
}
