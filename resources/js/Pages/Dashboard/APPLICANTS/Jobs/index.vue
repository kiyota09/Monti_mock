<script setup lang="ts">
// ============================================
// FILE LOCATION: resources/js/Pages/Dashboard/APPLICANTS/Jobs/index.vue
// PURPOSE: Job opportunities listing with application form modal including resume selection
// ============================================

import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { Briefcase, Clock, FileText, Calendar, GraduationCap, Monitor, ClipboardList, Search } from 'lucide-vue-next';

// ============================================
// PROPS
// ============================================
const props = defineProps<{
    jobPostings: Array<{
        id: number;
        posting_id: string;
        title: string;
        department: string;
        employment_type: string;
        work_location: string;
        work_mode: string;
        shift?: string | null;
        salary_min: number | null;
        salary_max: number | null;
        salary_visibility: string;
        vacancies: number;
        filled_vacancies: number;
        closing_date: string | null;
        published_date: string | null;
        created_at: string;
        description?: string;
        responsibilities?: string;
        qualifications?: string;
        required_skills?: string;
        has_applied?: boolean;
        application_status?: string;
        application_id?: number;
        application_date?: string;
        is_withdrawn?: boolean;
        is_rejected?: boolean;
        reapply_blocked?: boolean;
        can_reapply_at?: string | null;
        failed_at?: string | null;
        failed_stage?: string | null;
    }>;
    filters: {
        departments: string[];
        employment_types: string[];
        work_locations: string[];
        work_modes: string[];
    };
    searchParams?: {
        q: string;
        department: string;
        employment_type: string;
        work_location: string;
        work_mode: string;
    };
    profileCompletion?: {
        percentage: number;
        is_complete: boolean;
        missing_fields: string[];
    };
    applicantResumes?: string[];
}>();

// ============================================
// STATE
// ============================================
const searchQuery = ref(props.searchParams?.q || '');
const selectedDepartment = ref(props.searchParams?.department || 'All');
const selectedType = ref(props.searchParams?.employment_type || 'All');
const selectedLocation = ref(props.searchParams?.work_location || 'All');
const selectedWorkMode = ref(props.searchParams?.work_mode || 'All');
const viewMode = ref<'grid' | 'list'>('grid');

// ============================================
// MODAL STATE
// ============================================
const showModal = ref(false);
const selectedJob = ref<any>(null);
const detailLoading = ref(false);

// ============================================
// APPLICATION FORM MODAL STATE
// ============================================
const showApplicationModal = ref(false);
const applyingJob = ref<any>(null);
const isSubmitting = ref(false);

// Resume upload state
const selectedResume = ref<string>('');
const newResumeFile = ref<File | null>(null);
const resumeUploading = ref(false);

// Application Form
const applicationForm = useForm({
    notice_period: '30_Days',
    availability_date: '',
    cover_letter: '',
    resume_path: '',
});

// Notice Period Options
const noticePeriodOptions = [
    { value: 'Immediate', label: 'Immediate' },
    { value: '15_Days', label: '15 Days' },
    { value: '30_Days', label: '30 Days' },
    { value: '60_Days', label: '60 Days' },
];

// ============================================
// NOTICE PERIOD -> AVAILABILITY DATE RULE
// Dates from today up to (today + notice days) must be unclickable.
// e.g. 30 Days => min selectable date = today + 30 days.
// ============================================
const noticePeriodDaysMap: Record<string, number> = {
    Immediate: 0,
    '15_Days': 15,
    '30_Days': 30,
    '60_Days': 60,
};

function toLocalDateString(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

const minAvailabilityDate = computed(() => {
    const days = noticePeriodDaysMap[applicationForm.notice_period] ?? 0;
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    d.setDate(d.getDate() + days);
    return toLocalDateString(d);
});

const minAvailabilityLabel = computed(() => {
    try {
        return new Date(minAvailabilityDate.value + 'T00:00:00').toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    } catch {
        return minAvailabilityDate.value;
    }
});

// When notice period changes, clear an availability date that is now too early.
watch(
    () => applicationForm.notice_period,
    () => {
        if (
            applicationForm.availability_date &&
            applicationForm.availability_date < minAvailabilityDate.value
        ) {
            applicationForm.availability_date = '';
            toast.info(
                `Availability date reset. Earliest allowed date is ${minAvailabilityLabel.value} for ${getNoticePeriodLabel(applicationForm.notice_period)} notice.`
            );
        }
    }
);

// ============================================
// PROFILE COMPLETION MODAL
// ============================================
const showProfileModal = ref(false);
const profileMessage = ref('');
const profileJobId = ref<number | null>(null);

// ============================================
// CONFIRMATION MODAL
// ============================================
const showConfirmModal = ref(false);
const confirmJob = ref<any>(null);
const isApplying = ref(false);

// ============================================
// SUCCESS MODAL
// ============================================
const showSuccessModal = ref(false);
const successJob = ref<any>(null);

// ============================================
// COMPUTED - Filtered Jobs
// ============================================
const filteredJobs = computed(() => {
    return props.jobPostings || [];
});

// ============================================
// METRICS
// ============================================
const metrics = computed(() => {
    const total = props.jobPostings?.length || 0;
    const urgent = props.jobPostings?.filter((j: any) => j.hiring_priority === 'Urgent').length || 0;
    const remote = props.jobPostings?.filter((j: any) => j.work_mode === 'Remote').length || 0;
    const fullTime = props.jobPostings?.filter((j: any) => j.employment_type === 'Full-time').length || 0;

    return [
        { label: 'Total Openings', value: total, subtext: 'Active positions', tone: 'navy' },
        { label: 'Urgent Hiring', value: urgent, subtext: 'Priority positions', tone: 'danger' },
        { label: 'Remote Available', value: remote, subtext: 'Work from home', tone: 'info' },
        { label: 'Full Time', value: fullTime, subtext: 'Permanent roles', tone: 'gold' },
    ];
});

// ============================================
// HELPER FUNCTIONS
// ============================================

function getRelativeTime(dateString: string | null): string {
    if (!dateString) return 'Recently';

    try {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();

        if (diffMs < 0) return 'Just now';

        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        const diffMonths = Math.floor(diffDays / 30);
        const diffYears = Math.floor(diffDays / 365);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return diffMins + 'm ago';
        if (diffHours < 24) return diffHours + 'h ago';
        if (diffDays < 7) return diffDays + 'd ago';
        if (diffDays < 30) return Math.floor(diffDays / 7) + 'w ago';
        if (diffMonths < 12) return diffMonths + 'mo ago';
        return diffYears + 'y ago';
    } catch (e) {
        return 'Recently';
    }
}

function getEmploymentTypeColor(type: string): string {
    const colors: Record<string, string> = {
        'Full-time': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'Part-time': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'Contract': 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        'Temporary': 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
        'Internship': 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
        'Freelance': 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
    };
    return colors[type] || 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
}

function getEmploymentTypeIcon(type: string): any {
    const icons: Record<string, any> = {
        'Full-time': Briefcase,
        'Part-time': Clock,
        'Contract': FileText,
        'Temporary': Calendar,
        'Internship': GraduationCap,
        'Freelance': Monitor,
    };
    return icons[type] || ClipboardList;
}

function getWorkModeIcon(mode: string): string {
    const icons: Record<string, string> = {
        'On-site': '🏢',
        'Remote': '🏠',
        'Hybrid': '🔄',
    };
    return icons[mode] || '📍';
}

function formatSalary(min: number | null, max: number | null, visibility: string): string {
    if (visibility === 'Hide') return 'Confidential';
    if (visibility === 'Show on Application') return 'Disclosed upon application';
    if (min && max) return `₱${Number(min).toLocaleString()} - ₱${Number(max).toLocaleString()}`;
    if (min) return `From ₱${Number(min).toLocaleString()}`;
    if (max) return `Up to ₱${Number(max).toLocaleString()}`;
    return 'Negotiable';
}

function getAvailableVacancies(job: any): number {
    return (job.vacancies || 0) - (job.filled_vacancies || 0);
}

function getStatusBadge(job: any): string {
    const available = getAvailableVacancies(job);
    if (available <= 0) return 'Filled';
    if (isExpired(job)) return 'Expired';
    return 'Open';
}

// Expired = closing date is before today (date-only compare, so the
// closing day itself is still open). Expired jobs cannot be applied to.
function isExpired(job: any): boolean {
    if (!job?.closing_date) return false;
    const now = new Date();
    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    return String(job.closing_date).slice(0, 10) < today;
}

function getStatusColor(job: any): string {
    const status = getStatusBadge(job);
    const colors: Record<string, string> = {
        'Open': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'Filled': 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
        'Expired': 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
    };
    return colors[status] || 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
}

function getApplicationStatusBadge(status?: string): string {    const colors: Record<string, string> = {
        'Submitted': 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        'Screening': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'Shortlisted': 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
        'Interview Scheduled': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'Initial Interview': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'Final Interview': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'Interviewed': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'Selected': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'Job Offer': 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        'Offer Accepted': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'Pre-employment': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'Onboard': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'Onboarding': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'Hired': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'Rejected': 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
        'Withdrawn': 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    };
    return colors[status || ''] || 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
}

// ============================================
// 6-MONTH RE-APPLICATION COOLDOWN (interview/training failure)
// Backend sends reapply_blocked + can_reapply_at per posting.
// ============================================
function formatReapplyDate(dateString?: string | null): string {
    if (!dateString) return '';
    try {
        return new Date(dateString + 'T00:00:00').toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    } catch {
        return dateString;
    }
}

function cooldownNotice(job: any): string {
    const date = formatReapplyDate(job?.can_reapply_at);
    const stage = job?.failed_stage ? ` (${job.failed_stage} interview)` : '';
    return `You failed${stage} for this position. Please wait 6 months before applying again${date ? ` — you may re-apply after ${date}` : ''}. You may still apply to other positions.`;
}

// Get resume file name from path
function getResumeFileName(path: string): string {
    if (!path) return '';
    return path.split('/').pop() || '';
}

function viewJobDetails(job: any) {
    detailLoading.value = true;
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
        selectedJob.value = job;
        showModal.value = true;
        detailLoading.value = false;
    }, 500);
}

function closeModal() {
    showModal.value = false;
    selectedJob.value = null;
    document.body.style.overflow = '';
}

function closeProfileModal() {
    showProfileModal.value = false;
    profileMessage.value = '';
    profileJobId.value = null;
}

function closeConfirmModal() {
    showConfirmModal.value = false;
    confirmJob.value = null;
    isApplying.value = false;
}

function closeSuccessModal() {
    showSuccessModal.value = false;
    successJob.value = null;
}

// ============================================
// APPLICATION FORM MODAL FUNCTIONS
// ============================================

function openApplicationModal(job: any) {
    applyingJob.value = job;
    // Reset form
    applicationForm.notice_period = '30_Days';
    applicationForm.availability_date = '';
    applicationForm.cover_letter = '';
    applicationForm.resume_path = '';
    selectedResume.value = '';
    newResumeFile.value = null;
    showApplicationModal.value = true;
    document.body.style.overflow = 'hidden';
}

function closeApplicationModal() {
    showApplicationModal.value = false;
    applyingJob.value = null;
    document.body.style.overflow = '';
    applicationForm.reset();
    selectedResume.value = '';
    newResumeFile.value = null;
}

// Handle resume selection from existing resumes
function selectExistingResume(event: Event) {
    const select = event.target as HTMLSelectElement;
    selectedResume.value = select.value;
    applicationForm.resume_path = select.value;
}

// Handle new resume file upload
function handleNewResumeUpload(event: Event) {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
        const file = input.files[0];
        // Validate file type
        const validTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!validTypes.includes(file.type)) {
            toast.error('Please upload a PDF, DOC, or DOCX file.');
            input.value = '';
            return;
        }
        // Validate file size (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
            toast.error('File size must be less than 5MB.');
            input.value = '';
            return;
        }
        newResumeFile.value = file;
        // Clear existing resume selection
        selectedResume.value = '';
        applicationForm.resume_path = '';
        toast.success(`"${file.name}" selected for upload.`);
    }
}

// Remove selected new resume
function removeNewResume() {
    newResumeFile.value = null;
    const input = document.getElementById('new_resume_input') as HTMLInputElement;
    if (input) input.value = '';
}

function submitApplication() {
    // Validate
    if (!applicationForm.availability_date) {
        toast.error('Please select your availability/start date.');
        return;
    }

    // Enforce notice-period rule: availability cannot be earlier than today + notice days.
    if (applicationForm.availability_date < minAvailabilityDate.value) {
        toast.error(
            `For ${getNoticePeriodLabel(applicationForm.notice_period)} notice, the earliest available date is ${minAvailabilityLabel.value}.`
        );
        applicationForm.availability_date = '';
        return;
    }

    // Validate resume
    if (!selectedResume.value && !newResumeFile.value) {
        toast.error('Please select or upload a resume.');
        return;
    }

    // Show confirmation modal first
    showApplicationModal.value = false;
    document.body.style.overflow = '';
    
    // Set confirm job and show confirmation
    confirmJob.value = applyingJob.value;
    showConfirmModal.value = true;
    document.body.style.overflow = 'hidden';
}

// ============================================
// VIEW APPLICATION - Redirect to My Applications
// ============================================
function viewApplication(job: any) {
    router.visit(route('applicant.applications.index'));
}

// ============================================
// APPLY WITH PROFILE CHECK
// ============================================
function applyForJob(job: any) {
    // Expired postings cannot accept (re-)applications.
    if (isExpired(job)) {
        toast.error('This job posting has expired and is no longer accepting applications.');
        return;
    }

    // 6-month cooldown after an interview/training failure for this job.
    if (job.reapply_blocked) {
        toast.error(cooldownNotice(job));
        return;
    }

    // If withdrawn or rejected, open application form (re-apply)
    if (job.is_withdrawn || job.is_rejected) {
        openApplicationModal(job);
        return;
    }

    // Check if already applied (not withdrawn/rejected)
    if (job.has_applied) {
        viewApplication(job);
        return;
    }

    // Check if profile is complete
    if (!props.profileCompletion?.is_complete) {
        const missingFields = props.profileCompletion?.missing_fields || [];
        const fieldList = missingFields.join(', ');

        profileMessage.value = `Please complete your profile before applying. Missing: ${fieldList}`;
        profileJobId.value = job.id;
        showProfileModal.value = true;
        return;
    }

    // Open application form modal
    openApplicationModal(job);
}

// Confirm and submit application with form data
function confirmApply() {
    if (!confirmJob.value) return;

    isApplying.value = true;

    // Prepare form data
    const formData = new FormData();
    formData.append('notice_period', applicationForm.notice_period);
    formData.append('availability_date', applicationForm.availability_date);
    formData.append('cover_letter', applicationForm.cover_letter || '');
    
    // Handle resume - either selected existing or new upload
    if (newResumeFile.value) {
        formData.append('resume_file', newResumeFile.value);
    } else if (selectedResume.value) {
        formData.append('resume_path', selectedResume.value);
    }

    // Submit with form data
    const appliedJob = confirmJob.value;
    router.post(route('applicant.jobs.storeApplication', appliedJob.id), formData, {
        preserveScroll: true,
        onSuccess: (response) => {
            isApplying.value = false;
            closeConfirmModal();
            closeModal();
            closeApplicationModal();

            successJob.value = appliedJob;
            const jobIndex = props.jobPostings.findIndex(j => j.id === appliedJob.id);
            if (jobIndex !== -1) {
                const updatedJob = { ...props.jobPostings[jobIndex] };
                updatedJob.has_applied = true;
                updatedJob.application_status = 'Submitted';
                updatedJob.application_date = new Date().toISOString();
                updatedJob.is_withdrawn = false;
                updatedJob.is_rejected = false;
                updatedJob.reapply_blocked = false;
                updatedJob.can_reapply_at = null;
                updatedJob.failed_at = null;
                updatedJob.failed_stage = null;
                props.jobPostings[jobIndex] = updatedJob;
            }
            showSuccessModal.value = true;
            
            // Reset form after success
            applicationForm.reset();
            selectedResume.value = '';
            newResumeFile.value = null;
        },
        onError: (errors) => {
            isApplying.value = false;
            const errorMsg = Object.values(errors)[0] || 'Failed to submit application. Please try again.';
            toast.error(errorMsg);
        }
    });
}

function goToProfile() {
    closeProfileModal();
    router.visit('/applicant/profile');
}

// ============================================
// SEARCH FUNCTION
// ============================================
function performSearch() {
    const params: any = {};

    if (searchQuery.value) params.q = searchQuery.value;
    if (selectedDepartment.value && selectedDepartment.value !== 'All') params.department = selectedDepartment.value;
    if (selectedType.value && selectedType.value !== 'All') params.employment_type = selectedType.value;
    if (selectedLocation.value && selectedLocation.value !== 'All') params.work_location = selectedLocation.value;
    if (selectedWorkMode.value && selectedWorkMode.value !== 'All') params.work_mode = selectedWorkMode.value;

    router.get(route('applicant.jobs.search'), params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function resetFilters() {
    searchQuery.value = '';
    selectedDepartment.value = 'All';
    selectedType.value = 'All';
    selectedLocation.value = 'All';
    selectedWorkMode.value = 'All';
    performSearch();
}

// Watch for filter changes with debounce
let searchTimeout: any = null;
watch([searchQuery, selectedDepartment, selectedType, selectedLocation, selectedWorkMode], () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        performSearch();
    }, 400);
});

// ============================================
// NOTICE PERIOD HELPER
// ============================================
function getNoticePeriodLabel(value: string): string {
    const map: Record<string, string> = {
        'Immediate': 'Immediate',
        '15_Days': '15 Days',
        '30_Days': '30 Days',
        '60_Days': '60 Days',
    };
    return map[value] || value;
}
</script>

<template>
    <Head title="Job Opportunities - Monti ERP" />

    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    Job <span class="text-blue-600">Opportunities</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Find a position that matches your skills and experience at Monti Corp.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <span v-if="!profileCompletion?.is_complete" class="px-3 py-1.5 rounded-full text-xs font-bold bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">{{ profileCompletion?.percentage || 0 }}% Complete</span>
                <button
                    @click="resetFilters"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-sm font-bold rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 transition active:scale-95"
                >
                    Reset Filters
                </button>
                <div class="flex items-center gap-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-0.5">
                    <button
                        @click="viewMode = 'grid'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
                        :class="viewMode === 'grid' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700'"
                    >
                        Grid
                    </button>
                    <button
                        @click="viewMode = 'list'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
                        :class="viewMode === 'list' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700'"
                    >
                        List
                    </button>
                </div>
            </div>
        </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                    <div v-for="metric in metrics" :key="metric.label"
                         class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden p-4"
                    >
                        <div class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">{{ metric.label }}</div>
                        <div class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ metric.value }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ metric.subtext }}</div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                    <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex flex-col lg:flex-row gap-3 bg-slate-50/50 dark:bg-slate-800/50">
                        <div class="relative flex-1">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search jobs by title, department, or ID..."
                                class="w-full pl-9 pr-4 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200 placeholder-slate-400"
                            />
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <select v-model="selectedDepartment" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                                <option value="All">All Departments</option>
                                <option v-for="dept in filters.departments" :key="dept" :value="dept">{{ dept }}</option>
                            </select>
                            <select v-model="selectedType" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                                <option value="All">All Types</option>
                                <option v-for="type in filters.employment_types" :key="type" :value="type">{{ type }}</option>
                            </select>
                            <select v-model="selectedLocation" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                                <option value="All">All Locations</option>
                                <option v-for="loc in filters.work_locations" :key="loc" :value="loc">{{ loc }}</option>
                            </select>
                            <select v-model="selectedWorkMode" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                                <option value="All">All Work Modes</option>
                                <option v-for="mode in filters.work_modes" :key="mode" :value="mode">{{ mode }}</option>
                            </select>
                        </div>
                    </div>

                <!-- Job Count -->
                <div class="px-6 py-3 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Showing <span class="font-bold text-slate-900 dark:text-white">{{ filteredJobs.length }}</span>
                        job{{ filteredJobs.length !== 1 ? 's' : '' }} available
                    </p>
                    <p v-if="searchQuery || selectedDepartment !== 'All' || selectedType !== 'All' || selectedLocation !== 'All' || selectedWorkMode !== 'All'"
                       class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                        Filters applied
                    </p>
                </div>

                    <div class="p-4">
                        <!-- GRID VIEW -->
                        <div v-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                            <div
                                v-for="job in filteredJobs"
                                :key="job.id"
                                class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden"
                                @click="(!job.has_applied || job.is_withdrawn || job.is_rejected) ? viewJobDetails(job) : null"
                            >
                                <div class="p-4 border-b border-slate-100 dark:border-slate-700">
                                    <div class="flex items-start justify-between mb-2">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center text-white font-black text-sm shadow-sm">
                                                {{ job.title?.charAt(0) || 'J' }}
                                            </div>
                                            <div>
                                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ job.title }}</h3>
                                                <span class="text-[10px] font-mono text-slate-400 dark:text-slate-500">{{ job.posting_id }}</span>
                                            </div>
                                        </div>
                                        <span
                                            :class="[
                                                'px-2 py-0.5 rounded-full text-[10px] font-black uppercase',
                                                getStatusColor(job),
                                            ]"
                                        >{{ getStatusBadge(job) }}</span>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        <span class="text-slate-600 dark:text-slate-300 font-medium">{{ job.department || 'N/A' }}</span>
                                    </p>
                                    <!-- Application Status Badge -->
                                    <div v-if="job.has_applied && !job.is_withdrawn" class="mt-2">
                                        <span
                                            :class="[
                                                'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-semibold border',
                                                getApplicationStatusBadge(job.application_status)
                                            ]"
                                        >
                                            {{ job.application_status || 'Applied' }}
                                        </span>
                                        <span class="text-[9px] text-slate-400 dark:text-slate-500 ml-2">
                                            {{ job.application_date ? new Date(job.application_date).toLocaleDateString() : '' }}
                                        </span>
                                    </div>
                                    <!-- Withdrawn Badge -->
                                    <div v-if="job.is_withdrawn" class="mt-2">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-semibold border bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700">
                                            Withdrawn
                                        </span>
                                        <span class="text-[9px] text-slate-400 dark:text-slate-500 ml-2">
                                            {{ job.application_date ? new Date(job.application_date).toLocaleDateString() : '' }}
                                        </span>
                                    </div>
                                    <!-- Rejected Badge (re-application allowed) -->
                                    <div v-if="job.is_rejected && !job.reapply_blocked" class="mt-2">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-semibold border bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800">
                                            Not selected — you may re-apply
                                        </span>
                                        <span class="text-[9px] text-slate-400 dark:text-slate-500 ml-2">
                                            {{ job.application_date ? new Date(job.application_date).toLocaleDateString() : '' }}
                                        </span>
                                    </div>
                                    <!-- 6-month cooldown: failed interview/training for this job -->
                                    <div v-if="job.reapply_blocked" class="mt-2">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-semibold border bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400 border-orange-200 dark:border-orange-800">
                                            Re-apply after {{ formatReapplyDate(job.can_reapply_at) }}
                                        </span>
                                        <p class="mt-1.5 px-2 py-1.5 rounded-lg text-[10px] leading-relaxed bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 text-orange-700 dark:text-orange-400">
                                            {{ cooldownNotice(job) }}
                                        </p>
                                    </div>
                                </div>

                                <div class="p-4 space-y-2.5">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="p-2 bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700 rounded-xl text-xs">
                                            <span class="text-slate-400 dark:text-slate-500">Type</span>
                                            <p class="font-semibold text-slate-700 dark:text-slate-200 text-sm flex items-center gap-1">
                                                <span :class="getEmploymentTypeColor(job.employment_type)" class="px-1.5 py-0.5 rounded-full text-[10px] font-black uppercase inline-flex items-center gap-1">
                                                    <component :is="getEmploymentTypeIcon(job.employment_type)" class="w-3 h-3" /> {{ job.employment_type }}
                                                </span>
                                            </p>
                                        </div>
                                        <div class="p-2 bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700 rounded-xl text-xs">
                                            <span class="text-slate-400 dark:text-slate-500">Salary</span>
                                            <p class="font-semibold text-slate-700 dark:text-slate-200 text-sm truncate">
                                                {{ formatSalary(job.salary_min, job.salary_max, job.salary_visibility) }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="space-y-1 text-xs">
                                        <div class="flex justify-between">
                                            <span class="text-slate-400 dark:text-slate-500">Location</span>
                                            <span class="text-slate-600 dark:text-slate-300">{{ job.work_location || 'On-site' }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-slate-400 dark:text-slate-500">Vacancies</span>
                                            <span class="text-slate-600 dark:text-slate-300">{{ getAvailableVacancies(job) }} of {{ job.vacancies || 1 }} available</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-slate-400 dark:text-slate-500">Posted</span>
                                            <span class="text-slate-600 dark:text-slate-300">{{ getRelativeTime(job.published_date || job.created_at) }}</span>
                                        </div>
                                    </div>
                                    <div v-if="job.closing_date" class="px-2 py-1 rounded-xl text-[10px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                        Closes {{ new Date(job.closing_date).toLocaleDateString() }}
                                    </div>
                                </div>

                                <!-- ACTION BUTTONS -->
                                <div class="px-4 py-2.5 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center gap-2">
                                    <button
                                        @click.stop="viewJobDetails(job)"
                                        class="flex-1 px-3 py-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-[10px] font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition"
                                    >
                                        View Details
                                    </button>

                                    <!-- Expired - cannot apply -->
                                    <button
                                        v-if="isExpired(job) && !(job.has_applied && !job.is_withdrawn && !job.is_rejected)"
                                        disabled
                                        class="flex-1 px-3 py-1.5 bg-slate-200 dark:bg-slate-700 text-slate-400 dark:text-slate-500 rounded-xl text-[10px] font-bold cursor-not-allowed"
                                    >
                                        Expired
                                    </button>

                                    <!-- Cooldown - failed interview/training, re-apply after 6 months -->
                                    <button
                                        v-else-if="job.reapply_blocked"
                                        disabled
                                        :title="cooldownNotice(job)"
                                        class="flex-1 px-3 py-1.5 bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400 rounded-xl text-[10px] font-bold cursor-not-allowed"
                                    >
                                        Available {{ formatReapplyDate(job.can_reapply_at) }}
                                    </button>

                                    <!-- Case 1: Withdrawn/Rejected - Show Re-Apply -->
                                    <button
                                        v-else-if="job.is_withdrawn || job.is_rejected"
                                        @click.stop="applyForJob(job)"
                                        class="flex-1 px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-[10px] font-bold transition"
                                    >
                                        Re-Apply
                                    </button>

                                    <!-- Case 2: Applied (not withdrawn/rejected) - Show View Application -->
                                    <button
                                        v-else-if="job.has_applied && !job.is_withdrawn && !job.is_rejected"
                                        @click.stop="viewApplication(job)"
                                        class="flex-1 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-[10px] font-bold transition"
                                    >
                                        View Application
                                    </button>

                                    <!-- Case 3: Not Applied - Show Apply Now -->
                                    <button
                                        v-else
                                        @click.stop="applyForJob(job)"
                                        class="flex-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-[10px] font-bold transition shadow-lg shadow-blue-500/30"
                                    >
                                        Apply Now
                                    </button>
                                </div>
                            </div>

                            <!-- Empty State -->
                            <div v-if="filteredJobs.length === 0" class="col-span-full">
                                <div class="text-center py-12">
                                    <h3 class="text-base font-black text-slate-900 dark:text-white mb-1">No Jobs Found</h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">{{ searchQuery ? 'Try adjusting your search or filters.' : 'No job openings available at the moment.' }}</p>
                                    <button
                                        v-if="searchQuery || selectedDepartment !== 'All' || selectedType !== 'All' || selectedLocation !== 'All' || selectedWorkMode !== 'All'"
                                        @click="resetFilters"
                                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 active:scale-95"
                                    >
                                        Clear Filters
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- LIST VIEW -->
                        <div v-else class="space-y-3">
                            <div
                                v-for="job in filteredJobs"
                                :key="job.id"
                                class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden"
                                @click="(!job.has_applied || job.is_withdrawn || job.is_rejected) ? viewJobDetails(job) : null"
                            >
                                <div class="flex flex-wrap items-center justify-between p-4 gap-3">
                                    <div class="flex items-center gap-4 flex-1 min-w-[200px]">
                                        <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center text-white font-black text-sm shadow-sm">
                                            {{ job.title?.charAt(0) || 'J' }}
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ job.title }}</h3>
                                            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                                <span>{{ job.department || 'N/A' }}</span>
                                                <span class="w-px h-3 bg-slate-200 dark:bg-slate-700"></span>
                                                <span class="inline-flex items-center gap-1"><component :is="getEmploymentTypeIcon(job.employment_type)" class="w-3 h-3" /> {{ job.employment_type }}</span>
                                                <span class="w-px h-3 bg-slate-200 dark:bg-slate-700"></span>
                                                <span>{{ job.work_location || 'On-site' }}</span>
                                            </div>
                                            <!-- Application Status Badge for List View -->
                                            <div v-if="job.has_applied && !job.is_withdrawn" class="mt-1">
                                                <span
                                                    :class="[
                                                        'px-2 py-0.5 rounded-full text-[10px] font-black uppercase',
                                                        getApplicationStatusBadge(job.application_status)
                                                    ]"
                                                >
                                                    {{ job.application_status || 'Applied' }}
                                                </span>
                                            </div>
                                            <div v-if="job.is_withdrawn" class="mt-1">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                    Withdrawn
                                                </span>
                                            </div>
                                            <div v-if="job.is_rejected && !job.reapply_blocked" class="mt-1">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400">
                                                    Not selected — you may re-apply
                                                </span>
                                            </div>
                                            <div v-if="job.reapply_blocked" class="mt-1">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400">
                                                    Re-apply after {{ formatReapplyDate(job.can_reapply_at) }}
                                                </span>
                                                <p class="mt-1 text-[10px] leading-relaxed text-orange-700 dark:text-orange-400">
                                                    {{ cooldownNotice(job) }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <div class="text-right">
                                            <p class="text-xs font-bold text-slate-700 dark:text-slate-200">{{ formatSalary(job.salary_min, job.salary_max, job.salary_visibility) }}</p>
                                            <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ getAvailableVacancies(job) }} positions available</p>
                                        </div>
                                        <span
                                            :class="[
                                                'px-2 py-0.5 rounded-full text-[10px] font-black uppercase',
                                                getStatusColor(job),
                                            ]"
                                        >{{ getStatusBadge(job) }}</span>
                                        <div class="flex items-center gap-1">
                                            <button
                                                @click.stop="viewJobDetails(job)"
                                                class="px-3 py-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-[10px] font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition"
                                            >
                                                View
                                            </button>
                                            <!-- Expired - cannot apply -->
                                            <button
                                                v-if="isExpired(job) && !(job.has_applied && !job.is_withdrawn && !job.is_rejected)"
                                                disabled
                                                class="px-3 py-1.5 bg-slate-200 dark:bg-slate-700 text-slate-400 dark:text-slate-500 rounded-xl text-[10px] font-bold cursor-not-allowed"
                                            >
                                                Expired
                                            </button>
                                            <!-- Cooldown - failed interview/training, re-apply after 6 months -->
                                            <button
                                                v-else-if="job.reapply_blocked"
                                                disabled
                                                :title="cooldownNotice(job)"
                                                class="px-3 py-1.5 bg-slate-200 dark:bg-slate-700 text-slate-400 dark:text-slate-500 rounded-xl text-[10px] font-bold cursor-not-allowed"
                                            >
                                                Available {{ formatReapplyDate(job.can_reapply_at) }}
                                            </button>
                                            <!-- Case 1: Withdrawn/Rejected - Show Re-Apply -->
                                            <button
                                                v-else-if="job.is_withdrawn || job.is_rejected"
                                                @click.stop="applyForJob(job)"
                                                class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-[10px] font-bold transition"
                                            >
                                                Re-Apply
                                            </button>
                                            <!-- Case 2: Applied (not withdrawn/rejected) - Show View App -->
                                            <button
                                                v-else-if="job.has_applied && !job.is_withdrawn && !job.is_rejected"
                                                @click.stop="viewApplication(job)"
                                                class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-[10px] font-bold transition"
                                            >
                                                View App
                                            </button>
                                            <!-- Case 3: Not Applied - Show Apply -->
                                            <button
                                                v-else
                                                @click.stop="applyForJob(job)"
                                                class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-[10px] font-bold transition"
                                            >
                                                Apply
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty State for List View -->
                            <div v-if="filteredJobs.length === 0" class="text-center py-12">
                                <h3 class="text-base font-black text-slate-900 dark:text-white mb-1">No Jobs Found</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">{{ searchQuery ? 'Try adjusting your search or filters.' : 'No job openings available at the moment.' }}</p>
                                <button
                                    v-if="searchQuery || selectedDepartment !== 'All' || selectedType !== 'All' || selectedLocation !== 'All' || selectedWorkMode !== 'All'"
                                    @click="resetFilters"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 active:scale-95"
                                >
                                    Clear Filters
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                    Showing {{ filteredJobs.length }} {{ filteredJobs.length === 1 ? 'job' : 'jobs' }}
                </div>

        <div
            v-if="showModal && selectedJob"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            @click.self="closeModal"
        >
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-4xl max-h-[90vh] overflow-hidden shadow-2xl flex flex-col">
                <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex items-start justify-between shrink-0">
                    <div class="flex items-center gap-4 min-w-0 flex-1">
                        <div class="w-14 h-14 rounded-xl bg-blue-600 flex items-center justify-center text-white font-black text-xl shrink-0">
                            {{ selectedJob.title?.charAt(0) || 'J' }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-xl font-black text-slate-900 dark:text-white truncate">{{ selectedJob.title }}</h3>
                            <div class="flex flex-wrap items-center gap-2 mt-1">
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ selectedJob.department || 'N/A' }}</span>
                                <span class="w-px h-3 bg-slate-200 dark:bg-slate-700"></span>
                                <span :class="getEmploymentTypeColor(selectedJob.employment_type)" class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase inline-flex items-center gap-1">
                                    <component :is="getEmploymentTypeIcon(selectedJob.employment_type)" class="w-3 h-3" /> {{ selectedJob.employment_type }}
                                </span>
                                <span class="w-px h-3 bg-slate-200 dark:bg-slate-700"></span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ selectedJob.work_location || 'On-site' }}</span>
                            </div>
                        </div>
                    </div>
                    <button
                        @click="closeModal"
                        class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 shrink-0 ml-2"
                    >
                        ✕
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    <!-- Application Status (if applied) -->
                    <div v-if="selectedJob.has_applied && !selectedJob.is_withdrawn" class="p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl">
                        <svg class="w-6 h-6 text-emerald-700 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">Application Submitted</p>
                            <p class="text-xs text-emerald-600 dark:text-emerald-500">Status: {{ selectedJob.application_status || 'Submitted' }} • Applied: {{ selectedJob.application_date ? new Date(selectedJob.application_date).toLocaleDateString() : 'Recently' }}</p>
                        </div>
                    </div>

                    <!-- Withdrawn Status -->
                    <div v-if="selectedJob.is_withdrawn" class="flex items-center gap-3 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl">
                        <svg class="w-6 h-6 text-amber-700 dark:text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-amber-700 dark:text-amber-400">Application Withdrawn</p>
                            <p class="text-xs text-amber-600 dark:text-amber-500">You have withdrawn this application. You can re-apply if you wish.</p>
                        </div>
                    </div>

                    <!-- Rejected Status (re-application allowed) -->
                    <div v-if="selectedJob.is_rejected && !selectedJob.reapply_blocked" class="flex items-center gap-3 p-3 bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 rounded-xl">
                        <svg class="w-6 h-6 text-rose-700 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-rose-700 dark:text-rose-400">Application Not Selected</p>
                            <p class="text-xs text-rose-600 dark:text-rose-500">You were not selected for this position, but you may re-apply if you wish.</p>
                        </div>
                    </div>

                    <!-- 6-month cooldown: failed interview/training for this job -->
                    <div v-if="selectedJob.reapply_blocked" class="flex items-center gap-3 p-3 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-xl">
                        <svg class="w-6 h-6 text-orange-700 dark:text-orange-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-orange-700 dark:text-orange-400">Re-apply after {{ formatReapplyDate(selectedJob.can_reapply_at) }}</p>
                            <p class="text-xs text-orange-600 dark:text-orange-500">{{ cooldownNotice(selectedJob) }}</p>
                        </div>
                    </div>

                    <!-- JOB DESCRIPTION -->
                    <div>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-900 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                            </svg>
                            Job Description
                        </h4>
                        <div class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed bg-slate-50 dark:bg-slate-900 p-4 rounded-xl border border-slate-100 dark:border-slate-700 max-h-[200px] overflow-y-auto whitespace-pre-wrap">
                            {{ selectedJob.description || 'No description provided.' }}
                        </div>
                    </div>

                    <!-- KEY RESPONSIBILITIES -->
                    <div>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-900 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
                            </svg>
                            Key Responsibilities
                        </h4>
                        <div class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed bg-slate-50 dark:bg-slate-900 p-4 rounded-xl border border-slate-100 dark:border-slate-700 max-h-[200px] overflow-y-auto whitespace-pre-wrap">
                            {{ selectedJob.responsibilities || 'No responsibilities listed.' }}
                        </div>
                    </div>

                    <!-- QUALIFICATIONS -->
                    <div>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-900 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                            </svg>
                            Qualifications
                        </h4>
                        <div class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed bg-slate-50 dark:bg-slate-900 p-4 rounded-xl border border-slate-100 dark:border-slate-700 max-h-[200px] overflow-y-auto whitespace-pre-wrap">
                            {{ selectedJob.qualifications || 'No qualifications listed.' }}
                        </div>
                    </div>

                    <!-- REQUIRED SKILLS -->
                    <div>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-900 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                            Required Skills
                        </h4>
                        <div class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed bg-slate-50 dark:bg-slate-900 p-4 rounded-xl border border-slate-100 dark:border-slate-700 max-h-[200px] overflow-y-auto whitespace-pre-wrap">
                            {{ selectedJob.required_skills || 'No specific skills listed.' }}
                        </div>
                    </div>

                    <!-- QUICK INFO GRID -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                        <div class="p-3 bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700 rounded-xl">
                            <span class="text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-wider">Salary</span>
                            <p class="text-sm font-bold text-slate-700 dark:text-slate-200 break-words">{{ formatSalary(selectedJob.salary_min, selectedJob.salary_max, selectedJob.salary_visibility) }}</p>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700 rounded-xl">
                            <span class="text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-wider">Vacancies</span>
                            <p class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ getAvailableVacancies(selectedJob) }} of {{ selectedJob.vacancies || 1 }}</p>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700 rounded-xl">
                            <span class="text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-wider">Posted</span>
                            <p class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ getRelativeTime(selectedJob.published_date || selectedJob.created_at) }}</p>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700 rounded-xl">
                            <span class="text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-wider">Status</span>
                            <p class="text-sm font-bold">
                                <span :class="getStatusColor(selectedJob)" class="px-1.5 py-0.5 rounded border text-xs">{{ getStatusBadge(selectedJob) }}</span>
                            </p>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700 rounded-xl">
                            <span class="text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-wider">Department</span>
                            <p class="text-sm font-bold text-slate-700 dark:text-slate-200 break-words">{{ selectedJob.department || 'N/A' }}</p>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700 rounded-xl">
                            <span class="text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-wider">Shift</span>
                            <p class="text-sm font-bold text-slate-700 dark:text-slate-200 break-words">{{ selectedJob.shift || 'N/A' }}</p>
                        </div>
                    </div>

                    <!-- Closing Date Alert -->
                    <div v-if="selectedJob.closing_date" class="flex items-center gap-3 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl">
                        <svg class="w-6 h-6 text-amber-700 dark:text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-amber-700 dark:text-amber-400">Application Deadline</p>
                            <p class="text-xs text-amber-600 dark:text-amber-500">This position closes on {{ new Date(selectedJob.closing_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer - Fixed -->
                <div class="p-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 flex items-center justify-end gap-3 shrink-0">
                    <button
                        @click="closeModal"
                        class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-semibold transition-all duration-200"
                    >
                        Close
                    </button>

                    <!-- Expired - cannot apply -->
                    <button
                        v-if="isExpired(selectedJob) && !(selectedJob.has_applied && !selectedJob.is_withdrawn && !selectedJob.is_rejected)"
                        disabled
                        class="px-5 py-2.5 bg-slate-200 dark:bg-slate-700 text-slate-400 dark:text-slate-500 rounded-xl text-sm font-bold cursor-not-allowed transition-all duration-200"
                    >
                        Expired
                    </button>

                    <!-- Cooldown - failed interview/training, re-apply after 6 months -->
                    <button
                        v-else-if="selectedJob.reapply_blocked"
                        disabled
                        :title="cooldownNotice(selectedJob)"
                        class="px-5 py-2.5 bg-slate-200 dark:bg-slate-700 text-slate-400 dark:text-slate-500 rounded-xl text-sm font-bold cursor-not-allowed transition-all duration-200"
                    >
                        Available {{ formatReapplyDate(selectedJob.can_reapply_at) }}
                    </button>

                    <!-- Withdrawn/Rejected - Show Re-Apply -->
                    <button
                        v-else-if="selectedJob.is_withdrawn || selectedJob.is_rejected"
                        @click="applyForJob(selectedJob)"
                        class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-sm "
                    >
                        Re-Apply
                    </button>

                    <!-- Applied (not withdrawn/rejected) - Show View Application -->
                    <button
                        v-else-if="selectedJob.has_applied && !selectedJob.is_withdrawn && !selectedJob.is_rejected"
                        @click="viewApplication(selectedJob)"
                        class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-sm "
                    >
                        View Application
                    </button>

                    <!-- Not Applied - Show Apply Now -->
                    <button
                        v-else
                        @click="applyForJob(selectedJob)"
                        class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-slate-900 dark:text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-sm "
                    >
                        Apply Now
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- ✅ APPLICATION FORM MODAL - SCROLLABLE        -->
        <!-- ============================================ -->
        <div
            v-if="showApplicationModal && applyingJob"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            @click.self="closeApplicationModal"
        >
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-lg max-h-[90vh] overflow-hidden shadow-2xl animate-modalIn flex flex-col">
                <!-- Modal Header -->
                <div class="p-6 border-b border-slate-100 dark:border-slate-700 shrink-0">
                    <div class="hidden"></div>
                    <div class="relative flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center text-white font-black text-sm">
                                {{ applyingJob.title?.charAt(0) || 'J' }}
                            </div>
                            <div>
                                <h3 class="text-base font-black text-slate-900 dark:text-white">Submit Application</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ applyingJob.title }}</p>
                            </div>
                        </div>
                        <button
                            @click="closeApplicationModal"
                            class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Modal Body - SCROLLABLE -->
                <div class="flex-1 overflow-y-auto p-6 space-y-5">
                    <!-- Notice Period -->
                    <div>
                        <label class="block mb-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">Notice Period</label>
                        <select
                            v-model="applicationForm.notice_period"
                            class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/30 text-slate-700 dark:text-slate-200"
                            :disabled="isSubmitting"
                        >
                            <option v-for="option in noticePeriodOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Availability / Start Date -->
                    <div>
                        <label class="block mb-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">Available / Start Date <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                                </svg>
                            </div>
                            <input
                                v-model="applicationForm.availability_date"
                                type="date"
                                class="w-full pl-10 pr-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/30 text-slate-700 dark:text-slate-200"
                                :disabled="isSubmitting"
                                :min="minAvailabilityDate"
                            />
                        </div>
                        <p class="mt-1.5 text-[11px] text-slate-500 dark:text-slate-400">
                            Earliest selectable date for
                            <span class="font-bold">{{ getNoticePeriodLabel(applicationForm.notice_period) }}</span>
                            notice is
                            <span class="font-bold text-blue-600 dark:text-blue-400">{{ minAvailabilityLabel }} ({{ minAvailabilityDate }})</span>.
                            Earlier dates are disabled.
                        </p>
                        <p v-if="applicationForm.errors.availability_date" class="mt-1 text-xs text-rose-600 dark:text-rose-400">
                            {{ applicationForm.errors.availability_date }}
                        </p>
                    </div>

                    <!-- Cover Letter -->
                    <div>
                        <label class="block mb-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">Cover Letter <span class="text-slate-400 dark:text-slate-500 font-normal">(Optional)</span></label>
                        <textarea
                            v-model="applicationForm.cover_letter"
                            rows="4"
                            class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/30 text-slate-700 dark:text-slate-200 resize-none"
                            :disabled="isSubmitting"
                            placeholder="Write your cover letter here... Tell us why you're the perfect fit for this position."
                        ></textarea>
                        <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1.5">
                            <span class="font-medium">Tip:</span> Highlight your relevant experience and skills that match this role.
                        </p>
                    </div>

                    <!-- RESUME SELECTION -->
                    <div>
                        <label class="block mb-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">Resume <span class="text-red-500">*</span></label>
                        
                        <!-- Option 1: Select from existing resumes -->
                        <div v-if="props.applicantResumes && props.applicantResumes.length > 0" class="mb-3">
                            <p class="text-xs text-slate-500 dark:text-slate-400 mb-1.5">Select from your uploaded resumes:</p>
                            <select
                                v-model="selectedResume"
                                @change="selectExistingResume"
                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/30 text-slate-700 dark:text-slate-200"
                                :disabled="isSubmitting || !!newResumeFile"
                            >
                                <option value="">Select a resume...</option>
                                <option v-for="resume in props.applicantResumes" :key="resume" :value="resume">
                                    {{ getResumeFileName(resume) }}
                                </option>
                            </select>
                            <p v-if="selectedResume" class="text-xs text-emerald-700 dark:text-emerald-400 mt-1">
                                Selected: {{ getResumeFileName(selectedResume) }}
                            </p>
                        </div>

                        <!-- Divider -->
                        <div v-if="props.applicantResumes && props.applicantResumes.length > 0" class="relative my-4">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-slate-200 dark:border-slate-700"></div>
                            </div>
                            <div class="relative flex justify-center text-xs">
                                <span class="bg-white dark:bg-slate-800 px-2 text-slate-400 dark:text-slate-500">OR</span>
                            </div>
                        </div>

                        <!-- Option 2: Upload new resume -->
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mb-1.5">Upload a new resume:</p>
                            <div class="flex items-center gap-3">
                                <label
                                    class="flex-1 cursor-pointer rounded-lg border-2 border-dashed border-slate-200 dark:border-slate-700 p-3 text-center transition-all duration-200 hover:border-blue-300 hover:bg-blue-50 dark:bg-blue-900/20"
                                    :class="newResumeFile ? 'border-emerald-300 bg-emerald-50 dark:bg-emerald-900/20' : ''"
                                >
                                    <input
                                        id="new_resume_input"
                                        type="file"
                                        accept=".pdf,.doc,.docx"
                                        class="hidden"
                                        :disabled="isSubmitting || !!selectedResume"
                                        @change="handleNewResumeUpload"
                                    />
                                    <div v-if="!newResumeFile" class="flex items-center justify-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                        </svg>
                                        Click to upload PDF, DOC, DOCX
                                    </div>
                                    <div v-else class="flex items-center justify-center gap-2 text-xs text-emerald-700 dark:text-emerald-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        {{ newResumeFile.name }} ({{ (newResumeFile.size / 1024).toFixed(0) }} KB)
                                    </div>
                                </label>
                                <button
                                    v-if="newResumeFile"
                                    type="button"
                                    @click="removeNewResume"
                                    class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                    :disabled="isSubmitting"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">Max 5MB • PDF, DOC, DOCX</p>
                        </div>

                        <!-- Resume selection indicator -->
                        <div v-if="selectedResume || newResumeFile" class="mt-2 p-2 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg border border-emerald-200 dark:border-emerald-800">
                            <p class="text-xs text-emerald-700 dark:text-emerald-400 flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                Resume selected:
                                <span class="font-semibold">
                                    {{ selectedResume ? getResumeFileName(selectedResume) : newResumeFile?.name }}
                                </span>
                            </p>
                        </div>
                    </div>

                    <!-- Info Message -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-3 flex items-start gap-2">
                        <svg class="w-4 h-4 text-blue-700 dark:text-blue-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>
                        </svg>
                        <p class="text-xs text-blue-700 dark:text-blue-400">
                            Your profile information will be shared with the employer as part of your application.
                        </p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 flex items-center justify-end gap-3 shrink-0">
                    <button
                        @click="closeApplicationModal"
                        class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-semibold transition-all duration-200"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </button>
                    <button
                        @click="submitApplication"
                        class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-sm "
                        :disabled="isSubmitting"
                    >
                        <span v-if="isSubmitting" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Submitting...
                        </span>
                        <span v-else>Submit Application</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- ✅ PROFILE INCOMPLETE MODAL                  -->
        <!-- ============================================ -->
        <div
            v-if="showProfileModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            @click.self="closeProfileModal"
        >
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md overflow-hidden shadow-2xl animate-modalIn">
                <!-- Modal Header -->
                <div class="p-6 border-b border-slate-100 dark:border-slate-700 bg-amber-50 dark:bg-amber-900/20">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-amber-700 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-amber-700 dark:text-amber-400">Profile Incomplete</h3>
                            <p class="text-xs text-amber-600 dark:text-amber-500 mt-0.5">Complete your profile to apply for jobs</p>
                        </div>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between p-3 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-200 dark:border-amber-800">
                        <span class="text-sm text-amber-700 dark:text-amber-400">Profile Completion</span>
                        <span class="text-sm font-bold text-amber-700 dark:text-amber-400">{{ profileCompletion?.percentage || 0 }}%</span>
                    </div>

                    <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                        {{ profileMessage || 'Please complete your profile before applying for jobs.' }}
                    </p>

                    <div class="bg-slate-50 dark:bg-slate-900 rounded-xl p-4 border border-slate-100 dark:border-slate-700">
                        <p class="text-xs font-semibold text-slate-600 dark:text-slate-300 mb-2 flex items-center gap-2">
                            Missing Fields:
                        </p>
                        <ul class="space-y-1">
                            <li v-for="field in profileCompletion?.missing_fields || []" :key="field" class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-2">
                                <span class="text-rose-700 dark:text-rose-400">•</span>
                                {{ field.replace(/_/g, ' ') }}
                            </li>
                        </ul>
                    </div>

                    <!-- Progress Bar -->
                    <div class="mt-2">
                        <div class="h-2 overflow-hidden rounded-full bg-slate-200">
                            <div
                                class="h-full rounded-full bg-amber-500 transition-all"
                                :style="{ width: (profileCompletion?.percentage || 0) + '%' }"
                            ></div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 flex items-center justify-end gap-3">
                    <button
                        @click="closeProfileModal"
                        class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-semibold transition-all duration-200"
                    >
                        Later
                    </button>
                    <button
                        @click="goToProfile"
                        class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-sm "
                    >
                        Complete Profile
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- ✅ CONFIRMATION MODAL                        -->
        <!-- ============================================ -->
        <div
            v-if="showConfirmModal && confirmJob"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            @click.self="closeConfirmModal"
        >
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md overflow-hidden shadow-2xl animate-modalIn">
                <!-- Modal Header -->
                <div class="p-6 border-b border-slate-100 dark:border-slate-700 bg-blue-50 dark:bg-blue-900/20">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-blue-700 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-blue-700 dark:text-blue-400">Confirm Application</h3>
                            <p class="text-xs text-blue-700 dark:text-blue-400/80 mt-0.5">
                                {{ (confirmJob.is_withdrawn || confirmJob.is_rejected) ? 'You are re-applying for this position.' : 'Review your application details' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="p-6 space-y-4">
                    <div class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-700">
                        <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center text-white font-black text-sm">
                            {{ confirmJob.title?.charAt(0) || 'J' }}
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-900 dark:text-white">{{ confirmJob.title }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ confirmJob.department || 'N/A' }}</p>
                        </div>
                    </div>

                    <!-- Application Details Summary -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-3 space-y-1.5">
                        <div class="flex justify-between text-xs">
                            <span class="text-blue-700 dark:text-blue-400">Notice Period:</span>
                            <span class="font-medium text-blue-700 dark:text-blue-400">{{ getNoticePeriodLabel(applicationForm.notice_period) }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-blue-700 dark:text-blue-400">Availability Date:</span>
                            <span class="font-medium text-blue-700 dark:text-blue-400">{{ applicationForm.availability_date ? new Date(applicationForm.availability_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'Not specified' }}</span>
                        </div>
                        <div v-if="applicationForm.cover_letter" class="flex justify-between text-xs">
                            <span class="text-blue-700 dark:text-blue-400">Cover Letter:</span>
                            <span class="font-medium text-blue-700 dark:text-blue-400 truncate max-w-[150px]">{{ applicationForm.cover_letter.substring(0, 50) }}{{ applicationForm.cover_letter.length > 50 ? '...' : '' }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-blue-700 dark:text-blue-400">Resume:</span>
                            <span class="font-medium text-blue-700 dark:text-blue-400">
                                {{ selectedResume ? getResumeFileName(selectedResume) : newResumeFile?.name || 'Not specified' }}
                            </span>
                        </div>
                    </div>

                    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-3 flex items-start gap-2">
                        <svg class="w-4 h-4 text-amber-700 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                        </svg>
                        <p class="text-xs text-amber-700 dark:text-amber-400">By confirming, you agree to share your profile information with the employer.</p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 flex items-center justify-end gap-3">
                    <button
                        @click="closeConfirmModal"
                        class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-semibold transition-all duration-200"
                        :disabled="isApplying"
                    >
                        Cancel
                    </button>
                    <button
                        @click="confirmApply"
                        class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-sm "
                        :disabled="isApplying"
                    >
                        <span v-if="isApplying" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Submitting...
                        </span>
                        <span v-else>Confirm Application</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- ✅ SUCCESS MODAL                             -->
        <!-- ============================================ -->
        <div
            v-if="showSuccessModal && successJob"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            @click.self="closeSuccessModal"
        >
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md overflow-hidden shadow-2xl animate-modalIn">
                <!-- Modal Header -->
                <div class="p-6 border-b border-slate-100 dark:border-slate-700 bg-emerald-50 dark:bg-emerald-900/20">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-emerald-700 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-emerald-700 dark:text-emerald-400">Application Submitted!</h3>
                            <p class="text-xs text-emerald-600 dark:text-emerald-500 mt-0.5">
                                {{ (successJob.is_withdrawn || successJob.is_rejected) ? 'You have successfully re-applied!' : 'Your application was sent successfully' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="p-6 space-y-4">
                    <div class="flex items-center gap-3 p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-200 dark:border-emerald-800">
                        <div class="w-10 h-10 rounded-xl bg-emerald-600 flex items-center justify-center text-white font-black text-sm">
                            {{ successJob.title?.charAt(0) || 'J' }}
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-900 dark:text-white">{{ successJob.title }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ successJob.department || 'N/A' }}</p>
                        </div>
                    </div>

                    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl p-3 flex items-start gap-2">
                        <svg class="w-4 h-4 text-emerald-700 dark:text-emerald-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <div>
                            <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-400">Application Status: Submitted</p>
                            <p class="text-xs text-emerald-600 dark:text-emerald-500">Applied on {{ new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
                        </div>
                    </div>

                    <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                        Your application has been received. You can track the status of your application in the <strong>"My Applications"</strong> section.
                    </p>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 flex items-center justify-end gap-3">
                    <button
                        @click="closeSuccessModal"
                        class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-semibold transition-all duration-200"
                    >
                        Continue Browsing
                    </button>
                    <button
                        @click="viewApplication(successJob)"
                        class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-sm "
                    >
                        View My Applications
                    </button>
                </div>
            </div>
        </div>

        <!-- DETAILS LOADING OVERLAY: simple blur while job details load -->
        <div
            v-if="detailLoading"
            class="fixed inset-0 z-[70] flex items-center justify-center bg-black/30 backdrop-blur-md"
            aria-live="polite"
        >
            <div class="flex flex-col items-center gap-3">
                <div class="w-10 h-10 rounded-full border-4 border-slate-300 border-t-blue-600 animate-spin"></div>
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Loading job details...</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Modal Animation */
.animate-modalIn {
    animation: modalIn 0.25s ease-out;
}

@keyframes modalIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(15px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* Spinner Animation */
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
.animate-spin {
    animation: spin 1s linear infinite;
}
</style>