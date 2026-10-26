<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pull incoming client email into the CRM Email inbox. The Email page also
// syncs on demand, so this only matters when "php artisan schedule:work"
// (or a cron entry for `php artisan schedule:run`) is running.
Schedule::command('crm:sync-email')->everyMinute()->withoutOverlapping();
