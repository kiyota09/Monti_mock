<?php

namespace App\Http\Controllers\Ceo;

use App\Http\Controllers\Controller;
use App\Models\Ceo\ExecutiveGoal;
use App\Models\Crm\CrmLead;
use App\Models\Hrm\Payroll;
use App\Models\Man\Fabric;
use App\Models\Man\Package;
use App\Models\Man\SafetyIncident;
use App\Models\Ord\PurchaseOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CeoGoalController extends Controller
{
    /**
     * Metric catalog: each key maps to an actuals resolver for a month.
     * lower_is_better flips progress semantics (reject rate, cost, incidents).
     */
    public const METRICS = [
        'output' => ['label' => 'Fabrics Produced', 'unit' => 'pcs', 'lower' => false],
        'reject_rate' => ['label' => 'Reject Rate', 'unit' => '%', 'lower' => true],
        'revenue' => ['label' => 'Approved Revenue', 'unit' => '₱', 'lower' => false],
        'payroll_cost' => ['label' => 'Payroll Cost', 'unit' => '₱', 'lower' => true],
        'packages' => ['label' => 'Packages Delivered', 'unit' => 'pcs', 'lower' => false],
        'leads_won' => ['label' => 'Leads Won', 'unit' => 'count', 'lower' => false],
        'incidents' => ['label' => 'Safety Incidents', 'unit' => 'count', 'lower' => true],
    ];

    public function index(Request $request)
    {
        $period = $request->get('period', now()->format('Y-m'));
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $goals = ExecutiveGoal::whereYear('period', $start->year)
            ->whereMonth('period', $start->month)
            ->latest()
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'department' => $g->department,
                'metric_key' => $g->metric_key,
                'metric_label' => $g->metric_label,
                'unit' => $g->unit,
                'target' => (float) $g->target,
                'lower_is_better' => (bool) $g->lower_is_better,
                'actual' => $this->actual($g->metric_key, $start, $end),
                'notes' => $g->notes,
            ]);

        return Inertia::render('Dashboard/CEO/Goals', [
            'period' => $period,
            'metrics' => self::METRICS,
            'goals' => $goals,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'department' => 'required|in:knitting,dyeing,finishing,maintenance,boiler,sales,finance,hr,company',
            'metric_key' => 'required|in:' . implode(',', array_keys(self::METRICS)),
            'target' => 'required|numeric|min:0',
            'period' => 'required|date_format:Y-m',
            'notes' => 'nullable|string',
        ]);

        $meta = self::METRICS[$validated['metric_key']];
        $start = Carbon::createFromFormat('Y-m', $validated['period'])->startOfMonth();

        ExecutiveGoal::updateOrCreate(
            [
                'department' => $validated['department'],
                'metric_key' => $validated['metric_key'],
                'period' => $start->toDateString(),
            ],
            [
                'metric_label' => $meta['label'],
                'unit' => $meta['unit'],
                'target' => $validated['target'],
                'lower_is_better' => $meta['lower'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]
        );

        return back()->with('message', 'Target saved successfully.');
    }

    public function destroy(ExecutiveGoal $goal)
    {
        $goal->delete();

        return back()->with('message', 'Target removed.');
    }

    protected function actual(string $metric, Carbon $start, Carbon $end): float
    {
        $between = fn ($q, $col = 'created_at') => $q->whereBetween($col, [$start, $end]);

        return match ($metric) {
            'output' => (float) $between(Fabric::query(), 'processed_at')->count(),
            'reject_rate' => $this->rejectRate($start, $end),
            'revenue' => (float) $between(PurchaseOrder::where('status', 'approved'))->sum('total_amount'),
            'payroll_cost' => (float) $between(Payroll::query())->sum('net_pay'),
            'packages' => (float) $between(Package::where('status', 'delivered'), 'packaged_at')->count(),
            'leads_won' => (float) $between(CrmLead::where('status', 'Closed-Won'))->count(),
            'incidents' => (float) $between(SafetyIncident::query(), 'processed_at')->count(),
            default => 0,
        };
    }

    protected function rejectRate(Carbon $start, Carbon $end): float
    {
        $total = Fabric::whereBetween('processed_at', [$start, $end])->count();
        if ($total === 0) {
            return 0;
        }
        $rejected = Fabric::where('status', 'rejected')
            ->whereBetween('updated_at', [$start, $end])->count();

        return round($rejected / $total * 100, 1);
    }
}
