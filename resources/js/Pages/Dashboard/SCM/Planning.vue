<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { CalendarClock, Search, TriangleAlert, CheckCircle2 } from 'lucide-vue-next';

const props = defineProps({ summary: Object, requirements: Array });
const searchQuery = ref('');
const showOnlyShortage = ref(false);

const filtered = computed(() => {
    let rows = props.requirements || [];
    if (showOnlyShortage.value) rows = rows.filter(r => r.shortage);
    const q = searchQuery.value.trim().toLowerCase();
    if (q) rows = rows.filter(r => (r.material_name || '').toLowerCase().includes(q));
    return rows;
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="SCM Planning" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 shadow-lg">
                            <CalendarClock class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">SCM · Plan</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Demand &amp; Materials Planning</h1>
                            <p class="text-sm text-blue-100/90">Open yarn, dye &amp; chemical demand vs live warehouse stock.</p>
                        </div>
                        <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25">{{ summary.shortages }} shortages</span>
                    </div>
                    <div class="relative mt-6 grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <div class="rounded-2xl bg-white/12 ring-1 ring-white/25 px-4 py-3"><p class="text-2xl font-black">{{ summary.materials }}</p><p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Materials demanded</p></div>
                        <div class="rounded-2xl bg-white/12 ring-1 ring-white/25 px-4 py-3"><p class="text-2xl font-black">{{ summary.shortages }}</p><p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">In shortage</p></div>
                        <div class="rounded-2xl bg-white/12 ring-1 ring-white/25 px-4 py-3"><p class="text-2xl font-black">{{ Number(summary.totalRequired || 0).toLocaleString() }}</p><p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Total required</p></div>
                        <div class="rounded-2xl bg-white/12 ring-1 ring-white/25 px-4 py-3"><p class="text-2xl font-black">{{ Number(summary.totalOnHand || 0).toLocaleString() }}</p><p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Total on hand</p></div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1">
                        <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input v-model="searchQuery" placeholder="Search material…" class="w-full rounded-2xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 py-3 pl-11 pr-4 text-sm font-medium outline-none focus:ring-2 focus:ring-indigo-500/40" />
                    </div>
                    <button @click="showOnlyShortage = !showOnlyShortage" class="rounded-2xl px-5 py-3 text-xs font-black uppercase tracking-wide transition"
                        :class="showOnlyShortage ? 'bg-rose-600 text-white shadow-lg' : 'bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-500'">
                        {{ showOnlyShortage ? 'Showing shortages' : 'Show shortages only' }}
                    </button>
                </div>

                <div v-if="filtered.length" class="rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm min-w-[720px]">
                            <thead>
                                <tr class="text-left text-[10px] font-black uppercase tracking-widest text-slate-400 border-b border-slate-100 dark:border-zinc-800">
                                    <th class="px-5 py-3.5">Material</th>
                                    <th class="px-5 py-3.5 text-right">Required</th>
                                    <th class="px-5 py-3.5 text-right">On hand</th>
                                    <th class="px-5 py-3.5 text-right">Gap</th>
                                    <th class="px-5 py-3.5">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in filtered" :key="r.material_id ?? r.material_name" class="border-b border-slate-50 dark:border-zinc-800/60 hover:bg-indigo-50/40 dark:hover:bg-indigo-950/20 transition">
                                    <td class="px-5 py-3.5">
                                        <p class="font-black">{{ r.material_name }}</p>
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ r.requests }} request(s) · {{ r.top_urgency }} · {{ r.unit }}</p>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-black">{{ Number(r.required_qty).toLocaleString() }}</td>
                                    <td class="px-5 py-3.5 text-right font-black">{{ Number(r.on_hand).toLocaleString() }}</td>
                                    <td class="px-5 py-3.5 text-right font-black" :class="r.shortage ? 'text-rose-600' : 'text-emerald-600'">{{ r.gap > 0 ? '+' : '' }}{{ Number(r.gap).toLocaleString() }}</td>
                                    <td class="px-5 py-3.5">
                                        <span v-if="r.shortage" class="inline-flex items-center gap-1 rounded-full bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 px-3 py-1 text-[10px] font-black uppercase"><TriangleAlert class="h-3 w-3" /> Shortage</span>
                                        <span v-else class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 px-3 py-1 text-[10px] font-black uppercase"><CheckCircle2 class="h-3 w-3" /> Covered</span>
                                        <span v-if="r.below_reorder" class="ml-1.5 rounded-full bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 px-3 py-1 text-[10px] font-black uppercase">Below reorder</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div v-else class="rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 py-16 text-center">
                    <p class="text-sm font-black">No open material demand.</p>
                    <p class="text-xs text-slate-400 mt-1">New pending requests will appear here.</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
