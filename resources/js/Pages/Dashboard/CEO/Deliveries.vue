<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Truck, Search, ChevronDown, CheckCircle2, Clock, Package, User, CreditCard } from 'lucide-vue-next';

const props = defineProps({ filters: Object, summary: Object, deliveries: Array });

const q = ref(props.filters?.q || '');
const status = ref(props.filters?.status || '');
const expanded = ref(null);

const search = () => {
    router.get(route('ceo.deliveries'), { q: q.value, status: status.value },
        { preserveState: true, preserveScroll: true, replace: true });
};

const fdate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
const ftime = (d) => d ? new Date(d).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';
const badge = (s) => ({
    pending: 'bg-slate-100 dark:bg-zinc-800 text-slate-600', dispatched: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700',
    in_transit: 'bg-indigo-100 text-indigo-700', delivered: 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700',
    cancelled: 'bg-rose-100 text-rose-600',
}[s] || 'bg-slate-100 dark:bg-zinc-800 text-slate-600');
</script>

<template>
    <AuthenticatedLayout>
        <Head title="CEO Deliveries" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="mx-auto max-w-7xl space-y-5 p-4 pb-16 sm:p-6">

                <div class="relative overflow-hidden rounded-3xl bg-slate-900 p-6 text-white shadow-xl sm:p-7">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800" />
                    <div class="absolute -top-20 -right-16 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-13 w-13 items-center justify-center rounded-2xl bg-white/15 p-3 ring-1 ring-white/30">
                            <Truck class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">CEO · Oversight</p>
                            <h1 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">Deliveries Tracking</h1>
                            <p class="mt-1 text-sm text-blue-100/90">Every outbound delivery — client, truck, driver, proof &amp; payment.</p>
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs font-bold">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 ring-1 ring-white/25">{{ summary.total }} total</span>
                            <span class="rounded-full bg-white/15 px-3 py-1.5 ring-1 ring-white/25">{{ summary.inTransit }} moving</span>
                            <span class="rounded-full bg-emerald-400/90 px-3 py-1.5 text-emerald-950">{{ summary.delivered }} delivered</span>
                            <span class="rounded-full bg-white/15 px-3 py-1.5 ring-1 ring-white/25">{{ summary.confirmed }} confirmed</span>
                        </div>
                    </div>
                    <div class="relative mt-5 flex flex-col gap-2 sm:flex-row">
                        <div class="relative flex-1">
                            <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-zinc-500" />
                            <input v-model="q" @keyup.enter="search" placeholder="Delivery no, client, truck or plate…"
                                class="w-full rounded-xl border-0 bg-white/95 dark:bg-zinc-900/95 py-3 pl-10 pr-4 text-sm font-medium text-gray-900 dark:text-zinc-100 outline-none placeholder:text-gray-400 focus:ring-2 focus:ring-white/70" />
                        </div>
                        <select v-model="status" @change="search"
                            class="rounded-xl border-0 bg-white/95 dark:bg-zinc-900/95 px-4 py-3 text-xs font-black uppercase text-gray-700 dark:text-zinc-300 outline-none">
                            <option value="">All statuses</option>
                            <option value="pending">Pending</option>
                            <option value="dispatched">Dispatched</option>
                            <option value="in_transit">In transit</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div v-if="deliveries.length" class="space-y-3">
                    <div v-for="d in deliveries" :key="d.id"
                        class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <button @click="expanded = expanded === d.id ? null : d.id"
                            class="flex w-full items-center gap-3 px-5 py-4 text-left transition hover:bg-indigo-50/40 dark:hover:bg-indigo-950/20">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-violet-700 text-white shadow">
                                <Truck class="h-5 w-5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-mono text-sm font-black">{{ d.delivery_number }}</span>
                                <span class="block truncate text-[11px] font-bold text-slate-400 dark:text-zinc-500">{{ d.client_name }} · {{ d.truck }} · {{ d.driver }}</span>
                            </span>
                            <span class="hidden shrink-0 rounded-full px-3 py-1 text-[10px] font-black uppercase sm:inline" :class="badge(d.status)">{{ d.status }}</span>
                            <ChevronDown class="h-4 w-4 shrink-0 text-slate-400 dark:text-zinc-500 transition" :class="expanded === d.id ? 'rotate-180' : ''" />
                        </button>
                        <div v-if="expanded === d.id" class="grid gap-3 border-t border-slate-100 bg-slate-50/60 px-5 py-4 text-xs dark:border-zinc-800 dark:bg-zinc-800/40 sm:grid-cols-3">
                            <div class="space-y-1.5">
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Order</p>
                                <p><span class="font-bold text-slate-400 dark:text-zinc-500">JO:</span> <span class="font-black">{{ d.jo_number || '—' }}</span></p>
                                <p><span class="font-bold text-slate-400 dark:text-zinc-500">PO:</span> <span class="font-black">{{ d.po_number || '—' }}</span></p>
                                <p class="flex items-center gap-1.5"><CreditCard class="h-3.5 w-3.5 text-slate-400 dark:text-zinc-500" />
                                    <span class="font-black" :class="d.po_payment === 'paid' ? 'text-emerald-600' : 'text-amber-600'">{{ d.po_payment || 'unpaid' }}</span></p>
                                <p><span class="font-bold text-slate-400 dark:text-zinc-500">Route:</span> <span class="font-bold">{{ d.route }}</span></p>
                            </div>
                            <div class="space-y-1.5">
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Movement</p>
                                <p class="flex items-center gap-1.5"><Clock class="h-3.5 w-3.5 text-slate-400 dark:text-zinc-500" /> Sched: <strong>{{ ftime(d.scheduled_departure) }}</strong></p>
                                <p>Departed: <strong>{{ ftime(d.actual_departure) }}</strong></p>
                                <p>Arrived: <strong>{{ ftime(d.arrival_time) }}</strong></p>
                                <p>POD: <strong>{{ ftime(d.pod_at) }}</strong></p>
                                <p v-if="d.pod_notes" class="text-slate-500 dark:text-zinc-400">“{{ d.pod_notes }}”</p>
                                <p v-if="d.client_confirmed" class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900/30 px-2.5 py-0.5 text-[10px] font-black uppercase text-emerald-700 dark:text-emerald-300">
                                    <CheckCircle2 class="h-3 w-3" /> Client confirmed receipt
                                </p>
                            </div>
                            <div class="space-y-1.5">
                                <p class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500"><Package class="h-3.5 w-3.5" /> Packages ({{ d.packages_count }})</p>
                                <p v-for="p in d.packages" :key="p.package_number" class="flex justify-between gap-2">
                                    <span class="font-mono font-bold">{{ p.package_number }}</span>
                                    <span>{{ p.quantity }} · {{ p.status }}</span>
                                </p>
                                <p class="flex items-center gap-1.5 pt-1"><User class="h-3.5 w-3.5 text-slate-400 dark:text-zinc-500" /> Driver: <strong>{{ d.driver }}</strong></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-3xl border border-dashed border-gray-200 dark:border-zinc-700 bg-white py-16 text-center dark:bg-zinc-900">
                    <p class="text-sm font-black">No deliveries found.</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
