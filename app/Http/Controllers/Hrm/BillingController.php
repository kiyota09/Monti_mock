<?php

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Hrm\Agency;
use App\Models\Hrm\AgencyActivity;
use App\Models\Hrm\Attendance;
use App\Models\Hrm\BillingStatement;
use App\Support\BillingCalc;
use App\Support\StatementExportService;
use App\Traits\HasPagePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Billing statements (HRM > Billing, Pages 14-16).
 *
 * Lifecycle: Draft → Issued → Paid, with Cancelled from Draft/Issued
 * (reason required). Issuing marks the statement's attendance as billed so
 * it can never be billed twice; cancelling an Issued statement releases
 * its attendance back to billable.
 */
class BillingController extends Controller
{
    use HasPagePermissions;

    /** Shown when a period has no eligible, unbilled attendance to bill. */
    protected const NO_ELIGIBLE_MESSAGE = 'No eligible, unbilled attendance records were found for the selected agency and period.';

    // ------------------------------------------------------------------
    // Page 14: Billing statements list
    // ------------------------------------------------------------------
    public function index(Request $request)
    {
        $q = BillingStatement::with('agency:id,code,name');

        if ($agencyId = $request->get('agency_id')) {
            $q->where('agency_id', $agencyId);
        }
        if ($from = $request->get('from')) {
            $q->whereDate('period_start', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $q->whereDate('period_end', '<=', $to);
        }
        if (($status = $request->get('status')) && $status !== 'All') {
            $q->where('status', $status);
        }

        $paginated = $q->orderByDesc('id')->paginate(15)->withQueryString();
        $rows = collect($paginated->items())->map(fn ($s) => $this->row($s))->values()->all();

        return Inertia::render('Dashboard/HRM_NEW/Billing/Index', [
            'statements' => $rows,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'agencies' => Agency::orderBy('name')->get(['id', 'code', 'name', 'status']),
            'filters' => $request->only(['agency_id', 'from', 'to', 'status']),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    // ------------------------------------------------------------------
    // Page 15: Generate statement (agency + period + live preview)
    // ------------------------------------------------------------------
    public function create(Request $request)
    {
        $agency = $request->get('agency_id') ? Agency::find($request->get('agency_id')) : null;
        $from = $request->get('from', today()->startOfMonth()->toDateString());
        $to = $request->get('to', today()->toDateString());

        $preview = null;
        if ($agency && $from && $to) {
            $preview = BillingCalc::preview($agency, $from, $to);
        }

        return Inertia::render('Dashboard/HRM_NEW/Billing/Form', [
            'statement' => null,
            'suggested_code' => self::nextCode(),
            'agencies' => Agency::orderBy('name')->get(['id', 'code', 'name', 'status'])
                ->filter(fn ($a) => $a->status !== Agency::STATUS_TERMINATED || ! $a->has_final_statement)
                ->values()->all(),
            'agency_id' => $agency?->id,
            'from' => $from,
            'to' => $to,
            'today' => today()->toDateString(),
            'preview' => $preview,
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function store(Request $request)
    {
        // Save as draft vs Issue statement are two submit buttons.
        $issue = $request->boolean('issue');
        $data = $this->validated($request);

        $statement = DB::transaction(function () use ($data, $issue) {
            // Lock the agency row so two concurrent requests for the same
            // agency cannot both pass the overlap check and double-bill.
            $agency = Agency::whereKey($data['agency_id'])->lockForUpdate()->firstOrFail();
            $this->assertBillableAgency($agency, $data['period_start'], $data['period_end']);

            if ($issue) {
                $clash = $this->overlappingIssued($agency, $data['period_start'], $data['period_end']);
                if ($clash) {
                    throw ValidationException::withMessages([
                        'period_start' => "This period overlaps already-issued statement {$clash->code}. Choose a different period.",
                    ]);
                }
            }

            $calc = BillingCalc::preview($agency, $data['period_start'], $data['period_end']);
            if ($calc['count'] === 0) {
                throw ValidationException::withMessages([
                    'period_start' => self::NO_ELIGIBLE_MESSAGE,
                ]);
            }

            if (empty($data['code'])) {
                $data['code'] = self::nextCode();
            }

            $statement = BillingStatement::create($data + [
                'status' => $issue ? BillingStatement::STATUS_ISSUED : BillingStatement::STATUS_DRAFT,
                'labor_cost' => $calc['labor_cost'],
                'service_fee' => $calc['service_fee'],
                'total' => $calc['total'],
            ]);

            if ($issue) {
                $this->markBilled($statement);
            }
            $this->log($statement, $issue ? 'statement_issued' : 'statement_created', "Statement {$statement->code} " . ($issue ? "issued ({$calc['count']} records)." : 'saved as draft.'), null);

            return $statement;
        });

        return redirect()->route('hrm.billing.show', $statement)
            ->with('success', $issue ? "Statement {$statement->code} issued." : "Statement {$statement->code} saved as draft.");
    }

    public function edit(BillingStatement $statement)
    {
        if ($statement->status !== BillingStatement::STATUS_DRAFT) {
            return redirect()->route('hrm.billing.show', $statement)
                ->with('error', 'Only Draft statements can be edited.');
        }

        return Inertia::render('Dashboard/HRM_NEW/Billing/Form', [
            'statement' => $this->detail($statement->loadMissing('agency')),
            'suggested_code' => $statement->code,
            'agencies' => Agency::orderBy('name')->get(['id', 'code', 'name', 'status'])
                ->filter(fn ($a) => $a->status !== Agency::STATUS_TERMINATED || ! $a->has_final_statement)
                ->values()->all(),
            'agency_id' => $statement->agency_id,
            'from' => $statement->period_start->toDateString(),
            'to' => $statement->period_end->toDateString(),
            'today' => today()->toDateString(),
            'preview' => BillingCalc::preview($statement->agency, $statement->period_start->toDateString(), $statement->period_end->toDateString()),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function update(Request $request, BillingStatement $statement)
    {
        if ($statement->status !== BillingStatement::STATUS_DRAFT) {
            return back()->with('error', 'Only Draft statements can be edited.');
        }

        $issue = $request->boolean('issue');
        $data = $this->validated($request, $statement->id);
        if (empty($data['code'])) {
            $data['code'] = $statement->code;
        }

        DB::transaction(function () use ($statement, $data, $issue) {
            // Serialize concurrent issuance for the same agency.
            $agency = Agency::whereKey($data['agency_id'])->lockForUpdate()->firstOrFail();
            $this->assertBillableAgency($agency, $data['period_start'], $data['period_end']);

            if ($issue) {
                $clash = $this->overlappingIssued($agency, $data['period_start'], $data['period_end'], $statement->id);
                if ($clash) {
                    throw ValidationException::withMessages([
                        'period_start' => "This period overlaps already-issued statement {$clash->code}. Choose a different period.",
                    ]);
                }
            }

            $calc = BillingCalc::preview($agency, $data['period_start'], $data['period_end']);
            if ($calc['count'] === 0) {
                throw ValidationException::withMessages([
                    'period_start' => self::NO_ELIGIBLE_MESSAGE,
                ]);
            }

            $statement->update($data + [
                'status' => $issue ? BillingStatement::STATUS_ISSUED : BillingStatement::STATUS_DRAFT,
                'labor_cost' => $calc['labor_cost'],
                'service_fee' => $calc['service_fee'],
                'total' => $calc['total'],
            ]);

            if ($issue) {
                $this->markBilled($statement->fresh());
            }
            $this->log($statement, $issue ? 'statement_issued' : 'statement_updated', "Statement {$statement->code} " . ($issue ? "issued ({$calc['count']} records)." : 'updated.'), null);
        });

        return redirect()->route('hrm.billing.show', $statement)
            ->with('success', $issue ? "Statement {$statement->code} issued." : "Statement {$statement->code} updated.");
    }

    // ------------------------------------------------------------------
    // Page 16: Statement detail
    // ------------------------------------------------------------------
    public function show(BillingStatement $statement)
    {
        $statement->loadMissing('agency');

        return Inertia::render('Dashboard/HRM_NEW/Billing/Detail', [
            'statement' => $this->detail($statement),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function issue(BillingStatement $statement)
    {
        if ($statement->status !== BillingStatement::STATUS_DRAFT) {
            return back()->with('error', 'Only Draft statements can be issued.');
        }

        try {
            DB::transaction(function () use ($statement) {
                // Serialize concurrent issuance for the same agency so a period
                // cannot be billed twice under parallel requests.
                $statement->loadMissing('agency');
                $agency = Agency::whereKey($statement->agency_id)->lockForUpdate()->firstOrFail();
                $from = $statement->period_start->toDateString();
                $to = $statement->period_end->toDateString();

                $this->assertBillableAgency($agency, $from, $to);

                $clash = $this->overlappingIssued($agency, $from, $to, $statement->id);
                if ($clash) {
                    throw ValidationException::withMessages([
                        'period_start' => "Statement {$statement->code} cannot be issued: this period is already covered by issued statement {$clash->code} ({$clash->period_start->toDateString()} – {$clash->period_end->toDateString()}).",
                    ]);
                }

                $calc = BillingCalc::preview($agency, $from, $to);
                if ($calc['count'] === 0) {
                    throw ValidationException::withMessages([
                        'period_start' => self::NO_ELIGIBLE_MESSAGE,
                    ]);
                }

                $statement->update([
                    'status' => BillingStatement::STATUS_ISSUED,
                    'labor_cost' => $calc['labor_cost'],
                    'service_fee' => $calc['service_fee'],
                    'total' => $calc['total'],
                ]);
                $this->markBilled($statement->fresh());
                $this->log($statement, 'statement_issued', "Statement {$statement->code} issued ({$calc['count']} records).", null);
            });
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', "Statement {$statement->code} issued.");
    }

    public function pay(Request $request, BillingStatement $statement)
    {
        $data = $request->validate([
            'paid_at' => 'required|date',
            'payment_reference' => 'required|string|max:255',
        ]);

        if ($statement->status !== BillingStatement::STATUS_ISSUED) {
            return back()->with('error', 'Only Issued statements can be marked as paid.');
        }

        $statement->update([
            'status' => BillingStatement::STATUS_PAID,
            'paid_at' => $data['paid_at'],
            'payment_reference' => $data['payment_reference'],
        ]);
        $this->log($statement, 'statement_paid', "Statement {$statement->code} marked as paid (ref {$data['payment_reference']}).", null);

        return back()->with('success', "Statement {$statement->code} marked as paid.");
    }

    public function cancel(Request $request, BillingStatement $statement)
    {
        $request->validate(['reason' => 'required|string|max:2000']);

        if (! in_array($statement->status, [BillingStatement::STATUS_DRAFT, BillingStatement::STATUS_ISSUED], true)) {
            return back()->with('error', 'Only Draft or Issued statements can be cancelled.');
        }

        $wasIssued = $statement->status === BillingStatement::STATUS_ISSUED;
        $statement->update(['status' => BillingStatement::STATUS_CANCELLED, 'cancel_reason' => $request->reason]);

        if ($wasIssued) {
            // Released attendance becomes billable again.
            Attendance::where('billed_statement_id', $statement->id)->update(['billed_statement_id' => null]);
        }
        $this->log($statement, 'statement_cancelled', "Statement {$statement->code} cancelled" . ($wasIssued ? ' — its attendance is billable again.' : '.'), $request->reason);

        return back()->with('success', "Statement {$statement->code} cancelled.");
    }

    public function exportPdf(BillingStatement $statement)
    {
        return StatementExportService::pdf($statement);
    }

    public function exportExcel(BillingStatement $statement)
    {
        return StatementExportService::excel($statement);
    }

    // ------------------------------------------------------------------
    // Payloads + guards
    // ------------------------------------------------------------------
    protected function row(BillingStatement $s): array
    {
        $s->loadMissing('agency:id,code,name');

        return [
            'id' => $s->id,
            'code' => $s->code,
            'agency' => $s->agency?->name,
            'agency_id' => $s->agency_id,
            'period' => BillingCalc::periodLabel($s->period_start->toDateString(), $s->period_end->toDateString()),
            'period_start' => $s->period_start->toDateString(),
            'period_end' => $s->period_end->toDateString(),
            'total_hours' => (float) $s->attendances()->sum('hours'),
            'labor_cost' => $s->labor_cost,
            'service_fee' => $s->service_fee,
            'total' => $s->total,
            'status' => $s->status,
        ];
    }

    protected function detail(BillingStatement $statement): array
    {
        $calc = BillingCalc::forStatement($statement);

        $activities = AgencyActivity::where('statement_id', $statement->id)
            ->latest()->take(20)->get()->map(fn ($a) => [
                'id' => $a->id,
                'date' => optional($a->created_at)->format('M d, Y h:i A'),
                'action' => $a->action,
                'details' => $a->details,
                'actor' => $a->actor_name ?? ('#' . $a->actor_id),
                'reason' => $a->reason,
            ])->values()->all();

        return [
            'id' => $statement->id,
            'code' => $statement->code,
            'agency_id' => $statement->agency_id,
            'agency' => $statement->agency?->name,
            'agency_code' => $statement->agency?->code,
            'service_fee_type' => $statement->agency?->service_fee_type,
            'service_fee_value' => $statement->agency?->service_fee_value,
            'period' => BillingCalc::periodLabel($statement->period_start->toDateString(), $statement->period_end->toDateString()),
            'period_start' => $statement->period_start->toDateString(),
            'period_end' => $statement->period_end->toDateString(),
            'status' => $statement->status,
            'labor_cost' => $statement->labor_cost,
            'service_fee' => $statement->service_fee,
            'total' => $statement->total,
            'total_hours' => array_sum(array_column($calc['lines'], 'hours')),
            'total_days' => array_sum(array_column($calc['lines'], 'days')),
            'lines' => $calc['lines'],
            'record_count' => $calc['count'],
            'paid_at' => optional($statement->paid_at)->toDateString(),
            'payment_reference' => $statement->payment_reference,
            'cancel_reason' => $statement->cancel_reason,
            'activities' => $activities,
        ];
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        if ($request->get('code') === '') {
            $request->merge(['code' => null]);
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('billing_statements', 'code')->ignore($ignoreId)],
            'agency_id' => 'required|exists:agencies,id',
            // Billing periods may be in the past (work already completed) but
            // never in the future, and the range must be well formed. `today`
            // resolves against the app timezone (Asia/Manila).
            'period_start' => 'required|date|before_or_equal:today',
            'period_end' => 'required|date|before_or_equal:today|after_or_equal:period_start',
            'issue' => 'nullable|boolean',
        ], [
            'period_start.required' => 'Period from is required.',
            'period_start.date' => 'Period from must be a valid date.',
            'period_start.before_or_equal' => 'Period from cannot be later than today.',
            'period_end.required' => 'Period to is required.',
            'period_end.date' => 'Period to must be a valid date.',
            'period_end.before_or_equal' => 'Period to cannot be later than today.',
            'period_end.after_or_equal' => 'Period to must be on or after Period from.',
        ]);
        unset($data['issue']);

        return $data;
    }

    /**
     * Suspended agencies bill normally. Terminated agencies may only bill
     * while no final (Paid/Cancelled) statement exists, and only for
     * periods ending on/before termination.
     */
    protected function assertBillableAgency(Agency $agency, string $from, string $to): void
    {
        if ($agency->status === Agency::STATUS_TERMINATED) {
            if ($agency->has_final_statement) {
                throw ValidationException::withMessages([
                    'agency_id' => 'The final statement for this terminated agency is closed — nothing new can be billed.',
                ]);
            }
            if ($agency->terminated_at && $to > $agency->terminated_at->toDateString()) {
                throw ValidationException::withMessages([
                    'period_end' => 'Only work on or before termination (' . $agency->terminated_at->toDateString() . ') can still be billed.',
                ]);
            }
        }
    }

    /**
     * Returns an Issued/Paid statement for the same agency whose period
     * overlaps [$from, $to], or null when the period is free to bill.
     */
    protected function overlappingIssued(Agency $agency, string $from, string $to, ?int $ignoreId = null): ?BillingStatement
    {
        return BillingStatement::where('agency_id', $agency->id)
            ->whereIn('status', [BillingStatement::STATUS_ISSUED, BillingStatement::STATUS_PAID])
            ->whereDate('period_start', '<=', $to)
            ->whereDate('period_end', '>=', $from)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->orderBy('id')
            ->first();
    }

    protected function markBilled(BillingStatement $statement): void
    {
        $statement->loadMissing('agency');
        $from = $statement->period_start->toDateString();
        $to = $statement->period_end->toDateString();

        // Persist any clock-log days as real attendance records first, so
        // days worked via the time clock (My Attendance) are billed and
        // marked as billed like any other record.
        BillingCalc::persistLogAttendance($statement->agency, $from, $to);

        // Mark every in-period attendance record for the agency as billed
        // (any approval state — the preview now includes all of them) so it
        // can never be billed twice.
        Attendance::whereNull('billed_statement_id')
            ->whereHas('worker', fn ($q) => $q->where('agency_id', $statement->agency_id))
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->update(['billed_statement_id' => $statement->id]);
    }

    protected static function nextCode(): string
    {
        $next = (int) (BillingStatement::max('id') ?? 0) + 1;
        do {
            $code = 'BIL-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (BillingStatement::where('code', $code)->exists());

        return $code;
    }

    protected function log(BillingStatement $statement, string $action, ?string $details, ?string $reason): void
    {
        AgencyActivity::create([
            'agency_id' => $statement->agency_id,
            'statement_id' => $statement->id,
            'action' => $action,
            'details' => $details,
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()?->name,
            'reason' => $reason,
        ]);
    }
}
