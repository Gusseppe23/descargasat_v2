<?php

namespace DescargaSat\SatAutenticacion\Contracts;

/**
 * Lo que respondió el SAT a ObtenerPeticionesPendientes.
 */
final readonly class PeticionesPendientes
{
    /**
     * @param  string  $codEstatus  CodEstatus tal como lo mandó el SAT, aunque no sea un código conocido.
     * @param  list<string>  $uuids  UUID de las Cancelaciones pendientes, en mayúsculas.
     */
    public function __construct(
        public string $codEstatus,
        public array $uuids,
    ) {}

    /**
     * El código del SAT, o null si no es uno de los documentados.
     */
    public function codigo(): ?CodigoCancelacion
    {
        return ctype_digit($this->codEstatus) ? CodigoCancelacion::tryFrom((int) $this->codEstatus) : null;
    }
}
