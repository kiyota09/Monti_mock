<?php

namespace App\Http\Controllers\Man\Staff;

use App\Http\Controllers\Man\Concerns\HandlesProductionGates;
use App\Models\Man\DyeJob;
use App\Models\Man\Fabric;
use App\Models\Man\IronJob;
use App\Models\Man\ManufacturingOrder;
use App\Models\Man\Package;
use App\Models\Man\SoftenerJob;
use App\Models\Man\SqueezerJob;
use Inertia\Inertia;

class CheckerQualityController extends ManufacturingStaffController
{
    use HandlesProductionGates;

    public function index()
    {
        $stats = [
            'pending_orders' => ManufacturingOrder::where('status', 'pending')->count(),
            'packages_pending' => Package::where('status', 'pending')->count(),
            'packages_checked' => Package::where('status', 'checked')->count(),
            'packages_rejected' => Package::where('status', 'rejected')->count(),
        ];

        return Inertia::render('Dashboard/MAN/Employee/CheckerQuality/Index', [
            'stats' => $stats,
        ]);
    }

    /**
     * Finishing quality workspace: packaging outputs awaiting inspection.
     */
    public function production()
    {
        $orders = ManufacturingOrder::with(['purchaseOrder.client', 'salesOrder.client'])
            ->where('status', 'pending')
            ->get()
            ->map(function ($order) {
                if ($order->purchaseOrder) {
                    return [
                        'id' => $order->id,
                        'po_number' => $order->purchaseOrder->po_number,
                        'client' => $order->purchaseOrder->client->company_name ?? 'N/A',
                        'total_quantity' => $order->total_quantity,
                        'remaining_quantity' => $order->remaining_quantity,
                        'type' => 'purchase_order',
                    ];
                } elseif ($order->salesOrder) {
                    return [
                        'id' => $order->id,
                        'po_number' => $order->salesOrder->jo_number ?? 'JO-' . $order->salesOrder->id,
                        'client' => $order->salesOrder->client->company_name ?? 'N/A',
                        'total_quantity' => $order->total_quantity,
                        'remaining_quantity' => $order->remaining_quantity,
                        'type' => 'sales_order',
                    ];
                }
                return null;
            })
            ->filter();

        // Dyeing is finished by the time a package exists — the checker only
        // inspects packaging outputs in the Finishing department, and only
        // those whose Job Order the finishing supervisor assigned to them.
        $scope = $this->myAssignmentScope();
        $packages = Package::with(['fabric.salesOrder.recipe.product', 'operator'])
            ->where('status', 'pending')
            ->where(function ($q) use ($scope) {
                $q->whereIn('manufacturing_order_id', $scope['mo'])
                    ->orWhereHas('fabric', fn ($fq) => $fq->whereIn('sales_order_id', $scope['so']));
            })
            ->get()
            ->map(function ($pkg) {
                $product = $pkg->fabric->salesOrder->recipe->product ?? null;
                return [
                    'id' => $pkg->id,
                    'code' => $pkg->code,
                    'quantity' => $pkg->quantity,
                    'status' => $pkg->status,
                    'product_name' => $product->name ?? 'Unknown Product',
                    'product_sku' => $product->sku ?? '',
                    'fabric_code' => $pkg->fabric->code ?? '',
                    'yarn_type' => $pkg->fabric->yarn_type ?? '',
                    'weight' => $pkg->fabric->weight ?? 0,
                    'operator' => $pkg->operator->name ?? '',
                ];
            });

        return Inertia::render('Dashboard/MAN/Employee/CheckerQuality/Production', [
            'orders' => $orders,
            'packages' => $packages,
        ]);
    }
}
