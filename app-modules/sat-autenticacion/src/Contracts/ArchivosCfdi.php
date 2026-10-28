<?php

namespace DescargaSat\SatAutenticacion\Contracts;

/**
 * El XML y el PDF de un CFDI tal como los entregó el portal del SAT. Al menos uno de los dos viene lleno.
 */
final readonly class ArchivosCfdi
{
    /**
     * @param  string|null  $xml  El XML del CFDI, o null si el SAT no lo entregó.
     * @param  string|null  $pdf  La representación impresa en PDF, o null si el SAT no la entregó.
     * @param  string|null  $xmlNoDisponible  Por qué falta el XML, o null si vino.
     * @param  string|null  $pdfNoDisponible  Por qué falta el PDF, o null si vino.
     */
    public function __construct(
        public string $uuid,
        public ?string $xml,
        public ?string $pdf,
        public ?string $xmlNoDisponible = null,
        public ?string $pdfNoDisponible = null,
    ) {}
}
