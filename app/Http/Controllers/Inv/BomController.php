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
        $recipes = BomRecord::with('client', 'product')->get();
        $clients = Client::all();
        $products = Product::all();
        $materials = Material::all();
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