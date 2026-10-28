<?php

namespace DescargaSat\SatAutenticacion\Contracts;

/**
 * Portal de CFDI del SAT (portalcfdi.facturaelectronica.sat.gob.mx): descarga del XML y el PDF de un CFDI. Ver ADR-0007.
 */
interface PortalCfdi
{
    /**
     * Inicia sesión en el portal con la Fiel, busca el CFDI y descarga su XML y su PDF.
     *
     * Si el SAT entrega solo uno de los dos archivos (por ejemplo, el PDF de un CFDI cancelado), lo devuelve con el motivo del que falta.
     *
     * @throws FielNoVigente cuando la Fiel ya venció o todavía no entra en vigor (no se contacta al SAT), o cuando el SAT la rechaza al iniciar sesión (por ejemplo, revocada).
     * @throws CfdiNoEncontrado cuando el CFDI no está entre los emitidos o recibidos de la Fiel, según $tipo.
     * @throws ArchivoNoDisponible cuando el SAT no entrega ninguno de los dos archivos.
     * @throws ProblemaConElSat cuando no se puede iniciar sesión, no hay conexión o el portal no responde algo legible.
     */
    public function descargar(int $fielId, string $uuid, TipoCfdi $tipo): ArchivosCfdi;
}
