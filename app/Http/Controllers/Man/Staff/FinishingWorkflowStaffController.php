<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Core\User;
use App\Models\Man\FinishingPackageGroup;
use App\Models\Man\FinishingPackageGroupItem;
use App\Models\Man\Fabric;
use App\Models\Man\ManTaskAssignment;
use App\Models\Man\ManufacturingOrder;
use App\Models\Man\FinishingWorkItem;
use App\Models\Man\FinishingWorkOrder;
use App\Models\Man\FinishingWorkRecord;
use App\Models\Inv\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FinishingWorkflowStaffController extends ManufacturingStaffController
{
    private function role(): string { return (string)$this->staff()->manufacturing_role; }

    private function routePrefix(): string
    {
        return match($this->role()) {
            'finishing_squeezer'=>'man.staff.finishing-squeezer',
            'finishing_hydro'=>'man.staff.finishing-hydro',
            'finishing_ironing'=>'man.staff.finishing-ironing',
            'finishing_tubetex'=>'man.staff.finishing-tubetex',
            'finishing_inspector'=>'man.staff.finishing-inspector',
            'checker_quality'=>'man.staff.checker-quality',
            default=>'man.staff.finishing-packaging',
        };
    }

    public function work()
    {
        $role=$this->role();
        $stage=in_array($role,['finishing_squeezer','finishing_hydro'],true)?'extraction':'drying';
        $extractionAssignmentStage = [
            'finishing_squeezer' => ['water_extraction_squeezer', 'finishing_squeezer', 'squeezer'],
            'finishing_hydro' => ['water_extraction_hydro', 'finishing_hydro', 'hydro'],
        ][$role] ?? null;
        if ($extractionAssignmentStage) {
            // Materialize assignments created before JO Assignments was wired
            // into the Finishing workflow so existing work is not stranded.
            ManTaskAssignment::with('manufacturingOrder')->where('department', 'finishing')
                ->whereIn('stage', $extractionAssignmentStage)->where('assigned_to', $this->staff()->id)
                ->active()->get()->each(function (ManTaskAssignment $assignment) use ($role) {
                    $order = $assignment->manufacturingOrder;
                    if (! $order || ! $order->sales_order_id) return;

                    DB::transaction(function () use ($order, $role) {
                        $order = ManufacturingOrder::lockForUpdate()->find($order->id);
                        if (! $order) return;
                        $existingWorkOrder = FinishingWorkOrder::where('manufacturing_order_id', $order->id)->lockForUpdate()->first();
                        if ($existingWorkOrder) {
                            if ($existingWorkOrder->status === 'extraction') {
                                $existingWorkOrder->update([
                                    'extraction_method' => $role === 'finishing_hydro' ? 'hydro' : 'squeezer',
                                    'extraction_assigned_to' => $this->staff()->id,
                                ]);
                            }
                            return;
                        }
                        $fabrics = Fabric::where(function ($query) use ($order) {
                                $query->where('manufacturing_order_id', $order->id);
                                if ($order->sales_order_id) $query->orWhere('sales_order_id', $order->sales_order_id);
                            })->where('status', 'squeezer')->whereDoesntHave('finishingWorkItem')->lockForUpdate()->get();
                        if ($fabrics->isEmpty()) return;

                        $workOrder = FinishingWorkOrder::create([
                            'manufacturing_order_id' => $order->id,
                            'sales_order_id' => $order->sales_order_id,
                            'extraction_method' => $role === 'finishing_hydro' ? 'hydro' : 'squeezer',
                            'extraction_assigned_to' => $this->staff()->id,
                            'status' => 'extraction',
                        ]);
                        foreach ($fabrics as $fabric) {
                            FinishingWorkItem::create([
                                'finishing_work_order_id' => $workOrder->id,
                                'fabric_id' => $fabric->id,
                                'status' => 'extraction',
                            ]);
                        }
                    });
                });
        }
        $chemicalMaterials = $role === 'finishing_squeezer'
            ? Material::where('category', 'Bio-Processing Enzymes')->orderBy('name')->get(['id', 'name', 'category'])
                ->map(fn ($material) => ['id' => $material->id, 'name' => $material->display_name])->values()
            : collect();
        $orders=FinishingWorkOrder::with([
            'salesOrder.recipe.product',
            'salesOrder.recipe.fabricSampleRequest',
            'salesOrder.recipe.sampleRequests',
            'manufacturingOrder',
            'items.fabric',
        ])
            ->where($stage==='extraction'?'extraction_assigned_to':'drying_assigned_to',$this->staff()->id)
            ->whereIn('status',$stage==='extraction'?['extraction']:['drying'])
            ->get()->map(function ($order) use ($stage) {
                $recipe = $order->salesOrder?->recipe;
                $sampleRequest = $recipe?->fabricSampleRequest
                    ?? $recipe?->sampleRequests?->sortByDesc('id')->first();
                $recipeMaterials = is_array($recipe?->materials) ? $recipe->materials : [];
                $formula = $order->manufacturingOrder?->dyeing_production_recipe ?: $recipe?->dyeing_formula;
                $formula = is_array($formula) ? $formula : [];
                $materialIds = collect(array_keys($recipeMaterials))
                    ->merge(collect($formula)->flatMap(fn ($rows) => is_array($rows) ? collect($rows)->pluck('material_id') : []))
                    ->filter()->unique()->values();
                $materials = $materialIds->isEmpty() ? collect() : Material::whereIn('id', $materialIds)->get()->keyBy('id');
                $recipeRows = collect($recipeMaterials)->map(function ($quantity, $id) use ($materials) {
                    $material = $materials->get($id);
                    return ['name' => $material?->display_name ?? 'Material #'.$id, 'quantity' => $quantity, 'unit' => $material?->unit];
                })->values();
                $formulaGroups = [
                    'Dye stuffs' => ['keys' => ['dyestuffs', 'dye_stuffs'], 'dose' => 'pct', 'unit' => '% OWF'],
                    'Basic Chemicals' => ['keys' => ['basic_chemicals', 'basic_chemical'], 'dose' => 'gpl', 'unit' => 'g/L'],
                    'Auxiliary Agents' => ['keys' => ['auxiliary_agents', 'auxiliaries', 'auxiliary'], 'dose' => 'gpl', 'unit' => 'g/L'],
                    'Post Treatment' => ['keys' => ['post_treatment', 'post_treatment_agents'], 'dose' => 'gpl', 'unit' => 'g/L'],
                ];
                $formulaRows = collect($formulaGroups)->map(function ($definition, $label) use ($formula, $materials) {
                    $rows = collect($definition['keys'])->flatMap(fn ($key) => $formula[$key] ?? [])->values();
                    return ['label' => $label, 'materials' => $rows->map(function ($row) use ($definition, $materials) {
                        $material = $materials->get($row['material_id'] ?? null);
                        $dose = $row[$definition['dose']] ?? $row['quantity'] ?? null;
                        return ['name' => $material?->display_name ?? $row['name'] ?? $row['material_name'] ?? 'Material', 'dose' => $dose, 'unit' => $definition['unit']];
                    })->values()];
                })->values();

                return [
                    'id' => $order->id,
                    'jo_number' => $order->salesOrder?->jo_number,
                    'deadline' => ManTaskAssignment::where('department', 'finishing')
                        ->where('manufacturing_order_id', $order->manufacturing_order_id)
                        ->where('assigned_to', $this->staff()->id)->active()->min('deadline'),
                    'method' => $stage === 'extraction' ? $order->extraction_method : $order->drying_method,
                    'recipe' => ($recipe || $formula) ? [
                        'product' => $recipe?->product?->name,
                        'yarn_type' => $recipe?->yarn_type ?? $order->salesOrder?->yarn_type,
                        'dye_color' => $recipe?->dye_color ?? $order->salesOrder?->color,
                        'weave_design' => $recipe?->weave_design ?? $order->salesOrder?->design,
                        'gsm' => $sampleRequest?->gsm,
                        'texture' => $sampleRequest?->texture,
                        'materials' => $recipeRows,
                    ] : null,
                    'formula_source' => $order->manufacturingOrder?->dyeing_production_recipe ? 'JO production formula' : ($recipe?->dyeing_formula ? 'Linked recipe formula' : null),
                    'formula_groups' => $formulaRows,
                    'items' => $order->items->where('status', $stage === 'extraction' ? 'extraction' : 'drying')
                        ->map(fn ($item) => ['id' => $item->id, 'code' => $item->fabric->code, 'yarn_type' => $item->fabric->yarn_type, 'weight' => (float) $item->fabric->weight])->values(),
                ];
            })->filter(fn ($order) => $order['items']->isNotEmpty())->values();
        return Inertia::render('Dashboard/MAN/Employee/FinishingWorkflow/Work',[
            'orders'=>$orders,'stage'=>$stage,'methodLabel'=>$stage==='extraction'?'Water Extraction':'Drying / Ironing',
            'routePrefix'=>$this->routePrefix(),
            'chemicalMaterials' => $chemicalMaterials,
        ]);
    }

    public function complete(Request $request, FinishingWorkItem $item)
    {
        $role=$this->role();
        $isExtraction=in_array($role,['finishing_squeezer','finishing_hydro'],true);
        $method=match($role){'finishing_squeezer'=>'squeezer','finishing_hydro'=>'hydro','finishing_ironing'=>'heatsetting','finishing_tubetex'=>'tubetex',default=>null};
        abort_unless($method,403);
        $data=$request->validate([
            'chemical_material_id' => [$role === 'finishing_squeezer' ? 'required' : 'nullable', 'integer', Rule::exists('materials', 'id')->where(fn ($query) => $query->where('category', 'Bio-Processing Enzymes'))],
            'temperature_c'=>[in_array($role,['finishing_ironing','finishing_tubetex'],true)?'required':'nullable','numeric','min:0','max:400'],
            'remarks'=>['nullable','string','max:2000'],
        ]);
        $chemicalName = isset($data['chemical_material_id'])
            ? Material::where('category', 'Bio-Processing Enzymes')->findOrFail($data['chemical_material_id'])->display_name
            : null;
        DB::transaction(function()use($item,$role,$isExtraction,$method,$data,$chemicalName){
            $item=FinishingWorkItem::with('workOrder')->lockForUpdate()->findOrFail($item->id);
            $order=$item->workOrder;
            $assigned=$isExtraction?$order->extraction_assigned_to:$order->drying_assigned_to;
            abort_unless((int)$assigned===(int)$this->staff()->id,403);
            abort_unless($order->status===($isExtraction?'extraction':'drying') && $item->status===($isExtraction?'extraction':'drying'),422,'This fabric is no longer at your station.');
            FinishingWorkRecord::create([
                'finishing_work_item_id'=>$item->id,'stage'=>$method,'attempt'=>$item->records()->where('stage',$method)->max('attempt')+1,
                'operator_id'=>$this->staff()->id,'temperature_c'=>$data['temperature_c']??null,
                'chemicals_used'=>$chemicalName,
                'remarks'=>$data['remarks']??null,'result'=>'submitted',
            ]);
            $item->update(['status'=>$isExtraction?'extraction_done':'awaiting_shrinkage']);
            if (!$order->items()->where('status',$isExtraction?'extraction':'drying')->exists()) {
                $order->update(['status'=>$isExtraction?'awaiting_drying_assignment':'shrinkage']);
            }
        });
        return back()->with('success','Fabric stage recorded.');
    }

    public function inspection()
    {
        $items=FinishingWorkItem::with(['fabric.salesOrder','workOrder.salesOrder','records'=>fn($q)=>$q->latest()])
            ->where('status','awaiting_shrinkage')->whereHas('workOrder',fn($q)=>$q->where('status','shrinkage'))->orderBy('id')->get();
        return Inertia::render('Dashboard/MAN/Employee/FinishingWorkflow/Inspection',['items'=>$items]);
    }

    public function inspectShrinkage(Request $request, FinishingWorkItem $item)
    {
        $data=$request->validate([
            'gsm'=>['required','numeric','min:1','max:5000'],'shrinkage_percent'=>['required','numeric','min:-50','max:100'],
            'decision'=>['required','in:pass,rework'],'remarks'=>['nullable','string','max:2000'],
        ]);
        DB::transaction(function()use($item,$data){
            $item=FinishingWorkItem::with('workOrder')->lockForUpdate()->findOrFail($item->id);
            abort_unless($item->status==='awaiting_shrinkage'&&$item->workOrder->status==='shrinkage',422,'This fabric is no longer waiting for shrinkage inspection.');
            FinishingWorkRecord::create([
                'finishing_work_item_id'=>$item->id,'stage'=>'shrinkage','attempt'=>$item->records()->where('stage','shrinkage')->max('attempt')+1,
                'operator_id'=>$this->staff()->id,'gsm'=>$data['gsm'],'shrinkage_percent'=>$data['shrinkage_percent'],
                'remarks'=>$data['remarks']??null,'result'=>$data['decision'],'reviewed_by'=>$this->staff()->id,'reviewed_at'=>now(),
            ]);
            $item->update(['status'=>$data['decision']==='pass'?'awaiting_quality':'drying']);
            if($data['decision']==='rework') $item->workOrder->update(['status'=>'drying']);
            elseif(!$item->workOrder->items()->where('status','awaiting_shrinkage')->exists()) $item->workOrder->update(['status'=>'quality']);
        });
        return back()->with('success',$data['decision']==='pass'?'Shrinkage and GSM passed.':'Returned to the selected Drying staff for rework.');
    }

    public function quality()
    {
        $orders=FinishingWorkOrder::with(['salesOrder','items.fabric','items.records'])
            ->where('status','quality')->get();
        return Inertia::render('Dashboard/MAN/Employee/FinishingWorkflow/Quality',['orders'=>$orders]);
    }

    public function checkQuality(Request $request, FinishingWorkOrder $workOrder)
    {
        $data=$request->validate(['decision'=>['required','in:pass,rework'],'remarks'=>['nullable','string','max:2000']]);
        DB::transaction(function()use($workOrder,$data){
            $workOrder=FinishingWorkOrder::with('items')->lockForUpdate()->findOrFail($workOrder->id);
            abort_unless($workOrder->status==='quality',422,'This JO is not waiting for final Quality Check.');
            abort_if($workOrder->items->isEmpty()||$workOrder->items->contains(fn($item)=>$item->status!=='awaiting_quality'),422,'Shrinkage inspection must pass every roll first.');
            $workOrder->update([
                'status'=>$data['decision']==='pass'?'ready_logistics':'drying','quality_checked_by'=>$this->staff()->id,
                'quality_checked_at'=>now(),'quality_remarks'=>$data['remarks']??null,
            ]);
            $workOrder->items()->update(['status'=>$data['decision']==='pass'?'ready_logistics':'drying']);
        });
        return back()->with('success',$data['decision']==='pass'?'JO passed final Quality Check and is ready for the Finishing supervisor to push to Logistics.':'JO returned to Drying for quality rework.');
    }

    public function pack()
    {
        $orders=FinishingWorkOrder::with(['salesOrder','items.fabric','items.packageGroupItem.group'])
            ->where('status','packing')->get()->map(fn($order)=>[
                'id'=>$order->id,'jo_number'=>$order->salesOrder?->jo_number,
                'deadline'=>ManTaskAssignment::where('department','finishing')->where('manufacturing_order_id',$order->manufacturing_order_id)->where('assigned_to',$this->staff()->id)->active()->min('deadline'),
                'items'=>$order->items->where('status','packing')->map(fn($item)=>[
                    'id'=>$item->id,'code'=>$item->fabric->code,'weight'=>(float)$item->fabric->weight,'yarn_type'=>$item->fabric->yarn_type,
                ])->values(),
            ])->filter(fn($order)=>$order['items']->isNotEmpty())->values();
        return Inertia::render('Dashboard/MAN/Employee/FinishingWorkflow/Pack',['orders'=>$orders]);
    }

    public function storePackageGroup(Request $request, FinishingWorkOrder $workOrder)
    {
        $data=$request->validate([
            'group_name'=>['nullable','string','max:120'],'work_item_ids'=>['required','array','min:1'],
            'work_item_ids.*'=>['required','integer','distinct','exists:finishing_work_items,id'],
        ]);
        DB::transaction(function()use($workOrder,$data){
            $workOrder=FinishingWorkOrder::lockForUpdate()->findOrFail($workOrder->id);
            abort_unless($workOrder->status==='packing',422,'This JO is not ready for Packing.');
            $items=FinishingWorkItem::where('finishing_work_order_id',$workOrder->id)->whereIn('id',$data['work_item_ids'])
                ->where('status','packing')->lockForUpdate()->get();
            abort_unless($items->count()===count($data['work_item_ids']),422,'Only inspected rolls from this JO can be packed, and each roll may be packed once.');
            $group=FinishingPackageGroup::create([
                'finishing_work_order_id'=>$workOrder->id,'code'=>'FG-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'group_name'=>$data['group_name']??null,'packed_by'=>$this->staff()->id,'status'=>'packed',
            ]);
            foreach($items as $item){
                FinishingPackageGroupItem::create(['finishing_package_group_id'=>$group->id,'finishing_work_item_id'=>$item->id]);
                $item->update(['status'=>'packed']);
                $item->fabric->update(['status'=>'packed']);
            }
            if(!$workOrder->items()->where('status','!=','packed')->exists()) $workOrder->update(['status'=>'ready_logistics']);
        });
        return back()->with('success','Fabric-code group packed.');
    }
}
