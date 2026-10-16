<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Printer, Banknote, ShoppingCart, Users, Factory, PackageCheck,
    TrendingUp, TriangleAlert, FileText, Stamp, CalendarDays, Cog,
} from 'lucide-vue-next';

const props = defineProps({ generatedAt: String, kpis: Object, trends: Object, decisions: Array });

const peso = (v) => '₱' + new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(v ?? 0);
const pesoCompact = (v) => {
    const n = Number(v ?? 0);
    if (Math.abs(n) >= 1_000_000) return '₱' + (n / 1_000_000).toFixed(1) + 'M';
    if (Math.abs(n) >= 1_000) return '₱' + (n / 1_000).toFixed(0) + 'k';
    return '₱' + n;
};
const num = (v) => new Intl.NumberFormat('en-US').format(v ?? 0);
const fdate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
const printPack = () => window.print();

const refNo = computed(() => {
    const d = new Date();
    return `MONTI-BP-${d.getFullYear()}${String(d.getMonth() + 1).padStart(2, '0')}`;
});

const margin = computed(() => Number(props.kpis?.revenue_approved ?? 0) - Number(props.kpis?.procurement_spend ?? 0));

const maxMoney = computed(() => Math.max(1,
    ...(props.trends?.revenue || []), ...(props.trends?.spend || []), ...(props.trends?.payroll || [])));
const maxOutput = computed(() => Math.max(1, ...(props.trends?.output || []), ...(props.trends?.rejects || [])));

const kpiCards = computed(() => [
    { label: 'Approved revenue', value: peso(props.kpis?.revenue_approved), icon: Banknote, tone: 'emerald' },
    { label: 'Procurement spend', value: peso(props.kpis?.procurement_spend), icon: ShoppingCart, tone: 'blue' },
    { label: 'Gross margin', value: peso(margin.value), icon: TrendingUp, tone: margin.value >= 0 ? 'emerald' : 'rose' },
    { label: 'Payroll cost', value: peso(props.kpis?.payroll_cost), icon: Users, tone: 'violet' },
    { label: 'Unpaid invoices', value: peso(props.kpis?.invoices_unpaid), icon: FileText, tone: 'amber' },
    { label: 'Fabrics produced', value: num(props.kpis?.fabrics_total), icon: Factory, tone: 'indigo' },
    { label: 'Reject rate', value: `${props.kpis?.reject_rate ?? 0}%`, icon: TriangleAlert, tone: Number(props.kpis?.reject_rate ?? 0) > 5 ? 'rose' : 'emerald' },
    { label: 'Jobs in production', value: num(props.kpis?.jobs_in_production), icon: Cog, tone: 'cyan' },
    { label: 'Packages delivered', value: num(props.kpis?.packages_delivered), icon: PackageCheck, tone: 'emerald' },
    { label: 'Leads open / won', value: `${num(props.kpis?.leads_open)} / ${num(props.kpis?.leads_won)}`, icon: TrendingUp, tone: 'blue' },
    { label: 'Active headcount', value: num(props.kpis?.headcount), icon: Users, tone: 'slate' },
    { label: 'Machines down', value: num(props.kpis?.machines_down), icon: TriangleAlert, tone: Number(props.kpis?.machines_down ?? 0) > 0 ? 'amber' : 'emerald' },
]);

const toneBar = {
    emerald: 'bg-emerald-500', blue: 'bg-blue-600', violet: 'bg-violet-500', amber: 'bg-amber-500',
    indigo: 'bg-indigo-500', rose: 'bg-rose-500', cyan: 'bg-cyan-500', slate: 'bg-slate-400',
};
const toneText = {
    emerald: 'text-emerald-700', blue: 'text-blue-700', violet: 'text-violet-700', amber: 'text-amber-700',
    indigo: 'text-indigo-700', rose: 'text-rose-700', cyan: 'text-cyan-700', slate: 'text-slate-600',
};
const toneBg = {
    emerald: 'bg-emerald-50', blue: 'bg-blue-50', violet: 'bg-violet-50', amber: 'bg-amber-50',
    indigo: 'bg-indigo-50', rose: 'bg-rose-50', cyan: 'bg-cyan-50', slate: 'bg-slate-100',
};
</script>

<template>
    <Head title="Board Pack" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-slate-100 dark:bg-zinc-800 print:bg-white">
            <div class="mx-auto max-w-6xl space-y-5 p-4 pb-16 sm:p-6 print:m-0 print:max-w-none print:p-0 print:pb-0 print:space-y-0">

                <!-- ═══ SCREEN HERO (never printed) ═══ -->
                <div class="print:hidden relative overflow-hidden rounded-3xl bg-slate-900 p-6 text-white shadow-xl sm:p-7">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800" />
                    <div class="absolute -top-20 -right-16 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-13 w-13 items-center justify-center rounded-2xl bg-white/15 p-3 ring-1 ring-white/30">
                            <FileText class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">CEO · Board of Directors</p>
                            <h1 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">Monthly Board Pack</h1>
                            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-blue-100/90">
                                <span class="inline-flex items-center gap-1.5"><CalendarDays class="h-3.5 w-3.5" /> {{ generatedAt }}</span>
                                <span class="font-mono text-xs">Ref {{ refNo }} · Confidential</span>
                            </p>
                        </div>
                        <button @click="printPack"
                            class="inline-flex items-center gap-2 rounded-xl bg-white dark:bg-zinc-900 px-5 py-3 text-xs font-extrabold uppercase tracking-wide text-indigo-700 dark:text-indigo-300 shadow-lg transition hover:bg-indigo-50 active:scale-95">
                            <Printer class="h-4 w-4" /> Print / PDF
                        </button>
                    </div>
                </div>

                <!-- ═══ THE DOCUMENT (beautiful on screen, formal on paper) ═══ -->
                <article id="board-doc" class="overflow-hidden rounded-3xl border border-slate-200 bg-white text-gray-900 shadow-xl print:rounded-none print:border-0 print:shadow-none">

                    <!-- Letterhead -->
                    <header class="border-b-4 border-double border-gray-900 px-6 pb-5 pt-6 sm:px-10 sm:pt-8">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.3em] text-indigo-700">Monti Textile Manufacturing Corp.</p>
                                <h2 class="mt-1 text-2xl font-black tracking-tight sm:text-3xl">Monthly Board Pack</h2>
                                <p class="mt-1 text-xs font-bold uppercase tracking-widest text-gray-500">Board of Directors · For discussion &amp; notation</p>
                            </div>
                            <dl class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-xs print:rounded-none print:border-gray-400">
                                <div class="flex justify-between gap-6 py-0.5"><dt class="font-bold uppercase text-gray-400">Ref No.</dt><dd class="font-mono font-black">{{ refNo }}</dd></div>
                                <div class="flex justify-between gap-6 py-0.5"><dt class="font-bold uppercase text-gray-400">Generated</dt><dd class="font-bold">{{ generatedAt }}</dd></div>
                                <div class="flex justify-between gap-6 py-0.5"><dt class="font-bold uppercase text-gray-400">Classification</dt><dd class="font-black text-rose-700">Confidential</dd></div>
                            </dl>
                        </div>
                    </header>

                    <div class="space-y-8 px-6 py-6 sm:px-10 sm:py-8 print:space-y-6">

                        <!-- 1 · Executive summary -->
                        <section class="avoid-break">
                            <h3 class="doc-h">1 · Executive Summary</h3>
                            <div class="space-y-2 text-sm leading-relaxed text-gray-700">
                                <p>
                                    For the period ending <strong>{{ generatedAt }}</strong>, Monti Textile records approved
                                    revenue of <strong>{{ peso(kpis.revenue_approved) }}</strong> against procurement spend of
                                    <strong>{{ peso(kpis.procurement_spend) }}</strong>, for a gross margin of
                                    <strong>{{ peso(margin) }}</strong>. Payroll cost stands at
                                    <strong>{{ peso(kpis.payroll_cost) }}</strong> with
                                    <strong>{{ num(kpis.headcount) }}</strong> active personnel.
                                </p>
                                <p>
                                    Plant output totals <strong>{{ num(kpis.fabrics_total) }}</strong> fabrics at a
                                    <strong>{{ kpis.reject_rate }}%</strong> reject rate, with
                                    <strong>{{ num(kpis.jobs_in_production) }}</strong> job(s) currently in production and
                                    <strong>{{ num(kpis.packages_delivered) }}</strong> package(s) delivered.
                                    <strong>{{ num(kpis.machines_down) }}</strong> machine(s) are presently non-available and
                                    <strong>{{ peso(kpis.invoices_unpaid) }}</strong> in supplier invoices remain unpaid.
                                    The commercial pipeline carries <strong>{{ num(kpis.leads_open) }}</strong> open lead(s)
                                    against <strong>{{ num(kpis.leads_won) }}</strong> won.
                                </p>
                            </div>
                        </section>

                        <!-- 2 · KPI cards -->
                        <section class="avoid-break">
                            <h3 class="doc-h">2 · Key Figures</h3>
                            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-4 print:grid-cols-4 print:gap-2">
                                <div v-for="k in kpiCards" :key="k.label"
                                    class="rounded-2xl border border-gray-200 bg-white p-3.5 print:rounded-lg print:border-gray-400 print:p-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-xl print:rounded-md" :class="toneBg[k.tone]">
                                            <component :is="k.icon" class="h-4 w-4" :class="toneText[k.tone]" />
                                        </span>
                                        <p class="text-[10px] font-black uppercase tracking-wider text-gray-400">{{ k.label }}</p>
                                    </div>
                                    <p class="mt-1.5 text-lg font-black tracking-tight">{{ k.value }}</p>
                                    <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-gray-100 print:bg-gray-200"><div class="h-full rounded-full" :class="toneBar[k.tone]" style="width:100%" /></div>
                                </div>
                            </div>
                        </section>

                        <!-- 3 · Trend chart (screen + print, exact colors) -->
                        <section>
                            <h3 class="doc-h">3 · Twelve-Month Trend</h3>
                            <div class="rounded-2xl border border-gray-200 p-4 print:rounded-lg print:border-gray-400">
                                <div class="mb-3 flex flex-wrap gap-4 text-[10px] font-black uppercase tracking-widest text-gray-500">
                                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-indigo-600" /> Revenue</span>
                                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-rose-400" /> Spend</span>
                                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500" /> Output (fabrics)</span>
                                </div>
                                <div class="flex h-44 items-end gap-1.5 sm:gap-2.5 print:h-40">
                                    <div v-for="(label, i) in trends.labels" :key="label" class="flex min-w-0 flex-1 flex-col items-center gap-1">
                                        <div class="flex h-32 w-full items-end justify-center gap-[3px] print:h-28">
                                            <div class="w-full max-w-3 rounded-t bg-indigo-600" :title="`${label}: ${peso(trends.revenue[i])}`"
                                                :style="{ height: Math.max(2, (trends.revenue[i] / maxMoney) * 100) + '%' }" />
                                            <div class="w-full max-w-3 rounded-t bg-rose-400" :title="`${label}: ${peso(trends.spend[i])}`"
                                                :style="{ height: Math.max(2, (trends.spend[i] / maxMoney) * 100) + '%' }" />
                                            <div class="w-full max-w-3 rounded-t bg-emerald-500" :title="`${label}: ${num(trends.output[i])} fabrics`"
                                                :style="{ height: Math.max(2, (trends.output[i] / maxOutput) * 100) + '%' }" />
                                        </div>
                                        <span class="truncate text-[8px] font-black uppercase text-gray-400">{{ label }}</span>
                                    </div>
                                </div>
                                <p class="mt-2 text-[10px] font-bold text-gray-400">Peak revenue month: {{ pesoCompact(Math.max(...(trends.revenue || [0]))) }} · figures in Philippine peso unless stated.</p>
                            </div>

                            <div class="mt-3 overflow-x-auto rounded-2xl border border-gray-200 print:rounded-lg print:border-gray-400">
                                <table class="doc-table w-full min-w-[640px] text-xs">
                                    <thead><tr>
                                        <th>Month</th><th class="text-right">Revenue</th><th class="text-right">Spend</th>
                                        <th class="text-right">Output</th><th class="text-right">Rejects</th><th class="text-right">Payroll</th>
                                    </tr></thead>
                                    <tbody>
                                        <tr v-for="(label, i) in trends.labels" :key="label">
                                            <td class="font-bold">{{ label }}</td>
                                            <td class="text-right">{{ peso(trends.revenue[i]) }}</td>
                                            <td class="text-right">{{ peso(trends.spend[i]) }}</td>
                                            <td class="text-right">{{ num(trends.output[i]) }}</td>
                                            <td class="text-right">{{ num(trends.rejects[i]) }}</td>
                                            <td class="text-right">{{ peso(trends.payroll[i]) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <!-- 4 · Decisions -->
                        <section class="avoid-break">
                            <h3 class="doc-h">4 · Executive Decisions <span class="font-bold normal-case text-gray-400">({{ decisions?.length || 0 }} on record)</span></h3>
                            <ol v-if="decisions?.length" class="divide-y divide-gray-200 border-y border-gray-200 print:border-gray-400">
                                <li v-for="(d, i) in decisions" :key="d.id" class="flex gap-3 py-2.5">
                                    <span class="font-mono text-xs font-black text-gray-400">{{ String(i + 1).padStart(2, '0') }}</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm">
                                            <span class="mr-2 rounded px-1.5 py-0.5 text-[10px] font-black uppercase"
                                                :class="d.decision === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'">{{ d.decision }}</span>
                                            <strong>{{ d.subject_label }}</strong>
                                        </p>
                                        <p v-if="d.reason" class="mt-0.5 text-xs text-gray-500">Reason: {{ d.reason }}</p>
                                    </div>
                                    <p class="shrink-0 text-right text-[11px] font-bold text-gray-500">{{ d.actor?.name }}<br>{{ fdate(d.created_at) }}</p>
                                </li>
                            </ol>
                            <p v-else class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-400">No decisions recorded in this period.</p>
                        </section>

                        <!-- Signatories -->
                        <section class="avoid-break">
                            <h3 class="doc-h">Attestation</h3>
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-3 print:grid-cols-3">
                                <div v-for="role in ['Prepared by', 'Reviewed by', 'Approved by']" :key="role" class="pt-8">
                                    <div class="border-t-2 border-gray-900 pt-2">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ role }}</p>
                                        <p class="mt-4 text-xs text-gray-400">Signature over printed name / Date</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <footer class="border-t border-gray-200 pt-3 text-[10px] font-bold uppercase tracking-widest text-gray-400 print:border-gray-400">
                            Monti Textile ERP · system-generated from live module data · Ref {{ refNo }} · {{ generatedAt }}
                        </footer>
                    </div>
                </article>

               

                <!-- Print-only running footer with page number -->
                <div class="print-footer">
                    <span>Monti Textile · Board Pack {{ refNo }} · Confidential</span>
                    <span>Page <span class="page-num" /> </span>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.doc-h {
    font-size: 0.8rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.15em;
    border-bottom: 2px solid #111827;
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
}
.doc-table thead tr {
    text-transform: uppercase;
    font-size: 10px;
    color: #6b7280;
    border-bottom: 2px solid #111827;
}
.doc-table th { padding: 0.5rem 0.75rem; font-weight: 900; letter-spacing: 0.08em; }
.doc-table td { padding: 0.45rem 0.75rem; border-bottom: 1px solid #e5e7eb; }
.doc-table tbody tr:last-child td { border-bottom: none; }

@page { size: A4; margin: 13mm 11mm 17mm; }

@media print {
    .avoid-break { break-inside: avoid; }
    #board-doc tr { break-inside: avoid; }
    #board-doc section { break-inside: auto; }
    #board-doc { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .print-footer {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        display: flex;
        justify-content: space-between;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        color: #6b7280;
        border-top: 1px solid #9ca3af;
        padding-top: 6px;
    }
    .print-footer .page-num::after { content: counter(page) ' of ' counter(pages); }
}
@media screen {
    .print-footer { display: none; }
}
</style>
