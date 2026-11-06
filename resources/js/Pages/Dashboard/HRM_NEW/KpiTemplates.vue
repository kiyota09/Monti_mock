<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Archive, CircleCheck, LayoutGrid, List, Pencil, Plus, Power, Settings, X } from 'lucide-vue-next';

const props = defineProps({
  templates: { type: Array, default: () => [] },
  departments: { type: Array, default: () => [] },
  positions: { type: Array, default: () => [] },
  rules: { type: Array, default: () => [] },
  isHr: Boolean,
});

const showTemplate = ref(false);
const showRules = ref(false);
const busy = ref(false);
const error = ref('');
const toast = ref('');
const classificationRules = ref(props.rules.map(rule => ({ minimum_score: Number(rule.minimum_score), maximum_score: Number(rule.maximum_score), label: rule.label })));
const templateItems = ref(props.templates);
const showArchivedTemplates = ref(false);
const templateView = ref('table');
const selectedTemplate = ref(null);
const todayDate = (() => { const now = new Date(); return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`; })();
const measurementMethods = [
  { value: 'Quantitative', description: 'Numerical results, percentages, and counts.' },
  { value: 'Qualitative', description: 'Work quality, behavior, and observations.' },
  { value: 'Quantitative and Qualitative', description: 'Combines numerical results and quality assessments.' },
  { value: 'Milestone-Based', description: 'Completion of assigned milestones and deliverables.' },
  { value: 'Target-Based', description: 'Progress against defined targets and goals.' },
];
const visibleTemplates = computed(() => templateItems.value.filter(template => showArchivedTemplates.value ? template.status === 'archived' : template.status !== 'archived'));
const templateForm = ref(blankTemplate());
const templatePositions = computed(() => templateForm.value.department_id
  ? props.positions.filter(position => String(position.department_id) === String(templateForm.value.department_id))
  : []);

function blankTemplate() {
  return {
    id: null, name: '', department_id: '', position_id: '', description: '', frequency: 'quarterly',
    effective_date: todayDate, status: 'active',
    rating_scale: { 1: 'Unsatisfactory', 2: 'Needs Improvement', 3: 'Meets Expectations', 4: 'Exceeds Expectations', 5: 'Outstanding' },
    criteria: [{ category: '', name: '', description: '', weight: 100, performance_standards: '', measurement_method: '', required_evidence: '', rating_guidelines: '' }],
  };
}

function parseJson(value, fallback = '') {
  if (!value) return fallback;
  if (typeof value === 'object') return value;
  try { return JSON.parse(value); } catch { return fallback; }
}

function editTemplate(template = null) {
  templateForm.value = template ? {
    id: template.id || null, name: template.name, department_id: template.department_id || '',
    position_id: template.department_id ? (template.position_id || '') : '', description: template.description || '', frequency: template.frequency,
    effective_date: effectiveDateValue(template.effective_date),
    status: template.status || 'draft', rating_scale: parseJson(template.rating_scale, blankTemplate().rating_scale),
    criteria: (template.criteria || []).map(criterion => ({
      category: criterion.category, name: criterion.name, description: criterion.description || '', weight: Number(criterion.weight),
      performance_standards: criterion.performance_standards || '', measurement_method: criterion.measurement_method || '',
      required_evidence: criterion.required_evidence || '', rating_guidelines: parseJson(criterion.rating_guidelines, ''),
    })),
  } : blankTemplate();
  error.value = '';
  showTemplate.value = true;
}

function effectiveDateValue(value) {
  const date = String(value || todayDate).slice(0, 10);
  return date < todayDate ? todayDate : date;
}

function syncTemplatePosition() {
  if (templateForm.value.position_id && !templatePositions.value.some(position => String(position.id) === String(templateForm.value.position_id))) {
    templateForm.value.position_id = '';
  }
}

function addCriterion() {
  templateForm.value.criteria.push({ category: '', name: '', description: '', weight: 0, performance_standards: '', measurement_method: '', required_evidence: '', rating_guidelines: '' });
}

function removeCriterion(index) {
  if (templateForm.value.criteria.length > 1) templateForm.value.criteria.splice(index, 1);
}

function reload() {
  window.location.reload();
}

function openTemplateDetails(template) {
  selectedTemplate.value = template;
}

async function saveTemplate() {
  error.value = '';
  busy.value = true;
  try {
    await axios.post('/dashboard/hrm/workforce/performance-kpi/templates', templateForm.value);
    showTemplate.value = false;
    reload();
  } catch (exception) {
    error.value = exception.response?.data?.message || Object.values(exception.response?.data?.errors || {}).flat()[0] || 'Could not save template.';
  } finally {
    busy.value = false;
  }
}

async function templateAction(template, action) {
  try {
    const { data } = await axios.post(`/dashboard/hrm/workforce/performance-kpi/templates/${template.id}/action`, { action });
    if (action === 'duplicate') editTemplate(data.template);
    else {
      if (action === 'activate') {
        templateItems.value.forEach(item => {
          if (item.id === template.id || (item.template_code && item.template_code === template.template_code)) {
            item.status = item.id === template.id ? 'active' : 'archived';
          }
        });
      } else template.status = action === 'deactivate' ? 'draft' : 'archived';
      toast.value = data.message;
      window.setTimeout(() => { toast.value = ''; }, 3500);
    }
  } catch (exception) {
    toast.value = exception.response?.data?.message || 'Could not update template.';
  }
}

async function saveRules() {
  try {
    await axios.post('/dashboard/hrm/workforce/performance-kpi/classifications', { rules: classificationRules.value });
    showRules.value = false;
    reload();
  } catch (exception) {
    toast.value = exception.response?.data?.message || 'Classification ranges must cover 0–100 without gaps or overlaps.';
  }
}
</script>

<template>
  <AuthenticatedLayout>
    <main class="kpi-templates-page min-h-screen bg-slate-50 p-4 text-slate-900 sm:p-7">
      <div class="mx-auto max-w-screen-2xl space-y-5">
        <header class="flex flex-wrap items-end justify-between gap-4">
          <div><p class="text-xs font-bold uppercase tracking-widest text-blue-700">HRM / Workforce / Performance Management</p><h1 class="mt-1 text-2xl font-black sm:text-3xl">KPI Templates</h1><p class="mt-2 max-w-3xl text-sm text-slate-600">Create and manage position-based KPI templates. Active templates automatically match employees with the same department and position.</p></div>
          <div v-if="isHr" class="flex flex-wrap gap-2"><button @click="showArchivedTemplates = !showArchivedTemplates" :aria-label="showArchivedTemplates ? 'Show active templates' : 'Show archived templates'" :title="showArchivedTemplates ? 'Show active templates' : 'Show archived templates'" class="inline-flex items-center rounded-lg border border-slate-200 bg-white p-2 text-slate-700 hover:bg-slate-50"><Archive class="h-4 w-4"/></button><button @click="showRules = true" aria-label="Settings" title="Settings" class="inline-flex items-center rounded-lg border border-slate-200 bg-white p-2 text-slate-700 hover:bg-slate-50"><Settings class="h-4 w-4"/></button><button @click="editTemplate()" aria-label="New template" title="New template" class="inline-flex items-center rounded-lg bg-blue-700 p-2 text-white"><Plus class="h-4 w-4"/></button></div>
        </header>

        <section class="space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-5"><div><h2 class="font-bold">Position KPI templates</h2><p class="mt-1 text-sm text-slate-500">Each saved change creates a version. Existing evaluations keep their original template and score thresholds.</p></div><div class="inline-flex rounded-lg border border-slate-200 p-1" aria-label="Template layout"><button @click="templateView = 'cards'" aria-label="Card view" title="Card view" :aria-pressed="templateView === 'cards'" :class="templateView === 'cards' ? 'bg-blue-700 text-white' : 'text-slate-600 hover:bg-slate-100'" class="rounded-md p-2"><LayoutGrid class="h-4 w-4"/></button><button @click="templateView = 'table'" aria-label="Table view" title="Table view" :aria-pressed="templateView === 'table'" :class="templateView === 'table' ? 'bg-blue-700 text-white' : 'text-slate-600 hover:bg-slate-100'" class="rounded-md p-2"><List class="h-4 w-4"/></button></div></div>
          <div v-if="templateView === 'cards'" class="grid gap-4 lg:grid-cols-2">
            <article v-for="template in visibleTemplates" :key="template.id" class="rounded-xl border border-slate-200 bg-white p-5">
              <div class="flex justify-between gap-3"><div><h3 class="mt-1 font-bold">{{ template.name }}</h3><p class="mt-1 text-xs text-slate-500">{{ template.department_name || 'Any department' }} · {{ template.position_name }} · {{ template.frequency }}</p></div><span class="h-fit rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold">{{ template.status }}</span></div>
              <p v-if="template.description" class="mt-3 text-sm text-slate-600">{{ template.description }}</p>
              <div class="mt-3 divide-y divide-slate-100"><div v-for="criterion in template.criteria" :key="criterion.id" class="flex justify-between gap-3 py-2 text-sm"><span>{{ criterion.name }}<small class="block text-xs text-slate-500">{{ criterion.performance_standards }}</small></span><b>{{ Number(criterion.weight).toFixed(2) }}%</b></div><div class="flex justify-between py-2 text-sm font-bold"><span>Total</span><span>{{ template.criteria.reduce((sum, criterion) => sum + Number(criterion.weight), 0).toFixed(2) }}%</span></div></div>
              <div v-if="isHr" class="mt-4 flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-3"><button @click="editTemplate(template)" aria-label="Edit" title="Edit" class="inline-flex items-center justify-center rounded-lg border border-blue-200 bg-blue-50 p-2 text-blue-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-100 hover:shadow focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"><Pencil class="h-4 w-4"/></button><button v-if="template.status !== 'active'" @click="templateAction(template, 'activate')" aria-label="Activate" title="Activate" class="inline-flex items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 p-2 text-emerald-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-emerald-100 hover:shadow focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"><CircleCheck class="h-4 w-4"/></button><button v-else @click="templateAction(template, 'deactivate')" aria-label="Deactivate" title="Deactivate" class="inline-flex items-center justify-center rounded-lg border border-amber-200 bg-amber-50 p-2 text-amber-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-amber-100 hover:shadow focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500"><Power class="h-4 w-4"/></button><button @click="templateAction(template, 'archive')" aria-label="Archive" title="Archive" class="inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50 p-2 text-rose-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-rose-100 hover:shadow focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500"><Archive class="h-4 w-4"/></button></div>
            </article>
            <p v-if="!visibleTemplates.length" class="rounded-xl border border-dashed p-10 text-center text-sm text-slate-500">{{ showArchivedTemplates ? 'No archived KPI templates.' : 'No KPI templates have been created.' }}</p>
          </div>
          <div v-else-if="visibleTemplates.length" class="overflow-x-auto rounded-xl border border-slate-200 bg-white"><table class="w-full min-w-[760px] text-left text-xs"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-2.5 py-2">Template</th><th class="px-2.5 py-2">Department</th><th class="px-2.5 py-2">Position</th><th class="px-2.5 py-2">Frequency</th><th class="px-2.5 py-2">Status</th><th class="px-2.5 py-2">Criteria</th><th class="px-2.5 py-2 text-right">Actions</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="template in visibleTemplates" :key="template.id" tabindex="0" class="cursor-pointer transition hover:bg-blue-50 focus:bg-blue-50 focus:outline-none" @click="openTemplateDetails(template)" @keydown.enter="openTemplateDetails(template)" @keydown.space.prevent="openTemplateDetails(template)"><td class="px-2.5 py-2"><div class="font-semibold text-slate-900">{{ template.name }}</div><div v-if="template.description" class="mt-1 max-w-xs truncate text-xs text-slate-500" :title="template.description">{{ template.description }}</div></td><td class="px-2.5 py-2">{{ template.department_name || 'Any department' }}</td><td class="px-2.5 py-2">{{ template.position_name }}</td><td class="px-2.5 py-2 capitalize">{{ template.frequency }}</td><td class="px-2.5 py-2"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold">{{ template.status }}</span></td><td class="px-2.5 py-2">{{ (template.criteria || []).length }}</td><td class="px-2.5 py-2 text-right" @keydown.stop><div v-if="isHr" class="flex flex-wrap justify-end gap-1"><button @click.stop="editTemplate(template)" aria-label="Edit" title="Edit" class="inline-flex items-center justify-center rounded-lg border border-blue-200 bg-blue-50 p-2 text-blue-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-100 hover:shadow focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"><Pencil class="h-3.5 w-3.5"/></button><button v-if="template.status !== 'active'" @click.stop="templateAction(template, 'activate')" aria-label="Activate" title="Activate" class="inline-flex items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 p-2 text-emerald-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-emerald-100 hover:shadow focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"><CircleCheck class="h-3.5 w-3.5"/></button><button v-else @click.stop="templateAction(template, 'deactivate')" aria-label="Deactivate" title="Deactivate" class="inline-flex items-center justify-center rounded-lg border border-amber-200 bg-amber-50 p-2 text-amber-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-amber-100 hover:shadow focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500"><Power class="h-3.5 w-3.5"/></button><button @click.stop="templateAction(template, 'archive')" aria-label="Archive" title="Archive" class="inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50 p-2 text-rose-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-rose-100 hover:shadow focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500"><Archive class="h-3.5 w-3.5"/></button></div><span v-else class="text-slate-400">—</span></td></tr></tbody></table></div>
          <p v-if="templateView === 'table' && !visibleTemplates.length" class="rounded-xl border border-dashed p-10 text-center text-sm text-slate-500">{{ showArchivedTemplates ? 'No archived KPI templates.' : 'No KPI templates have been created.' }}</p>
        </section>

        <p v-if="toast" role="status" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{{ toast }}</p>
      </div>

      <div v-if="selectedTemplate" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="selectedTemplate = null" @keydown.esc="selectedTemplate = null">
        <section role="dialog" aria-modal="true" :aria-label="selectedTemplate.name" class="max-h-[90vh] w-full max-w-3xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
          <header class="flex items-start justify-between border-b border-slate-100 bg-gradient-to-r from-blue-50 via-white to-white px-5 py-5 sm:px-7"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-700">KPI template details</p><h2 class="mt-1 text-xl font-bold text-slate-900">{{ selectedTemplate.name }}</h2><p class="mt-1 text-sm text-slate-500">Template overview and performance criteria</p></div><button @click="selectedTemplate = null" aria-label="Close template details" class="rounded-lg border border-slate-200 bg-white p-2 text-slate-500 shadow-sm transition hover:bg-slate-50"><X class="h-5 w-5"/></button></header>
          <div class="max-h-[calc(90vh-100px)] space-y-5 overflow-y-auto p-5 sm:p-7">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Department</p><p class="mt-1 text-sm font-semibold text-slate-800">{{ selectedTemplate.department_name || 'Any department' }}</p></div><div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Position</p><p class="mt-1 text-sm font-semibold text-slate-800">{{ selectedTemplate.position_name }}</p></div><div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Frequency</p><p class="mt-1 text-sm font-semibold capitalize text-slate-800">{{ selectedTemplate.frequency }}</p></div><div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Status</p><span class="mt-1 inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold capitalize text-emerald-700">{{ selectedTemplate.status }}</span></div></div>
            <section v-if="selectedTemplate.description" class="rounded-xl border border-slate-200 bg-slate-50/70 p-4"><h3 class="mb-1 text-sm font-bold text-slate-900">Description</h3><p class="whitespace-pre-line text-sm leading-6 text-slate-600">{{ selectedTemplate.description }}</p></section>
            <section><div class="mb-3 flex items-center justify-between"><div><h3 class="text-base font-bold text-slate-900">Performance criteria</h3><p class="mt-0.5 text-xs text-slate-500">{{ (selectedTemplate.criteria || []).length }} criteria · total weight {{ (selectedTemplate.criteria || []).reduce((sum, criterion) => sum + Number(criterion.weight || 0), 0).toFixed(2) }}%</p></div></div><div class="space-y-3"><article v-for="(criterion, index) in selectedTemplate.criteria || []" :key="criterion.id || index" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"><div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 bg-slate-50/70 px-4 py-3"><div class="flex items-start gap-3"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-xs font-bold text-blue-700">{{ String(index + 1).padStart(2, '0') }}</span><div><p class="text-[11px] font-bold uppercase tracking-wide text-blue-700">{{ criterion.category }}</p><h4 class="mt-0.5 font-bold text-slate-900">{{ criterion.name }}</h4></div></div><span class="rounded-full border border-blue-100 bg-white px-3 py-1 text-sm font-bold text-blue-800">{{ Number(criterion.weight).toFixed(2) }}%</span></div><div class="p-4"><p v-if="criterion.description" class="text-sm leading-6 text-slate-600">{{ criterion.description }}</p><div class="mt-3 grid gap-2 sm:grid-cols-2"><div v-if="criterion.performance_standards" class="rounded-lg bg-slate-50 p-3"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Performance standard</p><p class="mt-1 text-sm leading-5 text-slate-700">{{ criterion.performance_standards }}</p></div><div v-if="criterion.measurement_method" class="rounded-lg bg-slate-50 p-3"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Measurement method</p><p class="mt-1 text-sm leading-5 text-slate-700">{{ criterion.measurement_method }}</p></div><div v-if="criterion.required_evidence" class="rounded-lg bg-blue-50/70 p-3 sm:col-span-2"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Required evidence</p><p class="mt-1 text-sm leading-5 text-slate-700">{{ criterion.required_evidence }}</p></div></div></div></article><p v-if="!selectedTemplate.criteria?.length" class="rounded-xl border border-dashed p-5 text-center text-sm text-slate-500">No criteria are defined for this template.</p></div></section>
            <div class="flex justify-end border-t border-slate-100 pt-4"><button @click="selectedTemplate = null" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Close</button></div>
          </div>
        </section>
      </div>

      <div v-if="showRules" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="showRules = false">
        <section class="flex max-h-[88vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
          <header class="flex items-start justify-between border-b border-slate-100 px-4 py-3 sm:px-5"><div><h2 class="text-sm font-bold text-slate-900">Performance classification thresholds</h2><p class="mt-1 text-[11px] text-slate-500">Ranges use an inclusive minimum and exclusive maximum, except the 100% upper bound.</p></div><button @click="showRules = false" aria-label="Close settings" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"><X class="h-5 w-5"/></button></header>
          <div class="grid gap-3 overflow-y-auto p-4 sm:grid-cols-2 sm:p-5">
            <div v-for="(rule, index) in classificationRules" :key="index" class="min-w-0 rounded-xl border border-slate-200 bg-slate-50 p-3">
              <h3 class="mb-2 truncate text-[11px] font-bold text-slate-800" :title="rule.label">{{ rule.label }}</h3>
              <div class="grid grid-cols-2 gap-2">
                <label class="block min-w-0 text-[11px] font-semibold text-slate-600">Minimum<input v-model.number="rule.minimum_score" type="number" min="0" max="100" step="0.01" class="mt-1 block w-full min-w-0 rounded-lg border-slate-300 bg-white px-2 py-1.5 text-xs font-normal text-slate-900"/></label>
                <label class="block min-w-0 text-[11px] font-semibold text-slate-600">Maximum<input v-model.number="rule.maximum_score" type="number" min="0" max="100" step="0.01" class="mt-1 block w-full min-w-0 rounded-lg border-slate-300 bg-white px-2 py-1.5 text-xs font-normal text-slate-900"/></label>
                <label class="col-span-2 block min-w-0 text-[11px] font-semibold text-slate-600">Classification name<input v-model="rule.label" class="mt-1 block w-full min-w-0 rounded-lg border-slate-300 bg-white px-2 py-1.5 text-xs font-normal text-slate-900"/></label>
              </div>
            </div>
          </div>
          <footer class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3 sm:px-5"><button @click="showRules = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button><button @click="saveRules" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white">Save new rules version</button></footer>
        </section>
      </div>

      <div v-if="showTemplate" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-6" @click.self="showTemplate = false">
        <form @submit.prevent="saveTemplate" class="flex max-h-[94vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
          <header class="flex items-start justify-between border-b border-slate-100 px-5 py-4 sm:px-7"><div><p class="text-xs font-bold uppercase tracking-wider text-blue-700">KPI Templates</p><h2 class="mt-1 text-xl font-bold">{{ templateForm.id ? 'Create template version' : 'New KPI template' }}</h2><p class="mt-1 text-sm text-slate-500">Set up the template details and define criteria that total exactly 100%.</p></div><button type="button" @click="showTemplate = false" aria-label="Close template form" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"><X class="h-5 w-5"/></button></header>
          <div class="space-y-5 overflow-y-auto px-5 py-5 sm:px-7">
            <section><h3 class="mb-3 text-sm font-bold">Template details</h3><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <label class="grid gap-1 text-xs font-semibold text-slate-600">Template name<input v-model="templateForm.name" required class="rounded-lg border-slate-300 text-sm"/></label>
              <label class="grid gap-1 text-xs font-semibold text-slate-600">Department<select v-model="templateForm.department_id" required @change="syncTemplatePosition" class="rounded-lg border-slate-300 text-sm"><option value="">Select department</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select></label>
              <label class="grid gap-1 text-xs font-semibold text-slate-600">Applicable position<select v-model="templateForm.position_id" required :disabled="!templateForm.department_id" class="rounded-lg border-slate-300 text-sm disabled:cursor-not-allowed disabled:opacity-60"><option value="">{{ !templateForm.department_id ? 'Select department first' : !templatePositions.length ? 'No positions available for this department' : 'Select position' }}</option><option v-for="position in templatePositions" :key="position.id" :value="position.id">{{ position.name }}</option></select></label>
              <label class="grid gap-1 text-xs font-semibold text-slate-600">Evaluation frequency<select v-model="templateForm.frequency" class="rounded-lg border-slate-300 text-sm"><option value="quarterly">Quarterly</option><option value="semiannual">Semiannual</option><option value="annual">Annual</option></select></label>
              <label class="grid gap-1 text-xs font-semibold text-slate-600">Effective date<input v-model="templateForm.effective_date" required type="date" :min="todayDate" class="rounded-lg border-slate-300 text-sm"/></label>
              <label class="grid gap-1 text-xs font-semibold text-slate-600">Status<select v-model="templateForm.status" class="rounded-lg border-slate-300 text-sm"><option value="draft">Draft</option><option value="active">Active</option><option value="archived">Archived</option></select></label>
              <label class="grid gap-1 text-xs font-semibold text-slate-600 sm:col-span-2 lg:col-span-3">Template description<textarea v-model="templateForm.description" rows="2" class="rounded-lg border-slate-300 text-sm"/></label>
            </div></section>
            <section class="rounded-xl border border-slate-200 bg-slate-50 p-4"><h3 class="text-sm font-bold">Rating scale</h3><p class="mb-3 mt-1 text-xs text-slate-500">Rating labels are fixed for consistent evaluations.</p><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5"><label v-for="number in 5" :key="number" class="grid gap-1 text-xs font-semibold text-slate-600">Rating {{ number }}<input :value="templateForm.rating_scale[number]" readonly class="cursor-not-allowed rounded-lg border-slate-200 bg-slate-100 text-sm text-slate-500"/></label></div></section>
            <section><div class="mb-3 flex flex-wrap items-end justify-between gap-2"><div><h3 class="text-sm font-bold">Performance criteria</h3><p class="mt-1 text-xs text-slate-500">Add criteria and distribute the full template weight across them.</p></div><span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-800">Total weight: {{ templateForm.criteria.reduce((sum, criterion) => sum + Number(criterion.weight || 0), 0).toFixed(2) }}%</span></div>
              <div class="space-y-3"><article v-for="(criterion, index) in templateForm.criteria" :key="index" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div class="mb-3 flex items-center justify-between"><h4 class="text-sm font-bold">Criterion {{ index + 1 }}</h4><button type="button" @click="removeCriterion(index)" :disabled="templateForm.criteria.length <= 1" class="text-xs font-semibold text-rose-700 disabled:opacity-40">Remove</button></div><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <label class="grid min-w-0 gap-1 text-xs font-semibold text-slate-600">Category<input v-model.trim="criterion.category" required placeholder="e.g. Quality" class="block w-full min-w-0 rounded-lg border-slate-300 text-sm"/></label><label class="grid min-w-0 gap-1 text-xs font-semibold text-slate-600">Criterion name<input v-model.trim="criterion.name" required placeholder="e.g. Inventory accuracy" class="block w-full min-w-0 rounded-lg border-slate-300 text-sm"/></label><label class="grid gap-1 text-xs font-semibold text-slate-600">Weight (%)<input v-model.number="criterion.weight" required type="number" min="0.01" max="100" step="0.01" class="block w-full min-w-0 rounded-lg border-slate-300 text-sm"/></label>
                <label class="grid gap-1 text-xs font-semibold text-slate-600 sm:col-span-2 lg:col-span-3">Description<textarea v-model="criterion.description" required rows="2" class="rounded-lg border-slate-300 text-sm"/></label><label class="grid gap-1 text-xs font-semibold text-slate-600 sm:col-span-2 lg:col-span-3">Performance standard<textarea v-model="criterion.performance_standards" required rows="2" class="rounded-lg border-slate-300 text-sm"/></label><label class="grid gap-1 text-xs font-semibold text-slate-600 sm:col-span-2 lg:col-span-3">Required supporting evidence, if any<input v-model="criterion.required_evidence" class="rounded-lg border-slate-300 text-sm"/></label><label class="grid gap-1 text-xs font-semibold text-slate-600 sm:col-span-2 lg:col-span-3">Rating guidelines for this criterion<textarea v-model="criterion.rating_guidelines" rows="2" class="rounded-lg border-slate-300 text-sm"/></label>
              </div></article></div><button type="button" @click="addCriterion" class="mt-3 inline-flex items-center gap-1 rounded-lg border border-blue-200 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50"><Plus class="h-4 w-4"/>Add criterion</button></section>
            <p v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</p>
          </div>
          <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-white px-5 py-4 sm:px-7"><p class="text-sm font-semibold text-slate-600">Total weight <span :class="Math.abs(templateForm.criteria.reduce((sum, criterion) => sum + Number(criterion.weight || 0), 0) - 100) > .001 ? 'text-rose-700' : 'text-emerald-700'">{{ templateForm.criteria.reduce((sum, criterion) => sum + Number(criterion.weight || 0), 0).toFixed(2) }}%</span></p><div class="flex justify-end gap-2"><button type="button" @click="showTemplate = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold">Cancel</button><button :disabled="busy || Math.abs(templateForm.criteria.reduce((sum, criterion) => sum + Number(criterion.weight || 0), 0) - 100) > .001" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">{{ busy ? 'Saving…' : 'Save template' }}</button></div></footer>
        </form>
      </div>
    </main>
  </AuthenticatedLayout>
</template>

<style>
html[data-theme='dark'] .kpi-templates-page {
  background-color: #09090b !important;
  background-image: linear-gradient(135deg, #09090b, #18181b 58%, #172554) !important;
  color: #e4e4e7 !important;
}
html[data-theme='dark'] .kpi-templates-page .bg-white {
  background-color: #18181b !important;
  color: #e4e4e7;
}
html[data-theme='dark'] .kpi-templates-page .bg-slate-50,
html[data-theme='dark'] .kpi-templates-page .bg-slate-100 {
  background-color: #27272a !important;
}
html[data-theme='dark'] .kpi-templates-page .hover\:bg-slate-50:hover,
html[data-theme='dark'] .kpi-templates-page .hover\:bg-slate-100:hover,
html[data-theme='dark'] .kpi-templates-page tr:hover {
  background-color: #27272a !important;
}
html[data-theme='dark'] .kpi-templates-page .border-slate-100,
html[data-theme='dark'] .kpi-templates-page .border-slate-200,
html[data-theme='dark'] .kpi-templates-page .border-slate-300,
html[data-theme='dark'] .kpi-templates-page .divide-slate-100 > :not([hidden]) ~ :not([hidden]) {
  border-color: #3f3f46 !important;
}
html[data-theme='dark'] .kpi-templates-page .text-slate-900,
html[data-theme='dark'] .kpi-templates-page .text-slate-800,
html[data-theme='dark'] .kpi-templates-page .text-slate-700 {
  color: #e4e4e7 !important;
}
html[data-theme='dark'] .kpi-templates-page .text-slate-600,
html[data-theme='dark'] .kpi-templates-page .text-slate-500,
html[data-theme='dark'] .kpi-templates-page .text-slate-400 {
  color: #a1a1aa !important;
}
html[data-theme='dark'] .kpi-templates-page input,
html[data-theme='dark'] .kpi-templates-page select,
html[data-theme='dark'] .kpi-templates-page textarea {
  background-color: #27272a !important;
  border-color: #52525b !important;
  color: #f4f4f5 !important;
}
html[data-theme='dark'] .kpi-templates-page .text-blue-700,
html[data-theme='dark'] .kpi-templates-page .text-blue-800 {
  color: #93c5fd !important;
}
</style>
