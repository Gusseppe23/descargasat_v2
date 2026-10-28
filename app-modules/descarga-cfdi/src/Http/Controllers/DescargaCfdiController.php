<?php

namespace DescargaSat\DescargaCfdi\Http\Controllers;

use DescargaSat\DescargaCfdi\Actions\DescargarCfdi;
use DescargaSat\DescargaCfdi\Exceptions\SinFielActiva;
use DescargaSat\DescargaCfdi\Http\Requests\DescargarCfdiRequest;
use DescargaSat\DescargaCfdi\Http\Resources\DescargaCfdiResource;
use DescargaSat\SatAutenticacion\Contracts\ArchivoNoDisponible;
use DescargaSat\SatAutenticacion\Contracts\CfdiNoEncontrado;
use DescargaSat\SatAutenticacion\Contracts\FielNoVigente;
use DescargaSat\SatAutenticacion\Contracts\ProblemaConElSat;
use DescargaSat\SatAutenticacion\Contracts\TipoCfdi;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DescargaCfdiController
{
    /**
     * Descarga del portal del SAT el XML y el PDF del CFDI. Cada motivo por el que no se puede es una respuesta HTTP propia.
     */
    public function __invoke(DescargarCfdiRequest $request, DescargarCfdi $descargarCfdi): JsonResponse
    {
        /** @var array{uuid: string, tipo: string} $datos */
        $datos = $request->validated();
        $tipo = TipoCfdi::from($datos['tipo']);

        try {
            $archivos = $descargarCfdi($datos['uuid'], $tipo);
        } catch (CfdiNoEncontrado $error) {
            return response()->json(['message' => $error->getMessage()], 404);
        } catch (SinFielActiva|FielNoVigente $error) {
            return response()->json(['message' => $error->getMessage()], 409);
        } catch (ArchivoNoDisponible $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        } catch (ProblemaConElSat $error) {
            Log::warning('No se pudo descargar un CFDI del portal del SAT.', ['error' => $error->getMessage()]);

            return response()->json(['message' => $error->getMessage()], 502);
        }

        return (new DescargaCfdiResource($archivos, $tipo))->response();
    }
}
