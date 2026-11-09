<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePageAccess } from '@/composables/usePageAccess';
import { Cog, Plus, Search, UserRound, Unlink, X, Zap, Flame, Waves, RotateCcw, Thermometer } from 'lucide-vue-next';

const props = defineProps({
    machines: { type: Array, default: () => [] },
    staff: { type: Array, default: () => [] },
    department: { type: String, default: 'knitting' },
    machineTypes: { type: Array, default: () => [] },
    machineQualifications: { type: Array, default: () => [] },
    steamReady: { type: Boolean, default: false },
    steamMachines: { type: Array, default: () => [] },
    needleMaterials: { type: Array, default: () => [] },
});
const { canEdit } = usePageAccess();
const canEditProduction = computed(() => canEdit('MAN', 'production'));
const departmentLabel = computed(() => ({ knitting: 'Knitting', dyeing: 'Dyeing', finishing: 'Finishing', boiler: 'Boiler' }[props.department] ?? 'Manufacturing'));
const search = ref('');
const showRegister = ref(false);
const selections = ref(Object.fromEntries(props.machines.map((machine) => [machine.id, machine.staff_id ?? ''])));
const steamSelections = ref(Object.fromEntries(props.machines.map((machine) => [machine.id, [...(machine.steam_machine_ids ?? [])]])));
const processing = ref({});
const needleCounts = ref(Object.fromEntries(props.machines.map((machine) => [machine.id, machine.needle_count ?? 0])));
const needleModalMachine = ref(null);
const needleForm = useForm({ material_id: '', quantity: '', notes: '' });
watch(() => props.machines, (machines) => {
    steamSelections.value = Object.fromEntries(machines.map((machine) => [machine.id, [...(machine.steam_machine_ids ?? [])]]));
    needleCounts.value = Object.fromEntries(machines.map((machine) => [machine.id, machine.needle_count ?? 0]));
}, { deep: true });
const typeDefaultPower = { knitting: 'Electricity', circular_knitting: 'Electricity', flatbed_knitting: 'Electricity', dyeing: 'Steam', squeezer: 'Electricity', hydro: 'Electricity', heatsetting: 'Gas', tubetex: 'Steam', boiler: 'Gas' };
const powerOptions = computed(() => props.department === 'dyeing' ? ['Steam', 'Electricity'] : ['Electricity', 'Steam', 'Gas']);
const temperatureOptions = ['Hot', 'Cold'];
const registerForm = useForm({ machine_no: '', type: props.machineTypes[0]?.value ?? '', qualification: props.machineQualifications[0]?.value ?? '', power: typeDefaultPower[props.machineTypes[0]?.value] ?? 'Electricity', temperature: 'Hot', load_capacity_kg: '', needle_count: '', remarks: '' });
watch(() => registerForm.type, (type) => { registerForm.power = typeDefaultPower[type] ?? registerForm.power; });
const visibleMachines = computed(() => {
    const term = search.value.trim().toLowerCase();
    return props.machines.filter((machine) => !term || machine.machine_no.toLowerCase().includes(term) || (machine.staff_name || '').toLowerCase().includes(term) || machine.type_label.toLowerCase().includes(term));
});
const registrationRoute = computed(() => ({ knitting: 'man.knitting-machines.store', dyeing: 'man.dyeing-machines.store', finishing: 'man.finishing-machines.store', boiler: 'man.boiler-machines.store' }[props.department]));
const assignmentRoutes = {
    knitting: { assign: 'man.knitting-machines.assign', unassign: 'man.knitting-machines.unassign' },
    dyeing: { assign: 'man.dyeing-machines.assign', unassign: 'man.dyeing-machines.unassign' },
    finishing: { assign: 'man.finishing-machines.assign', unassign: 'man.finishing-machines.unassign' },
};
const assignmentHeading = (machine) => machine.type === 'flatbed_knitting'
    ? 'Knitting Sample Staff'
    : machine.type === 'circular_knitting' || machine.type === 'knitting'
        ? 'Knitting Yarn Staff'
        : ({ dyeing: 'Dyeing Staff', finishing: 'Finishing Staff' }[props.department] ?? 'Department Staff');
const rolesByMachineType = {
    circular_knitting: ['knitting_yarn'],
    flatbed_knitting: ['knitting_sample'],
    knitting: ['knitting_yarn'],
    dyeing: ['dyeing_color', 'dyeing_bleaching', 'dyeing_pre_treatment', 'dyeing_treatment'],
    squeezer: ['finishing_squeezer'],
    hydro: ['finishing_hydro'],
    heatsetting: ['finishing_ironing'],
    ironing: ['finishing_ironing'],
    tubetex: ['finishing_tubetex'],
    tubetext: ['finishing_tubetex'],
};
const staffForMachine = (machine) => props.staff.filter((member) => (rolesByMachineType[machine.type] || []).includes(member.manufacturing_role));

const submitRegistration = () => registerForm.post(route(registrationRoute.value), {
    preserveScroll: true,
    onSuccess: () => { showRegister.value = false; registerForm.reset('machine_no', 'needle_count', 'remarks', 'qualification', 'temperature'); },
});
const assignMachine = (machine) => {
    const staffId = selections.value[machine.id];
    if (!staffId || machine.in_active_job || machine.status !== 'available' || processing.value[machine.id]) return;
    processing.value[machine.id] = true;
    router.put(route(assignmentRoutes[props.department].assign, machine.id), { staff_id: staffId }, {
        preserveScroll: true,
        onFinish: () => { processing.value[machine.id] = false; },
    });
};
const unassignMachine = (machine) => {
    if (machine.in_active_job || processing.value[machine.id]) return;
    processing.value[machine.id] = true;
    router.delete(route(assignmentRoutes[props.department].unassign, machine.id), {
        preserveScroll: true,
        onFinish: () => { processing.value[machine.id] = false; },
    });
};
const saveNeedleCount = (machine) => {
    if (processing.value[machine.id]) return;
    processing.value[machine.id] = true;
    router.put(route('man.knitting-machines.needles.update', machine.id), { needle_count: needleCounts.value[machine.id] }, {
        preserveScroll: true,
        onFinish: () => { processing.value[machine.id] = false; },
    });
};
const openNeedleReplacement = (machine) => {
    needleModalMachine.value = machine;
    needleForm.reset();
    needleForm.material_id = props.needleMaterials.find((item) => Number(item.available) > 0)?.id ?? '';
};
const replaceNeedles = () => {
    if (!needleModalMachine.value || needleForm.processing) return;
    needleForm.post(route('man.knitting-machines.needles.replace', needleModalMachine.value.id), {
        preserveScroll: true,
        onSuccess: () => { needleModalMachine.value = null; needleForm.reset(); },
    });
};
const saveBoilerSteamMachines = (machine) => {
    if (processing.value[machine.id]) return;
    processing.value[machine.id] = true;
    router.put(route('man.boiler-machines.steam-machines.sync', machine.id), {
        steam_machine_ids: steamSelections.value[machine.id] ?? [],
    }, {
        preserveScroll: true,
        onFinish: () => { processing.value[machine.id] = false; },
    });
};
</script>

<template>
    <Head :title="`${departmentLabel} Machines`" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <header class="rounded-3xl bg-gradient-to-r from-sky-700 via-blue-700 to-indigo-700 p-6 text-white shadow-lg sm:p-8">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4"><span class="rounded-2xl bg-white/15 p-3"><Cog class="h-7 w-7" /></span><div><p class="text-xs font-black uppercase tracking-widest text-blue-100">{{ departmentLabel }} Supervisor</p><h1 class="mt-1 text-2xl font-black sm:text-3xl">{{ departmentLabel }} Machines</h1><p class="mt-1 text-sm text-blue-100">Register and manage machines for your department.</p></div></div>
                    <button v-if="canEditProduction" type="button" @click="showRegister = true" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-black text-indigo-700 shadow hover:bg-blue-50"><Plus class="h-4 w-4"/> Register Machine</button>
                </div>
            </header>

            <div v-if="machines.some((machine) => machine.power === 'Steam')" class="rounded-2xl border p-4 text-sm" :class="steamReady ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200' : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200'">
                <span class="font-black">Boiler steam status: {{ steamReady ? 'Available' : 'Not available' }}.</span>
                {{ steamReady ? 'Steam powered machines can operate.' : 'A Boiler Operator must record an operating log and mark steam available before steam powered machines can operate.' }}
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div><p class="text-sm font-black text-slate-900 dark:text-white">{{ machines.length }} registered {{ departmentLabel.toLowerCase() }} machine{{ machines.length === 1 ? '' : 's' }}</p><p class="mt-1 text-xs text-slate-500">Power types: Electricity, Steam, Gas</p></div>
                <label class="relative block w-full sm:w-72"><Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"/><input v-model="search" type="search" placeholder="Search machine, type or staff" class="w-full rounded-xl border-slate-200 pl-9 text-sm dark:border-zinc-700 dark:bg-zinc-800" /></label>
            </div>

            <div v-if="!visibleMachines.length" class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500 dark:border-zinc-700 dark:bg-zinc-900">No {{ departmentLabel.toLowerCase() }} machines registered yet.</div>
            <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article v-for="machine in visibleMachines" :key="machine.id" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-3"><div><p class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ machine.qualification_label || machine.type_label }}</p><h2 class="mt-1 font-mono text-xl font-black text-slate-900 dark:text-white">{{ machine.machine_no }}</h2></div><span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase" :class="machine.status === 'available' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' : machine.status === 'retired' ? 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300'">{{ machine.status.replaceAll('_', ' ') }}</span></div>
                    <div class="mt-4 flex items-center gap-2 text-sm font-bold" :class="machine.power === 'Steam' && !machine.steam_ready ? 'text-amber-700 dark:text-amber-300' : 'text-slate-700 dark:text-zinc-200'"><Zap v-if="machine.power === 'Electricity'" class="h-4 w-4"/><Flame v-else-if="machine.power === 'Gas'" class="h-4 w-4"/><Waves v-else class="h-4 w-4"/>Power: {{ machine.power }}<span v-if="machine.power === 'Steam'" class="ml-auto text-[10px] font-black uppercase">Boiler {{ machine.steam_ready ? 'ready' : 'pending' }}</span></div>
                    <div v-if="department === 'dyeing' && machine.temperature" class="mt-2 flex items-center gap-2 text-sm font-bold text-slate-700 dark:text-zinc-200"><Thermometer class="h-4 w-4"/>Temperature: {{ machine.temperature }}</div>
                    <section v-if="department === 'knitting'" class="mt-4 rounded-xl border border-indigo-100 bg-indigo-50/60 p-3 dark:border-indigo-500/20 dark:bg-indigo-500/10">
                        <div class="flex items-center justify-between gap-2"><p class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-indigo-800 dark:text-indigo-200"><Cog class="h-4 w-4"/>Needles installed</p><span class="rounded-full bg-white px-2.5 py-1 text-xs font-black text-indigo-800 dark:bg-zinc-900 dark:text-indigo-200">{{ machine.needle_count }} pcs</span></div>
                        <form v-if="canEditProduction" class="mt-3 flex gap-2" @submit.prevent="saveNeedleCount(machine)"><input v-model.number="needleCounts[machine.id]" type="number" min="1" max="100000" step="1" required aria-label="Installed needle count" class="min-w-0 flex-1 rounded-lg border-slate-200 text-xs dark:border-zinc-700 dark:bg-zinc-900"/><button type="submit" :disabled="processing[machine.id]" class="rounded-lg bg-indigo-700 px-3 py-2 text-xs font-black text-white disabled:opacity-50">Save count</button></form>
                        <button v-if="canEditProduction" type="button" @click="openNeedleReplacement(machine)" class="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-indigo-200 bg-white px-3 py-2 text-xs font-black text-indigo-800 hover:bg-indigo-100 dark:border-indigo-500/30 dark:bg-zinc-900 dark:text-indigo-200 dark:hover:bg-indigo-950/40"><RotateCcw class="h-3.5 w-3.5"/>Replace broken needles</button>
                        <p class="mt-2 text-[10px] leading-relaxed text-indigo-700 dark:text-indigo-300">Replacing a needle deducts only the new piece(s) from Inventory. The machine's installed needle count stays the same.</p>
                    </section>
                    <p v-if="department === 'dyeing'" class="mt-2 text-sm font-semibold text-slate-600 dark:text-zinc-300">Load capacity: {{ machine.load_capacity_kg ?? 'Not set' }} kg per cycle</p>
                    <form v-if="department === 'dyeing' && canEditProduction" class="mt-2 flex gap-2" @submit.prevent="useForm({ load_capacity_kg: machine.load_capacity_kg ?? '' }).patch(route('man.dyeing-machines.capacity', machine.id), { preserveScroll: true })"><input v-model="machine.load_capacity_kg" type="number" min="0.01" step="0.01" required placeholder="Capacity kg" class="w-32 rounded-lg border-slate-200 text-xs dark:border-zinc-700 dark:bg-zinc-800"/><button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white">Save capacity</button></form>
                    <p v-if="machine.remarks" class="mt-3 text-xs text-slate-500 dark:text-zinc-400">{{ machine.remarks }}</p>
                    <section v-if="department === 'boiler'" class="mt-5 rounded-xl bg-slate-50 p-3 dark:bg-zinc-800/70">
                        <div class="mb-2 flex items-center justify-between gap-2"><p class="text-xs font-bold text-slate-700 dark:text-zinc-200">Linked Steam Machines</p><span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-black text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">{{ machine.steam_machine_ids?.length ?? 0 }}</span></div>
                        <p v-if="!steamMachines.length" class="text-[11px] text-slate-500 dark:text-zinc-400">No Steam powered Dyeing or Finishing machines are registered.</p>
                        <div v-else class="max-h-56 space-y-1 overflow-y-auto">
                            <label v-for="steamMachine in steamMachines" :key="steamMachine.id" class="flex cursor-pointer items-start gap-2 rounded-lg px-2 py-1.5 hover:bg-white dark:hover:bg-zinc-900">
                                <input v-model="steamSelections[machine.id]" type="checkbox" :value="steamMachine.id" :disabled="!canEditProduction" class="mt-0.5 rounded border-slate-300 text-indigo-600"/>
                                <span class="min-w-0 flex-1"><span class="block truncate text-xs font-bold text-slate-800 dark:text-zinc-100">{{ steamMachine.machine_no }} <span class="font-medium text-slate-500">· {{ steamMachine.type.replaceAll('_', ' ') }}</span></span><span class="text-[10px] text-slate-500 dark:text-zinc-400">{{ steamMachine.status.replaceAll('_', ' ') }}<span v-if="steamMachine.load_capacity_kg"> · {{ steamMachine.load_capacity_kg }} kg/cycle</span></span></span>
                            </label>
                        </div>
                        <button v-if="canEditProduction" type="button" @click="saveBoilerSteamMachines(machine)" :disabled="processing[machine.id]" class="mt-3 w-full rounded-lg bg-indigo-600 px-3 py-2 text-xs font-black text-white disabled:opacity-50">Save Steam Machine Links</button>
                        <p v-if="machine.steam_machine_names?.length" class="mt-2 text-[10px] text-slate-500 dark:text-zinc-400">Linked: {{ machine.steam_machine_names.join(', ') }}</p>
                    </section>
                    <div v-if="['knitting', 'dyeing', 'finishing'].includes(department)" class="mt-5 rounded-xl bg-slate-50 p-3 dark:bg-zinc-800/70">
                        <p class="mb-2 flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-zinc-300"><UserRound class="h-4 w-4"/>{{ assignmentHeading(machine) }}</p>
                        <p v-if="machine.staff_name" class="mb-3 text-sm font-semibold text-slate-900 dark:text-white">{{ machine.staff_name }}</p><p v-else class="mb-3 text-sm text-slate-500">No staff assigned</p>
                        <div class="flex gap-2"><select v-model="selections[machine.id]" :disabled="!canEditProduction || machine.in_active_job || machine.status !== 'available'" class="min-w-0 flex-1 rounded-lg border-slate-200 text-xs dark:border-zinc-700 dark:bg-zinc-900"><option value="">Choose staff member</option><option v-for="member in staffForMachine(machine)" :key="member.id" :value="member.id">{{ member.name }} · {{ ({ knitting_sample: 'Sample', knitting_yarn: 'Yarn', dyeing_color: 'Color', dyeing_bleaching: 'Bleaching', dyeing_pre_treatment: 'Pre Treatment', dyeing_treatment: 'Post Treatment' })[member.manufacturing_role] }}</option></select><button type="button" @click="assignMachine(machine)" :disabled="!canEditProduction || !selections[machine.id] || machine.in_active_job || machine.status !== 'available' || processing[machine.id]" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-40">Assign</button><button v-if="machine.staff_id" type="button" @click="unassignMachine(machine)" :disabled="!canEditProduction || machine.in_active_job || processing[machine.id]" title="Unassign machine" class="rounded-lg border border-slate-200 px-2.5 text-slate-600 disabled:opacity-40 dark:border-zinc-700 dark:text-zinc-300"><Unlink class="h-4 w-4"/></button></div>
                        <p v-if="!staffForMachine(machine).length" class="mt-2 text-[11px] text-amber-700 dark:text-amber-300">No active staff with the required machine role are available.</p>
                        <p v-if="machine.in_active_job" class="mt-2 text-[11px] font-medium text-amber-700 dark:text-amber-300">Machine is assigned to an active Job Order.</p>
                    </div>
                </article>
            </div>
        </div>

        <div v-if="needleModalMachine" class="fixed inset-0 z-[160] flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="needle-replacement-title">
            <form @submit.prevent="replaceNeedles" class="w-full max-w-lg rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <header class="flex items-center justify-between border-b border-slate-100 p-5 dark:border-zinc-800"><div><h2 id="needle-replacement-title" class="text-lg font-black text-slate-900 dark:text-white">Replace needles · {{ needleModalMachine.machine_no }}</h2><p class="mt-1 text-xs text-slate-500">Installed count remains {{ needleModalMachine.needle_count }}. Only replacement needles are deducted from stock.</p></div><button type="button" @click="needleModalMachine = null" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-zinc-800"><X class="h-4 w-4"/></button></header>
                <div class="space-y-4 p-5">
                    <label class="block space-y-1 text-sm font-semibold">Needle material<select v-model="needleForm.material_id" required class="block w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800"><option value="" disabled>Select needle material</option><option v-for="material in needleMaterials" :key="material.id" :value="material.id" :disabled="Number(material.available) < 1">{{ material.name }} · {{ Number(material.available).toLocaleString() }} pcs available</option></select><span v-if="needleForm.errors.material_id" class="text-xs text-rose-600">{{ needleForm.errors.material_id }}</span><span v-if="!needleMaterials.length" class="text-xs text-amber-700 dark:text-amber-300">Create a material with Category Needles and Unit Pcs first.</span></label>
                    <label class="block space-y-1 text-sm font-semibold">Replacement quantity (pieces)<input v-model.number="needleForm.quantity" type="number" min="1" :max="needleModalMachine.needle_count" step="1" required class="block w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800" placeholder="e.g. 20"/><span v-if="needleForm.errors.quantity" class="text-xs text-rose-600">{{ needleForm.errors.quantity }}</span><span class="text-xs font-normal text-slate-500">For example, replace 20 broken needles with 20 new needles. Stock drops by 20; machine count remains unchanged.</span></label>
                    <label class="block space-y-1 text-sm font-semibold">Notes <span class="font-normal text-slate-400">(optional)</span><textarea v-model="needleForm.notes" rows="2" maxlength="500" class="block w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800" placeholder="Reason or maintenance note"/></label>
                </div>
                <footer class="flex justify-end gap-2 border-t border-slate-100 p-4 dark:border-zinc-800"><button type="button" @click="needleModalMachine = null" class="rounded-xl border px-4 py-2 text-sm">Cancel</button><button type="submit" :disabled="needleForm.processing || !needleMaterials.length || !needleForm.material_id || !needleForm.quantity" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">Issue replacement needles</button></footer>
            </form>
        </div>

        <div v-if="showRegister" class="fixed inset-0 z-[150] flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-label="Register machine">
            <form @submit.prevent="submitRegistration" class="w-full max-w-lg rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <header class="flex items-center justify-between border-b border-slate-100 p-5 dark:border-zinc-800"><div><h2 class="text-lg font-black text-slate-900 dark:text-white">Register {{ departmentLabel }} Machine</h2><p class="mt-1 text-xs text-slate-500">Choose the machine classification and power source.</p></div><button type="button" @click="showRegister = false" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-zinc-800"><X class="h-4 w-4"/></button></header>
                <div class="space-y-4 p-5">
                    <label class="block space-y-1 text-sm font-semibold">Machine No.<input v-model="registerForm.machine_no" required maxlength="100" class="block w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800"/><span v-if="registerForm.errors.machine_no" class="text-xs text-rose-600">{{ registerForm.errors.machine_no }}</span></label>
                    <label v-if="department !== 'dyeing'" class="block space-y-1 text-sm font-semibold">Machine Type<select v-model="registerForm.type" required class="block w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800"><option v-for="type in machineTypes" :key="type.value" :value="type.value">{{ type.label }}</option></select><span v-if="registerForm.errors.type" class="text-xs text-rose-600">{{ registerForm.errors.type }}</span></label>
                    <label v-else class="block space-y-1 text-sm font-semibold">Machine Type<select v-model="registerForm.qualification" required class="block w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800"><option v-for="qualification in machineQualifications" :key="qualification.value" :value="qualification.value">{{ qualification.label }}</option></select><span v-if="registerForm.errors.qualification" class="text-xs text-rose-600">{{ registerForm.errors.qualification }}</span></label>
                    <label class="block space-y-1 text-sm font-semibold">Power<select v-model="registerForm.power" required class="block w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800"><option v-for="power in powerOptions" :key="power" :value="power" :disabled="department !== 'dyeing' && power !== typeDefaultPower[registerForm.type]">{{ power }}</option></select><span v-if="department !== 'dyeing'" class="text-xs font-normal text-slate-500">Power follows this machine type: {{ typeDefaultPower[registerForm.type] }}.</span><span v-else class="text-xs font-normal text-slate-500">Choose Steam or Electricity for this dyeing machine.</span><span v-if="registerForm.errors.power" class="block text-xs text-rose-600">{{ registerForm.errors.power }}</span></label>
                    <label v-if="department === 'dyeing'" class="block space-y-1 text-sm font-semibold">Temperature Set<select v-model="registerForm.temperature" required class="block w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800"><option v-for="temperature in temperatureOptions" :key="temperature" :value="temperature">{{ temperature }}</option></select><span v-if="registerForm.errors.temperature" class="text-xs text-rose-600">{{ registerForm.errors.temperature }}</span></label>
                    <label v-if="department === 'dyeing'" class="block space-y-1 text-sm font-semibold">Load capacity (kg per cycle)<input v-model="registerForm.load_capacity_kg" type="number" min="0.01" step="0.01" required class="block w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800"/><span v-if="registerForm.errors.load_capacity_kg" class="text-xs text-rose-600">{{ registerForm.errors.load_capacity_kg }}</span></label>
                    <label v-if="department === 'knitting'" class="block space-y-1 text-sm font-semibold">No. of needles installed<input v-model.number="registerForm.needle_count" type="number" min="1" max="100000" step="1" required class="block w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800" placeholder="e.g. 50"/><span v-if="registerForm.errors.needle_count" class="text-xs text-rose-600">{{ registerForm.errors.needle_count }}</span></label>
                    <label class="block space-y-1 text-sm font-semibold">Remarks <span class="font-normal text-slate-400">(optional)</span><textarea v-model="registerForm.remarks" rows="3" maxlength="2000" class="block w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800"/></label>
                </div>
                <footer class="flex justify-end gap-2 border-t border-slate-100 p-4 dark:border-zinc-800"><button type="button" @click="showRegister = false" class="rounded-xl border px-4 py-2 text-sm">Cancel</button><button type="submit" :disabled="registerForm.processing || !canEditProduction" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">Register Machine</button></footer>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
