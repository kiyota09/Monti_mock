<script setup>
import { ref, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Users, UserCheck, UserX, Calendar, Clock, Search, Filter,
    Briefcase, Building2, ChevronDown, ChevronRight
} from 'lucide-vue-next';

const props = defineProps({
    employees: {
        type: Array,
        default: () => []
    },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            present: 0,
            absent: 0,
            on_leave: 0
        })
    }
});

// Filters
const searchQuery = ref('');
const selectedModule = ref('ALL');
const selectedDepartment = ref('ALL');

// Extract unique modules and departments from employees
const modules = computed(() => {
    const mods = ['ALL', ...new Set(props.employees.map(e => e.role))];
    return mods;
});

const departments = computed(() => {
    const deps = ['ALL', ...new Set(props.employees.map(e => e.department).filter(Boolean))];
    return deps;
});

// Filtered employees
const filteredEmployees = computed(() => {
    let list = props.employees;
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(e =>
            e.name.toLowerCase().includes(q) ||
            e.role.toLowerCase().includes(q) ||
            e.department.toLowerCase().includes(q)
        );
    }
    if (selectedModule.value !== 'ALL') {
        list = list.filter(e => e.role === selectedModule.value);
    }
    if (selectedDepartment.value !== 'ALL') {
        list = list.filter(e => e.department === selectedDepartment.value);
    }
    return list;
});

// Helper functions
const getInitials = (name) => name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
const getStatusBadgeClass = (status) => {
    switch (status) {
        case 'On-Time': return 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30';
        case 'Late': return 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
        case 'Absent': return 'bg-red-100 text-red-700 ring-red-200 dark:bg-red-500/15 dark:text-red-300 dark:ring-red-500/30';
        default: return 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
    }
};
const getShiftBadgeClass = (shift) => {
    switch (shift) {
        case 'Morning': return 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
        case 'Afternoon': return 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30';
        case 'Graveyard': return 'bg-indigo-100 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30';
        default: return 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
    }
};
</script>

<template>

    <Head title="Workforce Dashboard" />

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
                            <Users class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Briefcase class="h-3.5 w-3.5" /> Workforce · Overview
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Workforce Dashboard</h1>
                            <p class="text-sm text-blue-100/90">Real-time employee attendance and shift overview · {{ filteredEmployees.length }} showing</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ stats.total }} total
                            </span>
                            <span class="rounded-full bg-emerald-400/90 px-3 py-1.5 text-xs font-black text-emerald-950">{{ stats.present }} present</span>
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
                            <select v-model="selectedModule"
                                class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide bg-white/15 text-white backdrop-blur ring-1 ring-white/25 hover:bg-white/25 transition-all outline-none [&>option]:text-gray-900">
                                <option value="ALL">All Modules</option>
                                <option v-for="mod in modules.slice(1)" :key="mod" :value="mod">{{ mod }}</option>
                            </select>
                            <select v-model="selectedDepartment"
                                class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide bg-white/15 text-white backdrop-blur ring-1 ring-white/25 hover:bg-white/25 transition-all outline-none [&>option]:text-gray-900">
                                <option value="ALL">All Departments</option>
                                <option v-for="dept in departments.slice(1)" :key="dept" :value="dept">{{ dept }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <TransitionGroup name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div
                        v-for="(card, i) in [
                            { label: 'Total Employees', value: stats.total, icon: Users, chip: 'bg-blue-50 dark:bg-blue-900/20', iconClass: 'text-blue-500', bar: 'from-blue-500 to-indigo-500' },
                            { label: 'Present Today', value: stats.present, icon: UserCheck, chip: 'bg-emerald-50 dark:bg-emerald-900/20', iconClass: 'text-emerald-500', bar: 'from-emerald-500 to-teal-500' },
                            { label: 'Absent Today', value: stats.absent, icon: UserX, chip: 'bg-red-50 dark:bg-red-900/20', iconClass: 'text-red-500', bar: 'from-rose-500 to-red-500' },
                            { label: 'On Leave', value: stats.on_leave, icon: Calendar, chip: 'bg-violet-50 dark:bg-violet-900/20', iconClass: 'text-violet-500', bar: 'from-violet-500 to-fuchsia-500' },
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

                <!-- Employees Table -->
                <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden" style="animation-delay: 120ms">
                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                    <div class="flex items-center gap-2 px-6 pt-5 pb-1">
                        <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Live roster · {{ filteredEmployees.length }} employees</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                        Employee</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                        Role</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                        Department</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                        Shift</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                        Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                                <tr v-if="filteredEmployees.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center justify-center text-center">
                                            <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                                                <Users class="h-9 w-9 text-indigo-400" />
                                            </div>
                                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No employees match the current filters</p>
                                            <p class="text-xs text-gray-400 mt-1">Try a different search or filter.</p>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-for="emp in filteredEmployees" :key="emp.id"
                                    class="hover:bg-indigo-50/40 dark:hover:bg-indigo-950/20 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="h-10 w-10 rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 flex items-center justify-center font-black text-xs text-white shadow-lg uppercase flex-shrink-0">
                                                {{ getInitials(emp.name) }}
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold text-slate-900 dark:text-white">{{ emp.name }}
                                                </p>
                                                <p class="text-xs text-slate-400">{{ emp.id }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-slate-700 dark:text-slate-300">{{ emp.role
                                        }}</td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="px-2 py-1 rounded-md text-[10px] font-black uppercase bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-gray-300">
                                            {{ emp.department }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            :class="['px-2 py-1 rounded-md text-[10px] font-black uppercase ring-1', getShiftBadgeClass(emp.shift)]">
                                            {{ emp.shift }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            :class="['px-3 py-1 rounded-full text-[10px] font-black uppercase ring-1 inline-flex items-center gap-1', getStatusBadgeClass(emp.status)]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ emp.status }}
                                        </span>
                                        <span v-if="emp.is_on_leave"
                                            class="ml-2 px-2 py-1 rounded-full text-[9px] font-black uppercase bg-purple-100 text-purple-700 ring-1 ring-purple-200 dark:bg-purple-500/15 dark:text-purple-300 dark:ring-purple-500/30">
                                            On Leave
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
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
/* Optional custom scrollbar */
.overflow-x-auto::-webkit-scrollbar {
    height: 6px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: transparent;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}

.dark .overflow-x-auto::-webkit-scrollbar-thumb {
    background: #334155;
}
</style>
