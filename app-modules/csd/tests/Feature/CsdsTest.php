<?php

use App\Models\User;
use DescargaSat\Csd\Actions\RegistrarCsd;
use DescargaSat\Csd\Contracts\Csds;
use DescargaSat\Csd\Models\Csd;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CertificadoDePrueba;

test('there is no active csd when none has been activated', function () {
    Csd::factory()->create();

    expect(app(Csds::class)->activo())->toBeNull();
});

test('returns the public data of the active csd', function () {
    $csd = Csd::factory()->activo()->create([
        'numero_certificado' => '30001000000500003417',
        'vigente_desde' => '2025-01-15 10:00:00',
        'vigente_hasta' => '2029-01-15 10:00:00',
    ]);

    $activo = app(Csds::class)->activo();

    expect($activo)->not->toBeNull()
        ->and($activo->id)->toBe($csd->id)
        ->and($activo->rfc)->toBe('EKU9003173C9')
        ->and($activo->razonSocial)->toBe('ESCUELA KEMPER URGATE')
        ->and($activo->numeroCertificado)->toBe('30001000000500003417')
        ->and($activo->vigenteDesde->toDateTimeString())->toBe('2025-01-15 10:00:00')
        ->and($activo->vigenteHasta->toDateTimeString())->toBe('2029-01-15 10:00:00')
        ->and($activo->activo)->toBeTrue();
});

test('returns a working decrypted credential for a stored csd', function () {
    Storage::fake('local');
    $certificado = CertificadoDePrueba::csd();
    $csd = app(RegistrarCsd::class)($certificado->cer, $certificado->key, $certificado->contrasena, User::factory()->create());

    $credencial = app(Csds::class)->credencial($csd->id);
    $firma = $credencial->sign('cadena de prueba');

    expect($credencial->rfc())->toBe('EKU9003173C9')
        ->and($credencial->certificate()->satType()->isCsd())->toBeTrue()
        ->and($credencial->verify('cadena de prueba', $firma))->toBeTrue();
});
