<?php

namespace Tests\Support;

use DescargaSat\SatAutenticacion\Contracts\WebClientCancelaciones;
use DescargaSat\SatAutenticacion\Contracts\WebClientConsultaCfdi;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Exceptions\WebClientException;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Request;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Response;
use RuntimeException;

/**
 * Doble del cliente web del SAT: guarda cada petición y responde en orden con respuestas preparadas.
 *
 * Sirve para la descarga masiva (WebClientInterface), el servicio de cancelaciones y la Consulta de CFDI.
 */
final class WebClientFalso implements WebClientCancelaciones, WebClientConsultaCfdi
{
    /** @var list<Request> */
    public array $peticiones = [];

    /** @var list<Response|WebClientException|null> */
    private array $respuestas = [];

    public function responder(string $fixture, int $estado = 200): self
    {
        $this->respuestas[] = new Response($estado, (string) file_get_contents(base_path("tests/Fixtures/sat/{$fixture}")));

        return $this;
    }

    /**
     * Acuse de ProcesarRespuesta del SAT (ejemplo 6.3) con los datos indicados.
     */
    public function responderAcuse(
        string $uuid,
        string $respuesta,
        string $codigo = '1000',
        string $estatus = '1005',
        bool $sellado = true,
    ): self {
        $plantilla = (string) file_get_contents(base_path('tests/Fixtures/sat/cancelaciones-acuse.xml'));
        $sello = '<Signature Id="SelloSAT" xmlns="http://www.w3.org/2000/09/xmldsig#"><SignatureValue>c2VsbG8tZGUtcHJ1ZWJh</SignatureValue></Signature>';

        $this->respuestas[] = new Response(200, strtr($plantilla, [
            '{codigo}' => $codigo,
            '{respuesta}' => $respuesta,
            '{uuid}' => $uuid,
            '{estatus}' => $estatus,
            '{sello}' => $sellado ? $sello : '',
        ]));

        return $this;
    }

    /**
     * Responde con el texto indicado tal cual, sin leerlo de un fixture.
     */
    public function responderTexto(string $cuerpo, int $estado = 200): self
    {
        $this->respuestas[] = new Response($estado, $cuerpo);

        return $this;
    }

    /**
     * La siguiente petición falla como Guzzle ante un HTTP 500 con cuerpo, como llega un SOAP Fault del SAT.
     */
    public function fallarCon(string $fixture, int $estado = 500): self
    {
        $this->respuestas[] = new WebClientException(
            'Server error',
            new Request('POST', '', '', []),
            new Response($estado, (string) file_get_contents(base_path("tests/Fixtures/sat/{$fixture}"))),
        );

        return $this;
    }

    /**
     * Respuesta de descarga del SAT con el ZIP indicado.
     */
    public function responderDescarga(string $zip, int $codigo = 5000, string $mensaje = 'Solicitud Aceptada'): self
    {
        $plantilla = (string) file_get_contents(base_path('tests/Fixtures/sat/descarga.xml'));
        $this->respuestas[] = new Response(200, strtr($plantilla, [
            '{codigo}' => (string) $codigo,
            '{mensaje}' => $mensaje,
            '{paquete}' => base64_encode($zip),
        ]));

        return $this;
    }

    /**
     * La siguiente petición falla como cuando no hay conexión con el SAT.
     */
    public function sinConexion(): self
    {
        $this->respuestas[] = null;

        return $this;
    }

    public function call(Request $request): Response
    {
        $this->peticiones[] = $request;

        if ($this->respuestas === []) {
            throw new RuntimeException("No hay respuesta preparada para {$request->getUri()}");
        }

        $respuesta = array_shift($this->respuestas);

        if ($respuesta instanceof WebClientException) {
            throw $respuesta;
        }

        return $respuesta
            ?? throw new WebClientException("Error connecting to {$request->getUri()}", $request, new Response(500, ''));
    }

    public function fireRequest(Request $request): void {}

    public function fireResponse(Response $response): void {}
}
