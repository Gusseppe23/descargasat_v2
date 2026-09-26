<?php

namespace DescargaSat\Solicitudes\Support;

use Carbon\CarbonImmutable;
use DescargaSat\SatAutenticacion\Contracts\Servicio;
use DescargaSat\Solicitudes\Enums\EstadoComprobante;
use DescargaSat\Solicitudes\Enums\TipoComprobante;
use DescargaSat\Solicitudes\Enums\TipoDescarga;
use DescargaSat\Solicitudes\Enums\TipoSolicitud;
use PhpCfdi\SatWsDescargaMasiva\Services\Query\QueryParameters;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTimePeriod;
use PhpCfdi\SatWsDescargaMasiva\Shared\DocumentStatus;
use PhpCfdi\SatWsDescargaMasiva\Shared\DocumentType;
use PhpCfdi\SatWsDescargaMasiva\Shared\DownloadType;
use PhpCfdi\SatWsDescargaMasiva\Shared\RequestType;

/**
 * Lo que el usuario pide en una Solicitud, antes de presentarla al SAT.
 */
final readonly class ParametrosSolicitud
{
    public ?TipoComprobante $tipoComprobante;

    public EstadoComprobante $estadoComprobante;

    /**
     * El tipo de comprobante solo aplica a CFDI. El SAT solo entrega XML recibidos vigentes,
     * así que en ese caso el estado siempre es Vigentes.
     */
    public function __construct(
        public Servicio $servicio,
        public CarbonImmutable $fechaInicio,
        public CarbonImmutable $fechaFin,
        public TipoDescarga $tipoDescarga,
        public TipoSolicitud $tipoSolicitud,
        ?TipoComprobante $tipoComprobante,
        EstadoComprobante $estadoComprobante,
    ) {
        $this->tipoComprobante = $servicio === Servicio::Cfdi ? $tipoComprobante : null;
        $this->estadoComprobante = self::soloVigentes($tipoDescarga, $tipoSolicitud)
            ? EstadoComprobante::Vigentes
            : $estadoComprobante;
    }

    public static function soloVigentes(TipoDescarga $tipoDescarga, TipoSolicitud $tipoSolicitud): bool
    {
        return $tipoDescarga === TipoDescarga::Recibidos && $tipoSolicitud === TipoSolicitud::Xml;
    }

    /**
     * Los parámetros en el formato de la librería del SAT.
     *
     * El periodo va de 00:00:00 del día inicial a 23:59:59 del día final; si el día final es hoy,
     * termina en la hora actual para no pedir comprobantes del futuro.
     */
    public function paraSat(): QueryParameters
    {
        $fin = $this->fechaFin->endOfDay()->min(now());

        return QueryParameters::create(
            DateTimePeriod::createFromValues(
                $this->fechaInicio->startOfDay()->format('Y-m-d H:i:s'),
                $fin->format('Y-m-d H:i:s'),
            ),
            match ($this->tipoDescarga) {
                TipoDescarga::Emitidos => DownloadType::issued(),
                TipoDescarga::Recibidos => DownloadType::received(),
            },
            match ($this->tipoSolicitud) {
                TipoSolicitud::Xml => RequestType::xml(),
                TipoSolicitud::Metadata => RequestType::metadata(),
            },
        )
            ->withDocumentType(match ($this->tipoComprobante) {
                null => DocumentType::undefined(),
                TipoComprobante::Ingreso => DocumentType::ingreso(),
                TipoComprobante::Egreso => DocumentType::egreso(),
                TipoComprobante::Traslado => DocumentType::traslado(),
                TipoComprobante::Nomina => DocumentType::nomina(),
                TipoComprobante::Pago => DocumentType::pago(),
            })
            ->withDocumentStatus(match ($this->estadoComprobante) {
                EstadoComprobante::Todos => DocumentStatus::undefined(),
                EstadoComprobante::Vigentes => DocumentStatus::active(),
                EstadoComprobante::Cancelados => DocumentStatus::cancelled(),
            });
    }
}
