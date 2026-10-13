# Descarga SAT

Aplicación que usa la FIEL de un contribuyente para pedir, vigilar y descargar sus CFDI y Retenciones desde el Servicio Web de Descarga Masiva del SAT, y su CSD para responder las cancelaciones de CFDI que le piden como ente público.

## Language

### Identidad ante el SAT

**Fiel**:
Par de certificado (.cer) y llave privada (.key) con su contraseña, emitido por el SAT al RFC del contribuyente, con el que la aplicación se identifica ante el SAT. Todas las Fiel guardadas son del mismo RFC y las comparten todos los usuarios.
_Avoid_: e.firma, firma electrónica, certificado

**Fiel activa**:
La única Fiel con la que se presentan nuevas Solicitudes. Activar una Fiel desactiva las demás.
_Avoid_: Fiel actual, Fiel predeterminada

**CSD**:
Certificado de sello digital (.cer, .key y contraseña) del mismo RFC que las Fiel, registrado ante el SAT para responder Cancelaciones pendientes. Una Fiel nunca es un CSD ni al revés.
_Avoid_: sello, certificado, Fiel

**CSD activo**:
El único CSD con el que se hacen las Revisiones de cancelaciones y se envían las Respuestas de cancelación. Activar un CSD desactiva los demás.
_Avoid_: CSD actual, CSD predeterminado

### Descarga masiva

**Servicio**:
Cuál de los dos servicios de descarga masiva del SAT atiende una Solicitud: CFDI o Retenciones.
_Avoid_: tipo de descarga, endpoint

**Solicitud**:
Petición presentada al SAT, en un Servicio, para obtener los comprobantes o la metadata de un periodo, con sus filtros (emitidos/recibidos, tipo de comprobante, estado). Queda ligada a la Fiel con la que se presentó.
_Avoid_: consulta, petición, request

**Solicitud duplicada**:
Solicitud con exactamente los mismos parámetros que otra ya presentada; el SAT solo acepta dos en toda la vida del RFC.
_Avoid_: solicitud repetida

**Verificación**:
Pregunta periódica al SAT sobre el avance de una Solicitud hasta que llega a un estado final.
_Avoid_: consulta de estado, polling

**Paquete**:
Archivo ZIP que el SAT entrega como resultado de una Solicitud terminada; contiene los XML o la metadata.
_Avoid_: archivo, descarga, zip

**Contenido del Paquete**:
Los archivos extraídos del ZIP de un Paquete: un XML por comprobante, o el archivo de texto de metadata.
_Avoid_: los XML, facturas

### Estados de una Solicitud

**Aceptada**:
El SAT recibió la Solicitud y todavía no empieza a prepararla.

**En proceso**:
El SAT está preparando los Paquetes de la Solicitud.

**Terminada**:
El SAT tiene listos los Paquetes, pero la aplicación todavía no los descarga todos.

**Descargada**:
Estado final: todos los Paquetes de la Solicitud están descargados y extraídos.
_Avoid_: completada, finalizada

**Sin resultados**:
Estado final: el SAT terminó la Solicitud sin ningún Paquete porque no hubo comprobantes en el periodo.
_Avoid_: vacía

**Rechazada**:
Estado final: el SAT no aceptó la Solicitud al presentarla.

**Error**:
Estado final: el SAT reportó un error mientras procesaba la Solicitud.
_Avoid_: fallida

**Vencida**:
Estado final: el propio SAT declaró vencida la Solicitud.
_Avoid_: expirada, caducada

**Abandonada**:
Estado final: la aplicación dejó de verificar la Solicitud porque pasaron 72 horas desde que se presentó sin que el SAT le diera un estado final.
_Avoid_: expirada, timeout

### Estados de un Paquete

**Pendiente**:
El SAT informó el Paquete, pero todavía no se descarga.

**Descargado**:
El ZIP del Paquete ya está guardado en la aplicación.

**Extraído**:
El Contenido del Paquete ya está separado en su carpeta.

**Fallido**:
El Paquete no se pudo descargar después de agotar los reintentos automáticos.
_Avoid_: error, fallida

### Cancelaciones como ente público

**Cancelación pendiente**:
CFDI recibido por el RFC de la aplicación cuyo emisor pidió cancelarlo y que el SAT reporta como esperando la Respuesta de cancelación del RFC.
_Avoid_: petición, solicitud de cancelación, consulta

**Revisión de cancelaciones**:
Pregunta periódica al SAT por las Cancelaciones pendientes del RFC de la aplicación.
_Avoid_: consulta de pendientes, polling, Verificación

**Respuesta de cancelación**:
La decisión de aceptar o rechazar una Cancelación pendiente que se envía al SAT; no se puede deshacer.
_Avoid_: aceptación, rechazo, Solicitud

**Envío de respuesta**:
Cada intento de mandar una Respuesta de cancelación al SAT, con el XML enviado y, si llegó, su Acuse de respuesta.
_Avoid_: reintento, petición

**Acuse de respuesta**:
XML firmado por el SAT (SelloSAT) que confirma que recibió una Respuesta de cancelación; es la evidencia que se conserva.
_Avoid_: comprobante, recibo

### Estados de una Cancelación pendiente

**Por responder**:
El SAT la reporta pendiente y nadie ha enviado una Respuesta de cancelación que el SAT haya recibido.
_Avoid_: Pendiente, nueva

**Enviando**:
Un usuario decidió la Respuesta de cancelación y su Envío de respuesta está en curso.

**Aceptación enviada**:
Estado final: el SAT devolvió el Acuse de respuesta de una aceptación.
_Avoid_: Aceptada, cancelada

**Rechazo enviado**:
Estado final: el SAT devolvió el Acuse de respuesta de un rechazo.
_Avoid_: Rechazada

**Envío incierto**:
El Envío de respuesta terminó sin Acuse de respuesta, así que no se sabe si el SAT la recibió.
_Avoid_: fallida, error

**Retirada**:
Dejó de aparecer en la Revisión de cancelaciones sin que la aplicación la respondiera.
_Avoid_: Vencida, Abandonada, cancelada
