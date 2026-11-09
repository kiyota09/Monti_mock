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
            $table->string('qualification', 40)->nullable()->after('type');
            $table->string('temperature', 20)->nullable()->after('power');
        });

        DB::table('machines')->where('type', 'dyeing')->whereNull('qualification')
            ->update(['qualification' => 'dyeing']);
    }

    public function down(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->dropColumn(['qualification', 'temperature']);
        });
    }
};
