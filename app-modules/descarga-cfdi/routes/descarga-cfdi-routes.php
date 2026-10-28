<?php

use DescargaSat\DescargaCfdi\Http\Controllers\DescargaCfdiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:api'])->prefix('api')->group(function () {
    Route::post('/descarga-cfdi', DescargaCfdiController::class)->name('descarga-cfdi.descargar');
});
