<?php

namespace DescargaSat\Solicitudes\Services;

use DescargaSat\Solicitudes\Contracts\DatosSolicitud;
use DescargaSat\Solicitudes\Contracts\EstadoSolicitud;
use DescargaSat\Solicitudes\Contracts\Solicitudes;
use DescargaSat\Solicitudes\Models\Solicitud;

class SolicitudesGuardadas implements Solicitudes
{
    public function pendientes(): array
    {
        return Solicitud::query()
            ->whereIn('estado', [EstadoSolicitud::Aceptada, EstadoSolicitud::EnProceso])
            ->whereNotNull('id_solicitud_sat')
            ->oldest('id')
            ->get()
            ->map(fn (Solicitud $solicitud): DatosSolicitud => $this->datos($solicitud))
            ->all();
    }

    public function porId(int $id): ?DatosSolicitud
    {
        $solicitud = Solicitud::whereKey($id)->whereNotNull('id_solicitud_sat')->first();

        return $solicitud === null ? null : $this->datos($solicitud);
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

    private function datos(Solicitud $solicitud): DatosSolicitud
    {
        return new DatosSolicitud(
            id: $solicitud->id,
            fielId: $solicitud->fiel_id,
            servicio: $solicitud->servicio,
            idSolicitudSat: (string) $solicitud->id_solicitud_sat,
            presentadaEl: $solicitud->created_at ?? now(),
        );
    }
}
