<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            // Link to the business client this route serves
            $table->foreignId('client_id')
                ->nullable()
                ->after('id')
                ->constrained('clients')
                ->nullOnDelete();

            // MontiTextiles origin coordinates (from CEO Geolocation)
            $table->decimal('origin_lat', 10, 8)->nullable()->after('origin');
            $table->decimal('origin_lng', 11, 8)->nullable()->after('origin_lat');

            // Client destination coordinates (from Client Profile pin)
            $table->decimal('destination_lat', 10, 8)->nullable()->after('destination');
            $table->decimal('destination_lng', 11, 8)->nullable()->after('destination_lat');

            // Actual road route geometry [[lat,lng],...] from OSRM for map polylines
            $table->json('route_geometry')->nullable()->after('waypoints');
        });
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn([
                'client_id',
                'origin_lat',
                'origin_lng',
                'destination_lat',
                'destination_lng',
                'route_geometry',
            ]);
        });
    }
};