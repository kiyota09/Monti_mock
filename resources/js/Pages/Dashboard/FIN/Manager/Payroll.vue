<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Users, Sparkles, HandCoins, Building2, X, ChevronDown } from 'lucide-vue-next';

const props = defineProps({
    payroll: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    isDummy: { type: Boolean, default: false },
    employees: { type: Array, default: () => [] },
});

const peso = (v) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(v ?? 0));

const totalHeadcount = computed(() => props.payroll.reduce((s, p) => s + Number(p.headcount ?? 0), 0));
const totalGross = computed(() => props.payroll.reduce((s, p) => s + Number(p.gross ?? 0), 0));
const totalDeductions = computed(() => props.payroll.reduce((s, p) => s + Number(p.deductions ?? 0), 0));
const totalNet = computed(() => props.payroll.reduce((s, p) => s + Number(p.net ?? 0), 0));

const selectedDepartment = ref(null);
const selectedEmployees = computed(() => {
    if (!selectedDepartment.value) return [];
    return props.employees.filter(e => e.department === selectedDepartment.value.department);
});

const openDepartmentModal = (dept) => {
    selectedDepartment.value = dept;
};

const closeModal = () => {
    selectedDepartment.value = null;
};

const statusClass = (status) => {
    switch (status) {
        case 'paid': return 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30';
        case 'processing': return 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
        case 'pending': return 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
        default: return 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
    }
};
</script>

<template>
    <Head title="Payroll" />
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
                            <HandCoins class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> Finance · Payroll
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Department Payroll</h1>
                            <p class="text-sm text-blue-100/90">{{ payroll.length }} department{{ payroll.length !== 1 ? 's' : '' }} · {{ totalHeadcount }} employees on run</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span v-if="isDummy" class="rounded-full bg-amber-300/90 px-3 py-1.5 text-xs font-black text-amber-950">Demo data</span>
                        </div>
                    </div>

                    <!-- Headcount / gross / net pills -->
                    <div class="relative mt-6 flex flex-wrap gap-2">
                        <span class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur ring-1 ring-white/25 flex items-center gap-2">
                            <Users class="h-4 w-4" /> {{ totalHeadcount }} headcount
                        </span>
                        <span class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur ring-1 ring-white/25">
                            Gross · {{ peso(totalGross) }}
                        </span>
                        <span class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg scale-105">
                            Net · {{ peso(totalNet) }}
                        </span>
                    </div>
                </div>

                <!-- Department table -->
                <TransitionGroup name="row" tag="table" class="w-full rounded-lg overflow-hidden bg-white dark:bg-zinc-900 text-sm">
                    <thead class="bg-gray-50 dark:bg-zinc-800">
                        <tr class="text-left text-[10px] font-bold uppercase text-gray-500 dark:text-gray-300">
                            <th class="px-3 py-2">Department</th>
                            <th class="px-3 py-2">Emp</th>
                            <th class="px-3 py-2">Gross</th>
                            <th class="px-3 py-2">Deductions</th>
                            <th class="px-3 py-2">Net pay</th>
                            <th class="px-3 py-2">Avg / head</th>
                            <th class="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(p, i) in payroll" :key="p.department"
                            class="border-b border-gray-100 dark:border-zinc-800 hover:bg-gray-50 dark:hover:bg-zinc-800 cursor-pointer"
                            @click="openDepartmentModal(p)">
                            <td class="px-3 py-2 font-medium text-gray-900 dark:text-white flex items-center gap-1.5">
                                <span @click.stop="openDepartmentModal(p)" class="text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer text-sm">{{ p.department }}</span>
                                <ChevronDown class="h-3.5 w-3.5 text-gray-400" />
                            </td>
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-300 text-sm">{{ p.headcount }}</td>
                            <td class="px-3 py-2 text-gray-900 dark:text-white text-sm">{{ peso(p.gross) }}</td>
                            <td class="px-3 py-2 text-rose-500 dark:text-rose-300 text-sm">−{{ peso(p.deductions) }}</td>
                            <td class="px-3 py-2 font-black text-indigo-600 dark:text-indigo-300 text-sm">{{ peso(p.net) }}</td>
                            <td class="px-3 py-2 text-[9px] font-bold uppercase text-gray-400 dark:text-gray-300">
                                {{ peso(Math.round(Number(p.net ?? 0) / Math.max(1, Number(p.headcount ?? 1)))) }} avg / head
                            </td>
                            <td class="px-3 py-2">
                                <span :class="statusClass(p.status)"
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[8px] font-black uppercase ring-1">
                                    <span class="h-1 w-1 rounded-full bg-current animate-pulse" /> {{ p.status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </TransitionGroup>

                <!-- Department Employees Modal -->
                <Transition name="modal">
                    <div v-if="selectedDepartment" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click="closeModal">
                        <div class="absolute inset-0 bg-zinc-950/60 backdrop-blur-sm" />
                        <div class="relative w-full max-w-3xl max-h-[80vh] overflow-hidden rounded-3xl bg-white dark:bg-zinc-900 shadow-2xl flex flex-col">
                            <div class="bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-4 text-white flex items-center justify-between">
                                <div>
                                    <p class="text-[9px] font-bold uppercase tracking-[0.2em] text-blue-100">Department Employees</p>
                                    <h3 class="text-base font-black tracking-tight">{{ selectedDepartment.department }}</h3>
                                </div>
                                <button @click="closeModal" class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition active:scale-95">
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </div>
                            <div class="p-3 overflow-y-auto flex-1">
                                <div v-if="selectedEmployees.length === 0" class="py-6 text-center text-[11px] text-gray-400">No employees found for this department.</div>
                                <table v-else class="w-full text-sm">
                                    <thead class="bg-gray-50 dark:bg-zinc-800">
                                        <tr class="text-left text-[9px] font-bold uppercase text-gray-500 dark:text-gray-300">
                                            <th class="px-2.5 py-1.5">Employee</th>
                                            <th class="px-2.5 py-1.5">Position</th>
                                            <th class="px-2.5 py-1.5 text-right">Gross Pay</th>
                                            <th class="px-2.5 py-1.5 text-right">Deductions</th>
                                            <th class="px-2.5 py-1.5 text-right">Net Pay</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                        <tr v-for="emp in selectedEmployees" :key="emp.id"
                                            class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                            <td class="px-2.5 py-1.5">
                                                <div class="flex items-center gap-2">
                                                    <div class="h-7 w-7 rounded-full bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 flex items-center justify-center text-white text-xs font-black">
                                                        {{ (emp.name ?? '?').charAt(0) }}
                                                    </div>
                                                    <div>
                                                        <p class="font-medium text-gray-900 dark:text-white text-sm">{{ emp.name }}</p>
                                                        <p class="text-[9px] text-gray-400">{{ emp.employee_id ?? '—' }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-2.5 py-1.5 text-gray-500 dark:text-gray-300 text-sm">{{ emp.position ?? '—' }}</td>
                                            <td class="px-2.5 py-1.5 text-right font-medium text-gray-900 dark:text-white text-sm">{{ peso(emp.gross_pay) }}</td>
                                            <td class="px-2.5 py-1.5 text-right text-rose-500 dark:text-rose-300 text-sm">−{{ peso(emp.deductions) }}</td>
                                            <td class="px-2.5 py-1.5 text-right font-black text-indigo-600 dark:text-indigo-300 text-sm">{{ peso(emp.net_pay) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div class="mt-3 pt-3 border-t border-gray-100 dark:border-zinc-800 flex items-center justify-between flex-wrap gap-2">
                                    <span class="text-[9px] font-bold uppercase text-gray-400">Total: {{ selectedEmployees.length }} employee{{ selectedEmployees.length !== 1 ? 's' : '' }}</span>
                                    <div class="flex items-center gap-3 text-[10px] font-medium">
                                        <span class="text-gray-900 dark:text-white">Gross: {{ peso(selectedEmployees.reduce((s, e) => s + Number(e.gross_pay ?? 0), 0)) }}</span>
                                        <span class="text-rose-500">Deductions: −{{ peso(selectedEmployees.reduce((s, e) => s + Number(e.deductions ?? 0), 0)) }}</span>
                                        <span class="text-indigo-600 dark:text-indigo-300">Net: {{ peso(selectedEmployees.reduce((s, e) => s + Number(e.net_pay ?? 0), 0)) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>

                <!-- Payroll totals footer -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative grid grid-cols-2 sm:grid-cols-4 gap-4 p-6 sm:p-8">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-blue-100">Headcount</p>
                            <p class="text-2xl font-black tracking-tight">{{ totalHeadcount }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-blue-100">Gross</p>
                            <p class="text-2xl font-black tracking-tight">{{ peso(totalGross) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-blue-100">Deductions</p>
                            <p class="text-2xl font-black tracking-tight">−{{ peso(totalDeductions) }}</p>
                        </div>
                        <div class="rounded-2xl bg-white/15 ring-1 ring-white/25 backdrop-blur p-3">
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-blue-100">Net payout</p>
                            <p class="text-2xl font-black tracking-tight">{{ peso(totalNet) }}</p>
                        </div>
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
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-float-delayed { animation: float 8s ease-in-out 1.2s infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active { transition: opacity 0.2s ease; }
.modal-enter-from { opacity: 0; }
.modal-leave-active { transition: opacity 0.15s ease; }
.modal-leave-to { opacity: 0; }
</style>
