<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Hrm\Agency;
use App\Models\Hrm\AgencyActivity;
use App\Models\Hrm\Attendance;
use App\Models\Hrm\BillingStatement;
use App\Support\BillingCalc;
use App\Support\StatementExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Agency portal: Issued + Paid statements only (Draft hidden, read-only).
 * Breakdown + PDF/Excel export. The agency can create and view invoices but
 * can never edit or pay an issued one.
 */
class BillingPortalController extends Controller
{
    private function agencyId(Request $request): int
    {
        return (int) $request->user('agency')->agency_id;
    }

    private function agency(Request $request): Agency
    {
        return Agency::findOrFail($this->agencyId($request));
    }

    // ------------------------------------------------------------------
    // Create invoice (period + live preview of unbilled attendance)
    // ------------------------------------------------------------------
    public function create(Request $request)
    {
        $agency = $this->agency($request);
        $from = $request->get('from', today()->startOfMonth()->toDateString());
        $to = $request->get('to', today()->toDateString());

        return Inertia::render('Agency/Billing/Create', [
            'agency' => [
                'id' => $agency->id,
                'code' => $agency->code,
                'name' => $agency->name,
            ],
            'from' => $from,
            'to' => $to,
            'today' => today()->toDateString(),
            'preview' => BillingCalc::preview($agency, $from, $to),
            'attachmentHistory' => DB::table('agency_billing_uploads')
                ->where('agency_id', $agency->id)
                ->orderByDesc('id')
                ->get(['id', 'original_name', 'size_bytes', 'period_start', 'period_end', 'created_at'])
                ->map(fn ($file) => [
                    'id' => 'upload-' . $file->id,
                    'name' => $file->original_name,
                    'uploaded_at' => $file->created_at,
                    'size' => $file->size_bytes,
                    'period' => $file->period_start . ' – ' . $file->period_end,
                    'url' => route('agency.billing.uploads.show', $file->id),
                ])
                ->concat(BillingStatement::where('agency_id', $agency->id)
                    ->whereNotNull('attachments')
                    ->orderByDesc('id')
                    ->get(['id', 'code', 'attachments', 'created_at'])
                    ->flatMap(fn ($statement) => collect($statement->attachments ?? [])->map(fn ($file, $index) => [
                        'id' => 'invoice-' . $statement->id . '-' . $index,
                        'name' => $file['name'],
                        'uploaded_at' => $statement->created_at,
                        'size' => $file['size'] ?? 0,
                        'period' => $statement->code,
                        'url' => route('agency.billing.attachment', [$statement, $index]),
                    ])))
                ->sortByDesc('uploaded_at')->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'period_start' => 'required|date|before_or_equal:today',
            'period_end' => 'required|date|before_or_equal:today|after_or_equal:period_start',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|max:10240',
        ], [
            'period_start.required' => 'Period from is required.',
            'period_start.before_or_equal' => 'Period from cannot be later than today.',
            'period_end.required' => 'Period to is required.',
            'period_end.before_or_equal' => 'Period to cannot be later than today.',
            'period_end.after_or_equal' => 'Period to must be on or after Period from.',
        ]);

        $agencyId = $this->agencyId($request);

        $statement = DB::transaction(function () use ($request, $agencyId, $data) {
            $agency = Agency::whereKey($agencyId)->lockForUpdate()->firstOrFail();
            $this->assertBillableAgency($agency, $data['period_start'], $data['period_end']);

            $clash = $this->overlappingIssued($agency, $data['period_start'], $data['period_end']);
            if ($clash) {
                throw ValidationException::withMessages([
                    'period_start' => "This period overlaps already-issued invoice {$clash->code}. Choose a different period.",
                ]);
            }

            $calc = BillingCalc::preview($agency, $data['period_start'], $data['period_end']);
            if ($calc['count'] === 0) {
                throw ValidationException::withMessages([
                    'period_start' => 'No eligible, unbilled attendance records were found for the selected period.',
                ]);
            }

            $statement = BillingStatement::create([
                'code' => $this->nextCode(),
                'agency_id' => $agency->id,
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'status' => BillingStatement::STATUS_ISSUED,
                'labor_cost' => $calc['labor_cost'],
                'service_fee' => $calc['service_fee'],
                'total' => $calc['total'],
            ]);

            $this->markBilled($statement);

            AgencyActivity::create([
                'agency_id' => $agency->id,
                'statement_id' => $statement->id,
                'action' => 'statement_created',
                'details' => "Invoice {$statement->code} created by {$request->user('agency')->name} (agency) covering {$statement->period_start->toDateString()} to {$statement->period_end->toDateString()} ({$calc['count']} records).",
                'actor_id' => null,
                'actor_name' => $request->user('agency')->name . ' (agency)',
            ]);

            return $statement;
        });

        if ($request->hasFile('attachments')) {
            $statement->attachments = collect($request->file('attachments'))->map(function ($file) use ($statement) {
                return [
                    'name' => $file->getClientOriginalName(),
                    'path' => $file->store("agency-invoices/{$statement->id}"),
                    'mime' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ];
            })->all();
            $statement->save();
        }

        return redirect()->route('agency.billing.show', $statement)
            ->with('message', "Invoice {$statement->code} created.");
    }

    public function uploadAttachments(Request $request)
    {
        $data = $request->validate([
            'period_start' => 'required|date|before_or_equal:today',
            'period_end' => 'required|date|before_or_equal:today|after_or_equal:period_start',
            'attachments' => 'required|array|min:1|max:10',
            'attachments.*' => 'file|max:10240',
        ]);

        $agencyId = $this->agencyId($request);
        foreach ($request->file('attachments') as $file) {
            DB::table('agency_billing_uploads')->insert([
                'agency_id' => $agencyId,
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'original_name' => $file->getClientOriginalName(),
                'path' => $file->store("agency-billing-uploads/{$agencyId}"),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('message', 'Invoice documents uploaded.');
    }

    public function downloadUpload(Request $request, int $upload)
    {
        $file = DB::table('agency_billing_uploads')
            ->where('id', $upload)
            ->where('agency_id', $this->agencyId($request))
            ->first();
        abort_unless($file && Storage::exists($file->path), 404);

        return Storage::response($file->path, $file->original_name);
    }

    public function index(Request $request)
    {
        return redirect()->route('agency.billing.create');
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
                'attachments' => collect($statement->attachments ?? [])->map(fn ($file, $index) => [
                    'name' => $file['name'],
                    'url' => route('agency.billing.attachment', [$statement, $index]),
                ])->values(),
            ],
        ]);
    }

    public function downloadAttachment(Request $request, BillingStatement $statement, int $index)
    {
        abort_unless((int) $statement->agency_id === $this->agencyId($request), 404);
        $file = $statement->attachments[$index] ?? null;
        abort_unless($file && Storage::exists($file['path']), 404);

        return Storage::response($file['path'], $file['name']);
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

    // ------------------------------------------------------------------
    // Create-invoice guards
    // ------------------------------------------------------------------
    protected function assertBillableAgency(Agency $agency, string $from, string $to): void
    {
        if ($agency->status === Agency::STATUS_TERMINATED) {
            if ($agency->has_final_statement) {
                throw ValidationException::withMessages([
                    'period_start' => 'The final statement for this terminated agency is closed — nothing new can be billed.',
                ]);
            }
            if ($agency->terminated_at && $to > $agency->terminated_at->toDateString()) {
                throw ValidationException::withMessages([
                    'period_end' => 'Only work on or before termination (' . $agency->terminated_at->toDateString() . ') can still be billed.',
                ]);
            }
        }
    }

    protected function overlappingIssued(Agency $agency, string $from, string $to): ?BillingStatement
    {
        return BillingStatement::where('agency_id', $agency->id)
            ->whereIn('status', [BillingStatement::STATUS_ISSUED, BillingStatement::STATUS_PAID])
            ->whereDate('period_start', '<=', $to)
            ->whereDate('period_end', '>=', $from)
            ->orderBy('id')
            ->first();
    }

    protected function markBilled(BillingStatement $statement): void
    {
        $statement->loadMissing('agency');
        $from = $statement->period_start->toDateString();
        $to = $statement->period_end->toDateString();

        BillingCalc::persistLogAttendance($statement->agency, $from, $to);

        Attendance::whereNull('billed_statement_id')
            ->whereHas('worker', fn ($q) => $q->where('agency_id', $statement->agency_id))
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->update(['billed_statement_id' => $statement->id]);
    }

    protected function nextCode(): string
    {
        $next = (int) (BillingStatement::max('id') ?? 0) + 1;
        do {
            $code = 'BIL-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (BillingStatement::where('code', $code)->exists());

        return $code;
    }
}
