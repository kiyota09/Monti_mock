<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const props = defineProps<{
    application: Record<string, any>;
    posting: Record<string, any> | null;
    interviews: Array<Record<string, any>>;
    timeline: Array<Record<string, any>>;
}>();

const withdrawing = ref(false);

const withdraw = () => {
    if (!confirm(`Cancel your application for "${props.application?.job_title}"?`)) return;
    withdrawing.value = true;
    router.post(route('applicant.applications.withdraw', props.application.id), {}, {
        onSuccess: () => toast.success('Application cancelled.'),
        onError: (e) => toast.error(String(Object.values(e || {})[0] || 'Failed to cancel.')),
        onFinish: () => { withdrawing.value = false; },
    });
};

const canWithdraw = ['Submitted', 'Screening', 'Shortlisted', 'Interview', 'Interview Scheduled', 'Initial Interview', 'Final Interview', 'Interviewed']
    .includes(props.application?.status);

// URL of the resume submitted for THIS job (stored as a raw storage path).
const resumeUrl = (() => {
    const p = props.application?.resume_path;
    if (!p) return null;
    if (p.startsWith('http://') || p.startsWith('https://')) return p;
    if (p.startsWith('/storage/')) return p;
    return `/storage/${p}`;
})();

const row = 'flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4 py-2.5 border-b border-slate-100 dark:border-slate-700/50 last:border-0';
const label = 'text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 sm:w-44 shrink-0';
const value = 'text-sm text-slate-800 dark:text-slate-200';
</script>

<template>
    <Head :title="(application?.job_title || 'Application') + ' - Monti ERP'" />

    <AuthenticatedLayout>
        <div class="max-w-4xl mx-auto">
            <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                        Application <span class="text-blue-600">Details</span>
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">
                        {{ application?.job_title || 'Position' }}
                        <span v-if="application?.application_date"> • Applied {{ application.application_date }}</span>
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <Link
                        :href="route('applicant.applications.index')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-sm font-bold rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 transition active:scale-95"
                    >
                        ← Back
                    </Link>
                    <button
                        v-if="application?.status && canWithdraw"
                        @click="withdraw"
                        :disabled="withdrawing"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-slate-800 text-rose-600 dark:text-rose-400 text-sm font-bold rounded-xl border border-rose-200 dark:border-rose-800 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition active:scale-95 disabled:opacity-50"
                    >
                        {{ withdrawing ? 'Cancelling...' : 'Cancel' }}
                    </button>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ application?.job_title }}</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                Applied {{ application?.application_date }}
                                <span v-if="posting?.department"> • {{ posting.department }}</span>
                                <span v-if="posting?.employment_type"> • {{ posting.employment_type }}</span>
                            </p>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                            {{ application?.status }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="mt-4 bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="p-6">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-2">My Submission for This Job</h2>
                    <div :class="row">
                        <span :class="label">Resume</span>
                        <span :class="value">
                            <a v-if="resumeUrl" :href="resumeUrl" target="_blank" class="font-bold text-blue-600 dark:text-blue-400 hover:underline">
                                {{ application?.resume_name || 'View resume' }}
                            </a>
                            <span v-else class="text-slate-400">No resume attached</span>
                        </span>
                    </div>
                    <div v-if="application?.cover_letter" :class="row"><span :class="label">Cover Letter</span><span :class="value">{{ application.cover_letter }}</span></div>
                </div>
            </div>

            <div v-if="posting" class="mt-4 bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="p-6">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-2">Position Details</h2>
                    <div :class="row"><span :class="label">Work Location</span><span :class="value">{{ posting.work_location || 'N/A' }}</span></div>
                    <div :class="row"><span :class="label">Work Mode</span><span :class="value">{{ posting.work_mode || 'N/A' }}</span></div>
                    <div v-if="posting.description" :class="row"><span :class="label">Job Description</span><span :class="value">{{ posting.description }}</span></div>
                    <div v-if="posting.responsibilities" :class="row"><span :class="label">Responsibilities</span><span :class="value">{{ posting.responsibilities }}</span></div>
                    <div v-if="posting.qualifications" :class="row"><span :class="label">Qualifications</span><span :class="value">{{ posting.qualifications }}</span></div>
                    <div v-if="posting.required_skills" :class="row"><span :class="label">Required Skills</span><span :class="value">{{ posting.required_skills }}</span></div>
                </div>
            </div>

            <div class="mt-4 bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="p-6">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-2">Interviews ({{ interviews.length }})</h2>
                    <div v-if="!interviews.length" class="text-xs text-slate-400 dark:text-slate-500">No interviews scheduled yet.</div>
                    <div v-for="iv in interviews" :key="iv.id" class="py-2.5 border-b border-slate-100 dark:border-slate-700/50 last:border-0 text-sm">
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ iv.type || 'Interview' }}</span>
                        <span class="text-slate-500 dark:text-slate-400"> — {{ iv.date || 'TBA' }} {{ iv.start_time || '' }}</span>
                        <span class="ml-2 px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">{{ iv.status }}</span>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
