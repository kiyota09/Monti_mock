<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Package,
    ShoppingCart,
    AlertTriangle,
    AlertCircle,
    CheckCircle,
    Check,
    Search,
    X,
    Sparkles
} from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditChecker = computed(() => canEdit('INV', 'checker'));

const props = defineProps({
    materials: {
        type: Array,
        default: () => [],
    },
    pendingOrdersCount: {
        type: Number,
        default: 0,
    },
    auth: Object,
});

// UI state
const searchQuery = ref('');
const processingMaterial = ref(null);

// Filter materials
const filteredMaterials = computed(() => {
    if (!searchQuery.value) return props.materials;
    const q = searchQuery.value.toLowerCase();
    return props.materials.filter(mat =>
        mat.name.toLowerCase().includes(q) ||
        mat.mat_id.toLowerCase().includes(q)
    );
});

/**
 * SOLO PROCUREMENT — per-material request modal. The employee fills in
 * quantity, urgency and notes, reviews, then sends to SCM.
 */
const singleMat = ref(null);
const showSingleModal = ref(false);
const singleQty = ref(0);
const singleUrgency = ref('Medium');
const singleNotes = ref('');
const singleSending = ref(false);

const openSingleProcure = (material) => {
    singleMat.value = material;
    singleQty.value = material.reorder_point > 0 ? material.reorder_point : 100;
    singleUrgency.value = 'Medium';
    singleNotes.value = '';
    showSingleModal.value = true;
};

const submitSingle = () => {
    if (!singleMat.value || !(Number(singleQty.value) > 0) || singleSending.value) return;
    singleSending.value = true;
    processingMaterial.value = singleMat.value.id;
    router.post(route('inv.checker.procurement', singleMat.value.id), {
        required_qty: Number(singleQty.value),
        urgency: singleUrgency.value,
        notes: singleNotes.value || undefined,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showSingleModal.value = false;
            singleMat.value = null;
        },
        onFinish: () => {
            singleSending.value = false;
            processingMaterial.value = null;
        },
    });
};

const requestProcurement = (material) => {
    openSingleProcure(material);
};

/**
 * BULK PROCUREMENT — choose multiple raw materials, set a quantity,
 * urgency and notes PER material, review in a confirmation modal,
 * send once. SCM receives one pending request row per material.
 */
const bulkSel = ref({}); // { [materialId]: { qty, urgency, notes } }
const showBulkModal = ref(false);
const bulkSending = ref(false);

const defaultBulkQty = (mat) => (mat.reorder_point > 0 ? mat.reorder_point : 100);

const toggleBulk = (mat) => {
    if (bulkSel.value[mat.id] !== undefined) {
        delete bulkSel.value[mat.id];
    } else {
        bulkSel.value[mat.id] = { qty: defaultBulkQty(mat), urgency: 'Medium', notes: '' };
    }
};

const toggleBulkAll = () => {
    const ids = filteredMaterials.value.map((m) => m.id);
    const allIn = ids.length > 0 && ids.every((id) => bulkSel.value[id] !== undefined);
    if (allIn) {
        ids.forEach((id) => delete bulkSel.value[id]);
    } else {
        filteredMaterials.value.forEach((m) => {
            if (bulkSel.value[m.id] === undefined) bulkSel.value[m.id] = { qty: defaultBulkQty(m), urgency: 'Medium', notes: '' };
        });
    }
};

const bulkCount = computed(() => Object.keys(bulkSel.value).length);

const bulkAllChecked = computed(() => {
    const ids = filteredMaterials.value.map((m) => m.id);
    return ids.length > 0 && ids.every((id) => bulkSel.value[id] !== undefined);
});

// Resolve selection against fresh server props (drops stale ids).
const bulkLines = computed(() => {
    const byId = new Map(props.materials.map((m) => [m.id, m]));
    return Object.entries(bulkSel.value)
        .map(([id, entry]) => ({ mat: byId.get(Number(id)), entry }))
        .filter((l) => l.mat);
});

const bulkValid = computed(() => bulkLines.value.length > 0 && bulkLines.value.every((l) => Number(l.entry.qty) > 0));

const clearBulk = () => {
    bulkSel.value = {};
};

const openBulkReview = () => {
    if (!bulkValid.value) return;
    showBulkModal.value = true;
};

const submitBulk = () => {
    if (!bulkValid.value || bulkSending.value) return;
    bulkSending.value = true;
    router.post(route('inv.checker.procurement.bulk'), {
        items: bulkLines.value.map((l) => ({
            material_id: l.mat.id,
            required_qty: Number(l.entry.qty),
            urgency: l.entry.urgency,
            notes: l.entry.notes || undefined,
        })),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showBulkModal.value = false;
            clearBulk();
        },
        onFinish: () => {
            bulkSending.value = false;
        },
    });
};

// Status badge helper (unopened — matches Materials page)
const statusBadge = (status) => {
    switch (status) {
        case 'ok': return { class: 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30', label: 'In Stock' };
        case 'low': return { class: 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30', label: 'Low Stock' };
        case 'out': return { class: 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30', label: 'Out of Stock' };
        default: return { class: 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700', label: status };
    }
};

// Status badge helper (opened — matches Materials page)
const openedStatus = (mat) => {
    if ((mat.opened_stock ?? 0) > 0) return 'In Production';
    if (mat.opened_status === 'depleted') return 'Depleted';
    return 'Not Moved';
};

const openedBadge = (label) => {
    switch (label) {
        case 'In Production': return { class: 'bg-sky-100 text-sky-700 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-300 dark:ring-sky-500/30', label };
        case 'Depleted': return { class: 'bg-zinc-200 text-zinc-600 ring-zinc-300 dark:bg-zinc-700/40 dark:text-zinc-300 dark:ring-zinc-600', label };
        default: return { class: 'bg-gray-100 text-gray-400 ring-gray-200 dark:bg-zinc-800 dark:text-gray-500 dark:ring-zinc-700', label };
    }
};
</script>

<template>
    <Head title="Stock Checker | Inventory" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">
                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <ShoppingCart class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> Inventory · Stock Health
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Stock Checker <span v-if="!canEditChecker" class="ml-2 inline-block rounded-full bg-amber-400/90 px-3 py-1 align-middle text-xs font-black uppercase tracking-widest text-amber-950">View only</span></h1>
                            <p class="text-sm text-blue-100/90">Monitor material stock levels and trigger procurement for low items.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ pendingOrdersCount }} orders awaiting check
                            </span>
                        </div>
                    </div>

                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search materials..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition"
                            />
                        </div>
                    </div>
                </div>

                <!-- Bulk procurement bar -->
                <div v-if="canEditChecker && bulkCount > 0" class="animate-fade-up flex flex-col sm:flex-row sm:items-center gap-3 bg-white/90 dark:bg-zinc-900/90 backdrop-blur rounded-3xl border border-indigo-200 dark:border-indigo-800 shadow-lg shadow-indigo-500/10 px-5 py-4">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="rounded-full bg-indigo-600 text-white text-[11px] font-black px-2.5 py-1">{{ bulkCount }}</span>
                        <p class="text-sm font-black text-gray-900 dark:text-white truncate">material{{ bulkCount !== 1 ? 's' : '' }} selected — set quantity, urgency &amp; notes per material in review</p>
                    </div>
                    <div class="flex gap-2 sm:ml-auto w-full sm:w-auto">
                        <button @click="clearBulk" class="px-4 py-2.5 text-xs font-black uppercase tracking-wide rounded-xl border border-gray-200 dark:border-zinc-700 text-gray-500 dark:text-gray-300 hover:bg-slate-100 dark:hover:bg-zinc-800 transition active:scale-95">Clear</button>
                        <button @click="openBulkReview" :disabled="!bulkValid" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 text-xs font-black uppercase tracking-wide rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 shadow-lg shadow-indigo-500/25 transition active:scale-95 disabled:opacity-50">
                            <ShoppingCart class="w-4 h-4" /> Review &amp; Send
                        </button>
                    </div>
                </div>

                <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden" style="animation-delay: 80ms">
                    <div v-if="filteredMaterials.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                            <Package class="h-9 w-9 text-indigo-400" />
                        </div>
                        <p class="text-sm font-black text-gray-700 dark:text-gray-200">No materials found</p>
                        <p class="text-xs text-gray-400 mt-1">Try a different search.</p>
                        <button v-if="searchQuery" @click="searchQuery=''" class="mt-4 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition active:scale-95">Clear filters</button>
                    </div>
                    <TransitionGroup v-else name="card" tag="div" class="overflow-x-auto">
                        <table key="checker-table" class="w-full text-left text-sm">
                            <thead class="bg-slate-50/80 dark:bg-zinc-800/60 border-b border-gray-100 dark:border-zinc-800">
                                <tr>
                                    <th v-if="canEditChecker" class="pl-6 pr-2 py-4 w-10">
                                        <input type="checkbox" :checked="bulkAllChecked" @change="toggleBulkAll" title="Select all"
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    </th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest">Material ID</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest">Name</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest">Category</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest text-center">Unopened · Warehouse</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest text-center">Opened · Production</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest">Unit</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest">Reorder Point</th>
                                    <th class="px-6 py-4 text-center text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr v-for="mat in filteredMaterials" :key="mat.id"
                                    :class="['transition-colors', bulkSel[mat.id] !== undefined ? 'bg-indigo-50/70 dark:bg-indigo-950/20 hover:bg-indigo-100/60 dark:hover:bg-indigo-950/30' : 'hover:bg-indigo-50/40 dark:hover:bg-indigo-950/10']">
                                    <td v-if="canEditChecker" class="pl-6 pr-2 py-5">
                                        <input type="checkbox" :checked="bulkSel[mat.id] !== undefined" @change="toggleBulk(mat)" :title="`Select ${mat.name}`"
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    </td>
                                    <td class="px-6 py-5 font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400">{{ mat.mat_id }}</td>
                                    <td class="px-6 py-5">
                                        <p class="font-bold text-gray-900 dark:text-white">{{ mat.name }}</p>
                                        <span v-if="bulkSel[mat.id] !== undefined"
                                            class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest bg-blue-600 text-white shadow-md shadow-blue-500/30 animate-pop">
                                            <Check class="w-3 h-3" /> Selected
                                        </span>
                                    </td>
                                    <td class="px-6 py-5 text-slate-500 dark:text-gray-400 font-medium">{{ mat.category }}</td>
                                    <td class="px-6 py-5 text-center">
                                        <span :class="['inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase ring-1', statusBadge(mat.status).class]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />
                                            <AlertTriangle v-if="mat.status === 'low'" class="w-3 h-3" />
                                            <CheckCircle v-else-if="mat.status === 'ok'" class="w-3 h-3" />
                                            <AlertCircle v-else class="w-3 h-3" />
                                            {{ statusBadge(mat.status).label }}
                                        </span>
                                        <p class="mt-1 text-[11px] font-black text-gray-900 dark:text-white">{{ mat.total_stock.toLocaleString() }} <span class="font-bold text-gray-400">{{ mat.unit }}</span></p>
                                    </td>
                                    <td class="px-6 py-5 text-center">
                                        <span :class="['inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase ring-1', openedBadge(openedStatus(mat)).class]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />
                                            {{ openedBadge(openedStatus(mat)).label }}
                                        </span>
                                        <p class="mt-1 text-[11px] font-black text-gray-900 dark:text-white">{{ (mat.opened_stock ?? 0).toLocaleString() }} <span class="font-bold text-gray-400">{{ mat.unit }}</span></p>
                                    </td>
                                    <td class="px-6 py-5 font-bold text-slate-500 dark:text-gray-400">{{ mat.unit }}</td>
                                    <td class="px-6 py-5 text-slate-500 dark:text-gray-400 font-bold">{{ mat.reorder_point.toLocaleString() }}</td>
                                    <td class="px-6 py-5 text-center">
                                        <button
                                            v-if="canEditChecker"
                                            @click="requestProcurement(mat)"
                                            :disabled="processingMaterial === mat.id"
                                            class="inline-flex items-center gap-2 px-4 py-2 text-[10px] font-black uppercase tracking-widest rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 hover:scale-105 active:scale-95 transition-all shadow-lg shadow-indigo-500/20 disabled:opacity-50"
                                        >
                                            <ShoppingCart class="w-3.5 h-3.5" />
                                            {{ processingMaterial === mat.id ? 'Sending...' : 'Procure' }}
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </TransitionGroup>
                </div>
            </div>
        </div>

        <!-- Solo procurement modal (quantity + urgency + notes per material) -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showSingleModal && singleMat" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-lg" @click.self="showSingleModal = false">
                    <div class="modal-panel bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl border border-gray-100 dark:border-zinc-800 w-full max-w-md overflow-hidden">
                        <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                            <div class="relative flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-black tracking-tight">Request Procurement</h3>
                                    <p class="relative text-xs text-blue-100/90 mt-1">{{ singleMat.name }} · {{ singleMat.mat_id }}</p>
                                </div>
                                <button @click="showSingleModal = false" class="p-2 rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition"><X class="w-4 h-4" /></button>
                            </div>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-gray-100 dark:border-zinc-800 px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-gray-400">
                                Unopened {{ Number(singleMat.total_stock ?? 0).toLocaleString() }} {{ singleMat.unit }} · reorder at {{ Number(singleMat.reorder_point ?? 0).toLocaleString() }} {{ singleMat.unit }}
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Quantity ({{ singleMat.unit }})</label>
                                <input v-model.number="singleQty" type="number" step="0.01" min="0.01"
                                    class="w-full px-4 py-3 bg-slate-100 dark:bg-zinc-800 border-0 rounded-2xl focus:ring-2 focus:ring-indigo-500/30 font-bold text-sm text-gray-900 dark:text-white outline-none" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Urgency</label>
                                <select v-model="singleUrgency"
                                    class="w-full px-4 py-3 bg-slate-100 dark:bg-zinc-800 border-0 rounded-2xl font-bold text-sm text-gray-900 dark:text-white outline-none">
                                    <option value="High">High</option>
                                    <option value="Medium">Medium</option>
                                    <option value="Low">Low</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Notes</label>
                                <textarea v-model="singleNotes" rows="3" placeholder="Why is this needed?..."
                                    class="w-full px-4 py-3 bg-slate-100 dark:bg-zinc-800 border-0 rounded-2xl focus:ring-2 focus:ring-indigo-500/30 font-bold text-sm text-gray-900 dark:text-white placeholder:text-gray-300 dark:placeholder:text-zinc-600 outline-none resize-none"></textarea>
                            </div>
                            <p v-if="!(Number(singleQty) > 0)" class="text-xs font-bold text-rose-500">Quantity must be above zero.</p>
                        </div>
                        <div class="p-6 pt-0 flex gap-3">
                            <button @click="showSingleModal = false" class="flex-1 py-3.5 bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-gray-300 font-black uppercase text-xs rounded-2xl hover:bg-slate-200 dark:hover:bg-zinc-700 transition">Cancel</button>
                            <button @click="submitSingle" :disabled="!(Number(singleQty) > 0) || singleSending" class="flex-[1.5] py-3.5 bg-indigo-600 text-white font-black uppercase text-xs rounded-2xl hover:bg-indigo-700 transition shadow-lg shadow-indigo-500/25 active:scale-95 disabled:opacity-50">
                                {{ singleSending ? 'Sending...' : 'Confirm & Send to SCM' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Bulk procurement review & confirmation modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showBulkModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-lg" @click.self="showBulkModal = false">
                    <div class="modal-panel bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl border border-gray-100 dark:border-zinc-800 w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
                        <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                            <div class="relative flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-black tracking-tight">Confirm Bulk Request</h3>
                                    <p class="relative text-xs text-blue-100/90 mt-1">{{ bulkLines.length }} material{{ bulkLines.length !== 1 ? 's' : '' }} · one send to SCM</p>
                                </div>
                                <button @click="showBulkModal = false" class="p-2 rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition"><X class="w-4 h-4" /></button>
                            </div>
                        </div>
                        <div class="p-6 overflow-y-auto space-y-3">
                            <div v-for="line in bulkLines" :key="line.mat.id" class="p-4 rounded-2xl border border-gray-100 dark:border-zinc-800 bg-slate-50/60 dark:bg-zinc-800/40 space-y-3">
                                <div class="flex items-start gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-black text-sm text-gray-900 dark:text-white truncate">{{ line.mat.name }}</p>
                                        <p class="text-[11px] text-slate-500 dark:text-gray-400 font-mono">{{ line.mat.mat_id }} · unopened {{ Number(line.mat.total_stock ?? 0).toLocaleString() }} {{ line.mat.unit }} · reorder at {{ Number(line.mat.reorder_point ?? 0).toLocaleString() }}</p>
                                    </div>
                                    <button @click="toggleBulk(line.mat)" title="Remove" class="flex h-8 w-8 items-center justify-center rounded-xl text-gray-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition flex-shrink-0"><X class="w-4 h-4" /></button>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    <div class="space-y-1">
                                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block">Quantity ({{ line.mat.unit }})</label>
                                        <input v-model.number="line.entry.qty" type="number" step="0.01" min="0.01"
                                            class="w-full px-3 py-2 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl font-bold text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500/30" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block">Urgency</label>
                                        <select v-model="line.entry.urgency"
                                            class="w-full px-3 py-2 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl font-bold text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500/30">
                                            <option value="High">High</option>
                                            <option value="Medium">Medium</option>
                                            <option value="Low">Low</option>
                                        </select>
                                    </div>
                                    <div class="space-y-1 col-span-2 sm:col-span-1">
                                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block">Notes</label>
                                        <input v-model="line.entry.notes" type="text" placeholder="Why is this needed?..."
                                            class="w-full px-3 py-2 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl font-bold text-sm text-gray-900 dark:text-white placeholder:text-gray-300 dark:placeholder:text-zinc-600 outline-none focus:ring-2 focus:ring-indigo-500/30" />
                                    </div>
                                </div>
                            </div>
                            <p v-if="!bulkValid" class="text-xs font-bold text-rose-500">Every material needs a quantity above zero.</p>
                        </div>
                        <div class="p-6 pt-0 flex gap-3">
                            <button @click="showBulkModal = false" class="flex-1 py-3.5 bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-gray-300 font-black uppercase text-xs rounded-2xl hover:bg-slate-200 dark:hover:bg-zinc-700 transition">Back</button>
                            <button @click="submitBulk" :disabled="!bulkValid || bulkSending" class="flex-[1.5] py-3.5 bg-indigo-600 text-white font-black uppercase text-xs rounded-2xl hover:bg-indigo-700 transition shadow-lg shadow-indigo-500/25 active:scale-95 disabled:opacity-50">
                                {{ bulkSending ? 'Sending...' : `Confirm & Send ${bulkLines.length} Request${bulkLines.length !== 1 ? 's' : ''}` }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
@keyframes bounceSoft { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-float-delayed { animation: float 8s ease-in-out 1.2s infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.animate-bounce-soft { animation: bounceSoft 2.4s ease-in-out infinite; }
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-active .modal-panel, .modal-leave-active .modal-panel { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from .modal-panel { opacity: 0; transform: translateY(20px) scale(0.97); }
.modal-leave-to .modal-panel { opacity: 0; transform: translateY(12px) scale(0.98); }
</style>
