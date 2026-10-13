<?php

namespace DescargaSat\Cancelaciones\Contracts;

/**
 * Cómo terminó un Envío de respuesta.
 */
enum ResultadoDeEnvio: string
{
    /**
     * El SAT devolvió el Acuse de respuesta sellado.
     */
    case AcuseRecibido = 'acuse_recibido';

    /**
     * Se sabe que el SAT no la registró: respondió con un código de error o ni siquiera se envió.
     */
    case NoProcesada = 'no_procesada';

    /**
     * Terminó sin Acuse de respuesta, así que no se sabe si el SAT la registró.
     */
    case Incierto = 'incierto';

    public function etiqueta(): string
    {
        return match ($this) {
            self::AcuseRecibido => 'Acuse recibido',
            self::NoProcesada => 'No procesada',
            self::Incierto => 'Incierto',
        };
    }
}
