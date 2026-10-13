<?php

namespace DescargaSat\SatAutenticacion\Contracts;

/**
 * Lo que se le contesta al SAT en una Respuesta de cancelación; el valor es el que exige el SAT (sin acento).
 */
enum Respuesta: string
{
    case Aceptacion = 'Aceptacion';
    case Rechazo = 'Rechazo';
}
