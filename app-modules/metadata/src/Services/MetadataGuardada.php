<?php

namespace DescargaSat\Metadata\Services;

use DescargaSat\Metadata\Contracts\DatosMetadata;
use DescargaSat\Metadata\Contracts\MetadataCfdi;
use DescargaSat\Metadata\Models\MetadataDeCfdi;

class MetadataGuardada implements MetadataCfdi
{
    public function deUuids(array $uuids): array
    {
        $metadata = [];

        foreach (MetadataDeCfdi::whereIn('uuid', array_map(strtoupper(...), $uuids))->get() as $registro) {
            $metadata[$registro->uuid] = new DatosMetadata(
                uuid: $registro->uuid,
                rfcEmisor: $registro->rfc_emisor,
                nombreEmisor: $registro->nombre_emisor,
                rfcReceptor: $registro->rfc_receptor,
                nombreReceptor: $registro->nombre_receptor,
                rfcPac: $registro->rfc_pac,
                fechaEmision: $registro->fecha_emision,
                fechaCertificacionSat: $registro->fecha_certificacion_sat,
                monto: $registro->monto,
                efecto: $registro->efecto,
                estatus: $registro->estatus,
                fechaCancelacion: $registro->fecha_cancelacion,
            );
        }

        return $metadata;
    }

    public function sinMetadata(array $uuids): array
    {
        $uuids = array_values(array_unique(array_map(strtoupper(...), $uuids)));
        $conMetadata = MetadataDeCfdi::whereIn('uuid', $uuids)->pluck('uuid')->all();

        return array_values(array_diff($uuids, $conMetadata));
    }
}
