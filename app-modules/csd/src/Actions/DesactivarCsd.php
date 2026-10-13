<?php

namespace DescargaSat\Csd\Actions;

use App\Models\User;
use DescargaSat\Csd\Models\Csd;

class DesactivarCsd
{
    /**
     * Deja de usar el CSD para revisar y responder cancelaciones.
     */
    public function __invoke(Csd $csd, User $usuario): void
    {
        $csd->activo = false;
        $csd->estado_cambiado_por_id = $usuario->id;
        $csd->estado_cambiado_el = now();
        $csd->save();
    }
}
