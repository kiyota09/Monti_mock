<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditProduction = computed(() => canEdit('MAN', 'production'));
import {
    Users, Package, Factory, TrendingUp, Search,
    X, ChevronRight, ArrowRight, Briefcase,
    Wrench, Sparkles, CheckCircle2, Palette,
    AlertCircle, RefreshCw, UserCheck, Clock,
    Calendar, BarChart3, PieChart, Zap, Activity,
    ShieldCheck
} from 'lucide-vue-next';

const props = defineProps({
    stats: Object,
    staff: Array,
    department: { type: String, default: null },
    departmentStats: { type: Object, default: null },
    recentHandovers: { type: Array, default: () => [] },
    activeBulletins: { type: Array, default: () => [] },
});

// ─── Shift handover (supervisor logs continuity for their own department) ────
const showHandoverForm = ref(false);
const handoverForm = useForm({
    shift_date: new Date().toISOString().slice(0, 10),
    shift_type: '', unfinished_work: '', machine_notes: '', hot_jobs: '', safety_notes: '',
});
const submitHandover = () => handoverForm.post(route('man.manager.handover.store'), {
    preserveScroll: true,
    onSuccess: () => { showHandoverForm.value = false; handoverForm.reset(); },
});

// ─── Department context (no manufacturing manager: each supervisor sees ─────
// ─── their own department; CEO / secretary / GM see the generic overview) ──
const DEPT_LABELS = {
    knitting: 'Knitting Department',
    dyeing: 'Dyeing Department',
    finishing: 'Finishing Department',
    maintenance: 'Maintenance Department',
};
const DEPT_ROLES = {
    knitting: ['knitting_yarn', 'knitting_mechanic'],
    dyeing: ['dyeing_color', 'dyeing_fabric_softener', 'dyeing_squeezer', 'dyeing_ironing', 'dyeing_packaging', 'dyeing_lab_chemist'],
    finishing: ['checker_quality'],
    maintenance: ['maintenance_checker', 'pollution_control_operator', 'safety_officer'],
    boiler: ['boiler_operator'],
};
const deptLabel = computed(() => (props.department ? (DEPT_LABELS[props.department] ?? props.department) : null));
const deptStatEntries = computed(() => {
    if (!props.departmentStats) return [];
    return Object.entries(props.departmentStats).map(([key, value]) => ({
        key,
        label: key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
        value,
    }));
});

// Reactive staff list
const staffList = ref([]);
const searchQuery = ref('');

// Modal state
const showConfirmModal = ref(false);
const pendingStaffId = ref(null);
const pendingNewRole = ref('');
const pendingOldRole = ref('');
const pendingStaffName = ref('');
const isUpdating = ref(false);

// Toast state
const toastMessage = ref('');
const toastType = ref('success');
const showToast = ref(false);
let toastTimeout = null;

onMounted(() => {
    staffList.value = props.staff.map(staff => ({
        ...staff,
        newRole: staff.manufacturing_role || ''
    }));
});

// Filter staff
const filteredStaff = computed(() => {
    if (!searchQuery.value.trim()) return staffList.value;
    const query = searchQuery.value.toLowerCase();
    return staffList.value.filter(staff =>
        staff.name.toLowerCase().includes(query) ||
        (staff.manufacturing_role && staff.manufacturing_role.replace(/_/g, ' ').toLowerCase().includes(query))
    );
});

// Helper for greeting
const getGreeting = () => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    return 'Good evening';
};

// Chart data (placeholder – replace with real data)
const productionTrend = computed(() => ({
    months: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
    values: [45, 52, 48, 67, 72, 68],
}));

const machineUtilization = computed(() => ({
    available: 7,
    maintenance: 2,
    retired: 1,
}));

const maxTrendValue = computed(() => Math.max(...productionTrend.value.values, 1));
const totalMachines = computed(() => machineUtilization.value.available + machineUtilization.value.maintenance + machineUtilization.value.retired);

const clearSearch = () => {
    searchQuery.value = '';
};

// Show toast notification
const showToastMessage = (message, type = 'success') => {
    if (toastTimeout) clearTimeout(toastTimeout);
    toastMessage.value = message;
    toastType.value = type;
    showToast.value = true;
    toastTimeout = setTimeout(() => {
        showToast.value = false;
    }, 3000);
};

// Open confirmation modal
const openConfirmModal = (staffId, newRole, oldRole, staffName) => {
    pendingStaffId.value = staffId;
    pendingNewRole.value = newRole;
    pendingOldRole.value = oldRole;
    pendingStaffName.value = staffName;
    showConfirmModal.value = true;
};

// Close modal without changes
const closeModal = () => {
    showConfirmModal.value = false;
    const index = staffList.value.findIndex(s => s.id === pendingStaffId.value);
    if (index !== -1) {
        staffList.value[index].newRole = pendingOldRole.value;
    }
    pendingStaffId.value = null;
    pendingNewRole.value = '';
    pendingOldRole.value = '';
    pendingStaffName.value = '';
};

// Confirm and update role
const confirmUpdate = () => {
    if (isUpdating.value) return;
    isUpdating.value = true;

    router.post(
        route('man.manager.update-staff-role', pendingStaffId.value),
        { manufacturing_role: pendingNewRole.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                const index = staffList.value.findIndex(s => s.id === pendingStaffId.value);
                if (index !== -1) {
                    staffList.value[index].manufacturing_role = pendingNewRole.value;
                    staffList.value[index].newRole = pendingNewRole.value;
                }
                showToastMessage(`Role updated for ${pendingStaffName.value}`, 'success');
                closeModal();
            },
            onError: () => {
                showToastMessage('Failed to update role. Please try again.', 'error');
                closeModal();
            },
            onFinish: () => {
                isUpdating.value = false;
            }
        }
    );
};

// Handle role change from select
const onRoleChange = (staffId, newRole, oldRole, staffName) => {
    if (!newRole) return;
    openConfirmModal(staffId, newRole, oldRole, staffName);
};

// Format role for display
const formatRole = (role) => {
    if (!role) return 'Unassigned';
    return role.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

// Role options — supervisors only see their own department's roles
// (mirrors the backend canSuperviseRole enforcement)
const ALL_ROLE_OPTIONS = [
    { value: '', label: 'Select Role' },
    { value: 'knitting_yarn', label: 'Knitting Yarn' },
    { value: 'knitting_mechanic', label: 'Knitting Mechanic' },
    { value: 'dyeing_color', label: 'Dyeing Color' },
    { value: 'dyeing_fabric_softener', label: 'Dyeing Fabric Softener' },
    { value: 'dyeing_squeezer', label: 'Dyeing Squeezer' },
    { value: 'dyeing_ironing', label: 'Dyeing Ironing' },
    { value: 'dyeing_packaging', label: 'Dyeing Packaging' },
    { value: 'dyeing_lab_chemist', label: 'Dyeing Lab Chemist' },
    { value: 'maintenance_checker', label: 'Maintenance Checker' },
    { value: 'pollution_control_operator', label: 'Pollution Control Operator' },
    { value: 'safety_officer', label: 'Safety Officer' },
    { value: 'boiler_operator', label: 'Boiler Operator' },
    { value: 'checker_quality', label: 'Checker Quality' },
];
const roleOptions = computed(() => {
    if (!props.department || !DEPT_ROLES[props.department]) return ALL_ROLE_OPTIONS;
    const allowed = new Set(DEPT_ROLES[props.department]);
    return ALL_ROLE_OPTIONS.filter(o => o.value === '' || allowed.has(o.value));
});

// Get role icon
const getRoleIcon = (role) => {
    if (!role) return Briefcase;
    if (role.includes('knitting')) return Sparkles;
    if (role.includes('dyeing')) return Palette;
    if (role.includes('maintenance')) return Wrench;
    if (role.includes('boiler')) return Zap;
    if (role.includes('pollution')) return Activity;
    if (role.includes('safety')) return ShieldCheck;
    if (role.includes('checker')) return CheckCircle2;
    return Briefcase;
};

// Avatar color
const getAvatarColor = (name) => {
    const colors = ['from-blue-600 via-indigo-600 to-violet-700', 'from-emerald-500 via-teal-600 to-cyan-700', 'from-rose-500 via-pink-600 to-fuchsia-700', 'from-amber-500 via-orange-600 to-rose-600', 'from-violet-500 via-purple-600 to-indigo-700'];
    const index = name.charCodeAt(0) % colors.length;
    return colors[index];
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Manufacturing Management" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">
                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <Factory class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> MAN · {{ deptLabel ?? 'Management' }}
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">{{ deptLabel ? `${deptLabel} Supervisor Dashboard` : 'Manufacturing Dashboard' }}</h1>
                            <span v-if="!canEditProduction" class="mt-2 inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-amber-800">View only</span>
                            <p class="text-sm text-blue-100/90">{{ getGreeting() }}, oversee {{ deptLabel ? `your ${deptLabel.toLowerCase()} production and staff` : 'production and staff' }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                <Calendar class="w-3.5 h-3.5" /> {{ new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Department overview (supervisor's own department) -->
                <div v-if="deptStatEntries.length" class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-5" style="animation-delay: 60ms">
                    <h2 class="text-sm font-black text-gray-900 dark:text-white tracking-tight mb-3">{{ deptLabel }} Overview</h2>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 text-center">
                        <div v-for="entry in deptStatEntries" :key="entry.key" class="rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/30 p-3">
                            <p class="text-2xl font-black text-indigo-700 dark:text-indigo-300">{{ entry.value }}</p>
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">{{ entry.label }}</p>
                        </div>
                    </div>
                </div>

                <!-- Shift handover + VP bulletins -->
                <div v-if="department" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                            <h2 class="text-sm font-black text-gray-900 dark:text-white">Shift Handover — {{ deptLabel }}</h2>
                            <button v-if="canEditProduction" @click="showHandoverForm = !showHandoverForm" class="ml-auto text-[11px] font-black text-indigo-700 hover:underline">+ Log handover</button>
                        </div>
                        <Transition name="modal">
                            <form v-if="showHandoverForm" @submit.prevent="submitHandover" class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-5 border-b border-gray-100 dark:border-zinc-800">
                                <div><label class="block text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1">Shift Date *</label>
                                    <input v-model="handoverForm.shift_date" type="date" required class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                                <div><label class="block text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1">Shift</label>
                                    <input v-model="handoverForm.shift_type" placeholder="Morning / Afternoon / Night" class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                                <div class="sm:col-span-2"><label class="block text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1">Unfinished Work</label>
                                    <textarea v-model="handoverForm.unfinished_work" rows="2" class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea></div>
                                <div class="sm:col-span-2"><label class="block text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1">Machine Notes</label>
                                    <textarea v-model="handoverForm.machine_notes" rows="2" class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea></div>
                                <div><label class="block text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1">Hot Jobs</label>
                                    <input v-model="handoverForm.hot_jobs" class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                                <div><label class="block text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1">Safety Notes</label>
                                    <input v-model="handoverForm.safety_notes" class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                                <div class="sm:col-span-2">
                                    <button v-if="canEditProduction" type="submit" :disabled="handoverForm.processing || !canEditProduction" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-black uppercase text-white hover:bg-indigo-700 active:scale-95 transition disabled:opacity-50">Save Handover</button>
                                </div>
                            </form>
                        </Transition>
                        <ul class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <li v-for="h in recentHandovers" :key="h.id" class="px-6 py-3 text-sm">
                                <p class="font-bold">{{ new Date(h.shift_date).toLocaleDateString() }}{{ h.shift_type ? ` · ${h.shift_type}` : '' }} <span class="font-medium text-gray-400">· {{ h.author?.name }}</span></p>
                                <p v-if="h.unfinished_work" class="text-xs text-gray-500 mt-0.5">Unfinished: {{ h.unfinished_work }}</p>
                                <p v-if="h.hot_jobs" class="text-xs text-gray-500">Hot: {{ h.hot_jobs }}</p>
                            </li>
                            <li v-if="!recentHandovers?.length" class="px-6 py-6 text-center text-xs text-gray-400">No handovers logged yet for this department.</li>
                        </ul>
                    </div>
                    <div class="bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                            <h2 class="text-sm font-black text-gray-900 dark:text-white">VP Bulletins</h2>
                        </div>
                        <ul class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <li v-for="b in activeBulletins" :key="b.id" class="px-6 py-3 text-sm">
                                <p class="font-bold">{{ b.title }} <span class="ml-1 px-1.5 py-0.5 rounded-full text-[9px] font-black uppercase ring-1 bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30">{{ b.priority }}</span></p>
                                <p class="text-xs text-gray-500 mt-0.5 whitespace-pre-line">{{ b.body }}</p>
                            </li>
                            <li v-if="!activeBulletins?.length" class="px-6 py-6 text-center text-xs text-gray-400">No active bulletins from the VP office.</li>
                        </ul>
                    </div>
                </div>

                <!-- Stats Cards -->                <TransitionGroup name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="group relative overflow-hidden bg-white/80 dark:bg-zinc-900/80 backdrop-blur-sm p-5 rounded-3xl shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 border border-gray-100 dark:border-zinc-800 hover:scale-[1.01] transition-all duration-300">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="relative flex items-start justify-between">
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.15em]">Received Orders</p>
                                <p class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white mt-1">{{ stats.receivedOrders }}</p>
                                <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 flex items-center gap-1 font-bold"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /><TrendingUp class="w-3 h-3" /> +12%</p>
                            </div>
                            <div class="p-3 rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Package class="w-5 h-5" />
                            </div>
                        </div>
                    </div>
                    <div class="group relative overflow-hidden bg-white/80 dark:bg-zinc-900/80 backdrop-blur-sm p-5 rounded-3xl shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 border border-gray-100 dark:border-zinc-800 hover:scale-[1.01] transition-all duration-300">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="relative flex items-start justify-between">
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.15em]">In Production</p>
                                <p class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white mt-1">{{ stats.inProduction }}</p>
                                <p class="text-xs text-amber-600 dark:text-amber-400 mt-1 flex items-center gap-1 font-bold"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />Active orders</p>
                            </div>
                            <div class="p-3 rounded-2xl bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Factory class="w-5 h-5" />
                            </div>
                        </div>
                    </div>
                    <div class="group relative overflow-hidden bg-white/80 dark:bg-zinc-900/80 backdrop-blur-sm p-5 rounded-3xl shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 border border-gray-100 dark:border-zinc-800 hover:scale-[1.01] transition-all duration-300 sm:col-span-2 lg:col-span-1">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="relative flex items-start justify-between">
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.15em]">Active Machines</p>
                                <p class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white mt-1">{{ stats.activeMachines }}</p>
                                <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 flex items-center gap-1 font-bold"><CheckCircle2 class="w-3 h-3" /> Operational</p>
                            </div>
                            <div class="p-3 rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <TrendingUp class="w-5 h-5" />
                            </div>
                        </div>
                    </div>
                </TransitionGroup>

                <!-- Quick Actions -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Link :href="route('man.manager.production')"
                        class="group relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 hover:shadow-2xl hover:shadow-indigo-500/25 hover:-translate-y-1 text-white px-6 py-4 font-black text-sm shadow-xl shadow-indigo-500/20 transition-all duration-300 flex items-center justify-center gap-2">
                        <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                        <Factory class="w-5 h-5 group-hover:rotate-12 transition-transform relative" />
                        <span class="relative">View Production Orders</span>
                        <ArrowRight class="w-4 h-4 group-hover:translate-x-1 transition-transform relative" />
                    </Link>
                    <Link :href="route('man.manager.rejected')"
                        class="group relative overflow-hidden rounded-3xl bg-white/80 dark:bg-zinc-900/80 backdrop-blur border border-gray-100 dark:border-zinc-800 hover:shadow-2xl hover:shadow-rose-500/15 hover:-translate-y-1 hover:border-rose-200 dark:hover:border-rose-800 text-gray-900 dark:text-white px-6 py-4 font-black text-sm shadow-sm transition-all duration-300 flex items-center justify-center gap-2">
                        <X class="w-5 h-5 text-rose-500 group-hover:rotate-90 transition-transform" />
                        View Rejected Items
                        <ArrowRight class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                    </Link>
                </div>

                <!-- Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Production Trend -->
                    <div class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl p-5 shadow-sm border border-gray-100 dark:border-zinc-800 hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 transition-all duration-300 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="relative flex items-center gap-2 mb-4">
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow"><BarChart3 class="w-4 h-4" /></span>
                            <h3 class="text-sm font-black tracking-tight text-gray-700 dark:text-gray-200">Production Output Trend</h3>
                        </div>
                        <div class="relative h-48 flex items-end gap-2">
                            <div v-for="(value, idx) in productionTrend.values" :key="idx" class="flex-1 flex flex-col items-center">
                                <div class="w-full bg-gradient-to-t from-blue-600 via-indigo-600 to-violet-600 rounded-t-xl transition-all duration-500" :style="{ height: `${(value / maxTrendValue) * 100}%`, minHeight: '4px' }"></div>
                                <span class="text-[10px] sm:text-xs mt-2 text-gray-500">{{ productionTrend.months[idx] }}</span>
                                <span class="text-[9px] font-black text-gray-600 dark:text-gray-400">{{ value }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Machine Utilization -->
                    <div class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl p-5 shadow-sm border border-gray-100 dark:border-zinc-800 hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 transition-all duration-300 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="relative flex items-center gap-2 mb-4">
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 text-white shadow"><PieChart class="w-4 h-4" /></span>
                            <h3 class="text-sm font-black tracking-tight text-gray-700 dark:text-gray-200">Machine Status</h3>
                        </div>
                        <div class="relative space-y-3">
                            <div class="flex items-center gap-3">
                                <span class="w-20 text-xs font-black uppercase text-gray-500 dark:text-gray-400 flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" />Available</span>
                                <div class="flex-1 h-6 bg-gray-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 rounded-full transition-all duration-700" :style="{ width: `${(machineUtilization.available / totalMachines) * 100}%` }"></div>
                                </div>
                                <span class="text-sm font-black">{{ machineUtilization.available }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="w-20 text-xs font-black uppercase text-gray-500 dark:text-gray-400 flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse" />Repair</span>
                                <div class="flex-1 h-6 bg-gray-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-amber-500 to-orange-500 rounded-full transition-all duration-700" :style="{ width: `${(machineUtilization.maintenance / totalMachines) * 100}%` }"></div>
                                </div>
                                <span class="text-sm font-black">{{ machineUtilization.maintenance }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="w-20 text-xs font-black uppercase text-gray-500 dark:text-gray-400 flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-rose-500 animate-pulse" />Retired</span>
                                <div class="flex-1 h-6 bg-gray-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-rose-500 to-pink-500 rounded-full transition-all duration-700" :style="{ width: `${(machineUtilization.retired / totalMachines) * 100}%` }"></div>
                                </div>
                                <span class="text-sm font-black">{{ machineUtilization.retired }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity & Staff Management -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Recent Activity -->
                    <div class="group relative lg:col-span-1 bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl p-5 shadow-sm border border-gray-100 dark:border-zinc-800 hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="relative flex items-center gap-2 mb-4">
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 via-purple-600 to-fuchsia-700 text-white shadow"><Activity class="w-4 h-4" /></span>
                            <h3 class="text-sm font-black tracking-tight text-gray-700 dark:text-gray-200">Recent Activity</h3>
                        </div>
                        <div class="relative space-y-3">
                            <div class="flex items-start gap-3">
                                <div class="p-1.5 bg-blue-50 dark:bg-blue-900/20 rounded-lg"><Factory class="w-3 h-3 text-blue-600" /></div>
                                <div><p class="text-sm text-gray-700 dark:text-gray-300">Order #PO-2026-045 sent to production</p><p class="text-xs text-gray-400">2 hours ago</p></div>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="p-1.5 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg"><Users class="w-3 h-3 text-emerald-600" /></div>
                                <div><p class="text-sm text-gray-700 dark:text-gray-300">Staff role updated: John → Dyeing Color</p><p class="text-xs text-gray-400">Yesterday</p></div>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="p-1.5 bg-amber-50 dark:bg-amber-900/20 rounded-lg"><AlertCircle class="w-3 h-3 text-amber-600" /></div>
                                <div><p class="text-sm text-gray-700 dark:text-gray-300">Low yarn stock alert for Polyester</p><p class="text-xs text-gray-400">2 days ago</p></div>
                            </div>
                        </div>
                    </div>

                    <!-- Staff Management Section -->
                    <div class="lg:col-span-2 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-sm rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300">
                        <div class="px-4 md:px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg"><Users class="w-4 h-4" /></span>
                                    <h2 class="text-sm font-black tracking-tight text-gray-900 dark:text-white">{{ deptLabel ? `${deptLabel} Staff` : 'Manufacturing Staff' }}</h2>
                                    <span class="px-2 py-1 text-[11px] font-black bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 rounded-full">{{ filteredStaff.length }} members</span>
                                </div>
                                <div class="relative w-full sm:w-64">
                                    <Search class="absolute left-4 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                                    <input v-model="searchQuery" type="text" placeholder="Search by name or role..." class="w-full rounded-2xl border-0 bg-gray-100 dark:bg-zinc-800 py-2.5 pl-11 pr-9 text-sm font-medium text-gray-900 dark:text-white placeholder:text-gray-400 focus:ring-2 focus:ring-indigo-500 outline-none transition" />
                                    <button v-if="searchQuery" @click="clearSearch" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"><X class="w-4 h-4" /></button>
                                </div>
                            </div>
                        </div>

                        <!-- Mobile Card View -->
                        <TransitionGroup name="card" tag="div" class="block md:hidden divide-y divide-gray-100 dark:divide-zinc-800">
                            <div v-for="(user, i) in filteredStaff" :key="user.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }" class="p-4 hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-all">
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0">
                                        <div :class="['w-10 h-10 bg-gradient-to-br rounded-2xl flex items-center justify-center text-white font-black text-sm shadow-lg', getAvatarColor(user.name)]">{{ user.name.charAt(0).toUpperCase() }}</div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-sm font-black text-gray-900 dark:text-white tracking-tight">{{ user.name }}</h3>
                                        <p class="text-[11px] text-indigo-600 dark:text-indigo-400 mt-0.5 capitalize flex items-center gap-1 font-bold">
                                            <component :is="getRoleIcon(user.manufacturing_role)" class="w-3 h-3" /> {{ formatRole(user.manufacturing_role) }}
                                        </p>
                                        <p v-if="user.is_manufacturing_supervisor" class="text-xs mt-1 text-purple-600 dark:text-purple-400 font-bold flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />Supervisor</p>
                                        <div class="mt-3">
                                            <select v-if="canEditProduction" v-model="user.newRole" @change="onRoleChange(user.id, user.newRole, user.manufacturing_role, user.name)" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 p-2.5 text-sm bg-white dark:bg-zinc-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                                                <option v-for="option in roleOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                            </select>
                                            <p v-else class="text-xs font-bold text-gray-500 dark:text-gray-400">Role changes disabled — view only</p>
                                        </div>
                                    </div>
                                    <ChevronRight class="w-4 h-4 text-gray-400 flex-shrink-0 mt-2" />
                                </div>
                            </div>
                        </TransitionGroup>

                        <!-- Desktop Table View -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 text-left text-[10px] font-black text-gray-400 uppercase tracking-wider">Staff Member</th>
                                        <th class="px-6 py-3 text-left text-[10px] font-black text-gray-400 uppercase tracking-wider">Current Role</th>
                                        <th class="px-6 py-3 text-left text-[10px] font-black text-gray-400 uppercase tracking-wider">Supervisor</th>
                                        <th class="px-6 py-3 text-left text-[10px] font-black text-gray-400 uppercase tracking-wider">Update Role</th>
                                    </tr>
                                </thead>
                                <TransitionGroup name="card" tag="tbody" class="divide-y divide-gray-100 dark:divide-zinc-800">
                                    <tr v-for="(user, i) in filteredStaff" :key="user.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-all">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div :class="['w-9 h-9 bg-gradient-to-br rounded-xl flex items-center justify-center text-white font-black text-xs shadow-lg', getAvatarColor(user.name)]">{{ user.name.charAt(0).toUpperCase() }}</div>
                                                <span class="font-bold text-sm text-gray-900 dark:text-white">{{ user.name }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-1">
                                                <component :is="getRoleIcon(user.manufacturing_role)" class="w-4 h-4 text-indigo-500 dark:text-indigo-400" />
                                                <span class="px-2.5 py-1 text-[10px] font-black uppercase rounded-full bg-gray-100 dark:bg-zinc-800 text-gray-700 dark:text-gray-300 capitalize">{{ formatRole(user.manufacturing_role) }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span v-if="user.is_manufacturing_supervisor" class="px-2.5 py-1 text-[10px] font-black uppercase rounded-full bg-purple-100 text-purple-700 ring-purple-200 dark:bg-purple-500/15 dark:text-purple-300 dark:ring-purple-500/30 ring-1 inline-flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />Yes</span>
                                            <span v-else class="text-gray-400 text-xs">No</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <select v-if="canEditProduction" v-model="user.newRole" @change="onRoleChange(user.id, user.newRole, user.manufacturing_role, user.name)" class="rounded-2xl border-gray-200 dark:border-zinc-700 px-3 py-2 text-sm bg-white dark:bg-zinc-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none w-48">
                                                <option v-for="option in roleOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                            </select>
                                            <span v-else class="text-xs text-gray-400">—</span>
                                        </td>
                                    </tr>
                                </TransitionGroup>
                            </table>
                        </div>

                        <div v-if="filteredStaff.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                            <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                                <Users class="h-9 w-9 text-indigo-400" />
                            </div>
                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No staff members found</p>
                            <button @click="clearSearch" class="mt-4 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition active:scale-95">Clear search</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <Transition name="modal">
            <div v-if="showConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="closeModal">
                <div class="bg-white/95 dark:bg-zinc-900/95 backdrop-blur rounded-3xl shadow-2xl max-w-md w-full overflow-hidden border border-gray-100 dark:border-zinc-800">
                    <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6">
                        <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                        <div class="relative flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30"><UserCheck class="w-6 h-6 text-white" /></div>
                            <h3 class="text-lg font-black text-white tracking-tight">Confirm Role Change</h3>
                        </div>
                    </div>
                    <div class="p-6">
                        <p class="text-sm text-gray-600 dark:text-gray-300 mb-6">
                            Are you sure you want to change the role of <span class="font-bold">{{ pendingStaffName }}</span> from
                            <span class="font-bold text-gray-900 dark:text-white">{{ formatRole(pendingOldRole) }}</span> to
                            <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ formatRole(pendingNewRole) }}</span>?
                        </p>
                        <div class="flex justify-end gap-3">
                            <button @click="closeModal" :disabled="isUpdating" class="px-4 py-2.5 text-xs font-black uppercase tracking-wide text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-zinc-800 rounded-2xl hover:bg-gray-200 dark:hover:bg-zinc-700 transition active:scale-95 disabled:opacity-50">Cancel</button>
                            <button v-if="canEditProduction" @click="confirmUpdate" :disabled="isUpdating" class="px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white bg-indigo-600 rounded-2xl hover:bg-indigo-700 shadow-lg shadow-indigo-500/25 transition active:scale-95 flex items-center gap-2 disabled:opacity-50">
                                <RefreshCw v-if="isUpdating" class="w-4 h-4 animate-spin" /> Confirm Change
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Toast Notification -->
        <div v-if="showToast" class="fixed bottom-4 right-4 z-50 transform transition-all duration-300 rounded-2xl shadow-2xl border" :class="{ 'bg-emerald-50 dark:bg-emerald-900/80 border-emerald-200 dark:border-emerald-700': toastType === 'success', 'bg-rose-50 dark:bg-rose-900/80 border-rose-200 dark:border-rose-700': toastType === 'error' }" style="animation: slide-up 0.3s ease-out;">
            <div class="flex items-center gap-3 px-4 py-3">
                <component :is="toastType === 'success' ? CheckCircle2 : AlertCircle" class="w-5 h-5" :class="toastType === 'success' ? 'text-emerald-600' : 'text-rose-600'" />
                <span class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ toastMessage }}</span>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
* { scroll-behavior: smooth; }
::-webkit-scrollbar { width: 8px; height: 8px; }
::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
.dark ::-webkit-scrollbar-track { background: #1f1f1f; }
.dark ::-webkit-scrollbar-thumb { background: #3f3f46; }
.dark ::-webkit-scrollbar-thumb:hover { background: #52525b; }
@keyframes slide-up {
    from { transform: translateY(1rem); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
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
