# Referencia técnica: Servicio de Consulta de CFDI (SAT)

> Fuente: *Documentación del Servicio de Consulta de CFDI*, SAT v1.3, noviembre 2020.

## 1. Descripción

Servicio público del SAT para verificar el estado de un CFDI (vigente, cancelado, cancelable) en sus bases de datos, y si el RFC emisor está en la lista de Empresas que Facturan Operaciones Simuladas (EFOS, Art. 69-B CFF). Es el mismo servicio que usa el validador público / QR de cualquier representación impresa de CFDI.

**No requiere FIEL ni CSD.** La póliza del WSDL declara `RequireClientCertificate="false"`: la autenticación es solo transporte (TLS), sin WS-Security ni firma.

## 2. Contrato

| Dato | Valor |
|---|---|
| URL / WSDL | `https://consultaqr.facturaelectronica.sat.gob.mx/ConsultaCFDIService.svc` (`?wsdl` para el WSDL) |
| Namespace | `http://tempuri.org/` |
| Protocolo | SOAP 1.1, document/literal, sobre HTTPS |
| Operación | `Consulta` |
| SOAPAction | `http://tempuri.org/IConsultaCFDIService/Consulta` |

### Petición

```xml
<Consulta xmlns="http://tempuri.org/">
  <expresionImpresa>?re=<RFC_EMISOR>&rr=<RFC_RECEPTOR>&tt=<TOTAL>&id=<UUID></expresionImpresa>
</Consulta>
```

`expresionImpresa` es la cadena pública estándar que trae el QR/representación impresa de cualquier CFDI (`re` = RFC emisor, `rr` = RFC receptor, `tt` = total, `id` = UUID).

### Respuesta (`Acuse`)

| Campo | Tipo | Descripción |
|---|---|---|
| `CodigoEstatus` | string | Mensaje de rechazo/aceptación (ver tabla de códigos) |
| `Estado` | string | `Vigente`, `Cancelado` o `No Encontrado` |
| `EsCancelable` | string | Si el comprobante admite cancelación |
| `EstatusCancelacion` | string | Detalle del estatus de cancelación, si aplica |
| `ValidacionEFOS` | string | `100` si el RFC emisor está en la lista EFOS; `200` si no |

## 3. Códigos de respuesta

**Rechazo:**
| Código | Significado |
|---|---|
| N 601 | La expresión impresa proporcionada no es válida (no respetó el formato) |
| N 602 | Comprobante no encontrado (el UUID no está en la Base de Datos del SAT) |

**Aceptación:**
| Código | Significado |
|---|---|
| S | Comprobante obtenido satisfactoriamente |

**Validación EFOS del RFC emisor:**
| Código | Significado |
|---|---|
| 100 | El emisor está en la lista de empresas que facturan operaciones simuladas (Art. 69-B CFF) — mostrar al usuario el aviso completo y las ligas de consulta del listado (ver PDF fuente) |
| 200 | El emisor no está en la lista EFOS |

## 4. Capacidad de respuesta

El servicio soporta hasta 2 millones de consultas por hora (accede a bases transaccionales del SAT). El SAT pide no aumentar la cantidad de consultas por hora para evitar impactos en la respuesta del servicio — no se anticipa necesitar throttling propio dado el volumen de esta dependencia, pero queda como punto a vigilar si el consumo crece mucho.

## 5. Notas de implementación

- A diferencia de Timbrado/Cancelación, aquí no hay credencial que validar ni vigencia de certificado que revisar: es una llamada SOAP simple, sin encabezado WS-Security.
- Nunca llamar al endpoint real del SAT desde pruebas, tinker, seeders ni scripts.
