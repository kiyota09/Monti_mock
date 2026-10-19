<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePageAccess } from '@/composables/usePageAccess';
import {
    ClipboardList, UserCheck, ArrowRight, Trash2,
    RefreshCw, PackageSearch, PlayCircle, CheckCircle2,
    Sparkles,
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
const eligibleStaff = computed(() =>
    requiredRole.value ? props.staff.filter((s) => s.manufacturing_role === requiredRole.value) : props.staff,
);

const switchDepartment = (dept) => {
    router.get(route('man.assignments.index', { department: dept }), {}, { preserveState: false });
};

const submit = () => {
    form.department = props.department;
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
                                <label class="block">
                                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400">Job Order</span>
                                    <select
                                        v-model="form.manufacturing_order_id"
                                        @change="form.stage = ''; form.assigned_to = ''"
                                        class="mt-1 w-full rounded-2xl border-gray-200 dark:border-zinc-700 dark:bg-zinc-800 text-sm font-semibold"
                                    >
                                        <option value="" disabled>Select a Job Order…</option>
                                        <option v-for="order in orders" :key="order.id" :value="order.id">
                                            {{ order.reference }} · {{ order.client }} ({{ order.remaining_quantity ?? order.total_quantity }})
                                        </option>
                                    </select>
                                    <span v-if="form.errors.manufacturing_order_id" class="text-xs text-rose-500">{{ form.errors.manufacturing_order_id }}</span>
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400">Stage</span>
                                    <select
                                        v-model="form.stage"
                                        @change="form.assigned_to = ''"
                                        :disabled="!form.manufacturing_order_id"
                                        class="mt-1 w-full rounded-2xl border-gray-200 dark:border-zinc-700 dark:bg-zinc-800 text-sm font-semibold disabled:opacity-50"
                                    >
                                        <option value="" disabled>{{ form.manufacturing_order_id ? 'Select a stage…' : 'Choose a Job Order first' }}</option>
                                        <option v-for="stage in availableStages" :key="stage.key" :value="stage.key">{{ stage.label }}</option>
                                    </select>
                                    <span v-if="selectedOrder && !availableStages.length" class="text-xs text-emerald-600">All stages for this Job Order are assigned.</span>
                                    <span v-if="form.errors.stage" class="text-xs text-rose-500">{{ form.errors.stage }}</span>
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400">Assign To</span>
                                    <select
                                        v-model="form.assigned_to"
                                        :disabled="!form.stage"
                                        class="mt-1 w-full rounded-2xl border-gray-200 dark:border-zinc-700 dark:bg-zinc-800 text-sm font-semibold disabled:opacity-50"
                                    >
                                        <option value="" disabled>{{ form.stage ? 'Select staff…' : 'Choose a stage first' }}</option>
                                        <option v-for="member in eligibleStaff" :key="member.id" :value="member.id">
                                            {{ member.name }} — {{ humanize(member.manufacturing_role) }}
                                        </option>
                                    </select>
                                    <span v-if="form.stage && !eligibleStaff.length" class="text-xs text-amber-600">No staff with the required role in this department.</span>
                                    <span v-if="form.errors.assigned_to" class="text-xs text-rose-500">{{ form.errors.assigned_to }}</span>
                                </label>

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
