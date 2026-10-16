<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Ord\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClientInvoiceController extends Controller
{
    public function index()
    {
        $orders = PurchaseOrder::where('client_id', Auth::guard('client')->id())
            ->whereIn('status', ['approved', 'pending_client_approval'])
            ->latest()
            ->get();
        return Inertia::render('Client/Invoice', ['orders' => $orders]);
    }

    /**
     * Detailed invoice: header + line items with computed line totals,
     * amounts, status and attached PO document (if any).
     */
    public function show($id)
    {
        $order = PurchaseOrder::with(['items.product'])
            ->where('client_id', Auth::guard('client')->id())
            ->findOrFail($id);

        $items = $order->items->map(fn ($i) => [
            'id' => $i->id,
            'product_name' => $i->product->name ?? '—',
            'quantity' => $i->quantity,
            'unit_price' => $i->unit_price,
            'line_total' => (float) $i->quantity * (float) $i->unit_price,
        ]);

        return Inertia::render('Client/InvoiceShow', [
            'order' => array_merge($order->toArray(), [
                'items' => $items,
                'items_total' => $items->sum('line_total'),
            ]),
        ]);
    }

    /**
     * Client accepts the invoice (price agreement). Only orders awaiting
     * client approval can be accepted — approved ones stay as-is.
     */
    public function accept($id)
    {
        $order = PurchaseOrder::where('client_id', Auth::guard('client')->id())
            ->findOrFail($id);

        if ($order->status !== 'pending_client_approval') {
            return back()->withErrors(['error' => 'This invoice can no longer be accepted.']);
        }

        $order->update(['status' => 'approved']);

        return back()->with('success', "Invoice {$order->po_number} accepted.");
    }

    /**
     * Client sends signed PO document(s) against this invoice.
     * Files are stored and linked to the order; acceptance stays a
     * separate deliberate decision via accept().
     */
    public function sendPO(Request $request, $id)
    {
        $order = PurchaseOrder::where('client_id', Auth::guard('client')->id())
            ->findOrFail($id);

        $request->validate([
            'po_files' => 'required|array|min:1',
            'po_files.*' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'notes' => 'nullable|string|max:500',
        ]);

        $paths = [];
        foreach ($request->file('po_files') as $file) {
            $paths[] = $file->store('client_po', 'public').' ('.$file->getClientOriginalName().')';
        }

        $order->update([
            'attachment_path' => $order->attachment_path
                ? $order->attachment_path."\n".implode("\n", $paths)
                : implode("\n", $paths),
            'notes' => trim(($order->notes ? $order->notes."\n" : '').($request->notes ? 'Client PO note: '.$request->notes : 'Client sent signed PO document(s).')),
        ]);

        return back()->with('success', 'Purchase Order document(s) sent.');
    }

    // Payment creation will be handled by Finance module later
}
