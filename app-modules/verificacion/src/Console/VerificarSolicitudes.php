<?php

namespace DescargaSat\Verificacion\Console;

use DescargaSat\SatAutenticacion\Contracts\ConexionSat;
use DescargaSat\SatAutenticacion\Contracts\FielNoVigente;
use DescargaSat\Solicitudes\Contracts\EstadoSolicitud;
use DescargaSat\Solicitudes\Contracts\SolicitudesPendientes;
use DescargaSat\Verificacion\Actions\VerificarSolicitud;
use Illuminate\Console\Command;
use PhpCfdi\SatWsDescargaMasiva\Service;

class VerificarSolicitudes extends Command
{
    protected $signature = 'sat:verificar';

    protected $description = 'Pregunta al SAT por las Solicitudes que todavía no tienen estado final';

    public function handle(SolicitudesPendientes $solicitudes, ConexionSat $conexionSat, VerificarSolicitud $verificarSolicitud): int
    {
        /** @var array<string, Service> $servicios Un Service por Fiel y Servicio en cada vuelta. */
        $servicios = [];

        foreach ($solicitudes->pendientes() as $solicitud) {
            $clave = $solicitud->fielId.'-'.$solicitud->servicio->value;

            try {
                $servicios[$clave] ??= $conexionSat->servicio($solicitud->fielId, $solicitud->servicio);
            } catch (FielNoVigente $error) {
                $solicitudes->cambiarEstado($solicitud->id, EstadoSolicitud::Error, $error->getMessage());

                continue;
            }

            $verificarSolicitud($solicitud, $servicios[$clave]);
        }

        return self::SUCCESS;
    }
}
