<?php

namespace DescargaSat\SatAutenticacion\Contracts;

/**
 * Lo que respondió el SAT a Consulta, con los valores tal como los mandó y el XML recibido tal cual.
 */
final readonly class AcuseDeConsulta
{
    /**
     * @param  string  $codigoEstatus  CodigoEstatus tal como lo mandó el SAT, p. ej. "S - Comprobante obtenido satisfactoriamente."
     * @param  string  $estado  "Vigente", "Cancelado" o "No Encontrado".
     * @param  string|null  $validacionEfos  ValidacionEFOS tal como lo mandó el SAT, o null si no vino.
     */
    public function __construct(
        public string $codigoEstatus,
        public string $estado,
        public ?string $esCancelable,
        public ?string $estatusCancelacion,
        public ?string $validacionEfos,
        public string $xmlRecibido,
    ) {}

    /**
     * El código del SAT, o null si no es uno de los documentados.
     */
    public function codigo(): ?CodigoConsultaCfdi
    {
        return CodigoConsultaCfdi::desdeCodigoEstatus($this->codigoEstatus);
    }

    /**
     * La validación EFOS del emisor, o null si no vino o no es un valor documentado.
     */
    public function efos(): ?ValidacionEfos
    {
        return $this->validacionEfos !== null && ctype_digit($this->validacionEfos)
            ? ValidacionEfos::tryFrom((int) $this->validacionEfos)
            : null;
    }
}
