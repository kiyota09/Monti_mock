<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('softener_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fabric_id')->constrained()->onDelete('cascade');
            $table->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            $table->string('softener_type');
            $table->string('softener_no');
            $table->text('remarks')->nullable();
            $table->foreignId('operator_id')->constrained('users');
            $table->string('shift');
            $table->string('code')->unique(); // SOFT-YYYY-MM-XXXXX
            $table->timestamp('processed_at');
            $table->enum('status', ['softened', 'squeezed', 'quality_check', 'resoften'])->default('softened');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('softener_jobs');
    }
};
