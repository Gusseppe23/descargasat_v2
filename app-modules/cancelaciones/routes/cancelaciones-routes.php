<?php

use DescargaSat\Cancelaciones\Http\Controllers\DescargarEvidenciaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::livewire('cancelaciones', 'cancelaciones::lista')->name('cancelaciones.index');
    Route::livewire('cancelaciones/{cancelacion}', 'cancelaciones::detalle')->name('cancelaciones.detalle');
    Route::get('cancelaciones/envios/{envio}/{archivo}', DescargarEvidenciaController::class)
        ->whereIn('archivo', ['enviado', 'recibido'])
        ->name('cancelaciones.evidencia');
});
