<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\BomRecord;
use App\Models\Crm\FabricSampleRequest;
use App\Models\Crm\FabricSampleFormulation;
use App\Models\Eco\ConversationAttachment;
use App\Models\Eco\ConversationMessage;
use App\Models\Inv\Material;
use App\Models\Man\LabDipRequest;
use App\Models\Man\LabStockSolution;
use App\Models\Man\LabTest;
use App\Models\Man\LabTrial;
use App\Models\Man\PantoneReference;
use App\Models\Man\PantoneFabricFormula;
use App\Models\Man\KnittingSampleRequest;
use App\Models\Man\ManufacturingInventoryItem;
use App\Models\Ord\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DyeingLabChemistController extends ManufacturingStaffController
{
    public function index()
    {
        $pendingCount = LabDipRequest::whereIn('status', ['pending', 'in_progress', 'awaiting_spectro'])->count();
        $recentDips = LabDipRequest::with('salesOrder')
            ->where('operator_id', $this->staff()->id)
            ->latest()
            ->take(10)
            ->get();

        $nextQueue = LabDipRequest::with('salesOrder')
            ->where('status', 'pending')
            ->orderByRaw("FIELD(urgency, 'urgent', 'high', 'normal', 'low')")
            ->orderBy('created_at', 'asc')
            ->take(3)
            ->get(['id', 'code', 'urgency', 'pantone_code', 'created_at']);

        return Inertia::render('Dashboard/MAN/Employee/DyeingLabChemist/Index', [
            'stats' => [
                'pending' => $pendingCount,
                'total_today' => LabDipRequest::whereDate('processed_at', today())->count(),
            ],
            'recentDips' => $recentDips,
            'efficiency' => $this->staffEfficiency(LabDipRequest::class),
            'labMetrics' => $this->labMetrics(),
            'nextQueue' => $nextQueue,
        ]);
    }

    public function knitFabricRequests()
    {
        $chemistRequests = KnittingSampleRequest::where('requested_by', $this->staff()->id);
        $query = (clone $chemistRequests)->with([
            'fabricSampleRequest:id,code,fabric_name',
            'operator:id,name', 'requestedBy:id,name', 'machine:id,machine_no',
        ]);

        if ($status = request('status')) {
            if (in_array($status, ['requested', 'in_progress', 'completed'], true)) {
                $query->where('status', $status);
            }
        }
        if ($search = trim((string) request('search'))) {
            $query->where(function ($requests) use ($search) {
                $requests->where('code', 'like', "%{$search}%")
                    ->orWhere('fabric_design', 'like', "%{$search}%")
                    ->orWhere('pantone_name', 'like', "%{$search}%")
                    ->orWhereHas('fabricSampleRequest', fn ($source) => $source
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('fabric_name', 'like', "%{$search}%"));
            });
        }

        $counts = (clone $chemistRequests)->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')->pluck('total', 'status');

        return Inertia::render('Dashboard/MAN/Employee/DyeingLabChemist/KnitFabricRequests', [
            'requests' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => ['status' => request('status'), 'search' => request('search')],
            'stats' => [
                'requested' => (int) ($counts['requested'] ?? 0),
                'in_progress' => (int) ($counts['in_progress'] ?? 0),
                'completed' => (int) ($counts['completed'] ?? 0),
                'total' => (int) $counts->sum(),
            ],
        ]);
    }

    /**
     * Shade development work page: open dip requests with trial history,
     * plus CRM fabric sample requests from inquiry conversations.
     */
    public function shades()
    {
        $dips = LabDipRequest::with(['trials.operator', 'salesOrder'])
            ->whereIn('status', ['pending', 'in_progress', 'awaiting_spectro'])
            ->orderByRaw("FIELD(urgency, 'urgent', 'high', 'normal', 'low')")
            ->orderBy('created_at', 'asc')
            ->get();

        $jobOrders = SalesOrder::where('status', 'in_production')
            ->orderBy('created_at', 'asc')
            ->take(30)
            ->get(['id', 'jo_number', 'color', 'design', 'yarn_type']);

        $sampleRequests = FabricSampleRequest::with(['inquiry.client', 'product', 'parent', 'pantoneReference', 'requester:id,name', 'formulations.submitter:id,name', 'formulations.reviewer:id,name'])
            ->whereIn('status', [FabricSampleRequest::STATUS_REQUESTED, FabricSampleRequest::STATUS_IN_PROGRESS, FabricSampleRequest::STATUS_ADJUSTMENT_REQUESTED])
            ->orderByRaw("FIELD(urgency, 'urgent', 'high', 'normal', 'low')")
            ->orderBy('created_at', 'asc')
            ->get()
            ->each(function (FabricSampleRequest $sample) {
                $colors = $this->pantoneColorEntries($sample);
                $sample->setAttribute('pantone_colors', $colors);
                if (count($colors) > 1) {
                    $sample->setAttribute('color_description', count($colors) . ' Pantone colors requested');
                }
            });

        return Inertia::render('Dashboard/MAN/Employee/DyeingLabChemist/Shades', [
            'dips' => $dips,
            'jobOrders' => $jobOrders,
            'sampleRequests' => $sampleRequests,
            'pantoneReferences' => PantoneReference::query()->orderBy('name')->get(['id', 'name', 'hex']),
            // Inventory-connected autocomplete source for dyestuff/auxiliary
            // name inputs — rows must reference these IDs (see storeTrial).
            'materials' => Material::orderBy('name')->get(['id', 'mat_id', 'name', 'unit', 'category', 'yarn_tier']),
        ]);
    }

    /** Pantone reference catalog, with yarn formulas fetched on demand. */
    public function pantones()
    {
        // Keep older Pantone codes from lab-dip requests in the catalog without
        // hydrating every dip, trial, sales order, and trial formula at once.
        $dips = LabDipRequest::query()
            ->whereNotNull('pantone_code')
            ->where('pantone_code', '!=', '')
            ->orderByDesc('updated_at')
            ->get(['pantone_code', 'customer_ref', 'rgb_lab_values', 'swatch_id', 'updated_at']);

        $pantones = $dips
            ->groupBy(fn (LabDipRequest $dip) => mb_strtoupper(trim($dip->pantone_code)))
            ->map(function ($pantoneDips) {
                $latestDip = $pantoneDips->first();
                $code = trim((string) $latestDip->pantone_code);

                return [
                    'code' => $code,
                    'name' => $latestDip->customer_ref ?: $code,
                    'hex' => $this->pantoneHex($latestDip->rgb_lab_values),
                    'rgb_lab_values' => $latestDip->rgb_lab_values,
                    'swatch_id' => $latestDip->swatch_id,
                    'requests_count' => $pantoneDips->count(),
                    'last_updated' => $latestDip->updated_at?->toDateString(),
                    'trial_code' => null,
                    'trial_status' => null,
                    'combinations' => [],
                ];
            })
            ->sortBy('code', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        // Keep the index payload small. Formula combinations are loaded only for
        // the Pantone opened in the detail modal (900+ colors × 31 yarns is too
        // large to serialize into the initial Inertia response).
        $yarnIdsByPantone = DB::table('pantone_fabric_formulas')
            ->orderBy('pantone_reference_id')
            ->get(['pantone_reference_id', 'yarn_material_id'])
            ->groupBy('pantone_reference_id')
            ->map(fn ($rows) => $rows->pluck('yarn_material_id')->map(fn ($id) => (int) $id)->values()->all());

        $savedPantones = PantoneReference::query()
            ->select(['id', 'name', 'hex', 'color_category', 'combinations', 'updated_at'])
            ->withCount('fabricFormulas')
            ->orderBy('name')
            ->get()
            ->map(fn (PantoneReference $pantone) => [
            'id' => $pantone->id,
            'code' => $pantone->name,
            'name' => $pantone->name,
            'hex' => $pantone->hex,
            'color_category' => $pantone->color_category,
            'rgb_lab_values' => null,
            'swatch_id' => null,
            'requests_count' => 0,
            'last_updated' => $pantone->updated_at?->toDateString(),
            'trial_code' => null,
            'trial_status' => null,
            'combinations' => $pantone->combinations ?: [],
            'fabric_formulas_count' => (int) $pantone->fabric_formulas_count,
            'yarn_material_ids' => $yarnIdsByPantone->get($pantone->id, []),
        ]);

        $pantones = $savedPantones->concat($pantones)
            ->unique(fn ($pantone) => mb_strtoupper(trim((string) $pantone['code'])))
            ->sortBy('code', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return Inertia::render('Dashboard/MAN/Employee/DyeingLabChemist/Pantones', [
            'pantones' => $pantones,
            'materials' => Material::query()->orderBy('name')->get(['id', 'mat_id', 'name', 'category', 'unit', 'yarn_tier']),
        ]);
    }

    public function showPantone(PantoneReference $pantoneReference)
    {
        $pantoneReference->load('fabricFormulas.yarnMaterial');

        return response()->json([
            'pantone' => [
                'id' => $pantoneReference->id,
                'code' => $pantoneReference->name,
                'name' => $pantoneReference->name,
                'hex' => $pantoneReference->hex,
                'color_category' => $pantoneReference->color_category,
                'last_updated' => $pantoneReference->updated_at?->toDateString(),
                'fabric_formulas_count' => $pantoneReference->fabricFormulas->count(),
                'yarn_material_ids' => $pantoneReference->fabricFormulas->pluck('yarn_material_id')->map(fn ($id) => (int) $id)->values(),
                'fabric_formulas' => $pantoneReference->fabricFormulas->map(fn (PantoneFabricFormula $formula) => [
                    'id' => $formula->id,
                    'yarn_material_id' => $formula->yarn_material_id,
                    'yarn_name' => $formula->yarnMaterial?->display_name,
                    'yarn_base_name' => $formula->yarnMaterial?->name,
                    'combinations' => $formula->combinations ?: [],
                    'is_demo_data' => $formula->is_demo_data,
                    'updated_at' => $formula->updated_at?->toDateString(),
                ])->values(),
            ],
        ]);
    }

    public function storePantone(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120|unique:pantone_references,name',
            'hex' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_category' => 'required|in:white,light,dark',
            'yarn_material_ids' => 'required|array|min:1',
            'yarn_material_ids.*' => ['required', 'integer', Rule::exists('materials', 'id')->where(fn ($query) => $query->where('category', 'Yarn'))],
        ]);

        $pantone = PantoneReference::create([
            'name' => trim($validated['name']),
            'hex' => strtoupper($validated['hex']),
            'color_category' => $validated['color_category'],
            'combinations' => [],
            'created_by' => $this->staff()->id,
        ]);

        foreach (Material::whereIn('id', array_unique($validated['yarn_material_ids']))->get() as $yarn) {
            $pantone->fabricFormulas()->create(['yarn_material_id' => $yarn->id, 'combinations' => $this->emptyPantoneFormula()]);
        }

        return redirect()->back()->with('message', 'Pantone color added with the selected yarn-based formula slots.');
    }

    public function updatePantone(Request $request, PantoneReference $pantoneReference)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('pantone_references', 'name')->ignore($pantoneReference->id)],
            'hex' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_category' => 'required|in:white,light,dark',
            'yarn_material_ids' => 'required|array|min:1',
            'yarn_material_ids.*' => ['required', 'integer', Rule::exists('materials', 'id')->where(fn ($query) => $query->where('category', 'Yarn'))],
        ]);

        $pantoneReference->update([
            'name' => trim($validated['name']),
            'hex' => strtoupper($validated['hex']),
            'color_category' => $validated['color_category'],
        ]);

        foreach (Material::whereIn('id', array_unique($validated['yarn_material_ids']))->get() as $yarn) {
            $pantoneReference->fabricFormulas()->firstOrCreate(
                ['yarn_material_id' => $yarn->id],
                ['combinations' => $this->emptyPantoneFormula()]
            );
        }

        return redirect()->back()->with('message', 'Pantone color updated and selected yarn types added.');
    }

    public function updatePantoneFabricFormula(Request $request, PantoneReference $pantoneReference, PantoneFabricFormula $pantoneFabricFormula)
    {
        abort_unless($pantoneFabricFormula->pantone_reference_id === $pantoneReference->id, 404);

        $validated = $request->validate([
            'combinations' => 'required|array',
            'combinations.dye_stuffs' => 'present|array',
            'combinations.auxiliary_agents' => 'present|array',
            'combinations.basic_chemicals' => 'present|array',
            'combinations.post_treatment' => 'present|array',
            'combinations.*.*.material_id' => 'required|integer|exists:materials,id',
            'combinations.*.*.amount' => 'nullable|numeric|min:0|max:999999',
        ]);

        $allowed = [
            'dye_stuffs' => ['dye stuff', 'dye', 'dyestuff'],
            'auxiliary_agents' => ['auxillary agent', 'auxiliary agent'],
            'basic_chemicals' => ['basic chemicals', 'basic chemical'],
            'post_treatment' => ['post-treatment', 'post treatment'],
        ];
        $ids = collect($validated['combinations'])->flatten(1)->pluck('material_id')->unique();
        $materials = Material::whereIn('id', $ids)->get()->keyBy('id');
        $combinations = [];

        foreach ($allowed as $group => $categories) {
            $combinations[$group] = [];
            foreach ($validated['combinations'][$group] as $row) {
                $material = $materials->get((int) $row['material_id']);
                if (! $material || ! in_array(mb_strtolower(trim($material->category)), $categories, true)) {
                    return back()->withErrors(['combinations' => 'Choose inventory materials that match each formula category.']);
                }
                $combinations[$group][] = [
                    'material_id' => $material->id,
                    'mat_id' => $material->mat_id,
                    'name' => $material->display_name,
                    'amount' => isset($row['amount']) && $row['amount'] !== '' ? (string) $row['amount'] : null,
                    'unit' => $material->unit,
                ];
            }
        }

        $pantoneFabricFormula->update(['combinations' => $combinations, 'is_demo_data' => false]);

        return redirect()->back()->with('message', 'Yarn-based Pantone formula saved.');
    }

    private function emptyPantoneFormula(): array
    {
        return ['dye_stuffs' => [], 'auxiliary_agents' => [], 'basic_chemicals' => [], 'post_treatment' => []];
    }

    private function pantoneHex(?string $values): ?string
    {
        $values = trim((string) $values);
        if (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $values)) {
            return strtoupper($values);
        }

        if (preg_match('/(?:rgb\s*\()?\s*(\d{1,3})\s*[,;]\s*(\d{1,3})\s*[,;]\s*(\d{1,3})\s*\)?/i', $values, $matches)) {
            $rgb = array_map(fn ($value) => max(0, min(255, (int) $value)), array_slice($matches, 1, 3));
            return sprintf('#%02X%02X%02X', ...$rgb);
        }

        return null;
    }

    private function pantoneColorEntries(FabricSampleRequest $sample): array
    {
        $saved = $sample->pantone_colors;
        if (is_array($saved) && count($saved)) {
            return array_map(fn ($color) => $color + [
                'texture' => $sample->texture,
                'gsm' => $sample->gsm,
                'color_hex' => $sample->color_hex,
                'color_image_path' => $sample->color_image_path,
            ], $saved);
        }

        $labels = preg_split('/\s*(?:·|•|;|\r?\n)\s*/u', trim((string) $sample->color_description), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (! count($labels)) $labels = ['Color reference'];

        return collect($labels)->values()->map(function (string $label, int $index) use ($sample, $labels) {
            preg_match('/\b\d{2}-\d{4}(?:\s+[A-Z]{2,4})?\b/i', $label, $match);
            $reference = $sample->pantoneReference;
            $matchesLegacyReference = $reference && (
                mb_stripos($label, $reference->name) !== false
                || ($match[0] ?? null) === $reference->name
            );
            $isOnlyColor = count($labels) === 1;

            return [
                'label' => trim($label),
                'pantone_code' => $match[0] ?? '',
                'pantone_reference_id' => $matchesLegacyReference ? $reference->id : null,
                'pantone_name' => $matchesLegacyReference ? $reference->name : null,
                'hex' => $matchesLegacyReference ? $reference->hex : null,
                'texture' => $sample->texture,
                'gsm' => $sample->gsm,
                'color_hex' => $sample->color_hex,
                'color_image_path' => $sample->color_image_path,
                'formula' => $matchesLegacyReference || ($isOnlyColor && $sample->pantone_formula)
                    ? ($sample->pantone_formula ?: $sample->formula)
                    : null,
            ];
        })->all();
    }

    /**
     * Pick up a CRM fabric sample request.
     */
    public function startSample(FabricSampleRequest $sampleRequest)
    {
        if ($sampleRequest->status !== FabricSampleRequest::STATUS_REQUESTED) {
            return back()->withErrors(['error' => 'This sample request is already being handled.']);
        }

        $sampleRequest->update([
            'status' => FabricSampleRequest::STATUS_IN_PROGRESS,
            'formulated_by' => $this->staff()->id,
        ]);

        return redirect()->back()->with('message', "Sample {$sampleRequest->code} started.");
    }

    public function linkSamplePantone(Request $request, FabricSampleRequest $sampleRequest)
    {
        $validated = $request->validate([
            'pantone_reference_id' => 'required|exists:pantone_references,id',
            'color_index' => 'required|integer|min:0',
            'yarn_material_id' => 'required|integer|exists:materials,id',
        ]);
        $yarn = Material::query()->whereKey($validated['yarn_material_id'])->where('category', 'Yarn')->first();
        abort_unless($yarn, 422, 'Choose a valid yarn type for this formula.');
        $colors = $this->pantoneColorEntries($sampleRequest);
        abort_unless(array_key_exists($validated['color_index'], $colors), 422, 'The selected color is not part of this fabric request.');
        $reference = PantoneReference::with('fabricFormulas')->findOrFail($validated['pantone_reference_id']);
        $formulaRow = $reference->fabricFormulas->first(fn ($row) => (int) $row->yarn_material_id === (int) $yarn->id);
        abort_unless($formulaRow, 422, 'No formula is available for that yarn type and Pantone color.');
        $colors[$validated['color_index']] = [
            ...$colors[$validated['color_index']],
            'pantone_reference_id' => $reference->id,
            'pantone_name' => $reference->name,
            'hex' => $reference->hex,
            'yarn_material_id' => (int) $yarn->id,
            'yarn_type' => $yarn->display_name ?: $yarn->name,
            'formula' => $formulaRow->combinations,
        ];
        $sampleRequest->update(['pantone_colors' => array_values($colors)]);
        return back()->with('message', 'Pantone reference and formula saved for this color in the fabric request.');
    }

    public function unlinkSamplePantone(Request $request, FabricSampleRequest $sampleRequest)
    {
        $validated = $request->validate([
            'color_index' => 'required|integer|min:0',
        ]);
        $colors = $this->pantoneColorEntries($sampleRequest);
        abort_unless(array_key_exists($validated['color_index'], $colors), 422, 'The selected color is not part of this fabric request.');

        $colors[$validated['color_index']]['pantone_reference_id'] = null;
        $colors[$validated['color_index']]['pantone_name'] = null;
        $colors[$validated['color_index']]['hex'] = null;
        $colors[$validated['color_index']]['yarn_material_id'] = null;
        $colors[$validated['color_index']]['yarn_type'] = null;
        $colors[$validated['color_index']]['formula'] = null;
        $sampleRequest->update(['pantone_colors' => array_values($colors)]);

        return back()->with('message', 'Pantone formula unlinked from this request color.');
    }

    public function requestKnitFabric(Request $request, FabricSampleRequest $sampleRequest)
    {
        $data = $request->validate([
            'color_index' => 'required|integer|min:0',
            'fabric_design' => 'required|string|max:255',
            'texture' => 'nullable|in:Hard,Medium,Soft',
            'gsm' => 'nullable|integer|min:1|max:2000',
            'requested_weight' => 'required|numeric|min:0.01|max:1',
        ]);
        $colors = $this->pantoneColorEntries($sampleRequest);
        abort_unless(isset($colors[$data['color_index']]), 422, 'The selected color is not part of this request.');
        $color = $colors[$data['color_index']];
        abort_unless(!empty($color['pantone_reference_id']) && !empty($color['yarn_material_id']) && !empty($color['formula']), 422, 'Link a Pantone formula and yarn type before requesting knit fabric.');

        $code = 'KSR-' . now()->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        KnittingSampleRequest::create([
            'code' => $code, 'fabric_sample_request_id' => $sampleRequest->id,
            'color_index' => $data['color_index'], 'fabric_design' => $data['fabric_design'],
            'texture' => $data['texture'] ?: ($color['texture'] ?? $sampleRequest->texture),
            'gsm' => $data['gsm'] ?: ($color['gsm'] ?? $sampleRequest->gsm),
            'yarn_material_id' => $color['yarn_material_id'], 'yarn_type' => $color['yarn_type'] ?? null,
            'pantone_name' => $color['pantone_name'] ?? null, 'pantone_hex' => $color['hex'] ?? null,
            'formula' => $color['formula'], 'requested_weight' => $data['requested_weight'],
            'requested_by' => auth()->id(), 'status' => 'requested',
        ]);

        return back()->with('message', "Knit fabric request {$code} sent to Knitting Sample staff.");
    }

    /**
     * Formulate the color: record the recipe formula, upload the sample
     * photo, create the BomRecord, and send everything back to the CRM
     * team as a thread message (hidden from the client until forwarded).
     */
    /**
     * Save one formulation attempt and send it to CRM for review. The client
     * sees the sample only after CRM explicitly forwards it.
     */
    public function formulateSample(Request $request, FabricSampleRequest $sampleRequest)
    {
        if (! in_array($sampleRequest->status, [
            FabricSampleRequest::STATUS_REQUESTED,
            FabricSampleRequest::STATUS_IN_PROGRESS,
            FabricSampleRequest::STATUS_ADJUSTMENT_REQUESTED,
        ], true)) {
            return back()->withErrors(['error' => 'This sample request is not ready for a new formulation.']);
        }

        $validated = $request->validate([
            'dyestuffs' => 'nullable|array',
            'dyestuffs.*.material_id' => 'required|exists:materials,id',
            'dyestuffs.*.name' => 'nullable|string|max:255',
            'dyestuffs.*.pct' => 'nullable|numeric|min:0',
            'auxiliary_agents' => 'nullable|array',
            'auxiliary_agents.*.material_id' => 'required|exists:materials,id',
            'auxiliary_agents.*.name' => 'nullable|string|max:255',
            'auxiliary_agents.*.gpl' => 'nullable|numeric|min:0',
            'basic_chemicals' => 'nullable|array',
            'basic_chemicals.*.material_id' => 'required|exists:materials,id',
            'basic_chemicals.*.name' => 'nullable|string|max:255',
            'basic_chemicals.*.gpl' => 'nullable|numeric|min:0',
            'post_treatment' => 'nullable|array',
            'post_treatment.*.material_id' => 'required|exists:materials,id',
            'post_treatment.*.name' => 'nullable|string|max:255',
            'post_treatment.*.gpl' => 'nullable|numeric|min:0',
            'liquor_ratio' => 'nullable|string|max:32',
            'notes' => 'nullable|string|max:2000',
            'sample_photo' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
        ]);

        $version = (int) $sampleRequest->formulations()->max('version') + 1;
        $file = $request->file('sample_photo');
        $attachmentPath = $file->storeAs(
            'fabric_samples',
            'sample_' . $sampleRequest->code . '_v' . $version . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($file->getClientOriginalExtension()),
            'public'
        );

        $baseFormula = $sampleRequest->formula ?: ($sampleRequest->pantone_formula ?: []);
        unset($baseFormula['dye_stuffs'], $baseFormula['auxiliaries']);
        $formula = array_merge($baseFormula, [
            'dyestuffs' => $this->resolveFormulaRows($validated['dyestuffs'] ?? [], 'pct'),
            'auxiliary_agents' => $this->resolveFormulaRows($validated['auxiliary_agents'] ?? [], 'gpl'),
            'basic_chemicals' => $this->resolveFormulaRows($validated['basic_chemicals'] ?? [], 'gpl'),
            'post_treatment' => $this->resolveFormulaRows($validated['post_treatment'] ?? [], 'gpl'),
            'liquor_ratio' => $validated['liquor_ratio'] ?? ($baseFormula['liquor_ratio'] ?? null),
            'notes' => $validated['notes'] ?? null,
        ]);

        try {
            DB::transaction(function () use ($sampleRequest, $formula, $version, $file, $attachmentPath) {
                $formulation = FabricSampleFormulation::create([
                    'fabric_sample_request_id' => $sampleRequest->id,
                    'version' => $version,
                    'formula' => $formula,
                    'attachment_path' => $attachmentPath,
                    'attachment_name' => $file->getClientOriginalName(),
                    'attachment_mime' => $file->getMimeType() ?: 'application/octet-stream',
                    'submitted_by' => $this->staff()->id,
                    'status' => 'awaiting_crm',
                ]);

                $sampleRequest->update([
                    'status' => FabricSampleRequest::STATUS_AWAITING_CRM_REVIEW,
                    'formula' => $formula,
                    'sample_image_path' => $attachmentPath,
                    'recipe_id' => null,
                    'formulated_by' => $this->staff()->id,
                    'adjustment_notes' => null,
                ]);

                $message = ConversationMessage::create([
                    'inquiry_id' => $sampleRequest->inquiry_id,
                    'sender_type' => 'eco',
                    'message' => "Fabric swatch image ready to send to the client ({$sampleRequest->code}): {$sampleRequest->fabric_name}.",
                    'is_system_event' => false,
                    'visible_to_client' => false,
                    'sample_request_id' => $sampleRequest->id,
                ]);

                ConversationAttachment::create([
                    'conversation_message_id' => $message->id,
                    'file_path' => $attachmentPath,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getMimeType() ?: 'application/octet-stream',
                ]);

                $formulation->update(['conversation_message_id' => $message->id]);
                $sampleRequest->inquiry?->update(['last_message_at' => now()]);
            });

            return back()->with('message', "Formulation v{$version} sent to CRM for review.");
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($attachmentPath);

            return back()->withErrors(['error' => 'Failed to submit formulation: ' . $e->getMessage()]);
        }
    }
    public function storeDip(Request $request)
    {
        $validated = $request->validate([
            'sales_order_id' => 'nullable|exists:sales_orders,id',
            'customer_ref' => 'nullable|string|max:255',
            'pantone_code' => 'nullable|string|max:64',
            'rgb_lab_values' => 'nullable|string|max:255',
            'swatch_id' => 'nullable|string|max:255',
            'fabric_details' => 'nullable|string|max:255',
            'urgency' => 'required|in:low,normal,high,urgent',
            'remarks' => 'nullable|string',
        ]);

        LabDipRequest::create([
            ...$validated,
            'code' => $this->generateCode('LABDIP', LabDipRequest::class),
            'status' => 'pending',
            'operator_id' => $this->staff()->id,
            'shift' => $this->getShift(),
            'processed_at' => now(),
        ]);

        return redirect()->back()->with('message', 'Lab dip request logged successfully.');
    }

    /**
     * Normalize submitted formula rows to inventory-connected records:
     * [{material_id, name, pct|gpl}]. Names are re-resolved from the
     * materials table so stored rows always match real inventory items —
     * free-typed names can never leak into recipes unconnected.
     */
    protected function resolveFormulaRows(array $rows, string $qtyKey): array
    {
        $ids = collect($rows)
            ->map(fn ($row) => (int) ($row['material_id'] ?? 0))
            ->filter()
            ->unique()
            ->values();
        $names = $ids->isEmpty()
            ? collect()
            : Material::whereIn('id', $ids)->get()->keyBy('id')->map(fn ($material) => $material->display_name);

        $out = [];
        foreach ($rows as $row) {
            $mid = (int) ($row['material_id'] ?? 0);
            if (! $names->has($mid)) {
                continue;
            }
            $out[] = [
                'material_id' => $mid,
                'name' => $names[$mid],
                $qtyKey => (float) ($row[$qtyKey] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Recipe material map (material_id => KG PER KG ORDERED) from a stored
     * formula. Unit contract — BomRecord.materials is ALWAYS kg of raw
     * material per 1 unit of order quantity, because DSS multiplies it
     * straight by the order qty:
     *  - dyestuffs are entered as %OWF  → kg/kg = pct / 100
     *  - auxiliaries are entered as g/L → kg/kg = gpl × liquor L/kg / 1000
     *    (liquor comes from the trial/sample liquor_ratio, default 1:10)
     *  - yarns are already entered as kg/kg → used as-is
     * New rows carry material_id; legacy name-only rows are resolved by
     * name and unresolvable ones skipped (never stored as ID 0).
     */
    protected function recipeMaterialsFromFormula(array $formula, ?float $liquorLitersPerKg = null): array
    {
        $lr = $liquorLitersPerKg ?? self::parseLiquorRatio($formula['liquor_ratio'] ?? null);
        $materials = [];

        // [group, qty key, to-kg-per-kg converter]
        $groups = [
            ['dyestuffs', 'pct', fn ($v) => ((float) $v) / 100],
            ['auxiliary_agents', 'gpl', fn ($v) => ((float) $v) * $lr / 1000],
            ['basic_chemicals', 'gpl', fn ($v) => ((float) $v) * $lr / 1000],
            ['post_treatment', 'gpl', fn ($v) => ((float) $v) * $lr / 1000],
        ];
        if (! array_key_exists('auxiliary_agents', $formula)) {
            $groups[] = ['auxiliaries', 'gpl', fn ($v) => ((float) $v) * $lr / 1000];
        }

        $legacyNames = [];
        foreach ($groups as [$group, $qtyKey, $convert]) {
            foreach ($formula[$group] ?? [] as $item) {
                $mid = (int) ($item['material_id'] ?? 0);
                $qty = $convert($item[$qtyKey] ?? 0);
                if ($mid > 0) {
                    $materials[$mid] = ($materials[$mid] ?? 0) + $qty;
                } elseif (! empty($item['name'])) {
                    $legacyNames[(string) $item['name']][] = $qty;
                }
            }
        }

        if ($legacyNames) {
            $idsByName = Material::whereIn('name', array_keys($legacyNames))
                ->pluck('id', 'name')
                ->toArray();
            foreach ($legacyNames as $name => $qtys) {
                if (isset($idsByName[$name])) {
                    $mid = (int) $idsByName[$name];
                    $materials[$mid] = ($materials[$mid] ?? 0) + array_sum($qtys);
                }
            }
        }

        return array_filter($materials, fn ($v) => $v > 0);
    }

    /**
     * Parse a liquor ratio ("1:10", "10", 10) into bath liters per kg of
     * fabric. Falls back to 10.0 (1:10) for missing/garbled values.
     */
    protected static function parseLiquorRatio($value): float
    {
        if (is_numeric($value)) {
            $lr = (float) $value;
            return ($lr > 0 && $lr <= 1000) ? $lr : 10.0;
        }
        if (is_string($value) && preg_match('/(\d+(?:\.\d+)?)\s*$/', trim($value), $m)) {
            $lr = (float) $m[1];
            return ($lr > 0 && $lr <= 1000) ? $lr : 10.0;
        }
        return 10.0;
    }

    public function storeTrial(Request $request)
    {
        $validated = $request->validate([
            'dip_request_id' => 'required|exists:lab_dip_requests,id',
            'dyestuffs' => 'nullable|array',
            'dyestuffs.*.material_id' => 'required|exists:materials,id',
            'dyestuffs.*.name' => 'nullable|string|max:255',
            'dyestuffs.*.pct' => 'nullable|numeric|min:0',
            'auxiliaries' => 'nullable|array',
            'auxiliaries.*.material_id' => 'required|exists:materials,id',
            'auxiliaries.*.name' => 'nullable|string|max:255',
            'auxiliaries.*.gpl' => 'nullable|numeric|min:0',
            'liquor_ratio' => 'nullable|string|max:32',
            'curve' => 'nullable|array',
            'adjustments' => 'nullable|string',
            'delta_e' => 'nullable|numeric|min:0',
            'status' => 'required|in:pending,passed,failed',
        ]);

        $dip = LabDipRequest::findOrFail($validated['dip_request_id']);
        if (in_array($dip->status, ['approved', 'rejected'])) {
            return back()->withErrors(['error' => 'This dip request is already closed.']);
        }

        $trialNo = (LabTrial::where('dip_request_id', $dip->id)->max('trial_no') ?? 0) + 1;

        LabTrial::create([
            'code' => $this->generateCode('TRIAL', LabTrial::class),
            'dip_request_id' => $dip->id,
            'trial_no' => $trialNo,
            'formula' => [
                'dyestuffs' => $this->resolveFormulaRows($validated['dyestuffs'] ?? [], 'pct'),
                'auxiliaries' => $this->resolveFormulaRows($validated['auxiliaries'] ?? [], 'gpl'),
                'liquor_ratio' => $validated['liquor_ratio'] ?? null,
                'curve' => $validated['curve'] ?? [],
            ],
            'adjustments' => $validated['adjustments'] ?? null,
            'delta_e' => $validated['delta_e'] ?? null,
            'status' => $validated['status'],
            'operator_id' => $this->staff()->id,
            'shift' => $this->getShift(),
            'processed_at' => now(),
        ]);

        if ($dip->status === 'pending') {
            $dip->update(['status' => 'in_progress']);
        }

        return redirect()->back()->with('message', "Trial {$trialNo} recorded for {$dip->code}.");
    }

    /**
     * Advance dip status (forward-only; rejected is terminal from lab stages).
     */
    public function updateDipStatus(Request $request, LabDipRequest $dip)
    {
        $validated = $request->validate([
            'status' => 'required|in:in_progress,awaiting_spectro,approved,rejected',
        ]);

        $order = ['pending' => 0, 'in_progress' => 1, 'awaiting_spectro' => 2, 'approved' => 3, 'rejected' => 3];
        if ($order[$validated['status']] < $order[$dip->status]) {
            return back()->withErrors(['status' => 'Dip requests cannot move backwards.']);
        }

        $dip->update(['status' => $validated['status']]);

        return redirect()->back()->with('message', "Dip {$dip->code} marked as {$validated['status']}.");
    }

    /**
     * Quality & fastness testing page with CoA dataset.
     */
    public function tests()
    {
        $dips = LabDipRequest::with(['trials', 'tests', 'salesOrder'])
            ->whereIn('status', ['in_progress', 'awaiting_spectro', 'approved'])
            ->latest()
            ->take(50)
            ->get();

        return Inertia::render('Dashboard/MAN/Employee/DyeingLabChemist/Tests', [
            'dips' => $dips,
        ]);
    }

    public function storeTest(Request $request)
    {
        $validated = $request->validate([
            'dip_request_id' => 'required|exists:lab_dip_requests,id',
            'test_type' => 'required|in:wash,rub_dry,rub_wet,light,perspiration,ph,absorbency,other',
            'method' => 'nullable|string|max:255',
            'rating' => 'nullable|string|max:16',
            'result' => 'nullable|string',
        ]);

        LabTest::create([
            ...$validated,
            'code' => $this->generateCode('LABTEST', LabTest::class),
            'operator_id' => $this->staff()->id,
            'shift' => $this->getShift(),
            'processed_at' => now(),
        ]);

        return redirect()->back()->with('message', 'Test result logged successfully.');
    }

    /**
     * Lab inventory: read-only dye lots from production inventory +
     * lab stock-solution prep log owned by the lab.
     */
    public function inventory()
    {
        $dyes = ManufacturingInventoryItem::with('material')
            ->where('department', 'dyeing')
            ->where('category', 'Dye')
            ->where('status', '!=', 'depleted')
            ->orderBy('created_at', 'asc')
            ->take(100)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'control_number' => $item->control_number,
                'material_name' => $item->material->display_name ?? 'Unknown',
                'remaining_quantity' => $item->remaining_quantity,
                'unit' => $item->unit,
            ]);

        $solutions = LabStockSolution::with('preparer')->latest()->get();

        return Inertia::render('Dashboard/MAN/Employee/DyeingLabChemist/Inventory', [
            'dyes' => $dyes,
            'solutions' => $solutions,
        ]);
    }

    public function storeSolution(Request $request)
    {
        $validated = $request->validate([
            'material_name' => 'required|string|max:255',
            'concentration' => 'nullable|string|max:64',
            'lot_no' => 'nullable|string|max:255',
            'prepared_at' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:prepared_at',
            'sds_url' => 'nullable|url|max:2048',
            'remarks' => 'nullable|string',
        ]);

        LabStockSolution::create([
            ...$validated,
            'prepared_by' => $this->staff()->id,
        ]);

        return redirect()->back()->with('message', 'Stock solution logged successfully.');
    }

    public function destroySolution(LabStockSolution $solution)
    {
        $solution->delete();

        return redirect()->back()->with('message', 'Stock solution record removed.');
    }

    /**
     * Bulk transfer: approved dips ready for lab-to-bulk sign-off.
     */
    public function transfer()
    {
        $dips = LabDipRequest::with(['trials', 'salesOrder.client', 'salesOrder.recipe.product'])
            ->where('status', 'approved')
            ->latest()
            ->get()
            ->map(function ($dip) {
                $passed = $dip->trials->where('status', 'passed')->values();
                $salesOrder = $dip->salesOrder;
                $recipe = $salesOrder?->recipe;
                $dyeingFormula = $recipe?->dyeing_formula ?: $passed->first()?->formula;
                $recipeMaterials = is_array($recipe?->materials) ? $recipe->materials : [];
                $materials = Material::whereIn('id', array_keys($recipeMaterials))->get()->keyBy('id');
                $recipeRows = collect($recipeMaterials)->map(function ($quantity, $materialId) use ($materials) {
                    $material = $materials->get($materialId);
                    return [
                        'material_id' => $materialId,
                        'name' => $material?->display_name ?? ('Material #'.$materialId),
                        'category' => $material?->category,
                        'quantity' => $quantity,
                        'unit' => $material?->unit,
                    ];
                })->values();

                return [
                    'id' => $dip->id,
                    'code' => $dip->code,
                    'pantone_code' => $dip->pantone_code,
                    'sales_order' => $salesOrder ? [
                        'id' => $salesOrder->id,
                        'jo_number' => $salesOrder->jo_number,
                        'control_number' => $salesOrder->control_number,
                        'client' => $salesOrder->client?->company_name,
                        'color' => $salesOrder->color,
                        'yarn_type' => $salesOrder->yarn_type,
                        'design' => $salesOrder->design,
                        'quantity' => (float) $salesOrder->quantity,
                        'status' => $salesOrder->status,
                        'recipe_id' => $salesOrder->recipe_id,
                        'recipe' => ($recipe || is_array($dyeingFormula)) ? [
                            'id' => $recipe?->id,
                            'product' => $recipe?->product?->name,
                            'yarn_type' => $recipe?->yarn_type ?? $salesOrder->yarn_type,
                            'dye_color' => $recipe?->dye_color ?? $salesOrder->color,
                            'weave_design' => $recipe?->weave_design ?? $salesOrder->design,
                            'materials' => $recipeRows->all(),
                            'formula_groups' => $this->dyeingFormulaGroups(is_array($dyeingFormula) ? $dyeingFormula : null),
                        ] : null,
                        'has_recipe' => ! is_null($salesOrder->recipe_id),
                    ] : null,
                    'passed_trials' => $passed->map(fn ($t) => [
                        'id' => $t->id,
                        'trial_no' => $t->trial_no,
                        'delta_e' => $t->delta_e,
                        'formula' => $t->formula,
                    ])->values(),
                ];
            });

        return Inertia::render('Dashboard/MAN/Employee/DyeingLabChemist/Transfer', [
            'dips' => $dips,
        ]);
    }

    /**
     * Sign off a passed trial to the dye-house floor: builds the BomRecord
     * recipe the dyeing_color page already reads and links it to the JO.
     */
    /**
     * Resolve free-typed yarn text (e.g. a JO's yarn_type) to a Yarn
     * material ID: exact case-insensitive match first, then a
     * contains-match inside the Yarn category. Null when nothing matches.
     */
    protected function resolveYarnMaterialId(?string $text): ?int
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }
        $yarns = Material::where('category', 'Yarn')->get(['id', 'name', 'yarn_tier', 'category']);
        $exact = $yarns->filter(fn ($m) => strcasecmp($m->display_name, $text) === 0);
        if ($exact->count() === 1) return (int) $exact->first()->id;

        // A base name shared by multiple tiers cannot be resolved safely.
        $baseMatches = $yarns->filter(fn ($m) => strcasecmp((string) $m->name, $text) === 0);
        return $baseMatches->count() === 1 ? (int) $baseMatches->first()->id : null;
    }

    public function signOff(Request $request)
    {
        $validated = $request->validate([
            'dip_request_id' => 'required|exists:lab_dip_requests,id',
            'trial_id' => 'required|exists:lab_trials,id',
            'correction_factor' => 'nullable|numeric|min:0.01|max:10',
        ]);

        $dip = LabDipRequest::with('salesOrder.recipe')->findOrFail($validated['dip_request_id']);
        if ($dip->status !== 'approved') {
            return back()->withErrors(['error' => 'Only approved dip requests can be transferred.']);
        }
        if (! $dip->salesOrder) {
            return back()->withErrors(['error' => 'This dip has no linked job order.']);
        }

        $trial = LabTrial::where('id', $validated['trial_id'])
            ->where('dip_request_id', $dip->id)
            ->where('status', 'passed')
            ->firstOrFail();

        $formula = $trial->formula ?? [];
        // ID-keyed recipe materials so downstream stock checks resolve
        // (legacy name-only trial rows are resolved by name inside).
        $materials = $this->recipeMaterialsFromFormula(is_array($formula) ? $formula : []);

        // Yarn belongs in the recipe too: resolve the JO's yarn text to a
        // Yarn material (1 kg yarn per kg ordered, like recipe #1) so the
        // ECO stock check reads it. Unmatched text leaves yarn text-only.
        $yarnText = $dip->salesOrder->yarn_type ?? $dip->salesOrder->recipe?->yarn_type;
        $yarnMid = $this->resolveYarnMaterialId($yarnText);
        if ($yarnMid && ! isset($materials[$yarnMid])) {
            $materials[$yarnMid] = 1;
        }

        $recipe = BomRecord::create([
            'client_id' => $dip->salesOrder->client_id,
            'product_id' => $dip->salesOrder->recipe?->product_id,
            'yarn_type' => $dip->salesOrder->yarn_type ?? $dip->salesOrder->recipe?->yarn_type,
            'dye_color' => $dip->pantone_code ?? $dip->salesOrder->color,
            'weave_design' => $dip->salesOrder->design ?? $dip->salesOrder->recipe?->weave_design,
            // ID-keyed recipe materials (material_id => quantity) so stock
            // checks resolve; the dyeing page reads names from formulas.
            'materials' => $materials,
            // Keep the chemist's sectioned formula alongside normalized stock
            // quantities so floor work pages can preserve its recipe groups.
            'dyeing_formula' => $formula,
        ]);

        $dip->salesOrder->update(['recipe_id' => $recipe->id]);

        return redirect()->back()->with(
            'message',
            "Recipe from trial {$trial->trial_no} signed off to JO {$dip->salesOrder->jo_number} (correction ×" . ($validated['correction_factor'] ?? 1) . ').'
        );
    }

    /**
     * Personal work log — own dip requests plus every fabric color the
     * chemist formulated for CRM conversations (mirrors the shade page's
     * sample queue, with approval state, full formula, recipe and photo).
     */
    public function history()
    {
        $samplesQuery = FabricSampleRequest::with(['inquiry.client', 'product', 'recipe', 'formulator:id,name'])
            ->where('formulated_by', $this->staff()->id);

        if ($search = request('search')) {
            $samplesQuery->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('fabric_name', 'like', "%{$search}%")
                    ->orWhere('color_description', 'like', "%{$search}%");
            });
        }
        if ($from = request('from')) {
            $samplesQuery->whereDate('created_at', '>=', $from);
        }
        if ($to = request('to')) {
            $samplesQuery->whereDate('created_at', '<=', $to);
        }

        return Inertia::render('Dashboard/MAN/Employee/Common/History', [
            'roleLabel' => 'Color Request',
            'historyRoute' => 'man.staff.dyeing-lab-chemist.history',
            'dateColumn' => 'processed_at',
            'jobs' => $this->staffHistory(LabDipRequest::class, ['salesOrder', 'trials']),
            'sampleHistory' => $samplesQuery->latest()->paginate(15, ['*'], 'sample_page')->withQueryString(),
        ]);
    }

    /**
     * First-time-right rate + average lab-dip turn time (all lab output).
     */
    protected function labMetrics(): array
    {
        $approved = LabDipRequest::with('trials')->where('status', 'approved')->get();

        $ftrBase = 0;
        $ftrHit = 0;
        $turnHours = [];
        foreach ($approved as $dip) {
            $passed = $dip->trials->where('status', 'passed')->sortBy('trial_no')->values();
            if ($passed->isNotEmpty()) {
                $ftrBase++;
                if ((int) $passed->first()->trial_no === 1) {
                    $ftrHit++;
                }
            }
            if ($dip->created_at && $dip->updated_at) {
                $turnHours[] = $dip->created_at->diffInHours($dip->updated_at);
            }
        }

        return [
            'ftr_rate' => $ftrBase > 0 ? round($ftrHit / $ftrBase * 100, 1) : 0,
            'avg_turn_hours' => count($turnHours) > 0 ? round(array_sum($turnHours) / count($turnHours), 1) : 0,
            'trials_total' => LabTrial::count(),
        ];
    }
}
