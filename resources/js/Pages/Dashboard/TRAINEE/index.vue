<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    user: Object,
    stats: Object,
});

// State management
const showAnnouncements = ref(false);
const showLeaveForm = ref(false);
const showPayslip = ref(false); // New state for Payslip modal
const isTimedIn = ref(false);
const currentTime = ref(new Date());

// Formatters
const formattedTime = computed(() => currentTime.value.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
const formattedDate = computed(() => currentTime.value.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric' }));

// Clock Interval
setInterval(() => {
    currentTime.value = new Date();
}, 1000);

const handleAttendance = () => {
    isTimedIn.value = !isTimedIn.value;
};

// Dummy Notifications Data
const notifications = ref([
    {
        id: 1,
        title: "Scheduled Training Session",
        message: "Reminder: You have a scheduled training on 16/02/2026 at 7am sharp. Please be on time.",
        type: "upcoming",
        date: "Feb 16, 2026",
        status: "High Priority"
    },
    {
        id: 2,
        title: "Attendance Alert",
        message: "You were marked LATE at training on 15/02/2026. Please coordinate with your supervisor.",
        type: "late",
        date: "Feb 15, 2026",
        status: "Warning"
    },
    {
        id: 3,
        title: "Performance Feedback",
        message: "Your supervisor has left a comment on your last module submission. Click to view.",
        type: "info",
        date: "Feb 14, 2026",
        status: "New"
    },
    {
        id: 4,
        title: "DTR Verification",
        message: "Please verify your Daily Time Record (DTR) for the previous week to process your allowance.",
        type: "action",
        date: "Feb 13, 2026",
        status: "Pending"
    }
]);

// Dummy Payslip Data
const payslipDetails = ref({
    period: "Feb 01 - Feb 15, 2026",
    basicAllowance: 5000.00,
    transportAllowance: 1200.00,
    lateDeductions: 150.00,
    netPay: 6050.00
});
</script>

<template>

    <Head title="Trainee Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="font-bold text-2xl text-gray-900 dark:text-zinc-100 leading-tight">
                        Trainee Workspace
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-zinc-400">Welcome back, {{ user.name }} • {{ formattedDate }}</p>
                </div>

                <div class="flex items-center space-x-3">
                    <button @click="showAnnouncements = true"
                        class="relative p-2 bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-full hover:bg-gray-50 dark:hover:bg-zinc-800 transition shadow-sm">
                        <span class="absolute top-1 right-1 flex h-3 w-3">
                            <span
                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-indigo-600"></span>
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 dark:text-zinc-400" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </button>

                    <button @click="showLeaveForm = true"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 transition ease-in-out duration-150 shadow-md">
                        Request Leave
                    </button>
                </div>
            </div>
        </template>

        <div class="py-10 bg-gray-50 dark:bg-zinc-800 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
                    <div
                        class="lg:col-span-3 bg-gradient-to-r from-indigo-800 to-blue-700 rounded-2xl shadow-xl overflow-hidden relative">
                        <div class="absolute right-0 top-0 opacity-10 transform translate-x-12 -translate-y-8">
                            <svg width="300" height="300" viewBox="0 0 24 24" fill="white">
                                <path d="M12 2L1 21h22L12 2z" />
                            </svg>
                        </div>
                        <div class="p-8 relative z-10 flex flex-col md:flex-row justify-between items-center h-full">
                            <div class="text-white">
                                <span
                                    class="bg-indigo-500/30 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider">Departmental
                                    Access</span>
                                <h3 class="text-3xl font-extrabold mt-2">{{ user.department }} Team</h3>
                                <p class="text-indigo-100 mt-2 text-lg">Currently active in your specialized training
                                    program.
                                </p>
                            </div>
                            <div
                                class="mt-6 md:mt-0 text-center bg-white/10 backdrop-blur-md p-4 rounded-xl border border-white/20">
                                <p class="text-indigo-100 text-sm font-medium">Digital Timeclock</p>
                                <p class="text-white text-3xl font-mono font-bold leading-none">{{ formattedTime }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-sm border border-gray-100 dark:border-zinc-800 p-6 flex flex-col justify-center">
                        <h4 class="text-sm font-bold text-gray-400 dark:text-zinc-500 uppercase tracking-tight mb-4 flex items-center">
                            <span class="w-2 h-2 rounded-full bg-green-500 mr-2"></span> System Status
                        </h4>
                        <button @click="handleAttendance"
                            :class="isTimedIn ? 'bg-rose-50 hover:bg-rose-100 text-rose-600 border-rose-200' : 'bg-emerald-50 dark:bg-emerald-900/20 hover:bg-emerald-100 text-emerald-600 dark:text-emerald-400 border-emerald-200'"
                            class="group relative w-full py-6 rounded-xl border-2 transition-all duration-300 overflow-hidden">
                            <span class="relative z-10 font-black text-lg tracking-tighter">
                                {{ isTimedIn ? 'CLOCK OUT' : 'CLOCK IN' }}
                            </span>
                            <div
                                class="absolute inset-0 opacity-0 group-hover:opacity-10 bg-current transition-opacity">
                            </div>
                        </button>
                        <p class="text-[10px] text-gray-400 dark:text-zinc-500 mt-3 text-center uppercase font-bold">Location: Registered
                            Office IP
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white dark:bg-zinc-900 p-5 rounded-xl border border-gray-100 dark:border-zinc-800 shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-gray-400 dark:text-zinc-500 uppercase">Progress</span>
                            <div class="p-1.5 bg-blue-50 dark:bg-blue-900/30 rounded-lg text-blue-600 dark:text-blue-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                </svg>
                            </div>
                        </div>
                        <div class="text-3xl font-black text-gray-800 dark:text-zinc-200">{{ stats.progress }}%</div>
                        <div class="w-full bg-gray-100 dark:bg-zinc-800 rounded-full h-1.5 mt-3">
                            <div class="bg-blue-600 h-1.5 rounded-full" :style="`width: ${stats.progress}%`"></div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-zinc-900 p-5 rounded-xl border border-gray-100 dark:border-zinc-800 shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-gray-400 dark:text-zinc-500 uppercase">Active Modules</span>
                            <div class="p-1.5 bg-green-50 dark:bg-green-900/20 rounded-lg text-green-600 dark:text-green-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="text-3xl font-black text-gray-800 dark:text-zinc-200">{{ stats.assigned_modules }}</div>
                        <p class="text-xs text-gray-500 dark:text-zinc-400 mt-2">2 modules pending review</p>
                    </div>

                    <div class="bg-white dark:bg-zinc-900 p-5 rounded-xl border border-gray-100 dark:border-zinc-800 shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-gray-400 dark:text-zinc-500 uppercase">Days Left</span>
                            <div class="p-1.5 bg-orange-50 rounded-lg text-orange-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="text-3xl font-black text-gray-800 dark:text-zinc-200">{{ stats.days_remaining }}</div>
                        <p class="text-xs text-gray-500 dark:text-zinc-400 mt-2">End date: April 15, 2026</p>
                    </div>

                    <div @click="showPayslip = true"
                        class="bg-indigo-50 dark:bg-indigo-900/30 p-5 rounded-xl border border-indigo-100 shadow-sm cursor-pointer group hover:bg-indigo-100 dark:hover:bg-indigo-900/40 transition text-left">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-indigo-400 uppercase">Payroll</span>
                            <div class="p-1.5 bg-indigo-600 rounded-lg text-white">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="text-lg font-bold text-indigo-900 group-hover:translate-x-1 transition-transform">
                            Latest
                            Payslip</div>
                        <p class="text-xs text-indigo-600 dark:text-indigo-400 font-bold mt-2 flex items-center">
                            View Details
                            <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                            </svg>
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-1 space-y-8">
                        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                            <div class="bg-gray-50 dark:bg-zinc-800 px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                                <h4 class="font-bold text-gray-800 dark:text-zinc-200">My Profile</h4>
                            </div>
                            <div class="p-6">
                                <div class="flex items-center space-x-4 mb-6">
                                    <div
                                        class="h-16 w-16 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 dark:text-indigo-300 font-bold text-xl border-2 border-white shadow-md">
                                        {{ user.name.charAt(0) }}
                                    </div>
                                    <div>
                                        <h5 class="font-bold text-gray-900 dark:text-zinc-100">{{ user.name }}</h5>
                                        <p class="text-xs text-gray-500 dark:text-zinc-400 uppercase tracking-widest font-bold">Trainee ID:
                                            #10294
                                        </p>
                                    </div>
                                </div>
                                <div class="space-y-4 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-400 dark:text-zinc-500">Email</span>
                                        <span class="font-medium text-gray-700 dark:text-zinc-300">{{ user.email }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-400 dark:text-zinc-500">Access Level</span>
                                        <span
                                            class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 rounded text-[10px] font-black uppercase">Internal</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden h-full">
                            <div
                                class="bg-gray-50 dark:bg-zinc-800 px-6 py-4 border-b border-gray-100 dark:border-zinc-800 flex justify-between items-center">
                                <h4 class="font-bold text-gray-800 dark:text-zinc-200">Trainee Notifications</h4>
                                <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-1 rounded">
                                    {{ notifications.length }} New Alerts
                                </span>
                            </div>
                            <div class="p-6">
                                <div class="space-y-4">
                                    <div v-for="notif in notifications" :key="notif.id"
                                        class="p-4 rounded-xl border border-gray-100 dark:border-zinc-800 hover:border-indigo-200 hover:bg-indigo-50/30 transition-all cursor-pointer group">
                                        <div class="flex justify-between items-start mb-1">
                                            <div class="flex items-center">
                                                <div :class="{ 'bg-blue-100 dark:bg-blue-900/40 text-blue-600': notif.type === 'upcoming', 'bg-red-100 text-red-600': notif.type === 'late', 'bg-amber-100 text-amber-600': notif.type === 'action', 'bg-green-100 text-green-600': notif.type === 'info' }" class="p-2 rounded-lg mr-3">
                                                    <svg v-if="notif.type === 'upcoming'" class="w-4 h-4" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path
                                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                    <svg v-else-if="notif.type === 'late'" class="w-4 h-4" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path
                                                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </div>
                                                <p class="text-sm font-bold text-gray-800 dark:text-zinc-200">{{ notif.title }}</p>
                                            </div>
                                            <span :class="{ 'bg-blue-50 dark:bg-blue-900/30 text-blue-700': notif.type === 'upcoming', 'bg-red-50 text-red-700': notif.type === 'late', 'bg-amber-50 text-amber-700': notif.type === 'action', 'bg-green-50 text-green-700': notif.type === 'info' }" class="text-[10px] font-black uppercase px-2 py-0.5 rounded">
                                                {{ notif.status }}
                                            </span>
                                        </div>
                                        <div class="ml-11">
                                            <p class="text-xs text-gray-600 dark:text-zinc-400 leading-relaxed">{{ notif.message }}</p>
                                            <p class="text-[10px] text-gray-400 dark:text-zinc-500 mt-2 font-medium">{{ notif.date }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showPayslip" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="showPayslip = false"></div>
            <div
                class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden relative z-10 border border-indigo-100">
                <div class="p-8">
                    <div class="flex justify-between items-center mb-6 pb-6 border-b border-gray-100 dark:border-zinc-800">
                        <div>
                            <h3 class="text-2xl font-black text-gray-900 dark:text-zinc-100 tracking-tighter">Earnings Statement</h3>
                            <p class="text-sm text-gray-500 dark:text-zinc-400 font-medium">Payroll Period: {{ payslipDetails.period }}</p>
                        </div>
                        <div class="bg-indigo-600 text-white p-3 rounded-2xl shadow-lg shadow-indigo-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="grid grid-cols-2 gap-4 bg-gray-50 dark:bg-zinc-800 p-4 rounded-2xl border border-gray-100 dark:border-zinc-800">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 dark:text-zinc-500 uppercase">Trainee Name</p>
                                <p class="text-sm font-bold">{{ user.name }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 dark:text-zinc-500 uppercase">Account Status</p>
                                <p class="text-sm font-bold text-green-600 dark:text-green-400">Active / Verified</p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <h4 class="text-xs font-black text-gray-400 dark:text-zinc-500 uppercase tracking-widest">Allowance Breakdown
                            </h4>
                            <div class="flex justify-between py-2 border-b border-gray-50"><span
                                    class="text-gray-600 dark:text-zinc-400 text-sm">Monthly Basic Stipend</span><span
                                    class="font-bold">₱{{
                                    payslipDetails.basicAllowance.toFixed(2) }}</span></div>
                            <div class="flex justify-between py-2 border-b border-gray-50"><span
                                    class="text-gray-600 dark:text-zinc-400 text-sm">Transportation Allowance</span><span
                                    class="font-bold">₱{{
                                        payslipDetails.transportAllowance.toFixed(2) }}</span></div>
                            <div class="flex justify-between py-2"><span
                                    class="text-red-500 text-sm italic font-medium">Deductions (Late
                                    Penalties)</span><span class="font-bold text-red-600 dark:text-red-400">-₱{{
                                    payslipDetails.lateDeductions.toFixed(2) }}</span></div>
                        </div>

                        <div
                            class="bg-indigo-900 text-white p-6 rounded-2xl flex justify-between items-center shadow-xl shadow-indigo-100">
                            <span class="text-sm font-bold uppercase tracking-widest opacity-80">Net Allowance</span>
                            <span class="text-3xl font-black">₱{{ payslipDetails.netPay.toFixed(2) }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mt-8">
                        <button class="text-indigo-600 dark:text-indigo-400 text-sm font-bold hover:underline flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Download PDF
                        </button>
                        <button @click="showPayslip = false"
                            class="bg-gray-100 dark:bg-zinc-800 text-gray-700 dark:text-zinc-300 px-6 py-2 rounded-xl font-bold text-sm hover:bg-gray-200 transition">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showAnnouncements" class="fixed inset-0 z-50 overflow-hidden" aria-labelledby="slide-over-title"
            role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm transition-opacity"
                @click="showAnnouncements = false">
            </div>
            <div class="fixed inset-y-0 right-0 pl-10 max-w-full flex">
                <div class="w-screen max-w-md bg-white dark:bg-zinc-900 shadow-2xl flex flex-col">
                    <div class="px-6 py-8 bg-indigo-900 text-white">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="text-2xl font-black">Announcements</h3>
                            <button @click="showAnnouncements = false"
                                class="text-indigo-200 hover:text-white transition">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <p class="text-indigo-300 text-sm">Stay updated with the latest news from HR.</p>
                    </div>
                    <div class="flex-1 overflow-y-auto p-6 space-y-6">
                        <div class="relative pl-8 pb-6 border-l-2 border-indigo-100">
                            <div
                                class="absolute -left-[9px] top-0 h-4 w-4 rounded-full bg-indigo-600 ring-4 ring-white">
                            </div>
                            <span class="text-[10px] font-black text-indigo-600 dark:text-indigo-400 uppercase">Today</span>
                            <h5 class="font-bold text-gray-900 dark:text-zinc-100 mt-1">System Wide Update</h5>
                            <p class="text-sm text-gray-500 dark:text-zinc-400 mt-2 leading-relaxed">The ERP will undergo maintenance at
                                11:00 PM
                                tonight. Please save your progress.</p>
                        </div>
                        <div class="relative pl-8 pb-6 border-l-2 border-gray-100 dark:border-zinc-800">
                            <div class="absolute -left-[9px] top-0 h-4 w-4 rounded-full bg-gray-300 ring-4 ring-white">
                            </div>
                            <span class="text-[10px] font-black text-gray-400 dark:text-zinc-500 uppercase">Feb 14, 2026</span>
                            <h5 class="font-bold text-gray-700 dark:text-zinc-300 mt-1">Valentine's Day Social</h5>
                            <p class="text-sm text-gray-500 dark:text-zinc-400 mt-2 leading-relaxed">Join us for coffee in the main lobby
                                at 3:00
                                PM today!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showLeaveForm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="showLeaveForm = false"></div>
            <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden relative z-10">
                <div class="p-8">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-black text-gray-900 dark:text-zinc-100">Request Leave</h3>
                        <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg text-indigo-600 dark:text-indigo-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>
                    <form @submit.prevent="showLeaveForm = false" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-black text-gray-400 dark:text-zinc-500 uppercase mb-2">Start Date</label>
                                <input type="date"
                                    class="w-full border-gray-200 dark:border-zinc-700 rounded-xl focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-black text-gray-400 dark:text-zinc-500 uppercase mb-2">End Date</label>
                                <input type="date"
                                    class="w-full border-gray-200 dark:border-zinc-700 rounded-xl focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-black text-gray-400 dark:text-zinc-500 uppercase mb-2">Reason for
                                Absence</label>
                            <textarea rows="4"
                                class="w-full border-gray-200 dark:border-zinc-700 rounded-xl focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                placeholder="Please provide details..."></textarea>
                        </div>
                        <div class="flex items-center justify-end space-x-4 pt-4">
                            <button type="button" @click="showLeaveForm = false"
                                class="text-sm font-bold text-gray-400 dark:text-zinc-500 hover:text-gray-600">Discard</button>
                            <button type="submit"
                                class="bg-indigo-600 text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition">Submit
                                Application</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

<style scoped>
.fixed.inset-0.z-50.overflow-hidden {
    transition: opacity 0.3s ease-in-out;
}
</style>