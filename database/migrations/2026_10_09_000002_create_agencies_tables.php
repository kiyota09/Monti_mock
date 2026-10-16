<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agency management (HRM): manpower agencies Monti Corp works with,
     * their login users, and the HR activity trail per agency.
     */
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('tin', 60)->nullable()->unique();
            $table->text('address')->nullable();
            $table->string('dole_number', 100)->nullable()->unique();
            $table->date('dole_expiry')->nullable();
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->string('service_fee_type', 20)->default('percentage'); // percentage|fixed
            $table->decimal('service_fee_value', 15, 2)->default(0);
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 60)->nullable();
            $table->string('status', 20)->default('Active'); // Active|Suspended|Terminated
            $table->timestamps();
            $table->index(['status', 'contract_end']);
        });

        Schema::create('agency_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->index(['agency_id', 'is_active']);
        });

        Schema::create('agency_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->string('action', 60);
            $table->text('details')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['agency_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_activities');
        Schema::dropIfExists('agency_users');
        Schema::dropIfExists('agencies');
    }
};
