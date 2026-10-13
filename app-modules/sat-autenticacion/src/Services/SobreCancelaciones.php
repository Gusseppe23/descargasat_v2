<?php

namespace DescargaSat\SatAutenticacion\Services;

use DescargaSat\SatAutenticacion\Contracts\FormatoDeAutorizacionCambiado;
use PhpCfdi\Credentials\Credential;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTime;

/**
 * Arma el sobre SOAP del servicio de cancelaciones: encabezado WS-Security firmado y el cuerpo de la operación.
 *
 * El encabezado se toma de FielRequestBuilder::authorization(); como su firma cubre solo el u:Timestamp,
 * se cambia su cuerpo <Autentica/> por el de la operación sin invalidarla.
 */
class SobreCancelaciones
{
    public const CUERPO_AUTENTICA = '<s:Body><Autentica xmlns="http://DescargaMasivaTerceros.gob.mx"/></s:Body>';

    private const ABRE_CUERPO = '<s:Body xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';

    /**
     * El sobre con el encabezado firmado con el CSD, vigente 5 minutos, y el cuerpo indicado.
     *
     * @throws FormatoDeAutorizacionCambiado
     */
    public function sobre(Credential $credencial, string $cuerpo): string
    {
        $creado = DateTime::now();
        $autorizacion = (new FielRequestBuilder(new Fiel($credencial)))->authorization($creado, $creado->modify('+5 minutes'));

        return $this->envolver($autorizacion, $cuerpo);
    }

    /**
     * Sustituye el cuerpo <Autentica/> del sobre de autorización por el cuerpo indicado.
     *
     * @throws FormatoDeAutorizacionCambiado
     */
    public function envolver(string $autorizacion, string $cuerpo): string
    {
        if (substr_count($autorizacion, self::CUERPO_AUTENTICA) !== 1) {
            throw new FormatoDeAutorizacionCambiado(
                'Cambió el formato de FielRequestBuilder::authorization(): no trae el cuerpo <Autentica/> esperado.',
            );
        }

        return str_replace(self::CUERPO_AUTENTICA, self::ABRE_CUERPO.$cuerpo.'</s:Body>', $autorizacion);
    }
}
