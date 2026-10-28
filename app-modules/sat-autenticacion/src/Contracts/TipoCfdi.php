<?php

namespace DescargaSat\SatAutenticacion\Contracts;

use PhpCfdi\CfdiSatScraper\Filters\DownloadType;

/**
 * Si el CFDI se busca entre los emitidos o entre los recibidos de la Fiel; el portal del SAT lo exige.
 */
enum TipoCfdi: string
{
    case Emitido = 'emitido';
    case Recibido = 'recibido';

    public function downloadType(): DownloadType
    {
        return match ($this) {
            self::Emitido => DownloadType::emitidos(),
            self::Recibido => DownloadType::recibidos(),
        };
    }
}
