<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::livewire('fiel', 'fiel::gestion')->name('fiel.index');
});
