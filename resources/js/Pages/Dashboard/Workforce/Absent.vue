<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    UserX, AlertTriangle, Calendar, Search, Filter,
    User, ShieldOff, X, CheckCircle, Clock
} from 'lucide-vue-next';

const props = defineProps({
    absentEmployees: {
        type: Array,
        default: () => []
    }
});

// Toast notification
const showToast = ref(false);
const toastMessage = ref('');
const triggerToast = (msg) => {
    toastMessage.value = msg;
    showToast.value = true;
    setTimeout(() => { showToast.value = false; }, 4000);
};

// Flash messages from server
const page = usePage();
if (page.props.flash?.message) {
    triggerToast(page.props.flash.message);
}

// Filters
const searchQuery = ref('');
const minConsecutiveAbsent = ref(1); // Minimum days to show

const filteredEmployees = computed(() => {
    let list = props.absentEmployees;
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(e =>
            e.name.toLowerCase().includes(q) ||
            e.role.toLowerCase().includes(q) ||
            e.department.toLowerCase().includes(q)
        );
    }
    list = list.filter(e => e.consecutive_absent_days >= minConsecutiveAbsent.value);
    return list;
});

// Stats
const stats = computed(() => ({
    total: props.absentEmployees.length,
    highAbsence: props.absentEmployees.filter(e => e.consecutive_absent_days >= 5).length,
    mediumAbsence: props.absentEmployees.filter(e => e.consecutive_absent_days >= 3 && e.consecutive_absent_days < 5).length,
    lowAbsence: props.absentEmployees.filter(e => e.consecutive_absent_days < 3).length,
}));

// Suspend modal state
const isSuspendModalOpen = ref(false);
const selectedEmployee = ref(null);
const suspendReason = ref('');

const openSuspendModal = (employee) => {
    selectedEmployee.value = employee;
    suspendReason.value = '';
    isSuspendModalOpen.value = true;
};

const closeSuspendModal = () => {
    isSuspendModalOpen.value = false;
    selectedEmployee.value = null;
    suspendReason.value = '';
};

const suspendEmployee = () => {
    if (!suspendReason.value.trim()) {
        alert('Please provide a reason for suspension.');
        return;
    }
    router.post(route('workforce.absent.suspend', selectedEmployee.value.id), {
        reason: suspendReason.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast(`Employee ${selectedEmployee.value.name} has been suspended.`);
            closeSuspendModal();
        },
        onError: (errors) => {
            triggerToast(Object.values(errors)[0] || 'Suspension failed.');
        }
    });
};

// Helper functions
const getAbsenceLevel = (days) => {
    if (days >= 5) return 'Critical';
    if (days >= 3) return 'Warning';
    return 'Monitor';
};

const getAbsenceClass = (days) => {
    if (days >= 5) return 'bg-red-100 text-red-700 ring-red-200 dark:bg-red-500/15 dark:text-red-300 dark:ring-red-500/30';
    if (days >= 3) return 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
    return 'bg-yellow-100 text-yellow-700 ring-yellow-200 dark:bg-yellow-500/15 dark:text-yellow-300 dark:ring-yellow-500/30';
};

const formatDate = (date) => date ? new Date(date).toLocaleString() : 'N/A';
const getInitials = (name) => name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
</script>

<template>
    <Head title="Absence Management" />

    <AuthenticatedLayout>
        <!-- Toast Notification -->
        <Transition name="toast">
            <div v-if="showToast"
                class="fixed top-6 right-6 z-[100] flex items-center gap-3 px-6 py-4 bg-slate-900 dark:bg-white text-white dark:text-slate-900 rounded-2xl shadow-2xl border border-white/10">
                <CheckCircle class="h-5 w-5 text-emerald-400 dark:text-emerald-600" />
                <p class="text-sm font-bold uppercase tracking-tight">{{ toastMessage }}</p>
            </div>
        </Transition>

        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">
                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <UserX class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <ShieldOff class="h-3.5 w-3.5" /> Workforce · Attendance
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Absence Tracking</h1>
                            <p class="text-sm text-blue-100/90">Monitor employee absenteeism and take action · {{ filteredEmployees.length }} showing</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ stats.total }} absent
                            </span>
                            <span class="rounded-full bg-red-400/90 px-3 py-1.5 text-xs font-black text-red-950">{{ stats.highAbsence }} critical</span>
                        </div>
                    </div>

                    <!-- Search + filters -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchQuery" type="text" placeholder="Search by name, role, department..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <div class="flex gap-2">
                            <button v-for="days in [1, 3, 5]" :key="days"
                                @click="minConsecutiveAbsent = days"
                                :class="minConsecutiveAbsent === days ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                                class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                                {{ days }}+ days
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <TransitionGroup name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div
                        v-for="(card, i) in [
                            { label: 'Total Absences', value: stats.total, icon: UserX, chip: 'bg-red-50 dark:bg-red-900/20', iconClass: 'text-red-500', bar: 'from-rose-500 to-red-500' },
                            { label: 'Critical (≥5 days)', value: stats.highAbsence, icon: AlertTriangle, chip: 'bg-red-100 dark:bg-red-900/30', iconClass: 'text-red-700 dark:text-red-400', bar: 'from-red-500 to-rose-600' },
                            { label: 'Warning (3-4 days)', value: stats.mediumAbsence, icon: Clock, chip: 'bg-amber-100 dark:bg-amber-900/30', iconClass: 'text-amber-700 dark:text-amber-400', bar: 'from-amber-500 to-orange-500' },
                            { label: 'Monitor (1-2 days)', value: stats.lowAbsence, icon: Calendar, chip: 'bg-yellow-100 dark:bg-yellow-900/30', iconClass: 'text-yellow-700 dark:text-yellow-400', bar: 'from-yellow-500 to-amber-500' },
                        ]"
                        :key="card.label"
                        :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                        class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div :class="['absolute left-0 top-5 bottom-5 w-1 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300 bg-gradient-to-b', card.bar]" />
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">{{ card.label }}</p>
                                <p class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ card.value }}</p>
                            </div>
                            <div :class="['p-3 rounded-2xl group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300', card.chip]">
                                <component :is="card.icon" :class="['w-5 h-5', card.iconClass]" />
                            </div>
                        </div>
                    </div>
                </TransitionGroup>

                <!-- Empty state -->
                <div v-if="filteredEmployees.length === 0"
                    class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                        <UserX class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">No absent employees found</p>
                    <p class="text-xs text-gray-400 mt-1">Try a different search or filter.</p>
                    <button v-if="searchQuery || minConsecutiveAbsent !== 1" @click="searchQuery=''; minConsecutiveAbsent=1"
                        class="mt-4 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition active:scale-95">Clear filters</button>
                </div>

                <!-- Absent Employees Table -->
                <div v-else class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden" style="animation-delay: 120ms">
                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Employee</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Department</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Consecutive Absences</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Last Clock In</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                                <tr v-for="emp in filteredEmployees" :key="emp.id" class="hover:bg-indigo-50/40 dark:hover:bg-indigo-950/20 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 flex items-center justify-center font-black text-xs text-white shadow-lg uppercase flex-shrink-0">
                                                {{ getInitials(emp.name) }}
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold text-slate-900 dark:text-white">{{ emp.name }}</p>
                                                <p class="text-xs text-slate-400">{{ emp.role }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ emp.department }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <span :class="['px-2 py-1 rounded-md text-[10px] font-black uppercase ring-1 inline-flex items-center gap-1', getAbsenceClass(emp.consecutive_absent_days)]">
                                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ emp.consecutive_absent_days }} days
                                            </span>
                                            <span class="text-[9px] font-bold" :class="getAbsenceLevel(emp.consecutive_absent_days) === 'Critical' ? 'text-red-600' : 'text-amber-600'">
                                                ({{ getAbsenceLevel(emp.consecutive_absent_days) }})
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-500">{{ emp.last_clock_in || 'Never' }}</td>
                                    <td class="px-6 py-4">
                                        <span v-if="emp.consecutive_absent_days >= 5" class="inline-flex items-center gap-1 text-red-600 text-xs font-bold uppercase"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />Action Required</span>
                                        <span v-else class="text-amber-600 text-xs font-bold uppercase">Monitor</span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button v-if="emp.consecutive_absent_days >= 5"
                                            @click="openSuspendModal(emp)"
                                            class="px-4 py-2 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white rounded-2xl text-xs font-black uppercase shadow-lg shadow-indigo-500/20 hover:scale-105 active:scale-95 transition flex items-center gap-2 ml-auto">
                                            <ShieldOff class="h-4 w-4" /> Suspend Account
                                        </button>
                                        <span v-else class="text-slate-400 text-xs italic">No action needed</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Suspend Modal -->
        <Transition name="modal">
            <div v-if="isSuspendModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeSuspendModal">
                <div class="modal-panel bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden">
                    <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-5 flex justify-between items-center">
                        <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                        <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                        <h2 class="relative text-lg font-black tracking-tight text-white uppercase">Suspend Employee</h2>
                        <button @click="closeSuspendModal" class="relative h-8 w-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition"><X class="h-4 w-4" /></button>
                    </div>
                    <div class="p-6 space-y-4">
                        <p class="text-sm text-slate-600 dark:text-slate-300">You are about to suspend <strong>{{ selectedEmployee?.name }}</strong> due to excessive absenteeism.</p>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Reason for Suspension</label>
                            <textarea v-model="suspendReason" rows="3" class="w-full px-3 py-2.5 rounded-2xl bg-slate-100 dark:bg-zinc-800 border-none text-sm outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g., Unauthorized absence for 5 consecutive days..."></textarea>
                        </div>
                        <div class="flex gap-3 pt-4">
                            <button @click="closeSuspendModal" class="flex-1 py-2.5 text-slate-500 dark:text-slate-400 font-bold text-sm hover:text-slate-700 transition">Cancel</button>
                            <button @click="suspendEmployee" class="flex-1 py-2.5 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white rounded-2xl font-extrabold text-sm shadow-lg shadow-indigo-500/20 hover:scale-[1.02] active:scale-95 transition">Confirm Suspension</button>
                        </div>
                    </div>
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
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-active .modal-panel, .modal-leave-active .modal-panel { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from .modal-panel { opacity: 0; transform: translateY(16px) scale(0.97); }
.modal-leave-to .modal-panel { opacity: 0; transform: translateY(8px) scale(0.98); }
.toast-enter-active, .toast-leave-active {
    transition: all 0.3s ease;
}
.toast-enter-from, .toast-leave-to {
    opacity: 0;
    transform: translateY(-12px);
}
</style>
