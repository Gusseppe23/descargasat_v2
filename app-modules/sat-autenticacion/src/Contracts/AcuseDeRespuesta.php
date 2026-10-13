<?php

namespace DescargaSat\SatAutenticacion\Contracts;

/**
 * Lo que respondió el SAT a ProcesarRespuesta, junto con el sobre enviado y el XML recibido tal cual.
 */
final readonly class AcuseDeRespuesta
{
    /**
     * @param  string  $codEstatus  CodEstatus tal como lo mandó el SAT, aunque no sea un código conocido.
     * @param  list<FolioDeAcuse>  $folios
     * @param  bool  $selladoPorElSat  Si trae la firma del SAT (Signature Id="SelloSAT").
     */
    public function __construct(
        public string $codEstatus,
        public array $folios,
        public bool $selladoPorElSat,
        public string $xmlEnviado,
        public string $xmlRecibido,
    ) {}

    /**
     * El código del SAT, o null si no es uno de los documentados.
     */
    public function codigo(): ?CodigoCancelacion
    {
        return ctype_digit($this->codEstatus) ? CodigoCancelacion::tryFrom((int) $this->codEstatus) : null;
    }

    /**
     * El folio del UUID indicado, o null si el SAT no lo incluyó.
     */
    public function folio(string $uuid): ?FolioDeAcuse
    {
        foreach ($this->folios as $folio) {
            if (strcasecmp($folio->uuid, $uuid) === 0) {
                return $folio;
            }
        }

        return null;
    }
}
