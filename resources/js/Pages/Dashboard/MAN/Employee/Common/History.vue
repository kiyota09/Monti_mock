<script setup>
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { History, Search, Sparkles, CalendarDays, Package, X, Eye, Factory, Cog, ClipboardList, User, Layers } from 'lucide-vue-next';

const props = defineProps({
    roleLabel: String,
    historyRoute: String,
    dateColumn: { type: String, default: 'processed_at' },
    jobs: Object,
    // Dyeing lab chemist only: formulated fabric colors (mirrors the shade
    // page's CRM sample queue). Other roles omit it and see no new UI.
    sampleHistory: { type: Object, default: null },
});

const initialParams = new URLSearchParams(window.location.search);
const search = ref(initialParams.get('search') ?? '');
const from = ref(initialParams.get('from') ?? '');
const to = ref(initialParams.get('to') ?? '');

let timer = null;
const reload = () => {
    router.get(route(props.historyRoute), { search: search.value, from: from.value, to: to.value }, { preserveState: true, preserveScroll: true, replace: true });
};
watch([search, from, to], () => { clearTimeout(timer); timer = setTimeout(reload, 400); });

const fmtDate = (row) => {
    const raw = row[props.dateColumn] ?? row.created_at;
    if (!raw) return '—';
    return new Date(raw).toLocaleString();
};
const refCode = (row) => row.code ?? `#${row.id}`;
const isSampleRow = (row) => !!row && 'fabric_name' in row && 'color_description' in row;
const subLine = (row) => {
    if (isSampleRow(row)) return `${row.fabric_name} · ${row.color_description}`;
    if (row.fabric?.code) return `Fabric ${row.fabric.code}`;
    if (row.softenerJob?.fabric?.code) return `Fabric ${row.softenerJob.fabric.code}`;
    if (row.squeezerJob?.softenerJob?.fabric?.code) return `Fabric ${row.squeezerJob.softenerJob.fabric.code}`;
    if (row.yarn_type) return row.yarn_type;
    if (row.dye_type) return row.dye_type;
    if (row.softener_type) return row.softener_type;
    if (typeof row.quantity !== 'undefined') return `Qty ${row.quantity}`;
    return row.shift ?? '';
};

// ─── Detail modal (data comes from the already-loaded row — no extra requests,
//     so staff still only ever see their own jobs) ─────────────────────────────
const selected = ref(null);
const openDetail = (row) => { selected.value = row; document.body.style.overflow = 'hidden'; };
const closeDetail = () => { selected.value = null; document.body.style.overflow = ''; };
const onKey = (e) => { if (e.key === 'Escape') closeDetail(); };
onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));

const isFabricRow = (row) => !!row && 'yarn_type' in row && 'weight' in row && !row.fabric && !row.softenerJob && !row.squeezerJob;
const fabricOf = (row) => {
    if (!row) return null;
    if (isFabricRow(row)) return row;
    return row.fabric ?? row.softenerJob?.fabric ?? row.squeezerJob?.softenerJob?.fabric ?? null;
};
const machineOf = (row) => row?.machine ?? fabricOf(row)?.machine ?? row?.softenerJob?.machine ?? row?.squeezerJob?.machine ?? null;
const salesOrderOf = (row) => {
    const f = fabricOf(row);
    return f?.sales_order ?? f?.salesOrder ?? null;
};
const recipeOf = (row) => salesOrderOf(row)?.recipe ?? null;
const clientOf = (row) => salesOrderOf(row)?.client ?? null;
const productOf = (row) => recipeOf(row)?.product ?? null;
const operatorOf = (row) => row?.operator ?? fabricOf(row)?.operator ?? null;

const detailTitle = computed(() => selected.value ? refCode(selected.value) : '');
const detailFabric = computed(() => fabricOf(selected.value));
const detailMachine = computed(() => machineOf(selected.value));
const detailSO = computed(() => salesOrderOf(selected.value));
const detailRecipe = computed(() => recipeOf(selected.value));
const detailClient = computed(() => clientOf(selected.value));
const detailProduct = computed(() => productOf(selected.value));
const detailOperator = computed(() => operatorOf(selected.value));

const val = (v) => (v ?? v === 0) ? v : '—';

const sampleStatusClass = (s) => s === 'approved'
    ? 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30'
    : s === 'forwarded'
        ? 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30'
        : s === 'adjustment_requested'
            ? 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30'
            : ['cancelled', 'rejected'].includes(s)
                ? 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30'
                : 'bg-teal-100 text-teal-700 ring-teal-200 dark:bg-teal-500/15 dark:text-teal-300 dark:ring-teal-500/30';

const sampleStatusLabel = (s) => s ? String(s).replace(/_/g, ' ') : '—';

const getFullUrl = (path) => {
    if (!path) return '#';
    if (path.startsWith('http')) return path;
    const cleanPath = path.replace(/^\/?storage\//, '');
    return `/storage/${cleanPath}`;
};

const sampleMaterials = (row) => Object.entries(row?.recipe?.materials ?? {});
</script>

<template>
    <Head :title="`${roleLabel} · My History`" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <History class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> MAN · {{ roleLabel }}
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">My Work History</h1>
                            <p class="text-sm text-blue-100/90">Only your own output — click a reference for full details</p>
                        </div>
                        <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                            {{ jobs?.total ?? jobs?.data?.length ?? 0 }} records
                        </span>
                        <span v-if="sampleHistory" class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                            {{ sampleHistory?.total ?? sampleHistory?.data?.length ?? 0 }} colors
                        </span>
                    </div>
                </div>

                <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-4 sm:p-5 flex flex-wrap gap-3 items-end" style="animation-delay: 80ms">
                    <div class="flex-1 min-w-[220px]">
                        <label class="block text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1.5">Search code</label>
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input v-model="search" placeholder="e.g. FABRIC-2026-…" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 pl-9 pr-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1.5">From</label>
                        <input v-model="from" type="date" class="rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1.5">To</label>
                        <input v-model="to" type="date" class="rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
                    </div>
                    <span class="text-[11px] text-gray-400 flex items-center gap-1 pb-2"><CalendarDays class="h-3.5 w-3.5" /> Filters apply automatically</span>
                </div>

                <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden" style="animation-delay: 140ms">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50/80 dark:bg-zinc-800/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Detail</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Shift</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr v-for="row in jobs?.data ?? []" :key="row.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors">
                                    <td class="px-6 py-4">
                                        <button @click="openDetail(row)" :title="`View full details of ${refCode(row)}`"
                                            class="group inline-flex items-center gap-1.5 font-mono text-sm font-bold text-indigo-700 dark:text-indigo-300 hover:text-indigo-900 dark:hover:text-indigo-100 underline decoration-dotted decoration-indigo-300 underline-offset-4 hover:decoration-solid transition">
                                            {{ refCode(row) }}
                                            <Eye class="h-3.5 w-3.5 opacity-0 group-hover:opacity-100 transition-opacity" />
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ subLine(row) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ row.shift ?? '—' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ fmtDate(row) }}</td>
                                </tr>
                                <tr v-if="!(jobs?.data?.length)">
                                    <td colspan="4" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <div class="p-4 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-3 animate-bounce-soft">
                                                <Package class="h-7 w-7 text-indigo-400" />
                                            </div>
                                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No records found.</p>
                                            <p class="text-xs text-gray-400 mt-1">Try clearing the search or date filters.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="jobs?.links?.length > 3" class="flex flex-wrap gap-2 px-6 py-4 border-t border-gray-100 dark:border-zinc-800">
                        <Link v-for="link in jobs.links" :key="link.label" :href="link.url ?? '#'" v-text="link.label.replace('&laquo;', '«').replace('&raquo;', '»')" :class="['px-3 py-1.5 rounded-xl text-xs font-bold ring-1 transition', link.active ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white dark:bg-zinc-800 text-gray-600 dark:text-gray-300 ring-gray-200 dark:ring-zinc-700 hover:ring-indigo-300']" preserve-scroll />
                    </div>
                </div>

                <!-- Fabric colors formulated (dyeing lab chemist: mirrors the shade page sample queue) -->
                <div v-if="sampleHistory" class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden" style="animation-delay: 200ms">
                    <div class="flex items-center gap-2 px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                        <span class="h-2 w-2 rounded-full bg-teal-500 animate-pulse" />
                        <h2 class="text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Fabric Colors Formulated ({{ sampleHistory?.total ?? sampleHistory?.data?.length ?? 0 }})</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50/80 dark:bg-zinc-800/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fabric Color</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr v-for="row in sampleHistory?.data ?? []" :key="row.id" class="hover:bg-teal-50/50 dark:hover:bg-teal-950/20 transition-colors">
                                    <td class="px-6 py-4">
                                        <button @click="openDetail(row)" :title="`View full details of ${refCode(row)}`"
                                            class="group inline-flex items-center gap-1.5 font-mono text-sm font-bold text-teal-700 dark:text-teal-300 hover:text-teal-900 dark:hover:text-teal-100 underline decoration-dotted decoration-teal-300 underline-offset-4 hover:decoration-solid transition">
                                            {{ refCode(row) }}
                                            <Eye class="h-3.5 w-3.5 opacity-0 group-hover:opacity-100 transition-opacity" />
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ subLine(row) }}</td>
                                    <td class="px-6 py-4">
                                        <span :class="sampleStatusClass(row.status)" class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-black uppercase ring-1">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ sampleStatusLabel(row.status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ fmtDate(row) }}</td>
                                </tr>
                                <tr v-if="!(sampleHistory?.data?.length)">
                                    <td colspan="4" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <div class="p-4 bg-gradient-to-br from-teal-50 to-emerald-50 dark:from-teal-900/30 rounded-full mb-3 animate-bounce-soft">
                                                <Package class="h-7 w-7 text-teal-400" />
                                            </div>
                                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No formulated colors yet.</p>
                                            <p class="text-xs text-gray-400 mt-1">Samples you formulate on the shade page will appear here.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="sampleHistory?.links?.length > 3" class="flex flex-wrap gap-2 px-6 py-4 border-t border-gray-100 dark:border-zinc-800">
                        <Link v-for="link in sampleHistory.links" :key="link.label" :href="link.url ?? '#'" v-text="link.label.replace('&laquo;', '«').replace('&raquo;', '»')" :class="['px-3 py-1.5 rounded-xl text-xs font-bold ring-1 transition', link.active ? 'bg-teal-600 text-white ring-teal-600' : 'bg-white dark:bg-zinc-800 text-gray-600 dark:text-gray-300 ring-gray-200 dark:ring-zinc-700 hover:ring-teal-300']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail modal -->
        <Transition name="modal">
            <div v-if="selected" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-6" role="dialog" aria-modal="true">
                <div class="absolute inset-0 bg-zinc-950/60 backdrop-blur-sm" @click="closeDetail" />
                <div class="relative w-full sm:max-w-3xl max-h-[92vh] overflow-y-auto bg-white dark:bg-zinc-900 rounded-t-3xl sm:rounded-3xl shadow-2xl border border-gray-100 dark:border-zinc-800">
                    <div class="sticky top-0 z-10 overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 py-5">
                        <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                        <div class="relative flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 text-white"><Factory class="h-5 w-5" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-blue-100">MAN · {{ roleLabel }} · Full details</p>
                                <h2 class="font-mono text-lg font-black text-white truncate">{{ detailTitle }}</h2>
                            </div>
                            <button @click="closeDetail" aria-label="Close details"
                                class="rounded-xl bg-white/15 p-2 text-white ring-1 ring-white/25 hover:bg-white/25 active:scale-95 transition">
                                <X class="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                    <div class="space-y-4 p-6">
                        <!-- Fabric sample round (dyeing lab chemist) -->
                        <template v-if="isSampleRow(selected)">
                            <section class="rounded-2xl border border-gray-100 dark:border-zinc-800 overflow-hidden">
                                <header class="flex items-center gap-2 px-4 py-2.5 bg-gray-50/80 dark:bg-zinc-800/50 text-xs font-black uppercase tracking-wider text-gray-500"><Layers class="h-4 w-4 text-teal-500" /> Fabric color</header>
                                <dl class="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-3 px-4 py-4 text-sm">
                                    <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Reference</dt><dd class="font-mono font-bold text-gray-900 dark:text-white">{{ val(selected.code) }}</dd></div>
                                    <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Status</dt><dd><span :class="sampleStatusClass(selected.status)" class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-black uppercase ring-1"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ sampleStatusLabel(selected.status) }}</span></dd></div>
                                    <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Urgency</dt><dd class="text-gray-700 dark:text-gray-200">{{ val(selected.urgency) }}</dd></div>
                                    <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Fabric</dt><dd class="font-bold text-gray-900 dark:text-white">{{ val(selected.fabric_name) }}</dd></div>
                                    <div v-if="selected.product"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Product</dt><dd class="text-gray-700 dark:text-gray-200">{{ selected.product.name ?? `#${selected.product.id}` }}</dd></div>
                                    <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Formulated</dt><dd class="text-gray-700 dark:text-gray-200">{{ selected.updated_at ? new Date(selected.updated_at).toLocaleString() : '—' }}</dd></div>
                                    <div class="col-span-2 md:col-span-3"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Color description</dt><dd class="text-gray-700 dark:text-gray-200 whitespace-pre-wrap">{{ val(selected.color_description) }}</dd></div>
                                    <div v-if="selected.notes" class="col-span-2 md:col-span-3"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">CRM notes</dt><dd class="text-gray-700 dark:text-gray-200 whitespace-pre-wrap">{{ selected.notes }}</dd></div>
                                    <div v-if="selected.formulator"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Formulated by</dt><dd class="flex items-center gap-1 text-gray-700 dark:text-gray-200"><User class="h-3.5 w-3.5 text-gray-400" />{{ selected.formulator.name }}</dd></div>
                                    <div v-if="selected.inquiry"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Conversation ref</dt><dd class="font-mono font-bold text-gray-900 dark:text-white">{{ selected.inquiry.hash_key ?? `#${selected.inquiry.id}` }}</dd></div>
                                    <div v-if="selected.inquiry?.client"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Client</dt><dd class="text-gray-700 dark:text-gray-200">{{ selected.inquiry.client.company_name ?? `#${selected.inquiry.client.id}` }}</dd></div>
                                </dl>
                            </section>

                            <section v-if="selected.formula" class="rounded-2xl border border-gray-100 dark:border-zinc-800 overflow-hidden">
                                <header class="flex items-center gap-2 px-4 py-2.5 bg-gray-50/80 dark:bg-zinc-800/50 text-xs font-black uppercase tracking-wider text-gray-500"><ClipboardList class="h-4 w-4 text-teal-500" /> Formula</header>
                                <div class="px-4 py-4 space-y-3 text-sm">
                                    <div v-if="(selected.formula.yarns ?? []).length">
                                        <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1.5">Yarns</p>
                                        <ul class="divide-y divide-gray-100 dark:divide-zinc-800 rounded-xl border border-gray-100 dark:border-zinc-800 overflow-hidden">
                                            <li v-for="(y, i) in selected.formula.yarns" :key="i" class="flex flex-wrap gap-x-3 px-3 py-2 text-xs">
                                                <span class="font-bold text-gray-800 dark:text-gray-100">{{ y.name }}</span>
                                                <span v-if="y.qty !== '' && y.qty != null" class="ml-auto font-bold text-teal-600 dark:text-teal-300">{{ y.qty }} kg/kg</span>
                                            </li>
                                        </ul>
                                    </div>
                                    <div v-if="(selected.formula.dyestuffs ?? []).length">
                                        <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1.5">Dyestuffs</p>
                                        <ul class="divide-y divide-gray-100 dark:divide-zinc-800 rounded-xl border border-gray-100 dark:border-zinc-800 overflow-hidden">
                                            <li v-for="(d, i) in selected.formula.dyestuffs" :key="i" class="flex flex-wrap gap-x-3 px-3 py-2 text-xs">
                                                <span class="font-bold text-gray-800 dark:text-gray-100">{{ d.name }}</span>
                                                <span v-if="d.pct !== '' && d.pct != null" class="ml-auto font-bold text-teal-600 dark:text-teal-300">{{ d.pct }}%</span>
                                            </li>
                                        </ul>
                                    </div>
                                    <div v-if="(selected.formula.auxiliaries ?? []).length">
                                        <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1.5">Auxiliaries</p>
                                        <ul class="divide-y divide-gray-100 dark:divide-zinc-800 rounded-xl border border-gray-100 dark:border-zinc-800 overflow-hidden">
                                            <li v-for="(a, i) in selected.formula.auxiliaries" :key="i" class="flex flex-wrap gap-x-3 px-3 py-2 text-xs">
                                                <span class="font-bold text-gray-800 dark:text-gray-100">{{ a.name }}</span>
                                                <span v-if="a.gpl !== '' && a.gpl != null" class="ml-auto font-bold text-teal-600 dark:text-teal-300">{{ a.gpl }} g/L</span>
                                            </li>
                                        </ul>
                                    </div>
                                    <p v-if="selected.formula.notes" class="text-xs text-gray-600 dark:text-gray-300 italic">Notes: {{ selected.formula.notes }}</p>
                                </div>
                            </section>

                            <section v-if="selected.recipe" class="rounded-2xl border border-gray-100 dark:border-zinc-800 overflow-hidden">
                                <header class="flex items-center gap-2 px-4 py-2.5 bg-gray-50/80 dark:bg-zinc-800/50 text-xs font-black uppercase tracking-wider text-gray-500"><Factory class="h-4 w-4 text-teal-500" /> Recipe created</header>
                                <dl class="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-3 px-4 py-4 text-sm">
                                    <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Yarn type</dt><dd class="text-gray-700 dark:text-gray-200">{{ val(selected.recipe.yarn_type) }}</dd></div>
                                    <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Dye color</dt><dd class="text-gray-700 dark:text-gray-200">{{ val(selected.recipe.dye_color) }}</dd></div>
                                    <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Weave design</dt><dd class="text-gray-700 dark:text-gray-200">{{ val(selected.recipe.weave_design) }}</dd></div>
                                </dl>
                                <div v-if="sampleMaterials(selected).length" class="px-4 pb-4">
                                    <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1.5">Recipe materials ({{ sampleMaterials(selected).length }})</p>
                                    <ul class="divide-y divide-gray-100 dark:divide-zinc-800 rounded-xl border border-gray-100 dark:border-zinc-800 overflow-hidden">
                                        <li v-for="[name, qty] in sampleMaterials(selected)" :key="name" class="flex flex-wrap gap-x-3 px-3 py-2 text-xs">
                                            <span class="font-bold text-gray-800 dark:text-gray-100">{{ name }}</span>
                                            <span class="ml-auto font-bold text-teal-600 dark:text-teal-300">{{ qty }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </section>

                            <section v-if="selected.sample_image_path" class="rounded-2xl border border-gray-100 dark:border-zinc-800 overflow-hidden">
                                <header class="flex items-center gap-2 px-4 py-2.5 bg-gray-50/80 dark:bg-zinc-800/50 text-xs font-black uppercase tracking-wider text-gray-500"><Eye class="h-4 w-4 text-teal-500" /> Sample photo</header>
                                <div class="p-4">
                                    <a :href="getFullUrl(selected.sample_image_path)" target="_blank" class="block overflow-hidden rounded-2xl border border-gray-100 dark:border-zinc-800 hover:opacity-95 transition">
                                        <img :src="getFullUrl(selected.sample_image_path)" :alt="`Sample ${selected.code}`" class="max-h-96 w-full object-contain bg-gray-50 dark:bg-zinc-800" />
                                    </a>
                                </div>
                            </section>
                        </template>

                        <!-- This job -->
                        <section class="rounded-2xl border border-gray-100 dark:border-zinc-800 overflow-hidden">
                            <header class="flex items-center gap-2 px-4 py-2.5 bg-gray-50/80 dark:bg-zinc-800/50 text-xs font-black uppercase tracking-wider text-gray-500"><ClipboardList class="h-4 w-4 text-indigo-500" /> My job record</header>
                            <dl class="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-3 px-4 py-4 text-sm">
                                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Reference</dt><dd class="font-mono font-bold text-gray-900 dark:text-white">{{ val(selected.code) }}</dd></div>
                                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Status</dt><dd class="font-bold text-gray-900 dark:text-white">{{ val(selected.status) }}</dd></div>
                                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Shift</dt><dd class="text-gray-700 dark:text-gray-200">{{ val(selected.shift) }}</dd></div>
                                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Processed</dt><dd class="text-gray-700 dark:text-gray-200">{{ fmtDate(selected) }}</dd></div>
                                <div v-if="selected.dye_type"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Dye type</dt><dd class="text-gray-700 dark:text-gray-200">{{ selected.dye_type }} <span v-if="selected.chemical_no" class="font-mono text-xs text-gray-400">({{ selected.chemical_no }})</span></dd></div>
                                <div v-if="selected.softener_type"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Softener</dt><dd class="text-gray-700 dark:text-gray-200">{{ selected.softener_type }} <span v-if="selected.softener_no" class="font-mono text-xs text-gray-400">({{ selected.softener_no }})</span></dd></div>
                                <div v-if="typeof selected.quantity !== 'undefined'"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Quantity</dt><dd class="font-bold text-gray-900 dark:text-white">{{ selected.quantity }}</dd></div>
                                <div v-if="selected.weight"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Weight (kg)</dt><dd class="text-gray-700 dark:text-gray-200">{{ selected.weight }}</dd></div>
                                <div v-if="selected.yarn_type"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Yarn type</dt><dd class="text-gray-700 dark:text-gray-200">{{ selected.yarn_type }}</dd></div>
                                <div class="col-span-2 md:col-span-3"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Remarks</dt><dd class="text-gray-700 dark:text-gray-200">{{ val(selected.remarks) }}</dd></div>
                                <div v-if="detailOperator"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Operator</dt><dd class="flex items-center gap-1 text-gray-700 dark:text-gray-200"><User class="h-3.5 w-3.5 text-gray-400" />{{ detailOperator.name }}</dd></div>
                            </dl>
                            <div v-if="selected.chemicals?.length" class="px-4 pb-4">
                                <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1.5">Dye chemicals consumed ({{ selected.chemicals.length }})</p>
                                <ul class="divide-y divide-gray-100 dark:divide-zinc-800 rounded-xl border border-gray-100 dark:border-zinc-800 overflow-hidden">
                                    <li v-for="c in selected.chemicals" :key="c.id" class="flex flex-wrap gap-x-3 gap-y-0.5 px-3 py-2 text-xs">
                                        <span class="font-bold text-gray-800 dark:text-gray-100">{{ c.dye_type }}</span>
                                        <span class="font-mono text-gray-400">{{ c.control_number }}</span>
                                        <span class="ml-auto font-bold text-indigo-600 dark:text-indigo-300">{{ c.quantity_used }}</span>
                                    </li>
                                </ul>
                            </div>
                            <div v-if="selected.items?.length" class="px-4 pb-4">
                                <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1.5">Package items ({{ selected.items.length }})</p>
                                <ul class="divide-y divide-gray-100 dark:divide-zinc-800 rounded-xl border border-gray-100 dark:border-zinc-800 overflow-hidden">
                                    <li v-for="it in selected.items" :key="it.id" class="flex flex-wrap gap-x-3 px-3 py-2 text-xs text-gray-600 dark:text-gray-300">
                                        <span class="font-mono font-bold">{{ it.code ?? `#${it.id}` }}</span>
                                        <span v-if="it.size">Size {{ it.size }}</span>
                                        <span v-if="it.quantity">Qty {{ it.quantity }}</span>
                                    </li>
                                </ul>
                            </div>
                        </section>

                        <!-- Fabric -->
                        <section v-if="detailFabric" class="rounded-2xl border border-gray-100 dark:border-zinc-800 overflow-hidden">
                            <header class="flex items-center gap-2 px-4 py-2.5 bg-gray-50/80 dark:bg-zinc-800/50 text-xs font-black uppercase tracking-wider text-gray-500"><Layers class="h-4 w-4 text-indigo-500" /> Fabric handled</header>
                            <dl class="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-3 px-4 py-4 text-sm">
                                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Fabric code</dt><dd class="font-mono font-bold text-gray-900 dark:text-white">{{ val(detailFabric.code) }}</dd></div>
                                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Yarn type</dt><dd class="text-gray-700 dark:text-gray-200">{{ val(detailFabric.yarn_type) }}</dd></div>
                                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Weight (kg)</dt><dd class="text-gray-700 dark:text-gray-200">{{ val(detailFabric.weight) }}</dd></div>
                                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Stage status</dt><dd><span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 dark:bg-indigo-500/15 px-2.5 py-0.5 text-[11px] font-black text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-200 dark:ring-indigo-500/30"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ val(detailFabric.status) }}</span></dd></div>
                                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Shift</dt><dd class="text-gray-700 dark:text-gray-200">{{ val(detailFabric.shift) }}</dd></div>
                                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Processed</dt><dd class="text-gray-700 dark:text-gray-200">{{ detailFabric.processed_at ? new Date(detailFabric.processed_at).toLocaleString() : '—' }}</dd></div>
                                <div v-if="detailFabric.rejection_reason || detailFabric.rejection_action" class="col-span-2 md:col-span-3"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Rejection</dt><dd class="text-gray-700 dark:text-gray-200">{{ val(detailFabric.rejection_action) }} — {{ val(detailFabric.rejection_reason) }}</dd></div>
                                <div v-if="detailFabric.remarks" class="col-span-2 md:col-span-3"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Fabric remarks</dt><dd class="text-gray-700 dark:text-gray-200">{{ detailFabric.remarks }}</dd></div>
                            </dl>
                            <div v-if="detailFabric.dye_jobs?.length || detailFabric.softener_jobs?.length || detailFabric.packages?.length" class="px-4 pb-4 flex flex-wrap gap-2 text-[11px] font-bold">
                                <span v-if="detailFabric.dye_jobs?.length" class="rounded-full bg-gray-100 dark:bg-zinc-800 px-2.5 py-1 text-gray-600 dark:text-gray-300">{{ detailFabric.dye_jobs.length }} dye job(s)</span>
                                <span v-if="detailFabric.softener_jobs?.length" class="rounded-full bg-gray-100 dark:bg-zinc-800 px-2.5 py-1 text-gray-600 dark:text-gray-300">{{ detailFabric.softener_jobs.length }} softener job(s)</span>
                                <span v-if="detailFabric.packages?.length" class="rounded-full bg-gray-100 dark:bg-zinc-800 px-2.5 py-1 text-gray-600 dark:text-gray-300">{{ detailFabric.packages.length }} package(s)</span>
                            </div>
                        </section>

                        <!-- Machine -->
                        <section v-if="detailMachine" class="rounded-2xl border border-gray-100 dark:border-zinc-800 overflow-hidden">
                            <header class="flex items-center gap-2 px-4 py-2.5 bg-gray-50/80 dark:bg-zinc-800/50 text-xs font-black uppercase tracking-wider text-gray-500"><Cog class="h-4 w-4 text-indigo-500" /> Machine</header>
                            <dl class="grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-3 px-4 py-4 text-sm">
                                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Machine no.</dt><dd class="font-mono font-bold text-gray-900 dark:text-white">{{ val(detailMachine.machine_no) }}</dd></div>
                                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Type</dt><dd class="text-gray-700 dark:text-gray-200">{{ val(detailMachine.type) }}</dd></div>
                                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Status</dt><dd class="text-gray-700 dark:text-gray-200">{{ val(detailMachine.status) }}</dd></div>
                                <div v-if="detailMachine.remarks"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Remarks</dt><dd class="text-gray-700 dark:text-gray-200">{{ detailMachine.remarks }}</dd></div>
                            </dl>
                        </section>

                        <!-- Job order / client / recipe -->
                        <section v-if="detailSO" class="rounded-2xl border border-gray-100 dark:border-zinc-800 overflow-hidden">
                            <header class="flex items-center gap-2 px-4 py-2.5 bg-gray-50/80 dark:bg-zinc-800/50 text-xs font-black uppercase tracking-wider text-gray-500"><ClipboardList class="h-4 w-4 text-indigo-500" /> Job order &amp; recipe</header>
                            <dl class="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-3 px-4 py-4 text-sm">
                                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">JO number</dt><dd class="font-mono font-bold text-gray-900 dark:text-white">{{ val(detailSO.jo_number) }}</dd></div>
                                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Color</dt><dd class="text-gray-700 dark:text-gray-200">{{ val(detailSO.color) }}</dd></div>
                                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Design</dt><dd class="text-gray-700 dark:text-gray-200">{{ val(detailSO.design) }}</dd></div>
                                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Order qty</dt><dd class="text-gray-700 dark:text-gray-200">{{ val(detailSO.quantity) }}</dd></div>
                                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Status</dt><dd class="text-gray-700 dark:text-gray-200">{{ val(detailSO.status) }}</dd></div>
                                <div v-if="detailClient"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Client</dt><dd class="text-gray-700 dark:text-gray-200">{{ detailClient.company_name ?? `#${detailClient.id}` }}</dd></div>
                                <div v-if="detailRecipe"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Recipe yarn</dt><dd class="text-gray-700 dark:text-gray-200">{{ val(detailRecipe.yarn_type) }}</dd></div>
                                <div v-if="detailRecipe"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Dye color</dt><dd class="text-gray-700 dark:text-gray-200">{{ val(detailRecipe.dye_color) }}</dd></div>
                                <div v-if="detailRecipe"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Weave design</dt><dd class="text-gray-700 dark:text-gray-200">{{ val(detailRecipe.weave_design) }}</dd></div>
                                <div v-if="detailProduct"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Product</dt><dd class="text-gray-700 dark:text-gray-200">{{ detailProduct.name ?? `#${detailProduct.id}` }}</dd></div>
                            </dl>
                        </section>
                        <p v-else-if="!isSampleRow(selected)" class="text-center text-xs text-gray-400">No linked job order for this record (unlinked fabric).</p>

                        <button @click="closeDetail" class="w-full rounded-2xl bg-indigo-600 px-4 py-3 text-xs font-black uppercase tracking-wide text-white shadow-lg shadow-indigo-500/25 hover:bg-indigo-700 active:scale-[0.99] transition">Close</button>
                    </div>
                </div>
            </div>
        </Transition>
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
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-active > div:last-child, .modal-leave-active > div:last-child { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from > div:last-child { transform: translateY(24px) scale(0.98); }
.modal-leave-to > div:last-child { transform: translateY(12px) scale(0.99); }
</style>
