<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Free-text CRM notes kept on a client record (used by the Approval board's
     * "Add Note" action). Previously referenced by the controller but never
     * persisted because the column did not exist.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('clients', 'notes')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->text('notes')->nullable()->after('company_address');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'notes')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('notes');
            });
        }
    }
};
