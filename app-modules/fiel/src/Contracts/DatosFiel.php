<?php

namespace DescargaSat\Fiel\Contracts;

use Carbon\CarbonImmutable;

/**
 * Datos públicos de una Fiel. Nunca incluye la contraseña ni los archivos.
 */
final readonly class DatosFiel
{
    public function __construct(
        public int $id,
        public string $rfc,
        public string $razonSocial,
        public string $numeroCertificado,
        public CarbonImmutable $vigenteDesde,
        public CarbonImmutable $vigenteHasta,
        public bool $activa,
    ) {}
}
