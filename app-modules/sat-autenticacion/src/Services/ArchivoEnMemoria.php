<?php

namespace DescargaSat\SatAutenticacion\Services;

use PhpCfdi\CfdiSatScraper\Contracts\ResourceDownloadHandlerInterface;
use PhpCfdi\CfdiSatScraper\Exceptions\ResourceDownloadError;
use Psr\Http\Message\ResponseInterface;

/**
 * Recibe la descarga de un archivo del portal y guarda en memoria su contenido o el error, para que una descarga fallida no pase desapercibida.
 */
class ArchivoEnMemoria implements ResourceDownloadHandlerInterface
{
    public ?string $contenido = null;

    public ?string $error = null;

    public function onSuccess(string $uuid, string $content, ResponseInterface $response): void
    {
        $this->contenido = $content;
    }

    public function onError(ResourceDownloadError $error): void
    {
        $this->error = $error->getMessage();
    }
}
