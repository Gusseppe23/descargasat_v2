<?php

namespace DescargaSat\SatAutenticacion\Contracts;

use PhpCfdi\SatWsDescargaMasiva\Service;

/**
 * Única puerta de acceso al SAT: arma el Service autenticado con una Fiel.
 */
interface ConexionSat
{
    /**
     * El Service del SAT para el Servicio indicado, firmado con la Fiel indicada.
     *
     * El Service se autentica solo la primera vez que se usa y reutiliza su token mientras sea válido.
     *
     * @throws FielNoVigente cuando la Fiel ya venció; no se contacta al SAT.
     */
    public function servicio(int $fielId, Servicio $servicio): Service;
}
