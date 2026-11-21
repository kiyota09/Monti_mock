<script setup>
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, computed } from 'vue';

// Daily-rate calc mirrors App\Support\PayrollCalc::calc.
// Multipliers/divisors come from Payroll Settings (props.settings) with classic fallbacks.
// Net = Gross less statutory, loan, and released-advance deductions. Blank = 0.
function calcJs(e, st = {}) {
    const nightMult = Number(st.night_multiplier ?? 1.10), otMult = Number(st.ot_multiplier ?? 1.25), sunMult = Number(st.sun_multiplier ?? 1.30);
    const lateDiv = Number(st.late_minutes_divisor ?? 60) || 60;
    const dailyDiv = Number(st.daily_divisor ?? 26) || 26;
    const hrsDay = Number(st.hours_per_day ?? 8) || 8;
    let days = Math.max(0, Number(e.days ?? 0));
    let daily = Math.max(0, Number(e.daily ?? 0));
    const monthlyFallback = Math.max(0, Number(e.monthly ?? 0));
    if (!(daily > 0) && monthlyFallback > 0) daily = monthlyFallback / dailyDiv;
    const hourly = daily > 0 ? daily / hrsDay : 0;
    const nightH = Math.max(0, Number(e.nightH ?? 0)), otH = Math.max(0, Number(e.otH ?? 0));
    const sunH = Math.max(0, Number(e.sunH ?? 0)), lateM = Math.max(0, Number(e.lateM ?? 0));
    let holAmt = Math.max(0, Number(e.holAmt ?? 0));
    const holDays = Math.max(0, Number(e.holDays ?? 0));
    if (!(holAmt > 0) && holDays > 0 && daily > 0) holAmt = holDays * daily;
    const nightRate = daily * nightMult, otRate = hourly * otMult, sunRate = hourly * sunMult;
    const lateRate = hourly > 0 ? hourly / lateDiv : 0;
    const basic = days * daily;
    const nightAmt = nightH * hourly * nightMult, otAmt = otH * hourly * otMult, sunAmt = sunH * hourly * sunMult;
    const lateTotal = lateM * lateRate;
    const gross = basic + nightAmt + otAmt + sunAmt + holAmt - lateTotal;
    const sss = Math.max(0, Number(e.sss ?? 0)), phil = Math.max(0, Number(e.phil ?? 0)), pag = Math.max(0, Number(e.pag ?? 0));
    const sssLoan = Math.max(0, Number(e.sssLoan ?? 0)), pfLoan = Math.max(0, Number(e.pfLoan ?? 0));
    const advanceDeduction = Math.max(0, Number(e.advanceDeduction ?? 0));
    const ded = sss + phil + pag + sssLoan + pfLoan + advanceDeduction;
    let net = gross - ded; if (net < 0) net = 0;
    const capped = ded > gross;
    return { days, daily, hourly, nightH, otH, sunH, lateM, holAmt, nightRate, otRate, sunRate, lateRate, basic, nightAmt, otAmt, sunAmt, lateTotal, gross, sss, phil, pag, sssLoan, pfLoan, advanceDeduction, ded, net, capped, nightMult, otMult, sunMult, lateDiv, hrsDay };
}

const props = defineProps({ payroll: { type: Object, required: true }, calc: { type: Object, default: null }, frequency: { type: String, default: 'semi_monthly' }, attendance: { type: Object, default: null }, settings: { type: Object, default: () => ({}) } });
const page = usePage();
const isHR = computed(() => String(page.props.auth?.user?.role || '').toUpperCase() === 'HRM');
const editable = computed(() => isHR.value && props.payroll?.status === 'Draft');

const form = useForm({
    employee_name: props.payroll?.employee_name || '',
    job_title: props.payroll?.job_title || '',
    department: props.payroll?.department || '',
    monthly_salary: Number(props.payroll?.monthly_salary ?? props.payroll?.base_salary ?? 0),
    days_worked: Number(props.payroll?.days_worked ?? props.attendance?.present ?? 0),
    daily_rate: Number(props.payroll?.daily_rate ?? 0),
    overtime_hours: Number(props.payroll?.overtime_hours ?? 0),
    night_hours: Number(props.payroll?.night_hours ?? 0),
    sunday_restday_hours: Number(props.payroll?.sunday_restday_hours ?? 0),
    holiday_amt: Number(props.payroll?.holiday_amt ?? 0),
    late_minutes: Number(props.payroll?.late_minutes ?? 0),
    sss_deduction: Number(props.payroll?.sss_deduction ?? 0),
    philhealth_deduction: Number(props.payroll?.philhealth_deduction ?? 0),
    pagibig_deduction: Number(props.payroll?.pagibig_deduction ?? 0),
    sss_loan: Number(props.payroll?.sss_loan ?? 0),
    pf_loan: Number(props.payroll?.pf_loan ?? 0),
    advance_deduction: Number(props.payroll?.advance_deduction ?? 0),
});

const live = computed(() => calcJs({
    days: form.days_worked, daily: form.daily_rate, monthly: form.monthly_salary,
    otH: form.overtime_hours, nightH: form.night_hours, sunH: form.sunday_restday_hours,
    holAmt: form.holiday_amt, lateM: form.late_minutes,
    sss: form.sss_deduction, phil: form.philhealth_deduction, pag: form.pagibig_deduction,
    sssLoan: form.sss_loan, pfLoan: form.pf_loan, advanceDeduction: form.advance_deduction,
}, props.settings || {}));
const otWarnAt = computed(() => Number(props.settings?.ot_warning_hours ?? 40));

const peso = (v) => '₱' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const num2 = (v) => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
// Blank inputs show as — (spec uses blank / - for empty hours and empty deductions).
const blankIfZero = (v, suffix = '') => {
    const n = Number(v || 0);
    if (!n) return '—';
    return suffix ? `${num2(n)}${suffix}` : num2(n);
};
const blankPeso = (v) => {
    const n = Number(v || 0);
    if (!n) return '—';
    return peso(n);
};

// Attendance / days-present summary for the payslip box.
// Live attendance (full pay-period) vs stored payroll snapshot (generation cutoff).
// "Days present" = live attendance now. "Days worked" = stored payroll value used in math.
const presentDays = computed(() => Number(props.attendance?.present ?? props.payroll?.days_worked ?? 0));
const scheduledDays = computed(() => Number(props.attendance?.scheduled ?? 0));
const absentDaysView = computed(() => Number(props.attendance?.absent ?? props.payroll?.absent_days ?? 0));
const leaveDaysView = computed(() => Number(props.attendance?.leave ?? 0));
const storedDays = computed(() => Number(props.payroll?.days_worked ?? 0));
const daysMismatch = computed(() => props.attendance && Number(form.days_worked) !== Number(presentDays.value));
const useAttendanceDays = () => { form.days_worked = Number(presentDays.value); };
const presentDates = computed(() => Array.isArray(props.attendance?.present_dates) ? props.attendance.present_dates : []);
const shortDay = (ymd) => {
    try {
        const d = new Date(ymd + 'T00:00:00');
        return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
    } catch { return ymd; }
};
const presentDatesLabel = computed(() => presentDates.value.map(shortDay).join(', '));
const printDate = computed(() => {
    try {
        return new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
    } catch { return ''; }
});

const save = () => {
    if (!String(form.employee_name || '').trim()) {
        confirmBox.value = { title: 'Save changes', message: 'Enter a name first.', confirmLabel: 'Understood', run: null };
        return;
    }
    form.patch(route('hrm.payroll.update', props.payroll.id), { preserveScroll: true });
};
const remove = () => {
    confirmBox.value = {
        title: 'Remove', message: `Remove ${props.payroll?.employee_name} from ${props.payroll?.period}? This cannot be undone.`,
        confirmLabel: 'Remove', danger: true,
        run: () => router.delete(route('hrm.payroll.destroy', props.payroll.id)),
    };
};
const approveOne = () => {
    confirmBox.value = {
        title: 'Approve', message: `Approve ${props.payroll?.employee_name} (${props.payroll?.period}) for finance payment?`,
        confirmLabel: 'Approve',
        run: () => router.post(route('hrm.payroll.approve', props.payroll.id)),
    };
};
const rejectOne = () => {
    confirmBox.value = {
        title: 'Return to draft', message: `${props.payroll?.employee_name} will go back to Draft for HR correction. Optionally leave a reason.`,
        inputLabel: 'Reason (optional)', inputValue: '', confirmLabel: 'Return to draft',
        run: (val) => router.post(route('hrm.payroll.reject', props.payroll.id), { reason: val || '' }),
    };
};
const closeWithConfirm = () => {    if (editable.value && form.isDirty) {
        confirmBox.value = {
            title: 'Close', message: 'Close without saving changes?',
            confirmLabel: 'Close without saving',
            run: () => router.visit(route('hrm.payroll')),
        };
        return;
    }
    router.visit(route('hrm.payroll'));
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
const showPayslipPreview = ref(false);
const syncFromAttendance = () => {
    confirmBox.value = {
        title: 'Sync from attendance',
        message: 'Re-pull days, overtime, night, Sun/special, holiday and late from attendance for this cutoff? Daily rate and deductions stay as typed.',
        confirmLabel: 'Sync now',
        run: () => router.post(route('hrm.payroll.sync', props.payroll.id)),
    };
};
const printSlip = () => { showPayslipPreview.value = true; };
const printPayslipDocument = () => { try { window.print(); } catch { confirmBox.value = { title: 'Print payslip', message: 'The print dialog is unavailable in this browser.', confirmLabel: 'Understood', run: null }; } };
const downloadPayslipPdf = () => {
    const currentPayroll = props.payroll || {};
    const currentAttendance = props.attendance;
    const current = live.value;
    const attendanceDayCount = presentDays.value;
    const attendanceDates = presentDates.value;
    const ascii = (value) => String(value ?? '')
        .replace(/₱/g, 'PHP ')
        .replace(/[—–·]/g, '-')
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^\x20-\x7E]/g, '?')
        .replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');
    const commands = [];
    const draw = (x, y, value, size = 9, bold = false) => {
        commands.push(`BT /${bold ? 'F2' : 'F1'} ${size} Tf 1 0 0 1 ${x} ${y} Tm (${ascii(value)}) Tj ET`);
    };
    const rule = (y) => commands.push(`0.75 w 48 ${y} m 564 ${y} l S`);

    draw(48, 754, `Payslip - ${currentPayroll.employee_name} · ${currentPayroll.period}`, 14, true);
    if (currentAttendance?.cutoff) draw(48, 736, `Cutoff: ${currentAttendance.cutoff}`, 9);
    draw(48, 710, 'Attendance summary', 10, true);
    const attendanceRows = [
        ['Days present', `${attendanceDayCount} day${attendanceDayCount === 1 ? '' : 's'}`],
        ['Scheduled shifts', scheduledDays.value],
        ['Absent', absentDaysView.value],
        ['On leave', leaveDaysView.value],
    ];
    let y = 692;
    attendanceRows.forEach(([label, value]) => {
        draw(48, y, label);
        draw(510, y, value);
        y -= 14;
    });
    draw(48, y - 2, attendanceDates.length
        ? `Present dates: ${attendanceDates.map(shortDay).join(', ')}`
        : `No present-date list from attendance - count comes from ${currentAttendance ? 'attendance sync' : 'payroll record'} (${attendanceDayCount} day${attendanceDayCount === 1 ? '' : 's'}).`, 8);
    y -= 24;
    draw(48, y, 'Field', 9, true);
    draw(510, y, 'Value', 9, true);
    rule(y - 4);
    y -= 18;

    const rows = [
        ['Days worked (payroll)', num2(current.days)], ['Daily rate', peso(current.daily)], ['Basic total', peso(current.basic)],
        ['Night hours', blankIfZero(current.nightH)], ['Night rate', peso(current.nightRate)], ['Night amount', peso(current.nightAmt)],
        ['O.T. hours', blankIfZero(current.otH)], ['O.T. rate', peso(current.otRate)], ['O.T. amount', peso(current.otAmt)],
        ['Sun / special holiday hours', blankIfZero(current.sunH)], ['Sun / special holiday rate', peso(current.sunRate)], ['Sun / special holiday amount', peso(current.sunAmt)],
        ['Holiday', current.holAmt ? peso(current.holAmt) : '—'], ['Late (min)', current.lateM ? num2(current.lateM) : '—'],
        ['Late rate/min', peso(current.lateRate)], ['Late total', current.lateTotal ? peso(current.lateTotal) : '—'],
        ['Gross', peso(current.gross)], ['SSS', blankPeso(current.sss)], ['PhilHealth', blankPeso(current.phil)], ['Pag-IBIG', blankPeso(current.pag)],
        ['SSS loan', peso(current.sssLoan)], ['PF loan', peso(current.pfLoan)],
        ...(current.advanceDeduction > 0 ? [['Advance repayment', peso(current.advanceDeduction)]] : []),
        ['Net', peso(current.net)],
    ];
    rows.forEach(([label, value]) => {
        draw(48, y, label, 9, label === 'Gross' || label === 'Net');
        draw(510, y, value, 9, label === 'Gross' || label === 'Net');
        y -= 13;
        if (label === 'Gross') rule(y + 6);
    });

    const stream = commands.join('\n');
    const objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        `<< /Length ${stream.length} >>\nstream\n${stream}\nendstream`,
    ];
    let pdf = '%PDF-1.4\n';
    const offsets = [0];
    objects.forEach((object, index) => {
        offsets.push(pdf.length);
        pdf += `${index + 1} 0 obj\n${object}\nendobj\n`;
    });
    const xrefOffset = pdf.length;
    pdf += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
    offsets.slice(1).forEach((offset) => { pdf += `${String(offset).padStart(10, '0')} 00000 n \n`; });
    pdf += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xrefOffset}\n%%EOF`;

    const link = document.createElement('a');
    link.href = URL.createObjectURL(new Blob([pdf], { type: 'application/pdf' }));
    link.download = `Payslip-${String(currentPayroll.employee_name || 'Employee').replace(/[^a-z0-9]+/gi, '-')}-${String(currentPayroll.period || 'period').replace(/[^a-z0-9]+/gi, '-')}.pdf`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(link.href), 1000);
};
</script>

<template>
    <Head title="Payroll detail" />
    <AuthenticatedLayout>
        <div class="flex min-h-screen bg-gradient-to-br from-slate-100 via-slate-50 to-blue-50 payroll-caps">
            <div class="flex-1 p-8 text-slate-900 font-sans min-w-0 relative">
                <div class="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,transparent,black,transparent)] pointer-events-none"></div>
                <header class="mb-8 relative">
                    <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3">
                        <Link :href="route('hrm.payroll')" class="hover:text-slate-700 transition-colors">Payroll</Link>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        <span class="text-slate-800 font-semibold">{{ payroll?.employee_name }}</span>
                    </nav>
                    <h1 class="text-4xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">{{ payroll?.employee_name }}</h1>
                    <p class="text-sm text-slate-500 mt-1.5">{{ payroll?.job_title || payroll?.role }} · {{ payroll?.department }} · {{ payroll?.period }} · {{ payroll?.status }}</p>
                </header>

                <div class="bg-white rounded-2xl border border-slate-200/60 shadow-lg backdrop-blur-sm relative">
                    <div class="p-5 border-b border-slate-100 bg-gradient-to-r from-blue-50 via-white to-slate-50">
                        <h3 class="font-bold text-slate-900">Employee payslip</h3>
                        <p class="text-xs text-slate-500 mt-0.5">{{ payroll?.employee_name }} · {{ payroll?.period }} · {{ payroll?.status }}</p>
                        <p v-if="attendance" class="text-xs text-slate-500 mt-1">From attendance {{ attendance.cutoff }}: {{ attendance.scheduled }} shifts · {{ attendance.present }} present · {{ attendance.absent }} absent · {{ attendance.leave }} on leave.</p>
                        <div v-if="!editable" class="mt-3 px-3 py-2 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 text-xs font-medium">
                            Locked. Only HR can edit, and only while the row is Draft. A manager can return it to draft.
                        </div>
                    </div>
                    <div class="p-5 grid grid-cols-1 lg:grid-cols-2 gap-5 items-start bg-slate-50/50">
                        <section class="employee-details-sticky self-start rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                        <div class="mb-4 border-b border-slate-100 pb-3">
                            <h4 class="text-sm font-bold text-slate-900">Employee details</h4>
                            <p class="mt-0.5 text-xs text-slate-500">Review attendance and compensation inputs.</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 form-grid">
                            <div><label class="text-xs font-bold text-slate-700 block mb-1.5">Full name</label><input v-model="form.employee_name" :disabled="!editable" aria-label="Full name" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
                            <div><label class="text-xs font-bold text-slate-700 block mb-1.5">Job title</label><input v-model="form.job_title" :disabled="!editable" aria-label="Job title" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
                            <div><label class="text-xs font-bold text-slate-700 block mb-1.5">Department</label><input v-model="form.department" :disabled="!editable" aria-label="Department" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
                            <div><label class="text-xs font-bold text-slate-700 block mb-1.5">Days worked</label><input v-model.number="form.days_worked" type="number" min="0" step="0.01" :disabled="!editable" aria-label="Days worked" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
                            <div><label class="text-xs font-bold text-slate-700 block mb-1.5">Daily rate</label><div class="relative"><span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">₱</span><input v-model.number="form.daily_rate" type="number" min="0" step="0.01" :disabled="!editable" aria-label="Daily rate" class="w-full pl-7 pr-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div></div>
                            <div><label class="text-xs font-bold text-slate-700 block mb-1.5">Night hours</label><input v-model.number="form.night_hours" type="number" min="0" step="0.01" :disabled="!editable" aria-label="Night hours" placeholder="Blank = 0" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
                            <div><label class="text-xs font-bold text-slate-700 block mb-1.5">O.T. hours</label><input v-model.number="form.overtime_hours" type="number" min="0" step="0.01" :disabled="!editable" aria-label="Overtime hours" placeholder="Blank = 0" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
                            <div><label class="text-xs font-bold text-slate-700 block mb-1.5">Sun / special holiday hours</label><input v-model.number="form.sunday_restday_hours" type="number" min="0" step="0.01" :disabled="!editable" aria-label="Sunday special holiday hours" placeholder="Blank = 0" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
                            <div><label class="text-xs font-bold text-slate-700 block mb-1.5">Holiday amount</label><div class="relative"><span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">₱</span><input v-model.number="form.holiday_amt" type="number" min="0" step="0.01" :disabled="!editable" aria-label="Holiday amount" placeholder="Blank = 0" class="w-full pl-7 pr-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div></div>
                            <div><label class="text-xs font-bold text-slate-700 block mb-1.5">Late (min)</label><input v-model.number="form.late_minutes" type="number" min="0" step="1" :disabled="!editable" aria-label="Late minutes" placeholder="Blank = 0" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
                            <div><label class="text-xs font-bold text-slate-700 block mb-1.5">SSS contribution amount</label><div class="relative"><span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">₱</span><input v-model.number="form.sss_deduction" type="number" min="0" step="0.01" readonly :disabled="!editable" aria-label="SSS contribution amount" placeholder="0.00" class="w-full pl-7 pr-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div></div>
                            <div><label class="text-xs font-bold text-slate-700 block mb-1.5">PhilHealth contribution amount</label><div class="relative"><span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">₱</span><input v-model.number="form.philhealth_deduction" type="number" min="0" step="0.01" readonly :disabled="!editable" aria-label="PhilHealth contribution amount" placeholder="0.00" class="w-full pl-7 pr-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div></div>
                            <div><label class="text-xs font-bold text-slate-700 block mb-1.5">Pag-IBIG contribution amount</label><div class="relative"><span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">₱</span><input v-model.number="form.pagibig_deduction" type="number" min="0" step="0.01" readonly :disabled="!editable" aria-label="Pag-IBIG contribution amount" placeholder="0.00" class="w-full pl-7 pr-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div></div>
                        </div>

                        </section>
                        <section class="rounded-2xl border border-blue-200 bg-white p-4 shadow-sm">
                        <div id="payslip" class="rounded-xl border border-blue-100 bg-gradient-to-b from-white to-blue-50/40 p-4 text-sm">
                            <div class="font-bold mb-1">Payslip — {{ payroll?.employee_name }} · {{ payroll?.period }}</div>
                            <div v-if="attendance?.cutoff" class="text-xs text-slate-500 mb-2">Cutoff: {{ attendance.cutoff }}</div>
                        <div v-if="payroll?.status === 'Paid'" class="text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl px-3 py-2 mb-2">Paid{{ payroll?.paid_at ? ' on ' + payroll.paid_at : '' }}{{ payroll?.payment_reference ? ' · Ref ' + payroll.payment_reference : '' }}{{ payroll?.payment_method ? ' · ' + payroll.payment_method : '' }}</div>
                            <div class="rounded-xl bg-slate-50 border border-slate-200 px-3 py-2 mb-2">
                                <div class="flex justify-between py-0.5 font-semibold"><span>Days present</span><span>{{ presentDays }} day{{ presentDays === 1 ? '' : 's' }}</span></div>
                                <div class="flex justify-between py-0.5 text-xs text-slate-600"><span>Scheduled shifts</span><span>{{ scheduledDays }}</span></div>
                                <div class="flex justify-between py-0.5 text-xs text-slate-600"><span>Absent</span><span>{{ absentDaysView }}</span></div>
                                <div class="flex justify-between py-0.5 text-xs text-slate-600"><span>On leave</span><span>{{ leaveDaysView }}</span></div>
                                <div v-if="presentDates.length" class="mt-1.5 flex flex-wrap gap-1">
                                    <span v-for="d in presentDates" :key="d" class="inline-block px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-[11px] font-semibold">{{ shortDay(d) }}</span>
                                </div>
                                <div v-else class="mt-1 text-[11px] text-slate-500">No present-date list from attendance — count comes from {{ attendance ? 'attendance sync' : 'payroll record' }} ({{ presentDays }} day{{ presentDays === 1 ? '' : 's' }}).</div>
                            </div>
                            <div v-if="daysMismatch" class="rounded-xl bg-amber-50 border border-amber-200 px-3 py-2 mb-2 text-xs text-amber-800">
                                <span class="font-bold">Mismatch:</span>
                                Days worked (payroll: {{ num2(form.days_worked) }}) ≠ Days present (attendance now: {{ num2(presentDays) }}).
                                Payroll math uses Days worked. Attendance is the full-period live count (stored row was {{ num2(storedDays) }} at generation).
                                <button v-if="editable" type="button" @click="useAttendanceDays" class="ml-1 underline font-bold hover:text-amber-900">Use {{ num2(presentDays) }} days</button>
                                <span v-else class="ml-1">Review the saved days worked before approving payroll.</span>
                            </div>
                            <div class="grid grid-cols-2 py-0.5 font-bold text-xs uppercase tracking-wide text-slate-500"><span>Field</span><span class="text-right">Value</span></div>
                            <div class="flex justify-between py-0.5"><span>Days worked (payroll)</span><span>{{ num2(live.days) }}<span v-if="daysMismatch" class="ml-1 text-[11px] text-amber-600 font-semibold">· attendance: {{ num2(presentDays) }}</span></span></div>
                            <div class="flex justify-between py-0.5"><span>Daily rate</span><span>{{ peso(live.daily) }}</span></div>
                            <div class="flex justify-between py-0.5 font-semibold"><span>Basic total</span><span>{{ peso(live.basic) }}</span></div>
                            <div class="flex justify-between py-0.5"><span>Night hours</span><span class="text-slate-500">{{ blankIfZero(live.nightH) }}</span></div>
                            <div class="flex justify-between py-0.5"><span>Night rate</span><span>{{ peso(live.nightRate) }}</span></div>
                            <div class="flex justify-between py-0.5"><span>Night amount</span><span>{{ peso(live.nightAmt) }}</span></div>
                            <div class="flex justify-between py-0.5"><span>O.T. hours</span><span class="text-slate-500">{{ blankIfZero(live.otH) }}</span></div>
                            <div class="flex justify-between py-0.5"><span>O.T. rate</span><span>{{ peso(live.otRate) }}</span></div>
                            <div class="flex justify-between py-0.5"><span>O.T. amount</span><span>{{ peso(live.otAmt) }}</span></div>
                            <div class="flex justify-between py-0.5"><span>Sun / special holiday hours</span><span class="text-slate-500">{{ blankIfZero(live.sunH) }}</span></div>
                            <div class="flex justify-between py-0.5"><span>Sun / special holiday rate</span><span>{{ peso(live.sunRate) }}</span></div>
                            <div class="flex justify-between py-0.5"><span>Sun / special holiday amount</span><span>{{ peso(live.sunAmt) }}</span></div>
                            <div class="flex justify-between py-0.5"><span>Holiday</span><span class="text-slate-500">{{ live.holAmt ? peso(live.holAmt) : '—' }}</span></div>
                            <div class="flex justify-between py-0.5"><span>Late (min)</span><span class="text-slate-500">{{ live.lateM ? num2(live.lateM) : '—' }}</span></div>
                            <div class="flex justify-between py-0.5"><span>Late rate/min</span><span>{{ peso(live.lateRate) }}</span></div>
                            <div class="flex justify-between py-0.5"><span>Late total</span><span>{{ live.lateTotal ? peso(live.lateTotal) : '—' }}</span></div>
                            <div class="flex justify-between py-0.5 font-semibold border-t border-slate-200 mt-2 pt-2"><span>Gross</span><span>{{ peso(live.gross) }}</span></div>
                            <div class="flex justify-between py-0.5"><span>SSS</span><span>{{ blankPeso(live.sss) }}</span></div>
                            <div class="flex justify-between py-0.5"><span>PhilHealth</span><span>{{ blankPeso(live.phil) }}</span></div>
                            <div class="flex justify-between py-0.5"><span>Pag-IBIG</span><span>{{ blankPeso(live.pag) }}</span></div>
                            <div class="flex justify-between py-0.5"><span>SSS loan</span><span>{{ peso(live.sssLoan) }}</span></div>
                            <div class="flex justify-between py-0.5"><span>PF loan</span><span>{{ peso(live.pfLoan) }}</span></div>
                            <div v-if="live.advanceDeduction > 0" class="flex justify-between py-0.5"><span>Advance repayment</span><span>{{ peso(live.advanceDeduction) }}</span></div>
                            <div class="flex justify-between font-bold border-t border-slate-200 mt-2 pt-2 text-base"><span>Net</span><span>{{ peso(live.net) }}</span></div>
                        </div>

                        <!-- Print-only formal payslip (proper tables). Hidden on screen, shown by @media print. -->
                        <div id="payslip-print">
                            <div style="text-align:center; margin-bottom:12px;">
                                <div style="font-size:20px; font-weight:800;">Monti ERP</div>
                                <div style="font-size:14px; font-weight:700;">Employee Payslip</div>
                                <div style="font-size:12px;">Pay period: {{ payroll?.period }}<span v-if="attendance?.cutoff"> · Cutoff: {{ attendance.cutoff }}</span></div>
                                <div style="font-size:12px;">Status: {{ payroll?.status }}</div>
                            </div>
                            <table style="width:100%; border-collapse:collapse; font-size:12px; margin-bottom:12px;" border="1" cellpadding="6">
                                <tr>
                                    <td style="font-weight:700; width:25%;">Employee name</td><td style="width:25%;">{{ payroll?.employee_name }}</td>
                                    <td style="font-weight:700; width:25%;">Employee ID</td><td style="width:25%;">{{ payroll?.employee_id || '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="font-weight:700;">Job title</td><td>{{ payroll?.job_title || payroll?.role || '—' }}</td>
                                    <td style="font-weight:700;">Department</td><td>{{ payroll?.department || '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="font-weight:700;">Days present</td><td>{{ presentDays }} day{{ presentDays === 1 ? '' : 's' }} (scheduled {{ scheduledDays }}, absent {{ absentDaysView }}, leave {{ leaveDaysView }})</td>
                                    <td style="font-weight:700;">Present dates</td><td>{{ presentDatesLabel || '—' }}</td>
                                </tr>
                            </table>
                            <table style="width:100%; border-collapse:collapse; font-size:12px; margin-bottom:12px;" border="1" cellpadding="6">
                                <tr style="font-weight:800; background:#f1f5f9;">
                                    <td colspan="4">Earnings</td>
                                </tr>
                                <tr style="font-weight:700;">
                                    <td>Description</td><td>Days / Hours</td><td>Rate</td><td style="text-align:right;">Amount</td>
                                </tr>
                                <tr>
                                    <td>Basic pay ({{ num2(live.days) }} × {{ peso(live.daily) }})</td><td>{{ num2(live.days) }} days</td><td>{{ peso(live.daily) }}</td><td style="text-align:right;">{{ peso(live.basic) }}</td>
                                </tr>
                                <tr>
                                    <td>Night differential</td><td>{{ live.nightH ? num2(live.nightH) + ' hrs' : '—' }}</td><td>{{ peso(live.nightRate) }}</td><td style="text-align:right;">{{ peso(live.nightAmt) }}</td>
                                </tr>
                                <tr>
                                    <td>Overtime</td><td>{{ live.otH ? num2(live.otH) + ' hrs' : '—' }}</td><td>{{ peso(live.otRate) }}</td><td style="text-align:right;">{{ peso(live.otAmt) }}</td>
                                </tr>
                                <tr>
                                    <td>Sun / special holiday</td><td>{{ live.sunH ? num2(live.sunH) + ' hrs' : '—' }}</td><td>{{ peso(live.sunRate) }}</td><td style="text-align:right;">{{ peso(live.sunAmt) }}</td>
                                </tr>
                                <tr>
                                    <td>Holiday pay</td><td>—</td><td>—</td><td style="text-align:right;">{{ peso(live.holAmt) }}</td>
                                </tr>
                                <tr>
                                    <td>Late / undertime</td><td>{{ live.lateM ? num2(live.lateM) + ' min' : '—' }}</td><td>{{ peso(live.lateRate) }}</td><td style="text-align:right;">({{ peso(live.lateTotal) }})</td>
                                </tr>
                                <tr style="font-weight:800;">
                                    <td colspan="3">Gross pay</td><td style="text-align:right;">{{ peso(live.gross) }}</td>
                                </tr>
                            </table>
                            <table style="width:100%; border-collapse:collapse; font-size:12px; margin-bottom:12px;" border="1" cellpadding="6">
                                <tr style="font-weight:800; background:#f1f5f9;">
                                    <td colspan="2">Deductions</td>
                                </tr>
                                <tr><td>SSS</td><td style="text-align:right;">{{ peso(live.sss) }}</td></tr>
                                <tr><td>PhilHealth</td><td style="text-align:right;">{{ peso(live.phil) }}</td></tr>
                                <tr><td>Pag-IBIG</td><td style="text-align:right;">{{ peso(live.pag) }}</td></tr>
                                <tr><td>SSS loan</td><td style="text-align:right;">{{ peso(live.sssLoan) }}</td></tr>
                                <tr><td>PF loan</td><td style="text-align:right;">{{ peso(live.pfLoan) }}</td></tr>
                                <tr v-if="live.advanceDeduction > 0"><td>Advance repayment</td><td style="text-align:right;">{{ peso(live.advanceDeduction) }}</td></tr>
                                <tr style="font-weight:800;">
                                    <td>Total deductions</td><td style="text-align:right;">{{ peso(live.ded) }}</td>
                                </tr>
                            </table>
                            <table style="width:100%; border-collapse:collapse; font-size:14px; margin-bottom:12px;" border="1" cellpadding="8">
                                <tr style="font-weight:800;">
                                    <td>Net pay</td><td style="text-align:right;">{{ peso(live.net) }}</td>
                                </tr>
                            </table>
                            <div v-if="payroll?.status === 'Paid'" style="font-size:12px; margin-bottom:12px;">
                                Paid{{ payroll?.paid_at ? ' on ' + payroll.paid_at : '' }}{{ payroll?.payment_reference ? ' · Ref ' + payroll.payment_reference : '' }}{{ payroll?.payment_method ? ' · ' + payroll.payment_method : '' }}
                            </div>
                            <table style="width:100%; font-size:12px; margin-top:24px;" cellpadding="6">
                                <tr>
                                    <td style="text-align:center;">_________________________<br />Prepared by (HR)</td>
                                    <td style="text-align:center;">_________________________<br />Approved by (Manager)</td>
                                    <td style="text-align:center;">_________________________<br />Received by (Employee / Date)</td>
                                </tr>
                            </table>
                            <div style="font-size:10px; margin-top:12px; text-align:center;">Generated {{ printDate }} · Amounts in PHP · Gross = (days × rate) + night + O.T. + Sun/special + holiday − late · Net = Gross − deductions including released advance repayments</div>
                        </div>
                        <div v-if="live.capped" class="text-xs text-amber-600 font-semibold mt-1.5">Deductions exceed gross — net floored at zero.</div>
                        <div v-if="Number(form.overtime_hours) > otWarnAt" class="text-xs text-amber-600 font-semibold mt-1">Overtime is above {{ otWarnAt }} hours. Confirm it was approved.</div>
                        </section>
                    </div>
                    <div class="px-5 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2 flex-wrap">

                        <button type="button" @click="printSlip" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 rounded-xl text-xs font-bold transition">Print payslip</button>
                        <button type="button" @click="closeWithConfirm" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 rounded-xl text-xs font-bold transition">Close</button>
                        <button v-if="editable" type="button" @click="save" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold transition">Save changes</button>
                        <template v-else-if="payroll?.status === 'Submitted'">
                            <button type="button" @click="approveOne" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold transition">Approve</button>
                            <button type="button" @click="rejectOne" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 rounded-xl text-xs font-bold transition">Return to draft</button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="showPayslipPreview" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/60 p-4">
            <div class="relative flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-bold text-slate-900">Payslip preview</h2>
                    <button type="button" @click="showPayslipPreview = false" class="rounded-lg px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-100">Close</button>
                </div>
                <div id="payslip-preview-content" class="min-h-0 overflow-y-auto p-4 text-[11px] leading-4 text-slate-900">
                    <h1 class="mb-1 text-sm font-bold">Payslip — {{ payroll?.employee_name }} · {{ payroll?.period }}</h1>
                    <p v-if="attendance?.cutoff" class="mb-2 text-[11px] text-slate-600">Cutoff: {{ attendance.cutoff }}</p>
                    <div class="mb-2 rounded-lg border border-slate-200 px-3 py-2">
                        <div class="flex justify-between py-0.5 font-semibold"><span>Days present</span><span>{{ presentDays }} day{{ presentDays === 1 ? '' : 's' }}</span></div>
                        <div class="flex justify-between py-0.5"><span>Scheduled shifts</span><span>{{ scheduledDays }}</span></div>
                        <div class="flex justify-between py-0.5"><span>Absent</span><span>{{ absentDaysView }}</span></div>
                        <div class="flex justify-between py-0.5"><span>On leave</span><span>{{ leaveDaysView }}</span></div>
                        <div v-if="presentDates.length" class="mt-1 border-t border-slate-200 pt-1 text-[11px]">
                            <span class="font-semibold">Present dates:</span> {{ presentDates.map(shortDay).join(', ') }}
                        </div>
                        <p v-else class="mt-1 border-t border-slate-200 pt-1 text-[11px] text-slate-600">No present-date list from attendance — count comes from {{ attendance ? 'attendance sync' : 'payroll record' }} ({{ presentDays }} day{{ presentDays === 1 ? '' : 's' }}).</p>
                    </div>
                    <table class="w-full border-collapse text-[11px] leading-4">
                        <thead><tr class="border-b-2 border-slate-300 text-left"><th class="py-1">Field</th><th class="py-1 text-right">Value</th></tr></thead>
                        <tbody>
                            <tr><td class="border-b border-slate-100 py-0.5">Days worked (payroll)</td><td class="border-b border-slate-100 py-0.5 text-right">{{ num2(live.days) }}</td></tr>
                            <tr><td class="border-b border-slate-100 py-0.5">Daily rate</td><td class="border-b border-slate-100 py-0.5 text-right">{{ peso(live.daily) }}</td></tr>
                            <tr class="font-semibold"><td class="border-b border-slate-100 py-0.5">Basic total</td><td class="border-b border-slate-100 py-0.5 text-right">{{ peso(live.basic) }}</td></tr>
                            <tr><td class="border-b border-slate-100 py-0.5">Night hours</td><td class="border-b border-slate-100 py-0.5 text-right">{{ blankIfZero(live.nightH) }}</td></tr>
                            <tr><td class="border-b border-slate-100 py-0.5">Night rate</td><td class="border-b border-slate-100 py-0.5 text-right">{{ peso(live.nightRate) }}</td></tr>
                            <tr><td class="border-b border-slate-100 py-0.5">Night amount</td><td class="border-b border-slate-100 py-0.5 text-right">{{ peso(live.nightAmt) }}</td></tr>
                            <tr><td class="border-b border-slate-100 py-0.5">O.T. hours</td><td class="border-b border-slate-100 py-0.5 text-right">{{ blankIfZero(live.otH) }}</td></tr>
                            <tr><td class="border-b border-slate-100 py-0.5">O.T. rate</td><td class="border-b border-slate-100 py-0.5 text-right">{{ peso(live.otRate) }}</td></tr>
                            <tr><td class="border-b border-slate-100 py-0.5">O.T. amount</td><td class="border-b border-slate-100 py-0.5 text-right">{{ peso(live.otAmt) }}</td></tr>
                            <tr><td class="border-b border-slate-100 py-0.5">Sun / special holiday hours</td><td class="border-b border-slate-100 py-0.5 text-right">{{ blankIfZero(live.sunH) }}</td></tr>
                            <tr><td class="border-b border-slate-100 py-0.5">Sun / special holiday rate</td><td class="border-b border-slate-100 py-0.5 text-right">{{ peso(live.sunRate) }}</td></tr>
                            <tr><td class="border-b border-slate-100 py-0.5">Sun / special holiday amount</td><td class="border-b border-slate-100 py-0.5 text-right">{{ peso(live.sunAmt) }}</td></tr>
                            <tr><td class="border-b border-slate-100 py-0.5">Holiday</td><td class="border-b border-slate-100 py-0.5 text-right">{{ live.holAmt ? peso(live.holAmt) : '—' }}</td></tr>
                            <tr><td class="border-b border-slate-100 py-0.5">Late (min)</td><td class="border-b border-slate-100 py-0.5 text-right">{{ live.lateM ? num2(live.lateM) : '—' }}</td></tr>
                            <tr><td class="border-b border-slate-100 py-0.5">Late rate/min</td><td class="border-b border-slate-100 py-0.5 text-right">{{ peso(live.lateRate) }}</td></tr>
                            <tr><td class="border-b border-slate-100 py-0.5">Late total</td><td class="border-b border-slate-100 py-0.5 text-right">{{ live.lateTotal ? peso(live.lateTotal) : '—' }}</td></tr>
                            <tr class="font-semibold"><td class="border-b border-slate-100 py-0.5">Gross</td><td class="border-b border-slate-100 py-0.5 text-right">{{ peso(live.gross) }}</td></tr>
                            <tr><td class="border-b border-slate-100 py-0.5">SSS</td><td class="border-b border-slate-100 py-0.5 text-right">{{ blankPeso(live.sss) }}</td></tr>
                            <tr><td class="border-b border-slate-100 py-0.5">PhilHealth</td><td class="border-b border-slate-100 py-0.5 text-right">{{ blankPeso(live.phil) }}</td></tr>
                            <tr><td class="border-b border-slate-100 py-0.5">Pag-IBIG</td><td class="border-b border-slate-100 py-0.5 text-right">{{ blankPeso(live.pag) }}</td></tr>
                            <tr><td class="border-b border-slate-100 py-0.5">SSS loan</td><td class="border-b border-slate-100 py-0.5 text-right">{{ peso(live.sssLoan) }}</td></tr>
                            <tr><td class="border-b border-slate-100 py-0.5">PF loan</td><td class="border-b border-slate-100 py-0.5 text-right">{{ peso(live.pfLoan) }}</td></tr>
                            <tr v-if="live.advanceDeduction > 0"><td class="border-b border-slate-100 py-0.5">Advance repayment</td><td class="border-b border-slate-100 py-0.5 text-right">{{ peso(live.advanceDeduction) }}</td></tr>
                            <tr class="text-sm font-bold"><td class="py-1">Net</td><td class="py-1 text-right">{{ peso(live.net) }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
                    <button type="button" @click="showPayslipPreview = false" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100">Close</button>
                    <button type="button" @click="printPayslipDocument" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100">Print</button>
                    <button type="button" @click="downloadPayslipPdf" class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700">PDF</button>
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
                        :class="confirmBox.danger ? 'bg-rose-600 hover:bg-rose-500' : 'bg-blue-600 hover:bg-blue-500'"
                        class="px-4 py-2 text-white rounded-xl text-xs font-bold transition">{{ confirmBox.confirmLabel }}</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.employee-details-sticky { position: sticky; top: 1rem; max-height: calc(100dvh - 2rem); overflow-y: auto; }
.employee-details-sticky .form-grid { gap: .625rem; }
.employee-details-sticky label { margin-bottom: .25rem; font-size: .6875rem; }
.employee-details-sticky input { padding-top: .375rem; padding-bottom: .375rem; font-size: .75rem; }
@media (max-width: 1023px) { .employee-details-sticky { position: static; max-height: none; overflow: visible; } }
.payroll-caps, .payroll-caps *, .payroll-caps input::placeholder { text-transform: capitalize; }
.bg-grid-slate-100 { background-image: radial-gradient(circle, #cbd5e1 1px, transparent 1px); background-size: 24px 24px; }
.bg-clip-text { -webkit-background-clip: text; background-clip: text; }
input:disabled { opacity: .6; }
:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
#payslip-print { display: none !important; }
@media print {
    :global(body *) { visibility: hidden !important; }
    :global(body) { background: #fff !important; }
    :global(#payslip-preview-content), :global(#payslip-preview-content *) { visibility: visible !important; text-transform: none; color: #000 !important; }
        :global(#payslip-preview-content) {
        display: block;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        max-height: none;
        margin: 0;
        padding: 8mm;
        border: 0;
        border-radius: 0;
        background: #fff;
        box-shadow: none;
        overflow: visible;
        font-size: 10px;
        line-height: 1.25;
    }
    :global(#payslip-preview-content h1) { font-size: 12px !important; }
    :global(#payslip-preview-content p) { font-size: 9px !important; }
    :global(#payslip-preview-content table) { font-size: 9px !important; line-height: 1.15 !important; }
    :global(#payslip-preview-content th), :global(#payslip-preview-content td) { padding-top: 1px !important; padding-bottom: 1px !important; }
    :global(#payslip-preview-content button) { display: none !important; }
}
</style>
