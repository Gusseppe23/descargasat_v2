<?php

namespace DescargaSat\Csd\Actions;

use DescargaSat\Csd\Models\Csd;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BorrarCsd
{
    /**
     * Borra el CSD junto con sus archivos cifrados.
     *
     * @throws ValidationException
     */
    public function __invoke(Csd $csd): void
    {
        if ($csd->activo) {
            throw ValidationException::withMessages(['csd' => 'No se puede borrar el CSD activo. Desactívalo primero.']);
        }

        $csd->delete();

        Storage::disk('local')->delete([$csd->ruta_cer, $csd->ruta_key]);
    }
}
