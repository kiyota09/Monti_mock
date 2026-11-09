<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { toast } from '@/lib/feedback'
import { ArrowLeft, Banknote, CalendarDays, Check, CircleDollarSign, ClipboardList, Eye, FileText, Pencil, Plus, Search, Send, ShieldCheck, Wallet, X } from 'lucide-vue-next'

const props = defineProps({
    advances: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    permissions: { type: Object, default: () => ({}) },
})

const page = usePage()
const search = ref('')
const statusFilter = ref('All statuses')
const formOpen = ref(false)
const editing = ref(null)
const detail = ref(null)
const action = ref(null)
const employeeSearch = ref('')
const employeeDropdownOpen = ref(false)
const localDate = new Date()
localDate.setMinutes(localDate.getMinutes() - localDate.getTimezoneOffset())
const today = localDate.toISOString().slice(0, 10)

const form = useForm({
    employee_id: '',
    advance_amount: '',
    reason: '',
    request_date: today,
    planned_release_date: today,
    payment_method: 'bank_transfer',
    repayment_method: 'installment',
    installments: 2,
    remarks: '',
    payroll_deduction_authorized: false,
    authorization_document: null,
})

const workflow = useForm({
    stage: '',
    decision: 'approved',
    remarks: '',
    payment_reference: '',
    actual_release_date: today,
})

const selectedEmployee = computed(() => (props.employees || []).find((employee) => String(employee.id) === String(form.employee_id)) || null)
const matchingEmployees = computed(() => {
    const query = employeeSearch.value.trim().toLowerCase()
    return (props.employees || []).filter((employee) => !query || String(employee.name || '').toLowerCase().includes(query))
})

const handleEmployeeSearch = () => {
    if (selectedEmployee.value && employeeSearch.value !== selectedEmployee.value.name) form.employee_id = ''
    employeeDropdownOpen.value = true
}

const chooseEmployee = (employee) => {
    form.employee_id = employee.id
    employeeSearch.value = employee.name
    employeeDropdownOpen.value = false
}

const money = (value) => `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
const dateLabel = (value) => {
    if (!value) return '—'
    const date = new Date(`${value}T00:00:00`)
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
}
const dateTimeLabel = (value) => value ? new Date(value.replace(' ', 'T')).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) : '—'

const summaryCards = computed(() => [
    { label: 'Total Advances', value: Number(props.summary.total_advances || 0).toLocaleString(), note: 'Active and completed records', icon: ClipboardList, accent: 'text-slate-700 bg-slate-100' },
    { label: 'Pending Approval', value: Number(props.summary.pending_approval || 0).toLocaleString(), note: 'Awaiting a workflow decision', icon: CalendarDays, accent: 'text-amber-700 bg-amber-50' },
    { label: 'Released Amount', value: money(props.summary.released_amount), note: 'Total amount disbursed', icon: Banknote, accent: 'text-emerald-700 bg-emerald-50' },
    { label: 'Outstanding Balance', value: money(props.summary.outstanding_balance), note: 'Awaiting repayment', icon: Wallet, accent: 'text-blue-700 bg-blue-50' },
    { label: 'Allowance Total', value: money(props.summary.allowance_total), note: `Current payroll period · ${props.summary.allowance_period || '—'}`, icon: CircleDollarSign, accent: 'text-violet-700 bg-violet-50' },
])

const statuses = ['All statuses', 'Draft', 'Pending Approval', 'Approved for Release', 'Released', 'Partially Repaid', 'Fully Repaid', 'Rejected', 'Cancelled']
const filteredAdvances = computed(() => {
    const query = search.value.trim().toLowerCase()
    return (props.advances || []).filter((advance) => {
        if (statusFilter.value !== 'All statuses' && advance.status !== statusFilter.value) return false
        if (!query) return true
        return [advance.advance_number, advance.employee_name, advance.employee_id, advance.department, advance.position]
            .some((value) => String(value || '').toLowerCase().includes(query))
    })
})

const statusClass = (status) => ({
    'Draft': 'bg-slate-100 text-slate-700 ring-slate-200',
    'Pending Approval': 'bg-amber-50 text-amber-800 ring-amber-200',
    'Approved for Release': 'bg-blue-50 text-blue-800 ring-blue-200',
    'Released': 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    'Partially Repaid': 'bg-indigo-50 text-indigo-800 ring-indigo-200',
    'Fully Repaid': 'bg-teal-50 text-teal-800 ring-teal-200',
    'Rejected': 'bg-rose-50 text-rose-800 ring-rose-200',
    'Cancelled': 'bg-slate-100 text-slate-500 ring-slate-200',
}[status] || 'bg-slate-100 text-slate-700 ring-slate-200')

const resetForm = () => {
    form.reset()
    form.clearErrors()
    form.employee_id = ''
    employeeSearch.value = ''
    employeeDropdownOpen.value = false
    form.request_date = today
    form.planned_release_date = today
    form.payment_method = 'bank_transfer'
    form.repayment_method = 'installment'
    form.installments = 2
    form.payroll_deduction_authorized = false
    form.authorization_document = null
}

const openCreate = () => {
    editing.value = null
    resetForm()
    formOpen.value = true
}

const openEdit = (advance) => {
    editing.value = advance
    form.clearErrors()
    form.employee_id = advance.employee_id
    employeeSearch.value = advance.employee_name
    employeeDropdownOpen.value = false
    form.advance_amount = advance.advance_amount
    form.reason = advance.reason
    form.request_date = advance.request_date
    form.planned_release_date = advance.planned_release_date
    form.payment_method = advance.payment_method
    form.repayment_method = advance.repayment_method
    form.installments = advance.installments || 2
    form.remarks = advance.remarks || ''
    form.payroll_deduction_authorized = advance.payroll_deduction_authorized
    form.authorization_document = null
    formOpen.value = true
}

const closeForm = () => {
    formOpen.value = false
    editing.value = null
    form.reset()
}

const changeRepaymentMethod = () => {
    if (form.repayment_method === 'one_time') form.installments = null
    else if (!form.installments) form.installments = 2
}

const submitForm = () => {
    const options = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: closeForm,
    }
    if (editing.value) {
        form.patch(route('hrm.payroll.advances.update', editing.value.id), options)
    } else {
        form.post(route('hrm.payroll.advances.store'), options)
    }
}

const openAction = (mode, advance, stage = null) => {
    if (mode === 'submit' && (!advance.payroll_deduction_authorized || !advance.authorization_url)) {
        toast.warning('Attach the signed payroll-deduction authorization before submitting this advance.');
        openEdit(advance);
        return;
    }
    action.value = { mode, advance, stage }
    workflow.reset()
    workflow.clearErrors()
    workflow.actual_release_date = today
    workflow.stage = stage || ''
    workflow.decision = 'approved'
    workflow.payment_reference = `${advance.advance_number}-PAY`
}

const closeAction = () => {
    action.value = null
    workflow.reset()
    workflow.clearErrors()
}

const openReject = (advance) => {
    openAction('decision', advance, advance.pending_stage)
    workflow.decision = 'rejected'
}

const runAction = () => {
    if (!action.value) return
    const { mode, advance } = action.value
    const options = { preserveScroll: true, onSuccess: closeAction }
    if (mode === 'submit') {
        router.post(route('hrm.payroll.advances.submit', advance.id), {}, options)
    } else if (mode === 'decision') {
        workflow.post(route('hrm.payroll.advances.decision', advance.id), options)
    } else if (mode === 'release') {
        workflow.post(route('hrm.payroll.advances.release', advance.id), options)
    } else if (mode === 'cancel') {
        workflow.post(route('hrm.payroll.advances.cancel', advance.id), options)
    }
}

const stages = ['manager', 'hr', 'finance']
const stageLabel = (stage) => ({ manager: 'Manager Approval', hr: 'HR Verification', finance: 'Finance Approval' }[stage] || stage)
const approvalFor = (advance, stage) => advance.approvals?.find((item) => item.stage === stage)
const flashMessage = computed(() => page.props.flash?.message || page.props.flash?.success || '')
const pageError = computed(() => {
    const errors = page.props.errors || {}
    const message = page.props.flash?.error || errors.error || Object.values(errors)[0] || ''
    return Array.isArray(message) ? message[0] : message
})
</script>

<template>
    <Head title="Advance Payments" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-slate-50/70 px-4 py-5 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-5">
                <header class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="mb-2 flex items-center gap-2 text-xs font-semibold text-slate-500">
                            <Link :href="route('hrm.payroll')" class="hover:text-blue-700">Dashboard / Payroll</Link>
                            <span>/</span><span class="text-slate-800">Advance Payments</span>
                        </div>
                        <h1 class="text-2xl font-black tracking-tight text-[#263c43]">Advance Payment Management</h1>
                        <p class="mt-1 max-w-2xl text-sm text-slate-500">Review employee advances, manage approvals, release funds, and track payroll repayments.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link :href="route('hrm.payroll')" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                            <ArrowLeft class="h-4 w-4" /> Payroll
                        </Link>
                        <button v-if="permissions.can_create" type="button" @click="openCreate" class="inline-flex items-center gap-2 rounded-xl bg-[#263c43] px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#1e3036]">
                            <Plus class="h-4 w-4" /> New Advance
                        </button>
                    </div>
                </header>

                <div v-if="flashMessage" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ flashMessage }}</div>
                <div v-if="pageError" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{{ pageError }}</div>

                <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <article v-for="card in summaryCards" :key="card.label" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ card.label }}</p>
                                <p class="mt-2 break-words text-2xl font-black tabular-nums text-slate-900">{{ card.value }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ card.note }}</p>
                            </div>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="card.accent"><component :is="card.icon" class="h-5 w-5" /></span>
                        </div>
                    </article>
                </section>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-slate-100 p-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="text-base font-extrabold text-slate-900">Advance Payment Records</h2>
                            <p class="mt-0.5 text-xs text-slate-500">{{ filteredAdvances.length }} record{{ filteredAdvances.length === 1 ? '' : 's' }}</p>
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <label class="relative block">
                                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input v-model="search" type="search" placeholder="Search advance or employee..." class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-700 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100 sm:w-72" />
                            </label>
                            <select v-model="statusFilter" aria-label="Filter advances by status" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100">
                                <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[980px] border-collapse text-left text-xs">
                            <thead class="bg-[#2d4148] text-[9px] font-bold uppercase tracking-wider text-white/90">
                                <tr>
                                    <th class="px-2.5 py-2">Advance Number</th>
                                    <th class="px-2.5 py-2">Employee</th>
                                    <th class="px-2.5 py-2">Department / Position</th>
                                    <th class="px-2.5 py-2 text-right">Advance Amount</th>
                                    <th class="px-2.5 py-2">Release Date</th>
                                    <th class="px-2.5 py-2">Repayment</th>
                                    <th class="px-2.5 py-2 text-right">Outstanding</th>
                                    <th class="px-2.5 py-2">Status</th>
                                    <th class="px-2.5 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="advance in filteredAdvances" :key="advance.id" class="align-top transition hover:bg-slate-50/70">
                                    <td class="whitespace-nowrap px-2.5 py-2.5">
                                        <p class="font-bold text-slate-900">{{ advance.advance_number }}</p>
                                        <p class="mt-0.5 text-[10px] text-slate-500">Requested {{ dateLabel(advance.request_date) }}</p>
                                    </td>
                                    <td class="px-2.5 py-2.5">
                                        <p class="font-semibold text-slate-800">{{ advance.employee_name }}</p>
                                        <p class="mt-0.5 text-[10px] text-slate-500">{{ advance.employee_id }}</p>
                                    </td>
                                    <td class="px-2.5 py-2.5">
                                        <p class="font-medium text-slate-700">{{ advance.department }}</p>
                                        <p class="mt-0.5 text-[10px] text-slate-500">{{ advance.position }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-2.5 py-2.5 text-right font-bold tabular-nums text-slate-900">{{ money(advance.advance_amount) }}</td>
                                    <td class="whitespace-nowrap px-2.5 py-2.5 text-slate-600">{{ advance.actual_release_date ? dateLabel(advance.actual_release_date) : dateLabel(advance.planned_release_date) }}</td>
                                    <td class="px-2.5 py-2.5 text-slate-600">
                                        <span>{{ advance.repayment_method === 'one_time' ? 'One-time' : `${advance.installments} installments` }}</span>
                                        <span v-if="advance.repayment_method === 'installment'" class="block text-[10px] text-slate-500">{{ money(advance.installment_amount) }} / payroll</span>
                                    </td>
                                    <td class="whitespace-nowrap px-2.5 py-2.5 text-right font-semibold tabular-nums text-slate-800">{{ money(advance.outstanding_balance) }}</td>
                                    <td class="whitespace-nowrap px-2.5 py-2.5"><span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold ring-1 ring-inset" :class="statusClass(advance.status)">{{ advance.status }}</span></td>
                                    <td class="min-w-[190px] px-2.5 py-2.5">
                                        <div class="flex flex-wrap justify-end gap-1.5">
                                            <button type="button" @click.stop="detail = advance" title="View advance" aria-label="View advance" class="rounded-lg border border-slate-200 p-1.5 text-slate-700 transition hover:border-slate-300 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-300"><Eye class="h-3.5 w-3.5" /></button>
                                            <button v-if="advance.can_edit" type="button" @click.stop="openEdit(advance)" title="Edit advance" aria-label="Edit advance" class="rounded-lg border border-blue-200 p-1.5 text-blue-700 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-300"><Pencil class="h-3.5 w-3.5" /></button>
                                            <button v-if="advance.can_submit" type="button" @click.stop="openAction('submit', advance)" title="Submit advance" aria-label="Submit advance" class="rounded-lg bg-blue-700 p-1.5 text-white transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-300"><Send class="h-3.5 w-3.5" /></button>
                                            <template v-if="advance.can_decide">
                                                <button type="button" @click.stop="openAction('decision', advance, advance.pending_stage)" :title="advance.pending_stage === 'hr' ? 'Verify advance' : advance.pending_stage === 'finance' ? 'Approve advance' : 'Review advance'" :aria-label="advance.pending_stage === 'hr' ? 'Verify advance' : advance.pending_stage === 'finance' ? 'Approve advance' : 'Review advance'" class="rounded-lg bg-emerald-700 p-1.5 text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-300"><Check class="h-3.5 w-3.5" /></button>
                                                <button type="button" @click.stop="openReject(advance)" title="Reject advance" aria-label="Reject advance" class="rounded-lg border border-rose-200 p-1.5 text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-300"><X class="h-3.5 w-3.5" /></button>
                                            </template>
                                            <button v-if="advance.can_release" type="button" @click.stop="openAction('release', advance)" title="Release advance" aria-label="Release advance" class="rounded-lg bg-[#263c43] p-1.5 text-white transition hover:bg-[#1e3036] focus:outline-none focus:ring-2 focus:ring-slate-400"><Banknote class="h-3.5 w-3.5" /></button>
                                            <button v-if="advance.can_cancel" type="button" @click.stop="openAction('cancel', advance)" title="Cancel advance" aria-label="Cancel advance" class="rounded-lg border border-slate-200 p-1.5 text-slate-600 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-300"><X class="h-3.5 w-3.5" /></button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredAdvances.length === 0">
                                    <td colspan="9" class="px-4 py-14 text-center">
                                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500"><CircleDollarSign class="h-6 w-6" /></div>
                                        <p class="mt-3 text-sm font-bold text-slate-800">No advance payments found</p>
                                        <p class="mt-1 text-xs text-slate-500">Create an advance record or adjust your search filters.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-900">
                    Payroll deductions are scheduled only after Finance records the actual release and the employee’s signed authorization is on file. Confirm the deduction basis is lawful before releasing or deducting an advance.
                </p>
            </div>
        </div>

        <div v-if="formOpen" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-3 backdrop-blur-sm sm:p-5" role="presentation" @click.self="closeForm">
            <section class="flex max-h-[94vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="advance-form-title">
                <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Payroll · Advance Payments</p>
                        <h2 id="advance-form-title" class="mt-1 text-lg font-black text-slate-900">{{ editing ? 'Edit Advance Draft' : 'Create Advance Payment' }}</h2>
                    </div>
                    <button type="button" @click="closeForm" aria-label="Close form" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"><X class="h-5 w-5" /></button>
                </header>

                <form class="min-h-0 flex-1 overflow-y-auto" @submit.prevent="submitForm">
                    <div class="space-y-5 px-5 py-5 sm:px-6">
                        <div v-if="form.errors.error || form.errors.authorization_document" class="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-3 text-xs font-semibold text-rose-800">
                            {{ form.errors.error || form.errors.authorization_document }}
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="relative sm:col-span-2">
                                <label for="advance-employee-search" class="mb-1.5 block text-xs font-bold text-slate-700">Employee <span class="text-rose-600">*</span></label>
                                <div class="relative">
                                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                    <input id="advance-employee-search" v-model="employeeSearch" type="search" role="combobox" aria-autocomplete="list" aria-controls="advance-employee-options" :aria-expanded="employeeDropdownOpen" :aria-activedescendant="undefined" required :placeholder="selectedEmployee?.name || 'Search employee name...'" @focus="employeeDropdownOpen = true" @input="handleEmployeeSearch" @keydown.esc="employeeDropdownOpen = false" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" />
                                </div>
                                <div v-if="employeeDropdownOpen" id="advance-employee-options" role="listbox" class="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                                    <button v-for="employee in matchingEmployees" :key="employee.id" type="button" role="option" :aria-selected="String(form.employee_id) === String(employee.id)" @click="chooseEmployee(employee)" class="block w-full px-3 py-2.5 text-left text-sm text-slate-800 transition hover:bg-blue-50 focus:bg-blue-50 focus:outline-none">
                                        {{ employee.name }}
                                    </button>
                                    <p v-if="matchingEmployees.length === 0" class="px-3 py-3 text-sm text-slate-500">No employees match that name.</p>
                                </div>
                                <small v-if="form.errors.employee_id" class="mt-1 block text-xs text-rose-600">{{ form.errors.employee_id }}</small>
                            </div>
                            <label>
                                <span class="mb-1.5 block text-xs font-bold text-slate-700">Job Position</span>
                                <input :value="selectedEmployee?.position || ''" readonly placeholder="Select an employee first" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-600 outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300" />
                            </label>
                            <label>
                                <span class="mb-1.5 block text-xs font-bold text-slate-700">Department</span>
                                <input :value="selectedEmployee?.department || ''" readonly placeholder="Select an employee first" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-600 outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300" />
                            </label>
                            <label>
                                <span class="mb-1.5 block text-xs font-bold text-slate-700">Advance Amount <span class="text-rose-600">*</span></span>
                                <div class="relative"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-bold text-slate-500">₱</span><input v-model="form.advance_amount" type="number" min="0.01" step="0.01" required placeholder="0.00" class="w-full rounded-xl border border-slate-200 py-2.5 pl-8 pr-3 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" /></div>
                                <small v-if="form.errors.advance_amount" class="mt-1 block text-xs text-rose-600">{{ form.errors.advance_amount }}</small>
                            </label>
                            <label>
                                <span class="mb-1.5 block text-xs font-bold text-slate-700">Reason <span class="text-rose-600">*</span></span>
                                <input v-model="form.reason" type="text" maxlength="2000" required placeholder="Reason for the advance" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" />
                                <small v-if="form.errors.reason" class="mt-1 block text-xs text-rose-600">{{ form.errors.reason }}</small>
                            </label>
                            <label>
                                <span class="mb-1.5 block text-xs font-bold text-slate-700">Request / Transaction Date <span class="text-rose-600">*</span></span>
                                <input v-model="form.request_date" type="date" required class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" />
                            </label>
                            <label>
                                <span class="mb-1.5 block text-xs font-bold text-slate-700">Payment Release Date <span class="text-rose-600">*</span></span>
                                <input v-model="form.planned_release_date" type="date" :min="form.request_date" required class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" />
                            </label>
                            <label>
                                <span class="mb-1.5 block text-xs font-bold text-slate-700">Payment Method <span class="text-rose-600">*</span></span>
                                <select v-model="form.payment_method" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100">
                                    <option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option><option value="e_wallet">E-wallet</option>
                                </select>
                            </label>
                            <label>
                                <span class="mb-1.5 block text-xs font-bold text-slate-700">Repayment Method <span class="text-rose-600">*</span></span>
                                <select v-model="form.repayment_method" @change="changeRepaymentMethod" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100">
                                    <option value="one_time">One-time</option><option value="installment">Installment</option>
                                </select>
                            </label>
                            <label v-if="form.repayment_method === 'installment'">
                                <span class="mb-1.5 block text-xs font-bold text-slate-700">Number of Installments <span class="text-rose-600">*</span></span>
                                <input v-model.number="form.installments" type="number" min="2" max="36" required class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" />
                                <small v-if="form.errors.installments" class="mt-1 block text-xs text-rose-600">{{ form.errors.installments }}</small>
                            </label>
                            <label :class="form.repayment_method === 'installment' ? '' : 'sm:col-span-2'">
                                <span class="mb-1.5 block text-xs font-bold text-slate-700">Remarks</span>
                                <input v-model="form.remarks" type="text" maxlength="3000" placeholder="Optional internal note" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" />
                            </label>
                        </div>

                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <div class="flex items-start gap-3">
                                <ShieldCheck class="mt-0.5 h-5 w-5 shrink-0 text-amber-700" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-extrabold text-amber-950">Employee payroll-deduction authorization</p>
                                    <p class="mt-1 text-xs leading-5 text-amber-900">Required before submission because repayments are deducted from payroll. Attach the signed employee authorization. The record stays a draft until this is provided.</p>
                                    <label class="mt-3 flex items-start gap-2 text-xs font-semibold leading-5 text-slate-800">
                                        <input v-model="form.payroll_deduction_authorized" type="checkbox" class="mt-1 rounded border-slate-300 text-blue-700 focus:ring-blue-500" />
                                        <span>I confirm the employee’s written payroll-deduction authorization is attached.</span>
                                    </label>
                                    <label class="mt-3 block">
                                        <span class="mb-1.5 block text-xs font-bold text-slate-700">Signed authorization / supporting document</span>
                                        <input type="file" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx" @change="form.authorization_document = $event.target.files?.[0] || null" class="block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-xs file:font-bold file:text-slate-700 file:ring-1 file:ring-slate-200 hover:file:bg-slate-100" />
                                        <span v-if="editing?.authorization_document_name" class="mt-1 block text-[11px] text-slate-500">Current file: {{ editing.authorization_document_name }}</span>
                                        <span class="mt-1 block text-[11px] text-slate-500">PDF, Word, PNG or JPG · up to 10 MB</span>
                                    </label>
                                    <small v-if="form.errors.payroll_deduction_authorized" class="mt-1 block text-xs text-rose-600">{{ form.errors.payroll_deduction_authorized }}</small>
                                    <small v-if="form.errors.authorization_document" class="mt-1 block text-xs text-rose-600">{{ form.errors.authorization_document }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <footer class="sticky bottom-0 flex flex-wrap justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4 sm:px-6">
                        <button type="button" @click="closeForm" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-100">Close</button>
                        <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-xl bg-[#263c43] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#1e3036] disabled:opacity-50">
                            <span v-if="form.processing" class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                            {{ form.processing ? 'Saving...' : 'Save Draft' }}
                        </button>
                    </footer>
                </form>
            </section>
        </div>

        <div v-if="action" class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="presentation" @click.self="closeAction">
            <section class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="advance-action-title">
                <header class="border-b border-slate-100 px-5 py-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">{{ action.advance.advance_number }}</p>
                            <h2 id="advance-action-title" class="mt-1 text-base font-black text-slate-900">
                                {{ action.mode === 'submit' ? 'Submit for Manager Approval' : action.mode === 'decision' ? `${stageLabel(action.stage)} · ${workflow.decision === 'rejected' ? 'Reject' : 'Review'}` : action.mode === 'release' ? 'Release Advance Payment' : 'Cancel Advance Payment' }}
                            </h2>
                        </div>
                        <button type="button" @click="closeAction" aria-label="Close" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100"><X class="h-4 w-4" /></button>
                    </div>
                </header>
                <div class="max-h-[65vh] space-y-4 overflow-y-auto px-5 py-4">
                    <p class="text-sm leading-6 text-slate-600">
                        {{ action.mode === 'submit' ? 'The request will enter the Manager → HR → Finance approval workflow.' : action.mode === 'decision' ? `${action.advance.employee_name} · ${money(action.advance.advance_amount)}. Record your decision and remarks for this approval stage.` : action.mode === 'release' ? 'Confirm the actual payment reference and release date. Payroll repayments become eligible only after this release is recorded.' : 'This advance has not been released. Cancel it to stop the approval and repayment workflow.' }}
                    </p>
                    <div v-if="action.mode === 'decision'" class="space-y-3">
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" @click="workflow.decision = 'approved'" class="rounded-xl border px-3 py-2.5 text-xs font-bold" :class="workflow.decision === 'approved' ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-slate-200 text-slate-600'">Approve stage</button>
                            <button type="button" @click="workflow.decision = 'rejected'" class="rounded-xl border px-3 py-2.5 text-xs font-bold" :class="workflow.decision === 'rejected' ? 'border-rose-300 bg-rose-50 text-rose-800' : 'border-slate-200 text-slate-600'">Reject request</button>
                        </div>
                        <label class="block">
                            <span class="mb-1.5 block text-xs font-bold text-slate-700">{{ workflow.decision === 'rejected' ? 'Rejection remarks (required)' : 'Approval remarks (optional)' }}</span>
                            <textarea v-model="workflow.remarks" rows="3" :required="workflow.decision === 'rejected'" maxlength="3000" placeholder="Add a review note..." class="w-full resize-y rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"></textarea>
                            <small v-if="workflow.errors.remarks" class="mt-1 block text-xs text-rose-600">{{ workflow.errors.remarks }}</small>
                        </label>
                    </div>
                    <div v-if="action.mode === 'release'" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="sm:col-span-2"><span class="mb-1.5 block text-xs font-bold text-slate-700">Payment reference number <span class="text-rose-600">*</span></span><input v-model="workflow.payment_reference" required maxlength="100" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" /><small v-if="workflow.errors.payment_reference" class="mt-1 block text-xs text-rose-600">{{ workflow.errors.payment_reference }}</small></label>
                        <label><span class="mb-1.5 block text-xs font-bold text-slate-700">Actual release date <span class="text-rose-600">*</span></span><input v-model="workflow.actual_release_date" type="date" :max="today" required class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" /><small v-if="workflow.errors.actual_release_date" class="mt-1 block text-xs text-rose-600">{{ workflow.errors.actual_release_date }}</small></label>
                        <label><span class="mb-1.5 block text-xs font-bold text-slate-700">Finance remarks</span><input v-model="workflow.remarks" maxlength="3000" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" /></label>
                    </div>
                    <label v-if="action.mode === 'cancel'" class="block"><span class="mb-1.5 block text-xs font-bold text-slate-700">Cancellation remarks</span><textarea v-model="workflow.remarks" rows="2" maxlength="3000" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"></textarea></label>
                    <div v-if="workflow.errors.error" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">{{ workflow.errors.error }}</div>
                </div>
                <footer class="flex flex-wrap justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4">
                    <button type="button" @click="closeAction" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-100">Back</button>
                    <button type="button" :disabled="workflow.processing" @click="runAction" class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold text-white disabled:opacity-50" :class="action.mode === 'cancel' || (action.mode === 'decision' && workflow.decision === 'rejected') ? 'bg-rose-700 hover:bg-rose-800' : 'bg-[#263c43] hover:bg-[#1e3036]'">
                        <span v-if="workflow.processing" class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                        {{ workflow.processing ? 'Processing...' : action.mode === 'submit' ? 'Submit request' : action.mode === 'decision' ? workflow.decision === 'rejected' ? 'Reject request' : 'Confirm approval' : action.mode === 'release' ? 'Confirm release' : 'Confirm cancellation' }}
                    </button>
                </footer>
            </section>
        </div>

        <div v-if="detail" class="fixed inset-0 z-[75] flex items-center justify-center bg-slate-950/60 p-3 backdrop-blur-sm sm:p-5" role="presentation" @click.self="detail = null">
            <section class="flex max-h-[94vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="advance-detail-title">
                <header class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4 sm:px-6">
                    <div>
                        <div class="flex flex-wrap items-center gap-2"><h2 id="advance-detail-title" class="text-lg font-black text-slate-900">{{ detail.advance_number }}</h2><span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset" :class="statusClass(detail.status)">{{ detail.status }}</span></div>
                        <p class="mt-1 text-xs text-slate-500">{{ detail.employee_name }} · {{ detail.employee_id }} · {{ detail.department }} / {{ detail.position }}</p>
                    </div>
                    <button type="button" @click="detail = null" aria-label="Close details" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"><X class="h-5 w-5" /></button>
                </header>
                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-5 sm:px-6">
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-bold uppercase text-slate-500">Original Advance</p><p class="mt-1 text-sm font-black text-slate-900">{{ money(detail.advance_amount) }}</p></div>
                        <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-bold uppercase text-slate-500">Total Repaid</p><p class="mt-1 text-sm font-black text-emerald-700">{{ money(detail.total_repaid) }}</p></div>
                        <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-bold uppercase text-slate-500">Outstanding Balance</p><p class="mt-1 text-sm font-black text-blue-800">{{ money(detail.outstanding_balance) }}</p></div>
                        <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-bold uppercase text-slate-500">Deduction / Payroll</p><p class="mt-1 text-sm font-black text-slate-900">{{ detail.repayment_method === 'one_time' ? money(detail.advance_amount) : money(detail.installment_amount) }}</p></div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <section class="rounded-xl border border-slate-200 p-4">
                            <h3 class="text-xs font-extrabold uppercase tracking-wide text-slate-700">Advance Details</h3>
                            <dl class="mt-3 grid grid-cols-[minmax(115px,0.7fr)_1.3fr] gap-x-3 gap-y-2 text-xs">
                                <dt class="text-slate-500">Request date</dt><dd class="font-semibold text-slate-800">{{ dateLabel(detail.request_date) }}</dd>
                                <dt class="text-slate-500">Planned release</dt><dd class="font-semibold text-slate-800">{{ dateLabel(detail.planned_release_date) }}</dd>
                                <dt class="text-slate-500">Actual release</dt><dd class="font-semibold text-slate-800">{{ dateLabel(detail.actual_release_date) }}</dd>
                                <dt class="text-slate-500">Payment method</dt><dd class="font-semibold capitalize text-slate-800">{{ detail.payment_method.replace('_', ' ') }}</dd>
                                <dt class="text-slate-500">Payment reference</dt><dd class="break-all font-semibold text-slate-800">{{ detail.payment_reference || '—' }}</dd>
                                <dt class="text-slate-500">Repayment plan</dt><dd class="font-semibold text-slate-800">{{ detail.repayment_method === 'one_time' ? 'One-time' : `${detail.installments} installments` }}</dd>
                                <dt class="text-slate-500">Reason</dt><dd class="font-semibold text-slate-800">{{ detail.reason }}</dd>
                                <dt class="text-slate-500">Remarks</dt><dd class="font-semibold text-slate-800">{{ detail.remarks || '—' }}</dd>
                                <dt class="text-slate-500">Authorization</dt><dd class="font-semibold text-slate-800">{{ detail.payroll_deduction_authorized ? 'Confirmed' : 'Not recorded' }}</dd>
                                <dt class="text-slate-500">Supporting file</dt><dd><a v-if="detail.authorization_url" :href="detail.authorization_url" class="inline-flex items-center gap-1 font-bold text-blue-700 hover:underline"><FileText class="h-3.5 w-3.5" />{{ detail.authorization_document_name }}</a><span v-else class="text-slate-500">—</span></dd>
                            </dl>
                        </section>

                        <section class="rounded-xl border border-slate-200 p-4">
                            <h3 class="text-xs font-extrabold uppercase tracking-wide text-slate-700">Approval & Disbursement</h3>
                            <ol class="mt-3 space-y-3">
                                <li v-for="stage in stages" :key="stage" class="flex gap-3">
                                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full" :class="approvalFor(detail, stage)?.decision === 'approved' ? 'bg-emerald-100 text-emerald-700' : approvalFor(detail, stage)?.decision === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-400'">
                                        <Check v-if="approvalFor(detail, stage)?.decision === 'approved'" class="h-3.5 w-3.5" /><X v-else-if="approvalFor(detail, stage)?.decision === 'rejected'" class="h-3.5 w-3.5" /><span v-else class="text-[10px] font-black">{{ stages.indexOf(stage) + 1 }}</span>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-bold text-slate-800">{{ stageLabel(stage) }} <span v-if="approvalFor(detail, stage)" class="font-semibold capitalize text-slate-500">· {{ approvalFor(detail, stage).decision }}</span></p>
                                        <p v-if="approvalFor(detail, stage)" class="mt-0.5 text-[11px] text-slate-500">{{ approvalFor(detail, stage).approver }} · {{ dateTimeLabel(approvalFor(detail, stage).decided_at) }}</p>
                                        <p v-if="approvalFor(detail, stage)?.remarks" class="mt-1 text-xs leading-5 text-slate-600">{{ approvalFor(detail, stage).remarks }}</p>
                                        <p v-else-if="detail.pending_stage === stage" class="mt-0.5 text-[11px] text-slate-400">Awaiting decision</p>
                                        <p v-else-if="!approvalFor(detail, stage)" class="mt-0.5 text-[11px] text-slate-400">Not started</p>
                                    </div>
                                </li>
                            </ol>
                        </section>
                    </div>

                    <section class="overflow-hidden rounded-xl border border-slate-200">
                        <div class="border-b border-slate-100 px-4 py-3"><h3 class="text-xs font-extrabold uppercase tracking-wide text-slate-700">Payroll Deduction Schedule</h3></div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[420px] text-left text-xs">
                                <thead class="bg-slate-50 font-bold text-slate-500"><tr><th class="px-4 py-2.5">Payroll Period</th><th class="px-4 py-2.5 text-right">Deduction</th><th class="px-4 py-2.5">Status</th></tr></thead>
                                <tbody class="divide-y divide-slate-100"><tr v-for="item in detail.deductions" :key="`${item.payroll_id}-${item.status}`"><td class="px-4 py-2.5 font-semibold text-slate-800">{{ item.payroll_period || `Payroll #${item.payroll_id}` }}</td><td class="px-4 py-2.5 text-right font-bold tabular-nums text-slate-800">{{ money(item.amount) }}</td><td class="px-4 py-2.5"><span class="rounded-full px-2 py-1 text-[10px] font-bold" :class="item.status === 'paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">{{ item.status === 'paid' ? 'Paid' : 'Reserved' }}</span></td></tr><tr v-if="!detail.deductions?.length"><td colspan="3" class="px-4 py-7 text-center text-slate-500">No payroll deductions scheduled.</td></tr></tbody>
                            </table>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-xl border border-slate-200">
                        <div class="border-b border-slate-100 px-4 py-3"><h3 class="text-xs font-extrabold uppercase tracking-wide text-slate-700">Repayment History</h3></div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[520px] text-left text-xs">
                                <thead class="bg-slate-50 font-bold text-slate-500"><tr><th class="px-4 py-2.5">Payroll Period</th><th class="px-4 py-2.5">Date Recorded</th><th class="px-4 py-2.5 text-right">Amount</th><th class="px-4 py-2.5">Payroll ID</th></tr></thead>
                                <tbody class="divide-y divide-slate-100"><tr v-for="repayment in detail.repayments" :key="`${repayment.payroll_id}-${repayment.repaid_at}`"><td class="px-4 py-2.5 font-semibold text-slate-800">{{ repayment.payroll_period }}</td><td class="px-4 py-2.5 text-slate-600">{{ dateTimeLabel(repayment.repaid_at) }}</td><td class="px-4 py-2.5 text-right font-bold text-emerald-700">{{ money(repayment.amount) }}</td><td class="px-4 py-2.5 text-slate-600">#{{ repayment.payroll_id }}</td></tr><tr v-if="!detail.repayments?.length"><td colspan="4" class="px-4 py-8 text-center text-slate-500">No payroll repayments recorded.</td></tr></tbody>
                            </table>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-xl border border-slate-200">
                        <div class="border-b border-slate-100 px-4 py-3"><h3 class="text-xs font-extrabold uppercase tracking-wide text-slate-700">Audit History</h3></div>
                        <div class="max-h-52 overflow-y-auto divide-y divide-slate-100">
                            <div v-for="(log, index) in detail.audit_logs" :key="`${log.action}-${index}`" class="flex flex-wrap items-start justify-between gap-2 px-4 py-3 text-xs">
                                <div><p class="font-bold capitalize text-slate-800">{{ log.action.replaceAll('_', ' ') }} <span v-if="log.from_status || log.to_status" class="font-medium text-slate-500">· {{ log.from_status || 'New' }} → {{ log.to_status || '—' }}</span></p><p class="mt-0.5 text-slate-500">{{ log.actor }}<span v-if="log.remarks"> · {{ log.remarks }}</span></p></div>
                                <time class="whitespace-nowrap text-[11px] text-slate-400">{{ dateTimeLabel(log.created_at) }}</time>
                            </div>
                            <p v-if="!detail.audit_logs?.length" class="px-4 py-6 text-center text-xs text-slate-500">No audit events recorded.</p>
                        </div>
                    </section>
                </div>
                <footer class="flex justify-end border-t border-slate-100 bg-slate-50 px-5 py-3 sm:px-6"><button type="button" @click="detail = null" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100">Close</button></footer>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
