<?php

namespace App\Http\Controllers\Man\Manager;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Man\KnittingMachineStaffAssignment;
use App\Models\Man\Machine;
use App\Models\Man\ManTaskAssignment;
use App\Models\Man\DyeingBatch;
use App\Services\Man\BoilerSteamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'knitting' => ['knitting'],
            'dyeing' => ['dyeing'],
            'finishing' => ['squeezer', 'hydro', 'heatsetting', 'ironing', 'tubetex', 'tubetext'],
            'boiler' => ['boiler'],
        ][$department];
        $staffRoles = [
            'knitting' => ['knitting_yarn'],
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
                'power' => $machine->power,
                'load_capacity_kg' => $machine->load_capacity_kg,
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
            'steamReady' => $steamReady,
            'steamMachines' => $steamMachines,
        ]);
    }

    private function typesForDepartment(string $department): array
    {
        return match ($department) {
            'knitting' => [['value' => 'knitting', 'label' => 'Knitting Machine']],
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

    private function typeLabel(string $type): string
    {
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
        $validated = $request->validate([
            'machine_no' => 'required|string|max:100|unique:machines,machine_no',
            'type' => ['required', 'string', \Illuminate\Validation\Rule::in(collect($this->typesForDepartment($department))->pluck('value')->all())],
            'power' => 'required|in:Electricity,Steam,Gas',
            'load_capacity_kg' => [$department === 'dyeing' ? 'required' : 'nullable', 'numeric', 'min:0.01', 'max:999999.99'],
            'remarks' => 'nullable|string|max:2000',
        ]);

        $requiredPower = [
            'knitting' => 'Electricity',
            'dyeing' => 'Steam',
            'squeezer' => 'Electricity',
            'hydro' => 'Electricity',
            'heatsetting' => 'Gas',
            'tubetex' => 'Steam',
            'boiler' => 'Gas',
        ][$validated['type']];
        if ($validated['power'] !== $requiredPower) {
            return back()->withErrors(['power' => "{$validated['type']} machines in this department use {$requiredPower}."]);
        }

        Machine::create($validated + ['status' => 'available']);

        return back()->with('message', 'Machine registered in the ' . ucfirst($department) . ' department.');
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
            'knitting' => ['knitting'],
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
            : [
                'knitting' => ['knitting_yarn'],
                'dyeing' => ['dyeing_color', 'dyeing_bleaching', 'dyeing_pre_treatment', 'dyeing_treatment'],
            ][$department];
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
            'knitting' => ['knitting'],
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
