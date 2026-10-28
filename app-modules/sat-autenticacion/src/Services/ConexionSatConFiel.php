<?php

namespace DescargaSat\SatAutenticacion\Services;

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
        private FielVigente $fielVigente,
        private WebClientInterface $webClient,
    ) {}

    /**
     * @throws FielNoVigente
     */
    public function servicio(int $fielId, Servicio $servicio): Service
    {
        $fiel = new Fiel($this->fielVigente->credencial($fielId));

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
