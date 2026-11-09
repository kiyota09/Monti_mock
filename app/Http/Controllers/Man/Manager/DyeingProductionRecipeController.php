<?php

namespace App\Http\Controllers\Man\Manager;

use App\Http\Controllers\Controller;
use App\Models\Inv\Material;
use App\Models\Man\ManufacturingOrder;
use App\Models\Ord\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DyeingProductionRecipeController extends Controller
{
    private const GROUPS = [
        'dyestuffs' => ['label' => 'Dye stuffs', 'aliases' => ['dyestuffs', 'dye_stuffs'], 'dose' => 'grams', 'unit' => 'kg'],
        'basic_chemicals' => ['label' => 'Basic Chemicals', 'aliases' => ['basic_chemicals', 'basic_chemical'], 'dose' => 'grams', 'unit' => 'kg'],
        'auxiliary_agents' => ['label' => 'Auxiliary Agents', 'aliases' => ['auxiliary_agents', 'auxiliaries', 'auxiliary'], 'dose' => 'grams', 'unit' => 'kg'],
        'post_treatment' => ['label' => 'Post Treatment', 'aliases' => ['post_treatment', 'post_treatment_agents'], 'dose' => 'grams', 'unit' => 'kg'],
        'yarns' => ['label' => 'Yarn components', 'aliases' => ['yarns'], 'dose' => 'qty', 'unit' => 'kg'],
    ];

    public function index()
    {
        $orders = SalesOrder::with([
            'client',
            'purchaseOrder',
            'recipe.product',
            'recipe.fabricSampleRequest.formulations',
            'activeManufacturingOrder',
        ])->whereNotNull('recipe_id')
            ->whereHas('purchaseOrder', fn ($purchaseOrder) => $purchaseOrder->whereNotNull('crm_passed_to_eco_at'))
            ->latest()->get()->map(function (SalesOrder $salesOrder) {
            $productionOrder = $salesOrder->activeManufacturingOrder;
            $recipe = $salesOrder->recipe;
            $request = $recipe?->fabricSampleRequest;
            $purchaseOrder = $salesOrder->purchaseOrder;
            if (! $salesOrder || ! $purchaseOrder || (int) $purchaseOrder->client_id !== (int) $salesOrder->client_id) return null;
            if ($recipe && (int) $recipe->client_id !== (int) $salesOrder->client_id) return null;

            $approvedSample = $request
                && $request->status === \App\Models\Crm\FabricSampleRequest::STATUS_APPROVED
                && (int) $request->client_id === (int) $salesOrder->client_id
                && (int) $request->recipe_id === (int) $recipe?->id;
            $approvedFormulation = $approvedSample
                ? $request->formulations->firstWhere('status', 'client_approved')
                : null;
            $approvedClientFormula = $approvedFormulation?->formula ?: ($approvedSample ? $request->formula : null);
            $linkedRecipeFormula = $request ? $approvedClientFormula : $recipe?->dyeing_formula;
            $formula = $productionOrder?->dyeing_production_recipe ?: $linkedRecipeFormula;
            if (! is_array($formula) || ! $formula) return null;

            $groupRows = [];
            $materialIds = collect(self::GROUPS)->flatMap(fn ($group) => collect($group['aliases'])
                ->flatMap(fn ($key) => $formula[$key] ?? []))->pluck('material_id')->filter()->unique();
            $materials = $materialIds->isEmpty() ? collect() : Material::whereIn('id', $materialIds)->get()->keyBy('id');
            $ratioSource = $formula['liquor_ratio'] ?? null;
            $ratio = $this->liquorRatio($ratioSource);
            $ratioDefaulted = ! $this->hasLiquorRatio($ratioSource);
            $sampleWeightGrams = max(0.0001, (float) ($formula['sample_weight_g'] ?? 100));
            $productionWeight = (float) ($productionOrder?->total_quantity ?? 0);
            $productionWeightSource = $productionWeight > 0 ? 'manufacturing order batch' : 'sales order quantity fallback';
            if ($productionWeight <= 0) {
                $productionWeight = (float) ($salesOrder->quantity ?? 0);
            }
            foreach (self::GROUPS as $key => $definition) {
                $rows = collect($definition['aliases'])->flatMap(fn ($alias) => $formula[$alias] ?? [])->values();
                $groupRows[$key] = $rows->map(function ($row) use ($definition, $materials, $ratio, $productionWeight, $sampleWeightGrams) {
                    $id = $row['material_id'] ?? null;
                    $dose = $definition['dose'];
                    $original = array_key_exists('grams', $row)
                        ? (float) $row['grams']
                        : (($row['unit'] ?? null) === 'g'
                            ? (float) ($row['amount'] ?? 0)
                            : (array_key_exists('pct', $row) || array_key_exists('percentage', $row)
                                ? (float) ($row['pct'] ?? $row['percentage']) * $sampleWeightGrams / 100
                                : (array_key_exists('gpl', $row) || array_key_exists('g_per_liter', $row)
                                    ? (float) ($row['gpl'] ?? $row['g_per_liter']) * $ratio * $sampleWeightGrams / 1000
                                    : (float) ($row['quantity'] ?? 0))));
                    $rateKgPerKg = $original / $sampleWeightGrams;
                    $amount = $dose === 'grams'
                        ? $rateKgPerKg * $productionWeight
                        : ($dose === 'pct'
                        // Depth of shade is a percentage of fabric weight:
                        // fabric kg × shade depth (%) ÷ 100 = dye kg.
                        ? ($productionWeight * $original) / 100
                        : ($dose === 'qty'
                            ? $original * $productionWeight
                            : $original * $ratio * $productionWeight / 1000));
                    $doseDisplay = rtrim(rtrim(number_format($original, 4, '.', ''), '0'), '.');
                    $weightDisplay = number_format($productionWeight, 2, '.', '');
                    $amountDisplay = rtrim(rtrim(number_format(round($amount, 4), 4, '.', ''), '0'), '.');
                    $sampleDisplay = number_format($sampleWeightGrams, 2, '.', '');
                    $rateDisplay = rtrim(rtrim(number_format($rateKgPerKg, 6, '.', ''), '0'), '.');
                    $percentageDisplay = rtrim(rtrim(number_format(($original / $sampleWeightGrams) * 100, 4, '.', ''), '0'), '.');
                    $calculation = $dose === 'grams'
                        ? $doseDisplay.' g ÷ '.$sampleDisplay.' g = '.$rateDisplay.' kg/kg; '.$rateDisplay.' kg/kg × '.$weightDisplay.' kg'
                        : ($dose === 'pct'
                        ? $weightDisplay.' kg × '.$doseDisplay.'% ÷ 100'
                        : ($dose === 'qty'
                            ? $doseDisplay.' kg/kg × '.$weightDisplay.' kg'
                            : $doseDisplay.' g/L × '.$ratio.' L/kg × '.$weightDisplay.' kg ÷ 1000'));
                    $calculationDescription = $dose === 'grams'
                        ? $doseDisplay.' g ÷ '.$sampleDisplay.' g = '.$rateDisplay.' kg/kg ('.$percentageDisplay.'% WOF); '.$rateDisplay.' kg/kg × '.$weightDisplay.' kg = '.$amountDisplay.' kg'
                        : ($dose === 'pct'
                        ? $weightDisplay.' kg × '.$doseDisplay.'% ÷ 100 = '.$amountDisplay.' kg'
                        : ($dose === 'qty'
                            ? $doseDisplay.' kg/kg × '.$weightDisplay.' kg = '.$amountDisplay.' kg'
                            : $doseDisplay.' g/L × '.$ratio.' L/kg × '.$weightDisplay.' kg ÷ 1000 = '.$amountDisplay.' kg'));
                    return [
                        'material_id' => $id,
                        'name' => ($id ? $materials->get($id)?->display_name : null)
                            ?? ($row['name'] ?? $row['material_name'] ?? 'Material'),
                        'lab_amount' => $original,
                        'dose' => $dose,
                        'lab_unit' => match ($dose) {
                            'grams' => 'g',
                            'pct' => '% OWF',
                            'qty' => 'kg/kg',
                            default => 'g/L',
                        },
                        'calculated_amount' => round($amount, 4),
                        'calculation' => $calculation,
                        'calculation_description' => $calculationDescription,
                        'production_unit' => 'kilograms',
                    ];
                })->all();
            }

            return [
                'id' => $productionOrder?->id,
                'manufacturing_order_id' => $productionOrder?->id,
                'sales_order_id' => $salesOrder->id,
                'jo_number' => $salesOrder->jo_number ?? 'JO-'.$salesOrder->id,
                'client' => $salesOrder->client?->company_name,
                'product' => $recipe?->product?->name,
                'po_number' => $purchaseOrder->po_number,
                'client_po_number' => $purchaseOrder->client_po_number,
                'sample_code' => $request?->code,
                'recipe_id' => $recipe?->id,
                'approved_formula_version' => $approvedFormulation?->version,
                'formula_approved' => (bool) ($approvedSample || $recipe?->dyeing_formula),
                'color' => $salesOrder->color,
                'yarn_type' => $salesOrder->yarn_type,
                'design' => $salesOrder->design,
                'order_quantity_kg' => (float) ($salesOrder->quantity ?? 0),
                'production_weight_kg' => $productionWeight,
                'production_weight_source' => $productionWeightSource,
                'liquor_ratio' => $ratio,
                'liquor_ratio_source' => $ratioSource,
                'liquor_ratio_defaulted' => $ratioDefaulted,
                'sample_weight_g' => $sampleWeightGrams,
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
            'sample_weight_g' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'groups' => ['required', 'array'],
            'groups.dyestuffs' => ['present', 'array'],
            'groups.basic_chemicals' => ['present', 'array'],
            'groups.auxiliary_agents' => ['present', 'array'],
            'groups.post_treatment' => ['present', 'array'],
            'groups.yarns' => ['present', 'array'],
            'groups.*.*.material_id' => ['nullable', 'integer', Rule::exists('materials', 'id')],
            'groups.dyestuffs.*.grams' => ['required', 'numeric', 'min:0', 'max:100000'],
            'groups.basic_chemicals.*.grams' => ['required', 'numeric', 'min:0', 'max:100000'],
            'groups.auxiliary_agents.*.grams' => ['required', 'numeric', 'min:0', 'max:100000'],
            'groups.post_treatment.*.grams' => ['required', 'numeric', 'min:0', 'max:100000'],
            'groups.yarns.*.qty' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);

        $formula = ['liquor_ratio' => $validated['liquor_ratio'], 'sample_weight_g' => (float) $validated['sample_weight_g']];
        foreach (self::GROUPS as $key => $definition) {
            $formula[$definition['aliases'][0]] = collect($validated['groups'][$key])->map(fn ($row) => [
                'material_id' => $row['material_id'] ?? null,
                ($key === 'yarns' ? 'qty' : 'grams') => (float) $row[$key === 'yarns' ? 'qty' : 'grams'],
            ])->all();
        }
        $order->dyeing_production_recipe = $formula;
        $order->save();

        return back()->with('message', 'Production recipe saved for '.$order->salesOrder?->jo_number.'.');
    }

    private function liquorRatio(mixed $value): float
    {
        return $this->parsedLiquorRatio($value) ?? 10.0;
    }

    private function hasLiquorRatio(mixed $value): bool
    {
        return $this->parsedLiquorRatio($value) !== null;
    }

    private function parsedLiquorRatio(mixed $value): ?float
    {
        if (is_numeric($value)) {
            $ratio = (float) $value;
            return $ratio > 0 ? $ratio : null;
        }
        if (preg_match_all('/\d+(?:\.\d+)?/', (string) $value, $matches) && $matches[0]) {
            $ratio = (float) end($matches[0]);
            return $ratio > 0 ? $ratio : null;
        }

        return null;
    }
}
