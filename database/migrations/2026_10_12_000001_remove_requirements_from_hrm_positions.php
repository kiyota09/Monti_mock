<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['required_training', 'required_certifications', 'required_equipment'] as $column) {
            if (Schema::hasColumn('hrm_positions', $column)) {
                Schema::table('hrm_positions', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('hrm_positions', function (Blueprint $table) {
            if (! Schema::hasColumn('hrm_positions', 'required_training')) {
                $table->text('required_training')->nullable();
            }
            if (! Schema::hasColumn('hrm_positions', 'required_certifications')) {
                $table->text('required_certifications')->nullable();
            }
            if (! Schema::hasColumn('hrm_positions', 'required_equipment')) {
                $table->text('required_equipment')->nullable();
            }
        });
    }
};
