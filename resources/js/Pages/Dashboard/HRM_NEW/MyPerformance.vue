<script setup>
import { ref } from 'vue';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineProps({ evaluations: { type: Array, default: () => [] } });
const openId = ref(null); const comment = ref({}); const agrees = ref({}); const error = ref(''); const message = ref(''); const saving = ref(false);
const date = value => value ? String(value).slice(0, 10) : '—';
async function acknowledge(evaluation) {
  error.value = ''; saving.value = true;
  try { await axios.post(`/dashboard/hrm/workforce/performance-kpi/evaluations/${evaluation.id}/acknowledge`, { agrees: agrees.value[evaluation.id], comments: comment.value[evaluation.id] || null }); message.value = 'Receipt acknowledged. This does not indicate agreement with every rating.'; window.location.reload(); }
  catch (e) { error.value = e.response?.data?.message || 'Could not submit acknowledgment.'; }
  finally { saving.value = false; }
}
</script>

<template>
  <AuthenticatedLayout>
    <main class="min-h-screen bg-slate-50 p-4 sm:p-7"><div class="mx-auto max-w-5xl space-y-5">
      <header><p class="text-xs font-bold uppercase tracking-widest text-blue-700">HRM / Workforce</p><h1 class="mt-1 text-2xl font-black">My Performance Results</h1><p class="mt-2 text-sm text-slate-600">Review finalized KPI evaluations, feedback, development recommendations, and your acknowledgment history.</p></header>
      <p v-if="message" class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ message }}</p><p v-if="error" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</p>
      <article v-for="evaluation in evaluations" :key="evaluation.id" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <button class="flex w-full flex-wrap items-center justify-between gap-3 p-5 text-left" @click="openId = openId === evaluation.id ? null : evaluation.id"><div><p class="text-xs font-bold text-blue-700">{{ evaluation.reference }} · {{ evaluation.status }}</p><h2 class="mt-1 font-bold">{{ evaluation.template_name }} · v{{ evaluation.template_version }}</h2><p class="mt-1 text-sm text-slate-500">{{ date(evaluation.period_start) }} – {{ date(evaluation.period_end) }} · Finalized {{ date(evaluation.completed_at) }}</p></div><div class="text-right"><p class="text-2xl font-black">{{ Number(evaluation.overall_score).toFixed(2) }}%</p><p class="text-sm font-semibold text-slate-600">{{ evaluation.classification }}</p></div></button>
        <div v-if="openId === evaluation.id" class="space-y-4 border-t border-slate-100 p-5">
          <div class="overflow-x-auto"><table class="w-full min-w-[700px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="p-3">KPI Criterion</th><th class="p-3">Weight</th><th class="p-3">Rating</th><th class="p-3">Weighted score</th><th class="p-3">Evaluator comments</th></tr></thead><tbody class="divide-y"><tr v-for="rating in evaluation.ratings" :key="rating.id"><td class="p-3"><b>{{ rating.criterion_name }}</b><small class="block text-slate-500">{{ rating.description }}</small></td><td class="p-3">{{ Number(rating.weight).toFixed(2) }}%</td><td class="p-3">{{ rating.rating }} / 5</td><td class="p-3">{{ Number(rating.weighted_score).toFixed(2) }}%</td><td class="p-3">{{ rating.comments || '—' }}</td></tr></tbody></table></div>
          <div class="grid gap-3 sm:grid-cols-3"><section class="rounded-lg bg-slate-50 p-3"><b class="text-sm">Evaluator feedback</b><p class="mt-1 whitespace-pre-wrap text-sm text-slate-600">{{ evaluation.evaluator_comments || '—' }}</p></section><section class="rounded-lg bg-slate-50 p-3"><b class="text-sm">Manager comments</b><p class="mt-1 whitespace-pre-wrap text-sm text-slate-600">{{ evaluation.manager_comments || '—' }}</p></section><section class="rounded-lg bg-slate-50 p-3"><b class="text-sm">HR comments</b><p class="mt-1 whitespace-pre-wrap text-sm text-slate-600">{{ evaluation.hr_comments || '—' }}</p></section></div>
          <section class="rounded-lg border border-blue-100 bg-blue-50 p-4"><b class="text-sm">Development recommendations</b><p class="mt-1 whitespace-pre-wrap text-sm">{{ evaluation.development_recommendations || 'No additional recommendations.' }}</p><p class="mt-3 text-xs text-slate-600">Acknowledgment confirms you received and reviewed this evaluation. It does not mean you agree with every rating.</p><template v-if="!evaluation.acknowledged_at"><div class="mt-3 grid gap-3 sm:grid-cols-[14rem_1fr]"><select v-model="agrees[evaluation.id]" class="rounded-lg border-slate-300 text-sm"><option :value="undefined">Choose response (optional)</option><option :value="true">I agree</option><option :value="false">I disagree</option></select><textarea v-model="comment[evaluation.id]" rows="2" placeholder="Feedback or appeal comments" class="rounded-lg border-slate-300 text-sm"/></div><button @click="acknowledge(evaluation)" :disabled="saving" class="mt-3 rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white">{{ saving ? 'Submitting…' : 'Acknowledge receipt' }}</button></template><p v-else class="mt-3 text-sm font-semibold text-emerald-800">Acknowledged {{ date(evaluation.acknowledged_at) }}{{ evaluation.employee_feedback ? ` · ${evaluation.employee_feedback}` : '' }}</p></section>
        </div>
      </article>
      <section v-if="!evaluations.length" class="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center"><h2 class="font-bold">No finalized evaluations yet</h2><p class="mt-1 text-sm text-slate-500">Approved results will appear here.</p></section>
    </div></main>
  </AuthenticatedLayout>
</template>
