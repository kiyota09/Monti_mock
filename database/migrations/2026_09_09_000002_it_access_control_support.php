<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IT organization-wide access control support:
 *  - users.suspended_until for temporary suspensions
 *  - it_access_logs audit trail for every change made in IT Access Control
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_until')->nullable()->after('is_active');
        });

        Schema::create('it_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50); // account.disabled, account.enabled, account.suspended, account.restored, position.updated, modules.updated, pages.updated
            $table->text('details')->nullable(); // human-readable summary
            $table->json('meta')->nullable(); // structured before/after
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['target_user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_access_logs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('suspended_until');
        });
    }
};
