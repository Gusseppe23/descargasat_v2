<?php

namespace DescargaSat\Fiel\Actions;

use App\Models\User;
use DescargaSat\Fiel\Models\Fiel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivarFiel
{
    /**
     * Convierte la Fiel en la Fiel activa y desactiva las demás.
     *
     * @throws ValidationException
     */
    public function __invoke(Fiel $fiel, User $usuario): void
    {
        if ($fiel->vigente_hasta->isPast()) {
            throw ValidationException::withMessages(['fiel' => 'No se puede activar una FIEL vencida.']);
        }

        DB::transaction(function () use ($fiel, $usuario): void {
            Fiel::where('activa', true)->update([
                'activa' => false,
                'estado_cambiado_por_id' => $usuario->id,
                'estado_cambiado_el' => now(),
            ]);

            $fiel->activa = true;
            $fiel->estado_cambiado_por_id = $usuario->id;
            $fiel->estado_cambiado_el = now();
            $fiel->save();
        });
    }
}
