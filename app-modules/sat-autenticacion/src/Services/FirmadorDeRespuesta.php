<?php

namespace DescargaSat\SatAutenticacion\Services;

use DescargaSat\SatAutenticacion\Contracts\Respuesta;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use PhpCfdi\Credentials\Credential;

/**
 * Arma y firma la SolicitudAceptacionRechazo de una Respuesta de cancelación (ADR-0005).
 *
 * Firma XMLDSig enveloped (Reference URI="") del elemento exactamente con el namespace con el que viaja dentro de
 * ProcesarRespuesta. Se canonicaliza con xml-exc-c14n para que el digest sea el mismo si el SAT verifica el elemento
 * suelto o dentro del sobre, donde hereda namespaces del s:Envelope que no usa.
 */
class FirmadorDeRespuesta
{
    public const NS_DSIG = 'http://www.w3.org/2000/09/xmldsig#';

    public const C14N_EXCLUSIVA = 'http://www.w3.org/2001/10/xml-exc-c14n#';

    /**
     * @var array<string, array{firma: string, digest: string, openssl: int}>
     */
    public const ALGORITMOS = [
        'sha256' => [
            'firma' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
            'digest' => 'http://www.w3.org/2001/04/xmlenc#sha256',
            'openssl' => OPENSSL_ALGO_SHA256,
        ],
        'sha1' => [
            'firma' => 'http://www.w3.org/2000/09/xmldsig#rsa-sha1',
            'digest' => 'http://www.w3.org/2000/09/xmldsig#sha1',
            'openssl' => OPENSSL_ALGO_SHA1,
        ],
    ];

    /**
     * La SolicitudAceptacionRechazo firmada, lista para ir dentro de <ProcesarRespuesta>.
     *
     * @param  string  $algoritmo  sha256 o sha1 (config sat.cancelaciones.firma).
     *
     * @throws InvalidArgumentException cuando el algoritmo no es sha256 ni sha1.
     */
    public function firmar(Credential $credencial, string $uuid, Respuesta $respuesta, string $algoritmo): string
    {
        $algoritmos = self::ALGORITMOS[$algoritmo]
            ?? throw new InvalidArgumentException("Algoritmo de firma no soportado: {$algoritmo}. Usa sha256 o sha1.");

        $documento = new DOMDocument('1.0', 'UTF-8');
        $solicitud = $documento->createElementNS(LectorDeRespuestas::NS_SERVICIO, 'SolicitudAceptacionRechazo');
        $documento->appendChild($solicitud);
        $solicitud->setAttribute('Fecha', now('America/Mexico_City')->format('Y-m-d\TH:i:s'));
        $solicitud->setAttribute('RfcPacEnviaSolicitud', '');
        $solicitud->setAttribute('RfcReceptor', $credencial->rfc());

        $folios = $this->agregar($solicitud, LectorDeRespuestas::NS_DATOS, 'Folios');
        $this->agregar($folios, LectorDeRespuestas::NS_DATOS, 'UUID', strtoupper($uuid));
        $this->agregar($folios, LectorDeRespuestas::NS_DATOS, 'Respuesta', $respuesta->value);

        $digest = base64_encode(hash($algoritmo, (string) $solicitud->C14N(true, false), true));

        $firma = $this->agregar($solicitud, self::NS_DSIG, 'Signature');
        $signedInfo = $this->agregar($firma, self::NS_DSIG, 'SignedInfo');
        $this->agregar($signedInfo, self::NS_DSIG, 'CanonicalizationMethod')->setAttribute('Algorithm', self::C14N_EXCLUSIVA);
        $this->agregar($signedInfo, self::NS_DSIG, 'SignatureMethod')->setAttribute('Algorithm', $algoritmos['firma']);
        $referencia = $this->agregar($signedInfo, self::NS_DSIG, 'Reference');
        $referencia->setAttribute('URI', '');
        $transformaciones = $this->agregar($referencia, self::NS_DSIG, 'Transforms');
        $this->agregar($transformaciones, self::NS_DSIG, 'Transform')->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $this->agregar($transformaciones, self::NS_DSIG, 'Transform')->setAttribute('Algorithm', self::C14N_EXCLUSIVA);
        $this->agregar($referencia, self::NS_DSIG, 'DigestMethod')->setAttribute('Algorithm', $algoritmos['digest']);
        $this->agregar($referencia, self::NS_DSIG, 'DigestValue', $digest);

        $valorDeFirma = $credencial->sign((string) $signedInfo->C14N(true, false), $algoritmos['openssl']);
        $this->agregar($firma, self::NS_DSIG, 'SignatureValue', base64_encode($valorDeFirma));

        $certificado = $credencial->certificate();
        $datosX509 = $this->agregar($this->agregar($firma, self::NS_DSIG, 'KeyInfo'), self::NS_DSIG, 'X509Data');
        $emisorYSerie = $this->agregar($datosX509, self::NS_DSIG, 'X509IssuerSerial');
        $this->agregar($emisorYSerie, self::NS_DSIG, 'X509IssuerName', $certificado->issuerAsRfc4514());
        $this->agregar($emisorYSerie, self::NS_DSIG, 'X509SerialNumber', $certificado->serialNumber()->decimal());
        $this->agregar($datosX509, self::NS_DSIG, 'X509Certificate', (string) preg_replace('/-----[A-Z ]+-----|\s/', '', $certificado->pem()));

        return (string) $documento->saveXML($solicitud);
    }

    private function agregar(DOMElement $padre, string $namespace, string $nombre, ?string $texto = null): DOMElement
    {
        $elemento = $padre->ownerDocument?->createElementNS($namespace, $nombre)
            ?? throw new InvalidArgumentException('El elemento no pertenece a un documento.');

        if ($texto !== null) {
            $elemento->textContent = $texto;
        }

        $padre->appendChild($elemento);

        return $elemento;
    }
}
