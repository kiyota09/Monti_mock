<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_stops', function (Blueprint $table) {
            $table->enum('outcome', ['pending', 'delivered', 'attempted', 'refused', 'partial'])
                ->default('pending')->after('status');
            $table->string('signature_path')->nullable()->after('outcome');
            $table->string('pod_photo_path')->nullable()->after('signature_path');
            $table->text('pod_notes')->nullable()->after('pod_photo_path');
            $table->string('recipient_name')->nullable()->after('pod_notes');
            $table->timestamp('completed_at')->nullable()->after('recipient_name');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_stops', function (Blueprint $table) {
            $table->dropColumn(['outcome', 'signature_path', 'pod_photo_path', 'pod_notes', 'recipient_name', 'completed_at']);
        });
    }
};
