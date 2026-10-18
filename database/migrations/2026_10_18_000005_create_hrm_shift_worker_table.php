<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agency workers can be assigned shifts too (mirrors hrm_shift_user).
     * Kept as its own pivot so worker shifts never collide with Monti
     * employees' shifts.
     */
    public function up(): void
    {
        Schema::create('hrm_shift_worker', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('hrm_shifts')->onDelete('cascade');
            $table->foreignId('worker_id')->constrained('workers')->onDelete('cascade');
            $table->date('effective_date')->nullable();
            $table->timestamps();

            $table->unique(['shift_id', 'worker_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_shift_worker');
    }
};
