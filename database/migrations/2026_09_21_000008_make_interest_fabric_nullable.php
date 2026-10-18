
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Leads can be captured without a known fabric interest.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE `crm_leads` MODIFY COLUMN `interest_fabric` VARCHAR(255) NULL'
            );

            return;
        }

        Schema::table('crm_leads', function (Blueprint $table) {
            $table->string('interest_fabric', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE `crm_leads` MODIFY COLUMN `interest_fabric` VARCHAR(255) NOT NULL'
            );

            return;
        }

        // Avoid a rollback failure if any rows currently contain NULL.
        DB::table('crm_leads')
            ->whereNull('interest_fabric')
            ->update(['interest_fabric' => '']);

        Schema::table('crm_leads', function (Blueprint $table) {
            $table->string('interest_fabric', 255)->nullable(false)->change();
        });
    }
};
