# Referencia técnica: Servicio de Cancelación Aceptación/Rechazo para Entes Públicos (SAT)

> Fuente: guía interna "Autenticación y consumo del servicio de Cancelación Aceptación/Rechazo para Entes Públicos" (8 oct 2026), basada en la *Documentación del Servicio de Cancelación Aceptación/Rechazo para Entes Públicos*, SAT v1.1, agosto 2019.
> El código de este documento es una **referencia**, no código final: debe adaptarse a la arquitectura de descargasat_v2.

## 1. Diferencias con la descarga masiva

| | Descarga masiva | Aceptación/Rechazo Entes Públicos |
|---|---|---|
| Autenticación | `Autenticacion.svc` una vez, token reutilizable unos minutos | **Sin token**: cada petición lleva su propio encabezado WS-Security firmado |
| Encabezado SOAP firmado | Solo en la llamada de autenticación | En **todas** las peticiones |
| Firma del cuerpo | En cada solicitud | Solo en `ProcesarRespuesta` (la `SolicitudAceptacionRechazo`) |
| Credencial | FIEL | **CSD de la dependencia** según sección 3 del SAT (la sección 2 menciona FIEL) → debe soportar ambas |
| Operaciones | Solicitud, verificación, descarga | `ObtenerPeticionesPendientes` y `ProcesarRespuesta` |

## 2. Endpoint

| Dato | Valor |
|---|---|
| URL producción | `https://prodcancelacionesep.clouda.sat.gob.mx/Cancelacion/AceptacionRechazoServiceEP.svc` |
| Namespace del servicio | `http://cancelacfd.sat.gob.mx/entespublicos` |
| Namespace de datos (Folios, UUID) | `http://cancelacfd.sat.gob.mx` |
| SOAPAction consulta | `http://cancelacfd.sat.gob.mx/entespublicos/IAceptacionRechazoServiceEP/ObtenerPeticionesPendientes` |
| SOAPAction respuesta | `http://cancelacfd.sat.gob.mx/entespublicos/IAceptacionRechazoServiceEP/ProcesarRespuesta` |
| Protocolo | SOAP 1.1 sobre HTTPS, document/literal |

- El WSDL está oculto: abrir la URL devuelve **404 (esperado)**. No usar `SoapClient`; los sobres se arman a mano y se envían por POST.
- No existe ambiente de pruebas documentado: **la URL es de producción**.

Paquetes: `phpcfdi/sat-ws-descarga-masiva`, `phpcfdi/xml-cancelacion`, `guzzlehttp/guzzle` (ambas librerías phpcfdi usan `phpcfdi/credentials`).

## 3. Anatomía de la petición

Cada petición lleva hasta dos firmas independientes:

1. **Encabezado WS-Security** (autentica al ente público): `u:Timestamp` con `u:Id="_0"` y vigencia de 5 min, `o:BinarySecurityToken` X509v3 en Base64, canonicalización `xml-exc-c14n#`, `rsa-sha1`/`sha1`, referencia firmada `#_0` (solo el Timestamp), `KeyInfo` con `SecurityTokenReference`.
   Es exactamente lo que genera `FielRequestBuilder::authorization($created, $expires)` de sat-ws-descarga-masiva, con cuerpo `<Autentica xmlns="http://DescargaMasivaTerceros.gob.mx"/>`.
   Como la firma cubre **solo el Timestamp**, se puede sustituir ese cuerpo por la operación del servicio sin invalidarla.
2. **Firma XMLDSig del cuerpo** (solo `ProcesarRespuesta`): se genera con `XmlCancelacionHelper::signCancellationAnswer()` de xml-cancelacion y se reacomoda al formato del ejemplo 6.1 del SAT.

Qué se reutiliza: `Fiel::create()`/`new Fiel($credential)`, `FielRequestBuilder::authorization()`, `Shared\DateTime` (fechas UTC formato SAT), `XmlCancelacionHelper::signCancellationAnswer()`. **No** se usan `Service`, `Token`, `query()`, `verify()`, `download()`.

Notas:
- Con CSD **no** llamar `Fiel::isValid()` (devuelve false para CSD); validar solo `certificate()->validOn()`.
- El código verifica que el cuerpo `<Autentica .../>` exista en el sobre generado, para detectar si una versión futura de la librería cambia el formato → **fijar versión** de sat-ws-descarga-masiva en composer.json.
- ⚠️ **Punto abierto**: el ejemplo 6.1 del SAT firma el cuerpo con **rsa-sha256**, el README de xml-cancelacion muestra rsa-sha1. Si el SAT devuelve **30002**, firmar el cuerpo con SHA-256 antes de buscar otra causa.

## 4. Operación ObtenerPeticionesPendientes

Cuerpo (dentro del sobre):

```xml
<ObtenerPeticionesPendientes xmlns="http://cancelacfd.sat.gob.mx/entespublicos">
  <RfcReceptor>WZF860724AB6</RfcReceptor>
</ObtenerPeticionesPendientes>
```

Respuesta esperada (ejemplo 6.4, sin encabezado):

```xml
<ObtenerPeticionesPendientesResponse xmlns="http://cancelacfd.sat.gob.mx/entespublicos">
  <ObtenerPeticionesPendientesResult CodEstatus="1100">
    <UUID xmlns="http://cancelacfd.sat.gob.mx">AE4B0CA8-8116-4699-8E20-77B6E9C507A2</UUID>
    <UUID xmlns="http://cancelacfd.sat.gob.mx">D92516E5-A881-423C-B40F-384008DFE03F</UUID>
  </ObtenerPeticionesPendientesResult>
</ObtenerPeticionesPendientesResponse>
```

Lista vacía con 1101 o 1102 **no es error**.

## 5. Operación ProcesarRespuesta

Cuerpo (ejemplo 6.1, firma abreviada):

```xml
<ProcesarRespuesta xmlns="http://cancelacfd.sat.gob.mx/entespublicos">
  <SolicitudAceptacionRechazo Fecha="2018-12-07T13:23:31" RfcPacEnviaSolicitud="" RfcReceptor="WZF860724AB6">
    <Folios xmlns="http://cancelacfd.sat.gob.mx">
      <UUID>AE4B0CA8-8116-4699-8E20-77B6E9C507A2</UUID>
      <Respuesta>Rechazo</Respuesta>
    </Folios>
    <Signature xmlns="http://www.w3.org/2000/09/xmldsig#">...</Signature>
  </SolicitudAceptacionRechazo>
</ProcesarRespuesta>
```

- `Respuesta` solo admite `Aceptacion` o `Rechazo` (sin acento); `CancelAnswer::accept()`/`reject()` ya los escriben así.
- `RfcPacEnviaSolicitud` vacío: el ente público envía directo.
- Respuesta esperada (ejemplo 6.3): `ProcesarRespuestaResult CodEstatus="1000"`, y por folio: UUID, atributo `Respuesta` y `EstatusUUID` (1005 en el ejemplo). El acuse viene firmado por el SAT (`Signature Id="SelloSAT"`) → **guardar el XML completo** como evidencia.
- ⚠️ **Irreversible**: una vez aceptada, la cancelación procede. Requiere confirmación explícita del usuario.

## 6. Códigos

| Código | Significado | Acción |
|---|---|---|
| 1000 | Solo en ejemplo 6.3 (respuesta procesada) — observado, no contrato | Guardar acuse; revisar EstatusUUID de cada folio |
| 1100 | Solo en ejemplo 6.4 (hay pendientes) — observado | Procesar lista de UUID |
| 1005 | EstatusUUID del ejemplo 6.3, sin descripción | Registrar |
| 1101 | Consulta exitosa, sin solicitudes para el RFC receptor | Nada pendiente |
| 1102 | No hay peticiones en ese momento | Reintentar más tarde |
| 2000 | Existen CFDI relacionados al folio | Revisar relaciones antes de aceptar |
| 2001 | No existen CFDI relacionados al folio | Continuar |
| 2002 | El folio no pertenece al receptor | Verificar RFC y UUID |
| 30002 | Sello mal formado o inválido | Revisar firma del cuerpo (SHA-256, namespaces) |
| 30005 | Certificado no válido para entes públicos | Usar el certificado registrado y vigente |

Si falla la autenticación del encabezado, el servicio responde `s:Fault` (no un código) → excepción.

## 7. Problemas comunes

| Síntoma | Causa probable | Solución |
|---|---|---|
| `s:Fault` de autenticación | RFC no dado de alta, certificado distinto al registrado, reloj desfasado | Confirmar alta con el SAT; sincronizar NTP (Timestamp vence en 5 min) |
| `Cambió el formato de authorization()` | Se actualizó sat-ws-descarga-masiva | Fijar versión; ajustar `BODY_AUTENTICA` |
| 30002 | Algoritmo/namespaces de la firma del cuerpo | Firmar con SHA-256; comparar con ejemplo 6.1 |
| 30005 | Certificado no válido para el servicio | Usar el registrado y vigente |
| 2002 | UUID no corresponde al RFC | Usar la credencial del receptor |
| HTTP 404 en navegador | WSDL oculto (esperado) | Ninguna |
| Error TLS en Docker | Faltan certificados raíz | Instalar `ca-certificates` en la imagen |

## 8. Recomendaciones de integración (de la guía)

> Son recomendaciones generales de la guía. Si alguna choca con `AGENTS.md`, `CONTEXT.md` o `docs/adr/` (p. ej. el acceso al SAT por `sat-autenticacion`/`WebClientInterface`, o el rechazo del CSD en el módulo fiel), mandan las reglas del repo.

1. Módulo nuevo que **reutiliza la gestión de credenciales** existente, sin tocar el módulo de descarga masiva.
2. Agregar **tipo de certificado (FIEL/CSD)** al almacenamiento cifrado de credenciales.
3. **Job programado** (p. ej. cada hora) que llame `obtenerPeticionesPendientes()` y guarde UUID + CodEstatus en una tabla de solicitudes de cancelación.
4. **Decisión humana**: aceptar/rechazar desde una vista con confirmación explícita, ejecutado en un job (no bloquear la petición web).
5. **Evidencia**: guardar XML enviado y acuse recibido de cada `ProcesarRespuesta`.
6. **Pruebas sin red**: `sobre()` y `solicitudFirmada()` (verificar `u:Timestamp`, `o:BinarySecurityToken` y cuerpo); `enviar()` con `MockHandler` de Guzzle usando las respuestas 6.3 y 6.4.

## 9. Cliente de referencia

```php
<?php

declare(strict_types=1);

namespace App\Sat\AceptacionRechazo;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use GuzzleHttp\Client;
use PhpCfdi\Credentials\Credential;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTime;
use PhpCfdi\XmlCancelacion\Credentials;
use PhpCfdi\XmlCancelacion\Models\CancelAnswer;
use PhpCfdi\XmlCancelacion\XmlCancelacionHelper;
use RuntimeException;

final class AceptacionRechazoClient
{
    public const URL = 'https://prodcancelacionesep.clouda.sat.gob.mx/Cancelacion/AceptacionRechazoServiceEP.svc';
    private const NS_EP = 'http://cancelacfd.sat.gob.mx/entespublicos';
    private const NS_DATOS = 'http://cancelacfd.sat.gob.mx';
    private const ACTION = 'http://cancelacfd.sat.gob.mx/entespublicos/IAceptacionRechazoServiceEP/';
    private const BODY_AUTENTICA = '<s:Body><Autentica xmlns="http://DescargaMasivaTerceros.gob.mx"/></s:Body>';
    private const BODY_ABRE = '<s:Body xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';

    private readonly FielRequestBuilder $headerBuilder;
    private readonly XmlCancelacionHelper $bodySigner;

    public function __construct(
        private readonly Credential $credential,
        private readonly Client $http = new Client(['timeout' => 60]),
        private readonly string $url = self::URL,
    ) {
        // Paso 1: validar vigencia (no usar Fiel::isValid() si es CSD)
        if (! $credential->certificate()->validOn()) {
            throw new RuntimeException('El certificado no está vigente');
        }
        $this->headerBuilder = new FielRequestBuilder(new Fiel($credential));
        $this->bodySigner = new XmlCancelacionHelper(Credentials::createWithPhpCfdiCredential($credential));
    }

    public static function fromFiles(string $cerPath, string $keyPath, string $password): self
    {
        return new self(Credential::openFiles($cerPath, $keyPath, $password));
    }

    public function rfc(): string
    {
        return $this->credential->rfc();
    }

    /** Pasos 2 y 3: encabezado WS-Security de la librería + cuerpo propio */
    public function sobre(string $contenidoBody): string
    {
        $created = DateTime::now();
        $soap = $this->headerBuilder->authorization($created, $created->modify('+5 minutes'));
        if (! str_contains($soap, self::BODY_AUTENTICA)) {
            throw new RuntimeException('Cambió el formato de FielRequestBuilder::authorization()');
        }
        return str_replace(self::BODY_AUTENTICA, self::BODY_ABRE . $contenidoBody . '</s:Body>', $soap);
    }

    /** Paso 4: firma la SolicitudAceptacionRechazo y la adapta al sobre del servicio */
    public function solicitudFirmada(string $uuid, bool $aceptar): string
    {
        $firmada = $this->bodySigner->signCancellationAnswer(
            $uuid,
            $aceptar ? CancelAnswer::accept() : CancelAnswer::reject(),
            '', // RfcPacEnviaSolicitud: vacío, el ente público envía directo
            new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City')),
        );
        return $this->aSobreEntesPublicos($firmada);
    }

    private function aSobreEntesPublicos(string $firmada): string
    {
        $doc = new DOMDocument();
        $doc->loadXML($firmada);
        $raiz = $doc->documentElement ?? throw new RuntimeException('Solicitud firmada vacía');
        $atributos = '';
        foreach (['Fecha', 'RfcPacEnviaSolicitud', 'RfcReceptor'] as $nombre) {
            $valor = htmlspecialchars($raiz->getAttribute($nombre), ENT_XML1 | ENT_COMPAT);
            $atributos .= sprintf(' %s="%s"', $nombre, $valor);
        }
        $hijos = '';
        foreach ($raiz->childNodes as $hijo) {
            if ($hijo instanceof DOMElement) {
                $hijos .= $hijo->C14N(); // Folios y Signature con sus namespaces
            }
        }
        return "<SolicitudAceptacionRechazo{$atributos}>{$hijos}</SolicitudAceptacionRechazo>";
    }

    /** Paso 5: envío y detección de SOAP Fault */
    public function enviar(string $operacion, string $sobre): DOMDocument
    {
        $respuesta = $this->http->post($this->url, [
                'headers' => [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => '"' . self::ACTION . $operacion . '"',
                ],
                'body' => $sobre,
                'http_errors' => false,
            ]);
        $xml = (string) $respuesta->getBody();
        $doc = new DOMDocument();
        if ('' === $xml || ! @$doc->loadXML($xml)) {
            throw new RuntimeException("Respuesta no XML (HTTP {$respuesta->getStatusCode()})");
        }
        $fault = $doc->getElementsByTagNameNS('http://schemas.xmlsoap.org/soap/envelope/', 'Fault')->item(0);
        if (null !== $fault) {
            throw new RuntimeException('SOAP Fault: ' . trim($fault->textContent));
        }
        return $doc;
    }

    /** @return array{codEstatus: string, uuids: list<string>} */
    public function obtenerPeticionesPendientes(): array
    {
        $rfc = htmlspecialchars($this->rfc(), ENT_XML1);
        $body = sprintf(
            '<ObtenerPeticionesPendientes xmlns="%s"><RfcReceptor>%s</RfcReceptor></ObtenerPeticionesPendientes>',
            self::NS_EP,
            $rfc,
        );
        $doc = $this->enviar('ObtenerPeticionesPendientes', $this->sobre($body));
        $result = $doc->getElementsByTagNameNS(self::NS_EP, 'ObtenerPeticionesPendientesResult')->item(0);
        $uuids = [];
        foreach ($doc->getElementsByTagNameNS(self::NS_DATOS, 'UUID') as $nodo) {
            $uuids[] = trim($nodo->textContent);
        }
        return [
            'codEstatus' => $result instanceof DOMElement ? $result->getAttribute('CodEstatus') : '',
            'uuids' => $uuids,
        ];
    }

    /** @return array{codEstatus: string, folios: list<array{uuid: string, respuesta: string, estatusUuid: string}>} */
    public function procesarRespuesta(string $uuid, bool $aceptar): array
    {
        $body = sprintf(
            '<ProcesarRespuesta xmlns="%s">%s</ProcesarRespuesta>',
            self::NS_EP,
            $this->solicitudFirmada($uuid, $aceptar),
        );
        $doc = $this->enviar('ProcesarRespuesta', $this->sobre($body));
        $result = $doc->getElementsByTagNameNS(self::NS_EP, 'ProcesarRespuestaResult')->item(0);
        $folios = [];
        foreach ($doc->getElementsByTagNameNS(self::NS_DATOS, 'Folios') as $folio) {
            if (! $folio instanceof DOMElement) {
                continue;
            }
            $folios[] = [
                'uuid' => trim($folio->getElementsByTagNameNS(self::NS_DATOS, 'UUID')->item(0)?->textContent ?? ''),
                'respuesta' => $folio->getAttribute('Respuesta'),
                'estatusUuid' => trim($folio->getElementsByTagNameNS(self::NS_DATOS, 'EstatusUUID')->item(0)?->textContent ?? ''),
            ];
        }
        return [
            'codEstatus' => $result instanceof DOMElement ? $result->getAttribute('CodEstatus') : '',
            'folios' => $folios,
        ];
    }
}
```

Uso:

```php
$cliente = AceptacionRechazoClient::fromFiles('csd.cer', 'csd.key', 'contraseña');
$pendientes = $cliente->obtenerPeticionesPendientes();
// ['codEstatus' => '1100', 'uuids' => ['AE4B0CA8-...', 'D92516E5-...']]

$resultado = $cliente->procesarRespuesta('AE4B0CA8-8116-4699-8E20-77B6E9C507A2', aceptar: false);
```
