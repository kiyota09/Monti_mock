
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('material_requests', 'forwarded_at')) {
            Schema::table('material_requests', function (Blueprint $table) {
                $table->timestamp('forwarded_at')->nullable();
            });
        }

        if (!Schema::hasColumn('material_requests', 'forwarded_by')) {
            Schema::table('material_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('forwarded_by')->nullable();
            });

            Schema::table('material_requests', function (Blueprint $table) {
                $table->foreign('forwarded_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        // SQLite can use a string status column without altering it.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE material_requests
                MODIFY COLUMN status
                ENUM(
                    'pending',
                    'forwarded',
                    'rfq_sent',
                    'po_created',
                    'fulfilled',
                    'cancelled'
                ) NOT NULL DEFAULT 'pending'
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('material_requests', 'forwarded_by')) {
            Schema::table('material_requests', function (Blueprint $table) {
                $table->dropForeign(['forwarded_by']);
            });

            Schema::table('material_requests', function (Blueprint $table) {
                $table->dropColumn('forwarded_by');
            });
        }

        if (Schema::hasColumn('material_requests', 'forwarded_at')) {
            Schema::table('material_requests', function (Blueprint $table) {
                $table->dropColumn('forwarded_at');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE material_requests
                MODIFY COLUMN status
                ENUM(
                    'pending',
                    'rfq_sent',
                    'po_created',
                    'fulfilled',
                    'cancelled'
                ) NOT NULL DEFAULT 'pending'
            ");
        }
    }
};
