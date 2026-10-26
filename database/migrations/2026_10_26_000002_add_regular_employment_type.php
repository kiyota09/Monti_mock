<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('hrm_employment_types')->updateOrInsert(
            ['slug' => 'regular'],
            ['name' => 'Regular', 'is_active' => true, 'archived_at' => null, 'updated_at' => $now, 'created_at' => $now]
        );
    }

    public function down(): void
    {
        // Keep the type once provisioned; employee records may depend on it.
    }
};
