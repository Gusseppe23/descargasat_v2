<?php

use DescargaSat\ClientesApi\Http\Controllers\TokenController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::livewire('clientes-api', 'clientes-api::gestion')->name('clientes-api.index');
});

Route::middleware(['api'])->prefix('api')->group(function () {
    Route::post('/token', TokenController::class)->name('clientes-api.token');
});
