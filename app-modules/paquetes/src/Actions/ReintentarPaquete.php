<?php

namespace DescargaSat\Paquetes\Actions;

use DescargaSat\Paquetes\Contracts\EstadoPaquete;
use DescargaSat\Paquetes\Contracts\PaqueteReintentado;
use DescargaSat\Paquetes\Models\Paquete;
use Illuminate\Validation\ValidationException;

class ReintentarPaquete
{
    /**
     * Regresa un Paquete Fallido a Pendiente y anuncia que hay que descargarlo de nuevo.
     *
     * @throws ValidationException
     */
    public function __invoke(Paquete $paquete): void
    {
        if ($paquete->estado !== EstadoPaquete::Fallido) {
            throw ValidationException::withMessages(['paquete' => 'Solo se pueden reintentar Paquetes fallidos.']);
        }

        $paquete->estado = EstadoPaquete::Pendiente;
        $paquete->ultimo_problema = null;
        $paquete->save();

        PaqueteReintentado::dispatch($paquete->id);
    }
}
