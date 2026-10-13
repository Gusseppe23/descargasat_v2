<?php

namespace DescargaSat\Cancelaciones\Contracts;

/**
 * Interfaz pública del módulo cancelaciones para el resto de la aplicación.
 */
interface Cancelaciones
{
    /**
     * Cuántas Cancelaciones pendientes están Por responder.
     */
    public function porResponder(): int;
}
