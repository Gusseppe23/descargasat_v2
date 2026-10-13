<?php

namespace DescargaSat\SatAutenticacion\Services;

use DescargaSat\SatAutenticacion\Contracts\AcuseDeRespuesta;
use DescargaSat\SatAutenticacion\Contracts\FaultDelSat;
use DescargaSat\SatAutenticacion\Contracts\FolioDeAcuse;
use DescargaSat\SatAutenticacion\Contracts\PeticionesPendientes;
use DescargaSat\SatAutenticacion\Contracts\ProblemaConElSat;
use DescargaSat\SatAutenticacion\Contracts\RespuestaNoXml;
use DOMDocument;
use DOMElement;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Response;

/**
 * Lee las respuestas del servicio de cancelaciones del SAT.
 */
class LectorDeRespuestas
{
    public const NS_SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';

    public const NS_SERVICIO = 'http://cancelacfd.sat.gob.mx/entespublicos';

    public const NS_DATOS = 'http://cancelacfd.sat.gob.mx';

    /**
     * @throws ProblemaConElSat
     */
    public function peticionesPendientes(Response $respuesta): PeticionesPendientes
    {
        $documento = $this->documento($respuesta);
        $resultado = $documento->getElementsByTagNameNS(self::NS_SERVICIO, 'ObtenerPeticionesPendientesResult')->item(0);

        if (! $resultado instanceof DOMElement) {
            throw new ProblemaConElSat("La respuesta del SAT (HTTP {$respuesta->getStatusCode()}) no trae ObtenerPeticionesPendientesResult.");
        }

        $uuids = [];

        foreach ($resultado->getElementsByTagNameNS(self::NS_DATOS, 'UUID') as $uuid) {
            $uuids[] = strtoupper(trim($uuid->textContent));
        }

        return new PeticionesPendientes(trim($resultado->getAttribute('CodEstatus')), $uuids);
    }

    /**
     * @throws ProblemaConElSat
     */
    public function acuse(Response $respuesta, string $xmlEnviado): AcuseDeRespuesta
    {
        $documento = $this->documento($respuesta);
        $resultado = $documento->getElementsByTagNameNS(self::NS_SERVICIO, 'ProcesarRespuestaResult')->item(0);

        if (! $resultado instanceof DOMElement) {
            throw new ProblemaConElSat("La respuesta del SAT (HTTP {$respuesta->getStatusCode()}) no trae ProcesarRespuestaResult.");
        }

        $folios = [];

        foreach ($resultado->getElementsByTagNameNS(self::NS_DATOS, 'Folios') as $folio) {
            $folios[] = new FolioDeAcuse(
                uuid: strtoupper(trim($folio->getElementsByTagNameNS(self::NS_DATOS, 'UUID')->item(0)->textContent ?? '')),
                respuesta: trim($folio->getAttribute('Respuesta')),
                estatusUuid: trim($folio->getElementsByTagNameNS(self::NS_DATOS, 'EstatusUUID')->item(0)->textContent ?? ''),
            );
        }

        $selladoPorElSat = false;

        foreach ($resultado->getElementsByTagNameNS(FirmadorDeRespuesta::NS_DSIG, 'Signature') as $firma) {
            $selladoPorElSat = $selladoPorElSat || $firma->getAttribute('Id') === 'SelloSAT';
        }

        return new AcuseDeRespuesta(
            codEstatus: trim($resultado->getAttribute('CodEstatus')),
            folios: $folios,
            selladoPorElSat: $selladoPorElSat,
            xmlEnviado: $xmlEnviado,
            xmlRecibido: $respuesta->getBody(),
        );
    }

    /**
     * @throws RespuestaNoXml
     * @throws FaultDelSat
     */
    private function documento(Response $respuesta): DOMDocument
    {
        if (trim($respuesta->getBody()) === '') {
            throw new RespuestaNoXml("El SAT respondió vacío (HTTP {$respuesta->getStatusCode()}).");
        }

        $documento = new DOMDocument;
        $erroresPrevios = libxml_use_internal_errors(true);
        $leido = $documento->loadXML($respuesta->getBody(), LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($erroresPrevios);

        if (! $leido) {
            throw new RespuestaNoXml("El SAT respondió algo que no es XML (HTTP {$respuesta->getStatusCode()}).");
        }

        $fault = $documento->getElementsByTagNameNS(self::NS_SOAP, 'Fault')->item(0);

        if ($fault instanceof DOMElement) {
            $explicacion = trim($fault->getElementsByTagName('faultstring')->item(0)->textContent ?? $fault->textContent);

            throw new FaultDelSat("El SAT respondió con un error SOAP: {$explicacion}");
        }

        return $documento;
    }
}
