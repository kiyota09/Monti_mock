<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\Fabric;
use App\Models\Man\DyeingBatchFabric;
use App\Models\Man\DyeingStageRecord;
use App\Models\Man\ManTaskAssignment;
use App\Models\Man\ManufacturingInventoryItem;
use App\Services\Man\DyeingInitialBatchService;
use App\Services\Man\DyeingMachineQueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DyeingTreatmentController extends ManufacturingStaffController
{
    private function roleLabel(): string
    {
        return $this->staff()->manufacturing_role === 'dyeing_pre_treatment'
            ? 'Dyeing Pre Treatment'
            : 'Dyeing Post Treatment';
    }

    private function stageStatuses(): array
    {
        return $this->staff()->manufacturing_role === 'dyeing_pre_treatment'
            ? ['pre_treatment']
            : ['post_treatment'];
    }

    private function stageFabrics()
    {
        $stage = $this->staff()->manufacturing_role === 'dyeing_pre_treatment' ? 'pre_treatment' : 'post_treatment';
        $query = Fabric::with(['machine', 'salesOrder'])
            ->whereIn('id', DyeingBatchFabric::where('current_stage',$stage)->where('status','active')->select('fabric_id'));
        $this->scopeToAssignment($query);

        return $query;
    }

    public function dashboard()
    {
        $query = $this->stageFabrics();

        return Inertia::render('Dashboard/MAN/Employee/DyeingTreatment/Dashboard', [
            'treatmentLabel' => $this->roleLabel(),
            'routePrefix' => $this->staff()->manufacturing_role === 'dyeing_pre_treatment' ? 'man.staff.dyeing-pre-treatment' : 'man.staff.dyeing-treatment',
            'stats' => [
                'waiting' => (clone $query)->count(),
                'updated_today' => (clone $query)->whereDate('updated_at', today())->count(),
            ],
            'recent' => (clone $query)->latest('updated_at')->take(8)->get(),
        ]);
    }

    public function work()
    {
        $stage = $this->staff()->manufacturing_role === 'dyeing_pre_treatment' ? 'pre_treatment' : 'post_treatment';
        // Older assignments may predate automatic batch creation. Materialize
        // their first-stage work items when the assigned operator opens work.
                if ($this->staff()->manufacturing_role === 'dyeing_pre_treatment') {
            ManTaskAssignment::with('manufacturingOrder')->where('department', 'dyeing')
                ->where('stage', 'dyeing_pre_treatment')->where('assigned_to', $this->staff()->id)
                ->active()->get()->each(function ($assignment) {
                    if ($assignment->manufacturingOrder) {
                        app(DyeingInitialBatchService::class)->prepare(
                            $assignment->manufacturingOrder,
                            $assignment->stage,
                            (int) $this->staff()->id,
                            $assignment->assigned_by ? (int) $assignment->assigned_by : null,
                            $assignment->fabric_id ? [(int) $assignment->fabric_id] : null,
                        );
                    }
                });
        }

        $fabrics = $this->stageFabrics()->with('dyeingBatchFabric.batch.machine')
            ->latest()->take(100)->get(['id', 'code', 'yarn_type', 'weight', 'status', 'machine_id', 'sales_order_id', 'manufacturing_order_id', 'created_at']);
        $allowedCategories = $stage === 'pre_treatment' ? ['Pre-Treatment','Pre Treatment'] : ['Post-Treatment','Post Treatment'];
        $materials = ManufacturingInventoryItem::with('material')->where('department','dyeing')->whereIn('category',$allowedCategories)
            ->where('remaining_quantity','>',0)->where('status','!=','depleted')->orderBy('created_at')
            ->get()->filter(fn($item) => strtolower((string)$item->unit) === 'kg')->map(fn($item) => [
                'id'=>$item->id, 'name'=>$item->material?->display_name ?? $item->material?->name ?? 'Material',
                'control_number'=>$item->control_number, 'remaining_quantity'=>(float)$item->remaining_quantity,
            ])->values();

        return Inertia::render('Dashboard/MAN/Employee/DyeingTreatment/Index', [
            'treatmentLabel' => $this->roleLabel(),
            'routePrefix' => $this->staff()->manufacturing_role === 'dyeing_pre_treatment' ? 'man.staff.dyeing-pre-treatment' : 'man.staff.dyeing-treatment',
            'fabrics' => $fabrics,
            'materials' => $materials,
            'assignedOrders' => $this->assignedProductionOrders('dyeing'),
        ]);
    }

    public function submit(Request $request)
    {
        $stage = $this->staff()->manufacturing_role === 'dyeing_pre_treatment' ? 'pre_treatment' : 'post_treatment';
        $data = $request->validate([
            'fabric_ids' => ['required','array','min:1'], 'fabric_ids.*' => ['required','integer','distinct','exists:fabrics,id'],
            'materials_used' => ['required','array','min:1'], 'materials_used.*.inventory_item_id' => ['required','distinct','exists:manufacturing_inventory_items,id'],
            'materials_used.*.quantity_used' => ['required','numeric','gt:0'],
        ]);
        DB::transaction(function () use ($data,$stage) {
            $items = DyeingBatchFabric::with(['batch.machine','fabric'])->whereIn('fabric_id',$data['fabric_ids'])->lockForUpdate()->get();
            abort_unless($items->count() === count($data['fabric_ids']),422,'One or more selected fabrics are not in a dyeing batch.');
            $first = $items->first(); $batch = $first->batch;
            abort_unless($items->every(fn($item) => $item->batch?->machine_id === $batch?->machine_id && $item->current_stage === $stage && $item->status === 'active'),422,'Select active fabrics assigned to the same machine at this stage.');
            foreach ($items->groupBy('dyeing_batch_id') as $batchItems) {
                $capacity = (float) $batchItems->first()->batch->load_capacity_kg;
                abort_if($batchItems->sum(fn($item) => (float) $item->fabric->weight) > $capacity + 0.001,422,'Selected fabrics exceed a batch machine load capacity.');
            }
            foreach ($items as $item) abort_unless($this->isAssignedFabric((int) $item->fabric_id, $item->fabric->manufacturing_order_id, $item->fabric->sales_order_id),403,'This fabric code is not assigned to you by the Dyeing Supervisor.');
            $queue = app(DyeingMachineQueueService::class);
            $assignments = $queue->assertCanRecord((int) $this->staff()->id, (int) $batch->machine_id, $stage, $items->pluck('fabric_id')->all());
            $used = [];
            $expectedCategory = $stage === 'pre_treatment' ? 'pretreatment' : 'posttreatment';
            foreach ($data['materials_used'] as $row) {
                $inventory = ManufacturingInventoryItem::with('material')->where('department','dyeing')->lockForUpdate()->findOrFail($row['inventory_item_id']);
                abort_unless(preg_replace('/[^a-z]/','',strtolower($inventory->category)) === $expectedCategory,422,'Choose materials for the current treatment stage only.');
                abort_unless(strtolower((string)$inventory->unit)==='kg',422,'Choose an inventory material tracked in kilograms.');
                abort_if((float)$inventory->remaining_quantity + 0.00001 < (float)$row['quantity_used'],422,'A selected material does not have enough stock.');
                $inventory->remaining_quantity=max(0,(float)$inventory->remaining_quantity-(float)$row['quantity_used']);
                $inventory->status=$inventory->remaining_quantity<=0?'depleted':'partial'; $inventory->save();
                $used[]=['inventory_item_id'=>$inventory->id,'name'=>$inventory->material?->display_name ?? $inventory->material?->name ?? 'Material','quantity_used'=>(float)$row['quantity_used'],'unit'=>'kg'];
            }
            $submission = strtoupper($stage).'-'.now()->format('ymdHis').'-'.Str::upper(Str::random(5));
            foreach ($items as $item) {
                $attempt = $item->records()->where('stage',$stage)->max('attempt') + 1;
                DyeingStageRecord::create(['dyeing_batch_fabric_id'=>$item->id,'stage'=>$stage,'attempt'=>$attempt,'operator_id'=>$this->staff()->id,
                    'code'=>$submission.'-'.$item->fabric_id,'submission_code'=>$submission,'materials_used'=>$item->id===$first->id?$used:null,
                    'review_status'=>'awaiting_review','processed_at'=>now()]);
                $item->update(['status'=>'awaiting_review']);
            }
            $queue->completeSubmitted($assignments);
        });
        return back()->with('message','Treatment recorded and sent to the Dyeing Supervisor for inspection.');
    }

    public function history()
    {
        $stage = $this->staff()->manufacturing_role === 'dyeing_pre_treatment' ? 'pre_treatment' : 'post_treatment';
        $query = DyeingStageRecord::with(['operator:id,name', 'batchFabric.fabric:id,code,yarn_type,weight'])
            ->where('operator_id', $this->staff()->id)
            ->where('stage', $stage);
        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhereHas('batchFabric.fabric', fn ($fabric) => $fabric->where('code', 'like', "%{$search}%"));
            });
        }
        if ($from = request('from')) {
            $query->whereDate('processed_at', '>=', $from);
        }
        if ($to = request('to')) {
            $query->whereDate('processed_at', '<=', $to);
        }

        return Inertia::render('Dashboard/MAN/Employee/DyeingTreatment/History', [
            'treatmentLabel' => $this->roleLabel(),
            'routePrefix' => $this->staff()->manufacturing_role === 'dyeing_pre_treatment' ? 'man.staff.dyeing-pre-treatment' : 'man.staff.dyeing-treatment',
            'records' => $query->latest('processed_at')->paginate(20)->withQueryString(),
        ]);
    }

    public function reports()
    {
        $query = $this->stageFabrics();

        return Inertia::render('Dashboard/MAN/Employee/DyeingTreatment/Reports', [
            'treatmentLabel' => $this->roleLabel(),
            'stats' => [
                'in_stage' => (clone $query)->count(),
                'updated_today' => (clone $query)->whereDate('updated_at', today())->count(),
                'total_fabrics' => Fabric::count(),
            ],
        ]);
    }
}
