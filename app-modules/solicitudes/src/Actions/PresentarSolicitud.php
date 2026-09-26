<?php

namespace DescargaSat\Solicitudes\Actions;

use App\Models\User;
use DescargaSat\SatAutenticacion\Contracts\ConexionSat;
use DescargaSat\SatAutenticacion\Contracts\FielNoVigente;
use DescargaSat\Solicitudes\Contracts\EstadoSolicitud;
use DescargaSat\Solicitudes\Models\Solicitud;
use DescargaSat\Solicitudes\Support\ParametrosSolicitud;
use Illuminate\Validation\ValidationException;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Exceptions\WebClientException;

class PresentarSolicitud
{
    public function __construct(private ConexionSat $conexionSat) {}

    /**
     * Una Solicitud que el SAT ya aceptó con los mismos parámetros; las Rechazadas no gastan intentos.
     */
    public function duplicadaDe(ParametrosSolicitud $parametros): ?Solicitud
    {
        return Solicitud::query()
            ->where('servicio', $parametros->servicio)
            ->whereDate('fecha_inicio', $parametros->fechaInicio)
            ->whereDate('fecha_fin', $parametros->fechaFin)
            ->where('tipo_descarga', $parametros->tipoDescarga)
            ->where('tipo_solicitud', $parametros->tipoSolicitud)
            ->where('tipo_comprobante', $parametros->tipoComprobante)
            ->where('estado_comprobante', $parametros->estadoComprobante)
            ->where('estado', '!=', EstadoSolicitud::Rechazada)
            ->oldest()
            ->first();
    }

    /**
     * Presenta la Solicitud al SAT con la Fiel indicada y guarda la respuesta.
     *
     * Si el SAT no responde no se guarda nada, porque no hay identificador que verificar.
     *
     * @throws ValidationException
     */
    public function __invoke(ParametrosSolicitud $parametros, int $fielId, User $usuario): Solicitud
    {
        try {
            $resultado = $this->conexionSat->servicio($fielId, $parametros->servicio)->query($parametros->paraSat());
        } catch (FielNoVigente $error) {
            throw ValidationException::withMessages(['fiel' => $error->getMessage()]);
        } catch (WebClientException $error) {
            throw ValidationException::withMessages([
                'sat' => "No se pudo contactar al SAT ({$error->getMessage()}). Intenta de nuevo.",
            ]);
        }
        $aceptada = $resultado->getStatus()->isAccepted();

        return Solicitud::create([
            'fiel_id' => $fielId,
            'presentada_por_id' => $usuario->id,
            'servicio' => $parametros->servicio,
            'fecha_inicio' => $parametros->fechaInicio,
            'fecha_fin' => $parametros->fechaFin,
            'tipo_descarga' => $parametros->tipoDescarga,
            'tipo_solicitud' => $parametros->tipoSolicitud,
            'tipo_comprobante' => $parametros->tipoComprobante,
            'estado_comprobante' => $parametros->estadoComprobante,
            'id_solicitud_sat' => $aceptada ? $resultado->getRequestId() : null,
            'estado' => $aceptada ? EstadoSolicitud::Aceptada : EstadoSolicitud::Rechazada,
            'codigo_sat' => $resultado->getStatus()->getCode(),
            'mensaje_sat' => $resultado->getStatus()->getMessage(),
        ]);
    }
}
