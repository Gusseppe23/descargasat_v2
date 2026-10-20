<?php

namespace DescargaSat\SatAutenticacion\Contracts;

/**
 * Resultado de ValidacionEFOS: si el RFC emisor está en la lista de Empresas que Facturan Operaciones Simuladas (Art. 69-B CFF).
 */
enum ValidacionEfos: int
{
    case EnLaLista = 100;
    case FueraDeLaLista = 200;

    public function descripcion(): string
    {
        return match ($this) {
            self::EnLaLista => 'El emisor está en la lista de empresas que facturan operaciones simuladas (Art. 69-B CFF)',
            self::FueraDeLaLista => 'El emisor no está en la lista EFOS',
        };
    }
}
