<?php

namespace App\Support;

use App\Models\Hrm\HrmOnboarding;
use App\Models\Hrm\HrmOnboardingTemplate;
use Carbon\Carbon;

/**
 * Copies a template definition into a concrete onboarding record.
 *
 * Templates (hrm_onboarding_template_items / _activities) are blueprints.
 * Per-hire progress lives on hrm_onboarding_items / _activities, so HR and
 * the applicant portal both read the snapshot — never the template itself.
 */
class OnboardingSnapshot
{
    /**
     * Snapshot active template rows into the onboarding.
     * No-op when the onboarding already has items (never duplicates).
     *
     * @return array{items: int, activities: int, skipped: bool}
     */
    public static function apply(HrmOnboarding $onboarding, HrmOnboardingTemplate $template): array
    {
        if ($onboarding->items()->exists() || $onboarding->activities()->exists()) {
            return ['items' => 0, 'activities' => 0, 'skipped' => true];
        }

        $template->loadMissing(['items', 'activities']);

        $base = $onboarding->start_date
            ?? $onboarding->expected_start_date
            ?? now();

        $items = 0;
        foreach ($template->items->where('is_active', true)->values() as $ti) {
            $onboarding->items()->create([
                'template_item_id' => $ti->id,
                'name' => $ti->name,
                'status' => 'Pending',
                'due_date' => self::dueDate($ti->due_rule_type, $ti->due_rule_value, $base),
            ]);
            $items++;
        }

        $activities = 0;
        foreach ($template->activities->where('is_active', true)->values() as $ta) {
            $onboarding->activities()->create([
                'template_activity_id' => $ta->id,
                'title' => $ta->title,
                'type' => $ta->type,
                'description' => $ta->description,
                'method' => $ta->default_method,
                'status' => 'Pending',
                'attendance_status' => 'Pending',
                'is_required' => (bool) $ta->is_required,
                'applicant_visible' => (bool) $ta->applicant_visible,
            ]);
            $activities++;
        }

        if ($onboarding->template_id !== $template->id) {
            $onboarding->update(['template_id' => $template->id]);
        }

        return ['items' => $items, 'activities' => $activities, 'skipped' => false];
    }

    /**
     * Resolve the default template for a hire.
     *
     * Optional $scope narrows by applicability (null = wildcard):
     * ['department_id' => ?, 'position_id' => ?, 'employment_type_id' => ?, 'work_mode' => ?]
     * The most specific matching Active template wins (most non-null scope
     * fields), so a position-scoped template beats the generic default for
     * that position while everyone else keeps the generic default.
     */
    public static function defaultTemplate(array $scope = []): ?HrmOnboardingTemplate
    {
        $actives = HrmOnboardingTemplate::where('status', 'Active')->get();
        if ($actives->isEmpty()) {
            return HrmOnboardingTemplate::first();
        }

        $keys = ['department_id', 'position_id', 'employment_type_id', 'work_mode'];
        $matching = $actives->filter(function ($t) use ($keys, $scope) {
            foreach ($keys as $k) {
                if ($t->{$k} === null || $t->{$k} === '') {
                    continue; // wildcard
                }
                if (! array_key_exists($k, $scope) || $scope[$k] === null || $scope[$k] === '') {
                    return false; // template is scoped but hire has no value
                }
                if (strcasecmp((string) $t->{$k}, (string) $scope[$k]) !== 0) {
                    return false;
                }
            }

            return true;
        });
        if ($matching->isNotEmpty()) {
            return $matching->sortByDesc(function ($t) use ($keys) {
                $specificity = collect($keys)->filter(fn ($k) => $t->{$k} !== null && $t->{$k} !== '')->count();
                // Specificity dominates; default flag breaks ties.
                return $specificity * 10 + ($t->is_default ? 1 : 0);
            })->values()->first();
        }

        return HrmOnboardingTemplate::where('is_default', true)->where('status', 'Active')->first()
            ?? HrmOnboardingTemplate::where('status', 'Active')->first()
            ?? HrmOnboardingTemplate::first();
    }

    private static function dueDate(?string $type, mixed $value, mixed $base): ?string
    {
        if (! $value && $type !== 'none') {
            // fall through to type handling; empty value yields null below
        }
        try {
            return match ($type) {
                'specific_date' => $value ? Carbon::parse($value)->toDateString() : null,
                'days_after_start' => Carbon::parse($base)->addDays((int) $value)->toDateString(),
                'days_before_start' => Carbon::parse($base)->subDays((int) $value)->toDateString(),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }
}
