<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finishing flow: the quality checker inspects each packaging output before the
 * finishing supervisor pushes it to logistics. Adds a `checked`/`rejected`
 * status plus the checker audit columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `packages` MODIFY `status` ENUM('pending','checked','rejected','assigned','delivered') NOT NULL DEFAULT 'pending'");
        } else {
            Schema::table('packages', function (Blueprint $table) {
                $table->enum('status', ['pending', 'checked', 'rejected', 'assigned', 'delivered'])->default('pending')->change();
            });
        }

        Schema::table('packages', function (Blueprint $table) {
            $table->foreignId('checked_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable()->after('checked_by');
            $table->text('check_remarks')->nullable()->after('checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checked_by');
            $table->dropColumn(['checked_at', 'check_remarks']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `packages` MODIFY `status` ENUM('pending','assigned','delivered') NOT NULL DEFAULT 'pending'");
        } else {
            Schema::table('packages', function (Blueprint $table) {
                $table->enum('status', ['pending', 'assigned', 'delivered'])->default('pending')->change();
            });
        }
    }
};
