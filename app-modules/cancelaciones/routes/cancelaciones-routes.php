<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::livewire('cancelaciones', 'cancelaciones::lista')->name('cancelaciones.index');
});
