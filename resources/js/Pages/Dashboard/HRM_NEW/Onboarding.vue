<template>
    <AuthenticatedLayout>
        <!-- START SCREEN LOADING OVERLAY -->
        <Transition name="fade">
            <div
                v-if="pageLoading"
                class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-100/50 backdrop-blur-md"
                aria-live="polite"
            >
                <div class="flex flex-col items-center gap-3">
                    <div class="w-10 h-10 rounded-full border-4 border-slate-300 border-t-blue-600 animate-spin"></div>
                    <p class="text-sm font-semibold text-slate-600">Loading onboarding records...</p>
                </div>
            </div>
        </Transition>

        <div class="flex min-h-screen bg-gradient-to-br from-slate-100 via-slate-50 to-blue-50">
            <div class="flex-1 p-8 text-slate-900 font-sans min-w-0 relative flex flex-col">
                <!-- Subtle Background Pattern -->
                <div class="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,transparent,black,transparent)] pointer-events-none"></div>

                <!-- Professional Header -->
                <header class="mb-6 relative shrink-0">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div>
                            <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
                                <span class="hover:text-slate-700 cursor-pointer transition-colors">Dashboard</span>
                                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="text-slate-800 font-semibold">Employee Lifecycle</span>
                                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="text-blue-600 font-semibold">Onboarding</span>
                            </nav>
                            <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">Onboarding</h1>
                            <p class="text-sm text-slate-500 mt-0.5">Manage the integration of newly hired employees. Track documents, tasks, and approvals for a seamless start.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link
                                :href="route('hrm.onboarding.templates.index')"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-semibold transition-all duration-200 shadow-sm hover:shadow-md"
                            >
                                <LayoutTemplate class="w-3.5 h-3.5" /> Templates
                                <span class="text-[9px] font-bold text-slate-400 bg-slate-50 border border-slate-200 px-1.5 py-0.5 rounded">{{ templateCount }}</span>
                            </Link>
                        </div>
                    </div>
                </header>

                <!-- KPI METRICS ROW -->
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 mb-4 shrink-0">
                    <div
                        v-for="metric in metricsData"
                        :key="metric.label"
                        class="group bg-white rounded-xl border border-slate-200/60 p-3 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-blue-200/60 shadow-md backdrop-blur-sm cursor-pointer"
                        @click="applyFilter({ status: metric.filter })"
                    >
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-[9px] font-semibold text-slate-500 uppercase tracking-wider">{{ metric.label }}</span>
                            <div :class="['w-7 h-7 rounded-lg flex items-center justify-center shadow-inner', metric.bg]">
                                <component :is="metric.icon" class="w-3.5 h-3.5" :class="metric.color" />
                            </div>
                        </div>
                        <div class="text-lg font-bold" :class="metric.color">{{ metric.value }}</div>
                    </div>
                </div>

                <!-- FILTER BAR -->
                <div class="bg-white rounded-xl border border-slate-200/60 shadow-md backdrop-blur-sm p-4 mb-4 shrink-0">
                    <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                        <!-- Search -->
                        <div class="flex-1">
                            <div class="relative">
                                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                                </svg>
                                <input
                                    v-model="search"
                                    type="text"
                                    placeholder="Search by name, email, position..."
                                    class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200"
                                />
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <select
                            v-model="filterStatus"
                            @change="applyFilter({ status: filterStatus })"
                            class="px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200"
                        >
                            <option value="">All Statuses</option>
                            <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
                        </select>

                        <!-- Department Filter -->
                        <select
                            v-model="filterDepartment"
                            @change="applyFilter({ department: filterDepartment })"
                            class="px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200"
                        >
                            <option value="">All Departments</option>
                            <option v-for="d in departmentOptions" :key="d" :value="d">{{ d }}</option>
                        </select>
                    </div>
                </div>

                <!-- ONBOARDING TABLE -->
                <div class="bg-white rounded-xl border border-slate-200/60 shadow-md backdrop-blur-sm overflow-hidden relative">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50/80 border-b border-slate-200">
                                <tr>
                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">New Hire</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Position / Department</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Start Date</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Assigned Manager</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Progress</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-right text-[10px] font-bold text-slate-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="onboard in onboardingList"
                                    :key="onboard.id"
                                    class="hover:bg-blue-50/40 transition-colors cursor-pointer group"
                                    @click="openDetail(onboard)"
                                >
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <div :class="['w-9 h-9 rounded-full flex items-center justify-center text-white font-bold text-xs shadow-lg', statusAvatarColor(onboard.status)]">
                                                {{ onboard.initials }}
                                            </div>
                                            <div>
                                                <div class="text-xs font-bold text-slate-800 group-hover:text-blue-700 transition-colors">{{ onboard.name }}</div>
                                                <div class="text-[10px] text-slate-400">{{ onboard.email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-xs font-medium text-slate-700">{{ onboard.position || 'N/A' }}</div>
                                        <div class="text-[10px] text-slate-400">{{ onboard.department || 'N/A' }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-xs font-medium text-slate-700">{{ formatDate(onboard.start_date) }}</div>
                                        <div class="text-[10px] text-slate-400">Applied {{ formatDate(onboard.created_at) }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-xs text-slate-600">{{ onboard.assigned_manager || 'Unassigned' }}</div>
                                    </td>
                                    <td class="px-4 py-3 w-40">
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden shadow-inner">
                                                <div
                                                    class="h-full bg-gradient-to-r from-blue-500 to-emerald-500 rounded-full transition-all duration-500"
                                                    :style="{ width: onboard.progress + '%' }"
                                                ></div>
                                            </div>
                                            <span class="text-[10px] font-bold text-slate-600 w-9 text-right">{{ onboard.progress }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span :class="['inline-flex px-2.5 py-1 rounded-full text-[10px] font-semibold border shadow-sm', statusBadge(onboard.status)]">
                                            {{ onboard.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            @click.stop="openDetail(onboard)"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-lg text-[10px] font-bold transition-all duration-200 shadow-md hover:shadow-lg"
                                        >
                                            Open
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State -->
                    <div v-if="onboardingList.length === 0" class="text-center py-16">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center mx-auto mb-4 shadow-inner">
                            <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-slate-900 mb-1">No Onboarding Records Found</h3>
                        <p class="text-xs text-slate-500">Candidates will appear here once they are advanced to onboarding.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { Users, RefreshCw, Hourglass, CheckCircle2, Rocket, LayoutTemplate } from 'lucide-vue-next';

const props = defineProps({
    onboardings: { type: [Array, Object], default: () => [] },
    pagination: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({ all: 0 }) },
    filters: { type: Object, default: () => ({ search: '', status: '', department: '' }) },
    departments: { type: [Array, Object], default: () => [] },
    templateCount: { type: Number, default: 0 },
});

// Accept both plain arrays (new controller) and legacy Laravel paginators
// ({data:[...]}). Filter out nulls so :key="onboard.id" never crashes.
const onboardingList = computed(() => {
    const raw = Array.isArray(props.onboardings) ? props.onboardings : (props.onboardings?.data ?? []);
    return (raw ?? []).filter((o) => o && typeof o === 'object' && o.id != null);
});

// Departments may arrive as [{id,name}] or plain strings — normalize to strings.
const departmentOptions = computed(() => {
    const raw = Array.isArray(props.departments) ? props.departments : (props.departments?.data ?? []);
    return (raw ?? []).map((d) => (typeof d === 'string' ? d : (d?.name ?? String(d?.id ?? '')))).filter(Boolean);
});

const page = usePage();
const pageLoading = ref(true);
const search = ref(props.filters.search || '');
const filterStatus = ref(props.filters.status || '');
const filterDepartment = ref(props.filters.department || '');

// Toast flash messages
watch(() => page.props.flash, (flash) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
}, { immediate: true });

onMounted(() => {
    setTimeout(() => { pageLoading.value = false; }, 200);
});

const statusOptions = ['In Progress', 'Pending Requirements', 'Ready to Hire', 'Completed'];

const metricsData = computed(() => [
    { label: 'All', value: props.stats.all, icon: Users, filter: '', bg: 'bg-slate-100', color: 'text-slate-600' },
    { label: 'In Progress', value: props.stats['In Progress'] || 0, icon: RefreshCw, filter: 'In Progress', bg: 'bg-blue-50', color: 'text-blue-600' },
    { label: 'Pending Requirements', value: props.stats['Pending Requirements'] || 0, icon: Hourglass, filter: 'Pending Requirements', bg: 'bg-amber-50', color: 'text-amber-600' },
    { label: 'Ready to Hire', value: props.stats['Ready to Hire'] || 0, icon: Rocket, filter: 'Ready to Hire', bg: 'bg-violet-50', color: 'text-violet-600' },
    { label: 'Completed', value: props.stats['Completed'] || 0, icon: CheckCircle2, filter: 'Completed', bg: 'bg-emerald-50', color: 'text-emerald-600' },
]);

const applyFilter = (overrides = {}) => {
    const params = {
        search: search.value.trim(),
        status: overrides.status !== undefined ? overrides.status : filterStatus.value,
        department: overrides.department !== undefined ? overrides.department : filterDepartment.value,
    };
    router.get(route('hrm.onboarding.status.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const debouncedSearch = (() => {
    let timer = null;
    return () => {
        clearTimeout(timer);
        timer = setTimeout(() => applyFilter(), 400);
    };
})();
watch(search, () => debouncedSearch());

const openDetail = (onboard) => {
    if (!onboard?.id) return;
    router.get(route('hrm.onboarding.show', onboard.id));
};

const formatDate = (value) => {
    if (!value) return '—';
    const d = new Date(value);
    if (isNaN(d) ) return '—';
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};

const statusAvatarColor = (s) =>
    ({
        'In Progress': 'bg-gradient-to-br from-blue-500 to-blue-600',
        'Pending Requirements': 'bg-gradient-to-br from-amber-400 to-amber-500',
        'Ready to Hire': 'bg-gradient-to-br from-violet-500 to-violet-600',
        Completed: 'bg-gradient-to-br from-emerald-500 to-emerald-600',
    })[s] || 'bg-gradient-to-br from-slate-400 to-slate-500';

const statusBadge = (s) =>
    ({
        'In Progress': 'bg-blue-50 text-blue-700 border-blue-200',
        'Pending Requirements': 'bg-amber-50 text-amber-700 border-amber-200',
        'Ready to Hire': 'bg-violet-50 text-violet-700 border-violet-200',
        Completed: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    })[s] || 'bg-slate-50 text-slate-500 border-slate-200';
</script>

<style scoped>
.bg-grid-slate-100 {
    background-image: radial-gradient(circle, #cbd5e1 1px, transparent 1px);
    background-size: 24px 24px;
}
</style>