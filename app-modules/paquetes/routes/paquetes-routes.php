<?php

use DescargaSat\Paquetes\Http\Controllers\DescargarZipController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::get('paquetes/{paquete}/zip', DescargarZipController::class)->name('paquetes.zip');
});
