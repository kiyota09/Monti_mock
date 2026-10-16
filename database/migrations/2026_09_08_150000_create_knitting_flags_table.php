<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Knitting flag channel: Knitting Yarn staff flag machine / yarn-feed /
 * quality issues on knitting machines; Knitting Mechanics receive and
 * resolve them. Separate from plant-wide MachineReport breakdowns
 * (which still go to Maintenance).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knitting_flags', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->foreignId('fabric_id')->nullable()->constrained('fabrics')->nullOnDelete();
            $table->string('issue_type', 32)->default('other'); // machine_jam|yarn_feeder|needle_damage|tension_issue|quality_defect|other
            $table->text('description');
            $table->string('status', 32)->default('open'); // open|acknowledged|resolved
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knitting_flags');
    }
};
