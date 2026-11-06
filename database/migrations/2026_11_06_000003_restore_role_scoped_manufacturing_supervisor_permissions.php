<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Account-specific rows came from the incorrect access-control model.
        DB::table('man_supervisor_staff_page_permissions')->whereNotNull('staff_user_id')->delete();

        Schema::table('man_supervisor_staff_page_permissions', function (Blueprint $table) {
            $table->dropUnique('man_supervisor_staff_account_page_unique');
            $table->dropIndex('man_supervisor_staff_account_index');
            $table->dropConstrainedForeignId('staff_user_id');
            $table->unique(
                ['supervisor_user_id', 'manufacturing_role', 'page'],
                'man_supervisor_staff_page_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('man_supervisor_staff_page_permissions', function (Blueprint $table) {
            $table->dropUnique('man_supervisor_staff_page_unique');
            $table->foreignId('staff_user_id')->nullable()->after('supervisor_user_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['supervisor_user_id', 'staff_user_id', 'page'], 'man_supervisor_staff_account_page_unique');
            $table->index(['supervisor_user_id', 'staff_user_id'], 'man_supervisor_staff_account_index');
        });
    }
};
