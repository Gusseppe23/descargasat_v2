<?php

namespace DescargaSat\Fiel\Actions;

use DescargaSat\Fiel\Contracts\UsoDeFiel;
use DescargaSat\Fiel\Models\Fiel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BorrarFiel
{
    public function __construct(private UsoDeFiel $usoDeFiel) {}

    /**
     * Borra la Fiel junto con sus archivos cifrados.
     *
     * @throws ValidationException
     */
    public function __invoke(Fiel $fiel): void
    {
        if ($fiel->activa) {
            throw ValidationException::withMessages(['fiel' => 'No se puede borrar la Fiel activa. Desactívala primero.']);
        }

        if ($this->usoDeFiel->tieneSolicitudesEnCurso($fiel->id)) {
            throw ValidationException::withMessages(['fiel' => 'Esta Fiel tiene Solicitudes en curso y no se puede borrar todavía.']);
        }

        $fiel->delete();

        Storage::disk('local')->delete([$fiel->ruta_cer, $fiel->ruta_key]);
    }
}
