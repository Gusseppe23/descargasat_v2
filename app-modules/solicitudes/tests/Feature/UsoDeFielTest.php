<?php

use DescargaSat\Fiel\Contracts\UsoDeFiel;
use DescargaSat\Solicitudes\Enums\EstadoSolicitud;
use DescargaSat\Solicitudes\Models\Solicitud;

test('a fiel with a solicitud still in progress is in use', function (EstadoSolicitud $estado) {
    Solicitud::factory()->create(['fiel_id' => 7, 'estado' => $estado]);

    expect(app(UsoDeFiel::class)->tieneSolicitudesEnCurso(7))->toBeTrue();
})->with([
    EstadoSolicitud::Aceptada,
    EstadoSolicitud::EnProceso,
    EstadoSolicitud::Terminada,
]);

test('a fiel whose solicitudes all reached a final state is not in use', function (EstadoSolicitud $estado) {
    Solicitud::factory()->create(['fiel_id' => 7, 'estado' => $estado]);

    expect(app(UsoDeFiel::class)->tieneSolicitudesEnCurso(7))->toBeFalse();
})->with([
    EstadoSolicitud::Descargada,
    EstadoSolicitud::SinResultados,
    EstadoSolicitud::Rechazada,
    EstadoSolicitud::Error,
    EstadoSolicitud::Vencida,
    EstadoSolicitud::Abandonada,
]);

test('solicitudes in progress of another fiel do not count', function () {
    Solicitud::factory()->create(['fiel_id' => 8, 'estado' => EstadoSolicitud::EnProceso]);

    expect(app(UsoDeFiel::class)->tieneSolicitudesEnCurso(7))->toBeFalse();
});
