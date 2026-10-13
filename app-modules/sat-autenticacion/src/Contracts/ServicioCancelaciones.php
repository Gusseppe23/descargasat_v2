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

    /**
     * Envía al SAT la Respuesta de cancelación del UUID, firmada con el CSD indicado. No se puede deshacer.
     *
     * Devuelve lo que contestó el SAT, sea un acuse o un código de error; quien llama decide qué significa.
     *
     * @throws CsdNoVigente cuando el CSD ya venció o todavía no entra en vigor; no se contacta al SAT.
     * @throws ProblemaConElSat cuando no hay conexión o el SAT no responde algo legible; si ya se había
     *                          enviado el sobre, viene en $xmlEnviado.
     * @throws ModelNotFoundException
     */
    public function responder(int $csdId, string $uuid, Respuesta $respuesta): AcuseDeRespuesta;
}
