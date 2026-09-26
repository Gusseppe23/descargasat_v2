<?php

namespace DescargaSat\Solicitudes\Contracts;

/**
 * Interfaz pública del módulo solicitudes para quien las verifica.
 */
interface SolicitudesPendientes
{
    /**
     * Las Solicitudes Aceptadas o En proceso.
     *
     * @return list<SolicitudPendiente>
     */
    public function pendientes(): array;

    public function cambiarEstado(int $id, EstadoSolicitud $estado, ?string $mensaje = null): void;

    /**
     * Anota la hora de este intento de Verificación y su problema, o null si salió bien.
     */
    public function anotarIntento(int $id, ?string $problema): void;
}
