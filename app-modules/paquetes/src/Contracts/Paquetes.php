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

    /**
     * Los Paquetes de la Solicitud que todavía no se descargan.
     *
     * @return list<DatosPaquete>
     */
    public function pendientesDe(int $solicitudId): array;

    public function porId(int $id): ?DatosPaquete;

    /**
     * Si todos los Paquetes de la Solicitud ya están Extraídos.
     */
    public function todosExtraidos(int $solicitudId): bool;

    /**
     * Anota el último problema al descargar o extraer el Paquete, o null para borrarlo.
     */
    public function anotarProblema(int $id, ?string $problema): void;

    /**
     * El Paquete no se pudo descargar y ya no se reintentará solo.
     */
    public function marcarFallido(int $id): void;

    /**
     * Guarda el ZIP que entregó el SAT; el Paquete queda Descargado.
     */
    public function guardarZip(int $id, string $zip): void;

    /**
     * Extrae el Contenido del Paquete en su carpeta; el Paquete queda Extraído.
     *
     * @throws PaqueteDanado cuando el ZIP no se puede leer o intenta escribir fuera de su carpeta.
     */
    public function extraer(int $id): void;
}
