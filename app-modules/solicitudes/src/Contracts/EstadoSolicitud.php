<?php

namespace DescargaSat\Solicitudes\Contracts;

/**
 * Estados de una Solicitud; ver CONTEXT.md.
 */
enum EstadoSolicitud: string
{
    case Aceptada = 'aceptada';
    case EnProceso = 'en_proceso';
    case Terminada = 'terminada';
    case Descargada = 'descargada';
    case SinResultados = 'sin_resultados';
    case Rechazada = 'rechazada';
    case Error = 'error';
    case Vencida = 'vencida';
    case Abandonada = 'abandonada';

    /**
     * Sin estado final: el SAT o la descarga todavía pueden cambiarla.
     */
    public function enCurso(): bool
    {
        return in_array($this, [self::Aceptada, self::EnProceso, self::Terminada], true);
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Aceptada => 'Aceptada',
            self::EnProceso => 'En proceso',
            self::Terminada => 'Terminada',
            self::Descargada => 'Descargada',
            self::SinResultados => 'Sin resultados',
            self::Rechazada => 'Rechazada',
            self::Error => 'Error',
            self::Vencida => 'Vencida',
            self::Abandonada => 'Abandonada',
        };
    }
}
