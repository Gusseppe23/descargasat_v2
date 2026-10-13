<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::livewire('csd', 'csd::gestion')->name('csd.index');
});
