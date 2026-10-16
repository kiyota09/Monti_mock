<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import {
    ShoppingCart, ClipboardList, FileText, Receipt,
    Sparkles, ArrowUpRight, ChevronRight
} from 'lucide-vue-next';

const cards = [
    {
        title: 'Material Requests',
        desc: 'Forwarded SCM requests awaiting RFQ generation.',
        href: route('pro.manager.material-requests'),
        icon: ClipboardList,
        gradient: 'from-blue-600 via-indigo-600 to-violet-700',
        chip: 'RFQ Queue',
    },
    {
        title: 'Supplier Quotations',
        desc: 'Review, accept or decline supplier quotes.',
        href: route('pro.manager.supplier-quotations'),
        icon: FileText,
        gradient: 'from-emerald-500 via-teal-600 to-cyan-700',
        chip: 'Review',
    },
    {
        title: 'Receipts',
        desc: 'Manage purchase orders & supplier invoices.',
        href: route('pro.manager.receipt'),
        icon: Receipt,
        gradient: 'from-amber-500 via-orange-600 to-rose-600',
        chip: 'POs + Pay',
    },
];
</script>

<template>
    <Head title="Procurement Manager Dashboard" />
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
                            <ShoppingCart class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> PRO · Procurement
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Procurement Manager Dashboard</h1>
                            <p class="text-sm text-blue-100/90">Requests, quotations & receipts — in one place</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse" /> Live
                            </span>
                            <span class="rounded-full bg-white px-3 py-1.5 text-xs font-black text-indigo-700 shadow-lg">Manager</span>
                        </div>
                    </div>
                </div>

                <!-- Nav cards -->
                <TransitionGroup name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Link
                        v-for="(c, i) in cards"
                        :key="c.title"
                        :href="c.href"
                        :style="{ transitionDelay: `${Math.min(i * 60, 400)}ms`, animationDelay: `${i * 80}ms` }"
                        class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden"
                    >
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-start justify-between mb-4">
                            <div :class="['flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300', c.gradient]">
                                <component :is="c.icon" class="h-6 w-6" />
                            </div>
                            <span class="text-[9px] font-black uppercase px-2.5 py-1 rounded-full ring-1 bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30 flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ c.chip }}
                            </span>
                        </div>
                        <p class="text-sm font-black text-gray-900 dark:text-white tracking-tight group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors">{{ c.title }}</p>
                        <p class="mt-1 text-[12px] font-medium text-gray-500 dark:text-gray-400 leading-relaxed">{{ c.desc }}</p>
                        <div class="mt-auto flex items-center justify-between pt-4 border-t border-gray-100 dark:border-zinc-800 mt-4">
                            <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Open module</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 group-hover:bg-indigo-600 group-hover:text-white group-hover:translate-x-1 transition-all duration-300">
                                <ChevronRight class="h-4 w-4" />
                            </span>
                        </div>
                        <ArrowUpRight class="absolute top-4 right-4 h-4 w-4 text-indigo-300 opacity-0 group-hover:opacity-100 transition-opacity" />
                    </Link>
                </TransitionGroup>

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
.modal-enter-active .modal-panel, .modal-leave-active .modal-panel { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-panel, .modal-leave-to .modal-panel { opacity: 0; transform: translateY(16px) scale(0.97); }
</style>
