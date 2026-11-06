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
            ->whereDate('processed_at', today())
            ->orderByDesc('processed_at')
            ->get(['machine_id', 'steam_available'])
            ->unique('machine_id');

        return $latestLogs->contains(fn (BoilerLog $log) => (bool) $log->steam_available);
    }
}
