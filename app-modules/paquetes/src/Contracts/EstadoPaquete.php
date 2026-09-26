<?php

namespace DescargaSat\Paquetes\Contracts;

/**
 * Estados de un Paquete; ver CONTEXT.md.
 */
enum EstadoPaquete: string
{
    case Pendiente = 'pendiente';
    case Descargado = 'descargado';
    case Extraido = 'extraido';
    case Fallido = 'fallido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Descargado => 'Descargado',
            self::Extraido => 'Extraído',
            self::Fallido => 'Fallido',
        };
    }
}
