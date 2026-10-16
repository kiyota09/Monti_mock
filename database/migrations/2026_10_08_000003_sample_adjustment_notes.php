<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Surface the client's color-adjustment notes on the sample request
     * itself so the dyeing lab chemist can read them on the shade page
     * (the notes otherwise live only inside the conversation thread,
     * which the chemist never opens).
     */
    public function up(): void
    {
        Schema::table('fabric_sample_requests', function (Blueprint $table) {
            $table->text('adjustment_notes')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('fabric_sample_requests', function (Blueprint $table) {
            $table->dropColumn('adjustment_notes');
        });
    }
};
