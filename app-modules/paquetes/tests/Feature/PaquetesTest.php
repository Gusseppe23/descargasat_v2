<?php

use DescargaSat\Paquetes\Contracts\Paquetes;
use DescargaSat\Paquetes\Models\Paquete;

test('tells which solicitudes still have a paquete being downloaded or extracted', function () {
    Paquete::factory()->create(['solicitud_id' => 1]);
    Paquete::factory()->extraido()->create(['solicitud_id' => 1]);
    Paquete::factory()->descargado()->create(['solicitud_id' => 2]);
    Paquete::factory()->extraido()->create(['solicitud_id' => 3]);
    Paquete::factory()->fallido()->create(['solicitud_id' => 3]);
    Paquete::factory()->create(['solicitud_id' => 4]);

    expect(app(Paquetes::class)->conDescargaEnCurso([1, 2, 3]))->toEqualCanonicalizing([1, 2]);
});
