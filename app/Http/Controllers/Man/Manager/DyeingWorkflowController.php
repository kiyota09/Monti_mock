<?php

namespace App\Http\Controllers\Man\Manager;

use App\Http\Controllers\Controller;
use App\Models\Man\DyeingBatch;
use App\Models\Man\DyeingBatchFabric;
use App\Models\Man\DyeingStageRecord;
use App\Models\Man\DyeingRemakeRequest;
use App\Models\Man\DyeingRemakeRequestFabric;
use App\Models\Man\Fabric;
use App\Models\Man\ManTaskAssignment;
use App\Models\Man\KnittingMachineStaffAssignment;
use App\Models\Man\Machine;
use App\Models\Man\ManufacturingOrder;
use App\Models\Ord\SalesOrder;
use App\Models\Core\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DyeingWorkflowController extends Controller
{
    public function index()
    {
        $orders = ManufacturingOrder::with(['salesOrder', 'fabrics.dyeingBatchFabric.batch.machine'])
            ->whereHas('fabrics', fn ($q) => $q->whereIn('status', ['dyeing', 'bleaching']))
            ->latest()->get()->map(function ($order) {
                $fabrics = $order->fabrics->whereIn('status',['dyeing','bleaching']);
                return [
                    'id' => $order->id, 'sales_order_id' => $order->sales_order_id,
                    'sales_order_number' => $order->salesOrder?->order_number ?? ('SO-'.$order->sales_order_id),
                    'quantity' => (float) $order->total_quantity,
                    'bleaching_required' => $order->dyeing_bleaching_required,
                    'fabrics' => $fabrics->map(fn ($f) => [
                        'id' => $f->id, 'code' => $f->code, 'weight' => (float) $f->weight,
                        'yarn_type' => $f->yarn_type, 'status' => $f->status,
                        'batch_id' => $f->dyeingBatchFabric?->dyeing_batch_id,
                    ]),
                ];
            });
        $machines = Machine::where('type', 'dyeing')->where('status', 'available')->get(['id','machine_no','power','load_capacity_kg']);
        $staff = User::where('manufacturing_role', 'dyeing_color')->get(['id','name']);
        $knittingStaff = User::where('manufacturing_role', 'knitting_yarn')->get(['id','name'])->map(fn ($user) => [
            'id'=>$user->id,'name'=>$user->name,
            'machine_ids'=>KnittingMachineStaffAssignment::where('staff_id',$user->id)->pluck('machine_id')->map(fn($id)=>(int)$id)->values(),
        ]);
        $batches = DyeingBatch::with(['machine','operator','fabrics.fabric','fabrics.latestRecord'])
            ->whereIn('status', ['queued','in_progress','completed','remake_requested'])->latest()->take(100)->get();
        $pendingRecords = DyeingStageRecord::with(['batchFabric.fabric','batchFabric.batch.machine','operator'])
            ->where('review_status','awaiting_review')->latest()->get();
        // A staff submission can contain multiple fabric codes, but each code
        // must have its own supervisor decision and review action.
        $reviews = $pendingRecords->map(fn($record) => [
            'id' => $record->id,
            'stage' => $record->stage,
            'attempt' => $record->attempt,
            'operator' => $record->operator,
            'batch' => $record->batchFabric?->batch,
            'fabric' => [
                'code' => $record->batchFabric?->fabric?->code,
                'weight' => (float) ($record->batchFabric?->fabric?->weight ?? 0),
            ],
            'materials_used' => $record->materials_used ?? [],
        ])->values();
        $remakeRequests = DyeingRemakeRequest::with(['salesOrder','assignee','fabrics.originalFabric','fabrics.replacementFabric'])
            ->whereIn('status',['knitting','awaiting_inspection','ready_for_dyeing'])->latest()->get();
        return Inertia::render('Dashboard/MAN/Supervisor/DyeingWorkflow', compact('orders','machines','staff','knittingStaff','batches','reviews','remakeRequests'));
    }

    public function storeBatch(Request $request)
    {
        $data = $request->validate([
            'manufacturing_order_id' => ['required','exists:manufacturing_orders,id'],
            'machine_id' => ['required','exists:machines,id'], 'assigned_to' => ['required','exists:users,id'],
            'color' => ['required','string','max:120'], 'fabric_ids' => ['required','array','min:1'],
            'fabric_ids.*' => ['required','integer','distinct','exists:fabrics,id'],
        ]);
        $machine = Machine::where('type','dyeing')->where('status','available')->findOrFail($data['machine_id']);
        abort_unless($machine->power === 'Steam' && $machine->boilerSteamAvailable(), 422, 'Boiler steam is not available for this machine.');
        abort_unless((float)$machine->load_capacity_kg > 0, 422, 'Set this dyeing machine load capacity before scheduling a batch.');
        $order = ManufacturingOrder::findOrFail($data['manufacturing_order_id']);
        abort_if($order->dyeing_bleaching_required === null, 422,
            'Select Bleaching or Direct to Pre Treatment for this JO on the Assignments page before queuing a batch.');
        $fabrics = Fabric::where('manufacturing_order_id',$order->id)->whereIn('id',$data['fabric_ids'])->whereIn('status',['dyeing','bleaching'])->get();
        abort_unless($fabrics->count() === count($data['fabric_ids']), 422, 'Choose only unbatched fabrics handed over from Knitting.');
        abort_unless(!DyeingBatchFabric::whereIn('fabric_id',$fabrics->pluck('id'))->exists(), 422, 'A fabric is already in a dyeing batch.');
        $weight = (float)$fabrics->sum('weight');
        abort_if($weight > (float)$machine->load_capacity_kg + 0.001, 422, 'Selected fabrics exceed the machine load capacity.');
        DB::transaction(function () use ($data,$machine,$order,$fabrics,$weight) {
            $batch = DyeingBatch::create([
                'code' => 'DB-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'manufacturing_order_id'=>$order->id,'sales_order_id'=>$order->sales_order_id,
                'machine_id'=>$machine->id,'assigned_to'=>$data['assigned_to'],'assigned_by'=>auth()->id(),
                'color'=>$data['color'],'load_capacity_kg'=>$machine->load_capacity_kg,'total_weight_kg'=>$weight,'status'=>'queued',
            ]);
            foreach ($fabrics as $fabric) {
                $bleach = (bool) $order->dyeing_bleaching_required;
                DyeingBatchFabric::create(['dyeing_batch_id'=>$batch->id,'fabric_id'=>$fabric->id,
                    'requires_bleaching'=>$bleach,'current_stage'=>$bleach?'bleaching':'pre_treatment','status'=>'active']);
                $fabric->update(['status'=>$bleach?'bleaching':'dyeing','machine_id'=>$machine->id]);
            }
        });
        return back()->with('success','Dyeing batch queued.');
    }

    public function review(Request $request, DyeingStageRecord $record)
    {
        $data = $request->validate(['decision'=>['required','in:pass,remake,approve,redo']]);
        $assignmentTarget = null;
        DB::transaction(function () use ($record,$data,&$assignmentTarget) {
            $record = DyeingStageRecord::with('batchFabric.fabric', 'batchFabric.batch')->lockForUpdate()->findOrFail($record->id);
            abort_unless ($record->review_status === 'awaiting_review', 422, 'This stage was already reviewed.');
            $item = $record->batchFabric;
            abort_unless($item && $item->current_stage === $record->stage && $item->status === 'awaiting_review', 422, 'This fabric is no longer awaiting review for this stage.');
            $decision=in_array($data['decision'],['pass','approve'],true)?'pass':'remake';
            $next=['bleaching'=>'pre_treatment','pre_treatment'=>'color','color'=>'post_treatment','post_treatment'=>'completed'][$record->stage] ?? null;
            if (in_array($data['decision'], ['pass', 'approve'], true)) {
                $nextAssignmentStage = [
                    'bleaching' => 'dyeing_pre_treatment',
                    'pre_treatment' => 'dyeing_color',
                    'color' => 'dyeing_treatment',
                ][$record->stage] ?? null;
                $assignmentTarget = [
                    'order_id' => $record->batchFabric->fabric?->manufacturing_order_id,
                    'stage' => $nextAssignmentStage,
                ];
            }
            $record->reviewed_by=auth()->id();
            $record->reviewed_at=now();
            if($decision==='pass'){
                $record->review_status='approved';
                if($next==='completed'){$item->status='completed';$item->current_stage='completed';$item->fabric->update(['status'=>'squeezer']);}
                else{$item->status='active';$item->current_stage=$next;$item->fabric->update(['status'=>$next==='bleaching'?'bleaching':'dyeing']);}
            } else {
                $record->review_status='redo'; $item->status='active';
                if($record->stage==='color' && $record->dye_job_id) $item->fabric->dyeJobs()->whereKey($record->dye_job_id)->delete();
                if($record->stage==='bleaching' && $record->bleaching_job_id) $item->fabric->bleachingJobs()->whereKey($record->bleaching_job_id)->delete();
            }
            $record->save();
            $item->save();
            if($decision==='pass' && $next==='completed'){
                $batch=$item->batch;
                if($batch && !$batch->fabrics()->where('status','!=','completed')->exists()) $batch->update(['status'=>'completed']);
            }
        });

        if ($assignmentTarget && $assignmentTarget['order_id'] && $assignmentTarget['stage']) {
            return redirect()->route('man.assignments.index', [
                'department' => 'dyeing',
                'order_id' => $assignmentTarget['order_id'],
                'stage' => $assignmentTarget['stage'],
            ])->with('success', 'Stage approved. Assign the next dyeing stage to a staff member.');
        }

        return back()->with('success','Dyeing review saved.');
    }

    public function releaseRemake(DyeingRemakeRequest $remake)
    {
        abort_unless($remake->status === 'ready_for_dyeing', 422, 'Knitting inspection must approve every replacement roll before it can return to Dyeing.');
        DB::transaction(function () use ($remake) {
            $remake = DyeingRemakeRequest::with(['batch','fabrics.originalFabric','fabrics.replacementFabric'])->lockForUpdate()->findOrFail($remake->id);
            abort_unless($remake->status === 'ready_for_dyeing', 422);
            $pairs = $remake->fabrics;
            abort_if($pairs->isEmpty() || $pairs->contains(fn($pair)=>!$pair->replacement_fabric_id || $pair->replacementFabric?->status !== 'knitting_approved'),422,'Every replacement roll needs Knitting Inspector approval.');
            $batch = $remake->batch;
            $weight = (float)$pairs->sum(fn($pair)=>(float)$pair->replacementFabric->weight);
            abort_if($weight > (float)$batch->load_capacity_kg + 0.001, 422, 'Replacement fabric weight exceeds the original dyeing machine capacity.');
            $newBatch = DyeingBatch::create([
                'code'=>'DB-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'manufacturing_order_id'=>$remake->manufacturing_order_id,'sales_order_id'=>$remake->sales_order_id,
                'machine_id'=>$batch->machine_id,'assigned_to'=>$batch->assigned_to,'assigned_by'=>auth()->id(),
                'color'=>$batch->color,'load_capacity_kg'=>$batch->load_capacity_kg,'total_weight_kg'=>$weight,'status'=>'queued',
            ]);
            foreach ($pairs as $pair) {
                $oldItem = DyeingBatchFabric::where('fabric_id',$pair->original_fabric_id)->firstOrFail();
                $oldItem->update(['status'=>'replaced','replacement_fabric_id'=>$pair->replacement_fabric_id]);
                $pair->originalFabric->update(['status'=>'rejected','rejection_reason'=>'Replaced by '.$pair->replacementFabric->code.' under remake '.$remake->code]);
                $next = $oldItem->requires_bleaching ? 'bleaching' : 'pre_treatment';
                DyeingBatchFabric::create(['dyeing_batch_id'=>$newBatch->id,'fabric_id'=>$pair->replacement_fabric_id,
                    'requires_bleaching'=>$oldItem->requires_bleaching,'current_stage'=>$next,'status'=>'active']);
                $pair->replacementFabric->update(['status'=>$next==='bleaching'?'bleaching':'dyeing','machine_id'=>$batch->machine_id]);
            }
            $remake->update(['status'=>'completed']);
            $batch->update(['status'=>'completed']);
            $remake->salesOrder?->update(['knitting_done_at'=>now(),'knitting_done_by'=>auth()->id()]);
        });
        return back()->with('success','Knitting replacements are queued back into Dyeing.');
    }

    public function release(Request $request, ManufacturingOrder $order)
    {
        DB::transaction(function () use ($order) {
            $unbatched = Fabric::where('manufacturing_order_id',$order->id)->whereIn('status',['dyeing','bleaching'])
                ->whereDoesntHave('dyeingBatchFabric')->exists();
            abort_if($unbatched,422,'Assign every handed-off fabric code to a Dyeing batch before JO release.');
            $items = DyeingBatchFabric::whereHas('batch',fn($q)=>$q->where('manufacturing_order_id',$order->id))
                ->where('status','!=','replaced')->with('fabric')->lockForUpdate()->get();
            abort_if($items->isEmpty(),422,'This JO has no dyeing batches.');
            abort_if($items->contains(fn($i)=>$i->status!=='completed'),422,'Every fabric code in the JO must complete Dyeing before release.');
            $weight=(float)$items->sum(fn($i)=>(float)$i->fabric->weight);
            abort_if($weight+0.001<(float)$order->total_quantity,422,'Completed fabric weight is below the JO requirement.');
            foreach ($items as $item) $item->fabric->update(['status'=>'squeezer']);
            DyeingBatch::where('manufacturing_order_id',$order->id)->where('status','!=','remake_requested')->update(['status'=>'completed']);
        });
        return back()->with('success','Complete JO released to Finishing.');
    }
}
