<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            // Snapshot of the Monti origin used for the optimized multi-stop plan.
            $table->decimal('origin_lat', 10, 8)->nullable()->after('route_id');
            $table->decimal('origin_lng', 11, 8)->nullable()->after('origin_lat');
            $table->string('origin_label')->nullable()->after('origin_lng');

            // Aggregated multi-stop plan.
            $table->decimal('total_distance_km', 10, 2)->nullable()->after('arrival_time');
            $table->unsignedInteger('estimated_minutes')->nullable()->after('total_distance_km');
            $table->json('route_geometry')->nullable()->after('estimated_minutes');

            // Business-client rating of this delivery.
            $table->unsignedTinyInteger('rating')->nullable()->after('notes');
            $table->text('rating_comment')->nullable()->after('rating');
            $table->timestamp('rated_at')->nullable()->after('rating_comment');
            $table->foreignId('rated_by_client_id')->nullable()->after('rated_at')
                ->constrained('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rated_by_client_id');
            $table->dropColumn([
                'origin_lat',
                'origin_lng',
                'origin_label',
                'total_distance_km',
                'estimated_minutes',
                'route_geometry',
                'rating',
                'rating_comment',
                'rated_at',
            ]);
        });
    }
};
