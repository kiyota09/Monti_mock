<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->string('category', 100)->change();
            $table->string('yarn_tier', 40)->nullable()->after('category');
            $table->index(['category', 'yarn_tier']);
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropIndex(['category', 'yarn_tier']);
            $table->dropColumn('yarn_tier');
        });
    }
};
