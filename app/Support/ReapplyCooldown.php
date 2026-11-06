<?php

namespace App\Support;

use App\Models\Core\User;
use App\Models\Hrm\Applicant;
use App\Models\Hrm\ApplicantJobApplication;
use App\Models\Hrm\ApplicantStatusHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Same-job 6-month re-application cooldown for interview/training failures.
 *
 * Rule: an applicant who fails an Initial or Final interview (or training)
 * for a job cannot re-apply to THAT SAME job until 6 months after the
 * failure. Other jobs stay open, and the applicant portal itself stays
 * usable (unlike terminated employees, who are fully blocked — see
 * TerminatedAccess).
 *
 * Failures are stamped on the rejected applicant_job_applications row
 * (failed_stage + failed_at). Rows without failed_at (legacy / manual
 * screening rejections) impose no cooldown and keep the old
 * immediate-reapply behaviour.
 */
class ReapplyCooldown
{
    public const COOLDOWN_MONTHS = 6;

    public const STAGE_INITIAL = 'Initial';

    public const STAGE_FINAL = 'Final';

    public const STAGE_TRAINING = 'Training';

    /**
     * Whether the failure columns exist (guard so the portal keeps working
     * when the migration has not run yet).
     */
    public static function columnsReady(): bool
    {
        try {
            return Schema::hasColumn('applicant_job_applications', 'failed_at')
                && Schema::hasColumn('applicant_job_applications', 'failed_stage');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Stamp a failure on the applicant's open application(s), marking them
     * Rejected with the stage + timestamp that starts the 6-month clock.
     *
     * @param int $applicantId
     * @param int|null $jobPostingId  when known, only that job is failed;
     *                                otherwise every open application.
     * @param string $stage  Initial|Final|Training
     */
    public static function recordFailure(int $applicantId, ?int $jobPostingId, string $stage, ?string $reason = null): void
    {
        $stage = static::normalizeStage($stage);

        $query = ApplicantJobApplication::where('applicant_id', $applicantId)
            ->whereNotIn('status', ['Withdrawn', 'Rejected'])
            ->notOnHold();
        if ($jobPostingId) {
            $query->where('job_posting_id', $jobPostingId);
        }

        $updates = ['status' => 'Rejected'];
        if (static::columnsReady()) {
            $updates['failed_stage'] = $stage;
            $updates['failed_at'] = now();
        }
        try {
            if ($reason !== null && Schema::hasColumn('applicant_job_applications', 'rejection_reason')) {
                $updates['rejection_reason'] = $reason;
            }
        } catch (\Throwable $e) {
            // Partial/test schemas — status-only update still applies.
        }

        $query->update($updates);
    }

    /**
     * Cooldown state for one applicant + one job posting.
     *
     * @return array{in_cooldown:bool,can_reapply_at:?string,failed_at:?string,failed_stage:?string}
     */
    public static function cooldownFor(int $applicantId, ?int $jobPostingId): array
    {
        $empty = ['in_cooldown' => false, 'can_reapply_at' => null, 'failed_at' => null, 'failed_stage' => null];

        if (! $jobPostingId || ! static::columnsReady()) {
            return $empty;
        }

        $failed = ApplicantJobApplication::where('applicant_id', $applicantId)
            ->where('job_posting_id', $jobPostingId)
            ->whereNotNull('failed_at')
            ->orderByDesc('failed_at')
            ->first();

        if (! $failed || ! $failed->failed_at) {
            return $empty;
        }

        $failedAt = Carbon::parse($failed->failed_at);
        $canReapplyAt = $failedAt->copy()->addMonthsNoOverflow(static::COOLDOWN_MONTHS);

        if (now()->lt($canReapplyAt)) {
            return [
                'in_cooldown' => true,
                'can_reapply_at' => $canReapplyAt->toDateString(),
                'failed_at' => $failedAt->toDateString(),
                'failed_stage' => $failed->failed_stage,
            ];
        }

        return $empty;
    }

    /**
     * Refusal message shown when re-applying inside the cooldown window.
     */
    public static function blockedMessage(array $cooldown, ?string $jobTitle = null): string
    {
        $job = $jobTitle ? " for {$jobTitle}" : '';
        $stage = $cooldown['failed_stage'] ?? null;
        $stageLabel = $stage ? " ({$stage} interview)" : '';

        return "You failed{$stageLabel}{$job} on {$cooldown['failed_at']}. "
            . 'You may re-apply to this position after '
            . Carbon::parse($cooldown['can_reapply_at'])->format('F j, Y')
            . ' (6 months). You may still apply to other positions.';
    }

    /**
     * Handle a post-hire training failure for an employee user:
     *  - company account deactivated (company-system access refused),
     *  - stamped employment_status = 'Failed' so the applicant portal
     *    stays usable (TerminatedAccess skips 'Failed' rows),
     *  - the hired job application failed with stage Training (6-month
     *    same-job clock),
     *  - applicant master archived as Rejected (reactivatable — unlike
     *    Terminated), so other jobs can be applied to.
     */
    public static function recordTrainingFailure(User $user, ?string $reason = null): void
    {
        $user->forceFill([
            'is_active' => false,
            'remember_token' => null,
            'employment_status' => TerminatedAccess::FAILED_STATUS,
        ])->save();

        $applicant = static::applicantForUser($user);

        $postingId = null;
        if ($applicant) {
            $postingId = $applicant->job_posting_id
                ?? ApplicantJobApplication::where('applicant_id', $applicant->id)
                    ->where('status', 'Hired')->latest()->value('job_posting_id')
                ?? ApplicantJobApplication::where('applicant_id', $applicant->id)
                    ->latest()->value('job_posting_id');
        }

        if ($applicant) {
            $query = ApplicantJobApplication::where('applicant_id', $applicant->id)
                ->whereNotIn('status', ['Withdrawn', 'Rejected'])
                ->notOnHold();
            if ($postingId) {
                $query->where('job_posting_id', $postingId);
            }
            $updates = ['status' => 'Rejected'];
            if (static::columnsReady()) {
                $updates['failed_stage'] = static::STAGE_TRAINING;
                $updates['failed_at'] = now();
            }
            try {
                if ($reason !== null && Schema::hasColumn('applicant_job_applications', 'rejection_reason')) {
                    $updates['rejection_reason'] = $reason;
                }
            } catch (\Throwable $e) {
                // Never break the failure flow on schema introspection.
            }
            $query->update($updates);

            $from = $applicant->status;
            $applicant->forceFill(['status' => 'Rejected', 'archived' => true])->save();
            ApplicantStatusHistory::create([
                'applicant_id' => $applicant->id, 'from_status' => $from,
                'to_status' => 'Rejected', 'changed_by' => auth()->id(),
                'reason' => $reason ?? 'Failed training — 6-month same-job cooldown',
            ]);
            NotifiesApplicant::push(
                $applicant,
                'rejection',
                'Training Result',
                'Unfortunately you did not pass training. You may re-apply to this position after 6 months; other positions remain open.',
                ['position' => $applicant->position_applied, 'status' => 'Rejected']
            );
        }
    }

    /**
     * The applicant master behind an employee (personal email first,
     * company email fallback, hired_user_id last).
     */
    public static function applicantForUser(User $user): ?Applicant
    {
        $candidates = collect([
            TerminatedAccess::normalize($user->personal_email),
            TerminatedAccess::normalize($user->email),
        ])->filter()->unique()->values();

        foreach ($candidates as $email) {
            $found = Applicant::whereRaw('LOWER(email) = ?', [$email])->orderByDesc('id')->first();
            if ($found) {
                return $found;
            }
        }

        return Applicant::where('hired_user_id', $user->id)->orderByDesc('id')->first();
    }

    public static function normalizeStage(?string $stage): string
    {
        $s = strtolower(trim((string) $stage));
        if (str_contains($s, 'final')) {
            return static::STAGE_FINAL;
        }
        if (str_contains($s, 'train')) {
            return static::STAGE_TRAINING;
        }

        return static::STAGE_INITIAL;
    }
}
