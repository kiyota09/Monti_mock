<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Award, BarChart3, CheckCircle2, ClipboardList, Clock3, Download, FileText, Plus, ShieldCheck, Star, TrendingUp, Users, X } from 'lucide-vue-next';

const props = defineProps({ employees: { type: Array, default: () => [] }, evaluators: { type: Array, default: () => [] }, reviewManagers: { type: Array, default: () => [] }, evaluations: { type: Array, default: () => [] }, templates: { type: Array, default: () => [] }, plans: { type: Array, default: () => [] }, rules: { type: Array, default: () => [] }, departments: { type: Array, default: () => [] }, positions: { type: Array, default: () => [] }, metrics: { type: Object, default: () => ({}) }, filters: { type: Object, default: () => ({}) }, isHr: Boolean, templatesOnly: Boolean, kpiEvaluationsOnly: Boolean });
const sections = [
  { id: 'dashboard', label: 'KPI Dashboard', icon: BarChart3 },
  { id: 'evaluations', label: 'Employee Evaluations', icon: Award },
  { id: 'reviews', label: 'Manager & HR Reviews', icon: ShieldCheck },
  { id: 'history', label: 'Evaluation History', icon: Clock3 }, { id: 'reports', label: 'Reports & Analytics', icon: FileText },
  { id: 'promotions', label: 'Qualified Promotion', icon: TrendingUp },
];
const activeTab = ref(props.templatesOnly ? 'templates' : props.kpiEvaluationsOnly ? 'evaluations' : 'dashboard');
const authUser = usePage().props.auth?.user || {};
const templateItems = ref(props.templates);
watch(() => props.templates, value => { templateItems.value = value; });
const showAssignment = ref(false); const showTemplate = ref(false); const showEvaluation = ref(false); const showReview = ref(false); const selectedEvaluation = ref(null);
const error = ref(''); const busy = ref(false); const toast = ref('');
const assignment = ref({ user_id: '', template_id: '', evaluator_id: '', manager_id: '', period_start: '', period_end: '', due_date: '', assignment_notes: '' });
const templateForm = ref(blankTemplate());
const planForm = ref({ user_id: '', evaluation_id: '', supervisor_id: '', performance_gap: '', improvement_objective: '', required_actions: '', training_or_coaching: '', success_measurement: '', notes: '', start_date: '', target_date: '', review_date: '', status: 'Active' });
const filters = ref({ period: props.filters.period || '', department: props.filters.department || '', position: props.filters.position || '', employee: props.filters.employee || '', status: props.filters.status || '' });
const tablePage = ref(1); const pageSize = 12; const sortBy = ref('period_desc');
const todayDate = (() => { const now = new Date(); return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`; })();
const measurementMethods = [
  { value: 'Quantitative', description: 'Numerical results, percentages, and counts.' },
  { value: 'Qualitative', description: 'Work quality, behavior, and observations.' },
  { value: 'Quantitative and Qualitative', description: 'Combines numerical results and quality assessments.' },
  { value: 'Milestone-Based', description: 'Completion of assigned milestones and deliverables.' },
  { value: 'Target-Based', description: 'Progress against defined targets and goals.' },
];
const statusOptions = ['Assigned', 'In Progress', 'Submitted', 'Manager Review', 'HR Review', 'Returned for Revision', 'Completed', 'Cancelled'];
const classificationRules = ref(props.rules.map(r => ({ minimum_score: Number(r.minimum_score), maximum_score: Number(r.maximum_score), label: r.label })));
const ratingForm = ref({ ratings: {}, comments: '', strengths: '', development_recommendations: '', recommendation: '' });
const reviewForm = ref({ decision: '', comments: '' });
const hrChecklist = ref({ evidenceReviewed: false, scoreVerified: false });
const hrChecklistItems = computed(() => {
  const evaluation = selectedEvaluation.value;
  const ratings = evaluation?.ratings || [];
  return [
    { label: 'All KPI criteria rated', complete: ratings.length > 0 && ratings.every(rating => rating.rating !== null && rating.rating !== '') },
    { label: 'Required comments provided', complete: Boolean(String(evaluation?.evaluator_comments || '').trim()) && ratings.every(rating => Boolean(String(rating.comments || '').trim())) },
    { label: 'Supporting evidence reviewed', key: 'evidenceReviewed', complete: hrChecklist.value.evidenceReviewed },
    { label: 'Weighted score verified', key: 'scoreVerified', complete: hrChecklist.value.scoreVerified },
    { label: 'Manager review completed', complete: evaluation?.status === 'HR Review' },
  ];
});
const hrChecklistComplete = computed(() => hrChecklistItems.value.every(item => item.complete));
const selectedEmployee = computed(() => props.employees.find(e => Number(e.id) === Number(assignment.value.user_id)));
const evaluatorOptions = computed(() => props.evaluators.filter(user => Number(user.hrm_department_id) === Number(selectedEmployee.value?.hrm_department_id)));
const evaluatorDepartmentName = computed(() => props.departments.find(item => Number(item.id) === Number(selectedEmployee.value?.hrm_department_id))?.name || selectedEmployee.value?.hrmDepartment?.name || selectedEmployee.value?.hrm_department?.name || 'employee department');
const templatePositions = computed(() => templateForm.value.department_id
  ? props.positions.filter(position => String(position.department_id) === String(templateForm.value.department_id))
  : []);
const selectedAssignmentTemplate = computed(() => templateItems.value.find(template => Number(template.id) === Number(assignment.value.template_id)) || null);
function templateForEmployee(employee) {
  const today = new Date().toISOString().slice(0, 10);
  return templateItems.value
    .filter(template => template.status === 'active'
      && Number(template.position_id) === Number(employee.hrm_position_id)
      && (!template.department_id || Number(template.department_id) === Number(employee.hrm_department_id))
      && (!template.effective_date || String(template.effective_date).slice(0, 10) <= today))
    .sort((a, b) => Number(Boolean(b.department_id)) - Number(Boolean(a.department_id)))[0] || null;
}
function activeEvaluationForEmployee(employee) {
  const template = templateForEmployee(employee);
  return template ? props.evaluations.find(evaluation => Number(evaluation.user_id) === Number(employee.id)
    && Number(evaluation.template_id) === Number(template.id)
    && !['Completed', 'Cancelled'].includes(evaluation.status)) || null : null;
}
function hasActiveAssignment(employee) {
  return Boolean(activeEvaluationForEmployee(employee));
}
const criteriaInForm = computed(() => templateItems.value.find(t => Number(t.id) === Number(assignment.value.template_id))?.criteria || []);
const evaluationScore = computed(() => {
  const criteria = selectedEvaluation.value?.ratings || [];
  const score = criteria.reduce((sum, criterion) => sum + (Number(ratingForm.value.ratings[criterion.id]?.rating || 0) / 5) * Number(criterion.weight), 0);
  return score.toFixed(2);
});
const evaluationCompletion = computed(() => {
  const criteria = selectedEvaluation.value?.ratings || [];
  if (!criteria.length) return 0;
  return Math.round(criteria.filter(c => ratingForm.value.ratings[c.id]?.rating).length / criteria.length * 100);
});
const filteredEvaluations = computed(() => props.evaluations.filter(e => {
  const term = String(filters.value.employee || '').trim().toLowerCase();
  const employeeMatches = !term || String(e.employee_name || '').toLowerCase().includes(term) || String(e.employee_id || '').toLowerCase().includes(term);
  const periodMatches = !filters.value.period || String(e.period_start || '').slice(0, 7) === String(filters.value.period).slice(0, 7);
  return employeeMatches && periodMatches && (!filters.value.department || String(e.hrm_department_id) === String(filters.value.department)) && (!filters.value.position || String(e.hrm_position_id) === String(filters.value.position)) && (!filters.value.status || e.status === filters.value.status);
}));
const filteredEmployees = computed(() => props.employees.filter(employee => {
  const term = String(filters.value.employee || '').trim().toLowerCase();
  const matchesEmployee = !term || String(employee.name || '').toLowerCase().includes(term) || String(employee.employee_id || '').toLowerCase().includes(term);
  return matchesEmployee
    && (!filters.value.department || String(employee.hrm_department_id) === String(filters.value.department))
    && (!filters.value.position || String(employee.hrm_position_id) === String(filters.value.position));
}));
const tableRows = computed(() => {
  const rows = filteredEvaluations.value.filter(e => activeTab.value === 'history' ? e.status === 'Completed' : activeTab.value === 'reviews' ? ['Submitted','HR Review'].includes(e.status) : activeTab.value === 'evaluations' ? !['Completed','Cancelled'].includes(e.status) : true);
  return rows.sort((a,b) => sortBy.value === 'employee_asc' ? String(a.employee_name).localeCompare(String(b.employee_name)) : sortBy.value === 'score_desc' ? Number(b.overall_score || 0) - Number(a.overall_score || 0) : String(b.period_end || '').localeCompare(String(a.period_end || '')));
});
const pageCount = computed(() => Math.max(1, Math.ceil(tableRows.value.length / pageSize)));
const pagedEvaluations = computed(() => tableRows.value.slice((tablePage.value - 1) * pageSize, tablePage.value * pageSize));
watch([activeTab, filters, sortBy], () => { tablePage.value = 1; }, { deep: true });
const bars = computed(() => props.departments.map(d => {
  const rows = props.evaluations.filter(e => Number(e.hrm_department_id) === Number(d.id) && e.status === 'Completed' && e.overall_score !== null);
  return { name: d.name, count: rows.length, score: rows.length ? rows.reduce((s, r) => s + Number(r.overall_score), 0) / rows.length : 0 };
}));
const ratingDistribution = computed(() => ['Outstanding', 'Exceeds Expectations', 'Meets Expectations', 'Needs Improvement', 'Unsatisfactory'].map(label => ({ label, count: props.evaluations.filter(e => e.status === 'Completed' && e.classification === label).length })));
const promotionThreshold = computed(() => {
  const qualifyingBands = classificationRules.value.filter(rule => ['outstanding', 'exceeds expectations'].includes(String(rule.label).trim().toLowerCase()));
  return qualifyingBands.length ? Math.min(...qualifyingBands.map(rule => Number(rule.minimum_score))) : 80;
});
const promotionWindowStart = computed(() => {
  return `${todayDate.slice(0, 4)}-01-01`;
});
const promotionCandidates = computed(() => {
  const scoresByEmployee = new Map();
  const overallEvaluationCounts = new Map();
  for (const evaluation of props.evaluations) {
    const submittedDate = String(evaluation.submitted_at || '').slice(0, 10);
    const scoreWasSubmittedThisYear = ['Submitted', 'HR Review', 'Completed'].includes(evaluation.status)
      && evaluation.overall_score != null
      && submittedDate >= promotionWindowStart.value
      && submittedDate <= todayDate;
    if (scoreWasSubmittedThisYear) {
      const employeeId = Number(evaluation.user_id);
      overallEvaluationCounts.set(employeeId, (overallEvaluationCounts.get(employeeId) || 0) + 1);
    }
  }
  const managerSubmittedScores = props.evaluations
    .filter(evaluation => ['Submitted', 'HR Review', 'Completed'].includes(evaluation.status)
      && evaluation.overall_score != null
      && String(evaluation.submitted_at || '').slice(0, 10) >= promotionWindowStart.value
      && String(evaluation.submitted_at || '').slice(0, 10) <= todayDate)
    .sort((a, b) => String(b.submitted_at || '').localeCompare(String(a.submitted_at || '')));
  for (const evaluation of managerSubmittedScores) {
    const employeeId = Number(evaluation.user_id);
    const aggregate = scoresByEmployee.get(employeeId) || { latest: evaluation, scoreTotal: 0, evaluationCount: 0, quarterTotals: [0, 0, 0, 0], quarterCounts: [0, 0, 0, 0] };
    const submittedMonth = Number(String(evaluation.submitted_at).slice(5, 7));
    const quarterIndex = Math.min(3, Math.max(0, Math.floor((submittedMonth - 1) / 3)));
    aggregate.scoreTotal += Number(evaluation.overall_score);
    aggregate.evaluationCount += 1;
    aggregate.quarterTotals[quarterIndex] += Number(evaluation.overall_score);
    aggregate.quarterCounts[quarterIndex] += 1;
    scoresByEmployee.set(employeeId, aggregate);
  }
  return props.employees.map(employee => {
    const aggregate = scoresByEmployee.get(Number(employee.id));
    const average = aggregate ? aggregate.scoreTotal / 4 : null;
    const latest = aggregate?.latest || null;
    const qualified = average !== null && average >= promotionThreshold.value;
    return {
      ...latest,
      id: employee.id,
      user_id: employee.id,
      employee_name: employee.name,
      position_name: employee.hrmOrgPosition?.name || employee.hrm_org_position?.name || props.positions.find(position => Number(position.id) === Number(employee.hrm_position_id))?.name || null,
      department_name: employee.hrmDepartment?.name || employee.hrm_department?.name || props.departments.find(department => Number(department.id) === Number(employee.hrm_department_id))?.name || null,
      annual_average_score: average,
      annual_scored_evaluation_count: aggregate?.evaluationCount || 0,
      annual_evaluation_count: overallEvaluationCounts.get(Number(employee.id)) || 0,
      latest_manager_evaluation: latest,
      promotion_qualified: qualified,
      promotion_recommendation: qualified ? 'Qualified for promotion' : average === null ? 'No score history' : 'Not qualified',
    };
  }).sort((a, b) => Number(b.promotion_qualified) - Number(a.promotion_qualified)
    || (Number(b.annual_average_score || 0) - Number(a.annual_average_score || 0))
    || String(a.employee_name).localeCompare(String(b.employee_name)));
});
const qualifiedPromotionCount = computed(() => promotionCandidates.value.filter(employee => employee.promotion_qualified).length);
const monthlyTrends = computed(() => {
  const buckets = new Map();
  for (const evaluation of props.evaluations) {
    if (!evaluation.period_end) continue;
    const month = String(evaluation.period_end).slice(0, 7);
    const row = buckets.get(month) || { month, assigned: 0, completed: 0, scoreSum: 0, scoreCount: 0 };
    row.assigned += 1;
    if (evaluation.status === 'Completed') row.completed += 1;
    if (evaluation.status === 'Completed' && evaluation.overall_score != null) { row.scoreSum += Number(evaluation.overall_score); row.scoreCount += 1; }
    buckets.set(month, row);
  }
  return [...buckets.values()].sort((a,b) => a.month.localeCompare(b.month)).slice(-6).map(row => ({ ...row, average: row.scoreCount ? row.scoreSum / row.scoreCount : 0 }));
});
const cards = computed(() => [
  ['Active employees in scope', props.metrics.employees ?? '—', Users], ['Evaluations assigned', props.metrics.assigned || 0, ClipboardList],
  ['Pending evaluations', props.metrics.pending || 0, Clock3],
  ['Pending HR reviews', props.metrics.hrReview || 0, ShieldCheck],
  ['Submitted evaluations', props.metrics.submitted || 0, FileText], ['Completed evaluations', props.metrics.completed || 0, CheckCircle2],
]);
function blankTemplate() { return { id: null, name: '', department_id: '', position_id: '', description: '', frequency: 'quarterly', effective_date: '', status: 'draft', rating_scale: { 1: 'Unsatisfactory', 2: 'Needs Improvement', 3: 'Meets Expectations', 4: 'Exceeds Expectations', 5: 'Outstanding' }, criteria: [{ category: '', name: '', description: '', weight: 100, performance_standards: '', measurement_method: '', required_evidence: '', rating_guidelines: '' }] }; }
function parseJson(value, fallback = {}) { if (!value) return fallback; if (typeof value === 'object') return value; try { return JSON.parse(value); } catch { return fallback; } }
function setTab(tab) { activeTab.value = tab; }
function date(value) { return value ? String(value).slice(0, 10) : '—'; }
function effectiveDateValue(value) { const selected = value ? String(value).slice(0, 10) : todayDate; return selected < todayDate ? todayDate : selected; }
function employeeInitials(name) { return String(name || '?').trim().split(/\s+/).slice(0, 2).map(part => part.charAt(0)).join('').toUpperCase(); }
function printReport() { window.print(); }
function notify(message) { toast.value = message; window.setTimeout(() => { toast.value = ''; }, 3500); }
function reload() { router.reload({ only: ['employees', 'evaluators', 'reviewManagers', 'evaluations', 'templates', 'plans', 'rules', 'metrics'] }); }
function filterNow() { router.get('/dashboard/hrm/workforce/performance-kpi', filters.value, { preserveState: true, replace: true }); }
function openAssignment() { error.value = ''; assignment.value = { user_id: '', template_id: '', evaluator_id: '', manager_id: '', period_start: '', period_end: '', due_date: '', assignment_notes: '' }; showAssignment.value = true; }
function openAssignmentForEmployee(employee) {
  openAssignment();
  assignment.value.user_id = employee.id;
  prepareEmployee();
  const template = templateForEmployee(employee);
  if (template) {
    assignment.value.template_id = template.id;
    preparePeriodFromTemplate();
  }
}
function prepareEmployee() {
  const employee = selectedEmployee.value;
  assignment.value.evaluator_id = '';
  assignment.value.template_id = '';
  assignment.value.period_end = '';
  assignment.value.due_date = '';
  if (!employee) return;
  assignment.value.period_start = todayDate;
  const template = templateForEmployee(employee);
  if (template) {
    assignment.value.template_id = template.id;
    preparePeriodFromTemplate();
  }
}
function preparePeriodFromTemplate() {
  if (!assignment.value.period_start) return;
  const template = templateItems.value.find(t => Number(t.id) === Number(assignment.value.template_id));
  if (!template) return;
  const months = template.frequency === 'annual' ? 12 : template.frequency === 'semiannual' ? 6 : 3;
  const end = new Date(`${assignment.value.period_start}T00:00:00`);
  const day = end.getDate();
  end.setDate(1);
  end.setMonth(end.getMonth() + months);
  end.setDate(Math.min(day, new Date(end.getFullYear(), end.getMonth() + 1, 0).getDate()));
  end.setDate(end.getDate() - 1);
  assignment.value.period_end = `${end.getFullYear()}-${String(end.getMonth() + 1).padStart(2, '0')}-${String(end.getDate()).padStart(2, '0')}`;
  assignment.value.due_date = assignment.value.period_end;
}
async function createAssignment() {
  error.value = ''; busy.value = true;
  try { await axios.post('/dashboard/hrm/workforce/performance-kpi/evaluations', assignment.value); showAssignment.value = false; reload(); notify('Evaluation assigned.'); }
  catch (e) { error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {}).flat()[0] || 'Could not assign evaluation.'; }
  finally { busy.value = false; }
}
async function cancelAssignment(evaluation) {
  if (!window.confirm('Cancel this unstarted evaluation assignment?')) return;
  try { await axios.post('/dashboard/hrm/workforce/performance-kpi/evaluations/' + evaluation.id + '/cancel'); reload(); notify('Assignment cancelled.'); }
  catch (e) { notify(e.response?.data?.message || 'Could not cancel assignment.'); }
}function openEvaluation(evaluation) {
  selectedEvaluation.value = evaluation;
  ratingForm.value = { ratings: Object.fromEntries((evaluation.ratings || []).map(c => [c.id, { rating: c.rating || '', comments: c.comments || '' }])), comments: evaluation.evaluator_comments || '', strengths: evaluation.strengths || '', development_recommendations: evaluation.development_recommendations || '', recommendation: evaluation.recommendation || '' };
  error.value = ''; showEvaluation.value = true;
}
async function saveRating(submit) {
  error.value = ''; busy.value = true;
  try { await axios.put(`/dashboard/hrm/workforce/performance-kpi/evaluations/${selectedEvaluation.value.id}`, { ...ratingForm.value, submit }); showEvaluation.value = false; reload(); notify(submit ? 'Evaluation submitted for manager review.' : 'Draft saved.'); }
  catch (e) { error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {}).flat()[0] || 'Could not save evaluation.'; }
  finally { busy.value = false; }
}
async function uploadEvidence(criterion, event) {
  const files = Array.from(event.target.files || []); if (!files.length) return;
  const body = new FormData(); body.append('rating_id', criterion.id); files.forEach(file => body.append('files[]', file));
  try {
    const response = await axios.post(`/dashboard/hrm/workforce/performance-kpi/evaluations/${selectedEvaluation.value.id}/evidence`, body);
    const savedEvidence = response.data.evidence || [];
    selectedEvaluation.value = {
      ...selectedEvaluation.value,
      ratings: selectedEvaluation.value.ratings.map(rating => rating.id === criterion.id
        ? { ...rating, evidence: [...(rating.evidence || []), ...savedEvidence] }
        : rating),
    };
    event.target.value = '';
    notify('Evidence uploaded and saved.');
    reload();
  }
  catch (e) { error.value = e.response?.data?.message || 'Could not upload evidence.'; }
}
function openReview(evaluation) { selectedEvaluation.value = evaluation; reviewForm.value = { decision: '', comments: '' }; hrChecklist.value = { evidenceReviewed: false, scoreVerified: false }; error.value = ''; showReview.value = true; }
async function submitReview() {
  const managerAction = selectedEvaluation.value.status === 'Submitted';
  const hrFinalReview = selectedEvaluation.value.status === 'HR Review';
  if (hrFinalReview && !hrChecklistComplete.value) { error.value = 'Complete the HR verification checklist before saving this decision.'; return; }
  if (hrFinalReview && !String(reviewForm.value.comments || '').trim()) { error.value = 'An HR explanation is required for approval or return.'; return; }
  const url = managerAction ? `/dashboard/hrm/workforce/performance-kpi/evaluations/${selectedEvaluation.value.id}/manager-review` : `/dashboard/hrm/workforce/performance-kpi/evaluations/${selectedEvaluation.value.id}/hr-review`;
  error.value = ''; busy.value = true;
  const payload = hrFinalReview ? { ...reviewForm.value, evidence_reviewed: hrChecklist.value.evidenceReviewed, score_verified: hrChecklist.value.scoreVerified } : reviewForm.value;
  try { await axios.post(url, payload); showReview.value = false; reload(); notify('Review decision saved.'); }
  catch (e) { error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {}).flat()[0] || 'Could not save review.'; }
  finally { busy.value = false; }
}
function editTemplate(template = null) {
  templateForm.value = template ? { id: template.id || null, name: template.name, department_id: template.department_id || '', position_id: template.department_id ? (template.position_id || '') : '', description: template.description || '', frequency: template.frequency, effective_date: effectiveDateValue(template.effective_date), status: template.status, rating_scale: parseJson(template.rating_scale, blankTemplate().rating_scale), criteria: (template.criteria || []).map(c => ({ category: c.category, name: c.name, description: c.description || '', weight: Number(c.weight), performance_standards: c.performance_standards || '', measurement_method: c.measurement_method || '', required_evidence: c.required_evidence || '', rating_guidelines: parseJson(c.rating_guidelines, '') })) } : blankTemplate();
  showTemplate.value = true; error.value = '';
}
function syncTemplatePosition() {
  if (templateForm.value.position_id && !templatePositions.value.some(position => String(position.id) === String(templateForm.value.position_id))) {
    templateForm.value.position_id = '';
  }
}
async function saveTemplate() {
  error.value = ''; busy.value = true;
  try { await axios.post('/dashboard/hrm/workforce/performance-kpi/templates', templateForm.value); showTemplate.value = false; reload(); notify('Template version saved.'); }
  catch (e) { error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {}).flat()[0] || 'Could not save template.'; }
  finally { busy.value = false; }
}
async function templateAction(template, action) {
  try {
    const { data } = await axios.post(`/dashboard/hrm/workforce/performance-kpi/templates/${template.id}/action`, { action });
    if (action === 'duplicate') {
      editTemplate(data.template);
      return;
    }
    if (action === 'activate') {
      templateItems.value.forEach(item => {
        if (item.id === template.id || (item.template_code && item.template_code === template.template_code)) {
          item.status = item.id === template.id ? 'active' : 'archived';
        }
      });
    } else {
      template.status = action === 'deactivate' ? 'draft' : 'archived';
    }
    notify(data.message);
  } catch (e) { notify(e.response?.data?.message || 'Could not update template.'); }
}
function addCriterion() { templateForm.value.criteria.push({ category: '', name: '', description: '', weight: 0, performance_standards: '', measurement_method: '', required_evidence: '' }); }
function removeCriterion(index) { if (templateForm.value.criteria.length > 1) templateForm.value.criteria.splice(index, 1); }
async function saveRules() {
  try { const { data } = await axios.post('/dashboard/hrm/workforce/performance-kpi/classifications', { rules: classificationRules.value }); notify(data.message); reload(); }
  catch (e) { notify(e.response?.data?.message || 'Classification ranges must cover 0–100 without gaps or overlaps.'); }
}
async function createPlan() {
  if (!props.isHr && !planForm.value.supervisor_id) planForm.value.supervisor_id = authUser.id;
  try { await axios.post('/dashboard/hrm/workforce/performance-kpi/plans', planForm.value); planForm.value = { user_id: '', evaluation_id: '', supervisor_id: '', performance_gap: '', improvement_objective: '', required_actions: '', training_or_coaching: '', success_measurement: '', notes: '', start_date: '', target_date: '', review_date: '', status: 'Active' }; reload(); notify('Improvement plan created.'); }
  catch (e) { error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {}).flat()[0] || 'Could not create plan.'; }
}
async function updatePlan(plan, status) {
  const progressNote = status === 'Monitoring' ? window.prompt('Add a progress note for this review:') : null;
  if (status === 'Monitoring' && progressNote === null) return;
  const reviewResults = ['Completed', 'Unsuccessful'].includes(status) ? window.prompt('Enter the PIP review result:') : null;
  if (['Completed', 'Unsuccessful'].includes(status) && reviewResults === null) return;
  try { await axios.put(`/dashboard/hrm/workforce/performance-kpi/plans/${plan.id}`, { status, progress_note: progressNote, review_results: reviewResults }); reload(); notify('Plan updated.'); }
  catch (e) { notify(e.response?.data?.message || 'Could not update plan.'); }
}
function canEvaluate(e) { return Number(e.evaluator_id) === Number(authUser.id) && ['Assigned', 'In Progress', 'Returned for Revision'].includes(e.status); }
function canSubmitEvaluation(e) { return Boolean(e?.period_end) && todayDate >= String(e.period_end).slice(0, 10); }
function canManagerReview(e) { return Number(e.manager_id) === Number(authUser.id) && e.status === 'Submitted'; }
function statusTone(status) { return ({ Completed: 'bg-emerald-100 text-emerald-800', 'HR Review': 'bg-violet-100 text-violet-800', 'Manager Review': 'bg-indigo-100 text-indigo-800', Submitted: 'bg-sky-100 text-sky-800', 'Returned for Revision': 'bg-rose-100 text-rose-800', 'In Progress': 'bg-amber-100 text-amber-800', Assigned: 'bg-slate-100 text-slate-700' })[status] || 'bg-slate-100 text-slate-700'; }
function evaluationHref(evaluation, evidence) { return `/dashboard/hrm/workforce/performance-kpi/evaluations/${evaluation.id}/evidence/${evidence.id}`; }
</script>

<template>
  <AuthenticatedLayout>
    <main class="performance-kpi-page min-h-screen bg-slate-50 p-4 text-slate-900 sm:p-7">
      <div class="mx-auto max-w-screen-2xl space-y-6">
        <header v-if="!templatesOnly" class="flex flex-wrap items-end justify-between gap-4">
          <div><p class="text-xs font-bold uppercase tracking-widest text-blue-700">HRM / Workforce</p><h1 class="mt-1 text-2xl font-black sm:text-3xl">{{ kpiEvaluationsOnly ? 'KPI Evaluation' : 'Performance Management' }}</h1><p class="mt-2 max-w-3xl text-sm text-slate-600">{{ kpiEvaluationsOnly ? 'Review evaluation assignments, scores, and approval status.' : 'Position-based KPI evaluations for active Monti employees, with manual ratings, automatic scoring, manager review, and HR finalization.' }}</p></div>
          <div class="flex gap-2"><a href="/dashboard/hrm/workforce/performance-kpi/export" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold"><Download class="h-4 w-4"/>Export CSV</a></div>
        </header>
        <div v-if="!templatesOnly && !kpiEvaluationsOnly" class="rounded-xl bg-slate-50 p-2 shadow-sm"><div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3"><article v-for="[label, value, icon] in cards" :key="label" class="flex items-start justify-between rounded-xl border border-slate-200 bg-white p-3"><div><p class="text-xs font-semibold text-slate-500">{{ label }}</p><p class="mt-1 text-xl font-black">{{ value }}</p></div><component :is="icon" class="h-4 w-4 shrink-0 text-blue-600"/></article></div></div>
        <nav v-if="!templatesOnly && !kpiEvaluationsOnly" class="flex gap-1.5 overflow-x-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-sm" aria-label="Performance management sections">
          <button v-for="tab in sections" :key="tab.id" @click="setTab(tab.id)" :aria-current="activeTab === tab.id ? 'page' : undefined" class="group inline-flex shrink-0 items-center gap-1.5 rounded-md px-2.5 py-1.5 text-[11px] font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-1 sm:text-xs" :class="activeTab === tab.id ? 'bg-blue-700 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950'"><component :is="tab.icon" class="h-3.5 w-3.5" :class="activeTab === tab.id ? 'text-blue-100' : 'text-slate-400 group-hover:text-slate-600'"/>{{ tab.label }}</button>
        </nav>

        <section v-if="activeTab === 'dashboard'" class="space-y-5">
          <div class="flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-white p-4">
            <input v-model="filters.period" type="month" aria-label="Evaluation period" class="rounded-lg border-slate-300 text-sm"/><select v-if="!kpiEvaluationsOnly" v-model="filters.department" aria-label="Department filter" class="rounded-lg border-slate-300 text-sm"><option value="">All departments</option><option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option></select><select v-if="!kpiEvaluationsOnly" v-model="filters.position" aria-label="Position filter" class="rounded-lg border-slate-300 text-sm"><option value="">All positions</option><option v-for="p in positions" :key="p.id" :value="p.id">{{ p.name }}</option></select><input v-model="filters.employee" placeholder="Employee / ID" class="rounded-lg border-slate-300 text-sm"/><select v-if="!kpiEvaluationsOnly" v-model="filters.status" aria-label="Status filter" class="rounded-lg border-slate-300 text-sm"><option value="">All statuses</option><option v-for="s in ['Assigned','In Progress','Submitted','Manager Review','HR Review','Returned for Revision','Completed','Cancelled']" :key="s">{{ s }}</option></select><button @click="filterNow" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Apply filters</button></div>
          <div class="grid gap-4 xl:grid-cols-2">
          <section class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-bold">Department performance</h2><p class="mt-1 text-xs text-slate-500">Average completed evaluation score for each department.</p><div class="mt-4 space-y-3"><div v-for="row in bars" :key="row.name" class="grid grid-cols-[9rem_1fr_4rem] items-center gap-3 text-sm"><span class="truncate">{{ row.name }}</span><div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-blue-600" :style="{ width: `${Math.min(100,row.score)}%` }"/></div><span class="text-right text-xs text-slate-500">{{ row.count ? `${row.score.toFixed(1)}%` : '—' }}</span></div><p v-if="!bars.length" class="py-4 text-sm text-slate-500">No department records.</p></div></section>
            <section class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-bold">Performance classification</h2><div class="mt-4 space-y-3"><div v-for="row in ratingDistribution" :key="row.label" class="grid grid-cols-[11rem_1fr_2rem] items-center gap-3 text-sm"><span>{{ row.label }}</span><div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-violet-500" :style="{ width: `${props.metrics.completed ? row.count / props.metrics.completed * 100 : 0}%` }"/></div><span class="text-right text-xs text-slate-500">{{ row.count }}</span></div></div></section>
          </div>
        </section>

        <section v-else-if="templatesOnly && activeTab === 'templates'" class="space-y-5">
          <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-5"><div><h2 class="font-bold">Position KPI templates</h2><p class="mt-1 text-sm text-slate-500">Each saved change creates a version. Existing evaluations keep their original template and score thresholds.</p></div><button v-if="isHr" @click="editTemplate()" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white">New template</button></div>
          <div class="grid gap-4 lg:grid-cols-2"><article v-for="template in templates" :key="template.id" class="rounded-xl border border-slate-200 bg-white p-5"><div class="flex justify-between gap-3"><div><p class="text-xs font-semibold text-blue-700">v{{ template.version }}</p><h3 class="mt-1 font-bold">{{ template.name }}</h3><p class="mt-1 text-xs text-slate-500">{{ template.department_name || 'Any department' }} · {{ template.position_name }} · {{ template.frequency }}</p></div><span class="h-fit rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold">{{ template.status }}</span></div><p class="mt-3 text-sm text-slate-600">{{ template.description }}</p><div class="mt-3 divide-y divide-slate-100"><div v-for="criterion in template.criteria" :key="criterion.id" class="flex justify-between gap-3 py-2 text-sm"><span>{{ criterion.name }}<small class="block text-xs text-slate-500">{{ criterion.performance_standards }}</small></span><b>{{ Number(criterion.weight).toFixed(2) }}%</b></div><div class="flex justify-between py-2 text-sm font-bold"><span>Total</span><span>{{ template.criteria.reduce((sum,c)=>sum+Number(c.weight),0).toFixed(2) }}%</span></div></div><div v-if="isHr" class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3"><button @click="editTemplate(template)" class="rounded-md border px-3 py-1.5 text-xs font-semibold">New version</button><button @click="templateAction(template,'duplicate')" class="rounded-md border px-3 py-1.5 text-xs font-semibold">Duplicate</button><button v-if="template.status !== 'active'" @click="templateAction(template,'activate')" class="rounded-md border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-700">Activate</button><button v-else @click="templateAction(template,'deactivate')" class="rounded-md border px-3 py-1.5 text-xs font-semibold">Deactivate</button><button @click="templateAction(template,'archive')" class="rounded-md border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700">Archive</button></div></article><p v-if="!templates.length" class="rounded-xl border border-dashed p-10 text-center text-sm text-slate-500">No KPI templates have been created.</p></div>
          <section v-if="isHr" class="rounded-xl border border-slate-200 bg-white p-5"><div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="font-bold">Performance classification thresholds</h2><p class="mt-1 text-xs text-slate-500">Ranges use an inclusive minimum and exclusive maximum, except the 100% upper bound.</p></div><button @click="saveRules" class="rounded-lg border px-3 py-2 text-sm font-semibold">Save new rules version</button></div><div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5"><div v-for="(rule,index) in classificationRules" :key="index" class="grid grid-cols-2 gap-2"><input v-model.number="rule.minimum_score" type="number" min="0" max="100" step="0.01" class="rounded border-slate-300 text-sm"/><input v-model.number="rule.maximum_score" type="number" min="0" max="100" step="0.01" class="rounded border-slate-300 text-sm"/><input v-model="rule.label" class="col-span-2 rounded border-slate-300 text-sm"/></div></div></section>
        </section>

        <section v-else-if="activeTab === 'evaluations' || activeTab === 'reviews' || activeTab === 'history'" :class="['flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white', isHr && activeTab === 'evaluations' && !kpiEvaluationsOnly ? 'kpi-evaluation-panel' : '']">
          <div class="order-0 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5"><div><h2 class="font-bold">{{ activeTab === 'evaluations' ? 'Employee evaluations' : activeTab === 'reviews' ? 'Manager and HR review queue' : 'Evaluation history' }}</h2><p class="mt-1 text-sm text-slate-500">{{ activeTab === 'history' ? 'Finalized records retain their original template and approval history.' : 'Access is restricted to your HR role, assigned evaluations, and your own finalized results.' }}</p></div></div>
          <section v-if="isHr && activeTab === 'evaluations' && !kpiEvaluationsOnly" class="order-2 border-b border-slate-100 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-2 p-5"><div><h3 class="font-bold">Official employees and KPI templates</h3><p class="mt-1 text-sm text-slate-500">Active non-probationary employees are matched to active templates by position and department. New templates appear here automatically.</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ filteredEmployees.length }} employees</span></div>
            <div class="overflow-x-auto">
              <table class="w-full min-w-[760px] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-bold uppercase text-slate-500"><tr><th class="px-3 py-2">Employee</th><th class="px-3 py-2">Position / Department</th><th class="px-3 py-2">KPI template</th><th class="px-3 py-2">Assignment status</th><th class="px-3 py-2">Action</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="employee in filteredEmployees" :key="employee.id">
                    <td class="px-3 py-2"><div class="flex items-center gap-3"><img v-if="employee.profile_photo_url" :src="employee.profile_photo_url" :alt="`${employee.name} profile photo`" class="h-10 w-10 shrink-0 rounded-full border border-slate-200 object-cover" loading="lazy"/><span v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700" aria-hidden="true">{{ employeeInitials(employee.name) }}</span><span class="font-semibold">{{ employee.name }}</span></div></td>
                    <td class="px-3 py-2">{{ employee.hrmOrgPosition?.name || positions.find(position => Number(position.id) === Number(employee.hrm_position_id))?.name || 'No position' }}<small class="block text-slate-500">{{ employee.hrmDepartment?.name || departments.find(department => Number(department.id) === Number(employee.hrm_department_id))?.name || 'No department' }}</small></td>
                    <td class="px-3 py-2"><span v-if="templateForEmployee(employee)" class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">{{ templateForEmployee(employee).name }}</span><span v-else class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800">No KPI template assigned</span></td>
                    <td class="px-3 py-2"><span :class="activeEvaluationForEmployee(employee)?.evaluator_id ? 'rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700' : 'rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-800'">{{ activeEvaluationForEmployee(employee)?.evaluator_id ? 'Evaluator assigned' : 'No evaluator assigned' }}</span></td>
                    <td class="px-3 py-2"><button v-if="hasActiveAssignment(employee)" @click="openEvaluation(activeEvaluationForEmployee(employee))" class="rounded-lg border border-blue-200 px-2.5 py-1 text-[11px] font-semibold text-blue-700 hover:bg-blue-50">View evaluation</button><button v-else-if="templateForEmployee(employee)" @click="openAssignmentForEmployee(employee)" class="rounded-lg bg-blue-700 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-blue-800">Assign evaluation</button><span v-else class="text-[11px] text-slate-500">Create a matching template to enable assignment</span></td>
                  </tr>
                  <tr v-if="!filteredEmployees.length"><td colspan="5" class="p-8 text-center text-sm text-slate-500">No employees match these filters.</td></tr>
                </tbody>
              </table>
            </div>
          </section>
          <div v-if="activeTab !== 'evaluations' || kpiEvaluationsOnly" class="flex flex-wrap gap-2 border-b border-slate-100 p-4"><input v-model="filters.employee" placeholder="Search employee" class="rounded-lg border-slate-300 text-sm" :class="kpiEvaluationsOnly ? 'w-full sm:flex-1 min-w-[260px]' : ''"/><input v-model="filters.period" type="month" aria-label="Period filter" class="rounded-lg border-slate-300 text-sm"/><select v-if="!kpiEvaluationsOnly" v-model="filters.department" aria-label="Department filter" class="rounded-lg border-slate-300 text-sm"><option value="">All departments</option><option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option></select><select v-if="!kpiEvaluationsOnly" v-model="filters.position" aria-label="Position filter" class="rounded-lg border-slate-300 text-sm"><option value="">All positions</option><option v-for="p in positions" :key="p.id" :value="p.id">{{ p.name }}</option></select><select v-if="!kpiEvaluationsOnly" v-model="filters.status" aria-label="Status filter" class="rounded-lg border-slate-300 text-sm"><option value="">All statuses</option><option v-for="status in statusOptions" :key="status">{{ status }}</option></select><select v-model="sortBy" aria-label="Sort evaluations" class="rounded-lg border-slate-300 text-sm"><option value="period_desc">Newest period</option><option value="employee_asc">Employee name</option><option value="score_desc">Highest score</option></select></div><div v-if="activeTab !== 'evaluations' || kpiEvaluationsOnly" class="overflow-x-auto"><table class="w-full min-w-[1100px] text-left text-xs"><thead class="bg-slate-50 text-[10px] font-bold uppercase text-slate-500"><tr><th class="px-2 py-2">{{ kpiEvaluationsOnly ? 'Employee' : 'Reference / Employee' }}</th><th class="px-2 py-2">Position / Department</th><th class="px-2 py-2">KPI Template</th><th class="px-2 py-2">Evaluation Period</th><th class="px-2 py-2">Evaluator / Manager</th><th class="px-2 py-2">Due</th><th class="px-2 py-2">Score</th><th class="px-2 py-2">Status</th><th class="px-2 py-2">Action</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="e in pagedEvaluations" :key="e.id"><td class="px-2 py-2"><b v-if="!kpiEvaluationsOnly">{{ e.reference }}</b><span :class="kpiEvaluationsOnly ? 'font-semibold' : 'mt-1 block'">{{ e.employee_name }}</span></td><td class="px-2 py-2">{{ e.position_name || '—' }}<small class="block text-slate-500">{{ e.department_name || '—' }}</small></td><td class="px-2 py-2">{{ e.template_name }}<small class="block text-slate-500">Version {{ e.template_version }}</small></td><td class="px-2 py-2">{{ date(e.period_start) }} – {{ date(e.period_end) }}</td><td class="px-2 py-2">{{ e.evaluator_name }}<small class="block text-slate-500">Manager: {{ e.manager_name || '—' }}</small></td><td class="px-2 py-2">{{ date(e.due_date) }}</td><td class="px-2 py-2">{{ e.overall_score == null ? '—' : `${Number(e.overall_score).toFixed(2)}%` }}<small class="block text-slate-500">{{ e.classification }}</small></td><td class="px-2 py-2"><span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="statusTone(e.status)">{{ e.status }}</span></td><td class="px-2 py-2"><div class="flex gap-1"><button v-if="canEvaluate(e)" @click="openEvaluation(e)" class="rounded-md bg-blue-700 px-2 py-1 text-[10px] font-semibold text-white">Evaluate</button><button v-else-if="canManagerReview(e) || (isHr && e.status === 'HR Review')" @click="openReview(e)" class="rounded-md bg-violet-700 px-2 py-1 text-[10px] font-semibold text-white">Review</button><button v-else @click="openEvaluation(e)" class="rounded-md border px-2 py-1 text-[10px] font-semibold">Details</button><button v-if="isHr && e.status === 'Assigned'" @click="cancelAssignment(e)" class="rounded-md border border-rose-200 px-2 py-1 text-[10px] font-semibold text-rose-700">Cancel</button></div></td></tr><tr v-if="!tableRows.length"><td colspan="9" class="p-8 text-center text-xs text-slate-500">No evaluations in this view.</td></tr></tbody></table></div><footer v-if="activeTab !== 'evaluations' || kpiEvaluationsOnly" class="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-xs text-slate-500"><span>{{ tableRows.length }} results · Page {{ tablePage }} of {{ pageCount }}</span><div class="flex gap-2"><button :disabled="tablePage <= 1" @click="tablePage--" class="rounded border px-3 py-1.5 disabled:opacity-40">Previous</button><button :disabled="tablePage >= pageCount" @click="tablePage++" class="rounded border px-3 py-1.5 disabled:opacity-40">Next</button></div></footer>
        </section>

        <section v-else-if="activeTab === 'plans'" class="grid gap-5 xl:grid-cols-[1fr_0.8fr]"><section class="overflow-hidden rounded-xl border border-slate-200 bg-white"><div class="border-b p-5"><h2 class="font-bold">Performance improvement plans</h2><p class="mt-1 text-sm text-slate-500">Coaching and development plans are separate from disciplinary decisions.</p></div><div class="divide-y"> <article v-for="plan in plans" :key="plan.id" class="flex flex-wrap items-center justify-between gap-3 p-4"><div><b>{{ plan.employee_name }} · {{ plan.status }}</b><p class="mt-1 text-xs text-slate-500">{{ plan.performance_gap }} · Target {{ date(plan.target_date) }} · Supervisor {{ plan.supervisor_name || 'Unassigned' }}</p><p v-for="entry in plan.progress" :key="entry.id" class="mt-1 text-xs text-slate-600">{{ date(entry.recorded_on) }} · {{ entry.progress_note }}</p></div><div class="flex gap-2"><button v-if="!['Completed','Cancelled','Unsuccessful'].includes(plan.status)" @click="updatePlan(plan,'Monitoring')" class="rounded border px-2 py-1 text-xs">Record progress</button><button v-if="isHr && !['Completed','Cancelled','Unsuccessful'].includes(plan.status)" @click="updatePlan(plan,'Completed')" class="rounded bg-emerald-700 px-2 py-1 text-xs text-white">Close completed</button><button v-if="isHr && !['Completed','Cancelled','Unsuccessful'].includes(plan.status)" @click="updatePlan(plan,'Unsuccessful')" class="rounded border border-rose-200 px-2 py-1 text-xs text-rose-700">Close unsuccessful</button><button v-if="isHr && !['Completed','Cancelled','Unsuccessful'].includes(plan.status)" @click="updatePlan(plan,'Cancelled')" class="rounded border px-2 py-1 text-xs">Cancel</button></div></article><p v-if="!plans.length" class="p-8 text-center text-sm text-slate-500">No improvement plans found.</p></div></section><form v-if="isHr || authUser.position === 'manager'" @submit.prevent="createPlan" class="space-y-3 rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-bold">Create improvement plan</h2><select v-model="planForm.user_id" required class="w-full rounded-lg border-slate-300 text-sm"><option value="">Employee</option><option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option></select><select v-model="planForm.evaluation_id" class="w-full rounded-lg border-slate-300 text-sm"><option value="">Related evaluation (optional)</option><option v-for="e in evaluations" :key="e.id" :value="e.id">{{ e.reference }} · {{ e.employee_name }}</option></select><textarea v-model="planForm.performance_gap" required placeholder="Performance gap" class="w-full rounded-lg border-slate-300 text-sm"/><textarea v-model="planForm.improvement_objective" required placeholder="Improvement objective" class="w-full rounded-lg border-slate-300 text-sm"/><textarea v-model="planForm.required_actions" required placeholder="Required actions" class="w-full rounded-lg border-slate-300 text-sm"/><textarea v-model="planForm.training_or_coaching" placeholder="Training or coaching" class="w-full rounded-lg border-slate-300 text-sm"/><textarea v-model="planForm.success_measurement" required placeholder="Success measurement" class="w-full rounded-lg border-slate-300 text-sm"/><select v-model="planForm.supervisor_id" class="w-full rounded-lg border-slate-300 text-sm"><option value="">Responsible supervisor</option><option v-if="!isHr" :value="authUser.id">{{ authUser.name }} · Me</option><option v-for="user in evaluators" :key="user.id" :value="user.id">{{ user.name }}</option></select><select v-model="planForm.status" class="w-full rounded-lg border-slate-300 text-sm"><option>Draft</option><option>Active</option></select><div class="grid grid-cols-3 gap-2"><input v-model="planForm.start_date" required type="date" class="rounded border-slate-300 text-xs"/><input v-model="planForm.target_date" required type="date" class="rounded border-slate-300 text-xs"/><input v-model="planForm.review_date" type="date" class="rounded border-slate-300 text-xs"/></div><button class="w-full rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white">Create plan</button><p v-if="error" class="text-sm text-rose-700">{{ error }}</p></form></section>

        <section v-else-if="activeTab === 'reports'" class="space-y-5"><div class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-bold">Reports and analytics</h2><p class="mt-1 text-sm text-slate-500">Export authorized employee results with evaluation periods, scores, classification, evaluator, reviewer, and approval comments.</p><a href="/dashboard/hrm/workforce/performance-kpi/export" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white"><Download class="h-4 w-4"/>Download CSV</a><button @click="printReport" class="ml-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold">Print / Save PDF</button></div><div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><article class="rounded-xl border bg-white p-4"><small>Evaluations in current result set</small><p class="mt-1 text-xl font-bold">{{ evaluations.length }}</p></article><article class="rounded-xl border bg-white p-4"><small>Completed</small><p class="mt-1 text-xl font-bold">{{ metrics.completed || 0 }}</p></article><article class="rounded-xl border bg-white p-4"><small>Requiring improvement</small><p class="mt-1 text-xl font-bold">{{ metrics.improvement || 0 }}</p></article><article class="rounded-xl border bg-white p-4"><small>Average completed score</small><p class="mt-1 text-xl font-bold">{{ metrics.averageScore == null ? '—' : `${Number(metrics.averageScore).toFixed(1)}%` }}</p></article></div></section>
        <section v-else-if="activeTab === 'promotions'" class="space-y-5">
          <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-5">
            <div><h2 class="font-bold">Promotion recommendations</h2><p class="mt-1 max-w-3xl text-sm text-slate-500">All employees are listed. The current-year overall score is the total of manager-submitted evaluation scores divided by four. Qualification requires {{ promotionThreshold }}% or higher.</p></div>
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-bold text-emerald-800">{{ qualifiedPromotionCount }} qualified</span>
          </div>
          <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="w-full min-w-[1200px] text-left text-sm">
              <thead class="bg-slate-50 text-xs font-bold uppercase text-slate-500"><tr><th class="px-4 py-3">Employee</th><th class="px-4 py-3">Position / Department</th><th class="px-4 py-3">Overall score (current year)</th><th class="px-4 py-3">Latest period</th><th class="px-4 py-3">Latest classification</th><th class="px-4 py-3">Evaluator / Manager</th><th class="px-4 py-3">Recommendation</th><th class="px-4 py-3">Action</th></tr></thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="employee in promotionCandidates" :key="employee.user_id">
                  <td class="px-4 py-3 font-semibold">{{ employee.employee_name }}</td>
                  <td class="px-4 py-3">{{ employee.position_name || '—' }}<small class="block text-slate-500">{{ employee.department_name || '—' }}</small></td>
                  <td class="px-4 py-3 font-bold" :class="employee.promotion_qualified ? 'text-emerald-700' : 'text-slate-500'">{{ employee.annual_average_score === null ? '—' : `${Number(employee.annual_average_score).toFixed(2)}%` }}<small class="block font-normal text-slate-500">{{ employee.annual_scored_evaluation_count ? `Total of ${employee.annual_scored_evaluation_count} manager-submitted scores divided by four` : 'No manager-submitted score history this year' }}</small></td>
                  <td class="px-4 py-3">{{ employee.latest_manager_evaluation ? `${date(employee.latest_manager_evaluation.period_start)} – ${date(employee.latest_manager_evaluation.period_end)}` : '—' }}</td>
                  <td class="px-4 py-3">{{ employee.latest_manager_evaluation?.classification || '—' }}</td>
                  <td class="px-4 py-3">{{ employee.latest_manager_evaluation?.evaluator_name || '—' }}<small v-if="employee.latest_manager_evaluation" class="block text-slate-500">Reviewing manager: {{ employee.latest_manager_evaluation.manager_name }}</small></td>
                  <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="employee.promotion_qualified ? 'bg-emerald-100 text-emerald-800' : employee.annual_evaluation_count ? 'bg-slate-100 text-slate-600' : 'bg-amber-100 text-amber-800'">{{ employee.promotion_recommendation }}</span></td>
                  <td class="px-4 py-3"><button v-if="employee.latest_manager_evaluation" @click="openEvaluation(employee.latest_manager_evaluation)" class="rounded-lg border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-50">View evaluation</button><span v-else class="text-xs text-slate-400">—</span></td>
                </tr>
                <tr v-if="!promotionCandidates.length"><td colspan="8" class="p-10 text-center text-sm text-slate-500">No active employees are in scope.</td></tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <div v-if="showAssignment" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="showAssignment=false"><form @submit.prevent="createAssignment" class="max-h-[92vh] w-full max-w-2xl space-y-4 overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl"><header class="flex justify-between"><div><h2 class="text-xl font-bold">Assign KPI evaluation</h2><p class="mt-1 text-sm text-slate-500">Employee data is drawn from Employee Master Records.</p></div><button type="button" @click="showAssignment=false"><X/></button></header><div class="grid gap-3 sm:grid-cols-2"><label class="text-sm font-semibold sm:col-span-2">Active employee<select v-model="assignment.user_id" required @change="prepareEmployee" class="mt-1 w-full rounded-lg border-slate-300"><option value="">Choose employee</option><option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }} · {{ e.hrmOrgPosition?.name || e.hrm_org_position?.name || '' }}</option></select><small v-if="selectedEmployee" class="font-normal text-slate-500">{{ selectedEmployee.hrmDepartment?.name || selectedEmployee.hrm_department?.name }} · {{ selectedEmployee.hrmOrgPosition?.name || selectedEmployee.hrm_org_position?.name }} · {{ selectedEmployee.hrmEmploymentType?.name || selectedEmployee.hrm_employment_type?.name }}</small></label><section class="sm:col-span-2"><h3 class="text-sm font-semibold">Position-matched active template</h3><div class="mt-1 grid gap-2 sm:grid-cols-3"><div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"><span class="block text-xs font-medium text-slate-500">KPI template</span><span class="text-sm">{{ selectedAssignmentTemplate ? `${selectedAssignmentTemplate.name} · v${selectedAssignmentTemplate.version}` : 'No active matching template' }}</span></div><div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"><span class="block text-xs font-medium text-slate-500">Applicable position</span><span class="text-sm">{{ selectedEmployee?.hrmOrgPosition?.name || selectedEmployee?.hrm_org_position?.name || 'No position' }}</span></div><div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"><span class="block text-xs font-medium text-slate-500">Evaluation frequency</span><span class="text-sm capitalize">{{ selectedAssignmentTemplate?.frequency || '—' }}</span></div></div><small v-if="selectedEmployee && !selectedAssignmentTemplate" class="mt-1 block font-normal text-rose-600">No active position-specific KPI template matches this employee.</small></section><label class="text-sm font-semibold">Assigned evaluator<select v-model="assignment.evaluator_id" required class="mt-1 w-full rounded-lg border-slate-300"><option value="">Choose evaluator</option><option v-for="u in evaluatorOptions" :key="u.id" :value="u.id">{{ u.name }} · {{ u.position }}</option></select><small class="font-normal text-slate-500">{{ evaluatorDepartmentName }} managers only.</small></label><label class="text-sm font-semibold">Reviewing manager<select v-model="assignment.manager_id" required class="mt-1 w-full rounded-lg border-slate-300"><option value="">Choose manager</option><option v-for="u in reviewManagers" :key="u.id" :value="u.id">{{ u.name }} · {{ u.position }}</option></select><small class="font-normal text-slate-500">HR Manager only.</small></label><label class="text-sm font-semibold">Period start<input v-model="assignment.period_start" required type="date" @change="preparePeriodFromTemplate" class="mt-1 w-full rounded-lg border-slate-300"/></label><label class="text-sm font-semibold">Period end<input v-model="assignment.period_end" required type="date" class="mt-1 w-full rounded-lg border-slate-300"/></label><label class="text-sm font-semibold">Due date<input v-model="assignment.due_date" required type="date" class="mt-1 w-full rounded-lg border-slate-300"/></label><label class="text-sm font-semibold sm:col-span-2">Assignment notes<textarea v-model="assignment.assignment_notes" rows="2" class="mt-1 w-full rounded-lg border-slate-300"/></label></div><p v-if="error" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</p><footer class="flex justify-end gap-2"><button type="button" @click="showAssignment=false" class="rounded-lg border px-4 py-2 text-sm">Cancel</button><button :disabled="busy || !selectedAssignmentTemplate" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">{{ busy ? 'Saving…' : 'Create assignment' }}</button></footer></form></div>

      <div v-if="showTemplate" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-6" @click.self="showTemplate=false">
        <form @submit.prevent="saveTemplate" class="flex max-h-[94vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
          <header class="flex items-start justify-between border-b border-slate-100 px-5 py-4 sm:px-7">
            <div>
              <p class="text-xs font-bold uppercase tracking-wider text-blue-700">Performance management</p>
              <h2 class="mt-1 text-xl font-bold text-slate-900">{{ templateForm.id ? 'Create template version' : 'New KPI template' }}</h2>
              <p class="mt-1 text-sm text-slate-500">Set up the template details and define criteria that total exactly 100%.</p>
            </div>
            <button type="button" @click="showTemplate=false" aria-label="Close template form" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800"><X class="h-5 w-5"/></button>
          </header>
          <div class="space-y-5 overflow-y-auto px-5 py-5 sm:px-7">
            <section>
              <h3 class="mb-3 text-sm font-bold text-slate-800">Template details</h3>
              <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <label class="grid gap-1 text-xs font-semibold text-slate-600">Template name<input v-model="templateForm.name" required placeholder="e.g. HR Staff KPI" class="rounded-lg border-slate-300 text-sm"/></label>
                <label class="grid gap-1 text-xs font-semibold text-slate-600">Department<select v-model="templateForm.department_id" required @change="syncTemplatePosition" class="rounded-lg border-slate-300 text-sm"><option value="">Select department</option><option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option></select></label>
                <label class="grid gap-1 text-xs font-semibold text-slate-600">Applicable position<select v-model="templateForm.position_id" required :disabled="!templateForm.department_id" class="rounded-lg border-slate-300 text-sm disabled:cursor-not-allowed disabled:opacity-60"><option value="">{{ !templateForm.department_id ? 'Select department first' : !templatePositions.length ? 'No positions available for this department' : 'Select position' }}</option><option v-for="p in templatePositions" :key="p.id" :value="p.id">{{ p.name }}</option></select></label>
                <label class="grid gap-1 text-xs font-semibold text-slate-600">Evaluation frequency<select v-model="templateForm.frequency" class="rounded-lg border-slate-300 text-sm"><option value="quarterly">Quarterly</option><option value="semiannual">Semiannual</option><option value="annual">Annual</option></select></label>
                <label class="grid gap-1 text-xs font-semibold text-slate-600">Effective date<input v-model="templateForm.effective_date" required type="date" :min="todayDate" class="rounded-lg border-slate-300 text-sm"/></label>
                <label class="grid gap-1 text-xs font-semibold text-slate-600">Status<select v-model="templateForm.status" class="rounded-lg border-slate-300 text-sm"><option value="draft">Draft</option><option value="active">Active</option><option value="archived">Archived</option></select></label>
                <label class="grid gap-1 text-xs font-semibold text-slate-600 sm:col-span-2 lg:col-span-3">Template description<textarea v-model="templateForm.description" rows="2" placeholder="Describe the purpose of this KPI template" class="rounded-lg border-slate-300 text-sm"/></label>
              </div>
            </section>
            <section class="rounded-xl border border-slate-200 bg-slate-50 p-4">
              <div class="mb-3"><h3 class="text-sm font-bold text-slate-800">Rating scale</h3><p class="mt-1 text-xs text-slate-500">Name each performance level used when rating criteria.</p></div>
              <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5"><label v-for="n in 5" :key="n" class="grid gap-1 text-xs font-semibold text-slate-600">Rating {{ n }}<input v-model="templateForm.rating_scale[n]" required :aria-label="`Rating ${n} label`" class="rounded-lg border-slate-300 bg-white text-sm"/></label></div>
            </section>
            <section>
              <div class="mb-3 flex flex-wrap items-end justify-between gap-2"><div><h3 class="text-sm font-bold text-slate-800">Performance criteria</h3><p class="mt-1 text-xs text-slate-500">Add criteria and distribute the full template weight across them.</p></div><span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-800">Total weight: {{ templateForm.criteria.reduce((s,c)=>s+Number(c.weight||0),0).toFixed(2) }}%</span></div>
              <div class="space-y-3">
                <article v-for="(criterion,index) in templateForm.criteria" :key="index" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                  <div class="mb-3 flex items-center justify-between"><h4 class="text-sm font-bold text-slate-800">Criterion {{ index + 1 }}</h4><button type="button" @click="removeCriterion(index)" :disabled="templateForm.criteria.length <= 1" class="rounded-md px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40">Remove</button></div>
                  <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                    <label class="grid gap-1 text-xs font-semibold text-slate-600 lg:col-span-1">Category<input v-model="criterion.category" required placeholder="e.g. Quality" class="rounded-lg border-slate-300 text-sm"/></label>
                    <label class="grid gap-1 text-xs font-semibold text-slate-600 sm:col-span-2 lg:col-span-2">Criterion name<input v-model="criterion.name" required placeholder="Name this criterion" class="rounded-lg border-slate-300 text-sm"/></label>
                    <label class="grid gap-1 text-xs font-semibold text-slate-600">Weight (%)<input v-model.number="criterion.weight" required type="number" min="0.01" max="100" step="0.01" aria-label="Weight %" class="rounded-lg border-slate-300 text-sm"/></label>

                    <label class="grid gap-1 text-xs font-semibold text-slate-600 sm:col-span-2 lg:col-span-3">Description<textarea v-model="criterion.description" required rows="2" placeholder="Describe the criterion" class="rounded-lg border-slate-300 text-sm"/></label>
                    <label class="grid gap-1 text-xs font-semibold text-slate-600 sm:col-span-2 lg:col-span-3">Performance standard<textarea v-model="criterion.performance_standards" required rows="2" placeholder="Define the expected performance" class="rounded-lg border-slate-300 text-sm"/></label>
                    <label class="grid gap-1 text-xs font-semibold text-slate-600 sm:col-span-2 lg:col-span-3">Required supporting evidence, if any<input v-model="criterion.required_evidence" placeholder="Optional evidence" class="rounded-lg border-slate-300 text-sm"/></label>
                    <label class="grid gap-1 text-xs font-semibold text-slate-600 sm:col-span-2 lg:col-span-3">Rating guidelines for this criterion<textarea v-model="criterion.rating_guidelines" rows="2" placeholder="Explain what each rating level means (optional)" class="rounded-lg border-slate-300 text-sm"/></label>
                  </div>
                </article>
              </div>
              <button type="button" @click="addCriterion" class="mt-3 inline-flex items-center gap-1 rounded-lg border border-blue-200 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50"><Plus class="h-4 w-4"/>Add criterion</button>
            </section>
            <p v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</p>
          </div>
          <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-white px-5 py-4 sm:px-7">
            <p class="text-sm font-semibold text-slate-600">Total weight <span :class="Math.abs(templateForm.criteria.reduce((s,c)=>s+Number(c.weight||0),0)-100)>.001 ? 'text-rose-700' : 'text-emerald-700'">{{ templateForm.criteria.reduce((s,c)=>s+Number(c.weight||0),0).toFixed(2) }}%</span><span v-if="Math.abs(templateForm.criteria.reduce((s,c)=>s+Number(c.weight||0),0)-100)>.001" class="ml-2 text-xs font-normal text-rose-600">Must equal 100%</span></p>
            <div class="flex justify-end gap-2"><button type="button" @click="showTemplate=false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button><button :disabled="busy || Math.abs(templateForm.criteria.reduce((s,c)=>s+Number(c.weight||0),0)-100)>.001" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">{{ busy ? 'Saving…' : 'Save template' }}</button></div>
          </footer>
        </form>
      </div>

      <div v-if="showEvaluation && selectedEvaluation" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3" @click.self="showEvaluation=false"><section class="max-h-[94vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white p-6"><header class="flex justify-between"><div><p class="text-xs font-bold uppercase text-blue-700">{{ selectedEvaluation.reference }} · {{ selectedEvaluation.status }}</p><h2 class="mt-1 text-xl font-bold">{{ selectedEvaluation.employee_name }}</h2><p class="text-sm text-slate-500">{{ selectedEvaluation.position_name }} · {{ selectedEvaluation.department_name }} · {{ selectedEvaluation.employment_type_name }}</p><p class="mt-1 text-xs text-slate-500">{{ selectedEvaluation.template_name }} v{{ selectedEvaluation.template_version }} · {{ date(selectedEvaluation.period_start) }} – {{ date(selectedEvaluation.period_end) }} · Evaluator: {{ selectedEvaluation.evaluator_name }} · Manager: {{ selectedEvaluation.manager_name }}</p></div><button @click="showEvaluation=false"><X/></button></header><div class="mt-4 rounded-lg bg-slate-50 p-3"><div class="flex justify-between text-sm font-semibold"><span>Evaluation completion</span><span>{{ evaluationCompletion }}%</span></div><div class="mt-2 h-2 rounded bg-slate-200"><div class="h-2 rounded bg-blue-600" :style="{width:`${evaluationCompletion}%`}"/></div><div class="mt-2 flex justify-between text-sm"><span>Live weighted score</span><b>{{ evaluationScore }}%</b></div></div><div v-if="selectedEvaluation.objective_data" class="mt-4 rounded-lg border border-sky-100 bg-sky-50 p-3 text-sm"><b>Objective attendance records</b><p class="mt-1 text-xs text-slate-600">{{ selectedEvaluation.objective_data.recorded_days }} recorded days · {{ selectedEvaluation.objective_data.late_days }} late · {{ selectedEvaluation.objective_data.absent_days }} absent. Reference data only; ratings remain evaluator-entered.</p></div><div class="mt-4 space-y-3"><article v-for="criterion in selectedEvaluation.ratings" :key="criterion.id" class="rounded-xl border border-slate-200 p-4"><div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase text-blue-700">{{ criterion.category }} · {{ Number(criterion.weight).toFixed(2) }}%</p><h3 class="mt-1 font-bold">{{ criterion.criterion_name }}</h3><p class="mt-1 text-sm text-slate-600">{{ criterion.description }}</p><p class="mt-2 rounded bg-slate-50 p-2 text-xs">Standard: {{ criterion.performance_standards }}</p><p v-if="criterion.rating_guidelines" class="mt-1 text-xs text-slate-600">Rating guidance: {{ parseJson(criterion.rating_guidelines, criterion.rating_guidelines) }}</p><p v-if="criterion.required_evidence" class="mt-1 text-xs text-amber-700">Evidence required: {{ criterion.required_evidence }}</p></div><div v-if="canEvaluate(selectedEvaluation)" class="flex flex-col gap-2"><div role="group" :aria-label="`Rating for ${criterion.criterion_name}`" class="flex items-center gap-1"><button v-for="star in 5" :key="star" type="button" :aria-label="`${star} stars: ${selectedEvaluation.template_snapshot.rating_scale?.[star] || ''}`" :aria-pressed="Number(ratingForm.ratings[criterion.id].rating) === star" @click="ratingForm.ratings[criterion.id].rating = star" class="rounded p-1 focus:outline-none focus:ring-2 focus:ring-amber-400"><Star class="h-6 w-6" :class="star <= Number(ratingForm.ratings[criterion.id].rating) ? 'fill-amber-400 text-amber-500' : 'text-slate-300'"/></button></div><b class="text-right text-xs">{{ criterion.rating ? `${Number(criterion.weighted_score).toFixed(2)} weighted` : '' }}</b></div><span v-else role="img" :aria-label="`${criterion.rating || 0} out of 5 stars`" class="flex items-center gap-1"> <Star v-for="star in 5" :key="star" class="h-5 w-5" :class="star <= Number(criterion.rating) ? 'fill-amber-400 text-amber-500' : 'text-slate-300'"/></span></div><textarea v-if="canEvaluate(selectedEvaluation)" v-model="ratingForm.ratings[criterion.id].comments" rows="2" placeholder="Evaluator comment" class="mt-3 w-full rounded-lg border-slate-300 text-sm"/><p v-else-if="criterion.comments" class="mt-3 text-sm text-slate-600">{{ criterion.comments }}</p><div class="mt-3 flex flex-wrap items-center gap-3"><label v-if="canEvaluate(selectedEvaluation)" class="cursor-pointer rounded border px-3 py-1.5 text-xs font-semibold">Upload evidence<input type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xlsx,.csv" class="sr-only" @change="uploadEvidence(criterion,$event)"/></label><a v-for="file in criterion.evidence" :key="file.id" :href="evaluationHref(selectedEvaluation,file)" class="text-xs font-semibold text-blue-700 underline">{{ file.original_name }}</a></div></article></div><div class="mt-4 grid gap-3 sm:grid-cols-2"><label class="text-sm font-semibold">Evaluator comments<textarea v-model="ratingForm.comments" rows="3" :readonly="!canEvaluate(selectedEvaluation)" class="mt-1 w-full rounded border-slate-300"/></label><label class="text-sm font-semibold">Strengths<textarea v-model="ratingForm.strengths" rows="3" :readonly="!canEvaluate(selectedEvaluation)" class="mt-1 w-full rounded border-slate-300"/></label><label class="text-sm font-semibold sm:col-span-2">Development recommendations<textarea v-model="ratingForm.development_recommendations" rows="2" :readonly="!canEvaluate(selectedEvaluation)" class="mt-1 w-full rounded border-slate-300"/></label><label v-if="canEvaluate(selectedEvaluation)" class="text-sm font-semibold sm:col-span-2">Recommendation<select v-model="ratingForm.recommendation" class="mt-1 w-full rounded border-slate-300"><option value="">Select recommendation</option><option>Excellent Performance</option><option>Meets Expectations</option><option>Performance Improvement Required</option><option>Additional Training Recommended</option><option>Promotion Consideration</option></select></label><section v-if="selectedEvaluation.reviews?.length" class="sm:col-span-2 rounded-lg border border-slate-200 p-3"><b class="text-sm">Review and approval history</b><ol class="mt-2 space-y-2"><li v-for="review in selectedEvaluation.reviews" :key="review.id" class="border-l-2 border-blue-200 pl-3 text-xs"><b class="capitalize">{{ review.review_role }} · {{ review.decision }}</b><span class="ml-2 text-slate-500">{{ date(review.created_at) }}</span><p class="mt-1 text-slate-600">{{ review.comments || 'No review comments.' }} · {{ review.previous_status }} → {{ review.new_status }}</p></li></ol></section><div v-if="selectedEvaluation.status === 'Completed'" class="sm:col-span-2 rounded-lg bg-amber-50 p-3 text-sm"><b>Employee acknowledgment</b><p>{{ selectedEvaluation.acknowledged_at ? `Acknowledged ${date(selectedEvaluation.acknowledged_at)}` : 'Not yet acknowledged' }}. Acknowledgment confirms receipt, not agreement.</p></div></div><p v-if="error" class="mt-4 rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</p><p v-if="canEvaluate(selectedEvaluation) && !canSubmitEvaluation(selectedEvaluation)" class="mt-3 text-xs text-amber-700">Submission is available starting {{ date(selectedEvaluation.period_end) }}. You can save a draft until then.</p><p v-else-if="canEvaluate(selectedEvaluation) && canSubmitEvaluation(selectedEvaluation)" role="note" class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900"><strong>Warning:</strong> Submitting sends this evaluation to the reviewing manager and locks your ratings unless it is returned for revision.</p><footer class="mt-5 flex justify-end gap-2"><button @click="showEvaluation=false" class="rounded-lg border px-4 py-2 text-sm">Close</button><template v-if="canEvaluate(selectedEvaluation)"><button @click="saveRating(false)" class="rounded-lg border px-4 py-2 text-sm font-semibold">Save draft</button><button @click="saveRating(true)" :disabled="busy || !canSubmitEvaluation(selectedEvaluation)" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">Submit for manager review</button></template></footer></section></div>

            <div v-if="showReview && selectedEvaluation" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="showReview=false">
        <form @submit.prevent="submitReview" class="max-h-[92vh] w-full max-w-3xl space-y-4 overflow-y-auto rounded-2xl bg-white p-6">
          <header class="flex justify-between gap-4">
            <div><h2 class="text-xl font-bold">{{ selectedEvaluation.status === 'Submitted' ? 'Manager review' : 'KPI Final Review' }}</h2><p class="mt-1 text-sm text-slate-500">{{ selectedEvaluation.employee_name }} · {{ selectedEvaluation.position_name }} · {{ selectedEvaluation.department_name }}</p></div>
            <span v-if="selectedEvaluation.status === 'HR Review'" class="h-fit rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">Ready for approval</span>
            <button type="button" @click="showReview=false"><X/></button>
          </header>
          <section v-if="selectedEvaluation.status === 'HR Review'" class="space-y-4 rounded-xl border border-slate-200 bg-white p-4">
            <div class="grid gap-3 sm:grid-cols-2">
              <div class="rounded-lg bg-blue-50 p-3"><p class="text-xs font-semibold text-blue-700">Final weighted score</p><p class="mt-1 text-2xl font-black text-blue-950">{{ Number(selectedEvaluation.overall_score || 0).toFixed(2) }}%</p></div>
              <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs font-semibold text-slate-500">Classification</p><p class="mt-1 text-lg font-bold">{{ selectedEvaluation.classification || '—' }}</p></div>
              <div class="rounded-lg bg-slate-50 p-3 sm:col-span-2"><p class="text-xs font-semibold text-slate-500">Manager’s recommendation</p><p class="mt-1 font-semibold">{{ selectedEvaluation.recommendation || 'No recommendation recorded' }}</p></div>
            </div>
            <div>
              <h3 class="text-sm font-bold">HR verification checklist</h3>
              <div class="mt-2 grid gap-2 sm:grid-cols-2">
                <label v-for="item in hrChecklistItems" :key="item.label" class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                  <input v-if="item.key" v-model="hrChecklist[item.key]" type="checkbox" class="rounded border-slate-300 text-blue-700 focus:ring-blue-600"/>
                  <input v-else type="checkbox" :checked="item.complete" disabled class="rounded border-slate-300 text-blue-700"/>
                  <span>{{ item.label }}</span>
                </label>
              </div>
            </div>
            <div>
              <h3 class="text-sm font-bold">Supporting evidence</h3>
              <div class="mt-2 space-y-2">
                <div v-for="criterion in selectedEvaluation.ratings" :key="criterion.id" class="rounded-lg bg-slate-50 p-3">
                  <p class="text-xs font-semibold">{{ criterion.criterion_name }}</p>
                  <div v-if="criterion.evidence?.length" class="mt-1 flex flex-wrap gap-3"><a v-for="file in criterion.evidence" :key="file.id" :href="evaluationHref(selectedEvaluation,file)" class="text-xs font-semibold text-blue-700 underline">{{ file.original_name }}</a></div>
                  <p v-else class="mt-1 text-xs text-slate-500">No supporting files attached.</p>
                </div>
              </div>
            </div>
          </section>
          <div v-else class="rounded-lg bg-slate-50 p-3 text-sm"><b>{{ selectedEvaluation.classification }}</b><p class="mt-1">{{ selectedEvaluation.evaluator_comments }}</p><p class="mt-2">Strengths: {{ selectedEvaluation.strengths || '—' }}</p><p>Development: {{ selectedEvaluation.development_recommendations || '—' }}</p></div>
          <label class="block text-sm font-semibold">{{ selectedEvaluation.status === 'HR Review' ? 'HR decision' : 'Decision' }}
            <select v-model="reviewForm.decision" required class="mt-1 w-full rounded border-slate-300">
              <template v-if="selectedEvaluation.status === 'Submitted'"><option value="Approve">Approve and forward to HR</option><option value="Return">Return for revision</option><option value="Request Evidence">Request additional evidence</option></template>
              <template v-else><option value="Finalize">Approve</option><option value="Return">Return for revision</option></template>
            </select>
          </label>
          <label class="block text-sm font-semibold">{{ selectedEvaluation.status === 'HR Review' ? 'HR explanation (required)' : 'Review comments' }}<textarea v-model="reviewForm.comments" :required="selectedEvaluation.status === 'HR Review'" rows="4" class="mt-1 w-full rounded border-slate-300"/></label>
          <p v-if="selectedEvaluation.status === 'HR Review' && !hrChecklistComplete" class="text-xs text-amber-700">Complete every verification item before saving the HR decision.</p>
          <p v-if="error" class="text-sm text-rose-700">{{ error }}</p>
          <footer class="flex justify-end gap-2"><button type="button" @click="showReview=false" class="rounded border px-4 py-2">Cancel</button><button :disabled="busy || (selectedEvaluation.status === 'HR Review' && (!hrChecklistComplete || !reviewForm.comments.trim()))" class="rounded bg-blue-700 px-4 py-2 font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">Save decision</button></footer>
        </form>
      </div>
      <div v-if="toast" class="fixed bottom-5 right-5 z-[60] rounded-lg bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-lg" role="status">{{ toast }}</div>
    </main>
  </AuthenticatedLayout>
</template>

<style>
.kpi-evaluation-panel > :nth-child(2) { order: 2; }
.kpi-evaluation-panel > :nth-child(3) { order: 1; }
.kpi-evaluation-panel > :nth-child(4) { order: 3; }
.kpi-evaluation-panel > :nth-child(5) { order: 4; }

html[data-theme='dark'] .performance-kpi-page {
  color: #e4e4e7 !important;
  background-color: #09090b !important;
}
html[data-theme='dark'] .performance-kpi-page .bg-white { background-color: #18181b !important; }
html[data-theme='dark'] .performance-kpi-page .bg-slate-50,
html[data-theme='dark'] .performance-kpi-page .bg-slate-100 { background-color: #27272a !important; }
html[data-theme='dark'] .performance-kpi-page .hover\:bg-slate-100:hover,
html[data-theme='dark'] .performance-kpi-page .hover\:bg-slate-50:hover { background-color: #3f3f46 !important; }
html[data-theme='dark'] .performance-kpi-page .border-slate-100,
html[data-theme='dark'] .performance-kpi-page .border-slate-200,
html[data-theme='dark'] .performance-kpi-page .border-slate-300,
html[data-theme='dark'] .performance-kpi-page .divide-slate-100 > :not([hidden]) ~ :not([hidden]) { border-color: #3f3f46 !important; }
html[data-theme='dark'] .performance-kpi-page .text-slate-900,
html[data-theme='dark'] .performance-kpi-page .text-slate-800,
html[data-theme='dark'] .performance-kpi-page .text-slate-700 { color: #e4e4e7 !important; }
html[data-theme='dark'] .performance-kpi-page .text-slate-600,
html[data-theme='dark'] .performance-kpi-page .text-slate-500 { color: #a1a1aa !important; }
html[data-theme='dark'] .performance-kpi-page .text-slate-400 { color: #71717a !important; }
html[data-theme='dark'] .performance-kpi-page input,
html[data-theme='dark'] .performance-kpi-page select,
html[data-theme='dark'] .performance-kpi-page textarea { background-color: #27272a !important; border-color: #52525b !important; color: #f4f4f5 !important; color-scheme: dark; }
html[data-theme='dark'] .performance-kpi-page input::placeholder,
html[data-theme='dark'] .performance-kpi-page textarea::placeholder { color: #a1a1aa; }
html[data-theme='dark'] .performance-kpi-page thead { background-color: #27272a !important; }
html[data-theme='dark'] .performance-kpi-page tbody tr:hover { background-color: #27272a !important; }
html[data-theme='dark'] .performance-kpi-page .text-blue-700,
html[data-theme='dark'] .performance-kpi-page .text-blue-800 { color: #93c5fd !important; }
html[data-theme='dark'] .performance-kpi-page .text-blue-600 { color: #60a5fa !important; }
html[data-theme='dark'] .performance-kpi-page .bg-blue-50,
html[data-theme='dark'] .performance-kpi-page .bg-sky-50 { background-color: #172554 !important; }
html[data-theme='dark'] .performance-kpi-page .bg-emerald-50,
html[data-theme='dark'] .performance-kpi-page .bg-emerald-100 { background-color: #064e3b !important; }
html[data-theme='dark'] .performance-kpi-page .text-emerald-700,
html[data-theme='dark'] .performance-kpi-page .text-emerald-800 { color: #6ee7b7 !important; }
html[data-theme='dark'] .performance-kpi-page .bg-amber-50,
html[data-theme='dark'] .performance-kpi-page .bg-amber-100 { background-color: #422006 !important; }
html[data-theme='dark'] .performance-kpi-page .text-amber-700,
html[data-theme='dark'] .performance-kpi-page .text-amber-800 { color: #fcd34d !important; }
html[data-theme='dark'] .performance-kpi-page .bg-rose-50,
html[data-theme='dark'] .performance-kpi-page .bg-rose-100 { background-color: #4c0519 !important; }
html[data-theme='dark'] .performance-kpi-page .text-rose-700,
html[data-theme='dark'] .performance-kpi-page .text-rose-600 { color: #fda4af !important; }
html[data-theme='dark'] .performance-kpi-page .bg-indigo-100,
html[data-theme='dark'] .performance-kpi-page .bg-violet-100 { background-color: #312e81 !important; }
html[data-theme='dark'] .performance-kpi-page .text-indigo-800,
html[data-theme='dark'] .performance-kpi-page .text-violet-800 { color: #c4b5fd !important; }
html[data-theme='dark'] .performance-kpi-page .bg-sky-100 { background-color: #0c4a6e !important; }
html[data-theme='dark'] .performance-kpi-page .text-sky-800 { color: #7dd3fc !important; }
html[data-theme='dark'] .performance-kpi-page .kpi-evaluation-panel,
html[data-theme='dark'] .performance-kpi-page .kpi-evaluation-panel .bg-white { background-color: #18181b !important; }
</style>
