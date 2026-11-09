<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\BleachingJob;
use App\Models\Man\Fabric;
use App\Models\Man\DyeingBatchFabric;
use App\Models\Man\DyeingStageRecord;
use App\Models\Man\ManufacturingInventoryItem;
use App\Services\Man\DyeingMachineQueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DyeingBleachingController extends ManufacturingStaffController
{
    public function index()
    {
        $jobs = BleachingJob::with('fabric.salesOrder')
            ->where('operator_id', $this->staff()->id)
            ->latest()
            ->take(10)
            ->get();

        return Inertia::render('Dashboard/MAN/Employee/DyeingBleaching/Index', [
            'stats' => [
                'pending' => $this->bleachingQueue()->count(),
                'total_today' => BleachingJob::whereDate('processed_at', today())->count(),
            ],
            'recentJobs' => $jobs,
            'efficiency' => $this->staffEfficiency(BleachingJob::class),
        ]);
    }

    public function bleaching()
    {
        $materials = ManufacturingInventoryItem::with('material')->where('department','dyeing')->whereIn('category',['Pre-Treatment','Pre Treatment'])->where('remaining_quantity','>',0)
            ->where('status','!=','depleted')->orderBy('created_at')->get()->filter(fn($item)=>strtolower((string)$item->unit)==='kg')
            ->map(fn($item)=>['id'=>$item->id,'name'=>$item->material?->display_name ?? $item->material?->name ?? 'Material','control_number'=>$item->control_number,'remaining_quantity'=>(float)$item->remaining_quantity])->values();
        return Inertia::render('Dashboard/MAN/Employee/DyeingBleaching/Bleaching', [
            'fabrics' => $this->bleachingQueue()->with('machine', 'salesOrder','dyeingBatchFabric.batch.machine')->orderBy('created_at')->get(),
            'materials' => $materials,
            'assignedOrders' => $this->assignedProductionOrders('dyeing'),
        ]);
    }

    public function history()
    {
        return Inertia::render('Dashboard/MAN/Employee/Common/History', [
            'roleLabel' => 'Dyeing Bleaching',
            'historyRoute' => 'man.staff.dyeing-bleaching.history',
            'dateColumn' => 'processed_at',
            'jobs' => $this->staffHistory(BleachingJob::class, ['fabric.machine', 'fabric.salesOrder.client', 'fabric.salesOrder.recipe.product', 'operator']),
        ]);
    }

    public function reports()
    {
        $jobs = BleachingJob::where('operator_id', $this->staff()->id);

        return Inertia::render('Dashboard/MAN/Employee/DyeingBleaching/Reports', [
            'stats' => [
                'total' => (clone $jobs)->count(),
                'today' => (clone $jobs)->whereDate('processed_at', today())->count(),
                'pending' => $this->bleachingQueue()->count(),
            ],
            'recentJobs' => (clone $jobs)->with('fabric:id,code')->latest()->take(20)->get(),
        ]);
    }

    public function storeBleaching(Request $request)
    {
        $validated = $request->validate([
            'fabric_ids'=>'required|array|min:1','fabric_ids.*'=>'required|integer|distinct|exists:fabrics,id',
            'materials_used'=>'required|array|min:1','materials_used.*.inventory_item_id'=>'required|distinct|exists:manufacturing_inventory_items,id',
            'materials_used.*.quantity_used'=>'required|numeric|gt:0',
        ]);
        DB::transaction(function () use ($validated) {
            $items=DyeingBatchFabric::with(['batch.machine','fabric'])->whereIn('fabric_id',$validated['fabric_ids'])->lockForUpdate()->get();
            abort_unless($items->count()===count($validated['fabric_ids']),422,'One or more fabrics are not in a dyeing batch.');
            $first=$items->first(); $batch=$first->batch;
            abort_unless($items->every(fn($item)=>$item->batch?->machine_id===$batch?->machine_id && $item->current_stage==='bleaching' && $item->status==='active'),422,'Select active fabrics assigned to the same machine awaiting bleaching.');
            foreach ($items as $item) abort_unless($this->isAssignedFabric((int) $item->fabric_id, $item->fabric->manufacturing_order_id, $item->fabric->sales_order_id),403,'This fabric code is not assigned to you by the Dyeing Supervisor.');
            $queue = app(DyeingMachineQueueService::class);
            $assignments = $queue->assertCanRecord((int) $this->staff()->id, (int) $batch->machine_id, 'bleaching', $items->pluck('fabric_id')->all());
            foreach($items->groupBy('dyeing_batch_id') as $batchItems) abort_if($batchItems->sum(fn($item)=>(float)$item->fabric->weight)>(float)$batchItems->first()->batch->load_capacity_kg+0.001,422,'Selected fabrics exceed a batch machine load capacity.');
            $used=[];
            foreach($validated['materials_used'] as $row){
                $stock=ManufacturingInventoryItem::with('material')->where('department','dyeing')->lockForUpdate()->findOrFail($row['inventory_item_id']);
                abort_unless(preg_replace('/[^a-z]/','',strtolower($stock->category))==='pretreatment',422,'Choose pre-treatment materials for bleaching.');
                abort_unless(strtolower((string)$stock->unit)==='kg',422,'Choose materials tracked in kilograms.');
                abort_if((float)$stock->remaining_quantity+0.00001<(float)$row['quantity_used'],422,'A selected material does not have enough stock.');
                $stock->remaining_quantity=max(0,(float)$stock->remaining_quantity-(float)$row['quantity_used']);
                $stock->status=$stock->remaining_quantity<=0?'depleted':'partial'; $stock->save();
                $used[]=['inventory_item_id'=>$stock->id,'name'=>$stock->material?->display_name ?? $stock->material?->name ?? 'Material','quantity_used'=>(float)$row['quantity_used'],'unit'=>'kg'];
            }
            $submission='BLEACH-'.now()->format('ymdHis').'-'.Str::upper(Str::random(5));
            foreach($items as $item){
                $job=BleachingJob::create(['fabric_id'=>$item->fabric_id,'bleaching_method'=>'Materials recorded','remarks'=>null,'operator_id'=>$this->staff()->id,
                    'shift'=>$this->getShift(),'code'=>$this->generateCode('BLEACH',BleachingJob::class),'processed_at'=>now(),'status'=>'bleached']);
                $attempt=$item->records()->where('stage','bleaching')->max('attempt')+1;
                DyeingStageRecord::create(['dyeing_batch_fabric_id'=>$item->id,'stage'=>'bleaching','attempt'=>$attempt,'operator_id'=>$this->staff()->id,
                    'bleaching_job_id'=>$job->id,'code'=>$submission.'-'.$item->fabric_id,'submission_code'=>$submission,'materials_used'=>$item->id===$first->id?$used:null,
                    'review_status'=>'awaiting_review','processed_at'=>now()]);
                $item->update(['status'=>'awaiting_review']);
            }
            $queue->completeSubmitted($assignments);
        });

        return back()->with('message', 'Bleaching recorded. Awaiting quality check.');
    }

    private function bleachingQueue()
    {
        $query = Fabric::where('status', 'bleaching')
            ->whereDoesntHave('bleachingJobs', fn ($jobs) => $jobs->whereIn('status', ['bleached', 'quality_check']));
        $this->scopeToAssignment($query);

        return $query;
    }
}
