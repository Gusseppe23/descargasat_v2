<?php

namespace DescargaSat\SatAutenticacion\Contracts;

/**
 * Un folio del Acuse de respuesta: el UUID, la respuesta que el SAT registró y su EstatusUUID.
 */
final readonly class FolioDeAcuse
{
    public function __construct(
        public string $uuid,
        public string $respuesta,
        public string $estatusUuid,
    ) {}
}
