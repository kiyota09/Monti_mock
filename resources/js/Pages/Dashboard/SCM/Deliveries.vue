<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ClipboardCheck, Search, ChevronDown } from 'lucide-vue-next';

const props = defineProps({ summary: Object, receivings: Array });
const searchQuery = ref('');
const statusFilter = ref('');
const expanded = ref(null);

const filtered = computed(() => {
    let rows = props.receivings || [];
    if (statusFilter.value) rows = rows.filter(r => r.status === statusFilter.value);
    const q = searchQuery.value.trim().toLowerCase();
    if (q) rows = rows.filter(r => (r.receiving_number || '').toLowerCase().includes(q) || (r.warehouse_name || '').toLowerCase().includes(q) || (r.po_number || '').toLowerCase().includes(q));
    return rows;
});

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
const badge = (s) => ({ pending: 'bg-slate-100 text-slate-600', partial: 'bg-amber-100 text-amber-700', completed: 'bg-emerald-100 text-emerald-700' }[s] || 'bg-slate-100 text-slate-600');
const itemBadge = (s) => ({ pending: 'bg-slate-100 text-slate-500', accepted: 'bg-emerald-100 text-emerald-700', rejected: 'bg-rose-100 text-rose-600', partial: 'bg-amber-100 text-amber-700' }[s] || 'bg-slate-100 text-slate-500');
</script>

<template>
    <AuthenticatedLayout>
        <Head title="SCM Deliveries" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 shadow-lg">
                            <ClipboardCheck class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">SCM · Deliver</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Inbound Deliveries &amp; QC</h1>
                            <p class="text-sm text-blue-100/90">Yarn lots, dyes &amp; accessories received — with fabric inspection results.</p>
                        </div>
                    </div>
                    <div class="relative mt-6 grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                        <div class="rounded-2xl bg-white/12 ring-1 ring-white/25 px-4 py-3"><p class="text-2xl font-black">{{ summary.total }}</p><p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Receipts</p></div>
                        <div class="rounded-2xl bg-white/12 ring-1 ring-white/25 px-4 py-3"><p class="text-2xl font-black">{{ summary.completed }}</p><p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Completed</p></div>
                        <div class="rounded-2xl bg-white/12 ring-1 ring-white/25 px-4 py-3"><p class="text-2xl font-black">{{ summary.partial }}</p><p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Partial</p></div>
                        <div class="rounded-2xl bg-white/12 ring-1 ring-white/25 px-4 py-3"><p class="text-2xl font-black">{{ summary.pending }}</p><p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Pending</p></div>
                        <div class="rounded-2xl bg-white/12 ring-1 ring-white/25 px-4 py-3"><p class="text-2xl font-black">{{ Number(summary.rejectedQty || 0).toLocaleString() }}</p><p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Units rejected</p></div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1">
                        <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input v-model="searchQuery" placeholder="Search receipt, warehouse or PO…" class="w-full rounded-2xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 py-3 pl-11 pr-4 text-sm font-medium outline-none focus:ring-2 focus:ring-indigo-500/40" />
                    </div>
                    <select v-model="statusFilter" class="rounded-2xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-4 py-3 text-xs font-black uppercase outline-none">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="partial">Partial</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>

                <div v-if="filtered.length" class="space-y-3">
                    <div v-for="r in filtered" :key="r.id" class="rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm overflow-hidden">
                        <button @click="expanded = expanded === r.id ? null : r.id" class="w-full flex items-center gap-3 px-5 py-4 text-left hover:bg-indigo-50/40 dark:hover:bg-indigo-950/20 transition">
                            <div class="min-w-0 flex-1">
                                <p class="font-black truncate">{{ r.receiving_number }} <span class="font-bold text-slate-400">· {{ r.warehouse_name }}</span></p>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">PO {{ r.po_number || '—' }} · {{ fmtDate(r.received_at) }} · by {{ r.received_by }} · {{ r.items.length }} line(s)</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase" :class="badge(r.status)">{{ r.status }}</span>
                            <ChevronDown class="h-4 w-4 text-slate-400 transition" :class="expanded === r.id ? 'rotate-180' : ''" />
                        </button>
                        <div v-if="expanded === r.id" class="border-t border-slate-100 dark:border-zinc-800 px-5 py-3 bg-slate-50/60 dark:bg-zinc-800/40">
                            <div v-for="(i, idx) in r.items" :key="idx" class="py-2.5 border-b border-slate-100 dark:border-zinc-800 last:border-0">
                                <div class="flex justify-between text-xs gap-2">
                                    <span class="font-bold">{{ i.material_name }}</span>
                                    <span class="rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase" :class="itemBadge(i.status)">{{ i.status }}</span>
                                </div>
                                <p class="mt-1 text-[11px] font-bold text-slate-400">Expected {{ i.expected_qty }} · Received {{ i.received_qty }} · Rejected {{ i.rejected_qty }}</p>
                                <p v-if="i.reject_reason" class="text-[11px] font-bold text-rose-500">Reason: {{ i.reject_reason }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 py-16 text-center">
                    <p class="text-sm font-black">No inbound receipts found.</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
