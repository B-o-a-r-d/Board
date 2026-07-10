<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('cards:notify-due')->hourly();
Schedule::command('automations:run-scheduled')->hourly();
Schedule::command('activities:prune')->daily();
