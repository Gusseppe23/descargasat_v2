<?php

namespace DescargaSat\SatAutenticacion\Contracts;

/**
 * Servicio de Consulta de CFDI del SAT: estado de un CFDI y validación EFOS del emisor. Es público, sin FIEL ni CSD.
 */
interface ServicioConsultaCfdi
{
    /**
     * Pregunta al SAT por el CFDI de la expresión impresa (?re=...&rr=...&tt=...&id=...).
     *
     * Devuelve lo que contestó el SAT, aunque sea un rechazo (601, 602); quien llama decide qué significa.
     *
     * @throws ProblemaConElSat cuando no hay conexión o el SAT no responde algo legible.
     */
    public function consultar(string $expresionImpresa): AcuseDeConsulta;
}
