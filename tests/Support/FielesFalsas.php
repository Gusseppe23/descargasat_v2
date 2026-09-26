<?php

namespace Tests\Support;

use Carbon\CarbonImmutable;
use DescargaSat\Fiel\Contracts\DatosFiel;
use DescargaSat\Fiel\Contracts\Fieles;
use PhpCfdi\Credentials\Credential;

/**
 * Sustituye el contrato Fieles del módulo fiel por una sola Fiel de prueba.
 */
final class FielesFalsas implements Fieles
{
    public function __construct(
        private CertificadoDePrueba $certificado,
        private bool $hayActiva = true,
        public readonly int $id = 1,
    ) {}

    /**
     * Registra en el contenedor una Fiel de prueba como la Fiel activa (o sin Fiel activa).
     */
    public static function usar(?CertificadoDePrueba $certificado = null, bool $hayActiva = true): self
    {
        $fieles = new self($certificado ?? CertificadoDePrueba::fiel(), $hayActiva);
        app()->instance(Fieles::class, $fieles);

        return $fieles;
    }

    public function activa(): ?DatosFiel
    {
        return $this->hayActiva ? $this->datos() : null;
    }

    public function porId(int $id): ?DatosFiel
    {
        return $id === $this->id ? $this->datos() : null;
    }

    public function credencial(int $id): Credential
    {
        return Credential::create($this->certificado->cer, $this->certificado->key, $this->certificado->contrasena);
    }

    private function datos(): DatosFiel
    {
        return new DatosFiel(
            id: $this->id,
            rfc: $this->certificado->rfc,
            razonSocial: $this->certificado->razonSocial,
            numeroCertificado: $this->certificado->numeroCertificado,
            vigenteDesde: CarbonImmutable::instance($this->certificado->vigenteDesde),
            vigenteHasta: CarbonImmutable::instance($this->certificado->vigenteHasta),
            activa: $this->hayActiva,
        );
    }
}
