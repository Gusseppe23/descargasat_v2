<?php

namespace DescargaSat\Solicitudes\Support;

use DescargaSat\Paquetes\Contracts\Paquetes;
use DescargaSat\Solicitudes\Contracts\EstadoSolicitud;
use DescargaSat\Solicitudes\Models\Solicitud;

/**
 * Decide si una pantalla de Solicitudes debe refrescarse sola.
 */
class RefrescoDeSolicitudes
{
    public function __construct(private Paquetes $paquetes) {}

    /**
     * Si alguna Solicitud todavía puede cambiar sin que nadie haga nada: el SAT no la ha terminado,
     * o está Terminada y le queda algún Paquete descargándose o extrayéndose.
     *
     * @param  iterable<Solicitud>  $solicitudes
     */
    public function debeRefrescarse(iterable $solicitudes): bool
    {
        $terminadas = [];

        foreach ($solicitudes as $solicitud) {
            if ($solicitud->estado === EstadoSolicitud::Terminada) {
                $terminadas[] = $solicitud->id;
            } elseif ($solicitud->estado->enCurso()) {
                return true;
            }
        }

        return $terminadas !== [] && $this->paquetes->conDescargaEnCurso($terminadas) !== [];
    }
}
