<?php

namespace App\Http\Controllers\Hrm\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Hrm\HrmDepartment;
use App\Models\Hrm\HrmEmploymentType;
use App\Models\Hrm\HrmOnboardingTemplate;
use App\Models\Hrm\HrmOnboardingTemplateActivity;
use App\Models\Hrm\HrmOnboardingTemplateItem;
use App\Models\Hrm\HrmPosition;
use App\Models\Hrm\HrmRole;
use App\Traits\HasPagePermissions;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TemplateController extends Controller
{
    use HasPagePermissions;

    public function index(Request $request)
    {
        $q = HrmOnboardingTemplate::withCount(['items as item_count', 'items as active_uses']);
        if ($s = $request->get('search')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"));
        }
        if ($st = $request->get('status')) {
            $q->where('status', $st);
        }

        $paginated = $q->latest()->paginate(12)->withQueryString();

        $badge = fn ($s) => match ($s) {
            'Active' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'Inactive' => 'bg-amber-50 text-amber-700 border-amber-200',
            'Archived' => 'bg-slate-100 text-slate-500 border-slate-200',
            default => 'bg-blue-50 text-blue-700 border-blue-200',
        };

        $templates = collect($paginated->items())->map(function ($t) use ($badge) {
            $t->status_badge = $badge($t->status);
            $t->applicability = collect([
                $t->department_id ? 'Dept #' . $t->department_id : 'Any dept',
                $t->position_id ? 'Pos #' . $t->position_id : 'Any position',
                $t->employment_type_id ? 'ET #' . $t->employment_type_id : 'Any type',
                $t->work_mode ?: 'Any mode',
            ])->join(' · ');
            $t->item_count ??= 0;
            $t->active_uses ??= 0;
            return $t;
        })->values()->all();

        return Inertia::render('Dashboard/HRM_NEW/OnboardingTemplates', [
            'templates' => $templates,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'filters' => $request->only(['search', 'status']),
            'options' => [
                'departments' => HrmDepartment::select('id', 'name', 'category')->orderBy('name')->get(),
                'positions' => HrmPosition::whereNull('archived_at')->select('id', 'department_id', 'name')->orderBy('name')->get(),
                'employment_types' => HrmEmploymentType::select('id', 'name')->get(),
                'work_modes' => ['On-site', 'Remote', 'Hybrid'],
                'modules' => $this->moduleOptions(),
            ],
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function show(HrmOnboardingTemplate $template)
    {
        $template->load([
            'items' => fn ($q) => $q->orderBy('sort_order'),
            'activities' => fn ($q) => $q->orderBy('sort_order'),
        ]);
        // The builder header reads resolved names (department/position/…),
        // not the raw *_id columns — attach them so saved scoping displays
        // instead of falling back to "Any".
        $template->department = $template->department_id
            ? HrmDepartment::whereKey($template->department_id)->value('name') : null;
        $template->position = $template->position_id
            ? HrmPosition::whereNull('archived_at')->whereKey($template->position_id)->value('name') : null;
        $template->employment_type = $template->employment_type_id
            ? HrmEmploymentType::whereKey($template->employment_type_id)->value('name') : null;

        return Inertia::render('Dashboard/HRM_NEW/OnboardingTemplateBuilder', [
            'template' => $template,
            'inputTypes' => ['text', 'file', 'date', 'checkbox', 'select'],
            'categories' => ['Applicant Requirements', 'HR Processing', 'Company Preparation'],
            'responsibleParties' => ['Applicant', 'HR', 'Manager', 'IT', 'Facilities'],
            'activityTypes' => ['Orientation', 'Training', 'Tour', 'Meeting', 'Assessment'],
            'activityMethods' => ['On-site', 'Online', 'Hybrid'],
            'options' => [
                'departments' => HrmDepartment::select('id', 'name', 'category')->orderBy('name')->get(),
                'positions' => HrmPosition::whereNull('archived_at')->select('id', 'department_id', 'name')->orderBy('name')->get(),
                'employment_types' => HrmEmploymentType::select('id', 'name')->get(),
                'work_modes' => ['On-site', 'Remote', 'Hybrid'],
                'modules' => $this->moduleOptions(),
            ],
        ]);
    }

    /**
     * Module / Category options (HRM, CRM, …): canonical role codes
     * unioned with categories already stored on departments.
     */
    protected function moduleOptions(): array
    {
        return HrmRole::where('is_active', true)->orderBy('code')->pluck('code')
            ->merge(HrmDepartment::distinct()->pluck('category'))
            ->filter()->unique()->values()->all();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'nullable|string|max:40|unique:hrm_onboarding_templates,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'department_id' => 'nullable|exists:hrm_departments,id',
            'position_id' => 'nullable|exists:hrm_positions,id',
            'employment_type_id' => 'nullable|exists:hrm_employment_types,id',
            'work_mode' => 'nullable|string',
            'is_default' => 'nullable|boolean',
        ]);
        $data['code'] ??= 'STD-' . str_pad((string) (HrmOnboardingTemplate::count() + 1), 3, '0', STR_PAD_LEFT);
        $data['created_by'] = $request->user()->id;
        $template = HrmOnboardingTemplate::create($data + ['status' => 'Draft', 'version' => 1]);
        // Only one default may exist: a new default demotes the others.
        if (! empty($data['is_default'])) {
            HrmOnboardingTemplate::where('id', '!=', $template->id)->update(['is_default' => false]);
        }

        return back()->with('success', 'Template created.');
    }

    public function update(Request $request, HrmOnboardingTemplate $template)
    {
        $data = $request->validate([
            'code' => 'sometimes|string|max:40|unique:hrm_onboarding_templates,code,' . $template->id,
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'department_id' => 'nullable|exists:hrm_departments,id',
            'position_id' => 'nullable|exists:hrm_positions,id',
            'employment_type_id' => 'nullable|exists:hrm_employment_types,id',
            'work_mode' => 'nullable|string',
            'is_default' => 'nullable|boolean',
        ]);
        $template->update($data);
        // Only one default may exist: promoting this one demotes the rest.
        if (! empty($data['is_default'])) {
            HrmOnboardingTemplate::where('id', '!=', $template->id)->update(['is_default' => false]);
        }

        return back()->with('success', 'Template updated.');
    }

    public function duplicate(HrmOnboardingTemplate $template)
    {
        $copy = $template->replicate();
        $copy->code .= '-COPY';
        $copy->version = 1;
        $copy->is_default = false; // copies never inherit the default flag
        $copy->save();
        foreach ($template->items as $i) {
            $copy->items()->create($i->toArray());
        }
        foreach ($template->activities as $a) {
            $copy->activities()->create($a->toArray());
        }

        return back()->with('success', 'Template duplicated.');
    }

    public function setStatus(Request $request, HrmOnboardingTemplate $template)
    {
        $template->update(['status' => $request->validate(['status' => 'required|in:Draft,Active,Inactive,Archived'])['status']]);

        return back()->with('success', 'Status updated.');
    }

    public function destroy(HrmOnboardingTemplate $template)
    {
        $template->delete();

        return back()->with('success', 'Template deleted.');
    }

    // Items
    public function itemStore(Request $request, HrmOnboardingTemplate $template)
    {
        $data = $request->validate([
            'category' => 'nullable|string', 'name' => 'required|string|max:255', 'description' => 'nullable|string',
            'input_type' => 'nullable|string|in:text,file,date,checkbox,select',
            'options' => 'nullable|array', 'options.*' => 'nullable|string|max:255',
            'responsible_party' => 'nullable|string',
            'is_required' => 'nullable|boolean', 'applicant_visible' => 'nullable|boolean',
            'is_active' => 'nullable|boolean', 'due_rule_type' => 'nullable|string',
            'due_rule_value' => 'nullable|string', 'sort_order' => 'nullable|integer',
        ]);
        // Normalise select options: trim, drop empties, re-index. Non-select types store null.
        $data['options'] = $this->normaliseOptions($data['input_type'] ?? null, $data['options'] ?? null);
        $template->items()->create($data);

        return back()->with('success', 'Item added.');
    }

    public function itemUpdate(Request $request, HrmOnboardingTemplate $template, HrmOnboardingTemplateItem $item)
    {
        $data = $request->validate([
            'category' => 'nullable|string', 'name' => 'sometimes|required|string|max:255', 'description' => 'nullable|string',
            'input_type' => 'nullable|string|in:text,file,date,checkbox,select',
            'options' => 'nullable|array', 'options.*' => 'nullable|string|max:255',
            'responsible_party' => 'nullable|string',
            'is_required' => 'nullable|boolean', 'applicant_visible' => 'nullable|boolean',
            'is_active' => 'nullable|boolean', 'due_rule_type' => 'nullable|string',
            'due_rule_value' => 'nullable|string', 'sort_order' => 'nullable|integer',
        ]);
        $inputType = $data['input_type'] ?? $item->input_type;
        $options = array_key_exists('options', $data) ? $data['options'] : $item->options;
        $data['options'] = $this->normaliseOptions($inputType, $options);
        // When switching away from select, clear stale options.
        if ($inputType !== 'select') {
            $data['options'] = null;
        }
        $item->update($data);

        return back()->with('success', 'Item updated.');
    }

    private function normaliseOptions(?string $inputType, mixed $options): ?array
    {
        if ($inputType !== 'select') {
            return null;
        }
        if (is_string($options)) {
            // Allow comma / newline separated string from the form.
            $options = preg_split('/[\r\n,]+/', $options) ?: [];
        }
        if (! is_array($options)) {
            return null;
        }
        $cleaned = array_values(array_filter(array_map(fn ($o) => trim((string) $o), $options), fn ($o) => $o !== ''));
        // De-duplicate while preserving order.
        $cleaned = array_values(array_unique($cleaned));

        return $cleaned === [] ? null : $cleaned;
    }

    public function itemDestroy(HrmOnboardingTemplate $template, HrmOnboardingTemplateItem $item)
    {
        $item->delete();

        return back()->with('success', 'Item deleted.');
    }

    public function reorder(Request $request, HrmOnboardingTemplate $template)
    {
        foreach ($request->validate(['ordered_ids' => 'required|array'])['ordered_ids'] as $i => $id) {
            HrmOnboardingTemplateItem::where('template_id', $template->id)->where('id', $id)->update(['sort_order' => $i]);
        }

        return back()->with('success', 'Order saved.');
    }

    // Activities
    public function activityStore(Request $request, HrmOnboardingTemplate $template)
    {
        $template->activities()->create($request->validate([
            'type' => 'nullable|string', 'title' => 'required|string', 'description' => 'nullable|string',
            'default_method' => 'nullable|string', 'default_duration_minutes' => 'nullable|integer',
            'is_required' => 'nullable|boolean', 'applicant_visible' => 'nullable|boolean',
            'attendance_required' => 'nullable|boolean',
            'must_complete_before_onboarding_completion' => 'nullable|boolean', 'is_active' => 'nullable|boolean',
        ]));

        return back()->with('success', 'Activity added.');
    }

    public function activityUpdate(Request $request, HrmOnboardingTemplate $template, HrmOnboardingTemplateActivity $activity)
    {
        $activity->update($request->all());

        return back()->with('success', 'Activity updated.');
    }

    public function activityDestroy(HrmOnboardingTemplate $template, HrmOnboardingTemplateActivity $activity)
    {
        $activity->delete();

        return back()->with('success', 'Activity deleted.');
    }

    public function activityReorder(Request $request, HrmOnboardingTemplate $template)
    {
        foreach ($request->validate(['ordered_ids' => 'required|array'])['ordered_ids'] as $i => $id) {
            HrmOnboardingTemplateActivity::where('template_id', $template->id)->where('id', $id)->update(['sort_order' => $i]);
        }

        return back()->with('success', 'Order saved.');
    }
}
