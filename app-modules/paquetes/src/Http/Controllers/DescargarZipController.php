<?php

namespace DescargaSat\Paquetes\Http\Controllers;

use DescargaSat\Paquetes\Models\Paquete;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DescargarZipController
{
    /**
     * Entrega el ZIP guardado del Paquete; nunca lo pide al SAT.
     */
    public function __invoke(Paquete $paquete): StreamedResponse
    {
        $disco = Storage::disk('local');

        abort_unless($disco->exists($paquete->rutaZip()), 404);

        return $disco->download($paquete->rutaZip(), "{$paquete->id_paquete_sat}.zip");
    }
}
