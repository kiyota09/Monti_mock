<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operational bulletins: VP-to-plant floor notices (overtime, audits,
 * rule changes). Distinct from the secretary's office memos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vp_bulletins', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            // company|knitting|dyeing|finishing|maintenance|boiler
            $table->string('audience', 32)->default('company');
            $table->string('priority', 16)->default('normal'); // low|normal|high|urgent
            $table->date('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vp_bulletins');
    }
};
