<script setup>
import { ref, reactive, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Truck, Search, ChevronDown, CheckCircle2, Clock, Package, User, CreditCard,
    MapPin, Phone, Mail, Building2, Navigation, Image as ImageIcon, X, Users,
    CalendarDays, Route as RouteIcon, Gauge, Palette, Hash, PackageCheck,
    FileText, RefreshCw, BadgeCheck, Boxes,
} from 'lucide-vue-next';

const props = defineProps({ filters: Object, summary: Object, deliveries: Array });

const q = ref(props.filters?.q || '');
const status = ref(props.filters?.status || '');
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');
const expanded = reactive(new Set());

const applying = ref(false);

const search = () => {
    applying.value = true;
    router.get(route('ceo.deliveries'), {
        q: q.value, status: status.value, from: from.value, to: to.value,
    }, {
        preserveState: true, preserveScroll: true, replace: true,
        onFinish: () => { applying.value = false; },
    });
};

const reset = () => {
    q.value = ''; status.value = ''; from.value = ''; to.value = '';
    search();
};

const toggle = (id) => { expanded.has(id) ? expanded.delete(id) : expanded.add(id); };
const isOpen = (id) => expanded.has(id);
const allOpen = computed(() => props.deliveries?.length > 0 && expanded.size === props.deliveries.length);
const toggleAll = () => {
    expanded.clear();
    if (!allOpen.value) props.deliveries.forEach((d) => expanded.add(d.id));
};

// ── formatting helpers ────────────────────────────────────────────────
const fdate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
const ftime = (d) => d ? new Date(d).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';

const STATUS = {
    pending:    { label: 'Pending',    chip: 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300', dot: 'bg-slate-400',   grad: 'from-slate-500 to-slate-700' },
    dispatched: { label: 'Dispatched', chip: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300', dot: 'bg-blue-500',    grad: 'from-blue-500 to-blue-700' },
    in_transit: { label: 'In transit', chip: 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300', dot: 'bg-indigo-500', grad: 'from-indigo-500 to-violet-700' },
    delivered:  { label: 'Delivered',  chip: 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300', dot: 'bg-emerald-500', grad: 'from-emerald-500 to-teal-700' },
    cancelled:  { label: 'Cancelled',  chip: 'bg-rose-100 dark:bg-rose-900/30 text-rose-600 dark:text-rose-300', dot: 'bg-rose-500',    grad: 'from-rose-500 to-red-700' },
};
const meta = (s) => STATUS[s] || STATUS.pending;

const soStatusChip = (s) => ({
    pending: 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300',
    confirmed: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
    in_planning: 'bg-cyan-100 dark:bg-cyan-900/40 text-cyan-700 dark:text-cyan-300',
    in_production: 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
    production_done: 'bg-lime-100 dark:bg-lime-900/40 text-lime-700 dark:text-lime-300',
    ready_for_dispatch: 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300',
    in_transit: 'bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300',
    delivered: 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300',
    completed: 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300',
    cancelled: 'bg-rose-100 dark:bg-rose-900/30 text-rose-600 dark:text-rose-300',
}[s] || 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300');
const prettyStatus = (s) => (s || '—').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

// POD image viewer
const viewer = ref({ open: false, url: '', label: '' });
const openImage = (path, label) => {
    if (!path) return;
    viewer.value = { open: true, url: '/storage/' + path, label: label || 'Proof of Delivery' };
};
const closeImage = () => { viewer.value.open = false; };

const refresh = () => router.reload({ preserveScroll: true });
</script>

<template>
    <AuthenticatedLayout>
        <Head title="CEO Deliveries" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="mx-auto max-w-7xl space-y-5 p-4 pb-16 sm:p-6">

                <!-- Hero -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-slate-900 p-6 text-white shadow-xl sm:p-7">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800" />
                    <div class="absolute -top-20 -right-16 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-12 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.12]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />

                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="animate-pop flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 p-3 ring-1 ring-white/30 shadow-lg backdrop-blur">
                            <Truck class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <BadgeCheck class="h-3.5 w-3.5" /> CEO · Oversight
                            </p>
                            <h1 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">Deliveries Tracking</h1>
                            <p class="mt-1 text-sm text-blue-100/90">Every outbound delivery — client, order, fleet, proof &amp; payment.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs font-bold">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 ring-1 ring-white/25 backdrop-blur">{{ summary.total }} total</span>
                            <span class="rounded-full bg-white/15 px-3 py-1.5 ring-1 ring-white/25 backdrop-blur">{{ summary.inTransit }} moving</span>
                            <span class="rounded-full bg-emerald-400/90 px-3 py-1.5 text-emerald-950">{{ summary.delivered }} delivered</span>
                            <button @click="refresh" class="flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow transition hover:scale-105 active:scale-95">
                                <RefreshCw class="h-3.5 w-3.5" /> Refresh
                            </button>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="relative mt-6 grid gap-2 lg:grid-cols-[1fr_auto_auto_auto_auto]">
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-zinc-500" />
                            <input v-model="q" @keyup.enter="search" placeholder="Delivery no, client, truck, plate or driver…"
                                class="w-full rounded-xl border-0 bg-white/95 py-3 pl-10 pr-4 text-sm font-medium text-gray-900 dark:text-zinc-100 outline-none placeholder:text-gray-400 focus:ring-2 focus:ring-white/70" />
                        </div>
                        <select v-model="status" @change="search"
                            class="rounded-xl border-0 bg-white/95 dark:bg-zinc-900/95 px-4 py-3 text-xs font-black uppercase text-gray-700 dark:text-zinc-300 outline-none focus:ring-2 focus:ring-white/70">
                            <option value="">All statuses</option>
                            <option value="pending">Pending</option>
                            <option value="dispatched">Dispatched</option>
                            <option value="in_transit">In transit</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <input v-model="from" @change="search" type="date" title="Scheduled from"
                            class="rounded-xl border-0 bg-white/95 dark:bg-zinc-900/95 px-3 py-3 text-xs font-bold text-gray-700 dark:text-zinc-300 outline-none focus:ring-2 focus:ring-white/70" />
                        <input v-model="to" @change="search" type="date" title="Scheduled to"
                            class="rounded-xl border-0 bg-white/95 dark:bg-zinc-900/95 px-3 py-3 text-xs font-bold text-gray-700 dark:text-zinc-300 outline-none focus:ring-2 focus:ring-white/70" />
                        <button @click="reset"
                            class="rounded-xl bg-white/15 px-4 py-3 text-xs font-black uppercase tracking-wide text-white ring-1 ring-white/25 backdrop-blur transition hover:bg-white/25 active:scale-95">
                            Clear
                        </button>
                    </div>
                </div>

                <!-- Stat cards -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <div class="animate-fade-up rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900" style="animation-delay:40ms">
                        <div class="flex items-center gap-2 text-slate-400"><Truck class="h-4 w-4" /><span class="text-[10px] font-black uppercase tracking-widest">Total</span></div>
                        <p class="mt-1 text-2xl font-black text-gray-900 dark:text-white">{{ summary.total }}</p>
                    </div>
                    <div class="animate-fade-up rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900" style="animation-delay:80ms">
                        <div class="flex items-center gap-2 text-slate-400"><Clock class="h-4 w-4" /><span class="text-[10px] font-black uppercase tracking-widest">Pending</span></div>
                        <p class="mt-1 text-2xl font-black text-gray-900 dark:text-white">{{ summary.pending }}</p>
                    </div>
                    <div class="animate-fade-up rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900" style="animation-delay:120ms">
                        <div class="flex items-center gap-2 text-indigo-500"><Navigation class="h-4 w-4" /><span class="text-[10px] font-black uppercase tracking-widest">Moving</span></div>
                        <p class="mt-1 text-2xl font-black text-indigo-600 dark:text-indigo-300">{{ summary.inTransit }}</p>
                    </div>
                    <div class="animate-fade-up rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900" style="animation-delay:160ms">
                        <div class="flex items-center gap-2 text-emerald-500"><CheckCircle2 class="h-4 w-4" /><span class="text-[10px] font-black uppercase tracking-widest">Delivered</span></div>
                        <p class="mt-1 text-2xl font-black text-emerald-600 dark:text-emerald-300">{{ summary.delivered }}</p>
                    </div>
                    <div class="animate-fade-up rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900" style="animation-delay:200ms">
                        <div class="flex items-center gap-2 text-teal-500"><PackageCheck class="h-4 w-4" /><span class="text-[10px] font-black uppercase tracking-widest">Confirmed</span></div>
                        <p class="mt-1 text-2xl font-black text-teal-600 dark:text-teal-300">{{ summary.confirmed }}</p>
                    </div>
                    <div class="animate-fade-up rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900" style="animation-delay:240ms">
                        <div class="flex items-center gap-2 text-violet-500"><Boxes class="h-4 w-4" /><span class="text-[10px] font-black uppercase tracking-widest">Packages</span></div>
                        <p class="mt-1 text-2xl font-black text-violet-600 dark:text-violet-300">{{ summary.packages }}</p>
                    </div>
                </div>

                <!-- List header -->
                <div class="flex items-center justify-between gap-3 px-1">
                    <p class="text-xs font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">
                        {{ deliveries.length }} {{ deliveries.length === 1 ? 'delivery' : 'deliveries' }}
                        <span v-if="applying" class="ml-2 text-indigo-500">updating…</span>
                    </p>
                    <button v-if="deliveries.length" @click="toggleAll"
                        class="rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-slate-500 transition hover:border-indigo-300 hover:text-indigo-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                        {{ allOpen ? 'Collapse all' : 'Expand all' }}
                    </button>
                </div>

                <!-- Delivery cards -->
                <div v-if="deliveries.length" class="space-y-3">
                    <div v-for="(d, i) in deliveries" :key="d.id"
                        class="animate-fade-up overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm transition hover:shadow-lg hover:shadow-indigo-500/10 dark:border-zinc-800 dark:bg-zinc-900"
                        :style="{ animationDelay: `${Math.min(i * 45, 360)}ms` }">

                        <!-- Card header -->
                        <button @click="toggle(d.id)"
                            class="flex w-full items-center gap-3 px-5 py-4 text-left transition hover:bg-indigo-50/40 dark:hover:bg-indigo-950/20">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-white shadow"
                                :class="`bg-gradient-to-br ${meta(d.status).grad}`">
                                <Truck class="h-5 w-5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="truncate font-mono text-sm font-black text-gray-900 dark:text-white">{{ d.delivery_number }}</span>
                                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase" :class="meta(d.status).chip">
                                        <span class="h-1.5 w-1.5 rounded-full" :class="meta(d.status).dot" /> {{ meta(d.status).label }}
                                    </span>
                                    <span v-if="d.client_confirmed" class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                        <CheckCircle2 class="h-3 w-3" /> Confirmed
                                    </span>
                                </span>
                                <span class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] font-bold text-slate-400 dark:text-zinc-500">
                                    <span class="text-slate-500 dark:text-zinc-400">{{ d.client?.company_name || '—' }}</span>
                                    <span>·</span>
                                    <span>{{ d.truck ? (d.truck.truck_number + ' · ' + d.truck.plate_number) : 'No truck' }}</span>
                                    <span>·</span>
                                    <span>{{ d.driver?.name || 'No driver' }}</span>
                                </span>
                            </span>
                            <span class="hidden shrink-0 flex-col items-end text-right sm:flex">
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Scheduled</span>
                                <span class="text-xs font-black text-gray-700 dark:text-zinc-200">{{ ftime(d.scheduled_departure) }}</span>
                            </span>
                            <span class="hidden shrink-0 items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black text-slate-600 dark:bg-zinc-800 dark:text-zinc-300 md:inline-flex">
                                <Package class="h-3 w-3" /> {{ d.packages_count }}
                            </span>
                            <ChevronDown class="h-4 w-4 shrink-0 text-slate-400 transition dark:text-zinc-500" :class="isOpen(d.id) ? 'rotate-180' : ''" />
                        </button>

                        <!-- Card body -->
                        <div v-if="isOpen(d.id)" class="border-t border-slate-100 bg-slate-50/60 px-5 py-5 dark:border-zinc-800 dark:bg-zinc-800/40">
                            <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">

                                <!-- Client -->
                                <section class="rounded-2xl border border-gray-100 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                                    <h3 class="mb-3 flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">
                                        <Building2 class="h-3.5 w-3.5 text-indigo-500" /> Client
                                    </h3>
                                    <template v-if="d.client">
                                        <p class="text-sm font-black text-gray-900 dark:text-white">{{ d.client.company_name }}</p>
                                        <div class="mt-2 space-y-1.5 text-xs text-slate-600 dark:text-zinc-300">
                                            <p class="flex items-center gap-2"><User class="h-3.5 w-3.5 text-slate-400" /> {{ d.client.contact_person || '—' }}</p>
                                            <p class="flex items-center gap-2"><Phone class="h-3.5 w-3.5 text-slate-400" /> {{ d.client.phone || '—' }}</p>
                                            <p class="flex items-center gap-2 break-all"><Mail class="h-3.5 w-3.5 shrink-0 text-slate-400" /> {{ d.client.email || '—' }}</p>
                                            <p class="flex items-start gap-2"><MapPin class="mt-0.5 h-3.5 w-3.5 shrink-0 text-slate-400" /> {{ d.client.address || '—' }}</p>
                                        </div>
                                    </template>
                                    <p v-else class="text-xs font-bold text-slate-400">No client linked.</p>
                                </section>

                                <!-- Order -->
                                <section class="rounded-2xl border border-gray-100 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                                    <h3 class="mb-3 flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">
                                        <FileText class="h-3.5 w-3.5 text-indigo-500" /> Order
                                    </h3>
                                    <template v-if="d.order">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-2 py-1 font-mono text-xs font-black text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                                                <Hash class="h-3 w-3" /> {{ d.order.jo_number }}
                                            </span>
                                            <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="soStatusChip(d.order.status)">{{ prettyStatus(d.order.status) }}</span>
                                        </div>
                                        <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-xs">
                                            <div><dt class="font-bold uppercase text-[9px] tracking-wider text-slate-400">Control #</dt><dd class="font-mono font-bold text-gray-800 dark:text-zinc-200">{{ d.order.control_number || '—' }}</dd></div>
                                            <div><dt class="font-bold uppercase text-[9px] tracking-wider text-slate-400">Client PO</dt><dd class="font-mono font-bold text-gray-800 dark:text-zinc-200">{{ d.order.po_number || '—' }}</dd></div>
                                            <div><dt class="font-bold uppercase text-[9px] tracking-wider text-slate-400">Color</dt><dd class="flex items-center gap-1 font-bold text-gray-800 dark:text-zinc-200"><Palette class="h-3 w-3 text-slate-400" /> {{ d.order.color || '—' }}</dd></div>
                                            <div><dt class="font-bold uppercase text-[9px] tracking-wider text-slate-400">Quantity</dt><dd class="font-bold text-gray-800 dark:text-zinc-200">{{ d.order.quantity }}</dd></div>
                                            <div><dt class="font-bold uppercase text-[9px] tracking-wider text-slate-400">Yarn</dt><dd class="font-bold text-gray-800 dark:text-zinc-200">{{ d.order.yarn_type || '—' }}</dd></div>
                                            <div><dt class="font-bold uppercase text-[9px] tracking-wider text-slate-400">Design</dt><dd class="font-bold text-gray-800 dark:text-zinc-200">{{ d.order.design || '—' }}</dd></div>
                                            <div class="col-span-2"><dt class="font-bold uppercase text-[9px] tracking-wider text-slate-400">Expected ship</dt><dd class="flex items-center gap-1 font-bold text-gray-800 dark:text-zinc-200"><CalendarDays class="h-3 w-3 text-slate-400" /> {{ fdate(d.order.expected_ship_date) }}</dd></div>
                                        </dl>
                                        <div class="mt-3 flex items-center gap-2 border-t border-dashed border-slate-100 pt-3 text-xs dark:border-zinc-800">
                                            <CreditCard class="h-3.5 w-3.5 text-slate-400" />
                                            <span class="font-bold uppercase text-[9px] tracking-wider text-slate-400">PO payment</span>
                                            <span class="ml-auto rounded-full px-2 py-0.5 text-[10px] font-black uppercase"
                                                :class="d.order.po_payment === 'paid' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'">
                                                {{ d.order.po_payment || 'unpaid' }}
                                            </span>
                                        </div>
                                    </template>
                                    <p v-else class="text-xs font-bold text-slate-400">No job order linked.</p>
                                </section>

                                <!-- Route & fleet -->
                                <section class="rounded-2xl border border-gray-100 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                                    <h3 class="mb-3 flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">
                                        <RouteIcon class="h-3.5 w-3.5 text-indigo-500" /> Route &amp; Fleet
                                    </h3>
                                    <template v-if="d.route">
                                        <p class="text-sm font-black text-gray-900 dark:text-white">{{ d.route.name }}</p>
                                        <p class="mt-1 flex items-center gap-2 text-xs text-slate-600 dark:text-zinc-300">
                                            <Navigation class="h-3.5 w-3.5 text-slate-400" /> {{ d.route.origin }} → {{ d.route.destination }}
                                        </p>
                                        <div class="mt-2 flex flex-wrap gap-2 text-[10px] font-black uppercase">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-1 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">
                                                <Gauge class="h-3 w-3" /> {{ d.route.distance_km }} km
                                            </span>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-1 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">
                                                <Clock class="h-3 w-3" /> ~{{ d.route.estimated_minutes }} min
                                            </span>
                                            <span v-if="d.route.waypoints_count" class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-1 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">
                                                <MapPin class="h-3 w-3" /> {{ d.route.waypoints_count }} stops
                                            </span>
                                        </div>
                                    </template>
                                    <p v-else class="text-xs font-bold text-slate-400">No route assigned.</p>

                                    <div class="mt-3 grid grid-cols-2 gap-2 border-t border-dashed border-slate-100 pt-3 text-xs dark:border-zinc-800">
                                        <div class="col-span-2">
                                            <p class="font-bold uppercase text-[9px] tracking-wider text-slate-400">Truck</p>
                                            <p v-if="d.truck" class="font-bold text-gray-800 dark:text-zinc-200">
                                                {{ d.truck.truck_number }} · {{ d.truck.plate_number }}
                                                <span class="font-medium text-slate-400">{{ d.truck.model }} ({{ d.truck.year }}) · {{ d.truck.mileage }} km</span>
                                            </p>
                                            <p v-else class="font-bold text-slate-400">—</p>
                                        </div>
                                        <div class="col-span-2">
                                            <p class="font-bold uppercase text-[9px] tracking-wider text-slate-400">Driver</p>
                                            <p v-if="d.driver" class="font-bold text-gray-800 dark:text-zinc-200">
                                                {{ d.driver.name || '—' }}
                                                <span class="font-medium text-slate-400">· Lic {{ d.driver.license_number }}</span>
                                                <span class="ml-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-[9px] font-black text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">★ {{ d.driver.rating }}</span>
                                            </p>
                                            <p v-else class="font-bold text-slate-400">—</p>
                                        </div>
                                        <div v-if="d.conductors.length" class="col-span-2">
                                            <p class="font-bold uppercase text-[9px] tracking-wider text-slate-400">Conductors</p>
                                            <p class="flex items-center gap-1 font-bold text-gray-800 dark:text-zinc-200"><Users class="h-3 w-3 text-slate-400" /> {{ d.conductors.join(', ') }}</p>
                                        </div>
                                    </div>
                                </section>

                                <!-- Movement timeline -->
                                <section class="rounded-2xl border border-gray-100 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                                    <h3 class="mb-3 flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">
                                        <Clock class="h-3.5 w-3.5 text-indigo-500" /> Movement
                                    </h3>
                                    <ol class="relative space-y-3 border-l border-dashed border-slate-200 pl-4 dark:border-zinc-700">
                                        <li class="relative">
                                            <span class="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full bg-blue-500 ring-2 ring-white dark:ring-zinc-900" />
                                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Scheduled departure</p>
                                            <p class="text-xs font-bold text-gray-800 dark:text-zinc-200">{{ ftime(d.scheduled_departure) }}</p>
                                        </li>
                                        <li class="relative">
                                            <span class="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full" :class="d.actual_departure ? 'bg-indigo-500' : 'bg-slate-300'" />
                                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Actual departure</p>
                                            <p class="text-xs font-bold text-gray-800 dark:text-zinc-200">{{ ftime(d.actual_departure) }}</p>
                                        </li>
                                        <li class="relative">
                                            <span class="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full" :class="d.arrival_time ? 'bg-violet-500' : 'bg-slate-300'" />
                                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Arrival</p>
                                            <p class="text-xs font-bold text-gray-800 dark:text-zinc-200">{{ ftime(d.arrival_time) }}</p>
                                        </li>
                                        <li class="relative">
                                            <span class="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full" :class="d.pod_at ? 'bg-emerald-500' : 'bg-slate-300'" />
                                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Proof of delivery</p>
                                            <p class="text-xs font-bold text-gray-800 dark:text-zinc-200">{{ ftime(d.pod_at) }}</p>
                                        </li>
                                    </ol>

                                    <!-- Order status history -->
                                    <div v-if="d.timeline && d.timeline.length" class="mt-3 border-t border-dashed border-slate-100 pt-3 dark:border-zinc-800">
                                        <p class="mb-2 text-[9px] font-black uppercase tracking-wider text-slate-400">Order status history</p>
                                        <div class="flex flex-wrap gap-1.5">
                                            <span v-for="(h, idx) in d.timeline" :key="idx" class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-bold text-slate-600 dark:bg-zinc-800 dark:text-zinc-300" :title="ftime(h.at)">
                                                {{ prettyStatus(h.from) }} → {{ prettyStatus(h.to) }}
                                            </span>
                                        </div>
                                    </div>
                                </section>

                                <!-- Packages -->
                                <section class="rounded-2xl border border-gray-100 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                                    <h3 class="mb-3 flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">
                                        <Package class="h-3.5 w-3.5 text-indigo-500" /> Packages ({{ d.packages_count }})
                                    </h3>
                                    <div v-if="d.packages.length" class="space-y-1.5">
                                        <div v-for="p in d.packages" :key="p.package_number"
                                            class="flex items-center justify-between gap-2 rounded-xl border border-slate-100 bg-slate-50/70 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-800/50">
                                            <div class="min-w-0">
                                                <p class="truncate font-mono text-[11px] font-black text-gray-800 dark:text-zinc-100">{{ p.package_number }}</p>
                                                <p class="truncate text-[10px] font-bold text-slate-400">{{ p.product_name || '—' }}<span v-if="p.product_sku"> · {{ p.product_sku }}</span></p>
                                            </div>
                                            <div class="shrink-0 text-right">
                                                <p class="text-[10px] font-black text-gray-700 dark:text-zinc-200">×{{ p.quantity }}</p>
                                                <p class="text-[9px] font-black uppercase" :class="meta(p.status).chip.replace(/bg-\S+/g, '')" >{{ p.status }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <p v-else class="text-xs font-bold text-slate-400">No packages.</p>
                                </section>

                                <!-- Proof of delivery -->
                                <section class="rounded-2xl border border-gray-100 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                                    <h3 class="mb-3 flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">
                                        <ImageIcon class="h-3.5 w-3.5 text-indigo-500" /> Proof of Delivery
                                    </h3>
                                    <div v-if="d.pod_image || d.pod_notes" class="space-y-3">
                                        <button v-if="d.pod_image" @click="openImage(d.pod_image, d.delivery_number)"
                                            class="group relative block w-full overflow-hidden rounded-xl border border-slate-200 dark:border-zinc-700">
                                            <img :src="'/storage/' + d.pod_image" alt="Proof of delivery" class="h-36 w-full object-cover transition duration-300 group-hover:scale-105" />
                                            <span class="absolute inset-0 flex items-center justify-center bg-black/0 text-white opacity-0 transition group-hover:bg-black/40 group-hover:opacity-100">
                                                <span class="rounded-full bg-white/20 px-3 py-1 text-[10px] font-black uppercase ring-1 ring-white/40 backdrop-blur">View photo</span>
                                            </span>
                                        </button>
                                        <p v-if="d.pod_notes" class="rounded-xl bg-slate-50 px-3 py-2 text-xs italic text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">“{{ d.pod_notes }}”</p>
                                        <p v-if="d.client_confirmed" class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-black uppercase text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                            <CheckCircle2 class="h-3 w-3" /> Client confirmed receipt
                                        </p>
                                    </div>
                                    <p v-else class="text-xs font-bold text-slate-400">No proof uploaded yet.</p>

                                    <div v-if="d.conductor_reports && d.conductor_reports.length" class="mt-3 space-y-1.5 border-t border-dashed border-slate-100 pt-3 dark:border-zinc-800">
                                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Conductor reports</p>
                                        <p v-for="(r, idx) in d.conductor_reports" :key="idx" class="text-[11px] text-slate-600 dark:text-zinc-300">
                                            {{ r.report_text }} <span class="text-slate-400">· {{ fdate(r.created_at) }}</span>
                                        </p>
                                    </div>
                                </section>

                            </div>

                            <p v-if="d.notes && !d.client_confirmed" class="mt-4 rounded-xl bg-amber-50 px-3 py-2 text-[11px] font-medium text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                                Note: {{ d.notes }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Empty -->
                <div v-else class="rounded-3xl border border-dashed border-gray-200 bg-white py-20 text-center dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="mb-4 inline-flex animate-bounce-soft rounded-full bg-gradient-to-br from-blue-50 to-indigo-50 p-5 dark:from-indigo-900/30 dark:to-violet-900/30">
                        <Truck class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">No deliveries found.</p>
                    <p class="mt-1 text-xs text-gray-400">Try clearing the filters or search another value.</p>
                </div>

            </div>
        </div>

        <!-- Image viewer -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="viewer.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm" @click.self="closeImage">
                    <div class="relative max-h-[90vh] w-full max-w-4xl overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="relative flex items-center justify-between overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 py-4 text-white">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <p class="relative flex items-center gap-2 text-sm font-black tracking-tight"><ImageIcon class="h-4 w-4" /> Proof of Delivery · {{ viewer.label }}</p>
                            <button @click="closeImage" class="relative rounded-xl bg-white/15 p-1.5 text-white transition hover:bg-white/30">
                                <X class="h-5 w-5" />
                            </button>
                        </div>
                        <img :src="viewer.url" class="max-h-[70vh] w-full bg-slate-100 object-contain dark:bg-zinc-950" alt="Proof of Delivery" />
                    </div>
                </div>
            </Transition>
        </Teleport>
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
.modal-enter-active, .modal-leave-active { transition: all 0.3s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: scale(0.95) translateY(12px); }
</style>
