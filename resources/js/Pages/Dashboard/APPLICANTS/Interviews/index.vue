<template>
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    My <span class="text-blue-600">Interviews</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">View and manage your scheduled interviews.</p>
            </div>
            <button
                @click="refreshInterviews"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-sm font-bold rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                </svg>
                Refresh
            </button>
        </div>

                <!-- ============================================ -->
                <!-- KPI METRICS ROW                              -->
                <!-- ============================================ -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                    <div
                        v-for="metric in metrics"
                        :key="metric.label"
                        class="relative bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4 overflow-hidden"
                    >
                        <span
                            class="absolute left-0 top-0 bottom-0 w-1 rounded-l-2xl"
                            :class="{
                                'bg-blue-600': metric.tone === 'navy',
                                'bg-blue-400': metric.tone === 'info',
                                'bg-emerald-500': metric.tone === 'success',
                                'bg-rose-500': metric.tone === 'danger',
                            }"
                        ></span>
                        <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ metric.label }}</div>
                        <div class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ metric.value }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ metric.subtext }}</div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- SEARCH & FILTERS                             -->
                <!-- ============================================ -->
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden mb-4">
                    <div class="p-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex flex-col md:flex-row md:items-center gap-3">
                        <div class="flex-1 relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                            </svg>
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search interviews..."
                                class="w-full pl-9 pr-4 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200 placeholder-slate-400"
                            />
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <select v-model="filterStatus" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                                <option value="All">All Statuses</option>
                                <option value="Scheduled">Scheduled</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Interview Count -->
                <div class="flex items-center justify-between mb-2 px-1">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Showing <span class="font-bold text-slate-900 dark:text-white">{{ filteredInterviews.length }}</span>
                        interview{{ filteredInterviews.length !== 1 ? 's' : '' }}
                    </p>
                    <p v-if="searchQuery || filterStatus !== 'All'"
                       class="px-3 py-1 rounded-full text-[11px] font-bold border bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border-blue-200 dark:border-blue-800">
                        Filters applied
                    </p>
                </div>

                <!-- ============================================ -->
                <!-- INTERVIEW CARDS                               -->
                <!-- ============================================ -->
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                    <div class="p-4">
                        <div v-if="loading" class="flex items-center justify-center py-12">
                            <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="ml-3 text-sm text-slate-500 dark:text-slate-400">Loading interviews...</span>
                        </div>

                        <div v-else-if="filteredInterviews.length > 0" class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                        <th class="py-3 px-4">Position</th>
                                        <th class="py-3 px-4">Type</th>
                                        <th class="py-3 px-4">Date &amp; Time</th>
                                        <th class="py-3 px-4">Interviewer</th>
                                        <th class="py-3 px-4 text-center">Status</th>
                                        <th class="py-3 px-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                    <tr v-for="interview in filteredInterviews" :key="interview.id" class="hover:bg-blue-50/50 dark:hover:bg-slate-900/40 transition-colors">
                                        <td class="py-3 px-4">
                                            <div class="text-xs font-bold text-slate-900 dark:text-white">{{ interview.position || 'Position' }}</div>
                                            <div v-if="interview.reschedule_status === 'requested'" class="mt-1 inline-flex items-center gap-1 text-[10px] font-bold text-amber-700 dark:text-amber-400">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                Reschedule requested
                                            </div>
                                        </td>
                                        <td class="py-3 px-4"><span class="text-xs text-slate-700 dark:text-slate-200">{{ interview.type || 'Interview' }}</span></td>
                                        <td class="py-3 px-4">
                                            <div class="text-xs font-medium text-slate-900 dark:text-white">{{ formatDate(interview.date) }}</div>
                                            <div class="text-[10px] text-slate-500 dark:text-slate-400">{{ formatTime(interview.start_time) }} - {{ formatTime(interview.end_time) }}</div>
                                            <div v-if="showInterviewLocation(interview)" class="text-[10px] text-slate-400 dark:text-slate-500">{{ interview.location || 'To be announced' }}</div>
                                        </td>
                                        <td class="py-3 px-4"><span class="text-xs text-slate-700 dark:text-slate-200">{{ interview.interviewer || 'N/A' }}</span></td>
                                        <td class="py-3 px-4 text-center">
                                            <span :class="['inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold border', getStatusBadgeClass(interview.status)]">{{ statusLabel(interview) }}</span>
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <div class="inline-flex items-center gap-2">
                                                <button
                                                    @click="viewInterviewDetails(interview)"
                                                    class="px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-blue-50 hover:border-blue-300 text-slate-600 dark:text-slate-300 hover:text-blue-600 rounded-lg text-[10px] font-bold transition inline-flex items-center gap-1"
                                                >
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                                    </svg>
                                                    View Details
                                                </button>
                                                <button
                                                    v-if="showMeetingLink(interview) && interview.status === 'Scheduled'"
                                                    @click="openMeeting(interview.meeting_link)"
                                                    class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-[10px] font-bold transition shadow-md inline-flex items-center gap-1"
                                                >
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.357-1.357a4.5 4.5 0 0 0-6.364-6.364l-1.757 1.757"/>
                                                    </svg>
                                                    Join Meeting
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="text-center py-12">
                            <div class="w-16 h-16 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700 flex items-center justify-center mx-auto mb-3">
                                <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                                </svg>
                            </div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white mb-1">No Interviews</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">You don't have any interviews scheduled at the moment.</p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-4 text-xs text-slate-400 dark:text-slate-400">
                    Showing {{ filteredInterviews.length }} {{ filteredInterviews.length === 1 ? 'interview' : 'interviews' }}
                </div>

        <!-- ============================================ -->
        <!-- ✅ INTERVIEW DETAIL MODAL                     -->
        <!-- ============================================ -->
        <div
            v-if="showDetailModal && selectedInterview"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            @click.self="closeDetailModal"
        >
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-2xl max-h-[90vh] overflow-hidden shadow-2xl flex flex-col">
                <!-- Modal Header -->
                <div class="p-6 border-b border-slate-100 dark:border-slate-700 shrink-0">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-xl bg-blue-600 flex items-center justify-center text-white font-black text-lg shadow-lg shadow-blue-500/30">
                                {{ getInitials(selectedInterview.position || 'I') }}
                            </div>
                            <div>
                                <h3 class="text-xl font-black text-slate-900 dark:text-white">{{ selectedInterview.position || 'Interview' }}</h3>
                                <span
                                    :class="[
                                        'px-3 py-1 rounded-full text-[11px] font-bold border mt-1 inline-flex',
                                        getStatusBadgeClass(selectedInterview.status)
                                    ]"
                                >{{ statusLabel(selectedInterview) }}</span>
                            </div>
                        </div>
                        <button
                            @click="closeDetailModal"
                            class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Modal Body - Scrollable -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    <!-- Quick Info -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700 rounded-xl">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Date</span>
                            <p class="text-sm font-black text-slate-900 dark:text-white">{{ formatDate(selectedInterview.date) }}</p>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700 rounded-xl">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Time</span>
                            <p class="text-sm font-black text-slate-900 dark:text-white">{{ formatTime(selectedInterview.start_time) }} - {{ formatTime(selectedInterview.end_time) }}</p>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700 rounded-xl">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Type</span>
                            <p class="text-sm font-black text-slate-900 dark:text-white">{{ selectedInterview.type || 'N/A' }}</p>
                        </div>
                        <div v-if="showInterviewLocation(selectedInterview)" class="p-3 bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700 rounded-xl">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Location</span>
                            <p class="text-sm font-black text-slate-900 dark:text-white">{{ selectedInterview.location || 'To be announced' }}</p>
                        </div>
                    </div>

                    <!-- Interviewer -->
                    <div v-if="selectedInterview.interviewer">
                        <h4 class="text-sm font-black text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                            </svg>
                            Interviewer
                        </h4>
                        <div class="text-sm text-slate-700 dark:text-slate-200 bg-slate-50 dark:bg-slate-900 p-3 rounded-xl border border-slate-100 dark:border-slate-700">
                            {{ selectedInterview.interviewer }}
                        </div>
                    </div>

                    <!-- Meeting Link -->
                    <div v-if="selectedInterview.meeting_link && showMeetingLink(selectedInterview) && selectedInterview.status === 'Scheduled'">
                        <h4 class="text-sm font-black text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.357-1.357a4.5 4.5 0 0 0-6.364-6.364l-1.757 1.757"/>
                            </svg>
                            Meeting Link
                        </h4>
                        <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-xl border border-blue-200 dark:border-blue-800">
                            <a :href="selectedInterview.meeting_link" target="_blank" class="text-sm text-blue-700 dark:text-blue-400 hover:underline font-bold break-all">
                                {{ selectedInterview.meeting_link }}
                            </a>
                        </div>
                    </div>

                    <!-- Status Timeline -->
                    <div>
                        <h4 class="text-sm font-black text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                            </svg>
                            Status
                        </h4>
                        <div class="bg-slate-50 dark:bg-slate-900 p-3 rounded-xl border border-slate-100 dark:border-slate-700">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-3 h-3 rounded-full"
                                    :class="{
                                        'bg-blue-500': selectedInterview.status === 'Scheduled',
                                        'bg-emerald-500': selectedInterview.status === 'Completed',
                                        'bg-rose-500': selectedInterview.status === 'Cancelled',
                                    }"
                                ></div>
                                <span class="text-sm font-bold text-slate-900 dark:text-white">
                                    {{ statusLabel(selectedInterview) }}
                                    <span v-if="selectedInterview.status === 'Scheduled'" class="text-xs text-slate-500 dark:text-slate-400 font-normal">
                                        - Waiting for interview date
                                    </span>
                                    <span v-else-if="selectedInterview.status === 'Completed'" class="text-xs text-emerald-600 dark:text-emerald-400 font-normal">
                                        - Interview completed
                                    </span>
                                    <span v-else-if="selectedInterview.status === 'Cancelled'" class="text-xs text-rose-600 dark:text-rose-400 font-normal">
                                        - {{ selectedInterview.cancelled_by === 'applicant' ? 'You cancelled this interview. Your application is still active.' : 'Interview cancelled' }}
                                    </span>
                                </span>
                            </div>
                            <div v-if="selectedInterview.created_at" class="mt-2 text-[11px] text-slate-400 dark:text-slate-400">
                                Scheduled on {{ formatDateTime(selectedInterview.created_at) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 shrink-0 space-y-3">
                    <!-- Reschedule form -->
                    <div v-if="showReschedule && selectedInterview?.status === 'Scheduled'" class="grid grid-cols-2 gap-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-3">
                        <input v-model="rescheduleForm.date" type="date" class="px-3 py-2 text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                        <div class="flex gap-2">
                            <input v-model="rescheduleForm.start_time" type="time" class="flex-1 px-3 py-2 text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                            <input v-model="rescheduleForm.end_time" type="time" class="flex-1 px-3 py-2 text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                        </div>
                        <input v-model="rescheduleForm.reason" type="text" placeholder="Reason (optional)" class="col-span-2 px-3 py-2 text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400" />
                        <div class="col-span-2 flex gap-2">
                            <button @click="submitReschedule" :disabled="actingId === selectedInterview?.id" class="flex-1 px-3 py-2 bg-amber-600 hover:bg-amber-700 disabled:opacity-60 text-white rounded-xl text-xs font-bold transition">
                                {{ actingId ? 'Saving...' : 'Submit reschedule' }}
                            </button>
                            <button @click="showReschedule = false" class="px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-xs font-bold">
                                Hide
                            </button>
                        </div>
                    </div>
                    <!-- Cancel reason -->
                    <div v-if="showCancel && selectedInterview?.status === 'Scheduled'" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-3 flex flex-col gap-1.5">
                        <div class="flex gap-2">
                            <input v-model="cancelReason" type="text" placeholder="Cancellation reason (required)" @input="cancelError = ''" @keyup.enter="submitCancel" class="flex-1 px-3 py-2 text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400" />
                            <button @click="submitCancel" :disabled="actingId === selectedInterview?.id || !cancelReason.trim()" class="px-3 py-2 bg-rose-600 hover:bg-rose-700 disabled:opacity-60 disabled:cursor-not-allowed text-white rounded-xl text-xs font-bold transition">
                                {{ actingId ? 'Saving...' : 'Confirm cancel' }}
                            </button>
                        </div>
                        <p v-if="cancelError" class="text-[11px] text-rose-600 dark:text-rose-400">{{ cancelError }}</p>
                    </div>
                    <div class="flex items-center justify-end gap-2 flex-wrap">
                    <button
                        @click="closeDetailModal"
                        class="px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-sm font-bold transition"
                    >
                        Close
                    </button>
                    <template v-if="selectedInterview?.status === 'Scheduled'">
                        <button
                            @click="showReschedule = !showReschedule; showCancel = false"
                            class="px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-sm font-bold transition active:scale-95"
                        >
                            Reschedule
                        </button>
                        <button
                            @click="showCancel = !showCancel; showReschedule = false"
                            class="px-4 py-2.5 bg-white dark:bg-slate-900 border border-rose-300 dark:border-rose-800 text-rose-600 dark:text-rose-400 rounded-xl text-sm font-bold transition"
                        >
                            Cancel
                        </button>
                    </template>
                    <button
                        v-if="showMeetingLink(selectedInterview) && selectedInterview.status === 'Scheduled'"
                        @click="openMeeting(selectedInterview.meeting_link)"
                        class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition shadow-lg shadow-blue-500/30 active:scale-95"
                    >
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.357-1.357a4.5 4.5 0 0 0-6.364-6.364l-1.757 1.757"/>
                        </svg>
                        Join Meeting
                    </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

// ============================================
// PROPS
// ============================================
const props = defineProps<{
    interviews: Array<{
        id: number;
        application_id: number;
        position: string;
        type: string;
        date: string;
        start_time: string;
        end_time: string;
        method: string | null;
        location: string;
        meeting_link: string | null;
        notes: string | null;
        interviewer: string | null;
        interviewer_id: number | null;
        status: string;
        created_at: string;
        updated_at: string;
    }>;
    metrics: {
        total: number;
        scheduled: number;
        completed: number;
        cancelled: number;
    };
}>();

// ============================================
// STATE
// ============================================
const searchQuery = ref('');
const filterStatus = ref('All');
const loading = ref(false);
const showDetailModal = ref(false);
const selectedInterview = ref<any>(null);
const actingId = ref<number | null>(null);
const showReschedule = ref(false);
const showCancel = ref(false);
const cancelReason = ref('');
const cancelError = ref('');
const rescheduleForm = ref({ date: '', start_time: '', end_time: '', reason: '' });

const interviews = ref(props.interviews || []);

// ============================================
// COMPUTED
// ============================================
const metrics = computed(() => {
    const total = interviews.value.length;
    const scheduled = interviews.value.filter(i => i.status === 'Scheduled').length;
    const completed = interviews.value.filter(i => i.status === 'Completed').length;
    const cancelled = interviews.value.filter(i => i.status === 'Cancelled').length;

    return [
        { label: 'Total', value: total, subtext: 'All interviews', tone: 'navy' },
        { label: 'Scheduled', value: scheduled, subtext: 'Upcoming interviews', tone: 'info' },
        { label: 'Completed', value: completed, subtext: 'Finished interviews', tone: 'success' },
        { label: 'Cancelled', value: cancelled, subtext: 'Cancelled interviews', tone: 'danger' },
    ];
});

const filteredInterviews = computed(() => {
    let filtered = interviews.value;

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(i =>
            i.position?.toLowerCase().includes(query) ||
            i.type?.toLowerCase().includes(query) ||
            i.location?.toLowerCase().includes(query)
        );
    }

    if (filterStatus.value !== 'All') {
        filtered = filtered.filter(i => i.status === filterStatus.value);
    }

    return filtered;
});

// ============================================
// METHODS
// ============================================
function getInitials(text: string): string {
    if (!text) return '?';
    return text.split(' ')
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase()
        .substring(0, 2);
}

function formatDate(date: string | null): string {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function formatTime(time: string | null): string {
    if (!time) return 'N/A';
    const [hours, minutes] = time.split(':');
    const h = parseInt(hours);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 || 12;
    return `${h12}:${minutes} ${ampm}`;
}

function formatDateTime(date: string | null): string {
    if (!date) return 'N/A';
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function getStatusBadgeClass(status: string): string {
    const classes: Record<string, string> = {
        'Scheduled': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'Completed': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'Cancelled': 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
    };
    return classes[status] || 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
}

// Distinguish who cancelled — kept separate from the stored 'Cancelled' status
// so metrics/filters keep working.
function statusLabel(iv: any): string {
    if (iv?.status === 'Cancelled' && iv?.cancelled_by === 'applicant') return 'Cancelled by Applicant';
    return iv?.status || 'Scheduled';
}

// Online meetings show the link and hide the location; onsite interviews
// show the location and hide the link. Unknown/legacy methods keep the
// previous behavior (show whatever is present).
function isOnlineInterview(iv: any): boolean {
    return String(iv?.method || '').toLowerCase() === 'online';
}

function isOnsiteInterview(iv: any): boolean {
    return String(iv?.method || '').toLowerCase() === 'onsite';
}

function locationMeaningful(value: any): boolean {
    const s = String(value ?? '').trim();
    return !!s && s.toLowerCase() !== 'n/a' && s !== '-';
}

function showInterviewLocation(iv: any): boolean {
    if (isOnlineInterview(iv)) return false;
    // No stored method (older records): a present link with no real location
    // still means online — hide the empty "N/A" location row.
    if (iv?.meeting_link) return locationMeaningful(iv?.location);
    return true;
}

function showMeetingLink(iv: any): boolean {
    return !!iv?.meeting_link && !isOnsiteInterview(iv);
}

function viewInterviewDetails(interview: any) {
    selectedInterview.value = interview;
    showDetailModal.value = true;
    showReschedule.value = false;
    showCancel.value = false;
    cancelReason.value = '';
    cancelError.value = '';
    rescheduleForm.value = { date: interview?.date || '', start_time: interview?.start_time || '', end_time: interview?.end_time || '', reason: '' };
    document.body.style.overflow = 'hidden';
}

function closeDetailModal() {
    showDetailModal.value = false;
    selectedInterview.value = null;
    showReschedule.value = false;
    showCancel.value = false;
    actingId.value = null;
    document.body.style.overflow = '';
}

function syncLocal(iv: any) {
    interviews.value = interviews.value.map((i: any) => i.id === iv.id ? { ...i, ...iv } : i);
    if (selectedInterview.value?.id === iv.id) {
        selectedInterview.value = { ...selectedInterview.value, ...iv };
    }
}

function confirmInterview(interview: any) {
    if (!interview || actingId.value) return;
    actingId.value = interview.id;
    router.post(route('applicant.interviews.confirm', interview.id), {}, {
        preserveScroll: true,
        onSuccess: () => syncLocal({ id: interview.id, status: 'Scheduled' }),
        onFinish: () => { actingId.value = null; refreshInterviews(); },
    });
}

function submitReschedule() {
    const iv = selectedInterview.value;
    if (!iv || actingId.value) return;
    actingId.value = iv.id;
    router.post(route('applicant.interviews.reschedule', iv.id), { ...rescheduleForm.value }, {
        preserveScroll: true,
        onSuccess: () => { showReschedule.value = false; },
        onFinish: () => { actingId.value = null; refreshInterviews(); },
    });
}

function submitCancel() {
    const iv = selectedInterview.value;
    if (!iv || actingId.value) return;
    const reason = cancelReason.value.trim();
    if (!reason) {
        cancelError.value = 'Please provide a reason for cancelling the interview.';
        return;
    }
    cancelError.value = '';
    actingId.value = iv.id;
    router.post(route('applicant.interviews.cancel', iv.id), { reason }, {
        preserveScroll: true,
        onSuccess: () => { showCancel.value = false; syncLocal({ id: iv.id, status: 'Cancelled', cancelled_by: 'applicant', cancel_reason: reason }); },
        onFinish: () => { actingId.value = null; refreshInterviews(); },
    });
}

function openMeeting(link: string | null) {
    if (link) {
        window.open(link, '_blank');
    }
}

function refreshInterviews() {
    loading.value = true;
    axios.get('/applicant/interviews')
        .then(response => {
            interviews.value = response.data;
        })
        .catch(error => {
            console.error('Failed to refresh interviews:', error);
        })
        .finally(() => {
            loading.value = false;
        });
}

// ============================================
// LIFECYCLE
// ============================================
onMounted(() => {
    if (props.interviews && props.interviews.length > 0) {
        interviews.value = props.interviews;
    }
});
</script>

<style scoped>
</style>