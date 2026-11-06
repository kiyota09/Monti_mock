<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RENAMES = [
        'MAT-185' => ['Egyptian Cotton', 'Egyptian Cotton Reserve'],
        'MAT-189' => ['Pima Cotton Yarn', 'Supima Cotton'],
        'MAT-193' => ['Cotton Yarn', 'Combed Cotton'],
        'MAT-197' => ['Cotton Blend Yarn', 'Open-End Cotton Blend'],
        'MAT-201' => ['Cotton Yarn', 'Regenerated Cotton'],
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $matId => [$oldName, $newName]) {
            DB::table('materials')
                ->where('mat_id', $matId)
                ->where('category', 'Yarn')
                ->where('name', $oldName)
                ->update(['name' => $newName]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $matId => [$oldName, $newName]) {
            DB::table('materials')
                ->where('mat_id', $matId)
                ->where('category', 'Yarn')
                ->where('name', $newName)
                ->update(['name' => $oldName]);
        }
    }
};
