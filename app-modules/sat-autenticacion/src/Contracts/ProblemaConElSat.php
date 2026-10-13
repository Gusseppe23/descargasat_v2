<?php

namespace DescargaSat\SatAutenticacion\Contracts;

use RuntimeException;

/**
 * El SAT no dio una respuesta útil, o ni siquiera se le pudo preguntar.
 */
class ProblemaConElSat extends RuntimeException
{
    /**
     * El sobre que se le mandó al SAT, o null si el problema ocurrió antes de enviar nada.
     */
    public ?string $xmlEnviado = null;

    /**
     * Anota el sobre que ya se había mandado cuando ocurrió el problema.
     */
    public function conXmlEnviado(string $xmlEnviado): static
    {
        $this->xmlEnviado = $xmlEnviado;

        return $this;
    }
}
