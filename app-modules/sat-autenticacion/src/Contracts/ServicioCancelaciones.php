<?php

namespace DescargaSat\SatAutenticacion\Contracts;

use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Servicio de Cancelación Aceptación/Rechazo para entes públicos del SAT, firmado con un CSD.
 */
interface ServicioCancelaciones
{
    /**
     * Pregunta al SAT qué Cancelaciones pendientes tiene el RFC del CSD indicado.
     *
     * @throws CsdNoVigente cuando el CSD ya venció o todavía no entra en vigor; no se contacta al SAT.
     * @throws ProblemaConElSat cuando no hay conexión o el SAT no responde algo legible.
     * @throws ModelNotFoundException
     */
    public function pendientes(int $csdId): PeticionesPendientes;
}
