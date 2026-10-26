<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every Help Center report auto-creates an IT Service Desk ticket, so
     * the IT helpdesk actually receives employee reports in its own queue.
     */
    public function up(): void
    {
        Schema::table('problem_reports', function (Blueprint $table) {
            $table->foreignId('ticket_id')->nullable()->after('user_id')
                ->constrained('it_tickets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('problem_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ticket_id');
        });
    }
};
