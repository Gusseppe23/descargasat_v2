<?php

use DescargaSat\Metadata\Models\MetadataDeCfdi;
use DescargaSat\SatAutenticacion\Contracts\WebClientCancelaciones;
use DescargaSat\Solicitudes\Models\Solicitud;
use Illuminate\Support\Str;
use PhpCfdi\SatWsDescargaMasiva\WebClient\WebClientInterface;
use Tests\Support\CsdsFalsos;
use Tests\Support\FielesFalsas;
use Tests\Support\WebClientFalso;

/**
 * Los dos UUID que reporta el fixture cancelaciones-pendientes.xml, en ese orden.
 */
const PRIMERA_PENDIENTE = 'AE4B0CA8-8116-4699-8E20-77B6E9C507A2';
const SEGUNDA_PENDIENTE = 'D92516E5-A881-423C-B40F-384008DFE03F';

/**
 * Dobla al SAT: el servicio de cancelaciones reporta los dos UUID en cada Revisión y la Descarga masiva
 * acepta las Solicitudes por folio dadas, cada una con su propio IdSolicitud.
 */
function satReportaPendientesYAceptaFolios(int $revisiones = 1, int $folios = 2): WebClientFalso
{
    $cancelaciones = new WebClientFalso;

    for ($i = 0; $i < $revisiones; $i++) {
        $cancelaciones->responder('cancelaciones-pendientes.xml');
    }

    app()->instance(WebClientCancelaciones::class, $cancelaciones);

    $descargaMasiva = new WebClientFalso;
    $folioAceptado = (string) file_get_contents(base_path('tests/Fixtures/sat/solicitud-folio-aceptada.xml'));

    for ($i = 0; $i < $folios; $i++) {
        $descargaMasiva->responder('autenticacion.xml')
            ->responderTexto(str_replace('acf13d76-1d37-453c-9950-9715ec254cf7', (string) Str::uuid(), $folioAceptado));
    }

    app()->instance(WebClientInterface::class, $descargaMasiva);

    return $descargaMasiva;
}

/**
 * Corre una Revisión de cancelaciones y después la cola, como lo harían el scheduler y el worker.
 */
function revisarYAtenderLaCola(): void
{
    test()->artisan('sat:cancelaciones')->assertSuccessful();
    test()->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true]);
    test()->assertDatabaseCount('failed_jobs', 0);
}

function guardarMetadataDe(string $uuid): void
{
    MetadataDeCfdi::create([
        'uuid' => $uuid,
        'rfc_emisor' => 'EKU9003173C9',
        'rfc_receptor' => 'XAXX010101000',
        'fecha_emision' => '2026-11-01 10:00:00',
        'monto' => '100.00',
        'efecto' => 'I',
        'estatus' => '1',
        'paquete_id' => 1,
    ]);
}

/**
 * @return list<string>
 */
function uuidsPedidos(): array
{
    return Solicitud::query()->where('automatica', true)->orderBy('id')->pluck('uuid')->all();
}

beforeEach(function () {
    CsdsFalsos::usar();
    FielesFalsas::usar();
});

test('after a revision it requests the por responder uuids without metadata and not the ones that have it', function () {
    satReportaPendientesYAceptaFolios();
    guardarMetadataDe(PRIMERA_PENDIENTE);

    revisarYAtenderLaCola();

    expect(uuidsPedidos())->toBe([SEGUNDA_PENDIENTE]);
});

test('requests at most the configured number per revision and leaves the rest for the next one', function () {
    config(['sat.metadata.maximo_por_revision' => 1]);
    satReportaPendientesYAceptaFolios(revisiones: 2);

    revisarYAtenderLaCola();
    expect(uuidsPedidos())->toBe([PRIMERA_PENDIENTE]);

    revisarYAtenderLaCola();
    expect(uuidsPedidos())->toBe([PRIMERA_PENDIENTE, SEGUNDA_PENDIENTE]);
});
