<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('crm_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users'); // staff who requested
            $table->string('action'); // e.g. 'update_lead_status', 'convert_to_client', etc.
            $table->json('data'); // store the action parameters
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignId('manager_id')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_approvals');
    }
};
