<?php

use App\Jobs\RetentionCleanupJob;
use App\Jobs\WeeklyBackupJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new WeeklyBackupJob())->weeklyOn(0, '23:55');
Schedule::job(new RetentionCleanupJob())->dailyAt('01:00');
