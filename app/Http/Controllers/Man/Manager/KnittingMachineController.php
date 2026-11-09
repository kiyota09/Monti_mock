<?php

namespace App\Http\Controllers\Man\Manager;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Inv\Material;
use App\Models\Man\KnittingMachineStaffAssignment;
use App\Models\Man\Machine;
use App\Models\Man\ManTaskAssignment;
use App\Models\Man\DyeingBatch;
use App\Services\Man\BoilerSteamService;
use App\Services\Inv\MaterialStockService;
use App\Models\War\WarehouseStockItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class KnittingMachineController extends Controller
{
    public function index()
    {
        return $this->departmentPage('knitting');
    }

    public function dyeing()
    {
        return $this->departmentPage('dyeing');
    }

    public function finishing()
    {
        return $this->departmentPage('finishing');
    }

    public function boiler()
    {
        return $this->departmentPage('boiler');
    }

    private function departmentPage(string $department)
    {
        $types = [
            'knitting' => [Machine::TYPE_CIRCULAR_KNITTING, Machine::TYPE_FLATBED_KNITTING, 'knitting'],
            'dyeing' => ['dyeing'],
            'finishing' => ['squeezer', 'hydro', 'heatsetting', 'ironing', 'tubetex', 'tubetext'],
            'boiler' => ['boiler'],
        ][$department];
        $staffRoles = [
            'knitting' => ['knitting_yarn', 'knitting_sample'],
            'dyeing' => ['dyeing_color', 'dyeing_bleaching', 'dyeing_pre_treatment', 'dyeing_treatment'],
            'finishing' => ['finishing_squeezer', 'finishing_hydro', 'finishing_ironing', 'finishing_tubetex'],
            'boiler' => ['boiler_operator'],
        ][$department];
        $staff = User::query()->where('role', 'MAN')->whereIn('manufacturing_role', $staffRoles)
            ->where('is_active', 1)->orderBy('name')->get(['id', 'name', 'manufacturing_role']);
        $activeMachineIds = ManTaskAssignment::query()->where('department', 'knitting')->active()
            ->get(['machine_ids'])->flatMap(fn ($assignment) => $assignment->machine_ids ?? [])
            ->map(fn ($id) => (int) $id)->unique()->values();
        $activeDyeingMachineIds = $department === 'dyeing'
            ? DyeingBatch::whereIn('status', ['queued', 'in_progress'])->pluck('machine_id')->map(fn ($id) => (int) $id)
            : collect();
        $steamReady = app(BoilerSteamService::class)->isAvailable();

        $steamMachineTypes = ['dyeing', 'squeezer', 'hydro', 'heatsetting', 'ironing', 'tubetex', 'tubetext'];
        $steamMachines = $department === 'boiler'
            ? Machine::query()->whereIn('type', $steamMachineTypes)->orderBy('machine_no')
                ->get(['id', 'machine_no', 'type', 'power', 'status', 'load_capacity_kg'])
                ->filter(fn (Machine $machine) => strtolower(trim((string) $machine->power)) === 'steam')
                ->map(fn (Machine $machine) => [
                    'id' => $machine->id,
                    'machine_no' => $machine->machine_no,
                    'type' => $machine->type,
                    'status' => $machine->status,
                    'load_capacity_kg' => $machine->load_capacity_kg,
                ])->values()
            : collect();

        $machineQuery = Machine::query()->whereIn('type', $types)
            ->with(['knittingStaffAssignment.staff:id,name,manufacturing_role']);
        if ($department === 'boiler') {
            $machineQuery->with('steamMachines:id,machine_no');
        }
        $machines = $machineQuery->orderBy('machine_no')->get()
            ->map(fn (Machine $machine) => [
                'id' => $machine->id,
                'machine_no' => $machine->machine_no,
                'staff_id' => $machine->knittingStaffAssignment?->staff_id,
                'staff_name' => $machine->knittingStaffAssignment?->staff?->name,
                'staff_role' => $machine->knittingStaffAssignment?->staff?->manufacturing_role,
                'in_active_job' => ($department === 'knitting' && $activeMachineIds->contains((int) $machine->id))
                    || ($department === 'dyeing' && $activeDyeingMachineIds->contains((int) $machine->id)),
                'type' => $machine->type,
                'type_label' => $this->typeLabel($machine->type),
                'qualification' => $machine->qualification,
                'qualification_label' => $this->qualificationLabel($machine->qualification),
                'power' => $machine->power,
                'temperature' => $machine->temperature,
                'load_capacity_kg' => $machine->load_capacity_kg,
                'needle_count' => (int) ($machine->needle_count ?? 0),
                'status' => $machine->status,
                'remarks' => $machine->remarks,
                'steam_ready' => $machine->power !== 'Steam' || $steamReady,
                'steam_machine_ids' => $department === 'boiler' ? $machine->steamMachines->pluck('id')->map(fn ($id) => (int) $id)->all() : [],
                'steam_machine_names' => $department === 'boiler' ? $machine->steamMachines->pluck('machine_no')->all() : [],
            ]);

        return Inertia::render('Dashboard/MAN/Manager/KnittingMachines', [
            'machines' => $machines,
            'staff' => $staff,
            'department' => $department,
            'machineTypes' => $this->typesForDepartment($department),
            'machineQualifications' => $this->qualificationsForDepartment($department),
            'steamReady' => $steamReady,
            'steamMachines' => $steamMachines,
            'needleMaterials' => $department === 'knitting'
                ? Material::where('category', 'Needles')->where('unit', 'Pcs')->orderBy('name')->get()->map(function (Material $material) {
                    $warehouseIds = MaterialStockService::visibleWarehouseIds(Auth::user());
                    return [
                        'id' => $material->id,
                        'name' => $material->display_name,
                        'unit' => $material->unit,
                        'available' => MaterialStockService::unopenedStock($material->id, $warehouseIds),
                    ];
                })->values()
                : [],
        ]);
    }

    private function typesForDepartment(string $department): array
    {
        return match ($department) {
            'knitting' => [
                ['value' => Machine::TYPE_CIRCULAR_KNITTING, 'label' => 'Circular Knitting Machine'],
                ['value' => Machine::TYPE_FLATBED_KNITTING, 'label' => 'Flatbed Knitting Machine'],
            ],
            'dyeing' => [['value' => 'dyeing', 'label' => 'Dyeing Machine']],
            'finishing' => [
                ['value' => 'squeezer', 'label' => 'Squeezer Machine'],
                ['value' => 'hydro', 'label' => 'Hydro Machine'],
                ['value' => 'heatsetting', 'label' => 'Heatsetting Machine'],
                ['value' => 'tubetex', 'label' => 'Tubetext Machine'],
            ],
            'boiler' => [['value' => 'boiler', 'label' => 'Boiler']],
        };
    }

    private function qualificationsForDepartment(string $department): array
    {
        if ($department !== 'dyeing') {
            return [];
        }

        return [
            ['value' => Machine::QUALIFICATION_BLEACHING, 'label' => 'Bleaching Machine'],
            ['value' => Machine::QUALIFICATION_DYEING, 'label' => 'Dyeing Machine'],
            ['value' => Machine::QUALIFICATION_PRE_TREATMENT, 'label' => 'Pre Treatment Machine'],
            ['value' => Machine::QUALIFICATION_POST_TREATMENT, 'label' => 'Post Treatment Machine'],
        ];
    }

    private function qualificationLabel(?string $qualification): ?string
    {
        if (! $qualification) {
            return null;
        }

        $option = collect($this->qualificationsForDepartment('dyeing'))->firstWhere('value', $qualification);

        return $option['label'] ?? ucfirst(str_replace('_', ' ', $qualification));
    }

    private function typeLabel(string $type): string
    {
        if ($type === Machine::TYPE_CIRCULAR_KNITTING || $type === 'knitting') return 'Circular Knitting Machine';
        if ($type === Machine::TYPE_FLATBED_KNITTING) return 'Flatbed Knitting Machine';
        if ($type === 'ironing') return 'Heatsetting Machine';
        if ($type === 'tubetext') return 'Tubetext Machine';
        $typeOption = collect($this->typesForDepartment(match ($type) {
            'knitting' => 'knitting',
            'dyeing' => 'dyeing',
            default => 'finishing',
        }))->firstWhere('value', $type);

        return $typeOption['label'] ?? ucfirst($type);
    }

    public function registerKnitting(Request $request) { return $this->registerMachine($request, 'knitting'); }
    public function registerDyeing(Request $request) { return $this->registerMachine($request, 'dyeing'); }
    public function updateDyeingCapacity(Request $request, Machine $machine)
    {
        abort_unless($machine->type === 'dyeing', 404);
        $data = $request->validate(['load_capacity_kg' => 'required|numeric|min:0.01|max:999999.99']);
        $machine->update($data);
        return back()->with('message', 'Dyeing machine load capacity updated.');
    }
    public function registerFinishing(Request $request) { return $this->registerMachine($request, 'finishing'); }
    public function registerBoiler(Request $request) { return $this->registerMachine($request, 'boiler'); }

    public function syncBoilerSteamMachines(Request $request, Machine $machine)
    {
        abort_unless($machine->type === 'boiler', 404);
        $validated = $request->validate([
            'steam_machine_ids' => 'present|array',
            'steam_machine_ids.*' => 'required|integer|distinct|exists:machines,id',
        ]);
        $allowedTypes = ['dyeing', 'squeezer', 'hydro', 'heatsetting', 'ironing', 'tubetex', 'tubetext'];
        $selected = Machine::query()->whereIn('id', $validated['steam_machine_ids'])
            ->whereIn('type', $allowedTypes)->get(['id', 'power']);
        abort_if($selected->count() !== count($validated['steam_machine_ids'])
            || $selected->contains(fn (Machine $consumer) => strtolower(trim((string) $consumer->power)) !== 'steam'),
            422, 'Only Dyeing and Finishing machines configured to use Steam can be linked to a boiler.');

        $machine->steamMachines()->sync($validated['steam_machine_ids']);

        return back()->with('message', "Steam machine links saved for boiler {$machine->machine_no}.");
    }

    private function registerMachine(Request $request, string $department)
    {
        $isDyeing = $department === 'dyeing';

        $validated = $request->validate([
            'machine_no' => 'required|string|max:100|unique:machines,machine_no',
            'type' => ['required', 'string', \Illuminate\Validation\Rule::in(collect($this->typesForDepartment($department))->pluck('value')->all())],
            'power' => $isDyeing ? 'required|in:Steam,Electricity' : 'required|in:Electricity,Steam,Gas',
            'qualification' => $isDyeing
                ? ['required', Rule::in(collect($this->qualificationsForDepartment('dyeing'))->pluck('value')->all())]
                : ['nullable'],
            'temperature' => $isDyeing
                ? ['required', Rule::in([Machine::TEMPERATURE_HOT, Machine::TEMPERATURE_COLD])]
                : ['nullable'],
            'load_capacity_kg' => [$isDyeing ? 'required' : 'nullable', 'numeric', 'min:0.01', 'max:999999.99'],
            'needle_count' => [$department === 'knitting' ? 'required' : 'nullable', 'integer', 'min:1', 'max:100000'],
            'remarks' => 'nullable|string|max:2000',
        ]);

        if (! $isDyeing) {
            unset($validated['qualification'], $validated['temperature']);

            $requiredPower = [
                'knitting' => 'Electricity',
                Machine::TYPE_CIRCULAR_KNITTING => 'Electricity',
                Machine::TYPE_FLATBED_KNITTING => 'Electricity',
                'squeezer' => 'Electricity',
                'hydro' => 'Electricity',
                'heatsetting' => 'Gas',
                'tubetex' => 'Steam',
                'boiler' => 'Gas',
            ][$validated['type']];
            if ($validated['power'] !== $requiredPower) {
                return back()->withErrors(['power' => "{$validated['type']} machines in this department use {$requiredPower}."]);
            }
        }

        Machine::create($validated + ['status' => 'available']);

        return back()->with('message', 'Machine registered in the ' . ucfirst($department) . ' department.');
    }

    public function updateNeedleCount(Request $request, Machine $machine)
    {
        abort_unless(in_array($machine->type, [Machine::TYPE_CIRCULAR_KNITTING, Machine::TYPE_FLATBED_KNITTING, 'knitting'], true), 404);
        $validated = $request->validate(['needle_count' => 'required|integer|min:1|max:100000']);
        $machine->update($validated);

        return back()->with('message', "Machine {$machine->machine_no} needle count updated.");
    }

    public function replaceNeedles(Request $request, Machine $machine)
    {
        abort_unless(in_array($machine->type, [Machine::TYPE_CIRCULAR_KNITTING, Machine::TYPE_FLATBED_KNITTING, 'knitting'], true), 404);
        $validated = $request->validate([
            'material_id' => ['required', 'integer', Rule::exists('materials', 'id')->where('category', 'Needles')],
            'quantity' => 'required|integer|min:1|max:100000',
            'notes' => 'nullable|string|max:500',
        ]);
        abort_if((int) $validated['quantity'] > (int) $machine->needle_count, 422, 'Replacement quantity cannot exceed the machine needle count.');

        DB::transaction(function () use ($machine, $validated) {
            $lockedMachine = Machine::whereKey($machine->id)->lockForUpdate()->firstOrFail();
            abort_if((int) $validated['quantity'] > (int) $lockedMachine->needle_count, 422, 'Replacement quantity cannot exceed the machine needle count.');
            $material = Material::whereKey($validated['material_id'])->lockForUpdate()->firstOrFail();
            abort_unless($material->category === 'Needles' && $material->unit === 'Pcs', 422, 'Select an inventory item categorized as Needles with unit Pcs.');

            $warehouseIds = MaterialStockService::visibleWarehouseIds(Auth::user());
            $lots = WarehouseStockItem::where('material_id', $material->id)
                ->whereIn('warehouse_id', $warehouseIds)
                ->whereIn('status', ['in_stock', 'reserved'])
                ->where('quantity', '>', 0)
                ->orderBy('received_at')->orderBy('id')->lockForUpdate()->get();
            $needed = (int) $validated['quantity'];
            abort_if((float) $lots->sum('quantity') < $needed, 422, 'Not enough needle stock is available in Inventory.');

            $remaining = $needed;
            foreach ($lots as $lot) {
                if ($remaining <= 0) break;
                $used = min($remaining, (int) $lot->quantity);
                $lot->quantity = (float) $lot->quantity - $used;
                if ($lot->quantity <= 0) {
                    $lot->quantity = 0;
                    $lot->status = 'used';
                }
                $lot->save();
                $remaining -= $used;
            }

            DB::table('knitting_machine_needle_replacements')->insert([
                'machine_id' => $lockedMachine->id,
                'material_id' => $material->id,
                'replaced_by' => Auth::id(),
                'quantity' => $needed,
                'needle_count_after' => (int) $lockedMachine->needle_count,
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('message', "{$validated['quantity']} replacement needles issued to {$machine->machine_no}. Its installed needle count is unchanged.");
    }

    public function assign(Request $request, Machine $machine)
    {
        return $this->assignDepartmentMachine($request, $machine, 'knitting');
    }

    public function assignDyeing(Request $request, Machine $machine)
    {
        return $this->assignDepartmentMachine($request, $machine, 'dyeing');
    }

    public function assignFinishing(Request $request, Machine $machine)
    {
        return $this->assignDepartmentMachine($request, $machine, 'finishing');
    }

    private function assignDepartmentMachine(Request $request, Machine $machine, string $department)
    {
        $allowedTypes = [
            'knitting' => [Machine::TYPE_CIRCULAR_KNITTING, Machine::TYPE_FLATBED_KNITTING, 'knitting'],
            'dyeing' => ['dyeing'],
            'finishing' => ['squeezer', 'hydro', 'heatsetting', 'ironing', 'tubetex', 'tubetext'],
        ][$department];
        abort_unless(in_array($machine->type, $allowedTypes, true) && $machine->status === 'available', 404);
        $validated = $request->validate([
            'staff_id' => 'required|integer|exists:users,id',
        ]);
        $staffRoles = $department === 'finishing'
            ? match ($machine->type) {
                'squeezer' => ['finishing_squeezer'],
                'hydro' => ['finishing_hydro'],
                'heatsetting', 'ironing' => ['finishing_ironing'],
                'tubetex', 'tubetext' => ['finishing_tubetex'],
            }
            : ($department === 'knitting'
                ? (in_array($machine->type, [Machine::TYPE_FLATBED_KNITTING], true) ? ['knitting_sample'] : ['knitting_yarn'])
                : [
                'dyeing' => ['dyeing_color', 'dyeing_bleaching', 'dyeing_pre_treatment', 'dyeing_treatment'],
            ][$department]);
        $staff = User::query()->whereKey($validated['staff_id'])->where('role', 'MAN')
            ->whereIn('manufacturing_role', $staffRoles)->where('is_active', 1)->firstOrFail();

        $inUse = $department === 'knitting'
            ? ManTaskAssignment::query()->where('department', 'knitting')->active()
                ->whereJsonContains('machine_ids', (int) $machine->id)->exists()
            : ($department === 'dyeing'
                ? DyeingBatch::where('machine_id', $machine->id)->whereIn('status', ['queued', 'in_progress'])->exists()
                : false);
        abort_if($inUse, 422, 'This machine is currently assigned to an active Job Order.');

        KnittingMachineStaffAssignment::updateOrCreate(
            ['machine_id' => $machine->id],
            ['staff_id' => $staff->id, 'assigned_by' => Auth::id()],
        );

        return back()->with('message', "Machine {$machine->machine_no} assigned to {$staff->name}.");
    }

    public function unassign(Machine $machine)
    {
        return $this->unassignDepartmentMachine($machine, 'knitting');
    }

    public function unassignDyeing(Machine $machine)
    {
        return $this->unassignDepartmentMachine($machine, 'dyeing');
    }

    public function unassignFinishing(Machine $machine)
    {
        return $this->unassignDepartmentMachine($machine, 'finishing');
    }

    private function unassignDepartmentMachine(Machine $machine, string $department)
    {
        $allowedTypes = [
            'knitting' => [Machine::TYPE_CIRCULAR_KNITTING, Machine::TYPE_FLATBED_KNITTING, 'knitting'],
            'dyeing' => ['dyeing'],
            'finishing' => ['squeezer', 'hydro', 'heatsetting', 'ironing', 'tubetex', 'tubetext'],
        ][$department];
        abort_unless(in_array($machine->type, $allowedTypes, true), 404);
        $inUse = $department === 'knitting'
            ? ManTaskAssignment::query()->where('department', 'knitting')->active()
                ->whereJsonContains('machine_ids', (int) $machine->id)->exists()
            : ($department === 'dyeing'
                ? DyeingBatch::where('machine_id', $machine->id)->whereIn('status', ['queued', 'in_progress'])->exists()
                : false);
        abort_if($inUse, 422, 'This machine is currently assigned to an active Job Order.');
        KnittingMachineStaffAssignment::where('machine_id', $machine->id)->delete();

        return back()->with('message', "Machine {$machine->machine_no} is now unassigned.");
    }
}
