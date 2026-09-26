<?php

namespace DescargaSat\Solicitudes\Services;

use DescargaSat\Fiel\Contracts\UsoDeFiel;
use DescargaSat\Solicitudes\Contracts\EstadoSolicitud;
use DescargaSat\Solicitudes\Models\Solicitud;

/**
 * Responde al módulo fiel si una Fiel todavía tiene Solicitudes sin estado final.
 */
class SolicitudesEnCursoDeFiel implements UsoDeFiel
{
    public function tieneSolicitudesEnCurso(int $fielId): bool
    {
        return Solicitud::where('fiel_id', $fielId)
            ->whereIn('estado', array_filter(EstadoSolicitud::cases(), fn (EstadoSolicitud $estado): bool => $estado->enCurso()))
            ->exists();
    }
}
