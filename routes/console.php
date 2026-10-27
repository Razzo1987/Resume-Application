<?php

use App\Support\Cron\Cron;
use Illuminate\Support\Facades\Schedule;

// Cron dell'hosting: "php …/core/artisan schedule:run" ogni 5-15 minuti (riga pronta in Sistema).
// Ogni lavoro decide da sé se è il momento (App\Support\Cron\Cron). Gira nello stesso processo di schedule:run,
// così funziona anche sugli hosting che non permettono di avviare altri processi (proc_open disattivato).
Schedule::call(fn () => app(Cron::class)->run(fromCron: true))
    ->name('resume:cron')
    ->everyMinute();
