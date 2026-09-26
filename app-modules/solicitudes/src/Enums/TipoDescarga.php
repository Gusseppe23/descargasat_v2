<?php

namespace DescargaSat\Solicitudes\Enums;

enum TipoDescarga: string
{
    case Emitidos = 'emitidos';
    case Recibidos = 'recibidos';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Emitidos => 'Emitidos',
            self::Recibidos => 'Recibidos',
        };
    }
}
