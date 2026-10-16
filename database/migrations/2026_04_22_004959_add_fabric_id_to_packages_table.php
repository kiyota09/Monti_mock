<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->foreignId('fabric_id')->nullable()->after('manufacturing_order_id')
                ->constrained('fabrics')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropForeign(['fabric_id']);
            $table->dropColumn('fabric_id');
        });
    }
};