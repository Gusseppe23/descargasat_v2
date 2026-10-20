<?php

namespace DescargaSat\SatAutenticacion\Contracts;

/**
 * Códigos del CodigoEstatus del Servicio de Consulta de CFDI; ver docs/sat/consulta-cfdi.md.
 */
enum CodigoConsultaCfdi: string
{
    case ComprobanteObtenido = 'S';
    case ExpresionInvalida = '601';
    case ComprobanteNoEncontrado = '602';

    /**
     * El código de un CodigoEstatus como lo manda el SAT ("S - Comprobante...", "N - 602: Comprobante..."),
     * o null si no es uno de los documentados.
     */
    public static function desdeCodigoEstatus(string $codigoEstatus): ?self
    {
        $codigoEstatus = trim($codigoEstatus);

        if (preg_match('/^S(\s|-|$)/', $codigoEstatus) === 1) {
            return self::ComprobanteObtenido;
        }

        if (preg_match('/^N\s*-?\s*(\d+)/', $codigoEstatus, $coincidencia) === 1) {
            return self::tryFrom($coincidencia[1]);
        }

        return null;
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::ComprobanteObtenido => 'Comprobante obtenido satisfactoriamente',
            self::ExpresionInvalida => 'La expresión impresa proporcionada no es válida',
            self::ComprobanteNoEncontrado => 'Comprobante no encontrado',
        };
    }
}
