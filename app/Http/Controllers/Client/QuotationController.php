<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client\ClientQuotation;
use App\Models\Ord\PurchaseOrder;
use App\Models\Ord\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuotationController extends Controller
{
    /**
     * Accept a quotation and convert it into a purchase order.
     */
    public function accept(ClientQuotation $quotation)
    {
        // Check authorization: the quotation must belong to the authenticated client
        if ($quotation->client_id !== Auth::guard('client')->id()) {
            return back()->withErrors(['error' => 'Unauthorized access to this quotation.']);
        }

        // Check if quotation is already accepted or rejected
        if ($quotation->status !== 'sent') {
            return back()->withErrors(['error' => 'This quotation has already been processed.']);
        }

        DB::beginTransaction();
        try {
            // Generate purchase order number
            $poNumber = 'PO-' . date('Y') . '-' . str_pad(PurchaseOrder::count() + 1, 5, '0', STR_PAD_LEFT);

            // Create purchase order
            $purchaseOrder = PurchaseOrder::create([
                'client_id' => $quotation->client_id,
                'po_number' => $poNumber,
                'subtotal' => $quotation->subtotal,
                'discount_amount' => 0,
                'total_amount' => $quotation->grand_total,
                'delivery_date' => $this->extractDeliveryDate($quotation->custom_notes),
                'status' => 'approved',
                'tier_level' => null,
                'notes' => 'Created from accepted quotation: ' . $quotation->quotation_number,
            ]);

            // Create purchase order items from quotation items
            foreach ($quotation->items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ]);
            }

            // Update quotation status
            $quotation->update([
                'status' => 'accepted',
                'client_accepted_at' => now(),
            ]);

            DB::commit();

            return back()->with('success', "Purchase Order {$poNumber} has been created.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to accept quotation: ' . $e->getMessage(), [
                'quotation_id' => $quotation->id,
                'trace' => $e->getTraceAsString()
            ]);
            return back()->withErrors(['error' => 'Failed to accept quotation: ' . $e->getMessage()]);
        }
    }

    /**
     * Extract delivery date from custom_notes (format: "Delivery: YYYY-MM-DD").
     */
    private function extractDeliveryDate($customNotes)
    {
        if (preg_match('/Delivery:\s*(\d{4}-\d{2}-\d{2})/', $customNotes, $matches)) {
            return $matches[1];
        }
        return null;
    }
}