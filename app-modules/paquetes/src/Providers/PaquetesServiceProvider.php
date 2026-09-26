<?php

namespace DescargaSat\Paquetes\Providers;

use DescargaSat\Paquetes\Contracts\Paquetes;
use DescargaSat\Paquetes\Services\PaquetesGuardados;
use Illuminate\Support\ServiceProvider;

class PaquetesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Paquetes::class, PaquetesGuardados::class);
    }

    public function boot(): void {}
}
