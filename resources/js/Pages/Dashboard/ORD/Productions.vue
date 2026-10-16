<template>
    <AuthenticatedLayout>
        <Head title="Production Tracking" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6 pb-16">

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
                                <Sparkles class="h-3.5 w-3.5" /> ORD · Manufacturing
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Production Tracking</h1>
                            <p class="text-sm text-blue-100/90">Monitor textile processing stages from Knitting to Packaging</p>
                        </div>
                        <!-- Live badge -->
                        <div class="flex items-center gap-2 rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                            <span class="h-2 w-2 rounded-full bg-emerald-300 animate-pulse"></span>
                            Live Updates
                        </div>
                    </div>

                    <!-- Summary pills -->
                    <div class="relative mt-6 flex flex-wrap items-center gap-2 text-xs font-black">
                        <span class="rounded-full bg-white/15 px-3 py-1.5 ring-1 ring-white/25 backdrop-blur">{{ productions.length }} batch{{ productions.length !== 1 ? 'es' : '' }}</span>
                        <span class="rounded-full bg-emerald-400/25 px-3 py-1.5 ring-1 ring-white/25 backdrop-blur">{{ releasable.length }} ready to release</span>
                    </div>
                </div>

                <!-- Releasable job orders -->
                <div v-if="releasable.length > 0" class="animate-fade-up overflow-hidden rounded-3xl border border-emerald-200 dark:border-emerald-900 bg-emerald-50/60 dark:bg-emerald-950/20 shadow-sm" style="animation-delay: 60ms">
                    <div class="px-5 pt-4 pb-2 flex items-center justify-between">
                        <h2 class="font-black text-emerald-800 dark:text-emerald-200 text-sm tracking-tight">Ready to release to the plant floor</h2>
                        <span class="text-[11px] font-bold text-emerald-600">{{ releasable.length }} cleared</span>
                    </div>
                    <div class="overflow-x-auto px-2 pb-3">
                        <table class="w-full min-w-[44rem] text-sm">
                            <tbody>
                                <tr v-for="job in releasable" :key="job.id" class="border-t border-emerald-100 dark:border-emerald-900/50">
                                    <td class="px-3 py-2.5 font-black">{{ job.jo_number }}</td>
                                    <td class="px-3 py-2.5">{{ job.client_name }}</td>
                                    <td class="px-3 py-2.5 text-gray-500 text-xs">{{ job.product_name }} · {{ job.quantity }} kg</td>
                                    <td class="px-3 py-2.5 text-xs font-bold">{{ job.expected_ship_date || '—' }}</td>
                                     <td class="px-3 py-2.5 text-right">
                                        <button v-if="canEditProductions" @click="release(job)" class="rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-black text-white hover:bg-emerald-500 active:scale-95">Release</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Content: Table on desktop, Cards on mobile -->
                <div v-if="productions.length > 0">

                    <!-- ── MOBILE CARDS (hidden on md+) ── -->
                    <TransitionGroup name="card" tag="div" class="flex flex-col gap-4 md:hidden">
                        <div
                            v-for="(item, i) in productions"
                            :key="item.id"
                            :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                            class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 overflow-hidden"
                        >
                            <!-- hover glow -->
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <!-- Card accent bar -->
                            <div :class="progressBarColor(item.status)" class="h-1 w-full"></div>

                            <div class="p-5">
                                <!-- Header row -->
                                <div class="flex items-start justify-between gap-2 mb-3">
                                    <div class="min-w-0">
                                        <p class="font-black text-gray-900 dark:text-white text-sm tracking-tight">{{ item.order_number }}</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-300 font-medium mt-0.5 truncate">{{ item.client_name }}</p>
                                        <p class="text-xs text-gray-400 truncate">{{ item.product_name }}</p>
                                    </div>
                                    <span
                                        class="px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-wide border flex-shrink-0 flex items-center gap-1"
                                        :class="statusBadge(item.status)"
                                    ><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ formatStatus(item.status) }}</span>
                                </div>

                                <!-- Progress Section -->
                                <div class="mb-3">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Progress</span>
                                        <span class="text-indigo-600 dark:text-indigo-300 font-black text-sm tabular-nums">{{ item.progress }}%</span>
                                    </div>
                                    <div class="h-2 bg-gray-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                        <div
                                            class="h-full rounded-full transition-all duration-1000"
                                            :class="progressBarColor(item.status)"
                                            :style="{ width: item.progress + '%' }"
                                        ></div>
                                    </div>
                                    <p class="text-[10px] text-gray-400 mt-1 font-bold uppercase tracking-wider">
                                        {{ item.completed_quantity }} / {{ item.total_quantity }} Units
                                    </p>
                                </div>

                                <!-- Footer row -->
                                <div class="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-zinc-800">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Started</span>
                                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">{{ item.created_at }}</span>
                                </div>
                            </div>
                            <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        </div>
                    </TransitionGroup>

                    <!-- ── DESKTOP TABLE (hidden on mobile) ── -->
                    <div class="animate-fade-up hidden md:block bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden" style="animation-delay: 100ms">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-gray-100 dark:border-zinc-800 bg-slate-50/60 dark:bg-zinc-800/40">
                                        <th class="py-3.5 px-5 text-[10px] font-black text-gray-400 uppercase tracking-widest">Order</th>
                                        <th class="py-3.5 px-5 text-[10px] font-black text-gray-400 uppercase tracking-widest">Client / Product</th>
                                        <th class="py-3.5 px-5 text-[10px] font-black text-gray-400 uppercase tracking-widest w-1/3">Progress</th>
                                        <th class="py-3.5 px-5 text-[10px] font-black text-gray-400 uppercase tracking-widest">Status</th>
                                        <th class="py-3.5 px-5 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Started</th>
                                    </tr>
                                </thead>
                                <TransitionGroup name="card" tag="tbody" class="divide-y divide-gray-50 dark:divide-zinc-800">
                                    <tr
                                        v-for="item in productions"
                                        :key="item.id"
                                        class="group hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors"
                                    >
                                        <!-- Order Number -->
                                        <td class="py-4 px-5">
                                            <div class="flex items-center gap-2.5">
                                                <div :class="progressBarColor(item.status)" class="w-1 h-8 rounded-full flex-shrink-0"></div>
                                                <span class="font-black text-gray-900 dark:text-white text-sm">{{ item.order_number }}</span>
                                            </div>
                                        </td>

                                        <!-- Client / Product -->
                                        <td class="py-4 px-5">
                                            <p class="font-semibold text-gray-900 dark:text-white text-sm">{{ item.client_name }}</p>
                                            <p class="text-xs text-gray-400 mt-0.5">{{ item.product_name }}</p>
                                        </td>

                                        <!-- Progress -->
                                        <td class="py-4 px-5">
                                            <div class="flex items-center gap-3">
                                                <div class="flex-1 h-1.5 bg-gray-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                                    <div
                                                        class="h-full rounded-full transition-all duration-1000"
                                                        :class="progressBarColor(item.status)"
                                                        :style="{ width: item.progress + '%' }"
                                                    ></div>
                                                </div>
                                                <span class="text-sm font-black text-indigo-600 dark:text-indigo-300 tabular-nums w-10 text-right flex-shrink-0">{{ item.progress }}%</span>
                                            </div>
                                            <p class="text-[10px] text-gray-400 mt-1.5 font-bold uppercase tracking-wider">
                                                {{ item.completed_quantity }} / {{ item.total_quantity }} Units
                                            </p>
                                        </td>

                                        <!-- Status -->
                                        <td class="py-4 px-5">
                                            <span
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wide border"
                                                :class="statusBadge(item.status)"
                                            >
                                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />
                                                {{ formatStatus(item.status) }}
                                            </span>
                                        </td>

                                        <!-- Date -->
                                        <td class="py-4 px-5 text-right">
                                            <span class="text-xs font-semibold text-gray-400">{{ item.created_at }}</span>
                                        </td>
                                    </tr>
                                </TransitionGroup>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- Empty State -->
                <div v-else class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 dark:to-violet-900/20 rounded-full mb-4 animate-bounce-soft">
                        <Factory class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">No active production batches</p>
                    <p class="text-xs text-gray-400 mt-1">Production lines will appear here once started</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePageAccess } from '@/composables/usePageAccess';
import { Factory, Sparkles } from 'lucide-vue-next';

defineProps({
    productions: Array,
    releasable: {
        type: Array,
        default: () => [],
    },
});

const { canEdit } = usePageAccess();
const canEditProductions = computed(() => canEdit('ORD', 'productions'));

const release = (job) => {
    if (!confirm(`Release ${job.jo_number} (${job.quantity} kg) to production?`)) return;
    router.post(route('ord.productions.release', { id: job.id }), {}, { preserveScroll: true });
};

const statusBadge = (status) => {
    const map = {
        pending:     'bg-yellow-50 text-yellow-700 border-yellow-200',
        in_progress: 'bg-blue-50 text-blue-700 border-blue-200',
        completed:   'bg-green-50 text-green-700 border-green-200',
        rejected:    'bg-red-50 text-red-700 border-red-200',
    };
    return map[status] || 'bg-gray-50 text-gray-600 border-gray-200';
};

const progressBarColor = (status) => {
    const map = {
        pending:     'bg-yellow-400',
        in_progress: 'bg-blue-500',
        completed:   'bg-green-500',
        rejected:    'bg-red-400',
    };
    return map[status] || 'bg-gray-300';
};

const formatStatus = (status) => {
    const map = {
        pending:     'Pending',
        in_progress: 'In Progress',
        completed:   'Completed',
        rejected:    'Rejected',
    };
    return map[status] || status;
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
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active { transition: opacity 0.25s ease, transform 0.32s cubic-bezier(0.22,1,0.36,1); }
.modal-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: translateY(18px) scale(0.97); }
</style>
