<?php

namespace DescargaSat\Cancelaciones\Actions;

use DescargaSat\Cancelaciones\Contracts\EstadoCancelacion;
use DescargaSat\Cancelaciones\Models\CancelacionPendiente;
use DescargaSat\Cancelaciones\Models\RevisionDeCancelaciones;
use DescargaSat\Csd\Contracts\Csds;
use DescargaSat\SatAutenticacion\Contracts\CodigoCancelacion;
use DescargaSat\SatAutenticacion\Contracts\PeticionesPendientes;
use DescargaSat\SatAutenticacion\Contracts\ProblemaConElSat;
use DescargaSat\SatAutenticacion\Contracts\ServicioCancelaciones;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RevisarCancelaciones
{
    /**
     * Segundos que dura el candado; una Revisión normal termina mucho antes.
     */
    public const SEGUNDOS_DEL_CANDADO = 300;

    public function __construct(
        private Csds $csds,
        private ServicioCancelaciones $servicioCancelaciones,
    ) {}

    /**
     * Pregunta al SAT por las Cancelaciones pendientes con el CSD activo y las guarda.
     *
     * Devuelve null, sin contactar al SAT, si ya hay otra Revisión en curso.
     */
    public function __invoke(): ?RevisionDeCancelaciones
    {
        $revision = Cache::lock('cancelaciones:revision', self::SEGUNDOS_DEL_CANDADO)->get(fn (): RevisionDeCancelaciones => $this->revisar());

        return $revision instanceof RevisionDeCancelaciones ? $revision : null;
    }

    private function revisar(): RevisionDeCancelaciones
    {
        $csd = $this->csds->activo();

        if ($csd === null) {
            return RevisionDeCancelaciones::create(['problema' => 'No hay un CSD activo. Activa uno en la pantalla CSD.']);
        }

        try {
            $pendientes = $this->servicioCancelaciones->pendientes($csd->id);
        } catch (ProblemaConElSat $error) {
            return RevisionDeCancelaciones::create(['csd_id' => $csd->id, 'problema' => $error->getMessage()]);
        }

        DB::transaction(function () use ($pendientes): void {
            foreach (array_unique($pendientes->uuids) as $uuid) {
                $this->registrar($uuid);
            }

            if (in_array($pendientes->codigo(), [CodigoCancelacion::HayPendientes, CodigoCancelacion::SinSolicitudes], true)) {
                CancelacionPendiente::where('estado', EstadoCancelacion::PorResponder)
                    ->whereNotIn('uuid', $pendientes->uuids)
                    ->update(['estado' => EstadoCancelacion::Retirada]);
            }
        });

        return RevisionDeCancelaciones::create([
            'csd_id' => $csd->id,
            'codigo_sat' => $pendientes->codEstatus,
            'pendientes_reportadas' => count($pendientes->uuids),
            'problema' => $this->problema($pendientes),
        ]);
    }

    /**
     * Da de alta la Cancelación pendiente o anota que se volvió a detectar; si estaba Retirada, vuelve a Por responder.
     */
    private function registrar(string $uuid): void
    {
        $cancelacion = CancelacionPendiente::firstOrNew(['uuid' => $uuid], [
            'estado' => EstadoCancelacion::PorResponder,
            'primera_deteccion_el' => now(),
        ]);

        if ($cancelacion->estado === EstadoCancelacion::Retirada) {
            $cancelacion->estado = EstadoCancelacion::PorResponder;
        }

        $cancelacion->ultima_deteccion_el = now();
        $cancelacion->save();
    }

    private function problema(PeticionesPendientes $pendientes): ?string
    {
        $codigo = $pendientes->codigo();

        return match ($codigo) {
            CodigoCancelacion::HayPendientes, CodigoCancelacion::SinSolicitudes, CodigoCancelacion::SinPeticionesPorAhora => null,
            null => "El SAT respondió un código desconocido: {$pendientes->codEstatus}.",
            default => "El SAT respondió {$codigo->value}: {$codigo->descripcion()}.",
        };
    }
}
