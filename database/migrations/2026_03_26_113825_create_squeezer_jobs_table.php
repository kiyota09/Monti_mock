<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('squeezer_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('softener_job_id')->constrained()->onDelete('cascade');
            $table->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->foreignId('operator_id')->constrained('users');
            $table->string('shift');
            $table->string('code')->unique(); // SQUEEZE-YYYY-MM-XXXXX
            $table->timestamp('processed_at');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('squeezer_jobs');
    }
};