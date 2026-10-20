<?php

namespace DescargaSat\ConsultaCfdi\Http\Controllers;

use DescargaSat\ConsultaCfdi\Actions\ConsultarCfdi;
use DescargaSat\ConsultaCfdi\Http\Requests\ConsultarCfdiRequest;
use DescargaSat\ConsultaCfdi\Http\Resources\ConsultaCfdiResource;
use DescargaSat\SatAutenticacion\Contracts\AcuseDeConsulta;
use DescargaSat\SatAutenticacion\Contracts\CodigoConsultaCfdi;
use DescargaSat\SatAutenticacion\Contracts\ProblemaConElSat;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ConsultaCfdiController
{
    /**
     * Consulta al SAT el estado del CFDI. Un rechazo del SAT (601, 602) es una respuesta HTTP propia, no un error genérico.
     */
    public function __invoke(ConsultarCfdiRequest $request, ConsultarCfdi $consultarCfdi): JsonResponse
    {
        /** @var array{expresion_impresa?: string, rfc_emisor?: string, rfc_receptor?: string, total?: string|int|float, uuid?: string} $datos */
        $datos = $request->validated();

        try {
            $acuse = isset($datos['expresion_impresa'])
                ? $consultarCfdi->conExpresionImpresa($datos['expresion_impresa'])
                : $consultarCfdi($datos['rfc_emisor'] ?? '', $datos['rfc_receptor'] ?? '', (string) ($datos['total'] ?? ''), $datos['uuid'] ?? '');
        } catch (ProblemaConElSat $error) {
            Log::warning('No se pudo consultar un CFDI al SAT.', ['error' => $error->getMessage()]);

            return response()->json(['message' => $error->getMessage()], 502);
        }

        return match ($acuse->codigo()) {
            CodigoConsultaCfdi::ComprobanteObtenido => ConsultaCfdiResource::make($acuse)->response(),
            CodigoConsultaCfdi::ComprobanteNoEncontrado => $this->rechazo($acuse, 404),
            CodigoConsultaCfdi::ExpresionInvalida => $this->rechazo($acuse, 422),
            null => response()->json([
                'message' => 'El SAT respondió un código desconocido.',
                'codigo_estatus' => $acuse->codigoEstatus,
            ], 502),
        };
    }

    private function rechazo(AcuseDeConsulta $acuse, int $estadoHttp): JsonResponse
    {
        return response()->json([
            'message' => $acuse->codigo()?->descripcion(),
            'codigo_estatus' => $acuse->codigoEstatus,
        ], $estadoHttp);
    }
}
