<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone_number');
            $table->string('street_address');
            $table->string('street_address_line2')->nullable();
            $table->string('city');
            $table->string('state_province');
            $table->string('postal_zip_code');
            $table->string('position_applied');
            $table->string('expected_salary')->nullable();
            $table->enum('notice_period', ['immediate', '15_days', '30_days'])->default('immediate');
            $table->enum('textile_experience', ['yes', 'no'])->default('no');
            $table->string('status')->default('Pending'); // Pending, Interview, Hired, Rejected
            $table->string('sss_file')->nullable();
            $table->string('philhealth_file')->nullable();
            $table->string('pagibig_file')->nullable();
            $table->timestamps();
        });

        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->onDelete('cascade');
            $table->dateTime('scheduled_at');
            $table->string('interview_type')->nullable();
            $table->integer('duration')->default(45); // in minutes
            $table->string('interviewer')->nullable();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('applicants');
    }
};
