<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Hrm\BillingStatement;
use App\Support\BillingCalc;
use App\Support\StatementExportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Agency portal: Issued + Paid statements only (Draft hidden, read-only).
 * Breakdown + PDF/Excel export. The agency can never edit or pay.
 */
class BillingPortalController extends Controller
{
    private function agencyId(Request $request): int
    {
        return (int) $request->user('agency')->agency_id;
    }

    public function index(Request $request)
    {
        $statements = BillingStatement::where('agency_id', $this->agencyId($request))
            ->whereIn('status', [BillingStatement::STATUS_ISSUED, BillingStatement::STATUS_PAID])
            ->orderByDesc('id')->paginate(15)->withQueryString();

        return Inertia::render('Agency/Billing/Index', [
            'statements' => collect($statements->items())->map(fn ($s) => [
                'id' => $s->id,
                'code' => $s->code,
                'period' => BillingCalc::periodLabel($s->period_start->toDateString(), $s->period_end->toDateString()),
                'labor_cost' => $s->labor_cost,
                'service_fee' => $s->service_fee,
                'total' => $s->total,
                'status' => $s->status,
                'paid_at' => optional($s->paid_at)->toDateString(),
                'payment_reference' => $s->payment_reference,
            ])->values()->all(),
            'pagination' => [
                'current_page' => $statements->currentPage(),
                'last_page' => $statements->lastPage(),
                'per_page' => $statements->perPage(),
                'total' => $statements->total(),
            ],
        ]);
    }

    public function show(Request $request, BillingStatement $statement)
    {
        abort_unless((int) $statement->agency_id === $this->agencyId($request), 404);
        abort_unless(in_array($statement->status, [BillingStatement::STATUS_ISSUED, BillingStatement::STATUS_PAID], true), 404);

        $statement->loadMissing('agency');
        $calc = BillingCalc::forStatement($statement);

        return Inertia::render('Agency/Billing/Show', [
            'statement' => [
                'id' => $statement->id,
                'code' => $statement->code,
                'period' => BillingCalc::periodLabel($statement->period_start->toDateString(), $statement->period_end->toDateString()),
                'status' => $statement->status,
                'service_fee_type' => $statement->agency?->service_fee_type,
                'service_fee_value' => $statement->agency?->service_fee_value,
                'labor_cost' => $statement->labor_cost,
                'service_fee' => $statement->service_fee,
                'total' => $statement->total,
                'total_hours' => array_sum(array_column($calc['lines'], 'hours')),
                'total_days' => array_sum(array_column($calc['lines'], 'days')),
                'lines' => $calc['lines'],
                'record_count' => $calc['count'],
                'paid_at' => optional($statement->paid_at)->toDateString(),
                'payment_reference' => $statement->payment_reference,
            ],
        ]);
    }

    public function exportPdf(Request $request, BillingStatement $statement)
    {
        abort_unless((int) $statement->agency_id === $this->agencyId($request), 404);
        abort_unless(in_array($statement->status, [BillingStatement::STATUS_ISSUED, BillingStatement::STATUS_PAID], true), 404);

        return StatementExportService::pdf($statement);
    }

    public function exportExcel(Request $request, BillingStatement $statement)
    {
        abort_unless((int) $statement->agency_id === $this->agencyId($request), 404);
        abort_unless(in_array($statement->status, [BillingStatement::STATUS_ISSUED, BillingStatement::STATUS_PAID], true), 404);

        return StatementExportService::excel($statement);
    }
}
