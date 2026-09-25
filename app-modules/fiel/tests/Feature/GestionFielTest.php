<?php

use App\Models\User;
use DescargaSat\Fiel\Models\Fiel;
use DescargaSat\Fiel\Tests\Support\CertificadoDePrueba;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get('/fiel')->assertRedirect(route('login'));
});

test('uploading a valid fiel lists it with its data as inactive', function () {
    Storage::fake('local');
    $usuario = User::factory()->create(['name' => 'Ana López']);
    $fiel = CertificadoDePrueba::fiel();
    [$cer, $key] = $fiel->comoArchivos();

    $this->actingAs($usuario);

    Livewire::test('fiel::gestion')
        ->set('cer', $cer)
        ->set('key', $key)
        ->set('contrasena', $fiel->contrasena)
        ->call('subir')
        ->assertHasNoErrors()
        ->assertSee([
            'EKU9003173C9',
            'ESCUELA KEMPER URGATE',
            '30001000000500003416',
            $fiel->vigenteDesde->format('d/m/Y'),
            $fiel->vigenteHasta->format('d/m/Y'),
            'Inactiva',
            'Ana López',
        ]);
});

test('rejects a key that the password cannot open and stores nothing', function () {
    Storage::fake('local');
    [$cer, $key] = CertificadoDePrueba::fiel(contrasena: 'correcta')->comoArchivos();

    $this->actingAs(User::factory()->create());

    Livewire::test('fiel::gestion')
        ->set('cer', $cer)
        ->set('key', $key)
        ->set('contrasena', 'incorrecta')
        ->call('subir')
        ->assertHasErrors(['contrasena' => 'La contraseña no abre la llave privada.'])
        ->assertSee('Todavía no hay ninguna Fiel guardada.');

    Storage::disk('local')->assertDirectoryEmpty('fiel');
});

test('rejects a key that does not belong to the certificate', function () {
    Storage::fake('local');
    [$cer] = CertificadoDePrueba::fiel()->comoArchivos();
    $otra = CertificadoDePrueba::fiel(numeroCertificado: '30001000000500009999');
    [, $keyDeOtra] = $otra->comoArchivos();

    $this->actingAs(User::factory()->create());

    Livewire::test('fiel::gestion')
        ->set('cer', $cer)
        ->set('key', $keyDeOtra)
        ->set('contrasena', $otra->contrasena)
        ->call('subir')
        ->assertHasErrors(['key' => 'La llave privada no corresponde al certificado.'])
        ->assertSee('Todavía no hay ninguna Fiel guardada.');
});

test('rejects a csd because only a fiel can download from the sat', function () {
    Storage::fake('local');
    $csd = CertificadoDePrueba::csd();
    [$cer, $key] = $csd->comoArchivos();

    $this->actingAs(User::factory()->create());

    Livewire::test('fiel::gestion')
        ->set('cer', $cer)
        ->set('key', $key)
        ->set('contrasena', $csd->contrasena)
        ->call('subir')
        ->assertHasErrors(['cer' => 'El certificado es un CSD (sello digital), no una FIEL.'])
        ->assertSee('Todavía no hay ninguna Fiel guardada.');
});

test('rejects an expired fiel', function () {
    Storage::fake('local');
    $fiel = CertificadoDePrueba::fiel();
    [$cer, $key] = $fiel->comoArchivos();

    $this->actingAs(User::factory()->create());

    $pantalla = Livewire::test('fiel::gestion')
        ->set('cer', $cer)
        ->set('key', $key)
        ->set('contrasena', $fiel->contrasena);

    $this->travel(5)->years();

    $pantalla->call('subir')
        ->assertHasErrors(['cer' => 'La FIEL venció el '.$fiel->vigenteHasta->format('d/m/Y').' y ya no está vigente.'])
        ->assertSee('Todavía no hay ninguna Fiel guardada.');
});

test('rejects a fiel from a different rfc than the stored ones', function () {
    Storage::fake('local');
    Fiel::factory()->deRfc('XAXX010101000')->create();
    $fiel = CertificadoDePrueba::fiel(rfc: 'EKU9003173C9', numeroCertificado: '30001000000700000001');
    [$cer, $key] = $fiel->comoArchivos();

    $this->actingAs(User::factory()->create());

    Livewire::test('fiel::gestion')
        ->set('cer', $cer)
        ->set('key', $key)
        ->set('contrasena', $fiel->contrasena)
        ->call('subir')
        ->assertHasErrors(['cer' => 'Esta FIEL es del RFC EKU9003173C9, pero la aplicación trabaja con el RFC XAXX010101000.'])
        ->assertDontSee('30001000000700000001');
});

test('rejects uploading the same certificate twice', function () {
    Storage::fake('local');
    $fiel = CertificadoDePrueba::fiel();

    $this->actingAs(User::factory()->create());

    $pantalla = Livewire::test('fiel::gestion');

    foreach ([1, 2] as $intento) {
        [$cer, $key] = $fiel->comoArchivos();

        $pantalla->set('cer', $cer)
            ->set('key', $key)
            ->set('contrasena', $fiel->contrasena)
            ->call('subir');
    }

    $pantalla->assertHasErrors(['cer' => 'Esta Fiel ya está guardada.']);
});

test('stores the certificate, the key and the password encrypted', function () {
    Storage::fake('local');
    $fiel = CertificadoDePrueba::fiel(contrasena: 'MiContrasenaSecreta1');
    [$cer, $key] = $fiel->comoArchivos();

    $this->actingAs(User::factory()->create());

    Livewire::test('fiel::gestion')
        ->set('cer', $cer)
        ->set('key', $key)
        ->set('contrasena', $fiel->contrasena)
        ->call('subir')
        ->assertHasNoErrors();

    $archivos = Storage::disk('local')->files('fiel');
    $contenidos = implode('', array_map(fn (string $archivo) => Storage::disk('local')->get($archivo), $archivos));

    expect($archivos)->toHaveCount(2)
        ->and($contenidos)->not->toContain($fiel->cer)
        ->and($contenidos)->not->toContain($fiel->key)
        ->and(DB::table('fieles')->value('contrasena'))->not->toContain('MiContrasenaSecreta1');
});

test('rejects a cer file that is not a certificate', function () {
    Storage::fake('local');
    $fiel = CertificadoDePrueba::fiel();
    [, $key] = $fiel->comoArchivos();

    $this->actingAs(User::factory()->create());

    Livewire::test('fiel::gestion')
        ->set('cer', UploadedFile::fake()->createWithContent('certificado.cer', 'esto no es un certificado'))
        ->set('key', $key)
        ->set('contrasena', $fiel->contrasena)
        ->call('subir')
        ->assertHasErrors(['cer' => 'El archivo .cer no es un certificado válido.']);
});

test('requires the certificate, the key and the password', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('fiel::gestion')
        ->call('subir')
        ->assertHasErrors(['cer' => 'required', 'key' => 'required', 'contrasena' => 'required']);
});
