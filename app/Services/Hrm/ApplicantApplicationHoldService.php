<?php

namespace App\Services\Hrm;

use App\Models\Hrm\ApplicantJobApplication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Automatic hold for an applicant's other active job applications once one
 * application reaches onboarding.
 *
 * The application's real `status` is preserved. Held rows are parked with
 * `is_on_hold`, the triggering application and the stage they were in before
 * the hold, so they can be restored deterministically later.
 */
class ApplicantApplicationHoldService
{
    public const ONBOARDING_STATUSES = ['Onboarding', 'Onboard'];

    public const TERMINAL_STATUSES = ['Hired', 'Rejected', 'Withdrawn', 'Cancelled'];

    public const HOLD_MESSAGE = 'This job application is on hold because another application is already in onboarding.';

    /**
     * Guard so the portal and older test schemas keep working before the
     * hold migration has run.
     */
    public static function columnsReady(): bool
    {
        try {
            return Schema::hasColumn('applicant_job_applications', 'is_on_hold')
                && Schema::hasColumn('applicant_job_applications', 'previous_status')
                && Schema::hasColumn('applicant_job_applications', 'hold_reason')
                && Schema::hasColumn('applicant_job_applications', 'held_by_application_id');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function isOnboardingStatus(?string $status): bool
    {
        return in_array(strtolower(trim((string) $status)), array_map('strtolower', self::ONBOARDING_STATUSES), true);
    }

    public static function isTerminalStatus(?string $status): bool
    {
        return in_array(strtolower(trim((string) $status)), array_map('strtolower', self::TERMINAL_STATUSES), true);
    }

    /**
     * Exclude held job applications from a query that targets that table.
     */
    public static function withoutHolds(Builder $query, string $column = 'is_on_hold'): Builder
    {
        if (! static::columnsReady()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($column) {
            $q->where($column, false)->orWhereNull($column);
        });
    }

    /**
     * Keep on-hold applications out of HRM interview lists. An interview is
     * considered held when its applicant/job posting matches a held row.
     */
    public static function excludeHeldInterviews(Builder $query, string $interviewTable = 'hrm_interviews'): Builder
    {
        if (! static::columnsReady()) {
            return $query;
        }

        return $query->whereNotExists(function ($sub) use ($interviewTable) {
            $sub->select(DB::raw(1))
                ->from('applicant_job_applications as held_ja')
                ->whereColumn('held_ja.applicant_id', "{$interviewTable}.applicant_id")
                ->where('held_ja.is_on_hold', true)
                ->where(function ($match) use ($interviewTable) {
                    $match->whereColumn('held_ja.job_posting_id', "{$interviewTable}.job_posting_id")
                        ->orWhereNull("{$interviewTable}.job_posting_id");
                });
        });
    }

    /**
     * Keep on-hold applications out of the onboarding list.
     */
    public static function excludeHeldOnboardings(Builder $query, string $onboardingTable = 'hrm_onboardings'): Builder
    {
        if (! static::columnsReady()) {
            return $query;
        }

        return $query->whereNotExists(function ($sub) use ($onboardingTable) {
            $sub->select(DB::raw(1))
                ->from('applicant_job_applications as held_ja')
                ->whereColumn('held_ja.applicant_id', "{$onboardingTable}.applicant_id")
                ->where('held_ja.is_on_hold', true)
                ->whereColumn('held_ja.job_posting_id', "{$onboardingTable}.job_posting_id");
        });
    }

    /**
     * Whether the given applicant/job application is currently held.
     */
    public static function isHeldFor(int $applicantId, ?int $jobPostingId = null, ?int $jobApplicationId = null): bool
    {
        if (! static::columnsReady()) {
            return false;
        }

        return ApplicantJobApplication::query()
            ->where('applicant_id', $applicantId)
            ->when($jobApplicationId, fn (Builder $q) => $q->whereKey($jobApplicationId))
            ->when(! $jobApplicationId && $jobPostingId, fn (Builder $q) => $q->where('job_posting_id', $jobPostingId))
            ->when(! $jobApplicationId && ! $jobPostingId, fn (Builder $q) => $q->where('is_on_hold', true))
            ->where('is_on_hold', true)
            ->exists();
    }

    /**
     * Called by the model after a status change. Keeps holds in sync no matter
     * which controller performed the single-row update.
     */
    public static function afterStatusChange(ApplicantJobApplication $application): void
    {
        if (! static::columnsReady()) {
            return;
        }

        $previous = $application->getOriginal('status');
        $current = $application->status;
        if ((string) $previous === (string) $current) {
            return;
        }

        if (static::isOnboardingStatus($current)) {
            static::clearHold($application);
            static::holdSiblings($application);

            return;
        }

        if (static::isOnboardingStatus($previous)) {
            if (strtolower((string) $current) === 'hired') {
                static::closeHeldForTrigger($application);
            } else {
                static::releaseHeldForTrigger($application);
            }
            static::clearHold($application);

            return;
        }

        if (static::isTerminalStatus($current)) {
            static::clearHold($application);
        }
    }

    /**
     * Called by the model after a row is created. A new application submitted
     * while the applicant already has an onboarding trigger must start held.
     */
    public static function afterCreated(ApplicantJobApplication $application): void
    {
        if (! static::columnsReady()) {
            return;
        }

        DB::transaction(function () use ($application) {
            DB::table('applicants')->where('id', $application->applicant_id)->lockForUpdate()->first();
            if (static::isOnboardingStatus($application->status)) {
                static::clearHold($application);
                static::holdSiblings($application);

                return;
            }

            if (static::isTerminalStatus($application->status) || $application->is_on_hold) {
                return;
            }

            $trigger = static::currentOnboardingTrigger((int) $application->applicant_id, (int) $application->id);
            if ($trigger) {
                static::holdApplication($application, $trigger);
            }
        });
    }

    /**
     * Hold every other active application for the applicant.
     *
     * @return int Number of rows newly held or re-bound to this trigger.
     */
    public static function holdSiblings(ApplicantJobApplication $trigger): int
    {
        if (! static::columnsReady()) {
            return 0;
        }

        return DB::transaction(function () use ($trigger) {
            // Serialize all onboarding handoffs for one applicant. Locking the
            // parent row also protects the empty/sibling query from two
            // concurrent requests choosing different triggers.
            DB::table('applicants')->where('id', $trigger->applicant_id)->lockForUpdate()->first();
            $fresh = ApplicantJobApplication::whereKey($trigger->id)->lockForUpdate()->first();
            if (! $fresh || ! static::isOnboardingStatus($fresh->status)) {
                return 0;
            }

            // If two onboarding transitions raced, the first committed trigger
            // owns the holds. Park the later application while retaining its
            // stage, so it cannot proceed through HRM concurrently.
            $existingTrigger = static::currentOnboardingTrigger((int) $fresh->applicant_id, (int) $fresh->id);
            if ($existingTrigger) {
                static::holdApplication($fresh, $existingTrigger);
                return 0;
            }

            if ($fresh->is_on_hold) {
                static::clearHold($fresh);
            }

            $reason = static::holdReasonFor($fresh);
            $count = 0;

            $siblings = ApplicantJobApplication::query()
                ->where('applicant_id', $fresh->applicant_id)
                ->whereKeyNot($fresh->id)
                ->whereNotIn('status', self::TERMINAL_STATUSES)
                ->lockForUpdate()
                ->get();

            foreach ($siblings as $sibling) {
                $sameTrigger = $sibling->is_on_hold
                    && (int) $sibling->held_by_application_id === (int) $fresh->id;

                if ($sameTrigger && $sibling->hold_reason === $reason) {
                    continue;
                }

                $attributes = [
                    'is_on_hold' => true,
                    'hold_reason' => $reason,
                    'held_by_application_id' => $fresh->id,
                ];
                if (! $sibling->is_on_hold || ! $sibling->previous_status) {
                    $attributes['previous_status'] = $sibling->status;
                }

                $sibling->forceFill($attributes)->saveQuietly();
                $count++;
            }

            return $count;
        });
    }

    /**
     * Release applications held by a trigger that was withdrawn/rejected or
     * otherwise left onboarding without being hired.
     */
    public static function releaseHeldForTrigger(ApplicantJobApplication $trigger): int
    {
        if (! static::columnsReady()) {
            return 0;
        }

        return DB::transaction(function () use ($trigger) {
            DB::table('applicants')->where('id', $trigger->applicant_id)->lockForUpdate()->first();
            $released = 0;
            $held = ApplicantJobApplication::query()
                ->where('held_by_application_id', $trigger->id)
                ->where('is_on_hold', true)
                ->lockForUpdate()
                ->get();

            $otherTrigger = static::currentOnboardingTrigger((int) $trigger->applicant_id, (int) $trigger->id);
            if ($otherTrigger) {
                foreach ($held as $application) {
                    $application->forceFill([
                        'is_on_hold' => true,
                        'hold_reason' => static::holdReasonFor($otherTrigger),
                        'held_by_application_id' => $otherTrigger->id,
                    ])->saveQuietly();
                }

                return 0;
            }

            foreach ($held as $application) {
                static::release($application);
                $released++;
            }

            return $released;
        });
    }

    /**
     * A hire closes the applicant's other held applications instead of
     * reopening them.
     */
    public static function closeHeldForTrigger(ApplicantJobApplication $trigger): int
    {
        if (! static::columnsReady()) {
            return 0;
        }

        return DB::transaction(function () use ($trigger) {
            DB::table('applicants')->where('id', $trigger->applicant_id)->lockForUpdate()->first();
            return static::closeHeld(
                ApplicantJobApplication::query()
                    ->where('held_by_application_id', $trigger->id)
                    ->where('is_on_hold', true)
            );
        });
    }

    public static function closeAllHeldForApplicant(int $applicantId): int
    {
        if (! static::columnsReady()) {
            return 0;
        }

        return DB::transaction(function () use ($applicantId) {
            DB::table('applicants')->where('id', $applicantId)->lockForUpdate()->first();
            return static::closeHeld(
                ApplicantJobApplication::query()
                    ->where('applicant_id', $applicantId)
                    ->where('is_on_hold', true)
            );
        });
    }

    /**
     * Reconcile holds after query-builder/mass status changes, which bypass
     * model events. Releases or closes holds whose trigger has left onboarding.
     */
    public static function reconcileApplicantHolds(int $applicantId): void
    {
        if (! static::columnsReady()) {
            return;
        }

        DB::transaction(function () use ($applicantId) {
            DB::table('applicants')->where('id', $applicantId)->lockForUpdate()->first();
            $activeTrigger = static::currentOnboardingTrigger($applicantId);
            $hasHire = ApplicantJobApplication::where('applicant_id', $applicantId)
                ->where('status', 'Hired')->exists();

            $held = ApplicantJobApplication::where('applicant_id', $applicantId)
                ->where('is_on_hold', true)->lockForUpdate()->get();

            foreach ($held as $application) {
                if (static::isTerminalStatus($application->status)) {
                    static::clearHold($application);
                    continue;
                }

                $trigger = $application->held_by_application_id
                    ? ApplicantJobApplication::find($application->held_by_application_id)
                    : null;

                if ($trigger && static::isOnboardingStatus($trigger->status)) {
                    continue;
                }

                if (! $activeTrigger && $hasHire) {
                    static::close($application);
                    continue;
                }

                if ($activeTrigger && (int) $activeTrigger->id !== (int) $application->held_by_application_id) {
                    $application->forceFill([
                        'is_on_hold' => true,
                        'hold_reason' => static::holdReasonFor($activeTrigger),
                        'held_by_application_id' => $activeTrigger->id,
                    ])->saveQuietly();

                    continue;
                }

                static::release($application);
            }
        });
    }

    /**
     * The live onboarding application that owns the holds.
     */
    public static function currentOnboardingTrigger(int $applicantId, ?int $exceptId = null): ?ApplicantJobApplication
    {
        if (! static::columnsReady()) {
            return null;
        }

        return ApplicantJobApplication::query()
            ->where('applicant_id', $applicantId)
            ->when($exceptId, fn (Builder $q) => $q->whereKeyNot($exceptId))
            ->whereIn('status', self::ONBOARDING_STATUSES)
            ->where('is_on_hold', false)
            ->latest()
            ->first();
    }

    public static function holdReasonFor(ApplicantJobApplication $trigger): string
    {
        $title = null;
        try {
            $title = $trigger->jobPosting?->title;
        } catch (\Throwable $e) {
            // Relation may be unavailable in a minimal schema.
        }

        return $title
            ? "Applicant is already in onboarding for {$title}."
            : 'Applicant is already in onboarding for another position.';
    }

    private static function holdApplication(ApplicantJobApplication $application, ApplicantJobApplication $trigger): void
    {
        if ((int) $application->id === (int) $trigger->id
            || static::isTerminalStatus($application->status)
            || ! static::isOnboardingStatus($trigger->status)) {
            return;
        }

        $application->forceFill([
            'is_on_hold' => true,
            'hold_reason' => static::holdReasonFor($trigger),
            'held_by_application_id' => $trigger->id,
            'previous_status' => $application->previous_status ?: $application->status,
        ])->saveQuietly();
    }

    private static function clearHold(ApplicantJobApplication $application): void
    {
        if (! $application->is_on_hold
            && $application->hold_reason === null
            && $application->held_by_application_id === null
            && $application->previous_status === null) {
            return;
        }

        $application->forceFill([
            'is_on_hold' => false,
            'hold_reason' => null,
            'held_by_application_id' => null,
            'previous_status' => null,
        ])->saveQuietly();
    }

    private static function release(ApplicantJobApplication $application): void
    {
        $restore = (string) ($application->previous_status ?? '');
        if ($restore === ''
            || static::isTerminalStatus($restore)
            || static::isOnboardingStatus($restore)) {
            $restore = 'Submitted';
        }

        $application->forceFill([
            'status' => $restore,
            'is_on_hold' => false,
            'hold_reason' => null,
            'held_by_application_id' => null,
            'previous_status' => null,
        ])->saveQuietly();
    }

    private static function close(ApplicantJobApplication $application): void
    {
        $attributes = [
            'status' => 'Withdrawn',
            'is_on_hold' => false,
            'hold_reason' => null,
            'held_by_application_id' => null,
            'previous_status' => null,
        ];

        try {
            if (Schema::hasColumn('applicant_job_applications', 'rejection_reason')) {
                $attributes['rejection_reason'] = 'Closed because the applicant was hired for another position.';
            }
        } catch (\Throwable $e) {
            // Partial/test schemas.
        }

        $application->forceFill($attributes)->saveQuietly();
    }

    private static function closeHeld(Builder $query): int
    {
        $closed = 0;
        foreach ($query->lockForUpdate()->get() as $application) {
            static::close($application);
            $closed++;
        }

        return $closed;
    }
}
