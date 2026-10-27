<?php

use App\Support\Cron\Cron;
use Illuminate\Support\Facades\Schedule;

// Hosting cron: "php …/core/artisan schedule:run" every 5-15 minutes (ready-made line on the System page).
// Each job decides on its own whether it is time to run (App\Support\Cron\Cron). It runs in the same process as
// schedule:run, so it also works on hosting that does not allow starting other processes (proc_open disabled).
Schedule::call(fn () => app(Cron::class)->run(fromCron: true))
    ->name('resume:cron')
    ->everyMinute();
