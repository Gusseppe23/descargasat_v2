<?php

namespace DescargaSat\Csd\Services;

use DescargaSat\Csd\Contracts\Csds;
use DescargaSat\Csd\Contracts\DatosCsd;
use DescargaSat\Csd\Models\Csd;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use PhpCfdi\Credentials\Credential;
use RuntimeException;

class CsdsGuardados implements Csds
{
    public function activo(): ?DatosCsd
    {
        $csd = Csd::where('activo', true)->first();

        return $csd === null ? null : $this->datos($csd);
    }

    public function credencial(int $id): Credential
    {
        $csd = Csd::findOrFail($id);

        return Credential::create(
            $this->descifrar($csd->ruta_cer),
            $this->descifrar($csd->ruta_key),
            $csd->contrasena,
        );
    }

    private function descifrar(string $ruta): string
    {
        $contenido = Storage::disk('local')->get($ruta);

        if ($contenido === null) {
            throw new RuntimeException("No se encontró el archivo cifrado del CSD: {$ruta}");
        }

        return Crypt::decryptString($contenido);
    }

    private function datos(Csd $csd): DatosCsd
    {
        return new DatosCsd(
            id: $csd->id,
            rfc: $csd->rfc,
            razonSocial: $csd->razon_social,
            numeroCertificado: $csd->numero_certificado,
            vigenteDesde: $csd->vigente_desde,
            vigenteHasta: $csd->vigente_hasta,
            activo: $csd->activo,
        );
    }
}
