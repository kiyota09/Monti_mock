<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // PACKAGE-YYYY-MM-XXXXX
            $table->foreignId('operator_id')->constrained('users');
            $table->string('shift');
            $table->timestamp('packaged_at');
            $table->enum('status', ['pending', 'assigned', 'delivered'])->default('pending');
            $table->foreignId('manufacturing_order_id')->nullable()->constrained()->nullOnDelete(); // assigned to which order
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('packages');
    }
};
