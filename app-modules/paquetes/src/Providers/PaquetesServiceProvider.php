<?php

namespace DescargaSat\Paquetes\Providers;

use DescargaSat\Paquetes\Contracts\Paquetes;
use DescargaSat\Paquetes\Services\PaquetesGuardados;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class PaquetesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Paquetes::class, PaquetesGuardados::class);
    }

    public function boot(): void
    {
        Livewire::addNamespace('paquetes', viewPath: __DIR__.'/../../resources/views/livewire');
    }
}
