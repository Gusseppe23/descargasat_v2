<?php

namespace DescargaSat\Cancelaciones\Jobs;

use DescargaSat\Cancelaciones\Contracts\EstadoCancelacion;
use DescargaSat\Cancelaciones\Contracts\ResultadoDeEnvio;
use DescargaSat\Cancelaciones\Models\EnvioDeRespuesta;
use DescargaSat\SatAutenticacion\Contracts\AcuseDeRespuesta;
use DescargaSat\SatAutenticacion\Contracts\CodigoCancelacion;
use DescargaSat\SatAutenticacion\Contracts\CsdNoVigente;
use DescargaSat\SatAutenticacion\Contracts\ProblemaConElSat;
use DescargaSat\SatAutenticacion\Contracts\Respuesta;
use DescargaSat\SatAutenticacion\Contracts\ServicioCancelaciones;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Envía una Respuesta de cancelación al SAT. Nunca se reintenta: aceptar no se puede deshacer (ADR-0006).
 */
class EnviarRespuesta implements ShouldQueue
{
    use Queueable;

    /**
     * Sin reintentos: si el worker se cae a medio envío, failed() deja la cancelación en Envío incierto.
     */
    public int $tries = 1;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    /**
     * Códigos con los que se sabe que el SAT no registró la Respuesta de cancelación.
     */
    private const CODIGOS_NO_PROCESADA = [
        CodigoCancelacion::FolioDeOtroReceptor,
        CodigoCancelacion::SelloInvalido,
        CodigoCancelacion::CertificadoNoValido,
    ];

    public function __construct(public readonly int $envioId)
    {
        $this->onConnection('database');
    }

    public function handle(ServicioCancelaciones $servicioCancelaciones): void
    {
        $envio = EnvioDeRespuesta::with('cancelacion')->find($this->envioId);

        if ($envio === null || $envio->resultado !== null) {
            return;
        }

        $enviado = Cache::lock("cancelaciones:envio:{$envio->cancelacion->uuid}", $this->timeout + 60)
            ->get(fn () => $this->enviar($envio, $servicioCancelaciones));

        if ($enviado === false) {
            $this->fail(new RuntimeException('Otro Envío de respuesta del mismo UUID estaba en curso.'));
        }
    }

    /**
     * La cola se rindió (worker caído, timeout o un error inesperado): no se sabe si el SAT recibió el envío.
     */
    public function failed(?Throwable $error): void
    {
        $envio = EnvioDeRespuesta::with('cancelacion')->find($this->envioId);

        if ($envio === null || $envio->resultado !== null) {
            return;
        }

        $this->terminar($envio, ResultadoDeEnvio::Incierto, EstadoCancelacion::EnvioIncierto, [
            'problema' => 'El envío se interrumpió'.($error === null ? '.' : ": {$error->getMessage()}"),
        ]);
    }

    private function enviar(EnvioDeRespuesta $envio, ServicioCancelaciones $servicioCancelaciones): bool
    {
        try {
            $acuse = $servicioCancelaciones->responder($envio->csd_id, $envio->cancelacion->uuid, $envio->respuesta);
        } catch (CsdNoVigente|ModelNotFoundException $error) {
            $problema = $error instanceof CsdNoVigente ? $error->getMessage() : 'El CSD del envío ya no existe.';
            $this->terminar($envio, ResultadoDeEnvio::NoProcesada, EstadoCancelacion::PorResponder, ['problema' => $problema]);

            return true;
        } catch (ProblemaConElSat $error) {
            $this->terminar($envio, ResultadoDeEnvio::Incierto, EstadoCancelacion::EnvioIncierto, [
                'problema' => $error->getMessage(),
                'ruta_enviado' => $this->guardarEvidencia($envio, 'enviado', $error->xmlEnviado),
            ]);

            return true;
        }

        $this->registrarAcuse($envio, $acuse);

        return true;
    }

    private function registrarAcuse(EnvioDeRespuesta $envio, AcuseDeRespuesta $acuse): void
    {
        $estatusUuid = $acuse->folio($envio->cancelacion->uuid)?->estatusUuid;
        $codigo = $acuse->codigo();
        $codigoDelFolio = $estatusUuid !== null && ctype_digit($estatusUuid) ? CodigoCancelacion::tryFrom((int) $estatusUuid) : null;

        $datos = [
            'codigo_sat' => $acuse->codEstatus,
            'estatus_uuid' => $estatusUuid,
            'ruta_enviado' => $this->guardarEvidencia($envio, 'enviado', $acuse->xmlEnviado),
            'ruta_recibido' => $this->guardarEvidencia($envio, 'recibido', $acuse->xmlRecibido),
        ];

        $noProcesada = collect([$codigo, $codigoDelFolio])->first(fn (?CodigoCancelacion $codigo): bool => in_array($codigo, self::CODIGOS_NO_PROCESADA, true));

        if ($noProcesada instanceof CodigoCancelacion) {
            $this->terminar($envio, ResultadoDeEnvio::NoProcesada, EstadoCancelacion::PorResponder, $datos + [
                'problema' => "El SAT respondió {$noProcesada->value}: {$noProcesada->descripcion()}.",
            ]);

            return;
        }

        if ($codigo === CodigoCancelacion::RespuestaProcesada && $acuse->selladoPorElSat) {
            $estado = $envio->respuesta === Respuesta::Aceptacion ? EstadoCancelacion::AceptacionEnviada : EstadoCancelacion::RechazoEnviado;
            $this->terminar($envio, ResultadoDeEnvio::AcuseRecibido, $estado, $datos);

            return;
        }

        $this->terminar($envio, ResultadoDeEnvio::Incierto, EstadoCancelacion::EnvioIncierto, $datos + [
            'problema' => $codigo === CodigoCancelacion::RespuestaProcesada
                ? 'El SAT respondió 1000 pero sin SelloSAT, así que no hay Acuse de respuesta.'
                : "El SAT respondió un código inesperado: {$acuse->codEstatus}.",
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function terminar(EnvioDeRespuesta $envio, ResultadoDeEnvio $resultado, EstadoCancelacion $estado, array $datos): void
    {
        DB::transaction(function () use ($envio, $resultado, $estado, $datos): void {
            $envio->update($datos + ['resultado' => $resultado, 'terminado_el' => now()]);
            $envio->cancelacion->update(['estado' => $estado]);
        });
    }

    private function guardarEvidencia(EnvioDeRespuesta $envio, string $archivo, ?string $xml): ?string
    {
        if ($xml === null) {
            return null;
        }

        $ruta = $envio->rutaDeEvidencia($archivo);
        Storage::disk('local')->put($ruta, $xml);

        return $ruta;
    }
}
