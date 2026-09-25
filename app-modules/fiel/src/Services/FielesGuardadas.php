<?php

namespace DescargaSat\Fiel\Services;

use DescargaSat\Fiel\Contracts\DatosFiel;
use DescargaSat\Fiel\Contracts\Fieles;
use DescargaSat\Fiel\Models\Fiel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use PhpCfdi\Credentials\Credential;
use RuntimeException;

class FielesGuardadas implements Fieles
{
    public function activa(): ?DatosFiel
    {
        $fiel = Fiel::where('activa', true)->first();

        return $fiel === null ? null : $this->datos($fiel);
    }

    public function porId(int $id): ?DatosFiel
    {
        $fiel = Fiel::find($id);

        return $fiel === null ? null : $this->datos($fiel);
    }

    public function credencial(int $id): Credential
    {
        $fiel = Fiel::findOrFail($id);

        return Credential::create(
            $this->descifrar($fiel->ruta_cer),
            $this->descifrar($fiel->ruta_key),
            $fiel->contrasena,
        );
    }

    private function descifrar(string $ruta): string
    {
        $contenido = Storage::disk('local')->get($ruta);

        if ($contenido === null) {
            throw new RuntimeException("No se encontró el archivo cifrado de la Fiel: {$ruta}");
        }

        return Crypt::decryptString($contenido);
    }

    private function datos(Fiel $fiel): DatosFiel
    {
        return new DatosFiel(
            id: $fiel->id,
            rfc: $fiel->rfc,
            razonSocial: $fiel->razon_social,
            numeroCertificado: $fiel->numero_certificado,
            vigenteDesde: $fiel->vigente_desde,
            vigenteHasta: $fiel->vigente_hasta,
            activa: $fiel->activa,
        );
    }
}
