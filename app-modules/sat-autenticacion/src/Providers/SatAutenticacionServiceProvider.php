<?php

namespace DescargaSat\SatAutenticacion\Providers;

use DescargaSat\SatAutenticacion\Contracts\ConexionSat;
use DescargaSat\SatAutenticacion\Contracts\FabricaDeScraper;
use DescargaSat\SatAutenticacion\Contracts\PortalCfdi;
use DescargaSat\SatAutenticacion\Contracts\ServicioCancelaciones;
use DescargaSat\SatAutenticacion\Contracts\ServicioConsultaCfdi;
use DescargaSat\SatAutenticacion\Contracts\WebClientCancelaciones;
use DescargaSat\SatAutenticacion\Contracts\WebClientConsultaCfdi;
use DescargaSat\SatAutenticacion\Services\ConexionSatConFiel;
use DescargaSat\SatAutenticacion\Services\FabricaDeScraperConFiel;
use DescargaSat\SatAutenticacion\Services\GuzzleWebClientCancelaciones;
use DescargaSat\SatAutenticacion\Services\GuzzleWebClientConsultaCfdi;
use DescargaSat\SatAutenticacion\Services\PortalCfdiSat;
use DescargaSat\SatAutenticacion\Services\ServicioCancelacionesSat;
use DescargaSat\SatAutenticacion\Services\ServicioConsultaCfdiSat;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\ServiceProvider;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use PhpCfdi\SatWsDescargaMasiva\WebClient\WebClientInterface;

class SatAutenticacionServiceProvider extends ServiceProvider
{
    /**
     * Intermedio que el portal de CFDI no envía en su cadena TLS (GlobalSign RSA OV SSL CA 2018, vence el 21/11/2028).
     *
     * Se usa junto con los certificados raíz del sistema (CURLOPT_CAPATH); la verificación TLS nunca se desactiva. Ver ADR-0007.
     */
    public const INTERMEDIO_DEL_PORTAL = __DIR__.'/../../resources/certificados/globalsign-rsa-ov-ssl-ca-2018.pem';

    public function register(): void
    {
        $this->app->bind(ConexionSat::class, ConexionSatConFiel::class);
        $this->app->bind(ServicioCancelaciones::class, ServicioCancelacionesSat::class);
        $this->app->bind(ServicioConsultaCfdi::class, ServicioConsultaCfdiSat::class);
        $this->app->bind(PortalCfdi::class, PortalCfdiSat::class);

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

        $this->app->bind(FabricaDeScraper::class, fn (): FabricaDeScraper => new FabricaDeScraperConFiel([
            RequestOptions::CONNECT_TIMEOUT => 10,
            RequestOptions::TIMEOUT => (int) config('sat.portal_cfdi.timeout'),
            RequestOptions::VERIFY => self::INTERMEDIO_DEL_PORTAL,
            'curl' => [
                CURLOPT_CAPATH => openssl_get_cert_locations()['default_cert_dir'],
                CURLOPT_SSL_CIPHER_LIST => 'DEFAULT@SECLEVEL=1',
            ],
        ]));
    }

    public function boot(): void {}
}
