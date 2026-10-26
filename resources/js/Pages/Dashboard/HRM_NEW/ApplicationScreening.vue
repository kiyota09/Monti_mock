<template>
  <AuthenticatedLayout>
    <main class="min-h-screen bg-slate-50 p-4 text-slate-900 md:p-8">
      <div class="mx-auto max-w-7xl">
        <button @click="router.visit('/hrm/recruitment/applications')" class="mb-5 text-sm font-semibold text-blue-700 hover:text-blue-900">← Back to Applications</button>

        <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white p-12 text-center text-slate-500">Loading applicant screening…</div>
        <div v-else-if="error" class="rounded-2xl border border-rose-200 bg-white p-8 text-rose-700">{{ error }}</div>
        <template v-else>
          <header class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
              <div class="flex items-center gap-4">
                <img v-if="profile.personal?.profile_photo_path" :src="profile.personal.profile_photo_path" class="h-14 w-14 rounded-xl object-cover" />
                <div v-else class="flex h-14 w-14 items-center justify-center rounded-xl bg-slate-800 text-xl font-bold text-white">{{ initials }}</div>
                <div>
                  <p class="text-xs font-bold uppercase tracking-widest text-blue-600">Applicant Screening · #{{ profile.application?.id }}</p>
                  <h1 class="mt-1 text-2xl font-bold">{{ fullName }}</h1>
                  <p class="mt-1 text-sm text-slate-500">{{ profile.personal?.email || 'No email' }} · {{ profile.job_posting?.title || profile.position?.name || 'Position not specified' }}</p>
                </div>
              </div>
              <span class="rounded-full bg-blue-50 px-3 py-1.5 text-sm font-semibold text-blue-700">Screening</span>
            </div>
          </header>

          <div class="grid items-start gap-5 lg:grid-cols-2">
            <section class="space-y-3">
              <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-base font-bold">Applicant Information</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                  <Info label="Position" :value="profile.job_posting?.title || profile.position?.name || profile.position?.applied" />
                  <Info label="Department" :value="profile.job_posting?.department" />
                  <Info label="Email" :value="profile.personal?.email" />
                  <Info label="Phone" :value="profile.personal?.phone" />
                  <Info label="Date of Birth" :value="formatDate(profile.personal?.birth_date)" />
                  <Info label="Place of Birth" :value="profile.personal?.place_of_birth" />
                  <Info label="Gender" :value="profile.personal?.gender" />
                  <Info label="Civil Status" :value="profile.personal?.civil_status" />
                  <Info label="Citizenship" :value="profile.personal?.citizenship" />
                  <Info label="Religion" :value="profile.personal?.religion" />
                  <Info label="Height / Weight" :value="[profile.personal?.height, profile.personal?.weight].filter(Boolean).join(' / ')" />
                  <Info label="Applied" :value="formatDate(profile.application?.application_date)" />
                </div>
              </div>

              <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-base font-bold">Address</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                  <Info label="Street Address" :value="profile.address?.street" />
                  <Info label="Barangay" :value="profile.address?.barangay" />
                  <Info label="City" :value="profile.address?.city" />
                  <Info label="Province" :value="profile.address?.province" />
                  <Info label="ZIP Code" :value="profile.address?.zip_code" />
                </div>
              </section>

              <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-base font-bold">Education</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                  <Info label="Highest Education" :value="profile.education?.highest_education" />
                  <Info label="Special Skills" :value="profile.education?.special_skills" />
                  <Info label="Elementary" :value="educationEntry(profile.education?.elementary_school, profile.education?.elementary_year)" />
                  <Info label="High School" :value="educationEntry(profile.education?.high_school, profile.education?.high_year)" />
                  <Info label="College" :value="educationEntry(profile.education?.college, profile.education?.college_year)" />
                  <Info label="Vocational" :value="educationEntry(profile.education?.vocational, profile.education?.vocational_year)" />
                </div>
              </section>

              <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-base font-bold">Work Experience</h2>
                <div v-if="profile.employment?.work_experience?.length" class="space-y-3">
                  <article v-for="(job, index) in profile.employment.work_experience" :key="index" class="rounded-lg bg-slate-50 p-3">
                    <div class="grid gap-2 sm:grid-cols-2">
                      <Info label="Position" :value="job.position" />
                      <Info label="Company" :value="job.company" />
                      <Info label="Duration" :value="job.years" />
                      <Info label="Salary" :value="job.salary" />
                    </div>
                    <p v-if="job.reason" class="mt-3 text-sm text-slate-600"><strong>Reason for leaving:</strong> {{ job.reason }}</p>
                  </article>
                </div>
                <p v-else class="text-sm text-slate-400">No work experience provided.</p>
              </section>

              <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-base font-bold">Emergency Contact</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                  <Info label="Name" :value="profile.emergency?.name" />
                  <Info label="Relationship" :value="profile.emergency?.relationship" />
                  <Info label="Phone" :value="profile.emergency?.phone" />
                  <Info label="Address" :value="profile.emergency?.address" />
                </div>
              </section>

              <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-2 text-base font-bold">Notes</h2>
                <p class="whitespace-pre-line text-[13px] leading-5 text-slate-600">{{ profile.application?.notes || 'No notes provided.' }}</p>
              </section>

              <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-2 text-base font-bold">Cover Letter</h2>
                <p class="whitespace-pre-line text-[13px] leading-5 text-slate-600">{{ profile.application?.cover_letter || 'No cover letter provided.' }}</p>
              </section>

              <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-base font-bold">Resume</h2>
                <button v-if="resumePath" @click="showResumeModal = true" class="mt-3 inline-flex rounded-lg bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100">Preview {{ profile.application?.resume_name || resumePath.split('/').pop() }}</button>
                <p v-else class="mt-2 text-sm text-slate-400">No resume uploaded.</p>
              </section>

            </section>

            <aside class="self-start rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:sticky lg:top-24">
              <h2 class="mb-3 text-base font-bold">Position Requirements</h2>
              <div class="space-y-3">
                <Requirement label="Job Description" :value="profile.position?.description || profile.job_posting?.description" />
                <Requirement label="Key Responsibilities" :value="profile.position?.responsibilities" />
                <Requirement label="Qualifications" :value="profile.position?.qualifications" />
                <Requirement label="Required Skills" :value="profile.position?.required_skills" />
              </div>
            </aside>

            <div class="fixed bottom-20 right-4 z-[60] w-[calc(100vw-2rem)] max-w-[360px] md:bottom-6 md:right-8">
              <button
                type="button"
                @click="showScreeningReview = !showScreeningReview"
                :aria-expanded="showScreeningReview"
                aria-label="Toggle Screening Review"
                title="Screening Review"
                class="ml-auto flex h-14 w-14 items-center justify-center rounded-full bg-blue-700 text-white shadow-lg shadow-blue-900/20 ring-4 ring-white transition hover:scale-105 hover:bg-blue-800"
                :class="{ 'animate-bounce': !showScreeningReview }"
              >
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M9 5.5h10M9 12h10M9 18.5h10" />
                  <path d="m3.5 5.5 1.2 1.2 2-2.2M3.5 12l1.2 1.2 2-2.2M3.5 18.5l1.2 1.2 2-2.2" />
                </svg>
              </button>

              <section v-if="showScreeningReview" class="absolute bottom-[calc(100%+0.75rem)] right-0 max-h-[calc(100vh-11rem)] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl animate-modalIn">
                <header class="sticky top-0 z-10 -mx-4 -mt-4 mb-2 bg-white px-4 pt-4">
                  <div class="flex items-center justify-between gap-2">
                    <h2 class="text-base font-bold">Screening Review</h2>
                    <button type="button" @click="showScreeningReview = false" class="rounded-lg px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-100">Close</button>
                  </div>
                  <p class="mt-0.5 text-xs text-slate-500">Review and record your assessment.</p>
                  <p class="pb-2 pt-1 text-[11px] font-medium text-emerald-700" aria-live="polite">{{ draftSaveMessage }}</p>
                </header>
                <div class="space-y-1">
                  <label v-for="item in checklistItems" :key="item.key" class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-slate-50">
                    <input v-model="checks[item.key]" @change="scheduleDraftSave" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600" />
                    <span class="text-xs font-medium leading-4 text-slate-700">{{ item.label }}</span>
                  </label>
                </div>
                <button @click="showNotesModal = true" class="mt-3 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:border-blue-300 hover:bg-blue-50">
                  Screening Notes
                  <span class="mt-0.5 block truncate text-[11px] font-normal text-slate-500">{{ notes.trim() || 'Click to add internal notes' }}</span>
                </button>
                <fieldset class="mt-3">
                  <legend class="text-xs font-semibold text-slate-700">Decision</legend>
                  <div class="mt-1.5 flex gap-4">
                    <label class="flex items-center gap-1.5 text-xs font-medium text-emerald-700"><input v-model="decision" type="radio" value="passed" /> Pass</label>
                    <label class="flex items-center gap-1.5 text-xs font-medium text-rose-700"><input v-model="decision" type="radio" value="rejected" /> Reject</label>
                  </div>
                </fieldset>
                <div v-if="decision === 'rejected'" class="mt-3">
                  <label class="block text-xs font-semibold text-slate-700">Rejection Reason <span class="text-rose-600">*</span></label>
                  <textarea v-model="rejectionReason" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 p-2.5 text-xs focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-100" placeholder="Explain the reason for rejection"></textarea>
                </div>
                <p v-if="formError" class="mt-3 rounded-lg bg-rose-50 p-2.5 text-xs text-rose-700">{{ formError }}</p>
                <button @click="submitScreening" :disabled="submitting" class="mt-3 w-full rounded-lg bg-blue-700 px-3 py-2.5 text-xs font-bold text-white hover:bg-blue-800 disabled:opacity-50">{{ submitting ? 'Submitting…' : 'Submit Screening' }}</button>
              </section>
            </div>
          </div>

          <div v-if="showResumeModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/70 p-3 md:p-6" @click.self="showResumeModal = false">
            <section class="flex h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
              <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div class="min-w-0">
                  <h2 class="truncate text-base font-bold text-slate-900">{{ profile.application?.resume_name || resumePath?.split('/').pop() || 'Resume Preview' }}</h2>
                  <p class="text-xs text-slate-500">{{ fullName }}</p>
                </div>
                <button @click="showResumeModal = false" class="ml-4 rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Close</button>
              </header>
              <iframe :src="resumeUrl(resumePath)" title="Applicant resume preview" class="min-h-0 flex-1 bg-slate-100"></iframe>
            </section>
          </div>

          <div v-if="showNotesModal" class="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/60 p-4" @click.self="showNotesModal = false">
            <section class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl">
              <header class="mb-4 flex items-center justify-between">
                <div>
                  <h2 class="text-lg font-bold text-slate-900">Screening Notes</h2>
                  <p class="mt-1 text-sm text-slate-500">These notes will be saved with the screening decision.</p>
                </div>
                <button @click="showNotesModal = false" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Close</button>
              </header>
              <textarea v-model="notes" @input="scheduleDraftSave" rows="8" autofocus class="w-full rounded-xl border border-slate-200 p-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" placeholder="Enter internal screening notes…"></textarea>
              <div class="mt-4 flex justify-end">
                <button @click="showNotesModal = false" class="rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-800">Done</button>
              </div>
            </section>
          </div>
        </template>
      </div>
    </main>
  </AuthenticatedLayout>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({ applicationId: { type: Number, required: true }, jobApplicationId: { type: Number, default: null } });
const profile = ref({});
const loading = ref(true);
const submitting = ref(false);
const showScreeningReview = ref(false);
const showResumeModal = ref(false);
const showNotesModal = ref(false);
const error = ref('');
const formError = ref('');
const notes = ref('');
const draftSaveMessage = ref('Changes save automatically.');
const decision = ref('');
const rejectionReason = ref('');
const checks = reactive({ resume_reviewed: false, education_verified: false, work_experience_reviewed: false, skills_reviewed: false, qualifications_met: false, applicant_info_reviewed: false });
const checklistItems = [
  { key: 'resume_reviewed', label: 'Resume reviewed' },
  { key: 'education_verified', label: 'Education verified' },
  { key: 'work_experience_reviewed', label: 'Work experience reviewed' },
  { key: 'skills_reviewed', label: 'Skills reviewed' },
  { key: 'applicant_info_reviewed', label: 'Applicant information reviewed' },
];
let draftSaveTimer;
const scheduleDraftSave = () => {
  clearTimeout(draftSaveTimer);
  draftSaveMessage.value = 'Saving changes…';
  draftSaveTimer = setTimeout(saveDraft, 350);
};
const saveDraft = async () => {
  try {
    await axios.post(`/hrm/recruitment/applications/${props.applicationId}/screening-draft`, {
      ...checks,
      notes: notes.value,
      job_application_id: props.jobApplicationId,
    });
    draftSaveMessage.value = 'All screening review changes are saved.';
  } catch (e) {
    draftSaveMessage.value = e.response?.data?.message || 'Could not auto-save changes. Please try again.';
  }
};
const fullName = computed(() => [profile.value.personal?.first_name, profile.value.personal?.middle_name, profile.value.personal?.last_name].filter(Boolean).join(' ') || 'Applicant');
const initials = computed(() => fullName.value.split(/\s+/).map((part) => part[0]).join('').slice(0, 2).toUpperCase());
const resumePath = computed(() => profile.value.application?.resume_path);
const Info = defineComponent({ props: { label: String, value: [String, Number] }, setup(p) { return () => h('div', [h('p', { class: 'text-[10px] font-semibold uppercase tracking-wide text-slate-400' }, p.label), h('p', { class: 'mt-0.5 text-[13px] leading-5 text-slate-700' }, p.value || 'Not provided')]); } });
const Requirement = defineComponent({ props: { label: String, value: String }, setup(p) { return () => h('div', [h('h3', { class: 'text-[11px] font-bold uppercase tracking-wide text-slate-500' }, p.label), h('p', { class: 'mt-0.5 whitespace-pre-line text-[13px] leading-5 text-slate-700' }, p.value || 'No details provided.')]); } });
const educationEntry = (school, year) => [school, year].filter(Boolean).join(' · ') || 'Not provided';
const requestParams = computed(() => props.jobApplicationId ? { job_application_id: props.jobApplicationId } : {});
const loadProfile = async () => {
  const response = await axios.get(`/hrm/recruitment/applications/${props.applicationId}/profile`, { params: requestParams.value });
  profile.value = response.data;
};
const formatDate = (value) => value ? new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' }) : 'Not provided';
const resumeUrl = (path) => /^https?:\/\//.test(path) || path.startsWith('/storage/') ? path : `/storage/${path}`;

onMounted(async () => {
  try {
    await loadProfile();
    if (profile.value.application?.status !== 'Screening') {
      await axios.post(`/hrm/recruitment/applications/${props.applicationId}/start-screening`, requestParams.value);
      await loadProfile();
    }
    const saved = profile.value.application?.screening;
    if (saved) {
      checklistItems.forEach(({ key }) => { checks[key] = !!saved[key]; });
      notes.value = saved.notes || '';
      draftSaveMessage.value = 'Saved screening review loaded.';
      if (['passed', 'rejected'].includes(saved.result)) decision.value = saved.result;
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not load this screening. Please return to Applications and try again.';
  } finally {
    loading.value = false;
  }
});

const submitScreening = async () => {
  formError.value = '';
  if (!decision.value) return formError.value = 'Select Pass or Reject before submitting.';
  if (!Object.values(checks).some(Boolean)) return formError.value = 'Complete at least one screening checklist item.';
  if (decision.value === 'rejected' && !rejectionReason.value.trim()) return formError.value = 'Provide a rejection reason.';
  submitting.value = true;
  try {
    await axios.post(`/hrm/recruitment/applications/${props.applicationId}/screening`, {
      ...checks, result: decision.value, notes: notes.value,
      rejection_reason: decision.value === 'rejected' ? rejectionReason.value : null,
      job_application_id: props.jobApplicationId,
    });
    router.visit('/hrm/recruitment/applications');
  } catch (e) {
    formError.value = e.response?.data?.message || 'Could not submit screening. Please try again.';
  } finally {
    submitting.value = false;
  }
};
</script>
