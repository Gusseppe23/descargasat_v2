<?php

namespace DescargaSat\Cancelaciones\Console;

use DescargaSat\Cancelaciones\Actions\RevisarCancelaciones;
use Illuminate\Console\Command;

class RevisionDeCancelaciones extends Command
{
    protected $signature = 'sat:cancelaciones';

    protected $description = 'Pregunta al SAT por las Cancelaciones pendientes del RFC con el CSD activo';

    public function handle(RevisarCancelaciones $revisarCancelaciones): int
    {
        $revision = $revisarCancelaciones();

        if ($revision === null) {
            $this->components->info('Ya hay una Revisión de cancelaciones en curso.');
        } elseif ($revision->problema !== null) {
            $this->components->warn($revision->problema);
        }

        return self::SUCCESS;
    }
}
