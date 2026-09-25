<?php

namespace DescargaSat\Fiel\Actions;

use App\Models\User;
use DescargaSat\Fiel\Models\Fiel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpCfdi\Credentials\Certificate;
use PhpCfdi\Credentials\PrivateKey;
use RuntimeException;

class RegistrarFiel
{
    /**
     * Valida el .cer y la .key con su contraseña y guarda la Fiel, con sus archivos cifrados.
     *
     * @throws ValidationException
     */
    public function __invoke(string $cer, string $key, string $contrasena, User $usuario): Fiel
    {
        try {
            $certificado = new Certificate($cer);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['cer' => 'El archivo .cer no es un certificado válido.']);
        }

        if (! $certificado->satType()->isFiel()) {
            throw ValidationException::withMessages(['cer' => 'El certificado es un CSD (sello digital), no una FIEL.']);
        }

        if (! $certificado->validOn(now()->toDateTimeImmutable())) {
            $venceEl = Carbon::instance($certificado->validToDateTime())->format('d/m/Y');

            throw ValidationException::withMessages(['cer' => "La FIEL venció el {$venceEl} y ya no está vigente."]);
        }

        $numeroCertificado = $certificado->serialNumber()->bytes();

        if (Fiel::where('numero_certificado', $numeroCertificado)->exists()) {
            throw ValidationException::withMessages(['cer' => 'Esta Fiel ya está guardada.']);
        }

        $rfcDeLaAplicacion = Fiel::query()->value('rfc');

        if ($rfcDeLaAplicacion !== null && $rfcDeLaAplicacion !== $certificado->rfc()) {
            throw ValidationException::withMessages([
                'cer' => "Esta FIEL es del RFC {$certificado->rfc()}, pero la aplicación trabaja con el RFC {$rfcDeLaAplicacion}.",
            ]);
        }

        try {
            $llavePrivada = new PrivateKey($key, $contrasena);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['contrasena' => 'La contraseña no abre la llave privada.']);
        }

        if (! $llavePrivada->belongsTo($certificado)) {
            throw ValidationException::withMessages(['key' => 'La llave privada no corresponde al certificado.']);
        }

        $nombre = 'fiel/'.Str::uuid();
        Storage::disk('local')->put($nombre.'.cer', Crypt::encryptString($cer));
        Storage::disk('local')->put($nombre.'.key', Crypt::encryptString($key));

        return Fiel::create([
            'rfc' => $certificado->rfc(),
            'razon_social' => $certificado->legalName(),
            'numero_certificado' => $numeroCertificado,
            'vigente_desde' => Carbon::instance($certificado->validFromDateTime()),
            'vigente_hasta' => Carbon::instance($certificado->validToDateTime()),
            'ruta_cer' => $nombre.'.cer',
            'ruta_key' => $nombre.'.key',
            'contrasena' => $contrasena,
            'subida_por_id' => $usuario->id,
        ]);
    }
}
