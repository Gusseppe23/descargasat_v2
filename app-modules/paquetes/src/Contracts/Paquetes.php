<?php

namespace DescargaSat\Paquetes\Contracts;

/**
 * Interfaz pública del módulo paquetes.
 */
interface Paquetes
{
    /**
     * Registra como Pendiente cada Paquete que el SAT informó para una Solicitud Terminada
     * y anuncia PaquetesRegistrados.
     *
     * @param  list<string>  $idsPaqueteSat
     */
    public function registrarPendientes(int $solicitudId, array $idsPaqueteSat): void;
}
