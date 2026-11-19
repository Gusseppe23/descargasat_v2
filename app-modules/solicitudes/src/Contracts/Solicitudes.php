<?php

namespace DescargaSat\Solicitudes\Contracts;

/**
 * Interfaz pública del módulo solicitudes.
 */
interface Solicitudes
{
    /**
     * Las Solicitudes Aceptadas o En proceso.
     *
     * @return list<DatosSolicitud>
     */
    public function pendientes(): array;

    /**
     * La Solicitud con ese id, o null si no existe o el SAT nunca la aceptó.
     */
    public function porId(int $id): ?DatosSolicitud;

    public function cambiarEstado(int $id, EstadoSolicitud $estado, ?string $mensaje = null): void;

    /**
     * Anota la hora de este intento de Verificación y su problema, o null si salió bien.
     */
    public function anotarIntento(int $id, ?string $problema): void;

    /**
     * Presenta con la Fiel activa una Solicitud automática por UUID (folio), sin usuario, y devuelve su id.
     * El SAT entrega en su Paquete el XML del CFDI.
     *
     * @throws NoSePudoPresentarSolicitud
     */
    public function presentarPorUuid(string $uuid): int;
}
