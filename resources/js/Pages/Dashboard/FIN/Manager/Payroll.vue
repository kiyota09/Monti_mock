<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Users, Sparkles, HandCoins, Building2, X, ChevronDown, FileText, Download, Eye } from 'lucide-vue-next';

const props = defineProps({
    payroll: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    isDummy: { type: Boolean, default: false },
    employees: { type: Array, default: () => [] },
    agencyInvoices: { type: Array, default: () => [] },
});

const peso = (v) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(v ?? 0));

const totalHeadcount = computed(() => props.payroll.reduce((s, p) => s + Number(p.headcount ?? 0), 0));
const totalGross = computed(() => props.payroll.reduce((s, p) => s + Number(p.gross ?? 0), 0));
const totalNet = computed(() => props.payroll.reduce((s, p) => s + Number(p.net ?? 0), 0));
const statusLabel = (status) => ({
    pending: 'Awaiting approval',
    processing: 'Awaiting payment',
    paid: 'Paid',
    submitted: 'Awaiting approval',
    approved: 'Awaiting payment',
}[String(status ?? '').toLowerCase()] ?? status);

const selectedDepartment = ref(null);
const selectedInvoice = ref(null);
const invoiceSheetRows = ref([]);
const invoicePreviewLoading = ref(false);
const invoicePreviewError = ref('');
const payingDepartment = ref(null);
const pendingPayment = ref(null);
const pendingPaymentNet = computed(() => {
    if (!pendingPayment.value) return 0;
    return props.employees
        .filter((employee) => pendingPayment.value.ids.includes(employee.payroll_id))
        .reduce((total, employee) => total + Number(employee.net_pay ?? 0), 0);
});
const invoiceGrid = computed(() => {
    const width = invoiceSheetRows.value.reduce((max, row) => Math.max(max, row.length), 0);
    const columnName = (number) => {
        let name = '';
        for (let value = number; value > 0; value = Math.floor((value - 1) / 26)) {
            name = String.fromCharCode(65 + ((value - 1) % 26)) + name;
        }
        return name;
    };
    return {
        columns: Array.from({ length: width }, (_, index) => columnName(index + 1)),
        rows: invoiceSheetRows.value.map((row) => Array.from({ length: width }, (_, index) => row[index] ?? '')),
    };
});
const selectedEmployees = computed(() => {
    if (!selectedDepartment.value) return [];
    return props.employees.filter(e => e.department === selectedDepartment.value.department);
});

const openDepartmentModal = (dept) => {
    selectedDepartment.value = dept;
};

const payDepartment = (department) => {
    const ids = props.employees
        .filter((employee) => employee.department === department.department && employee.status === 'approved')
        .map((employee) => employee.payroll_id)
        .filter(Boolean);
    if (!ids.length) return;

    pendingPayment.value = { department: department.department, ids };
};

const confirmDepartmentPayment = () => {
    if (!pendingPayment.value || payingDepartment.value) return;
    const payment = pendingPayment.value;

    payingDepartment.value = payment.department;
    router.post(route('hrm.payroll.paid'), { ids: payment.ids }, {
        preserveScroll: true,
        onFinish: () => {
            payingDepartment.value = null;
            pendingPayment.value = null;
        },
    });
};

const closePaymentConfirmation = () => {
    if (!payingDepartment.value) pendingPayment.value = null;
};

const closeModal = () => {
    selectedDepartment.value = null;
};

const parseInvoiceSpreadsheet = (text) => {
    const xml = new DOMParser().parseFromString(text, 'application/xml');
    if (!xml.querySelector('parsererror')) {
        const rows = [];
        let nextRow = 0;
        Array.from(xml.getElementsByTagNameNS('*', 'Row')).forEach((row) => {
            const rowIndex = Number(row.getAttributeNS('urn:schemas-microsoft-com:office:spreadsheet', 'Index'));
            const targetRow = rowIndex > 0 ? rowIndex - 1 : nextRow;
            while (rows.length < targetRow) rows.push([]);
            const cells = [];
            let nextCell = 0;
            Array.from(row.children).filter((cell) => cell.localName === 'Cell').forEach((cell) => {
                const cellIndex = Number(cell.getAttributeNS('urn:schemas-microsoft-com:office:spreadsheet', 'Index'));
                if (cellIndex > 0) nextCell = cellIndex - 1;
                cells[nextCell] = Array.from(cell.children).find((node) => node.localName === 'Data')?.textContent ?? '';
                nextCell++;
            });
            rows[targetRow] = cells;
            nextRow = targetRow + 1;
        });
        if (rows.length) return rows;
    }

    const html = new DOMParser().parseFromString(text, 'text/html');
    const htmlRows = Array.from(html.querySelectorAll('tr')).map((row) =>
        Array.from(row.querySelectorAll('th, td')).map((cell) => cell.textContent.trim())
    ).filter((row) => row.length > 0);
    if (htmlRows.length) return htmlRows;

    const parseCsvLine = (line) => {
        const cells = [];
        let value = '';
        let quoted = false;
        for (let index = 0; index < line.length; index++) {
            const char = line[index];
            if (char === '"' && line[index + 1] === '"' && quoted) { value += '"'; index++; }
            else if (char === '"') quoted = !quoted;
            else if (char === ',' && !quoted) { cells.push(value); value = ''; }
            else value += char;
        }
        cells.push(value);
        return cells;
    };
    return text.split(/\r?\n/).map(parseCsvLine);
};

const viewInvoice = async (invoice) => {
    selectedInvoice.value = invoice;
    invoiceSheetRows.value = invoice.sheet_rows || [];
    invoicePreviewError.value = '';
    const spreadsheet = (invoice.attachments || []).find((file) => /\.(xls|xlsx|csv)$/i.test(file.name || ''));
    if (!spreadsheet) return;

    invoicePreviewLoading.value = true;
    try {
        const response = await fetch(spreadsheet.url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) throw new Error('Invoice could not be opened.');
        const rows = parseInvoiceSpreadsheet(await response.text());
        if (rows.length) invoiceSheetRows.value = rows;
        else if (!invoiceSheetRows.value.length) invoicePreviewError.value = 'This workbook could not be previewed. Download the original file to view it.';
    } catch (error) {
        if (!invoiceSheetRows.value.length) invoicePreviewError.value = error.message || 'Invoice could not be opened.';
    } finally {
        invoicePreviewLoading.value = false;
    }
};

const closeInvoice = () => {
    selectedInvoice.value = null;
    invoiceSheetRows.value = [];
    invoicePreviewError.value = '';
};

const statusClass = (status) => {
    switch (status) {
        case 'paid': return 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30';
        case 'processing': return 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
        case 'pending': return 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
        default: return 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
    }
};
</script>

<template>
    <Head title="Payroll" />
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
                            <HandCoins class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> Finance · Payroll
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Department Payroll</h1>
                            <p class="text-sm text-blue-100/90">{{ payroll.length }} department{{ payroll.length !== 1 ? 's' : '' }} · {{ totalHeadcount }} employees on run</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span v-if="isDummy" class="rounded-full bg-amber-300/90 px-3 py-1.5 text-xs font-black text-amber-950">Demo data</span>
                        </div>
                    </div>

                    <!-- Headcount / gross / net pills -->
                    <div class="relative mt-6 flex flex-wrap gap-2">
                        <span class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur ring-1 ring-white/25 flex items-center gap-2">
                            <Users class="h-4 w-4" /> {{ totalHeadcount }} headcount
                        </span>
                        <span class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur ring-1 ring-white/25">
                            Gross · {{ peso(totalGross) }}
                        </span>
                        <span class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg scale-105">
                            Net · {{ peso(totalNet) }}
                        </span>
                    </div>
                </div>

                <!-- Department table and agency invoice column -->
                <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,1.8fr)_minmax(300px,0.8fr)]">
                <div class="min-w-0 overflow-x-auto rounded-lg">
                <TransitionGroup name="row" tag="table" class="w-full min-w-[760px] overflow-hidden rounded-lg bg-white text-sm dark:bg-zinc-900">
                    <thead class="bg-gray-50 dark:bg-zinc-800">
                        <tr class="text-left text-[10px] font-bold uppercase text-gray-500 dark:text-gray-300">
                            <th class="px-3 py-2">Department</th>
                            <th class="px-3 py-2">Emp</th>
                            <th class="px-3 py-2">Gross</th>
                            <th class="px-3 py-2">Deductions</th>
                            <th class="px-3 py-2">Net pay</th>
                            <th class="px-3 py-2">Avg / head</th>
                            <th class="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(p, i) in payroll" :key="p.department"
                            class="border-b border-gray-100 dark:border-zinc-800 hover:bg-gray-50 dark:hover:bg-zinc-800 cursor-pointer"
                            @click="openDepartmentModal(p)">
                            <td class="px-3 py-2 font-medium text-gray-900 dark:text-white flex items-center gap-1.5">
                                <span @click.stop="openDepartmentModal(p)" class="text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer text-sm">{{ p.department }}</span>
                                <ChevronDown class="h-3.5 w-3.5 text-gray-400" />
                            </td>
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-300 text-sm">{{ p.headcount }}</td>
                            <td class="px-3 py-2 text-gray-900 dark:text-white text-sm">{{ peso(p.gross) }}</td>
                            <td class="px-3 py-2 text-rose-500 dark:text-rose-300 text-sm">−{{ peso(p.deductions) }}</td>
                            <td class="px-3 py-2 font-black text-indigo-600 dark:text-indigo-300 text-sm">{{ peso(p.net) }}</td>
                            <td class="px-3 py-2 text-[9px] font-bold uppercase text-gray-400 dark:text-gray-300">
                                {{ peso(Math.round(Number(p.net ?? 0) / Math.max(1, Number(p.headcount ?? 1)))) }} avg / head
                            </td>
                            <td class="px-3 py-2">
                                <span :class="statusClass(p.status)"
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[8px] font-black uppercase ring-1">
                                    <span class="h-1 w-1 rounded-full bg-current animate-pulse" /> {{ statusLabel(p.status) }}
                                </span>
                                <span class="mt-1 block text-[9px] text-gray-400">{{ p.status_detail }}</span>
                                <button v-if="p.approved_count > 0" type="button" :disabled="payingDepartment === p.department" @click.stop="payDepartment(p)" class="mt-2 inline-flex items-center gap-1 rounded-md bg-emerald-600 px-2.5 py-1 text-[10px] font-bold text-white transition hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-60">
                                    <HandCoins class="h-3 w-3" /> {{ payingDepartment === p.department ? 'Processing…' : 'Pay' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </TransitionGroup>
                </div>

                <aside class="min-w-0 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-zinc-800">
                        <div>
                            <h2 class="flex items-center gap-2 text-sm font-black text-gray-900 dark:text-white">
                                <FileText class="h-4 w-4 text-indigo-500" /> Agency invoices
                            </h2>
                            <p class="mt-0.5 text-[10px] text-gray-400">Documents submitted by agencies</p>
                        </div>
                        <span class="rounded-full bg-indigo-50 px-2 py-1 text-[10px] font-bold text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">{{ agencyInvoices.length }}</span>
                    </div>
                    <div class="max-h-[520px] space-y-2 overflow-y-auto p-3">
                        <article v-for="invoice in agencyInvoices" :key="invoice.id" class="rounded-xl border border-gray-100 p-3 dark:border-zinc-800">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-bold text-gray-900 dark:text-white">{{ invoice.agency }}</p>
                                    <p class="mt-0.5 truncate text-[10px] text-gray-500 dark:text-gray-400">{{ invoice.invoice }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-bold text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">{{ invoice.status }}</span>
                            </div>
                            <div class="mt-2 flex items-center justify-between gap-2 text-[10px] text-gray-500 dark:text-gray-400">
                                <span>{{ invoice.period }}</span>
                                <div class="flex shrink-0 items-center gap-2">
                                    <span v-if="invoice.amount !== null" class="font-bold text-gray-700 dark:text-gray-200">{{ peso(invoice.amount) }}</span>
                                    <button type="button" @click="viewInvoice(invoice)" class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-2 py-1 font-bold text-indigo-700 transition hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">
                                        <Eye class="h-3 w-3" /> View
                                    </button>
                                </div>
                            </div>
                            <div v-if="invoice.attachments?.length" class="mt-2 space-y-1 border-t border-gray-100 pt-2 dark:border-zinc-800">
                                <a v-for="file in invoice.attachments" :key="file.url" :href="file.url" class="flex min-w-0 items-center gap-1.5 text-[10px] font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-300 dark:hover:text-indigo-200">
                                    <Download class="h-3 w-3 shrink-0" />
                                    <span class="truncate">{{ file.name }}</span>
                                </a>
                            </div>
                        </article>
                        <div v-if="agencyInvoices.length === 0" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center dark:border-zinc-700">
                            <FileText class="mx-auto h-6 w-6 text-gray-300 dark:text-zinc-600" />
                            <p class="mt-2 text-xs font-semibold text-gray-500 dark:text-gray-400">No agency invoices submitted yet.</p>
                        </div>
                    </div>
                </aside>
                </div>

                <!-- Agency invoice preview -->
                <div v-if="selectedInvoice" class="fixed inset-0 z-[70] flex items-center justify-center bg-zinc-950/60 p-3 backdrop-blur-sm" @click.self="closeInvoice">
                    <section class="flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                        <header class="flex items-start justify-between gap-4 border-b border-gray-100 p-5 dark:border-zinc-800">
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-300">Agency invoice</p>
                                <h2 class="mt-1 truncate text-lg font-black text-gray-900 dark:text-white">{{ selectedInvoice.invoice }}</h2>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ selectedInvoice.agency }}</p>
                            </div>
                            <button type="button" @click="closeInvoice" aria-label="Close invoice preview" class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-zinc-800 dark:hover:text-white">
                                <X class="h-4 w-4" />
                            </button>
                        </header>
                        <div class="grid gap-3 border-b border-gray-100 bg-gray-50 p-4 sm:grid-cols-2 lg:grid-cols-4 dark:border-zinc-800 dark:bg-zinc-950">
                            <div><p class="text-[9px] font-bold uppercase text-gray-400">Agency</p><p class="mt-1 text-xs font-semibold text-gray-800 dark:text-gray-100">{{ selectedInvoice.agency }}</p></div>
                            <div><p class="text-[9px] font-bold uppercase text-gray-400">Invoice</p><p class="mt-1 text-xs font-semibold text-gray-800 dark:text-gray-100">{{ selectedInvoice.invoice }}</p></div>
                            <div><p class="text-[9px] font-bold uppercase text-gray-400">Period</p><p class="mt-1 text-xs font-semibold text-gray-800 dark:text-gray-100">{{ selectedInvoice.period }}</p></div>
                            <div><p class="text-[9px] font-bold uppercase text-gray-400">Status / total</p><p class="mt-1 text-xs font-semibold text-gray-800 dark:text-gray-100">{{ selectedInvoice.status }}<span v-if="selectedInvoice.amount !== null"> · {{ peso(selectedInvoice.amount) }}</span></p></div>
                        </div>
                        <div class="min-h-0 flex-1 overflow-auto p-4">
                            <div v-if="invoicePreviewLoading" class="py-12 text-center text-sm text-gray-500">Loading invoice spreadsheet…</div>
                            <div v-else-if="invoicePreviewError" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">{{ invoicePreviewError }}</div>
                            <div v-else-if="invoiceGrid.rows.length" class="overflow-auto rounded-xl border border-gray-200 dark:border-zinc-700">
                                <table class="w-max min-w-full border-collapse text-left text-xs">
                                    <thead class="sticky top-0 z-10 bg-gray-100 dark:bg-zinc-800">
                                        <tr>
                                            <th class="sticky left-0 border border-gray-200 bg-gray-100 px-3 py-2 text-center text-[10px] font-bold text-gray-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300">#</th>
                                            <th v-for="column in invoiceGrid.columns" :key="column" class="border border-gray-200 px-3 py-2 text-center text-[10px] font-bold text-gray-500 dark:border-zinc-700 dark:text-gray-300">{{ column }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, rowIndex) in invoiceGrid.rows" :key="rowIndex" :class="rowIndex % 2 ? 'bg-gray-50 dark:bg-zinc-800/60' : 'bg-white dark:bg-zinc-900'">
                                            <th class="sticky left-0 border border-gray-200 bg-gray-100 px-3 py-2 text-center text-[10px] font-bold text-gray-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300">{{ rowIndex + 1 }}</th>
                                            <td v-for="(cell, cellIndex) in row" :key="cellIndex" class="min-w-28 border border-gray-200 px-3 py-2 text-gray-700 dark:border-zinc-700 dark:text-gray-200">
                                                {{ cell }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="rounded-xl border border-dashed border-gray-200 p-8 text-center text-xs text-gray-500 dark:border-zinc-700 dark:text-gray-400">
                                No spreadsheet rows are available for this invoice.
                            </div>
                            <div v-if="selectedInvoice.attachments?.length" class="mt-4 flex flex-wrap gap-2">
                                <a v-for="file in selectedInvoice.attachments" :key="file.url" :href="file.url" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-50 dark:border-zinc-700 dark:text-indigo-300 dark:hover:bg-zinc-800">
                                    <Download class="h-3.5 w-3.5" /> Download {{ file.name }}
                                </a>
                            </div>
                        </div>
                        <footer class="flex justify-end border-t border-gray-100 p-4 dark:border-zinc-800">
                            <button type="button" @click="closeInvoice" class="rounded-lg bg-gray-100 px-4 py-2 text-xs font-bold text-gray-700 hover:bg-gray-200 dark:bg-zinc-800 dark:text-gray-200 dark:hover:bg-zinc-700">Close</button>
                        </footer>
                    </section>
                </div>

                <!-- Department Employees Modal -->
                <Transition name="modal">
                    <div v-if="selectedDepartment" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click="closeModal">
                        <div class="absolute inset-0 bg-zinc-950/60 backdrop-blur-sm" />
                        <div class="relative w-full max-w-3xl max-h-[80vh] overflow-hidden rounded-3xl bg-white dark:bg-zinc-900 shadow-2xl flex flex-col">
                            <div class="bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-4 text-white flex items-center justify-between">
                                <div>
                                    <p class="text-[9px] font-bold uppercase tracking-[0.2em] text-blue-100">Department Employees</p>
                                    <h3 class="text-base font-black tracking-tight">{{ selectedDepartment.department }}</h3>
                                </div>
                                <button @click="closeModal" class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition active:scale-95">
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </div>
                            <div class="p-3 overflow-y-auto flex-1">
                                <div v-if="selectedEmployees.length === 0" class="py-6 text-center text-[11px] text-gray-400">No employees found for this department.</div>
                                <table v-else class="w-full min-w-[760px] text-sm">
                                    <thead class="bg-gray-50 dark:bg-zinc-800">
                                        <tr class="text-left text-[9px] font-bold uppercase text-gray-500 dark:text-gray-300">
                                            <th class="px-2.5 py-1.5">Employee</th>
                                            <th class="px-2.5 py-1.5">Pay period</th>
                                            <th class="px-2.5 py-1.5">Status</th>
                                            <th class="px-2.5 py-1.5">Position</th>
                                            <th class="px-2.5 py-1.5 text-right">Gross Pay</th>
                                            <th class="px-2.5 py-1.5 text-right">Deductions</th>
                                            <th class="px-2.5 py-1.5 text-right">Net Pay</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                        <tr v-for="emp in selectedEmployees" :key="`${emp.id}-${emp.period}`"
                                            class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                            <td class="px-2.5 py-1.5">
                                                <div class="flex items-center gap-2">
                                                    <div class="h-7 w-7 rounded-full bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 flex items-center justify-center text-white text-xs font-black">
                                                        {{ (emp.name ?? '?').charAt(0) }}
                                                    </div>
                                                    <div>
                                                        <p class="font-medium text-gray-900 dark:text-white text-sm">{{ emp.name }}</p>
                                                        <p class="text-[9px] text-gray-400">{{ emp.employee_id ?? '—' }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-2.5 py-1.5 whitespace-nowrap text-gray-500 dark:text-gray-300">{{ emp.period }}</td>
                                            <td class="px-2.5 py-1.5 whitespace-nowrap text-xs font-semibold text-gray-600 dark:text-gray-300">{{ statusLabel(emp.status) }}</td>
                                            <td class="px-2.5 py-1.5 text-gray-500 dark:text-gray-300 text-sm">{{ emp.position ?? '—' }}</td>
                                            <td class="px-2.5 py-1.5 text-right font-medium text-gray-900 dark:text-white text-sm">{{ peso(emp.gross_pay) }}</td>
                                            <td class="px-2.5 py-1.5 text-right text-rose-500 dark:text-rose-300 text-sm">−{{ peso(emp.deductions) }}</td>
                                            <td class="px-2.5 py-1.5 text-right font-black text-indigo-600 dark:text-indigo-300 text-sm">{{ peso(emp.net_pay) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div class="mt-3 pt-3 border-t border-gray-100 dark:border-zinc-800 flex items-center justify-between flex-wrap gap-2">
                                    <span class="text-[9px] font-bold uppercase text-gray-400">Total: {{ selectedEmployees.length }} employee{{ selectedEmployees.length !== 1 ? 's' : '' }}</span>
                                    <div class="flex items-center gap-3 text-[10px] font-medium">
                                        <span class="text-gray-900 dark:text-white">Gross: {{ peso(selectedEmployees.reduce((s, e) => s + Number(e.gross_pay ?? 0), 0)) }}</span>
                                        <span class="text-rose-500">Deductions: −{{ peso(selectedEmployees.reduce((s, e) => s + Number(e.deductions ?? 0), 0)) }}</span>
                                        <span class="text-indigo-600 dark:text-indigo-300">Net: {{ peso(selectedEmployees.reduce((s, e) => s + Number(e.net_pay ?? 0), 0)) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>

                <div v-if="pendingPayment" class="fixed inset-0 z-[80] flex items-center justify-center bg-zinc-950/60 p-4 backdrop-blur-sm" @click.self="closePaymentConfirmation">
                    <section role="dialog" aria-modal="true" aria-labelledby="pay-confirm-title" class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                        <header class="flex items-start gap-3 border-b border-gray-100 p-5 dark:border-zinc-800">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                <HandCoins class="h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h2 id="pay-confirm-title" class="text-base font-black text-gray-900 dark:text-white">Confirm payroll payment</h2>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Review the approved payroll amount before recording payment.</p>
                            </div>
                            <button type="button" :disabled="!!payingDepartment" @click="closePaymentConfirmation" aria-label="Close confirmation" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 disabled:opacity-50 dark:hover:bg-zinc-800">
                                <X class="h-4 w-4" />
                            </button>
                        </header>
                        <div class="space-y-3 p-5">
                            <div class="flex items-center justify-between rounded-xl bg-gray-50 px-4 py-3 dark:bg-zinc-800">
                                <span class="text-xs font-semibold text-gray-500 dark:text-gray-300">Department</span>
                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ pendingPayment.department }}</span>
                            </div>
                            <div class="flex items-center justify-between rounded-xl bg-gray-50 px-4 py-3 dark:bg-zinc-800">
                                <span class="text-xs font-semibold text-gray-500 dark:text-gray-300">Approved payroll rows</span>
                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ pendingPayment.ids.length }}</span>
                            </div>
                            <div class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-500/20 dark:bg-emerald-500/10">
                                <span class="text-xs font-bold text-emerald-800 dark:text-emerald-200">Net payment total</span>
                                <span class="text-sm font-black text-emerald-800 dark:text-emerald-200">{{ peso(pendingPaymentNet) }}</span>
                            </div>
                            <p class="text-[11px] leading-relaxed text-gray-500 dark:text-gray-400">Confirming records these payroll rows as paid and emails each employee’s payslip to their personal email address on file.</p>
                        </div>
                        <footer class="flex justify-end gap-2 border-t border-gray-100 p-4 dark:border-zinc-800">
                            <button type="button" :disabled="!!payingDepartment" @click="closePaymentConfirmation" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-bold text-gray-600 hover:bg-gray-50 disabled:opacity-50 dark:border-zinc-700 dark:text-gray-300 dark:hover:bg-zinc-800">Cancel</button>
                            <button type="button" :disabled="!!payingDepartment" @click="confirmDepartmentPayment" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-60">
                                <HandCoins class="h-3.5 w-3.5" /> {{ payingDepartment ? 'Processing…' : 'Confirm payment' }}
                            </button>
                        </footer>
                    </section>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-float-delayed { animation: float 8s ease-in-out 1.2s infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active { transition: opacity 0.2s ease; }
.modal-enter-from { opacity: 0; }
.modal-leave-active { transition: opacity 0.15s ease; }
.modal-leave-to { opacity: 0; }
</style>
