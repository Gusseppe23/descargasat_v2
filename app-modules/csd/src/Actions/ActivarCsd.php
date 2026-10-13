<?php

namespace DescargaSat\Csd\Actions;

use App\Models\User;
use DescargaSat\Csd\Models\Csd;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivarCsd
{
    /**
     * Convierte el CSD en el CSD activo y desactiva los demás.
     *
     * @throws ValidationException
     */
    public function __invoke(Csd $csd, User $usuario): void
    {
        if ($csd->vigente_hasta->isPast()) {
            throw ValidationException::withMessages(['csd' => 'No se puede activar un CSD vencido.']);
        }

        DB::transaction(function () use ($csd, $usuario): void {
            Csd::where('activo', true)->update([
                'activo' => false,
                'estado_cambiado_por_id' => $usuario->id,
                'estado_cambiado_el' => now(),
            ]);

            $csd->activo = true;
            $csd->estado_cambiado_por_id = $usuario->id;
            $csd->estado_cambiado_el = now();
            $csd->save();
        });
    }
}
