<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    ScanSearch, Search, Package, Factory, Truck, User, FlaskConical,
    CheckCircle2, TriangleAlert, CreditCard, ClipboardList, ArrowRight,
} from 'lucide-vue-next';

const props = defineProps({ filters: Object, trace: Object, recentFabrics: Array, recentPackages: Array, recentLots: Array });

const mode = ref(props.filters?.mode || 'unit');
const q = ref(props.filters?.q || '');

const search = (term) => {
    if (term !== undefined) q.value = term;
    if (!q.value.trim()) return;
    router.get(route('ceo.traceability'), { mode: mode.value, q: q.value.trim() },
        { preserveState: true, preserveScroll: true, replace: true });
};
const setMode = (m) => { mode.value = m; if (q.value.trim()) search(); };

const fdate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
const ftime = (d) => d ? new Date(d).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';
const peso = (v) => '₱' + Number(v || 0).toLocaleString('en-PH', { maximumFractionDigits: 0 });

const stageIcon = (s) => ({
    Knitting: Factory, Dyeing: FlaskConical, Softener: FlaskConical, Squeezer: Factory,
    Ironing: Factory, Packaging: Package,
}[s] || ClipboardList);
</script>

<template>
    <AuthenticatedLayout>
        <Head title="CEO Traceability" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="mx-auto max-w-6xl space-y-5 p-4 pb-16 sm:p-6">

                <div class="relative overflow-hidden rounded-3xl bg-slate-900 p-6 text-white shadow-xl sm:p-7">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800" />
                    <div class="absolute -top-20 -right-16 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-13 w-13 items-center justify-center rounded-2xl bg-white/15 p-3 ring-1 ring-white/30">
                            <ScanSearch class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">CEO · Oversight</p>
                            <h1 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">Material Traceability</h1>
                            <p class="mt-1 text-sm text-blue-100/90">Follow any raw lot to finished product — every hand it passed through.</p>
                        </div>
                    </div>
                    <div class="relative mt-5 flex flex-col gap-2 sm:flex-row">
                        <div class="flex shrink-0 gap-1 rounded-xl bg-white/15 p-1 ring-1 ring-white/25">
                            <button @click="setMode('unit')" :class="mode === 'unit' ? 'bg-white dark:bg-zinc-900 text-indigo-700 dark:text-indigo-300 shadow' : 'text-white/80'"
                                class="rounded-lg px-4 py-2 text-[11px] font-black uppercase tracking-wide transition">Finished unit</button>
                            <button @click="setMode('lot')" :class="mode === 'lot' ? 'bg-white dark:bg-zinc-900 text-indigo-700 dark:text-indigo-300 shadow' : 'text-white/80'"
                                class="rounded-lg px-4 py-2 text-[11px] font-black uppercase tracking-wide transition">Raw lot</button>
                        </div>
                        <div class="relative flex-1">
                            <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-zinc-500" />
                            <input v-model="q" @keyup.enter="search()" :placeholder="mode === 'lot' ? 'Control number, e.g. CTL-… (see lots below)' : 'Fabric code, package no, JO or PO number…'"
                                class="w-full rounded-xl border-0 bg-white/95 dark:bg-zinc-900/95 py-3 pl-10 pr-4 text-sm font-medium text-gray-900 dark:text-zinc-100 outline-none placeholder:text-gray-400 focus:ring-2 focus:ring-white/70" />
                        </div>
                        <button @click="search()"
                            class="rounded-xl bg-white dark:bg-zinc-900 px-5 py-3 text-xs font-extrabold uppercase tracking-wide text-indigo-700 dark:text-indigo-300 shadow transition hover:bg-indigo-50 active:scale-95">Trace</button>
                    </div>
                </div>

                <!-- ── RESULT ── -->
                <div v-if="trace && !trace.found" class="rounded-3xl border border-amber-200 bg-amber-50 dark:bg-amber-900/20 p-6 text-center dark:border-amber-800">
                    <TriangleAlert class="mx-auto h-8 w-8 text-amber-500" />
                    <p class="mt-2 text-sm font-black">No match for “{{ trace.query }}”</p>
                    <p class="mt-1 text-xs font-bold text-slate-500 dark:text-zinc-400">{{ trace.hint }}</p>
                </div>

                <!-- LOT TRACE -->
                <div v-if="trace?.found && trace.kind === 'lot'" class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-4">
                        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Material</p>
                            <p class="mt-1 font-black">{{ trace.summary.material }}</p>
                            <p class="text-xs font-bold text-slate-400 dark:text-zinc-500">{{ trace.summary.quantity }} · {{ trace.summary.status }}</p>
                        </div>
                        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Warehouse</p>
                            <p class="mt-1 font-black">{{ trace.summary.warehouse }}</p>
                            <p class="text-xs font-bold text-slate-400 dark:text-zinc-500">{{ trace.summary.location }}</p>
                        </div>
                        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Received</p>
                            <p class="mt-1 font-black">{{ fdate(trace.origin.received_at) }}</p>
                            <p class="text-xs font-bold text-slate-400 dark:text-zinc-500">by {{ trace.origin.received_by || '—' }}</p>
                        </div>
                        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Supplier PO</p>
                            <p class="mt-1 font-mono font-black">{{ trace.origin.scm_po || '—' }}</p>
                            <p class="text-xs font-bold text-slate-400 dark:text-zinc-500">{{ trace.origin.supplier || '' }}</p>
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-3">
                        <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400 dark:text-zinc-500">Inbound receipt</h3>
                            <div v-if="trace.inbound" class="mt-3 space-y-1.5 text-xs">
                                <p><span class="font-bold text-slate-400 dark:text-zinc-500">Receipt:</span> <span class="font-mono font-black">{{ trace.inbound.receiving_number }}</span></p>
                                <p><span class="font-bold text-slate-400 dark:text-zinc-500">Date:</span> <strong>{{ fdate(trace.inbound.received_at) }}</strong></p>
                                <p><span class="font-bold text-slate-400 dark:text-zinc-500">Status:</span> <strong class="uppercase">{{ trace.inbound.status }}</strong></p>
                                <div v-for="(l, i) in trace.inbound.lines" :key="i" class="rounded-xl bg-slate-50 p-2.5 dark:bg-zinc-800">
                                    <p class="font-black">{{ l.material }}</p>
                                    <p class="text-slate-500 dark:text-zinc-400">Exp {{ l.expected }} · Got {{ l.received }} · Rej {{ l.rejected }} ({{ l.status }})</p>
                                </div>
                            </div>
                            <p v-else class="mt-2 text-xs font-bold text-slate-400 dark:text-zinc-500">No receiving record linked.</p>
                        </div>
                        <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <h3 class="flex items-center gap-1.5 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400 dark:text-zinc-500"><CreditCard class="h-3.5 w-3.5" /> Supplier billing</h3>
                            <div v-if="trace.billing" class="mt-3 space-y-1.5 text-xs">
                                <p><span class="font-bold text-slate-400 dark:text-zinc-500">Invoice:</span> <span class="font-mono font-black">{{ trace.billing.invoice_number }}</span></p>
                                <p><span class="font-bold text-slate-400 dark:text-zinc-500">Amount:</span> <strong>{{ peso(trace.billing.amount) }}</strong> · <strong class="uppercase">{{ trace.billing.status }}</strong></p>
                                <p><span class="font-bold text-slate-400 dark:text-zinc-500">Due:</span> <strong>{{ fdate(trace.billing.due_date) }}</strong></p>
                                <p><span class="font-bold text-slate-400 dark:text-zinc-500">Paid:</span> <strong class="text-emerald-600 dark:text-emerald-400">{{ trace.billing.paid_at ? fdate(trace.billing.paid_at) + ' · ' + trace.billing.pay_method : '—' }}</strong></p>
                            </div>
                            <p v-else class="mt-2 text-xs font-bold text-slate-400 dark:text-zinc-500">No supplier invoice linked.</p>
                        </div>
                        <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400 dark:text-zinc-500">Released to production</h3>
                            <div v-if="trace.release" class="mt-3 space-y-1.5 text-xs">
                                <p><span class="font-bold text-slate-400 dark:text-zinc-500">Dept:</span> <strong class="uppercase">{{ trace.release.department }}</strong></p>
                                <p><span class="font-bold text-slate-400 dark:text-zinc-500">Qty:</span> <strong>{{ trace.release.qty }}</strong> · left <strong>{{ trace.release.remaining }}</strong></p>
                                <p><span class="font-bold text-slate-400 dark:text-zinc-500">By:</span> <strong>{{ trace.release.released_by || '—' }}</strong> · {{ fdate(trace.release.released_at) }}</p>
                            </div>
                            <p v-else class="mt-2 text-xs font-bold text-slate-400 dark:text-zinc-500">Still in warehouse — not released.</p>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400 dark:text-zinc-500">Dye-job consumption of this lot ({{ trace.consumptions.length }})</h3>
                        <div v-if="trace.consumptions.length" class="mt-3 space-y-2">
                            <div v-for="(c, i) in trace.consumptions" :key="i" class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-2xl bg-slate-50 px-4 py-3 text-xs dark:bg-zinc-800">
                                <span class="font-mono font-black">{{ c.dye_job }}</span>
                                <span><strong>{{ c.qty_used }}kg</strong> into fabric <button @click="search(c.fabric)" class="font-mono font-black text-indigo-600 dark:text-indigo-400 hover:underline">{{ c.fabric }}</button></span>
                                <span class="text-slate-500 dark:text-zinc-400">by {{ c.operator || '—' }} · {{ fdate(c.processed_at) }} · {{ c.client || '' }}</span>
                            </div>
                        </div>
                        <p v-else class="mt-2 text-xs font-bold text-slate-400 dark:text-zinc-500">No dye-job consumption recorded.</p>
                        <p v-if="trace.note" class="mt-3 rounded-2xl bg-amber-50 dark:bg-amber-900/20 px-4 py-3 text-[11px] font-bold text-amber-700 dark:text-amber-300">{{ trace.note }}</p>
                    </div>
                </div>

                <!-- UNIT / ORDER TRACE -->
                <div v-if="trace?.found && (trace.kind === 'unit' || trace.kind === 'order')" class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-4">
                        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Client (ordered by)</p>
                            <p class="mt-1 font-black">{{ trace.summary.client || '—' }}</p>
                            <p class="text-xs font-bold text-slate-400 dark:text-zinc-500">{{ trace.summary.jo || '' }} · {{ trace.summary.po || '' }}</p>
                        </div>
                        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Order value</p>
                            <p class="mt-1 font-black">{{ peso(trace.summary.ordered_total) }}</p>
                            <p class="text-xs font-bold" :class="trace.summary.po_payment === 'paid' ? 'text-emerald-600' : 'text-amber-600'">PO {{ trace.summary.po_payment || 'unpaid' }}</p>
                        </div>
                        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Fabric</p>
                            <p class="mt-1 font-mono font-black">{{ trace.summary.fabric || '—' }}</p>
                            <p class="text-xs font-bold text-slate-400 dark:text-zinc-500">{{ trace.summary.yarn || '' }} · {{ trace.summary.weight || '' }}kg</p>
                        </div>
                        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500">Status</p>
                            <p class="mt-1 font-black uppercase">{{ trace.summary.status || '—' }}</p>
                            <p class="text-xs font-bold text-slate-400 dark:text-zinc-500">{{ trace.stages.length }} process step(s) · {{ trace.deliveries.length }} deliver(ies)</p>
                        </div>
                    </div>

                    <p v-if="trace.note" class="rounded-2xl border border-amber-200 bg-amber-50 dark:bg-amber-900/20 px-4 py-3 text-xs font-bold text-amber-700 dark:text-amber-300 dark:border-amber-800">{{ trace.note }}</p>

                    <div v-if="trace.stages.length" class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
                        <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400 dark:text-zinc-500">Process trail — knit to pack</h3>
                        <ol class="relative mt-4 space-y-0 border-l-2 border-indigo-100 pl-0 dark:border-indigo-900/40">
                            <li v-for="(s, i) in trace.stages" :key="i" class="relative pb-5 pl-8 last:pb-0">
                                <span class="absolute -left-[9px] top-0 flex h-4 w-4 items-center justify-center rounded-full bg-indigo-600 ring-4 ring-white dark:ring-zinc-900" />
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <component :is="stageIcon(s.stage)" class="h-4 w-4 text-indigo-500" />
                                    <span class="text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400">{{ s.stage }}</span>
                                    <span class="font-mono text-xs font-black">{{ s.code }}</span>
                                    <span class="ml-auto text-[11px] font-bold text-slate-400 dark:text-zinc-500">{{ ftime(s.at) }}</span>
                                </div>
                                <p class="mt-1 text-xs">
                                    <span class="inline-flex items-center gap-1 font-bold"><User class="h-3 w-3 text-slate-400 dark:text-zinc-500" /> {{ s.operator || '—' }}</span>
                                    <span v-if="s.machine" class="ml-3 text-slate-500 dark:text-zinc-400">Machine {{ s.machine }}</span>
                                    <span v-if="s.detail" class="ml-3 text-slate-500 dark:text-zinc-400">{{ s.detail }}</span>
                                    <span v-if="s.status" class="ml-3 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase dark:bg-zinc-800">{{ s.status }}</span>
                                </p>
                                <div v-if="s.lots?.length" class="mt-1.5 flex flex-wrap gap-1.5">
                                    <button v-for="l in s.lots" :key="l.lot" @click="mode = 'lot'; search(l.lot)"
                                        class="rounded-lg bg-amber-50 px-2.5 py-1 font-mono text-[10px] font-black text-amber-700 dark:text-amber-300 ring-1 ring-amber-200 hover:bg-amber-100 dark:bg-amber-900/30">
                                        lot {{ l.lot }} · {{ l.used }}kg
                                    </button>
                                </div>
                            </li>
                        </ol>
                    </div>

                    <div v-if="trace.deliveries.length" class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
                        <h3 class="flex items-center gap-1.5 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400 dark:text-zinc-500"><Truck class="h-4 w-4" /> Deliveries ({{ trace.deliveries.length }})</h3>
                        <div class="mt-3 space-y-2">
                            <div v-for="d in trace.deliveries" :key="d.delivery_number" class="rounded-2xl bg-slate-50 px-4 py-3 text-xs dark:bg-zinc-800">
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                                    <span class="font-mono font-black">{{ d.delivery_number }}</span>
                                    <span class="rounded-full bg-white px-2.5 py-0.5 text-[10px] font-black uppercase dark:bg-zinc-900">{{ d.status }}</span>
                                    <span class="ml-auto font-bold text-slate-500 dark:text-zinc-400">{{ fdate(d.arrived) }}</span>
                                </div>
                                <p class="mt-1.5 text-slate-600 dark:text-zinc-400">
                                    Truck <strong>{{ d.truck }}</strong> · driver <strong>{{ d.driver }}</strong> · {{ d.route }}
                                    · dep {{ ftime(d.departed) }} · POD {{ ftime(d.pod_at) }}
                                    <span v-if="d.client_confirmed" class="ml-1 font-black text-emerald-600 dark:text-emerald-400">· client confirmed ✓</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── QUICK PICK ── -->
                <div v-if="!trace" class="grid gap-4 lg:grid-cols-3">
                    <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <h3 class="flex items-center gap-1.5 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400 dark:text-zinc-500"><Factory class="h-4 w-4" /> Recent fabrics</h3>
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            <button v-for="f in recentFabrics" :key="f.code" @click="mode = 'unit'; search(f.code)"
                                class="rounded-xl bg-slate-100 px-3 py-1.5 font-mono text-[11px] font-black hover:bg-indigo-100 dark:hover:bg-indigo-900/40 hover:text-indigo-700 dark:text-indigo-300 dark:bg-zinc-800">{{ f.code }}</button>
                        </div>
                    </div>
                    <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <h3 class="flex items-center gap-1.5 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400 dark:text-zinc-500"><Package class="h-4 w-4" /> Recent packages</h3>
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            <button v-for="p in recentPackages" :key="p.number" @click="mode = 'unit'; search(p.number)"
                                class="rounded-xl bg-slate-100 px-3 py-1.5 font-mono text-[11px] font-black hover:bg-indigo-100 dark:hover:bg-indigo-900/40 hover:text-indigo-700 dark:text-indigo-300 dark:bg-zinc-800">{{ p.number }}</button>
                        </div>
                    </div>
                    <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <h3 class="flex items-center gap-1.5 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400 dark:text-zinc-500"><ClipboardList class="h-4 w-4" /> Recent raw lots</h3>
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            <button v-for="l in recentLots" :key="l.control" @click="mode = 'lot'; search(l.control)"
                                class="rounded-xl bg-slate-100 px-3 py-1.5 font-mono text-[11px] font-black hover:bg-amber-100 dark:bg-amber-900/30 hover:text-amber-700 dark:text-amber-300">{{ l.control }}</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
