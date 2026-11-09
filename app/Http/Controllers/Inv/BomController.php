<?php

namespace App\Http\Controllers\Inv;

use App\Http\Controllers\Controller;
use App\Models\Man\BomRecord;
use App\Models\Crm\Client;
use App\Models\Inv\Material;
use App\Models\Inv\Product;
use App\Models\Ord\SalesOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BomController extends Controller
{
    /**
     * Display a listing of recipe records (formerly BOM).
     */
    public function index()
    {
        $recipes = BomRecord::with([
            'client',
            'product',
            'fabricSampleRequest.formulations',
            'sampleRequests.formulations',
        ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();
        $clients = Client::all();
        $products = Product::all();
        $materials = Material::all();
        $materialsById = $materials->keyBy('id');

        $recipes->each(function (BomRecord $recipe) use ($materialsById) {
            $sample = $recipe->fabricSampleRequest
                ?: $recipe->sampleRequests->sortByDesc('updated_at')->first();
            $approvedAttempt = $sample?->formulations->firstWhere('status', 'client_approved');
            $formula = $sample?->formula ?: ($approvedAttempt?->formula ?? []);

            $materialQuantities = $recipe->materials ?? [];
            if (empty($materialQuantities) && $formula) {
                $liquorRatio = (string) ($formula['liquor_ratio'] ?? '1:10');
                $liquor = preg_match('/(?:^|:)\s*(\d+(?:\.\d+)?)\s*$/', $liquorRatio, $match)
                    ? (float) $match[1]
                    : 10.0;
                foreach ([['dyestuffs', 'pct'], ['dye_stuffs', 'pct'], ['auxiliary_agents', 'gpl'], ['auxiliaries', 'gpl'], ['basic_chemicals', 'gpl'], ['post_treatment', 'gpl'], ['yarns', 'qty']] as [$group, $quantityKey]) {
                    foreach ($formula[$group] ?? [] as $row) {
                        $materialId = (int) ($row['material_id'] ?? 0);
                        if (! $materialId) {
                            continue;
                        }

                        $usesGrams = array_key_exists('grams', $row) || (($row['unit'] ?? null) === 'g' && array_key_exists('amount', $row));
                        $quantity = (float) ($usesGrams ? ($row['grams'] ?? $row['amount'] ?? 0) : ($row[$quantityKey] ?? $row['quantity'] ?? 0));
                        $perKg = $usesGrams
                            ? $quantity / 1000
                            : ($group === 'dyestuffs' || $group === 'dye_stuffs'
                                ? $quantity / 100
                                : (in_array($group, ['yarns'], true) ? $quantity : $quantity * $liquor / 1000));
                        $materialQuantities[$materialId] = ($materialQuantities[$materialId] ?? 0) + $perKg;
                    }
                }
            }

            $recipe->setAttribute('formula_materials', collect($materialQuantities)
                ->map(function ($quantity, $materialId) use ($materialsById) {
                    $material = $materialsById->get((int) $materialId);

                    return [
                        'material_id' => (int) $materialId,
                        'mat_id' => $material?->mat_id,
                        'name' => $material?->display_name ?? $material?->name ?? 'Unknown material',
                        'quantity' => (float) $quantity,
                        'unit' => 'kg per kg fabric',
                    ];
                })
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all());

            if ($sample) {
                $recipe->setAttribute('sample_info', [
                    'code' => $sample->code,
                    'fabric_design' => $sample->fabricDesignForSample(),
                    'gsm' => $sample->gsm,
                    'texture' => $sample->texture,
                    'image_path' => $sample->sample_image_path ?: $approvedAttempt?->attachment_path,
                    'approved_at' => $approvedAttempt?->reviewed_at,
                ]);
                $recipe->setAttribute('approved_formula', $formula);
                $recipe->setAttribute('yarn_type', $recipe->yarn_type ?: $sample->yarnTypeForSample());
                $recipe->setAttribute('weave_design', $recipe->weave_design ?: ($formula['weave_design'] ?? $sample->fabricDesignForSample()));
            } else {
                $recipe->setAttribute('sample_info', null);
                $recipe->setAttribute('approved_formula', null);
            }

            $recipe->unsetRelation('fabricSampleRequest');
            $recipe->unsetRelation('sampleRequests');
        });

        // Orders for per-order formula pinning (latest first).
        $orders = SalesOrder::orderBy('created_at', 'desc')
            ->take(200)
            ->get(['id', 'client_id', 'jo_number', 'quantity', 'status', 'recipe_id']);

        return Inertia::render('Dashboard/Inventory/Bom', [
            'boms'      => $recipes,       // Keep prop name 'boms' for compatibility with the Vue file
            'clients'   => $clients,
            'products'  => $products,
            'materials' => $materials,
            'orders'    => $orders,
        ]);
    }

    /**
     * Pin a recipe to one specific client order. The order must belong to
     * the recipe's client — formulas never leak across clients.
     */
    protected function pinToOrder(BomRecord $recipe, $orderId): void
    {
        if (empty($orderId)) {
            return;
        }

        $order = SalesOrder::findOrFail($orderId);

        if ((int) $order->client_id !== (int) $recipe->client_id) {
            abort(422, 'That order belongs to a different client than this recipe.');
        }

        $order->update(['recipe_id' => $recipe->id]);
    }

    /**
     * Store a newly created recipe.
     * materials = {material_id: kg-per-unit-qty} — the DSS multiplies each
     * qty straight by the order quantity, so values must already be in
     * kg per 1 unit ordered (lab %OWF/gpl are converted at formulation).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id'    => 'required|exists:clients,id',
            'product_id'   => 'required|exists:products,id',
            'yarn_type'    => 'required|string',
            'dye_color'    => 'required|string',
            'weave_design' => 'required|string',
            'materials'    => 'required|array|min:1',
            'materials.*'  => 'required|numeric|min:0.0001|max:100000',
            'order_id'     => 'nullable|exists:sales_orders,id',
        ]);

        $orderId = $data['order_id'] ?? null;
        unset($data['order_id']);

        $recipe = BomRecord::updateOrCreate(
            ['client_id' => $data['client_id'], 'product_id' => $data['product_id']],
            $data
        );

        $this->pinToOrder($recipe, $orderId);

        return redirect()->back()->with('success', 'Recipe saved successfully.');
    }

    /**
     * Update the specified recipe.
     */
    public function update(Request $request, $id)
    {
        $recipe = BomRecord::findOrFail($id);

        $data = $request->validate([
            'client_id'    => 'required|exists:clients,id',
            'product_id'   => 'required|exists:products,id',
            'yarn_type'    => 'required|string',
            'dye_color'    => 'required|string',
            'weave_design' => 'required|string',
            'materials'    => 'required|array|min:1',
            'materials.*'  => 'required|numeric|min:0.0001|max:100000',
            'order_id'     => 'nullable|exists:sales_orders,id',
        ]);

        $orderId = $data['order_id'] ?? null;
        unset($data['order_id']);

        $recipe->update($data);

        $this->pinToOrder($recipe, $orderId);

        return redirect()->back()->with('success', 'Recipe updated successfully.');
    }

    /**
     * Remove the specified recipe.
     */
    public function destroy($id)
    {
        $recipe = BomRecord::findOrFail($id);
        $recipe->delete();

        return redirect()->back()->with('success', 'Recipe deleted successfully.');
    }
}
