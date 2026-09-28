<?php

namespace DescargaSat\SatAutenticacion\Services;

use Carbon\CarbonImmutable;
use DescargaSat\Fiel\Contracts\Fieles;
use DescargaSat\SatAutenticacion\Contracts\ConexionSat;
use DescargaSat\SatAutenticacion\Contracts\FielNoVigente;
use DescargaSat\SatAutenticacion\Contracts\Servicio;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\Shared\ServiceEndpoints;
use PhpCfdi\SatWsDescargaMasiva\WebClient\WebClientInterface;

class ConexionSatConFiel implements ConexionSat
{
    public function __construct(
        private Fieles $fieles,
        private WebClientInterface $webClient,
    ) {}

    /**
     * @throws FielNoVigente
     */
    public function servicio(int $fielId, Servicio $servicio): Service
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

        $fiel = new Fiel($credencial);

        return new Service(
            new FielRequestBuilder($fiel),
            $this->webClient,
            endpoints: match ($servicio) {
                Servicio::Cfdi => ServiceEndpoints::cfdi(),
                Servicio::Retenciones => ServiceEndpoints::retenciones(),
            },
        );
    }
}
