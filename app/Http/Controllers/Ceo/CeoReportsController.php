<?php

namespace App\Http\Controllers\Ceo;

use App\Http\Controllers\Controller;
use App\Models\Ceo\ExecutiveActionLog;
use App\Models\Crm\Client;
use App\Models\Crm\CrmLead;
use App\Models\Inv\Product;
use App\Models\Hrm\Payroll;
use App\Models\Man\Fabric;
use App\Models\Man\Machine;
use App\Models\Man\Package;
use App\Models\Ord\PurchaseOrder;
use App\Models\Ord\SalesOrder;
use App\Models\Core\User;
use App\Models\Scm\PurchaseInvoice;
use App\Models\Scm\ScmPurchaseOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CeoReportsController extends Controller
{
    /**
     * Executive reports: cross-module KPIs + monthly trends.
     * Read-only aggregates — no module or record is modified here.
     */
    public function index(Request $request)
    {
        $months = (int) $request->get('months', 6);
        $months = in_array($months, [3, 6, 12], true) ? $months : 6;

        return Inertia::render('Dashboard/CEO/Reports', [
            'months' => $months,
            'kpis' => $this->snapshot(),
            'trends' => $this->trends($months),
        ]);
    }

    /**
     * Board pack: print-ready monthly bundle (KPIs + 12-month trends +
     * executive decisions). Rendered as a normal page with print CSS.
     */
    public function boardPack()
    {
        return Inertia::render('Dashboard/CEO/BoardPack', [
            'generatedAt' => now()->format('F d, Y g:i A'),
            'kpis' => $this->snapshot(),
            'trends' => $this->trends(12),
            'decisions' => ExecutiveActionLog::with('actor:id,name')
                ->latest()->take(30)->get(),
        ]);
    }

    protected function snapshot(): array
    {
        $fabricsTotal = Fabric::count();
        $rejected = Fabric::where('status', 'rejected')->count();

        return [
            'revenue_approved' => PurchaseOrder::where('status', 'approved')->sum('total_amount'),
            'orders_open' => PurchaseOrder::whereNotIn('status', ['approved', 'cancelled', 'rejected'])->count(),
            'jobs_in_production' => SalesOrder::where('status', 'in_production')->count(),
            'procurement_spend' => ScmPurchaseOrder::sum('grand_total'),
            'invoices_unpaid' => PurchaseInvoice::where('status', '!=', 'paid')->sum('amount'),
            'payroll_cost' => Payroll::sum('net_pay'),
            'fabrics_total' => $fabricsTotal,
            'reject_rate' => $fabricsTotal > 0 ? round($rejected / $fabricsTotal * 100, 1) : 0,
            'packages_pending' => Package::where('status', 'pending')->count(),
            'packages_delivered' => Package::where('status', 'delivered')->count(),
            'leads_open' => CrmLead::whereIn('status', ['Inquiry', 'Negotiation'])->count(),
            'leads_won' => CrmLead::where('status', 'Closed-Won')->count(),
            'clients' => Client::count(),
            'products' => Product::count(),
            'headcount' => User::where('is_active', true)->count(),
            'machines_down' => Machine::where('status', '!=', 'available')->count(),
        ];
    }

    protected function trends(int $months): array
    {
        $labels = $revenue = $spend = $output = $rejects = $payroll = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $labels[] = $date->format('M Y');

            $revenue[] = (float) PurchaseOrder::where('status', 'approved')
                ->whereYear('created_at', $date->year)->whereMonth('created_at', $date->month)
                ->sum('total_amount');
            $spend[] = (float) ScmPurchaseOrder::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)->sum('grand_total');
            $output[] = Fabric::whereYear('processed_at', $date->year)
                ->whereMonth('processed_at', $date->month)->count();
            $rejects[] = Fabric::where('status', 'rejected')
                ->whereYear('updated_at', $date->year)->whereMonth('updated_at', $date->month)->count();
            $payroll[] = (float) Payroll::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)->sum('net_pay');
        }

        return compact('labels', 'revenue', 'spend', 'output', 'rejects', 'payroll');
    }
}
