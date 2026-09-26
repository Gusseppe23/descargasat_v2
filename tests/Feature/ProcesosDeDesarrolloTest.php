<?php

use Illuminate\Foundation\DevCommands;

test('artisan dev runs the scheduler and the queue worker needed by the background processes', function () {
    $comandos = collect(DevCommands::commands())->pluck('command', 'name');

    expect($comandos->get('scheduler'))->toBe('php artisan schedule:work')
        ->and($comandos->get('queue'))->toStartWith('php artisan queue:listen');
});
