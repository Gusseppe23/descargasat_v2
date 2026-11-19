<?php

namespace DescargaSat\Metadata\Jobs;

use Carbon\CarbonImmutable;
use DescargaSat\Metadata\Models\MetadataDeCfdi;
use DescargaSat\Paquetes\Contracts\Paquetes;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Lee el .txt de Metadata del Contenido de un Paquete y guarda cada renglón por UUID.
 * Un Paquete de XML no trae .txt, así que no guarda nada; el *_tercero.txt (CFDI a cuenta de terceros) se ignora.
 */
class GuardarMetadata implements ShouldQueue
{
    use Queueable;

    /**
     * Renglones por cada upsert, para no armar una sola consulta enorme con archivos de miles de renglones.
     */
    private const RENGLONES_POR_LOTE = 500;

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

        foreach ($disco->files($this->carpeta) as $archivo) {
            if (str_ends_with($archivo, '.txt') && ! str_ends_with($archivo, '_tercero.txt')) {
                $this->guardar($disco->path($archivo), basename($archivo), $paquetes);
            }
        }
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
