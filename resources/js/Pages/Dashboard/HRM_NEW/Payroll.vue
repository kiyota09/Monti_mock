<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, computed } from 'vue'
import { Search, Upload, DollarSign, TrendingDown, Wallet, Plus, FileText } from 'lucide-vue-next'

// NOTE (real system): role comes from the login session and is enforced on the
// server (PayrollController isHR/isManager/isFinance). Never trust the browser
// for permissions. This UI only reflects server state.

const props = defineProps({
    payroll_data: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    period_meta: { type: Array, default: () => [] },
    active_period: { type: String, default: '' },
    active_period_meta: { type: Object, default: null },
    calendar_year: { type: Number, default: null },
    frequency: { type: String, default: 'semi_monthly' },
    departments: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    stepper: { type: Object, default: () => ({ Draft: 0, Submitted: 0, Approved: 0, Paid: 0 }) },
    audit_log: { type: Array, default: () => [] },
    permissions: { type: Object, default: () => ({}) },
    employees: { type: Array, default: () => [] },
})

const periodOptions = computed(() => {
    if ((props.period_meta || []).length) return props.period_meta
    return (props.periods || []).map((p) => ({ label: typeof p === 'string' ? p : p.label, start: null, end: null, pay_date: null, is_current: false, locked: false, row_count: 0 }))
})

const page = usePage()
const authRole = computed(() => {
    const u = page.props.auth?.user
    if (!u) return 'HRM'
    if (u.role === 'FIN') return 'Finance'
    if (u.position === 'manager' || u.role === 'CEO' || u.role === 'COO') return 'Manager'
    return 'HR officer'
})

const canEdit = computed(() => props.permissions?.payroll === 'edit')
const tab = ref('pay')
const searchQuery = ref('')
const deptFilter = ref('All')
const sortKey = ref('')
const sortDir = ref(1)
const selected = ref(new Set())

const departments = computed(() => {
    const fromProps = (props.departments || []).filter(Boolean)
    const fromRows = (props.payroll_data || []).map((p) => p.department || 'Unassigned')
    const merged = [...fromProps, ...fromRows].filter(Boolean)
    return ['All', ...new Set(merged)]
})

const peso = (v) => '₱' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const visibleRows = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    let r = (props.payroll_data || []).filter((p) => {
        if (deptFilter.value !== 'All' && (p.department || 'Unassigned') !== deptFilter.value) return false
        if (q && !(p.employee_name || '').toLowerCase().includes(q) && !(p.job_title || '').toLowerCase().includes(q)) return false
        return true
    })
    if (sortKey.value) {
        const k = sortKey.value, d = sortDir.value
        r = [...r].sort((a, b) => {
            const val = (row, key) => key === 'name' ? (row.employee_name || '').toLowerCase()
                : key === 'dept' ? (row.department || '')
                : key === 'gross' ? Number(row.gross_pay || 0)
                : key === 'ded' ? Number(row.deductions || 0)
                : key === 'net' ? Number(row.net_pay || 0)
                : (row.status || '')
            const va = val(a, k), vb = val(b, k)
            if (va < vb) return -d
            if (va > vb) return d
            return 0
        })
    }
    return r
})

const footerTotals = computed(() => {
    let g = 0, d = 0, n = 0
    visibleRows.value.forEach((p) => { g += Number(p.gross_pay || 0); d += Number(p.deductions || 0); n += Number(p.net_pay || 0) })
    return { g, d, n }
})

const steps = computed(() => [
    { key: 'Draft', n: props.stepper?.Draft || 0 },
    { key: 'Submitted', n: props.stepper?.Submitted || 0 },
    { key: 'Approved', n: props.stepper?.Approved || 0 },
    { key: 'Paid', n: props.stepper?.Paid || 0 },
])
const highlightStep = computed(() => {
    const total = (props.stats?.total_count || props.payroll_data.length)
    if (total > 0 && (props.stepper?.Paid || 0) === total) return 'Paid'
    for (const s of steps.value) if (s.n > 0) return s.key
    return 'Draft'
})
const allPaid = computed(() => {
    const total = (props.stats?.total_count || props.payroll_data.length)
    return total > 0 && (props.stepper?.Paid || 0) === total
})

const metricCards = computed(() => [
    { label: 'Gross pay', value: peso(props.stats.gross_pay || 0), icon: DollarSign, color: 'text-blue-600' },
    { label: 'Deductions', value: peso(props.stats.deductions || 0), icon: TrendingDown, color: 'text-rose-600', negative: true },
    { label: 'Net payroll', value: peso(props.stats.net_payroll || 0), icon: Wallet, color: 'text-emerald-600' },
])

const initials = (name) => String(name || '?').split(' ').map((n) => n[0]).join('').slice(0, 2).toUpperCase()

// Backend uses back()->withErrors() for rejections and with('message') for
// success — without this banner every rejection looks like a dead button.
const flashMsg = computed(() => page.props.flash?.message || page.props.flash?.success || '')
const errorMsg = computed(() => {
    const e = page.props.errors || {}
    return e.error || e.ids || Object.values(e)[0] || ''
})

const statusStyle = (s) => ({
    'Draft': { badge: 'bg-slate-100 text-slate-500 border-slate-200', dot: 'bg-slate-300' },
    'Submitted': { badge: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-400' },
    'Approved': { badge: 'bg-blue-50 text-blue-700 border-blue-200', dot: 'bg-blue-500' },
    'Paid': { badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' },
}[s] || { badge: 'bg-slate-100 text-slate-500 border-slate-200', dot: 'bg-slate-300' })

const toggleSort = (k) => {
    if (sortKey.value === k) sortDir.value *= -1
    else { sortKey.value = k; sortDir.value = 1 }
}
const toggleOne = (id) => {
    const s = new Set(selected.value)
    if (s.has(id)) s.delete(id); else s.add(id)
    selected.value = s
}
const toggleAllVisible = () => {
    const s = new Set(selected.value)
    const allOn = visibleRows.value.length > 0 && visibleRows.value.every((p) => s.has(p.id))
    visibleRows.value.forEach((p) => { if (allOn) s.delete(p.id); else s.add(p.id) })
    selected.value = s
}
const selectedIds = () => [...selected.value]
const hasSelection = computed(() => selected.value.size > 0)

const postBatch = (routeName, extra = {}) => {
    router.post(route(routeName), { ids: selectedIds(), ...extra }, {
        preserveScroll: true,
        onSuccess: () => { selected.value = new Set() },
    })
}
const doSubmit = () => {
    confirmBox.value = {
        title: 'Submit for approval',
        message: `${selected.value.size} Draft row(s) will be submitted for manager approval. HR editing locks after submit.`,
        confirmLabel: 'Submit for approval',
        run: () => postBatch('hrm.payroll.submit'),
    };
};
const doApprove = () => {
    confirmBox.value = {
        title: 'Approve',
        message: `${selected.value.size} Submitted row(s) will be approved for finance payment.`,
        confirmLabel: 'Approve',
        run: () => postBatch('hrm.payroll.approve.batch'),
    };
};
const doReturn = () => {
    confirmBox.value = {
        title: 'Return to draft',
        message: `${selected.value.size} row(s) will go back to Draft. Optionally leave a reason for HR.`,
        inputLabel: 'Reason (optional)',
        inputValue: '',
        confirmLabel: 'Return to draft',
        run: (val) => postBatch('hrm.payroll.return', { reason: val || '' }),
    };
};
const doPaid = () => {
    if (authRole.value !== 'Finance') {
        confirmBox.value = {
            title: 'Mark paid',
            message: 'Only Finance can do this. Switch the role at the top.',
            confirmLabel: 'Understood',
            run: null,
        };
        return;
    }
    confirmBox.value = {
        title: 'Mark paid',
        message: `${selected.value.size} Approved row(s) will be marked Paid. Paid is final and locks the period when all rows are paid.`,
        inputLabel: 'Payment reference',
        inputValue: 'PAY-' + new Date().toISOString().slice(0, 10).replaceAll('-', ''),
        confirmLabel: 'Mark paid',
        run: (val) => postBatch('hrm.payroll.paid', { payment_reference: val || '', payment_method: 'bank' }),
    };
};
const closeConfirm = () => { confirmBox.value = null; };
const submitConfirm = () => {
    const box = confirmBox.value;
    if (!box) return;
    if (box.run) box.run(box.inputValue);
    confirmBox.value = null;
};
// In-page confirmation modal (no native alert/confirm/prompt here).
const confirmBox = ref(null);
const localMsg = ref('');
const switchPeriod = (e) => {
    router.get(route('hrm.payroll'), { period: e.target.value, year: props.calendar_year || undefined, frequency: props.frequency || undefined }, { preserveState: true })
}
const switchYear = (e) => {
    router.get(route('hrm.payroll'), { year: e.target.value, frequency: props.frequency || undefined }, { preserveState: false })
}
const switchFrequency = (e) => {
    router.get(route('hrm.payroll'), { frequency: e.target.value, year: props.calendar_year || undefined }, { preserveState: false })
}
const freqLabel = (f) => f === 'weekly' ? 'Weekly' : f === 'monthly' ? 'Monthly' : 'Semi-monthly'
const openRow = (id) => { router.visit(route('hrm.payroll.show', id)); };
const exportCsv = async () => {
    const lines = ['Name,Department,Gross,Deductions,Net,Status']
    visibleRows.value.forEach((p) => {
        const q = (v) => '"' + String(v ?? '').replace(/"/g, '""') + '"'
        lines.push([q(p.employee_name), q(p.department || ''), Number(p.gross_pay || 0).toFixed(2), Number(p.deductions || 0).toFixed(2), Number(p.net_pay || 0).toFixed(2), p.status].join(','))
    })
    try {
        await navigator.clipboard.writeText(lines.join('\n'))
        localMsg.value = 'CSV copied to clipboard';
    } catch { localMsg.value = 'Copy blocked by the browser'; }
}
</script>

<template>
    <Head title="Payroll" />
    <AuthenticatedLayout>
        <div class="flex min-h-screen bg-gradient-to-br from-slate-100 via-slate-50 to-blue-50 payroll-caps">
            <div class="flex-1 p-8 text-slate-900 font-sans min-w-0 relative">
                <div class="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,transparent,black,transparent)] pointer-events-none"></div>

                <header class="mb-8 relative">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                        <div>
                            <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3">
                                <span class="hover:text-slate-700 cursor-pointer transition-colors">Dashboard</span>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                <span class="text-slate-800 font-semibold">Payroll</span>
                            </nav>
                            <h1 class="text-4xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">Payroll</h1>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <select :value="frequency" aria-label="Pay frequency" @change="switchFrequency"
                                class="bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer">
                                <option value="weekly">Weekly</option>
                                <option value="semi_monthly">Semi-monthly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                            <select :value="calendar_year" aria-label="Payroll year" @change="switchYear"
                                class="bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer">
                                <option v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
                            </select>
                            <select :value="active_period" aria-label="Pay period" @change="switchPeriod"
                                class="bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer max-w-72">
                                <option v-for="p in periodOptions" :key="p.label" :value="p.label">{{ p.label }}{{ p.is_current ? ' · Current' : '' }}{{ p.locked ? ' · Locked' : '' }}{{ p.row_count ? ` (${p.row_count})` : '' }}</option>
                            </select>
                            <Link :href="route('hrm.payroll.generate')" :class="canEdit ? '' : 'opacity-50 pointer-events-none'"
                                class="flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-sm font-medium transition-all duration-200 shadow-sm hover:shadow-md hover:-translate-y-0.5">
                                <Plus class="w-4 h-4" /> Generate Payroll
                            </Link>
                        </div>
                    </div>
                    <div v-if="active_period_meta" class="mt-3 flex flex-wrap gap-2 text-xs relative">
                        <span class="px-3 py-1.5 rounded-full bg-white border border-slate-200 text-slate-600 shadow-sm">{{ freqLabel(frequency) }} · Cutoff {{ active_period_meta.start }} – {{ active_period_meta.end }}</span>
                        <span class="px-3 py-1.5 rounded-full bg-white border border-slate-200 text-slate-600 shadow-sm">Pay date {{ active_period_meta.pay_date }}</span>
                        <span v-if="active_period_meta.is_current" class="px-3 py-1.5 rounded-full bg-blue-50 border border-blue-200 text-blue-700 font-semibold">Current period</span>
                        <span v-if="active_period_meta.locked" class="px-3 py-1.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 font-semibold">Locked · all Paid</span>
                    </div>
                </header>

                <div v-if="errorMsg" class="mb-4 px-4 py-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold relative">{{ errorMsg }}</div>
                <div v-if="flashMsg" class="mb-4 px-4 py-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold relative">{{ flashMsg }}</div>
                <div v-if="localMsg" class="mb-4 px-4 py-3 rounded-2xl bg-blue-50 border border-blue-200 text-blue-700 text-xs font-semibold relative">{{ localMsg }}</div>

                <div class="grid grid-cols-3 gap-4 mb-8 relative">
                    <div v-for="m in metricCards" :key="m.label"
                        class="group bg-white rounded-2xl border border-slate-200/60 p-5 transition-all duration-300 hover:shadow-2xl hover:-translate-y-1 hover:border-blue-200/60 shadow-md backdrop-blur-sm">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ m.label }}</span>
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center group-hover:from-blue-100 group-hover:to-blue-200 transition-all duration-300 shadow-inner">
                                <component :is="m.icon" class="w-5 h-5" :class="m.color" />
                            </div>
                        </div>
                        <div class="text-2xl font-bold text-slate-900" style="font-variant-numeric:tabular-nums"><span v-if="m.negative" class="text-rose-500 mr-0.5">−</span>{{ m.value }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4 relative">
                    <div v-for="(s, i) in steps" :key="s.key"
                        class="bg-white rounded-2xl border p-4 shadow-md transition-all duration-300"
                        :class="highlightStep === s.key ? 'border-blue-300 shadow-lg' : 'border-slate-200/60'">
                        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ i + 1 }}. {{ s.key }}</div>
                        <div class="text-2xl font-bold text-slate-900">{{ s.n }}</div>
                    </div>
                </div>

                <div v-if="allPaid" class="mb-4 px-4 py-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-medium relative">
                    Payroll complete for {{ active_period }}. Next: remit contributions and tax (see Remittances).
                </div>

                <div class="flex gap-2 mb-4 relative">
                    <button type="button" @click="tab = 'pay'" class="px-4 py-2 rounded-xl text-xs font-bold border transition"
                        :class="tab === 'pay' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'">Payroll</button>
                    <button type="button" @click="tab = 'audit'" class="px-4 py-2 rounded-xl text-xs font-bold border transition"
                        :class="tab === 'audit' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'">Audit log</button>
                </div>

                <div v-if="tab === 'pay'" class="bg-white rounded-2xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 backdrop-blur-sm overflow-hidden relative">
                    <div class="p-4 border-b border-slate-100 flex flex-col xl:flex-row xl:items-center gap-3">
                        <div class="flex items-center gap-3 flex-wrap">
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3"><Search class="w-4 h-4 text-slate-400" /></span>
                                <input v-model="searchQuery" type="text" placeholder="Search name or job title..."
                                    class="w-64 pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                            </div>
                            <select v-model="deptFilter"
                                class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-600 focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer">
                                <option v-for="d in departments" :key="d" :value="d">{{ d === 'All' ? 'All Departments' : d }}</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap xl:ml-auto">
                            <button type="button" :disabled="!hasSelection" @click="doSubmit"
                                class="px-3 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold transition disabled:opacity-50 shadow-sm">Submit for approval</button>
                            <button type="button" :disabled="!hasSelection" @click="doApprove"
                                class="px-3 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold transition disabled:opacity-50 shadow-sm">Approve</button>
                            <button type="button" :disabled="!hasSelection" @click="doReturn"
                                class="px-3 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold transition disabled:opacity-50 shadow-sm">Return to draft</button>
                            <button type="button" :disabled="!hasSelection" @click="doPaid"
                                class="px-3 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold transition disabled:opacity-50 shadow-sm">Mark paid</button>
                            <button type="button" @click="exportCsv"
                                class="flex items-center gap-2 px-3 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold transition shadow-sm"><Upload class="w-3.5 h-3.5" />Export CSV</button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gradient-to-r from-slate-50 to-transparent border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                    <th class="py-3 px-5"><input type="checkbox" aria-label="Select all visible rows" :checked="visibleRows.length > 0 && visibleRows.every((p) => selected.has(p.id))" @change="toggleAllVisible" /></th>
                                    <th class="py-3 px-5 cursor-pointer" @click="toggleSort('name')">Employee</th>
                                    <th class="py-3 px-5 cursor-pointer" @click="toggleSort('dept')">Department</th>
                                    <th class="py-3 px-5 text-right cursor-pointer" @click="toggleSort('gross')">Gross</th>
                                    <th class="py-3 px-5 text-right cursor-pointer" @click="toggleSort('ded')">Deductions</th>
                                    <th class="py-3 px-5 text-right cursor-pointer" @click="toggleSort('net')">Net pay</th>
                                    <th class="py-3 px-5 text-center cursor-pointer" @click="toggleSort('status')">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="p in visibleRows" :key="p.id" @click="openRow(p.id)" class="hover:bg-gradient-to-r hover:from-blue-50/50 hover:to-transparent transition-all duration-200 text-xs cursor-pointer">
                                    <td class="py-3 px-5" @click.stop><input type="checkbox" :aria-label="'Select ' + p.employee_name" :checked="selected.has(p.id)" @change="toggleOne(p.id)" /></td>
                                    <td class="py-3 px-5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center text-[10px] font-bold text-blue-600 shadow-inner">{{ initials(p.employee_name) }}</div>
                                            <div>
                                                <Link :href="route('hrm.payroll.show', p.id)" @click.stop class="font-semibold text-slate-800 hover:text-blue-600">{{ p.employee_name }}</Link>
                                                <div class="text-slate-500">{{ p.job_title || p.role }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-5 text-slate-600">{{ p.department || 'Unassigned' }}</td>
                                    <td class="py-3 px-5 text-right font-medium" style="font-variant-numeric:tabular-nums">{{ peso(p.gross_pay) }}</td>
                                    <td class="py-3 px-5 text-right text-slate-600" style="font-variant-numeric:tabular-nums">{{ peso(p.deductions) }}</td>
                                    <td class="py-3 px-5 text-right font-bold" style="font-variant-numeric:tabular-nums">{{ peso(p.net_pay) }}</td>
                                    <td class="py-3 px-5 text-center">
                                        <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold shadow-sm border', statusStyle(p.status).badge]">
                                            <span :class="['w-1.5 h-1.5 rounded-full', statusStyle(p.status).dot]"></span>{{ p.status }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!visibleRows.length">
                                    <td colspan="7" class="py-10 text-center text-sm text-slate-400">No employees match. Clear the search or pick another department.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-6 text-xs font-semibold text-slate-700" style="font-variant-numeric:tabular-nums">
                        <span class="text-slate-500">Total ({{ visibleRows.length }})</span>
                        <span>{{ peso(footerTotals.g) }}</span>
                        <span>{{ peso(footerTotals.d) }}</span>
                        <span class="font-bold text-slate-900">{{ peso(footerTotals.n) }}</span>
                    </div>
                </div>
                <p v-if="tab === 'pay'" class="text-xs text-slate-500 mt-3 relative">Amounts are in PHP for a semi-monthly period. Rates follow the 2026 SSS, PhilHealth, Pag-IBIG and TRAIN schedules as I understand them. Verify against the official circulars and your accountant before real use.</p>

                <div v-if="tab === 'audit'" class="bg-white rounded-2xl border border-slate-200/60 shadow-lg backdrop-blur-sm overflow-hidden relative">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead><tr class="bg-gradient-to-r from-slate-50 to-transparent border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                <th class="py-3 px-5">Time</th><th class="py-3 px-5">Role</th><th class="py-3 px-5">Message</th><th class="py-3 px-5">Decision</th>
                            </tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(a, i) in audit_log" :key="i" class="hover:bg-blue-50/50"><td class="py-3 px-5 text-slate-500 whitespace-nowrap">{{ a.time }}</td><td class="py-3 px-5">{{ a.role }}</td><td class="py-3 px-5">{{ a.message }}</td><td class="py-3 px-5">{{ a.decision }}</td></tr>
                                <tr v-if="!audit_log?.length"><td colspan="4" class="py-10 text-center text-sm text-slate-400">No activity yet. Submits, approvals, payments and edits will appear here.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="confirmBox" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50" @click="closeConfirm"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="font-bold text-slate-900">{{ confirmBox.title }}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">{{ confirmBox.message }}</p>
                </div>
                <div v-if="confirmBox.inputLabel" class="p-5">
                    <label class="text-xs font-bold text-slate-700 block mb-1.5">{{ confirmBox.inputLabel }}</label>
                    <input v-model="confirmBox.inputValue" type="text"
                        class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
                <div class="px-5 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
                    <button type="button" @click="closeConfirm"
                        class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 rounded-xl text-xs font-bold transition">Cancel</button>
                    <button type="button" @click="submitConfirm"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold transition">{{ confirmBox.confirmLabel }}</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.payroll-caps, .payroll-caps *, .payroll-caps input::placeholder { text-transform: capitalize; }
.bg-grid-slate-100 { background-image: radial-gradient(circle, #cbd5e1 1px, transparent 1px); background-size: 24px 24px; }
.bg-clip-text { -webkit-background-clip: text; background-clip: text; }
.backdrop-blur-sm { backdrop-filter: blur(8px); }
button:disabled { opacity: .45; cursor: not-allowed; }
:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
</style>
