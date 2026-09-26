<?php

namespace DescargaSat\Solicitudes\Enums;

/**
 * Tipo de CFDI; solo aplica al Servicio de CFDI.
 */
enum TipoComprobante: string
{
    case Ingreso = 'I';
    case Egreso = 'E';
    case Traslado = 'T';
    case Nomina = 'N';
    case Pago = 'P';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Ingreso => 'Ingreso',
            self::Egreso => 'Egreso',
            self::Traslado => 'Traslado',
            self::Nomina => 'Nómina',
            self::Pago => 'Pago',
        };
    }
}
