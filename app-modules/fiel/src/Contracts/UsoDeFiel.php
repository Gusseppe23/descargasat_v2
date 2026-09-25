<?php

namespace DescargaSat\Fiel\Contracts;

/**
 * Pregunta que el módulo fiel hace a quien use las Fiel (el módulo solicitudes).
 *
 * Mientras nadie la implemente, la respuesta es que no hay Solicitudes en curso.
 */
interface UsoDeFiel
{
    public function tieneSolicitudesEnCurso(int $fielId): bool;
}
