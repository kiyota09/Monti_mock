<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\BomRecord;
use App\Models\Crm\FabricSampleRequest;
use App\Models\Eco\ConversationAttachment;
use App\Models\Eco\ConversationMessage;
use App\Models\Inv\Material;
use App\Models\Man\LabDipRequest;
use App\Models\Man\LabStockSolution;
use App\Models\Man\LabTest;
use App\Models\Man\LabTrial;
use App\Models\Man\ManufacturingInventoryItem;
use App\Models\Ord\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $sampleRequests = FabricSampleRequest::with(['inquiry.client', 'product', 'requester:id,name'])
            ->whereNotIn('status', [FabricSampleRequest::STATUS_APPROVED, FabricSampleRequest::STATUS_CANCELLED])
            ->orderByRaw("FIELD(urgency, 'urgent', 'high', 'normal', 'low')")
            ->orderBy('created_at', 'asc')
            ->get();

        return Inertia::render('Dashboard/MAN/Employee/DyeingLabChemist/Shades', [
            'dips' => $dips,
            'jobOrders' => $jobOrders,
            'sampleRequests' => $sampleRequests,
            // Inventory-connected autocomplete source for dyestuff/auxiliary
            // name inputs — rows must reference these IDs (see storeTrial).
            'materials' => Material::orderBy('name')->get(['id', 'mat_id', 'name', 'unit', 'category']),
        ]);
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

    /**
     * Formulate the color: record the recipe formula, upload the sample
     * photo, create the BomRecord, and send everything back to the CRM
     * team as a thread message (hidden from the client until forwarded).
     */
    public function formulateSample(Request $request, FabricSampleRequest $sampleRequest)
    {
        if (! in_array($sampleRequest->status, [
            FabricSampleRequest::STATUS_REQUESTED,
            FabricSampleRequest::STATUS_IN_PROGRESS,
            FabricSampleRequest::STATUS_ADJUSTMENT_REQUESTED,
        ], true)) {
            return back()->withErrors(['error' => 'This sample request is already closed.']);
        }

        $validated = $request->validate([
            'dyestuffs' => 'nullable|array',
            'dyestuffs.*.material_id' => 'required|exists:materials,id',
            'dyestuffs.*.name' => 'nullable|string|max:255',
            'dyestuffs.*.pct' => 'nullable|numeric|min:0',
            'auxiliaries' => 'nullable|array',
            'auxiliaries.*.material_id' => 'required|exists:materials,id',
            'auxiliaries.*.name' => 'nullable|string|max:255',
            'auxiliaries.*.gpl' => 'nullable|numeric|min:0',
            'liquor_ratio' => 'nullable|string|max:32',
            'formula_notes' => 'nullable|string|max:2000',
            // One or more yarns (designs may combine 2+ yarns): each row is
            // inventory-connected, like dyestuffs/auxiliaries.
            'yarns' => 'required|array|min:1',
            'yarns.*.material_id' => ['required', Rule::exists('materials', 'id')->where(fn ($q) => $q->where('category', 'Yarn'))],
            'yarns.*.name' => 'nullable|string|max:255',
            'yarns.*.qty' => 'required|numeric|min:0.01|max:1000',
            'weave_design' => 'required|string|max:255',
            'sample_photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        DB::beginTransaction();
        try {
            $extension = $request->file('sample_photo')->extension();
            $photoPath = $request->file('sample_photo')->storeAs(
                'fabric_samples',
                'sample_' . $sampleRequest->code . '_' . time() . '.' . $extension,
                'public'
            );

            $formula = [
                'dyestuffs' => $this->resolveFormulaRows($validated['dyestuffs'] ?? [], 'pct'),
                'auxiliaries' => $this->resolveFormulaRows($validated['auxiliaries'] ?? [], 'gpl'),
                'liquor_ratio' => $validated['liquor_ratio'] ?? null,
                'notes' => $validated['formula_notes'] ?? null,
            ];

            // ID-keyed recipe materials (kg per kg ordered — pct/gpl are
            // converted inside) so downstream stock checks resolve.
            $materials = $this->recipeMaterialsFromFormula($formula);
            // Yarn(s) are part of the recipe too (kg yarn per kg ordered)
            // so the ECO stock check reads them like recipe #1.
            $yarnRows = $this->resolveFormulaRows($validated['yarns'] ?? [], 'qty');
            $yarnNames = [];
            foreach ($yarnRows as $yr) {
                $materials[$yr['material_id']] = ($materials[$yr['material_id']] ?? 0) + (float) ($yr['qty'] ?? 0);
                $yarnNames[] = $yr['name'];
            }
            $formula['yarns'] = $yarnRows;

            $inquiry = $sampleRequest->inquiry;
            $recipe = BomRecord::updateOrCreate(
                [
                    'client_id' => $sampleRequest->client_id,
                    'product_id' => $sampleRequest->product_id,
                ],
                [
                    'yarn_type' => implode(' + ', array_unique($yarnNames)),
                    'dye_color' => $sampleRequest->color_description,
                    'weave_design' => $validated['weave_design'],
                    'materials' => $materials,
                ]
            );

            $sampleRequest->update([
                // Straight to forwarded: the client sees the formulated
                // sample (formula + photo) at once and decides approve /
                // adjust. Only the lab request itself stays internal.
                // A fresh formulation resolves any pending adjustment notes.
                'status' => FabricSampleRequest::STATUS_FORWARDED,
                'formula' => $formula,
                'sample_image_path' => $photoPath,
                'recipe_id' => $recipe->id,
                'formulated_by' => $this->staff()->id,
                'adjustment_notes' => null,
            ]);

            // NOTE: the formula itself stays off the message text on purpose —
            // it lives on the sample request record, visible only to the CRM
            // team and the lab. The client decides from the sample photo.
            // NOTE: a regular bubble (not a system pill) on purpose — the
            // photo, the per-image approve/adjust buttons and (CRM-side)
            // the formulation all render in the message-bubble branch.
            // The formula itself stays off the message text: it lives on
            // the sample request record, visible only to CRM and the lab.
            $message = ConversationMessage::create([
                'inquiry_id' => $sampleRequest->inquiry_id,
                'sender_type' => 'eco',
                'message' => "🧬 Lab sample ready ({$sampleRequest->code}): {$sampleRequest->fabric_name}\n"
                    . "Please review the sample photo below and approve it or request a color adjustment.",
                'is_system_event' => false,
                // Client-facing at once: they decide approve vs adjust.
                'visible_to_client' => true,
                'sample_request_id' => $sampleRequest->id,
            ]);

            ConversationAttachment::create([
                'conversation_message_id' => $message->id,
                'file_path' => $photoPath,
                'file_name' => 'fabric-sample-' . $sampleRequest->code . '.' . $request->file('sample_photo')->getClientOriginalExtension(),
                'file_type' => $request->file('sample_photo')->getMimeType(),
            ]);

            $inquiry?->update(['last_message_at' => now()]);

            DB::commit();

            return redirect()->back()->with('message', "Sample {$sampleRequest->code} formulated and sent back to CRM.");
        } catch (\Exception $e) {
            DB::rollBack();

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
            : Material::whereIn('id', $ids)->pluck('name', 'id');

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
            ['auxiliaries', 'gpl', fn ($v) => ((float) $v) * $lr / 1000],
        ];

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
                'material_name' => $item->material->name ?? 'Unknown',
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
        $dips = LabDipRequest::with(['trials', 'salesOrder.recipe'])
            ->where('status', 'approved')
            ->latest()
            ->get()
            ->map(function ($dip) {
                $passed = $dip->trials->where('status', 'passed')->values();
                return [
                    'id' => $dip->id,
                    'code' => $dip->code,
                    'pantone_code' => $dip->pantone_code,
                    'sales_order' => $dip->salesOrder ? [
                        'id' => $dip->salesOrder->id,
                        'jo_number' => $dip->salesOrder->jo_number,
                        'color' => $dip->salesOrder->color,
                        'yarn_type' => $dip->salesOrder->yarn_type,
                        'has_recipe' => ! is_null($dip->salesOrder->recipe_id),
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
        $yarns = Material::where('category', 'Yarn')->get(['id', 'name']);
        $exact = $yarns->first(fn ($m) => strcasecmp((string) $m->name, $text) === 0);
        if ($exact) {
            return (int) $exact->id;
        }
        $lower = strtolower($text);
        $hit = $yarns->first(fn ($m) => str_contains(strtolower((string) $m->name), $lower)
            || str_contains($lower, strtolower((string) $m->name)));
        return $hit ? (int) $hit->id : null;
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
            'roleLabel' => 'Dyeing Lab Chemist',
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
