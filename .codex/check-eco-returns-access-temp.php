<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$permissions = Illuminate\Support\Facades\DB::table('page_permissions')
    ->where('module', 'ECO')->where('page', 'returns')->orderByDesc('id')->limit(30)
    ->get(['id', 'user_id', 'module', 'page', 'permission_level']);
$logs = Illuminate\Support\Facades\DB::table('it_access_logs')
    ->whereIn('action', ['pages.updated', 'modules.updated'])->orderByDesc('id')->limit(20)
    ->get(['target_user_id', 'action', 'meta', 'created_at'])
    ->map(function ($log) {
        $meta = json_decode($log->meta ?? '{}', true) ?: [];
        return [
            'target_user_id' => $log->target_user_id,
            'action' => $log->action ?? null,
            'after' => array_slice($meta['after'] ?? [], 0, 12),
            'eco_returns_in_after' => collect($meta['after'] ?? [])->filter(fn ($item) => str_contains((string) $item, 'ECO.returns'))->values()->all(),
            'created_at' => $log->created_at,
        ];
    });

echo json_encode(['permissions' => $permissions, 'recent_page_saves' => $logs], JSON_PRETTY_PRINT).PHP_EOL;
