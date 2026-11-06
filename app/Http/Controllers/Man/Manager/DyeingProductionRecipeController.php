<?php

namespace App\Http\Controllers\Man\Manager;

use App\Http\Controllers\Controller;
use App\Models\Inv\Material;
use App\Models\Man\ManufacturingOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DyeingProductionRecipeController extends Controller
{
    private const GROUPS = [
        'dyestuffs' => ['label' => 'Dye stuffs', 'aliases' => ['dyestuffs', 'dye_stuffs'], 'dose' => 'pct', 'unit' => 'kg'],
        'basic_chemicals' => ['label' => 'Basic Chemicals', 'aliases' => ['basic_chemicals', 'basic_chemical'], 'dose' => 'gpl', 'unit' => 'kg'],
        'auxiliary_agents' => ['label' => 'Auxiliary Agents', 'aliases' => ['auxiliary_agents', 'auxiliaries', 'auxiliary'], 'dose' => 'gpl', 'unit' => 'kg'],
        'post_treatment' => ['label' => 'Post Treatment', 'aliases' => ['post_treatment', 'post_treatment_agents'], 'dose' => 'gpl', 'unit' => 'kg'],
    ];

    public function index()
    {
        $orders = ManufacturingOrder::with([
            'salesOrder.client',
            'salesOrder.recipe.product',
            'salesOrder.recipe.fabricSampleRequest',
            'salesOrder.recipe.sampleRequests',
        ])->where(function ($query) {
            $query->whereHas('dyeingBatches')->orWhereHas('taskAssignments', fn ($assignments) => $assignments
                ->where('department', 'dyeing')->whereIn('status', ['pending', 'in_progress']));
        })->latest()->get()->map(function (ManufacturingOrder $order) {
            $recipe = $order->salesOrder?->recipe;
            $request = $recipe?->fabricSampleRequest;
            $formula = $order->dyeing_production_recipe
                ?: $recipe?->dyeing_formula
                ?: $request?->formula
                ?: $recipe?->sampleRequests?->sortByDesc('id')->first()?->formula;
            if (! is_array($formula) || ! $formula) return null;

            $groupRows = [];
            $materialIds = collect(self::GROUPS)->flatMap(fn ($group) => collect($group['aliases'])
                ->flatMap(fn ($key) => $formula[$key] ?? []))->pluck('material_id')->filter()->unique();
            $materials = $materialIds->isEmpty() ? collect() : Material::whereIn('id', $materialIds)->get()->keyBy('id');
            $ratio = $this->liquorRatio($formula['liquor_ratio'] ?? null);
            $productionWeight = (float) $order->total_quantity;
            if ($productionWeight <= 0) {
                $productionWeight = (float) $order->fabrics()->sum('weight');
            }
            if ($productionWeight <= 0) {
                $productionWeight = (float) ($order->salesOrder?->quantity ?? 0);
            }
            foreach (self::GROUPS as $key => $definition) {
                $rows = collect($definition['aliases'])->flatMap(fn ($alias) => $formula[$alias] ?? [])->values();
                $groupRows[$key] = $rows->map(function ($row) use ($definition, $materials, $ratio, $productionWeight) {
                    $id = $row['material_id'] ?? null;
                    $original = (float) ($row[$definition['dose']] ?? $row['quantity'] ?? 0);
                    $amount = $definition['dose'] === 'pct'
                        // Depth of shade is a percentage of fabric weight:
                        // fabric kg × shade depth (%) ÷ 100 = dye kg.
                        ? ($productionWeight * $original) / 100
                        : $original * $ratio * $productionWeight / 1000;
                    $calculation = $definition['dose'] === 'pct'
                        ? number_format($productionWeight, 2).' kg × '.rtrim(rtrim(number_format($original, 4, '.', ''), '0'), '.').'% ÷ 100'
                        : rtrim(rtrim(number_format($original, 4, '.', ''), '0'), '.').' g/L × '.$ratio.' L/kg × '.number_format($productionWeight, 2).' kg ÷ 1000';
                    return [
                        'material_id' => $id,
                        'name' => ($id ? $materials->get($id)?->display_name : null)
                            ?? ($row['name'] ?? $row['material_name'] ?? 'Material'),
                        'lab_amount' => $original,
                        'dose' => $definition['dose'],
                        'lab_unit' => $definition['dose'] === 'pct' ? '% OWF' : 'g/L',
                        'calculated_amount' => round($amount, 4),
                        'calculation' => $calculation,
                        'production_unit' => 'kg',
                    ];
                })->all();
            }

            return [
                'id' => $order->id,
                'jo_number' => $order->salesOrder?->jo_number ?? 'MO-'.$order->id,
                'client' => $order->salesOrder?->client?->company_name,
                'product' => $recipe?->product?->name,
                'production_weight_kg' => $productionWeight,
                'liquor_ratio' => $ratio,
                'liquor_ratio_source' => $formula['liquor_ratio'] ?? null,
                'editable_formula' => $formula,
                'groups' => $groupRows,
            ];
        })->filter()->values();

        return Inertia::render('Dashboard/MAN/Supervisor/DyeingProductionRecipes', [
            'orders' => $orders,
            'groupDefinitions' => collect(self::GROUPS)->map(fn ($group, $key) => ['key' => $key, 'label' => $group['label']])->values(),
            'materials' => Material::orderBy('name')->get(['id', 'name', 'category', 'yarn_tier'])
                ->map(fn ($material) => ['id' => $material->id, 'name' => $material->display_name]),
        ]);
    }

    public function update(Request $request, ManufacturingOrder $order)
    {
        $validated = $request->validate([
            'liquor_ratio' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'groups' => ['required', 'array'],
            'groups.dyestuffs' => ['present', 'array'],
            'groups.basic_chemicals' => ['present', 'array'],
            'groups.auxiliary_agents' => ['present', 'array'],
            'groups.post_treatment' => ['present', 'array'],
            'groups.*.*.material_id' => ['nullable', 'integer', Rule::exists('materials', 'id')],
            'groups.dyestuffs.*.pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'groups.basic_chemicals.*.gpl' => ['required', 'numeric', 'min:0', 'max:100000'],
            'groups.auxiliary_agents.*.gpl' => ['required', 'numeric', 'min:0', 'max:100000'],
            'groups.post_treatment.*.gpl' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);

        $formula = ['liquor_ratio' => $validated['liquor_ratio']];
        foreach (self::GROUPS as $key => $definition) {
            $formula[$definition['aliases'][0]] = collect($validated['groups'][$key])->map(fn ($row) => [
                'material_id' => $row['material_id'] ?? null,
                $definition['dose'] => (float) $row[$definition['dose']],
            ])->all();
        }
        $order->dyeing_production_recipe = $formula;
        $order->save();

        return back()->with('message', 'Production recipe saved for '.$order->salesOrder?->jo_number.'.');
    }

    private function liquorRatio(mixed $value): float
    {
        if (is_numeric($value) && (float) $value > 0) return (float) $value;
        if (preg_match_all('/\d+(?:\.\d+)?/', (string) $value, $matches) && $matches[0]) {
            return max(0.01, (float) end($matches[0]));
        }

        return 10.0;
    }
}
