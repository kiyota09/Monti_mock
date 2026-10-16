<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('manufacturing_supervisor_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('manufacturing_role', [
                'knitting_yarn',
                'dyeing_color',
                'dyeing_fabric_softener',
                'dyeing_squeezer',
                'dyeing_ironing',
                'dyeing_forming',
                'dyeing_packaging',
                'maintenance_checker',
                'checker_quality'
            ]);
            $table->timestamps();
            $table->unique(['user_id', 'manufacturing_role']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('manufacturing_supervisor_roles');
    }
};