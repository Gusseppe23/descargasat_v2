<?php

namespace DescargaSat\DescargaCfdi\Actions;

use DescargaSat\DescargaCfdi\Exceptions\SinFielActiva;
use DescargaSat\Fiel\Contracts\Fieles;
use DescargaSat\SatAutenticacion\Contracts\ArchivoNoDisponible;
use DescargaSat\SatAutenticacion\Contracts\ArchivosCfdi;
use DescargaSat\SatAutenticacion\Contracts\CfdiNoEncontrado;
use DescargaSat\SatAutenticacion\Contracts\FielNoVigente;
use DescargaSat\SatAutenticacion\Contracts\PortalCfdi;
use DescargaSat\SatAutenticacion\Contracts\ProblemaConElSat;
use DescargaSat\SatAutenticacion\Contracts\TipoCfdi;

class DescargarCfdi
{
    public function __construct(
        private Fieles $fieles,
        private PortalCfdi $portalCfdi,
    ) {}

    /**
     * Descarga del portal del SAT el XML y el PDF del CFDI, iniciando sesión con la Fiel activa.
     *
     * @throws SinFielActiva
     * @throws FielNoVigente
     * @throws CfdiNoEncontrado
     * @throws ArchivoNoDisponible
     * @throws ProblemaConElSat
     */
    public function __invoke(string $uuid, TipoCfdi $tipo): ArchivosCfdi
    {
        $fiel = $this->fieles->activa() ?? throw new SinFielActiva('No hay una FIEL activa para iniciar sesión en el portal del SAT.');

        return $this->portalCfdi->descargar($fiel->id, strtoupper(trim($uuid)), $tipo);
    }
}
