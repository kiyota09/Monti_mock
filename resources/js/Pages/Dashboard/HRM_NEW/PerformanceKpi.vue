<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { BarChart3, CheckCircle2, Clock3, Search, ShieldCheck, UserRoundCheck, Users } from 'lucide-vue-next';

const props = defineProps({ employees: { type: Array, default: () => [] }, evaluations: { type: Array, default: () => [] }, criteria: { type: Object, default: () => ({}) }, metrics: { type: Object, default: () => ({}) } });
const activeTab = ref('employees');
const search = ref('');
const employeeFilter = ref('all');
const showForm = ref(false);
const saving = ref(false);
const error = ref('');
const form = ref({ id: null, user_id: '', period_start: '', period_end: '', due_date: '', scores: {}, comments: '', strengths: '', improvements: '', recommendation: 'Further review', review_notes: '' });
const criterionEntries = computed(() => Object.entries(props.criteria));
const score = computed(() => {
  const values = criterionEntries.value.map(([key]) => Number(form.value.scores[key] || 0));
  return values.length ? (values.reduce((sum, value) => sum + value, 0) / values.length * 20).toFixed(1) : '0.0';
});
const cards = computed(() => [
  { label: 'Active probationary employees', value: props.metrics.employees || 0, icon: Users, color: 'blue' },
  { label: 'Open evaluations', value: props.metrics.pending || 0, icon: Clock3, color: 'amber' },
  { label: 'Awaiting HR review', value: props.metrics.forReview || 0, icon: ShieldCheck, color: 'violet' },
  { label: 'Finalized evaluations', value: props.metrics.finalized || 0, icon: CheckCircle2, color: 'emerald' },
]);
const date = value => value ? String(value).slice(0, 10) : '—';
const employeeName = evaluation => evaluation.employee?.name || 'Employee';
const dayCount = value => {
  if (!value) return null;
  const target = new Date(`${String(value).slice(0, 10)}T00:00:00`);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return Math.ceil((target - today) / 86400000);
};
const latestEvaluation = employeeId => props.evaluations
  .filter(item => Number(item.user_id) === Number(employeeId))
  .sort((a, b) => String(b.period_end || '').localeCompare(String(a.period_end || '')))[0] || null;
const probationState = employee => {
  const days = dayCount(employee.probation_end_date);
  if (days === null) return { label: 'End date not set', tone: 'slate' };
  if (days < 0) return { label: `Overdue ${Math.abs(days)}d`, tone: 'rose' };
  if (days <= 30) return { label: `Due in ${days}d`, tone: 'amber' };
  return { label: 'In progress', tone: 'emerald' };
};
const probationProgress = employee => {
  const start = new Date(`${String(employee.join_date || '').slice(0, 10)}T00:00:00`).getTime();
  const end = new Date(`${String(employee.probation_end_date || '').slice(0, 10)}T00:00:00`).getTime();
  if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start) return 0;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return Math.max(0, Math.min(100, Math.round(((today.getTime() - start) / (end - start)) * 100)));
};
const filteredEmployees = computed(() => props.employees.filter(employee => {
  const state = probationState(employee).label;
  const latest = latestEvaluation(employee.id);
  const text = `${employee.name || ''} ${employee.employee_id || ''} ${employee.hrm_department?.name || ''} ${employee.hrm_org_position?.name || ''}`.toLowerCase();
  const matchesSearch = text.includes(search.value.toLowerCase());
  const matchesFilter = employeeFilter.value === 'all'
    || (employeeFilter.value === 'due' && (state.startsWith('Due') || state.startsWith('Overdue')))
    || (employeeFilter.value === 'no_evaluation' && !latest)
    || (employeeFilter.value === 'awaiting_review' && latest?.status === 'Submitted');
  return matchesSearch && matchesFilter;
}));
const dueEmployees = computed(() => props.employees.filter(employee => {
  const days = dayCount(employee.probation_end_date);
  return days !== null && days <= 30;
}).length);
const employeesWithoutEvaluation = computed(() => props.employees.filter(employee => !latestEvaluation(employee.id)).length);
function openNew() {
  error.value = '';
  form.value = { id: null, user_id: '', period_start: '', period_end: '', due_date: '', scores: Object.fromEntries(criterionEntries.value.map(([key]) => [key, ''])), comments: '', strengths: '', improvements: '', recommendation: 'Further review', review_notes: '' };
  showForm.value = true;
}
function openNewFor(employee) {
  openNew();
  form.value.user_id = employee.id;
  form.value.period_start = String(employee.join_date || '').slice(0, 10);
  form.value.period_end = String(employee.probation_end_date || '').slice(0, 10);
  form.value.due_date = form.value.period_end;
}
function openEdit(evaluation) {
  error.value = '';
  form.value = { id: evaluation.id, user_id: evaluation.user_id, period_start: date(evaluation.period_start), period_end: date(evaluation.period_end), due_date: date(evaluation.due_date) === '—' ? '' : date(evaluation.due_date), scores: { ...(evaluation.scores || {}) }, comments: evaluation.comments || '', strengths: evaluation.strengths || '', improvements: evaluation.improvements || '', recommendation: evaluation.recommendation || 'Further review', review_notes: '' };
  showForm.value = true;
}
async function createEvaluation() {
  error.value = '';
  saving.value = true;
  try {
    const response = await axios.post('/dashboard/hrm/workforce/performance-kpi/evaluations', { user_id: form.value.user_id, period_start: form.value.period_start, period_end: form.value.period_end, due_date: form.value.due_date || null });
    form.value.id = response.data.evaluation.id;
    await saveEvaluation(false);
  } catch (e) { error.value = e.response?.data?.message || 'Could not create the evaluation.'; }
  finally { saving.value = false; }
}
async function saveEvaluation(submit) {
  error.value = '';
  saving.value = true;
  try {
    if (!form.value.id) return await createEvaluation();
    await axios.put(`/dashboard/hrm/workforce/performance-kpi/evaluations/${form.value.id}`, { scores: form.value.scores, comments: form.value.comments, strengths: form.value.strengths, improvements: form.value.improvements, recommendation: form.value.recommendation, submit });
    showForm.value = false;
    router.reload({ only: ['evaluations', 'metrics'] });
  } catch (e) { error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {}).flat()[0] || 'Could not save the evaluation.'; }
  finally { saving.value = false; }
}
async function reviewEvaluation(evaluation, decision) {
  const notes = window.prompt(decision === 'Finalize' ? 'HR review notes (optional):' : 'Reason for returning this evaluation:');
  if (notes === null) return;
  try {
    await axios.post(`/dashboard/hrm/workforce/performance-kpi/evaluations/${evaluation.id}/review`, { decision, review_notes: notes });
    router.reload({ only: ['evaluations', 'metrics', 'employees'] });
  } catch (e) { window.alert(e.response?.data?.message || 'Could not update the evaluation review.'); }
}
</script>

<template>
  <AuthenticatedLayout>
    <main class="min-h-screen bg-slate-50 p-4 text-slate-900 sm:p-7">
      <div class="mx-auto max-w-screen-2xl space-y-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
          <div><p class="text-xs font-semibold uppercase tracking-widest text-blue-700">HRM / Workforce</p><h1 class="mt-1 text-2xl font-bold sm:text-3xl">Probation Management</h1><p class="mt-2 text-sm text-slate-600">Monitor probation periods, complete performance reviews, and record HR’s final decision.</p></div>
          <button @click="activeTab = 'employees'; openNew()" :disabled="!employees.length" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"><UserRoundCheck class="mr-2 inline h-4 w-4" />Start evaluation</button>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <article v-for="card in cards" :key="card.label" class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5">
            <div class="rounded-xl p-3" :class="{ 'bg-blue-50 text-blue-700': card.color === 'blue', 'bg-amber-50 text-amber-700': card.color === 'amber', 'bg-violet-50 text-violet-700': card.color === 'violet', 'bg-emerald-50 text-emerald-700': card.color === 'emerald' }"><component :is="card.icon" class="h-5 w-5" /></div>
            <div><p class="text-sm text-slate-500">{{ card.label }}</p><p class="text-2xl font-bold">{{ card.value }}</p></div>
          </article>
        </section>

        <nav class="flex gap-2 border-b border-slate-200" aria-label="Probation management sections">
          <button v-for="tab in [{ id: 'employees', label: 'Probation roster', icon: Users }, { id: 'evaluations', label: 'Review queue', icon: UserRoundCheck }, { id: 'history', label: 'Completed reviews', icon: BarChart3 }]" :key="tab.id" @click="activeTab = tab.id" class="inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold" :class="activeTab === tab.id ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500'"><component :is="tab.icon" class="h-4 w-4" />{{ tab.label }}<span v-if="tab.id === 'evaluations' && metrics.forReview" class="rounded-full bg-violet-100 px-2 py-0.5 text-[11px] text-violet-700">{{ metrics.forReview }}</span></button>
        </nav>

        <section v-if="activeTab === 'employees'" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
          <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 p-5">
            <div><h2 class="font-bold">Active probation roster</h2><p class="mt-1 text-sm text-slate-500">Track each employee’s probation timeline and latest evaluation.</p></div>
            <div class="flex flex-wrap gap-2 text-xs"><span class="rounded-lg bg-amber-50 px-3 py-2 font-semibold text-amber-800">{{ dueEmployees }} due within 30 days or overdue</span><span class="rounded-lg bg-blue-50 px-3 py-2 font-semibold text-blue-800">{{ employeesWithoutEvaluation }} without an evaluation</span></div>
          </div>
          <div class="flex flex-wrap gap-3 border-b border-slate-100 p-4">
            <label class="relative min-w-[220px] flex-1"><Search class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" /><input v-model="search" type="search" placeholder="Search name, ID, department or position" class="w-full rounded-lg border-slate-200 py-2 pl-9 pr-3 text-sm focus:border-blue-400 focus:ring-blue-400" /></label>
            <select v-model="employeeFilter" class="rounded-lg border-slate-200 py-2 text-sm"><option value="all">All probationary employees</option><option value="due">Due soon or overdue</option><option value="no_evaluation">No evaluation started</option><option value="awaiting_review">Awaiting HR review</option></select>
          </div>
          <div class="overflow-x-auto"><table class="w-full min-w-[1050px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="p-4">Employee</th><th class="p-4">Department / Position</th><th class="p-4">Probation period</th><th class="p-4">Timeline</th><th class="p-4">Latest evaluation</th><th class="p-4 text-right">Next action</th></tr></thead><tbody class="divide-y divide-slate-100">
            <tr v-for="employee in filteredEmployees" :key="employee.id" class="hover:bg-slate-50/70">
              <td class="p-4"><div class="flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-50 font-bold text-blue-700">{{ (employee.name || 'E').slice(0, 1).toUpperCase() }}</div><div><strong>{{ employee.name }}</strong><p class="text-xs text-slate-500">{{ employee.employee_id || 'No employee ID' }}</p></div></div></td>
              <td class="p-4"><span class="font-medium">{{ employee.hrm_department?.name || 'Unassigned' }}</span><p class="text-xs text-slate-500">{{ employee.hrm_org_position?.name || 'No position' }}</p></td>
              <td class="p-4"><p>{{ date(employee.join_date) }} – {{ date(employee.probation_end_date) }}</p><p class="mt-1 text-xs text-slate-500">Probation end date</p></td>
              <td class="p-4"><div class="mb-2 flex items-center justify-between gap-2"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="{ 'bg-rose-50 text-rose-700': probationState(employee).tone === 'rose', 'bg-amber-50 text-amber-800': probationState(employee).tone === 'amber', 'bg-emerald-50 text-emerald-700': probationState(employee).tone === 'emerald', 'bg-slate-100 text-slate-600': probationState(employee).tone === 'slate' }">{{ probationState(employee).label }}</span><span class="text-xs text-slate-500">{{ probationProgress(employee) }}%</span></div><div class="h-1.5 w-32 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-blue-600" :style="{ width: `${probationProgress(employee)}%` }" /></div></td>
              <td class="p-4"><template v-if="latestEvaluation(employee.id)"><span class="font-medium">{{ latestEvaluation(employee.id).status }}</span><p class="text-xs text-slate-500">{{ latestEvaluation(employee.id).overall_score == null ? 'Not scored yet' : `${Number(latestEvaluation(employee.id).overall_score).toFixed(1)}%` }}<span v-if="latestEvaluation(employee.id).recommendation"> · {{ latestEvaluation(employee.id).recommendation }}</span></p></template><span v-else class="text-slate-500">Not started</span></td>
              <td class="p-4 text-right"><button v-if="!latestEvaluation(employee.id) || latestEvaluation(employee.id).status === 'Draft'" @click="latestEvaluation(employee.id)?.status === 'Draft' ? openEdit(latestEvaluation(employee.id)) : openNewFor(employee)" class="rounded-lg border border-blue-200 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-50">{{ latestEvaluation(employee.id)?.status === 'Draft' ? 'Continue review' : 'Start review' }}</button><button v-else-if="latestEvaluation(employee.id).status === 'Submitted'" @click="activeTab = 'evaluations'" class="rounded-lg border border-violet-200 px-3 py-2 text-xs font-semibold text-violet-700 hover:bg-violet-50">Review submitted</button><span v-else class="text-xs font-medium text-slate-500">{{ latestEvaluation(employee.id).recommendation === 'Regularize' ? 'Regularized' : 'Review completed' }}</span></td>
            </tr>
            <tr v-if="!filteredEmployees.length"><td colspan="6" class="p-10 text-center text-slate-500">{{ employees.length ? 'No employees match your search or filter.' : 'No active employees currently have the Probationary employment type.' }}</td></tr>
          </tbody></table></div>
        </section>

        <section v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 p-5"><div><h2 class="font-bold">{{ activeTab === 'history' ? 'Completed review history' : 'Evaluation review queue' }}</h2><p class="mt-1 text-sm text-slate-500">Scores use an equal-weighted 1–5 scale, displayed as a percentage.</p></div><span class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-medium text-slate-600">{{ evaluations.filter(e => activeTab === 'history' ? e.status === 'Finalized' : e.status !== 'Finalized').length }} record(s)</span></div>
          <div class="overflow-x-auto"><table class="w-full min-w-[950px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="p-4">Employee</th><th class="p-4">Review period</th><th class="p-4">Due</th><th class="p-4 text-center">Score</th><th class="p-4">Recommendation</th><th class="p-4">Status</th><th class="p-4 text-right">HR action</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="item in evaluations.filter(e => activeTab === 'history' ? e.status === 'Finalized' : e.status !== 'Finalized')" :key="item.id"><td class="p-4"><strong>{{ employeeName(item) }}</strong><p class="text-xs text-slate-500">{{ item.employee?.hrm_department?.name || '—' }} · {{ item.employee?.hrm_org_position?.name || '—' }}</p></td><td class="p-4">{{ date(item.period_start) }} – {{ date(item.period_end) }}</td><td class="p-4">{{ date(item.due_date) }}</td><td class="p-4 text-center font-semibold">{{ item.overall_score == null ? '—' : `${Number(item.overall_score).toFixed(1)}%` }}</td><td class="p-4">{{ item.recommendation || '—' }}</td><td class="p-4"><span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ item.status }}</span></td><td class="space-x-2 p-4 text-right"><button v-if="item.status === 'Draft'" @click="openEdit(item)" class="font-semibold text-blue-700">Edit draft</button><template v-if="item.status === 'Submitted'"><button @click="reviewEvaluation(item, 'Return')" class="font-semibold text-amber-700">Return</button><button @click="reviewEvaluation(item, 'Finalize')" class="font-semibold text-emerald-700">{{ item.recommendation === 'Regularize' ? 'Finalize & regularize' : 'Finalize decision' }}</button></template><span v-if="item.status === 'Finalized'" class="text-xs text-slate-500">Finalized by HR</span></td></tr><tr v-if="!evaluations.filter(e => activeTab === 'history' ? e.status === 'Finalized' : e.status !== 'Finalized').length"><td colspan="7" class="p-10 text-center text-slate-500">No evaluations in this section yet.</td></tr></tbody></table></div>
        </section>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="showForm = false">
          <section role="dialog" aria-modal="true" class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl sm:p-7">
            <div class="mb-5 flex items-start justify-between"><div><h2 class="text-xl font-bold">{{ form.id ? 'Edit evaluation' : 'Start probation evaluation' }}</h2><p class="mt-1 text-sm text-slate-500">Rate each area from 1 (below expectations) to 5 (excellent).</p></div><button @click="showForm = false" class="text-sm font-semibold text-slate-500">Close</button></div>
            <div v-if="!form.id" class="mb-5 grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium">Employee<select v-model="form.user_id" class="mt-1 w-full rounded-lg border-slate-300"><option value="">Select probationary employee</option><option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }} · {{ employee.employee_id || 'No ID' }}</option></select></label><label class="text-sm font-medium">Evaluation due date<input v-model="form.due_date" type="date" class="mt-1 w-full rounded-lg border-slate-300" /></label><label class="text-sm font-medium">Period start<input v-model="form.period_start" type="date" class="mt-1 w-full rounded-lg border-slate-300" /></label><label class="text-sm font-medium">Period end<input v-model="form.period_end" type="date" class="mt-1 w-full rounded-lg border-slate-300" /></label></div>
            <div v-else class="mb-4 rounded-xl bg-slate-50 p-3 text-sm font-semibold">{{ employeeName(evaluations.find(e => e.id === form.id) || {}) }} · {{ form.period_start }} to {{ form.period_end }}</div>
            <div class="space-y-3"><label v-for="[key, label] in criterionEntries" :key="key" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-100 p-3"><span class="text-sm font-medium">{{ label }}</span><select v-model="form.scores[key]" class="w-36 rounded-lg border-slate-300 text-sm"><option value="">Choose score</option><option v-for="n in 5" :key="n" :value="n">{{ n }} / 5</option></select></label></div>
            <p class="mt-4 text-right text-sm font-semibold">Current score: {{ score }}%</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2"><label class="text-sm font-medium">Strengths<textarea v-model="form.strengths" rows="3" class="mt-1 w-full rounded-lg border-slate-300" /></label><label class="text-sm font-medium">Improvement areas<textarea v-model="form.improvements" rows="3" class="mt-1 w-full rounded-lg border-slate-300" /></label><label class="text-sm font-medium sm:col-span-2">Evaluator comments<textarea v-model="form.comments" rows="3" class="mt-1 w-full rounded-lg border-slate-300" /></label><label class="text-sm font-medium sm:col-span-2">Recommendation<select v-model="form.recommendation" class="mt-1 w-full rounded-lg border-slate-300"><option>Regularize</option><option>Further review</option><option>Do not regularize</option></select></label></div>
            <p v-if="error" class="mt-4 rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</p>
            <footer class="mt-6 flex flex-wrap justify-end gap-2"><button @click="showForm = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold">Cancel</button><button v-if="!form.id" @click="createEvaluation" :disabled="saving || !form.user_id || !form.period_start || !form.period_end" class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ saving ? 'Saving…' : 'Create draft' }}</button><template v-else><button @click="saveEvaluation(false)" :disabled="saving" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold">Save draft</button><button @click="saveEvaluation(true)" :disabled="saving" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ saving ? 'Saving…' : 'Submit to HR' }}</button></template></footer>
          </section>
        </div>
      </div>
    </main>
  </AuthenticatedLayout>
</template>
