<?php

namespace DescargaSat\Cancelaciones\Contracts;

/**
 * Estados de una Cancelación pendiente; ver CONTEXT.md.
 */
enum EstadoCancelacion: string
{
    case PorResponder = 'por_responder';
    case Enviando = 'enviando';
    case AceptacionEnviada = 'aceptacion_enviada';
    case RechazoEnviado = 'rechazo_enviado';
    case EnvioIncierto = 'envio_incierto';
    case Retirada = 'retirada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PorResponder => 'Por responder',
            self::Enviando => 'Enviando',
            self::AceptacionEnviada => 'Aceptación enviada',
            self::RechazoEnviado => 'Rechazo enviado',
            self::EnvioIncierto => 'Envío incierto',
            self::Retirada => 'Retirada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PorResponder => 'amber',
            self::Enviando => 'blue',
            self::AceptacionEnviada, self::RechazoEnviado => 'green',
            self::EnvioIncierto => 'red',
            self::Retirada => 'zinc',
        };
    }
}
