<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * New HRM > Workers and HRM > Requests pages: grant them to everyone
     * who already holds a usable HRM grant, mirroring their Agencies level
     * (else their employee level, else view). Users whose HRM rows are all
     * disabled, and users with zero HRM rows, get nothing.
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('page_permissions')) {
            return;
        }

        foreach (['workers', 'requests'] as $page) {
            $grantees = DB::table('page_permissions as p')
                ->select('p.user_id')
                ->whereIn('p.module', ['HRM', 'hrm', 'Hrm'])
                ->where(function ($q) {
                    $q->whereIn('p.permission_level', ['view', 'edit'])
                        ->orWhereNull('p.permission_level');
                })
                ->whereNotExists(function ($q) use ($page) {
                    $q->select(DB::raw(1))->from('page_permissions as x')
                        ->whereColumn('x.user_id', 'p.user_id')
                        ->whereIn('x.module', ['HRM', 'hrm', 'Hrm'])
                        ->whereRaw('LOWER(x.page) = ?', [$page]);
                })
                ->groupBy('p.user_id')
                ->pluck('p.user_id');

            foreach ($grantees as $userId) {
                $levelRaw = DB::table('page_permissions')
                    ->where('user_id', $userId)
                    ->whereIn('module', ['HRM', 'hrm', 'Hrm'])
                    ->whereRaw('LOWER(page) = ?', ['agencies'])
                    ->orderByDesc('id')
                    ->value('permission_level')
                    ?? DB::table('page_permissions')
                        ->where('user_id', $userId)
                        ->whereIn('module', ['HRM', 'hrm', 'Hrm'])
                        ->whereRaw('LOWER(page) = ?', ['employee'])
                        ->orderByDesc('id')
                        ->value('permission_level');

                // Legacy NULL levels are grandfathered as 'edit'.
                $level = $levelRaw === null ? 'edit' : strtolower((string) $levelRaw);
                $level = in_array($level, ['view', 'edit'], true) ? $level : 'view';

                DB::table('page_permissions')->insert([
                    'user_id' => $userId,
                    'module' => 'HRM',
                    'page' => $page,
                    'permission_level' => $level,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('page_permissions')) {
            return;
        }

        DB::table('page_permissions')
            ->whereIn('module', ['HRM', 'hrm', 'Hrm'])
            ->whereRaw('LOWER(page) IN (?, ?)', ['workers', 'requests'])
            ->delete();
    }
};
