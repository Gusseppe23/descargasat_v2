<?php

namespace DescargaSat\Verificacion\Actions;

use DescargaSat\Paquetes\Contracts\Paquetes;
use DescargaSat\Solicitudes\Contracts\DatosSolicitud;
use DescargaSat\Solicitudes\Contracts\EstadoSolicitud;
use DescargaSat\Solicitudes\Contracts\Solicitudes;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\Services\Verify\VerifyResult;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Exceptions\WebClientException;

class VerificarSolicitud
{
    /**
     * Horas desde que se presentó tras las cuales se deja de verificar una Solicitud sin estado final.
     */
    public const HORAS_PARA_ABANDONAR = 72;

    public function __construct(
        private Solicitudes $solicitudes,
        private Paquetes $paquetes,
    ) {}

    /**
     * Pregunta al SAT por la Solicitud y traduce su respuesta a un estado.
     *
     * Si el SAT no responde bien, el estado no cambia: se anota el problema y se reintenta en la siguiente vuelta.
     */
    public function __invoke(DatosSolicitud $solicitud, Service $servicio): void
    {
        try {
            $resultado = $servicio->verify($solicitud->idSolicitudSat);
        } catch (WebClientException $error) {
            $this->solicitudes->anotarIntento($solicitud->id, "No se pudo contactar al SAT ({$error->getMessage()}).");
            $this->abandonarSiSeAgotoElPlazo($solicitud);

            return;
        }

        if (! $resultado->getStatus()->isAccepted()) {
            $this->solicitudes->anotarIntento(
                $solicitud->id,
                "El SAT respondió {$resultado->getStatus()->getCode()}: {$resultado->getStatus()->getMessage()}",
            );
            $this->abandonarSiSeAgotoElPlazo($solicitud);

            return;
        }

        $this->solicitudes->anotarIntento($solicitud->id, null);
        $estado = $this->estado($resultado);

        $mensaje = in_array($estado, [EstadoSolicitud::Error, EstadoSolicitud::Rechazada], true)
            ? $resultado->getCodeRequest()->getMessage()
            : null;

        if ($estado === EstadoSolicitud::Terminada) {
            $this->paquetes->registrarPendientes($solicitud->id, $resultado->getPackagesIds());
        }

        $this->solicitudes->cambiarEstado($solicitud->id, $estado, $mensaje);

        if (in_array($estado, [EstadoSolicitud::Aceptada, EstadoSolicitud::EnProceso], true)) {
            $this->abandonarSiSeAgotoElPlazo($solicitud);
        }
    }

    private function abandonarSiSeAgotoElPlazo(DatosSolicitud $solicitud): void
    {
        if ($solicitud->presentadaEl->addHours(self::HORAS_PARA_ABANDONAR)->isFuture()) {
            return;
        }

        $this->solicitudes->cambiarEstado(
            $solicitud->id,
            EstadoSolicitud::Abandonada,
            'El SAT no dio un estado final en las '.self::HORAS_PARA_ABANDONAR.' horas siguientes a la presentación.',
        );
    }

    private function estado(VerifyResult $resultado): EstadoSolicitud
    {
        $estadoSat = $resultado->getStatusRequest();

        return match (true) {
            $estadoSat->isInProgress() => EstadoSolicitud::EnProceso,
            $estadoSat->isFinished() && $resultado->countPackages() === 0 => EstadoSolicitud::SinResultados,
            $estadoSat->isFinished() => EstadoSolicitud::Terminada,
            $estadoSat->isFailure() => EstadoSolicitud::Error,
            $estadoSat->isRejected() => EstadoSolicitud::Rechazada,
            $estadoSat->isExpired() => EstadoSolicitud::Vencida,
            default => EstadoSolicitud::Aceptada,
        };
    }
}
