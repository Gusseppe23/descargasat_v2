<?php

namespace DescargaSat\Csd\Actions;

use App\Models\User;
use DescargaSat\Csd\Models\Csd;
use DescargaSat\Fiel\Contracts\Fieles;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpCfdi\Credentials\Certificate;
use PhpCfdi\Credentials\PrivateKey;
use RuntimeException;

class RegistrarCsd
{
    public function __construct(private Fieles $fieles) {}

    /**
     * Valida el .cer y la .key con su contraseña y guarda el CSD, con sus archivos cifrados.
     *
     * @throws ValidationException
     */
    public function __invoke(string $cer, string $key, string $contrasena, User $usuario): Csd
    {
        try {
            $certificado = new Certificate($cer);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['cer' => 'El archivo .cer no es un certificado válido.']);
        }

        if (! $certificado->satType()->isCsd()) {
            throw ValidationException::withMessages(['cer' => 'El certificado es una FIEL, no un CSD (sello digital).']);
        }

        if (now()->lessThan($certificado->validFromDateTime())) {
            $entraEnVigorEl = Carbon::instance($certificado->validFromDateTime())->format('d/m/Y');

            throw ValidationException::withMessages(['cer' => "El CSD entra en vigor el {$entraEnVigorEl}."]);
        }

        if (! $certificado->validOn(now()->toDateTimeImmutable())) {
            $venceEl = Carbon::instance($certificado->validToDateTime())->format('d/m/Y');

            throw ValidationException::withMessages(['cer' => "El CSD venció el {$venceEl} y ya no está vigente."]);
        }

        $numeroCertificado = $certificado->serialNumber()->bytes();

        if (Csd::where('numero_certificado', $numeroCertificado)->exists()) {
            throw ValidationException::withMessages(['cer' => 'Este CSD ya está guardado.']);
        }

        $rfcDeLaAplicacion = Csd::query()->value('rfc');

        if ($rfcDeLaAplicacion !== null && $rfcDeLaAplicacion !== $certificado->rfc()) {
            throw ValidationException::withMessages([
                'cer' => "Este CSD es del RFC {$certificado->rfc()}, pero la aplicación trabaja con el RFC {$rfcDeLaAplicacion}.",
            ]);
        }

        $rfcDeLaFielActiva = $this->fieles->activa()?->rfc;

        if ($rfcDeLaFielActiva !== null && $rfcDeLaFielActiva !== $certificado->rfc()) {
            throw ValidationException::withMessages([
                'cer' => "Este CSD es del RFC {$certificado->rfc()}, pero la Fiel activa es del RFC {$rfcDeLaFielActiva}.",
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

        $nombre = 'csd/'.Str::uuid();
        Storage::disk('local')->put($nombre.'.cer', Crypt::encryptString($cer));
        Storage::disk('local')->put($nombre.'.key', Crypt::encryptString($key));

        return Csd::create([
            'rfc' => $certificado->rfc(),
            'razon_social' => $certificado->legalName(),
            'numero_certificado' => $numeroCertificado,
            'vigente_desde' => Carbon::instance($certificado->validFromDateTime()),
            'vigente_hasta' => Carbon::instance($certificado->validToDateTime()),
            'ruta_cer' => $nombre.'.cer',
            'ruta_key' => $nombre.'.key',
            'contrasena' => $contrasena,
            'subido_por_id' => $usuario->id,
        ]);
    }
}
