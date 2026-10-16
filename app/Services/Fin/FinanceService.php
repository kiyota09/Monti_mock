<?php

namespace App\Services\Fin;

use App\Models\Core\User;
use App\Models\Fin\FinBill;
use App\Models\Fin\FinBillPayment;
use App\Models\Fin\FinBudget;
use App\Models\Fin\FinExpense;
use App\Models\Fin\FinInvoice;
use App\Models\Fin\FinInvoicePayment;
use App\Models\Hrm\Payroll;
use App\Models\Ord\SalesOrder;
use App\Models\Scm\ProcurementPayment;
use App\Models\Scm\PurchaseInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Finance module read model. Every figure on every FIN screen is
 * computed here from live tables — no hardcoded numbers anywhere.
 *
 * Sources:
 * - Receivables: fin_invoices, lazily synced 1:1 from sales orders.
 * - Payables: fin_bills, recorded by FIN staff (supplier side).
 * - Expenses / budgets: fin_expenses / fin_budgets, recorded by FIN.
 * - Payroll: payrolls (HRM-owned runs), grouped by department.
 */
class FinanceService
{
    /**
     * Ensure every sales order has a matching receivable invoice.
     * Orders already marked paid (legacy direct postings) get a balancing
     * collection entry so the ledger reflects the real collection event.
     */
    public function syncInvoices(): void
    {
        $orders = SalesOrder::with('client')->get();

        foreach ($orders as $so) {
            $invoice = FinInvoice::firstOrCreate(
                ['sales_order_id' => $so->id],
                [
                    'invoice_no' => 'INV-' . ltrim((string) $so->jo_number, 'JO-') ?: ('SO-' . $so->id),
                    'client_id' => $so->client_id,
                    'client_name' => $so->client?->company_name ?? 'N/A',
                    'amount' => (float) $so->total_amount,
                    'due_date' => $so->expected_ship_date
                        ?? ($so->created_at ? $so->created_at->copy()->addDays(30)->toDateString() : today()->addDays(30)->toDateString()),
                    'status' => 'unpaid',
                    'notes' => 'Synced from job order ' . ($so->jo_number ?? $so->id),
                ]
            );

            // Keep mutable order facts in sync without touching payments.
            $invoice->fill([
                'amount' => (float) $so->total_amount,
                'client_name' => $so->client?->company_name ?? $invoice->client_name,
            ]);
            $invoice->save();

            if ($so->payment_status === 'paid' && $invoice->paidTotal() < (float) $invoice->amount) {
                FinInvoicePayment::create([
                    'fin_invoice_id' => $invoice->id,
                    'amount' => round((float) $invoice->amount - $invoice->paidTotal(), 2),
                    'paid_at' => ($so->updated_at ?? now())->toDateString(),
                    'method' => 'Order payment',
                    'reference' => 'Synced from order payment status',
                    'recorded_by' => $so->confirmed_by,
                ]);
            }

            $invoice->refreshStatus();
        }
    }

    public function invoiceRows()
    {
        $this->syncInvoices();

        return FinInvoice::withSum('payments as paid_sum', 'amount')
            ->latest()
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_no' => $inv->invoice_no,
                'client' => $inv->client_name,
                'amount' => round((float) $inv->amount, 2),
                'paid' => round((float) ($inv->paid_sum ?? 0), 2),
                'due_date' => $inv->due_date?->format('Y-m-d'),
                'status' => $inv->displayStatus(),
            ])
            ->values();
    }

    /**
     * Ensure every supplier purchase invoice sent from the supplier
     * portal has a matching finance bill (payables book of record).
     */
    public function syncBills(): void
    {
        $invoices = PurchaseInvoice::all();

        foreach ($invoices as $inv) {
            $bill = FinBill::firstOrCreate(
                ['bill_no' => $inv->invoice_number],
                [
                    'supplier_id' => $inv->supplier_id,
                    'purchase_invoice_id' => $inv->id,
                    'supplier_name' => $inv->supplier_name ?? 'N/A',
                    'category' => 'Materials',
                    'amount' => (float) $inv->amount,
                    'due_date' => $inv->due_date,
                    'status' => 'unpaid',
                    'notes' => 'Synced from supplier invoice' . ($inv->po_id ? ' (PO #' . $inv->po_id . ')' : ''),
                ]
            );

            $bill->fill([
                'purchase_invoice_id' => $bill->purchase_invoice_id ?? $inv->id,
                'amount' => (float) $inv->amount,
                'supplier_name' => $inv->supplier_name ?? $bill->supplier_name,
            ]);
            $bill->save();
            $bill->refreshStatus();
        }
    }

    /**
     * Total paid against a bill across BOTH ledgers: FIN bill payments
     * plus cleared procurement payments recorded in PRO receipts.
     */
    public function billPaidTotal(FinBill $bill, ?float $finSum = null): float
    {
        $total = (float) ($finSum ?? $bill->payments()->sum('amount'));
        if ($bill->purchase_invoice_id) {
            $total += (float) ProcurementPayment::where('invoice_id', $bill->purchase_invoice_id)
                ->where('status', 'cleared')
                ->sum('amount');
        }

        return $total;
    }

    public function billRows()
    {
        $this->syncBills();

        return FinBill::withSum('payments as paid_sum', 'amount')
            ->latest()
            ->get()
            ->map(function ($bill) {
                $paid = round($this->billPaidTotal($bill, (float) ($bill->paid_sum ?? 0)), 2);
                $balance = round((float) $bill->amount - $paid, 2);
                $status = $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
                if ($balance > 0 && $bill->due_date && $bill->due_date->isPast()) {
                    $status = 'overdue';
                }

                return [
                    'id' => $bill->id,
                    'bill_no' => $bill->bill_no,
                    'supplier' => $bill->supplier_name,
                    'category' => $bill->category,
                    'amount' => round((float) $bill->amount, 2),
                    'paid' => $paid,
                    'due_date' => $bill->due_date?->format('Y-m-d'),
                    'status' => $status,
                ];
            })
            ->values();
    }

    public function expenseRows()
    {
        return FinExpense::latest('expense_date')
            ->latest('id')
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'date' => $e->expense_date?->format('Y-m-d'),
                'category' => $e->category,
                'description' => $e->description,
                'amount' => round((float) $e->amount, 2),
                'department' => $e->department,
            ])
            ->values();
    }

    public function expenseCategories()
    {
        return FinExpense::selectRaw('category, SUM(amount) as amount')
            ->groupBy('category')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($r) => ['category' => $r->category, 'amount' => round((float) $r->amount, 2)])
            ->values();
    }

    /**
     * Payroll runs grouped by department (HRM payrolls + user department
     * lookup; unassigned staff fall under Operations). Only live runs
     * (pending/approved); rejected runs are excluded.
     */
    public function payrollRows()
    {
        $deptOf = User::whereNotNull('employee_id')
            ->pluck('department', 'employee_id');

        $runs = Payroll::whereIn('status', ['pending', 'approved'])->get();

        $groups = [];
        foreach ($runs as $run) {
            $dept = trim((string) ($deptOf[$run->employee_id] ?? '')) !== ''
                ? trim((string) $deptOf[$run->employee_id])
                : 'Operations';
            $deductions = (float) $run->sss_deduction + (float) $run->philhealth_deduction
                + (float) $run->pagibig_deduction + (float) $run->tax_withheld
                + (float) $run->sss_loan + (float) $run->pf_loan
                + (float) $run->late_total_deduction;
            if (! isset($groups[$dept])) {
                $groups[$dept] = ['department' => $dept, 'headcount' => 0, 'gross' => 0, 'deductions' => 0, 'net' => 0, 'paid' => 0, 'pending' => 0];
            }
            $groups[$dept]['headcount']++;
            $groups[$dept]['gross'] += (float) $run->gross_pay;
            $groups[$dept]['deductions'] += $deductions;
            $groups[$dept]['net'] += (float) $run->net_pay;
            if ($run->status === 'approved') {
                $groups[$dept]['paid']++;
            } else {
                $groups[$dept]['pending']++;
            }
        }

        return collect($groups)
            ->map(function ($g) {
                $g['gross'] = round($g['gross'], 2);
                $g['deductions'] = round($g['deductions'], 2);
                $g['net'] = round($g['net'], 2);
                // Department is fully paid only when nothing is pending.
                $g['status'] = $g['pending'] > 0 ? 'pending' : 'paid';
                unset($g['paid'], $g['pending']);

                return $g;
            })
            ->sortByDesc('gross')
            ->values();
    }

    public function stats(): array
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $yearStart = $now->copy()->startOfYear()->toDateString();
        $today = $now->toDateString();

        $collectedYtd = (float) FinInvoicePayment::whereDate('paid_at', '>=', $yearStart)->sum('amount');
        $collectedMonth = (float) FinInvoicePayment::whereDate('paid_at', '>=', $monthStart)->sum('amount');

        $outstandingAR = 0.0;
        $overdueAR = 0.0;
        foreach (FinInvoice::withSum('payments as paid_sum', 'amount')->get() as $inv) {
            $balance = round((float) $inv->amount - (float) ($inv->paid_sum ?? 0), 2);
            if ($balance <= 0) {
                continue;
            }
            $outstandingAR += $balance;
            if ($inv->due_date && $inv->due_date->toDateString() < $today) {
                $overdueAR += $balance;
            }
        }

        $outstandingAP = 0.0;
        $overdueAP = 0.0;
        foreach ($this->billRows() as $row) {
            $balance = round((float) ($row['amount'] ?? 0) - (float) ($row['paid'] ?? 0), 2);
            if ($balance <= 0) {
                continue;
            }
            $outstandingAP += $balance;
            if (($row['status'] ?? '') === 'overdue') {
                $overdueAP += $balance;
            }
        }

        $paidBillsMonth = (float) FinBillPayment::whereDate('paid_at', '>=', $monthStart)->sum('amount')
            + (float) ProcurementPayment::where('status', 'cleared')->whereDate('paid_date', '>=', $monthStart)->sum('amount');
        $expensesMonth = (float) FinExpense::whereDate('expense_date', '>=', $monthStart)->sum('amount');
        $payrollMonth = (float) Payroll::whereIn('status', ['pending', 'approved'])
            ->whereDate('created_at', '>=', $monthStart)
            ->sum('gross_pay');
        $expenseMonth = round($paidBillsMonth + $expensesMonth + $payrollMonth, 2);

        $paidBillsAll = (float) FinBillPayment::sum('amount')
            + (float) ProcurementPayment::where('status', 'cleared')->sum('amount');
        $expensesAll = (float) FinExpense::sum('amount');
        $payrollAll = (float) Payroll::whereIn('status', ['pending', 'approved'])->sum('gross_pay');
        $cashOnHand = round($collectedYtd - $paidBillsAll - $expensesAll - $payrollAll, 2);

        return [
            'revenueYtd' => round($collectedYtd, 2),
            'revenueMonth' => round($collectedMonth, 2),
            'outstandingAR' => round($outstandingAR, 2),
            'overdueAR' => round($overdueAR, 2),
            'outstandingAP' => round($outstandingAP, 2),
            'overdueAP' => round($overdueAP, 2),
            'cashOnHand' => $cashOnHand,
            'netProfitMonth' => round($collectedMonth - $expenseMonth, 2),
            'expenseMonth' => $expenseMonth,
            'payrollMonth' => round($payrollMonth, 2),
        ];
    }

    public function revenueTrend(int $months = 6): array
    {
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->copy()->subMonths($i);
            $start = $month->copy()->startOfMonth()->toDateString();
            $end = $month->copy()->endOfMonth()->toDateString();

            $revenue = (float) FinInvoicePayment::whereBetween('paid_at', [$start, $end])->sum('amount');
            $expenses = (float) FinExpense::whereBetween('expense_date', [$start, $end])->sum('amount')
                + (float) FinBillPayment::whereBetween('paid_at', [$start, $end])->sum('amount')
                + (float) ProcurementPayment::where('status', 'cleared')->whereBetween('paid_date', [$start, $end])->sum('amount')
                + (float) Payroll::whereIn('status', ['pending', 'approved'])
                    ->whereBetween('created_at', [$start, $end])
                    ->sum('gross_pay');

            $out[] = [
                'month' => $month->format('M'),
                'revenue' => round($revenue, 2),
                'expenses' => round($expenses, 2),
            ];
        }

        return $out;
    }

    public function cashFlow(): array
    {
        $monthStart = now()->copy()->startOfMonth()->toDateString();

        $collections = (float) FinInvoicePayment::whereDate('paid_at', '>=', $monthStart)->sum('amount');
        $supplierPayments = (float) FinBillPayment::whereDate('paid_at', '>=', $monthStart)->sum('amount')
            + (float) ProcurementPayment::where('status', 'cleared')->whereDate('paid_date', '>=', $monthStart)->sum('amount');
        $payroll = (float) Payroll::whereIn('status', ['pending', 'approved'])
            ->whereDate('created_at', '>=', $monthStart)
            ->sum('gross_pay');
        $operating = (float) FinExpense::whereDate('expense_date', '>=', $monthStart)->sum('amount');

        return [
            ['label' => 'Client collections', 'inflow' => round($collections, 2), 'outflow' => 0],
            ['label' => 'Supplier payments', 'inflow' => 0, 'outflow' => round($supplierPayments, 2)],
            ['label' => 'Payroll payout', 'inflow' => 0, 'outflow' => round($payroll, 2)],
            ['label' => 'Operating expenses', 'inflow' => 0, 'outflow' => round($operating, 2)],
        ];
    }

    protected function aging(array $rows, string $dateKey): array
    {
        $buckets = [
            'Current' => ['amount' => 0.0, 'count' => 0, 'min' => null, 'max' => 0],
            '1–30 days' => ['amount' => 0.0, 'count' => 0, 'min' => 1, 'max' => 30],
            '31–60 days' => ['amount' => 0.0, 'count' => 0, 'min' => 31, 'max' => 60],
            '60+ days' => ['amount' => 0.0, 'count' => 0, 'min' => 61, 'max' => null],
        ];

        foreach ($rows as $row) {
            $balance = (float) ($row['amount'] ?? 0) - (float) ($row['paid'] ?? 0);
            if ($balance <= 0) {
                continue;
            }
            $days = 0;
            if (! empty($row[$dateKey])) {
                $due = Carbon::parse($row[$dateKey])->startOfDay();
                // Days past due (absolute); future/not-yet-due stays Current.
                $days = $due->isPast() ? (int) abs(now()->startOfDay()->diffInDays($due)) : 0;
            }
            foreach ($buckets as $label => $range) {
                $inMin = $range['min'] === null || $days >= $range['min'];
                $inMax = $range['max'] === null || $days <= $range['max'];
                if ($inMin && $inMax) {
                    $buckets[$label]['amount'] += $balance;
                    $buckets[$label]['count']++;
                    break;
                }
            }
        }

        return collect($buckets)
            ->map(fn ($b, $label) => ['bucket' => $label, 'amount' => round($b['amount'], 2), 'count' => $b['count']])
            ->values()
            ->all();
    }

    public function arAging(): array
    {
        return $this->aging($this->invoiceRows()->all(), 'due_date');
    }

    public function apAging(): array
    {
        return $this->aging($this->billRows()->all(), 'due_date');
    }

    public function recentTransactions(int $limit = 8): array
    {
        $tx = [];

        foreach (FinInvoicePayment::with('invoice')->latest('paid_at')->latest('id')->take($limit)->get() as $p) {
            $tx[] = [
                'id' => 'in-' . $p->id,
                'date' => $p->paid_at?->format('Y-m-d'),
                'description' => 'Collection — ' . ($p->invoice?->client_name ?? '') . ' (' . ($p->invoice?->invoice_no ?? '') . ')',
                'type' => 'inflow',
                'amount' => round((float) $p->amount, 2),
                'at' => $p->paid_at?->format('Y-m-d') . ' ' . $p->id,
            ];
        }
        foreach (FinBillPayment::with('bill')->latest('paid_at')->latest('id')->take($limit)->get() as $p) {
            $tx[] = [
                'id' => 'bill-' . $p->id,
                'date' => $p->paid_at?->format('Y-m-d'),
                'description' => 'Vendor payment — ' . ($p->bill?->supplier_name ?? '') . ' (' . ($p->bill?->bill_no ?? '') . ')',
                'type' => 'outflow',
                'amount' => round((float) $p->amount, 2),
                'at' => $p->paid_at?->format('Y-m-d') . ' ' . $p->id,
            ];
        }
        foreach (ProcurementPayment::where('status', 'cleared')->latest('paid_date')->latest('id')->take($limit)->get() as $p) {
            $tx[] = [
                'id' => 'pro-' . $p->id,
                'date' => $p->paid_date,
                'description' => 'Vendor payment (PRO) — ' . ($p->supplier_name ?? '') . ' (' . ($p->invoice_number ?? '') . ')',
                'type' => 'outflow',
                'amount' => round((float) $p->amount, 2),
                'at' => $p->paid_date . ' ' . $p->id,
            ];
        }
        foreach (FinExpense::latest('expense_date')->latest('id')->take($limit)->get() as $e) {
            $tx[] = [
                'id' => 'exp-' . $e->id,
                'date' => $e->expense_date?->format('Y-m-d'),
                'description' => $e->description . ' — ' . $e->category,
                'type' => 'outflow',
                'amount' => round((float) $e->amount, 2),
                'at' => $e->expense_date?->format('Y-m-d') . ' ' . $e->id,
            ];
        }

        return collect($tx)->sortByDesc('at')->take($limit)->values()->map(fn ($t) => [
            'id' => $t['id'],
            'date' => $t['date'],
            'description' => $t['description'],
            'type' => $t['type'],
            'amount' => $t['amount'],
        ])->all();
    }

    public function profitLoss(): array
    {
        $yearStart = now()->copy()->startOfYear()->toDateString();

        $revenue = (float) FinInvoicePayment::whereDate('paid_at', '>=', $yearStart)->sum('amount');

        $cogsCategories = ['Raw materials', 'Dye chemicals'];
        $cogsExpenses = (float) FinExpense::whereDate('expense_date', '>=', $yearStart)
            ->whereIn('category', $cogsCategories)
            ->sum('amount');
        $materialBills = (float) FinBillPayment::whereDate('paid_at', '>=', $yearStart)
            ->whereHas('bill', fn ($q) => $q->where('category', 'Materials'))
            ->sum('amount')
            + (float) ProcurementPayment::where('status', 'cleared')->whereDate('paid_date', '>=', $yearStart)->sum('amount');
        $labor = (float) Payroll::whereIn('status', ['pending', 'approved'])
            ->whereDate('created_at', '>=', $yearStart)
            ->sum('gross_pay');
        $payrollExpenses = (float) FinExpense::whereDate('expense_date', '>=', $yearStart)
            ->where('category', 'Payroll')
            ->sum('amount');

        $opex = FinExpense::selectRaw('category, SUM(amount) as amount')
            ->whereDate('expense_date', '>=', $yearStart)
            ->whereNotIn('category', array_merge($cogsCategories, ['Payroll']))
            ->groupBy('category')
            ->orderByDesc('amount')
            ->get();

        $lines = [['line' => 'Sales revenue', 'amount' => round($revenue, 2), 'section' => 'income']];
        $lines[] = ['line' => 'Raw materials', 'amount' => -round($cogsExpenses + $materialBills, 2), 'section' => 'cogs'];
        $lines[] = ['line' => 'Direct labor', 'amount' => -round($labor + $payrollExpenses, 2), 'section' => 'cogs'];
        foreach ($opex as $row) {
            $lines[] = ['line' => $row->category, 'amount' => -round((float) $row->amount, 2), 'section' => 'opex'];
        }

        return $lines;
    }

    public function budgets(): array
    {
        $year = (int) now()->format('Y');
        $month = (int) now()->format('m');
        $period = now()->format('Y-m');

        return FinBudget::where('period', $period)
            ->orderBy('department')
            ->get()
            ->map(function ($b) use ($year, $month) {
                $spent = (float) FinExpense::where('department', $b->department)
                    ->whereYear('expense_date', $year)
                    ->whereMonth('expense_date', $month)
                    ->sum('amount');

                return [
                    'department' => $b->department,
                    'allocated' => round((float) $b->allocated, 2),
                    'spent' => round($spent, 2),
                ];
            })
            ->values()
            ->all();
    }

    public function recordInvoicePayment(FinInvoice $invoice, array $data, ?int $recordedBy): FinInvoicePayment
    {
        return DB::transaction(function () use ($invoice, $data, $recordedBy) {
            $payment = FinInvoicePayment::create([
                'fin_invoice_id' => $invoice->id,
                'amount' => round((float) $data['amount'], 2),
                'paid_at' => $data['paid_at'] ?? today()->toDateString(),
                'method' => $data['method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'recorded_by' => $recordedBy,
            ]);

            $invoice->refreshStatus();

            $so = SalesOrder::find($invoice->sales_order_id);
            if ($so && $invoice->balance() <= 0 && $so->payment_status !== 'paid') {
                $so->update(['payment_status' => 'paid']);
            }

            // Close the order-to-cash loop: a delivered job order whose
            // invoice is now fully paid is complete. Guarded lifecycle
            // transition (audit row included); never blocks the payment.
            if ($so && $invoice->balance() <= 0 && $so->status === 'delivered') {
                try {
                    app(\App\Services\Ord\OrderLifecycleService::class)
                        ->transitionSalesOrder($so, 'completed', null, 'Auto-completed on full payment of ' . $invoice->invoice_no);
                } catch (\InvalidArgumentException) {
                    // Leave the status untouched — payment is already recorded.
                }
            }

            return $payment;
        });
    }

    public function recordBillPayment(FinBill $bill, array $data, ?int $recordedBy): FinBillPayment
    {
        return DB::transaction(function () use ($bill, $data, $recordedBy) {
            $payment = FinBillPayment::create([
                'fin_bill_id' => $bill->id,
                'amount' => round((float) $data['amount'], 2),
                'paid_at' => $data['paid_at'] ?? today()->toDateString(),
                'method' => $data['method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'recorded_by' => $recordedBy,
            ]);

            $bill->refreshStatus();

            // Keep the linked supplier invoice truthful in PRO receipts:
            // combined paid across both ledgers drives its status.
            if ($bill->purchase_invoice_id) {
                $inv = PurchaseInvoice::find($bill->purchase_invoice_id);
                if ($inv && ! in_array($inv->status, ['paid', 'cancelled'], true)) {
                    $combined = $this->billPaidTotal($bill->fresh());
                    if ($combined >= (float) $inv->amount) {
                        $inv->update(['status' => 'paid']);
                    } elseif ($combined > 0) {
                        $inv->update(['status' => 'partial']);
                    }
                }
            }

            return $payment;
        });
    }
}
