<?php

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Ceo\ExecutiveActionLog;
use App\Models\Hrm\PayrollSetting;
use App\Models\Hrm\PayrollSettingHistory;
use App\Traits\HasPagePermissions;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayrollSettingsController extends Controller
{
    use HasPagePermissions;

    private function isHR(): bool
    {
        $u = auth()->user();
        return $u && strtoupper((string) $u->role) === 'HRM';
    }

    /**
     * New settings page: grouped tunable values + change history.
     */
    public function index()
    {
        $rows = PayrollSetting::orderBy('group')->orderBy('id')->get();
        if ($rows->isEmpty()) {
            $rows = collect(PayrollSetting::defaults())->map(fn ($def, $key) => (object) array_merge(['key' => $key], $def));
        }
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r->group ?? 'general'][] = [
                'key' => $r->key,
                'label' => $r->label,
                'value' => $r instanceof PayrollSetting
                    ? PayrollSetting::castValue($r->value, $r->value_type)
                    : PayrollSetting::castValue($r->value ?? '', $r->value_type ?? 'text'),
                'raw' => $r instanceof PayrollSetting ? (string) ($r->value ?? '') : (string) ($r->value ?? ''),
                'value_type' => $r->value_type,
                'description' => $r->description,
            ];
        }
        $history = PayrollSettingHistory::with('changer:id,name')
            ->latest()->take(200)->get()->map(fn ($h) => [
                'at' => $h->created_at?->toDateTimeString(),
                'by' => $h->changer?->name ?? 'System',
                'key' => $h->setting_key,
                'old_value' => $h->old_value,
                'new_value' => $h->new_value,
            ]);

        return Inertia::render('Dashboard/HRM_NEW/PayrollSettings', [
            'settings' => $grouped,
            'history' => $history,
            'is_hr' => $this->isHR(),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    /**
     * HR-only bulk update. Writes a history row per changed key.
     */
    public function update(Request $request)
    {
        if (!$this->isHR()) {
            return back()->withErrors(['error' => 'Only HR officer can change payroll settings.']);
        }
        $v = $request->validate(['values' => 'required|array']);
        $defs = PayrollSetting::defaults();
        $changed = [];

        foreach ((array) $v['values'] as $key => $incoming) {
            if (!isset($defs[$key])) continue;
            $type = $defs[$key]['value_type'];
            $normalized = $this->normalize($incoming, $type);
            if ($normalized === null) continue; // invalid input for its type

            $row = PayrollSetting::firstOrNew(['key' => $key]);
            $isNew = !$row->exists;
            if ($isNew) {
                $row->fill([
                    'group' => $defs[$key]['group'], 'label' => $defs[$key]['label'],
                    'value_type' => $type, 'description' => $defs[$key]['description'] ?? null,
                ]);
            }
            $old = $isNew ? (string) $defs[$key]['value'] : (string) ($row->value ?? '');
            if ($old === (string) $normalized) continue;

            $row->value = (string) $normalized;
            $row->updated_by = auth()->id();
            $row->save();

            PayrollSettingHistory::create([
                'setting_key' => $key,
                'old_value' => $old,
                'new_value' => (string) $normalized,
                'changed_by' => auth()->id(),
            ]);
            $changed[] = "{$defs[$key]['label']}: {$old}→{$normalized}";
        }

        PayrollSetting::forgetCache();

        if ($changed) {
            ExecutiveActionLog::create([
                'actor_id' => auth()->id(),
                'action_type' => 'payroll',
                'subject_label' => mb_substr('Payroll settings updated ['.implode('; ', array_slice($changed, 0, 3)).(count($changed) > 3 ? ' (+'.(count($changed) - 3).' more)' : '').']', 0, 250),
                'decision' => 'settings-updated',
            ]);
        }

        return back()->with('message', $changed ? 'Settings saved ('.count($changed).' changed). New payroll math uses them immediately.' : 'No changes.');
    }

    private function normalize($incoming, string $type): ?string
    {
        return match ($type) {
            'number' => is_numeric($incoming) && (float) $incoming >= 0 ? (string) $incoming : null,
            'boolean' => in_array($incoming, [true, 1, '1', 'true', 'on', 'yes'], true) ? '1'
                : (in_array($incoming, [false, 0, '0', 'false', 'off', 'no'], true) ? '0' : null),
            'time' => $this->normalizeTime($incoming),
            default => is_scalar($incoming) && mb_strlen((string) $incoming) <= 40 ? (string) $incoming : null,
        };
    }

    private function normalizeTime($incoming): ?string
    {
        if (!is_string($incoming) && !is_numeric($incoming)) return null;
        $t = trim((string) $incoming);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $t, $m)) {
            $h = (int) $m[1]; $i = (int) $m[2]; $s = isset($m[3]) ? (int) $m[3] : 0;
            if ($h > 23 || $i > 59 || $s > 59) return null;
            return sprintf('%02d:%02d:%02d', $h, $i, $s);
        }
        if (preg_match('/^(\d{1,2})(?::(\d{2}))?\s*(AM|PM)$/i', $t, $m)) {
            $h = (int) $m[1]; $i = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0; $ap = strtoupper($m[3]);
            if ($h < 1 || $h > 12 || $i > 59) return null;
            if ($ap === 'PM' && $h < 12) $h += 12;
            if ($ap === 'AM' && $h == 12) $h = 0;
            return sprintf('%02d:%02d:00', $h, $i);
        }
        return null;
    }
}
