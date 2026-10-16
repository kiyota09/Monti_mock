<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('form_jobs', function (Blueprint $table) {
            $table->enum('status', ['pending', 'packed', 'rejected'])->default('pending')->after('quantity');
        });
    }

    public function down()
    {
        Schema::table('form_jobs', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
