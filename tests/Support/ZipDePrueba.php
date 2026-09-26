<?php

namespace Tests\Support;

use RuntimeException;
use ZipArchive;

/**
 * Arma al vuelo el contenido binario de un ZIP con los archivos indicados.
 */
final class ZipDePrueba
{
    /**
     * @param  array<string, string>  $archivos  nombre dentro del ZIP => contenido
     */
    public static function con(array $archivos): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;

        if ($ruta === false || $zip->open($ruta, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el ZIP de prueba');
        }

        foreach ($archivos as $nombre => $contenido) {
            $zip->addFromString($nombre, $contenido);
        }

        $zip->close();
        $contenido = (string) file_get_contents($ruta);
        unlink($ruta);

        return $contenido;
    }
}
