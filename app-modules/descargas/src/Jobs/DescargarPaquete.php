<?php

namespace DescargaSat\Descargas\Jobs;

use DescargaSat\Paquetes\Contracts\PaqueteDanado;
use DescargaSat\Paquetes\Contracts\Paquetes;
use DescargaSat\SatAutenticacion\Contracts\ConexionSat;
use DescargaSat\SatAutenticacion\Contracts\FielNoVigente;
use DescargaSat\Solicitudes\Contracts\EstadoSolicitud;
use DescargaSat\Solicitudes\Contracts\Solicitudes;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Exceptions\WebClientException;
use RuntimeException;
use Throwable;

/**
 * Baja un Paquete del SAT, lo guarda y extrae su contenido.
 */
class DescargarPaquete implements ShouldQueue
{
    use Queueable;

    /**
     * El primer intento más 3 reintentos.
     */
    public int $tries = 4;

    public function __construct(public readonly int $paqueteId)
    {
        $this->onConnection('database');
    }

    /**
     * Espera antes de cada reintento: 1, 5 y 15 minutos.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /**
     * @throws RuntimeException cuando algo falla, para que la cola lo reintente.
     */
    public function handle(Paquetes $paquetes, Solicitudes $solicitudes, ConexionSat $conexionSat): void
    {
        $paquete = $paquetes->porId($this->paqueteId);
        $solicitud = $paquete === null ? null : $solicitudes->porId($paquete->solicitudId);

        if ($paquete === null || $solicitud === null) {
            return;
        }

        try {
            $resultado = $conexionSat->servicio($solicitud->fielId, $solicitud->servicio)->download($paquete->idPaqueteSat);
        } catch (FielNoVigente $error) {
            // Reintentar no arregla una Fiel vencida.
            $paquetes->anotarProblema($paquete->id, $error->getMessage());
            $paquetes->marcarFallido($paquete->id);
            $this->fail($error);

            return;
        } catch (WebClientException $error) {
            throw $this->problema($paquetes, "No se pudo contactar al SAT ({$error->getMessage()}).", $error);
        }

        if (! $resultado->getStatus()->isAccepted()) {
            throw $this->problema(
                $paquetes,
                "El SAT respondió {$resultado->getStatus()->getCode()}: {$resultado->getStatus()->getMessage()}",
            );
        }

        $paquetes->guardarZip($paquete->id, $resultado->getPackageContent());

        try {
            $this->extraerYMarcarDescargada($paquetes, $solicitudes, $paquete->id, $solicitud->id);
        } catch (PaqueteDanado $error) {
            throw $this->problema($paquetes, $error->getMessage(), $error);
        }
    }

    /**
     * Extrae el Paquete y, si era el último, deja la Solicitud Descargada. Los cambios en la base de datos
     * van en una transacción para que nadie vea el último Paquete Extraído con la Solicitud todavía Terminada;
     * si algo falla, los archivos ya extraídos quedan en disco y el reintento los sobrescribe.
     *
     * @throws PaqueteDanado
     */
    private function extraerYMarcarDescargada(Paquetes $paquetes, Solicitudes $solicitudes, int $paqueteId, int $solicitudId): void
    {
        DB::transaction(function () use ($paquetes, $solicitudes, $paqueteId, $solicitudId): void {
            $paquetes->extraer($paqueteId);
            $paquetes->anotarProblema($paqueteId, null);

            if ($paquetes->todosExtraidos($solicitudId)) {
                $solicitudes->cambiarEstado($solicitudId, EstadoSolicitud::Descargada);
            }
        });
    }

    /**
     * Se agotaron los intentos: el Paquete queda Fallido hasta que alguien lo reintente.
     */
    public function failed(?Throwable $error): void
    {
        app(Paquetes::class)->marcarFallido($this->paqueteId);
    }

    private function problema(Paquetes $paquetes, string $problema, ?Throwable $causa = null): RuntimeException
    {
        $paquetes->anotarProblema($this->paqueteId, $problema);

        return new RuntimeException($problema, previous: $causa);
    }
}
