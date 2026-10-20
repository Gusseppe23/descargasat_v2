<?php

namespace DescargaSat\ConsultaCfdi\Http\Resources;

use DescargaSat\SatAutenticacion\Contracts\AcuseDeConsulta;
use DescargaSat\SatAutenticacion\Contracts\ValidacionEfos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resultado de la Consulta de CFDI en la forma propia de la API.
 *
 * @property AcuseDeConsulta $resource
 */
class ConsultaCfdiResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $efos = $this->resource->efos();

        return [
            'estado' => $this->resource->estado,
            'es_cancelable' => $this->resource->esCancelable,
            'estatus_cancelacion' => $this->resource->estatusCancelacion,
            'efos' => [
                'emisor_en_lista' => $efos === null ? null : $efos === ValidacionEfos::EnLaLista,
                'validacion' => $this->resource->validacionEfos,
                'descripcion' => $efos?->descripcion(),
            ],
            'codigo_estatus' => $this->resource->codigoEstatus,
        ];
    }
}
