<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    CheckCircle, User, Search,
    Eye, ClipboardList, FileText, Star, UserCheck, UserMinus,
    KeyRound, Copy, X, ChevronLeft, ChevronRight, Lock
} from 'lucide-vue-next';

const props = defineProps({
    trainees: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    positions: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    summary: {
        type: Object,
        default: () => ({ scheduled: 0, in_progress: 0, completed: 0, evaluated: 0, passed: 0, failed: 0, total: 0 }),
    },
    permissions: { type: Object, default: () => ({}) },
    department: { type: String, default: null },
    evaluationWeights: {
        type: Object,
        default: () => ({
            attendance: 0.20,
            knowledge: 0.25,
            practical_skills: 0.30,
            work_behavior: 0.15,
            safety_compliance: 0.10,
        }),
    },
    passingGrade: { type: Number, default: 75 },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const departmentName = computed(() => props.department || 'HRM');

const canEdit = computed(() => {
    if (props.permissions?.trainee === 'edit') return true;
    if (['secretary', 'special_officer'].includes(user.value?.position)) return true;
    if (user.value?.role === 'CEO') return true;
    return false;
});

// ---- Toast ----
const showToast = ref(false);
const toastMessage = ref('');
const triggerToast = (msg) => {
    toastMessage.value = msg;
    showToast.value = true;
    setTimeout(() => { showToast.value = false; }, 4000);
};
if (page.props.flash?.message) triggerToast(page.props.flash.message);
if (page.props.flash?.error) triggerToast(page.props.flash.error);

// One-time generated login pair (flashed once after employee creation or
// credential resend). Dismissible; never stored — copy it now.
const hireCreds = ref(page.props.flash?.hire_credentials ?? null);
const copiedField = ref('');
const copyCred = async (field, value) => {
    try {
        await navigator.clipboard.writeText(String(value ?? ''));
        copiedField.value = field;
        setTimeout(() => { copiedField.value = ''; }, 1500);
    } catch (e) {
        triggerToast('Copy failed — select the text manually.');
    }
};

// ---- Filters ----
const search = ref('');
const evalFilter = ref('All');
const trainingFilter = ref('All');

const filteredTrainees = computed(() => {
    const q = search.value.trim().toLowerCase();
    return (props.trainees || []).filter((t) => {
        const matchesSearch = !q
            || (t.name || '').toLowerCase().includes(q)
            || (t.employee_id || '').toLowerCase().includes(q)
            || (t.position || '').toLowerCase().includes(q)
            || (t.department || '').toLowerCase().includes(q);
        const matchesEval = evalFilter.value === 'All' || t.evaluation_state === evalFilter.value;
        const matchesTraining = trainingFilter.value === 'All' || t.training_status === trainingFilter.value;
        return matchesSearch && matchesEval && matchesTraining;
    });
});

// ---- Helpers ----
const getInitials = (name) => name ? name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) : '?';

// Explicit system-role options (users.role). Server list wins; hardcoded
// fallback mirrors backend VALID_ROLES so the select never renders empty.
const roleOptions = computed(() => (props.roles?.length ? props.roles : ['HRM', 'SCM', 'FIN', 'MAN', 'INV', 'ORD', 'WAR', 'CRM', 'ECO', 'PRO', 'PROJ', 'IT', 'LOG']));

// Best-guess role for a department: UPPER(name) when it is itself a valid
// code, else the person's current role when valid, else blank for admin pick.
const guessRole = (deptId, fallback = '') => {
    const list = roleOptions.value || [];
    const d = (props.departments || []).find((x) => String(x.id) === String(deptId));
    const fromName = String(d?.name || '').toUpperCase();
    if (fromName && list.includes(fromName)) return fromName;
    const fb = String(fallback || '').toUpperCase();
    if (fb && list.includes(fb)) return fb;
    return '';
};
const formatDate = (date) => date ? new Date(date).toLocaleDateString() : 'N/A';
const formatGrade = (g) => (g === null || g === undefined) ? '—' : `${Number(g).toFixed(2)}%`;

const evalBadge = (status) => {
    switch (status) {
        case 'Passed': return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
        case 'Failed': return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
        case 'Evaluated': return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
        case 'Retraining': return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400';
        default: return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
    }
};
// Evaluation column: readiness / state of the final evaluation (never a verdict).
const evaluationBadge = (status) => {
    switch (status) {
        case 'Evaluated': return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
        case 'Ready for Evaluation': return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
        default: return 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300';
    }
};
// Result column: the verdict / recommendation.
const resultBadge = (status) => {
    switch (status) {
        case 'Passed': return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
        case 'Recommend for Regularization': return 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400';
        case 'Needs Improvement': return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
        case 'Reassigned': return 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400';
        case 'Retraining': return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400';
        case 'Failed': return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
        case 'Not Recommended': return 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400';
        default: return 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300';
    }
};
// HR review not yet decided (Pending or never recorded).
const hrReviewPending = (t) => {
    const s = t?.hr_review?.status;
    return !s || s === 'Pending';
};
const trainingBadge = (status) => {
    switch (status) {
        case 'Completed': return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
        case 'In Progress': return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
        case 'Extended': return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400';
        case 'Cancelled': return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
        case 'Scheduled': return 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300';
        default: return 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300';
    }
};

// ---- Evaluation criteria (weights must match backend TraineeGrade::EVALUATION_WEIGHTS) ----
const criteria = computed(() => [
    { key: 'attendance', label: 'Attendance', weight: props.evaluationWeights.attendance ?? 0.20 },
    { key: 'knowledge', label: 'Knowledge', weight: props.evaluationWeights.knowledge ?? 0.25 },
    { key: 'practical_skills', label: 'Practical Skills', weight: props.evaluationWeights.practical_skills ?? 0.30 },
    { key: 'work_behavior', label: 'Work Behavior', weight: props.evaluationWeights.work_behavior ?? 0.15 },
    { key: 'safety_compliance', label: 'Safety Compliance', weight: props.evaluationWeights.safety_compliance ?? 0.10 },
]);
const weightLabel = (w) => `${Math.round(w * 100)}%`;

// ---- Modals ----
const isEvaluateOpen = ref(false);
const isViewEvalOpen = ref(false);
const isDetailsOpen = ref(false);
const isPassModalOpen = ref(false);
const isFailModalOpen = ref(false);
const selectedTrainee = ref(null);
const evalMode = ref('evaluate'); // 'evaluate' | 'view'
const failReason = ref('');
const saving = ref(false);

const scores = ref({ attendance: '', knowledge: '', practical_skills: '', work_behavior: '', safety_compliance: '' });
const remarks = ref('');

const openEvaluate = (trainee) => {
    if (!canEdit.value) { triggerToast('You do not have permission to evaluate trainees.'); return; }
    selectedTrainee.value = trainee;
    evalMode.value = 'evaluate';
    const ev = trainee.evaluation || {};
    scores.value = {
        attendance: ev.attendance ?? '',
        knowledge: ev.knowledge ?? '',
        practical_skills: ev.practical_skills ?? '',
        work_behavior: ev.work_behavior ?? '',
        safety_compliance: ev.safety_compliance ?? '',
    };
    remarks.value = ev.remarks ?? '';
    isEvaluateOpen.value = true;
};

const openViewEvaluation = (trainee) => {
    selectedTrainee.value = trainee;
    evalMode.value = 'view';
    const ev = trainee.evaluation || {};
    scores.value = {
        attendance: ev.attendance ?? '',
        knowledge: ev.knowledge ?? '',
        practical_skills: ev.practical_skills ?? '',
        work_behavior: ev.work_behavior ?? '',
        safety_compliance: ev.safety_compliance ?? '',
    };
    remarks.value = ev.remarks ?? '';
    isViewEvalOpen.value = true;
};

const openDetails = async (trainee) => {
    closeModals();
    selectedTrainee.value = trainee;
    trainingDetail.value = null;
    detailLoading.value = true;
    attWeek.value = 1;
    showDetailAttendance.value = false;
    detailDay.value = null;
    showDetailObservation.value = false;
    hrDecision.value = '';
    hrNotes.value = '';
    hrEffectiveDate.value = todayLocal();
    showApproveConfirm.value = false;
    resetReassignForm();
    isDetailsOpen.value = true;
    try {
        const res = await fetch(route('hrm.trainee.show', trainee.id), { headers: { Accept: 'application/json' } });
        const json = await res.json();
        trainingDetail.value = json.trainee || null;
        reassignManagers.value = json.managers || [];
    } catch (e) {
        trainingDetail.value = null;
        triggerToast('Failed to load training details.');
    } finally {
        detailLoading.value = false;
    }
};

// ---- Read-only Trainee Training Details (Training List > View Details) ----
// Same data/shape as My Assigned Trainees, but view-only: no selects,
// no inputs, no Save/Submit buttons. Daily observations are viewable.
const trainingDetail = ref(null);
const detailLoading = ref(false);
const attWeek = ref(1);
const showDetailAttendance = ref(false);
const detailDay = ref(null);
const showDetailObservation = ref(false);

const dFmtDate = (d) => {
    if (!d) return '—';
    const dt = new Date(d);
    return isNaN(dt) ? '—' : dt.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};
const dWeekOptions = computed(() => {
    const n = Math.max(1, Math.min(52, Number(trainingDetail.value?.duration_weeks) || 8));
    return Array.from({ length: n }, (_, i) => i + 1);
});
const dWeekStart = (week) => {
    if (!trainingDetail.value?.training_start) return null;
    const d = new Date(trainingDetail.value.training_start + 'T00:00:00');
    if (isNaN(d)) return null;
    d.setDate(d.getDate() + (Number(week) - 1) * 7);
    return d;
};
const dWeekEndLabel = (week) => {
    const s = dWeekStart(week);
    if (!s) return '—';
    const d = new Date(s); d.setDate(d.getDate() + 6);
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};
const dWeekRangeLabel = (week) => {
    const s = dWeekStart(week);
    if (!s) return '';
    const e = new Date(s); e.setDate(e.getDate() + 6);
    const mon = (d) => d.toLocaleDateString('en-PH', { month: 'short' });
    if (s.getMonth() === e.getMonth() && s.getFullYear() === e.getFullYear()) return `${mon(s)} ${s.getDate()}–${e.getDate()}, ${e.getFullYear()}`;
    return `${mon(s)} ${s.getDate()} – ${mon(e)} ${e.getDate()}, ${e.getFullYear()}`;
};
const weekSavedOf = (week) => (trainingDetail.value?.weekly_evaluations || []).find((w) => Number(w.week_number) === Number(week)) || null;
const dWeekSaved = (week) => !!weekSavedOf(week);
const dNormAttStatus = (s) => {
    const v = String(s || '').toLowerCase().replace(/[-_]/g, ' ').replace(/\s+/g, ' ').trim();
    if (['on time', 'ontime', 'present'].includes(v)) return 'Present';
    if (v === 'late') return 'Late';
    if (v === 'absent' || v === 'no show') return 'Absent';
    return s || '—';
};
const dFmtTime = (t) => {
    if (!t) return '—';
    const m = String(t).trim().match(/(\d{1,2}):(\d{2})(?::(\d{2}))?\s*([AaPp])\.?\s*[Mm]?\.?/);
    if (m) {
        let h = Number(m[1]);
        const min = m[2];
        let suffix = m[4] ? m[4].toUpperCase() + 'M' : null;
        if (suffix) { if (h === 0) h = 12; if (h > 12) h = h - 12; }
        else { suffix = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12; }
        return `${h}:${min} ${suffix}`;
    }
    return String(t);
};
const dAttStatusBadge = (s) => ({
    'Present': 'bg-emerald-100 text-emerald-700',
    'Late': 'bg-amber-100 text-amber-700',
    'Absent': 'bg-rose-100 text-rose-700',
    'Rest Day': 'bg-slate-100 text-slate-500',
}[s] || 'bg-slate-100 text-slate-500');
const daysForWeek = (week) => {
    const s = dWeekStart(week);
    if (!s) return [];
    const logs = trainingDetail.value?.attendance?.recent || [];
    const byDate = {};
    logs.forEach((l) => { if (l?.date) byDate[String(l.date).slice(0, 10)] = l; });
    const today = new Date(); today.setHours(0, 0, 0, 0);
    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(s); d.setDate(d.getDate() + i);
        const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        const log = byDate[key];
        const isSun = d.getDay() === 0;
        let status;
        if (log) status = dNormAttStatus(log.status);
        else if (isSun) status = 'Rest Day';
        else if (d > today) status = '—';
        else status = 'Absent';
        return {
            date: d, key,
            dateLabel: d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }),
            dayLabel: d.toLocaleDateString('en-PH', { weekday: 'short' }),
            timeIn: log ? dFmtTime(log.clock_in) : '—',
            timeOut: log ? dFmtTime(log.clock_out) : '—',
            status,
        };
    });
};
const summaryForDays = (days) => {
    const sched = days.filter((d) => d.status !== 'Rest Day' && d.status !== '—');
    return {
        scheduled: sched.length,
        present: sched.filter((d) => d.status === 'Present').length,
        late: sched.filter((d) => d.status === 'Late').length,
        absent: sched.filter((d) => d.status === 'Absent').length,
    };
};
// Attendance modal follows attWeek (set by each week's View Details button).
const dAttDays = computed(() => daysForWeek(attWeek.value));
const dAttSummary = computed(() => summaryForDays(dAttDays.value));
const dObsByDate = computed(() => {
    const map = {};
    (trainingDetail.value?.observations || []).forEach((o) => { if (o?.date) map[String(o.date).slice(0, 10)] = o; });
    return map;
});
const dHasObservation = (day) => !!dObsByDate.value[day?.key];
const obsTextFor = (day) => (dObsByDate.value[day?.key]?.notes || '').trim();
const dOpenObservation = (day) => { detailDay.value = day; showDetailObservation.value = true; };
const dCloseObservation = () => { showDetailObservation.value = false; detailDay.value = null; };
const dDayObservation = computed(() => detailDay.value ? (dObsByDate.value[detailDay.value.key] || null) : null);
const obsForWeek = (week) => {
    const s = dWeekStart(week);
    if (!s) return [];
    const e = new Date(s); e.setDate(e.getDate() + 6);
    return (trainingDetail.value?.observations || []).filter((o) => {
        const d = new Date(String(o?.date).slice(0, 10) + 'T00:00:00');
        return !isNaN(d) && d >= s && d <= e;
    });
};
const autoForWeek = (week) => {
    const obs = obsForWeek(week);
    const avgOf = (k) => {
        const vals = obs.map((o) => Number(o?.[k])).filter((v) => Number.isFinite(v));
        if (!vals.length) return null;
        return vals.reduce((a, b) => a + b, 0) / vals.length;
    };
    const toScore = (v) => (v === null || v === undefined ? null : Math.max(1, Math.min(5, Math.round(v))));
    const days = daysForWeek(week).filter((d) => ['Present', 'Late', 'Absent'].includes(d.status));
    let attendance = null;
    if (days.length) {
        const pts = days.map((d) => (d.status === 'Present' ? 5 : d.status === 'Late' ? 3 : 1));
        attendance = pts.reduce((a, b) => a + b, 0) / pts.length;
    }
    return { attendance: toScore(attendance), knowledge: toScore(avgOf('knowledge')), practical_skills: toScore(avgOf('practical_skills')), work_behavior: toScore(avgOf('work_behavior')), safety_compliance: toScore(avgOf('safety_compliance')), count: obs.length };
};
const scoresForWeek = (week) => {
    const saved = weekSavedOf(week);
    if (saved) return { attendance: saved.attendance, knowledge: saved.knowledge, practical_skills: saved.practical_skills, work_behavior: saved.work_behavior, safety_compliance: saved.safety_compliance, notes: saved.notes || '', recommendation: saved.recommendation || '', saved: true };
    const a = autoForWeek(week);
    if (a.count) return { attendance: a.attendance, knowledge: a.knowledge, practical_skills: a.practical_skills, work_behavior: a.work_behavior, safety_compliance: a.safety_compliance, notes: '', recommendation: '', saved: false };
    return { attendance: null, knowledge: null, practical_skills: null, work_behavior: null, safety_compliance: null, notes: '', recommendation: '', saved: false, empty: true };
};
const overallForWeek = (week) => {
    const sc = scoresForWeek(week);
    const v = [sc.attendance, sc.knowledge, sc.practical_skills, sc.work_behavior, sc.safety_compliance].map(Number).filter((n) => Number.isFinite(n));
    if (!v.length) return '—';
    return (v.reduce((a, b) => a + b, 0) / v.length).toFixed(1);
};
const dOpenWeekDetails = (week) => { attWeek.value = Number(week); dCloseObservation(); showDetailAttendance.value = true; };
// Weekly calendar strip (same UX as My Assigned Trainees): all weeks visible,
// clicking a week shows that week's evaluation below.
// attWeek doubles as the selected evaluation week (read-only here).
const dWeekStrip = ref(null);
const dSlideWeeks = (dir) => {
    dWeekStrip.value?.scrollBy({ left: dir * 220, behavior: 'smooth' });
};
const dSelectEvalWeek = (week) => { attWeek.value = Number(week); dCloseObservation(); };
const dWeekEndDate = (week) => {
    const s = dWeekStart(week);
    if (!s) return null;
    const d = new Date(s); d.setDate(d.getDate() + 6);
    return d;
};
const dWeekOverdue = (week) => {
    const d = dWeekEndDate(week);
    if (!d || dWeekSaved(week)) return false;
    const today = new Date(); today.setHours(0, 0, 0, 0);
    return d < today;
};
const dEvaluatedCount = computed(() => (trainingDetail.value?.weekly_evaluations || []).length);
const dScoreText = (v) => (v === null || v === undefined ? '—' : `${v} / 5`);
const dWeeklyRecLabel = (r) => ({ continue: 'Continue Training', needs_improvement: 'Needs Improvement', ready_final: 'Ready for Final Evaluation', reassigned: 'Reassigned', early_termination: 'Early Termination' }[r] || '—');
const dFinalRecLabel = (r) => ({ regularization: 'Recommend for Regularization', needs_improvement: 'Needs Improvement', reassigned: 'Reassigned', extend: 'Extend Training', do_not_recommend: 'Do Not Recommend' }[r] || '—');
const dFinalCriteria = computed(() => [
    { key: 'attendance', label: 'Attendance', weight: '20%', score: trainingDetail.value?.final_breakdown?.attendance ?? null },
    { key: 'knowledge', label: 'Knowledge', weight: '25%', score: trainingDetail.value?.final_breakdown?.knowledge ?? null },
    { key: 'practical_skills', label: 'Practical Skills', weight: '30%', score: trainingDetail.value?.final_breakdown?.practical_skills ?? null },
    { key: 'work_behavior', label: 'Work Behavior', weight: '15%', score: trainingDetail.value?.final_breakdown?.work_behavior ?? null },
    { key: 'safety_compliance', label: 'Safety Compliance', weight: '10%', score: trainingDetail.value?.final_breakdown?.safety_compliance ?? null },
]);
const dStars = (score100) => (score100 === null || score100 === undefined ? 0 : Math.max(0, Math.min(5, Math.round(Number(score100) / 20))));

// ---- HR Review & Decision (the only editable part of this view) ----
const todayLocal = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; };
const hrDecision = ref('');
const hrNotes = ref('');
const hrEffectiveDate = ref(todayLocal());
const hrSaving = ref(false);
const showApproveConfirm = ref(false);

const hrReview = computed(() => trainingDetail.value?.hr_review || null);
const hrReviewStatus = computed(() => hrReview.value?.status || '—');
const hrDecided = computed(() => ['Approved', 'Extended', 'Rejected', 'Reassigned'].includes(hrReview.value?.status));
const hrChecklist = computed(() => {
    const t = trainingDetail.value;
    if (!t) return [];
    return [
        { label: 'Training Completion', done: t.training_status === 'Completed' },
        { label: 'Final Evaluation', done: t.final_score !== null && t.final_score !== undefined },
        { label: 'Attendance', done: (t.attendance?.recent || []).length > 0 },
        { label: 'Weekly Evaluations', done: (t.weekly_evaluations || []).length > 0 },
        { label: 'Daily Observations', done: (t.observations || []).length > 0 },
    ];
});
const hrReviewBadge = (s) => ({
    'Approved': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'Extended': 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    'Rejected': 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    'Reassigned': 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
    'Pending': 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
}[s] || 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300');
const hrDecisionLabel = (d) => ({ approve_regularization: 'Approve Regularization', extend_training: 'Extend Training', do_not_regularize: 'Do Not Regularize', approve_reassignment: 'Approve Reassignment', reject_reassignment: 'Reject Reassignment' }[d] || d || '—');
const hrReviewerName = computed(() => hrReview.value?.reviewer_name || user.value?.name || '—');

const reloadDetail = async () => {
    if (!selectedTrainee.value) return;
    detailLoading.value = true;
    try {
        const res = await fetch(route('hrm.trainee.show', selectedTrainee.value.id), { headers: { Accept: 'application/json' } });
        const json = await res.json();
        trainingDetail.value = json.trainee || null;
        reassignManagers.value = json.managers || [];
    } catch (e) {
        triggerToast('Failed to refresh training details.');
    } finally {
        detailLoading.value = false;
    }
};
const submitHrReview = (skipConfirm = false) => {
    if (!trainingDetail.value || !canEdit.value || hrSaving.value) return;
    if (!hrDecision.value) { triggerToast('Select an HR decision first.'); return; }
    if (hrDecision.value === 'approve_regularization' && !skipConfirm) { showApproveConfirm.value = true; return; }
    if (hrDecision.value === 'do_not_regularize' && !hrNotes.value.trim()) { triggerToast('HR notes are required for this decision.'); return; }
    if (hrDecision.value === 'approve_regularization' && !hrEffectiveDate.value) { triggerToast('Set the regularization effective date.'); return; }
    if (hrDecision.value === 'approve_regularization' && hrEffectiveDate.value < todayLocal()) { triggerToast('Regularization date cannot be in the past.'); return; }
    hrSaving.value = true;
    router.post(route('hrm.trainee.hr-review', trainingDetail.value.id), {
        decision: hrDecision.value,
        notes: hrNotes.value,
        effective_date: hrDecision.value === 'approve_regularization' ? hrEffectiveDate.value : null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showApproveConfirm.value = false;
            hrDecision.value = '';
            hrNotes.value = '';
            hrEffectiveDate.value = todayLocal();
            reloadDetail();
            triggerToast(page.props.flash?.message || 'HR review recorded.');
        },
        onError: (errors) => {
            hrSaving.value = false;
            triggerToast(Object.values(errors)[0] || 'Failed to record HR review.');
        },
        onFinish: () => { hrSaving.value = false; },
    });
};

// ---- Reassignment review (manager recommended Reassigned) ----
const reassignManagers = ref([]);
const reDeptId = ref('');
const rePosition = ref('');
const reManagerId = ref('');
const reEffectiveDate = ref(todayLocal());
const reRole = ref('');
const resetReassignForm = () => {
    reDeptId.value = '';
    rePosition.value = '';
    reManagerId.value = '';
    reEffectiveDate.value = todayLocal();
    reRole.value = '';
};
const onReDeptChange = () => {
    rePosition.value = '';
    if (!reRole.value) {
        reRole.value = guessRole(reDeptId.value, trainingDetail.value?.role);
    }
};
const isReassignment = computed(() => trainingDetail.value?.manager_recommendation === 'reassigned'
    && trainingDetail.value?.final_score !== null && trainingDetail.value?.final_score !== undefined);
const lastTransfer = computed(() => {
    const list = trainingDetail.value?.transfers || [];
    return list.length ? list[list.length - 1] : null;
});
const hrDecidedKey = computed(() => {
    const s = hrReview.value?.status;
    if (s === 'Reassigned') return 'approve_reassignment';
    if (s === 'Rejected' && trainingDetail.value?.manager_recommendation === 'reassigned') return 'reject_reassignment';
    return { Approved: 'approve_regularization', Extended: 'extend_training', Rejected: 'do_not_regularize' }[s] || '';
});
const rePositions = computed(() => {
    if (!reDeptId.value) return [];
    // Department-aligned positions first, then general (no-department) ones.
    const list = (props.positions || []).filter((p) => p.department_id === null || p.department_id === undefined || String(p.department_id) === String(reDeptId.value));
    return [...list].sort((a, b) => {
        const ad = String(a.department_id ?? '') === String(reDeptId.value) ? 0 : 1;
        const bd = String(b.department_id ?? '') === String(reDeptId.value) ? 0 : 1;
        return ad - bd || String(a.name).localeCompare(String(b.name));
    });
});
const reassignValid = computed(() => {
    if (hrDecision.value === 'approve_reassignment') {
        return reDeptId.value !== '' && rePosition.value.trim() !== '' && reManagerId.value !== ''
            && reEffectiveDate.value !== '' && hrNotes.value.trim() !== '' && reRole.value !== '';
    }
    if (hrDecision.value === 'reject_reassignment') {
        return hrNotes.value.trim() !== '';
    }
    return false;
});
const submitReassignment = () => {
    if (!trainingDetail.value || !canEdit.value || hrSaving.value) return;
    if (!hrDecision.value) { triggerToast('Select an HR decision first.'); return; }
    if (hrDecision.value === 'approve_reassignment' && !reassignValid.value) { triggerToast('Complete all reassignment fields (department, position, manager, date, role, reason).'); return; }
    if (hrDecision.value === 'reject_reassignment' && !hrNotes.value.trim()) { triggerToast('HR notes are required to reject the reassignment.'); return; }
    hrSaving.value = true;
    const payload = hrDecision.value === 'approve_reassignment'
        ? {
            decision: hrDecision.value,
            notes: hrNotes.value,
            effective_date: reEffectiveDate.value,
            to_department_id: Number(reDeptId.value),
            to_position: rePosition.value.trim(),
            manager_id: Number(reManagerId.value),
            role: reRole.value,
        }
        : { decision: hrDecision.value, notes: hrNotes.value };
    router.post(route('hrm.trainee.hr-review', trainingDetail.value.id), payload, {
        preserveScroll: true,
        onSuccess: () => {
            hrDecision.value = '';
            hrNotes.value = '';
            hrEffectiveDate.value = todayLocal();
            resetReassignForm();
            reloadDetail();
            triggerToast(page.props.flash?.message || 'HR review recorded.');
        },
        onError: (errors) => {
            hrSaving.value = false;
            triggerToast(Object.values(errors)[0] || 'Failed to record HR review.');
        },
        onFinish: () => { hrSaving.value = false; },
    });
};

const openPassModal = (trainee) => {
    if (!canEdit.value) { triggerToast('You do not have permission to promote trainees.'); return; }
    selectedTrainee.value = trainee;
    isViewEvalOpen.value = false;
    isPassModalOpen.value = true;
};
const openFailModal = (trainee) => {
    if (!canEdit.value) { triggerToast('You do not have permission to fail trainees.'); return; }
    selectedTrainee.value = trainee;
    failReason.value = '';
    isViewEvalOpen.value = false;
    isFailModalOpen.value = true;
};

const closeModals = () => {
    isEvaluateOpen.value = false;
    isViewEvalOpen.value = false;
    isDetailsOpen.value = false;
    isPassModalOpen.value = false;
    isFailModalOpen.value = false;
    isRetrainOpen.value = false;
    isTransferOpen.value = false;
    selectedTrainee.value = null;
    retrainScores.value = null;
    failReason.value = '';
    saving.value = false;
    trainingDetail.value = null;
    detailLoading.value = false;
    attWeek.value = 1;
    showDetailAttendance.value = false;
    detailDay.value = null;
    showDetailObservation.value = false;
    hrDecision.value = '';
    hrNotes.value = '';
    hrEffectiveDate.value = todayLocal();
    showApproveConfirm.value = false;
    reassignManagers.value = [];
    resetReassignForm();
};

// ---- Live final grade (auto-calculated) ----
const allScoresEntered = computed(() =>
    criteria.value.every((c) => scores.value[c.key] !== '' && scores.value[c.key] !== null && !Number.isNaN(Number(scores.value[c.key])))
);
const finalGrade = computed(() => {
    if (!allScoresEntered.value) return null;
    const total = criteria.value.reduce((acc, c) => acc + (Number(scores.value[c.key]) * c.weight), 0);
    return Math.round(total * 100) / 100;
});
const liveResult = computed(() => {
    if (finalGrade.value === null) return 'Pending';
    return finalGrade.value >= props.passingGrade ? 'Passed' : 'Failed';
});

const saveEvaluation = () => {
    if (!allScoresEntered.value) { triggerToast('Please rate all 5 criteria before submitting.'); return; }
    saving.value = true;
    router.post(route('hrm.trainee.evaluate', selectedTrainee.value.id), {
        attendance: Number(scores.value.attendance),
        knowledge: Number(scores.value.knowledge),
        practical_skills: Number(scores.value.practical_skills),
        work_behavior: Number(scores.value.work_behavior),
        safety_compliance: Number(scores.value.safety_compliance),
        remarks: remarks.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast(`Evaluation saved for ${selectedTrainee.value.name}. Final grade: ${finalGrade.value}% (${liveResult.value}).`);
            closeModals();
        },
        onError: (errors) => {
            saving.value = false;
            triggerToast(Object.values(errors)[0] || 'Failed to save evaluation.');
        },
    });
};

const passTrainee = () => {
    router.post(route('hrm.trainee.pass', selectedTrainee.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => { triggerToast(`${selectedTrainee.value.name} is now Active and has completed training!`); closeModals(); },
        onError: (errors) => { triggerToast(Object.values(errors)[0] || 'Failed to promote trainee.'); },
    });
};

const scheduleRetraining = (trainee) => {
    openRetrainModal(trainee || selectedTrainee.value);
};

// ---- Transfer modal ----
const isTransferOpen = ref(false);
const transferForm = ref({ department_id: '', position: '', effective_date: '', reason: '', new_training_required: true, role: '' });

const openTransferModal = (trainee) => {
    if (!canEdit.value) { triggerToast('You do not have permission to transfer employees.'); return; }
    if (!trainee) return;
    closeModals();
    selectedTrainee.value = trainee;
    transferForm.value = { department_id: '', position: '', effective_date: '', reason: '', new_training_required: true, role: guessRole('', trainee?.role) };
    isTransferOpen.value = true;
};

const onTransferDepartmentChange = () => {
    transferForm.value.position = '';
    if (!transferForm.value.role) {
        transferForm.value.role = guessRole(transferForm.value.department_id, selectedTrainee.value?.role);
    }
};

const transferPositions = computed(() => {
    // Department-first: no department selected → no position options.
    if (!transferForm.value.department_id) return [];
    const list = props.positions || [];
    return list.filter((p) => String(p.department_id) === String(transferForm.value.department_id));
});

const transferValid = computed(() => {
    return transferForm.value.department_id !== ''
        && transferForm.value.position.trim() !== ''
        && transferForm.value.effective_date !== ''
        && transferForm.value.reason.trim() !== ''
        && transferForm.value.role !== '';
});

const confirmTransfer = () => {
    if (!transferValid.value) { triggerToast('Please complete the required transfer fields (including Role).'); return; }
    saving.value = true;
    const name = selectedTrainee.value.name;
    router.post(route('hrm.trainee.transfer', selectedTrainee.value.id), {
        to_department_id: Number(transferForm.value.department_id),
        to_position: transferForm.value.position.trim(),
        effective_date: transferForm.value.effective_date,
        reason: transferForm.value.reason,
        new_training_required: transferForm.value.new_training_required,
        role: transferForm.value.role,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast(`${name} transferred to ${transferForm.value.position.trim()}.`);
            closeModals();
        },
        onError: (errors) => {
            saving.value = false;
            triggerToast(Object.values(errors)[0] || 'Failed to transfer employee.');
        },
    });
};
// ---- Schedule Retraining modal ----
const isRetrainOpen = ref(false);
const retrainForm = ref({ start_date: '', end_date: '', reason: '', notes: '' });
const retrainScores = ref(null); // scores carried over from the Evaluate modal, if any

const openRetrainModal = (trainee, scores = null) => {
    if (!canEdit.value) { triggerToast('You do not have permission to schedule retraining.'); return; }
    if (!trainee) return;
    closeModals();
    selectedTrainee.value = trainee;
    retrainScores.value = scores ? { ...scores } : null;
    retrainForm.value = { start_date: '', end_date: '', reason: '', notes: '' };
    isRetrainOpen.value = true;
};

// From the Evaluate modal: carry the just-entered failing scores into the form.
const stashScoresAndOpenRetrain = () => {
    if (!allScoresEntered.value) { triggerToast('Please rate all 5 criteria before submitting.'); return; }
    openRetrainModal(selectedTrainee.value, { ...scores.value });
};

const retrainPreviousGrade = computed(() => {
    if (retrainScores.value) {
        const total = criteria.value.reduce((acc, c) => acc + (Number(retrainScores.value[c.key]) * c.weight), 0);
        return Math.round(total * 100) / 100;
    }
    const g = selectedTrainee.value?.grade;
    return (g === null || g === undefined) ? null : Number(g);
});

const retrainValid = computed(() => {
    if (!retrainForm.value.start_date || !retrainForm.value.reason.trim()) return false;
    if (retrainForm.value.end_date && retrainForm.value.end_date < retrainForm.value.start_date) return false;
    return true;
});

const confirmRetraining = () => {
    if (!retrainValid.value) { triggerToast('Please complete the required retraining fields.'); return; }
    saving.value = true;
    const payload = {
        retraining_start_date: retrainForm.value.start_date,
        retraining_end_date: retrainForm.value.end_date || null,
        retraining_reason: retrainForm.value.reason,
        retraining_notes: retrainForm.value.notes || null,
    };
    if (retrainScores.value) {
        Object.assign(payload, {
            attendance: Number(retrainScores.value.attendance),
            knowledge: Number(retrainScores.value.knowledge),
            practical_skills: Number(retrainScores.value.practical_skills),
            work_behavior: Number(retrainScores.value.work_behavior),
            safety_compliance: Number(retrainScores.value.safety_compliance),
            remarks: remarks.value,
        });
    }
    const startLabel = formatDate(retrainForm.value.start_date);
    const name = selectedTrainee.value.name;
    router.post(route('hrm.trainee.retrain', selectedTrainee.value.id), payload, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast(`Retraining scheduled for ${name} starting ${startLabel}.`);
            closeModals();
        },
        onError: (errors) => {
            saving.value = false;
            triggerToast(Object.values(errors)[0] || 'Failed to schedule retraining.');
        },
    });
};

const failTrainee = () => {
    if (!failReason.value.trim()) { triggerToast('Please provide a reason for failure.'); return; }
    router.post(route('hrm.trainee.fail', selectedTrainee.value.id), { reason: failReason.value }, {
        preserveScroll: true,
        onSuccess: () => { triggerToast(`${selectedTrainee.value.name}'s employment has ended. Record archived.`); closeModals(); },
        onError: (errors) => { triggerToast(Object.values(errors)[0] || 'Failed to process failure.'); },
    });
};
</script>

<template>
    <Head :title="`${departmentName} Training`" />

    <AuthenticatedLayout>
        <Transition name="toast">
            <div v-if="showToast" class="fixed top-6 right-6 z-[100] flex items-center gap-3 px-6 py-4 bg-slate-900 dark:bg-white text-white dark:text-slate-900 rounded-2xl shadow-2xl border border-white/10">
                <CheckCircle class="h-5 w-5 text-emerald-400 dark:text-emerald-600" />
                <p class="text-sm font-bold uppercase tracking-tight">{{ toastMessage }}</p>
            </div>
        </Transition>

        <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6">
            <!-- Header -->
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    {{ departmentName }} <span class="text-blue-600">Training</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">
                    Monitor trainee progress, evaluate performance, and manage training completion.
                </p>
                <div v-if="!canEdit" class="mt-2 text-xs text-amber-600 bg-amber-50 inline-block px-2 py-0.5 rounded-full">View only access</div>
                <div v-else class="mt-2 text-xs text-emerald-600 bg-emerald-50 inline-block px-2 py-0.5 rounded-full">Full access (can evaluate trainees)</div>
            </div>

            <!-- Generated login pair (one-time, after employee creation) -->
            <div v-if="hireCreds" class="rounded-2xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <KeyRound class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                        <h3 class="text-sm font-black text-emerald-800 dark:text-emerald-200">Employee account created — copy these credentials now</h3>
                    </div>
                    <button @click="hireCreds = null" title="Dismiss" class="p-1 rounded-lg text-emerald-500 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 transition">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                    <button @click="copyCred('email', hireCreds.email)" title="Click to copy" class="flex items-center justify-between gap-2 px-3 py-2 bg-white dark:bg-slate-800 border border-emerald-200 dark:border-emerald-800 rounded-xl font-mono hover:border-emerald-400 transition text-left">
                        <span class="truncate">{{ hireCreds.email }} <span class="font-sans text-[10px] text-slate-400">({{ hireCreds.employee_id }})</span></span>
                        <Copy class="w-3.5 h-3.5 shrink-0 text-slate-400" />
                    </button>
                    <button v-if="hireCreds.password" @click="copyCred('password', hireCreds.password)" title="Click to copy" class="flex items-center justify-between gap-2 px-3 py-2 bg-white dark:bg-slate-800 border border-emerald-200 dark:border-emerald-800 rounded-xl font-mono hover:border-emerald-400 transition text-left">
                        <span class="truncate">{{ hireCreds.password }}</span>
                        <Copy class="w-3.5 h-3.5 shrink-0 text-slate-400" />
                    </button>
                    <span v-else class="px-3 py-2 text-[11px] text-slate-500">Existing account — password unchanged.</span>
                </div>
                <p class="mt-2 text-[11px] leading-relaxed" :class="hireCreds.mailed ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300'">
                    <template v-if="hireCreds.mailed">Emailed to {{ hireCreds.mailed_to }} — use the copy above if the mail hasn't arrived.</template>
                    <template v-else>Email NOT sent{{ hireCreds.mail_error ? (': ' + hireCreds.mail_error) : '' }} — hand these credentials over manually.</template>
                    <template v-if="copiedField"> · Copied {{ copiedField }}!</template>
                </p>
            </div>

            <!-- Pipeline summary -->
            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3">
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-slate-400"></span>
                        <p class="text-[11px] font-black text-slate-400 uppercase tracking-wider">Scheduled</p>
                    </div>
                    <p class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ summary.scheduled ?? 0 }}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">Training not started</p>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>
                        <p class="text-[11px] font-black text-slate-400 uppercase tracking-wider">In Progress</p>
                    </div>
                    <p class="text-2xl font-black text-blue-600 mt-1">{{ summary.in_progress ?? 0 }}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">Training started</p>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-indigo-500"></span>
                        <p class="text-[11px] font-black text-slate-400 uppercase tracking-wider">Completed</p>
                    </div>
                    <p class="text-2xl font-black text-indigo-600 mt-1">{{ summary.completed ?? 0 }}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">Awaiting evaluation</p>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                        <p class="text-[11px] font-black text-slate-400 uppercase tracking-wider">Evaluated</p>
                    </div>
                    <p class="text-2xl font-black text-amber-600 mt-1">{{ summary.evaluated ?? 0 }}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">Graded by HR</p>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                        <p class="text-[11px] font-black text-slate-400 uppercase tracking-wider">Passed</p>
                    </div>
                    <p class="text-2xl font-black text-emerald-600 mt-1">{{ summary.passed ?? 0 }}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">Promoted to staff</p>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>
                        <p class="text-[11px] font-black text-slate-400 uppercase tracking-wider">Failed</p>
                    </div>
                    <p class="text-2xl font-black text-red-600 mt-1">{{ summary.failed ?? 0 }}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">Archived</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                    <input v-model="search" type="text" placeholder="Search trainee, employee ID, position, department..."
                        class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                </div>
                <select v-model="evalFilter" class="px-4 py-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold">
                    <option>All</option><option>Not Ready</option><option>Ready for Evaluation</option><option>Evaluated</option>
                </select>
                <select v-model="trainingFilter" class="px-4 py-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold">
                    <option>All</option><option>Scheduled</option><option>In Progress</option><option>Completed</option><option>Extended</option><option>Cancelled</option>
                </select>
            </div>

            <!-- Training table -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-tight">Trainee <span class="text-blue-600">Training List</span></h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Review completed trainee training and process final evaluation results. <span class="font-semibold text-slate-600 dark:text-slate-300">Click the table for view.</span></p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-[10px] uppercase tracking-wider text-slate-400 border-b border-slate-100 dark:border-slate-700">
                                <th class="px-4 py-3 font-bold">Trainee</th>
                                <th class="px-4 py-3 font-bold">Employee ID</th>
                                <th class="px-4 py-3 font-bold">Position</th>
                                <th class="px-4 py-3 font-bold">Department</th>
                                <th class="px-4 py-3 font-bold">Training Status</th>
                                <th class="px-4 py-3 font-bold">Grade</th>
                                <th class="px-4 py-3 font-bold">Evaluation</th>
                                <th class="px-4 py-3 font-bold">Result</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            <tr v-if="filteredTrainees.length === 0">
                                <td colspan="8" class="px-4 py-12 text-center">
                                    <User class="h-10 w-10 text-slate-300 mx-auto mb-3" />
                                    <p class="font-bold text-slate-500">No trainees found</p>
                                    <p class="text-xs text-slate-400 mt-1">All trainees and newly hired employees from different departments and modules appear here for HR to monitor and evaluate.</p>
                                </td>
                            </tr>
                            <tr v-for="t in filteredTrainees" :key="t.id" @click="openDetails(t)" class="hover:bg-blue-50/40 dark:hover:bg-slate-700/30 transition-colors cursor-pointer">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 flex items-center justify-center text-xs font-black shrink-0">{{ getInitials(t.name) }}</div>
                                        <div>
                                            <div class="font-bold text-slate-900 dark:text-white whitespace-nowrap">{{ t.name }}</div>
                                            <div class="text-[11px] text-slate-400">{{ t.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ t.employee_id }}</td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ t.position }}</td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ t.department }}</td>
                                <td class="px-4 py-3">
                                    <span :class="['px-2 py-1 rounded-full text-[11px] font-bold whitespace-nowrap', trainingBadge(t.training_status)]">{{ t.training_status }}</span>
                                </td>
                                <td class="px-4 py-3 font-black text-slate-800 dark:text-slate-100 whitespace-nowrap">{{ formatGrade(t.grade) }}</td>
                                <td class="px-4 py-3">
                                    <span :class="['px-2 py-1 rounded-full text-[11px] font-bold whitespace-nowrap', evaluationBadge(t.evaluation_state)]">{{ t.evaluation_state }}</span>
                                    <div v-if="t.retraining_count > 0" class="text-[10px] font-bold text-purple-600 dark:text-purple-400 mt-0.5 whitespace-nowrap">Retraining #{{ t.retraining_count }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="t.result" :class="['px-2 py-1 rounded-full text-[11px] font-bold whitespace-nowrap', resultBadge(t.result)]">{{ t.result }}</span>
                                    <span v-else class="text-slate-300 dark:text-slate-600">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Evaluate / View Evaluation modal -->
        <div v-if="isEvaluateOpen || isViewEvalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md" @click.self="closeModals">
            <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-slate-100 dark:border-slate-700 sticky top-0 bg-white dark:bg-slate-800 z-10">
                    <div class="flex justify-between items-start">
                        <div>
                            <h2 class="text-xl font-black text-slate-900 dark:text-white">{{ selectedTrainee?.name }}</h2>
                            <p class="text-sm text-slate-500 mt-0.5">{{ selectedTrainee?.position }} • {{ selectedTrainee?.department }}</p>
                        </div>
                        <button @click="closeModals" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">&times;</button>
                    </div>
                </div>

                <div class="p-6 space-y-6">
                    <!-- Training -->
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wider text-slate-700 dark:text-slate-200 mb-3 flex items-center gap-2">
                            <ClipboardList class="h-4 w-4" /> Training
                        </h3>
                        <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-900/50 text-left text-[11px] uppercase tracking-wider text-slate-400">
                                        <th class="px-4 py-2.5 font-bold">Training</th>
                                        <th class="px-4 py-2.5 font-bold text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                    <tr v-if="!(selectedTrainee?.trainings || []).length">
                                        <td colspan="2" class="px-4 py-4 text-center text-xs text-slate-400">No trainings assigned yet.</td>
                                    </tr>
                                    <tr v-for="tr in (selectedTrainee?.trainings || [])" :key="tr.id">
                                        <td class="px-4 py-2.5 font-medium text-slate-700 dark:text-slate-200">{{ tr.name }}</td>
                                        <td class="px-4 py-2.5 text-right">
                                            <span :class="['px-2 py-0.5 rounded-full text-[11px] font-bold', trainingBadge(tr.status)]">{{ tr.status }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Evaluation -->
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wider text-slate-700 dark:text-slate-200 mb-3 flex items-center gap-2">
                            <Star class="h-4 w-4" /> Evaluation
                        </h3>
                        <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-900/50 text-left text-[11px] uppercase tracking-wider text-slate-400">
                                        <th class="px-4 py-2.5 font-bold">Criteria</th>
                                        <th class="px-4 py-2.5 font-bold text-center">Weight</th>
                                        <th class="px-4 py-2.5 font-bold text-center">Score</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                    <tr v-for="c in criteria" :key="c.key">
                                        <td class="px-4 py-2.5 font-medium text-slate-700 dark:text-slate-200">{{ c.label }}</td>
                                        <td class="px-4 py-2.5 text-center text-slate-500">{{ weightLabel(c.weight) }}</td>
                                        <td class="px-4 py-2.5 text-center">
                                            <template v-if="isEvaluateOpen">
                                                <div class="flex justify-center gap-1">
                                                    <button v-for="star in 5" :key="star" type="button" @click="scores[c.key] = star * 20"
                                                        class="focus:outline-none transition-transform active:scale-110" :title="`${star * 20} points`">
                                                        <Star :class="[
                                                            (Number(scores[c.key]) / 20) >= star ? 'text-amber-400 fill-amber-400' : 'text-slate-200 dark:text-slate-600',
                                                            'h-6 w-6 transition-colors cursor-pointer'
                                                        ]" />
                                                    </button>
                                                </div>
                                                <p class="text-[10px] font-bold text-slate-400 mt-1">{{ scores[c.key] === '' ? 'Tap a star to rate' : `${scores[c.key]} pts` }}</p>
                                            </template>
                                            <template v-else>
                                                <div class="flex justify-center gap-0.5">
                                                    <Star v-for="star in 5" :key="star" :class="[
                                                        Math.round(Number(scores[c.key] || 0) / 20) >= star ? 'text-amber-400 fill-amber-400' : 'text-slate-200 dark:text-slate-600',
                                                        'h-5 w-5'
                                                    ]" />
                                                </div>
                                                <p class="text-[10px] font-bold text-slate-400 mt-1">{{ scores[c.key] === '' ? '—' : `${scores[c.key]} pts` }}</p>
                                            </template>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Final grade + result -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Final Grade</p>
                            <p class="text-2xl font-black text-slate-900 dark:text-white mt-1">
                                <span v-if="isEvaluateOpen">{{ finalGrade === null ? '—' : `${finalGrade.toFixed(2)}%` }}</span>
                                <span v-else>{{ formatGrade(selectedTrainee?.grade) }}</span>
                            </p>
                            <p class="text-[10px] text-slate-400 mt-1">Auto-calculated (weighted)</p>
                        </div>
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Result</p>
                            <p class="mt-1">
                                <span v-if="isEvaluateOpen" :class="['px-3 py-1 rounded-full text-sm font-black', liveResult === 'Passed' ? 'bg-emerald-100 text-emerald-700' : liveResult === 'Failed' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-700']">{{ liveResult }}</span>
                                <span v-else :class="['px-3 py-1 rounded-full text-sm font-black', resultBadge(selectedTrainee?.result)]">{{ selectedTrainee?.result ?? '—' }}</span>
                            </p>
                            <p class="text-[10px] text-slate-400 mt-1">Passing: {{ passingGrade }}%</p>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest block mb-2">Remarks</label>
                        <textarea v-if="isEvaluateOpen" v-model="remarks" rows="3" placeholder="Evaluator remarks..."
                            class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-sm focus:ring-2 focus:ring-blue-500"></textarea>
                        <p v-else class="text-sm text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 min-h-[3rem]">
                            {{ remarks || 'No remarks.' }}
                        </p>
                    </div>

                    <!-- Footer - always visible regardless of status / result / trainee-vs-staff.
                         Only permission-gated by canEdit. -->
                    <div class="flex flex-wrap gap-3 pt-2">
                        <button @click="closeModals" class="flex-1 py-3 text-slate-500 font-bold text-xs uppercase">Cancel</button>
                        <template v-if="isEvaluateOpen">
                            <button v-if="canEdit" @click="openTransferModal(selectedTrainee)" title="Transfer to Another Position/Department"
                                class="flex-1 py-3 bg-indigo-600 text-white rounded-xl text-xs font-black uppercase shadow-lg hover:bg-indigo-700 transition-all">
                                Transfer
                            </button>
                            <button v-if="canEdit" @click="openFailModal(selectedTrainee)" title="End Employment"
                                class="flex-1 py-3 bg-red-600 text-white rounded-xl text-xs font-black uppercase shadow-lg hover:bg-red-700 transition-all">
                                End Employment
                            </button>
                            <button @click="liveResult === 'Failed' ? stashScoresAndOpenRetrain() : saveEvaluation()" :disabled="!allScoresEntered || saving"
                                :class="[liveResult === 'Failed' ? 'bg-amber-500 hover:bg-amber-600' : 'bg-blue-600 hover:bg-blue-700', 'flex-1 py-3 text-white rounded-xl text-xs font-black uppercase shadow-lg transition-all disabled:opacity-50']">
                                {{ saving ? 'Saving...' : (liveResult === 'Failed' ? 'Schedule Retraining' : 'Save Evaluation') }}
                            </button>
                        </template>
                        <template v-else>
                            <button v-if="canEdit" @click="scheduleRetraining()"
                                class="flex-1 py-3 bg-amber-500 text-white rounded-xl text-xs font-black uppercase shadow-lg hover:bg-amber-600 transition-all">
                                Retrain
                            </button>
                            <button v-if="canEdit" @click="openTransferModal(selectedTrainee)"
                                class="flex-1 py-3 bg-indigo-600 text-white rounded-xl text-xs font-black uppercase shadow-lg hover:bg-indigo-700 transition-all">
                                Transfer
                            </button>
                            <button v-if="canEdit && selectedTrainee?.evaluation_state === 'Evaluated' && ['Passed','Recommend for Regularization'].includes(selectedTrainee?.result) && (selectedTrainee?.grade ?? 0) >= passingGrade && hrReviewPending(selectedTrainee)" @click="openPassModal(selectedTrainee)"
                                class="flex-1 py-3 bg-emerald-600 text-white rounded-xl text-xs font-black uppercase shadow-lg hover:bg-emerald-700 transition-all flex items-center justify-center gap-2">
                                <UserCheck class="h-4 w-4" /> Confirm &amp; Complete
                            </button>
                            <button v-if="canEdit" @click="openFailModal(selectedTrainee)"
                                class="flex-1 py-3 bg-red-600 text-white rounded-xl text-xs font-black uppercase shadow-lg hover:bg-red-700 transition-all flex items-center justify-center gap-2">
                                <UserMinus class="h-4 w-4" /> End Employment
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedule Retraining modal -->
        <div v-if="isRetrainOpen && selectedTrainee" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md" @click.self="closeModals">
            <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                <div class="bg-amber-500 p-6 text-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <h2 class="text-xl font-black uppercase">Schedule Retraining</h2>
                            <p class="text-amber-100 text-xs mt-1">A new training cycle will start for this employee.</p>
                        </div>
                        <button @click="closeModals" class="text-white/70 hover:text-white text-2xl leading-none">&times;</button>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 text-sm space-y-1.5">
                        <div class="flex justify-between"><span class="text-slate-400">Employee</span><span class="font-bold text-slate-800 dark:text-slate-100">{{ selectedTrainee.name }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Previous Grade</span><span class="font-bold text-slate-800 dark:text-slate-100">{{ retrainPreviousGrade === null ? '—' : `${retrainPreviousGrade.toFixed(2)}%` }}</span></div>
                        <div class="flex justify-between items-center"><span class="text-slate-400">Result</span><span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-100 text-red-600">Failed</span></div>
                    </div>
                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest block mb-2">Retraining Start Date *</label>
                        <input v-model="retrainForm.start_date" type="date"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-sm focus:ring-2 focus:ring-amber-500" />
                    </div>
                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest block mb-2">Retraining End Date <span class="normal-case font-medium">(optional)</span></label>
                        <input v-model="retrainForm.end_date" type="date" :min="retrainForm.start_date"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-sm focus:ring-2 focus:ring-amber-500" />
                        <p v-if="retrainForm.end_date && retrainForm.end_date < retrainForm.start_date" class="text-[11px] text-red-500 font-semibold mt-1">End date must be on or after the start date.</p>
                    </div>
                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest block mb-2">Reason for Retraining *</label>
                        <textarea v-model="retrainForm.reason" rows="2" placeholder="e.g., Failed practical skills evaluation"
                            class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-sm focus:ring-2 focus:ring-amber-500"></textarea>
                    </div>
                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest block mb-2">Notes <span class="normal-case font-medium">(optional)</span></label>
                        <textarea v-model="retrainForm.notes" rows="2" placeholder="Additional notes for the retraining cycle..."
                            class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-sm focus:ring-2 focus:ring-amber-500"></textarea>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button @click="closeModals" class="flex-1 py-3 text-slate-500 font-bold text-xs uppercase">Cancel</button>
                        <button @click="confirmRetraining" :disabled="!retrainValid || saving"
                            class="flex-1 py-3 bg-amber-500 text-white rounded-xl text-xs font-black uppercase shadow-lg hover:bg-amber-600 transition-all disabled:opacity-50">
                            {{ saving ? 'Scheduling...' : 'Confirm Retraining' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transfer modal -->
        <div v-if="isTransferOpen && selectedTrainee" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md" @click.self="closeModals">
            <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                <div class="bg-indigo-600 p-6 text-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <h2 class="text-xl font-black uppercase">Transfer Employee</h2>
                            <p class="text-indigo-100 text-xs mt-1">Move to another position/department. History is preserved.</p>
                        </div>
                        <button @click="closeModals" class="text-white/70 hover:text-white text-2xl leading-none">&times;</button>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 text-sm space-y-1.5">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Previous</p>
                        <div class="flex justify-between"><span class="text-slate-400">Employee</span><span class="font-bold text-slate-800 dark:text-slate-100">{{ selectedTrainee.name }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Department</span><span class="font-bold text-slate-800 dark:text-slate-100">{{ selectedTrainee.department }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Position</span><span class="font-bold text-slate-800 dark:text-slate-100">{{ selectedTrainee.position }}</span></div>
                        <div class="flex justify-between items-center"><span class="text-slate-400">Training Result</span><span :class="['px-2 py-0.5 rounded-full text-[11px] font-bold', selectedTrainee.result ? resultBadge(selectedTrainee.result) : evaluationBadge(selectedTrainee.evaluation_state)]">{{ selectedTrainee.result ?? selectedTrainee.evaluation_state }}</span></div>
                    </div>
                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest block mb-2">New Department *</label>
                        <select v-model="transferForm.department_id" @change="onTransferDepartmentChange"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="" disabled>Select department</option>
                            <option v-for="d in (departments || [])" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest block mb-2">System Role *</label>
                        <select v-model="transferForm.role"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="" disabled>Select role</option>
                            <option v-for="r in roleOptions" :key="r" :value="r">{{ r }}</option>
                        </select>
                        <p class="text-[11px] text-slate-400 mt-1">Written to the employee record. Prefilled from current role — confirm it.</p>
                    </div>
                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest block mb-2">New Position *</label>
                        <select v-model="transferForm.position" :disabled="!transferForm.department_id"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-sm focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                            <option value="" disabled>{{ transferForm.department_id ? 'Select position' : 'Select a department first' }}</option>
                            <option v-for="p in transferPositions" :key="p.id" :value="p.name">{{ p.name }}</option>
                        </select>
                        <p v-if="!transferForm.department_id" class="text-[11px] text-slate-400 mt-1">Choose a department first to see its positions.</p>
                        <p v-else-if="transferPositions.length === 0" class="text-[11px] text-amber-500 font-semibold mt-1">No positions found for this department.</p>
                    </div>
                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest block mb-2">Effective Date *</label>
                        <input v-model="transferForm.effective_date" type="date"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest block mb-2">Reason *</label>
                        <textarea v-model="transferForm.reason" rows="2" placeholder="e.g., Failed practical evaluation as Machine Operator; better fit for warehouse role"
                            class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-sm focus:ring-2 focus:ring-indigo-500"></textarea>
                    </div>
                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest block mb-2">New Training Required</label>
                        <div class="flex gap-2">
                            <button type="button" @click="transferForm.new_training_required = true"
                                :class="[transferForm.new_training_required ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white dark:bg-slate-900 text-slate-500 border-slate-200 dark:border-slate-700', 'flex-1 py-2.5 rounded-xl border text-xs font-black uppercase transition-all']">Yes</button>
                            <button type="button" @click="transferForm.new_training_required = false"
                                :class="[!transferForm.new_training_required ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white dark:bg-slate-900 text-slate-500 border-slate-200 dark:border-slate-700', 'flex-1 py-2.5 rounded-xl border text-xs font-black uppercase transition-all']">No</button>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Yes starts a fresh training/evaluation cycle for the new position.</p>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button @click="closeModals" class="flex-1 py-3 text-slate-500 font-bold text-xs uppercase">Cancel</button>
                        <button @click="confirmTransfer" :disabled="!transferValid || saving"
                            class="flex-1 py-3 bg-indigo-600 text-white rounded-xl text-xs font-black uppercase shadow-lg hover:bg-indigo-700 transition-all disabled:opacity-50">
                            {{ saving ? 'Transferring...' : 'Confirm Transfer' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- View Details modal: read-only Trainee Training Details -->
        <div v-if="isDetailsOpen && selectedTrainee" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md" @click.self="closeModals">
            <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
                <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-start justify-between sticky top-0 bg-white dark:bg-slate-800 z-10">
                    <div>
                        <h3 class="font-black text-slate-900 dark:text-white">Trainee Training Details</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ selectedTrainee.name }} · {{ selectedTrainee.employee_id }}</p>
                    </div>
                    <button @click="closeModals" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl text-slate-400"><X class="w-4 h-4" /></button>
                </div>
                <div v-if="detailLoading" class="p-10 text-center text-sm text-slate-400">Loading training details…</div>
                <div v-else-if="trainingDetail" class="p-5 space-y-6">
                    <!-- Employee Information -->
                    <section class="bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl p-4">
                        <h4 class="text-xs font-black uppercase text-slate-500 mb-2">Employee Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs text-slate-700 dark:text-slate-200">
                            <div><span class="text-slate-400">Employee:</span> <b>{{ trainingDetail.name }}</b></div>
                            <div><span class="text-slate-400">Employee ID:</span> <b class="font-mono">{{ trainingDetail.employee_id }}</b></div>
                            <div><span class="text-slate-400">Position:</span> <b>{{ trainingDetail.position }}</b></div>
                            <div><span class="text-slate-400">Department:</span> <b>{{ trainingDetail.department }}</b></div>
                            <div><span class="text-slate-400">Reporting Manager:</span> <b>{{ trainingDetail.manager_name || trainingDetail.reporting_manager || '—' }}</b></div>
                            <div><span class="text-slate-400">Employment Status:</span> <b>{{ trainingDetail.employment_status_label }}</b></div>
                        </div>
                    </section>

                    <!-- Training Information -->
                    <section>
                        <h4 class="text-xs font-black uppercase text-slate-500 mb-2">Training Information</h4>
                        <div class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden text-xs">
                            <div class="grid grid-cols-2 border-b border-slate-100 dark:border-slate-700"><span class="px-3 py-2 text-slate-400 bg-slate-50 dark:bg-slate-900/50">Training Program</span><span class="px-3 py-2 font-bold text-slate-800 dark:text-slate-100">{{ trainingDetail.training_program }}</span></div>
                            <div class="grid grid-cols-2 border-b border-slate-100 dark:border-slate-700"><span class="px-3 py-2 text-slate-400 bg-slate-50 dark:bg-slate-900/50">Training Start</span><span class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ dFmtDate(trainingDetail.training_start) }}</span></div>
                            <div class="grid grid-cols-2 border-b border-slate-100 dark:border-slate-700"><span class="px-3 py-2 text-slate-400 bg-slate-50 dark:bg-slate-900/50">Expected End</span><span class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ dFmtDate(trainingDetail.expected_end) }}</span></div>
                            <div class="grid grid-cols-2 border-b border-slate-100 dark:border-slate-700"><span class="px-3 py-2 text-slate-400 bg-slate-50 dark:bg-slate-900/50">Duration</span><span class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ trainingDetail.duration_label }}</span></div>
                            <div class="grid grid-cols-2 border-b border-slate-100 dark:border-slate-700"><span class="px-3 py-2 text-slate-400 bg-slate-50 dark:bg-slate-900/50">Current Week</span><span class="px-3 py-2 font-bold text-slate-800 dark:text-slate-100">{{ trainingDetail.current_week_label }}</span></div>
                            <div class="grid grid-cols-2 border-b border-slate-100 dark:border-slate-700"><span class="px-3 py-2 text-slate-400 bg-slate-50 dark:bg-slate-900/50">Training Status</span><span class="px-3 py-2 font-bold text-slate-800 dark:text-slate-100">{{ trainingDetail.training_status }}</span></div>
                            <div class="grid grid-cols-2 border-b border-slate-100 dark:border-slate-700"><span class="px-3 py-2 text-slate-400 bg-slate-50 dark:bg-slate-900/50">Evaluation Status</span><span class="px-3 py-2"><span :class="['px-2 py-0.5 rounded-full text-[11px] font-bold', evaluationBadge(trainingDetail.evaluation_state)]">{{ trainingDetail.evaluation_state }}</span></span></div>
                            <div class="grid grid-cols-2 border-b border-slate-100 dark:border-slate-700"><span class="px-3 py-2 text-slate-400 bg-slate-50 dark:bg-slate-900/50">Result</span><span class="px-3 py-2"><span v-if="trainingDetail.result" :class="['px-2 py-0.5 rounded-full text-[11px] font-bold', resultBadge(trainingDetail.result)]">{{ trainingDetail.result }}</span><span v-else class="text-slate-300">—</span></span></div>
                            <div class="grid grid-cols-2"><span class="px-3 py-2 text-slate-400 bg-slate-50 dark:bg-slate-900/50">HR Review</span><span class="px-3 py-2"><span :class="['px-2 py-0.5 rounded-full text-[11px] font-bold', hrReviewBadge(hrReviewStatus)]">{{ hrReviewStatus }}</span></span></div>
                        </div>
                    </section>
                    <!-- Weekly Evaluations (read-only weekly calendar, same as My Assigned Trainees) -->
                    <section class="space-y-4">
                        <h4 class="text-xs font-black uppercase text-slate-500">Weekly Evaluations</h4>
                        <p class="text-[11px] text-slate-500 -mt-2">{{ dEvaluatedCount }} of {{ dWeekOptions.length }} weeks evaluated · click a week to show its evaluation</p>
                        <!-- Week calendar: slidable strip showing every week + due date -->
                        <div class="flex items-center gap-1.5">
                            <button type="button" @click="dSlideWeeks(-1)" class="shrink-0 w-7 h-7 rounded-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-500 hover:bg-slate-50 flex items-center justify-center"><ChevronLeft class="w-4 h-4" /></button>
                            <div ref="dWeekStrip" class="flex gap-1.5 overflow-x-auto pb-1 snap-x snap-mandatory week-strip">
                                <button v-for="w in dWeekOptions" :key="'wk-cal-' + w" type="button" @click="dSelectEvalWeek(w)" :title="'Week ' + w + ' — due ' + dWeekEndLabel(w)" :class="['shrink-0 snap-start px-2 py-1.5 rounded-lg text-[10px] font-bold border transition-all whitespace-nowrap inline-flex items-center gap-1', Number(attWeek) === Number(w) ? 'border-blue-500 ring-2 ring-blue-200' : 'border-slate-200 dark:border-slate-700', dWeekSaved(w) ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : (dWeekOverdue(w) ? 'bg-red-50 text-red-700 border-red-300' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300')]">
                                    <Lock v-if="dWeekSaved(w)" class="w-3 h-3" />W{{ w }} · {{ dWeekEndLabel(w) }}
                                </button>
                            </div>
                            <button type="button" @click="dSlideWeeks(1)" class="shrink-0 w-7 h-7 rounded-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-500 hover:bg-slate-50 flex items-center justify-center"><ChevronRight class="w-4 h-4" /></button>
                        </div>
                        <div class="text-xs font-bold">Week {{ attWeek }} of {{ dWeekOptions.length }} weeks (from training duration)</div>
                        <!-- Selected week's evaluation (read-only) -->
                        <div class="border border-slate-200 dark:border-slate-700 rounded-xl p-4">
                            <h5 class="text-xs font-black uppercase text-slate-700 dark:text-slate-200 mb-1">Week {{ attWeek }} — {{ dWeekEndLabel(attWeek) }}</h5>
                            <p class="text-[11px] text-slate-500 mb-3">Weekly Score: <b class="text-slate-800 dark:text-slate-100">{{ overallForWeek(attWeek) }} / 5</b></p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs text-slate-700 dark:text-slate-200">
                                <div v-for="c in [['attendance','Attendance & Punctuality'],['knowledge','Knowledge & Learning'],['practical_skills','Practical Skills'],['work_behavior','Work Behavior'],['safety_compliance','Safety Compliance']]" :key="c[0]" class="flex items-center justify-between gap-2 border border-slate-100 dark:border-slate-700 rounded-lg px-3 py-2">
                                    <span>{{ c[1] }}</span>
                                    <b>{{ dScoreText(scoresForWeek(attWeek)[c[0]]) }}</b>
                                </div>
                            </div>
                            <div class="mt-3 text-xs font-bold text-slate-700 dark:text-slate-200">Manager's Recommendation</div>
                            <p class="mt-1 text-xs text-slate-700 dark:text-slate-200 flex items-center gap-2 flex-wrap">{{ weekSavedOf(attWeek) ? dWeeklyRecLabel(scoresForWeek(attWeek).recommendation) : '—' }}<span v-if="weekSavedOf(attWeek) && scoresForWeek(attWeek).recommendation === 'reassigned'" class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Reassigned</span><span v-if="weekSavedOf(attWeek) && scoresForWeek(attWeek).recommendation === 'early_termination'" class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">Early Termination</span></p>
                            <div class="mt-2 w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl text-xs bg-slate-50 dark:bg-slate-900/50 text-slate-600 dark:text-slate-300 min-h-[2.5rem]"><span class="font-bold">Manager's Notes:</span> {{ scoresForWeek(attWeek).notes || 'No notes.' }}</div>
                            <p class="mt-2 text-[11px] text-slate-500">Daily Observations: {{ obsForWeek(attWeek).length ? `Based on ${obsForWeek(attWeek).length} daily observation${obsForWeek(attWeek).length > 1 ? 's' : ''} recorded by the manager${weekSavedOf(attWeek) ? '' : ' (not yet saved)'}.` : 'No daily observations recorded for this week.' }}</p>
                            <button type="button" @click="dOpenWeekDetails(attWeek)" class="mt-3 inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:border-blue-300 hover:bg-blue-50 text-slate-700 hover:text-blue-700 rounded-xl text-xs font-bold transition-all shadow-sm">
                                <ClipboardList class="w-4 h-4" /> View Week {{ attWeek }} Details
                            </button>
                        </div>
                        <p v-if="(trainingDetail.weekly_evaluations || []).length" class="text-[11px] text-slate-500">
                            Past weeks: <span v-for="w in trainingDetail.weekly_evaluations" :key="w.id" class="font-bold text-slate-700 dark:text-slate-200 mr-2">W{{ w.week_number }} {{ Number(w.overall).toFixed(1) }}/5</span>
                        </p>
                    </section>

                    <!-- Final Evaluation (read-only) -->
                    <section class="border-2 border-blue-100 dark:border-blue-900/40 rounded-xl p-4 bg-blue-50/50 dark:bg-blue-900/10">
                        <h4 class="text-xs font-black uppercase text-slate-700 dark:text-slate-200 flex items-center gap-2"><ClipboardList class="w-4 h-4" /> Final Evaluation</h4>
                        <div v-if="trainingDetail.final_score === null || trainingDetail.final_score === undefined" class="mt-3 text-xs text-slate-500 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2">No final evaluation submitted yet.</div>
                        <div v-else>
                            <div class="mt-3 overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900">
                                <table class="w-full text-xs">
                                    <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 uppercase text-[10px]">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Category</th>
                                            <th class="px-3 py-2 text-right">Weight</th>
                                            <th class="px-3 py-2 text-right">Rating</th>
                                            <th class="px-3 py-2 text-right">Score</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="c in dFinalCriteria" :key="c.key" class="border-t border-slate-100 dark:border-slate-700">
                                            <td class="px-3 py-2 font-bold text-slate-700 dark:text-slate-200">{{ c.label }}</td>
                                            <td class="px-3 py-2 text-right text-slate-500">{{ c.weight }}</td>
                                            <td class="px-3 py-2 text-right text-slate-700 dark:text-slate-200">{{ c.score === null ? '—' : `${Math.round(c.score / 20)}/5` }}</td>
                                            <td class="px-3 py-2 text-right font-bold text-slate-800 dark:text-slate-100">{{ c.score === null ? '—' : `${c.score}/100` }}</td>
                                        </tr>
                                        <tr class="border-t-2 border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50">
                                            <td class="px-3 py-2 font-black text-slate-800 dark:text-slate-100">Final Score</td>
                                            <td class="px-3 py-2 text-right font-black text-slate-800 dark:text-slate-100">100%</td>
                                            <td class="px-3 py-2"></td>
                                            <td class="px-3 py-2 text-right font-black text-slate-800 dark:text-slate-100">{{ Number(trainingDetail.final_score).toFixed(2) }}/100</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3 text-xs font-bold text-slate-700 dark:text-slate-200">Manager's Recommendation</div>
                            <p class="mt-1 text-xs font-black text-slate-800 dark:text-slate-100">{{ dFinalRecLabel(trainingDetail.manager_recommendation) }}</p>
                            <div class="mt-3 text-xs font-bold text-slate-700 dark:text-slate-200">Final Notes</div>
                            <p class="mt-1 w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl text-xs bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 min-h-[3rem]">{{ trainingDetail.final_notes || 'No final notes.' }}</p>
                            <div class="mt-3 text-xs font-bold text-slate-700 dark:text-slate-200">Evaluation Information</div>
                            <div class="mt-1 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden text-xs">
                                <div class="grid grid-cols-2 border-b border-slate-100 dark:border-slate-700"><span class="px-3 py-2 text-slate-400 bg-slate-50 dark:bg-slate-900/50">Evaluator</span><span class="px-3 py-2 font-bold text-slate-800 dark:text-slate-100">{{ trainingDetail.final_evaluator_name || '—' }}</span></div>
                                <div class="grid grid-cols-2 border-b border-slate-100 dark:border-slate-700"><span class="px-3 py-2 text-slate-400 bg-slate-50 dark:bg-slate-900/50">Submitted</span><span class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ trainingDetail.final_evaluated_at ? dFmtDate(trainingDetail.final_evaluated_at) : '—' }}</span></div>
                                <div class="grid grid-cols-2 border-b border-slate-100 dark:border-slate-700"><span class="px-3 py-2 text-slate-400 bg-slate-50 dark:bg-slate-900/50">Evaluation Status</span><span class="px-3 py-2 font-bold text-slate-800 dark:text-slate-100">{{ trainingDetail.final_score !== null && trainingDetail.final_score !== undefined ? 'Submitted' : 'Not Submitted' }}</span></div>
                                <div class="grid grid-cols-2"><span class="px-3 py-2 text-slate-400 bg-slate-50 dark:bg-slate-900/50">HR Review Status</span><span class="px-3 py-2"><span :class="['px-2 py-0.5 rounded-full text-[11px] font-bold', hrReviewBadge(hrReviewStatus)]">{{ hrReviewStatus }}</span></span></div>
                            </div>
                        </div>
                    </section>

                    <!-- HR Review & Decision (the part HR controls) -->
                    <section class="border-2 border-emerald-200 dark:border-emerald-900/40 rounded-xl p-4 bg-emerald-50/50 dark:bg-emerald-900/10">
                        <h4 class="text-xs font-black uppercase text-slate-700 dark:text-slate-200 mb-2">HR Review &amp; Decision</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs mb-3">
                            <div v-for="item in hrChecklist" :key="item.label" class="flex items-center gap-2 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 bg-white dark:bg-slate-900">
                                <span :class="['w-5 h-5 rounded-full flex items-center justify-center text-[11px] font-black shrink-0', item.done ? 'bg-emerald-500 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-400']">{{ item.done ? '✓' : '✗' }}</span>
                                <span class="text-slate-700 dark:text-slate-200">{{ item.label }}</span>
                            </div>
                        </div>
                        <div v-if="hrDecided" class="border border-slate-200 dark:border-slate-700 rounded-xl p-3 bg-white dark:bg-slate-900 text-xs space-y-1.5">
                            <div class="flex items-center justify-between"><span class="text-slate-400">HR Decision</span><span :class="['px-2 py-0.5 rounded-full text-[11px] font-bold', hrReviewBadge(hrReview.status)]">{{ hrDecisionLabel(hrDecidedKey) }}</span></div>
                            <div class="flex items-center justify-between"><span class="text-slate-400">HR Reviewer</span><span class="font-bold text-slate-800 dark:text-slate-100">{{ hrReview.reviewer_name || '—' }}</span></div>
                            <div class="flex items-center justify-between"><span class="text-slate-400">Review Date</span><span class="font-bold text-slate-800 dark:text-slate-100">{{ hrReview.reviewed_at ? dFmtDate(hrReview.reviewed_at) : '—' }}</span></div>
                            <div v-if="hrReview.regularization_date" class="flex items-center justify-between"><span class="text-slate-400">Regularization Date</span><span class="font-bold text-slate-800 dark:text-slate-100">{{ dFmtDate(hrReview.regularization_date) }}</span></div>
                            <div v-if="hrReview.status === 'Reassigned' && lastTransfer" class="flex items-center justify-between"><span class="text-slate-400">New Assignment</span><span class="font-bold text-slate-800 dark:text-slate-100 text-right">{{ lastTransfer.to_position }} · {{ lastTransfer.to_department }}<span v-if="lastTransfer.effective_date" class="block text-[11px] font-medium text-slate-500">effective {{ dFmtDate(lastTransfer.effective_date) }}</span></span></div>
                            <p class="text-slate-500 pt-1">HR Notes: <span class="text-slate-700 dark:text-slate-200 font-medium">{{ hrReview.notes || '—' }}</span></p>
                        </div>
                        <div v-else-if="trainingDetail.final_score === null || trainingDetail.final_score === undefined" class="text-xs text-slate-500 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2">HR review opens once the manager submits the final evaluation.</div>
                        <div v-else-if="!canEdit" class="text-xs text-slate-500 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2">You do not have permission to record HR decisions.</div>
                        <div v-else-if="isReassignment" class="space-y-3">
                            <div class="text-[11px] text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-900/20 border border-sky-200 dark:border-sky-900/40 rounded-xl px-3 py-2">The manager recommended <b>Reassignment</b> — approve to move {{ trainingDetail.name }} to a new assignment (fresh training cycle), or reject.</div>
                            <div>
                                <div class="text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">HR Decision</div>
                                <div class="flex flex-col gap-2 text-xs">
                                    <label class="flex items-center gap-2 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 bg-white dark:bg-slate-900 cursor-pointer"><input v-model="hrDecision" type="radio" value="approve_reassignment" class="accent-emerald-600" /> <b>Approve Reassignment</b> <span class="text-slate-400">— move to new assignment</span></label>
                                    <label class="flex items-center gap-2 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 bg-white dark:bg-slate-900 cursor-pointer"><input v-model="hrDecision" type="radio" value="reject_reassignment" class="accent-red-600" /> <b>Reject Reassignment</b> <span class="text-slate-400">— end employment</span></label>
                                </div>
                            </div>
                            <div v-if="hrDecision === 'approve_reassignment'" class="space-y-3">
                                <div>
                                    <div class="text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">New Department *</div>
                                    <select v-model="reDeptId" @change="onReDeptChange" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl text-xs bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200">
                                        <option value="" disabled>Select department</option>
                                        <option v-for="d in (departments || [])" :key="d.id" :value="d.id">{{ d.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">System Role *</div>
                                    <select v-model="reRole" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl text-xs bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200">
                                        <option value="" disabled>Select role</option>
                                        <option v-for="r in roleOptions" :key="r" :value="r">{{ r }}</option>
                                    </select>
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">New Position *</div>
                                    <select v-model="rePosition" :disabled="!reDeptId" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl text-xs bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 disabled:opacity-50 disabled:cursor-not-allowed">
                                        <option value="" disabled>{{ reDeptId ? 'Select position' : 'Select a department first' }}</option>
                                        <option v-for="p in rePositions" :key="p.id" :value="p.name">{{ p.name }}</option>
                                    </select>
                                    <p v-if="!reDeptId" class="text-[11px] text-slate-400 mt-1">Choose a department first to see its positions.</p>
                                    <p v-else-if="rePositions.length === 0" class="text-[11px] text-amber-500 font-semibold mt-1">No positions found for this department.</p>
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">New Reporting Manager *</div>
                                    <select v-model="reManagerId" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl text-xs bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200">
                                        <option value="" disabled>Select reporting manager</option>
                                        <option v-for="m in reassignManagers" :key="m.id" :value="m.id">{{ m.name }} — {{ m.position }}</option>
                                    </select>
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Effective Date *</div>
                                    <input v-model="reEffectiveDate" type="date" class="w-full md:w-64 px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl text-xs bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200" />
                                </div>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">HR Reason/Notes *</div>
                                <textarea v-model="hrNotes" rows="2" placeholder="Enter reason for this reassignment decision..." class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl text-xs bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200"></textarea>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                                <div><div class="font-bold text-slate-700 dark:text-slate-200 mb-1">HR Reviewer</div><p class="px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-900/50 text-slate-700 dark:text-slate-200">{{ user?.name || '—' }}</p></div>
                                <div><div class="font-bold text-slate-700 dark:text-slate-200 mb-1">Review Date</div><p class="px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-900/50 text-slate-700 dark:text-slate-200">{{ dFmtDate(todayLocal()) }}</p></div>
                            </div>
                            <button @click="submitReassignment" :disabled="hrSaving || !reassignValid" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold disabled:opacity-50 transition">{{ hrDecision === 'approve_reassignment' ? 'Confirm Reassignment' : 'Submit HR Review' }}</button>
                        </div>
                        <div v-else class="space-y-3">
                            <div>
                                <div class="text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">HR Decision</div>
                                <div class="flex flex-col gap-2 text-xs">
                                    <label class="flex items-center gap-2 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 bg-white dark:bg-slate-900 cursor-pointer"><input v-model="hrDecision" type="radio" value="approve_regularization" class="accent-emerald-600" /> <b>Approve Regularization</b> <span class="text-slate-400">— Trainee becomes Regular</span></label>
                                    <label class="flex items-center gap-2 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 bg-white dark:bg-slate-900 cursor-pointer"><input v-model="hrDecision" type="radio" value="do_not_regularize" class="accent-red-600" /> <b>Do Not Regularize</b> <span class="text-slate-400">— end employment</span></label>
                                </div>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">HR Notes{{ hrDecision && hrDecision !== 'approve_regularization' ? ' *' : '' }}</div>
                                <textarea v-model="hrNotes" rows="2" placeholder="Enter HR review notes..." class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl text-xs bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200"></textarea>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                                <div><div class="font-bold text-slate-700 dark:text-slate-200 mb-1">HR Reviewer</div><p class="px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-900/50 text-slate-700 dark:text-slate-200">{{ user?.name || '—' }}</p></div>
                                <div><div class="font-bold text-slate-700 dark:text-slate-200 mb-1">Review Date</div><p class="px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-900/50 text-slate-700 dark:text-slate-200">{{ dFmtDate(todayLocal()) }}</p></div>
                            </div>
                            <div v-if="hrDecision === 'approve_regularization'">
                                <div class="text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Regularization Effective Date *</div>
                                <input v-model="hrEffectiveDate" type="date" :min="todayLocal()" class="w-full md:w-64 px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl text-xs bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200" />
                            </div>
                            <button @click="submitHrReview(false)" :disabled="hrSaving || !hrDecision" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold disabled:opacity-50 transition">Submit HR Review</button>
                        </div>
                    </section>

                    <div class="flex justify-end">
                        <button @click="closeModals" class="px-5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold transition">Close</button>
                    </div>
                </div>
                <div v-else class="p-10 text-center text-sm text-slate-400">Failed to load training details.</div>

                <!-- Attendance Details (read-only) -->
                <div v-if="showDetailAttendance && trainingDetail" class="fixed inset-0 z-[60] overflow-auto p-4">
                    <div class="fixed inset-0 bg-slate-900/50" @click="showDetailAttendance = false; dCloseObservation()"></div>
                    <div class="relative flex flex-col xl:flex-row gap-4 items-stretch xl:items-start justify-center w-full xl:w-max max-w-7xl mx-auto my-auto">
                        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl overflow-hidden max-h-[90vh] overflow-y-auto transition-all duration-300 shrink-0 w-full xl:w-[600px]">
                            <div class="p-5 border-b border-slate-100 dark:border-slate-700">
                                <h3 class="font-black text-slate-900 dark:text-white">Week {{ attWeek }} Evaluation Details</h3>
                                <p class="text-[11px] text-slate-500 mt-0.5">{{ dWeekRangeLabel(attWeek) }} · {{ trainingDetail.name }}</p>
                            </div>
                            <div class="p-5 space-y-4">
                                <div class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden text-xs">
                                    <div class="grid grid-cols-2 border-b border-slate-100 dark:border-slate-700"><span class="px-3 py-2 text-slate-400 bg-slate-50 dark:bg-slate-900/50">Scheduled Days</span><span class="px-3 py-2 font-black text-right text-slate-800 dark:text-slate-100">{{ dAttSummary.scheduled }}</span></div>
                                    <div class="grid grid-cols-2 border-b border-slate-100 dark:border-slate-700"><span class="px-3 py-2 text-slate-400 bg-slate-50 dark:bg-slate-900/50">Present</span><span class="px-3 py-2 font-black text-right text-emerald-600">{{ dAttSummary.present }}</span></div>
                                    <div class="grid grid-cols-2 border-b border-slate-100 dark:border-slate-700"><span class="px-3 py-2 text-slate-400 bg-slate-50 dark:bg-slate-900/50">Late</span><span class="px-3 py-2 font-black text-right text-amber-600">{{ dAttSummary.late }}</span></div>
                                    <div class="grid grid-cols-2"><span class="px-3 py-2 text-slate-400 bg-slate-50 dark:bg-slate-900/50">Absent</span><span class="px-3 py-2 font-black text-right text-rose-600">{{ dAttSummary.absent }}</span></div>
                                </div>
                                <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-xl">
                                    <table class="w-full text-xs">
                                        <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 uppercase text-[10px]">
                                            <tr>
                                                <th class="px-3 py-2 text-left">Date</th>
                                                <th class="px-3 py-2 text-left">Day</th>
                                                <th class="px-3 py-2 text-left">Time In</th>
                                                <th class="px-3 py-2 text-left">Time Out</th>
                                                <th class="px-3 py-2 text-left">Attendance</th>
                                                <th class="px-3 py-2 text-left">Observation</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(d, idx) in dAttDays" :key="idx" class="border-t border-slate-100 dark:border-slate-700">
                                                <td class="px-3 py-2 font-bold text-slate-800 dark:text-slate-100 whitespace-nowrap">{{ d.dateLabel }}</td>
                                                <td class="px-3 py-2 text-slate-500">{{ d.dayLabel }}</td>
                                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ d.timeIn }}</td>
                                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ d.timeOut }}</td>
                                                <td class="px-3 py-2"><span :class="['px-2 py-0.5 rounded-full font-bold text-[10px]', dAttStatusBadge(d.status)]">{{ d.status }}</span></td>
                                                <td class="px-3 py-2">
                                                    <button type="button" @click="dOpenObservation(d)" :title="dHasObservation(d) ? (obsTextFor(d) || 'View observation') : 'No observation recorded'" class="inline-flex items-center gap-1 px-2.5 py-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 text-slate-600 dark:text-slate-300 rounded-lg text-[10px] font-bold transition">
                                                        <Eye class="w-3.5 h-3.5" /> Observation<span v-if="dHasObservation(d)" class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p v-if="!(trainingDetail.attendance?.recent || []).length" class="text-[11px] text-slate-400 text-center">No attendance logs recorded for this trainee yet.</p>
                            </div>
                            <div class="p-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 flex justify-end">
                                <button @click="showDetailAttendance = false; dCloseObservation()" class="px-5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold transition">Close</button>
                            </div>
                        </div>

                        <!-- Daily Observation (read-only, beside) -->
                        <div v-if="showDetailObservation && detailDay" class="w-full xl:w-[600px] shrink-0 bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-h-[90vh] overflow-y-auto transition-all duration-300">
                            <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-start justify-between">
                                <div>
                                    <h3 class="font-black text-slate-900 dark:text-white">Daily Observation — {{ detailDay.dateLabel }} ({{ detailDay.dayLabel }})</h3>
                                    <p class="text-[11px] text-slate-500 mt-0.5">{{ dWeekRangeLabel(attWeek) }} · {{ trainingDetail.name }} · <span :class="['px-2 py-0.5 rounded-full font-bold text-[10px]', dAttStatusBadge(detailDay.status)]">{{ detailDay.status }}</span> · In {{ detailDay.timeIn }} / Out {{ detailDay.timeOut }}</p>
                                </div>
                                <button @click="dCloseObservation" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl text-slate-400"><X class="w-4 h-4" /></button>
                            </div>
                            <div v-if="dDayObservation" class="p-5 space-y-3">
                                <div v-for="c in [{key:'knowledge',label:'Knowledge & Learning'},{key:'practical_skills',label:'Practical Skills'},{key:'work_behavior',label:'Work Behavior'},{key:'safety_compliance',label:'Safety Compliance'}]" :key="c.key" class="border border-slate-200 dark:border-slate-700 rounded-xl p-3">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs font-black text-slate-700 dark:text-slate-200">{{ c.label }}</span>
                                        <b class="text-xs text-slate-800 dark:text-slate-100">{{ dScoreText(dDayObservation[c.key]) }}</b>
                                    </div>
                                    <p class="mt-2 text-xs text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700 rounded-lg px-3 py-2 min-h-[2.5rem]">{{ dDayObservation[c.key + '_notes'] || 'No notes.' }}</p>
                                </div>
                                <div>
                                    <div class="text-xs font-black text-slate-700 dark:text-slate-200 mb-1">Overall Notes</div>
                                    <p class="text-xs text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 min-h-[3rem]">{{ dDayObservation.notes || 'No notes.' }}</p>
                                </div>
                            </div>
                            <div v-else class="p-5"><p class="text-xs text-slate-400 text-center border border-dashed border-slate-200 dark:border-slate-700 rounded-xl px-3 py-6">No observation recorded for this day yet.</p></div>
                            <div class="p-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 flex justify-end">
                                <button @click="dCloseObservation" class="px-5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold transition">Close</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Approve Regularization confirmation -->
                <div v-if="showApproveConfirm && trainingDetail" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-slate-900/60" @click="showApproveConfirm = false"></div>
                    <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                        <div class="p-6 text-center">
                            <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-emerald-100 dark:bg-emerald-900/30 mb-3"><UserCheck class="h-7 w-7 text-emerald-600" /></div>
                            <h3 class="text-lg font-black text-slate-900 dark:text-white">Approve Regularization?</h3>
                            <p class="text-xs text-slate-500 mt-2">This action will change the employee's employment status from <b>Trainee</b> to <b>Regular</b>.</p>
                            <div class="mt-4 text-left">
                                <div class="text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Regularization Effective Date *</div>
                                <input v-model="hrEffectiveDate" type="date" :min="todayLocal()" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl text-xs bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200" />
                            </div>
                            <div v-if="trainingDetail" class="mt-3 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden text-xs text-left">
                                <div class="grid grid-cols-2 border-b border-slate-100 dark:border-slate-700"><span class="px-3 py-2 text-slate-400 bg-slate-50 dark:bg-slate-900/50">Before</span><span class="px-3 py-2 text-slate-400 bg-slate-50 dark:bg-slate-900/50">After</span></div>
                                <div class="grid grid-cols-2 border-b border-slate-100 dark:border-slate-700"><span class="px-3 py-2">Employment Status: Trainee</span><span class="px-3 py-2 font-bold text-emerald-700">Regular</span></div>
                                <div class="grid grid-cols-2 border-b border-slate-100 dark:border-slate-700"><span class="px-3 py-2">Training Status: {{ trainingDetail.training_status }}</span><span class="px-3 py-2 font-bold">Completed</span></div>
                                <div class="grid grid-cols-2 border-b border-slate-100 dark:border-slate-700"><span class="px-3 py-2">Result: {{ trainingDetail.result ?? '—' }}</span><span class="px-3 py-2 font-bold">Passed</span></div>
                                <div class="grid grid-cols-2"><span class="px-3 py-2">HR Review: Pending</span><span class="px-3 py-2 font-bold">Approved</span></div>
                            </div>
                            <div class="mt-4 flex gap-3">
                                <button @click="showApproveConfirm = false" class="flex-1 py-2.5 text-slate-500 font-bold text-xs uppercase">Cancel</button>
                                <button @click="submitHrReview(true)" :disabled="hrSaving || !hrEffectiveDate" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-black uppercase transition disabled:opacity-50">{{ hrSaving ? 'Confirming...' : 'Confirm Regularization' }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pass Modal -->
        <div v-if="isPassModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md" @click.self="closeModals">
            <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden text-center">
                <div class="bg-emerald-600 p-8 text-white">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white/20 mb-4"><UserCheck class="h-8 w-8" /></div>
                    <h2 class="text-xl font-black uppercase">Confirm &amp; Complete</h2>
                </div>
                <div class="p-6">
                    <p class="text-slate-600 dark:text-slate-300 mb-6">Confirm completion for <strong>{{ selectedTrainee?.name }}</strong>? They will become Active and leave the trainee list.</p>
                    <div class="flex gap-3">
                        <button @click="closeModals" class="flex-1 py-3 text-slate-500 font-bold text-xs uppercase">Cancel</button>
                        <button @click="passTrainee" class="flex-1 py-3 bg-emerald-600 text-white rounded-xl text-xs font-black uppercase hover:bg-emerald-700 transition-all">Confirm &amp; Complete</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- End Employment modal -->
        <div v-if="isFailModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md" @click.self="closeModals">
            <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">
                <div class="bg-red-600 p-6 text-white">
                    <h2 class="text-xl font-black uppercase">End Employment</h2>
                    <p class="text-red-200 text-xs mt-1">Per company policy — provide the reason below</p>
                </div>
                <div class="p-6 space-y-4">
                    <textarea v-model="failReason" rows="3" placeholder="e.g., Failed training evaluation, end of contract..."
                        class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-sm focus:ring-2 focus:ring-red-500"></textarea>
                    <div class="flex gap-3">
                        <button @click="closeModals" class="flex-1 py-3 text-slate-500 font-bold text-xs uppercase">Cancel</button>
                        <button @click="failTrainee" :disabled="!failReason.trim()" class="flex-1 py-3 bg-red-600 text-white rounded-xl text-xs font-black uppercase hover:bg-red-700 transition-all disabled:opacity-50">End Employment</button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(-12px); }
input[type="number"]::-webkit-inner-spin-button { opacity: 1; }
.week-strip { scrollbar-width: thin; -webkit-overflow-scrolling: touch; }
.week-strip::-webkit-scrollbar { height: 6px; }
.week-strip::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
</style>
