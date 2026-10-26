<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePageAccess } from '@/composables/usePageAccess';
import {
    ClipboardList, UserCheck, ArrowRight, Trash2,
    RefreshCw, PackageSearch, PlayCircle, CheckCircle2,
    Sparkles, ChevronDown, Check, ArrowUpDown, CalendarDays,
    Palette, Layers, Info,
} from 'lucide-vue-next';

const props = defineProps({
    department: { type: String, required: true },
    departmentLabel: { type: String, default: '' },
    departments: { type: Array, default: () => [] },
    canSwitchDepartment: { type: Boolean, default: false },
    stages: { type: Object, default: () => ({}) },
    staff: { type: Array, default: () => [] },
    orders: { type: Array, default: () => [] },
    assignments: { type: Array, default: () => [] },
});

const { canEdit } = usePageAccess();
const canEditProduction = computed(() => canEdit('MAN', 'production'));

const humanize = (slug) => slug.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());

const stageEntries = computed(() =>
    Object.entries(props.stages).map(([key, role]) => ({ key, role, label: humanize(key) })),
);

const form = useForm({
    department: props.department,
    manufacturing_order_id: '',
    stage: '',
    assigned_to: '',
    notes: '',
});

// Only the selected JO's remaining stages may be assigned next.
const selectedOrder = computed(() =>
    props.orders.find((o) => o.id === form.manufacturing_order_id) ?? null,
);
const availableStages = computed(() => {
    if (!selectedOrder.value) return stageEntries.value;
    const remaining = selectedOrder.value.remaining_stages ?? [];
    return stageEntries.value.filter((s) => remaining.includes(s.key));
});

const requiredRole = computed(() => props.stages[form.stage] ?? null);

const eligibleStaff = computed(() => {
    if (!requiredRole.value) return [];

    return props.staff
        .filter((member) => member.manufacturing_role === requiredRole.value)
        .sort((a, b) => (a.active_load - b.active_load) || a.name.localeCompare(b.name));
});

const selectedMember = computed(() =>
    props.staff.find((member) => member.id === form.assigned_to) ?? null,
);

const orderPicker = ref(null);
const staffPicker = ref(null);
const orderOpen = ref(false);
const staffOpen = ref(false);

const closePickers = () => {
    orderOpen.value = false;
    staffOpen.value = false;
};

const toggleOrderPicker = () => {
    orderOpen.value = !orderOpen.value;
    staffOpen.value = false;
};

const toggleStaffPicker = () => {
    staffOpen.value = !staffOpen.value;
    orderOpen.value = false;
};

const chooseOrder = (order) => {
    form.manufacturing_order_id = order.id;
    form.stage = '';
    form.assigned_to = '';
    form.clearErrors();
    orderOpen.value = false;
    staffOpen.value = false;
};

const chooseStaff = (member) => {
    form.assigned_to = member.id;
    form.clearErrors('assigned_to');
    staffOpen.value = false;
};

const handlePointerDown = (event) => {
    if (orderPicker.value && !orderPicker.value.contains(event.target)) orderOpen.value = false;
    if (staffPicker.value && !staffPicker.value.contains(event.target)) staffOpen.value = false;
};

const handleKeydown = (event) => {
    if (event.key === 'Escape') closePickers();
};

onMounted(() => {
    document.addEventListener('mousedown', handlePointerDown);
    document.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handlePointerDown);
    document.removeEventListener('keydown', handleKeydown);
});

const brokenPhotos = ref(new Set());

const markPhotoBroken = (id) => {
    const next = new Set(brokenPhotos.value);
    next.add(id);
    brokenPhotos.value = next;
};

const avatarUrl = (member) =>
    member?.photo && !brokenPhotos.value.has(member.id) ? member.photo : null;

const initials = (name) =>
    (name ?? '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('')
        .toUpperCase() || '?';

const avatarTones = [
    'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-200',
    'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200',
    'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200',
    'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-200',
    'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-200',
    'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-200',
];

const avatarTone = (id) => avatarTones[Math.abs(Number(id) || 0) % avatarTones.length];

const loadTone = (load) => {
    if (load === 0) return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300';
    if (load <= 2) return 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300';

    return 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300';
};

const loadLabel = (load) => `${load} active JO${load === 1 ? '' : 's'}`;

const num = (value) => Number(value ?? 0).toLocaleString();

const formatDate = (value) => {
    if (!value) return '—';
    const date = new Date(String(value).length === 10 ? `${value}T00:00:00` : value);

    return Number.isNaN(date.getTime())
        ? '—'
        : date.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};

const priorityTone = (priority) => {
    const level = String(priority ?? '').toLowerCase();
    if (level === 'urgent' || level === 'high') {
        return 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300';
    }
    if (level === 'low') {
        return 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300';
    }

    return 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300';
};

const switchDepartment = (dept) => {
    router.get(route('man.assignments.index', { department: dept }), {}, { preserveState: false });
};

const submit = () => {
    form.department = props.department;
    closePickers();
    form.post(route('man.assignments.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('manufacturing_order_id', 'stage', 'assigned_to', 'notes'),
    });
};

const setStatus = (assignment, status) => {
    router.patch(route('man.assignments.update', assignment.id), { status }, { preserveScroll: true });
};

const remove = (assignment) => {
    if (confirm('Remove this assignment?')) {
        router.delete(route('man.assignments.destroy', assignment.id), { preserveScroll: true });
    }
};

const checkInventory = (order) =>
    router.post(route('man.gates.knitting.check-inventory', order.id), {}, { preserveScroll: true });

const startProduction = (order) =>
    router.post(route('man.gates.knitting.start-production', order.id), {}, { preserveScroll: true });

const statusStyle = {
    pending: 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
    in_progress: 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30',
    completed: 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30',
    rejected: 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30',
};
</script>

<template>
    <Head title="Job Order Assignments" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6 pb-16">

                <!-- Hero -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg">
                            <ClipboardList class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> MAN · Job Orders
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">JO Assignment Board</h1>
                            <p class="text-sm text-blue-100/90">{{ departmentLabel || department }} — assign each stage to one staff member.</p>
                        </div>
                    </div>

                    <div v-if="canSwitchDepartment" class="relative mt-5 flex flex-wrap gap-2">
                        <button
                            v-for="dept in departments"
                            :key="dept"
                            type="button"
                            @click="switchDepartment(dept)"
                            class="px-4 py-2 rounded-2xl text-xs font-black uppercase tracking-wide transition active:scale-95"
                            :class="dept === department
                                ? 'bg-white text-indigo-700 shadow-lg'
                                : 'bg-white/15 text-white hover:bg-white/25'"
                        >
                            {{ humanize(dept) }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Assign form -->
                    <div class="lg:col-span-1">
                        <div class="rounded-3xl bg-white dark:bg-zinc-900 border border-gray-100 dark:border-zinc-800 shadow-sm p-5 space-y-4">
                            <h2 class="flex items-center gap-2 text-sm font-black text-gray-800 dark:text-gray-100 uppercase tracking-wide">
                                <UserCheck class="h-4 w-4 text-indigo-500" /> New Assignment
                            </h2>

                            <div class="space-y-3">
                                <div ref="orderPicker" class="relative">
                                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400">Job Order</span>
                                    <button
                                        type="button"
                                        @click="toggleOrderPicker"
                                        :aria-expanded="orderOpen"
                                        aria-haspopup="listbox"
                                        class="mt-1 flex w-full items-center gap-3 rounded-2xl border border-gray-200 bg-white px-3 py-2.5 text-left transition hover:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 dark:border-zinc-700 dark:bg-zinc-800"
                                    >
                                        <span v-if="selectedOrder" class="min-w-0 flex-1">
                                            <span class="block truncate font-mono text-sm font-black text-gray-900 dark:text-white">{{ selectedOrder.reference }}</span>
                                            <span class="block truncate text-[11px] text-gray-500 dark:text-gray-400">
                                                {{ selectedOrder.client }} · {{ num(selectedOrder.remaining_quantity) }} of {{ num(selectedOrder.total_quantity) }} units
                                            </span>
                                        </span>
                                        <span v-else class="flex-1 text-sm font-semibold text-gray-400">Select a Job Order…</span>
                                        <ChevronDown
                                            class="h-4 w-4 shrink-0 text-gray-400 transition-transform"
                                            :class="orderOpen ? 'rotate-180' : ''"
                                        />
                                    </button>
                                    <span v-if="form.errors.manufacturing_order_id" class="text-xs text-rose-500">{{ form.errors.manufacturing_order_id }}</span>

                                    <div
                                        v-if="orderOpen"
                                        class="absolute left-0 right-0 z-40 mt-2 max-h-[28rem] overflow-y-auto rounded-2xl border border-gray-200 bg-white p-1.5 shadow-2xl shadow-indigo-500/10 dark:border-zinc-700 dark:bg-zinc-900"
                                    >
                                        <p v-if="!orders.length" class="px-3 py-8 text-center text-xs font-semibold text-gray-400">
                                            No open Job Orders available.
                                        </p>
                                        <button
                                            v-for="order in orders"
                                            :key="order.id"
                                            type="button"
                                            @click="chooseOrder(order)"
                                            class="w-full rounded-xl border px-3 py-3 text-left transition"
                                            :class="form.manufacturing_order_id === order.id
                                                ? 'border-indigo-300 bg-indigo-50/80 dark:border-indigo-500/40 dark:bg-indigo-500/10'
                                                : 'border-transparent hover:border-gray-200 hover:bg-gray-50 dark:hover:border-zinc-700 dark:hover:bg-zinc-800/60'"
                                        >
                                            <div class="flex items-start gap-2">
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex flex-wrap items-center gap-1.5">
                                                        <span class="font-mono text-xs font-black text-gray-900 dark:text-white">{{ order.reference }}</span>
                                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase text-slate-600 dark:bg-slate-500/15 dark:text-slate-300">{{ humanize(order.status) }}</span>
                                                        <span v-if="order.priority" class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase" :class="priorityTone(order.priority)">{{ humanize(order.priority) }}</span>
                                                    </div>
                                                    <p class="mt-1 truncate text-[11px] font-bold text-gray-600 dark:text-gray-300">
                                                        {{ order.client }} · {{ num(order.remaining_quantity) }} of {{ num(order.total_quantity) }} units remaining
                                                    </p>
                                                </div>
                                                <Check v-if="form.manufacturing_order_id === order.id" class="mt-0.5 h-4 w-4 shrink-0 text-indigo-600 dark:text-indigo-300" />
                                            </div>

                                            <div class="mt-2 flex flex-wrap gap-1.5 text-[10px] font-semibold text-gray-500 dark:text-gray-400">
                                                <span v-if="order.color" class="inline-flex items-center gap-1 rounded-md bg-white px-1.5 py-0.5 ring-1 ring-gray-200 dark:bg-zinc-800 dark:ring-zinc-700">
                                                    <Palette class="h-3 w-3" />{{ order.color }}
                                                </span>
                                                <span v-if="order.yarn_type" class="inline-flex items-center gap-1 rounded-md bg-white px-1.5 py-0.5 ring-1 ring-gray-200 dark:bg-zinc-800 dark:ring-zinc-700">
                                                    <Layers class="h-3 w-3" />{{ order.yarn_type }}
                                                </span>
                                                <span v-if="order.design" class="rounded-md bg-white px-1.5 py-0.5 ring-1 ring-gray-200 dark:bg-zinc-800 dark:ring-zinc-700">
                                                    Design: {{ order.design }}
                                                </span>
                                                <span v-if="order.expected_ship_date" class="inline-flex items-center gap-1 rounded-md bg-white px-1.5 py-0.5 ring-1 ring-gray-200 dark:bg-zinc-800 dark:ring-zinc-700">
                                                    <CalendarDays class="h-3 w-3" />Due {{ formatDate(order.expected_ship_date) }}
                                                </span>
                                            </div>

                                            <p v-if="order.control_number || order.po_number" class="mt-1.5 truncate text-[10px] text-gray-400">
                                                <span v-if="order.control_number">CTL {{ order.control_number }}</span>
                                                <span v-if="order.control_number && order.po_number"> · </span>
                                                <span v-if="order.po_number">PO {{ order.po_number }}</span>
                                            </p>

                                            <div v-if="order.items?.length" class="mt-1.5 space-y-0.5">
                                                <p v-for="(item, index) in order.items" :key="index" class="truncate text-[10px] text-gray-500 dark:text-gray-400">
                                                    {{ item.fabric || 'Item' }}
                                                    <span v-if="item.color"> · {{ item.color }}</span>
                                                    <span v-if="item.design"> · {{ item.design }}</span>
                                                    <span v-if="item.quantity"> · {{ num(item.quantity) }} pcs</span>
                                                </p>
                                            </div>

                                            <p v-if="order.notes" class="mt-1.5 whitespace-pre-wrap text-[10px] italic leading-snug text-gray-400">{{ order.notes }}</p>
                                            <p v-if="order.order_notes" class="mt-1 whitespace-pre-wrap text-[10px] leading-snug text-gray-400">
                                                Order notes: {{ order.order_notes }}
                                            </p>

                                            <div v-if="order.remaining_stages?.length" class="mt-2 flex flex-wrap gap-1">
                                                <span
                                                    v-for="stage in order.remaining_stages"
                                                    :key="stage"
                                                    class="rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase text-amber-700 dark:bg-amber-500/15 dark:text-amber-300"
                                                >
                                                    {{ humanize(stage) }}
                                                </span>
                                            </div>
                                        </button>
                                    </div>
                                </div>

                                <div v-if="selectedOrder" class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-3 dark:border-indigo-500/20 dark:bg-indigo-500/10">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="text-[10px] font-black uppercase tracking-[0.15em] text-indigo-500 dark:text-indigo-300">Selected {{ selectedOrder.source_label }}</p>
                                            <p class="truncate font-mono text-sm font-black text-gray-900 dark:text-white">{{ selectedOrder.reference }}</p>
                                        </div>
                                        <span class="shrink-0 rounded-full bg-white/80 px-2 py-0.5 text-[9px] font-black uppercase text-indigo-700 ring-1 ring-indigo-200 dark:bg-zinc-900/60 dark:text-indigo-200 dark:ring-indigo-500/30">
                                            {{ humanize(selectedOrder.status) }}
                                        </span>
                                    </div>

                                    <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-2 text-[11px]">
                                        <div class="min-w-0">
                                            <dt class="font-bold uppercase tracking-wide text-indigo-400">Client</dt>
                                            <dd class="truncate font-semibold text-gray-700 dark:text-gray-200">{{ selectedOrder.client }}</dd>
                                        </div>
                                        <div>
                                            <dt class="font-bold uppercase tracking-wide text-indigo-400">Quantity</dt>
                                            <dd class="font-semibold text-gray-700 dark:text-gray-200">{{ num(selectedOrder.remaining_quantity) }} / {{ num(selectedOrder.total_quantity) }}</dd>
                                        </div>
                                        <div v-if="selectedOrder.color">
                                            <dt class="font-bold uppercase tracking-wide text-indigo-400">Color</dt>
                                            <dd class="font-semibold text-gray-700 dark:text-gray-200">{{ selectedOrder.color }}</dd>
                                        </div>
                                        <div v-if="selectedOrder.yarn_type">
                                            <dt class="font-bold uppercase tracking-wide text-indigo-400">Yarn</dt>
                                            <dd class="font-semibold text-gray-700 dark:text-gray-200">{{ selectedOrder.yarn_type }}</dd>
                                        </div>
                                        <div v-if="selectedOrder.design" class="col-span-2">
                                            <dt class="font-bold uppercase tracking-wide text-indigo-400">Design</dt>
                                            <dd class="truncate font-semibold text-gray-700 dark:text-gray-200">{{ selectedOrder.design }}</dd>
                                        </div>
                                        <div>
                                            <dt class="font-bold uppercase tracking-wide text-indigo-400">Due Date</dt>
                                            <dd class="font-semibold text-gray-700 dark:text-gray-200">{{ formatDate(selectedOrder.expected_ship_date) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="font-bold uppercase tracking-wide text-indigo-400">Order Date</dt>
                                            <dd class="font-semibold text-gray-700 dark:text-gray-200">{{ formatDate(selectedOrder.ordered_at) }}</dd>
                                        </div>
                                        <div v-if="selectedOrder.control_number" class="min-w-0">
                                            <dt class="font-bold uppercase tracking-wide text-indigo-400">Control #</dt>
                                            <dd class="truncate font-mono font-semibold text-gray-700 dark:text-gray-200">{{ selectedOrder.control_number }}</dd>
                                        </div>
                                        <div v-if="selectedOrder.po_number" class="col-span-2 min-w-0">
                                            <dt class="font-bold uppercase tracking-wide text-indigo-400">{{ selectedOrder.source === 'purchase_order' ? 'PO Number' : 'Customer PO' }}</dt>
                                            <dd class="truncate font-semibold text-gray-700 dark:text-gray-200">{{ selectedOrder.po_number }}</dd>
                                        </div>
                                    </dl>

                                    <p v-if="selectedOrder.notes" class="mt-2 flex items-start gap-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                                        <Info class="mt-0.5 h-3 w-3 shrink-0" />
                                        <span class="whitespace-pre-wrap leading-snug">{{ selectedOrder.notes }}</span>
                                    </p>
                                    <p v-if="selectedOrder.order_notes" class="mt-1 flex items-start gap-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                                        <Info class="mt-0.5 h-3 w-3 shrink-0" />
                                        <span class="whitespace-pre-wrap leading-snug">Order notes: {{ selectedOrder.order_notes }}</span>
                                    </p>

                                    <div v-if="selectedOrder.items?.length" class="mt-2 space-y-0.5">
                                        <p v-for="(item, index) in selectedOrder.items" :key="index" class="truncate text-[11px] text-gray-600 dark:text-gray-300">
                                            {{ item.fabric || 'Item' }}
                                            <span v-if="item.color"> · {{ item.color }}</span>
                                            <span v-if="item.design"> · {{ item.design }}</span>
                                            <span v-if="item.quantity"> · {{ num(item.quantity) }} pcs</span>
                                        </p>
                                    </div>

                                    <div v-if="selectedOrder.remaining_stages?.length" class="mt-2 flex flex-wrap gap-1">
                                        <span
                                            v-for="stage in selectedOrder.remaining_stages"
                                            :key="stage"
                                            class="rounded-full bg-white/80 px-2 py-0.5 text-[9px] font-black uppercase text-amber-700 ring-1 ring-amber-200 dark:bg-zinc-900/60 dark:text-amber-300 dark:ring-amber-500/30"
                                        >
                                            {{ humanize(stage) }}
                                        </span>
                                    </div>
                                </div>

                                <label class="block">
                                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400">Stage</span>
                                    <select
                                        v-model="form.stage"
                                        @change="form.assigned_to = ''; staffOpen = false"
                                        :disabled="!form.manufacturing_order_id"
                                        class="mt-1 w-full rounded-2xl border-gray-200 dark:border-zinc-700 dark:bg-zinc-800 text-sm font-semibold disabled:opacity-50"
                                    >
                                        <option value="" disabled>{{ form.manufacturing_order_id ? 'Select a stage…' : 'Choose a Job Order first' }}</option>
                                        <option v-for="stage in availableStages" :key="stage.key" :value="stage.key">{{ stage.label }}</option>
                                    </select>
                                    <span v-if="selectedOrder && !availableStages.length" class="text-xs text-emerald-600">All stages for this Job Order are assigned.</span>
                                    <span v-if="form.errors.stage" class="text-xs text-rose-500">{{ form.errors.stage }}</span>
                                </label>

                                <div ref="staffPicker" class="relative">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400">Assign To</span>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wide text-gray-400">
                                            <ArrowUpDown class="h-3 w-3" /> Least loaded first
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        @click="toggleStaffPicker"
                                        :disabled="!form.stage"
                                        :aria-expanded="staffOpen"
                                        aria-haspopup="listbox"
                                        class="mt-1 flex w-full items-center gap-2.5 rounded-2xl border border-gray-200 bg-white px-3 py-2 text-left transition hover:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-800"
                                    >
                                        <template v-if="selectedMember">
                                            <img
                                                v-if="avatarUrl(selectedMember)"
                                                :src="avatarUrl(selectedMember)"
                                                :alt="selectedMember.name"
                                                class="h-9 w-9 shrink-0 rounded-full object-cover ring-2 ring-white dark:ring-zinc-800"
                                                @error="markPhotoBroken(selectedMember.id)"
                                            />
                                            <span
                                                v-else
                                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[11px] font-black ring-2 ring-white dark:ring-zinc-800"
                                                :class="avatarTone(selectedMember.id)"
                                            >
                                                {{ initials(selectedMember.name) }}
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm font-bold text-gray-900 dark:text-white">{{ selectedMember.name }}</span>
                                                <span class="block truncate text-[11px] text-gray-500 dark:text-gray-400">{{ selectedMember.role_label }}</span>
                                            </span>
                                            <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-black" :class="loadTone(selectedMember.active_load)">
                                                {{ loadLabel(selectedMember.active_load) }}
                                            </span>
                                        </template>
                                        <span v-else class="flex-1 text-sm font-semibold text-gray-400">
                                            {{ form.stage ? 'Choose a staff member…' : 'Choose a stage first' }}
                                        </span>
                                        <ChevronDown
                                            class="h-4 w-4 shrink-0 text-gray-400 transition-transform"
                                            :class="staffOpen ? 'rotate-180' : ''"
                                        />
                                    </button>
                                    <span v-if="form.errors.assigned_to" class="text-xs text-rose-500">{{ form.errors.assigned_to }}</span>

                                    <div
                                        v-if="staffOpen"
                                        class="absolute left-0 right-0 z-40 mt-2 max-h-80 overflow-y-auto rounded-2xl border border-gray-200 bg-white p-1.5 shadow-2xl shadow-indigo-500/10 dark:border-zinc-700 dark:bg-zinc-900"
                                    >
                                        <div v-if="!eligibleStaff.length" class="px-3 py-8 text-center">
                                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">No staff with the required role in this department.</p>
                                        </div>
                                        <template v-else>
                                            <button
                                                v-for="(member, index) in eligibleStaff"
                                                :key="member.id"
                                                type="button"
                                                @click="chooseStaff(member)"
                                                class="flex w-full items-center gap-2.5 rounded-xl border px-2.5 py-2 text-left transition"
                                                :class="form.assigned_to === member.id
                                                    ? 'border-indigo-300 bg-indigo-50/80 dark:border-indigo-500/40 dark:bg-indigo-500/10'
                                                    : 'border-transparent hover:border-gray-200 hover:bg-gray-50 dark:hover:border-zinc-700 dark:hover:bg-zinc-800/60'"
                                            >
                                                <span class="w-4 shrink-0 text-center text-[10px] font-black text-gray-300 dark:text-zinc-600">{{ index + 1 }}</span>
                                                <img
                                                    v-if="avatarUrl(member)"
                                                    :src="avatarUrl(member)"
                                                    :alt="member.name"
                                                    class="h-10 w-10 shrink-0 rounded-full object-cover ring-2 ring-white dark:ring-zinc-800"
                                                    @error="markPhotoBroken(member.id)"
                                                />
                                                <span
                                                    v-else
                                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xs font-black ring-2 ring-white dark:ring-zinc-800"
                                                    :class="avatarTone(member.id)"
                                                >
                                                    {{ initials(member.name) }}
                                                </span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="flex items-center gap-1.5">
                                                        <span class="truncate text-sm font-bold text-gray-900 dark:text-white">{{ member.name }}</span>
                                                        <span
                                                            v-if="member.active_load === 0"
                                                            class="shrink-0 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[9px] font-black uppercase text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                                                        >
                                                            Available
                                                        </span>
                                                    </span>
                                                    <span class="block truncate text-[11px] text-gray-500 dark:text-gray-400">{{ member.role_label }}</span>
                                                </span>
                                                <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-black" :class="loadTone(member.active_load)">
                                                    {{ member.active_load }} JO{{ member.active_load === 1 ? '' : 's' }}
                                                </span>
                                                <Check v-if="form.assigned_to === member.id" class="h-4 w-4 shrink-0 text-indigo-600 dark:text-indigo-300" />
                                            </button>
                                        </template>
                                        <p class="px-3 py-2 text-[10px] leading-snug text-gray-400">
                                            Sorted by active workload (least first). Loads include pending and in-progress Job Orders.
                                        </p>
                                    </div>
                                </div>

                                <label class="block">
                                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400">Notes <span class="font-normal">(optional)</span></span>
                                    <textarea
                                        v-model="form.notes"
                                        rows="2"
                                        class="mt-1 w-full rounded-2xl border-gray-200 dark:border-zinc-700 dark:bg-zinc-800 text-sm"
                                    />
                                </label>

                                <button
                                    v-if="canEditProduction"
                                    type="button"
                                    @click="submit"
                                    :disabled="form.processing || !form.manufacturing_order_id || !form.stage || !form.assigned_to"
                                    class="w-full flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-black px-4 py-3 rounded-2xl shadow-lg shadow-indigo-500/25 transition active:scale-95"
                                >
                                    <UserCheck class="h-4 w-4" /> Assign Job Order
                                </button>
                                <p v-else class="text-xs font-bold uppercase tracking-wide text-amber-700 bg-amber-100 rounded-2xl px-3 py-2 text-center">View only</p>
                            </div>
                        </div>
                    </div>

                    <!-- Board -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Unassigned orders (knitting intake actions) -->
                        <div class="rounded-3xl bg-white dark:bg-zinc-900 border border-gray-100 dark:border-zinc-800 shadow-sm p-5">
                            <h2 class="flex items-center gap-2 text-sm font-black text-gray-800 dark:text-gray-100 uppercase tracking-wide mb-4">
                                <PackageSearch class="h-4 w-4 text-indigo-500" /> Open Job Orders
                                <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 px-2.5 py-0.5 text-[11px]">{{ orders.length }}</span>
                            </h2>

                            <div v-if="!orders.length" class="py-10 text-center">
                                <p class="text-sm font-black text-gray-600 dark:text-gray-300">No open Job Orders</p>
                                <p class="text-xs text-gray-400 mt-1">New Job Orders will appear here once pushed to production.</p>
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    v-for="order in orders"
                                    :key="order.id"
                                    class="flex flex-wrap items-center gap-3 rounded-2xl border border-gray-100 dark:border-zinc-800 bg-gray-50/60 dark:bg-zinc-800/40 px-4 py-3"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-black text-gray-900 dark:text-white font-mono">{{ order.reference }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ order.client }} · {{ order.remaining_quantity ?? order.total_quantity }} units</p>
                                        <div v-if="order.remaining_stages?.length" class="mt-1.5 flex flex-wrap gap-1">
                                            <span
                                                v-for="stage in order.remaining_stages"
                                                :key="stage"
                                                class="rounded-full bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 px-2 py-0.5 text-[10px] font-black uppercase"
                                            >
                                                {{ humanize(stage) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div v-if="department === 'knitting' && canEditProduction" class="flex gap-2">
                                        <button
                                            type="button"
                                            @click="checkInventory(order)"
                                            class="flex items-center gap-1.5 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 text-gray-700 dark:text-gray-200 text-xs font-black px-3 py-2 rounded-2xl transition active:scale-95"
                                        >
                                            <RefreshCw class="h-3.5 w-3.5" /> Check Stock
                                        </button>
                                        <button
                                            type="button"
                                            @click="startProduction(order)"
                                            class="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black px-3 py-2 rounded-2xl shadow-lg shadow-indigo-500/25 transition active:scale-95"
                                        >
                                            <PlayCircle class="h-3.5 w-3.5" /> Start
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Current assignments -->
                        <div class="rounded-3xl bg-white dark:bg-zinc-900 border border-gray-100 dark:border-zinc-800 shadow-sm p-5">
                            <h2 class="flex items-center gap-2 text-sm font-black text-gray-800 dark:text-gray-100 uppercase tracking-wide mb-4">
                                <CheckCircle2 class="h-4 w-4 text-indigo-500" /> Current Assignments
                                <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 px-2.5 py-0.5 text-[11px]">{{ assignments.length }}</span>
                            </h2>

                            <div v-if="!assignments.length" class="py-10 text-center">
                                <p class="text-sm font-black text-gray-600 dark:text-gray-300">Nothing assigned yet</p>
                                <p class="text-xs text-gray-400 mt-1">Assignments you create will be listed here.</p>
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    v-for="a in assignments"
                                    :key="a.id"
                                    class="rounded-2xl border border-gray-100 dark:border-zinc-800 bg-white/70 dark:bg-zinc-800/40 px-4 py-3"
                                >
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-xs font-black font-mono text-gray-800 dark:text-gray-100">{{ humanize(a.stage) }}</span>
                                        <span
                                            class="inline-flex items-center rounded-full ring-1 text-[10px] font-black uppercase px-2.5 py-1"
                                            :class="statusStyle[a.status] ?? statusStyle.pending"
                                        >
                                            {{ humanize(a.status) }}
                                        </span>
                                        <span class="ml-auto text-[11px] text-gray-400">MO #{{ a.manufacturing_order_id }}</span>
                                    </div>
                                    <p class="mt-1 flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300">
                                        <ArrowRight class="h-3.5 w-3.5 text-indigo-400" />
                                        <span class="font-bold">{{ a.assignee?.name ?? 'Unassigned' }}</span>
                                        <span class="text-gray-400">· {{ humanize(a.assigned_role) }}</span>
                                    </p>
                                    <p v-if="a.notes" class="mt-1 text-xs text-gray-400 italic">{{ a.notes }}</p>

                                    <div v-if="canEditProduction" class="mt-2 flex flex-wrap items-center gap-2">
                                        <select
                                            :value="a.status"
                                            @change="setStatus(a, $event.target.value)"
                                            class="rounded-xl border-gray-200 dark:border-zinc-700 dark:bg-zinc-800 text-xs font-bold py-1"
                                        >
                                            <option value="pending">Pending</option>
                                            <option value="in_progress">In Progress</option>
                                            <option value="completed">Completed</option>
                                            <option value="rejected">Rejected</option>
                                        </select>
                                        <button
                                            type="button"
                                            @click="remove(a)"
                                            class="ml-auto flex items-center gap-1 text-xs font-black text-rose-600 hover:text-rose-700 transition"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" /> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
