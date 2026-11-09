<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\DyeJob;
use App\Models\Man\DyeJobChemical;
use App\Models\Man\DyeingStageRecord;
use App\Models\Man\Fabric;
use App\Models\Man\Machine;
use App\Models\Man\MachineReport;
use App\Models\Man\ManufacturingOrder;
use App\Models\Man\ManufacturingInventoryItem;
use App\Services\Man\BoilerSteamService;
use App\Services\Man\DyeingMachineQueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DyeingColorController extends ManufacturingStaffController
{
    public function index()
    {
        // Only fabrics still awaiting dye work: at 'dyeing' stage with no dye
        // job recorded yet. Once recorded, the fabric sits with the quality
        // checker until approved — it must not linger in the dye queue.
        $pendingQuery = Fabric::where('status', 'dyeing')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('dye_jobs')
                    ->whereColumn('dye_jobs.fabric_id', 'fabrics.id');
            });
        $this->scopeToAssignment($pendingQuery);
        $pendingCount = $pendingQuery->count();
        $recentJobs   = DyeJob::with('fabric')
            ->where('operator_id', $this->staff()->id)
            ->latest()
            ->take(10)
            ->get();

        $nextQueueQuery = Fabric::with('machine')
            ->where('status', 'dyeing')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('dye_jobs')
                    ->whereColumn('dye_jobs.fabric_id', 'fabrics.id');
            });
        $this->scopeToAssignment($nextQueueQuery);
        $nextQueue = $nextQueueQuery
            ->orderBy('created_at', 'asc')
            ->take(3)
            ->get(['id', 'code', 'yarn_type', 'weight', 'created_at']);

        return Inertia::render('Dashboard/MAN/Employee/DyeingColor/Index', [
            'stats' => [
                'pending'     => $pendingCount,
                'total_today' => DyeJob::whereDate('processed_at', today())->count(),
            ],
            'recentJobs' => $recentJobs,
            'efficiency' => array_merge(
                $this->staffEfficiency(DyeJob::class),
                ['machines' => $this->machineAvailability('dyeing')]
            ),
            'nextQueue' => $nextQueue,
        ]);
    }

    /**
     * Personal work log — own dye jobs only.
     */
    public function history()
    {
        return Inertia::render('Dashboard/MAN/Employee/Common/History', [
            'roleLabel' => 'Dyeing Color',
            'historyRoute' => 'man.staff.dyeing-color.history',
            'dateColumn' => 'processed_at',
            'jobs' => $this->staffHistory(DyeJob::class, ['fabric.machine', 'fabric.salesOrder.client', 'fabric.salesOrder.recipe.product', 'machine', 'operator', 'chemicals', 'fabric.bleachingJobs', 'fabric.packages']),
        ]);
    }

    public function dyeingColor()
    {
        // Fabrics ready for dyeing — eager-load their linked JO + recipe.
        // Only fabrics with no dye job recorded yet: once recorded, the
        // fabric waits for the dyeing supervisor's gate (stays 'dyeing') until the
        // checker approves it to bleaching or rejects it.
        $fabricsQuery = Fabric::with(['machine', 'operator', 'salesOrder.recipe', 'dyeingBatchFabric.batch'])
            ->where('status', 'dyeing')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('dye_jobs')
                    ->whereColumn('dye_jobs.fabric_id', 'fabrics.id');
            });
        $this->scopeToAssignment($fabricsQuery);

        $fabrics = $fabricsQuery
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($fabric) {
                $salesOrder = $fabric->salesOrder;
                $recipe     = $salesOrder?->recipe;

                // Decode recipe materials if present (array via model cast,
                // or a raw JSON string on older rows).
                $materialsData = [];
                if ($recipe && $recipe->materials) {
                    $raw = $recipe->materials;
                    $json = is_string($raw) ? json_decode($raw, true) : $raw;
                    if (is_array($json)) {
                        foreach ($json as $matId => $qty) {
                            $materialsData[] = ['material_id' => $matId, 'quantity' => $qty];
                        }
                    }
                }

                return [
                    'id'           => $fabric->id,
                    'manufacturing_order_id' => $fabric->manufacturing_order_id,
                    'sales_order_id' => $fabric->sales_order_id,
                    'batch_id'     => $fabric->dyeingBatchFabric?->dyeing_batch_id,
                    'batch_machine_id' => $fabric->dyeingBatchFabric?->batch?->machine_id,
                    'batch_capacity_kg' => (float)($fabric->dyeingBatchFabric?->batch?->load_capacity_kg ?? 0),
                    'code'         => $fabric->code,
                    'yarn_type'    => $fabric->yarn_type,
                    'weight'       => $fabric->weight,
                    'shift'        => $fabric->shift,
                    'remarks'      => $fabric->remarks,
                    'processed_at' => $fabric->processed_at?->format('M d, Y g:i A'),
                    'machine'      => $fabric->machine
                        ? ['machine_no' => $fabric->machine->machine_no] : null,
                    'operator'     => $fabric->operator
                        ? ['name' => $fabric->operator->name] : null,

                    // Linked job order info
                    'sales_order'  => $salesOrder ? [
                        'jo_number' => $salesOrder->jo_number,
                        'color'     => $salesOrder->color,
                        'design'    => $salesOrder->design,
                        'quantity'  => $salesOrder->quantity,
                        'yarn_type' => $salesOrder->yarn_type,
                    ] : null,

                    // Recipe — the dye staff uses this to know the color formula
                    'recipe'       => $recipe ? [
                        'id'           => $recipe->id,
                        'yarn_type'    => $recipe->yarn_type,
                        'dye_color'    => $recipe->dye_color,
                        'weave_design' => $recipe->weave_design,
                        'materials'    => $materialsData,
                    ] : null,
                ];
            });

        $steamReady = app(BoilerSteamService::class)->isAvailable();
        $machines = Machine::where('type', 'dyeing')
            ->where('status', 'available')
            ->where(function ($query) use ($steamReady) {
                $query->where('power', '!=', 'Steam');
                if ($steamReady) $query->orWhere('power', 'Steam');
            })
            ->get(['id', 'machine_no']);

        // Dye chemicals available in production inventory (dyeing department)
        $dyes = ManufacturingInventoryItem::with('material')
            ->where('department', 'dyeing')
            ->where('category', 'Dye')
            ->where('status', '!=', 'depleted')
            ->where('remaining_quantity', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get()
            ->filter(fn ($item) => strtolower((string)$item->unit) === 'kg')
            ->map(fn ($item) => [
                'id'                 => $item->id,
                'material_id'        => $item->material_id,
                'control_number'     => $item->control_number,
                'material_name'      => $item->material->display_name,
                'remaining_quantity' => $item->remaining_quantity,
                'unit'               => $item->unit,
            ]);

        $assignedOrders = collect($this->assignedProductionOrders('dyeing'))
            ->filter(fn (array $order) => $order['sales_order_id'] || $order['manufacturing_order_id'] || $order['fabric_code'])
            ->unique(fn (array $order) => $order['sales_order_id']
                ? 'sales-order:'.$order['sales_order_id']
                : ($order['manufacturing_order_id']
                    ? 'manufacturing-order:'.$order['manufacturing_order_id']
                    : 'fabric:'.$order['fabric_code']))
            ->values()
            ->all();

        return Inertia::render('Dashboard/MAN/Employee/DyeingColor/DyeingColor', [
            'fabrics'  => $fabrics,
            'assignedOrders' => $assignedOrders,
            'machines' => $machines,
            'steamReady' => $steamReady,
            'dyes'     => $dyes,       // NEW — inventory-sourced dye chemicals
        ]);
    }

    /** Record a dye job using the JO formula and automatically allocated inventory lots. */
    public function storeDye(Request $request)
    {
        $validated = $request->validate([
            'fabric_ids' => 'required|array|min:1', 'fabric_ids.*' => 'required|integer|distinct|exists:fabrics,id',
            'machine_id' => 'required|exists:machines,id', 'remarks' => 'nullable|string',
            'dyes_used' => 'required|array|min:1',
            'dyes_used.*.material_id' => 'required|integer|exists:materials,id',
            'dyes_used.*.quantity_used' => 'required|numeric|min:0.01',
        ]);
        $machine = Machine::whereKey($validated['machine_id'])->where('type','dyeing')->where('status','available')->firstOrFail();
        if ($machine->power === 'Steam' && !app(BoilerSteamService::class)->isAvailable()) return back()->withErrors(['machine_id'=>'Steam is not available.']);
        DB::beginTransaction();
        try {
            $fabrics=Fabric::with(['dyeingBatchFabric.batch', 'manufacturingOrder', 'salesOrder'])->whereIn('id',$validated['fabric_ids'])->lockForUpdate()->get();
            abort_unless($fabrics->count()===count($validated['fabric_ids']) && $fabrics->every(fn($f)=>$f->status==='dyeing'),422,'One or more fabrics are no longer waiting for dyeing.');
            $items=$fabrics->map(fn($f)=>$f->dyeingBatchFabric); $first=$items->first();
            abort_unless($first && $items->every(fn($i)=>$i && $i->batch?->machine_id===$first->batch?->machine_id && $i->current_stage==='color' && $i->status==='active'),422,'Select active fabrics assigned to the same machine at this stage.');
            abort_unless((int)$first->batch->machine_id===(int)$machine->id,422,'Use the machine assigned to this JO batch.');
            $queue = app(DyeingMachineQueueService::class);
            $assignments = $queue->assertCanRecord((int) $this->staff()->id, (int) $machine->id, 'color', $fabrics->pluck('id')->all());
            foreach($fabrics->groupBy(fn($fabric)=>$fabric->dyeingBatchFabric->dyeing_batch_id) as $batchFabrics) abort_if($batchFabrics->sum(fn($f)=>(float)$f->weight)>(float)$batchFabrics->first()->dyeingBatchFabric->batch->load_capacity_kg+0.001,422,'Selected fabrics exceed a batch machine load capacity.');
            foreach($fabrics as $f) abort_unless($this->isAssignedFabric((int) $f->id, $f->manufacturing_order_id, $f->sales_order_id),403,'This fabric code is not assigned to you by the Dyeing Supervisor.');

            $firstFabric = $fabrics->first();
            $order = $firstFabric->manufacturingOrder
                ?: ManufacturingOrder::find($first->batch?->manufacturing_order_id)
                ?: $firstFabric->salesOrder?->manufacturingOrder;
            $salesOrder = $firstFabric->salesOrder ?: $order?->salesOrder ?: $first->batch?->salesOrder;
            $formula = $this->dyeingFormulaForOrder($order, $salesOrder);
            $selectedWeight = (float)$fabrics->sum(fn($fabric)=>(float)$fabric->weight);
            $formulaGroups = $formula ? $this->dyeingFormulaGroups($formula, $selectedWeight) : [];
            $dyeStuffGroup = collect($formulaGroups)->first(fn ($group) => strtolower($group['label'] ?? '') === 'dye stuffs');
            $formulaRows = collect($dyeStuffGroup['materials'] ?? []);
            abort_if($formulaRows->isEmpty(),422,'No dye stuffs are configured in this Job Order recipe. Ask the supervisor or dyeing lab chemist to complete it.');

            $expectedMaterialIds = $formulaRows->pluck('material_id')->map(fn ($id) => (int)$id)->unique()->values();
            abort_if($expectedMaterialIds->contains(0),422,'A dye stuff in this JO recipe is not linked to an inventory material. Ask the supervisor to correct the recipe.');
            $neededByMaterial = collect($validated['dyes_used'])
                ->groupBy(fn ($row) => (int)$row['material_id'])
                ->map(fn ($rows) => round((float)$rows->sum(fn ($row) => (float)$row['quantity_used']), 2));
            $submittedMaterialIds = $neededByMaterial->keys()->map(fn ($id) => (int)$id);
            abort_unless(
                $expectedMaterialIds->diff($submittedMaterialIds)->isEmpty()
                    && $submittedMaterialIds->diff($expectedMaterialIds)->isEmpty(),
                422,
                'The submitted dye materials do not match this Job Order recipe.'
            );

            $stockRows=[];
            foreach($neededByMaterial as $materialId=>$needed){
                $lots=ManufacturingInventoryItem::with('material')->where('department','dyeing')->where('category','Dye')
                    ->where('material_id',$materialId)->whereRaw('LOWER(unit) = ?', ['kg'])
                    ->where('status','!=','depleted')->where('remaining_quantity','>',0)
                    ->orderBy('received_at')->orderBy('id')->lockForUpdate()->get();
                foreach($lots as $stock){
                    if($needed <= 0) break;
                    $use=round(min((float)$needed,(float)$stock->remaining_quantity),2);
                    if($use <= 0) continue;
                    $stockRows[]=['usage'=>['quantity_used'=>$use],'item'=>$stock];
                    $needed=round($needed-$use,2);
                }
                abort_if($needed > 0,422,'Not enough inventory for one or more dye stuffs in this JO recipe. Ask the dyeing supervisor to check stock.');
            }
            $submission='COLOR-'.now()->format('ymdHis').'-'.Str::upper(Str::random(5));
            $primary=$stockRows[0]['item']; $totalWeight=(float)$fabrics->sum(fn($f)=>(float)$f->weight);
            foreach($fabrics as $fabric){
                $job=DyeJob::create(['fabric_id'=>$fabric->id,'machine_id'=>$machine->id,'dye_type'=>$primary->material?->display_name ?? 'Dye','chemical_no'=>$primary->control_number,
                    'remarks'=>null,'operator_id'=>$this->staff()->id,'shift'=>$this->getShift(),'code'=>$this->generateCode('CHEM',DyeJob::class),'processed_at'=>now()]);
                $batchItem=$fabric->dyeingBatchFabric; $attempt=$batchItem->records()->where('stage','color')->max('attempt')+1;
                $materialDetails=[];
                foreach($stockRows as $row){$share=round((float)$row['usage']['quantity_used']*((float)$fabric->weight/$totalWeight),4);$stock=$row['item'];
                    DyeJobChemical::create(['dye_job_id'=>$job->id,'inventory_item_id'=>$stock->id,'dye_type'=>$stock->material?->display_name ?? 'Dye','control_number'=>$stock->control_number,'quantity_used'=>$share]);
                    $materialDetails[]=['inventory_item_id'=>$stock->id,'name'=>$stock->material?->display_name ?? 'Dye','quantity_used'=>$share,'unit'=>'kg'];
                }
                DyeingStageRecord::create(['dyeing_batch_fabric_id'=>$batchItem->id,'stage'=>'color','attempt'=>$attempt,'operator_id'=>$this->staff()->id,'dye_job_id'=>$job->id,
                    'code'=>$submission.'-'.$fabric->id,'submission_code'=>$submission,'materials_used'=>$fabric->id===$fabrics->first()->id?array_map(fn($row)=>['inventory_item_id'=>$row['item']->id,'name'=>$row['item']->material?->display_name ?? 'Dye','quantity_used'=>(float)$row['usage']['quantity_used'],'unit'=>'kg'],$stockRows):null,
                    'review_status'=>'awaiting_review','processed_at'=>now()]);
                $batchItem->update(['status'=>'awaiting_review']);
            }
            foreach($stockRows as $row){$stock=$row['item'];$stock->remaining_quantity=max(0,(float)$stock->remaining_quantity-(float)$row['usage']['quantity_used']);$stock->status=$stock->remaining_quantity<=0?'depleted':'partial';$stock->save();}
            $queue->completeSubmitted($assignments);
            DB::commit();
            return back()->with('message','Grouped dye job recorded. Awaiting supervisor inspection.');
        } catch (\Throwable $e) {
            DB::rollBack();
            if($e instanceof \Illuminate\Validation\ValidationException) throw $e;
            return back()->withErrors(['error'=>'Failed to record dye job: '.$e->getMessage()]);
        }
    }
    public function reports()
    {
        $machines  = Machine::where('type', 'dyeing')->get(['id', 'machine_no', 'status']);
        $myReports = MachineReport::where('reported_by', $this->staff()->id)
            ->with('machine')->latest()->get();

        return Inertia::render('Dashboard/MAN/Employee/DyeingColor/Reports', [
            'machines'  => $machines,
            'myReports' => $myReports,
        ]);
    }

    public function reportMachine(Request $request)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'issue'      => 'required|string',
        ]);

        MachineReport::create([
            'machine_id'  => $validated['machine_id'],
            'reported_by' => $this->staff()->id,
            'issue'       => $validated['issue'],
            'status'      => 'pending',
        ]);

        return redirect()->back()->with('message', 'Machine issue reported.');
    }
}
