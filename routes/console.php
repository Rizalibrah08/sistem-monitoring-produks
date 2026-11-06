<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('documents:flag-expiring')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('bpom:expire-pre-registrations')->dailyAt('00:30')->withoutOverlapping();
