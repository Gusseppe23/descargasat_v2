<?php

namespace DescargaSat\SatAutenticacion\Services;

use DescargaSat\SatAutenticacion\Contracts\AcuseDeConsulta;
use DescargaSat\SatAutenticacion\Contracts\ProblemaConElSat;
use DescargaSat\SatAutenticacion\Contracts\ServicioConsultaCfdi;
use DescargaSat\SatAutenticacion\Contracts\WebClientConsultaCfdi;
use DOMElement;
use Illuminate\Support\Facades\Log;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Exceptions\WebClientException;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Request;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Response;

class ServicioConsultaCfdiSat implements ServicioConsultaCfdi
{
    public const NS_SERVICIO = 'http://tempuri.org/';

    public const ACCION = 'http://tempuri.org/IConsultaCFDIService/Consulta';

    public function __construct(
        private WebClientConsultaCfdi $webClient,
        private LectorDeRespuestas $lector,
    ) {}

    public function consultar(string $expresionImpresa): AcuseDeConsulta
    {
        $sobre = sprintf(
            '<?xml version="1.0" encoding="UTF-8"?><s:Envelope xmlns:s="%s"><s:Body><Consulta xmlns="%s"><expresionImpresa>%s</expresionImpresa></Consulta></s:Body></s:Envelope>',
            LectorDeRespuestas::NS_SOAP,
            self::NS_SERVICIO,
            htmlspecialchars($expresionImpresa, ENT_XML1),
        );

        $acuse = $this->acuse($this->enviar($sobre));

        if ($acuse->codigo() === null) {
            Log::warning('El SAT respondió un código desconocido a Consulta.', [
                'codigo' => $acuse->codigoEstatus,
            ]);
        }

        return $acuse;
    }

    /**
     * Lee el ConsultaResult. Los campos vienen en el namespace del DataContract del SAT, así que se buscan por nombre local.
     *
     * @throws ProblemaConElSat
     */
    private function acuse(Response $respuesta): AcuseDeConsulta
    {
        $documento = $this->lector->documento($respuesta);
        $resultado = $documento->getElementsByTagNameNS(self::NS_SERVICIO, 'ConsultaResult')->item(0);

        if (! $resultado instanceof DOMElement) {
            throw new ProblemaConElSat("La respuesta del SAT (HTTP {$respuesta->getStatusCode()}) no trae ConsultaResult.");
        }

        $campos = [];

        foreach ($resultado->childNodes as $campo) {
            if ($campo instanceof DOMElement) {
                $valor = trim($campo->textContent);
                $campos[$campo->localName] = $valor === '' ? null : $valor;
            }
        }

        return new AcuseDeConsulta(
            codigoEstatus: $campos['CodigoEstatus'] ?? '',
            estado: $campos['Estado'] ?? '',
            esCancelable: $campos['EsCancelable'] ?? null,
            estatusCancelacion: $campos['EstatusCancelacion'] ?? null,
            validacionEfos: $campos['ValidacionEFOS'] ?? null,
            xmlRecibido: $respuesta->getBody(),
        );
    }

    /**
     * Envía el sobre al URL configurado. Un SOAP Fault llega con HTTP 500, así que esa respuesta se devuelve para leerla.
     *
     * @throws ProblemaConElSat
     */
    private function enviar(string $sobre): Response
    {
        $peticion = new Request('POST', (string) config('sat.consulta_cfdi.url'), $sobre, [
            'SOAPAction' => '"'.self::ACCION.'"',
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
