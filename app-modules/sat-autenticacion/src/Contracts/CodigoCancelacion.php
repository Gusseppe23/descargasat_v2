<?php

namespace DescargaSat\SatAutenticacion\Contracts;

/**
 * Códigos del servicio de Aceptación/Rechazo para entes públicos; ver docs/sat/aceptacion-rechazo-entes-publicos.md.
 */
enum CodigoCancelacion: int
{
    case RespuestaProcesada = 1000;
    case EstatusDelFolio = 1005;
    case HayPendientes = 1100;
    case SinSolicitudes = 1101;
    case SinPeticionesPorAhora = 1102;
    case ConCfdiRelacionados = 2000;
    case SinCfdiRelacionados = 2001;
    case FolioDeOtroReceptor = 2002;
    case SelloInvalido = 30002;
    case CertificadoNoValido = 30005;

    public function descripcion(): string
    {
        return match ($this) {
            self::RespuestaProcesada => 'Respuesta procesada',
            self::EstatusDelFolio => 'Estatus del folio sin descripción del SAT',
            self::HayPendientes => 'Hay Cancelaciones pendientes',
            self::SinSolicitudes => 'No hay solicitudes de cancelación para el RFC receptor',
            self::SinPeticionesPorAhora => 'No hay peticiones en este momento',
            self::ConCfdiRelacionados => 'Existen CFDI relacionados al folio',
            self::SinCfdiRelacionados => 'No existen CFDI relacionados al folio',
            self::FolioDeOtroReceptor => 'El folio no pertenece al receptor',
            self::SelloInvalido => 'Sello mal formado o inválido',
            self::CertificadoNoValido => 'Certificado no válido para entes públicos',
        };
    }
}
