<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HRM_NEW leave management: DB-rooted leave types + per-user yearly
     * balances. Existing `leave_requests` table is reused as the request
     * master (status: pending/approved/rejected).
     */
    public function up(): void
    {
        Schema::create('hrm_leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique(); // vacation, sick, ...
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('default_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hrm_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('hrm_leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('entitled')->default(0);
            $table->unsignedInteger('used')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'leave_type_id', 'year']);
        });

        $now = now();
        $types = [
            ['vacation', 'Vacation Leave', 12],
            ['sick', 'Sick Leave', 15],
            ['emergency', 'Emergency', 5],
            ['maternity', 'Maternity', 60],
            ['other', 'Other', 5],
        ];
        foreach ($types as [$code, $name, $days]) {
            if (! DB::table('hrm_leave_types')->where('code', $code)->exists()) {
                DB::table('hrm_leave_types')->insert([
                    'code' => $code, 'name' => $name, 'default_days' => $days,
                    'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_leave_balances');
        Schema::dropIfExists('hrm_leave_types');
    }
};
