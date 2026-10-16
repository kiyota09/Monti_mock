<!-- resources/js/Pages/Dashboard/APPLICANTS/Dashboard.vue -->
<script setup lang="ts">
// ============================================
// FILE LOCATION: resources/js/Pages/Dashboard/APPLICANTS/Dashboard.vue
// PURPOSE: Applicant Dashboard - Overview of recruitment activity
// ============================================

import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ChevronRightIcon } from '@heroicons/vue/24/outline';
import { Hand, Calendar, MapPin, Building2, Inbox, Search } from 'lucide-vue-next';

const page = usePage();

// ✅ FIX: Safe auth access - check both applicant and user
const user = computed(() => {
    const auth = page.props.auth as any;
    return auth?.applicant || auth?.user || null;
});

// ============================================
// PROPS - Real data from controller
// ============================================
const props = defineProps<{
    stats?: {
        applications_count: number;
        interviews_count: number;
        pending_applications: number;
    };
    recentApplications?: Array<{
        id: number;
        job_title: string;
        status: string;
        application_date: string;
    }>;
    upcomingInterviews?: Array<{
        id: number;
        job_title: string;
        interview_type: string;
        date: string;
        time: string;
        location: string;
    }>;
    recommendedJobs?: Array<{
        id: number;
        title: string;
        department: string;
        location: string;
        match: number;
    }>;
}>();

// ============================================
// STATE
// ============================================
const currentTime = ref(new Date());
let timeInterval: number | null = null;

onMounted(() => {
    timeInterval = window.setInterval(() => {
        currentTime.value = new Date();
    }, 60000);
});

onBeforeUnmount(() => {
    if (timeInterval) {
        clearInterval(timeInterval);
    }
});

// ============================================
// METRICS - Using real data
// ============================================
const stats = computed(() => [
    { 
        id: 'applications', 
        label: 'Applications', 
        value: props.stats?.applications_count || 0, 
        subtext: 'Total applications',
        tone: 'navy',
        route: '/applicant/applications'
    },
    { 
        id: 'interviews', 
        label: 'Interviews', 
        value: props.stats?.interviews_count || 0, 
        subtext: 'Scheduled interviews',
        tone: 'info',
        route: '/applicant/interviews'
    },
]);

// ============================================
// APPLICATION STATUS - Real data or fallback
// ============================================
const applicationStatus = computed(() => {
    const recent = props.recentApplications?.[0];
    if (recent) {
        return {
            title: recent.job_title || 'Your Application',
            status: recent.status || 'Pending',
            date: recent.application_date ? new Date(recent.application_date).toLocaleDateString() : 'Recently',
        };
    }
    return {
        title: 'No applications yet',
        status: 'Start applying',
        date: 'Browse jobs to get started',
    };
});

// ============================================
// UPCOMING INTERVIEW - Real data or fallback
// ============================================
const upcomingInterview = computed(() => {
    const interview = props.upcomingInterviews?.[0];
    if (interview) {
        return {
            position: interview.job_title || 'Interview',
            type: interview.interview_type || 'Interview',
            date: interview.date ? new Date(interview.date).toLocaleDateString() : 'TBD',
            time: interview.time || 'TBD',
            location: interview.location || 'TBD',
            company: 'Monti Textile',
        };
    }
    return {
        position: 'No upcoming interviews',
        type: 'Check back later',
        date: '—',
        time: '—',
        location: '—',
        company: 'Monti Textile',
    };
});

// ============================================
// RECOMMENDED JOBS
// ============================================
const recommendedJobs = computed(() => props.recommendedJobs || []);

// ============================================
// HELPER FUNCTIONS
// ============================================

function getStatusColor(status: string): string {
    const colors: Record<string, string> = {
        'Pending': 'text-amber-700 dark:text-amber-400',
        'Submitted': 'text-amber-700 dark:text-amber-400',
        'Screening': 'text-blue-700 dark:text-blue-400',
        'Shortlisted': 'text-indigo-700 dark:text-indigo-400',
        'Interview Scheduled': 'text-blue-700 dark:text-blue-400',
        'Initial Interview': 'text-blue-700 dark:text-blue-400',
        'Final Interview': 'text-blue-700 dark:text-blue-400',
        'Interviewed': 'text-blue-700 dark:text-blue-400',
        'Interview': 'text-blue-700 dark:text-blue-400',
        'Selected': 'text-emerald-700 dark:text-emerald-400',
        'Job Offer': 'text-amber-700 dark:text-amber-400',
        'Offer Accepted': 'text-emerald-700 dark:text-emerald-400',
        'Pre-employment': 'text-emerald-700 dark:text-emerald-400',
        'Onboard': 'text-emerald-700 dark:text-emerald-400',
        'Onboarding': 'text-emerald-700 dark:text-emerald-400',
        'Hired': 'text-emerald-700 dark:text-emerald-400',
        'Rejected': 'text-rose-700 dark:text-rose-400',
        'Withdrawn': 'text-slate-500 dark:text-slate-400',
    };
    return colors[status] || 'text-slate-500 dark:text-slate-400';
}

function getStatusBadge(status: string): string {
    const colors: Record<string, string> = {
        'Pending': 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        'Submitted': 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        'Screening': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'Shortlisted': 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
        'Interview Scheduled': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'Initial Interview': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'Final Interview': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'Interviewed': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'Interview': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
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
    return colors[status] || 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
}

function getMatchColor(match: number): string {
    if (match >= 85) return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
    if (match >= 70) return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
    return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
}

function getProgressWidth(status: string): string {
    if (['Hired', 'Onboard', 'Onboarding', 'Pre-employment', 'Offer Accepted'].includes(status)) return '100%';
    if (['Selected', 'Job Offer'].includes(status)) return '90%';
    if (['Interview Scheduled', 'Initial Interview', 'Final Interview', 'Interviewed', 'Interview'].includes(status)) return '75%';
    if (status === 'Shortlisted') return '50%';
    if (status === 'Screening') return '35%';
    if (status === 'Submitted') return '20%';
    return '10%';
}

function getGreeting(): string {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good Morning';
    if (hour < 17) return 'Good Afternoon';
    return 'Good Evening';
}

// ============================================
// NAVIGATION FUNCTIONS
// ============================================

function navigateTo(url: string) {
    router.visit(url);
}

function viewAllJobs() {
    router.visit('/applicant/jobs');
}

function viewApplication() {
    router.visit('/applicant/applications');
}

function viewInterview() {
    router.visit('/applicant/interviews');
}
</script>

<template>
    <Head title="Dashboard - Monti ERP" />

    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    Applicant <span class="text-blue-600">Dashboard</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Welcome back, {{ user?.email?.split('@')[0] || 'Applicant' }}! Here's your recruitment activity.</p>
            </div>
            <button
                @click="viewAllJobs"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 active:scale-95"
            >
                <Search class="w-4 h-4" />
                Browse Jobs
            </button>
        </div>

                <!-- Welcome Banner -->
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4 mb-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-black text-slate-900 dark:text-white">
                                {{ getGreeting() }}, {{ user?.email?.split('@')[0] || 'Applicant' }}! <Hand class="w-5 h-5 inline" />
                            </h2>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ currentTime.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-slate-400 dark:text-slate-400">Your journey starts here</span>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- KPI METRICS ROW                              -->
                <!-- ============================================ -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div 
                        v-for="stat in stats" 
                        :key="stat.id"
                        class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4 cursor-pointer"
                        @click="navigateTo(stat.route)"
                    >
                        <span
                            class="absolute left-0 top-0 bottom-0 w-1 rounded-l-2xl"
                            :class="{
                                'bg-blue-600': stat.tone === 'navy',
                                'bg-blue-400': stat.tone === 'info',
                            }"
                        ></span>
                        <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ stat.label }}</div>
                        <div class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ stat.value }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ stat.subtext }}</div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- Main Content Grid                            -->
                <!-- ============================================ -->
                <div class="grid lg:grid-cols-2 gap-4">
                    
                    <!-- LEFT COLUMN -->
                    <div class="space-y-4">
                        <!-- Application Status -->
                        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                            <div class="p-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center justify-between">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                    </svg>
                                    Application Status
                                </h3>
                                <button 
                                    @click="viewApplication"
                                    class="text-xs text-blue-600 hover:text-blue-700 transition-colors flex items-center gap-0.5 font-bold"
                                >
                                    View All
                                    <ChevronRightIcon class="w-3 h-3" />
                                </button>
                            </div>
                            <div class="p-4">
                                <p class="text-base font-black text-slate-900 dark:text-white mb-3">{{ applicationStatus.title }}</p>
                                
                                <div class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-700">
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Status</span>
                                            <span class="text-xs font-semibold" :class="getStatusColor(applicationStatus.status)">
                                                {{ applicationStatus.status }}
                                            </span>
                                        </div>
                                        <div class="mt-1 h-1.5 w-full rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                            <div 
                                                class="h-full rounded-full bg-blue-600 transition-all duration-500"
                                                :style="{ width: getProgressWidth(applicationStatus.status) }"
                                            ></div>
                                        </div>
                                        <p class="text-[10px] text-slate-400 dark:text-slate-400 mt-1">Applied: {{ applicationStatus.date }}</p>
                                    </div>
                                </div>

                                <button 
                                    @click="viewApplication"
                                    class="mt-3 w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-lg shadow-blue-500/30 active:scale-95"
                                >
                                    View Application
                                </button>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                            <div class="p-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
                                    </svg>
                                    Quick Stats
                                </h3>
                            </div>
                            <div class="p-4 grid grid-cols-3 gap-2">
                                <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-700 text-center">
                                    <p class="text-lg font-black text-slate-900 dark:text-white">{{ props.stats?.applications_count || 0 }}</p>
                                    <p class="text-[10px] text-slate-400 dark:text-slate-400">Applications</p>
                                </div>
                                <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-700 text-center">
                                    <p class="text-lg font-black text-slate-900 dark:text-white">{{ props.stats?.interviews_count || 0 }}</p>
                                    <p class="text-[10px] text-slate-400 dark:text-slate-400">Interviews</p>
                                </div>
                                <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-700 text-center">
                                    <p class="text-lg font-black text-slate-900 dark:text-white">{{ props.stats?.pending_applications || 0 }}</p>
                                    <p class="text-[10px] text-slate-400 dark:text-slate-400">Pending</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN -->
                    <div class="space-y-4">
                        <!-- Upcoming Interview -->
                        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                            <div class="p-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center justify-between">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008z"/>
                                    </svg>
                                    Upcoming Interview
                                </h3>
                                <button 
                                    @click="viewInterview"
                                    class="text-xs text-blue-600 hover:text-blue-700 transition-colors flex items-center gap-0.5 font-bold"
                                >
                                    View All
                                    <ChevronRightIcon class="w-3 h-3" />
                                </button>
                            </div>
                            <div class="p-4">
                                <div v-if="upcomingInterview.position !== 'No upcoming interviews'" class="flex items-start gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-700 dark:text-blue-400 flex-shrink-0">
                                        <Calendar class="w-5 h-5" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-black text-slate-900 dark:text-white">{{ upcomingInterview.position }}</p>
                                        <p class="text-xs text-blue-600 dark:text-blue-400">{{ upcomingInterview.type }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                            {{ upcomingInterview.date }} • {{ upcomingInterview.time }}
                                        </p>
                                        <p class="text-xs text-slate-400 dark:text-slate-400"><MapPin class="w-4 h-4 inline" /> {{ upcomingInterview.location }}</p>
                                        <p class="text-xs text-slate-400 dark:text-slate-400"><Building2 class="w-4 h-4 inline" /> {{ upcomingInterview.company }}</p>
                                    </div>
                                </div>
                                <div v-else class="text-center py-4">
                                    <Inbox class="w-6 h-6 mx-auto text-slate-400" />
                                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">No upcoming interviews</p>
                                </div>
                                <button 
                                    v-if="upcomingInterview.position !== 'No upcoming interviews'"
                                    @click="viewInterview"
                                    class="mt-3 w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-lg shadow-blue-500/30 active:scale-95"
                                >
                                    View Interview
                                </button>
                            </div>
                        </div>

                        <!-- Recommended Jobs -->
                        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                            <div class="p-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center justify-between">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z"/>
                                    </svg>
                                    Recommended Jobs
                                </h3>
                                <button 
                                    @click="viewAllJobs"
                                    class="text-xs text-blue-600 hover:text-blue-700 transition-colors flex items-center gap-0.5 font-bold"
                                >
                                    View All
                                    <ChevronRightIcon class="w-3 h-3" />
                                </button>
                            </div>
                            <div class="p-4 space-y-2">
                                <div 
                                    v-for="job in recommendedJobs.slice(0, 3)" 
                                    :key="job.id" 
                                    class="flex items-center justify-between p-2.5 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-700 transition cursor-pointer"
                                    @click="viewAllJobs"
                                >
                                    <div>
                                        <p class="text-xs font-bold text-slate-900 dark:text-white">{{ job.title }}</p>
                                        <p class="text-[10px] text-slate-500 dark:text-slate-400">{{ job.department }} • {{ job.location }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="px-3 py-1 rounded-full text-[11px] font-bold border" :class="getMatchColor(job.match)">
                                            {{ job.match }}%
                                        </span>
                                        <ChevronRightIcon class="w-3 h-3 text-slate-400" />
                                    </div>
                                </div>
                                <div v-if="recommendedJobs.length === 0" class="text-center py-4">
                                    <Search class="w-6 h-6 mx-auto text-slate-400" />
                                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">Browse jobs to get recommendations</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-4 text-xs text-slate-400 dark:text-slate-400">
                    © 2026 Monti Textile Manufacturing Corp. All rights reserved.
                </div>
    </AuthenticatedLayout>
</template>

<style scoped>
</style>