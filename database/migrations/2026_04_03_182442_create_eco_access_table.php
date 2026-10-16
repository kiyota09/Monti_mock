<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('eco_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // CEO, Secretary or GM
            $table->boolean('can_access_eco')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('eco_access');
    }
};