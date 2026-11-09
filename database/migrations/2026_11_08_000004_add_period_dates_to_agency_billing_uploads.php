<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agency_billing_uploads')) {
            return;
        }

        $hasPeriodStart = Schema::hasColumn('agency_billing_uploads', 'period_start');
        $hasPeriodEnd = Schema::hasColumn('agency_billing_uploads', 'period_end');
        if ($hasPeriodStart && $hasPeriodEnd) {
            return;
        }

        Schema::table('agency_billing_uploads', function (Blueprint $table) use ($hasPeriodStart, $hasPeriodEnd) {
            if (! $hasPeriodStart) {
                $table->date('period_start')->nullable()->after('agency_id');
            }
            if (! $hasPeriodEnd) {
                $table->date('period_end')->nullable()->after('period_start');
            }
        });
    }

    public function down(): void
    {
        // These columns may predate this compatibility migration in databases
        // where the upload table was created with billing periods already set.
    }
};
