<?php

namespace Tests\Support;

use Carbon\CarbonImmutable;
use DescargaSat\Csd\Contracts\Csds;
use DescargaSat\Csd\Contracts\DatosCsd;
use PhpCfdi\Credentials\Credential;

/**
 * Sustituye el contrato Csds del módulo csd por un solo CSD de prueba.
 */
final class CsdsFalsos implements Csds
{
    public function __construct(
        private CertificadoDePrueba $certificado,
        private bool $hayActivo = true,
        public readonly int $id = 1,
    ) {}

    /**
     * Registra en el contenedor un CSD de prueba como el CSD activo (o sin CSD activo).
     */
    public static function usar(?CertificadoDePrueba $certificado = null, bool $hayActivo = true): self
    {
        $csds = new self($certificado ?? CertificadoDePrueba::csd(), $hayActivo);
        app()->instance(Csds::class, $csds);

        return $csds;
    }

    public function activo(): ?DatosCsd
    {
        if (! $this->hayActivo) {
            return null;
        }

        return new DatosCsd(
            id: $this->id,
            rfc: $this->certificado->rfc,
            razonSocial: $this->certificado->razonSocial,
            numeroCertificado: $this->certificado->numeroCertificado,
            vigenteDesde: CarbonImmutable::instance($this->certificado->vigenteDesde),
            vigenteHasta: CarbonImmutable::instance($this->certificado->vigenteHasta),
            activo: true,
        );
    }

    public function credencial(int $id): Credential
    {
        return Credential::create($this->certificado->cer, $this->certificado->key, $this->certificado->contrasena);
    }
}
