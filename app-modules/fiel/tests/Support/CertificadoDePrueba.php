<?php

namespace DescargaSat\Fiel\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\UploadedFile;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Genera al vuelo un par .cer / .key con la misma forma que los que emite el SAT.
 *
 * El RFC va en x500UniqueIdentifier y la razón social en name. Un CSD se distingue
 * de una FIEL porque lleva unidad organizacional (OU). El número de certificado va
 * como dígitos ASCII codificados en el número de serie, igual que en el SAT.
 */
final class CertificadoDePrueba
{
    private function __construct(
        public readonly string $cer,
        public readonly string $key,
        public readonly string $contrasena,
        public readonly string $rfc,
        public readonly string $razonSocial,
        public readonly string $numeroCertificado,
        public readonly DateTimeImmutable $vigenteDesde,
        public readonly DateTimeImmutable $vigenteHasta,
    ) {}

    public static function fiel(
        string $rfc = 'EKU9003173C9',
        string $razonSocial = 'ESCUELA KEMPER URGATE',
        string $numeroCertificado = '30001000000500003416',
        string $contrasena = '12345678a',
    ): self {
        return self::generar($rfc, $razonSocial, $numeroCertificado, $contrasena, null);
    }

    public static function csd(
        string $rfc = 'EKU9003173C9',
        string $razonSocial = 'ESCUELA KEMPER URGATE',
        string $numeroCertificado = '30001000000500003417',
        string $contrasena = '12345678a',
    ): self {
        return self::generar($rfc, $razonSocial, $numeroCertificado, $contrasena, 'Sucursal 1');
    }

    /**
     * @return array{0: UploadedFile, 1: UploadedFile}
     */
    public function comoArchivos(): array
    {
        return [
            UploadedFile::fake()->createWithContent('certificado.cer', $this->cer),
            UploadedFile::fake()->createWithContent('llave.key', $this->key),
        ];
    }

    private static function generar(
        string $rfc,
        string $razonSocial,
        string $numeroCertificado,
        string $contrasena,
        ?string $sucursal,
    ): self {
        $llave = self::nuevaLlave();

        $sujeto = array_filter([
            'commonName' => $razonSocial,
            'name' => $razonSocial,
            'organizationName' => $razonSocial,
            'organizationalUnitName' => $sucursal,
            'x500UniqueIdentifier' => $rfc.' / ',
        ]);

        $csr = openssl_csr_new($sujeto, $llave, ['digest_alg' => 'sha256']);
        $firmado = $csr === false ? false : openssl_csr_sign($csr, null, $llave, 1461, ['digest_alg' => 'sha256'], 0, bin2hex($numeroCertificado));

        if ($firmado === false || ! openssl_x509_export($firmado, $pem) || ! openssl_pkey_export($llave, $llavePem, $contrasena)) {
            throw new RuntimeException('No se pudo generar el certificado de prueba: '.openssl_error_string());
        }

        $datos = openssl_x509_parse($pem);
        $utc = new DateTimeZone('UTC');

        return new self(
            cer: self::pemADer($pem),
            key: self::pemADer($llavePem),
            contrasena: $contrasena,
            rfc: $rfc,
            razonSocial: $razonSocial,
            numeroCertificado: $numeroCertificado,
            vigenteDesde: (new DateTimeImmutable('@'.$datos['validFrom_time_t']))->setTimezone($utc),
            vigenteHasta: (new DateTimeImmutable('@'.$datos['validTo_time_t']))->setTimezone($utc),
        );
    }

    private static function nuevaLlave(): OpenSSLAsymmetricKey
    {
        $llave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        if ($llave === false) {
            throw new RuntimeException('No se pudo generar la llave de prueba: '.openssl_error_string());
        }

        return $llave;
    }

    private static function pemADer(string $pem): string
    {
        return (string) base64_decode((string) preg_replace('/-----[A-Z ]+-----|\s/', '', $pem));
    }
}
