<?php

namespace DescargaSat\DescargaCfdi\Http\Resources;

use DescargaSat\SatAutenticacion\Contracts\ArchivosCfdi;
use DescargaSat\SatAutenticacion\Contracts\TipoCfdi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El XML y el PDF de un CFDI en base64. El archivo que el SAT no entregó va en null, con su motivo.
 *
 * @property ArchivosCfdi $resource
 */
class DescargaCfdiResource extends JsonResource
{
    public function __construct(ArchivosCfdi $archivos, private TipoCfdi $tipo)
    {
        parent::__construct($archivos);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->resource->uuid,
            'tipo' => $this->tipo->value,
            'xml' => $this->resource->xml === null ? null : base64_encode($this->resource->xml),
            'pdf' => $this->resource->pdf === null ? null : base64_encode($this->resource->pdf),
            'xml_no_disponible' => $this->resource->xmlNoDisponible,
            'pdf_no_disponible' => $this->resource->pdfNoDisponible,
        ];
    }
}
