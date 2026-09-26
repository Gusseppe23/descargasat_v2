<?php

use App\Models\User;
use DescargaSat\Paquetes\Models\Paquete;
use Illuminate\Support\Facades\Storage;

test('guests are redirected to the login page', function () {
    $paquete = Paquete::factory()->extraido()->create();

    $this->get(route('paquetes.zip', $paquete))->assertRedirect(route('login'));
});

test('serves the stored zip of the paquete with its sat identifier as file name', function () {
    Storage::fake('local');
    $paquete = Paquete::factory()->extraido()->create(['solicitud_id' => 3, 'id_paquete_sat' => 'ABC_01']);
    Storage::disk('local')->put('paquetes/3/ABC_01.zip', 'contenido del zip');

    $this->actingAs(User::factory()->create());

    $respuesta = $this->get(route('paquetes.zip', $paquete));

    $respuesta->assertOk()->assertDownload('ABC_01.zip');
    expect($respuesta->streamedContent())->toBe('contenido del zip');
});

test('returns 404 when the paquete has no zip yet', function () {
    Storage::fake('local');
    $paquete = Paquete::factory()->create();

    $this->actingAs(User::factory()->create());

    $this->get(route('paquetes.zip', $paquete))->assertNotFound();
});
