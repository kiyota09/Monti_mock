<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'body', 'link_route', 'memo_id', 'is_read', 'created_by',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Named `notify` (not `push`) — Eloquent already defines a non-static
     * push() method, so a static push() is a fatal error.
     */
    public static function notify(int $userId, string $type, string $title, ?string $body = null, ?string $linkRoute = null, ?int $createdBy = null, ?int $memoId = null): self
    {
        return static::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'link_route' => $linkRoute,
            'memo_id' => $memoId,
            'is_read' => false,
            // Portal guards (client, agency, applicant) have separate user
            // tables, so only the internal web guard may populate this FK.
            'created_by' => $createdBy ?? auth()->guard('web')->id(),
        ]);
    }

    /** Notify every active ERP user who can view a specific module page. */
    public static function notifyPage(
        string $module,
        string $page,
        string $type,
        string $title,
        ?string $body = null,
        ?string $linkRoute = null,
        array $exceptUserIds = [],
        ?int $createdBy = null,
        ?string $manufacturingRole = null,
    ): int {
        $exceptUserIds = array_values(array_unique(array_map('intval', $exceptUserIds)));
        $moduleVariants = array_values(array_unique([$module, strtoupper($module), strtolower($module)]));

        // Only inspect users who are native to the module or hold an explicit
        // usable grant for this page. hasPagePermission remains authoritative,
        // including explicit disabled rows and module-native grant rules.
        $users = User::query()
            ->where('is_active', true)
            ->whereNotIn('id', $exceptUserIds ?: [0])
            ->when($manufacturingRole, fn ($query) => $query->where('manufacturing_role', $manufacturingRole))
            ->where(function ($query) use ($moduleVariants, $module, $page) {
                $query->whereRaw('UPPER(role) = ?', [strtoupper($module)])
                    ->orWhereHas('pagePermissions', function ($permissions) use ($moduleVariants, $page) {
                        $permissions->whereIn('module', $moduleVariants)
                            ->whereRaw('LOWER(page) = ?', [strtolower($page)])
                            ->where(function ($level) {
                                $level->whereNull('permission_level')->orWhereIn('permission_level', ['view', 'edit']);
                            });
                    });
            })
            ->get();

        $sent = 0;
        foreach ($users as $user) {
            if (! $user->hasPagePermission($module, $page, 'view')) {
                continue;
            }

            static::notify((int) $user->id, $type, $title, $body, $linkRoute, $createdBy);
            $sent++;
        }

        return $sent;
    }

    /** Notify active users who can enter a module when the destination has no page-level gate. */
    public static function notifyModule(
        string $module,
        string $type,
        string $title,
        ?string $body = null,
        ?string $linkRoute = null,
        array $exceptUserIds = [],
        ?int $createdBy = null,
    ): int {
        $exceptUserIds = array_values(array_unique(array_map('intval', $exceptUserIds)));
        $moduleVariants = array_values(array_unique([$module, strtoupper($module), strtolower($module)]));
        $sent = 0;

        User::query()
            ->where('is_active', true)
            ->whereNotIn('id', $exceptUserIds ?: [0])
            ->where(function ($query) use ($module, $moduleVariants) {
                $query->where('role', 'admin')
                    ->orWhere('role', $module)
                    ->when(strtoupper($module) === 'MAN', fn ($users) => $users->orWhere('is_manufacturing_supervisor', true))
                    ->orWhereHas('moduleAccess', fn ($access) => $access->where('module', $module))
                    ->orWhereHas('pagePermissions', function ($permissions) use ($moduleVariants) {
                        $permissions->whereIn('module', $moduleVariants)
                            ->where(function ($level) {
                                $level->whereNull('permission_level')->orWhereIn('permission_level', ['view', 'edit']);
                            });
                    });
            })
            ->chunkById(100, function ($users) use ($type, $title, $body, $linkRoute, $createdBy, &$sent) {
            foreach ($users as $user) {
                static::notify((int) $user->id, $type, $title, $body, $linkRoute, $createdBy);
                $sent++;
            }
        });

        return $sent;
    }

    /** Notify one named employee only when the employee can still view the destination page. */
    public static function notifyUserOnPage(
        int $userId,
        string $module,
        string $page,
        string $type,
        string $title,
        ?string $body = null,
        ?string $linkRoute = null,
        ?int $createdBy = null,
    ): bool {
        $user = User::query()->whereKey($userId)->where('is_active', true)->first();
        if (! $user || ! $user->hasPagePermission($module, $page, 'view')) {
            return false;
        }

        static::notify($userId, $type, $title, $body, $linkRoute, $createdBy);
        return true;
    }
}
