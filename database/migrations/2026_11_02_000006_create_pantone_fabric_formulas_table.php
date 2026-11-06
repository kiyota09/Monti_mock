<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pantone_fabric_formulas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pantone_reference_id')->constrained('pantone_references')->cascadeOnDelete();
            $table->foreignId('yarn_material_id')->constrained('materials')->cascadeOnDelete();
            $table->json('combinations');
            $table->timestamps();
            $table->unique(['pantone_reference_id', 'yarn_material_id'], 'pantone_fabric_yarn_unique');
        });

        // One initial formula slot for each of five common base yarn types.
        // They remain blank until a chemist records a lab-verified recipe.
        $baseYarns = DB::table('materials')
            ->where('category', 'Yarn')
            ->where(function ($query) {
                $query->where(function ($q) { $q->where('name', 'Cotton')->where('yarn_tier', 'Standard'); })
                    ->orWhere(function ($q) { $q->where('name', 'Polyester')->where('yarn_tier', 'Standard'); })
                    ->orWhere(function ($q) { $q->where('name', 'Viscose Yarn')->where('yarn_tier', 'Standard'); })
                    ->orWhere(function ($q) { $q->where('name', 'Wool')->where('yarn_tier', 'Economy'); })
                    ->orWhere(function ($q) { $q->where('name', 'Nylon Yarn')->where('yarn_tier', 'Standard'); });
            })
            ->get(['id']);

        $emptyFormula = json_encode([
            'dye_stuffs' => [],
            'auxiliary_agents' => [],
            'basic_chemicals' => [],
            'post_treatment' => [],
        ]);

        foreach (DB::table('pantone_references')->pluck('id') as $pantoneId) {
            foreach ($baseYarns as $yarn) {
                DB::table('pantone_fabric_formulas')->insertOrIgnore([
                    'pantone_reference_id' => $pantoneId,
                    'yarn_material_id' => $yarn->id,
                    'combinations' => $emptyFormula,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pantone_fabric_formulas');
    }
};
