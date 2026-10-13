<?php

namespace DescargaSat\Csd\Providers;

use DescargaSat\Csd\Contracts\Csds;
use DescargaSat\Csd\Services\CsdsGuardados;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class CsdServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Csds::class, CsdsGuardados::class);
    }

    public function boot(): void
    {
        Livewire::addNamespace('csd', viewPath: __DIR__.'/../../resources/views/livewire');
    }
}
