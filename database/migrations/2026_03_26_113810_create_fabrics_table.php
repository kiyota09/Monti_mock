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
        Schema::create('fabrics', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g. FABRIC-2026-03-00001
            $table->foreignId('manufacturing_order_id')->nullable()->constrained();
            $table->foreignId('machine_id')->nullable()->constrained();
            $table->string('yarn_type');
            $table->string('roll_no');
            $table->decimal('weight', 10, 2);
            $table->text('remarks')->nullable();
            $table->foreignId('operator_id')->constrained('users');
            $table->string('shift');
            $table->timestamp('processed_at');
            $table->enum('status', ['pending', 'dyeing', 'softener', 'squeezer', 'iron', 'forming', 'packed', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fabrics');
    }
};
