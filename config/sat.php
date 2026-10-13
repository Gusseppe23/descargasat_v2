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

];
