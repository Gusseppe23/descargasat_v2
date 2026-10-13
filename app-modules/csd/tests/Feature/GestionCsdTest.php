<?php

use App\Models\User;
use DescargaSat\Csd\Models\Csd;
use DescargaSat\Fiel\Models\Fiel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\CertificadoDePrueba;

test('guests are redirected to the login page', function () {
    $this->get('/csd')->assertRedirect(route('login'));
});

test('uploading a valid csd lists it with its data as inactive', function () {
    Storage::fake('local');
    $usuario = User::factory()->create(['name' => 'Ana López']);
    $csd = CertificadoDePrueba::csd();
    [$cer, $key] = $csd->comoArchivos();

    $this->actingAs($usuario);

    Livewire::test('csd::gestion')
        ->set('cer', $cer)
        ->set('key', $key)
        ->set('contrasena', $csd->contrasena)
        ->call('subir')
        ->assertHasNoErrors()
        ->assertSee([
            'EKU9003173C9',
            'ESCUELA KEMPER URGATE',
            '30001000000500003417',
            $csd->vigenteDesde->format('d/m/Y'),
            $csd->vigenteHasta->format('d/m/Y'),
            'Inactivo',
            'Ana López',
        ]);
});

test('rejects a fiel because only a csd can answer cancellations', function () {
    Storage::fake('local');
    $fiel = CertificadoDePrueba::fiel();
    [$cer, $key] = $fiel->comoArchivos();

    $this->actingAs(User::factory()->create());

    Livewire::test('csd::gestion')
        ->set('cer', $cer)
        ->set('key', $key)
        ->set('contrasena', $fiel->contrasena)
        ->call('subir')
        ->assertHasErrors(['cer' => 'El certificado es una FIEL, no un CSD (sello digital).'])
        ->assertSee('Todavía no hay ningún CSD guardado.');
});

test('rejects a cer file that is not a certificate', function () {
    Storage::fake('local');
    $csd = CertificadoDePrueba::csd();
    [, $key] = $csd->comoArchivos();

    $this->actingAs(User::factory()->create());

    Livewire::test('csd::gestion')
        ->set('cer', UploadedFile::fake()->createWithContent('certificado.cer', 'esto no es un certificado'))
        ->set('key', $key)
        ->set('contrasena', $csd->contrasena)
        ->call('subir')
        ->assertHasErrors(['cer' => 'El archivo .cer no es un certificado válido.']);
});

test('rejects an expired csd', function () {
    Storage::fake('local');
    $csd = CertificadoDePrueba::csd();
    [$cer, $key] = $csd->comoArchivos();

    $this->actingAs(User::factory()->create());

    $pantalla = Livewire::test('csd::gestion')
        ->set('cer', $cer)
        ->set('key', $key)
        ->set('contrasena', $csd->contrasena);

    $this->travel(5)->years();

    $pantalla->call('subir')
        ->assertHasErrors(['cer' => 'El CSD venció el '.$csd->vigenteHasta->format('d/m/Y').' y ya no está vigente.'])
        ->assertSee('Todavía no hay ningún CSD guardado.');
});

test('rejects a csd that is not valid yet', function () {
    Storage::fake('local');
    $csd = CertificadoDePrueba::csd();
    [$cer, $key] = $csd->comoArchivos();

    $this->actingAs(User::factory()->create());

    $pantalla = Livewire::test('csd::gestion')
        ->set('cer', $cer)
        ->set('key', $key)
        ->set('contrasena', $csd->contrasena);

    $this->travel(-1)->days();

    $pantalla->call('subir')
        ->assertHasErrors(['cer' => 'El CSD entra en vigor el '.$csd->vigenteDesde->format('d/m/Y').'.'])
        ->assertSee('Todavía no hay ningún CSD guardado.');
});

test('rejects uploading the same certificate twice', function () {
    Storage::fake('local');
    $csd = CertificadoDePrueba::csd();

    $this->actingAs(User::factory()->create());

    $pantalla = Livewire::test('csd::gestion');

    foreach ([1, 2] as $intento) {
        [$cer, $key] = $csd->comoArchivos();

        $pantalla->set('cer', $cer)
            ->set('key', $key)
            ->set('contrasena', $csd->contrasena)
            ->call('subir');
    }

    $pantalla->assertHasErrors(['cer' => 'Este CSD ya está guardado.']);
});

test('rejects a key that the password cannot open and stores nothing', function () {
    Storage::fake('local');
    [$cer, $key] = CertificadoDePrueba::csd(contrasena: 'correcta')->comoArchivos();

    $this->actingAs(User::factory()->create());

    Livewire::test('csd::gestion')
        ->set('cer', $cer)
        ->set('key', $key)
        ->set('contrasena', 'incorrecta')
        ->call('subir')
        ->assertHasErrors(['contrasena' => 'La contraseña no abre la llave privada.'])
        ->assertSee('Todavía no hay ningún CSD guardado.');

    Storage::disk('local')->assertDirectoryEmpty('csd');
});

test('rejects a key that does not belong to the certificate', function () {
    Storage::fake('local');
    [$cer] = CertificadoDePrueba::csd()->comoArchivos();
    $otro = CertificadoDePrueba::csd(numeroCertificado: '30001000000500009999');
    [, $keyDeOtro] = $otro->comoArchivos();

    $this->actingAs(User::factory()->create());

    Livewire::test('csd::gestion')
        ->set('cer', $cer)
        ->set('key', $keyDeOtro)
        ->set('contrasena', $otro->contrasena)
        ->call('subir')
        ->assertHasErrors(['key' => 'La llave privada no corresponde al certificado.'])
        ->assertSee('Todavía no hay ningún CSD guardado.');
});

test('rejects a csd from a different rfc than the stored csds', function () {
    Storage::fake('local');
    Csd::factory()->deRfc('XAXX010101000')->create();
    $csd = CertificadoDePrueba::csd(rfc: 'EKU9003173C9', numeroCertificado: '30001000000700000001');
    [$cer, $key] = $csd->comoArchivos();

    $this->actingAs(User::factory()->create());

    Livewire::test('csd::gestion')
        ->set('cer', $cer)
        ->set('key', $key)
        ->set('contrasena', $csd->contrasena)
        ->call('subir')
        ->assertHasErrors(['cer' => 'Este CSD es del RFC EKU9003173C9, pero la aplicación trabaja con el RFC XAXX010101000.'])
        ->assertDontSee('30001000000700000001');
});

test('rejects a csd from a different rfc than the active fiel', function () {
    Storage::fake('local');
    Fiel::factory()->activa()->deRfc('XAXX010101000')->create();
    $csd = CertificadoDePrueba::csd(rfc: 'EKU9003173C9');
    [$cer, $key] = $csd->comoArchivos();

    $this->actingAs(User::factory()->create());

    Livewire::test('csd::gestion')
        ->set('cer', $cer)
        ->set('key', $key)
        ->set('contrasena', $csd->contrasena)
        ->call('subir')
        ->assertHasErrors(['cer' => 'Este CSD es del RFC EKU9003173C9, pero la Fiel activa es del RFC XAXX010101000.'])
        ->assertSee('Todavía no hay ningún CSD guardado.');
});

test('stores the certificate, the key and the password encrypted', function () {
    Storage::fake('local');
    $csd = CertificadoDePrueba::csd(contrasena: 'MiContrasenaSecreta1');
    [$cer, $key] = $csd->comoArchivos();

    $this->actingAs(User::factory()->create());

    Livewire::test('csd::gestion')
        ->set('cer', $cer)
        ->set('key', $key)
        ->set('contrasena', $csd->contrasena)
        ->call('subir')
        ->assertHasNoErrors()
        ->assertDontSee('MiContrasenaSecreta1');

    $archivos = Storage::disk('local')->files('csd');
    $contenidos = implode('', array_map(fn (string $archivo) => Storage::disk('local')->get($archivo), $archivos));

    expect($archivos)->toHaveCount(2)
        ->and($contenidos)->not->toContain($csd->cer)
        ->and($contenidos)->not->toContain($csd->key)
        ->and(DB::table('csds')->value('contrasena'))->not->toContain('MiContrasenaSecreta1');
});

test('requires the certificate, the key and the password', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('csd::gestion')
        ->call('subir')
        ->assertHasErrors(['cer' => 'required', 'key' => 'required', 'contrasena' => 'required']);
});

test('activating a csd deactivates the previously active one', function () {
    [$primero, $segundo] = Csd::factory()->count(2)->create();

    $this->actingAs(User::factory()->create());

    Livewire::test('csd::gestion')
        ->call('activar', $primero->id)
        ->call('activar', $segundo->id)
        ->assertHasNoErrors();

    expect($segundo->fresh()->activo)->toBeTrue()
        ->and($primero->fresh()->activo)->toBeFalse();
});

test('deactivating the active csd leaves the application without one', function () {
    $csd = Csd::factory()->activo()->create();

    $this->actingAs(User::factory()->create());

    Livewire::test('csd::gestion')
        ->call('desactivar', $csd->id)
        ->assertHasNoErrors();

    expect(Csd::where('activo', true)->exists())->toBeFalse();
});

test('rejects activating an expired csd', function () {
    $csd = Csd::factory()->vencido()->create();

    $this->actingAs(User::factory()->create());

    Livewire::test('csd::gestion')
        ->call('activar', $csd->id)
        ->assertHasErrors(['csd' => 'No se puede activar un CSD vencido.']);

    expect($csd->fresh()->activo)->toBeFalse();
});

test('deleting an inactive csd removes it and its encrypted files', function () {
    Storage::fake('local');
    $csd = Csd::factory()->create();
    Storage::disk('local')->put($csd->ruta_cer, 'cer cifrado');
    Storage::disk('local')->put($csd->ruta_key, 'key cifrada');

    $this->actingAs(User::factory()->create());

    Livewire::test('csd::gestion')
        ->call('borrar', $csd->id)
        ->assertHasNoErrors()
        ->assertSee('Todavía no hay ningún CSD guardado.');

    expect(Csd::find($csd->id))->toBeNull();
    Storage::disk('local')->assertMissing([$csd->ruta_cer, $csd->ruta_key]);
});

test('rejects deleting the active csd', function () {
    $csd = Csd::factory()->activo()->create();

    $this->actingAs(User::factory()->create());

    Livewire::test('csd::gestion')
        ->call('borrar', $csd->id)
        ->assertHasErrors(['csd' => 'No se puede borrar el CSD activo. Desactívalo primero.']);

    expect(Csd::find($csd->id))->not->toBeNull();
});

test('shows who activated the csd and when', function () {
    $this->freezeTime();
    $csd = Csd::factory()->create();

    $this->actingAs(User::factory()->create(['name' => 'Beto Ruiz']));

    Livewire::test('csd::gestion')
        ->call('activar', $csd->id)
        ->assertSee('Activado por Beto Ruiz el '.now()->format('d/m/Y H:i'));
});

test('shows who deactivated the csd and when', function () {
    $this->freezeTime();
    $csd = Csd::factory()->activo()->create();

    $this->actingAs(User::factory()->create(['name' => 'Carla Díaz']));

    Livewire::test('csd::gestion')
        ->call('desactivar', $csd->id)
        ->assertSee('Desactivado por Carla Díaz el '.now()->format('d/m/Y H:i'));
});

test('the sidebar links to the csd page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('csd.index'))
        ->assertOk()
        ->assertSee('href="'.route('csd.index').'"', escape: false);
});
