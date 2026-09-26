<?php

namespace Tests\Support;

use PhpCfdi\SatWsDescargaMasiva\WebClient\Request;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Response;
use PhpCfdi\SatWsDescargaMasiva\WebClient\WebClientInterface;
use RuntimeException;

/**
 * Doble del cliente web del SAT: guarda cada petición y responde en orden con respuestas preparadas.
 */
final class WebClientFalso implements WebClientInterface
{
    /** @var list<Request> */
    public array $peticiones = [];

    /** @var list<Response> */
    private array $respuestas = [];

    public function responder(string $fixture, int $estado = 200): self
    {
        $this->respuestas[] = new Response($estado, (string) file_get_contents(base_path("tests/Fixtures/sat/{$fixture}")));

        return $this;
    }

    public function call(Request $request): Response
    {
        $this->peticiones[] = $request;

        return array_shift($this->respuestas)
            ?? throw new RuntimeException("No hay respuesta preparada para {$request->getUri()}");
    }

    public function fireRequest(Request $request): void {}

    public function fireResponse(Response $response): void {}
}
