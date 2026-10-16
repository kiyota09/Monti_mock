<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Search, ClipboardList, X, Star, Lock, ChevronLeft, ChevronRight, Eye } from 'lucide-vue-next';

const props = defineProps({
    trainees: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({ total: 0, in_training: 0, due_evaluation: 0, final_evaluation: 0, completed: 0, extended: 0 }) },
    departments: { type: Array, default: () => [] },
    positions: { type: Array, default: () => [] },
    programs: { type: Array, default: () => [] },
    weights: { type: Object, default: () => ({ attendance: 0.20, knowledge: 0.25, practical_skills: 0.30, work_behavior: 0.15, safety_compliance: 0.10 }) },
    passingGrade: { type: Number, default: 75 },
});

const page = usePage();
const saving = ref(false);

// ---- Filters ----
const search = ref('');
const statusFilter = ref('All');
const positionFilter = ref('All');
const departmentFilter = ref('All');
const programFilter = ref('All');

const statusOptions = ['All', 'Not Started', 'In Progress', 'Evaluation Due', 'Final Evaluation', 'Completed', 'Extended'];

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return (props.trainees || []).filter((t) => {
        const okQ = !q || [t.name, t.employee_id, t.position, t.department, t.training_program].join(' ').toLowerCase().includes(q);
        const okS = statusFilter.value === 'All' || t.display_status === statusFilter.value;
        const okP = positionFilter.value === 'All' || t.position === positionFilter.value;
        const okD = departmentFilter.value === 'All' || t.department === departmentFilter.value;
        const okG = programFilter.value === 'All' || t.training_program === programFilter.value;
        return okQ && okS && okP && okD && okG;
    });
});

const statusBadge = (s) => ({
    'Not Started': 'bg-slate-100 text-slate-500',
    'In Progress': 'bg-emerald-100 text-emerald-700',
    'Evaluation Due': 'bg-amber-100 text-amber-700',
    'Final Evaluation': 'bg-blue-100 text-blue-700',
    'Completed': 'bg-emerald-100 text-emerald-700',
    'Extended': 'bg-purple-100 text-purple-700',
}[s] || 'bg-slate-100 text-slate-500');

const statusDotClass = (s) => ({
    'In Progress': 'bg-emerald-500', 'Evaluation Due': 'bg-amber-400', 'Final Evaluation': 'bg-blue-500', 'Completed': 'bg-emerald-600', 'Extended': 'bg-purple-500', 'Not Started': 'bg-slate-300',
}[s] || 'bg-slate-300');

const fmtDate = (d) => {
    if (!d) return '—';
    const dt = new Date(d);
    return isNaN(dt) ? '—' : dt.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};

// ---- Detail ----
const selected = ref(null);
const weekOptions = computed(() => {
    const n = Math.max(1, Math.min(52, Number(selected.value?.duration_weeks) || parseInt(String(selected.value?.duration_label || '8')) || 8));
    return Array.from({ length: n }, (_, i) => i + 1);
});
const loadWeeklyForm = (week, skipAuto = false) => {
    weeklyNotesError.value = false;
    const saved = (selected.value?.weekly_evaluations || []).find((w) => Number(w.week_number) === Number(week));
    // Final week of training: "Continue Training" is meaningless (nothing
    // left to continue into), so unsaved final weeks default to ready_final.
    const isFinalWeek = Number(week) === weekOptions.value.length;
    weeklyForm.value = saved ? {
        week_number: Number(saved.week_number),
        attendance: Number(saved.attendance ?? 5),
        knowledge: Number(saved.knowledge ?? 5),
        practical_skills: Number(saved.practical_skills ?? 5),
        work_behavior: Number(saved.work_behavior ?? 5),
        safety_compliance: Number(saved.safety_compliance ?? 5),
        notes: saved.notes || '',
        recommendation: saved.recommendation || 'continue',
    } : {
        week_number: Number(week) || 1,
        attendance: 5, knowledge: 5, practical_skills: 5, work_behavior: 5, safety_compliance: 5,
        notes: '', recommendation: isFinalWeek ? 'ready_final' : 'continue',
    };
    // Auto-fill from this week's daily observations when any exist
    // (skipped right after a manual save so the saved values stay shown).
    if (!skipAuto) applyAutoWeekly();
};
const openDetail = (t) => {
    selected.value = t;
    showAttendanceModal.value = false;
    showObservationModal.value = false;
    selectedDay.value = null;
    showWeeklyConfirm.value = false;
    showFinalConfirm.value = false;
    weeklyNotesError.value = false;
    loadWeeklyForm(t.current_week || 1);
    finalForm.value = {
        attendance: 5, knowledge: 5, practical_skills: 5, work_behavior: 5, safety_compliance: 5,
        notes: '', recommendation: 'regularization',
    };
};
const closeDetail = () => { selected.value = null; showAttendanceModal.value = false; showObservationModal.value = false; selectedDay.value = null; showWeeklyConfirm.value = false; showFinalConfirm.value = false; showFinalModal.value = false; showFinalDoneModal.value = false; finalDoneScore.value = null; weeklyNotesError.value = false; };

// Slidable week strip.
const weekStrip = ref(null);
const slideWeeks = (dir) => {
    weekStrip.value?.scrollBy({ left: dir * 220, behavior: 'smooth' });
};

// Week calendar: Week N covers training_start + (N-1)*7 → +6 days.
// The week-end date is the evaluation due date; overdue + unevaluated = red.
const weekEndDate = (week) => {
    if (!selected.value?.training_start) return null;
    const d = new Date(selected.value.training_start + 'T00:00:00');
    if (isNaN(d)) return null;
    d.setDate(d.getDate() + (Number(week) * 7) - 1);
    return d;
};
const weekEndLabel = (week) => {
    const d = weekEndDate(week);
    return d ? d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
};
const weekSaved = (week) => (selected.value?.weekly_evaluations || []).some((w) => Number(w.week_number) === Number(week));
const weekOverdue = (week) => {
    const d = weekEndDate(week);
    if (!d || weekSaved(week)) return false;
    const today = new Date(); today.setHours(0, 0, 0, 0);
    return d < today;
};

// ---- Attendance Details modal (per-week daily records) ----
// Week N covers training_start + (N-1)*7 → +6 days (Mon–Sat scheduled,
// Sunday = Rest Day). Built from the trainee's attendance logs.
const showAttendanceModal = ref(false);
const weekStartDate = (week) => {
    if (!selected.value?.training_start) return null;
    const d = new Date(selected.value.training_start + 'T00:00:00');
    if (isNaN(d)) return null;
    d.setDate(d.getDate() + (Number(week) - 1) * 7);
    return d;
};
const weekRangeLabel = (week) => {
    const s = weekStartDate(week);
    if (!s) return '';
    const e = new Date(s); e.setDate(e.getDate() + 6);
    const mon = (d) => d.toLocaleDateString('en-PH', { month: 'short' });
    if (s.getMonth() === e.getMonth() && s.getFullYear() === e.getFullYear()) {
        return `${mon(s)} ${s.getDate()}–${e.getDate()}, ${e.getFullYear()}`;
    }
    return `${mon(s)} ${s.getDate()} – ${mon(e)} ${e.getDate()}, ${e.getFullYear()}`;
};
const fmtTime = (t) => {
    if (!t) return '—';
    const m = String(t).trim().match(/(\d{1,2}):(\d{2})(?::(\d{2}))?\s*([AaPp])\.?\s*[Mm]?\.?/);
    if (m) {
        let h = Number(m[1]);
        const min = m[2];
        let suffix = m[4] ? m[4].toUpperCase() + 'M' : null;
        if (suffix) {
            if (h === 0) h = 12;
            if (h > 12) h = h - 12;
        } else {
            suffix = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
        }
        return `${h}:${min} ${suffix}`;
    }
    return String(t);
};
const normAttStatus = (s) => {
    const v = String(s || '').toLowerCase().replace(/[-_]/g, ' ').replace(/\s+/g, ' ').trim();
    if (['on time', 'ontime', 'present'].includes(v)) return 'Present';
    if (v === 'late') return 'Late';
    if (v === 'absent' || v === 'no show') return 'Absent';
    return s || '—';
};
const weekAttendanceDays = computed(() => {
    const s = weekStartDate(weeklyForm.value.week_number);
    if (!s) return [];
    const logs = selected.value?.attendance?.recent || [];
    const byDate = {};
    logs.forEach((l) => { if (l?.date) byDate[String(l.date).slice(0, 10)] = l; });
    const today = new Date(); today.setHours(0, 0, 0, 0);
    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(s); d.setDate(d.getDate() + i);
        const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        const log = byDate[key];
        const isSun = d.getDay() === 0;
        let status;
        if (log) status = normAttStatus(log.status);
        else if (isSun) status = 'Rest Day';
        else if (d > today) status = '—';
        else status = 'Absent';
        return {
            date: d,
            dateLabel: d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }),
            dayLabel: d.toLocaleDateString('en-PH', { weekday: 'short' }),
            timeIn: log ? fmtTime(log.clock_in) : '—',
            timeOut: log ? fmtTime(log.clock_out) : '—',
            status,
        };
    });
});
const weekAttendanceSummary = computed(() => {
    const days = weekAttendanceDays.value.filter((d) => d.status !== 'Rest Day' && d.status !== '—');
    return {
        scheduled: days.length,
        present: days.filter((d) => d.status === 'Present').length,
        late: days.filter((d) => d.status === 'Late').length,
        absent: days.filter((d) => d.status === 'Absent').length,
    };
});
const attStatusBadge = (s) => ({
    'Present': 'bg-emerald-100 text-emerald-700',
    'Late': 'bg-amber-100 text-amber-700',
    'Absent': 'bg-rose-100 text-rose-700',
    'Rest Day': 'bg-slate-100 text-slate-500',
}[s] || 'bg-slate-100 text-slate-500');

// ---- Daily Observation modal (per-date, nested inside Evaluation Details) ----
const showObservationModal = ref(false);
const selectedDay = ref(null);
const savingObservation = ref(false);
const observationForm = ref({ knowledge: 5, practical_skills: 5, work_behavior: 5, safety_compliance: 5, knowledge_notes: '', practical_skills_notes: '', work_behavior_notes: '', safety_compliance_notes: '', notes: '' });
const observationByDate = computed(() => {
    const map = {};
    (selected.value?.observations || []).forEach((o) => { if (o?.date) map[String(o.date).slice(0, 10)] = o; });
    return map;
});
const dayKey = (d) => {
    const dt = d?.date instanceof Date ? d.date : new Date(d?.date);
    if (isNaN(dt)) return '';
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
};
const hasObservation = (d) => !!observationByDate.value[dayKey(d)];
const openObservation = (d) => {
    selectedDay.value = d;
    const saved = observationByDate.value[dayKey(d)];
    observationForm.value = saved ? {
        knowledge: Number(saved.knowledge ?? 5),
        practical_skills: Number(saved.practical_skills ?? 5),
        work_behavior: Number(saved.work_behavior ?? 5),
        safety_compliance: Number(saved.safety_compliance ?? 5),
        knowledge_notes: saved.knowledge_notes || '',
        practical_skills_notes: saved.practical_skills_notes || '',
        work_behavior_notes: saved.work_behavior_notes || '',
        safety_compliance_notes: saved.safety_compliance_notes || '',
        notes: saved.notes || '',
    } : { knowledge: 5, practical_skills: 5, work_behavior: 5, safety_compliance: 5, knowledge_notes: '', practical_skills_notes: '', work_behavior_notes: '', safety_compliance_notes: '', notes: '' };
    showObservationModal.value = true;
};
const closeObservation = () => { showObservationModal.value = false; selectedDay.value = null; };
const saveObservation = () => {
    if (!selected.value || !selectedDay.value) return;
    savingObservation.value = true;
    const id = selected.value.id;
    router.post(route('hrm.assigned-trainees.observation', id), { date: dayKey(selectedDay.value), ...observationForm.value }, {
        preserveScroll: true,
        onSuccess: () => {
            const updated = (props.trainees || []).find((t) => t.id === id);
            if (updated) selected.value = updated;
            // Refresh the weekly scores from the updated daily observations.
            applyAutoWeekly();
            showObservationModal.value = false;
        },
        onFinish: () => { savingObservation.value = false; },
    });
};

// ---- Auto-calculation: weekly scores from daily observations + logs ----
// Each saved daily observation contributes to its week's averages:
// criteria = mean of that day's ratings; attendance = mean of daily points
// (Present 5 / Late 3 / Absent 1) over scheduled days. Rounded to 1–5.
const observationsInWeek = computed(() => {
    const s = weekStartDate(weeklyForm.value.week_number);
    if (!s) return [];
    const e = new Date(s); e.setDate(e.getDate() + 6);
    return (selected.value?.observations || []).filter((o) => {
        const d = new Date(String(o?.date).slice(0, 10) + 'T00:00:00');
        return !isNaN(d) && d >= s && d <= e;
    });
});
const autoWeekly = computed(() => {
    const obs = observationsInWeek.value;
    const avgOf = (k) => {
        const vals = obs.map((o) => Number(o?.[k])).filter((v) => Number.isFinite(v));
        if (!vals.length) return null;
        return vals.reduce((a, b) => a + b, 0) / vals.length;
    };
    const toScore = (v) => (v === null || v === undefined ? null : Math.max(1, Math.min(5, Math.round(v))));
    const days = weekAttendanceDays.value.filter((d) => ['Present', 'Late', 'Absent'].includes(d.status));
    let attendance = null;
    if (days.length) {
        const pts = days.map((d) => (d.status === 'Present' ? 5 : d.status === 'Late' ? 3 : 1));
        attendance = pts.reduce((a, b) => a + b, 0) / pts.length;
    }
    return {
        attendance: toScore(attendance),
        knowledge: toScore(avgOf('knowledge')),
        practical_skills: toScore(avgOf('practical_skills')),
        work_behavior: toScore(avgOf('work_behavior')),
        safety_compliance: toScore(avgOf('safety_compliance')),
        count: obs.length,
    };
});
const applyAutoWeekly = () => {
    // Never overwrite a submitted (locked) week.
    if (weekSaved(weeklyForm.value.week_number)) return;
    // Only auto-fill once the week has daily observations —
    // otherwise keep the saved (or default) weekly values untouched.
    const a = autoWeekly.value;
    if (!a.count) return;
    ['attendance', 'knowledge', 'practical_skills', 'work_behavior', 'safety_compliance'].forEach((k) => {
        if (a[k] !== null && a[k] !== undefined) weeklyForm.value[k] = a[k];
    });
};

// Final evaluation unlocks only when every weekly evaluation is finished.
const weeklyDoneCount = computed(() => (selected.value?.weekly_evaluations || []).length);
const weeklyRequiredCount = computed(() => weekOptions.value.length);
const weeklyComplete = computed(() => weeklyRequiredCount.value > 0 && weeklyDoneCount.value >= weeklyRequiredCount.value);

const weeklyForm = ref({ week_number: 1, attendance: 5, knowledge: 5, practical_skills: 5, work_behavior: 5, safety_compliance: 5, notes: '', recommendation: 'continue' });
const weeklyOverall = computed(() => {
    const v = [weeklyForm.value.attendance, weeklyForm.value.knowledge, weeklyForm.value.practical_skills, weeklyForm.value.work_behavior, weeklyForm.value.safety_compliance].map(Number);
    return (v.reduce((a, b) => a + b, 0) / v.length).toFixed(1);
});

// ---- Submit locks: a saved week / submitted final cannot be modified ----
const showWeeklyConfirm = ref(false);
const showFinalConfirm = ref(false);
const weeklyNotesError = ref(false);
const currentWeekSaved = computed(() => weekSaved(weeklyForm.value.week_number));
// Final training week (up to and including its end date): there is no next
// week to continue into, so "Continue Training" is locked — only Ready for
// Final Evaluation and Early Termination remain selectable.
const isLastTrainingWeek = computed(() => weekOptions.value.length > 0 && Number(weeklyForm.value.week_number) === Number(weekOptions.value[weekOptions.value.length - 1]));
const finalSubmitted = computed(() => selected.value?.final_score !== null && selected.value?.final_score !== undefined);
const weeklyRecLabel = (r) => ({ continue: 'Continue Training', needs_improvement: 'Needs Improvement', ready_final: 'Ready for Final Evaluation', reassigned: 'Reassigned', early_termination: 'Early Termination' }[r] || r || '—');
const terminationNeedsNotes = computed(() => weeklyForm.value.recommendation === 'early_termination' && !(weeklyForm.value.notes || '').trim());
// Early termination cancels the rest of training: no final evaluation happens.
const terminationWeek = computed(() => (selected.value?.weekly_evaluations || []).find((w) => w.recommendation === 'early_termination')?.week_number ?? null);
const terminatedEarly = computed(() => terminationWeek.value !== null);
// Ready for Final auto-passes the remaining training days: final unlocks now.
const readyForFinal = computed(() => !finalSubmitted.value && !terminatedEarly.value && (selected.value?.weekly_evaluations || []).some((w) => w.recommendation === 'ready_final'));
const readyForFinalWeek = computed(() => (selected.value?.weekly_evaluations || []).find((w) => w.recommendation === 'ready_final')?.week_number ?? null);
const finalUnlocked = computed(() => weeklyComplete.value || readyForFinal.value);

const saveWeekly = () => {
    if (!selected.value || saving.value) return;
    if (currentWeekSaved.value || terminatedEarly.value) return;
    if (terminationNeedsNotes.value) { weeklyNotesError.value = true; return; }
    weeklyNotesError.value = false;
    showWeeklyConfirm.value = true;
};

const confirmWeeklySave = () => {
    if (!selected.value || saving.value) return;
    if (currentWeekSaved.value || terminatedEarly.value) { showWeeklyConfirm.value = false; return; }
    if (terminationNeedsNotes.value) { weeklyNotesError.value = true; showWeeklyConfirm.value = false; return; }
    saving.value = true;
    const id = selected.value.id;
    const week = weeklyForm.value.week_number;
    const wasReadyFinal = weeklyForm.value.recommendation === 'ready_final';
    router.post(route('hrm.assigned-trainees.weekly', id), weeklyForm.value, {
        preserveScroll: true,
        onSuccess: async () => {
            showWeeklyConfirm.value = false;
            // Pull fresh detail from the server so unlock state (0/2 etc.)
            // reflects the just-saved week, then keep showing it locked.
            await refreshSelected(id);
            loadWeeklyForm(week, true);
            // Ready-for-Final chains straight into the Final Evaluation modal.
            if (wasReadyFinal && finalUnlocked.value && !finalSubmitted.value && !terminatedEarly.value) {
                showFinalModal.value = true;
            }
        },
        onError: () => { showWeeklyConfirm.value = false; },
        onFinish: () => { saving.value = false; },
    });
};

// Fresh server detail (weekly_evaluations, final score) — props may lag
// behind the just-saved record, which wrongly keeps Final locked at 0/2.
const refreshSelected = async (id) => {
    try {
        const res = await fetch(route('hrm.assigned-trainees.show', id), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (res.ok) {
            const data = await res.json();
            if (data?.trainee) {
                selected.value = data.trainee;
                return;
            }
        }
    } catch (e) { /* fall back to props below */ }
    const updated = (props.trainees || []).find((t) => t.id === id);
    if (updated) selected.value = updated;
};

const finalForm = ref({ attendance: 5, knowledge: 5, practical_skills: 5, work_behavior: 5, safety_compliance: 5, notes: '', recommendation: 'regularization' });
// Stars are 1–5 per criterion; each star = 20pts on the 0–100 backend scale.
const starToScore = (stars) => Math.max(0, Math.min(5, Number(stars) || 0)) * 20;
const finalCriteria = [
    { key: 'attendance', label: 'Attendance' },
    { key: 'knowledge', label: 'Knowledge' },
    { key: 'practical_skills', label: 'Practical Skills' },
    { key: 'work_behavior', label: 'Work Behavior' },
    { key: 'safety_compliance', label: 'Safety Compliance' },
];
const setFinalStars = (key, stars) => { if (finalSubmitted.value) return; finalForm.value[key] = stars; };
const finalScore = computed(() => {
    const w = props.weights;
    const f = finalForm.value;
    const vals = { attendance: starToScore(f.attendance), knowledge: starToScore(f.knowledge), practical_skills: starToScore(f.practical_skills), work_behavior: starToScore(f.work_behavior), safety_compliance: starToScore(f.safety_compliance) };
    return ((vals.attendance * (w.attendance ?? 0.20)) + (vals.knowledge * (w.knowledge ?? 0.25)) + (vals.practical_skills * (w.practical_skills ?? 0.30)) + (vals.work_behavior * (w.work_behavior ?? 0.15)) + (vals.safety_compliance * (w.safety_compliance ?? 0.10))).toFixed(2);
});

const clockTrainee = (action) => {
    if (!selected.value) return;
    saving.value = true;
    const id = selected.value.id;
    router.post(route('hrm.assigned-trainees.clock', id), { action }, {
        preserveScroll: true,
        onSuccess: () => {
            const updated = (props.trainees || []).find((t) => t.id === id);
            if (updated) selected.value = updated;
        },
        onFinish: () => { saving.value = false; },
    });
};

const submitFinal = () => {
    if (!selected.value || saving.value) return;
    if (!finalUnlocked.value || finalSubmitted.value || terminatedEarly.value) return;
    showFinalConfirm.value = true;
};

const confirmFinalSubmit = () => {
    if (!selected.value || saving.value) return;
    if (!finalUnlocked.value || finalSubmitted.value || terminatedEarly.value) { showFinalConfirm.value = false; return; }
    saving.value = true;
    const id = selected.value.id;
    const doneScore = finalScore.value;
    const payload = {
        attendance: starToScore(finalForm.value.attendance),
        knowledge: starToScore(finalForm.value.knowledge),
        practical_skills: starToScore(finalForm.value.practical_skills),
        work_behavior: starToScore(finalForm.value.work_behavior),
        safety_compliance: starToScore(finalForm.value.safety_compliance),
        notes: finalForm.value.notes,
        recommendation: finalForm.value.recommendation,
    };
    router.post(route('hrm.assigned-trainees.final', id), payload, {
        preserveScroll: true,
        onSuccess: async () => {
            showFinalConfirm.value = false;
            showFinalModal.value = false;
            await refreshSelected(id);
            // Post-submit confirmation modal (score + HR-review next step).
            finalDoneScore.value = doneScore;
            showFinalDoneModal.value = true;
        },
        onError: () => { showFinalConfirm.value = false; },
        onFinish: () => { saving.value = false; },
    });
};

// Final Evaluation lives in its own modal (opened from the inline section
// or auto-opened after a Ready-for-Final weekly save).
const showFinalModal = ref(false);
const showFinalDoneModal = ref(false);
const finalDoneScore = ref(null);
</script>

<template>
    <Head title="My Assigned Trainees" />
    <AuthenticatedLayout>
        <div class="p-6 space-y-6">
            <div>
                <h1 class="text-xl font-black text-slate-900">My Assigned Trainees</h1>
                <p class="text-xs text-slate-500 mt-1">Monitor the training progress and evaluate employees assigned to you.</p>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
                    <p class="text-[11px] font-bold text-slate-400 uppercase">Total Trainees</p>
                    <p class="text-2xl font-black text-slate-900 mt-1">{{ summary.total }}</p>
                </div>
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
                    <p class="text-[11px] font-bold text-slate-400 uppercase">In Training</p>
                    <p class="text-2xl font-black text-emerald-600 mt-1">{{ summary.in_training }}</p>
                </div>
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
                    <p class="text-[11px] font-bold text-slate-400 uppercase">Due for Evaluation</p>
                    <p class="text-2xl font-black text-amber-600 mt-1">{{ summary.due_evaluation }}</p>
                </div>
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
                    <p class="text-[11px] font-bold text-slate-400 uppercase">Ready for Final Evaluation</p>
                    <p class="text-2xl font-black text-blue-600 mt-1">{{ summary.final_evaluation }}</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm grid grid-cols-1 md:grid-cols-5 gap-3">
                <div class="relative md:col-span-1">
                    <Search class="w-4 h-4 absolute left-3 top-3 text-slate-400" />
                    <input v-model="search" placeholder="Search trainee" class="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-xs" />
                </div>
                <select v-model="statusFilter" class="px-3 py-2 border border-slate-200 rounded-xl text-xs">
                    <option v-for="s in statusOptions" :key="s" :value="s">{{ s === 'All' ? 'Training Status' : s }}</option>
                </select>
                <select v-model="positionFilter" class="px-3 py-2 border border-slate-200 rounded-xl text-xs">
                    <option value="All">Position</option>
                    <option v-for="p in positions" :key="p.id" :value="p.name">{{ p.name }}</option>
                </select>
                <select v-model="departmentFilter" class="px-3 py-2 border border-slate-200 rounded-xl text-xs">
                    <option value="All">Department</option>
                    <option v-for="d in departments" :key="d.id" :value="d.name">{{ d.name }}</option>
                </select>
                <select v-model="programFilter" class="px-3 py-2 border border-slate-200 rounded-xl text-xs">
                    <option value="All">Training Program</option>
                    <option v-for="g in programs" :key="g" :value="g">{{ g }}</option>
                </select>
            </div>

            <!-- Trainee List -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-[10px]">
                            <tr>
                                <th class="px-4 py-3 text-left">Trainee</th>
                                <th class="px-4 py-3 text-left">Employee ID</th>
                                <th class="px-4 py-3 text-left">Position</th>
                                <th class="px-4 py-3 text-left">Department</th>
                                <th class="px-4 py-3 text-left">Training Program</th>
                                <th class="px-4 py-3 text-left">Start Date</th>
                                <th class="px-4 py-3 text-left">Expected End</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Latest Evaluation</th>
                                <th class="px-4 py-3 text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in filtered" :key="t.id" class="border-t border-slate-100 hover:bg-slate-50">
                                <td class="px-4 py-3 font-bold text-slate-800">{{ t.name }}</td>
                                <td class="px-4 py-3 font-mono">{{ t.employee_id }}</td>
                                <td class="px-4 py-3">{{ t.position }}</td>
                                <td class="px-4 py-3">{{ t.department }}</td>
                                <td class="px-4 py-3">{{ t.training_program }}</td>
                                <td class="px-4 py-3">{{ fmtDate(t.training_start) }}</td>
                                <td class="px-4 py-3">{{ fmtDate(t.expected_end) }}</td>
                                <td class="px-4 py-3"><span :class="['px-2 py-1 rounded-full font-bold inline-flex items-center gap-1.5', statusBadge(t.display_status)]"><span :class="['w-2 h-2 rounded-full inline-block', statusDotClass(t.display_status)]"></span>{{ t.display_status }}</span></td>
                                <td class="px-4 py-3 font-bold">{{ t.latest_evaluation_label }}</td>
                                <td class="px-4 py-3">
                                    <button @click="openDetail(t)" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-bold text-[11px]">{{ t.display_status === 'Final Evaluation' || t.display_status === 'Evaluation Due' ? 'Evaluate' : 'View' }}</button>
                                </td>
                            </tr>
                            <tr v-if="!filtered.length">
                                <td colspan="10" class="px-4 py-10 text-center text-slate-400">No trainees assigned to you.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Detail modal -->
            <div v-if="selected" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/50" @click="closeDetail"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
                    <div class="p-5 border-b border-slate-200 flex items-start justify-between sticky top-0 bg-white">
                        <div>
                            <h3 class="font-black text-slate-900">Trainee Training Details</h3>
                            <p class="text-[11px] text-slate-500">{{ selected.name }} · {{ selected.employee_id }}</p>
                        </div>
                        <button @click="closeDetail" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400"><X class="w-4 h-4" /></button>
                    </div>

                    <div class="p-5 space-y-6">
                        <!-- Employee Information -->
                        <section class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <h4 class="text-xs font-black uppercase text-slate-500 mb-2">Employee Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                                <div><span class="text-slate-400">Employee:</span> <b>{{ selected.name }}</b></div>
                                <div><span class="text-slate-400">Employee ID:</span> <b class="font-mono">{{ selected.employee_id }}</b></div>
                                <div><span class="text-slate-400">Position:</span> <b>{{ selected.position }}</b></div>
                                <div><span class="text-slate-400">Department:</span> <b>{{ selected.department }}</b></div>
                                <div><span class="text-slate-400">Reporting Manager:</span> <b>{{ selected.manager_name || selected.reporting_manager || '—' }}</b></div>
                                <div><span class="text-slate-400">Employment Status:</span> <b>{{ selected.employment_status_label }}</b></div>
                            </div>
                        </section>

                        <!-- Training Information -->
                        <section>
                            <h4 class="text-xs font-black uppercase text-slate-500 mb-2">Training Information</h4>
                            <div class="border border-slate-200 rounded-xl overflow-hidden text-xs">
                                <div class="grid grid-cols-2 border-b border-slate-100"><span class="px-3 py-2 text-slate-400 bg-slate-50">Training Program</span><span class="px-3 py-2 font-bold">{{ selected.training_program }}</span></div>
                                <div class="grid grid-cols-2 border-b border-slate-100"><span class="px-3 py-2 text-slate-400 bg-slate-50">Training Start</span><span class="px-3 py-2">{{ fmtDate(selected.training_start) }}</span></div>
                                <div class="grid grid-cols-2 border-b border-slate-100"><span class="px-3 py-2 text-slate-400 bg-slate-50">Expected End</span><span class="px-3 py-2">{{ fmtDate(selected.expected_end) }}</span></div>
                                <div class="grid grid-cols-2 border-b border-slate-100"><span class="px-3 py-2 text-slate-400 bg-slate-50">Duration</span><span class="px-3 py-2">{{ selected.duration_label }}</span></div>
                                <div class="grid grid-cols-2 border-b border-slate-100"><span class="px-3 py-2 text-slate-400 bg-slate-50">Current Week</span><span class="px-3 py-2 font-bold">{{ selected.current_week_label }}</span></div>
                                <div class="grid grid-cols-2"><span class="px-3 py-2 text-slate-400 bg-slate-50">Status</span><span class="px-3 py-2 font-bold">{{ selected.display_status }}</span></div>
                            </div>
                        </section>

                        <!-- Weekly Evaluation -->
                        <section class="border border-slate-200 rounded-xl p-4">
                            <h4 class="text-xs font-black uppercase text-slate-700 mb-1">Week {{ weeklyForm.week_number }} Evaluation</h4>
                            <p class="text-[11px] text-slate-500 mb-3">Weekly scale 1–5 · Overall {{ weeklyOverall }}/5 · data kept per week · Due <b :class="weekOverdue(weeklyForm.week_number) ? 'text-red-600' : ''">{{ weekEndLabel(weeklyForm.week_number) }}</b></p>
                            <div v-if="terminatedEarly" class="mb-3 bg-red-50 border border-red-200 rounded-xl p-3 text-xs text-red-800 flex items-center gap-2">
                                <Lock class="w-4 h-4 shrink-0" /><span><b>Training terminated early (Week {{ terminationWeek }}) — remaining weeks cancelled.</b> No final evaluation will happen.</span>
                            </div>
                            <div v-if="currentWeekSaved" class="mb-3 bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-xs text-emerald-800 flex items-center gap-2">
                                <Lock class="w-4 h-4 shrink-0" /><span><b>Week {{ weeklyForm.week_number }} submitted — locked.</b> This evaluation can no longer be modified.</span>
                            </div>
                            <!-- Week calendar: slidable strip, every week-end due date, overdue unevaluated in red -->
                            <div class="flex items-center gap-1.5 mb-3">
                                <button type="button" @click="slideWeeks(-1)" class="shrink-0 w-7 h-7 rounded-full border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 flex items-center justify-center"><ChevronLeft class="w-4 h-4" /></button>
                                <div ref="weekStrip" class="flex gap-1.5 overflow-x-auto pb-1 snap-x snap-mandatory week-strip">
                                    <button v-for="w in weekOptions" :key="'cal-' + w" type="button" @click="loadWeeklyForm(w)" :disabled="weekSaved(w) || terminatedEarly" :title="weekSaved(w) ? 'Week ' + w + ' submitted — locked' : (terminatedEarly ? 'Training terminated — remaining weeks cancelled' : 'Week ' + w + ' — due ' + weekEndLabel(w))" :class="['shrink-0 snap-start px-2 py-1.5 rounded-lg text-[10px] font-bold border transition-all whitespace-nowrap inline-flex items-center gap-1', Number(weeklyForm.week_number) === Number(w) ? 'border-blue-500 ring-2 ring-blue-200' : 'border-slate-200', weekSaved(w) ? 'bg-emerald-50 text-emerald-700 border-emerald-200 opacity-70 cursor-not-allowed' : (terminatedEarly ? 'bg-slate-100 text-slate-400 border-slate-200 opacity-70 cursor-not-allowed' : (weekOverdue(w) ? 'bg-red-50 text-red-700 border-red-300' : 'bg-white text-slate-600'))]">
                                        <Lock v-if="weekSaved(w)" class="w-3 h-3" />W{{ w }} · {{ weekEndLabel(w) }}
                                    </button>
                                </div>
                                <button type="button" @click="slideWeeks(1)" class="shrink-0 w-7 h-7 rounded-full border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 flex items-center justify-center"><ChevronRight class="w-4 h-4" /></button>
                            </div>
                            <div class="text-xs font-bold mb-2">Week {{ weeklyForm.week_number }} of {{ weekOptions.length }} weeks (from training duration)</div>
                            <div :class="(currentWeekSaved || terminatedEarly) ? 'opacity-50 pointer-events-none select-none' : ''">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                                <label v-for="c in [['attendance','Attendance & Punctuality'],['knowledge','Knowledge & Learning'],['practical_skills','Practical Skills'],['work_behavior','Work Behavior'],['safety_compliance','Safety Compliance']]" :key="c[0]" class="flex items-center justify-between gap-2">
                                    <span>{{ c[1] }}</span>
                                    <select v-model.number="weeklyForm[c[0]]" :disabled="currentWeekSaved" class="w-24 px-2 py-1.5 border border-slate-200 rounded-lg disabled:bg-slate-100">
                                        <option :value="1">1 / 5</option><option :value="2">2 / 5</option><option :value="3">3 / 5</option><option :value="4">4 / 5</option><option :value="5">5 / 5</option>
                                    </select>
                                </label>
                            </div>
                            <p v-if="observationsInWeek.length && !currentWeekSaved" class="mt-2 text-[11px] text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">Auto-calculated from {{ observationsInWeek.length }} daily observation{{ observationsInWeek.length > 1 ? 's' : '' }} this week — you can still adjust before saving.</p>
                            <button type="button" @click="showAttendanceModal = true" class="mt-3 inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-slate-200 hover:border-blue-300 hover:bg-blue-50 text-slate-700 hover:text-blue-700 rounded-xl text-xs font-bold transition-all shadow-sm">
                                <ClipboardList class="w-4 h-4" /> View Week {{ weeklyForm.week_number }} Evaluation Details
                            </button>
                            <textarea v-model="weeklyForm.notes" :disabled="currentWeekSaved" @input="weeklyNotesError = false" placeholder="Notes" class="mt-3 w-full px-3 py-2 border border-slate-200 rounded-xl text-xs disabled:bg-slate-100" rows="2"></textarea>
                            <div class="mt-3 text-xs font-bold">Manager's Recommendation</div>
                            <div class="flex flex-wrap gap-4 mt-1 text-xs">
                                <label :class="['flex items-center gap-1.5', isLastTrainingWeek ? 'opacity-40 cursor-not-allowed' : '']"><input v-model="weeklyForm.recommendation" :disabled="currentWeekSaved || isLastTrainingWeek" type="radio" value="continue" /> Continue Training</label>
                                <label class="flex items-center gap-1.5"><input v-model="weeklyForm.recommendation" :disabled="currentWeekSaved" type="radio" value="ready_final" /> Ready for Final Evaluation</label>
                                <label class="flex items-center gap-1.5"><input v-model="weeklyForm.recommendation" :disabled="currentWeekSaved" type="radio" value="early_termination" /> Early Termination</label>
                            </div>
                            <p v-if="isLastTrainingWeek && !currentWeekSaved && !terminatedEarly" class="mt-2 text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">Final week of training — <b>Continue Training is unavailable.</b> Choose Ready for Final Evaluation or Early Termination.</p>
                            <div v-if="weeklyForm.recommendation === 'early_termination' && !currentWeekSaved && !terminatedEarly" class="mt-3 bg-red-50 border border-red-300 rounded-xl p-3 text-xs text-red-800">
                                <b>Early termination notes required.</b> Please state in the Notes above why {{ selected.name }}'s training is being ended early — once saved, <b>no final evaluation will happen</b> and the remaining training is <b>automatically cancelled</b>.
                                <p v-if="weeklyNotesError" class="mt-1 font-bold text-red-600">Notes are required before saving an Early Termination.</p>
                            </div>
                            <div v-if="weeklyForm.recommendation === 'ready_final' && !currentWeekSaved && !terminatedEarly" class="mt-3 bg-blue-50 border border-blue-200 rounded-xl p-3 text-xs text-blue-800">
                                <b>Ready for Final Evaluation.</b> Once saved, the remaining training days are <b>automatically passed</b> — {{ selected.name }} no longer needs to attend training, and the Final Evaluation below unlocks.
                            </div>
                            </div>
                            <div v-if="(selected.weekly_evaluations || []).length" class="mt-3 text-[11px] text-slate-500">
                                Past weeks: <span v-for="w in selected.weekly_evaluations" :key="w.id" class="font-bold text-slate-700 mr-2">W{{ w.week_number }} {{ Number(w.overall).toFixed(1) }}/5</span>
                            </div>
                            <button v-if="currentWeekSaved" disabled class="mt-4 px-5 py-2.5 bg-emerald-100 text-emerald-700 rounded-xl text-xs font-bold inline-flex items-center gap-2 cursor-not-allowed"><Lock class="w-4 h-4" /> Saved — Locked</button>
                            <button v-else-if="terminatedEarly" disabled class="mt-4 px-5 py-2.5 bg-red-100 text-red-700 rounded-xl text-xs font-bold inline-flex items-center gap-2 cursor-not-allowed"><Lock class="w-4 h-4" /> Terminated — Locked</button>
                            <button v-else @click="saveWeekly" :disabled="saving" class="mt-4 px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold disabled:opacity-50">[ Save Evaluation ]</button>
                        </section>

                        <!-- Final Evaluation -->
                        <section class="border-2 border-blue-100 rounded-xl p-4 bg-blue-50/50">
                            <div v-if="finalSubmitted" class="mb-3 bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-xs text-emerald-800 flex items-center gap-2">
                                <Lock class="w-4 h-4 shrink-0" /><span><b>Final evaluation submitted — locked.</b> This evaluation can no longer be modified. HR reviews the evaluation and decides (Approve Regularization / Extend Training / Do Not Regularize).</span>
                            </div>
                            <div v-else-if="selected.final_score !== null && selected.final_score !== undefined" class="mb-3 bg-blue-50 border border-blue-200 rounded-xl p-3 text-xs text-blue-800">
                                <b>Submitted — awaiting HR review.</b> HR reviews the evaluation and decides (Approve Regularization / Extend Training / Do Not Regularize).
                            </div>
                            <div v-if="selected.final_due && weeklyComplete && (selected.final_score === null || selected.final_score === undefined)" class="mb-3 bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs">
                                <b>Final Evaluation Required</b> — {{ selected.name }}'s training period has ended. Please complete the final evaluation.
                            </div>
                            <div v-if="terminatedEarly" class="mb-3 bg-red-50 border border-red-200 rounded-xl p-3 text-xs text-red-800 flex items-center gap-2">
                                <Lock class="w-4 h-4 shrink-0" /><span><b>No final evaluation</b> — training was terminated early (Week {{ terminationWeek }}). Remaining training is cancelled.</span>
                            </div>
                            <div v-if="readyForFinal && !finalSubmitted" class="mb-3 bg-blue-50 border border-blue-200 rounded-xl p-3 text-xs text-blue-800">
                                <b>Ready for Final Evaluation</b> — marked ready in Week {{ readyForFinalWeek }}. Remaining training days are auto-passed; {{ selected.name }} no longer needs to attend training.
                            </div>
                            <div v-if="selected.final_score !== null && selected.final_score !== undefined" class="mt-2 text-[11px] text-slate-500">Saved final: <b>{{ selected.final_score }}/100</b><span v-if="selected.manager_recommendation"> · {{ selected.manager_recommendation }}</span></div>
                            <button v-if="finalSubmitted" disabled class="mt-4 px-5 py-2.5 bg-emerald-100 text-emerald-700 rounded-xl text-xs font-bold inline-flex items-center gap-2 cursor-not-allowed"><Lock class="w-4 h-4" /> Submitted — Locked</button>
                            <button v-else-if="terminatedEarly" disabled class="mt-4 px-5 py-2.5 bg-red-100 text-red-700 rounded-xl text-xs font-bold inline-flex items-center gap-2 cursor-not-allowed"><Lock class="w-4 h-4" /> No Final Evaluation — Terminated</button>
                            <button v-else-if="finalUnlocked" @click="showFinalModal = true" class="mt-4 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold">[ Open Final Evaluation ]</button>
                        </section>
                    </div>

                    <!-- Weekly confirm: saving locks the week permanently -->
                    <div v-if="showWeeklyConfirm && selected" class="fixed inset-0 z-[80] flex items-center justify-center p-4">
                        <div class="absolute inset-0 bg-slate-900/50" @click="showWeeklyConfirm = false"></div>
                        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-5">
                            <h3 class="font-black text-slate-900">Submit Week {{ weeklyForm.week_number }} Evaluation?</h3>
                            <p class="mt-2 text-xs text-slate-600 leading-relaxed">Overall <b>{{ weeklyOverall }}/5</b> · Recommendation <b>{{ weeklyRecLabel(weeklyForm.recommendation) }}</b> will be saved for <b>{{ selected.name }}</b>. Once submitted, <b>this week cannot be modified</b>.</p>
                            <p v-if="weeklyForm.recommendation === 'early_termination'" class="mt-2 text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2 leading-relaxed"><b>Early Termination:</b> no final evaluation will happen and the remaining training will be automatically cancelled.</p>
                            <p v-if="weeklyForm.recommendation === 'ready_final'" class="mt-2 text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2 leading-relaxed"><b>Ready for Final:</b> remaining training days are auto-passed and the Final Evaluation unlocks.</p>
                            <div class="mt-4 flex justify-end gap-2">
                                <button @click="showWeeklyConfirm = false" :disabled="saving" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold disabled:opacity-50">Cancel</button>
                                <button @click="confirmWeeklySave" :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold disabled:opacity-50">{{ saving ? 'Saving…' : 'Confirm & Save' }}</button>
                            </div>
                        </div>
                    </div>

                    <!-- Final Evaluation form modal (opened from the section or auto-opened after Ready-for-Final) -->
                    <div v-if="showFinalModal && selected" class="fixed inset-0 z-[75] flex items-center justify-center p-4">
                        <div class="absolute inset-0 bg-slate-900/50" @click="showFinalModal = false"></div>
                        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                            <div class="p-5 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white rounded-t-2xl">
                                <div>
                                    <h3 class="font-black text-slate-900">Final Evaluation — {{ selected.name }}</h3>
                                    <p class="text-[11px] text-slate-500 mt-0.5">Stars 1–5 per criterion · submitting locks it and sends it to HR review.</p>
                                </div>
                                <button @click="showFinalModal = false" :disabled="saving" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 disabled:opacity-50">✕</button>
                            </div>
                            <div class="p-5">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                                    <div v-for="c in finalCriteria" :key="c.key" class="border border-slate-200 rounded-xl px-3 py-2 bg-white">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold">{{ c.label }} <span class="text-slate-400 font-semibold">— {{ {attendance: 20, knowledge: 25, practical_skills: 30, work_behavior: 15, safety_compliance: 10}[c.key] }}%</span></span>
                                            <span class="text-[11px] text-slate-500">{{ starToScore(finalForm[c.key]) }}/100</span>
                                        </div>
                                        <div class="flex items-center gap-1 mt-1.5">
                                            <button v-for="s in [1,2,3,4,5]" :key="s" type="button" @click="setFinalStars(c.key, s)" :title="s + ' star' + (s > 1 ? 's' : '')" class="transition-all hover:scale-110">
                                                <Star :class="['w-6 h-6', s <= Number(finalForm[c.key] || 0) ? 'text-amber-400 fill-amber-400' : 'text-slate-300']" />
                                            </button>
                                            <span class="ml-1 text-[11px] font-bold text-slate-600">{{ finalForm[c.key] }}/5</span>
                                        </div>
                                    </div>
                                    <div class="flex items-end"><p class="font-black">Final Score: {{ finalScore }} / 100</p></div>
                                </div>
                                <textarea v-model="finalForm.notes" placeholder="Final notes" class="mt-3 w-full px-3 py-2 border border-slate-200 rounded-xl text-xs" rows="2"></textarea>
                                <div class="mt-3 text-xs font-bold">Manager Recommendation</div>
                                <div class="flex flex-wrap gap-4 mt-1 text-xs">
                                    <label class="flex items-center gap-1.5"><input v-model="finalForm.recommendation" type="radio" value="regularization" /> Recommend for Regularization</label>
                                    <label class="flex items-center gap-1.5"><input v-model="finalForm.recommendation" type="radio" value="reassigned" /> Reassigned</label>
                                    <label class="flex items-center gap-1.5"><input v-model="finalForm.recommendation" type="radio" value="do_not_recommend" /> Do Not Recommend</label>
                                </div>
                            </div>
                            <div class="px-5 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2 rounded-b-2xl">
                                <button @click="showFinalModal = false" :disabled="saving" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold disabled:opacity-50">Cancel</button>
                                <button @click="submitFinal" :disabled="saving" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold disabled:opacity-50">{{ saving ? 'Working…' : '[ Submit the Evaluation ]' }}</button>
                            </div>
                        </div>
                    </div>

                    <!-- Final submit confirmation modal -->
                    <div v-if="showFinalConfirm && selected" class="fixed inset-0 z-[80] flex items-center justify-center p-4">
                        <div class="absolute inset-0 bg-slate-900/50" @click="showFinalConfirm = false"></div>
                        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-5">
                            <h3 class="font-black text-slate-900">Submit Final Evaluation?</h3>
                            <p class="mt-2 text-xs text-slate-600 leading-relaxed">Final score <b>{{ finalScore }}/100</b> will be submitted for <b>{{ selected.name }}</b>. Once submitted, <b>it cannot be modified</b> and goes to HR review.</p>
                            <div class="mt-4 flex justify-end gap-2">
                                <button @click="showFinalConfirm = false" :disabled="saving" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold disabled:opacity-50">Cancel</button>
                                <button @click="confirmFinalSubmit" :disabled="saving" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold disabled:opacity-50">{{ saving ? 'Submitting…' : 'Confirm & Submit' }}</button>
                            </div>
                        </div>
                    </div>

                    <!-- Final submitted confirmation modal -->
                    <div v-if="showFinalDoneModal && selected" class="fixed inset-0 z-[85] flex items-center justify-center p-4">
                        <div class="absolute inset-0 bg-slate-900/50" @click="showFinalDoneModal = false"></div>
                        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-5 text-center">
                            <div class="mx-auto w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-2xl font-black">✓</div>
                            <h3 class="mt-3 font-black text-slate-900">Final Evaluation Submitted</h3>
                            <p class="mt-2 text-xs text-slate-600 leading-relaxed"><b>{{ selected.name }}</b> scored <b>{{ finalDoneScore }}/100</b>. The evaluation is now locked and sent to HR review (Approve Regularization / Extend Training / Do Not Regularize).</p>
                            <div class="mt-4 flex justify-center">
                                <button @click="showFinalDoneModal = false" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold">Done</button>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Details modal (side-by-side with Daily Observation) -->
                    <div v-if="showAttendanceModal && selected" class="fixed inset-0 z-[70] overflow-auto p-4">
                        <div class="fixed inset-0 bg-slate-900/50" @click="showAttendanceModal = false; closeObservation()"></div>
                        <div class="relative flex flex-col xl:flex-row gap-4 items-stretch xl:items-start justify-center w-full xl:w-max max-w-7xl mx-auto my-auto">
                            <div :class="['bg-white rounded-2xl shadow-2xl overflow-hidden max-h-[90vh] overflow-y-auto transition-all duration-300 shrink-0', showObservationModal ? 'w-full xl:w-[600px]' : 'w-full max-w-2xl mx-auto']">
                            <div class="p-5 border-b border-slate-100">
                                <h3 class="font-black text-slate-900">Attendance Details — Week {{ weeklyForm.week_number }}</h3>
                                <p class="text-[11px] text-slate-500 mt-0.5">{{ weekRangeLabel(weeklyForm.week_number) }} · {{ selected.name }}</p>
                            </div>
                            <div class="p-5 space-y-4">
                                <div class="border border-slate-200 rounded-xl overflow-hidden text-xs">
                                    <div class="grid grid-cols-2 border-b border-slate-100"><span class="px-3 py-2 text-slate-400 bg-slate-50">Scheduled Days</span><span class="px-3 py-2 font-black text-right">{{ weekAttendanceSummary.scheduled }}</span></div>
                                    <div class="grid grid-cols-2 border-b border-slate-100"><span class="px-3 py-2 text-slate-400 bg-slate-50">Present</span><span class="px-3 py-2 font-black text-right text-emerald-600">{{ weekAttendanceSummary.present }}</span></div>
                                    <div class="grid grid-cols-2 border-b border-slate-100"><span class="px-3 py-2 text-slate-400 bg-slate-50">Late</span><span class="px-3 py-2 font-black text-right text-amber-600">{{ weekAttendanceSummary.late }}</span></div>
                                    <div class="grid grid-cols-2"><span class="px-3 py-2 text-slate-400 bg-slate-50">Absent</span><span class="px-3 py-2 font-black text-right text-rose-600">{{ weekAttendanceSummary.absent }}</span></div>
                                </div>
                                <div class="overflow-x-auto border border-slate-200 rounded-xl">
                                    <table class="w-full text-xs">
                                        <thead class="bg-slate-50 text-slate-500 uppercase text-[10px]">
                                            <tr>
                                                <th class="px-3 py-2 text-left">Date</th>
                                                <th class="px-3 py-2 text-left">Day</th>
                                                <th class="px-3 py-2 text-left">Time In</th>
                                                <th class="px-3 py-2 text-left">Time Out</th>
                                                <th class="px-3 py-2 text-left">Status</th>
                                                <th class="px-3 py-2 text-left">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(d, idx) in weekAttendanceDays" :key="idx" class="border-t border-slate-100">
                                                <td class="px-3 py-2 font-bold text-slate-800 whitespace-nowrap">{{ d.dateLabel }}</td>
                                                <td class="px-3 py-2 text-slate-500">{{ d.dayLabel }}</td>
                                                <td class="px-3 py-2">{{ d.timeIn }}</td>
                                                <td class="px-3 py-2">{{ d.timeOut }}</td>
                                                <td class="px-3 py-2"><span :class="['px-2 py-0.5 rounded-full font-bold text-[10px]', attStatusBadge(d.status)]">{{ d.status }}</span></td>
                                                <td class="px-3 py-2">
                                                    <button type="button" @click="openObservation(d)" class="inline-flex items-center gap-1 px-2.5 py-1 bg-white border border-slate-200 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 text-slate-600 rounded-lg text-[10px] font-bold transition">
                                                        <Eye class="w-3.5 h-3.5" /> Observation<span v-if="hasObservation(d)" class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p v-if="!(selected.attendance?.recent || []).length" class="text-[11px] text-slate-400 text-center">No attendance logs recorded for this trainee yet.</p>
                            </div>
                            <div class="p-4 border-t border-slate-100 bg-slate-50 flex justify-end">
                                <button @click="showAttendanceModal = false; closeObservation()" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 rounded-xl text-xs font-bold transition">Close</button>
                            </div>
                        </div>

                            <!-- Daily Observation panel (beside, not on top) -->
                            <div v-if="showObservationModal && selectedDay" class="w-full xl:w-[600px] shrink-0 bg-white rounded-2xl shadow-2xl max-h-[90vh] overflow-y-auto transition-all duration-300">
                                <div class="p-5 border-b border-slate-100 flex items-start justify-between">
                                    <div>
                                        <h3 class="font-black text-slate-900">Daily Observation — {{ selectedDay.dateLabel }} ({{ selectedDay.dayLabel }})</h3>
                                        <p class="text-[11px] text-slate-500 mt-0.5">{{ weekRangeLabel(weeklyForm.week_number) }} · {{ selected.name }} · <span :class="['px-2 py-0.5 rounded-full font-bold text-[10px]', attStatusBadge(selectedDay.status)]">{{ selectedDay.status }}</span> · In {{ selectedDay.timeIn }} / Out {{ selectedDay.timeOut }}</p>
                                    </div>
                                    <button @click="closeObservation" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400"><X class="w-4 h-4" /></button>
                                </div>
                                <div class="p-5 space-y-3">
                                    <div v-for="c in [{key:'knowledge',label:'Knowledge & Learning'},{key:'practical_skills',label:'Practical Skills'},{key:'work_behavior',label:'Work Behavior'},{key:'safety_compliance',label:'Safety Compliance'}]" :key="c.key" class="border border-slate-200 rounded-xl p-3">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-xs font-black text-slate-700">{{ c.label }}</span>
                                            <select v-model.number="observationForm[c.key]" class="w-24 px-2 py-1.5 border border-slate-200 rounded-lg text-xs">
                                                <option :value="1">1 / 5</option><option :value="2">2 / 5</option><option :value="3">3 / 5</option><option :value="4">4 / 5</option><option :value="5">5 / 5</option>
                                            </select>
                                        </div>
                                        <textarea v-model="observationForm[c.key + '_notes']" :placeholder="c.label + ' notes'" class="mt-2 w-full px-3 py-2 border border-slate-200 rounded-xl text-xs" rows="2"></textarea>
                                    </div>
                                    <div>
                                        <div class="text-xs font-black text-slate-700 mb-1">Overall Notes</div>
                                        <textarea v-model="observationForm.notes" placeholder="Overall observation notes for this day" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs" rows="2"></textarea>
                                    </div>
                                </div>
                                <div class="p-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-2">
                                    <button @click="closeObservation" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 rounded-xl text-xs font-bold transition">Close</button>
                                    <button @click="saveObservation" :disabled="savingObservation" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold disabled:opacity-50 transition">Save Observation</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
table th { white-space: nowrap; }
.week-strip { scrollbar-width: thin; -webkit-overflow-scrolling: touch; }
.week-strip::-webkit-scrollbar { height: 6px; }
.week-strip::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
</style>
