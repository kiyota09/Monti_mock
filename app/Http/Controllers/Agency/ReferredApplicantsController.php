<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Core\Notification;
use App\Models\Hrm\AgencyReferral;
use App\Models\Hrm\AgencyReferralHistory;
use App\Models\Hrm\ApplicantDocument;
use App\Models\Hrm\HrmOnboardingItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Inertia\Inertia;

class ReferredApplicantsController extends Controller
{
    public function index(Request $request)
    {
        $agencyId = (int) $request->user('agency')->agency_id;
        $base = AgencyReferral::query()->where('agency_id', $agencyId)->where('status', '!=', 'Ready to Refer');

        return Inertia::render('Agency/ReferredApplicants/Index', [
            'referrals' => (clone $base)
                ->with(['applicant', 'jobPosting', 'jobApplication'])
                ->latest('referral_date')->latest('id')->paginate(30)->withQueryString()
                ->through(fn (AgencyReferral $referral) => $this->row($referral)),
            'summary' => [
                'new' => (clone $base)->whereIn('status', ['Pending Agency Response', 'Ready to Refer'])->count(),
                'review' => (clone $base)->where('status', 'Under Review')->count(),
                'accepted' => (clone $base)->where(fn ($q) => $q->where('agency_response', 'Accepted')->orWhere('status', 'Agency Accepted'))->count(),
                'declined' => (clone $base)->where(fn ($q) => $q->where('agency_response', 'Declined')->orWhere('status', 'Agency Declined'))->count(),
                'awaiting_monti' => (clone $base)->whereIn('status', ['Awaiting Monti Decision', 'Response Sent to Monti'])->count(),
            ],
        ]);
    }

    public function show(Request $request, int $referral)
    {
        $record = $this->agencyReferral($request, $referral)->with([
            'applicant', 'jobApplication', 'jobPosting.employmentType', 'agency',
            'history.actor', 'history.agencyActor',
        ])->firstOrFail();

        $applicant = $record->applicant;
        $posting = $record->jobPosting;
        $application = $record->jobApplication;
        $shared = collect($record->shared_documents ?? []);
        $documents = $record->consent_verified
            ? $shared->map(function (array $document) use ($record) {
                if (($document['source'] ?? 'applicant') === 'onboarding') {
                    $item = HrmOnboardingItem::query()->with('templateItem')
                        ->whereKey($document['id'] ?? 0)
                        ->whereIn('status', ['Completed', 'Verified'])
                        ->whereHas('templateItem', fn ($q) => $q->where('category', 'Applicant Requirements'))
                        ->whereHas('onboarding', fn ($q) => $q->where('applicant_id', $record->applicant_id)->where('job_posting_id', $record->job_posting_id))
                        ->first();
                    if (! $item) return null;
                    $filePath = $item->file_url ?: $item->attachment_path;
                    $hasFile = (bool) ($filePath && Storage::disk('public')->exists($filePath));
                    $value = $document['value'] ?? $item->value;
                    $hasValue = filled($value) && ! $this->isStatusOnlyValue($value);
                    if (! $hasFile && ! $hasValue) return null;

                    return [
                        'id' => 'onboarding-'.$item->id,
                        'name' => $document['name'] ?? $item->original_name ?? $item->attachment_name ?? $item->name,
                        'type' => $document['type'] ?? $item->templateItem?->input_type,
                        'value' => $hasFile || ! $hasValue ? null : $value,
                        'view_url' => $hasFile ? route('agency.referrals.documents.download', [$record->id, 'onboarding-'.$item->id]).'?view=1' : null,
                        'download_url' => $hasFile ? route('agency.referrals.documents.download', [$record->id, 'onboarding-'.$item->id]) : null,
                    ];
                }
                $liveDocument = ApplicantDocument::query()->where('applicant_id', $record->applicant_id)->whereKey($document['id'] ?? 0)->first();
                if (! $liveDocument) return null;

                return [
                    'id' => $liveDocument->id,
                    'name' => $document['name'] ?? $liveDocument->original_name ?? basename($liveDocument->file_path),
                    'type' => $document['type'] ?? $liveDocument->type,
                    'view_url' => route('agency.referrals.documents.download', [$record->id, $liveDocument->id]).'?view=1',
                    'download_url' => route('agency.referrals.documents.download', [$record->id, $liveDocument->id]),
                ];
            })->filter()->values()
            : collect();

        $history = $record->history->map(function (AgencyReferralHistory $entry) {
            $isAgencyAction = $entry->agency_user_id !== null;

            return [
                'action' => $entry->action,
                'from_status' => $entry->from_status,
                'to_status' => $entry->to_status,
                // HR-only decision notes stay private. Agency notes and HR's
                // explicit update requests are visible to the assigned agency.
                'notes' => $isAgencyAction || $entry->action === 'Agency update requested' ? $entry->notes : null,
                'actor' => $entry->agencyActor?->name ?? ($isAgencyAction ? 'Agency user' : 'Monti HR'),
                'at' => $entry->created_at?->toDateTimeString(),
            ];
        })->values();

        return response()->json(['data' => [
            'id' => $record->id,
            'referral_id' => 'AR-'.str_pad((string) $record->id, 6, '0', STR_PAD_LEFT),
            'status' => $this->agencyStatus($record),
            'agency_response' => $record->agency_response,
            'referral_date' => optional($record->referral_date)->toDateString(),
            'expected_response_date' => optional($record->expected_response_date)->toDateString(),
            'applicant' => [
                'id' => $applicant->id,
                'name' => $applicant->name,
                // Applicant profile details are exposed only after HR verifies consent.
                'email' => $record->consent_verified ? $applicant->email : null,
                'phone' => $record->consent_verified ? ($applicant->phone_number ?: $applicant->contact_number) : null,
                'profile' => $record->consent_verified ? [
                        'first_name' => $applicant->first_name,
                        'middle_name' => $applicant->middle_name,
                        'last_name' => $applicant->last_name,
                        'suffix' => $applicant->suffix,
                        'date_of_birth' => optional($applicant->date_of_birth)->toDateString(),
                        'place_of_birth' => $applicant->place_of_birth,
                        'gender' => $applicant->sex,
                        'civil_status' => $applicant->civil_status,
                        'citizenship' => $applicant->citizenship,
                        'religion' => $applicant->religion,
                        'weight' => $applicant->weight,
                        'height' => $applicant->height,
                        'street_address' => $applicant->street_address,
                        'street_address_line2' => $applicant->street_address_line2,
                        'barangay' => $applicant->barangay,
                        'city' => $applicant->city,
                        'province' => $applicant->state_province,
                        'postal_zip_code' => $applicant->postal_zip_code,
                        'emergency_contact' => [
                            'name' => $applicant->emergency_name,
                            'relationship' => $applicant->emergency_relationship,
                            'phone' => $applicant->emergency_phone,
                            'address' => $applicant->emergency_address,
                        ],
                        'highest_education' => $applicant->highest_education,
                        'school' => $applicant->school ?: $applicant->college,
                        'course' => $applicant->course,
                        'graduation_year' => $applicant->graduation_year ?: $applicant->college_year,
                        'elementary_school' => $applicant->elementary_school,
                        'elementary_year' => $applicant->elementary_year,
                        'high_school' => $applicant->high_school,
                        'high_year' => $applicant->high_year,
                        'college' => $applicant->college,
                        'college_year' => $applicant->college_year,
                        'vocational' => $applicant->vocational,
                        'vocational_year' => $applicant->vocational_year,
                        'special_skills' => $applicant->special_skills,
                        'machine_operation' => $applicant->machine_operation,
                        'work_experience' => $applicant->employment_records ?? [],
                        'previous_employment_company' => $applicant->previous_employment_company,
                        'previous_employment_position' => $applicant->previous_employment_position,
                        'previous_employment_department' => $applicant->previous_employment_department,
                        'previous_employment_when' => $applicant->previous_employment_when,
                        'profile_photo_url' => ($applicant->profile_photo_path ?: $applicant->image)
                            ? Storage::url($applicant->profile_photo_path ?: $applicant->image)
                            : null,
                    ] : null,
            ],
            'position' => [
                'title' => $posting?->title ?: $applicant->position_applied,
                'department' => $posting?->department,
                'posting_code' => $posting?->posting_id,
                'employment_type' => $posting?->employmentType?->name,
                'work_location' => $posting?->work_location,
                'work_mode' => $posting?->work_mode,
                'work_schedule' => $posting?->work_schedule,
                'training_required' => (bool) ($posting?->training_required ?? false),
                'initial_interview_required' => (bool) ($posting?->require_initial_interview ?? false),
                'final_interview_required' => (bool) ($posting?->require_final_interview ?? false),
            ],
            'monti_interview_result' => 'Passed',
            'monti_approved_feedback' => $record->monti_approved_feedback,
            'instructions' => $record->agency_instructions,
            'documents' => $documents,
            'agency_feedback' => $record->agency_feedback,
            'monti_decision' => $record->monti_decision,
            'history' => $history,
            'can_respond' => in_array($record->status, ['Pending Agency Response', 'Ready to Refer', 'Under Review'], true),
        ]]);
    }

    public function startReview(Request $request, int $referral)
    {
        $record = $this->agencyReferral($request, $referral)->firstOrFail();
        if (! in_array($record->status, ['Pending Agency Response', 'Ready to Refer'], true)) {
            throw ValidationException::withMessages(['status' => 'This referral is no longer new.']);
        }

        DB::transaction(function () use ($request, $record) {
            $from = $record->status;
            $record->update(['status' => 'Under Review']);
            $this->record($record, 'Agency started review', $from, 'Under Review', $request, 'Agency review started.');
        });

        return back()->with('success', 'Referral moved to Pending Review.');
    }

    public function respond(Request $request, int $referral)
    {
        $data = $request->validate([
            'response' => ['required', 'in:Accepted,Declined,More Information Required'],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);
        DB::transaction(function () use ($request, $referral, $data) {
            $record = $this->agencyReferral($request, $referral)->lockForUpdate()->firstOrFail();
            if (! in_array($record->status, ['Pending Agency Response', 'Ready to Refer', 'Under Review'], true)) {
                throw ValidationException::withMessages(['status' => 'This referral has already been responded to.']);
            }
            $from = $record->status;
            $record->update([
                'status' => 'Awaiting Monti Decision',
                'agency_response' => $data['response'],
                'agency_feedback' => $data['feedback'] ?? null,
                'response_received_at' => now(),
            ]);
            $this->record($record, 'Agency response sent to Monti', $from, 'Awaiting Monti Decision', $request, $data['feedback'] ?? null);

            if (in_array($data['response'], ['Accepted', 'Declined'], true) && $record->created_by) {
                $record->loadMissing(['agency', 'applicant', 'jobPosting']);
                $decision = $data['response'] === 'Accepted' ? 'accepted' : 'declined';
                $applicantName = $record->applicant?->name ?? 'the applicant';
                $position = $record->jobPosting?->title ?: $record->applicant?->position_applied;
                $body = sprintf(
                    '%s %s the referral for %s%s.',
                    $record->agency?->name ?? 'The agency',
                    $decision,
                    $applicantName,
                    $position ? ' for '.$position : '',
                );

                Notification::notify(
                    (int) $record->created_by,
                    'agency_referral',
                    $data['response'] === 'Accepted' ? 'Agency accepted applicant referral' : 'Agency declined applicant referral',
                    $body,
                    'hrm.recruitment.agency-referrals.index',
                    (int) $record->created_by,
                );
            }
        });

        return back()->with('success', 'Your response was sent to Monti HR.');
    }

    public function downloadDocument(Request $request, int $referral, string $document)
    {
        $record = $this->agencyReferral($request, $referral)->firstOrFail();
        abort_unless($record->consent_verified, 404);

        if (str_starts_with($document, 'onboarding-')) {
            $itemId = (int) substr($document, strlen('onboarding-'));
            $selected = collect($record->shared_documents ?? [])->contains(
                fn (array $item) => ($item['source'] ?? null) === 'onboarding' && (int) ($item['id'] ?? 0) === $itemId
            );
            abort_unless($selected, 404);
            $item = HrmOnboardingItem::query()->with('templateItem')
                ->whereKey($itemId)->whereIn('status', ['Completed', 'Verified'])
                ->whereHas('templateItem', fn ($q) => $q->where('category', 'Applicant Requirements'))
                ->whereHas('onboarding', fn ($q) => $q->where('applicant_id', $record->applicant_id)->where('job_posting_id', $record->job_posting_id))
                ->firstOrFail();
            $filePath = $item->file_url ?: $item->attachment_path;
            abort_unless($filePath && Storage::disk('public')->exists($filePath), 404);

            return $this->serveAuthorizedFile($request, $filePath, $item->original_name ?: $item->attachment_name ?: basename($filePath));
        }

        abort_unless(ctype_digit($document), 404);
        $document = (int) $document;

        $selected = collect($record->shared_documents ?? [])->contains(
            fn (array $item) => ($item['source'] ?? 'applicant') === 'applicant' && (int) ($item['id'] ?? 0) === $document
        );
        abort_unless($selected, 404);

        $file = ApplicantDocument::query()
            ->where('applicant_id', $record->applicant_id)
            ->whereKey($document)->firstOrFail();
        abort_unless(Storage::disk('public')->exists($file->file_path), 404);

        return $this->serveAuthorizedFile($request, $file->file_path, $file->original_name ?: basename($file->file_path));
    }

    private function serveAuthorizedFile(Request $request, string $path, string $filename)
    {
        if ($request->boolean('view')) {
            return Storage::disk('public')->response($path, $filename, [
                'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $filename),
            ]);
        }

        return Storage::disk('public')->download($path, $filename);
    }

    private function isStatusOnlyValue(?string $value): bool
    {
        return in_array(strtolower(trim((string) $value)), [
            'submitted', 'submitted and verified', 'submitted and verified.', 'verified',
        ], true);
    }

    private function agencyReferral(Request $request, int $id)
    {
        return AgencyReferral::query()
            ->whereKey($id)
            ->where('agency_id', (int) $request->user('agency')->agency_id)
            ->where('status', '!=', 'Ready to Refer');
    }

    private function row(AgencyReferral $referral): array
    {
        $photoPath = $referral->applicant?->profile_photo_path ?: $referral->applicant?->image;

        return [
            'id' => $referral->id,
            'referral_id' => 'AR-'.str_pad((string) $referral->id, 6, '0', STR_PAD_LEFT),
            'applicant_name' => $referral->applicant?->name,
            'profile_photo_url' => $referral->consent_verified && $photoPath ? Storage::url($photoPath) : null,
            'position' => $referral->jobPosting?->title ?: $referral->applicant?->position_applied,
            'department' => $referral->jobPosting?->department,
            'referral_date' => optional($referral->referral_date)->toDateString(),
            'interview_result' => 'Passed',
            'status' => $this->agencyStatus($referral),
            'agency_response' => $referral->agency_response,
        ];
    }

    private function agencyStatus(AgencyReferral $referral): string
    {
        return match ($referral->status) {
            'Ready to Refer', 'Pending Agency Response' => 'New Referral',
            'Under Review' => 'Under Review',
            'Awaiting Monti Decision', 'Response Sent to Monti' => match ($referral->agency_response) {
                'Accepted' => 'Referral Accepted',
                'Declined' => 'Referral Denied',
                'More Information Required' => 'More Information Required',
                default => 'Response Sent to Monti',
            },
            'Confirmed for Onboarding' => 'Confirmed for Onboarding',
            'Withdrawn / Cancelled' => 'Withdrawn / Cancelled',
            default => $referral->status,
        };
    }

    private function record(AgencyReferral $referral, string $action, ?string $from, ?string $to, Request $request, ?string $notes): void
    {
        AgencyReferralHistory::create([
            'agency_referral_id' => $referral->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
            'agency_user_id' => $request->user('agency')->id,
        ]);
    }
}
