<?php

namespace App\Services\Man;

use App\Models\Man\BoilerLog;
use App\Models\Man\Machine;

class BoilerSteamService
{
    public function isAvailable(): bool
    {
        $boilerIds = Machine::query()->where('type', 'boiler')->where('status', 'available')->pluck('id');
        if ($boilerIds->isEmpty()) {
            return false;
        }

        $latestLogs = BoilerLog::query()->whereIn('machine_id', $boilerIds)
            ->orderByDesc('processed_at')
            ->get(['machine_id', 'steam_available', 'operating_hours', 'processed_at'])
            ->unique('machine_id');

        return $latestLogs->contains(function (BoilerLog $log) {
            if (! $log->steam_available || ! $log->processed_at || (float) $log->operating_hours <= 0) {
                return false;
            }

            return $log->processed_at->copy()
                ->addMinutes((int) round((float) $log->operating_hours * 60))
                ->isFuture();
        });
    }
}
