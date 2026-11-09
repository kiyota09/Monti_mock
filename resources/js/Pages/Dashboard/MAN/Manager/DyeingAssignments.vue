<script setup>
import { computed, defineComponent, h, reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePageAccess } from '@/composables/usePageAccess';
import draggable from 'vuedraggable';
import {
    ClipboardList, Droplets, Flame, GripVertical, Layers,
    Palette, Snowflake, Thermometer, UserCheck,
} from 'lucide-vue-next';

const props = defineProps({
    department: { type: String, default: 'dyeing' },
    departmentLabel: { type: String, default: 'Dyeing Department' },
    canSwitchDepartment: { type: Boolean, default: false },
    board: { type: Object, required: true },
});

const { canEdit } = usePageAccess();
const canEditProduction = computed(() => canEdit('MAN', 'assignments'));

const asList = (value) => {
    if (Array.isArray(value)) return value;
    if (value && typeof value === 'object') return Object.values(value);
    return [];
};

const clone = (value) => JSON.parse(JSON.stringify(value ?? {}));
const normalizeBoard = (value) => {
    const board = clone(value);
    board.received = asList(board.received);
    board.unclassified = asList(board.unclassified);
    board.staff = asList(board.staff);
    board.colorGroups = asList(board.colorGroups);
    board.machines = asList(board.machines);
    board.tabs = asList(board.tabs).map((tab) => ({
        ...tab,
        groups: asList(tab.groups).map((group) => ({
            ...group,
            hot: asList(group.hot),
            cold: asList(group.cold),
        })),
        machines: asList(tab.machines).map((machine) => ({
            ...machine,
            queue: asList(machine.queue),
            staff_order: asList(machine.staff_order),
        })),
    }));
    return board;
};

const localBoard = ref(normalizeBoard(props.board));
watch(() => props.board, (value) => { localBoard.value = normalizeBoard(value); }, { deep: true });

const firstTabWithWork = localBoard.value.tabs.find((tab) => tab.groups.some((group) => group.hot.length || group.cold.length))?.key;
const activeTab = ref(firstTabWithWork ?? localBoard.value.tabs[0]?.key ?? 'dyeing_bleaching');
const activeTabData = computed(() => localBoard.value.tabs.find((tab) => tab.key === activeTab.value) ?? null);
const unclassifiedForTab = computed(() => localBoard.value.unclassified.filter((card) => card.stage === activeTab.value));
const received = computed(() => localBoard.value.received);
const staffForTab = (tab) => localBoard.value.staff.filter((member) => member.manufacturing_role === tab.role);
const colorGroups = computed(() => localBoard.value.colorGroups);

const selections = reactive({});
const assignmentConfirmation = ref(null);
const assignmentSubmitting = ref(false);
const assignmentResult = ref(null);
const selectionFor = (card) => {
    if (!selections[card.order_id]) selections[card.order_id] = {
        machine_id: card.assignment?.machine_id ?? '',
        staff_id: card.assignment?.assigned_to ?? '',
    };
    return selections[card.order_id];
};

const routeColorGroups = reactive({});
const routeColorFor = (order) => {
    if (!(order.order_id in routeColorGroups)) routeColorGroups[order.order_id] = order.color_group ?? '';
    return routeColorGroups[order.order_id];
};

watch(received, (orders) => {
    for (const order of orders) {
        if (!(order.order_id in routeColorGroups)) routeColorGroups[order.order_id] = order.color_group ?? '';
    }
}, { immediate: true, deep: true });

const now = new Date();
const today = [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-');

const setRoute = (order, requiresBleaching) => {
    router.patch(route('man.assignments.dyeing-route', order.order_id), {
        department: 'dyeing',
        requires_bleaching: requiresBleaching,
        color_group: routeColorFor(order) || null,
    }, { preserveScroll: true });
};

const setColorGroup = (card, colorGroup) => {
    router.patch(route('man.assignments.dyeing-color-group', card.order_id), {
        department: 'dyeing',
        color_group: colorGroup,
    }, { preserveScroll: true });
};

const setProcess = (card, process) => {
    router.patch(route('man.assignments.dyeing-process', card.order_id), {
        department: 'dyeing',
        stage: card.stage,
        process,
    }, { preserveScroll: true });
};

const assign = (card) => {
    const selection = selectionFor(card);
    if (!selection.machine_id || !selection.staff_id) return;
    assignmentResult.value = null;
    assignmentConfirmation.value = card;
};

const submitAssignment = () => {
    const card = assignmentConfirmation.value;
    if (!card || assignmentSubmitting.value) return;
    const selection = selectionFor(card);

    const deadline = card.expected_ship_date && card.expected_ship_date >= today
        ? card.expected_ship_date
        : today;

    assignmentSubmitting.value = true;
    router.post(route('man.assignments.store'), {
        department: 'dyeing',
        manufacturing_order_id: card.order_id,
        stage: card.stage,
        assigned_to: selection.staff_id,
        machine_ids: [selection.machine_id],
        process: card.process,
        fabric_ids: card.assignable_fabric_ids,
        deadline,
        notes: '',
    }, {
        preserveScroll: true,
        onSuccess: (page) => {
            const error = page.props.flash?.error;
            assignmentResult.value = error
                ? { success: false, title: 'Assignment failed', message: error }
                : { success: true, title: 'Assignment successful', message: page.props.flash?.message || `${card.reference} was assigned successfully.` };
            assignmentConfirmation.value = null;
        },
        onError: (errors) => {
            const message = Object.values(errors || {}).flat().find(Boolean) || 'The assignment could not be saved. Check the selected machine, staff member, and fabric codes, then try again.';
            assignmentResult.value = { success: false, title: 'Assignment failed', message };
            assignmentConfirmation.value = null;
        },
        onHttpException: () => {
            assignmentResult.value = { success: false, title: 'Assignment failed', message: 'The server could not complete the assignment. Please try again or contact support if it continues.' };
            assignmentConfirmation.value = null;
        },
        onFinish: () => { assignmentSubmitting.value = false; },
    });
};

const saveQueue = (tab, machine) => {
    router.patch(route('man.assignments.dyeing-queue'), {
        department: 'dyeing',
        stage: tab.key,
        machine_id: machine.id,
        order_ids: machine.queue.map((item) => item.order_id),
    }, { preserveScroll: true });
};

const saveStaffOrder = (tab, machine) => {
    router.patch(route('man.assignments.dyeing-staff-order'), {
        department: 'dyeing',
        stage: tab.key,
        machine_id: machine.id,
        staff_ids: machine.staff_order.map((member) => member.id),
    }, { preserveScroll: true });
};

const groupTone = (key) => ({
    white: 'border-slate-200 bg-white dark:border-zinc-700 dark:bg-zinc-900',
    light: 'border-amber-200 bg-amber-50/60 dark:border-amber-500/30 dark:bg-amber-500/10',
    dark: 'border-slate-300 bg-slate-100/70 dark:border-zinc-700 dark:bg-zinc-800/60',
}[key] ?? 'border-slate-200 bg-white dark:border-zinc-700 dark:bg-zinc-900');

const processTone = (process) => process === 'Cold'
    ? 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300'
    : 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300';

const CardBody = defineComponent({
    name: 'CardBody',
    props: {
        card: { type: Object, required: true },
        tab: { type: Object, required: true },
        machines: { type: Array, default: () => [] },
        staff: { type: Array, default: () => [] },
        selection: { type: Object, required: true },
        canEdit: { type: Boolean, default: false },
        processTone: { type: Function, required: true },
    },
    emits: ['assign', 'process'],
    setup(props, { emit }) {
        return () => h('div', { class: 'space-y-2' }, [
            h('div', { class: 'flex flex-wrap items-start justify-between gap-2' }, [
                h('div', {}, [
                    h('p', { class: 'font-mono text-sm font-black text-slate-900 dark:text-white' }, props.card.reference),
                    h('p', { class: 'text-[10px] text-slate-500' }, `${props.card.client} · ${props.card.color || 'No color'} · ${props.card.fabric_count} code(s) · ${props.card.weight} kg`),
                ]),
                h('div', { class: 'flex items-center gap-1' }, [
                    h('button', {
                        type: 'button',
                        disabled: !props.canEdit || props.tab.hot_only,
                        onClick: () => emit('process', 'Hot'),
                        class: ['rounded px-2 py-1 text-[9px] font-black', props.card.process === 'Hot' ? props.processTone('Hot') : 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-zinc-400'],
                    }, 'Hot'),
                    h('button', {
                        type: 'button',
                        disabled: !props.canEdit || props.tab.hot_only,
                        onClick: () => emit('process', 'Cold'),
                        class: ['rounded px-2 py-1 text-[9px] font-black', props.card.process === 'Cold' ? props.processTone('Cold') : 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-zinc-400'],
                    }, 'Cold'),
                ]),
            ]),

            props.card.assigned
                ? h('div', { class: 'rounded-lg bg-emerald-50 px-2.5 py-2 text-[10px] font-bold text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300' }, [
                    `Assigned to ${props.card.assignment.machine_no} · ${props.card.assignment.staff_name}`,
                    props.card.assignment.queue_position !== null ? ` · queue #${Number(props.card.assignment.queue_position) + 1}` : '',
                ].join(''))
                : h('div', { class: 'space-y-2' }, [
                    h('div', { class: 'grid grid-cols-2 gap-2' }, [
                        h('select', {
                            value: props.selection.machine_id,
                            onChange: (event) => { props.selection.machine_id = event.target.value; },
                            class: 'rounded-lg border-slate-200 text-[11px] dark:border-zinc-700 dark:bg-zinc-800',
                        }, [
                            h('option', { value: '' }, 'Machine'),
                            ...props.machines.filter((machine) => !props.card.has_assignment || Number(machine.id) === Number(props.card.assignment?.machine_id)).map((machine) => h('option', { value: machine.id }, machine.machine_no)),
                        ]),
                        h('select', {
                            value: props.selection.staff_id,
                            onChange: (event) => { props.selection.staff_id = event.target.value; },
                            class: 'rounded-lg border-slate-200 text-[11px] dark:border-zinc-700 dark:bg-zinc-800',
                        }, [
                            h('option', { value: '' }, 'Staff'),
                            ...props.staff.map((member) => h('option', { value: member.id }, member.name)),
                        ]),
                    ]),
                    props.card.assigned_fabric_count > 0
                        ? h('p', { class: 'text-[10px] font-bold text-amber-700 dark:text-amber-300' }, `${props.card.assigned_fabric_count} ready code(s) already assigned; assigning the remaining ${props.card.assignable_fabric_ids.length}.`)
                        : null,
                    h('button', {
                        type: 'button',
                        disabled: !props.canEdit || !props.card.can_assign || !props.selection.machine_id || !props.selection.staff_id,
                        onClick: () => emit('assign'),
                        class: 'w-full rounded-lg bg-cyan-700 px-2 py-1.5 text-[10px] font-black text-white disabled:opacity-40',
                    }, props.card.can_assign ? 'Assign JO to machine & staff' : 'Waiting for the previous stage'),
                ]),
        ]);
    },
});
</script>

<template>
    <Head title="Dyeing Job Order Assignments" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-cyan-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-cyan-950/20">
            <div class="mx-auto max-w-[1700px] space-y-6 p-4 pb-16 sm:p-6">
                <header class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-cyan-800 via-blue-800 to-indigo-800 p-6 text-white shadow-xl sm:p-8">
                    <div class="relative flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <span class="rounded-2xl bg-white/15 p-3"><Droplets class="h-7 w-7" /></span>
                            <div>
                                <p class="text-xs font-black uppercase tracking-widest text-cyan-100">Dyeing Supervisor</p>
                                <h1 class="mt-1 text-2xl font-black sm:text-3xl">Job Order Assignment Board</h1>
                                <p class="mt-1 text-sm text-cyan-100">Choose the route, organize by color and process, then assign machines and staff in order.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 rounded-2xl bg-white/10 px-4 py-3 text-xs font-bold">
                            <ClipboardList class="h-4 w-4" /> {{ received.length }} JO waiting for a route
                        </div>
                    </div>
                </header>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-black text-slate-900 dark:text-white">Received Job Orders</h2>
                            <p class="text-xs text-slate-500">Set the dyeing route and color group before the JO enters a stage tab.</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">{{ received.length }}</span>
                    </div>

                    <p v-if="!received.length" class="mt-4 rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-zinc-700">
                        No new Job Orders are waiting for a dyeing route.
                    </p>

                    <div v-else class="mt-4 grid gap-3 lg:grid-cols-2">
                        <article v-for="order in received" :key="order.order_id" class="rounded-2xl border border-slate-200 p-4 dark:border-zinc-700">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-mono text-lg font-black text-slate-900 dark:text-white">{{ order.reference }}</p>
                                    <p class="text-xs text-slate-500">{{ order.client }} · {{ order.fabric_count }} fabric code(s) · {{ order.weight }} kg</p>
                                </div>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">
                                    {{ order.color || 'No color' }}
                                </span>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <label class="text-[11px] font-black uppercase tracking-wide text-slate-500">Color group</label>
                                <select v-model="routeColorGroups[order.order_id]" class="rounded-lg border-slate-200 text-xs dark:border-zinc-700 dark:bg-zinc-800">
                                    <option value="">Auto / needs review</option>
                                    <option v-for="group in colorGroups" :key="group.key" :value="group.key">{{ group.label }}</option>
                                </select>
                                <span v-if="order.color_group_source === 'auto'" class="text-[10px] font-bold text-emerald-600">Auto-detected from Pantone</span>
                                <span v-else-if="!order.color_group" class="text-[10px] font-bold text-amber-600">No Pantone match — pick manually</span>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" :disabled="!canEditProduction" @click="setRoute(order, true)" class="rounded-lg bg-indigo-700 px-3 py-2 text-[10px] font-black text-white disabled:opacity-50">
                                    Bleaching → Pre Treatment → Dyeing → Post Treatment
                                </button>
                                <button type="button" :disabled="!canEditProduction" @click="setRoute(order, false)" class="rounded-lg bg-teal-700 px-3 py-2 text-[10px] font-black text-white disabled:opacity-50">
                                    Pre Treatment → Dyeing → Post Treatment
                                </button>
                            </div>
                        </article>
                    </div>
                </section>

                <div class="flex flex-wrap gap-2">
                    <button v-for="tab in localBoard.tabs" :key="tab.key" type="button" @click="activeTab = tab.key"
                        class="flex items-center gap-2 rounded-2xl border px-4 py-2.5 text-sm font-black transition"
                        :class="activeTab === tab.key
                            ? 'border-cyan-700 bg-cyan-700 text-white shadow'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-cyan-300 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300'">
                        <Layers class="h-4 w-4" /> {{ tab.label }}
                        <span v-if="tab.hot_only" class="rounded-full bg-white/20 px-1.5 text-[9px] uppercase">Hot only</span>
                    </button>
                </div>

                <div v-if="activeTabData" class="space-y-6">
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ activeTabData.label }} machines & queues</h2>
                                <p class="text-xs text-slate-500">Drag JO cards to set who runs first; drag staff to set the operating order.</p>
                            </div>
                            <span class="rounded-full bg-cyan-100 px-3 py-1 text-xs font-black text-cyan-800 dark:bg-cyan-500/15 dark:text-cyan-300">{{ activeTabData.machines.length }} machines</span>
                        </div>

                        <p v-if="!activeTabData.machines.length" class="mt-4 rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-zinc-700">
                            No available Dyeing machines. Register machines first.
                        </p>

                        <div v-else class="mt-4 grid gap-4 xl:grid-cols-2">
                            <article v-for="machine in activeTabData.machines" :key="machine.id" class="rounded-2xl border border-slate-200 p-4 dark:border-zinc-700">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p class="font-mono text-base font-black text-slate-900 dark:text-white">{{ machine.machine_no }}</p>
                                        <p class="text-[11px] text-slate-500">
                                            {{ machine.power }} · {{ machine.load_capacity_kg || '—' }} kg/cycle
                                            <span v-if="machine.qualification"> · {{ machine.qualification.replaceAll('_', ' ') }}</span>
                                        </p>
                                    </div>
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">
                                        {{ machine.queue.length }} JO in queue
                                    </span>
                                </div>

                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <p class="mb-1 flex items-center gap-1 text-[10px] font-black uppercase tracking-wide text-slate-500">JO queue · assignment creation order</p>
                                        <div class="space-y-1.5">
                                            <div v-for="(element, index) in machine.queue" :key="element.order_id" class="rounded-lg border border-slate-200 px-2 py-1.5 text-[11px] dark:border-zinc-700">
                                                {{ index + 1 }}. {{ element.reference }} · {{ element.process }} · {{ element.staff_name }}
                                            </div>
                                        </div>
                                        <p v-if="!machine.queue.length" class="text-[10px] text-slate-400">No JO assigned yet.</p>
                                    </div>

                                    <div>
                                        <p class="mb-1 flex items-center gap-1 text-[10px] font-black uppercase tracking-wide text-slate-500"><UserCheck class="h-3 w-3" /> Staff order</p>
                                        <draggable v-if="canEditProduction" v-model="machine.staff_order" item-key="id" handle=".drag-handle" class="space-y-1.5" @end="saveStaffOrder(activeTabData, machine)">
                                            <template #item="{ element, index }">
                                                <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[11px] dark:border-zinc-700 dark:bg-zinc-900">
                                                    <GripVertical class="drag-handle h-3.5 w-3.5 cursor-grab text-slate-400" />
                                                    <span class="font-black text-slate-700 dark:text-zinc-200">{{ index + 1 }}.</span>
                                                    <span class="min-w-0 flex-1 truncate font-bold text-slate-800 dark:text-zinc-100">{{ element.name }}</span>
                                                </div>
                                            </template>
                                        </draggable>
                                        <div v-else class="space-y-1.5">
                                            <div v-for="(element, index) in machine.staff_order" :key="element.id" class="rounded-lg border border-slate-200 px-2 py-1.5 text-[11px] dark:border-zinc-700">
                                                {{ index + 1 }}. {{ element.name }}
                                            </div>
                                        </div>
                                        <p v-if="!machine.staff_order.length" class="text-[10px] text-slate-400">No staff with this stage role.</p>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </section>

                    <section v-for="group in activeTabData.groups" :key="group.key" class="rounded-2xl border p-4 shadow-sm" :class="groupTone(group.key)">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="flex items-center gap-2 text-sm font-black uppercase tracking-wide text-slate-700 dark:text-zinc-200">
                                <Palette class="h-4 w-4" /> {{ group.label }}
                            </h3>
                            <span class="text-[10px] font-bold text-slate-500">{{ group.hot.length + group.cold.length }} JO</span>
                        </div>

                        <div class="grid gap-4" :class="activeTabData.hot_only ? '' : 'lg:grid-cols-2'">
                            <div>
                                <p class="mb-2 flex items-center gap-1 text-[10px] font-black uppercase tracking-wide text-rose-600 dark:text-rose-300"><Flame class="h-3.5 w-3.5" /> Hot process</p>
                                <div class="space-y-2">
                                    <article v-for="card in group.hot" :key="card.order_id" class="rounded-xl border border-rose-200 bg-white p-3 dark:border-rose-500/20 dark:bg-zinc-900">
                                        <CardBody :card="card" :tab="activeTabData" :machines="activeTabData.machines" :staff="staffForTab(activeTabData)"
                                            :selection="selectionFor(card)" :can-edit="canEditProduction" :process-tone="processTone"
                                            @assign="assign(card)" @process="(value) => setProcess(card, value)" />
                                    </article>
                                    <p v-if="!group.hot.length" class="rounded-lg border border-dashed border-rose-200 p-3 text-center text-[10px] text-slate-400 dark:border-rose-500/20">No Hot JO.</p>
                                </div>
                            </div>

                            <div v-if="!activeTabData.hot_only">
                                <p class="mb-2 flex items-center gap-1 text-[10px] font-black uppercase tracking-wide text-sky-600 dark:text-sky-300"><Snowflake class="h-3.5 w-3.5" /> Cold process</p>
                                <div class="space-y-2">
                                    <article v-for="card in group.cold" :key="card.order_id" class="rounded-xl border border-sky-200 bg-white p-3 dark:border-sky-500/20 dark:bg-zinc-900">
                                        <CardBody :card="card" :tab="activeTabData" :machines="activeTabData.machines" :staff="staffForTab(activeTabData)"
                                            :selection="selectionFor(card)" :can-edit="canEditProduction" :process-tone="processTone"
                                            @assign="assign(card)" @process="(value) => setProcess(card, value)" />
                                    </article>
                                    <p v-if="!group.cold.length" class="rounded-lg border border-dashed border-sky-200 p-3 text-center text-[10px] text-slate-400 dark:border-sky-500/20">No Cold JO.</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-if="unclassifiedForTab.length" class="rounded-2xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
                        <h3 class="flex items-center gap-2 text-sm font-black uppercase tracking-wide text-amber-800 dark:text-amber-200">
                            <Thermometer class="h-4 w-4" /> Needs a color group
                        </h3>
                        <p class="mb-3 text-[11px] text-amber-700 dark:text-amber-300">These JOs have no Pantone match. Pick White, Light, or Dark to place them.</p>
                        <div class="grid gap-2 lg:grid-cols-2">
                            <article v-for="card in unclassifiedForTab" :key="card.order_id" class="rounded-xl border border-amber-200 bg-white p-3 dark:border-amber-500/20 dark:bg-zinc-900">
                                <CardBody :card="card" :tab="activeTabData" :machines="activeTabData.machines" :staff="staffForTab(activeTabData)"
                                    :selection="selectionFor(card)" :can-edit="canEditProduction" :process-tone="processTone"
                                    @assign="assign(card)" @process="(value) => setProcess(card, value)" />
                                <div class="mt-2 flex items-center gap-2">
                                    <label class="text-[10px] font-black uppercase text-slate-500">Set color group</label>
                                    <select class="rounded-lg border-slate-200 text-xs dark:border-zinc-700 dark:bg-zinc-800" @change="setColorGroup(card, $event.target.value)">
                                        <option value="" selected disabled>Choose…</option>
                                        <option v-for="group in colorGroups" :key="group.key" :value="group.key">{{ group.label }}</option>
                                    </select>
                                </div>
                            </article>
                        </div>
                    </section>
                </div>
            </div>
        </div>
        <div v-if="assignmentConfirmation" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="assign-confirm-title">
            <section class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                <h2 id="assign-confirm-title" class="text-lg font-black text-slate-900 dark:text-white">Confirm JO assignment</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-zinc-300">Assign <b>{{ assignmentConfirmation.reference }}</b> to
                    <b>{{ localBoard.machines.find((machine) => Number(machine.id) === Number(selectionFor(assignmentConfirmation).machine_id))?.machine_no || 'the selected machine' }}</b>
                    and <b>{{ localBoard.staff.find((member) => Number(member.id) === Number(selectionFor(assignmentConfirmation).staff_id))?.name || 'the selected staff member' }}</b>?
                </p>
                <p class="mt-2 text-xs text-slate-500">{{ assignmentConfirmation.assignable_fabric_ids.length }} ready fabric code(s) will be assigned to the {{ assignmentConfirmation.stage_label }} stage.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" :disabled="assignmentSubmitting" @click="assignmentConfirmation = null" class="rounded-lg border px-4 py-2 text-sm font-bold text-slate-600 disabled:opacity-50 dark:border-zinc-700 dark:text-zinc-300">Cancel</button>
                    <button type="button" :disabled="assignmentSubmitting" @click="submitAssignment" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-black text-white disabled:opacity-50">{{ assignmentSubmitting ? 'Assigning…' : 'Confirm assignment' }}</button>
                </div>
            </section>
        </div>
        <div v-if="assignmentResult" class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-950/60 p-4" role="alertdialog" aria-modal="true" :aria-labelledby="assignmentResult.success ? 'assign-success-title' : 'assign-failure-title'">
            <section class="w-full max-w-md rounded-2xl bg-white p-6 text-center shadow-2xl dark:bg-zinc-900">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full text-2xl font-black" :class="assignmentResult.success ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300'">{{ assignmentResult.success ? '✓' : '!' }}</div>
                <h2 :id="assignmentResult.success ? 'assign-success-title' : 'assign-failure-title'" class="mt-3 text-lg font-black text-slate-900 dark:text-white">{{ assignmentResult.title }}</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-zinc-300">{{ assignmentResult.message }}</p>
                <button type="button" @click="assignmentResult = null" class="mt-5 rounded-lg bg-cyan-700 px-5 py-2 text-sm font-black text-white">Close</button>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
