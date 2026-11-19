<?php

namespace DescargaSat\Solicitudes\Services;

use Carbon\CarbonImmutable;
use DescargaSat\Fiel\Contracts\Fieles;
use DescargaSat\SatAutenticacion\Contracts\Servicio;
use DescargaSat\Solicitudes\Actions\PresentarSolicitud;
use DescargaSat\Solicitudes\Contracts\DatosSolicitud;
use DescargaSat\Solicitudes\Contracts\EstadoSolicitud;
use DescargaSat\Solicitudes\Contracts\NoSePudoPresentarSolicitud;
use DescargaSat\Solicitudes\Contracts\Solicitudes;
use DescargaSat\Solicitudes\Enums\EstadoComprobante;
use DescargaSat\Solicitudes\Enums\TipoDescarga;
use DescargaSat\Solicitudes\Enums\TipoSolicitud;
use DescargaSat\Solicitudes\Models\Solicitud;
use DescargaSat\Solicitudes\Support\ParametrosSolicitud;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class SolicitudesGuardadas implements Solicitudes
{
    public function __construct(
        private Fieles $fieles,
        private PresentarSolicitud $presentarSolicitud,
    ) {}

    public function presentarPorUuid(string $uuid): int
    {
        // El SAT solo acepta dos veces los mismos parámetros en la vida del RFC; las Rechazadas no gastan intentos.
        $anterior = Solicitud::query()
            ->where('uuid', strtoupper($uuid))
            ->where('estado', '!=', EstadoSolicitud::Rechazada)
            ->value('id');

        if ($anterior !== null) {
            return $anterior;
        }

        $fiel = $this->fieles->activa();

        if ($fiel === null) {
            throw new NoSePudoPresentarSolicitud('No hay una Fiel activa para presentar la Solicitud por UUID.');
        }

        $hoy = CarbonImmutable::today();
        $parametros = new ParametrosSolicitud(
            Servicio::Cfdi, $hoy, $hoy, TipoDescarga::Recibidos, TipoSolicitud::Xml, null, EstadoComprobante::Todos, $uuid,
        );

        try {
            return ($this->presentarSolicitud)($parametros, $fiel->id, null)->id;
        } catch (ValidationException $error) {
            // Fiel no vigente o el SAT no respondió; el mensaje es el mismo que ve el usuario en el formulario.
            throw new NoSePudoPresentarSolicitud((string) Arr::first(Arr::flatten($error->errors())), previous: $error);
        }
    }

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
