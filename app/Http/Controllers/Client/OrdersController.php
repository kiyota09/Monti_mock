<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
// Assuming you have an Order or PurchaseOrder model
// use App\Models\PurchaseOrder; 

class OrdersController extends Controller
{
    /**
     * Display the partner's orders.
     * Route Name: client.orders
     */
    public function orders()
    {
        // Get the currently authenticated B2B client
        $client = Auth::guard('client')->user();

        // Fetch orders belonging to this client
        // $orders = $client->orders()->latest()->get(); 

        return Inertia::render('Client/Orders', [
            'client' => $client,
            // 'orders' => $orders,
        ]);
    }

    /**
     * Handle purchase order acceptance.
     * Route Name: client.orders.accept
     */
    public function acceptPurchaseOrder(Request $request, $id)
    {
        $order = \App\Models\Ord\PurchaseOrder::where('client_id', Auth::guard('client')->id())
            ->findOrFail($id);

        if ($order->status !== 'pending_client_approval') {
            return back()->withErrors(['error' => 'This order can no longer be accepted.']);
        }

        $order->update(['status' => 'approved']);

        return back()->with('message', "Order {$order->po_number} accepted successfully.");
    }
}