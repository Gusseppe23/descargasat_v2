<?php

namespace DescargaSat\Csd\Contracts;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use PhpCfdi\Credentials\Credential;

/**
 * Interfaz pública del módulo csd para los demás módulos.
 */
interface Csds
{
    /**
     * El CSD activo, o null si no hay ninguno.
     */
    public function activo(): ?DatosCsd;

    /**
     * La credencial descifrada del CSD, lista para firmar ante el SAT.
     *
     * Solo vive en memoria; nunca debe guardarse ni registrarse en logs.
     *
     * @throws ModelNotFoundException
     */
    public function credencial(int $id): Credential;
}
