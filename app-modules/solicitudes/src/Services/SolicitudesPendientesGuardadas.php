<?php

namespace DescargaSat\Solicitudes\Services;

use DescargaSat\Solicitudes\Contracts\EstadoSolicitud;
use DescargaSat\Solicitudes\Contracts\SolicitudesPendientes;
use DescargaSat\Solicitudes\Contracts\SolicitudPendiente;
use DescargaSat\Solicitudes\Models\Solicitud;

class SolicitudesPendientesGuardadas implements SolicitudesPendientes
{
    public function pendientes(): array
    {
        return Solicitud::query()
            ->whereIn('estado', [EstadoSolicitud::Aceptada, EstadoSolicitud::EnProceso])
            ->whereNotNull('id_solicitud_sat')
            ->oldest('id')
            ->get()
            ->map(fn (Solicitud $solicitud): SolicitudPendiente => new SolicitudPendiente(
                id: $solicitud->id,
                fielId: $solicitud->fiel_id,
                servicio: $solicitud->servicio,
                idSolicitudSat: (string) $solicitud->id_solicitud_sat,
                presentadaEl: $solicitud->created_at ?? now(),
            ))
            ->all();
    }

    public function cambiarEstado(int $id, EstadoSolicitud $estado, ?string $mensaje = null): void
    {
        $solicitud = Solicitud::findOrFail($id);
        $solicitud->estado = $estado;

        if ($mensaje !== null) {
            $solicitud->mensaje_sat = $mensaje;
        }

        $solicitud->save();
    }

    public function anotarIntento(int $id, ?string $problema): void
    {
        Solicitud::whereKey($id)->update([
            'verificada_el' => now(),
            'ultimo_problema' => $problema === null ? null : mb_substr($problema, 0, 255),
        ]);
    }
}
