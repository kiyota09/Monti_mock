<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Plus, Wrench, Calendar, X, CheckCircle, AlertTriangle, Eye, Sparkles, Cog, ClipboardList } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditProduction = computed(() => canEdit('MAN', 'production'));

const props = defineProps({
    machines: Array,
});

// State for modals
const showAddModal = ref(false);
const showStatusModal = ref(false);
const currentMachine = ref(null);

// Form for adding new machine
const addForm = useForm({
    machine_no: '',
    type: '',
    remarks: '',
});

// Form for updating machine status
const statusForm = useForm({
    status: '',
    remarks: '',
});

// Machine types
const machineTypes = [
    { value: 'knitting', label: 'Knitting Machine' },
    { value: 'dyeing', label: 'Dyeing Machine' },
    { value: 'softening', label: 'Softening Machine' },
    { value: 'squeezer', label: 'Squeezer Machine' },
];

// Status options
const statusOptions = [
    { value: 'available', label: 'Available', color: 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30' },
    { value: 'under_maintenance', label: 'Under Maintenance', color: 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30' },
    { value: 'retired', label: 'Retired', color: 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30' },
];

const getStatusBadgeClass = (status) => {
    const option = statusOptions.find(opt => opt.value === status);
    return option?.color || 'bg-gray-100 text-gray-800';
};

const openAddModal = () => {
    addForm.reset();
    showAddModal.value = true;
};

const openStatusModal = (machine) => {
    currentMachine.value = machine;
    statusForm.status = machine.status;
    statusForm.remarks = machine.remarks || '';
    showStatusModal.value = true;
};

const submitAddMachine = () => {
    addForm.post(route('man.staff.maintenance-checker.store-machine'), {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
            router.reload({ only: ['machines'] });
        },
    });
};

const submitUpdateStatus = () => {
    statusForm.patch(route('man.staff.maintenance-checker.update-machine', currentMachine.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showStatusModal.value = false;
            statusForm.reset();
            router.reload({ only: ['machines'] });
        },
    });
};

const closeModal = () => {
    showAddModal.value = false;
    showStatusModal.value = false;
    currentMachine.value = null;
};

// Group machines by type for display
const groupedMachines = computed(() => {
    const groups = {};
    props.machines.forEach(machine => {
        if (!groups[machine.type]) groups[machine.type] = [];
        groups[machine.type].push(machine);
    });
    return groups;
});
</script>

<template>
    <AuthenticatedLayout title="Maintenance Management">
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <Cog class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> MAN · Maintenance
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Machine Management</h1>
                            <span v-if="!canEditProduction" class="mt-2 inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-amber-800">View only</span>
                            <p class="text-sm text-blue-100/90">{{ machines?.length ?? 0 }} machine{{ (machines?.length ?? 0) !== 1 ? 's' : '' }} · {{ Object.keys(groupedMachines).length }} types</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ machines?.length ?? 0 }} total
                            </span>
                            <button v-if="canEditProduction" @click="openAddModal"
                                class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg transition-all duration-200 hover:scale-105 active:scale-95 flex items-center gap-1.5">
                                <Plus class="w-4 h-4" /> Add Machine
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Calendar Section -->
                <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 transition-all duration-300 overflow-hidden" style="animation-delay: 80ms">
                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                    <div class="relative flex items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg"><Calendar class="w-4 h-4" /></span>
                        <h2 class="text-sm font-black tracking-tight text-gray-900 dark:text-white">Maintenance Schedule</h2>
                    </div>
                    <div class="relative p-6 text-center">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full w-fit mx-auto mb-4 animate-bounce-soft">
                            <Calendar class="w-9 h-9 text-indigo-400" />
                        </div>
                        <p class="text-sm font-black text-gray-700 dark:text-gray-200">Schedule preview will be implemented soon</p>
                        <p class="text-xs text-gray-400 mt-1">Here you'll be able to view upcoming maintenance tasks and set reminders.</p>
                    </div>
                </div>

                <!-- Machines by Type -->
                <TransitionGroup name="card" tag="div" class="space-y-6">
                    <div v-for="(machinesList, type, gi) in groupedMachines" :key="type"
                        :style="{ transitionDelay: `${Math.min(gi * 60, 400)}ms` }"
                        class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full" />
                        <div class="relative flex items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg"><Wrench class="w-4 h-4" /></span>
                            <h2 class="text-sm font-black tracking-tight text-gray-900 dark:text-white capitalize">{{ type }} Machines</h2>
                            <span class="ml-auto rounded-full bg-indigo-50 dark:bg-indigo-900/20 px-3 py-1 text-[11px] font-black text-indigo-700 dark:text-indigo-300">{{ machinesList.length }}</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Machine No.</th>
                                        <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Status</th>
                                        <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Remarks</th>
                                        <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                    <tr v-for="machine in machinesList" :key="machine.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors">
                                        <td class="px-6 py-4 font-mono text-sm font-bold text-gray-900 dark:text-white">{{ machine.machine_no }}</td>
                                        <td class="px-6 py-4">
                                            <span :class="getStatusBadgeClass(machine.status)"
                                                class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1 inline-flex items-center gap-1">
                                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ machine.status.replace('_', ' ') }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ machine.remarks || '—' }}</td>
                                        <td class="px-6 py-4">
                                            <button v-if="canEditProduction" @click="openStatusModal(machine)"
                                                class="rounded-xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-300 hover:bg-indigo-600 hover:text-white text-xs font-black px-3 py-1.5 flex items-center gap-1 transition-all hover:-translate-y-0.5 active:scale-95">
                                                <Eye class="w-4 h-4" /> Update Status
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </TransitionGroup>

                <div v-if="Object.keys(groupedMachines).length === 0" class="flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                        <Wrench class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">No machines found</p>
                    <p class="text-xs text-gray-400 mt-1">Click "Add Machine" to get started.</p>
                </div>
            </div>
        </div>

        <!-- Add Machine Modal -->
        <Transition name="modal">
            <div v-if="showAddModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="closeModal">
                <div class="bg-white/95 dark:bg-zinc-900/95 backdrop-blur rounded-3xl shadow-2xl w-full max-w-md overflow-hidden border border-gray-100 dark:border-zinc-800">
                    <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6">
                        <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                        <div class="relative flex justify-between items-center">
                            <h3 class="text-lg font-black text-white tracking-tight">Add New Machine</h3>
                            <button @click="closeModal" class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/15 text-white hover:bg-white/25 transition">
                                <X class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                    <form @submit.prevent="submitAddMachine" class="p-6 space-y-4">
                        <div>
                            <label class="block text-xs font-black uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1.5">Machine No.</label>
                            <input type="text" v-model="addForm.machine_no" required class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition"
                                placeholder="e.g., KN-001, DY-023" />
                        </div>
                        <div>
                            <label class="block text-xs font-black uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1.5">Machine Type</label>
                            <select v-model="addForm.type" required class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                                <option value="">Select Type</option>
                                <option v-for="type in machineTypes" :key="type.value" :value="type.value">
                                    {{ type.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-black uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1.5">Remarks</label>
                            <textarea v-model="addForm.remarks" rows="2" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition"
                                placeholder="Optional notes..."></textarea>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button v-if="canEditProduction" type="submit" :disabled="addForm.processing || !canEditProduction"
                                class="flex-1 rounded-2xl bg-indigo-600 text-white py-2.5 text-xs font-black uppercase tracking-wide shadow-lg shadow-indigo-500/25 hover:bg-indigo-700 transition active:scale-95 disabled:opacity-50">
                                {{ addForm.processing ? 'Adding...' : 'Add Machine' }}
                            </button>
                            <button type="button" @click="closeModal"
                                class="flex-1 rounded-2xl bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-gray-300 py-2.5 text-xs font-black uppercase tracking-wide hover:bg-gray-200 dark:hover:bg-zinc-700 transition active:scale-95">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>

        <!-- Update Status Modal -->
        <Transition name="modal">
            <div v-if="showStatusModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="closeModal">
                <div class="bg-white/95 dark:bg-zinc-900/95 backdrop-blur rounded-3xl shadow-2xl w-full max-w-md overflow-hidden border border-gray-100 dark:border-zinc-800">
                    <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6">
                        <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                        <div class="relative flex justify-between items-center">
                            <h3 class="text-lg font-black text-white tracking-tight">Update Machine Status</h3>
                            <button @click="closeModal" class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/15 text-white hover:bg-white/25 transition">
                                <X class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                    <form @submit.prevent="submitUpdateStatus" class="p-6 space-y-4">
                        <div class="rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 p-3 ring-1 ring-indigo-100 dark:ring-indigo-800">
                            <p class="text-sm font-bold text-gray-900 dark:text-white">Machine: <span class="font-mono">{{ currentMachine?.machine_no }}</span></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 capitalize">Type: {{ currentMachine?.type }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-black uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1.5">Status</label>
                            <select v-model="statusForm.status" required class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-black uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1.5">Remarks</label>
                            <textarea v-model="statusForm.remarks" rows="2" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition"
                                placeholder="Notes about status change..."></textarea>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button v-if="canEditProduction" type="submit" :disabled="statusForm.processing || !canEditProduction"
                                class="flex-1 rounded-2xl bg-indigo-600 text-white py-2.5 text-xs font-black uppercase tracking-wide shadow-lg shadow-indigo-500/25 hover:bg-indigo-700 transition active:scale-95 disabled:opacity-50">
                                {{ statusForm.processing ? 'Updating...' : 'Update Status' }}
                            </button>
                            <button type="button" @click="closeModal"
                                class="flex-1 rounded-2xl bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-gray-300 py-2.5 text-xs font-black uppercase tracking-wide hover:bg-gray-200 dark:hover:bg-zinc-700 transition active:scale-95">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
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
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: translateY(16px) scale(0.98); }
</style>
