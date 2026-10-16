<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Warehouse,
    Plus,
    Pencil,
    Trash2,
    X,
    MapPin,
    User,
    Building2,
    CheckCircle,
    AlertCircle,
    Search,
    ChevronDown,
    Eye,
    AlertTriangle,
} from 'lucide-vue-next';

const props = defineProps({
    warehouses: {
        type: Array,
        default: () => [],
    },
    users: {
        type: Array,
        default: () => [], 
    },
    auth: Object,
});

import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();

const page = usePage();
const user = computed(() => props.auth?.user);
// Respect IT Access Control: anyone holding WAR/warehouse=edit (e.g. a
// supervisor granted edit in the IT module) can manage — not just CEO.
// Native WAR managers/staff already receive auto-grants via shared props.
const canManage = computed(() => {
    if (canEdit('WAR', 'warehouse')) return true;
    const role = user.value?.role;
    const position = user.value?.position;
    return role === 'CEO' || position === 'secretary' || position === 'special_officer';
});

// UI state
const showAddModal = ref(false);
const showEditModal = ref(false);
const showErrorModal = ref(false);
const showConfirmModal = ref(false); // Modal for Deletion Confirmation
const editingWarehouse = ref(null);
const searchQuery = ref('');
const processing = ref(false);

// Form data
const form = ref({
    name: '',
    location: '',
    supervisor_id: '',
});

// Configuration for the custom confirmation modal
const confirmConfig = ref({
    title: '',
    message: '',
    id: null
});

// Watch for errors to trigger the error modal
watch(() => page.props.errors.error, (newError) => {
    if (newError) {
        showErrorModal.value = true;
    }
});

const resetForm = () => {
    form.value = {
        name: '',
        location: '',
        supervisor_id: '',
    };
};

const openAddModal = () => {
    resetForm();
    showAddModal.value = true;
};

const openEditModal = (warehouse) => {
    editingWarehouse.value = warehouse;
    form.value = {
        name: warehouse.name,
        location: warehouse.location,
        supervisor_id: warehouse.supervisor_id || '',
    };
    showEditModal.value = true;
};

const addWarehouse = () => {
    if (!form.value.name || !form.value.location) return;
    processing.value = true;
    router.post(route('warehouse.store'), form.value, {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            resetForm();
        },
        onFinish: () => (processing.value = false),
    });
};

const updateWarehouse = () => {
    if (!form.value.name || !form.value.location) return;
    processing.value = true;
    router.put(route('warehouse.update', editingWarehouse.value.id), form.value, {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editingWarehouse.value = null;
            resetForm();
        },
        onFinish: () => (processing.value = false),
    });
};

// Trigger the custom confirmation modal
const confirmDelete = (warehouse) => {
    confirmConfig.value = {
        title: 'Delete Warehouse',
        message: `Are you sure you want to delete "${warehouse.name}"? This will remove the warehouse and all its historical data from the system.`,
        id: warehouse.id
    };
    showConfirmModal.value = true;
};

// Actual delete execution
const executeDelete = () => {
    if (!confirmConfig.value.id) return;
    
    router.delete(route('warehouse.destroy', confirmConfig.value.id), {
        preserveScroll: true,
        onFinish: () => {
            showConfirmModal.value = false;
        },
    });
};

const getUserName = (id) => {
    const user = props.users.find(u => u.id === id);
    return user ? user.name : '—';
};

const getColorClass = (color) => {
    const colors = {
        blue: 'bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800',
        emerald: 'bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800',
        amber: 'bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800',
        violet: 'bg-violet-50 border-violet-200 dark:bg-violet-900/20 dark:border-violet-800',
        rose: 'bg-rose-50 border-rose-200 dark:bg-rose-900/20 dark:border-rose-800',
        cyan: 'bg-cyan-50 border-cyan-200 dark:bg-cyan-900/20 dark:border-cyan-800',
    };
    return colors[color] || colors.blue;
};

const filteredWarehouses = computed(() => {
    if (!searchQuery.value) return props.warehouses;
    const q = searchQuery.value.toLowerCase();
    return props.warehouses.filter(wh =>
        wh.name.toLowerCase().includes(q) ||
        wh.location.toLowerCase().includes(q)
    );
});
</script>

<template>
    <Head title="Warehouse Management | Monti Textile" />
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
                            <Warehouse class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Building2 class="h-3.5 w-3.5" /> Warehouse · Storage
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Warehouse Management</h1>
                            <p class="text-sm text-blue-100/90">{{ filteredWarehouses.length }} of {{ warehouses.length }} location{{ warehouses.length !== 1 ? 's' : '' }} showing · Manage locations and supervisors</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ warehouses.length }} total
                            </span>
                            <span v-if="canManage" class="rounded-full bg-emerald-400/90 px-3 py-1.5 text-xs font-black text-emerald-950">Full access</span>
                            <span v-else class="rounded-full bg-amber-300/90 px-3 py-1.5 text-xs font-black text-amber-950">View only</span>
                        </div>
                    </div>

                    <!-- Search + action -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchQuery" type="text" placeholder="Search warehouses or locations..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <button
                            v-if="canManage"
                            @click="openAddModal"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl bg-white px-5 py-3 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg transition-all duration-200 hover:scale-105 active:scale-95"
                        >
                            <Plus class="w-4 h-4" />
                            Add Warehouse
                        </button>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="filteredWarehouses.length === 0"
                    class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 dark:to-violet-900/20 rounded-full mb-4 animate-bounce-soft">
                        <Warehouse class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">{{ warehouses.length === 0 ? 'No warehouses yet' : 'No matches found' }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ warehouses.length === 0 ? 'Click "Add Warehouse" to get started.' : 'Try a different search.' }}</p>
                    <button v-if="searchQuery" @click="searchQuery=''"
                        class="mt-4 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition active:scale-95">Clear search</button>
                </div>

                <!-- Warehouse grid -->
                <TransitionGroup v-else name="card" tag="div" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="(wh, i) in filteredWarehouses"
                        :key="wh.id"
                        :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                        class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden"
                    >
                        <!-- hover glow -->
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />

                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 flex items-center justify-center text-white shadow-lg uppercase flex-shrink-0 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                    <Warehouse class="h-6 w-6" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-black text-gray-900 dark:text-white truncate tracking-tight group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors">
                                        {{ wh.name }}
                                    </p>
                                    <p class="flex items-center gap-1 text-[10px] text-indigo-600 dark:text-indigo-400 font-bold uppercase truncate">
                                        <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ wh.skus || 0 }} SKUs · {{ Number(wh.total_units || 0).toLocaleString() }} units
                                    </p>
                                </div>
                            </div>
                            <div class="flex gap-1 flex-shrink-0 ml-2">
                                <Link :href="route('warehouse.monitor', wh.id)" preserve-scroll
                                    class="flex h-8 w-8 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 hover:bg-emerald-600 hover:text-white transition-all duration-300"
                                    title="Monitor Layout">
                                    <Eye class="w-3.5 h-3.5" />
                                </Link>
                                <template v-if="canManage">
                                    <button @click="openEditModal(wh)" class="flex h-8 w-8 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 hover:bg-indigo-600 hover:text-white transition-all duration-300" title="Edit"><Pencil class="w-3.5 h-3.5" /></button>
                                    <button @click="confirmDelete(wh)" class="flex h-8 w-8 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 hover:bg-rose-600 hover:text-white transition-all duration-300" title="Delete"><Trash2 class="w-3.5 h-3.5" /></button>
                                </template>
                            </div>
                        </div>

                        <div class="space-y-1.5 mb-4 text-[12px]">
                            <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20"><MapPin class="h-3 w-3 text-blue-500" /></span>
                                <span class="truncate font-medium">{{ wh.location }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-900/20"><User class="h-3 w-3 text-emerald-500" /></span>
                                <span class="truncate font-medium">Supervisor: {{ wh.supervisor ? wh.supervisor.name : getUserName(wh.supervisor_id) || '—' }}</span>
                            </div>
                        </div>

                        <div class="mt-auto flex items-center justify-between pt-3 border-t border-gray-100 dark:border-zinc-800">
                            <div class="flex gap-4">
                                <div class="flex items-center gap-1.5">
                                    <CheckCircle class="h-3.5 w-3.5 text-indigo-400" />
                                    <span class="text-sm font-black text-gray-900 dark:text-white">{{ wh.skus || 0 }}</span>
                                    <span class="text-[9px] text-gray-400 font-bold uppercase">SKUs</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <Building2 class="h-3.5 w-3.5 text-fuchsia-400" />
                                    <span class="text-sm font-black text-gray-900 dark:text-white">{{ Number(wh.total_units || 0).toLocaleString() }}</span>
                                    <span class="text-[9px] text-gray-400 font-bold uppercase">Units</span>
                                </div>
                            </div>
                            <Link :href="route('warehouse.monitor', wh.id)" preserve-scroll class="flex h-8 w-8 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 group-hover:bg-indigo-600 group-hover:text-white transition-all duration-300">
                                <Eye class="h-4 w-4" />
                            </Link>
                        </div>
                    </div>
                </TransitionGroup>

            </div>
        </div>

        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" @click.self="showAddModal = false">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl border border-gray-100 dark:border-zinc-800 w-full max-w-md overflow-hidden">
                        <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 py-5 text-white">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <div class="relative flex items-center justify-between">
                                <h3 class="text-lg font-black tracking-tight">Add Warehouse</h3>
                                <button @click="showAddModal = false" class="p-1.5 rounded-xl bg-white/15 ring-1 ring-white/25 hover:bg-white/25 transition"><X class="w-4 h-4" /></button>
                            </div>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Warehouse Name *</label>
                                <input v-model="form.name" type="text" class="mt-1 w-full px-3 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-2xl focus:ring-2 focus:ring-indigo-500/30 outline-none text-slate-700 dark:text-slate-200" />
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Location *</label>
                                <input v-model="form.location" type="text" class="mt-1 w-full px-3 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-2xl focus:ring-2 focus:ring-indigo-500/30 outline-none text-slate-700 dark:text-slate-200" />
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Supervisor</label>
                                <div class="relative">
                                    <select v-model="form.supervisor_id" class="mt-1 w-full appearance-none pl-3 pr-8 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-2xl focus:ring-2 focus:ring-indigo-500/30 outline-none text-slate-700 dark:text-slate-200">
                                        <option value="">— Select Supervisor —</option>
                                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} ({{ user.position }})</option>
                                    </select>
                                    <ChevronDown class="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400 pointer-events-none" />
                                </div>
                            </div>
                        </div>
                        <div class="px-6 pb-6 flex gap-3">
                            <button @click="showAddModal = false" class="flex-1 py-2.5 text-sm font-bold rounded-2xl border border-slate-200 dark:border-zinc-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-zinc-800 transition active:scale-95">Cancel</button>
                            <button @click="addWarehouse" :disabled="processing" class="flex-1 py-2.5 text-sm font-bold rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:opacity-90 transition shadow-lg shadow-indigo-500/25 disabled:opacity-40 active:scale-95">Add Warehouse</button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" @click.self="showEditModal = false">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl border border-gray-100 dark:border-zinc-800 w-full max-w-md overflow-hidden">
                        <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 py-5 text-white">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <div class="relative flex items-center justify-between">
                                <h3 class="text-lg font-black tracking-tight">Edit Warehouse</h3>
                                <button @click="showEditModal = false" class="p-1.5 rounded-xl bg-white/15 ring-1 ring-white/25 hover:bg-white/25 transition"><X class="w-4 h-4" /></button>
                            </div>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Warehouse Name *</label>
                                <input v-model="form.name" type="text" class="mt-1 w-full px-3 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-2xl focus:ring-2 focus:ring-indigo-500/30 outline-none text-slate-700 dark:text-slate-200" />
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Location *</label>
                                <input v-model="form.location" type="text" class="mt-1 w-full px-3 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-2xl focus:ring-2 focus:ring-indigo-500/30 outline-none text-slate-700 dark:text-slate-200" />
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Supervisor</label>
                                <div class="relative">
                                    <select v-model="form.supervisor_id" class="mt-1 w-full appearance-none pl-3 pr-8 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-2xl focus:ring-2 focus:ring-indigo-500/30 outline-none text-slate-700 dark:text-slate-200">
                                        <option value="">— Select Supervisor —</option>
                                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} ({{ user.position }})</option>
                                    </select>
                                    <ChevronDown class="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400 pointer-events-none" />
                                </div>
                            </div>
                        </div>
                        <div class="px-6 pb-6 flex gap-3">
                            <button @click="showEditModal = false" class="flex-1 py-2.5 text-sm font-bold rounded-2xl border border-slate-200 dark:border-zinc-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-zinc-800 transition active:scale-95">Cancel</button>
                            <button @click="updateWarehouse" :disabled="processing" class="flex-1 py-2.5 text-sm font-bold rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:opacity-90 transition shadow-lg shadow-indigo-500/25 disabled:opacity-40 active:scale-95">Save Changes</button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showConfirmModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden border border-gray-100 dark:border-zinc-800">
                        <div class="relative overflow-hidden bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 px-6 py-5 text-white text-center">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <div class="relative flex flex-col items-center">
                                <div class="w-14 h-14 bg-white/15 ring-1 ring-white/30 rounded-2xl flex items-center justify-center mb-2 animate-pop">
                                    <AlertTriangle class="w-7 h-7" />
                                </div>
                                <h3 class="text-lg font-black tracking-tight uppercase">{{ confirmConfig.title }}</h3>
                            </div>
                        </div>
                        <div class="p-6 flex flex-col items-center text-center">
                            <p class="text-slate-500 dark:text-slate-400 text-sm mb-6 leading-relaxed">{{ confirmConfig.message }}</p>
                            <div class="flex w-full gap-3">
                                <button @click="showConfirmModal = false" class="flex-1 py-2.5 text-sm font-bold rounded-2xl border border-slate-200 dark:border-zinc-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-zinc-800 transition active:scale-95">No, Cancel</button>
                                <button @click="executeDelete" class="flex-1 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-black uppercase rounded-2xl transition shadow-lg shadow-red-500/25 active:scale-95">Yes, Delete</button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showErrorModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl border border-red-100 dark:border-red-900/30 w-full max-w-sm overflow-hidden">
                        <div class="relative overflow-hidden bg-gradient-to-br from-rose-600 via-red-600 to-orange-600 px-6 py-5 text-white text-center">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <div class="relative flex flex-col items-center">
                                <div class="w-14 h-14 bg-white/15 ring-1 ring-white/30 rounded-2xl flex items-center justify-center mb-2 animate-pop">
                                    <AlertCircle class="w-7 h-7" />
                                </div>
                                <h3 class="text-lg font-black tracking-tight uppercase">Action Denied</h3>
                            </div>
                        </div>
                        <div class="p-6 flex flex-col items-center text-center">
                            <p class="text-slate-500 dark:text-slate-400 text-sm leading-relaxed mb-6 font-medium">{{ $page.props.errors.error }}</p>
                            <button @click="showErrorModal = false" class="w-full py-3 bg-red-600 hover:bg-red-700 text-white text-sm font-black uppercase rounded-2xl transition shadow-lg shadow-red-500/25 active:scale-95">Understood</button>
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
.modal-enter-active { transition: opacity 0.3s ease; }
.modal-enter-active > div { transition: transform 0.35s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from { opacity: 0; }
.modal-enter-from > div { opacity: 0; transform: translateY(20px) scale(0.96); }
.modal-leave-active { transition: opacity 0.2s ease; }
.modal-leave-to { opacity: 0; }
</style>
