<?php

namespace DescargaSat\ClientesApi\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class ClientesApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Livewire::addNamespace('clientes-api', viewPath: __DIR__.'/../../resources/views/livewire');
    }
}
