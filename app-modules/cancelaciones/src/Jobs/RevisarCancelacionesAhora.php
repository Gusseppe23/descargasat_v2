<?php

namespace DescargaSat\Cancelaciones\Jobs;

use DescargaSat\Cancelaciones\Actions\RevisarCancelaciones;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Revisión de cancelaciones pedida desde la pantalla; usa la misma acción y el mismo candado que el scheduler.
 */
class RevisarCancelacionesAhora implements ShouldQueue
{
    use Queueable;

    /**
     * Marca en caché mientras una Revisión pedida desde la pantalla está en cola o corriendo.
     */
    public const MARCA_EN_CURSO = 'cancelaciones:revision-solicitada';

    /**
     * La marca caduca sola por si el job nunca llega a correr.
     */
    public const SEGUNDOS_DE_LA_MARCA = 600;

    public int $tries = 1;

    public function __construct()
    {
        $this->onConnection('database');
    }

    /**
     * Encola la Revisión, salvo que ya haya una pedida. Devuelve si la encoló.
     */
    public static function solicitar(): bool
    {
        if (! Cache::add(self::MARCA_EN_CURSO, true, self::SEGUNDOS_DE_LA_MARCA)) {
            return false;
        }

        self::dispatch();

        return true;
    }

    public static function enCurso(): bool
    {
        return Cache::has(self::MARCA_EN_CURSO);
    }

    public function handle(RevisarCancelaciones $revisarCancelaciones): void
    {
        try {
            $revisarCancelaciones();
        } finally {
            Cache::forget(self::MARCA_EN_CURSO);
        }
    }

    public function failed(): void
    {
        Cache::forget(self::MARCA_EN_CURSO);
    }
}
