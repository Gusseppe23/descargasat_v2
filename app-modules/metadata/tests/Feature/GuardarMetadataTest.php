<?php

use DescargaSat\Descargas\Jobs\DescargarPaquete;
use DescargaSat\Metadata\Contracts\MetadataCfdi;
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
 * Descarga y extrae un Paquete con el contenido dado, como lo haría la cola con el SAT doblado.
 *
 * @param  array<string, string>  $archivos
 */
function descargarPaqueteCon(array $archivos, string $idPaqueteSat = 'ABC_01'): Paquete
{
    $webClient = (new WebClientFalso)->responder('autenticacion.xml')->responderDescarga(ZipDePrueba::con($archivos));
    app()->instance(WebClientInterface::class, $webClient);
    $solicitud = Solicitud::factory()->create(['fiel_id' => 1, 'estado' => EstadoSolicitud::Terminada]);
    $paquete = Paquete::factory()->create(['solicitud_id' => $solicitud->id, 'id_paquete_sat' => $idPaqueteSat]);

    DescargarPaquete::dispatchSync($paquete->id);
    test()->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true]);
    test()->assertDatabaseCount('failed_jobs', 0);

    return $paquete;
}

/**
 * Un .txt de Metadata como lo entrega el SAT: separado por ~ y con fin de línea CRLF.
 */
function txtDeMetadata(string ...$renglones): string
{
    $encabezado = 'Uuid~RfcEmisor~NombreEmisor~RfcReceptor~NombreReceptor~PacCertifico~FechaEmision~FechaCertificacionSat~Monto~EfectoComprobante~Estatus~FechaCancelacion';

    return implode("\r\n", [$encabezado, ...$renglones])."\r\n";
}

beforeEach(function () {
    Storage::fake('local');
    FielesFalsas::usar();
});

test('stores by uuid the metadata of an extracted paquete', function () {
    descargarPaqueteCon(['ABC_01.txt' => txtDeMetadata(
        '7ce14eee-219f-453a-a9a9-e7f111e95413~ESS040127PZ1~EDUCACIÓN SUPERIOR SUIZA~PME380607P35~PETRÓLEOS MEXICANOS~PPD101129EA3~2026-01-12 13:07:49~2026-01-12 14:08:13~1250~I~1~',
        '2FBF4E27-8BFF-43F6-AC44-5375BF268E7D~GDF9712054NA~GOBIERNO DE LA CIUDAD DE MÉXICO~PME380607P35~PETRÓLEOS MEXICANOS~SAT970701NN3~2026-01-13 12:50:15~2026-01-13 14:02:42~14121.29~E~0~2026-02-01 09:15:00',
    )]);

    $metadata = app(MetadataCfdi::class)->deUuids([
        '7CE14EEE-219F-453A-A9A9-E7F111E95413',
        '2FBF4E27-8BFF-43F6-AC44-5375BF268E7D',
        'FFFFFFFF-0000-0000-0000-000000000000',
    ]);

    expect($metadata)->toHaveKeys(['7CE14EEE-219F-453A-A9A9-E7F111E95413', '2FBF4E27-8BFF-43F6-AC44-5375BF268E7D'])
        ->and($metadata)->toHaveCount(2);

    $vigente = $metadata['7CE14EEE-219F-453A-A9A9-E7F111E95413'];
    expect($vigente->rfcEmisor)->toBe('ESS040127PZ1')
        ->and($vigente->nombreEmisor)->toBe('EDUCACIÓN SUPERIOR SUIZA')
        ->and($vigente->rfcReceptor)->toBe('PME380607P35')
        ->and($vigente->nombreReceptor)->toBe('PETRÓLEOS MEXICANOS')
        ->and($vigente->rfcPac)->toBe('PPD101129EA3')
        ->and($vigente->fechaEmision->format('Y-m-d H:i:s'))->toBe('2026-01-12 13:07:49')
        ->and($vigente->fechaCertificacionSat->format('Y-m-d H:i:s'))->toBe('2026-01-12 14:08:13')
        ->and($vigente->monto)->toBe('1250.00')
        ->and($vigente->efecto)->toBe('I')
        ->and($vigente->estatus)->toBe('1')
        ->and($vigente->fechaCancelacion)->toBeNull();

    $cancelado = $metadata['2FBF4E27-8BFF-43F6-AC44-5375BF268E7D'];
    expect($cancelado->monto)->toBe('14121.29')
        ->and($cancelado->estatus)->toBe('0')
        ->and($cancelado->fechaCancelacion?->format('Y-m-d H:i:s'))->toBe('2026-02-01 09:15:00');
});

test('the same uuid in a later paquete updates its metadata', function () {
    descargarPaqueteCon(['ABC_01.txt' => txtDeMetadata(
        '7CE14EEE-219F-453A-A9A9-E7F111E95413~ESS040127PZ1~EDUCACION SUPERIOR SUIZA~PME380607P35~PETROLEOS MEXICANOS~PPD101129EA3~2026-01-12 13:07:49~2026-01-12 14:08:13~1250~I~1~',
    )], 'ABC_01');
    descargarPaqueteCon(['DEF_01.txt' => txtDeMetadata(
        '7CE14EEE-219F-453A-A9A9-E7F111E95413~ESS040127PZ1~EDUCACION SUPERIOR SUIZA~PME380607P35~PETROLEOS MEXICANOS~PPD101129EA3~2026-01-12 13:07:49~2026-01-12 14:08:13~1250~I~0~2026-03-01 10:00:00',
    )], 'DEF_01');

    $metadata = app(MetadataCfdi::class)->deUuids(['7CE14EEE-219F-453A-A9A9-E7F111E95413']);

    expect($metadata)->toHaveCount(1)
        ->and($metadata['7CE14EEE-219F-453A-A9A9-E7F111E95413']->estatus)->toBe('0')
        ->and($metadata['7CE14EEE-219F-453A-A9A9-E7F111E95413']->fechaCancelacion?->format('Y-m-d H:i:s'))->toBe('2026-03-01 10:00:00');
});

test('ignores the terceros file and paquetes of xml', function () {
    descargarPaqueteCon([
        'ABC_01.txt' => txtDeMetadata(
            '7CE14EEE-219F-453A-A9A9-E7F111E95413~ESS040127PZ1~EDUCACION SUPERIOR SUIZA~PME380607P35~PETROLEOS MEXICANOS~PPD101129EA3~2026-01-12 13:07:49~2026-01-12 14:08:13~1250~I~1~',
        ),
        'ABC_tercero.txt' => "Uuid~RfcACuentaTerceros~NombreACuentaTerceros\r\n2FBF4E27-8BFF-43F6-AC44-5375BF268E7D~GDF9712054NA~GOBIERNO DE LA CIUDAD DE MEXICO\r\n",
    ], 'ABC_01');
    descargarPaqueteCon(['D92516E5-A881-423C-B40F-384008DFE03F.xml' => '<cfdi:Comprobante Total="100.00"/>'], 'XML_01');

    expect(array_keys(app(MetadataCfdi::class)->deUuids([
        '7CE14EEE-219F-453A-A9A9-E7F111E95413',
        '2FBF4E27-8BFF-43F6-AC44-5375BF268E7D',
        'D92516E5-A881-423C-B40F-384008DFE03F',
    ])))->toBe(['7CE14EEE-219F-453A-A9A9-E7F111E95413']);
});

test('a txt without the expected header keeps the paquete extracted and notes the problem', function () {
    $paquete = descargarPaqueteCon(['ABC_01.txt' => "Columna~Otra\r\n7CE14EEE-219F-453A-A9A9-E7F111E95413~algo\r\n"]);

    expect($paquete->fresh()->estado)->toBe(EstadoPaquete::Extraido)
        ->and($paquete->fresh()->ultimo_problema)->toBe('El archivo ABC_01.txt no tiene el encabezado de la Metadata del SAT; no se guardó su Metadata.')
        ->and(app(MetadataCfdi::class)->deUuids(['7CE14EEE-219F-453A-A9A9-E7F111E95413']))->toBe([]);
});

test('tells which uuids still have no metadata', function () {
    descargarPaqueteCon(['ABC_01.txt' => txtDeMetadata(
        '7CE14EEE-219F-453A-A9A9-E7F111E95413~ESS040127PZ1~EDUCACION SUPERIOR SUIZA~PME380607P35~PETROLEOS MEXICANOS~PPD101129EA3~2026-01-12 13:07:49~2026-01-12 14:08:13~1250~I~1~',
    )]);

    expect(app(MetadataCfdi::class)->sinMetadata([
        '7ce14eee-219f-453a-a9a9-e7f111e95413',
        'D92516E5-A881-423C-B40F-384008DFE03F',
    ]))->toBe(['D92516E5-A881-423C-B40F-384008DFE03F']);
});
