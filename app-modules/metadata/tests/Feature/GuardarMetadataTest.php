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

test('ignores the terceros file', function () {
    descargarPaqueteCon([
        'ABC_01.txt' => txtDeMetadata(
            '7CE14EEE-219F-453A-A9A9-E7F111E95413~ESS040127PZ1~EDUCACION SUPERIOR SUIZA~PME380607P35~PETROLEOS MEXICANOS~PPD101129EA3~2026-01-12 13:07:49~2026-01-12 14:08:13~1250~I~1~',
        ),
        'ABC_tercero.txt' => "Uuid~RfcACuentaTerceros~NombreACuentaTerceros\r\n2FBF4E27-8BFF-43F6-AC44-5375BF268E7D~GDF9712054NA~GOBIERNO DE LA CIUDAD DE MEXICO\r\n",
    ]);

    expect(array_keys(app(MetadataCfdi::class)->deUuids([
        '7CE14EEE-219F-453A-A9A9-E7F111E95413',
        '2FBF4E27-8BFF-43F6-AC44-5375BF268E7D',
    ])))->toBe(['7CE14EEE-219F-453A-A9A9-E7F111E95413']);
});

test('a damaged xml or one without timbre notes the problem and the other xml are still stored', function () {
    $paquete = descargarPaqueteCon([
        'danado.xml' => 'esto no es xml',
        'e4b88846-5546-4f75-ad70-2f61d993c2c6.xml' => cfdiXml('E4B88846-5546-4F75-AD70-2F61D993C2C6'),
        'sin-timbre.xml' => '<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" Total="100.00"/>',
    ]);

    expect(array_keys(app(MetadataCfdi::class)->deUuids(['E4B88846-5546-4F75-AD70-2F61D993C2C6'])))->toBe(['E4B88846-5546-4F75-AD70-2F61D993C2C6'])
        ->and($paquete->fresh()->estado)->toBe(EstadoPaquete::Extraido)
        ->and($paquete->fresh()->ultimo_problema)->toBe('No se pudo leer la Metadata de 2 XML del Paquete: danado.xml, sin-timbre.xml.');
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

/**
 * Un CFDI timbrado mínimo, con los nodos de los que sale la Metadata de CFDI.
 */
function cfdiXml(string $uuid, string $version = '4.0', string $total = '2448.00', string $rfcEmisor = 'CFC110121742'): string
{
    $namespace = $version === '4.0' ? 'http://www.sat.gob.mx/cfd/4' : 'http://www.sat.gob.mx/cfd/3';

    return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <cfdi:Comprobante xmlns:cfdi="{$namespace}" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Version="{$version}" Fecha="2026-11-10T18:38:29" Total="{$total}" TipoDeComprobante="I">
          <cfdi:CfdiRelacionados TipoRelacion="04"><cfdi:CfdiRelacionado UUID="11111111-2222-3333-4444-555555555555"/></cfdi:CfdiRelacionados>
          <cfdi:Emisor Rfc="{$rfcEmisor}" Nombre="COMERCIALIZADORA FARMACÉUTICA DE CHIAPAS" RegimenFiscal="601"/>
          <cfdi:Receptor Rfc="PME380607P35" Nombre="PETRÓLEOS MEXICANOS"/>
          <cfdi:Complemento>
            <tfd:TimbreFiscalDigital Version="1.1" UUID="{$uuid}" FechaTimbrado="2026-11-10T18:39:02" RfcProvCertif="MAS980812UK1"/>
          </cfdi:Complemento>
        </cfdi:Comprobante>
        XML;
}

test('stores the metadata of the cfdi xml of a paquete', function () {
    descargarPaqueteCon(['e4b88846-5546-4f75-ad70-2f61d993c2c6.xml' => cfdiXml('e4b88846-5546-4f75-ad70-2f61d993c2c6')]);

    $metadata = app(MetadataCfdi::class)->deUuids(['E4B88846-5546-4F75-AD70-2F61D993C2C6']);

    expect($metadata)->toHaveKey('E4B88846-5546-4F75-AD70-2F61D993C2C6');

    $cfdi = $metadata['E4B88846-5546-4F75-AD70-2F61D993C2C6'];
    expect($cfdi->rfcEmisor)->toBe('CFC110121742')
        ->and($cfdi->nombreEmisor)->toBe('COMERCIALIZADORA FARMACÉUTICA DE CHIAPAS')
        ->and($cfdi->rfcReceptor)->toBe('PME380607P35')
        ->and($cfdi->nombreReceptor)->toBe('PETRÓLEOS MEXICANOS')
        ->and($cfdi->rfcPac)->toBe('MAS980812UK1')
        ->and($cfdi->fechaEmision->format('Y-m-d H:i:s'))->toBe('2026-11-10 18:38:29')
        ->and($cfdi->fechaCertificacionSat?->format('Y-m-d H:i:s'))->toBe('2026-11-10 18:39:02')
        ->and($cfdi->monto)->toBe('2448.00')
        ->and($cfdi->efecto)->toBe('I')
        ->and($cfdi->estatus)->toBeNull()
        ->and($cfdi->fechaCancelacion)->toBeNull();
});

test('a later xml keeps the estatus and cancellation date that came in the metadata', function () {
    descargarPaqueteCon(['ABC_01.txt' => txtDeMetadata(
        'E4B88846-5546-4F75-AD70-2F61D993C2C6~CFC110121742~COMERCIALIZADORA~PME380607P35~PEMEX~MAS980812UK1~2026-11-10 18:38:29~2026-11-10 18:39:02~1250~I~0~2026-11-15 09:00:00',
    )], 'ABC_01');
    descargarPaqueteCon(['e4b88846-5546-4f75-ad70-2f61d993c2c6.xml' => cfdiXml('E4B88846-5546-4F75-AD70-2F61D993C2C6', total: '2448.00')], 'XML_01');

    $cfdi = app(MetadataCfdi::class)->deUuids(['E4B88846-5546-4F75-AD70-2F61D993C2C6'])['E4B88846-5546-4F75-AD70-2F61D993C2C6'];

    expect($cfdi->monto)->toBe('2448.00')
        ->and($cfdi->nombreEmisor)->toBe('COMERCIALIZADORA FARMACÉUTICA DE CHIAPAS')
        ->and($cfdi->estatus)->toBe('0')
        ->and($cfdi->fechaCancelacion?->format('Y-m-d H:i:s'))->toBe('2026-11-15 09:00:00');
});

test('stores a cfdi 3.3 too', function () {
    descargarPaqueteCon(['aaaa.xml' => cfdiXml('AE4B0CA8-8116-4699-8E20-77B6E9C507A2', version: '3.3', total: '100.50', rfcEmisor: 'ESS040127PZ1')]);

    $cfdi = app(MetadataCfdi::class)->deUuids(['AE4B0CA8-8116-4699-8E20-77B6E9C507A2'])['AE4B0CA8-8116-4699-8E20-77B6E9C507A2'];

    expect($cfdi->rfcEmisor)->toBe('ESS040127PZ1')
        ->and($cfdi->monto)->toBe('100.50');
});
