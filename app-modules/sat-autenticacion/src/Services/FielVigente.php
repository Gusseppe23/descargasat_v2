<?php

namespace DescargaSat\SatAutenticacion\Services;

use Carbon\CarbonImmutable;
use DescargaSat\Fiel\Contracts\Fieles;
use DescargaSat\SatAutenticacion\Contracts\FielNoVigente;
use PhpCfdi\Credentials\Credential;

/**
 * Entrega la credencial de una Fiel solo si está vigente, para no mandarle al SAT una que rechazaría.
 */
class FielVigente
{
    public function __construct(private Fieles $fieles) {}

    /**
     * @throws FielNoVigente
     */
    public function credencial(int $fielId): Credential
    {
        $credencial = $this->fieles->credencial($fielId);
        $certificado = $credencial->certificate();
        $numeroCertificado = $certificado->serialNumber()->bytes();

        if (now()->lessThan($certificado->validFromDateTime())) {
            $entraEnVigorEl = CarbonImmutable::instance($certificado->validFromDateTime())->format('d/m/Y');

            throw new FielNoVigente("La Fiel {$numeroCertificado} entra en vigor el {$entraEnVigorEl}.");
        }

        if (! $certificado->validOn(now()->toDateTimeImmutable())) {
            $venceEl = CarbonImmutable::instance($certificado->validToDateTime())->format('d/m/Y');

            throw new FielNoVigente("La Fiel {$numeroCertificado} venció el {$venceEl}.");
        }

        return $credencial;
    }
}
