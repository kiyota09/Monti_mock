<?php

namespace App\Http\Controllers\Eco;

use App\Http\Controllers\Controller;
use App\Models\Pro\Supplier;
use App\Models\Pro\VendorRegistration;
use App\Models\Eco\CreditAccount;
use App\Models\SupplierMessage;
use App\Models\SupplierRequest;
use App\Models\SupplierRequestItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class EcoSupplierController extends Controller
{
    /**
     * Display the supplier directory.
     *
     * Aligned with SCM Vendors (ScmVendorController@index): same
     * VendorRegistration source of truth, same payload shape.
     * View-only here — SCM remains the sole approver.
     */
    public function index()
    {
        $registrations = VendorRegistration::with('requirements')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($r) {
                $supplierId = $r->supplier_id
                    ?? Supplier::where('email', $r->email)->value('id');

                return [
                    'id' => $r->id,
                    'supplier_id' => $supplierId,
                    'business_name' => $r->business_name,
                    'representative_name' => $r->representative_name,
                    'email' => $r->email,
                    'phone_number' => $r->phone_number,
                    'address' => $r->address,
                    'status' => $r->status,
                    'rejection_reason' => $r->rejection_reason,
                    'approved_at' => $r->approved_at,
                    'rejected_at' => $r->rejected_at,
                    'created_at' => $r->created_at,
                    'requirements' => $r->requirements,
                    // Suppliers have no uploaded logo file (unlike client
                    // CrmLogoPartner) — directory shows a branded initial
                    // avatar; logo_url stays null until a logo store exists.
                    'logo_url' => null,
                    'latest_message' => $supplierId
                        ? SupplierMessage::where('supplier_id', $supplierId)->latest()->first()
                        : null,
                ];
            });

        $suppliers = Supplier::where('status', 'approved')->get();
        foreach ($suppliers as $supplier) {
            $supplier->latest_message = SupplierMessage::where('supplier_id', $supplier->id)
                ->latest()
                ->first();
        }

        return Inertia::render('Dashboard/ECO/SupplierList', [
            'registrations' => $registrations,
            'suppliers' => $suppliers,
        ]);
    }

    protected function presentMessage(SupplierMessage $msg): array
    {
        $arr = $msg->toArray();
        if (!empty($arr['attachment'])) {
            // Stored path is relative (e.g. supplier-messages/x.pdf) —
            // expose a web URL so the frontend link actually opens.
            $arr['attachment_url'] = str_starts_with($arr['attachment'], 'http') || str_starts_with($arr['attachment'], '/storage')
                ? $arr['attachment']
                : Storage::url($arr['attachment']);
        } else {
            $arr['attachment_url'] = null;
        }
        return $arr;
    }

    /**
     * Show the conversation with a specific supplier.
     */
    public function conversation(Supplier $supplier)
    {
        $messages = SupplierMessage::where('supplier_id', $supplier->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn ($m) => $this->presentMessage($m));

        $requests = SupplierRequest::where('supplier_id', $supplier->id)
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->get();

        $registration = VendorRegistration::where('supplier_id', $supplier->id)
            ->orWhere('email', $supplier->email)
            ->latest()
            ->first();

        return Inertia::render('Dashboard/ECO/EcoConversation', [
            'supplier' => array_merge(
                $supplier->toArray(),
                ['approval_status' => $registration?->status ?? $supplier->status ?? 'pending']
            ),
            'messages' => $messages,
            'requests' => $requests,
            'registration' => $registration ? $registration->load('requirements') : null,
        ]);
    }

    /**
     * Real-time poll feed: returns messages after a given id plus the
     * current request list. Polled by the frontend every few seconds so
     * both sides see new messages without a page reload.
     */
    public function feed(Request $request, Supplier $supplier)
    {
        $afterId = (int) $request->query('after', 0);

        $messages = SupplierMessage::where('supplier_id', $supplier->id)
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('id', 'asc')
            ->limit(200)
            ->get()
            ->map(fn ($m) => $this->presentMessage($m));

        $requests = SupplierRequest::where('supplier_id', $supplier->id)
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'messages' => $messages,
            'requests' => $requests,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Vendor must be approved in SCM before ECO can message/meet/request.
     */
    protected function ensureApproved(Supplier $supplier): ?VendorRegistration
    {
        $registration = VendorRegistration::where('supplier_id', $supplier->id)
            ->orWhere('email', $supplier->email)
            ->latest()
            ->first();

        $status = $registration?->status ?? $supplier->status ?? 'pending';

        return $status === 'approved' ? $registration : null;
    }

    /**
     * Send a new message to the supplier.
     * Returns JSON for fetch() callers, redirect for Inertia posts.
     */
    public function sendMessage(Request $request, Supplier $supplier)
    {
        if (!$this->ensureApproved($supplier)) {
            $msg = 'Vendor is not approved yet. Approval is managed in SCM · Vendors.';
            return $request->wantsJson()
                ? response()->json(['message' => $msg], 422)
                : back()->withErrors(['error' => $msg]);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png,zip|max:10240',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('supplier-messages', 'public');
        }

        $created = SupplierMessage::create([
            'supplier_id' => $supplier->id,
            'sender_type' => 'eco',
            'sender_id' => auth()->id(),
            'message' => $validated['message'],
            'attachment' => $attachmentPath,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => $this->presentMessage($created)], 201);
        }

        return back()->with('success', 'Message sent.');
    }

    /**
     * Schedule a meeting with the supplier.
     */
    public function setMeeting(Request $request, Supplier $supplier)
    {
        if (!$this->ensureApproved($supplier)) {
            $msg = 'Vendor is not approved yet. Approval is managed in SCM · Vendors.';
            return $request->wantsJson()
                ? response()->json(['message' => $msg], 422)
                : back()->withErrors(['error' => $msg]);
        }

        $data = $request->validate([
            'scheduled_at' => 'required|date',
            'location' => 'required|string|max:255',
            'type' => 'required|string|max:50',
        ]);

        $created = SupplierMessage::create([
            'supplier_id' => $supplier->id,
            'sender_type' => 'eco',
            'sender_id' => auth()->id(),
            'message' => "Meeting scheduled: {$data['type']} at {$data['location']} on {$data['scheduled_at']}",
            'meeting_data' => $data,
            'is_system_event' => true,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => $this->presentMessage($created)], 201);
        }

        return back()->with('success', 'Meeting invite sent.');
    }

    /**
     * Check supplier's credit status (safe default — ledger is per-client).
     */
    public function creditCheck(Supplier $supplier)
    {
        try {
            if (Schema::hasColumn('credit_accounts', 'supplier_id')) {
                $credit = CreditAccount::where('supplier_id', $supplier->id)->first();
                $outstanding = $credit ? $credit->outstanding_balance : 0;
                $isGood = $credit ? $credit->is_good_payer : true;
            } else {
                $outstanding = 0;
                $isGood = true;
            }
        } catch (\Throwable $e) {
            $outstanding = 0;
            $isGood = true;
        }

        return response()->json([
            'outstanding' => $outstanding,
            'is_good_payer' => $isGood,
        ]);
    }

    /**
     * Send a quotation / purchase request to the supplier.
     */
    public function sendRequest(Request $request, Supplier $supplier)
    {
        if (!$this->ensureApproved($supplier)) {
            return back()->withErrors(['error' => 'Vendor is not approved yet. Approval is managed in SCM · Vendors.']);
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.material_name' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.specs' => 'nullable|string',
            'delivery_date' => 'required|date',
            'payment_terms' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $requestNumber = 'RQ-'.date('Ymd').'-'.str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);

        DB::beginTransaction();
        try {
            $supplierRequest = SupplierRequest::create([
                'request_number' => $requestNumber,
                'supplier_id' => $supplier->id,
                'delivery_date' => $validated['delivery_date'],
                'payment_terms' => $validated['payment_terms'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending',
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                SupplierRequestItem::create([
                    'supplier_request_id' => $supplierRequest->id,
                    'material_name' => $item['material_name'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'unit_price' => $item['unit_price'] ?? 0,
                    'specs' => $item['specs'] ?? null,
                ]);
            }

            SupplierMessage::create([
                'supplier_id' => $supplier->id,
                'sender_type' => 'eco',
                'sender_id' => auth()->id(),
                'message' => "Request {$requestNumber} has been sent. Please provide your quotation.",
                'is_system_event' => true,
            ]);

            DB::commit();

            if ($request->wantsJson()) {
                return response()->json([
                    'request' => $supplierRequest->load('items'),
                ], 201);
            }

            return back()->with('success', "Request {$requestNumber} sent to supplier.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to send request: '.$e->getMessage()]);
        }
    }
}
