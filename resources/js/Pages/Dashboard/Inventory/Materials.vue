<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Package, Plus, X, Search, ChevronDown, AlertTriangle,
    Eye, Info, History, TrendingUp, Save, Pencil,
    CheckCircle, AlertCircle, Trash2, BadgeCheck, Sparkles, Factory
} from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditMaterials = computed(() => canEdit('INV', 'materials'));

const props = defineProps({
    materials: { type: Array, default: () => [] },
    auth: Object,
});

// UI State
const searchQuery = ref('');
const showAddModal = ref(false);
const showViewModal = ref(false);
const showEditModal = ref(false);
const showConfirmModal = ref(false);
const selectedMaterial = ref(null);

// Configuration for the Global Confirmation Modal
const confirmConfig = ref({
    title: '',
    message: '',
    type: 'confirm',
    action: null
});

// Forms
const addForm = useForm({
    name: '',
    category: 'Yarn',
    unit: 'Kg',
    reorder_point: 0,
});

const editForm = useForm({
    name: '',
    category: 'Yarn',
    unit: 'Kg',
    reorder_point: 0,
});

// Helpers
const formatCurrency = (val) => '₱' + Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2 });

const filteredMaterials = computed(() => {
    if (!searchQuery.value) return props.materials;
    const q = searchQuery.value.toLowerCase();
    return props.materials.filter(mat =>
        mat.name.toLowerCase().includes(q) || mat.mat_id.toLowerCase().includes(q)
    );
});

// Modal Control Helpers
const triggerConfirm = (title, message, type, action) => {
    confirmConfig.value = { title, message, type, action };
    showConfirmModal.value = true;
};

const closeConfirm = () => {
    showConfirmModal.value = false;
};

// --- Actions ---

const addMaterial = () => {
    addForm.clearErrors();
    if (!addForm.name) {
        addForm.setError('name', 'Material name is required.');
        return;
    }

    triggerConfirm(
        'Confirm Registration',
        `Are you sure you want to register ${addForm.name} into the system?`,
        'confirm',
        () => {
            addForm.post(route('inv.materials.store'), {
                onSuccess: () => {
                    showAddModal.value = false;
                    addForm.reset();
                    closeConfirm();
                },
            });
        }
    );
};

const openViewModal = (material) => {
    selectedMaterial.value = material;
    editForm.name = material.name;
    editForm.category = material.category;
    editForm.unit = material.unit;
    editForm.reorder_point = material.reorder_point;
    editForm.clearErrors();
    showViewModal.value = true;
};

const openEditModal = (material) => {
    selectedMaterial.value = material;
    editForm.name = material.name;
    editForm.category = material.category;
    editForm.unit = material.unit;
    editForm.reorder_point = material.reorder_point;
    editForm.clearErrors();
    showEditModal.value = true;
};

const submitUpdate = () => {
    triggerConfirm(
        'Save Changes',
        'Do you want to apply these updates to the material profile?',
        'confirm',
        () => {
            editForm.patch(route('inv.materials.update', selectedMaterial.value.id), {
                onSuccess: () => {
                    showViewModal.value = false;
                    showEditModal.value = false;
                    closeConfirm();
                },
            });
        }
    );
};

const stockStatus = (mat) => {
    if (mat.total_stock <= 0) return 'Out of Stock';
    if (mat.total_stock <= mat.reorder_point) return 'Low Stock';
    return 'In Stock';
};

const statusColor = (status) => {
    if (status === 'In Stock') return 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30';
    if (status === 'Low Stock') return 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
    return 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30';
};

// CHECK 2 — OPENED (production) stock: remaining qty on live manufacturing lots.
const openedStatus = (mat) => {
    if ((mat.opened_stock ?? 0) > 0) return 'In Production';
    if (mat.opened_status === 'depleted') return 'Depleted';
    return 'Not Moved';
};

const openedColor = (status) => {
    if (status === 'In Production') return 'bg-sky-100 text-sky-700 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-300 dark:ring-sky-500/30';
    if (status === 'Depleted') return 'bg-zinc-200 text-zinc-600 ring-zinc-300 dark:bg-zinc-700/40 dark:text-zinc-300 dark:ring-zinc-600';
    return 'bg-gray-100 text-gray-400 ring-gray-200 dark:bg-zinc-800 dark:text-gray-500 dark:ring-zinc-700';
};
</script>

<template>
    <Head title="Materials | Inventory" />
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
                            <Package class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> Inventory · Control Center
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Materials Management <span v-if="!canEditMaterials" class="ml-2 inline-block rounded-full bg-amber-400/90 px-3 py-1 align-middle text-xs font-black uppercase tracking-widest text-amber-950">View only</span></h1>
                            <p class="text-sm text-blue-100/90">{{ filteredMaterials.length }} of {{ materials.length }} material{{ materials.length !== 1 ? 's' : '' }} showing</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button v-if="canEditMaterials" @click="showAddModal = true" class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg transition-all duration-200 hover:scale-105 active:scale-95 inline-flex items-center gap-2">
                                <Plus class="w-4 h-4" /> Add Material
                            </button>
                        </div>
                    </div>

                    <!-- Search inside hero -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchQuery" type="text" placeholder="Filter by name or ID..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                    </div>
                </div>

                <!-- Materials table card -->
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
                        <table key="mat-table" class="w-full text-left text-sm">
                            <thead class="bg-slate-50/80 dark:bg-zinc-800/60 border-b border-gray-100 dark:border-zinc-800">
                                <tr>
                                    <th class="px-8 py-4 text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest">Material ID</th>
                                    <th class="px-8 py-4 text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest">Name</th>
                                    <th class="px-8 py-4 text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest">Category</th>
                                    <th class="px-8 py-4 text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest text-center">Unopened · Warehouse</th>
                                    <th class="px-8 py-4 text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest text-center">Opened · Production</th>
                                    <th class="px-8 py-4 text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr v-for="mat in filteredMaterials" :key="mat.id" class="hover:bg-indigo-50/40 dark:hover:bg-indigo-950/10 transition-colors">
                                    <td class="px-8 py-5 font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400">{{ mat.mat_id }}</td>
                                    <td class="px-8 py-5 font-black text-sm text-gray-900 dark:text-white tracking-tight">{{ mat.name }}</td>
                                    <td class="px-8 py-5 text-[11px] font-bold uppercase text-slate-500 dark:text-gray-400 tracking-wider">{{ mat.category }}</td>
                                    <td class="px-8 py-5 text-center">
                                        <span :class="['inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase ring-1', statusColor(stockStatus(mat))]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ stockStatus(mat) }}
                                        </span>
                                        <p class="mt-1 text-[11px] font-black text-gray-900 dark:text-white">{{ Number(mat.total_stock ?? 0).toLocaleString() }} <span class="font-bold text-gray-400">{{ mat.unit }}</span></p>
                                    </td>
                                    <td class="px-8 py-5 text-center">
                                        <span :class="['inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase ring-1', openedColor(openedStatus(mat))]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ openedStatus(mat) }}
                                        </span>
                                        <p class="mt-1 text-[11px] font-black text-gray-900 dark:text-white">{{ Number(mat.opened_stock ?? 0).toLocaleString() }} <span class="font-bold text-gray-400">{{ mat.unit }}</span></p>
                                    </td>
                                    <td class="px-8 py-5 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button @click="openViewModal(mat)" class="flex h-9 w-9 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-gray-400 hover:bg-indigo-600 hover:text-white hover:scale-110 transition-all duration-200" title="View"><Eye class="w-4 h-4" /></button>
                                            <button v-if="canEditMaterials" @click="openEditModal(mat)" class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-900/20 text-amber-500 hover:bg-amber-500 hover:text-white hover:scale-110 transition-all duration-200" title="Edit"><Pencil class="w-4 h-4" /></button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </TransitionGroup>
                </div>
            </div>
        </div>

        <!-- Add Material Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showAddModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/40 dark:bg-black/60 backdrop-blur-md" @click.self="showAddModal = false">
                    <div class="modal-panel bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl border border-gray-100 dark:border-zinc-800 w-full max-w-md overflow-hidden">
                        <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                            <div class="relative flex items-center justify-between">
                                <h3 class="text-lg font-black tracking-tight">Create Material</h3>
                                <button @click="showAddModal = false" class="p-2 rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition"><X class="w-4 h-4" /></button>
                            </div>
                            <p class="relative text-xs text-blue-100/90 mt-1">Register a new raw material.</p>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="space-y-1">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Material Name</label>
                                <input v-model="addForm.name" type="text" class="w-full px-4 py-3 bg-slate-100 dark:bg-zinc-800 border-0 rounded-2xl focus:ring-2 focus:ring-indigo-500/30 font-bold text-sm text-gray-900 dark:text-white outline-none" placeholder="e.g. Cotton Yarn" />
                                <p v-if="addForm.errors.name" class="text-rose-500 text-[10px] font-black uppercase tracking-tight">{{ addForm.errors.name }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Category</label>
                                    <select v-model="addForm.category" class="w-full px-4 py-3 bg-slate-100 dark:bg-zinc-800 border-0 rounded-2xl font-bold text-sm text-gray-900 dark:text-white outline-none">
                                        <option v-for="cat in ['Yarn', 'Dye', 'Supplies', 'Packaging']" :value="cat">{{ cat }}</option>
                                    </select>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Unit</label>
                                    <select v-model="addForm.unit" class="w-full px-4 py-3 bg-slate-100 dark:bg-zinc-800 border-0 rounded-2xl font-bold text-sm text-gray-900 dark:text-white outline-none">
                                        <option v-for="u in ['Rolls', 'Kg', 'Pcs']" :value="u">{{ u }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Reorder Point</label>
                                <input v-model="addForm.reorder_point" type="number" class="w-full px-4 py-3 bg-slate-100 dark:bg-zinc-800 border-0 rounded-2xl font-bold text-sm text-gray-900 dark:text-white outline-none" />
                            </div>
                        </div>
                        <div class="p-6 pt-0">
                            <button v-if="canEditMaterials" @click="addMaterial" class="w-full py-3.5 bg-indigo-600 text-white font-black uppercase text-xs rounded-2xl hover:bg-indigo-700 hover:scale-[1.01] active:scale-95 transition-all shadow-lg shadow-indigo-500/25">Register Material</button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- View/Edit Material Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showViewModal && selectedMaterial" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-lg" @click.self="showViewModal = false">
                    <div class="modal-panel bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl border border-gray-100 dark:border-zinc-800 w-full max-w-5xl overflow-hidden flex flex-col max-h-[90vh]">
                        <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white">
                            <div class="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/10 blur-3xl animate-float" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                            <div class="relative flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 animate-pop"><Info class="w-6 h-6" /></div>
                                    <div>
                                        <h3 class="font-black tracking-tight text-lg">Material Insight &amp; Edit</h3>
                                        <p class="text-xs text-blue-100/90">{{ selectedMaterial.mat_id }} · {{ selectedMaterial.name }}</p>
                                    </div>
                                </div>
                                <button @click="showViewModal = false" class="p-2.5 rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition"><X class="w-5 h-5" /></button>
                            </div>
                        </div>

                        <div class="p-6 overflow-y-auto space-y-6">
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                                <div class="p-5 bg-indigo-50/60 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/40 rounded-3xl col-span-2 md:col-span-1">
                                    <label class="text-[10px] font-black text-indigo-600 dark:text-indigo-400 block mb-2 tracking-widest uppercase">Update Name</label>
                                    <input v-model="editForm.name" type="text" :disabled="!canEditMaterials" class="w-full bg-transparent border-0 p-0 text-base font-black focus:ring-0 text-slate-800 dark:text-white outline-none disabled:opacity-60" />
                                </div>
                                <div class="p-5 bg-slate-50 dark:bg-zinc-800/60 border border-gray-100 dark:border-zinc-800 rounded-3xl opacity-70">
                                    <p class="text-[10px] text-slate-400 mb-2 tracking-widest uppercase font-black">Material ID (Locked)</p>
                                    <p class="font-mono text-sm font-bold text-indigo-600 dark:text-indigo-400">{{ selectedMaterial.mat_id }}</p>
                                </div>
                                <div class="p-5 bg-slate-50 dark:bg-zinc-800/60 border border-gray-100 dark:border-zinc-800 rounded-3xl">
                                    <p class="text-[10px] text-slate-400 mb-2 tracking-widest uppercase font-black">Unopened · Warehouse</p>
                                    <p class="text-2xl font-black text-gray-900 dark:text-white">{{ selectedMaterial.total_stock }} <span class="text-xs font-bold text-gray-400">{{ selectedMaterial.unit }}</span></p>
                                    <span :class="['mt-2 inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase ring-1', statusColor(stockStatus(selectedMaterial))]">
                                        <span class="h-1 w-1 rounded-full bg-current animate-pulse" />{{ stockStatus(selectedMaterial) }}
                                    </span>
                                </div>
                                <div class="p-5 bg-sky-50/70 dark:bg-sky-950/20 border border-sky-100 dark:border-sky-900/40 rounded-3xl">
                                    <p class="text-[10px] text-sky-600 dark:text-sky-400 mb-2 tracking-widest uppercase font-black flex items-center gap-1"><Factory class="w-3 h-3" /> Opened · Production</p>
                                    <p class="text-2xl font-black text-gray-900 dark:text-white">{{ selectedMaterial.opened_stock ?? 0 }} <span class="text-xs font-bold text-gray-400">{{ selectedMaterial.unit }}</span></p>
                                    <span :class="['mt-2 inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase ring-1', openedColor(openedStatus(selectedMaterial))]">
                                        <span class="h-1 w-1 rounded-full bg-current animate-pulse" />{{ openedStatus(selectedMaterial) }}
                                    </span>
                                </div>
                                <div class="p-5 bg-indigo-50/60 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/40 rounded-3xl">
                                    <label class="text-[10px] font-black text-indigo-600 dark:text-indigo-400 block mb-2 tracking-widest uppercase">Reorder Threshold</label>
                                    <div class="flex items-center gap-2">
                                        <input v-model.number="editForm.reorder_point" type="number" :disabled="!canEditMaterials" class="w-full bg-transparent border-0 p-0 text-2xl font-black focus:ring-0 text-slate-900 dark:text-white outline-none disabled:opacity-60" />
                                        <span class="text-xs text-slate-400">{{ selectedMaterial.unit }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-3xl border border-indigo-100 dark:border-indigo-900/40 bg-indigo-50/50 dark:bg-indigo-950/10 p-5">
                                <h4 class="text-[10px] font-black uppercase tracking-widest text-indigo-500 dark:text-indigo-300 mb-3 flex items-center gap-2"><TrendingUp class="w-4 h-4" /> Flow Trace · every kilo accounted for</h4>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-center">
                                    <div class="bg-white dark:bg-zinc-900 rounded-2xl px-3 py-3 border border-gray-100 dark:border-zinc-800">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Received</p>
                                        <p class="text-lg font-black text-gray-900 dark:text-white">{{ Number(selectedMaterial.received_total ?? 0).toLocaleString() }} <span class="text-[10px] font-bold text-gray-400">{{ selectedMaterial.unit }}</span></p>
                                    </div>
                                    <div class="bg-white dark:bg-zinc-900 rounded-2xl px-3 py-3 border border-gray-100 dark:border-zinc-800">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Unopened · Warehouse</p>
                                        <p class="text-lg font-black text-emerald-600">{{ Number(selectedMaterial.total_stock ?? 0).toLocaleString() }} <span class="text-[10px] font-bold text-gray-400">{{ selectedMaterial.unit }}</span></p>
                                    </div>
                                    <div class="bg-white dark:bg-zinc-900 rounded-2xl px-3 py-3 border border-gray-100 dark:border-zinc-800">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Opened · Production</p>
                                        <p class="text-lg font-black text-sky-600">{{ Number(selectedMaterial.opened_stock ?? 0).toLocaleString() }} <span class="text-[10px] font-bold text-gray-400">{{ selectedMaterial.unit }}</span></p>
                                    </div>
                                    <div class="bg-white dark:bg-zinc-900 rounded-2xl px-3 py-3 border border-gray-100 dark:border-zinc-800">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Consumed</p>
                                        <p class="text-lg font-black text-slate-500 dark:text-gray-300">{{ Number(selectedMaterial.consumed_qty ?? 0).toLocaleString() }} <span class="text-[10px] font-bold text-gray-400">{{ selectedMaterial.unit }}</span></p>
                                    </div>
                                </div>
                                <p class="mt-2 text-[10px] font-bold text-slate-400 text-center">Received = Warehouse + Production + Consumed · moved to production: {{ Number(selectedMaterial.moved_to_production ?? 0).toLocaleString() }} {{ selectedMaterial.unit }}</p>
                            </div>

                            <div class="space-y-4">
                                <h4 class="text-sm font-black uppercase tracking-widest flex items-center gap-2 text-gray-900 dark:text-white"><History class="w-5 h-5 text-indigo-500" /> Lot Management Records <span class="text-[10px] text-slate-400 font-bold">· Unopened warehouse lots</span></h4>
                                <div class="rounded-3xl border border-gray-100 dark:border-zinc-800 overflow-hidden bg-slate-50/60 dark:bg-zinc-800/30">
                                    <table class="w-full text-left text-xs uppercase font-bold">
                                        <thead class="text-slate-400 border-b border-gray-100 dark:border-zinc-800 tracking-widest text-[10px]">
                                            <tr>
                                                <th class="px-6 py-4">Lot ID</th>
                                                <th class="px-6 py-4">PO Ref</th>
                                                <th class="px-6 py-4">Location</th>
                                                <th class="px-6 py-4 text-right">Received Qty</th>
                                                <th class="px-6 py-4 text-right">Total Value</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800 text-slate-700 dark:text-gray-300">
                                            <tr v-for="log in selectedMaterial.delivery_history" :key="log.lot_number" class="hover:bg-white dark:hover:bg-zinc-900 transition">
                                                <td class="px-6 py-4 text-indigo-600 dark:text-indigo-400 font-black">{{ log.lot_number }}</td>
                                                <td class="px-6 py-4 font-mono">{{ log.po_number }}</td>
                                                <td class="px-6 py-4">{{ log.warehouse_name }}</td>
                                                <td class="px-6 py-4 text-right text-gray-900 dark:text-white font-black">{{ log.kg }} {{ selectedMaterial.unit }}</td>
                                                <td class="px-6 py-4 text-right text-gray-900 dark:text-white font-black">{{ formatCurrency(log.total_amount) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <h4 class="text-sm font-black uppercase tracking-widest flex items-center gap-2 text-gray-900 dark:text-white"><Factory class="w-5 h-5 text-sky-500" /> Production Lots <span class="text-[10px] text-slate-400 font-bold">· Opened stock in manufacturing</span></h4>
                                <div class="rounded-3xl border border-sky-100 dark:border-sky-900/40 overflow-hidden bg-sky-50/50 dark:bg-sky-950/10">
                                    <table v-if="selectedMaterial.production_breakdown?.length" class="w-full text-left text-xs uppercase font-bold">
                                        <thead class="text-slate-400 border-b border-sky-100 dark:border-sky-900/40 tracking-widest text-[10px]">
                                            <tr>
                                                <th class="px-6 py-4">Department</th>
                                                <th class="px-6 py-4 text-right">Live Lots</th>
                                                <th class="px-6 py-4 text-right">Remaining</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-sky-100/60 dark:divide-sky-900/20 text-slate-700 dark:text-gray-300">
                                            <tr v-for="row in selectedMaterial.production_breakdown" :key="row.department" class="hover:bg-white dark:hover:bg-zinc-900 transition">
                                                <td class="px-6 py-4 text-sky-700 dark:text-sky-300 font-black capitalize">{{ row.department }}</td>
                                                <td class="px-6 py-4 text-right font-black">{{ row.lots }}</td>
                                                <td class="px-6 py-4 text-right text-gray-900 dark:text-white font-black">{{ row.remaining }} {{ selectedMaterial.unit }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <p v-else class="px-6 py-8 text-center text-xs font-bold text-slate-400">No opened stock — this material has never been moved to production{{ selectedMaterial.opened_status === 'depleted' ? ' (all lots fully consumed)' : '' }}.</p>
                                </div>
                            </div>
                        </div>
                        <div class="p-5 bg-slate-50/80 dark:bg-zinc-800/40 border-t border-gray-100 dark:border-zinc-800 flex flex-wrap justify-between items-center gap-3">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2"><CheckCircle class="w-4 h-4 text-emerald-500" /> Changes persist in master database</span>
                            <div class="flex gap-3">
                                <button @click="showViewModal = false" class="px-6 py-3 font-black uppercase text-[11px] text-slate-500 hover:text-slate-900 dark:hover:text-white transition">Dismiss</button>
                                <button v-if="canEditMaterials" @click="submitUpdate" :disabled="editForm.processing" class="px-8 py-3 bg-indigo-600 text-white font-black uppercase text-[11px] rounded-2xl shadow-lg shadow-indigo-500/25 hover:bg-indigo-700 hover:scale-105 active:scale-95 transition-all flex items-center gap-2">
                                    <Save class="w-4 h-4" /> {{ editForm.processing ? 'Updating...' : 'Sync Master Profile' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Edit Material Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showEditModal && selectedMaterial" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-lg" @click.self="showEditModal = false">
                    <div class="modal-panel bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl border border-gray-100 dark:border-zinc-800 w-full max-w-md overflow-hidden">
                        <div class="relative overflow-hidden bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 p-6 text-white">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                            <div class="relative flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 animate-pop"><Pencil class="w-5 h-5" /></div>
                                    <div>
                                        <h3 class="text-lg font-black tracking-tight">Edit Material</h3>
                                        <p class="relative text-xs text-white/80 mt-0.5 font-mono">{{ selectedMaterial.mat_id }}</p>
                                    </div>
                                </div>
                                <button @click="showEditModal = false" class="p-2 rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition"><X class="w-4 h-4" /></button>
                            </div>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="space-y-1">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Material Name</label>
                                <input v-model="editForm.name" type="text" placeholder="e.g. Cotton Yarn"
                                    class="w-full px-4 py-3 bg-slate-100 dark:bg-zinc-800 border-0 rounded-2xl focus:ring-2 focus:ring-amber-500/30 font-bold text-sm text-gray-900 dark:text-white outline-none" />
                                <p v-if="editForm.errors.name" class="text-rose-500 text-[10px] font-black uppercase tracking-tight">{{ editForm.errors.name }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Category</label>
                                    <select v-model="editForm.category"
                                        class="w-full px-4 py-3 bg-slate-100 dark:bg-zinc-800 border-0 rounded-2xl font-bold text-sm text-gray-900 dark:text-white outline-none">
                                        <option v-for="cat in ['Yarn', 'Dye', 'Supplies', 'Packaging']" :value="cat">{{ cat }}</option>
                                    </select>
                                    <p v-if="editForm.errors.category" class="text-rose-500 text-[10px] font-black uppercase tracking-tight">{{ editForm.errors.category }}</p>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Unit</label>
                                    <select v-model="editForm.unit"
                                        class="w-full px-4 py-3 bg-slate-100 dark:bg-zinc-800 border-0 rounded-2xl font-bold text-sm text-gray-900 dark:text-white outline-none">
                                        <option v-for="u in ['Rolls', 'Kg', 'Pcs']" :value="u">{{ u }}</option>
                                    </select>
                                    <p v-if="editForm.errors.unit" class="text-rose-500 text-[10px] font-black uppercase tracking-tight">{{ editForm.errors.unit }}</p>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Reorder Point ({{ selectedMaterial.unit }})</label>
                                <input v-model.number="editForm.reorder_point" type="number" min="0"
                                    class="w-full px-4 py-3 bg-slate-100 dark:bg-zinc-800 border-0 rounded-2xl focus:ring-2 focus:ring-amber-500/30 font-bold text-sm text-gray-900 dark:text-white outline-none" />
                                <p v-if="editForm.errors.reorder_point" class="text-rose-500 text-[10px] font-black uppercase tracking-tight">{{ editForm.errors.reorder_point }}</p>
                            </div>
                        </div>
                        <div class="p-6 pt-0 flex gap-3">
                            <button @click="showEditModal = false" class="flex-1 py-3.5 bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-gray-300 font-black uppercase text-xs rounded-2xl hover:bg-slate-200 dark:hover:bg-zinc-700 transition">Cancel</button>
                            <button @click="submitUpdate" :disabled="editForm.processing || !editForm.name" class="flex-[1.5] py-3.5 bg-amber-500 text-white font-black uppercase text-xs rounded-2xl hover:bg-amber-600 transition shadow-lg shadow-amber-500/25 active:scale-95 disabled:opacity-50">
                                {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Global Confirmation Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showConfirmModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xl">
                    <div class="modal-panel bg-white dark:bg-zinc-900 rounded-3xl p-8 max-w-md w-full shadow-2xl border border-gray-100 dark:border-zinc-800 text-center">
                        <div class="mx-auto w-16 h-16 rounded-2xl flex items-center justify-center mb-5 shadow-lg animate-pop" :class="confirmConfig.type === 'danger' ? 'bg-gradient-to-br from-rose-500 to-red-600 text-white' : 'bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white'">
                            <AlertTriangle v-if="confirmConfig.type === 'danger'" class="w-8 h-8" />
                            <BadgeCheck v-else class="w-8 h-8" />
                        </div>
                        <h3 class="text-lg font-black uppercase tracking-tight text-gray-900 dark:text-white mb-2">{{ confirmConfig.title }}</h3>
                        <p class="text-sm font-medium text-slate-500 dark:text-gray-400 leading-relaxed px-4 mb-6">{{ confirmConfig.message }}</p>
                        <div class="flex gap-3">
                            <button @click="closeConfirm" class="flex-1 py-3.5 rounded-2xl bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-gray-300 text-xs font-black uppercase hover:bg-slate-200 dark:hover:bg-zinc-700 transition">Wait, Go Back</button>
                            <button @click="confirmConfig.action" class="flex-1 py-3.5 rounded-2xl text-white text-xs font-black uppercase shadow-lg transition hover:scale-[1.02] active:scale-95" :class="confirmConfig.type === 'danger' ? 'bg-red-600 hover:bg-red-700 shadow-red-500/25' : 'bg-indigo-600 hover:bg-indigo-700 shadow-indigo-500/25'">Process Action</button>
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
