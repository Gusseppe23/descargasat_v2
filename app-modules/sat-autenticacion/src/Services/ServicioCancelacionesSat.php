<?php

namespace DescargaSat\SatAutenticacion\Services;

use Carbon\CarbonImmutable;
use DescargaSat\Csd\Contracts\Csds;
use DescargaSat\SatAutenticacion\Contracts\AcuseDeRespuesta;
use DescargaSat\SatAutenticacion\Contracts\CsdNoVigente;
use DescargaSat\SatAutenticacion\Contracts\PeticionesPendientes;
use DescargaSat\SatAutenticacion\Contracts\ProblemaConElSat;
use DescargaSat\SatAutenticacion\Contracts\Respuesta;
use DescargaSat\SatAutenticacion\Contracts\ServicioCancelaciones;
use DescargaSat\SatAutenticacion\Contracts\WebClientCancelaciones;
use Illuminate\Support\Facades\Log;
use PhpCfdi\Credentials\Credential;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Exceptions\WebClientException;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Request;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Response;

class ServicioCancelacionesSat implements ServicioCancelaciones
{
    public const ACCION = 'http://cancelacfd.sat.gob.mx/entespublicos/IAceptacionRechazoServiceEP/';

    public function __construct(
        private Csds $csds,
        private WebClientCancelaciones $webClient,
        private SobreCancelaciones $sobres,
        private LectorDeRespuestas $lector,
        private FirmadorDeRespuesta $firmador,
    ) {}

    public function pendientes(int $csdId): PeticionesPendientes
    {
        $credencial = $this->credencialVigente($csdId);

        $cuerpo = sprintf(
            '<ObtenerPeticionesPendientes xmlns="%s"><RfcReceptor>%s</RfcReceptor></ObtenerPeticionesPendientes>',
            LectorDeRespuestas::NS_SERVICIO,
            htmlspecialchars($credencial->rfc(), ENT_XML1),
        );

        $pendientes = $this->lector->peticionesPendientes(
            $this->enviar('ObtenerPeticionesPendientes', $this->sobres->sobre($credencial, $cuerpo)),
        );

        if ($pendientes->codigo() === null) {
            Log::warning('El SAT respondió un código desconocido a ObtenerPeticionesPendientes.', [
                'codigo' => $pendientes->codEstatus,
            ]);
        }

        return $pendientes;
    }

    public function responder(int $csdId, string $uuid, Respuesta $respuesta): AcuseDeRespuesta
    {
        $credencial = $this->credencialVigente($csdId);

        $cuerpo = sprintf(
            '<ProcesarRespuesta xmlns="%s">%s</ProcesarRespuesta>',
            LectorDeRespuestas::NS_SERVICIO,
            $this->firmador->firmar($credencial, $uuid, $respuesta, (string) config('sat.cancelaciones.firma')),
        );
        $sobre = $this->sobres->sobre($credencial, $cuerpo);

        try {
            return $this->lector->acuse($this->enviar('ProcesarRespuesta', $sobre), $sobre);
        } catch (ProblemaConElSat $error) {
            throw $error->conXmlEnviado($sobre);
        }
    }

    /**
     * @throws CsdNoVigente
     */
    private function credencialVigente(int $csdId): Credential
    {
        $credencial = $this->csds->credencial($csdId);
        $certificado = $credencial->certificate();
        $numeroCertificado = $certificado->serialNumber()->bytes();

        if (now()->lessThan($certificado->validFromDateTime())) {
            $entraEnVigorEl = CarbonImmutable::instance($certificado->validFromDateTime())->format('d/m/Y');

            throw new CsdNoVigente("El CSD {$numeroCertificado} entra en vigor el {$entraEnVigorEl}.");
        }

        if (! $certificado->validOn(now()->toDateTimeImmutable())) {
            $venceEl = CarbonImmutable::instance($certificado->validToDateTime())->format('d/m/Y');

            throw new CsdNoVigente("El CSD {$numeroCertificado} venció el {$venceEl}.");
        }

        return $credencial;
    }

    /**
     * Envía el sobre al URL configurado. Un SOAP Fault llega con HTTP 500, así que esa respuesta se devuelve para leerla.
     *
     * @throws ProblemaConElSat
     */
    private function enviar(string $operacion, string $sobre): Response
    {
        $peticion = new Request('POST', (string) config('sat.cancelaciones.url'), $sobre, [
            'SOAPAction' => '"'.self::ACCION.$operacion.'"',
        ]);

        try {
            return $this->webClient->call($peticion);
        } catch (WebClientException $error) {
            if (! $error->getResponse()->isEmpty()) {
                return $error->getResponse();
            }

            throw new ProblemaConElSat("No se pudo contactar al SAT ({$error->getMessage()}).", previous: $error);
        }
    }
}
