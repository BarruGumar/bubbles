<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('audit:prune --days=180')->weekly()->sundays()->at('03:00');
Schedule::command('punishments:expire')->everyFiveMinutes();
Schedule::command('users:prune-unverified')->everyFifteenMinutes();
