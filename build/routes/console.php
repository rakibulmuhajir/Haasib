<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('palette:cleanup-idempotency')->dailyAt('03:00');

// Auto-lock previous month's daily closes on the 3rd of each month at 2 AM
Schedule::command('fuel:lock-month-closes')->monthlyOn(3, '02:00');
