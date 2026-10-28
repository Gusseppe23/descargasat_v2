<?php

namespace Tests\Support;

use DescargaSat\SatAutenticacion\Contracts\FabricaDeScraper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use LogicException;
use PhpCfdi\CfdiSatScraper\Contracts\SatScraperInterface;
use PhpCfdi\CfdiSatScraper\Filters\DownloadType;
use PhpCfdi\CfdiSatScraper\Metadata;
use PhpCfdi\CfdiSatScraper\MetadataList;
use PhpCfdi\CfdiSatScraper\QueryByFilters;
use PhpCfdi\CfdiSatScraper\ResourceDownloader;
use PhpCfdi\CfdiSatScraper\ResourceType;
use PhpCfdi\CfdiSatScraper\SatHttpGateway;
use PhpCfdi\CfdiSatScraper\SatScraper;
use PhpCfdi\Credentials\Credential;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Doble del scraper del portal de CFDI: la consulta devuelve los CFDI preparados y las descargas
 * pasan por el descargador real de la librería con respuestas HTTP preparadas por URL.
 */
final class ScraperFalso implements FabricaDeScraper, SatScraperInterface
{
    public ?Credential $credencial = null;

    /** @var list<array{uuids: list<string>, tipo: DownloadType}> */
    public array $consultas = [];

    /** @var list<string> */
    public array $descargas = [];

    /** @var array<string, Metadata> */
    private array $cfdis = [];

    /** @var array<string, Response|null> */
    private array $respuestas = [];

    private ?Throwable $errorDeConsulta = null;

    /**
     * Registra en el contenedor este doble como fábrica del scraper.
     */
    public static function usar(): self
    {
        $scraper = new self;
        app()->instance(FabricaDeScraper::class, $scraper);

        return $scraper;
    }

    /**
     * Un CFDI que el portal encuentra, con sus enlaces de descarga y demás datos de la consulta.
     *
     * @param  array<string, string>  $datos
     */
    public function conCfdi(string $uuid, array $datos = []): self
    {
        $this->cfdis[strtolower($uuid)] = new Metadata($uuid, $datos);

        return $this;
    }

    public function responderEn(string $url, string $contenido, int $estado = 200): self
    {
        $this->respuestas[$url] = new Response($estado, [], $contenido);

        return $this;
    }

    public function sinConexionEn(string $url): self
    {
        $this->respuestas[$url] = null;

        return $this;
    }

    public function fallarConsultaCon(Throwable $error): self
    {
        $this->errorDeConsulta = $error;

        return $this;
    }

    public function scraper(Credential $credencial): SatScraperInterface
    {
        $this->credencial = $credencial;

        return $this;
    }

    public function listByUuids(array $uuids, DownloadType $downloadType): MetadataList
    {
        $this->consultas[] = ['uuids' => array_values($uuids), 'tipo' => $downloadType];

        if ($this->errorDeConsulta !== null) {
            throw $this->errorDeConsulta;
        }

        return (new MetadataList(array_values($this->cfdis)))->filterWithUuids($uuids);
    }

    public function resourceDownloader(
        ?ResourceType $resourceType = null,
        ?MetadataList $metadataList = null,
        int $concurrency = ResourceDownloader::DEFAULT_CONCURRENCY,
    ): ResourceDownloader {
        $gateway = new SatHttpGateway(new Client(['handler' => fn (RequestInterface $peticion): PromiseInterface => $this->responder($peticion)]));

        return new ResourceDownloader($gateway, $resourceType ?? ResourceType::xml(), $metadataList, $concurrency);
    }

    public function confirmSessionIsAlive(): SatScraper
    {
        throw new LogicException('El portal se usa solo a través de listByUuids.');
    }

    public function listByPeriod(QueryByFilters $query): MetadataList
    {
        throw new LogicException('El portal se usa solo a través de listByUuids.');
    }

    public function listByDateTime(QueryByFilters $query): MetadataList
    {
        throw new LogicException('El portal se usa solo a través de listByUuids.');
    }

    private function responder(RequestInterface $peticion): PromiseInterface
    {
        $url = (string) $peticion->getUri();
        $this->descargas[] = $url;

        if (! array_key_exists($url, $this->respuestas)) {
            throw new LogicException("No hay respuesta preparada para {$url}");
        }

        return $this->respuestas[$url] === null
            ? Create::rejectionFor(new ConnectException("Error connecting to {$url}", $peticion))
            : Create::promiseFor($this->respuestas[$url]);
    }
}
