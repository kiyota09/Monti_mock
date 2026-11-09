<?php

namespace App\Http\Controllers\Hrm\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Core\Notification;
use App\Models\Hrm\Agency;
use App\Models\Hrm\AgencyActivity;
use App\Models\Hrm\AgencyReferral;
use App\Models\Hrm\AgencyReferralHistory;
use App\Models\Hrm\ApplicantJobApplication;
use App\Models\Hrm\ApplicantDocument;
use App\Models\Hrm\HrmOnboarding;
use App\Models\Hrm\HrmOnboardingItem;
use App\Models\Hrm\HrmInterview;
use App\Traits\HasPagePermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AgencyReferralController extends Controller
{
    use HasPagePermissions;

    private const STATUSES = [
        'Ready to Refer', 'Pending Agency Response', 'More Information Required',
        'Agency Accepted', 'Agency Declined', 'Awaiting Monti Decision',
        'Under Review', 'Response Sent to Monti', 'Confirmed for Onboarding', 'Cancelled',
    ];

    public function index()
    {
        $referrals = AgencyReferral::query()
            ->with(['applicant', 'jobApplication', 'jobPosting', 'agency', 'creator'])
            ->latest('referral_date')->latest('id')->paginate(50)->withQueryString()
            ->through(fn (AgencyReferral $referral) => $this->present($referral));

        $referralQuery = AgencyReferral::query();
        $eligibleCount = $this->eligibleApplications()->count();

        return Inertia::render('Dashboard/HRM_NEW/AgencyReferrals', [
            'referrals' => $referrals,
            'agencies' => Agency::query()->where('status', Agency::STATUS_ACTIVE)->orderBy('name')->get(['id', 'code', 'name', 'contact_name', 'contact_email']),
            'summary' => [
                'eligible' => $eligibleCount,
                'pending' => (clone $referralQuery)->whereIn('status', ['Pending Agency Response', 'Under Review'])->count(),
                'accepted' => (clone $referralQuery)->where(fn ($q) => $q->where('agency_response', 'Accepted')->orWhere('status', 'Agency Accepted'))->count(),
                'decision' => (clone $referralQuery)->whereIn('status', ['Awaiting Monti Decision', 'Response Sent to Monti'])->count(),
            ],
            'statuses' => self::STATUSES,
        ]);
    }

    public function eligibleApplicants()
    {
        return response()->json([
            'data' => $this->eligibleApplications()->limit(500)->get()
                ->map(fn (ApplicantJobApplication $application) => $this->eligiblePayload($application))->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'applicant_job_application_id' => ['required', 'integer', 'exists:applicant_job_applications,id'],
            'agency_id' => ['required', 'integer', 'exists:agencies,id'],
            'referral_reason' => ['nullable', 'string', 'max:5000'],
            'agency_instructions' => ['nullable', 'string', 'max:5000'],
            'monti_approved_feedback' => ['nullable', 'string', 'max:5000'],
            'consent_verified' => ['accepted'],
            'shared_documents' => ['nullable', 'array'],
            'shared_documents.*' => ['string', 'regex:/^(applicant|onboarding):\d+$/'],
            'expected_response_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $application = $this->eligibleApplications()
            ->with(['applicant.documents', 'jobPosting'])
            ->whereKey($data['applicant_job_application_id'])->first();
        if (! $application) {
            throw ValidationException::withMessages([
                'applicant_job_application_id' => 'This application is not eligible. The applicant must have completed onboarding and have no active agency referral.',
            ]);
        }

        $agency = Agency::query()->whereKey($data['agency_id'])->where('status', Agency::STATUS_ACTIVE)->first();
        if (! $agency) {
            throw ValidationException::withMessages(['agency_id' => 'Choose an active agency.']);
        }

        $selectedKeys = array_values(array_unique($data['shared_documents'] ?? []));
        $applicantDocumentIds = collect($selectedKeys)->filter(fn ($key) => str_starts_with($key, 'applicant:'))->map(fn ($key) => (int) substr($key, 10))->all();
        $onboardingItemIds = collect($selectedKeys)->filter(fn ($key) => str_starts_with($key, 'onboarding:'))->map(fn ($key) => (int) substr($key, 11))->all();
        $documents = ApplicantDocument::query()
            ->where('applicant_id', $application->applicant_id)
            ->whereIn('id', $applicantDocumentIds)
            ->get()
            ->map(fn ($document) => [
                'id' => $document->id, 'source' => 'applicant', 'name' => $document->original_name ?: basename($document->file_path),
                'type' => $document->type, 'verified' => (bool) $document->verified,
            ]);
        $onboarding = HrmOnboarding::query()
            ->where('applicant_id', $application->applicant_id)
            ->where('job_posting_id', $application->job_posting_id)
            ->where('status', 'Completed')
            ->latest('id')->first();
        if ($onboarding && $onboardingItemIds) {
            $onboardingDocuments = HrmOnboardingItem::query()
                ->with('templateItem')
                ->where('onboarding_id', $onboarding->id)
                ->whereIn('id', $onboardingItemIds)
                ->whereIn('status', ['Completed', 'Verified'])
                ->get()
                ->filter(function ($item) {
                    $filePath = $item->file_url ?: $item->attachment_path;
                    $hasFile = $filePath && Storage::disk('public')->exists($filePath);
                    $hasValue = filled($item->value) && ! $this->isStatusOnlyValue($item->value);

                    return $item->templateItem?->category === 'Applicant Requirements' && ($hasFile || $hasValue);
                })
                ->map(function ($item) {
                    $filePath = $item->file_url ?: $item->attachment_path;
                    $hasFile = $filePath && Storage::disk('public')->exists($filePath);

                    return [
                        'id' => $item->id,
                        'source' => 'onboarding',
                        'name' => $item->original_name ?: $item->attachment_name ?: $item->name,
                        'type' => $item->templateItem?->input_type ?: 'onboarding document',
                        'verified' => true,
                        'value' => $hasFile || $this->isStatusOnlyValue($item->value) ? null : $item->value,
                    ];
                });
            $documents = $documents->concat($onboardingDocuments);
        }
        if ($documents->count() !== count($selectedKeys)) {
            throw ValidationException::withMessages(['shared_documents' => 'One or more selected documents do not belong to this applicant.']);
        }

        $referral = DB::transaction(function () use ($request, $data, $application, $documents) {
            $referral = AgencyReferral::create([
                'applicant_job_application_id' => $application->id,
                'applicant_id' => $application->applicant_id,
                'job_posting_id' => $application->job_posting_id,
                'agency_id' => $data['agency_id'],
                'status' => 'Ready to Refer',
                'referral_reason' => $data['referral_reason'] ?? '',
                'agency_instructions' => $data['agency_instructions'] ?? null,
                'monti_approved_feedback' => $data['monti_approved_feedback'] ?? null,
                'consent_verified' => true,
                'shared_documents' => $documents->values()->all(),
                'expected_response_date' => $data['expected_response_date'] ?? null,
                'referral_date' => now()->toDateString(),
                'created_by' => $request->user()->id,
            ]);
            $this->record($referral, 'Referral created', null, $referral->status, $request->user()->id, 'Applicant consent and data-sharing verification recorded.');

            return $referral;
        });

        return back()->with('success', 'Agency referral created. Review it and send when ready.');
    }

    public function show(AgencyReferral $referral)
    {
        $referral->load([
            'applicant.documents', 'jobApplication', 'jobPosting', 'agency', 'creator', 'decider',
            'history.actor', 'history.agencyActor',
        ]);
        $interviews = HrmInterview::query()
            ->with(['evaluation.evaluator'])
            ->where('applicant_id', $referral->applicant_id)
            ->where('job_posting_id', $referral->job_posting_id)
            ->latest('date')->get();

        return response()->json([
            'data' => $this->present($referral) + [
                'applicant_info' => [
                    'id' => $referral->applicant->id,
                    'name' => $referral->applicant->name,
                    'email' => $referral->applicant->email,
                    'phone' => $referral->applicant->phone_number ?: $referral->applicant->contact_number,
                    'address' => collect([$referral->applicant->street_address, $referral->applicant->city, $referral->applicant->state_province])->filter()->implode(', '),
                ],
                'application' => [
                    'id' => $referral->jobApplication->id,
                    'status' => $referral->jobApplication->status,
                    'position' => $referral->jobPosting?->title ?: $referral->applicant->position_applied,
                    'department' => $referral->jobPosting?->department,
                    'applied_at' => optional($referral->jobApplication->created_at)->toDateString(),
                ],
                'interviews' => $interviews->map(fn (HrmInterview $interview) => [
                    'date' => optional($interview->date)->toDateString(),
                    'type' => $interview->type,
                    'status' => $interview->status,
                    'result' => $interview->evaluation?->result,
                    'recommendation' => $interview->evaluation?->recommendation,
                    'notes' => $interview->evaluation?->interview_notes,
                    'evaluator' => $interview->evaluation?->evaluator?->name,
                ]),
                'documents' => $referral->shared_documents ?? [],
                'history' => $referral->history->map(fn (AgencyReferralHistory $history) => [
                    'action' => $history->action,
                    'from_status' => $history->from_status,
                    'to_status' => $history->to_status,
                    'notes' => $history->notes,
                    'actor' => $history->agencyActor?->name ?? $history->actor?->name,
                    'at' => $history->created_at?->toDateTimeString(),
                ]),
            ],
        ]);
    }

    public function send(Request $request, AgencyReferral $referral)
    {
        if ($referral->status !== 'Ready to Refer') {
            throw ValidationException::withMessages(['status' => 'Only a referral that is Ready to Refer can be sent.']);
        }
        $this->changeStatus($referral, 'Pending Agency Response', 'Referral sent to agency', $request->user()->id, 'Referral marked as sent to the assigned agency.');
        $referral->update(['sent_at' => now()]);
        $referral->loadMissing(['agency', 'applicant', 'jobPosting']);
        AgencyActivity::create([
            'agency_id' => $referral->agency_id,
            'action' => 'New applicant referral',
            'details' => sprintf(
                '%s referred %s for %s (Referral %s).',
                'Monti HR',
                $referral->applicant?->name ?? 'An applicant',
                $referral->jobPosting?->title ?? $referral->applicant?->position_applied ?? 'a position',
                'AR-'.str_pad((string) $referral->id, 6, '0', STR_PAD_LEFT),
            ),
            'actor_id' => $request->user()->id,
            'actor_name' => $request->user()->name,
        ]);

        return back()->with('success', 'Referral marked as sent and awaiting agency response.');
    }

    public function requestUpdate(Request $request, AgencyReferral $referral)
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:3000']]);
        if (! in_array($referral->status, ['Pending Agency Response', 'Under Review', 'More Information Required', 'Awaiting Monti Decision'], true)
            || ($referral->status === 'Awaiting Monti Decision' && $referral->agency_response !== 'More Information Required')) {
            throw ValidationException::withMessages(['status' => 'An update can only be requested while the agency response is pending.']);
        }
        $this->record($referral, 'Agency update requested', $referral->status, $referral->status, $request->user()->id, $data['notes']);

        return back()->with('success', 'Agency update request recorded.');
    }

    public function agencyResponse(Request $request, AgencyReferral $referral)
    {
        $data = $request->validate([
            'response' => ['required', 'in:accepted,declined,more_information,decision'],
            'feedback' => ['required', 'string', 'max:5000'],
        ]);
        if (! in_array($referral->status, ['Pending Agency Response', 'Under Review', 'More Information Required'], true)) {
            throw ValidationException::withMessages(['status' => 'This referral is not awaiting an agency response.']);
        }
        $response = match ($data['response']) {
            'accepted' => 'Accepted',
            'declined' => 'Declined',
            'more_information' => 'More Information Required',
            default => 'Awaiting Monti Decision',
        };
        DB::transaction(function () use ($request, $referral, $data, $response) {
            $from = $referral->status;
            $referral->update([
                'status' => 'Awaiting Monti Decision',
                'agency_response' => $response,
                'agency_feedback' => $data['feedback'],
                'response_received_at' => now(),
            ]);
            $this->record($referral, 'Agency response received', $from, 'Awaiting Monti Decision', $request->user()->id, $data['feedback']);
        });

        Notification::notifyModule(
            'HRM', 'agency_referral_response', 'Agency responded to a referral',
            'The agency submitted a response for referral AR-'.str_pad((string) $referral->id, 6, '0', STR_PAD_LEFT).'.',
            'hrm.recruitment.agency-referrals.index'
        );

        return back()->with('success', 'Agency response saved.');
    }

    public function montiDecision(Request $request, AgencyReferral $referral)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:confirm,decline'],
            'reason' => ['required', 'string', 'max:3000'],
        ]);
        if (! in_array($referral->status, ['Agency Accepted', 'Agency Declined', 'Awaiting Monti Decision', 'Response Sent to Monti'], true)) {
            throw ValidationException::withMessages(['status' => 'A Monti decision can only be recorded after an agency response.']);
        }

        $next = $data['decision'] === 'confirm' ? 'Confirmed for Onboarding' : 'Cancelled';
        DB::transaction(function () use ($request, $referral, $data, $next) {
            $from = $referral->status;
            $referral->update([
                'status' => $next,
                'monti_decision' => $data['decision'] === 'confirm' ? 'Confirmed for Onboarding' : 'Declined Applicant',
                'decision_reason' => $data['reason'],
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);
            $this->record($referral, $data['decision'] === 'confirm' ? 'Confirmed for onboarding' : 'Applicant declined by Monti HR', $from, $next, $request->user()->id, $data['reason']);
        });

        return back()->with('success', $data['decision'] === 'confirm' ? 'Referral confirmed for onboarding.' : 'Applicant declined and referral closed.');
    }

    public function withdraw(Request $request, AgencyReferral $referral)
    {
        if ($referral->agency_response === 'Accepted' || $referral->status === 'Agency Accepted') {
            throw ValidationException::withMessages(['status' => 'This referral cannot be cancelled after the agency accepts it.']);
        }
        if (in_array($referral->status, ['Confirmed for Onboarding', 'Cancelled', 'Withdrawn / Cancelled'], true)) {
            throw ValidationException::withMessages(['status' => 'This referral is already closed.']);
        }
        $data = $request->validate(['reason' => ['required', 'string', 'max:3000']]);
        $this->changeStatus($referral, 'Cancelled', 'Referral cancelled', $request->user()->id, $data['reason']);

        return back()->with('success', 'Referral cancelled.');
    }

    private function eligibleApplications(): Builder
    {
        $activeReferralIds = AgencyReferral::query()
            ->whereNotIn('status', ['Cancelled', 'Withdrawn / Cancelled'])
            ->select('applicant_job_application_id');

        return ApplicantJobApplication::query()
            ->with(['applicant.documents', 'jobPosting'])
            ->whereNotNull('job_posting_id')
            // A completed onboarding can still need an agency referral when
            // an older workflow already advanced the application to Hired.
            // The onboarding page only exposes referral when no employee
            // account exists, so keep those completed records eligible.
            ->whereNotIn('status', ['Rejected', 'Withdrawn'])
            ->where(function (Builder $query) {
                $query->whereNotIn('status', ['Onboard', 'Onboarding'])
                    ->orWhereExists(function ($completedOnboarding) {
                        $completedOnboarding->selectRaw('1')
                            ->from('hrm_onboardings')
                            ->whereColumn('hrm_onboardings.applicant_id', 'applicant_job_applications.applicant_id')
                            ->whereColumn('hrm_onboardings.job_posting_id', 'applicant_job_applications.job_posting_id')
                            ->where('hrm_onboardings.status', 'Completed');
                    });
            })
            ->whereNotIn('id', $activeReferralIds)
            ->whereExists(function ($completedOnboarding) {
                $completedOnboarding->selectRaw('1')
                    ->from('hrm_onboardings')
                    ->whereColumn('hrm_onboardings.applicant_id', 'applicant_job_applications.applicant_id')
                    ->whereColumn('hrm_onboardings.job_posting_id', 'applicant_job_applications.job_posting_id')
                    ->where('hrm_onboardings.status', 'Completed');
            });
    }

    private function eligiblePayload(ApplicantJobApplication $application): array
    {
        $applicantDocuments = $application->applicant?->documents?->map(fn ($document) => [
            'id' => $document->id,
            'key' => 'applicant:'.$document->id,
            'source' => 'Applicant file',
            'name' => $document->original_name ?: basename($document->file_path),
            'type' => $document->type,
            'verified' => (bool) $document->verified,
            'shareable' => true,
            'status' => $document->verified ? 'Verified' : 'Not verified',
            'preview_url' => Storage::disk('public')->url($document->file_path),
        ]) ?? collect();
        $onboarding = HrmOnboarding::query()
            ->with('items.templateItem')
            ->where('applicant_id', $application->applicant_id)
            ->where('job_posting_id', $application->job_posting_id)
            ->where('status', 'Completed')
            ->latest('id')->first();
        $onboardingDocuments = $onboarding?->items
            ?->filter(fn ($item) => $item->templateItem?->category === 'Applicant Requirements')
            ->map(function ($item) {
                $approved = in_array($item->status, ['Completed', 'Verified'], true);
                $filePath = $item->file_url ?: $item->attachment_path;
                $hasFile = (bool) ($filePath && Storage::disk('public')->exists($filePath));
                $hasValue = filled($item->value) && ! $this->isStatusOnlyValue($item->value);

                return [
                'id' => $item->id,
                'key' => 'onboarding:'.$item->id,
                'source' => 'Onboarding requirement',
                'name' => $item->original_name ?: $item->attachment_name ?: $item->name,
                'type' => $item->templateItem?->input_type ?: 'onboarding document',
                'status' => $approved ? 'Approved' : $item->status,
                'shareable' => $approved && ($hasFile || $hasValue),
                'verified' => $approved,
                'has_file' => $hasFile,
                'value' => $hasFile || ! $hasValue ? null : $item->value,
                'preview_url' => $hasFile ? Storage::disk('public')->url($filePath) : null,
                ];
            }) ?? collect();

        return [
            'job_application_id' => $application->id,
            'applicant_id' => $application->applicant_id,
            'name' => $application->applicant?->name,
            'email' => $application->applicant?->email,
            'position' => $application->jobPosting?->title,
            'department' => $application->jobPosting?->department,
            'eligibility_status' => 'Onboarding Completed',
            'documents' => $applicantDocuments->concat($onboardingDocuments)->values(),
        ];
    }

    private function isStatusOnlyValue(?string $value): bool
    {
        return in_array(strtolower(trim((string) $value)), [
            'submitted', 'submitted and verified', 'submitted and verified.', 'verified',
        ], true);
    }

    private function present(AgencyReferral $referral): array
    {
        $passedInterview = $this->passedInterviewFor($referral->applicant_id, $referral->job_posting_id);

        return [
            'id' => $referral->id,
            'referral_id' => 'AR-'.str_pad((string) $referral->id, 6, '0', STR_PAD_LEFT),
            'applicant_id' => $referral->applicant_id,
            'applicant_name' => $referral->applicant?->name,
            'email' => $referral->applicant?->email,
            'position' => $referral->jobPosting?->title ?: $referral->applicant?->position_applied,
            'department' => $referral->jobPosting?->department,
            'job_application_status' => $referral->jobApplication?->status,
            'interview_result' => $passedInterview ? 'Passed' : 'Onboarding Completed',
            'interview_stage' => $passedInterview?->type,
            'agency' => $referral->agency ? ['id' => $referral->agency->id, 'name' => $referral->agency->name, 'code' => $referral->agency->code, 'contact_email' => $referral->agency->contact_email] : null,
            'referral_date' => optional($referral->referral_date)->toDateString(),
            'expected_response_date' => optional($referral->expected_response_date)->toDateString(),
            'sent_at' => optional($referral->sent_at)->toDateTimeString(),
            'status' => $this->displayStatus($referral),
            'referral_reason' => $referral->referral_reason,
            'agency_instructions' => $referral->agency_instructions,
            'monti_approved_feedback' => $referral->monti_approved_feedback,
            'consent_verified' => (bool) $referral->consent_verified,
            'shared_documents' => $referral->shared_documents ?? [],
            'agency_feedback' => $referral->agency_feedback,
            'agency_response' => $referral->agency_response,
            'response_received_at' => optional($referral->response_received_at)->toDateTimeString(),
            'monti_decision' => $referral->monti_decision,
            'decision_reason' => $referral->decision_reason,
            'decided_by' => $referral->decider?->name,
            'decided_at' => optional($referral->decided_at)->toDateTimeString(),
            'created_by' => $referral->creator?->name,
        ];
    }

    private function passedInterviewFor(int $applicantId, ?int $jobPostingId): ?HrmInterview
    {
        return HrmInterview::query()
            ->where('applicant_id', $applicantId)
            ->where('job_posting_id', $jobPostingId)
            ->whereHas('evaluation', fn (Builder $evaluation) => $evaluation->whereRaw('LOWER(result) = ?', ['passed']))
            ->latest('date')->latest('id')->first();
    }

    private function displayStatus(AgencyReferral $referral): string
    {
        if ($referral->status === 'Withdrawn / Cancelled') {
            return 'Cancelled';
        }

        if ($referral->status !== 'Awaiting Monti Decision') {
            return $referral->status;
        }

        return match ($referral->agency_response) {
            'Accepted' => 'Agency Accepted',
            'Declined' => 'Agency Declined',
            'More Information Required' => 'More Information Required',
            default => 'Awaiting Monti Decision',
        };
    }

    private function changeStatus(AgencyReferral $referral, string $status, string $action, int $actorId, ?string $notes = null): void
    {
        DB::transaction(function () use ($referral, $status, $action, $actorId, $notes) {
            $from = $referral->status;
            $referral->update(['status' => $status]);
            $this->record($referral, $action, $from, $status, $actorId, $notes);
        });
    }

    private function record(AgencyReferral $referral, string $action, ?string $from, ?string $to, int $actorId, ?string $notes = null): void
    {
        AgencyReferralHistory::create([
            'agency_referral_id' => $referral->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
            'actor_id' => $actorId,
        ]);
    }
}
