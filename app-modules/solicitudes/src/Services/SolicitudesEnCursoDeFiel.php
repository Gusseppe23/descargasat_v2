<?php

namespace DescargaSat\Solicitudes\Services;

use DescargaSat\Fiel\Contracts\UsoDeFiel;
use DescargaSat\Solicitudes\Enums\EstadoSolicitud;
use DescargaSat\Solicitudes\Models\Solicitud;

/**
 * Responde al módulo fiel si una Fiel todavía tiene Solicitudes sin estado final.
 */
class SolicitudesEnCursoDeFiel implements UsoDeFiel
{
    public function tieneSolicitudesEnCurso(int $fielId): bool
    {
        return Solicitud::where('fiel_id', $fielId)
            ->whereIn('estado', [EstadoSolicitud::Aceptada, EstadoSolicitud::EnProceso, EstadoSolicitud::Terminada])
            ->exists();
    }
}
