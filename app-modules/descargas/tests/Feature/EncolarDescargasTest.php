<?php

use DescargaSat\Descargas\Jobs\DescargarPaquete;
use DescargaSat\Paquetes\Contracts\PaquetesRegistrados;
use DescargaSat\Paquetes\Models\Paquete;
use Illuminate\Support\Facades\Queue;

test('queues one download for each pendiente paquete of the solicitud', function () {
    [$primero, $segundo] = Paquete::factory()->count(2)->create(['solicitud_id' => 5]);
    Paquete::factory()->extraido()->create(['solicitud_id' => 5]);
    Paquete::factory()->create(['solicitud_id' => 6]);
    Queue::fake([DescargarPaquete::class]);

    PaquetesRegistrados::dispatch(5);

    Queue::assertPushed(DescargarPaquete::class, 2);
    Queue::assertPushed(DescargarPaquete::class, fn (DescargarPaquete $job): bool => $job->paqueteId === $primero->id);
    Queue::assertPushed(DescargarPaquete::class, fn (DescargarPaquete $job): bool => $job->paqueteId === $segundo->id);
});
