<?php

namespace DescargaSat\SatAutenticacion\Services;

use DescargaSat\SatAutenticacion\Contracts\ArchivoNoDisponible;
use DescargaSat\SatAutenticacion\Contracts\ArchivosCfdi;
use DescargaSat\SatAutenticacion\Contracts\CfdiNoEncontrado;
use DescargaSat\SatAutenticacion\Contracts\FabricaDeScraper;
use DescargaSat\SatAutenticacion\Contracts\PortalCfdi;
use DescargaSat\SatAutenticacion\Contracts\ProblemaConElSat;
use DescargaSat\SatAutenticacion\Contracts\TipoCfdi;
use Illuminate\Support\Facades\Log;
use PhpCfdi\CfdiSatScraper\Contracts\SatScraperInterface;
use PhpCfdi\CfdiSatScraper\Exceptions\LoginException;
use PhpCfdi\CfdiSatScraper\Exceptions\SatException;
use PhpCfdi\CfdiSatScraper\Metadata;
use PhpCfdi\CfdiSatScraper\MetadataList;
use PhpCfdi\CfdiSatScraper\ResourceType;

class PortalCfdiSat implements PortalCfdi
{
    public function __construct(
        private FielVigente $fielVigente,
        private FabricaDeScraper $fabrica,
    ) {}

    public function descargar(int $fielId, string $uuid, TipoCfdi $tipo): ArchivosCfdi
    {
        $scraper = $this->fabrica->scraper($this->fielVigente->credencial($fielId));
        $cfdi = $this->buscar($scraper, $uuid, $tipo);

        $xml = $this->descargarArchivo($scraper, $cfdi, ResourceType::xml());
        $pdf = $this->descargarArchivo($scraper, $cfdi, ResourceType::pdf());
        $xmlNoDisponible = $this->motivoDelFaltante($cfdi, $xml, 'XML');
        $pdfNoDisponible = $this->motivoDelFaltante($cfdi, $pdf, 'PDF');

        if ($xml->contenido === null && $pdf->contenido === null) {
            throw new ArchivoNoDisponible("El SAT no entregó ni el XML ni el PDF del CFDI {$uuid}. {$xmlNoDisponible} {$pdfNoDisponible}");
        }

        return new ArchivosCfdi(
            uuid: $uuid,
            xml: $xml->contenido,
            pdf: $pdf->contenido,
            xmlNoDisponible: $xmlNoDisponible,
            pdfNoDisponible: $pdfNoDisponible,
        );
    }

    /**
     * Inicia sesión (si hace falta) y busca el CFDI en el portal.
     *
     * @throws CfdiNoEncontrado
     * @throws ProblemaConElSat
     */
    private function buscar(SatScraperInterface $scraper, string $uuid, TipoCfdi $tipo): Metadata
    {
        try {
            $cfdi = $scraper->listByUuids([$uuid], $tipo->downloadType())->find($uuid);
        } catch (LoginException $error) {
            throw new ProblemaConElSat("No se pudo iniciar sesión en el portal del SAT ({$error->getMessage()}).", previous: $error);
        } catch (SatException $error) {
            throw new ProblemaConElSat("No se pudo consultar el portal del SAT ({$error->getMessage()}).", previous: $error);
        }

        if ($cfdi === null) {
            $entre = $tipo === TipoCfdi::Emitido ? 'emitidos' : 'recibidos';

            throw new CfdiNoEncontrado("El SAT no tiene el CFDI {$uuid} entre los {$entre} de la Fiel.");
        }

        return $cfdi;
    }

    /**
     * Descarga un archivo del CFDI. Si el portal no ofrece el enlace, no se hace ninguna petición y el archivo queda vacío.
     */
    private function descargarArchivo(SatScraperInterface $scraper, Metadata $cfdi, ResourceType $tipo): ArchivoEnMemoria
    {
        $archivo = new ArchivoEnMemoria;
        $scraper->resourceDownloader($tipo, new MetadataList([$cfdi]))->download($archivo);

        if ($archivo->error !== null) {
            Log::warning('El portal del SAT no entregó un archivo de un CFDI.', [
                'uuid' => $cfdi->uuid(),
                'archivo' => $tipo->value(),
                'error' => $archivo->error,
            ]);
        }

        return $archivo;
    }

    private function motivoDelFaltante(Metadata $cfdi, ArchivoEnMemoria $archivo, string $nombre): ?string
    {
        if ($archivo->contenido !== null) {
            return null;
        }

        if ($archivo->error !== null) {
            return "El SAT no entregó el {$nombre} ({$archivo->error}).";
        }

        if (strcasecmp(trim($cfdi->get('estadoComprobante')), 'Cancelado') === 0) {
            return "El CFDI está cancelado y el portal del SAT no ofrece su {$nombre}.";
        }

        return "El portal del SAT no ofrece el {$nombre} de este CFDI.";
    }
}
