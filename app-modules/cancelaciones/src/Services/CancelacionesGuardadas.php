<?php

namespace DescargaSat\Cancelaciones\Services;

use DescargaSat\Cancelaciones\Contracts\Cancelaciones;
use DescargaSat\Cancelaciones\Contracts\EstadoCancelacion;
use DescargaSat\Cancelaciones\Models\CancelacionPendiente;

class CancelacionesGuardadas implements Cancelaciones
{
    public function porResponder(): int
    {
        return CancelacionPendiente::where('estado', EstadoCancelacion::PorResponder)->count();
    }
}
