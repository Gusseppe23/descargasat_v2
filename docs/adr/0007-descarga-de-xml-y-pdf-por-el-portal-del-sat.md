# Descarga de XML y PDF por el portal del SAT

Para descargar el XML y el PDF de un CFDI en una sola llamada usamos un segundo canal hacia el SAT: el portal de CFDI (portalcfdi.facturaelectronica.sat.gob.mx), leído con `phpcfdi/cfdi-sat-scraper` e iniciando sesión con la Fiel. El Servicio Web de Descarga Masiva también acepta filtrar por UUID, pero es asíncrono (Solicitud, Verificación y Paquete) y no entrega el PDF, que solo existe en el portal. Descartamos la CIEC porque obliga a resolver captchas con un servicio externo y a guardar otro secreto.

El portal no es un servicio documentado: el SAT ya cambió su HTML varias veces y la librería dejó de funcionar hasta su siguiente versión. Por eso la versión de la librería queda fijada (`^5.1`), todo el acceso pasa por el contrato `PortalCfdi` de `sat-autenticacion` y un cambio del portal solo afecta a la descarga de CFDI, no a las Solicitudes ni a las cancelaciones. Aceptar la librería obligó a bajar Guzzle de 8 a 7.

El portal no envía completa su cadena TLS y el servidor de inicio de sesión usa cifrados que OpenSSL 3 rechaza por omisión. El cliente HTTP del portal, y solo ese, agrega el intermedio GlobalSign RSA OV SSL CA 2018 (guardado en el repo, vence el 21/11/2028) a los certificados raíz del sistema y baja el nivel de seguridad de los cifrados (`DEFAULT@SECLEVEL=1`). La verificación TLS nunca se desactiva.
