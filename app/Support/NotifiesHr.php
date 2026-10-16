<?php

namespace App\Support;

use App\Models\Core\User;
use App\Models\Core\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * Fan-out notifications to the HR audience (HRM managers + anyone holding
 * a usable agency/workers/requests/employee HRM grant). Used for agency
 * events: new worker, proposals, attendance disputes.
 */
class NotifiesHr
{
    /**
     * @return int[]
     */
    public static function audienceIds(): array
    {
        try {
            if (! Schema::hasTable('users')) {
                return [];
            }

            $ids = User::where('is_active', true)
                ->where(function ($q) {
                    $q->where(function ($w) {
                        $w->where('role', 'HRM')->where('position', 'manager');
                    })->orWhereIn('id', function ($sq) {
                        $sq->select('user_id')->from('page_permissions')
                            ->whereIn('module', ['HRM', 'hrm', 'Hrm'])
                            ->whereRaw('LOWER(page) IN (?, ?, ?, ?, ?, ?)', ['agencies', 'workers', 'requests', 'employee', 'agency_attendance', 'agency_billing'])
                            ->where(function ($w) {
                                $w->whereIn('permission_level', ['view', 'edit'])
                                    ->orWhereNull('permission_level');
                            });
                    });
                })
                ->pluck('id')->unique()->values()->all();

            return array_map('intval', $ids);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function push(string $title, ?string $body = null, ?string $linkRoute = null): void
    {
        try {
            if (! Schema::hasTable('notifications')) {
                return;
            }
            foreach (static::audienceIds() as $userId) {
                Notification::notify($userId, 'agency', $title, $body, $linkRoute, auth()->id() ?: null);
            }
        } catch (\Throwable $e) {
            // Notifications must never break the underlying action.
        }
    }
}
