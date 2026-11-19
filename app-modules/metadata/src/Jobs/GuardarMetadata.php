<?php

namespace DescargaSat\Metadata\Jobs;

use Carbon\CarbonImmutable;
use DescargaSat\Metadata\Models\MetadataDeCfdi;
use DescargaSat\Paquetes\Contracts\Paquetes;
use DOMDocument;
use DOMElement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Lee el Contenido de un Paquete y guarda la Metadata de CFDI por UUID: cada renglón del .txt de Metadata y cada
 * CFDI XML. El *_tercero.txt (CFDI a cuenta de terceros) se ignora.
 */
class GuardarMetadata implements ShouldQueue
{
    use Queueable;

    /**
     * Renglones por cada upsert, para no armar una sola consulta enorme con archivos de miles de renglones.
     */
    private const RENGLONES_POR_LOTE = 500;

    private const NS_TIMBRE = 'http://www.sat.gob.mx/TimbreFiscalDigital';

    /**
     * Columnas que debe traer el encabezado del .txt de Metadata del SAT.
     */
    private const COLUMNAS = [
        'Uuid', 'RfcEmisor', 'NombreEmisor', 'RfcReceptor', 'NombreReceptor', 'PacCertifico',
        'FechaEmision', 'FechaCertificacionSat', 'Monto', 'EfectoComprobante', 'Estatus', 'FechaCancelacion',
    ];

    public function __construct(public readonly int $paqueteId, public readonly string $carpeta)
    {
        $this->onConnection('database');
    }

    public function handle(Paquetes $paquetes): void
    {
        $disco = Storage::disk('local');

        $lote = [];
        $ilegibles = [];

        foreach ($disco->files($this->carpeta) as $archivo) {
            if (str_ends_with($archivo, '.txt') && ! str_ends_with($archivo, '_tercero.txt')) {
                $this->guardar($disco->path($archivo), basename($archivo), $paquetes);
            }

            if (str_ends_with($archivo, '.xml')) {
                $registro = $this->registroDeCfdi((string) $disco->get($archivo));

                if ($registro === null) {
                    $ilegibles[] = basename($archivo);

                    continue;
                }

                $lote[] = $registro;

                if (count($lote) === self::RENGLONES_POR_LOTE) {
                    $this->guardarLote($lote);
                    $lote = [];
                }
            }
        }

        $this->guardarLote($lote);

        if ($ilegibles !== []) {
            $paquetes->anotarProblema($this->paqueteId, sprintf(
                'No se pudo leer la Metadata de %d XML del Paquete: %s.',
                count($ilegibles),
                implode(', ', $ilegibles),
            ));
        }
    }

    /**
     * La Metadata de CFDI que sale del XML, o null si no se puede leer o le falta el Timbre o el Emisor. No trae estatus ni fecha de cancelación, así que no se incluyen
     * y el upsert conserva los que haya traído la Metadata del SAT.
     *
     * @return array<string, mixed>|null
     */
    private function registroDeCfdi(string $xml): ?array
    {
        $documento = new DOMDocument;

        if ($xml === '' || ! $documento->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return null;
        }

        $comprobante = $documento->documentElement;
        $timbre = $documento->getElementsByTagNameNS(self::NS_TIMBRE, 'TimbreFiscalDigital')->item(0);
        $emisor = $this->hijo($comprobante, 'Emisor');
        $receptor = $this->hijo($comprobante, 'Receptor');
        $fechaEmision = $this->fechaDeCfdi((string) $comprobante?->getAttribute('Fecha'));

        if (! $timbre instanceof DOMElement || $timbre->getAttribute('UUID') === '' || $emisor === null || $fechaEmision === null) {
            return null;
        }

        return [
            'uuid' => strtoupper(trim($timbre->getAttribute('UUID'))),
            'rfc_emisor' => $emisor->getAttribute('Rfc'),
            'nombre_emisor' => $emisor->getAttribute('Nombre') ?: null,
            'rfc_receptor' => (string) $receptor?->getAttribute('Rfc'),
            'nombre_receptor' => $receptor?->getAttribute('Nombre') ?: null,
            'rfc_pac' => $timbre->getAttribute('RfcProvCertif') ?: null,
            'fecha_emision' => $fechaEmision,
            'fecha_certificacion_sat' => $this->fechaDeCfdi($timbre->getAttribute('FechaTimbrado')),
            'monto' => (string) $comprobante?->getAttribute('Total'),
            'efecto' => (string) $comprobante?->getAttribute('TipoDeComprobante'),
            'paquete_id' => $this->paqueteId,
        ];
    }

    /**
     * El primer hijo directo con ese nombre local; un complemento (por ejemplo Comercio Exterior) también trae Emisor.
     */
    private function hijo(?DOMElement $padre, string $nombre): ?DOMElement
    {
        if ($padre === null) {
            return null;
        }

        foreach ($padre->childNodes as $nodo) {
            if ($nodo instanceof DOMElement && $nodo->localName === $nombre) {
                return $nodo;
            }
        }

        return null;
    }

    private function fechaDeCfdi(string $valor): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $valor) !== 1) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d\TH:i:s', $valor)?->format('Y-m-d H:i:s');
    }

    private function guardar(string $ruta, string $nombre, Paquetes $paquetes): void
    {
        $archivo = fopen($ruta, 'r');

        if ($archivo === false) {
            return;
        }

        try {
            $encabezado = explode('~', rtrim((string) fgets($archivo), "\r\n"));

            if (array_diff(self::COLUMNAS, $encabezado) !== []) {
                $paquetes->anotarProblema($this->paqueteId, "El archivo {$nombre} no tiene el encabezado de la Metadata del SAT; no se guardó su Metadata.");

                return;
            }

            $lote = [];

            while (($linea = fgets($archivo)) !== false) {
                $linea = rtrim($linea, "\r\n");

                if ($linea === '') {
                    continue;
                }

                $lote[] = $this->registro(array_combine($encabezado, explode('~', $linea)));

                if (count($lote) === self::RENGLONES_POR_LOTE) {
                    $this->guardarLote($lote);
                    $lote = [];
                }
            }

            $this->guardarLote($lote);
        } finally {
            fclose($archivo);
        }
    }

    /**
     * @param  array<string, string>  $campos
     * @return array<string, mixed>
     */
    private function registro(array $campos): array
    {
        return [
            'uuid' => strtoupper(trim($campos['Uuid'])),
            'rfc_emisor' => $campos['RfcEmisor'],
            'nombre_emisor' => $campos['NombreEmisor'] !== '' ? $campos['NombreEmisor'] : null,
            'rfc_receptor' => $campos['RfcReceptor'],
            'nombre_receptor' => $campos['NombreReceptor'] !== '' ? $campos['NombreReceptor'] : null,
            'rfc_pac' => $campos['PacCertifico'] !== '' ? $campos['PacCertifico'] : null,
            'fecha_emision' => $this->fecha($campos['FechaEmision']),
            'fecha_certificacion_sat' => $this->fecha($campos['FechaCertificacionSat']),
            'monto' => $campos['Monto'],
            'efecto' => $campos['EfectoComprobante'],
            'estatus' => $campos['Estatus'],
            'fecha_cancelacion' => $this->fecha($campos['FechaCancelacion']),
            'paquete_id' => $this->paqueteId,
        ];
    }

    private function fecha(string $valor): ?string
    {
        return $valor === '' ? null : CarbonImmutable::createFromFormat('Y-m-d H:i:s', $valor)?->format('Y-m-d H:i:s');
    }

    /**
     * @param  list<array<string, mixed>>  $lote
     */
    private function guardarLote(array $lote): void
    {
        if ($lote === []) {
            return;
        }

        MetadataDeCfdi::upsert($lote, ['uuid'], array_keys(array_diff_key($lote[0], ['uuid' => true])));
    }
}
