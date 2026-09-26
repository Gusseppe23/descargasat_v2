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

    /**
     * El Paquete todavía se está descargando o extrayendo.
     */
    public function enCurso(): bool
    {
        return in_array($this, [self::Pendiente, self::Descargado], true);
    }

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
