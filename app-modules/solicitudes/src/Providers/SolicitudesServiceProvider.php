<?php

namespace DescargaSat\Solicitudes\Providers;

use DescargaSat\Fiel\Contracts\UsoDeFiel;
use DescargaSat\Solicitudes\Contracts\SolicitudesPendientes;
use DescargaSat\Solicitudes\Services\SolicitudesEnCursoDeFiel;
use DescargaSat\Solicitudes\Services\SolicitudesPendientesGuardadas;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class SolicitudesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UsoDeFiel::class, SolicitudesEnCursoDeFiel::class);
        $this->app->bind(SolicitudesPendientes::class, SolicitudesPendientesGuardadas::class);
    }

    public function boot(): void
    {
        Livewire::addNamespace('solicitudes', viewPath: __DIR__.'/../../resources/views/livewire');
    }
}
