<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {
    Receipt,
    Download,
    ChevronLeft,
    ChevronRight,
    MoreHorizontal,
    Wallet,
    ArrowDownCircle,
    ArrowUpCircle,
    Info
} from 'lucide-vue-next';

// Mock Data for the Payslip
const earnings = [
    { label: 'Basic Salary', amount: 3500.00 },
    { label: 'Overtime (12hrs)', amount: 450.00 },
    { label: 'Night Differential', amount: 200.00 },
    { label: 'Holiday Pay', amount: 130.00 },
];

const deductions = [
    { label: 'Income Tax', amount: 420.00 },
    { label: 'SSS Contribution', amount: 120.00 },
    { label: 'PhilHealth', amount: 80.00 },
    { label: 'Pag-IBIG', amount: 50.00 },
];

const payHistory = [
    { period: 'Jan 16 - Jan 31', net: '$2,140.00', status: 'Paid' },
    { period: 'Jan 01 - Jan 15', net: '$2,140.00', status: 'Paid' },
];
</script>

<template>

    <Head title="Employee Payslip" />

    <AuthenticatedLayout>
        <div class="p-6 bg-[#f8faff] min-h-screen">
            <div class="max-w-[1400px] mx-auto">

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

                    <div class="lg:col-span-8 space-y-8">

                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6">
                            <div>
                                <h1 class="text-3xl font-black text-slate-900 dark:text-zinc-100 uppercase tracking-tight italic">
                                    Payroll <span class="text-blue-600 dark:text-blue-400 font-light">Statement</span>
                                </h1>
                                <p class="text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-widest mt-1 italic">
                                    Reference: MT-PAY-2026-002
                                </p>
                            </div>

                            <div class="flex items-center gap-3">
                                <button
                                    class="p-3 bg-white dark:bg-zinc-900 rounded-2xl shadow-sm border border-slate-100 dark:border-zinc-800 text-slate-400 dark:text-zinc-500 hover:text-blue-600 transition-colors">
                                    <Download class="size-5" />
                                </button>
                                <PrimaryButton
                                    class="bg-blue-600 hover:bg-blue-700 rounded-2xl px-8 py-3 uppercase text-[10px] font-black tracking-widest shadow-lg shadow-blue-200">
                                    Print PDF
                                </PrimaryButton>
                            </div>
                        </div>

                        <div
                            class="bg-blue-600 rounded-[2.5rem] p-10 shadow-xl shadow-blue-200 text-white relative overflow-hidden">
                            <div class="relative z-10 flex flex-col md:flex-row justify-between gap-8">
                                <div class="space-y-6">
                                    <div>
                                        <p class="text-blue-100 text-[10px] font-black uppercase tracking-[0.2em]">
                                            Current Pay Period</p>
                                        <h2 class="text-2xl font-black italic mt-1 uppercase">February 01 - February 15
                                        </h2>
                                    </div>
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-6xl font-black italic tracking-tighter">$4,280.00</span>
                                        <span class="text-blue-200 text-xs font-black uppercase">Net Pay</span>
                                    </div>
                                    <div class="flex gap-4">
                                        <div
                                            class="bg-white/10 px-4 py-2 rounded-xl backdrop-blur-md border border-white/5">
                                            <p class="text-[9px] font-black uppercase text-blue-100">Gross: $4,950.00
                                            </p>
                                        </div>
                                        <div
                                            class="bg-white/10 px-4 py-2 rounded-xl backdrop-blur-md border border-white/5">
                                            <p class="text-[9px] font-black uppercase text-blue-100">Deductions: $670.00
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="hidden md:flex flex-col items-end justify-between">
                                    <div class="bg-white/10 p-4 rounded-[2rem] backdrop-blur-md">
                                        <Wallet class="size-10 text-white opacity-50" />
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[10px] font-black text-blue-200 uppercase tracking-widest">
                                            Disbursement Date</p>
                                        <p class="text-sm font-bold italic">Feb 15, 2026</p>
                                    </div>
                                </div>
                            </div>
                            <div class="absolute -right-16 -bottom-16 size-64 bg-white/5 rounded-full blur-3xl"></div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="bg-white dark:bg-zinc-900 rounded-[2.5rem] p-8 shadow-sm border border-slate-100 dark:border-zinc-800 space-y-6">
                                <h3
                                    class="text-xs font-black text-slate-800 dark:text-zinc-200 uppercase tracking-[0.2em] flex items-center gap-3">
                                    <ArrowUpCircle class="size-5 text-emerald-500" /> Earnings
                                </h3>
                                <div class="space-y-4">
                                    <div v-for="item in earnings" :key="item.label"
                                        class="flex justify-between items-center group">
                                        <span
                                            class="text-xs font-bold text-slate-500 dark:text-zinc-400 group-hover:text-slate-900 transition-colors uppercase">{{
                                            item.label }}</span>
                                        <span class="text-sm font-black text-slate-800 dark:text-zinc-200 italic">${{
                                            item.amount.toFixed(2) }}</span>
                                    </div>
                                    <div class="pt-4 border-t border-slate-50 dark:border-zinc-800 flex justify-between items-center">
                                        <span class="text-[10px] font-black text-slate-400 dark:text-zinc-500 uppercase">Total
                                            Earnings</span>
                                        <span class="text-lg font-black text-emerald-600 dark:text-emerald-400 italic">$4,280.00</span>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white dark:bg-zinc-900 rounded-[2.5rem] p-8 shadow-sm border border-slate-100 dark:border-zinc-800 space-y-6">
                                <h3
                                    class="text-xs font-black text-slate-800 dark:text-zinc-200 uppercase tracking-[0.2em] flex items-center gap-3">
                                    <ArrowDownCircle class="size-5 text-rose-500" /> Deductions
                                </h3>
                                <div class="space-y-4">
                                    <div v-for="item in deductions" :key="item.label"
                                        class="flex justify-between items-center group">
                                        <span
                                            class="text-xs font-bold text-slate-500 dark:text-zinc-400 group-hover:text-slate-900 transition-colors uppercase">{{
                                            item.label }}</span>
                                        <span class="text-sm font-black text-rose-600 italic">-${{
                                            item.amount.toFixed(2) }}</span>
                                    </div>
                                    <div class="pt-4 border-t border-slate-50 dark:border-zinc-800 flex justify-between items-center">
                                        <span class="text-[10px] font-black text-slate-400 dark:text-zinc-500 uppercase">Total
                                            Deductions</span>
                                        <span class="text-lg font-black text-rose-600 italic">-$670.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-4">
                        <div class="bg-white dark:bg-zinc-900 rounded-[2.5rem] shadow-sm border border-slate-100 dark:border-zinc-800 p-8 h-full space-y-10">

                            <div>
                                <h2 class="text-xl font-black text-slate-800 dark:text-zinc-200 italic tracking-tighter uppercase mb-6">
                                    Payment Method</h2>
                                <div
                                    class="bg-slate-50/50 p-6 rounded-[2rem] border border-slate-100 dark:border-zinc-800 relative overflow-hidden group">
                                    <div class="relative z-10">
                                        <p class="text-[9px] font-black text-blue-600 dark:text-blue-400 uppercase tracking-widest mb-2">
                                            Direct Deposit</p>
                                        <p class="text-sm font-black text-slate-800 dark:text-zinc-200 tracking-tight italic">Bank of the
                                            Philippine Islands</p>
                                        <p class="text-xs font-mono text-slate-400 dark:text-zinc-500 font-bold mt-1">**** **** 8821</p>
                                    </div>
                                    <ChevronRight
                                        class="absolute right-6 top-1/2 -translate-y-1/2 size-5 text-slate-200 group-hover:text-blue-600 dark:text-blue-400 transition-colors" />
                                </div>
                            </div>

                            <hr class="border-slate-50 dark:border-zinc-800" />

                            <div class="space-y-6">
                                <div class="flex justify-between items-center mb-2">
                                    <h4 class="text-[10px] font-black text-slate-400 dark:text-zinc-500 uppercase tracking-[0.3em]">
                                        Historical</h4>
                                    <button class="text-[9px] font-black text-blue-600 dark:text-blue-400 uppercase tracking-widest">View
                                        All</button>
                                </div>

                                <div v-for="history in payHistory" :key="history.period"
                                    class="flex gap-5 relative group">
                                    <div
                                        class="size-8 rounded-full bg-blue-50 dark:bg-blue-900/30 border-[3px] border-white z-10 shadow-sm flex items-center justify-center">
                                        <Receipt class="size-3 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <div
                                        class="flex-1 bg-slate-50/30 p-5 rounded-[1.8rem] border border-slate-50 dark:border-zinc-800 hover:bg-white dark:bg-zinc-900 hover:shadow-md transition-all cursor-pointer">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <p
                                                    class="text-[9px] font-black text-slate-400 dark:text-zinc-500 uppercase tracking-widest">
                                                    {{ history.period }}</p>
                                                <p
                                                    class="text-xs font-black text-slate-800 dark:text-zinc-200 mt-1 uppercase italic tracking-tight">
                                                    {{ history.net }}</p>
                                            </div>
                                            <span
                                                class="text-[8px] font-black uppercase px-2 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-800">
                                                {{ history.status }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="p-5 bg-blue-50/50 rounded-[1.5rem] border border-blue-100/50 flex gap-4">
                                <Info class="size-5 text-blue-500 flex-shrink-0" />
                                <p
                                    class="text-[9px] font-bold text-blue-700 dark:text-blue-300 leading-relaxed uppercase tracking-tight italic">
                                    If you notice any discrepancies in your payslip, please contact the HRM department
                                    immediately.
                                </p>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>