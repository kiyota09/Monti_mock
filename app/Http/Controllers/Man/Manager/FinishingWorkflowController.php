<?php

namespace App\Http\Controllers\Man\Manager;

use App\Http\Controllers\Controller;
use App\Models\Man\Fabric;
use App\Models\Man\FinishingPackageGroup;
use App\Models\Man\FinishingWorkItem;
use App\Models\Man\FinishingWorkOrder;
use App\Models\Man\ManufacturingOrder;
use App\Models\Core\User;
use App\Models\War\WarehousePackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FinishingWorkflowController extends Controller
{
    public function index()
    {
        $incomingOrders = ManufacturingOrder::with(['salesOrder','fabrics'])
            ->whereHas('fabrics',fn($q)=>$q->where('status','squeezer'))
            ->whereDoesntHave('fabrics.finishingWorkItem')
            ->latest()->get()->map(fn($order)=>[
                'id'=>$order->id,'sales_order_id'=>$order->sales_order_id,
                'jo_number'=>$order->salesOrder?->jo_number ?? ('JO-'.$order->sales_order_id),
                'required_weight'=>(float)$order->total_quantity,
                'fabrics'=>$order->fabrics->where('status','squeezer')->map(fn($fabric)=>[
                    'id'=>$fabric->id,'code'=>$fabric->code,'weight'=>(float)$fabric->weight,'yarn_type'=>$fabric->yarn_type,
                ])->values(),
            ]);
        $staff = User::whereIn('manufacturing_role',['finishing_squeezer','finishing_hydro','finishing_ironing','finishing_tubetex'])
            ->orderBy('name')->get(['id','name','manufacturing_role']);
        $workOrders = FinishingWorkOrder::with([
            'salesOrder','extractionOperator','dryingOperator','items.fabric','packageGroups.items.workItem.fabric','packageGroups.warehousePackage',
        ])->latest()->get();
        return Inertia::render('Dashboard/MAN/Supervisor/FinishingWorkflow',compact('incomingOrders','staff','workOrders'));
    }

    public function start(Request $request)
    {
        $data=$request->validate([
            'manufacturing_order_id'=>['required','exists:manufacturing_orders,id'],
            'extraction_method'=>['required','in:squeezer,hydro'],
            'assigned_to'=>['required','exists:users,id'],
        ]);
        $role=$data['extraction_method']==='squeezer'?'finishing_squeezer':'finishing_hydro';
        abort_unless(User::whereKey($data['assigned_to'])->where('manufacturing_role',$role)->exists(),422,'Select a staff member for the chosen extraction process.');
        DB::transaction(function()use($data,$role){
            $order=ManufacturingOrder::with('fabrics')->lockForUpdate()->findOrFail($data['manufacturing_order_id']);
            abort_if(FinishingWorkOrder::where('manufacturing_order_id',$order->id)->exists(),422,'This JO already has a Finishing workflow.');
            $fabrics=$order->fabrics->where('status','squeezer');
            abort_if($fabrics->isEmpty(),422,'No Dyeing-released rolls are waiting for Finishing.');
            $weight=(float)$fabrics->sum('weight');
            abort_if($weight+0.001<(float)$order->total_quantity,422,'The complete JO must be handed over from Dyeing before Finishing starts.');
            $workOrder=FinishingWorkOrder::create([
                'manufacturing_order_id'=>$order->id,'sales_order_id'=>$order->sales_order_id,
                'extraction_method'=>$data['extraction_method'],'extraction_assigned_to'=>$data['assigned_to'],'status'=>'extraction',
            ]);
            foreach($fabrics as $fabric){
                FinishingWorkItem::create(['finishing_work_order_id'=>$workOrder->id,'fabric_id'=>$fabric->id,'status'=>'extraction']);
            }
        });
        return back()->with('success','JO assigned to the selected Water Extraction process.');
    }

    public function assignDrying(Request $request, FinishingWorkOrder $workOrder)
    {
        $data=$request->validate(['drying_method'=>['required','in:heatsetting,tubetex'],'assigned_to'=>['required','exists:users,id']]);
        abort_unless($workOrder->status==='awaiting_drying_assignment',422,'All Water Extraction rolls must be returned to the supervisor first.');
        $role=$data['drying_method']==='heatsetting'?'finishing_ironing':'finishing_tubetex';
        abort_unless(User::whereKey($data['assigned_to'])->where('manufacturing_role',$role)->exists(),422,'Select staff for the chosen Drying process.');
        DB::transaction(function()use($workOrder,$data){
            $workOrder=FinishingWorkOrder::lockForUpdate()->findOrFail($workOrder->id);
            abort_unless($workOrder->status==='awaiting_drying_assignment',422);
            $workOrder->update(['drying_method'=>$data['drying_method'],'drying_assigned_to'=>$data['assigned_to'],'status'=>'drying']);
            $workOrder->items()->where('status','extraction_done')->update(['status'=>'drying']);
        });
        return back()->with('success','JO assigned to the selected Drying process.');
    }

    public function sendToLogistics(FinishingWorkOrder $workOrder)
    {
        abort_unless(in_array($workOrder->status, ['ready_logistics', 'packing'], true),422,'Final Quality Check must pass before sending this JO to Logistics.');
        DB::transaction(function()use($workOrder){
            $workOrder=FinishingWorkOrder::with(['salesOrder.recipe.product','items.fabric','packageGroups.items.workItem.fabric'])->lockForUpdate()->findOrFail($workOrder->id);
            abort_unless(in_array($workOrder->status, ['ready_logistics', 'packing'], true),422);
            $groups=$workOrder->packageGroups;
            abort_if($groups->contains(fn($group)=>$group->status!=='packed'),422,'Every existing package group must be packed before JO handoff.');
            $product=$workOrder->salesOrder?->recipe?->product;
            abort_unless($product,422,'This JO has no product linked to its sales order recipe.');
            if ($groups->isNotEmpty()) {
                foreach($groups as $group){
                    foreach($group->items as $groupItem){
                        $fabric=$groupItem->workItem?->fabric;
                        abort_unless($fabric,422,'A packed fabric roll is missing its record.');
                        $weight=(float)$fabric->weight;
                        $warehousePackage=WarehousePackage::create([
                            'package_number'=>$group->code.'-'.$fabric->code,'manufacturing_order_id'=>$workOrder->manufacturing_order_id,
                            'product_id'=>$product->id,'quantity'=>$weight,'weight_kg'=>$weight,
                            'status'=>'pushed_to_logistics','pushed_at'=>now(),'pushed_by'=>auth()->id(),
                        ]);
                        $groupItem->update(['warehouse_package_id'=>$warehousePackage->id]);
                    }
                    $group->update(['status'=>'sent_to_logistics','sent_to_logistics_at'=>now()]);
                }
            } else {
                abort_if($workOrder->items->isEmpty() || $workOrder->items->contains(fn($item) => ! in_array($item->status, ['ready_logistics', 'packing', 'packed'], true)),422,'Every fabric roll must pass final Quality Check before JO handoff.');
                foreach ($workOrder->items as $item) {
                    $fabric=$item->fabric;
                    abort_unless($fabric,422,'A quality-approved fabric roll is missing its record.');
                    $weight=(float)$fabric->weight;
                    WarehousePackage::create([
                        'package_number'=>'FIN-'.$workOrder->id.'-'.$fabric->code,'manufacturing_order_id'=>$workOrder->manufacturing_order_id,
                        'product_id'=>$product->id,'quantity'=>$weight,'weight_kg'=>$weight,
                        'status'=>'pushed_to_logistics','pushed_at'=>now(),'pushed_by'=>auth()->id(),
                    ]);
                    $fabric->update(['status'=>'packed']);
                    $item->update(['status'=>'sent_to_logistics']);
                }
            }
            $workOrder->update(['status'=>'sent_to_logistics']);
        });
        return back()->with('success','The quality-approved JO has been passed to Logistics.');
    }
}
