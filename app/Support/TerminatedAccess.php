<?php

namespace App\Support;

use App\Models\Core\User;
use App\Models\Hrm\Applicant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single source of truth for terminated-employee access revocation.
 *
 * Requirement: once an employee is terminated —
 *  1. their system-generated company email (users.email) must not be able
 *     to access the company system, and
 *  2. their personal email must not be able to access the applicant portal.
 *
 * Termination is represented by users.is_active = false (set by every
 * deactivate/disable/suspend path). employment_status = 'Terminated' is
 * stamped when known. Legacy rows without employment_status are still
 * covered. Reactivation (is_active = true) automatically lifts the block.
 *
 * Training failures are deliberately EXCLUDED from the applicant-portal
 * block: users stamped employment_status = 'Failed' keep portal access
 * and are instead governed by the per-job 6-month re-application cooldown
 * (see ReapplyCooldown) — they may apply to other jobs, just not the one
 * they failed for 6 months.
 */
class TerminatedAccess
{
    public const TERMINATED_STATUS = 'Terminated';

    public const FAILED_STATUS = 'Failed';

    public const COMPANY_BLOCK_MESSAGE = 'This account has been deactivated. Please contact HR.';

    public const APPLICANT_BLOCK_MESSAGE = 'This email belongs to a terminated employee and can no longer access the applicant portal. Please contact HR.';

    /**
     * Normalize an email for case-insensitive comparison.
     */
    public static function normalize(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    /**
     * Whether a company (users) account is blocked from the company system.
     */
    public static function isCompanyBlocked(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return isset($user->is_active) && ! $user->is_active;
    }

    /**
     * Whether an email address belongs to a terminated/inactive employee
     * and must be refused by the applicant portal.
     *
     * Matches (case-insensitive) against:
     *  - users.email (system-generated company email), and
     *  - users.personal_email (the personal email used in the applicant portal)
     * for every inactive user row — except training failures
     * (employment_status = 'Failed'), which keep portal access under the
     * 6-month same-job cooldown (see ReapplyCooldown).
     */
    public static function isApplicantBlockedByEmail(?string $email): bool
    {
        $needle = static::normalize($email);
        if ($needle === '') {
            return false;
        }

        // Test/partial schemas may not have the users table — never break
        // the applicant flow on introspection.
        try {
            if (! Schema::hasTable('users')) {
                return false;
            }

            return User::query()
                ->where('is_active', false)
                ->where(function ($q) {
                    $q->whereNull('employment_status')
                        ->orWhere('employment_status', '!=', static::FAILED_STATUS);
                })
                ->where(function ($q) use ($needle) {
                    $q->whereRaw('LOWER(email) = ?', [$needle])
                        ->orWhereRaw('LOWER(personal_email) = ?', [$needle]);
                })
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The inactive user row behind a blocked applicant email, if any.
     */
    public static function terminatedUserForEmail(?string $email): ?User
    {
        $needle = static::normalize($email);
        if ($needle === '') {
            return null;
        }

        try {
            if (! Schema::hasTable('users')) {
                return null;
            }

            return User::query()
                ->where('is_active', false)
                ->where(function ($q) {
                    $q->whereNull('employment_status')
                        ->orWhere('employment_status', '!=', static::FAILED_STATUS);
                })
                ->where(function ($q) use ($needle) {
                    $q->whereRaw('LOWER(email) = ?', [$needle])
                        ->orWhereRaw('LOWER(personal_email) = ?', [$needle]);
                })
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Fully revoke a terminated employee's access:
     *  - remember-me token cleared so "remember me" cookies stop working,
     *  - employment_status stamped Terminated + last working day,
     *  - every linked applicant row (personal email, company email, or
     *    hired_user_id) archived with status Terminated so the applicant
     *    portal login/registration/reactivation all refuse it.
     */
    public static function revoke(User $user, ?string $reason = null): void
    {
        $updates = [
            'remember_token' => null,
        ];

        // Stamp termination markers when the schema has the columns.
        try {
            if (DB::getSchemaBuilder()->hasColumn('users', 'employment_status')) {
                $updates['employment_status'] = self::TERMINATED_STATUS;
            }
            if (DB::getSchemaBuilder()->hasColumn('users', 'last_working_day') && empty($user->last_working_day)) {
                $updates['last_working_day'] = now()->toDateString();
            }
            if (DB::getSchemaBuilder()->hasColumn('users', 'resignation_date') && empty($user->resignation_date)) {
                $updates['resignation_date'] = now()->toDateString();
            }
        } catch (\Throwable $e) {
            // Schema introspection must never break termination.
        }

        // Preserve reason context in skills/languages? No — keep it minimal.
        unset($reason);

        $user->forceFill($updates)->save();

        static::archiveApplicantsFor($user);
    }

    /**
     * Archive applicant-portal rows tied to this employee so the personal
     * email (and the company email, in case it is tried there) can no
     * longer log in, register, verify OTP, or reactivate.
     */
    public static function archiveApplicantsFor(User $user): void
    {
        $emails = collect([$user->personal_email, $user->email])
            ->map(fn ($e) => static::normalize($e))
            ->filter()
            ->unique()
            ->values();

        if ($emails->isEmpty() && ! $user->id) {
            return;
        }

        $query = Applicant::query();

        $query->where(function ($q) use ($emails, $user) {
            foreach ($emails as $email) {
                $q->orWhereRaw('LOWER(email) = ?', [$email]);
            }
            if ($user->id) {
                $q->orWhere('hired_user_id', $user->id);
            }
        });

        foreach ($query->get() as $applicant) {
            $applicant->forceFill([
                'archived' => true,
                'status' => self::TERMINATED_STATUS,
            ])->save();
        }
    }

    /**
     * Lift the applicant-portal block when an employee is reactivated
     * (is_active flipped back to true by HR/IT).
     */
    public static function restoreApplicantsFor(User $user): void
    {
        $emails = collect([$user->personal_email, $user->email])
            ->map(fn ($e) => static::normalize($e))
            ->filter()
            ->unique()
            ->values();

        if ($emails->isEmpty() && ! $user->id) {
            return;
        }

        $query = Applicant::where('status', self::TERMINATED_STATUS);
        $query->where(function ($q) use ($emails, $user) {
            foreach ($emails as $email) {
                $q->orWhereRaw('LOWER(email) = ?', [$email]);
            }
            if ($user->id) {
                $q->orWhere('hired_user_id', $user->id);
            }
        });

        foreach ($query->get() as $applicant) {
            $applicant->forceFill([
                'archived' => false,
                'status' => 'Submitted',
            ])->save();
        }
    }
}
