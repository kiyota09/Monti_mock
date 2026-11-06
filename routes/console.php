<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Core\Notification;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pull incoming client email into the CRM Email inbox. The Email page also
// syncs on demand, so this only matters when "php artisan schedule:work"
// (or a cron entry for `php artisan schedule:run`) is running.
Schedule::command('crm:sync-email')->everyMinute()->withoutOverlapping();

Schedule::call(function () {
    $today = now()->toDateString();
    $lastReminderDate = now()->addDays(3)->toDateString();
    $evaluations = DB::table('hrm_kpi_evaluations')->whereNotNull('due_date')
        ->whereIn('status', ['Assigned', 'In Progress', 'Submitted', 'Manager Review', 'HR Review'])
        ->whereDate('due_date', '<=', $lastReminderDate)->get();

    foreach ($evaluations as $evaluation) {
        $overdue = $evaluation->due_date < $today;
        $recipients = match ($evaluation->status) {
            'Submitted' => [$evaluation->manager_id],
            'Manager Review' => [$evaluation->manager_id],
            'HR Review' => DB::table('users')->where('role', 'HRM')->where('is_active', true)->pluck('id')->all(),
            default => [$evaluation->evaluator_id],
        };
        foreach (array_filter(array_unique($recipients)) as $recipientId) {
            $type = $overdue ? 'kpi_evaluation_overdue' : 'kpi_evaluation_due_soon';
            $body = "{$evaluation->reference} is " . ($overdue ? 'overdue.' : "due {$evaluation->due_date}.");
            $alreadySentToday = Notification::query()->where('user_id', $recipientId)->where('type', $type)
                ->where('body', 'like', '%' . $evaluation->reference . '%')->whereDate('created_at', $today)->exists();
            if (! $alreadySentToday) {
                Notification::notify((int) $recipientId, $type, $overdue ? 'KPI evaluation overdue' : 'KPI evaluation due soon', $body, 'hrm.workforce.performance-kpi.index');
            }
        }
    }
})->dailyAt('08:00')->name('kpi-due-notifications')->withoutOverlapping();
