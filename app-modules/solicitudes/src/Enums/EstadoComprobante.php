<?php

namespace DescargaSat\Solicitudes\Enums;

enum EstadoComprobante: string
{
    case Todos = 'todos';
    case Vigentes = 'vigentes';
    case Cancelados = 'cancelados';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Todos => 'Todos',
            self::Vigentes => 'Vigentes',
            self::Cancelados => 'Cancelados',
        };
    }
}
