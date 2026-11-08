<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            if (! Schema::hasColumn('agencies', 'billing_cycle')) {
                $table->string('billing_cycle', 40)->default('Monthly');
            }
            if (! Schema::hasColumn('agencies', 'payment_terms')) {
                $table->string('payment_terms', 80)->default('Due upon invoice approval');
            }
            if (! Schema::hasColumn('agencies', 'payment_custom_terms')) {
                $table->string('payment_custom_terms')->nullable();
            }
            if (! Schema::hasColumn('agencies', 'ot_multiplier')) {
                $table->decimal('ot_multiplier', 5, 2)->default(1.25);
            }
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('agencies', 'billing_cycle') ? 'billing_cycle' : null,
                Schema::hasColumn('agencies', 'payment_terms') ? 'payment_terms' : null,
                Schema::hasColumn('agencies', 'payment_custom_terms') ? 'payment_custom_terms' : null,
                Schema::hasColumn('agencies', 'ot_multiplier') ? 'ot_multiplier' : null,
            ]);

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
