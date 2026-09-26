<?php

namespace DescargaSat\Verificacion\Providers;

use DescargaSat\Verificacion\Console\VerificarSolicitudes;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class VerificacionServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([VerificarSolicitudes::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(VerificarSolicitudes::class)->everyFiveMinutes()->withoutOverlapping();
        });
    }
}
