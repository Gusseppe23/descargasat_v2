<?php

namespace DescargaSat\Paquetes\Services;

use DescargaSat\Paquetes\Contracts\EstadoPaquete;
use DescargaSat\Paquetes\Contracts\Paquetes;
use DescargaSat\Paquetes\Contracts\PaquetesRegistrados;
use DescargaSat\Paquetes\Models\Paquete;

class PaquetesGuardados implements Paquetes
{
    public function registrarPendientes(int $solicitudId, array $idsPaqueteSat): void
    {
        foreach ($idsPaqueteSat as $idPaqueteSat) {
            Paquete::firstOrCreate(
                ['id_paquete_sat' => $idPaqueteSat],
                ['solicitud_id' => $solicitudId, 'estado' => EstadoPaquete::Pendiente],
            );
        }

        PaquetesRegistrados::dispatch($solicitudId);
    }
}
