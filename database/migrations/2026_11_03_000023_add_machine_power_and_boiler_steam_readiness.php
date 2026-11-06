<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->string('power', 20)->default('Electricity')->after('type');
        });

        DB::table('machines')->where('type', 'dyeing')->update(['power' => 'Steam']);
        DB::table('machines')->whereIn('type', ['squeezer', 'hydro'])->update(['power' => 'Electricity']);
        DB::table('machines')->whereIn('type', ['heatsetting', 'ironing'])->update(['power' => 'Gas']);
        DB::table('machines')->whereIn('type', ['tubetex', 'tubetext'])->update(['power' => 'Steam']);
        DB::table('machines')->where('type', 'boiler')->update(['power' => 'Gas']);

        Schema::table('boiler_logs', function (Blueprint $table) {
            $table->boolean('steam_available')->default(false)->after('operating_hours');
        });
    }

    public function down(): void
    {
        Schema::table('boiler_logs', function (Blueprint $table) {
            $table->dropColumn('steam_available');
        });

        Schema::table('machines', function (Blueprint $table) {
            $table->dropColumn('power');
        });
    }
};
