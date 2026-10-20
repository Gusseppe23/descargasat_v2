<?php

namespace DescargaSat\SatAutenticacion\Providers;

use DescargaSat\SatAutenticacion\Contracts\ConexionSat;
use DescargaSat\SatAutenticacion\Contracts\ServicioCancelaciones;
use DescargaSat\SatAutenticacion\Contracts\ServicioConsultaCfdi;
use DescargaSat\SatAutenticacion\Contracts\WebClientCancelaciones;
use DescargaSat\SatAutenticacion\Contracts\WebClientConsultaCfdi;
use DescargaSat\SatAutenticacion\Services\ConexionSatConFiel;
use DescargaSat\SatAutenticacion\Services\GuzzleWebClientCancelaciones;
use DescargaSat\SatAutenticacion\Services\GuzzleWebClientConsultaCfdi;
use DescargaSat\SatAutenticacion\Services\ServicioCancelacionesSat;
use DescargaSat\SatAutenticacion\Services\ServicioConsultaCfdiSat;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\ServiceProvider;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use PhpCfdi\SatWsDescargaMasiva\WebClient\WebClientInterface;

class SatAutenticacionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ConexionSat::class, ConexionSatConFiel::class);
        $this->app->bind(ServicioCancelaciones::class, ServicioCancelacionesSat::class);
        $this->app->bind(ServicioConsultaCfdi::class, ServicioConsultaCfdiSat::class);

        $this->app->bind(WebClientInterface::class, fn (): WebClientInterface => new GuzzleWebClient(new Client([
            RequestOptions::CONNECT_TIMEOUT => 10,
            RequestOptions::TIMEOUT => 300,
        ])));

        $this->app->bind(WebClientCancelaciones::class, fn (): WebClientCancelaciones => new GuzzleWebClientCancelaciones(new Client([
            RequestOptions::CONNECT_TIMEOUT => 10,
            RequestOptions::TIMEOUT => (int) config('sat.cancelaciones.timeout'),
        ])));

        $this->app->bind(WebClientConsultaCfdi::class, fn (): WebClientConsultaCfdi => new GuzzleWebClientConsultaCfdi(new Client([
            RequestOptions::CONNECT_TIMEOUT => 10,
            RequestOptions::TIMEOUT => (int) config('sat.consulta_cfdi.timeout'),
        ])));
    }

    public function boot(): void {}
}
