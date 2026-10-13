<?php

namespace DescargaSat\Cancelaciones\Providers;

use DescargaSat\Cancelaciones\Console\RevisionDeCancelaciones;
use DescargaSat\Cancelaciones\Contracts\Cancelaciones;
use DescargaSat\Cancelaciones\Services\CancelacionesGuardadas;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class CancelacionesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Cancelaciones::class, CancelacionesGuardadas::class);
    }

    public function boot(): void
    {
        Livewire::addNamespace('cancelaciones', viewPath: __DIR__.'/../../resources/views/livewire');

        if ($this->app->runningInConsole()) {
            $this->commands([RevisionDeCancelaciones::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $minutos = max(1, min(59, (int) config('sat.cancelaciones.cada_minutos')));

            $schedule->command(RevisionDeCancelaciones::class)->cron("*/{$minutos} * * * *")->withoutOverlapping();
        });
    }
}
