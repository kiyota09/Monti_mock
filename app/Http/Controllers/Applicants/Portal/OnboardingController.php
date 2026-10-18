<?php

namespace App\Http\Controllers\Applicants\Portal;

use App\Http\Controllers\Controller;
use App\Models\Hrm\HrmOnboarding;
use App\Models\Hrm\HrmOnboardingActivity;
use App\Models\Hrm\HrmOnboardingItem;
use App\Support\NotifiesApplicant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Official APPLICANTS onboarding tracker
 * (Dashboard/APPLICANTS/Onboarding/index.vue).
 * Candidates submit requirements and confirm attendance; HR verifies.
 */
class OnboardingController extends Controller
{
    public function index(Request $request)
    {
        $applicant = $request->user('applicant');
        $all = HrmOnboarding::with(['items.templateItem', 'activities', 'template', 'jobPosting'])
            ->where(fn ($q) => $q->where('applicant_id', $applicant->id)->orWhere('email', $applicant->email))
            ->latest()->get();

        if ($all->isEmpty()) {
            return Inertia::render('Dashboard/APPLICANTS/Onboarding/index', [
                'active' => false,
                'onboarding' => null,
                'onboardings' => [],
                'selectedId' => null,
            ]);
        }

        // Per-job table rows: one row per onboarding record (each tied to the
        // job the applicant applied for via job_posting_id / position).
        // Kick off each countdown on first portal visit + remind when close.
        foreach ($all as $record) {
            $this->syncDuration($record, $applicant);
        }

        $rows = $all->map(fn ($o) => [
            'id' => $o->id,
            'job' => $o->jobPosting?->title ?? $o->position ?? '—',
            'position' => $o->position ?? '—',
            'department' => $o->department ?? $o->jobPosting?->department ?? '—',
            'status' => $o->status,
            'progress' => (int) $o->progress,
            'start_date' => optional($o->start_date)?->toDateString(),
            'updated_at' => optional($o->updated_at)?->toDateTimeString(),
        ])->values()->all();

        // Detail panel follows the selected row (defaults to the latest).
        $onboarding = $all->firstWhere('id', (int) $request->get('onboarding_id')) ?? $all->first();

        return Inertia::render('Dashboard/APPLICANTS/Onboarding/index', [
            'active' => true,
            'selectedId' => $onboarding->id,
            'onboardings' => $rows,
            'onboarding' => $this->detailPayload($onboarding),
        ]);
    }

    /**
     * Row-click detail for the portal table modal (JSON, ownership-checked).
     */
    public function show(Request $request, HrmOnboarding $onboarding)
    {
        $applicant = $request->user('applicant');
        abort_unless(
            $onboarding->applicant_id === $applicant->id || $onboarding->email === $applicant->email,
            404
        );
        $onboarding->loadMissing(['items.templateItem', 'activities', 'template', 'jobPosting']);

        return response()->json(['data' => $this->detailPayload($onboarding)]);
    }

    private function detailPayload(HrmOnboarding $onboarding): array
    {
        $sections = [];
        foreach ($onboarding->items as $item) {
            // Applicant-visible gating (ported from super_monti): HR-only
            // tasks (applicant_visible = false) stay hidden in the portal.
            if ($item->templateItem && ! (bool) $item->templateItem->applicant_visible) {
                continue;
            }
            $section = $item->templateItem?->category ?? 'General Requirements';
            // HR-internal sections stay inside the HR module — the applicant
            // portal only shows Applicant Requirements (plus legacy
            // uncategorised items).
            if (in_array($section, ['HR Processing', 'Company Preparation'], true)) {
                continue;
            }
            $ti = $item->templateItem;
            $sections[$section][] = [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $ti?->description,
                'due_date' => optional($item->due_date)?->toDateString(),
                'is_required' => (bool) ($ti?->is_required ?? true),
                'input_type' => $this->inputType($ti?->input_type),
                'options' => $this->normaliseOptions($ti?->options),
                'status' => $item->status,
                // HR-owned task when explicitly internal OR owned by non-applicant party.
                'is_task' => $ti ? (! (bool) $ti->applicant_visible || ($ti->responsible_party && $ti->responsible_party !== 'Applicant')) : false,
                'responsible_party' => $ti?->responsible_party,
                'latest_submission' => ($item->file_url || $item->value || $item->submitted_at) ? [
                    'file_url' => $item->file_url ? Storage::url($item->file_url) : null,
                    'original_name' => $item->original_name,
                    'value' => $item->value,
                    'submitted_at' => optional($item->submitted_at)?->toDateTimeString(),
                    'rejection_reason' => $item->rejection_reason,
                ] : null,
            ];
        }

        return [
            'id' => $onboarding->id,
            'name' => $onboarding->name,
            'job' => $onboarding->jobPosting?->title ?? $onboarding->position,
            'position' => $onboarding->position,
            'department' => $onboarding->department ?? $onboarding->jobPosting?->department,
            'template' => $onboarding->template?->name,
            'start_date' => optional($onboarding->start_date)?->toDateString(),
            'expected_start_date' => optional($onboarding->expected_start_date)?->toDateString(),
            'work_location' => $onboarding->work_location,
            'work_schedule' => $onboarding->work_schedule,
            'assigned_manager' => $onboarding->assigned_manager,
            'status' => $onboarding->status,
            'progress' => (int) $onboarding->progress,
            'duration_days' => $onboarding->duration_days,
            'duration_started_at' => optional($onboarding->duration_started_at)?->toDateTimeString(),
            'duration_end_date' => $onboarding->duration_days && $onboarding->duration_started_at
                ? Carbon::parse($onboarding->duration_started_at)->addDays((int) $onboarding->duration_days)->toDateString()
                : null,
            'duration_days_left' => $this->durationDaysLeft($onboarding),
            'activities' => $onboarding->activities->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'type' => $a->type,
                'status' => $a->status,
                'schedule_date' => optional($a->schedule_date)?->toDateString(),
                'start_time' => $a->start_time,
                'end_time' => $a->end_time,
                'method' => $a->method,
                'location' => $a->location,
                'meeting_link' => $a->meeting_link,
                'facilitator' => $a->facilitator,
                'organizer' => $a->organizer,
                'is_required' => (bool) $a->is_required,
                'attendance_status' => $a->attendance_status,
            ])->values()->all(),
            'sections' => $sections,
        ];
    }

    /**
     * Applicant portal onboarding countdown.
     *
     * The template sets a duration in days. The clock starts the first time
     * the applicant opens the onboarding portal, and once fewer than 4 days
     * remain (i.e. within 3 days of the end) a single reminder notification
     * is pushed to the applicant portal.
     */
    private function syncDuration(HrmOnboarding $onboarding, $applicant): void
    {
        if (! $onboarding->duration_days) {
            return;
        }
        if (! $onboarding->duration_started_at) {
            $onboarding->duration_started_at = now();
            $onboarding->save();
        }
        if ($onboarding->status === 'Completed' || $onboarding->duration_reminder_sent_at) {
            return;
        }
        $end = Carbon::parse($onboarding->duration_started_at)->addDays((int) $onboarding->duration_days);
        $daysLeft = now()->startOfDay()->diffInDays($end->startOfDay(), false);
        if ($daysLeft <= 3) {
            NotifiesApplicant::push(
                $applicant,
                'onboarding',
                'Onboarding Ending Soon',
                'Your onboarding is about to reach the end of its ' . $onboarding->duration_days
                    . '-day duration (ends ' . $end->toFormattedDateString() . '). Please complete any remaining requirements.',
                [
                    'onboarding_id' => $onboarding->id,
                    'end_date' => $end->toDateString(),
                    'days_left' => max(0, $daysLeft),
                    'status' => 'Onboarding',
                ]
            );
            $onboarding->duration_reminder_sent_at = now();
            $onboarding->save();
        }
    }

    private function durationDaysLeft(HrmOnboarding $onboarding): ?int
    {
        if (! $onboarding->duration_days || ! $onboarding->duration_started_at) {
            return null;
        }
        $end = Carbon::parse($onboarding->duration_started_at)->addDays((int) $onboarding->duration_days);

        return (int) now()->startOfDay()->diffInDays($end->startOfDay(), false);
    }

    public function submitItem(Request $request, HrmOnboardingItem $item)
    {
        $this->owned($request, $item);
        // Block submissions for HR-owned tasks.
        $ti = $item->templateItem;
        if ($ti && (! (bool) $ti->applicant_visible || ($ti->responsible_party && $ti->responsible_party !== 'Applicant'))) {
            return back()->withErrors(['item' => 'This item is completed by HR.']);
        }
        // Per-input-type validation: file requires a file (pdf/jpg/png ≤5MB);
        // text/date/select/checkbox validate the entered value.
        $kind = strtolower((string) ($ti?->input_type ?? 'text'));
        $rules = ['notes' => 'nullable|string|max:1000'];
        if ($kind === 'file') {
            $rules['file'] = 'required|file|mimes:pdf,jpg,jpeg,png,gif,webp|max:5120';
            $rules['value'] = 'nullable|string|max:2000';
        } elseif ($kind === 'date') {
            $rules['value'] = 'required|date';
            $rules['file'] = 'nullable|file|max:5120';
        } elseif ($kind === 'checkbox') {
            // Accept boolean true, "1", "true", "yes", "on", "agreed".
            $rules['value'] = 'required';
            $rules['file'] = 'nullable|file|max:5120';
        } elseif ($kind === 'select') {
            $rules['value'] = 'required|string|max:255';
            $rules['file'] = 'nullable|file|max:5120';
        } else {
            $rules['value'] = 'required|string|max:2000';
            $rules['file'] = 'nullable|file|max:5120';
        }
        $data = $request->validate($rules);

        // Normalise checkbox to a stable stored string ("1" = agreed).
        $value = $data['value'] ?? null;
        if ($kind === 'checkbox') {
            $truthy = [true, 1, '1', 'true', 'True', 'TRUE', 'yes', 'Yes', 'YES', 'on', 'On', 'ON', 'agreed', 'Agreed', 'AGREED'];
            if (! in_array($value, $truthy, true)) {
                return back()->withErrors(['value' => 'Please confirm by checking the box.']);
            }
            $value = '1';
        }
        // If select options are defined on the template, enforce them.
        if ($kind === 'select') {
            $allowed = $this->normaliseOptions($ti?->options);
            if ($allowed !== null && ! in_array((string) $value, $allowed, true)) {
                return back()->withErrors(['value' => 'Invalid selection.']);
            }
            $value = (string) $value;
        }

        $update = ['submitted_at' => now(), 'status' => 'Submitted', 'rejection_reason' => null];
        if ($request->hasFile('file')) {
            $update['file_url'] = $request->file('file')->store('onboarding/submissions', 'public');
            $update['original_name'] = $request->file('file')->getClientOriginalName();
        }
        if ($value !== null && $value !== '') {
            $update['value'] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        }
        $item->update($update);

        return back()->with('success', 'Requirement submitted for HR verification.');
    }

    public function attendActivity(Request $request, HrmOnboardingActivity $activity)
    {
        $this->ownedActivity($request, $activity);
        // Terminal-state guard (ported from super_monti).
        if (in_array($activity->status, ['Completed', 'Cancelled', 'No Show', 'NoShow'], true)) {
            return back()->withErrors(['activity' => "This activity is already {$activity->status}."]);
        }
        $activity->update(['attendance_status' => 'Confirmed']);

        return back()->with('success', 'Attendance confirmed.');
    }

    /**
     * Canonical input types used by both the template builder and the
     * applicant portal: text, file, date, checkbox, select.
     * Always returns lowercase so the Vue `v-if` branches match.
     */
    private function inputType(?string $raw): string
    {
        $kind = strtolower(trim((string) $raw));
        // Back-compat with legacy labels.
        $kind = match ($kind) {
            'document upload', 'document', 'upload' => 'file',
            'datetime', 'time' => 'date',
            'dropdown', 'form', 'selection' => 'select',
            'acknowledgement', 'acknowledgment', 'agree', 'boolean' => 'checkbox',
            'number', 'textarea', 'signature' => 'text',
            default => $kind,
        };

        return in_array($kind, ['text', 'file', 'date', 'checkbox', 'select'], true) ? $kind : 'text';
    }

    private function normaliseOptions(mixed $options): ?array
    {
        if (is_string($options)) {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : preg_split('/[\r\n,]+/', $options);
        }
        if (! is_array($options)) {
            return null;
        }
        $cleaned = array_values(array_unique(array_filter(array_map(fn ($o) => trim((string) $o), $options), fn ($o) => $o !== '')));

        return $cleaned === [] ? null : $cleaned;
    }

    private function owned(Request $request, HrmOnboardingItem $item): void
    {
        $applicant = $request->user('applicant');
        $owner = $item->onboarding;
        abort_unless(
            $owner && ($owner->applicant_id === $applicant->id || $owner->email === $applicant->email),
            404
        );
    }

    private function ownedActivity(Request $request, HrmOnboardingActivity $activity): void
    {
        $applicant = $request->user('applicant');
        $owner = $activity->onboarding;
        abort_unless(
            $owner && ($owner->applicant_id === $applicant->id || $owner->email === $applicant->email),
            404
        );
    }
}
