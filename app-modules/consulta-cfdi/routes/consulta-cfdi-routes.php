<?php

use DescargaSat\ConsultaCfdi\Http\Controllers\ConsultaCfdiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:api'])->prefix('api')->group(function () {
    Route::post('/consulta-cfdi', ConsultaCfdiController::class)->name('consulta-cfdi.consultar');
});
