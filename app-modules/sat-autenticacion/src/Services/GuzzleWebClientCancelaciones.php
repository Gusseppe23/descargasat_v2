<?php

namespace DescargaSat\SatAutenticacion\Services;

use DescargaSat\SatAutenticacion\Contracts\WebClientCancelaciones;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;

class GuzzleWebClientCancelaciones extends GuzzleWebClient implements WebClientCancelaciones
{
    /**
     * Un cliente que devuelve también las respuestas HTTP 4xx y 5xx, como el SOAP Fault que el SAT manda con HTTP 500.
     *
     * GuzzleWebClient de phpcfdi/sat-ws-descarga-masiva las pierde: busca la respuesta en la excepción de Guzzle con
     * GuzzleHttp\Exception\ResponseException, clase que no existe en Guzzle 7, y la deja vacía como si no hubiera conexión.
     *
     * @param  array<string, mixed>  $opciones  Opciones del cliente de Guzzle.
     */
    public static function crear(array $opciones): self
    {
        return new self(new Client([...$opciones, RequestOptions::HTTP_ERRORS => false]));
    }
}
