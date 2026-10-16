<?php

namespace App\Http\Controllers\Fin;

use App\Http\Controllers\Controller;
use App\Models\Core\Notification;
use App\Models\Core\User;
use App\Models\Fin\FinBill;
use App\Models\Fin\FinBudget;
use App\Models\Fin\FinExpense;
use App\Models\Fin\FinInvoice;
use App\Models\Scm\ScmPurchaseOrder;
use App\Services\Fin\FinanceService;
use App\Services\Fin\PayMongoService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FinDashboardController extends Controller
{
    public function __construct(protected FinanceService $finance, protected PayMongoService $gateway) {}

    protected function gatewayMode(): array
    {
        return ['mode' => $this->gateway->mode(), 'methods' => PayMongoService::METHOD_LABELS];
    }

    public function managerDashboard()
    {
        return Inertia::render('Dashboard/FIN/Manager/index', [
            'user' => auth()->user(),
            'stats' => $this->finance->stats(),
            'revenueTrend' => $this->finance->revenueTrend(),
            'cashFlow' => $this->finance->cashFlow(),
            'arAging' => $this->finance->arAging(),
            'apAging' => $this->finance->apAging(),
            'recentTransactions' => $this->finance->recentTransactions(),
            'budgets' => $this->finance->budgets(),
            'isDummy' => false,
        ]);
    }

    public function staffDashboard()
    {
        return Inertia::render('Dashboard/FIN/Employee/index', [
            'user' => auth()->user(),
            'stats' => $this->finance->stats(),
            'receivables' => $this->finance->invoiceRows(),
            'payables' => $this->finance->billRows(),
            'recentTransactions' => $this->finance->recentTransactions(),
            'isDummy' => false,
        ]);
    }

    public function receivables()
    {
        return Inertia::render('Dashboard/FIN/Manager/Receivables', [
            'receivables' => $this->finance->invoiceRows(),
            'arAging' => $this->finance->arAging(),
            'stats' => $this->finance->stats(),
            'gateway' => $this->gatewayMode(),
            'isDummy' => false,
        ]);
    }

    public function payables()
    {
        return Inertia::render('Dashboard/FIN/Manager/Payables', [
            'payables' => $this->finance->billRows(),
            'apAging' => $this->finance->apAging(),
            'stats' => $this->finance->stats(),
            'gateway' => $this->gatewayMode(),
            'isDummy' => false,
        ]);
    }

    public function expenses()
    {
        return Inertia::render('Dashboard/FIN/Manager/Expenses', [
            'expenses' => $this->finance->expenseRows(),
            'expenseCategories' => $this->finance->expenseCategories(),
            'stats' => $this->finance->stats(),
            'isDummy' => false,
        ]);
    }

    public function payroll()
    {
        return Inertia::render('Dashboard/FIN/Manager/Payroll', [
            'payroll' => $this->finance->payrollRows(),
            'stats' => $this->finance->stats(),
            'isDummy' => false,
        ]);
    }

    public function reports()
    {
        return Inertia::render('Dashboard/FIN/Manager/Reports', [
            'profitLoss' => $this->finance->profitLoss(),
            'revenueTrend' => $this->finance->revenueTrend(),
            'stats' => $this->finance->stats(),
            'isDummy' => false,
        ]);
    }

    public function recordInvoicePayment(Request $request, FinInvoice $invoice)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . max(0.01, $invoice->balance()),
            'paid_at' => 'nullable|date|before_or_equal:today',
            'method' => 'nullable|string|max:64',
            'reference' => 'nullable|string|max:128',
        ]);

        // Online methods (GCash / Maya / Card) go through the payment
        // gateway first — nothing is written to the ledger on decline.
        if ($this->gateway->isOnlineMethod($data['method'] ?? null)) {
            $charge = $this->gateway->charge((float) $data['amount'], $data['method'], [
                'invoice_no' => $invoice->invoice_no,
            ]);
            if (! $charge['success']) {
                return back()->withErrors(['error' => $charge['message'] ?? 'Online payment failed.']);
            }
            $data['reference'] = $data['reference'] ?: $charge['reference'];
        }

        $this->finance->recordInvoicePayment($invoice, $data, auth()->id());

        return back()->with('success', 'Payment of ₱' . number_format($data['amount'], 2) . ' recorded for ' . $invoice->invoice_no . '.');
    }

    public function recordBillPayment(Request $request, FinBill $bill)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . max(0.01, $bill->balance()),
            'paid_at' => 'nullable|date|before_or_equal:today',
            'method' => 'nullable|string|max:64',
            'reference' => 'nullable|string|max:128',
        ]);

        if ($this->gateway->isOnlineMethod($data['method'] ?? null)) {
            $charge = $this->gateway->charge((float) $data['amount'], $data['method'], [
                'bill_no' => $bill->bill_no,
            ]);
            if (! $charge['success']) {
                return back()->withErrors(['error' => $charge['message'] ?? 'Online payment failed.']);
            }
            $data['reference'] = $data['reference'] ?: $charge['reference'];
        }

        $this->finance->recordBillPayment($bill, $data, auth()->id());

        return back()->with('success', 'Payment of ₱' . number_format($data['amount'], 2) . ' recorded for ' . $bill->bill_no . '.');
    }

    public function storeExpense(Request $request)
    {
        $data = $request->validate([
            'expense_date' => 'required|date|before_or_equal:today',
            'category' => 'required|string|max:64',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'department' => 'nullable|string|max:64',
        ]);

        FinExpense::create([...$data, 'recorded_by' => auth()->id()]);

        return back()->with('success', 'Expense recorded.');
    }

    public function storeBill(Request $request)
    {
        $data = $request->validate([
            'supplier_name' => 'required|string|max:255',
            'category' => 'nullable|string|max:64',
            'amount' => 'required|numeric|min:0.01',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $bill = FinBill::create([
            ...$data,
            'bill_no' => 'BILL-' . now()->format('Ymd') . '-' . strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 6)),
            'category' => $data['category'] ?? 'Materials',
            'status' => 'unpaid',
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Bill ' . $bill->bill_no . ' recorded.');
    }

    public function storeBudget(Request $request)
    {
        $data = $request->validate([
            'department' => 'required|string|max:64',
            'period' => 'nullable|date_format:Y-m',
            'allocated' => 'required|numeric|min:0',
        ]);

        FinBudget::updateOrCreate(
            [
                'department' => $data['department'],
                'period' => $data['period'] ?? now()->format('Y-m'),
            ],
            ['allocated' => $data['allocated'], 'created_by' => auth()->id()]
        );

        return back()->with('success', 'Budget saved.');
    }

    /**
     * Purchase orders awaiting finance approval (accepted PRO quotations).
     */
    public function poApprovals()
    {
        $pending = ScmPurchaseOrder::with('items')
            ->where('finance_status', 'pending')
            ->latest()
            ->get()
            ->map(fn ($po) => $this->poApprovalRow($po));

        $decided = ScmPurchaseOrder::with('items')
            ->whereIn('finance_status', ['approved', 'declined'])
            ->latest('finance_decided_at')
            ->take(20)
            ->get()
            ->map(fn ($po) => $this->poApprovalRow($po));

        return Inertia::render('Dashboard/FIN/Manager/Approvals', [
            'pending' => $pending,
            'decided' => $decided,
            'isDummy' => false,
        ]);
    }

    protected function poApprovalRow(ScmPurchaseOrder $po): array
    {
        return [
            'id' => $po->id,
            'po_number' => $po->po_number,
            'supplier_name' => $po->supplier_name,
            'rfq_ref' => $po->rfq_ref,
            'status' => $po->status,
            'finance_status' => $po->finance_status ?? 'pending',
            'finance_remarks' => $po->finance_remarks,
            'finance_decided_at' => $po->finance_decided_at,
            'grand_total' => round((float) $po->grand_total, 2),
            'issued_date' => $po->issued_date?->format('Y-m-d'),
            'items' => $po->items->map(fn ($item) => [
                'material_name' => $item->material_name,
                'qty' => $item->qty,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'total' => $item->total,
            ])->values(),
        ];
    }

    public function approvePo(ScmPurchaseOrder $po)
    {
        if (($po->finance_status ?? 'pending') !== 'pending') {
            return back()->withErrors(['error' => 'This PO has already been decided by finance.']);
        }

        $po->update([
            'finance_status' => 'approved',
            'finance_decided_by' => auth()->id(),
            'finance_decided_at' => now(),
            'finance_remarks' => null,
        ]);

        $this->notifyPro($po, true);

        return back()->with('success', "PO {$po->po_number} approved — PRO can now send it.");
    }

    public function declinePo(Request $request, ScmPurchaseOrder $po)
    {
        $data = $request->validate(['remarks' => 'required|string|max:500']);

        if (($po->finance_status ?? 'pending') !== 'pending') {
            return back()->withErrors(['error' => 'This PO has already been decided by finance.']);
        }

        $po->update([
            'finance_status' => 'declined',
            'finance_decided_by' => auth()->id(),
            'finance_decided_at' => now(),
            'finance_remarks' => $data['remarks'],
        ]);

        $this->notifyPro($po, false);

        return back()->with('success', "PO {$po->po_number} declined — PRO has been notified.");
    }

    protected function notifyPro(ScmPurchaseOrder $po, bool $approved): void
    {
        $decision = $approved ? 'approved' : 'declined';
        $proUsers = User::where('role', 'PRO')->where('is_active', true)->get(['id']);
        foreach ($proUsers as $pro) {
            Notification::notify(
                (int) $pro->id,
                'finance',
                "Finance {$decision} PO {$po->po_number}",
                $approved
                    ? "{$po->supplier_name} · ₱" . number_format((float) $po->grand_total, 2) . ' — the Send PO button is now available in Receipts.'
                    : "{$po->supplier_name} · ₱" . number_format((float) $po->grand_total, 2) . " — reason: {$po->finance_remarks}. Return it to Material Requests for a fresh round.",
                'pro.manager.receipt',
                auth()->id()
            );
        }
    }
}
