<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::livewire('solicitudes', 'solicitudes::lista')->name('solicitudes.index');
    Route::livewire('solicitudes/nueva', 'solicitudes::nueva')->name('solicitudes.create');
    Route::livewire('solicitudes/{solicitud}', 'solicitudes::detalle')->whereNumber('solicitud')->name('solicitudes.show');
});
