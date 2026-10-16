<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('fabrics', function (Blueprint $table) {
            $table->enum('rejection_action', ['recolor', 'total'])->nullable()->after('status');
            $table->text('rejection_reason')->nullable()->after('rejection_action');
        });
    }

    public function down()
    {
        Schema::table('fabrics', function (Blueprint $table) {
            $table->dropColumn(['rejection_action', 'rejection_reason']);
        });
    }
};
