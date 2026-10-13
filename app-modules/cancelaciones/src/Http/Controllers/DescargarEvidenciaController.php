<?php

namespace DescargaSat\Cancelaciones\Http\Controllers;

use DescargaSat\Cancelaciones\Models\EnvioDeRespuesta;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DescargarEvidenciaController
{
    /**
     * Entrega el XML enviado o el recibido de un Envío de respuesta.
     */
    public function __invoke(EnvioDeRespuesta $envio, string $archivo): StreamedResponse
    {
        $ruta = $archivo === 'enviado' ? $envio->ruta_enviado : $envio->ruta_recibido;
        $disco = Storage::disk('local');

        abort_unless($ruta !== null && $disco->exists($ruta), 404);

        return $disco->download($ruta, "{$envio->cancelacion->uuid}-envio-{$envio->id}-{$archivo}.xml");
    }
}
