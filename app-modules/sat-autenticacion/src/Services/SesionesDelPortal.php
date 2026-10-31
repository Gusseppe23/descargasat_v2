<?php

namespace DescargaSat\SatAutenticacion\Services;

use DescargaSat\SatAutenticacion\Contracts\ProblemaConElSat;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use PhpCfdi\Credentials\Credential;

/**
 * Guarda la sesión del portal de CFDI (sus cookies) por Fiel para no iniciar sesión en cada descarga.
 *
 * Las cookies dan acceso al portal con la Fiel, así que se guardan cifradas. La llave usa el número de certificado:
 * al cambiar de Fiel, la sesión anterior ya no se usa. Si el SAT la cerró antes, la librería lo detecta e inicia
 * sesión de nuevo.
 */
class SesionesDelPortal
{
    /**
     * Minutos que se guarda la sesión desde su último uso.
     */
    public const MINUTOS_DE_LA_SESION = 20;

    /**
     * Segundos que dura el candado si el proceso que lo tiene muere sin soltarlo.
     */
    private const SEGUNDOS_DEL_CANDADO = 600;

    public function __construct(private int $segundosDeEspera = 180) {}

    /**
     * Usa la sesión guardada de la Fiel y la vuelve a guardar al terminar, aunque la descarga falle. Las descargas
     * con la misma Fiel van una a la vez para que dos inicios de sesión simultáneos no se invaliden entre sí.
     *
     * @template T
     *
     * @param  callable(CookieJar): T  $usar
     * @return T
     *
     * @throws ProblemaConElSat cuando otra descarga con la misma Fiel no termina a tiempo.
     */
    public function conSesion(Credential $credencial, callable $usar): mixed
    {
        $numeroCertificado = $credencial->certificate()->serialNumber()->bytes();

        try {
            return Cache::lock("portal-cfdi:candado:{$numeroCertificado}", self::SEGUNDOS_DEL_CANDADO)
                ->block($this->segundosDeEspera, function () use ($numeroCertificado, $usar): mixed {
                    $cookies = $this->recuperar($numeroCertificado);

                    try {
                        return $usar($cookies);
                    } finally {
                        $this->guardar($numeroCertificado, $cookies);
                    }
                });
        } catch (LockTimeoutException $error) {
            throw new ProblemaConElSat("Hay otra descarga en curso con la Fiel {$numeroCertificado}; intenta de nuevo en unos minutos.", previous: $error);
        }
    }

    private function recuperar(string $numeroCertificado): CookieJar
    {
        $cifradas = Cache::get($this->llave($numeroCertificado));

        if (! is_string($cifradas)) {
            return new CookieJar;
        }

        /** @var list<array<string, mixed>> $cookies */
        $cookies = json_decode(Crypt::decryptString($cifradas), true);

        return new CookieJar(false, $cookies);
    }

    private function guardar(string $numeroCertificado, CookieJar $cookies): void
    {
        Cache::put(
            $this->llave($numeroCertificado),
            Crypt::encryptString((string) json_encode($cookies->toArray())),
            now()->addMinutes(self::MINUTOS_DE_LA_SESION),
        );
    }

    private function llave(string $numeroCertificado): string
    {
        return "portal-cfdi:sesion:{$numeroCertificado}";
    }
}
