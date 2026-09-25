<?php

namespace DescargaSat\Fiel\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class FielServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Livewire::addNamespace('fiel', viewPath: __DIR__.'/../../resources/views/livewire');
    }
}
