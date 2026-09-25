<?php

namespace DescargaSat\Fiel\Contracts;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use PhpCfdi\Credentials\Credential;

/**
 * Interfaz pública del módulo fiel para los demás módulos.
 */
interface Fieles
{
    /**
     * La Fiel activa, o null si no hay ninguna.
     */
    public function activa(): ?DatosFiel;

    /**
     * La Fiel con ese id, o null si no existe.
     */
    public function porId(int $id): ?DatosFiel;

    /**
     * La credencial descifrada de la Fiel, lista para firmar ante el SAT.
     *
     * Solo vive en memoria; nunca debe guardarse ni registrarse en logs.
     *
     * @throws ModelNotFoundException
     */
    public function credencial(int $id): Credential;
}
