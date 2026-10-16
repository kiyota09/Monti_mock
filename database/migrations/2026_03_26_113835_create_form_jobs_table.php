<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('form_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iron_job_id')->constrained()->onDelete('cascade');
            $table->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity'); // number of products made
            $table->foreignId('product_id')->constrained(); // link to inv product
            $table->text('remarks')->nullable();
            $table->foreignId('operator_id')->constrained('users');
            $table->string('shift');
            $table->string('code')->unique(); // FORM-YYYY-MM-XXXXX
            $table->timestamp('processed_at');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('form_jobs');
    }
};
