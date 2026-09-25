<?php

namespace DescargaSat\Fiel\Actions;

use App\Models\User;
use DescargaSat\Fiel\Models\Fiel;

class DesactivarFiel
{
    /**
     * Deja de usar la Fiel para presentar nuevas Solicitudes.
     */
    public function __invoke(Fiel $fiel, User $usuario): void
    {
        $fiel->activa = false;
        $fiel->estado_cambiado_por_id = $usuario->id;
        $fiel->estado_cambiado_el = now();
        $fiel->save();
    }
}
