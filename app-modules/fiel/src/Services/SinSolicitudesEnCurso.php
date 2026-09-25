<?php

namespace DescargaSat\Fiel\Services;

use DescargaSat\Fiel\Contracts\UsoDeFiel;

/**
 * Respuesta por omisión mientras el módulo solicitudes no registre la suya.
 */
class SinSolicitudesEnCurso implements UsoDeFiel
{
    public function tieneSolicitudesEnCurso(int $fielId): bool
    {
        return false;
    }
}
