<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Eco\ConversationMessage;
use App\Models\Eco\Inquiry;
use App\Models\Inv\Product;
use App\Models\Ord\PurchaseOrder;
use App\Models\Logistics\Delivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClientDashboardController extends Controller
{
    public function index()
    {
        $client = Auth::guard('client')->user();

        $orders = PurchaseOrder::where('client_id', $client->id)->latest();
        $totalOrders = (clone $orders)->count();
        $recentOrders = (clone $orders)->take(5)->get(['id', 'po_number', 'total_amount', 'status', 'created_at']);

        $pendingDeliveries = Delivery::with('route')
            ->whereHas('route', fn ($q) => $q->where('client_id', $client->id))
            ->whereIn('status', ['dispatched', 'in_transit'])
            ->latest('scheduled_departure')
            ->take(5)
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'po_number' => $d->delivery_number,
                'expected_date' => $d->scheduled_departure?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Client/Dashboard', [
            'client' => $client,
            'stats' => [
                'totalOrders' => $totalOrders,
                'activeInquiries' => Inquiry::where('client_id', $client->id)->where('status', 'open')->count(),
                'pendingQuotations' => \App\Models\Client\ClientQuotation::where('client_id', $client->id)
                    ->where('status', 'sent')->count(),
                // Back-compat alias (older builds read this key).
                'pendingInquiries' => Inquiry::where('client_id', $client->id)->where('status', 'open')->count(),
            ],
            'recentOrders' => $recentOrders,
            'pendingDeliveries' => $pendingDeliveries,
        ]);
    }

    /**
     * Quick order from the dashboard: opens a product inquiry conversation
     * (the portal's order entry point) so the request flows through
     * negotiation → quotation like every other inquiry. No skipped steps.
     */
    public function placeOrder(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'message' => 'required|string|max:2000',
        ]);

        $client = Auth::guard('client')->user();
        $product = Product::findOrFail($data['product_id']);

        $inquiry = Inquiry::create([
            'client_id' => $client->id,
            'product_id' => $product->id,
            'initial_message' => $data['message'],
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        ConversationMessage::create([
            'inquiry_id' => $inquiry->id,
            'sender_type' => 'client',
            'message' => $data['message'],
        ]);

        return redirect()->route('client.conversation.show', $inquiry)
            ->with('success', "Order request for {$product->name} sent. You can now continue the conversation.");
    }
}
