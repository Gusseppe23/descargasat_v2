<?php

use DescargaSat\Descargas\Jobs\DescargarPaquete;
use DescargaSat\Paquetes\Contracts\EstadoPaquete;
use DescargaSat\Paquetes\Models\Paquete;
use DescargaSat\Solicitudes\Contracts\EstadoSolicitud;
use DescargaSat\Solicitudes\Models\Solicitud;
use Illuminate\Support\Facades\Storage;
use PhpCfdi\SatWsDescargaMasiva\WebClient\WebClientInterface;
use Tests\Support\FielesFalsas;
use Tests\Support\WebClientFalso;
use Tests\Support\ZipDePrueba;

/**
 * Un Paquete Pendiente de una Solicitud Terminada, presentada con la Fiel de prueba.
 */
function paquetePendiente(): Paquete
{
    $solicitud = Solicitud::factory()->create(['fiel_id' => 1, 'estado' => EstadoSolicitud::Terminada]);

    return Paquete::factory()->create(['solicitud_id' => $solicitud->id, 'id_paquete_sat' => 'ABC_01']);
}

/**
 * Sustituye el cliente web del SAT: responde la autenticación y luego lo que se prepare.
 */
function satDescarga(): WebClientFalso
{
    $webClient = (new WebClientFalso)->responder('autenticacion.xml');
    app()->instance(WebClientInterface::class, $webClient);

    return $webClient;
}

test('downloads the paquete with the fiel of its solicitud, stores the zip and extracts it', function () {
    Storage::fake('local');
    FielesFalsas::usar();
    $zip = ZipDePrueba::con(['uuid-1.xml' => '<cfdi:Comprobante Total="100.00"/>']);
    $sat = satDescarga()->responderDescarga($zip);
    $paquete = paquetePendiente();
    $carpeta = "paquetes/{$paquete->solicitud_id}/ABC_01";

    DescargarPaquete::dispatchSync($paquete->id);

    expect($sat->peticiones[1]->getUri())->toBe('https://cfdidescargamasiva.clouda.sat.gob.mx/DescargaMasivaService.svc')
        ->and($sat->peticiones[1]->getBody())->toContain('ABC_01')
        ->and(Storage::disk('local')->get("{$carpeta}.zip"))->toBe($zip)
        ->and(Storage::disk('local')->get("{$carpeta}/uuid-1.xml"))->toBe('<cfdi:Comprobante Total="100.00"/>')
        ->and($paquete->fresh()->estado)->toBe(EstadoPaquete::Extraido);
});

test('marks the solicitud as descargada only when its last paquete is extracted', function () {
    Storage::fake('local');
    FielesFalsas::usar();
    $zip = ZipDePrueba::con(['uuid-1.xml' => '<cfdi:Comprobante/>']);
    satDescarga()->responderDescarga($zip)->responder('autenticacion.xml')->responderDescarga($zip);
    $solicitud = Solicitud::factory()->create(['fiel_id' => 1, 'estado' => EstadoSolicitud::Terminada]);
    [$primero, $segundo] = Paquete::factory()->count(2)->sequence(
        ['id_paquete_sat' => 'ABC_01'],
        ['id_paquete_sat' => 'ABC_02'],
    )->create(['solicitud_id' => $solicitud->id]);

    DescargarPaquete::dispatchSync($primero->id);
    $despuesDelPrimero = $solicitud->fresh()->estado;

    DescargarPaquete::dispatchSync($segundo->id);

    expect($despuesDelPrimero)->toBe(EstadoSolicitud::Terminada)
        ->and($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Descargada);
});

test('fails so the queue retries it and records the problem when the download goes wrong', function (Closure $prepararSat, string $problema, EstadoPaquete $estado) {
    Storage::fake('local');
    FielesFalsas::usar();
    $prepararSat(satDescarga());
    $paquete = paquetePendiente();

    // Se llama a handle() directo: la cola síncrona de pruebas no reintenta y llamaría a failed() de inmediato.
    expect(fn () => app()->call([new DescargarPaquete($paquete->id), 'handle']))->toThrow(RuntimeException::class);

    $paquete->refresh();
    expect($paquete->estado)->toBe($estado)
        ->and($paquete->ultimo_problema)->toStartWith($problema);
})->with([
    'no connection' => [fn (WebClientFalso $sat) => $sat->sinConexion(), 'No se pudo contactar al SAT', EstadoPaquete::Pendiente],
    'error code' => [
        fn (WebClientFalso $sat) => $sat->responderDescarga('', 5004, 'No se encontró la información'),
        'El SAT respondió 5004: No se encontró la información',
        EstadoPaquete::Pendiente,
    ],
    'damaged zip' => [fn (WebClientFalso $sat) => $sat->responderDescarga('esto no es un zip'), 'El ZIP del Paquete ABC_01 no se puede abrir.', EstadoPaquete::Descargado],
]);

test('is retried three times after 1, 5 and 15 minutes on the database queue', function () {
    $job = new DescargarPaquete(1);

    expect($job->tries)->toBe(4)
        ->and($job->backoff())->toBe([60, 300, 900])
        ->and($job->connection)->toBe('database');
});

test('marks the paquete as fallido when the retries run out', function () {
    $paquete = paquetePendiente();

    (new DescargarPaquete($paquete->id))->failed(new RuntimeException('No se pudo contactar al SAT'));

    expect($paquete->fresh()->estado)->toBe(EstadoPaquete::Fallido);
});

test('marks the paquete as fallido at once when the fiel is no longer valid', function () {
    Storage::fake('local');
    FielesFalsas::usar();
    $sat = new WebClientFalso;
    app()->instance(WebClientInterface::class, $sat);
    $this->travel(5)->years();
    $paquete = paquetePendiente();
    $job = new DescargarPaquete($paquete->id);

    app()->call([$job, 'handle']);

    $paquete->refresh();
    expect($paquete->estado)->toBe(EstadoPaquete::Fallido)
        ->and($paquete->ultimo_problema)->toStartWith('La Fiel 30001000000500003416 venció el')
        ->and($sat->peticiones)->toBeEmpty();
});

test('rejects a zip that tries to write outside the folder of the paquete', function () {
    Storage::fake('local');
    FielesFalsas::usar();
    satDescarga()->responderDescarga(ZipDePrueba::con([
        'uuid-1.xml' => '<cfdi:Comprobante/>',
        '../../fuera.txt' => 'no debería existir',
    ]));
    $paquete = paquetePendiente();

    expect(fn () => app()->call([new DescargarPaquete($paquete->id), 'handle']))->toThrow(RuntimeException::class);

    Storage::disk('local')->assertMissing('paquetes/fuera.txt');
    expect($paquete->fresh()->ultimo_problema)->toBe('El ZIP del Paquete ABC_01 contiene una ruta no permitida: ../../fuera.txt');
});
