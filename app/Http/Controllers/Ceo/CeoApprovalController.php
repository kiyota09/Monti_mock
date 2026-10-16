<?php

namespace App\Http\Controllers\Ceo;

use App\Http\Controllers\Controller;
use App\Models\Ceo\ExecutiveActionLog;
use App\Models\Crm\Client;
use App\Models\Hrm\Payroll;
use App\Models\Ord\PurchaseOrder;
use App\Models\Pro\Supplier;
use App\Models\Pro\VendorRegistration;
use App\Models\Pro\VendorRequirement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class CeoApprovalController extends Controller
{
    /**
     * Approvals Center: every pending executive decision in one queue.
     * This is the President's action arm under the overseer model —
     * approve/reject here instead of editing inside modules.
     */
    public function index()
    {
        $payrolls = Payroll::where('status', 'pending')
            ->latest()->take(50)->get();

        $vendors = VendorRegistration::where('status', 'pending')
            ->latest()->take(50)->get();

        $credits = PurchaseOrder::with('client')
            ->where('status', 'credit_review')
            ->latest()->take(50)->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'po_number' => $o->po_number,
                'client' => $o->client?->company_name ?? 'N/A',
                'total_amount' => $o->total_amount,
                'created_at' => $o->created_at,
            ]);

        // Supervisor seats vacant per manufacturing department (appointed
        // via MAN access control, which the President retains for governance).
        $departments = ['knitting', 'dyeing', 'finishing', 'maintenance', 'boiler'];
        $vacancies = [];
        foreach ($departments as $dept) {
            $occupied = DB::table('users')
                ->where('is_manufacturing_supervisor', true)
                ->where('supervisor_department', $dept)
                ->exists();
            if (! $occupied) {
                $vacancies[] = $dept;
            }
        }

        $recentDecisions = ExecutiveActionLog::with('actor:id,name')
            ->latest()->take(20)->get();

        return Inertia::render('Dashboard/CEO/Approvals', [
            'counts' => [
                'payrolls' => Payroll::where('status', 'pending')->count(),
                'vendors' => VendorRegistration::where('status', 'pending')->count(),
                'credits' => PurchaseOrder::where('status', 'credit_review')->count(),
                'vacancies' => count($vacancies),
            ],
            'payrolls' => $payrolls,
            'vendors' => $vendors,
            'credits' => $credits,
            'vacancies' => $vacancies,
            'recentDecisions' => $recentDecisions,
        ]);
    }

    protected function log(string $type, string $label, $subjectId, string $decision, ?string $reason): void
    {
        ExecutiveActionLog::create([
            'actor_id' => auth()->id(),
            'action_type' => $type,
            'subject_label' => $label,
            'subject_id' => $subjectId,
            'decision' => $decision,
            'reason' => $reason,
        ]);
    }

    // ─── Payroll ─────────────────────────────────────────────────────────────

    public function approvePayroll(Payroll $payroll)
    {
        if ($payroll->status !== 'pending') {
            return back()->withErrors(['error' => 'Only pending payroll can be approved.']);
        }

        $payroll->update(['status' => 'approved']);
        $this->log('payroll', "Payroll #{$payroll->id} (" . ($payroll->employee_name ?? 'unknown') . ", net {$payroll->net_pay})", $payroll->id, 'approved', null);

        return back()->with('message', 'Payroll approved.');
    }

    public function rejectPayroll(Request $request, Payroll $payroll)
    {
        $validated = $request->validate(['reason' => 'required|string|max:1000']);

        if ($payroll->status !== 'pending') {
            return back()->withErrors(['error' => 'Only pending payroll can be rejected.']);
        }

        $payroll->update(['status' => 'rejected']);
        $this->log('payroll', "Payroll #{$payroll->id} (" . ($payroll->employee_name ?? 'unknown') . ", net {$payroll->net_pay})", $payroll->id, 'rejected', $validated['reason']);

        return back()->with('message', 'Payroll rejected.');
    }

    // ─── Vendors (mirrors ScmVendorController + decision log) ────────────────

    public function approveVendor(VendorRegistration $registration)
    {
        if ($registration->status !== 'pending') {
            return back()->withErrors(['error' => 'Only pending registrations can be approved.']);
        }

        $supplier = Supplier::where('email', $registration->email)->first();

        if (! $supplier) {
            $supplier = Supplier::create([
                'business_name' => $registration->business_name,
                'representative_name' => $registration->representative_name,
                'address' => $registration->address,
                'phone_number' => $registration->phone_number,
                'email' => $registration->email,
                'password' => Hash::make('temporary-reset-required'),
            ]);
        }

        $registration->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
            'supplier_id' => $supplier->id,
        ]);

        $this->log('vendor', "Vendor {$registration->business_name} ({$registration->email})", $registration->id, 'approved', null);

        return back()->with('message', 'Vendor approved successfully.');
    }

    public function rejectVendor(Request $request, VendorRegistration $registration)
    {
        $validated = $request->validate(['rejection_reason' => 'required|string|max:1000']);

        if ($registration->status !== 'pending') {
            return back()->withErrors(['error' => 'Only pending registrations can be rejected.']);
        }

        $registration->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'rejected_at' => now(),
            'rejected_by' => auth()->id(),
        ]);

        $this->log('vendor', "Vendor {$registration->business_name} ({$registration->email})", $registration->id, 'rejected', $validated['rejection_reason']);

        return back()->with('message', 'Vendor registration rejected.');
    }

    // ─── Credit reviews (mirrors EcoCreditController + decision log) ─────────

    public function approveCredit(PurchaseOrder $order)
    {
        if ($order->status !== 'credit_review') {
            return back()->withErrors(['error' => 'Order is not pending credit review.']);
        }

        $order->update(['status' => 'approved']);
        $this->log('credit', "PO {$order->po_number} ({$order->total_amount})", $order->id, 'approved', null);

        return back()->with('message', 'Credit review approved.');
    }

    public function rejectCredit(Request $request, PurchaseOrder $order)
    {
        $validated = $request->validate(['reason' => 'required|string|max:1000']);

        if ($order->status !== 'credit_review') {
            return back()->withErrors(['error' => 'Order is not pending credit review.']);
        }

        $order->update(['status' => 'rejected']);
        $this->log('credit', "PO {$order->po_number} ({$order->total_amount})", $order->id, 'rejected', $validated['reason']);

        return back()->with('message', 'Credit review rejected.');
    }
}
