<template>
    <AuthenticatedLayout>
        <div class="flex min-h-screen bg-white">
            <div class="flex-1 p-8 text-[#1C2126] font-sans min-w-0 relative">

                <!-- Professional Header -->
                <header class="mb-8 relative">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                        <div>
                            <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3">
                                <span class="hover:text-slate-700 cursor-pointer transition-colors">Dashboard</span>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="text-slate-800 font-semibold">Recruitment</span>
                            </nav>
                            <h1 class="text-4xl font-bold tracking-tight text-slate-900">
                                Interviews
                            </h1>
                            <p class="text-sm text-slate-500 mt-1.5">
                                Manage candidates who have been shortlisted or scheduled for interviews.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <button
                                @click="activeTab = 'schedule'"
                                class="flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-md hover:shadow-lg"
                            >
                                <Calendar class="w-4 h-4" />Schedule Interview
                            </button>
                        </div>
                    </div>
                </header>

                <!-- KPI METRICS ROW -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <div
                        v-for="metric in metricsData"
                        :key="metric.label"
                        class="group bg-white rounded-2xl border border-slate-200 p-5 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 shadow-sm"
                    >
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ metric.label }}</span>
                            <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center group-hover:bg-blue-100 transition-all duration-300">
                                <component :is="metric.icon" class="w-5 h-5 text-slate-900" />
                            </div>
                        </div>
                        <div class="text-2xl font-bold text-slate-900">{{ metric.value }}</div>
                        <div v-if="metric.subtext" class="text-xs text-slate-400 mt-1">{{ metric.subtext }}</div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- TOGGLE BUTTONS                                -->
                <!-- ============================================ -->
                <div class="flex gap-2 mb-6">
                    <button
                        @click="activeTab = 'schedule'"
                        class="px-5 py-2 rounded-lg text-sm font-semibold transition-all duration-200 flex items-center gap-2"
                        :class="activeTab === 'schedule' 
                            ? 'bg-blue-600 text-white shadow-md hover:bg-blue-700' 
                            : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-100'"
                    >
                        <span><ClipboardList class="w-4 h-4" /></span> Candidates to Schedule
                        <span 
                            class="text-xs px-2 py-0.5 rounded-full"
                            :class="activeTab === 'schedule' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'"
                        >
                            {{ shortlistedCandidates.length }}
                        </span>
                    </button>
                    <button
                        @click="activeTab = 'upcoming'"
                        class="px-5 py-2 rounded-lg text-sm font-semibold transition-all duration-200 flex items-center gap-2"
                        :class="activeTab === 'upcoming' 
                            ? 'bg-purple-600 text-white shadow-md hover:bg-purple-700' 
                            : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-100'"
                    >
                        <span><Calendar class="w-4 h-4" /></span> Upcoming Interviews
                        <span 
                            class="text-xs px-2 py-0.5 rounded-full"
                            :class="activeTab === 'upcoming' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'"
                        >
                            {{ upcomingInterviews.length }}
                        </span>
                    </button>
                    <button
                        @click="activeTab = 'final'"
                        class="px-5 py-2 rounded-lg text-sm font-semibold transition-all duration-200 flex items-center gap-2"
                        :class="activeTab === 'final' 
                            ? 'bg-indigo-600 text-white shadow-md hover:bg-indigo-700' 
                            : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-100'"
                    >
                        <span><ClipboardList class="w-4 h-4" /></span> Final Interviews
                        <span 
                            class="text-xs px-2 py-0.5 rounded-full"
                            :class="activeTab === 'final' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'"
                        >
                            {{ finalInterviews.length }}
                        </span>
                    </button>
                    <button
                        @click="activeTab = 'completed'"
                    class="px-5 py-2 rounded-lg text-sm font-semibold transition-all duration-200 flex items-center gap-2"
                    :class="activeTab === 'completed' ? 'bg-emerald-600 text-white shadow-md hover:bg-emerald-700' : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-100'"
                >
                    <span><CheckCircle2 class="w-4 h-4" /></span> Interview History
                    <span class="text-xs px-2 py-0.5 rounded-full" :class="activeTab === 'completed' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'">{{ completedInterviews.length }}</span>
                    </button>
                </div>

                <!-- ============================================ -->
                <!-- SECTION 1: CANDIDATES TO SCHEDULE             -->
                <!-- ============================================ -->
                <div v-show="activeTab === 'schedule'" class="mb-8">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden">
                        <div class="p-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                            <div class="relative flex-1 min-w-[200px]">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3"><Search class="w-4 h-4 text-slate-400" /></span>
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search candidates..."
                                    class="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200"
                                />
                            </div>
                            <button
                                @click="refreshList"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold transition-all duration-200 shadow-md hover:shadow-lg"
                            >
                                <span class="inline-flex items-center gap-1.5"><RefreshCw class="w-3.5 h-3.5" />Refresh</span>
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                        <th class="py-3 px-5">Candidate</th>
                                        <th class="py-3 px-5">Position</th>
                                        <th class="py-3 px-5">Contact</th>
                                        <th class="py-3 px-5 text-center">Status</th>
                                        <th class="py-3 px-5 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="app in filteredShortlistedCandidates"
                                        :key="app.id"
                                        class="hover:bg-blue-50 transition-all duration-200 group"
                                    >
                                        <td class="py-3 px-5">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold text-xs shadow-md"
                                                >
                                                    {{ getInitials(app.first_name, app.last_name) }}
                                                </div>
                                                <div>
                                                    <div class="text-xs font-bold text-slate-900">{{ app.first_name }} {{ app.last_name }}</div>
                                                    <div class="text-[10px] text-slate-400">#{{ app.id }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-5">
                                            <span class="text-xs text-slate-700">{{ app.job_posting?.title || 'N/A' }}</span>
                                            <div class="text-[10px] text-slate-400">{{ app.job_posting?.department || '' }}</div>
                                        </td>
                                        <td class="py-3 px-5">
                                            <div class="text-xs text-slate-700">{{ app.email || 'N/A' }}</div>
                                            <div class="text-[10px] text-slate-400">{{ app.phone || 'N/A' }}</div>
                                        </td>
<td class="py-3 px-5 text-center">
                                            <span v-if="app.status === 'Shortlisted'" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-700 border border-emerald-200 shadow-sm">
                                                <Star class="w-3 h-3" />Shortlisted
                                            </span>
                                            <span v-else class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-100 text-indigo-700 border border-indigo-200 shadow-sm">
                                                <Calendar class="w-3 h-3" />Interview Scheduled
                                            </span>
                                        </td>
                                        <td class="py-3 px-5 text-right">
                                            <button
                                                v-if="app.status === 'Shortlisted'"
                                                @click="openScheduleModal(app)"
                                                class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-[10px] font-semibold transition-all duration-200 shadow-md hover:shadow-lg"
                                            >
<span class="inline-flex items-center gap-1"><Calendar class="w-3.5 h-3.5" />Schedule</span>
                                            </button>
                                            <span v-else class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg text-[10px] font-semibold bg-indigo-50 text-indigo-600 border border-indigo-100">
                                                <Calendar class="w-3 h-3" />Scheduled
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="filteredShortlistedCandidates.length === 0" class="text-center py-8">
                            <p class="text-sm text-slate-500">No candidates available for scheduling.</p>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- SECTION 2: UPCOMING INTERVIEWS                -->
                <!-- ============================================ -->
                <div v-show="activeTab === 'upcoming'">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden">
                        <div class="p-4 border-b border-slate-100 flex flex-wrap items-center gap-3">
                            <div class="relative flex-1 min-w-[200px]">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3"><Search class="w-4 h-4 text-slate-400" /></span>
                                <input
                                    v-model="interviewSearchQuery"
                                    type="text"
                                    placeholder="Search interviews..."
                                    class="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-purple-500 focus:border-transparent shadow-sm transition-all duration-200"
                                />
                            </div>
                            <select
                                v-model="filterStatus"
                                class="bg-white border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-600 focus:ring-2 focus:ring-purple-500 shadow-sm transition-all duration-200 cursor-pointer"
                            >
                                <option value="">All Statuses</option>
                                <option value="Scheduled">Scheduled</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                                <option value="No Show">No Show</option>
                            </select>
                            <button
                                @click="refreshList"
                                class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-xs font-semibold transition-all duration-200 shadow-md hover:shadow-lg"
                            >
                                <span class="inline-flex items-center gap-1.5"><RefreshCw class="w-3.5 h-3.5" />Refresh</span>
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                        <th class="py-3 px-5">Candidate</th>
                                        <th class="py-3 px-5">Position</th>
                                        <th class="py-3 px-5">Date & Time</th>
                                        <th class="py-3 px-5">Interviewer</th>
                                        <th class="py-3 px-5 text-center">Status</th>
                                        <th class="py-3 px-5 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="interview in filteredUpcomingInterviews"
                                        :key="interview.id"
                                        class="hover:bg-purple-50 transition-all duration-200 group"
                                    >
                                        <td class="py-3 px-5">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="w-8 h-8 rounded-full bg-purple-600 flex items-center justify-center text-white font-bold text-xs shadow-md"
                                                >
                                                    {{ getInitials(interview.candidate_name) }}
                                                </div>
                                                <div>
                                                    <div class="text-xs font-bold text-slate-900">{{ interview.candidate_name }}</div>
                                                    <div class="text-[10px] text-slate-400">#{{ interview.application_id }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-5">
                                            <span class="text-xs text-slate-700">{{ interview.position || 'N/A' }}</span>
                                        </td>
                                        <td class="py-3 px-5">
                                            <div class="text-xs font-medium text-slate-900">{{ formatDate(interview.date) }}</div>
                                            <div class="text-[10px] text-slate-500">{{ formatTime(interview.start_time) }} - {{ formatTime(interview.end_time) }}</div>
                                        </td>
                                        <td class="py-3 px-5">
                                            <span class="text-xs text-slate-700">{{ interview.interviewer?.name || 'Not assigned' }}</span>
                                        </td>
                                        <td class="py-3 px-5 text-center">
                                            <span
                                                :class="[
                                                    'inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold',
                                                    getInterviewStatusBadge(interview.status)
                                                ]"
                                            >{{ interview.status || 'Scheduled' }}</span>
                                        </td>
                                        <td class="py-3 px-5 text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                <button
                                                    @click="viewInterviewDetails(interview)"
                                                    class="px-3 py-1.5 bg-white border border-slate-200 hover:bg-purple-50 hover:border-purple-300 text-slate-600 hover:text-purple-600 rounded-lg text-[10px] font-semibold transition-all duration-200"
                                                >
<span class="inline-flex items-center gap-1"><Eye class="w-3.5 h-3.5" />View</span>
                                                </button>
                                                <button
                                                    v-if="interview.meeting_link && interview.status === 'Scheduled'"
                                                    @click="openMeeting(interview.meeting_link)"
                                                    class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[10px] font-semibold transition-all duration-200 shadow-md hover:shadow-lg"
                                                >
                                                    Join
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="filteredUpcomingInterviews.length === 0" class="text-center py-8">
                            <p class="text-sm text-slate-500">No upcoming interviews found.</p>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- SECTION 3: INTERVIEW HISTORY                  -->
                <!-- ============================================ -->
                <div v-show="activeTab === 'completed'">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-lg overflow-hidden">
                        <div class="p-4 border-b border-slate-100 flex flex-wrap items-center gap-3">
                            <div class="relative flex-1 min-w-[200px]">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3"><Search class="w-4 h-4 text-slate-400" /></span>
                                <input v-model="interviewSearchQuery" type="text" placeholder="Search interview history..." class="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 focus:border-transparent shadow-sm" />
                            </div>
                            <button @click="refreshList" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold shadow-md"><span class="inline-flex items-center gap-1.5"><RefreshCw class="w-3.5 h-3.5" />Refresh</span></button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead><tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                    <th class="py-3 px-5">Candidate</th><th class="py-3 px-5">Position</th><th class="py-3 px-5 text-center">Type</th><th class="py-3 px-5">Date</th><th class="py-3 px-5">Interviewer</th><th class="py-3 px-5 text-center">Status</th><th class="py-3 px-5 text-right">Action</th>
                                </tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="interview in completedInterviews.filter(i => !interviewSearchQuery || i.candidate_name?.toLowerCase().includes(interviewSearchQuery.toLowerCase()) || i.position?.toLowerCase().includes(interviewSearchQuery.toLowerCase()) || (i.interviewer?.name || getInterviewerName(i.interviewer_id))?.toLowerCase().includes(interviewSearchQuery.toLowerCase()))" :key="'history-'+interview.id" class="hover:bg-emerald-50 transition-colors">
                                        <td class="py-3 px-5 text-xs font-bold text-slate-900">{{ interview.candidate_name }}</td>
                                        <td class="py-3 px-5 text-xs text-slate-700">{{ interview.position || 'N/A' }}</td>
                                        <td class="py-3 px-5 text-center"><span :class="['inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold border', getInterviewTypeBadge(interview)]">{{ getInterviewTypeLabel(interview) }}</span></td>
                                        <td class="py-3 px-5 text-xs text-slate-700">{{ formatDate(interview.date) }}<div class="text-[10px] text-slate-500">{{ formatTime(interview.start_time) }} - {{ formatTime(interview.end_time) }}</div></td>
                                        <td class="py-3 px-5 text-xs text-slate-700">{{ interview.interviewer?.name || getInterviewerName(interview.interviewer_id) }}</td>
                                        <td class="py-3 px-5 text-center"><span :class="['inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold border', getInterviewStatusBadge(interview.status)]">{{ interview.status }}</span></td>
                                        <td class="py-3 px-5 text-right"><button @click="viewInterviewDetails(interview)" class="px-3 py-1.5 bg-white border border-slate-200 hover:bg-emerald-50 hover:border-emerald-300 text-slate-600 hover:text-emerald-600 rounded-lg text-[10px] font-semibold inline-flex items-center gap-1"><Eye class="w-3.5 h-3.5" />View</button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="completedInterviews.length === 0" class="text-center py-8"><p class="text-sm text-slate-500">No interview history found.</p></div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- SECTION 4: FINAL INTERVIEWS                  -->
                <!-- ============================================ -->
                <div v-show="activeTab === 'final'">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden">
                        <div class="p-4 border-b border-slate-100 flex flex-wrap items-center gap-3">
                            <div class="relative flex-1 min-w-[200px]">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3"><Search class="w-4 h-4 text-slate-400" /></span>
                                <input v-model="interviewSearchQuery" type="text" placeholder="Search final interviews..." class="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:border-transparent shadow-sm transition-all duration-200" />
                            </div>
                            <button @click="refreshList" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold transition-all duration-200 shadow-md hover:shadow-lg"><span class="inline-flex items-center gap-1.5"><RefreshCw class="w-3.5 h-3.5" />Refresh</span></button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                        <th class="py-3 px-5">Candidate</th>
                                        <th class="py-3 px-5">Position</th>
                                        <th class="py-3 px-5">Date & Time</th>
                                        <th class="py-3 px-5">Interviewer</th>
                                        <th class="py-3 px-5 text-center">Status</th>
                                        <th class="py-3 px-5">Recommendation</th>
                                        <th class="py-3 px-5 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="interview in filteredFinalInterviews" :key="interview.key" class="hover:bg-indigo-50 transition-all duration-200 group">
                                        <td class="py-3 px-5">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold text-xs shadow-md">{{ getInitials(interview.candidate_name) }}</div>
                                                <div>
                                                    <div class="text-xs font-bold text-slate-900">{{ interview.candidate_name }}</div>
                                                    <div class="text-[10px] text-slate-400">#{{ interview.application_id }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-5"><span class="text-xs text-slate-700">{{ interview.position || 'N/A' }}</span></td>
                                        <td class="py-3 px-5">
                                            <template v-if="interview._queue">
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-700 border border-amber-200">Not yet scheduled</span>
                                            </template>
                                            <template v-else>
                                                <div class="text-xs font-medium text-slate-900">{{ formatDate(interview.date) }}</div>
                                                <div class="text-[10px] text-slate-500">{{ formatTime(interview.start_time) }} - {{ formatTime(interview.end_time) }}</div>
                                            </template>
                                        </td>
                                        <td class="py-3 px-5">
                                            <span v-if="interview._queue" class="text-[10px] text-slate-400">Pending</span>
                                            <span v-else class="text-xs text-slate-700">{{ interview.interviewer?.name || 'Not assigned' }}</span>
                                        </td>
                                        <td class="py-3 px-5 text-center">
                                            <span v-if="interview._queue" class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-700 border border-amber-200">Pending Final Interview</span>
                                            <span v-else :class="['inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold', getInterviewStatusBadge(interview.status)]">{{ interview.status || 'Scheduled' }}</span>
                                        </td>
                                        <td class="py-3 px-5">
                                            <span v-if="interview.evaluation?.recommendation" :class="['inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold border', getEvaluationRecommendationBadge(interview.evaluation.recommendation)]">{{ getEvaluationRecommendationLabel(interview.evaluation.recommendation) }}</span>
                                            <span v-else class="text-[10px] text-slate-400">Not evaluated</span>
                                        </td>
                                        <td class="py-3 px-5 text-right">
                                            <button v-if="interview._queue" @click="scheduleFinalInterview(interview._sourceInterview)" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-[10px] font-semibold transition-all duration-200 shadow-md hover:shadow-lg"><span class="inline-flex items-center gap-1"><Calendar class="w-3.5 h-3.5" />Schedule Final Interview</span></button>
                                            <button v-else @click="viewInterviewDetails(interview)" class="px-3 py-1.5 bg-white border border-slate-200 hover:bg-indigo-50 hover:border-indigo-300 text-slate-600 hover:text-indigo-600 rounded-lg text-[10px] font-semibold transition-all duration-200"><span class="inline-flex items-center gap-1"><Eye class="w-3.5 h-3.5" />View</span></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="filteredFinalInterviews.length === 0" class="text-center py-8"><p class="text-sm text-slate-500">No final interviews found.</p></div>
                    </div>
                </div>

                <!-- RESCHEDULE MODAL -->
                <div v-if="showRescheduleModal && selectedInterview" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeRescheduleModal">
                    <div class="bg-white rounded-2xl border border-slate-200 w-full max-w-2xl max-h-[90vh] overflow-hidden shadow-2xl flex flex-col animate-modalIn">
                        <div class="p-6 border-b border-slate-100 bg-blue-50 flex items-center justify-between"><div><h3 class="text-lg font-bold text-slate-900">Reschedule Interview</h3><p class="text-xs text-slate-500 mt-1">Choose a new date and time for this interview.</p></div><button @click="closeRescheduleModal" class="p-2 hover:bg-slate-100 rounded-xl"><X class="w-4 h-4" /></button></div>
                        <div class="flex-1 overflow-y-auto p-6 space-y-4">
                            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200"><div class="text-sm font-bold text-slate-900">{{ selectedInterview.candidate_name }}</div><div class="text-xs text-slate-500">{{ selectedInterview.position }}</div><div class="text-xs text-slate-500 mt-1">Current: {{ formatDate(selectedInterview.date) }} · {{ formatTime(selectedInterview.start_time) }} - {{ formatTime(selectedInterview.end_time) }}</div></div>
                            <div class="grid grid-cols-2 gap-4"><div><label class="block text-xs font-semibold text-slate-600 mb-1.5">New Date *</label><input v-model="rescheduleForm.date" type="date" :min="todayDate" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm" /></div><div><label class="block text-xs font-semibold text-slate-600 mb-1.5">Interviewer *</label><div class="relative"><button type="button" @click="rescheduleMenuOpen = !rescheduleMenuOpen" class="w-full flex items-center gap-3 px-4 py-3 bg-white border border-slate-200 rounded-xl text-left hover:border-blue-300 hover:shadow-md transition-all duration-200"><span class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-600 to-blue-700 text-white text-xs font-bold flex items-center justify-center shrink-0 shadow">{{ interviewerInitials(selectedRescheduleInterviewer?.name) }}</span><span class="flex-1 min-w-0"><span class="block text-sm font-semibold text-slate-900 truncate">{{ selectedRescheduleInterviewer?.name || 'Select interviewer...' }}</span><span class="block text-[11px] text-slate-400 truncate">{{ selectedRescheduleInterviewer ? interviewerRoleLine(selectedRescheduleInterviewer) : 'Choose who will conduct this interview' }}</span></span><svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-200" :class="rescheduleMenuOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button><div v-if="rescheduleMenuOpen" class="fixed inset-0 z-40 cursor-default" @click="rescheduleMenuOpen = false"></div><div v-if="rescheduleMenuOpen" class="absolute left-0 right-0 top-full mt-2 z-50 bg-white border border-slate-200 rounded-xl shadow-2xl overflow-hidden"><div class="max-h-60 overflow-y-auto p-1.5"><button v-for="i in interviewers" :key="i.id" type="button" @click="pickRescheduleInterviewer(i.id)" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left transition-colors" :class="String(rescheduleForm.interviewer_id) === String(i.id) ? 'bg-blue-50' : 'hover:bg-slate-50'"><span class="w-9 h-9 rounded-full text-xs font-bold flex items-center justify-center shrink-0" :class="String(rescheduleForm.interviewer_id) === String(i.id) ? 'bg-gradient-to-br from-blue-600 to-blue-700 text-white shadow' : 'bg-slate-100 text-slate-500'">{{ interviewerInitials(i.name) }}</span><span class="flex-1 min-w-0"><span class="block text-sm font-semibold text-slate-900 truncate">{{ i.name }}</span><span class="block text-[11px] text-slate-400 truncate">{{ interviewerRoleLine(i) }}</span></span><svg v-if="String(rescheduleForm.interviewer_id) === String(i.id)" class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></button><div v-if="!interviewers.length" class="px-3 py-6 text-center text-xs text-slate-400">No interviewers available.</div></div></div></div></div></div>
                            <div class="grid grid-cols-2 gap-4"><div><label class="block text-xs font-semibold text-slate-600 mb-1.5">Start Time *</label><input v-model="rescheduleForm.start_time" type="time" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm" /></div><div><label class="block text-xs font-semibold text-slate-600 mb-1.5">End Time *</label><input v-model="rescheduleForm.end_time" type="time" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm" /></div></div>
                            <div><label class="block text-xs font-semibold text-slate-600 mb-1.5">Interview Method *</label><div class="flex gap-5"><label class="flex items-center gap-2 text-sm"><input type="radio" value="Online" v-model="rescheduleForm.method" /> Online</label><label class="flex items-center gap-2 text-sm"><input type="radio" value="Onsite" v-model="rescheduleForm.method" /> Onsite</label></div></div>
                            <div v-if="rescheduleForm.method === 'Online'"><label class="block text-xs font-semibold text-slate-600 mb-1.5">Meeting Link *</label><input v-model="rescheduleForm.meeting_link" type="url" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm" placeholder="https://meet.google.com/..." /></div>
                            <div v-else><label class="block text-xs font-semibold text-slate-600 mb-1.5">Location *</label><input v-model="rescheduleForm.location" type="text" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm" placeholder="HR Office / Conference Room" /></div>
                            <div><label class="block text-xs font-semibold text-slate-600 mb-1.5">Reason *</label><textarea v-model="rescheduleForm.reason" rows="3" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm" placeholder="Reason for rescheduling..."></textarea></div>
                        </div>
                        <div class="p-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3"><button @click="closeRescheduleModal" class="px-5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold">Back</button><button @click="rescheduleInterview" :disabled="isSubmitting" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold disabled:opacity-50">↻ Confirm Reschedule</button></div>
                    </div>
                </div>

                <!-- CANCEL MODAL -->
                <div v-if="showCancelModal && selectedInterview" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeCancelModal">
                    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl animate-modalIn"><div class="p-6 border-b border-slate-100 bg-rose-50"><h3 class="text-lg font-bold text-slate-900">Cancel Interview</h3><p class="text-xs text-slate-500 mt-1">This will return the application to Shortlisted.</p></div><div class="p-6"><label class="block text-xs font-semibold text-slate-600 mb-1.5">Reason *</label><textarea v-model="cancelForm.reason" rows="4" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm" placeholder="Why is the interview being cancelled?"></textarea></div><div class="p-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3"><button @click="closeCancelModal" class="px-5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold">Keep Interview</button><button @click="cancelInterview" :disabled="isSubmitting" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-sm font-bold disabled:opacity-50">Cancel Interview</button></div></div>
                </div>

                <!-- EVALUATION MODAL -->
                <div v-if="showEvaluationModal && selectedInterview" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeEvaluationModal">
                    <div class="bg-white rounded-2xl w-full max-w-3xl max-h-[90vh] overflow-hidden shadow-2xl flex flex-col animate-modalIn">
                        <div class="p-6 border-b border-slate-100 bg-purple-50 shrink-0">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-900">Interview Evaluation</h3>
                                    <p class="text-xs text-slate-500 mt-1">Rate the candidate, record the result and the interviewer recommendation.</p>
                                </div>
                                <button @click="closeEvaluationModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all duration-200 hover:rotate-90 transform"><X class="w-4 h-4" /></button>
                            </div>
                            <div class="mt-4 p-4 bg-white rounded-xl border border-slate-200">
                                <div class="flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-full bg-purple-600 flex items-center justify-center text-white font-bold text-sm shadow-sm shrink-0">{{ getInitials(selectedInterview.candidate_name) }}</div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-bold text-slate-900 truncate">{{ selectedInterview.candidate_name }}</div>
                                        <div class="text-xs text-slate-500 truncate">{{ selectedInterview.position }}</div>
                                    </div>
                                </div>
                                <div class="mt-3 grid grid-cols-3 gap-3">
                                    <div class="p-2.5 bg-slate-50 rounded-lg border border-slate-100"><span class="text-[10px] text-slate-400 block">Interview Type</span><span class="text-xs font-semibold text-slate-800">{{ selectedInterview.type || 'N/A' }}</span></div>
                                    <div class="p-2.5 bg-slate-50 rounded-lg border border-slate-100"><span class="text-[10px] text-slate-400 block">Interviewer</span><span class="text-xs font-semibold text-slate-800">{{ selectedInterview.interviewer?.name || getInterviewerName(selectedInterview.interviewer_id) || 'Not assigned' }}</span></div>
                                    <div class="p-2.5 bg-slate-50 rounded-lg border border-slate-100"><span class="text-[10px] text-slate-400 block">Interview Date</span><span class="text-xs font-semibold text-slate-800">{{ formatDate(selectedInterview.date) }}</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto p-6 space-y-6">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Candidate Ratings</h4>
                                    <span class="text-[10px] text-slate-400">1 = Poor · 2 = Below Expectations · 3 = Meets Expectations · 4 = Good · 5 = Excellent</span>
                                </div>
                                <div class="space-y-4">
                                    <div v-for="criterion in evaluationCriteria" :key="criterion.key" class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                                        <div class="flex items-center justify-between mb-2">
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-700">{{ criterion.label }}</label>
                                                <span class="block text-[10px] text-slate-400">{{ criterion.description }}</span>
                                            </div>
                                            <span v-if="ratingLabelFor(evaluationForm[criterion.key])" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 border border-purple-200">{{ ratingLabelFor(evaluationForm[criterion.key]) }}</span>
                                        </div>
                                        <div class="flex gap-1.5">
                                            <button v-for="r in ratingScale" :key="r.value" type="button" @click="evaluationForm[criterion.key] = r.value" :title="r.label"
                                                :class="[Number(evaluationForm[criterion.key]) === r.value ? 'bg-purple-600 text-white border-purple-600 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:border-purple-400 hover:text-purple-600']"
                                                class="flex-1 py-2 rounded-lg border text-xs font-bold transition-colors">{{ r.value }}</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 rounded-xl border bg-gradient-to-r from-purple-50 to-blue-50 border-purple-200">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-[10px] text-purple-500 font-bold uppercase tracking-wider block">Overall Score</span>
                                        <span class="text-xs text-slate-500">Computed by the system from the ratings above.</span>
                                    </div>
                                    <div class="text-2xl font-bold text-slate-900">{{ evaluationPreviewScore ? evaluationPreviewScore + ' / 5' : '—' }}</div>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Written Evaluation</h4>
                                <div class="space-y-4">
                                    <div><label class="block text-xs font-semibold text-slate-600 mb-1.5">Strengths</label><textarea v-model="evaluationForm.strengths" rows="2" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm" placeholder="What the candidate did well..."></textarea></div>
                                    <div><label class="block text-xs font-semibold text-slate-600 mb-1.5">Areas for Improvement / Concerns</label><textarea v-model="evaluationForm.concerns" rows="2" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm" placeholder="Concerns and areas the candidate should improve..."></textarea></div>
                                    <div><label class="block text-xs font-semibold text-slate-600 mb-1.5">Interview Notes</label><textarea v-model="evaluationForm.interview_notes" rows="3" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm" placeholder="Additional notes about this interview..."></textarea></div>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Interview Result *</h4>
                                <div class="grid grid-cols-3 gap-3">
                                    <label v-for="opt in evaluationResultOptions" :key="opt.value"
                                        :class="[evaluationForm.result === opt.value ? 'border-purple-600 bg-purple-50 ring-1 ring-purple-600' : 'border-slate-200 bg-white hover:border-slate-300']"
                                        class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition-colors">
                                        <input type="radio" :value="opt.value" v-model="evaluationForm.result" class="accent-purple-600" />
                                        <span class="text-xs font-semibold text-slate-800">{{ opt.label }}</span>
                                    </label>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Interviewer Recommendation *</h4>
                                <div class="space-y-2">
                                    <label v-for="opt in evaluationRecommendationOptions" :key="opt.value"
                                        :class="[evaluationForm.recommendation === opt.value ? 'border-purple-600 bg-purple-50 ring-1 ring-purple-600' : 'border-slate-200 bg-white hover:border-slate-300']"
                                        class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-colors">
                                        <input type="radio" :value="opt.value" v-model="evaluationForm.recommendation" class="accent-purple-600 mt-0.5" />
                                        <div>
                                            <div class="text-sm font-semibold text-slate-800">{{ opt.label }}</div>
                                            <div v-if="opt.description" class="text-[11px] text-slate-500 mt-0.5">{{ opt.description }}</div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3 shrink-0">
                            <button @click="closeEvaluationModal" class="px-5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold">Back</button>
                            <button @click="completeInterview" :disabled="isSubmitting" class="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-sm font-bold disabled:opacity-50"><span class="inline-flex items-center gap-1.5"><Check class="w-4 h-4" />Submit Evaluation</span></button>
                        </div>
                    </div>
                </div>

                <!-- SELECT CANDIDATE MODAL -->
                <div v-if="showSelectModal && selectedInterview" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeSelectModal">
                    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl animate-modalIn"><div class="p-6 border-b border-slate-100 bg-emerald-50"><h3 class="text-lg font-bold text-slate-900">Select Candidate</h3><p class="text-xs text-slate-500 mt-1">Move this applicant to the Selected stage.</p></div><div class="p-6 space-y-3"><div class="p-4 bg-slate-50 rounded-xl"><div class="text-sm font-bold text-slate-900">{{ selectedInterview.candidate_name }}</div><div class="text-xs text-slate-500">{{ selectedInterview.position }}</div></div><p class="text-sm text-slate-600">The interview result is <strong class="text-emerald-600">Passed</strong>. Are you sure you want to select this candidate?</p></div><div class="p-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3"><button @click="closeSelectModal" class="px-5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold">Cancel</button><button @click="selectCandidate" :disabled="isSubmitting" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold disabled:opacity-50"><span class="inline-flex items-center gap-1.5"><Star class="w-4 h-4" />Confirm Selection</span></button></div></div>
                </div>

                <!-- ============================================ -->
                <!-- ✅ VIEW PROFILE MODAL - SCROLLABLE            -->
                <!-- ============================================ -->
                <div
                    v-if="showProfileModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                    @click.self="closeProfileModal"
                >
                    <div class="bg-white rounded-2xl border border-slate-200 w-full max-w-4xl max-h-[90vh] overflow-hidden shadow-2xl flex flex-col">
                        <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50 shrink-0">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">Applicant Profile</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Full details of the candidate</p>
                            </div>
                            <button @click="closeProfileModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all duration-200 hover:rotate-90 transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <div class="flex-1 overflow-y-auto p-6">
                            <div v-if="isLoadingProfile" class="flex items-center justify-center py-12">
                                <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="ml-3 text-sm text-slate-500">Loading profile...</span>
                            </div>
                            <div v-else-if="profileData" class="space-y-6">
                                <!-- Personal Info -->
                                <div>
                                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                                        <span class="w-1 h-4 bg-blue-500 rounded-full"></span>
                                        Personal Information
                                    </h4>
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 bg-slate-50 rounded-xl p-4">
                                        <div><span class="text-[10px] text-slate-400 block">Full Name</span><span class="text-sm font-semibold text-slate-900">{{ profileData.personal?.first_name }} {{ profileData.personal?.last_name }}</span></div>
                                        <div><span class="text-[10px] text-slate-400 block">Email</span><span class="text-sm font-semibold text-slate-900">{{ profileData.personal?.email || 'N/A' }}</span></div>
                                        <div><span class="text-[10px] text-slate-400 block">Phone</span><span class="text-sm font-semibold text-slate-900">{{ profileData.personal?.phone || 'N/A' }}</span></div>
                                        <div><span class="text-[10px] text-slate-400 block">Gender</span><span class="text-sm font-semibold text-slate-900">{{ profileData.personal?.gender || 'N/A' }}</span></div>
                                        <div><span class="text-[10px] text-slate-400 block">Civil Status</span><span class="text-sm font-semibold text-slate-900">{{ profileData.personal?.civil_status || 'N/A' }}</span></div>
                                        <div><span class="text-[10px] text-slate-400 block">Date of Birth</span><span class="text-sm font-semibold text-slate-900">{{ formatDate(profileData.personal?.birth_date) }}</span></div>
                                        <div><span class="text-[10px] text-slate-400 block">Place of Birth</span><span class="text-sm font-semibold text-slate-900">{{ profileData.personal?.place_of_birth || 'N/A' }}</span></div>
                                        <div><span class="text-[10px] text-slate-400 block">Citizenship</span><span class="text-sm font-semibold text-slate-900">{{ profileData.personal?.citizenship || 'N/A' }}</span></div>
                                        <div><span class="text-[10px] text-slate-400 block">Religion</span><span class="text-sm font-semibold text-slate-900">{{ profileData.personal?.religion || 'N/A' }}</span></div>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                                        <span class="w-1 h-4 bg-blue-500 rounded-full"></span>
                                        Address
                                    </h4>
                                    <div class="bg-slate-50 rounded-xl p-4">
                                        <span class="text-sm text-slate-900">
                                            {{ profileData.address?.street || '' }}
                                            {{ profileData.address?.barangay ? ', ' + profileData.address.barangay : '' }}
                                            {{ profileData.address?.city ? ', ' + profileData.address.city : '' }}
                                            {{ profileData.address?.province ? ', ' + profileData.address.province : '' }}
                                            {{ profileData.address?.zip_code ? ' ' + profileData.address.zip_code : '' }}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                                        <span class="w-1 h-4 bg-blue-500 rounded-full"></span>
                                        Education
                                    </h4>
                                    <div class="bg-slate-50 rounded-xl p-4 space-y-2">
                                        <div><span class="text-[10px] text-slate-400">Highest Education:</span> <span class="text-sm font-semibold text-slate-900">{{ profileData.education?.highest_education || 'N/A' }}</span></div>
                                        <div><span class="text-[10px] text-slate-400">School/University:</span> <span class="text-sm font-semibold text-slate-900">{{ profileData.education?.school || 'N/A' }}</span></div>
                                        <div><span class="text-[10px] text-slate-400">Course/Program:</span> <span class="text-sm font-semibold text-slate-900">{{ profileData.education?.course || 'N/A' }}</span></div>
                                        <div><span class="text-[10px] text-slate-400">Graduation Year:</span> <span class="text-sm font-semibold text-slate-900">{{ profileData.education?.graduation_year || 'N/A' }}</span></div>
                                        <div><span class="text-[10px] text-slate-400">Special Skills:</span> <span class="text-sm font-semibold text-slate-900">{{ profileData.education?.special_skills || 'N/A' }}</span></div>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                                        <span class="w-1 h-4 bg-blue-500 rounded-full"></span>
                                        Work Experience
                                    </h4>
                                    <div v-if="profileData.employment?.work_experience?.length > 0" class="space-y-3">
                                        <div v-for="(exp, idx) in profileData.employment.work_experience" :key="idx" class="bg-slate-50 rounded-xl p-4">
                                            <div class="font-semibold text-slate-900">{{ exp.position }} at {{ exp.company }}</div>
                                            <div class="text-xs text-slate-500">{{ formatDate(exp.start_date) }} - {{ exp.end_date ? formatDate(exp.end_date) : 'Present' }}</div>
                                            <div v-if="exp.responsibilities" class="text-sm text-slate-600 mt-1">{{ exp.responsibilities }}</div>
                                        </div>
                                    </div>
                                    <div v-else class="bg-slate-50 rounded-xl p-4 text-sm text-slate-500">No work experience recorded.</div>
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                                        <span class="w-1 h-4 bg-blue-500 rounded-full"></span>
                                        Emergency Contact
                                    </h4>
                                    <div class="bg-slate-50 rounded-xl p-4 grid grid-cols-2 gap-4">
                                        <div><span class="text-[10px] text-slate-400 block">Name</span><span class="text-sm font-semibold text-slate-900">{{ profileData.emergency?.name || 'N/A' }}</span></div>
                                        <div><span class="text-[10px] text-slate-400 block">Relationship</span><span class="text-sm font-semibold text-slate-900">{{ profileData.emergency?.relationship || 'N/A' }}</span></div>
                                        <div><span class="text-[10px] text-slate-400 block">Phone</span><span class="text-sm font-semibold text-slate-900">{{ profileData.emergency?.phone || 'N/A' }}</span></div>
                                        <div><span class="text-[10px] text-slate-400 block">Address</span><span class="text-sm font-semibold text-slate-900">{{ profileData.emergency?.address || 'N/A' }}</span></div>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                                        <span class="w-1 h-4 bg-blue-500 rounded-full"></span>
                                        Notes
                                    </h4>
                                    <div class="bg-slate-50 rounded-xl p-4">
                                        <p class="text-sm text-slate-700">{{ profileData.application?.notes || 'No notes available.' }}</p>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                                        <span class="w-1 h-4 bg-blue-500 rounded-full"></span>
                                        Screening Result
                                    </h4>
                                    <div class="bg-slate-50 rounded-xl p-4">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 border border-emerald-200"><CheckCircle2 class="w-3.5 h-3.5" />Passed</span>
                                            <span class="text-sm text-slate-600">- Shortlisted for interview</span>
                                        </div>
                                        <div v-if="profileData.screening?.notes" class="mt-2 text-sm text-slate-600">
                                            <span class="text-[10px] text-slate-400">Screening Notes:</span>
                                            <p class="mt-1">{{ profileData.screening.notes }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 border-t border-slate-100 bg-slate-50 flex items-center justify-end gap-3 shrink-0">
                            <button
                                v-if="profileData?.application?.id && profileData?.application?.status === 'Shortlisted'"
                                @click="openScheduleModal(profileData.application)"
                                class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-md hover:shadow-md"
                            >
                                <span class="inline-flex items-center gap-1.5"><Calendar class="w-4 h-4" />Schedule Interview</span>
                            </button>
                            <span
                                v-else-if="profileData?.application?.status === 'Interview Scheduled'"
                                class="px-5 py-2.5 bg-slate-200 text-slate-600 rounded-xl text-sm font-semibold"
                            >
                                Interview Scheduled
                            </span>
                            <button @click="closeProfileModal" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-all duration-200 shadow-sm hover:shadow-md">Close</button>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- ✅ SCHEDULE INTERVIEW MODAL                   -->
                <!-- ============================================ -->
                <div
                    v-if="showScheduleModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                    @click.self="closeScheduleModal"
                >
                    <div class="bg-white rounded-2xl border border-slate-200 w-full max-w-2xl max-h-[90vh] overflow-hidden shadow-2xl flex flex-col">
                        <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50 shrink-0">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">Schedule Interview</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Schedule an interview for the selected candidate</p>
                            </div>
                            <button @click="closeScheduleModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all duration-200 hover:rotate-90 transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <div class="flex-1 overflow-y-auto p-6 space-y-5">
                            <!-- Selected Applicant Display -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Applicant</label>
                                <div v-if="scheduleForm.candidate_name" class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold text-sm">
                                            {{ getInitials(scheduleForm.candidate_name) }}
                                        </div>
                                        <div>
                                            <div class="text-sm font-semibold text-slate-900">{{ scheduleForm.candidate_name }}</div>
                                            <div class="text-xs text-slate-500">{{ scheduleForm.position }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-amber-700 text-sm flex items-center gap-2">
                                    <AlertTriangle class="w-4 h-4 flex-shrink-0" />
                                    <span>No applicant selected. Please go back and select a candidate.</span>
                                </div>
                            </div>

                            <!-- Interview Type -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Interview Type</label>
                                <div class="p-3 bg-blue-50 rounded-xl border border-blue-200">
                                    <span class="text-sm font-bold text-slate-900 inline-flex items-center gap-2">
                                        <ClipboardList class="w-4 h-4 text-blue-600" />{{ scheduleForm.type }}
                                    </span>
                                </div>
                            </div>

                            <!-- Interviewer -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Interviewer</label>
                                <div class="relative">
                                    <button
                                        type="button"
                                        @click="scheduleMenuOpen = !scheduleMenuOpen"
                                        class="w-full flex items-center gap-3 px-4 py-3 bg-white border border-slate-200 rounded-xl shadow-sm hover:border-blue-300 hover:shadow-md transition-all duration-200 text-left"
                                    >
                                        <span class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-600 to-blue-700 text-white text-xs font-bold flex items-center justify-center shrink-0 shadow">
                                            {{ interviewerInitials(selectedScheduleInterviewer?.name) }}
                                        </span>
                                        <span class="flex-1 min-w-0">
                                            <span class="block text-sm font-semibold text-slate-900 truncate">{{ selectedScheduleInterviewer?.name || 'Select interviewer...' }}</span>
                                            <span class="block text-[11px] text-slate-400 truncate">{{ selectedScheduleInterviewer ? interviewerRoleLine(selectedScheduleInterviewer) : 'Choose who will conduct this interview' }}</span>
                                        </span>
                                        <svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-200" :class="scheduleMenuOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                    <div v-if="scheduleMenuOpen" class="fixed inset-0 z-40 cursor-default" @click="scheduleMenuOpen = false"></div>
                                    <div v-if="scheduleMenuOpen" class="absolute left-0 right-0 top-full mt-2 z-50 bg-white border border-slate-200 rounded-xl shadow-2xl overflow-hidden">
                                        <div class="max-h-60 overflow-y-auto p-1.5">
                                            <button
                                                v-for="person in interviewers"
                                                :key="person.id"
                                                type="button"
                                                @click="pickScheduleInterviewer(person.id)"
                                                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left transition-colors"
                                                :class="String(scheduleForm.interviewer_id) === String(person.id) ? 'bg-blue-50' : 'hover:bg-slate-50'"
                                            >
                                                <span class="w-9 h-9 rounded-full text-xs font-bold flex items-center justify-center shrink-0" :class="String(scheduleForm.interviewer_id) === String(person.id) ? 'bg-gradient-to-br from-blue-600 to-blue-700 text-white shadow' : 'bg-slate-100 text-slate-500'">
                                                    {{ interviewerInitials(person.name) }}
                                                </span>
                                                <span class="flex-1 min-w-0">
                                                    <span class="block text-sm font-semibold text-slate-900 truncate">{{ person.name }}</span>
                                                    <span class="block text-[11px] text-slate-400 truncate">{{ interviewerRoleLine(person) }}</span>
                                                </span>
                                                <svg v-if="String(scheduleForm.interviewer_id) === String(person.id)" class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </button>
                                            <div v-if="!interviewers.length" class="px-3 py-6 text-center text-xs text-slate-400">No interviewers available.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Date - UPDATED: min attribute to prevent past dates -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Date</label>
                                <div class="relative">
                                    <input 
                                        v-model="scheduleForm.date" 
                                        type="date" 
                                        :min="todayDate"
                                        class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm text-slate-700 focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" 
                                    />
                                    <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none">
                                        <span class="text-slate-400"><Calendar class="w-4 h-4" /></span>
                                    </div>
                                </div>
                                <p v-if="validationErrors.includes('Please select a date.')" class="text-xs text-red-500 mt-1">Please select a valid future date.</p>
                            </div>

                            <!-- Start Time & End Time -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Start Time</label>
                                    <div class="relative">
                                        <select v-model="scheduleForm.start_time" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm text-slate-700 focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200 cursor-pointer appearance-none">
                                            <option value="08:00">8:00 AM</option>
                                            <option value="08:30">8:30 AM</option>
                                            <option value="09:00">9:00 AM</option>
                                            <option value="09:30">9:30 AM</option>
                                            <option value="10:00">10:00 AM</option>
                                            <option value="10:30">10:30 AM</option>
                                            <option value="11:00">11:00 AM</option>
                                            <option value="11:30">11:30 AM</option>
                                            <option value="12:00">12:00 PM</option>
                                            <option value="13:00">1:00 PM</option>
                                            <option value="13:30">1:30 PM</option>
                                            <option value="14:00">2:00 PM</option>
                                            <option value="14:30">2:30 PM</option>
                                            <option value="15:00">3:00 PM</option>
                                            <option value="15:30">3:30 PM</option>
                                            <option value="16:00">4:00 PM</option>
                                            <option value="16:30">4:30 PM</option>
                                            <option value="17:00">5:00 PM</option>
                                        </select>
                                        <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none">
                                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">End Time</label>
                                    <div class="relative">
                                        <select v-model="scheduleForm.end_time" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm text-slate-700 focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200 cursor-pointer appearance-none">
                                            <option value="08:30">8:30 AM</option>
                                            <option value="09:00">9:00 AM</option>
                                            <option value="09:30">9:30 AM</option>
                                            <option value="10:00">10:00 AM</option>
                                            <option value="10:30">10:30 AM</option>
                                            <option value="11:00">11:00 AM</option>
                                            <option value="11:30">11:30 AM</option>
                                            <option value="12:00">12:00 PM</option>
                                            <option value="12:30">12:30 PM</option>
                                            <option value="13:00">1:00 PM</option>
                                            <option value="13:30">1:30 PM</option>
                                            <option value="14:00">2:00 PM</option>
                                            <option value="14:30">2:30 PM</option>
                                            <option value="15:00">3:00 PM</option>
                                            <option value="15:30">3:30 PM</option>
                                            <option value="16:00">4:00 PM</option>
                                            <option value="16:30">4:30 PM</option>
                                            <option value="17:00">5:00 PM</option>
                                            <option value="17:30">5:30 PM</option>
                                            <option value="18:00">6:00 PM</option>
                                        </select>
                                        <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none">
                                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </div>
                                    </div>
                                    <p v-if="validationErrors.includes('End time must be after start time.')" class="text-xs text-red-500 mt-1">End time must be after start time.</p>
                                </div>
                            </div>

                            <!-- Interview Method -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Interview Method</label>
                                <div class="flex gap-4 mt-1">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" value="Online" v-model="scheduleForm.method" class="w-4 h-4 text-blue-600 border-slate-300 focus:ring-blue-500" />
                                        <span class="text-sm text-slate-700 inline-flex items-center gap-1.5"><Monitor class="w-4 h-4" />Online</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" value="Onsite" v-model="scheduleForm.method" class="w-4 h-4 text-blue-600 border-slate-300 focus:ring-blue-500" />
                                        <span class="text-sm text-slate-700 inline-flex items-center gap-1.5"><MapPin class="w-4 h-4" />Onsite</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Meeting Link (Online) -->
                            <div v-if="scheduleForm.method === 'Online'">
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Meeting Link <span class="text-red-500">*</span></label>
                                <input v-model="scheduleForm.meeting_link" type="url" placeholder="https://meet.google.com/..." class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm text-slate-700 focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                                <p v-if="validationErrors.includes('Please provide a meeting link for the online interview.')" class="text-xs text-red-500 mt-1">Please provide a meeting link for the online interview.</p>
                            </div>

                            <!-- Location (Onsite) -->
                            <div v-if="scheduleForm.method === 'Onsite'">
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Location <span class="text-red-500">*</span></label>
                                <input v-model="scheduleForm.location" type="text" placeholder="e.g., HR Office, Conference Room A" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm text-slate-700 focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                                <p v-if="validationErrors.includes('Please provide a location for the onsite interview.')" class="text-xs text-red-500 mt-1">Please provide a location for the onsite interview.</p>
                            </div>

                            <!-- Notes -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Notes / Instructions</label>
                                <textarea v-model="scheduleForm.notes" rows="3" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm text-slate-700 focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200 resize-none" placeholder="Add any notes or instructions for the candidate..."></textarea>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="p-4 border-t border-slate-100 bg-slate-50 flex items-center justify-end gap-3 shrink-0">
                            <button @click="closeScheduleModal" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-all duration-200 shadow-sm hover:shadow-md">Cancel</button>
                            <button @click="openConfirmationModal" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-md hover:shadow-md"><span class="inline-flex items-center gap-1.5"><Calendar class="w-4 h-4" />Schedule Interview</span></button>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- ✅ CONFIRMATION MODAL                        -->
                <!-- ============================================ -->
                <div
                    v-if="showConfirmationModal"
                    class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                    @click.self="closeConfirmationModal"
                >
                    <div class="bg-white rounded-2xl border border-slate-200 w-full max-w-md max-h-[90vh] overflow-hidden shadow-2xl flex flex-col animate-modalIn">
                        <div class="p-6 border-b border-slate-100 bg-emerald-50 flex items-center justify-between shrink-0">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-display text-lg font-bold text-slate-900">Confirm Interview Schedule</h3>
                                    <p class="text-xs text-slate-500 mt-0.5">Please review the interview details before confirming</p>
                                </div>
                            </div>
                            <button @click="closeConfirmationModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all duration-200 hover:rotate-90 transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <div class="flex-1 overflow-y-auto p-6 space-y-4">
                            <div class="bg-slate-50 rounded-xl p-4 space-y-3">
                                <div class="flex justify-between text-sm"><span class="text-slate-500">Candidate</span><span class="font-semibold text-slate-900">{{ scheduleForm.candidate_name || 'Not selected' }}</span></div>
                                <div class="flex justify-between text-sm"><span class="text-slate-500">Position</span><span class="font-semibold text-slate-900">{{ scheduleForm.position || 'N/A' }}</span></div>
                                <div class="flex justify-between text-sm"><span class="text-slate-500">Interview Type</span><span class="font-semibold text-slate-900">{{ scheduleForm.type }}</span></div>
                                <div class="flex justify-between text-sm"><span class="text-slate-500">Interviewer</span><span class="font-semibold text-slate-900">{{ getInterviewerName(scheduleForm.interviewer_id) }}</span></div>
                                <div class="flex justify-between text-sm"><span class="text-slate-500">Date</span><span class="font-semibold text-slate-900">{{ formatDateDisplay(scheduleForm.date) }}</span></div>
                                <div class="flex justify-between text-sm"><span class="text-slate-500">Time</span><span class="font-semibold text-slate-900">{{ formatTimeDisplay(scheduleForm.start_time) }} - {{ formatTimeDisplay(scheduleForm.end_time) }}</span></div>
                                <div class="flex justify-between text-sm"><span class="text-slate-500">Method</span><span class="font-semibold text-slate-900">{{ scheduleForm.method }}</span></div>
                                <div v-if="scheduleForm.method === 'Online' && scheduleForm.meeting_link" class="flex justify-between text-sm"><span class="text-slate-500">Meeting Link</span><a :href="scheduleForm.meeting_link" target="_blank" class="font-semibold text-blue-600 hover:underline truncate max-w-[200px]">{{ scheduleForm.meeting_link }}</a></div>
                                <div v-if="scheduleForm.method === 'Onsite' && scheduleForm.location" class="flex justify-between text-sm"><span class="text-slate-500">Location</span><span class="font-semibold text-slate-900">{{ scheduleForm.location }}</span></div>
                                <div v-if="scheduleForm.notes" class="text-sm"><span class="text-slate-500 block mb-1">Notes:</span><p class="text-slate-700 bg-white rounded-lg p-2 border border-slate-200">{{ scheduleForm.notes }}</p></div>
                            </div>
                            <p class="text-xs text-slate-500 text-center">The interview details will be saved to the applicant's recruitment record.</p>
                            <div v-if="validationErrors.length > 0" class="bg-red-50 border border-red-200 rounded-xl p-3">
                                <p class="text-xs text-red-600 font-semibold mb-1">Please fix the following:</p>
                                <ul class="list-disc list-inside text-xs text-red-600 space-y-0.5">
                                    <li v-for="error in validationErrors" :key="error">{{ error }}</li>
                                </ul>
                            </div>
                        </div>
                        <div class="p-4 border-t border-slate-100 bg-slate-50 flex items-center justify-end gap-3 shrink-0">
                            <button @click="closeConfirmationModal" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 rounded-xl text-sm font-semibold transition-all duration-200 shadow-sm hover:shadow-md">Cancel</button>
                            <button @click="confirmSchedule" :disabled="isSubmitting" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-md hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed">
                                <span v-if="isSubmitting" class="flex items-center gap-2">
                                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Confirming...
                                </span>
                                <span v-else class="inline-flex items-center gap-1.5"><CheckCircle2 class="w-4 h-4" />Confirm Schedule</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- ✅ TOAST NOTIFICATION                         -->
                <!-- ============================================ -->
                <div
                    v-if="showToast"
                    class="fixed top-4 right-4 z-[70] max-w-sm w-full bg-white rounded-2xl border shadow-2xl animate-slideIn"
                    :class="toastType === 'success' ? 'border-emerald-200' : 'border-red-200'"
                >
                    <div class="flex items-start gap-3 p-4">
                        <div
                            class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                            :class="toastType === 'success' ? 'bg-emerald-100' : 'bg-red-100'"
                        >
                            <CheckCircle2 v-if="toastType === 'success'" class="w-5 h-5 text-emerald-600" />
                            <XCircle v-else class="w-5 h-5 text-red-600" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-900">{{ toastTitle }}</p>
                            <p class="text-xs text-slate-600 mt-0.5">{{ toastMessage }}</p>
                        </div>
                        <button @click="showToast = false" class="p-1 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-slate-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- ✅ INTERVIEW DETAILS MODAL                   -->
                <!-- ============================================ -->
                <div
                    v-if="showDetailModal && selectedInterview"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                    @click.self="closeDetailModal"
                >
                    <div class="bg-white rounded-2xl border border-slate-200 w-full max-w-2xl max-h-[90vh] overflow-hidden shadow-2xl flex flex-col animate-modalIn">
                        <div class="p-6 border-b border-slate-100 bg-purple-50 flex items-start justify-between shrink-0">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-full bg-purple-600 flex items-center justify-center text-white font-display font-bold text-lg shadow-sm">
                                    {{ getInitials(selectedInterview.candidate_name) }}
                                </div>
                                <div>
                                    <h3 class="font-display text-lg font-bold text-slate-900">Interview Details</h3>
                                    <p class="text-xs text-slate-500">{{ selectedInterview.position }}</p>
                                </div>
                            </div>
                            <button @click="closeDetailModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all duration-200 hover:rotate-90 transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <div class="flex-1 overflow-y-auto p-6 space-y-5">
                            <div class="flex items-center justify-between">
                                <span :class="['inline-flex px-2.5 py-1 rounded-full text-xs font-semibold', getInterviewStatusBadge(selectedInterview.status)]">{{ selectedInterview.status || 'Scheduled' }}</span>
                                <span class="text-xs text-slate-400">#{{ selectedInterview.id }}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200"><span class="text-[10px] text-slate-400 block">Type</span><span class="text-sm font-semibold text-slate-900">{{ selectedInterview.type || 'N/A' }}</span></div>
                                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200"><span class="text-[10px] text-slate-400 block">Method</span><span class="text-sm font-semibold text-slate-900">{{ selectedInterview.method || 'N/A' }}</span></div>
                                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200"><span class="text-[10px] text-slate-400 block">Date</span><span class="text-sm font-semibold text-slate-900">{{ formatDate(selectedInterview.date) }}</span></div>
                                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200"><span class="text-[10px] text-slate-400 block">Time</span><span class="text-sm font-semibold text-slate-900">{{ formatTime(selectedInterview.start_time) }} - {{ formatTime(selectedInterview.end_time) }}</span></div>
                            </div>
                            <div>
                                <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Interviewer</h4>
                                <div class="text-sm text-slate-700 bg-slate-50 p-2.5 rounded-xl border border-slate-200">{{ selectedInterview.interviewer?.name || getInterviewerName(selectedInterview.interviewer_id) }}</div>
                            </div>
                            <div v-if="selectedInterview.method === 'Online' && selectedInterview.meeting_link">
                                <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Meeting Link</h4>
                                <div class="bg-blue-50 p-2.5 rounded-xl border border-blue-200">
                                    <a :href="selectedInterview.meeting_link" target="_blank" class="text-sm text-blue-600 hover:underline font-medium break-all">{{ selectedInterview.meeting_link }}</a>
                                </div>
                            </div>
                            <div v-if="selectedInterview.method === 'Onsite' && selectedInterview.location">
                                <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Location</h4>
                                <div class="text-sm text-slate-700 bg-slate-50 p-2.5 rounded-xl border border-slate-200">{{ selectedInterview.location }}</div>
                            </div>
                            <div v-if="selectedInterview.notes">
                                <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Notes</h4>
                                <div class="text-sm text-slate-700 bg-slate-50 p-2.5 rounded-xl border border-slate-200 whitespace-pre-wrap">{{ selectedInterview.notes }}</div>
                            </div>
                            <div v-if="selectedInterview.status === 'Completed'" class="border-t border-slate-200 pt-4">
                                <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Result</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200"><span class="text-[10px] text-slate-400 block">Result</span><span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-semibold mt-1', getInterviewResultBadge(selectedInterview.result)]">{{ selectedInterview.result || 'Pending' }}</span></div>
                                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200"><span class="text-[10px] text-slate-400 block">Application Status</span><span class="text-sm font-semibold text-slate-900">{{ selectedInterview.applicant?.status || 'N/A' }}</span></div>
                                </div>
                                <div v-if="isStructuredEvaluation(selectedInterview.evaluation)" class="mt-4 space-y-3">
                                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                                        <div class="flex items-center justify-between mb-3">
                                            <div><span class="text-[10px] text-slate-400 block">Overall Score</span><span class="text-xl font-bold text-slate-900">{{ Number(selectedInterview.evaluation.overall_score).toFixed(2) }} / 5</span></div>
                                            <span :class="['inline-flex px-2.5 py-1 rounded-full text-[10px] font-semibold border', getEvaluationRecommendationBadge(selectedInterview.evaluation.recommendation)]">{{ getEvaluationRecommendationLabel(selectedInterview.evaluation.recommendation) }}</span>
                                        </div>
                                        <div class="grid grid-cols-4 gap-2.5">
                                            <div v-for="criterion in evaluationCriteria" :key="criterion.key" class="p-2.5 bg-white rounded-lg border border-slate-100">
                                                <span class="text-[10px] text-slate-400 block">{{ criterion.label }}</span>
                                                <span :class="[Number(selectedInterview.evaluation[criterion.key]) >= 4 ? 'text-emerald-600' : Number(selectedInterview.evaluation[criterion.key]) === 3 ? 'text-amber-600' : 'text-rose-600']" class="text-sm font-bold">{{ selectedInterview.evaluation[criterion.key] }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="selectedInterview.evaluation.strengths" class="p-3 bg-emerald-50 rounded-xl border border-emerald-200">
                                        <span class="text-[10px] text-emerald-600 font-semibold uppercase tracking-wider block mb-1">Strengths</span>
                                        <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ selectedInterview.evaluation.strengths }}</p>
                                    </div>
                                    <div v-if="selectedInterview.evaluation.concerns" class="p-3 bg-amber-50 rounded-xl border border-amber-200">
                                        <span class="text-[10px] text-amber-600 font-semibold uppercase tracking-wider block mb-1">Areas for Improvement / Concerns</span>
                                        <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ selectedInterview.evaluation.concerns }}</p>
                                    </div>
                                    <div v-if="selectedInterview.evaluation.interview_notes" class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                                        <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider block mb-1">Interview Notes</span>
                                        <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ selectedInterview.evaluation.interview_notes }}</p>
                                    </div>
                                    <div v-if="selectedInterview.evaluation.submitted_at" class="text-[10px] text-slate-400">Submitted {{ formatDate(selectedInterview.evaluation.submitted_at) }}</div>
                                </div>
                                <div v-else-if="typeof selectedInterview.evaluation === 'string' && selectedInterview.evaluation" class="mt-3">
                                    <span class="text-[10px] text-slate-400 block mb-1">Evaluation</span>
                                    <div class="text-sm text-slate-700 bg-slate-50 p-2.5 rounded-xl border border-slate-200 whitespace-pre-wrap">{{ selectedInterview.evaluation }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 border-t border-slate-100 bg-slate-50 flex flex-wrap items-center justify-end gap-2 shrink-0">
                            <button v-if="selectedInterview.status === 'Scheduled'" @click="openRescheduleModal(selectedInterview)" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md">↻ Reschedule</button>
                            <button v-if="selectedInterview.status === 'Scheduled'" @click="openCancelModal(selectedInterview)" class="px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow-md inline-flex items-center gap-1"><X class="w-3.5 h-3.5" />Cancel</button>
                            <button v-if="['Scheduled','No Show'].includes(selectedInterview.status)" @click="markNoShow(selectedInterview)" class="px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold shadow-md inline-flex items-center gap-1"><AlertTriangle class="w-3.5 h-3.5" />No Show</button>
                            <button v-if="selectedInterview.status === 'Scheduled'" @click="openEvaluationModal(selectedInterview)" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold shadow-md inline-flex items-center gap-1"><Check class="w-3.5 h-3.5" />Start Evaluation</button>
                            <button v-if="selectedInterview.status === 'Completed' && selectedInterview.result === 'Passed' && selectedInterview.applicant?.status === 'Interviewed'" @click="openSelectModal(selectedInterview)" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md inline-flex items-center gap-1"><Star class="w-3.5 h-3.5" />Select Candidate</button>
                            <button v-if="selectedInterview.status === 'Completed' && !isFinalInterview(selectedInterview) && selectedInterview.evaluation?.recommendation === 'proceed_to_final_interview' && ['Shortlisted','Interview Scheduled','Initial Interview','Final Interview','Interviewed'].includes(selectedInterview.applicant?.status)" @click="scheduleFinalInterview(selectedInterview)" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-md inline-flex items-center gap-1"><Calendar class="w-3.5 h-3.5" />Schedule Final Interview</button>
                            <button v-if="selectedInterview.status === 'Completed' && isFinalInterview(selectedInterview) && ['proceed_to_onboarding_training', 'proceed_to_onboarding', 'recommend_onboarding_training'].includes(selectedInterview.evaluation?.recommendation) && !['Onboard','Onboarding','Hired','Rejected','Withdrawn'].includes(selectedInterview.applicant?.status)" @click="advanceToOnboarding(selectedInterview)" class="px-4 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold shadow-md inline-flex items-center gap-1"><ArrowRight class="w-3.5 h-3.5" />Advance to Onboarding</button>
                            <button @click="closeDetailModal" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 rounded-xl text-sm font-semibold transition-all duration-200 shadow-sm hover:shadow-md">Close</button>
                            <button v-if="selectedInterview.meeting_link && selectedInterview.status === 'Scheduled'" @click="openMeeting(selectedInterview.meeting_link)" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-md hover:shadow-md">
                                <svg class="w-4 h-4 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.357-1.357a4.5 4.5 0 0 0-6.364-6.364l-1.757 1.757"/>
                                </svg>
                                Join Meeting
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Calendar, ClipboardList, CheckCircle2, Search, RefreshCw, Star, Eye, X, Check, AlertTriangle, Monitor, MapPin, BarChart3, XCircle, ArrowRight } from 'lucide-vue-next';

// ============================================
// PROPS
// ============================================
const props = defineProps({
    applicants: {
        type: Array,
        default: () => []
    },
    metrics: {
        type: Object,
        default: () => ({
            total: 0,
            shortlisted: 0,
            interview_scheduled: 0,
            interviewed: 0
        })
    }
});

// ============================================
// STATE
// ============================================
const activeTab = ref('schedule');
const searchQuery = ref('');
const interviewSearchQuery = ref('');
const filterStatus = ref('');
const showProfileModal = ref(false);
const showScheduleModal = ref(false);
const showConfirmationModal = ref(false);
const showDetailModal = ref(false);
const showRescheduleModal = ref(false);
const showCancelModal = ref(false);
const showEvaluationModal = ref(false);
const showSelectModal = ref(false);
const showToast = ref(false);
const toastTitle = ref('');
const toastMessage = ref('');
const toastType = ref('success');
const isLoadingProfile = ref(false);
const isSubmitting = ref(false);
const profileData = ref(null);
const selectedInterview = ref(null);
const applicants = ref(props.applicants || []);
const interviews = ref([]);
const interviewers = ref([]);
const validationErrors = ref([]);

// Custom (non-native) interviewer dropdown state + helpers.
const scheduleMenuOpen = ref(false);
const rescheduleMenuOpen = ref(false);
const interviewerRoleLine = (u) => {
    const role = u?.job_position || u?.position || '';
    const dept = u?.department || '';
    if (role && dept) return `${role} · ${dept}`;
    return role || dept || 'Interviewer';
};
const interviewerInitials = (name) => String(name || '?').trim().split(/\s+/).map(w => w.charAt(0)).join('').slice(0, 2).toUpperCase();
const selectedScheduleInterviewer = computed(() => interviewers.value.find(i => String(i.id) === String(scheduleForm.value.interviewer_id)) || null);
const selectedRescheduleInterviewer = computed(() => interviewers.value.find(i => String(i.id) === String(rescheduleForm.value.interviewer_id)) || null);
const pickScheduleInterviewer = (id) => { scheduleForm.value.interviewer_id = id; scheduleMenuOpen.value = false; };
const pickRescheduleInterviewer = (id) => { rescheduleForm.value.interviewer_id = id; rescheduleMenuOpen.value = false; };

// The /list endpoint returns a Laravel paginator ({data:[...]}) while older
// payloads may be bare arrays — normalize so .filter() never crashes.
const normalizeRows = (v) => {
    if (Array.isArray(v)) return v.filter((r) => r && typeof r === 'object');
    if (v && Array.isArray(v.data)) return v.data.filter((r) => r && typeof r === 'object');
    return [];
};
const interviewRows = computed(() => normalizeRows(interviews.value));

// Get today's date for min attribute
const todayDate = ref('');

// Schedule Form
const scheduleForm = ref({
    application_id: null,
    job_posting_id: null,
    candidate_name: '',
    position: '',
    type: 'Initial Interview',
    interviewer_id: null,
    date: '',
    start_time: '09:00',
    end_time: '10:00',
    method: 'Online',
    location: '',
    meeting_link: '',
    notes: '',
});

const rescheduleForm = ref({
    interview_id: null,
    candidate_name: '',
    date: '',
    start_time: '09:00',
    end_time: '10:00',
    interviewer_id: 2,
    method: 'Online',
    location: '',
    meeting_link: '',
    reason: '',
});

const cancelForm = ref({
    interview_id: null,
    reason: '',
});

const evaluationCriteria = [
    { key: 'communication_skills', label: 'Communication Skills', description: 'Clarity, articulation, and responsiveness.' },
    { key: 'relevant_skills', label: 'Relevant Skills', description: 'Skill set aligned with the role.' },
    { key: 'technical_knowledge', label: 'Technical Knowledge', description: 'Understanding of tools, systems, and processes.' },
    { key: 'problem_solving', label: 'Problem Solving', description: 'Approach to challenges and decision-making.' },
    { key: 'work_experience', label: 'Work Experience', description: 'Relevant prior experience and achievements.' },
    { key: 'adaptability', label: 'Adaptability', description: 'Flexibility to change and new situations.' },
    { key: 'teamwork', label: 'Teamwork', description: 'Collaboration and interpersonal skills.' },
    { key: 'professionalism', label: 'Professionalism', description: 'Attitude, integrity, and work ethic.' },
];

const ratingScale = [
    { value: 1, label: 'Poor' },
    { value: 2, label: 'Below Expectations' },
    { value: 3, label: 'Meets Expectations' },
    { value: 4, label: 'Good' },
    { value: 5, label: 'Excellent' },
];

const evaluationResultOptions = [
    { value: 'passed', label: 'Passed' },
    { value: 'failed', label: 'Failed' },
    { value: 'pending_review', label: 'Pending Review' },
];

const doNotProceedOption = { value: 'do_not_proceed', label: 'Do Not Proceed', description: 'Do not recommend this candidate for the next steps.' };
const rejectOption = { value: 'reject', label: 'Reject', description: 'Reject the candidate for this position.' };
const proceedToOnboardingOption = { value: 'proceed_to_onboarding', label: 'Proceed to Onboarding', description: 'The candidate moves directly to HR onboarding on submit.' };

// Passed → only forward/reject options. Failed → only Do Not Proceed.
// Pending review → neutral options only.
const evaluationRecommendationOptionsPassedInitial = [
    { value: 'proceed_to_final_interview', label: 'Proceed to Final Interview', description: 'Recommend moving this candidate to the next interview stage.' },
    proceedToOnboardingOption,
    rejectOption,
];

const evaluationRecommendationOptionsPassedFinal = [
    proceedToOnboardingOption,
    rejectOption,
];

const evaluationRecommendationOptionsFailed = [doNotProceedOption];

const evaluationRecommendationOptionsPending = [
    doNotProceedOption,
    { value: 'further_assessment_required', label: 'Further Assessment Required', description: 'Recommend the candidate for a further assessment before a decision is made. (Reschedule is a separate manual action.)' },
];

const isFinalInterview = (interview) => ['final', 'final interview'].includes(String(interview?.type ?? '').toLowerCase());

const evaluationRecommendationOptions = computed(() => {
    const result = evaluationForm.value.result;
    if (result === 'passed') {
        return isFinalInterview(selectedInterview.value)
            ? evaluationRecommendationOptionsPassedFinal
            : evaluationRecommendationOptionsPassedInitial;
    }
    if (result === 'failed') return evaluationRecommendationOptionsFailed;
    return evaluationRecommendationOptionsPending;
});

const evaluationForm = ref({
    interview_id: null,
    communication_skills: null,
    relevant_skills: null,
    technical_knowledge: null,
    problem_solving: null,
    work_experience: null,
    adaptability: null,
    teamwork: null,
    professionalism: null,
    strengths: '',
    concerns: '',
    interview_notes: '',
    result: 'passed',
    recommendation: 'proceed_to_final_interview',
});

// When the result changes, drop any recommendation that is no longer valid.
// (Declared after evaluationForm so the watcher never reads it before init.)
watch(() => evaluationForm.value.result, () => {
    const valid = evaluationRecommendationOptions.value.some(o => o.value === evaluationForm.value.recommendation);
    if (!valid) evaluationForm.value.recommendation = evaluationRecommendationOptions.value[0].value;
});

// ============================================
// COMPUTED
// ============================================
const metricsData = computed(() => {
    const shortlisted = props.metrics?.shortlisted || 0;
    const interviewScheduled = props.metrics?.interview_scheduled || 0;
    const interviewed = props.metrics?.interviewed || 0;
    const total = props.metrics?.total || 0;
    
    return [
        {
            label: 'Shortlisted',
            value: shortlisted,
            icon: Star,
            subtext: 'Ready for interview'
        },
        {
            label: 'Interview Scheduled',
            value: interviewScheduled,
            icon: Calendar,
            subtext: 'Upcoming interviews'
        },
        {
            label: 'Interviewed',
            value: interviewed,
            icon: CheckCircle2,
            subtext: 'Completed interviews'
        },
        {
            label: 'Total',
            value: total,
            icon: BarChart3,
            subtext: 'All candidates'
        }
    ];
});

const shortlistedCandidates = computed(() => {
    return applicants.value.filter(app => ['Shortlisted', 'Interview Scheduled', 'Initial Interview', 'Final Interview'].includes(app.status));
});

const filteredShortlistedCandidates = computed(() => {
    if (!searchQuery.value) return shortlistedCandidates.value;
    const query = searchQuery.value.toLowerCase();
    return shortlistedCandidates.value.filter(app => 
        `${app.first_name} ${app.last_name}`.toLowerCase().includes(query) ||
        app.email?.toLowerCase().includes(query) ||
        app.job_posting?.title?.toLowerCase().includes(query)
    );
});

const upcomingInterviews = computed(() => {
    return interviewRows.value.filter(interview => interview.status === 'Scheduled');
});

const completedInterviews = computed(() => {
    return interviewRows.value.filter(interview => ['Completed', 'Cancelled', 'No Show'].includes(interview.status));
});

const finalInterviews = computed(() => {
    const isFinal = (interview) => ['final', 'final interview'].includes(String(interview.type ?? '').toLowerCase());
    const rows = [];
    const withFinal = new Set();

    interviewRows.value.filter(isFinal).forEach(i => {
        if (i.applicant) withFinal.add(i.applicant.id);
        rows.push(Object.assign({ key: 'final-' + i.id, _queue: false, _sourceInterview: i }, i));
    });

    interviewRows.value
        .filter(i => !isFinal(i) && i.status === 'Completed' && i.evaluation?.recommendation === 'proceed_to_final_interview')
        .forEach(i => {
            // HrmInterview relation is `applicant` (there is no `application` relation).
            const app = i.applicant || i.application;
            if (!app) return;
            if (withFinal.has(app.id)) return;
            if (rows.some(r => r._queue && r.application_id === app.id)) return;
            rows.push({
                key: 'queue-' + app.id,
                _queue: true,
                _sourceInterview: i,
                application_id: app.id,
                candidate_name: i.candidate_name,
                position: i.position,
                date: null,
                start_time: null,
                end_time: null,
                interviewer: i.interviewer,
                status: 'Pending Final Interview',
                evaluation: i.evaluation,
            });
        });

    return rows.sort((a, b) => {
        if (a._queue !== b._queue) return a._queue ? -1 : 1;
        const da = a.date ? new Date(a.date) : new Date();
        const db = b.date ? new Date(b.date) : new Date();
        return da - db;
    });
});

const filteredFinalInterviews = computed(() => {
    if (!interviewSearchQuery.value) return finalInterviews.value;
    const query = interviewSearchQuery.value.toLowerCase();
    return finalInterviews.value.filter(i =>
        i.candidate_name?.toLowerCase().includes(query) ||
        i.position?.toLowerCase().includes(query)
    );
});

const filteredUpcomingInterviews = computed(() => {
    let filtered = upcomingInterviews.value;
    
    if (interviewSearchQuery.value) {
        const query = interviewSearchQuery.value.toLowerCase();
        filtered = filtered.filter(i => 
            i.candidate_name?.toLowerCase().includes(query) ||
            i.position?.toLowerCase().includes(query)
        );
    }
    
    if (filterStatus.value) {
        filtered = filtered.filter(i => i.status === filterStatus.value);
    }
    
    return filtered;
});

const evaluationPreviewScore = computed(() => {
    const values = evaluationCriteria.map(c => Number(evaluationForm.value[c.key]));
    if (values.some(v => !Number.isInteger(v) || v < 1 || v > 5)) {
        return null;
    }
    const score = values.reduce((a, b) => a + b, 0) / values.length;
    return (score % 1 === 0) ? score.toFixed(1) : score.toFixed(2);
});

// ============================================
// METHODS
// ============================================
const getInitials = (name) => {
    if (!name) return '?';
    return name.split(' ')
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase()
        .substring(0, 2);
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

const formatDateDisplay = (date) => {
    if (!date) return 'N/A';
    return new Date(date + 'T00:00:00').toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

const formatTime = (time) => {
    if (!time) return 'N/A';
    const [hours, minutes] = time.split(':');
    const h = parseInt(hours);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 || 12;
    return `${h12}:${minutes} ${ampm}`;
};

const formatTimeDisplay = (time) => {
    if (!time) return 'N/A';
    const [hours, minutes] = time.split(':');
    const h = parseInt(hours);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 || 12;
    return `${h12}:${minutes} ${ampm}`;
};

const getInterviewStatusBadge = (status) => {
    const classes = {
        'Scheduled': 'bg-amber-100 text-amber-700 border-amber-200',
        'Completed': 'bg-emerald-100 text-emerald-700 border-emerald-200',
        'Cancelled': 'bg-rose-100 text-rose-700 border-rose-200',
        'No Show': 'bg-red-100 text-red-700 border-red-200',
            };
    return classes[status] || 'bg-slate-100 text-slate-600 border-slate-200';
};

const getInterviewResultBadge = (result) => {
    const classes = {
        'Passed': 'bg-emerald-100 text-emerald-700 border-emerald-200',
        'Failed': 'bg-rose-100 text-rose-700 border-rose-200',
        'Pending': 'bg-amber-100 text-amber-700 border-amber-200',
    };
    return classes[result] || 'bg-slate-100 text-slate-600 border-slate-200';
};

// Normalized interview-type label/badge (handles both "Initial"/"Final"
// and legacy "Initial Interview"/"Final Interview" values).
const getInterviewTypeLabel = (interview) => {
    const t = String(interview?.type ?? '').toLowerCase();
    if (t.includes('final')) return 'Final Interview';
    if (t.includes('initial')) return 'Initial Interview';
    return interview?.type || 'N/A';
};

const getInterviewTypeBadge = (interview) => {
    const t = String(interview?.type ?? '').toLowerCase();
    if (t.includes('final')) return 'bg-indigo-100 text-indigo-700 border-indigo-200';
    if (t.includes('initial')) return 'bg-blue-100 text-blue-700 border-blue-200';
    return 'bg-slate-100 text-slate-600 border-slate-200';
};

const getInterviewerName = (id) => {
    const interviewer = interviewers.value.find(i => i.id === id);
    return interviewer?.name || 'Not assigned';
};

const ratingLabelFor = (value) => {
    const match = ratingScale.find(r => r.value === Number(value));
    return match ? match.label : '';
};

const isStructuredEvaluation = (value) => {
    return !!value && typeof value === 'object' && value.overall_score !== undefined;
};

const getEvaluationRecommendationLabel = (recommendation) => {
    const labels = {
        proceed_to_final_interview: 'Proceed to Final Interview',
        recommend_onboarding_training: 'Recommend for Onboarding/Training',
        do_not_proceed: 'Do Not Proceed',
        proceed_to_onboarding_training: 'Proceed to Onboarding/Training',
        proceed_to_onboarding: 'Proceed to Onboarding',
        reject: 'Reject',
        further_assessment_required: 'Further Assessment Required',
    };
    return labels[recommendation] || 'N/A';
};

const getEvaluationRecommendationBadge = (recommendation) => {
    const classes = {
        proceed_to_final_interview: 'bg-emerald-100 text-emerald-700 border-emerald-200',
        recommend_onboarding_training: 'bg-blue-100 text-blue-700 border-blue-200',
        do_not_proceed: 'bg-rose-100 text-rose-700 border-rose-200',
        proceed_to_onboarding_training: 'bg-teal-100 text-teal-700 border-teal-200',
        proceed_to_onboarding: 'bg-teal-100 text-teal-700 border-teal-200',
        reject: 'bg-rose-100 text-rose-700 border-rose-200',
        further_assessment_required: 'bg-amber-100 text-amber-700 border-amber-200',
    };
    return classes[recommendation] || 'bg-slate-100 text-slate-600 border-slate-200';
};

const showToastMessage = (title, message, type = 'success') => {
    toastTitle.value = title;
    toastMessage.value = message;
    toastType.value = type;
    showToast.value = true;
    setTimeout(() => {
        showToast.value = false;
    }, 5000);
};

const validateScheduleForm = () => {
    const errors = [];
    
    if (!scheduleForm.value.application_id) {
        errors.push('Please select a candidate.');
    }
    if (!scheduleForm.value.date) {
        errors.push('Please select a date.');
    }
    // Check if date is in the past
    if (scheduleForm.value.date) {
        const selectedDate = new Date(scheduleForm.value.date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (selectedDate < today) {
            errors.push('Please select a future date (today or later).');
        }
    }
    if (!scheduleForm.value.start_time) {
        errors.push('Please select a start time.');
    }
    if (!scheduleForm.value.end_time) {
        errors.push('Please select an end time.');
    }
    if (!scheduleForm.value.interviewer_id) {
        errors.push('Please select an interviewer.');
    }
    if (scheduleForm.value.method === 'Online' && !scheduleForm.value.meeting_link) {
        errors.push('Please provide a meeting link for the online interview.');
    }
    if (scheduleForm.value.method === 'Onsite' && !scheduleForm.value.location) {
        errors.push('Please provide a location for the onsite interview.');
    }
    if (scheduleForm.value.start_time && scheduleForm.value.end_time && 
        scheduleForm.value.start_time >= scheduleForm.value.end_time) {
        errors.push('End time must be after start time.');
    }
    
    validationErrors.value = errors;
    return errors.length === 0;
};

const viewApplicantProfile = async (applicationId) => {
    isLoadingProfile.value = true;
    showProfileModal.value = true;
    
    try {
        const response = await axios.get(`/hrm/recruitment/applications/${applicationId}/profile`);
        profileData.value = response.data;
    } catch (error) {
        console.error('Failed to load profile:', error);
        showToastMessage('Error', 'Failed to load applicant profile.', 'error');
    } finally {
        isLoadingProfile.value = false;
    }
};

const closeProfileModal = () => {
    showProfileModal.value = false;
    profileData.value = null;
};

const openScheduleModal = (applicant = null, type = 'Initial Interview', positionHint = '') => {
    const today = new Date().toISOString().split('T')[0];
    todayDate.value = today;
    validationErrors.value = [];
    
    if (applicant) {
        scheduleForm.value = {
            application_id: applicant.id,
            job_posting_id: applicant.job_posting?.id || applicant.job_posting_id || applicant.jobPosting?.id || null,
            candidate_name: applicant.first_name ? `${applicant.first_name} ${applicant.last_name}` : applicant.candidate_name || '',
            position: applicant.job_posting?.title || applicant.position_applied || applicant.position || positionHint || '',
            type,
            interviewer_id: null,
            date: today,
            start_time: '09:00',
            end_time: '10:00',
            method: 'Online',
            location: '',
            meeting_link: '',
            notes: '',
        };
    } else {
        scheduleForm.value = {
            application_id: null,
            job_posting_id: null,
            candidate_name: '',
            position: '',
            type,
            interviewer_id: null,
            date: today,
            start_time: '09:00',
            end_time: '10:00',
            method: 'Online',
            location: '',
            meeting_link: '',
            notes: '',
        };
    }
    
    showScheduleModal.value = true;
};

const closeScheduleModal = () => {
    showScheduleModal.value = false;
    scheduleMenuOpen.value = false;
    validationErrors.value = [];
};

const openConfirmationModal = () => {
    if (!validateScheduleForm()) {
        return;
    }
    showConfirmationModal.value = true;
};

const closeConfirmationModal = () => {
    showConfirmationModal.value = false;
    validationErrors.value = [];
};

const confirmSchedule = async () => {
    isSubmitting.value = true;

    try {
        const payload = {
            application_id: scheduleForm.value.application_id,
            job_posting_id: scheduleForm.value.job_posting_id || null,
            candidate_name: scheduleForm.value.candidate_name,
            position: scheduleForm.value.position,
            // Backend/DB accept only Initial|Final — normalize the display value.
            type: /final/i.test(scheduleForm.value.type || '') ? 'Final' : 'Initial',
            interviewer_id: scheduleForm.value.interviewer_id,
            date: scheduleForm.value.date,
            start_time: scheduleForm.value.start_time,
            end_time: scheduleForm.value.end_time,
            method: scheduleForm.value.method,
            location: scheduleForm.value.location,
            meeting_link: scheduleForm.value.meeting_link || null,
            notes: scheduleForm.value.notes || null,
            status: 'Scheduled'
        };

        const response = await axios.post('/hrm/recruitment/interviews', payload);
        
        closeConfirmationModal();
        closeScheduleModal();
        
        showToastMessage(
            'Interview Scheduled!',
            `Interview scheduled successfully for ${scheduleForm.value.candidate_name}.`,
            'success'
        );
        
        const scheduledApp = applicants.value.find(a => a.id === scheduleForm.value.application_id);
        if (scheduledApp) {
            scheduledApp.status = scheduleForm.value.type === 'Final Interview'
                ? 'Final Interview'
                : scheduleForm.value.type === 'Initial Interview'
                    ? 'Initial Interview'
                    : 'Interview Scheduled';
        }
        
        refreshList();

    } catch (error) {
        console.error('❌ Failed to schedule interview:', error);
        const errorMsg = error.response?.data?.message || 'Failed to schedule interview. Please try again.';
        showToastMessage('Error', errorMsg, 'error');
    } finally {
        isSubmitting.value = false;
    }
};

const viewInterviewDetails = (interview) => {
    selectedInterview.value = interview;
    showDetailModal.value = true;
    document.body.style.overflow = 'hidden';
};

const closeDetailModal = () => {
    showDetailModal.value = false;
    selectedInterview.value = null;
    document.body.style.overflow = '';
};

const openMeeting = (link) => {
    if (link) {
        window.open(link, '_blank');
    }
};

const openRescheduleModal = (interview) => {
    selectedInterview.value = interview;
    rescheduleForm.value = {
        interview_id: interview.id,
        candidate_name: interview.candidate_name || '',
        date: interview.date || todayDate.value,
        start_time: interview.start_time || '09:00',
        end_time: interview.end_time || '10:00',
        interviewer_id: interview.interviewer_id || interview.interviewer?.id || 2,
        method: interview.method || 'Online',
        location: interview.location || '',
        meeting_link: interview.meeting_link || '',
        reason: '',
    };
    showRescheduleModal.value = true;
    showDetailModal.value = false;
};

const closeRescheduleModal = () => { showRescheduleModal.value = false; rescheduleMenuOpen.value = false; };

const rescheduleInterview = async () => {
    // Temporary interviewer until the HR interviewer selector is enabled.
    rescheduleForm.value.interviewer_id = rescheduleForm.value.interviewer_id || 2;

    if (!rescheduleForm.value.date || !rescheduleForm.value.start_time || !rescheduleForm.value.end_time || !rescheduleForm.value.interviewer_id || !rescheduleForm.value.reason) {
        showToastMessage('Validation Error', 'Please complete the new schedule, interviewer, and reason.', 'error'); return;
    }
    if (rescheduleForm.value.start_time >= rescheduleForm.value.end_time) {
        showToastMessage('Validation Error', 'End time must be after start time.', 'error'); return;
    }
    if (rescheduleForm.value.method === 'Online' && !rescheduleForm.value.meeting_link) {
        showToastMessage('Validation Error', 'Please provide a meeting link.', 'error'); return;
    }
    if (rescheduleForm.value.method === 'Onsite' && !rescheduleForm.value.location) {
        showToastMessage('Validation Error', 'Please provide a location.', 'error'); return;
    }
    isSubmitting.value = true;
    try {
        await axios.post(`/hrm/recruitment/interviews/${rescheduleForm.value.interview_id}/reschedule`, rescheduleForm.value);
        showRescheduleModal.value = false;
        showToastMessage('Interview Rescheduled', 'The interview schedule was updated successfully.', 'success');
        await refreshList();
    } catch (error) { showToastMessage('Error', error.response?.data?.message || 'Failed to reschedule interview.', 'error'); }
    finally { isSubmitting.value = false; }
};

const openCancelModal = (interview) => {
    selectedInterview.value = interview;
    cancelForm.value = { interview_id: interview.id, reason: '' };
    showCancelModal.value = true;
    showDetailModal.value = false;
};
const closeCancelModal = () => { showCancelModal.value = false; };

const cancelInterview = async () => {
    if (!cancelForm.value.reason) { showToastMessage('Validation Error', 'Please provide a cancellation reason.', 'error'); return; }
    isSubmitting.value = true;
    try {
        await axios.post(`/hrm/recruitment/interviews/${cancelForm.value.interview_id}/cancel`, { reason: cancelForm.value.reason });
        showCancelModal.value = false;
        showToastMessage('Interview Cancelled', 'The interview was cancelled and the applicant returned to Shortlisted.', 'success');
        await refreshList();
    } catch (error) { showToastMessage('Error', error.response?.data?.message || 'Failed to cancel interview.', 'error'); }
    finally { isSubmitting.value = false; }
};

const markNoShow = async (interview) => {
    if (!confirm(`Mark ${interview.candidate_name} as No Show?`)) return;
    isSubmitting.value = true;
    try {
        await axios.post(`/hrm/recruitment/interviews/${interview.id}/no-show`);
        showDetailModal.value = false;
        showToastMessage('Marked as No Show', 'The interview was marked as No Show. You can reschedule it later.', 'success');
        await refreshList();
    } catch (error) { showToastMessage('Error', error.response?.data?.message || 'Failed to mark No Show.', 'error'); }
    finally { isSubmitting.value = false; }
};

const openEvaluationModal = (interview) => {
    selectedInterview.value = interview;
    const existing = interview.evaluation;
    const defaultRecommendation = isFinalInterview(interview) ? 'proceed_to_onboarding' : 'proceed_to_final_interview';

    if (existing && existing.overall_score !== undefined) {
        evaluationForm.value = {
            interview_id: interview.id,
            communication_skills: Number(existing.communication_skills) || 3,
            relevant_skills: Number(existing.relevant_skills) || 3,
            technical_knowledge: Number(existing.technical_knowledge) || 3,
            problem_solving: Number(existing.problem_solving) || 3,
            work_experience: Number(existing.work_experience) || 3,
            adaptability: Number(existing.adaptability) || 3,
            teamwork: Number(existing.teamwork) || 3,
            professionalism: Number(existing.professionalism) || 3,
            strengths: existing.strengths || '',
            concerns: existing.concerns || '',
            interview_notes: existing.interview_notes || '',
            result: existing.result || 'passed',
            recommendation: existing.recommendation || defaultRecommendation,
        };
    } else {
        evaluationForm.value = {
            interview_id: interview.id,
            communication_skills: null,
            relevant_skills: null,
            technical_knowledge: null,
            problem_solving: null,
            work_experience: null,
            adaptability: null,
            teamwork: null,
            professionalism: null,
            strengths: '',
            concerns: '',
            interview_notes: '',
            result: 'passed',
            recommendation: defaultRecommendation,
        };
    }

    showEvaluationModal.value = true;
    showDetailModal.value = false;
};
const closeEvaluationModal = () => { showEvaluationModal.value = false; };

const completeInterview = async () => {
    const missing = evaluationCriteria.filter(c => {
        const v = Number(evaluationForm.value[c.key]);
        return Number.isNaN(v) || v < 1 || v > 5;
    });

    if (missing.length > 0) {
        showToastMessage('Validation Error', `Please rate the following criteria: ${missing.map(c => c.label).join(', ')}.`, 'error');
        return;
    }
    if (!evaluationForm.value.result) {
        showToastMessage('Validation Error', 'Please select an interview result.', 'error');
        return;
    }
    if (!evaluationForm.value.recommendation) {
        showToastMessage('Validation Error', 'Please select an interviewer recommendation.', 'error');
        return;
    }

    isSubmitting.value = true;
    try {
        const payload = {
            communication_skills: Number(evaluationForm.value.communication_skills),
            relevant_skills: Number(evaluationForm.value.relevant_skills),
            technical_knowledge: Number(evaluationForm.value.technical_knowledge),
            problem_solving: Number(evaluationForm.value.problem_solving),
            work_experience: Number(evaluationForm.value.work_experience),
            adaptability: Number(evaluationForm.value.adaptability),
            teamwork: Number(evaluationForm.value.teamwork),
            professionalism: Number(evaluationForm.value.professionalism),
            strengths: evaluationForm.value.strengths.trim() || null,
            concerns: evaluationForm.value.concerns.trim() || null,
            interview_notes: evaluationForm.value.interview_notes.trim() || null,
            result: evaluationForm.value.result,
            recommendation: evaluationForm.value.recommendation,
        };

        const response = await axios.post(`/hrm/recruitment/interviews/${evaluationForm.value.interview_id}/complete`, payload);
        const savedScore = Number(response.data?.evaluation?.overall_score ?? response.data?.data?.evaluation?.overall_score);
        showEvaluationModal.value = false;
        const movedToOnboarding = response.data?.advanced_to_onboarding === true;
        const wasRejected = response.data?.rejected === true;
        showToastMessage(
            'Evaluation Submitted',
            `Interview evaluation saved with an overall score of ${Number.isFinite(savedScore) ? savedScore.toFixed(2) : 'N/A'} / 5. ${movedToOnboarding ? 'The applicant has been moved to Onboarding and will appear in the HRM Onboarding module.' : wasRejected ? 'The applicant has been marked as Rejected.' : "The applicant's status was not changed."}`,
            'success'
        );
        await refreshList();
    } catch (error) {
        showToastMessage('Error', error.response?.data?.message || 'Failed to submit interview evaluation.', 'error');
    } finally {
        isSubmitting.value = false;
    }
};

const scheduleFinalInterview = (interview) => {
    // HrmInterview relation is `applicant` (there is no `application` relation).
    const applicant = interview.applicant || interview.application;
    if (applicant && applicant.id) {
        openScheduleModal(applicant, 'Final Interview', interview.position || '');
        return;
    }
    // Fallback: build a minimal applicant from the interview row itself.
    if (!interview.applicant_id) {
        showToastMessage('Error', 'No application found for this interview.', 'error');
        return;
    }
    const parts = String(interview.candidate_name || '').split(' ').filter(Boolean);
    openScheduleModal({
        id: interview.applicant_id,
        first_name: parts[0] || interview.candidate_name || '',
        last_name: parts.slice(1).join(' ') || '',
        candidate_name: interview.candidate_name || '',
        position_applied: interview.position || '',
    }, 'Final Interview', interview.position || '');
};

const advanceToOnboarding = async (interview) => {
    if (!confirm(`Advance ${interview.candidate_name} to Onboarding / Training? The applicant will be notified through the portal.`)) return;
    isSubmitting.value = true;
    try {
        const response = await axios.post(`/hrm/recruitment/interviews/${interview.id}/advance-to-onboarding`);
        showToastMessage('Advanced to Onboarding', response.data?.message || 'The applicant has been advanced to Onboarding.', 'success');
        await refreshList();
    } catch (error) {
        showToastMessage('Error', error.response?.data?.message || 'Failed to advance applicant to onboarding.', 'error');
    } finally {
        isSubmitting.value = false;
    }
};

const openSelectModal = (interview) => { selectedInterview.value = interview; showSelectModal.value = true; showDetailModal.value = false; };
const closeSelectModal = () => { showSelectModal.value = false; };
const selectCandidate = async () => {
    isSubmitting.value = true;
    try {
        await axios.post(`/hrm/recruitment/interviews/${selectedInterview.value.id}/select`);
        showSelectModal.value = false;
        showToastMessage('Candidate Selected', `${selectedInterview.value.candidate_name} has been moved to Selected.`, 'success');
        await refreshList();
    } catch (error) { showToastMessage('Error', error.response?.data?.message || 'Failed to select candidate.', 'error'); }
    finally { isSubmitting.value = false; }
};

const fetchInterviewers = async () => {
    try {
        const response = await axios.get('/hrm/recruitment/interviewers');
        const rows = Array.isArray(response.data) ? response.data : [];
        // Safety net: collapse same-name entries so one person appears once.
        const seen = new Set();
        interviewers.value = rows.filter((u) => {
            const key = String(u?.name || '').trim().toLowerCase();
            if (!key || seen.has(key)) return false;
            seen.add(key);
            return true;
        });
    } catch (error) {
        console.error('Failed to fetch interviewers:', error);
    }
};

const fetchInterviews = async () => {
    try {
        const response = await axios.get('/hrm/recruitment/interviews/list');
        interviews.value = normalizeRows(response.data);
    } catch (error) {
        console.error('Failed to fetch interviews:', error);
    }
};

const refreshList = async () => {
    await Promise.all([fetchInterviews(), axios.get('/hrm/recruitment/interviews').then(response => {
        const data = response.data;
        const list = Array.isArray(data) ? data : (data?.applicants ?? data?.data ?? []);
        // That endpoint returns HrmInterview rows (candidate_name), not Applicants —
        // only assign when the rows actually look like applicants.
        if (Array.isArray(list) && list.length && list[0] && typeof list[0] === 'object' && 'first_name' in list[0]) applicants.value = list;
    }).catch(() => {})]);
};

// ============================================
// LIFECYCLE
// ============================================
onMounted(() => {
    // Set today's date for min attribute
    todayDate.value = new Date().toISOString().split('T')[0];
    
    if (props.applicants && props.applicants.length > 0) {
        applicants.value = props.applicants;
    }
    fetchInterviewers();
    fetchInterviews();
});
</script>

<style scoped>
.bg-clip-text {
    -webkit-background-clip: text;
    background-clip: text;
}
.backdrop-blur-sm {
    backdrop-filter: blur(8px);
}

.overflow-x-auto::-webkit-scrollbar {
    height: 6px;
}
.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}
.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}
.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}
.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}
.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}
.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

select {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}

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

.animate-slideIn {
    animation: slideIn 0.3s ease-out;
}
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.animate-spin {
    animation: spin 1s linear infinite;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>