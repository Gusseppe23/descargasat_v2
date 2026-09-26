<?php

use App\Models\User;
use DescargaSat\Fiel\Actions\RegistrarFiel;
use DescargaSat\Fiel\Contracts\Fieles;
use DescargaSat\Fiel\Models\Fiel;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CertificadoDePrueba;

test('there is no active fiel when none has been activated', function () {
    Fiel::factory()->create();

    expect(app(Fieles::class)->activa())->toBeNull();
});

test('returns the public data of the active fiel', function () {
    $fiel = Fiel::factory()->activa()->create([
        'numero_certificado' => '30001000000500003416',
        'vigente_desde' => '2025-01-15 10:00:00',
        'vigente_hasta' => '2029-01-15 10:00:00',
    ]);

    $activa = app(Fieles::class)->activa();

    expect($activa)->not->toBeNull()
        ->and($activa->id)->toBe($fiel->id)
        ->and($activa->rfc)->toBe('EKU9003173C9')
        ->and($activa->razonSocial)->toBe('ESCUELA KEMPER URGATE')
        ->and($activa->numeroCertificado)->toBe('30001000000500003416')
        ->and($activa->vigenteDesde->toDateTimeString())->toBe('2025-01-15 10:00:00')
        ->and($activa->vigenteHasta->toDateTimeString())->toBe('2029-01-15 10:00:00')
        ->and($activa->activa)->toBeTrue();
});

test('returns a working decrypted credential for a stored fiel', function () {
    Storage::fake('local');
    $certificado = CertificadoDePrueba::fiel();
    $fiel = app(RegistrarFiel::class)($certificado->cer, $certificado->key, $certificado->contrasena, User::factory()->create());

    $credencial = app(Fieles::class)->credencial($fiel->id);
    $firma = $credencial->sign('cadena de prueba');

    expect($credencial->rfc())->toBe('EKU9003173C9')
        ->and($credencial->verify('cadena de prueba', $firma))->toBeTrue();
});
