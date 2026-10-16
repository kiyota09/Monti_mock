<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Higher-ups (secretary, special_officer, manager, manufacturing
     * supervisor) hold default-on access to their root module — root pages
     * can never be disabled. Flip any legacy 'disabled' rows on those roots
     * to the default 'edit' so existing data matches the rule enforced by
     * ItAccessControlController::lockedRootModuleFor() (updatePages coercion
     * + seed default + Page Access tab UI lock).
     */
    public function up(): void
    {
        $core = ['HRM', 'CRM', 'MAN', 'LOG'];

        $users = DB::table('users')
            ->where('role', '!=', 'CEO')
            ->where(function ($q) {
                $q->whereIn('position', ['manager', 'secretary', 'special_officer'])
                    ->orWhere('is_manufacturing_supervisor', 1);
            })
            ->get(['id', 'role', 'position', 'is_manufacturing_supervisor']);

        foreach ($users as $u) {
            if (! empty($u->is_manufacturing_supervisor)) {
                $root = 'MAN';
            } else {
                $roleUpper = strtoupper($u->role ?? '');
                $root = null;
                foreach ($core as $c) {
                    if ($roleUpper !== '' && str_contains($roleUpper, $c)) {
                        $root = $c;
                        break;
                    }
                }
                $root ??= ($roleUpper !== '' ? $roleUpper : null);
            }
            if (! $root) {
                continue;
            }

            DB::table('page_permissions')
                ->where('user_id', $u->id)
                ->where('permission_level', 'disabled')
                ->whereRaw('UPPER(`module`) = ?', [$root])
                ->update(['permission_level' => 'edit']);
        }
    }

    public function down(): void
    {
        // Non-reversible by design: pre-migration disabled rows cannot be
        // distinguished afterwards — and the rule says locked roots are never
        // disabled, so there is nothing to restore.
    }
};
