<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use DescargaSat\SatAutenticacion\Contracts\Servicio;
use DescargaSat\Solicitudes\Actions\PresentarSolicitud;
use DescargaSat\Solicitudes\Contracts\EstadoSolicitud;
use DescargaSat\Solicitudes\Contracts\NoSePudoPresentarSolicitud;
use DescargaSat\Solicitudes\Contracts\Solicitudes;
use DescargaSat\Solicitudes\Enums\EstadoComprobante;
use DescargaSat\Solicitudes\Enums\TipoDescarga;
use DescargaSat\Solicitudes\Enums\TipoSolicitud;
use DescargaSat\Solicitudes\Models\Solicitud;
use DescargaSat\Solicitudes\Support\ParametrosSolicitud;
use PhpCfdi\SatWsDescargaMasiva\WebClient\WebClientInterface;
use Tests\Support\FielesFalsas;
use Tests\Support\WebClientFalso;

/**
 * Sustituye el cliente web del SAT: responde la autenticación y la Solicitud por folio aceptada.
 */
function satAceptaFolio(): WebClientFalso
{
    $webClient = (new WebClientFalso)->responder('autenticacion.xml')->responder('solicitud-folio-aceptada.xml');
    app()->instance(WebClientInterface::class, $webClient);

    return $webClient;
}

test('presents an automatic solicitud by uuid with the active fiel', function () {
    FielesFalsas::usar();
    $sat = satAceptaFolio();

    $id = app(Solicitudes::class)->presentarPorUuid('e4b88846-5546-4f75-ad70-2f61d993c2c6');

    expect($sat->peticiones[1]->getBody())->toContain('SolicitaDescargaFolio')
        ->and($sat->peticiones[1]->getBody())->toContain('Folio="e4b88846-5546-4f75-ad70-2f61d993c2c6"');

    $solicitud = Solicitud::findOrFail($id);
    expect($solicitud->automatica)->toBeTrue()
        ->and($solicitud->presentada_por_id)->toBeNull()
        ->and($solicitud->uuid)->toBe('E4B88846-5546-4F75-AD70-2F61D993C2C6')
        ->and($solicitud->fiel_id)->toBe(1)
        ->and($solicitud->estado)->toBe(EstadoSolicitud::Aceptada)
        ->and($solicitud->id_solicitud_sat)->toBe('acf13d76-1d37-453c-9950-9715ec254cf7');
});

test('does not present a second solicitud for a uuid that already has one', function () {
    FielesFalsas::usar();
    $sat = satAceptaFolio();
    $solicitudes = app(Solicitudes::class);

    $primera = $solicitudes->presentarPorUuid('E4B88846-5546-4F75-AD70-2F61D993C2C6');
    $segunda = $solicitudes->presentarPorUuid('e4b88846-5546-4f75-ad70-2f61d993c2c6');

    expect($segunda)->toBe($primera)
        ->and($sat->peticiones)->toHaveCount(2)
        ->and(Solicitud::count())->toBe(1);
});

test('presents it again when the previous solicitud by uuid was rechazada', function () {
    FielesFalsas::usar();
    $rechazada = Solicitud::factory()->rechazada()->create([
        'presentada_por_id' => null,
        'automatica' => true,
        'uuid' => 'E4B88846-5546-4F75-AD70-2F61D993C2C6',
    ]);
    $sat = satAceptaFolio();

    $id = app(Solicitudes::class)->presentarPorUuid('E4B88846-5546-4F75-AD70-2F61D993C2C6');

    expect($id)->not->toBe($rechazada->id)
        ->and($sat->peticiones)->toHaveCount(2);
});

test('without an active fiel it does not contact the sat', function () {
    FielesFalsas::usar(hayActiva: false);
    $sat = satAceptaFolio();

    expect(fn () => app(Solicitudes::class)->presentarPorUuid('E4B88846-5546-4F75-AD70-2F61D993C2C6'))
        ->toThrow(NoSePudoPresentarSolicitud::class, 'No hay una Fiel activa');

    expect($sat->peticiones)->toBeEmpty()
        ->and(Solicitud::count())->toBe(0);
});

test('when the sat does not answer it throws the contract exception and saves nothing', function () {
    FielesFalsas::usar();
    app()->instance(WebClientInterface::class, (new WebClientFalso)->sinConexion());

    expect(fn () => app(Solicitudes::class)->presentarPorUuid('E4B88846-5546-4F75-AD70-2F61D993C2C6'))
        ->toThrow(NoSePudoPresentarSolicitud::class, 'No se pudo contactar al SAT');

    expect(Solicitud::count())->toBe(0);
});

test('the list and the detail show an automatic solicitud by uuid as automatica, with its uuid instead of the period', function () {
    $solicitud = Solicitud::factory()->create([
        'presentada_por_id' => null,
        'automatica' => true,
        'uuid' => 'E4B88846-5546-4F75-AD70-2F61D993C2C6',
        'fecha_inicio' => '2026-11-18',
        'fecha_fin' => '2026-11-18',
        'tipo_descarga' => TipoDescarga::Recibidos,
    ]);
    $this->actingAs(User::factory()->create());

    $this->get(route('solicitudes.index'))
        ->assertOk()
        ->assertSee(['Automática', 'E4B88846-5546-4F75-AD70-2F61D993C2C6'])
        ->assertDontSee('18/11/2026 – 18/11/2026');

    $this->get(route('solicitudes.show', $solicitud))
        ->assertOk()
        ->assertSeeInOrder(['UUID', 'E4B88846-5546-4F75-AD70-2F61D993C2C6'])
        ->assertSeeInOrder(['Presentada por', 'Automática'])
        ->assertDontSee('18/11/2026 – 18/11/2026');
});

test('the detail shows who presented a solicitud from the form', function () {
    $usuario = User::factory()->create(['name' => 'Ana Contadora']);
    $solicitud = Solicitud::factory()->create(['presentada_por_id' => $usuario->id]);
    $this->actingAs($usuario);

    $this->get(route('solicitudes.show', $solicitud))
        ->assertOk()
        ->assertSeeInOrder(['Periodo', '01/03/2026 – 31/03/2026'])
        ->assertSeeInOrder(['Presentada por', 'Ana Contadora'])
        ->assertDontSee('Automática');
});

test('a solicitud by uuid is not a duplicate of a solicitud by period', function () {
    $this->travelTo('2026-11-18 12:00:00');
    Solicitud::factory()->create([
        'presentada_por_id' => null,
        'automatica' => true,
        'uuid' => 'E4B88846-5546-4F75-AD70-2F61D993C2C6',
        'fecha_inicio' => '2026-11-18',
        'fecha_fin' => '2026-11-18',
        'tipo_descarga' => TipoDescarga::Recibidos,
        'tipo_solicitud' => TipoSolicitud::Xml,
        'estado_comprobante' => EstadoComprobante::Vigentes,
    ]);
    $porPeriodo = new ParametrosSolicitud(
        Servicio::Cfdi, CarbonImmutable::parse('2026-11-18'), CarbonImmutable::parse('2026-11-18'),
        TipoDescarga::Recibidos, TipoSolicitud::Xml, null, EstadoComprobante::Vigentes,
    );

    expect(app(PresentarSolicitud::class)->duplicadaDe($porPeriodo))->toBeNull();
});
