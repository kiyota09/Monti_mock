<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hrm_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // SHF-001
            $table->string('name'); // Morning Shift
            $table->string('shift_type')->default('Fixed'); // Fixed | Flexible | Rotating
            $table->string('start_time')->nullable(); // 8:00 AM
            $table->string('end_time')->nullable(); // 5:00 PM
            $table->unsignedInteger('break_minutes')->default(60); // 1 hr
            $table->unsignedInteger('grace_minutes')->default(15); // 15 min
            $table->json('working_days')->nullable(); // ["Monday",...]
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hrm_shift_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('hrm_shifts')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['shift_id', 'user_id']);
        });

        // Default roster matching the spec sheet.
        $rows = [
            ['code' => 'SHF-001', 'name' => 'Morning Shift', 'shift_type' => 'Fixed', 'start_time' => '8:00 AM', 'end_time' => '5:00 PM', 'break_minutes' => 60, 'grace_minutes' => 15, 'working_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'is_active' => true],
            ['code' => 'SHF-002', 'name' => 'Afternoon Shift', 'shift_type' => 'Fixed', 'start_time' => '2:00 PM', 'end_time' => '11:00 PM', 'break_minutes' => 60, 'grace_minutes' => 15, 'working_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'is_active' => true],
            ['code' => 'SHF-003', 'name' => 'Night Shift', 'shift_type' => 'Fixed', 'start_time' => '10:00 PM', 'end_time' => '7:00 AM', 'break_minutes' => 60, 'grace_minutes' => 15, 'working_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'is_active' => true],
            ['code' => 'SHF-004', 'name' => 'Flexible Shift', 'shift_type' => 'Flexible', 'start_time' => null, 'end_time' => null, 'break_minutes' => 0, 'grace_minutes' => 0, 'working_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'is_active' => true],
        ];
        foreach ($rows as $row) {
            \App\Models\Hrm\HrmShift::firstOrCreate(
                ['code' => $row['code']],
                $row + ['working_days' => $row['working_days'], 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_shift_user');
        Schema::dropIfExists('hrm_shifts');
    }
};
