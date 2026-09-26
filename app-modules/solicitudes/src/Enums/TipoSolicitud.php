<?php

namespace DescargaSat\Solicitudes\Enums;

enum TipoSolicitud: string
{
    case Xml = 'xml';
    case Metadata = 'metadata';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Xml => 'XML',
            self::Metadata => 'Metadata',
        };
    }
}
