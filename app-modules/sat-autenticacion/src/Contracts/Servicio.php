<?php

namespace DescargaSat\SatAutenticacion\Contracts;

/**
 * Cuál de los dos servicios de descarga masiva del SAT se usa.
 */
enum Servicio: string
{
    case Cfdi = 'cfdi';
    case Retenciones = 'retenciones';
}
