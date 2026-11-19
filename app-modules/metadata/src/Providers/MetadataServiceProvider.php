<?php

namespace DescargaSat\Metadata\Providers;

use DescargaSat\Metadata\Contracts\MetadataCfdi;
use DescargaSat\Metadata\Services\MetadataGuardada;
use Illuminate\Support\ServiceProvider;

/**
 * Los listeners de src/Listeners los registra internachi/modular por descubrimiento de eventos.
 */
class MetadataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MetadataCfdi::class, MetadataGuardada::class);
    }

    public function boot(): void {}
}
