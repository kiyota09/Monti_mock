<template>
    <Head title="Fleet Management" />
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
                            <Truck class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> Logistics · Asset Registry
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Fleet Management <span v-if="!canEditFleet" class="ml-2 inline-block rounded-full bg-amber-400/90 px-3 py-1 align-middle text-xs font-black uppercase tracking-widest text-amber-950">View only</span></h1>
                            <p class="text-sm text-blue-100/90">{{ filteredTrucks.length }} of {{ trucks.length }} truck{{ trucks.length !== 1 ? 's' : '' }} showing</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse" /> {{ availableCount }} available
                            </span>
                            <button v-if="canEditFleet" @click="openCreateModal"
                                class="rounded-full bg-white px-4 py-2 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:bg-indigo-50 hover:scale-105 active:scale-95 transition-all flex items-center gap-1.5">
                                <Plus class="h-4 w-4" /> Add Truck
                            </button>
                        </div>
                    </div>

                    <!-- Search + filters -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchTerm" type="text" placeholder="Search by number, plate, or model..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="key in ['all','available','in_use','under_maintenance','retired']" :key="key" @click="statusFilter = key"
                                :class="statusFilter === key ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                                class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                                {{ key.replace('_', ' ') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats Summary -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:80ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 via-blue-600 to-cyan-600 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Truck class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Total Trucks</p>
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ trucks.length }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:160ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-emerald-400/20 to-teal-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-emerald-500 to-teal-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <CheckCircle2 class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-1.5">Available <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" /></p>
                                <h3 class="text-3xl font-black text-emerald-600 tracking-tight">{{ availableCount }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:240ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-amber-400/20 to-orange-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-amber-500 to-orange-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Navigation class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">In Use</p>
                                <h3 class="text-3xl font-black text-amber-600 tracking-tight">{{ inUseCount }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:320ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-rose-400/20 to-red-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-rose-500 to-red-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-rose-500 via-red-600 to-orange-600 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Wrench class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Maintenance</p>
                                <h3 class="text-3xl font-black text-red-600 tracking-tight">{{ maintenanceCount }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Trucks Table -->
                <div class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden hover:shadow-xl transition-shadow duration-300" style="animation-delay:120ms">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                        <Truck class="h-5 w-5 text-indigo-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">Registered Trucks</h2>
                        <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-1">{{ filteredTrucks.length }}</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 dark:border-zinc-800">
                                <tr>
                                    <th class="px-6 py-4">Truck #</th>
                                    <th class="px-6 py-4">Plate #</th>
                                    <th class="px-6 py-4">Model</th>
                                    <th class="px-6 py-4">Year</th>
                                    <th class="px-6 py-4 text-right">Mileage (km)</th>
                                    <th class="px-6 py-4 text-center">Status</th>
                                    <th class="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <TransitionGroup name="card" tag="tbody" class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="(truck, i) in filteredTrucks" :key="truck.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }" class="group text-sm hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 group-hover:scale-110 transition-transform">
                                                <Truck class="h-5 w-5" />
                                            </div>
                                            <span class="font-mono text-sm font-black text-gray-900 dark:text-white">{{ truck.truck_number }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 font-mono text-sm text-gray-500 dark:text-gray-400">{{ truck.plate_number }}</td>
                                    <td class="px-6 py-3 text-sm font-medium text-gray-700 dark:text-gray-200">{{ truck.model }}</td>
                                    <td class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400">{{ truck.year }}</td>
                                    <td class="px-6 py-3 text-right text-sm font-bold text-gray-900 dark:text-white">{{ truck.mileage?.toLocaleString() || 0 }}</td>
                                    <td class="px-6 py-3 text-center">
                                        <span :class="statusBadge(truck.status)" class="px-3 py-1 rounded-full ring-1 text-[9px] font-black uppercase inline-flex items-center gap-1">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />
                                            {{ formatStatus(truck.status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button v-if="canEditFleet" @click="openEditModal(truck)" class="flex h-8 w-8 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 hover:bg-indigo-600 hover:text-white transition-all">
                                                <Edit class="h-4 w-4" />
                                            </button>
                                            <button v-if="canEditFleet" @click="confirmDelete(truck)" class="flex h-8 w-8 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 hover:bg-red-600 hover:text-white transition-all">
                                                <Trash2 class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </TransitionGroup>
                        </table>
                        <div v-if="filteredTrucks.length === 0" class="px-6 py-12 text-center">
                            <Truck class="mx-auto h-10 w-10 text-gray-200 dark:text-zinc-700 animate-bounce-soft" />
                            <p class="mt-2 text-sm font-bold text-gray-400">No trucks found. Click "Add Truck" to register your first vehicle.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeModal">
                    <div class="modal-panel bg-white dark:bg-zinc-900 w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden">
                        <div class="relative overflow-hidden px-6 py-5 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white flex justify-between items-center">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                            <h3 class="relative font-black text-lg">{{ isEditing ? 'Edit Truck' : 'Add New Truck' }}</h3>
                            <button @click="closeModal" class="relative p-1.5 hover:bg-white/20 rounded-xl transition"><X class="h-5 w-5" /></button>
                        </div>

                        <form @submit.prevent="submitForm" class="p-6 space-y-5">
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">Truck Number *</label>
                                <input v-model="form.truck_number" type="text" required
                                    class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500"
                                    placeholder="e.g., TRK-001">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">Plate Number *</label>
                                <input v-model="form.plate_number" type="text" required
                                    class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500"
                                    placeholder="e.g., ABC-1234">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">Model *</label>
                                <input v-model="form.model" type="text" required
                                    class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500"
                                    placeholder="e.g., Isuzu Elf">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">Year *</label>
                                <input v-model.number="form.year" type="number" required min="1990" :max="new Date().getFullYear()"
                                    class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">Mileage (km)</label>
                                <input v-model.number="form.mileage" type="number" step="0.01" min="0"
                                    class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">Status</label>
                                <select v-model="form.status"
                                    class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                                    <option value="available">Available</option>
                                    <option value="in_use">In Use</option>
                                    <option value="under_maintenance">Under Maintenance</option>
                                    <option value="retired">Retired</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">Remarks</label>
                                <textarea v-model="form.remarks" rows="2"
                                    class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500"
                                    placeholder="Additional notes..."></textarea>
                            </div>

                            <div class="flex gap-3 pt-4 border-t border-gray-100 dark:border-zinc-800">
                                <button type="button" @click="closeModal"
                                    class="flex-1 px-4 py-3 border border-gray-200 dark:border-zinc-700 rounded-xl text-[10px] font-black uppercase hover:bg-gray-50 dark:hover:bg-zinc-800 transition active:scale-95">
                                    Cancel
                                </button>
                                <button v-if="canEditFleet" type="submit" :disabled="form.processing"
                                    class="flex-1 px-4 py-3 bg-indigo-600 text-white rounded-xl text-[10px] font-black uppercase hover:bg-indigo-700 transition disabled:opacity-50 flex items-center justify-center gap-2 active:scale-95">
                                    <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                                    {{ form.processing ? 'Saving...' : (isEditing ? 'Update' : 'Create') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Delete Confirmation Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showDeleteModal = false">
                    <div class="modal-panel bg-white dark:bg-zinc-900 w-full max-w-md rounded-3xl shadow-2xl overflow-hidden">
                        <div class="relative overflow-hidden px-6 py-5 bg-gradient-to-br from-rose-600 via-red-600 to-orange-600 text-white flex justify-between items-center">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <h3 class="relative font-black text-lg">Delete Truck</h3>
                            <button @click="showDeleteModal = false" class="relative p-1.5 hover:bg-white/20 rounded-xl transition"><X class="h-5 w-5" /></button>
                        </div>
                        <div class="p-6 space-y-4">
                            <p class="text-sm text-gray-700 dark:text-gray-300">Are you sure you want to delete <span class="font-bold">{{ truckToDelete?.truck_number }}</span>?</p>
                            <p class="text-xs text-red-500 font-medium">This action cannot be undone.</p>
                            <div class="flex gap-3">
                                <button @click="showDeleteModal = false" class="flex-1 px-4 py-3 border border-gray-200 dark:border-zinc-700 rounded-xl text-[10px] font-black uppercase hover:bg-gray-50 dark:hover:bg-zinc-800 transition active:scale-95">Cancel</button>
                                <button v-if="canEditFleet" @click="deleteTruck" :disabled="deleting" class="flex-1 px-4 py-3 bg-red-600 text-white rounded-xl text-[10px] font-black uppercase hover:bg-red-700 transition disabled:opacity-50 active:scale-95">
                                    <Loader2 v-if="deleting" class="h-4 w-4 animate-spin inline mr-1" />
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Toast Notification -->
        <Transition name="toast">
            <div v-if="toast.show" class="fixed bottom-8 right-8 z-50 px-6 py-3 rounded-2xl shadow-lg text-white font-bold text-sm"
                :class="toast.type === 'success' ? 'bg-emerald-600' : 'bg-red-600'">
                {{ toast.message }}
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Truck, Plus, Search, Edit, Trash2, X, Loader2, Sparkles, CheckCircle2, Navigation, Wrench } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditFleet = computed(() => canEdit('LOG', 'fleet'));

const props = defineProps({
    trucks: {
        type: Array,
        default: () => []
    }
});

// Filters
const searchTerm = ref('');
const statusFilter = ref('all');

// Modal states
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const showDeleteModal = ref(false);
const truckToDelete = ref(null);
const deleting = ref(false);
const toast = ref({ show: false, type: 'success', message: '' });

const form = useForm({
    truck_number: '',
    plate_number: '',
    model: '',
    year: new Date().getFullYear(),
    mileage: 0,
    status: 'available',
    remarks: ''
});

const filteredTrucks = computed(() => {
    let list = props.trucks;
    if (searchTerm.value) {
        const term = searchTerm.value.toLowerCase();
        list = list.filter(t =>
            t.truck_number.toLowerCase().includes(term) ||
            t.plate_number.toLowerCase().includes(term) ||
            t.model.toLowerCase().includes(term)
        );
    }
    if (statusFilter.value !== 'all') {
        list = list.filter(t => t.status === statusFilter.value);
    }
    return list;
});

const availableCount = computed(() => props.trucks.filter(t => t.status === 'available').length);
const inUseCount = computed(() => props.trucks.filter(t => t.status === 'in_use').length);
const maintenanceCount = computed(() => props.trucks.filter(t => t.status === 'under_maintenance').length);

const statusBadge = (status) => {
    const map = {
        available: 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30',
        in_use: 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
        under_maintenance: 'bg-red-100 text-red-700 ring-red-200 dark:bg-red-500/15 dark:text-red-300 dark:ring-red-500/30',
        retired: 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700'
    };
    return map[status] || 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
};

const formatStatus = (status) => {
    const map = {
        available: 'Available',
        in_use: 'In Use',
        under_maintenance: 'Under Maintenance',
        retired: 'Retired'
    };
    return map[status] || status;
};

const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

const refreshData = () => {
    router.reload({ only: ['trucks'] });
};

const openCreateModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    form.year = new Date().getFullYear();
    form.status = 'available';
    showModal.value = true;
};

const openEditModal = (truck) => {
    isEditing.value = true;
    editingId.value = truck.id;
    form.truck_number = truck.truck_number;
    form.plate_number = truck.plate_number;
    form.model = truck.model;
    form.year = truck.year;
    form.mileage = truck.mileage || 0;
    form.status = truck.status;
    form.remarks = truck.remarks || '';
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
};

const submitForm = () => {
    if (isEditing.value) {
        form.put(route('logistics.fleet.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => {
                showToast('success', 'Truck updated successfully.');
                closeModal();
                refreshData();
            },
            onError: (errors) => {
                const errorMsg = Object.values(errors)[0] || 'Update failed.';
                showToast('error', errorMsg);
            }
        });
    } else {
        form.post(route('logistics.fleet.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showToast('success', 'Truck added successfully.');
                closeModal();
                refreshData();
            },
            onError: (errors) => {
                const errorMsg = Object.values(errors)[0] || 'Creation failed.';
                showToast('error', errorMsg);
            }
        });
    }
};

const confirmDelete = (truck) => {
    truckToDelete.value = truck;
    showDeleteModal.value = true;
};

const deleteTruck = () => {
    deleting.value = true;
    router.delete(route('logistics.fleet.destroy', truckToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showToast('success', `Truck ${truckToDelete.value.truck_number} deleted.`);
            showDeleteModal.value = false;
            truckToDelete.value = null;
            refreshData();
        },
        onError: (errors) => {
            showToast('error', 'Failed to delete truck.');
        },
        onFinish: () => {
            deleting.value = false;
        }
    });
};
</script>

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
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease; }
.modal-enter-active .modal-panel, .modal-leave-active .modal-panel { transition: transform 0.35s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-panel { opacity: 0; transform: translateY(24px) scale(0.97); }
.modal-leave-to .modal-panel { opacity: 0; transform: translateY(12px) scale(0.98); }
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }
</style>
