<?php

namespace DescargaSat\Fiel\Providers;

use DescargaSat\Fiel\Contracts\Fieles;
use DescargaSat\Fiel\Contracts\UsoDeFiel;
use DescargaSat\Fiel\Services\FielesGuardadas;
use DescargaSat\Fiel\Services\SinSolicitudesEnCurso;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class FielServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Fieles::class, FielesGuardadas::class);
        $this->app->bindIf(UsoDeFiel::class, SinSolicitudesEnCurso::class);
    }

    public function boot(): void
    {
        Livewire::addNamespace('fiel', viewPath: __DIR__.'/../../resources/views/livewire');
    }
}
