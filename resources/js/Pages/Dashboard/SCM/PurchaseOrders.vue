<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { PackageCheck, Search, ChevronDown } from 'lucide-vue-next';

const props = defineProps({ summary: Object, orders: Array });
const searchQuery = ref('');
const statusFilter = ref('');
const expanded = ref(null);

const filtered = computed(() => {
    let rows = props.orders || [];
    if (statusFilter.value) rows = rows.filter(o => o.status === statusFilter.value);
    const q = searchQuery.value.trim().toLowerCase();
    if (q) rows = rows.filter(o => (o.po_number || '').toLowerCase().includes(q) || (o.supplier_name || '').toLowerCase().includes(q));
    return rows;
});

const statuses = computed(() => [...new Set((props.orders || []).map(o => o.status))]);
const fmtMoney = (n) => '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
const badge = (s) => ({
    draft: 'bg-slate-100 text-slate-600', sent: 'bg-blue-100 text-blue-700', confirmed: 'bg-indigo-100 text-indigo-700',
    partially_received: 'bg-amber-100 text-amber-700', received: 'bg-emerald-100 text-emerald-700', cancelled: 'bg-rose-100 text-rose-600',
}[s] || 'bg-slate-100 text-slate-600');
</script>

<template>
    <AuthenticatedLayout>
        <Head title="SCM Purchase Orders" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 shadow-lg">
                            <PackageCheck class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">SCM · Source</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Purchase Order Tracking</h1>
                            <p class="text-sm text-blue-100/90">Pipeline visibility — execution stays in PRO.</p>
                        </div>
                    </div>
                    <div class="relative mt-6 grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <div class="rounded-2xl bg-white/12 ring-1 ring-white/25 px-4 py-3"><p class="text-2xl font-black">{{ summary.total }}</p><p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Total POs</p></div>
                        <div class="rounded-2xl bg-white/12 ring-1 ring-white/25 px-4 py-3"><p class="text-2xl font-black">{{ summary.awaiting }}</p><p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Awaiting</p></div>
                        <div class="rounded-2xl bg-white/12 ring-1 ring-white/25 px-4 py-3"><p class="text-2xl font-black">{{ summary.inTransit }}</p><p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Partially received</p></div>
                        <div class="rounded-2xl bg-white/12 ring-1 ring-white/25 px-4 py-3"><p class="text-2xl font-black">{{ summary.received }}</p><p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Received</p></div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1">
                        <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input v-model="searchQuery" placeholder="Search PO number or supplier…" class="w-full rounded-2xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 py-3 pl-11 pr-4 text-sm font-medium outline-none focus:ring-2 focus:ring-indigo-500/40" />
                    </div>
                    <select v-model="statusFilter" class="rounded-2xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-4 py-3 text-xs font-black uppercase outline-none">
                        <option value="">All statuses</option>
                        <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                    </select>
                </div>

                <div v-if="filtered.length" class="space-y-3">
                    <div v-for="o in filtered" :key="o.id" class="rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm overflow-hidden">
                        <button @click="expanded = expanded === o.id ? null : o.id" class="w-full flex items-center gap-3 px-5 py-4 text-left hover:bg-indigo-50/40 dark:hover:bg-indigo-950/20 transition">
                            <div class="min-w-0 flex-1">
                                <p class="font-black truncate">{{ o.po_number }} · {{ o.supplier_name }}</p>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Issued {{ fmtDate(o.issued_date) }} · ETA {{ o.expected_delivery || '—' }} · {{ o.items_count }} line(s) · {{ fmtMoney(o.grand_total) }}</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase" :class="badge(o.status)">{{ o.status }}</span>
                            <ChevronDown class="h-4 w-4 text-slate-400 transition" :class="expanded === o.id ? 'rotate-180' : ''" />
                        </button>
                        <div v-if="expanded === o.id" class="border-t border-slate-100 dark:border-zinc-800 px-5 py-4 bg-slate-50/60 dark:bg-zinc-800/40">
                            <div v-for="(i, idx) in o.items" :key="idx" class="flex justify-between text-xs py-1.5 border-b border-slate-100 dark:border-zinc-800 last:border-0">
                                <span class="font-bold">{{ i.material_name }} <span class="text-slate-400">· {{ i.qty }} {{ i.unit }}</span></span>
                                <span class="font-black">{{ fmtMoney(i.total) }}</span>
                            </div>
                            <p class="mt-2 text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ o.invoices_count }} invoice(s) linked</p>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 py-16 text-center">
                    <p class="text-sm font-black">No purchase orders found.</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
