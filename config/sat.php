<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Servicio de Cancelación Aceptación/Rechazo para entes públicos
    |--------------------------------------------------------------------------
    |
    | El SAT no documenta un ambiente de pruebas: el URL por omisión es el de
    | producción. "firma" es el algoritmo de la firma del cuerpo de la Respuesta
    | de cancelación (sha256 o sha1, ver ADR-0005). "cada_minutos" es la
    | frecuencia de la Revisión de cancelaciones (de 1 a 59).
    |
    */

    'cancelaciones' => [
        'url' => env('SAT_CANCELACIONES_URL', 'https://prodcancelacionesep.clouda.sat.gob.mx/Cancelacion/AceptacionRechazoServiceEP.svc'),
        'timeout' => (int) env('SAT_CANCELACIONES_TIMEOUT', 60),
        'firma' => env('SAT_CANCELACIONES_FIRMA', 'sha256'),
        'cada_minutos' => (int) env('SAT_CANCELACIONES_CADA_MINUTOS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Servicio de Consulta de CFDI
    |--------------------------------------------------------------------------
    |
    | Servicio público del SAT (sin FIEL ni CSD) que dice si un CFDI está
    | vigente, cancelado o es cancelable, y si su emisor está en la lista EFOS.
    | Ver docs/sat/consulta-cfdi.md.
    |
    */

    'consulta_cfdi' => [
        'url' => env('SAT_CONSULTA_CFDI_URL', 'https://consultaqr.facturaelectronica.sat.gob.mx/ConsultaCFDIService.svc'),
        'timeout' => (int) env('SAT_CONSULTA_CFDI_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Portal de CFDI
    |--------------------------------------------------------------------------
    |
    | Descarga del XML y el PDF de un CFDI desde el portal del SAT con
    | phpcfdi/cfdi-sat-scraper, iniciando sesión con la Fiel. "timeout" aplica
    | a cada petición; una descarga hace varias (login, consulta y archivos).
    | Ver ADR-0007.
    |
    */

    'portal_cfdi' => [
        'timeout' => (int) env('SAT_PORTAL_CFDI_TIMEOUT', 60),
    ],

];
