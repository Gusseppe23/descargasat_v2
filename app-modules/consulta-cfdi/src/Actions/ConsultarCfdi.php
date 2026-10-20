<?php

namespace DescargaSat\ConsultaCfdi\Actions;

use DescargaSat\SatAutenticacion\Contracts\AcuseDeConsulta;
use DescargaSat\SatAutenticacion\Contracts\ProblemaConElSat;
use DescargaSat\SatAutenticacion\Contracts\ServicioConsultaCfdi;

class ConsultarCfdi
{
    public function __construct(private ServicioConsultaCfdi $servicioConsultaCfdi) {}

    /**
     * Pregunta al SAT por el estado del CFDI y la validación EFOS de su emisor.
     *
     * @param  string  $total  Total del CFDI tal como viene en el comprobante; se manda sin convertirlo a número.
     *
     * @throws ProblemaConElSat
     */
    public function __invoke(string $rfcEmisor, string $rfcReceptor, string $total, string $uuid): AcuseDeConsulta
    {
        return $this->servicioConsultaCfdi->consultar($this->expresionImpresa($rfcEmisor, $rfcReceptor, $total, $uuid));
    }

    /**
     * La expresión impresa del CFDI (?re=...&rr=...&tt=...&id=...), la misma que trae su código QR.
     */
    private function expresionImpresa(string $rfcEmisor, string $rfcReceptor, string $total, string $uuid): string
    {
        return sprintf(
            '?re=%s&rr=%s&tt=%s&id=%s',
            mb_strtoupper(trim($rfcEmisor)),
            mb_strtoupper(trim($rfcReceptor)),
            trim($total),
            strtoupper(trim($uuid)),
        );
    }
}
