<?php

use App\Models\User;
use DescargaSat\Fiel\Contracts\Fieles;
use DescargaSat\Fiel\Contracts\UsoDeFiel;
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

test('activating a fiel deactivates the previously active one', function () {
    [$primera, $segunda] = Fiel::factory()->count(2)->create();

    $this->actingAs(User::factory()->create());

    Livewire::test('fiel::gestion')
        ->call('activar', $primera->id)
        ->call('activar', $segunda->id)
        ->assertHasNoErrors();

    $fieles = app(Fieles::class);

    expect($fieles->activa()?->id)->toBe($segunda->id)
        ->and($fieles->porId($primera->id)?->activa)->toBeFalse();
});

test('deactivating the active fiel leaves the application without one', function () {
    $fiel = Fiel::factory()->activa()->create();

    $this->actingAs(User::factory()->create());

    Livewire::test('fiel::gestion')
        ->call('desactivar', $fiel->id)
        ->assertHasNoErrors();

    expect(app(Fieles::class)->activa())->toBeNull();
});

test('rejects activating an expired fiel', function () {
    $fiel = Fiel::factory()->vencida()->create();

    $this->actingAs(User::factory()->create());

    Livewire::test('fiel::gestion')
        ->call('activar', $fiel->id)
        ->assertHasErrors(['fiel' => 'No se puede activar una FIEL vencida.']);

    expect(app(Fieles::class)->activa())->toBeNull();
});

test('deleting an inactive fiel removes it and its encrypted files', function () {
    Storage::fake('local');
    $fiel = Fiel::factory()->create();
    Storage::disk('local')->put($fiel->ruta_cer, 'cer cifrado');
    Storage::disk('local')->put($fiel->ruta_key, 'key cifrada');

    $this->actingAs(User::factory()->create());

    Livewire::test('fiel::gestion')
        ->call('borrar', $fiel->id)
        ->assertHasNoErrors()
        ->assertSee('Todavía no hay ninguna Fiel guardada.');

    expect(app(Fieles::class)->porId($fiel->id))->toBeNull();
    Storage::disk('local')->assertMissing([$fiel->ruta_cer, $fiel->ruta_key]);
});

test('rejects deleting the active fiel', function () {
    $fiel = Fiel::factory()->activa()->create();

    $this->actingAs(User::factory()->create());

    Livewire::test('fiel::gestion')
        ->call('borrar', $fiel->id)
        ->assertHasErrors(['fiel' => 'No se puede borrar la Fiel activa. Desactívala primero.']);

    expect(app(Fieles::class)->porId($fiel->id))->not->toBeNull();
});

test('rejects deleting a fiel that has solicitudes in progress', function () {
    $fiel = Fiel::factory()->create();
    app()->instance(UsoDeFiel::class, new class implements UsoDeFiel
    {
        public function tieneSolicitudesEnCurso(int $fielId): bool
        {
            return true;
        }
    });

    $this->actingAs(User::factory()->create());

    Livewire::test('fiel::gestion')
        ->call('borrar', $fiel->id)
        ->assertHasErrors(['fiel' => 'Esta Fiel tiene Solicitudes en curso y no se puede borrar todavía.']);

    expect(app(Fieles::class)->porId($fiel->id))->not->toBeNull();
});

test('warns when the active fiel expires within 30 days', function () {
    $this->freezeTime();
    Fiel::factory()->activa()->create(['vigente_hasta' => now()->addDays(30)]);

    $this->actingAs(User::factory()->create());

    Livewire::test('fiel::gestion')
        ->assertSee('La Fiel activa vence en 30 días, el '.now()->addDays(30)->format('d/m/Y').'.');
});

test('does not warn when the active fiel expires in more than 30 days', function () {
    $this->freezeTime();
    Fiel::factory()->activa()->create(['vigente_hasta' => now()->addDays(31)]);

    $this->actingAs(User::factory()->create());

    Livewire::test('fiel::gestion')
        ->assertDontSee('La Fiel activa vence en');
});

test('warns when the active fiel has already expired', function () {
    $this->freezeTime();
    Fiel::factory()->activa()->create(['vigente_hasta' => now()->subDays(3)]);

    $this->actingAs(User::factory()->create());

    Livewire::test('fiel::gestion')
        ->assertSee('La Fiel activa venció el '.now()->subDays(3)->format('d/m/Y').'.')
        ->assertDontSee('La Fiel activa vence en');
});

test('shows who activated the fiel and when', function () {
    $this->freezeTime();
    $fiel = Fiel::factory()->create();

    $this->actingAs(User::factory()->create(['name' => 'Beto Ruiz']));

    Livewire::test('fiel::gestion')
        ->call('activar', $fiel->id)
        ->assertSee('Activada por Beto Ruiz el '.now()->format('d/m/Y H:i'));
});

test('shows who deactivated the fiel and when', function () {
    $this->freezeTime();
    $fiel = Fiel::factory()->activa()->create();

    $this->actingAs(User::factory()->create(['name' => 'Carla Díaz']));

    Livewire::test('fiel::gestion')
        ->call('desactivar', $fiel->id)
        ->assertSee('Desactivada por Carla Díaz el '.now()->format('d/m/Y H:i'));
});
