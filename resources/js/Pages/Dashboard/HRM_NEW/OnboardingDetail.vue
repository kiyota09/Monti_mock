<template>
    <AuthenticatedLayout>
        <!-- START SCREEN LOADING OVERLAY -->
        <Transition name="fade">
            <div
                v-if="pageLoading"
                class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-100/50 backdrop-blur-md"
                aria-live="polite"
            >
                <div class="flex flex-col items-center gap-3">
                    <div class="w-10 h-10 rounded-full border-4 border-slate-300 border-t-blue-600 animate-spin"></div>
                    <p class="text-sm font-semibold text-slate-600">Loading onboarding...</p>
                </div>
            </div>
        </Transition>

        <div class="flex min-h-screen bg-gradient-to-br from-slate-100 via-slate-50 to-blue-50">
            <div class="flex-1 p-6 lg:p-8 text-slate-900 font-sans min-w-0 relative">
                <div class="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,transparent,black,transparent)] pointer-events-none"></div>

                <!-- HEADER -->
                <header class="mb-6 relative">
                    <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3">
                        <Link :href="route('hrm.onboarding.status.index')" class="hover:text-slate-700 cursor-pointer transition-colors inline-flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Onboarding
                        </Link>
                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span class="text-slate-800 font-semibold">{{ onboarding.name }}</span>
                    </nav>

                    <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 rounded-full bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center text-white font-bold text-lg shadow-lg">
                                {{ initials }}
                            </div>
                            <div>
                                <h1 class="text-2xl lg:text-3xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">{{ onboarding.name }}</h1>
                                <div class="flex flex-wrap items-center gap-2 mt-1">
                                    <span class="text-xs text-slate-500">{{ onboarding.position }}</span>
                                    <span class="text-[10px] text-slate-300">•</span>
                                    <span class="text-xs text-slate-500">{{ onboarding.department }}</span>
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-semibold border shadow-sm" :class="onboarding.status_badge">
                                        {{ onboarding.status }}
                                    </span>
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-semibold border bg-slate-50 text-slate-600 border-slate-200">
                                        Application: {{ onboarding.application_status }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                @click="openModal('details')"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-semibold transition-all duration-200 shadow-sm hover:shadow-md"
                            >
                                <Pencil class="w-3.5 h-3.5" /> Edit Details
                            </button>
                            <button
                                v-if="!alreadyHired"
                                @click="confirmComplete"
                                :disabled="!canComplete"
                                :title="!canComplete ? 'Complete all required items and mandatory activities first.' : ''"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold transition-all duration-200 shadow-lg"
                                :class="canComplete
                                    ? 'bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-500 hover:to-emerald-600 text-white shadow-emerald-500/25'
                                    : 'bg-slate-200 text-slate-500 cursor-not-allowed'
                                "
                            >
                                <CheckCircle2 class="w-3.5 h-3.5" /> Complete Onboarding
                            </button>
                            <button
                                v-if="!alreadyHired"
                                @click="confirmCreateEmployee"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-xs font-bold transition-all duration-200 shadow-lg hover:shadow-xl"
                            >
                                <UserPlus class="w-3.5 h-3.5" /> Create Employee
                            </button>
                            <div
                                v-else
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl text-xs font-bold"
                            >
                                <CheckCircle2 class="w-3.5 h-3.5" /> Employee Record Created
                            </div>
                        </div>
                    </div>

                    <!-- Missing items warning -->
                    <div v-if="missing.length > 0" class="mt-4 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
                        <p class="text-xs font-bold text-amber-800 mb-1">Not yet ready to complete onboarding:</p>
                        <ul class="list-disc list-inside text-[11px] text-amber-700 space-y-0.5">
                            <li v-for="m in missing" :key="m">{{ m }}</li>
                        </ul>
                    </div>
                </header>

                <!-- PROGRESS -->
                <div class="bg-white rounded-xl border border-slate-200/60 shadow-md backdrop-blur-sm p-4 mb-5 relative">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-slate-600">Overall Onboarding Progress</span>
                        <span class="text-sm font-bold text-blue-600">{{ progress }}%</span>
                    </div>
                    <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden shadow-inner">
                        <div class="h-full bg-gradient-to-r from-blue-500 to-emerald-500 rounded-full transition-all duration-700" :style="{ width: progress + '%' }"></div>
                    </div>
                </div>

                <!-- FINAL REVIEW -->
                <div class="bg-white rounded-xl border border-slate-200/60 shadow-md backdrop-blur-sm overflow-hidden mb-5 relative">
                    <div class="px-4 py-3 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-transparent flex items-center gap-2">
                        <ClipboardCheck class="w-4 h-4 text-blue-600" />
                        <h2 class="text-sm font-bold text-slate-800">Final Review</h2>
                        <span class="ml-auto text-[10px] font-semibold text-slate-400">{{ displayTemplate || 'Template not set' }}</span>
                    </div>

                    <!-- Ready banner -->
                    <div
                        v-if="readiness.completed"
                        class="mx-4 mt-4 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3 flex items-center gap-3"
                    >
                        <CheckCircle2 class="w-5 h-5 text-emerald-600 shrink-0" />
                        <div>
                            <p class="text-xs font-bold text-emerald-800">Onboarding Completed</p>
                            <p class="text-[11px] text-emerald-700">The employee record can now be created. All required items and mandatory activities are done.</p>
                        </div>
                    </div>
                    <div
                        v-else-if="readiness.ready"
                        class="mx-4 mt-4 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3 flex items-center gap-3"
                    >
                        <CheckCircle2 class="w-5 h-5 text-emerald-600 shrink-0" />
                        <div>
                            <p class="text-xs font-bold text-emerald-800">Ready for Completion</p>
                            <p class="text-[11px] text-emerald-700">All required items and mandatory activities are done. You can complete the onboarding now.</p>
                        </div>
                    </div>
                    <div
                        v-else
                        class="mx-4 mt-4 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 flex items-center gap-3"
                    >
                        <X class="w-5 h-5 text-amber-600 shrink-0" />
                        <div>
                            <p class="text-xs font-bold text-amber-800">Not Yet Ready for Completion</p>
                            <p class="text-[11px] text-amber-700">Complete every required item and mandatory activity to unlock the onboarding completion.</p>
                        </div>
                    </div>

                    <!-- Readiness counts -->
                    <div class="p-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
                        <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3">
                            <div class="flex items-center justify-between">
                                <FileText class="w-3.5 h-3.5 text-slate-400" />
                                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Required Items</span>
                            </div>
                            <p class="text-xl font-bold text-slate-800 mt-1.5">{{ readiness.required_items_done }}<span class="text-xs text-slate-400 font-semibold"> / {{ readiness.required_items_total }}</span></p>
                            <p class="text-[10px] text-slate-400 mt-0.5">verified or completed</p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3">
                            <div class="flex items-center justify-between">
                                <CalendarDays class="w-3.5 h-3.5 text-slate-400" />
                                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Mandatory Activities</span>
                            </div>
                            <p class="text-xl font-bold text-slate-800 mt-1.5">{{ readiness.required_activities_done }}<span class="text-xs text-slate-400 font-semibold"> / {{ readiness.required_activities_total }}</span></p>
                            <p class="text-[10px] text-slate-400 mt-0.5">completed</p>
                        </div>
                        <div class="rounded-xl border border-rose-100 bg-rose-50/40 p-3">
                            <div class="flex items-center justify-between">
                                <X class="w-3.5 h-3.5 text-rose-400" />
                                <span class="text-[9px] font-bold text-rose-500 uppercase tracking-wider">Rejected Required Items</span>
                            </div>
                            <p class="text-xl font-bold text-slate-800 mt-1.5">{{ readiness.rejected_items }}</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">awaiting resubmission</p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3">
                            <div class="flex items-center justify-between">
                                <StickyNote class="w-3.5 h-3.5 text-slate-400" />
                                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Blocked Items</span>
                            </div>
                            <p class="text-xl font-bold text-slate-800 mt-1.5">{{ readiness.blocked_items }}</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">need unblocking</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 relative">
                    <!-- LEFT COLUMN -->
                    <div class="space-y-5">
                        <!-- PERSONAL INFO -->
                        <div class="bg-white rounded-xl border border-slate-200/60 shadow-md backdrop-blur-sm overflow-hidden">
                            <div class="px-4 py-3 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-transparent flex items-center gap-2">
                                <User class="w-4 h-4 text-blue-600" />
                                <h3 class="text-sm font-bold text-slate-800">Personal Information</h3>
                            </div>
                            <div class="p-4 space-y-3">
                                <div v-if="profile?.photo" class="flex justify-center mb-2">
                                    <img :src="profile.photo" alt="Profile" class="w-20 h-20 rounded-full object-cover border-4 border-white shadow-lg" />
                                </div>
                                <div class="flex justify-between border-b border-slate-50 pb-2">
                                    <span class="text-[11px] text-slate-400">Full Name</span>
                                    <span class="text-xs font-medium text-slate-700">{{ profile?.full_name || onboarding.name }}</span>
                                </div>
                                <div class="flex justify-between border-b border-slate-50 pb-2">
                                    <span class="text-[11px] text-slate-400">Email</span>
                                    <span class="text-xs font-medium text-slate-700">{{ profile?.email || onboarding.email }}</span>
                                </div>
                                <div class="flex justify-between border-b border-slate-50 pb-2">
                                    <span class="text-[11px] text-slate-400">Phone</span>
                                    <span class="text-xs font-medium text-slate-700">{{ profile?.phone || onboarding.phone }}</span>
                                </div>
                                <div v-if="profile?.birth_date" class="flex justify-between border-b border-slate-50 pb-2">
                                    <span class="text-[11px] text-slate-400">Birth Date</span>
                                    <span class="text-xs font-medium text-slate-700">{{ formatDate(profile.birth_date) }}</span>
                                </div>
                                <div v-if="profile?.gender" class="flex justify-between border-b border-slate-50 pb-2">
                                    <span class="text-[11px] text-slate-400">Gender</span>
                                    <span class="text-xs font-medium text-slate-700">{{ profile.gender }}</span>
                                </div>
                                <div v-if="profile?.civil_status" class="flex justify-between border-b border-slate-50 pb-2">
                                    <span class="text-[11px] text-slate-400">Civil Status</span>
                                    <span class="text-xs font-medium text-slate-700">{{ profile.civil_status }}</span>
                                </div>
                                <div v-if="profile?.address" class="flex justify-between gap-3 border-b border-slate-50 pb-2">
                                    <span class="text-[11px] text-slate-400 shrink-0">Address</span>
                                    <span class="text-xs font-medium text-slate-700 text-right">{{ profile.address }} {{ profile.zip_code }}</span>
                                </div>
                                <div v-if="profile?.education" class="flex justify-between gap-3 border-b border-slate-50 pb-2">
                                    <span class="text-[11px] text-slate-400 shrink-0">Education</span>
                                    <span class="text-xs font-medium text-slate-700 text-right">{{ profile.education }}</span>
                                </div>
                                <div v-if="profile?.emergency_contact" class="flex justify-between gap-3 border-b border-slate-50 pb-2">
                                    <span class="text-[11px] text-slate-400 shrink-0">Emergency</span>
                                    <span class="text-xs font-medium text-slate-700 text-right">
                                        {{ profile.emergency_contact.name }}
                                        <span class="block text-[10px] text-slate-400">{{ profile.emergency_contact.relationship }} · {{ profile.emergency_contact.phone }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- EMPLOYMENT DETAILS -->
                        <div class="bg-white rounded-xl border border-slate-200/60 shadow-md backdrop-blur-sm overflow-hidden">
                            <div class="px-4 py-3 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-transparent flex items-center gap-2">
                                <Briefcase class="w-4 h-4 text-blue-600" />
                                <h3 class="text-sm font-bold text-slate-800">Employment Details</h3>
                            </div>
                            <div class="p-4 space-y-3">
                                <div class="flex justify-between border-b border-slate-50 pb-2">
                                    <span class="text-[11px] text-slate-400">Position</span>
                                    <span class="text-xs font-medium text-slate-700">{{ onboarding.position }}</span>
                                </div>
                                <div class="flex justify-between border-b border-slate-50 pb-2">
                                    <span class="text-[11px] text-slate-400">Department</span>
                                    <span class="text-xs font-medium text-slate-700">{{ onboarding.department }}</span>
                                </div>
                                <div class="flex justify-between border-b border-slate-50 pb-2">
                                    <span class="text-[11px] text-slate-400">Employment Type</span>
                                    <span class="text-xs font-medium text-slate-700">{{ onboarding.employment_type || '—' }}</span>
                                </div>
                                <div class="flex justify-between border-b border-slate-50 pb-2">
                                    <span class="text-[11px] text-slate-400">Work Mode</span>
                                    <span class="text-xs font-medium text-slate-700">{{ onboarding.work_mode || '—' }}</span>
                                </div>
                                <div class="flex justify-between border-b border-slate-50 pb-2">
                                    <span class="text-[11px] text-slate-400">Onboarding Template</span>
                                    <span class="text-xs font-medium text-slate-700">{{ displayTemplate || '—' }}</span>
                                </div>
                                <div v-if="onboarding.salary" class="flex justify-between border-b border-slate-50 pb-2">
                                    <span class="text-[11px] text-slate-400">Salary Range</span>
                                    <span class="text-xs font-medium text-slate-700">{{ onboarding.salary }}</span>
                                </div>
                                <div class="flex justify-between border-b border-slate-50 pb-2">
                                    <span class="text-[11px] text-slate-400">Start Date</span>
                                    <span class="text-xs font-medium text-slate-700">{{ formatDate(onboarding.start_date) }}</span>
                                </div>
                                <div class="flex justify-between border-b border-slate-50 pb-2">
                                    <span class="text-[11px] text-slate-400">Expected Start</span>
                                    <span class="text-xs font-medium text-slate-700">{{ formatDate(onboarding.expected_start_date) }}</span>
                                </div>
                                <div class="flex justify-between border-b border-slate-50 pb-2">
                                    <span class="text-[11px] text-slate-400">Expected Completion</span>
                                    <span class="text-xs font-medium text-slate-700">{{ formatDate(onboarding.expected_completion_date) }}</span>
                                </div>
                                <div class="flex justify-between border-b border-slate-50 pb-2">
                                    <span class="text-[11px] text-slate-400">Work Location</span>
                                    <span class="text-xs font-medium text-slate-700">{{ onboarding.work_location || '—' }}</span>
                                </div>
                                <div class="flex justify-between border-b border-slate-50 pb-2">
                                    <span class="text-[11px] text-slate-400">Work Schedule</span>
                                    <span class="text-xs font-medium text-slate-700">{{ onboarding.work_schedule || '—' }}</span>
                                </div>
                                <div class="flex justify-between pb-2">
                                    <span class="text-[11px] text-slate-400">Assigned Manager</span>
                                    <span class="text-xs font-medium text-slate-700">{{ onboarding.assigned_manager || 'Unassigned' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT 2 COLUMNS -->
                    <div class="xl:col-span-2 space-y-5">
                        <!-- UNIFIED ONBOARDING ITEMS -->
                        <div class="bg-white rounded-xl border border-slate-200/60 shadow-md backdrop-blur-sm overflow-hidden">
                            <div class="px-4 py-3 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-transparent flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <ClipboardCheck class="w-4 h-4 text-blue-600" />
                                    <h3 class="text-sm font-bold text-slate-800">Onboarding Items</h3>
                                </div>
                                <span class="text-[10px] text-slate-500 bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-200/60">Eligible items for this template snapshot</span>
                            </div>
                            <div class="p-4 space-y-6" v-if="hasSnapshot">
                                <div v-for="(items, section) in sections" :key="section">
                                    <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 flex items-center gap-2">
                                        <span class="w-1 h-3.5 rounded-full" :class="sectionColor(section)"></span>{{ section }}
                                        <span class="text-[9px] font-semibold text-slate-400 bg-slate-50 px-1.5 py-0.5 rounded border border-slate-100">{{ items.length }}</span>
                                    </h4>
                                    <div class="space-y-2">
                                        <div
                                            v-for="item in items"
                                            :key="item.id"
                                            class="p-3 rounded-xl border border-slate-100 hover:border-blue-100 hover:bg-blue-50/20 transition-all"
                                        >
                                            <div class="flex flex-col md:flex-row md:items-start gap-3">
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-xs font-medium text-slate-700 flex flex-wrap items-center gap-2">
                                                        {{ item.name }}
                                                        <span :class="item.is_required ? 'bg-blue-50 text-blue-600 border-blue-200' : 'bg-slate-50 text-slate-400 border-slate-200'" class="px-1.5 py-0.5 rounded text-[9px] font-bold border">
                                                            {{ item.is_required ? 'REQUIRED' : 'OPTIONAL' }}
                                                        </span>
                                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold border bg-indigo-50 text-indigo-600 border-indigo-200">{{ item.input_type }}</span>
                                                        <span v-if="!item.applicant_visible" class="px-1.5 py-0.5 rounded text-[9px] font-bold border bg-slate-50 text-slate-500 border-slate-200">INTERNAL</span>
                                                        <span :class="['px-2 py-0.5 rounded-full text-[9px] font-bold border', item.status_badge]">
                                                            {{ item.status }}
                                                        </span>
                                                    </div>
                                                    <p v-if="item.description && section !== 'HR Processing'" class="text-[10px] text-slate-400 mt-0.5">{{ item.description }}</p>
                                                    <!-- HR task: parent name on top, contents as an organized list below -->
                                                    <div v-else-if="item.description" class="mt-1.5 rounded-lg bg-slate-50/80 border border-slate-100 px-3 py-2">
                                                        <p v-if="descHead(item.description)" class="text-[10px] text-slate-400">{{ descHead(item.description) }}</p>
                                                        <ul v-if="descBullets(item.description).length" class="mt-1 space-y-1">
                                                            <li v-for="(line, li) in descBullets(item.description)" :key="li" class="flex items-start gap-1.5 text-[10px] text-slate-500">
                                                                <span class="mt-1 w-1 h-1 rounded-full bg-violet-400 shrink-0"></span>
                                                                <span>{{ line }}</span>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                    <div class="flex flex-wrap items-center gap-2 mt-1.5 text-[9px] text-slate-400">
                                                        <span v-if="section !== 'HR Processing' && section !== 'Company Preparation'">Owner: {{ item.responsible_party }}</span>
                                                        <span v-if="item.due_date">· Due: {{ formatDate(item.due_date) }}</span>
                                                        <span v-if="item.submitted_at">· Submitted: {{ formatDateTime(item.submitted_at) }}</span>
                                                    </div>

                                                    <!-- Submission details for applicant items -->
                                                    <div v-if="!item.is_task && item.latest_submission" class="mt-2 flex flex-wrap items-center gap-2">
                                                        <a
                                                            v-if="item.latest_submission.file_url"
                                                            :href="item.latest_submission.file_url"
                                                            target="_blank"
                                                            class="inline-flex items-center gap-1 text-[10px] font-semibold text-blue-600 hover:text-blue-800 bg-blue-50 px-2 py-1 rounded-lg border border-blue-100"
                                                        >
                                                            <Download class="w-3 h-3" /> {{ item.latest_submission.original_name }}
                                                        </a>
                                                        <span v-if="item.latest_submission.value" class="text-[10px] text-slate-600 bg-slate-50 px-2 py-1 rounded-lg border border-slate-100 max-w-xs truncate">
                                                            {{ displayValue(item.latest_submission.value) }}
                                                        </span>
                                                        <span v-if="item.latest_submission.submitted_at" class="text-[9px] text-slate-400">Submitted {{ formatDateTime(item.latest_submission.submitted_at) }}</span>
                                                    </div>

                                                    <div v-if="item.status === 'Rejected'" class="mt-1.5 text-[10px] font-semibold text-rose-600 bg-rose-50 px-2.5 py-1.5 rounded-lg border border-rose-100">
                                                        Rejected: {{ item.rejection_reason || 'No reason provided.' }} — applicant may resubmit.
                                                    </div>

                                                    <!-- Submission history -->
                                                    <div v-if="item.submissions && item.submissions.length > 1" class="mt-2">
                                                        <button class="text-[9px] font-bold text-slate-400 hover:text-slate-600" @click="toggleHistory(item.id)">
                                                        History ({{ item.submissions.length }})
                                                    </button>
                                                        <div v-if="expandedHistory[item.id]" class="mt-1.5 space-y-1">
                                                            <div v-for="(sub, i) in item.submissions.slice(1)" :key="sub.id" class="flex items-center gap-2 text-[9px] text-slate-500">
                                                                <span class="px-1.5 py-0.5 rounded bg-slate-50 border border-slate-100">{{ sub.status }}</span>
                                                                <span v-if="sub.original_name" class="truncate max-w-[180px]">{{ sub.original_name }}</span>
                                                                <span v-if="sub.value" class="truncate max-w-[180px]">{{ displayValue(sub.value) }}</span>
                                                                <span>{{ formatDateTime(sub.submitted_at) }}</span>
                                                                <span v-if="sub.status === 'Rejected'" class="text-rose-500">{{ sub.rejection_reason }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Actions -->
                                                <div class="flex items-center gap-2 shrink-0">
                                                    <button
                                                        @click="openItemModal(item, section)"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 rounded-lg text-[10px] font-bold transition-all duration-200"
                                                    >
                                                        Details
                                                    </button>
                                                    <!-- Task / Verification: HR sets status directly -->
                                                    <div v-if="item.is_task">
                                                        <select
                                                            :value="item.status"
                                                            @change="updateTaskStatus(item, $event.target.value)"
                                                            :class="['px-2.5 py-1.5 rounded-lg border text-[10px] font-semibold focus:ring-2 focus:ring-blue-500', itemStatusSelect(item.status)]"
                                                        >
                                                            <option v-for="s in taskStatuses" :key="s" :value="s">{{ s }}</option>
                                                        </select>
                                                    </div>

                                                    <!-- Applicant item: review workflow -->
                                                    <template v-else>
                                                        <button
                                                            v-if="canReview(item)"
                                                            @click="reviewItem(item, 'approve')"
                                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-500 hover:to-emerald-600 text-white rounded-lg text-[10px] font-bold transition-all duration-200 shadow-sm"
                                                        >
                                                            <Check class="w-3 h-3" /> Approve
                                                        </button>
                                                        <button
                                                            v-if="canReview(item)"
                                                            @click="openRejectModal(item)"
                                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-white border border-rose-200 text-rose-600 hover:bg-rose-50 rounded-lg text-[10px] font-bold transition-all duration-200"
                                                        >
                                                            <X class="w-3 h-3" /> Reject
                                                        </button>
                                                        <span
                                                            v-if="item.status === 'Verified'"
                                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-emerald-50 text-emerald-600 border border-emerald-200 rounded-lg text-[10px] font-bold"
                                                        >
                                                            <Check class="w-3 h-3" /> Verified
                                                        </span>
                                                        <span
                                                            v-if="!item.latest_submission && section !== 'HR Processing' && section !== 'Company Preparation'"
                                                            class="inline-flex items-center px-2.5 py-1.5 text-[10px] text-amber-600 bg-amber-50 border border-amber-100 rounded-lg"
                                                        >
                                                            Awaiting submission
                                                        </span>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="p-8 text-center">
                                <p class="text-xs text-slate-400">No onboarding items yet. Edit Details to choose a template (or contact HR).</p>
                            </div>
                        </div>

                        <!-- HR NOTES -->
                        <div class="bg-white rounded-xl border border-slate-200/60 shadow-md backdrop-blur-sm overflow-hidden">
                            <div class="px-4 py-3 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-transparent flex items-center gap-2">
                                <StickyNote class="w-4 h-4 text-blue-600" />
                                <h3 class="text-sm font-bold text-slate-800">HR Notes <span class="text-[9px] font-medium text-slate-400">(private — not visible to the applicant)</span></h3>
                            </div>
                            <div class="p-4 space-y-3">
                                <form @submit.prevent="storeNote" class="space-y-2">
                                    <textarea
                                        v-model="noteForm.content"
                                        rows="3"
                                        placeholder="Add an internal note about this new hire..."
                                        class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200"
                                    ></textarea>
                                    <div class="flex items-center justify-between">
                                        <label class="flex items-center gap-2 text-[10px] text-slate-500 cursor-pointer">
                                            <input v-model="noteForm.is_hr_private" type="checkbox" class="w-3.5 h-3.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                            Private note (HR only)
                                        </label>
                                        <button
                                            type="submit"
                                            class="inline-flex items-center gap-1 px-3.5 py-2 bg-gradient-to-r from-slate-800 to-slate-900 hover:from-slate-700 hover:to-slate-800 text-white rounded-xl text-[10px] font-bold transition-all duration-200 shadow-md"
                                        >
                                            <Plus class="w-3 h-3" /> Add Note
                                        </button>
                                    </div>
                                </form>

                                <div v-if="notes.length === 0" class="text-center py-4">
                                    <p class="text-xs text-slate-400">No notes yet.</p>
                                </div>
                                <div v-else class="space-y-2 max-h-72 overflow-y-auto pr-1">
                                    <div v-for="note in notes" :key="note.id" class="flex gap-3 p-3 rounded-xl bg-slate-50/70 border border-slate-100">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-slate-600 to-slate-700 flex items-center justify-center text-white font-bold text-[10px] shrink-0">
                                            {{ note.user ? note.user.email.charAt(0).toUpperCase() : 'HR' }}
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-[11px] text-slate-600 leading-relaxed">{{ note.content }}</p>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="text-[9px] text-slate-400">{{ note.user?.email || 'HR' }} · {{ formatDateTime(note.created_at) }}</span>
                                                <span v-if="note.is_hr_private" class="text-[9px] font-bold text-violet-500 bg-violet-50 px-1.5 py-0.5 rounded">PRIVATE</span>
                                            </div>
                                        </div>
                                        <button @click="deleteNote(note)" class="p-1 text-slate-300 hover:text-rose-500 transition-colors">
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ MODALS ============ -->
        <div v-if="activeModal" class="fixed inset-0 z-[80] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeModal">
            <div :class="['bg-white rounded-2xl border border-slate-200/60 w-full overflow-hidden shadow-2xl', activeModal === 'create-employee' ? 'max-w-3xl max-h-[92vh] flex flex-col' : 'max-w-2xl']">
                <!-- Details Modal -->
                <template v-if="activeModal === 'details'">
                    <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-transparent">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Edit Onboarding Details</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Update start date, manager, template (before items exist), location and schedule.</p>
                        </div>
                        <button @click="closeModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                    <div class="p-5 space-y-4">
                        <div v-if="!hasSnapshot" class="bg-blue-50/60 border border-blue-100 rounded-xl p-3">
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Onboarding Template</label>
                            <select v-model="detailsForm.template_id" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm">
                                <option value="">-- Select template --</option>
                                <option v-for="t in templates" :key="t.id" :value="t.id">
                                    {{ t.name }} ({{ t.code }} v{{ t.version }})
                                </option>
                            </select>
                            <p class="text-[10px] text-slate-500 mt-1">Selecting a template snapshots its items. Not shown once items already exist.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Start Date</label>
                                <input v-model="detailsForm.start_date" type="date" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Expected Start Date</label>
                                <input v-model="detailsForm.expected_start_date" type="date" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Expected Completion Date</label>
                                <input v-model="detailsForm.expected_completion_date" type="date" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Assigned Manager</label>
                                <input v-model="detailsForm.assigned_manager" type="text" placeholder="e.g. HR Operations Manager" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Work Location</label>
                                <input v-model="detailsForm.work_location" type="text" placeholder="e.g. Main Plant" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Work Schedule</label>
                                <input v-model="detailsForm.work_schedule" type="text" placeholder="e.g. Monday - Friday, 8:00 AM - 5:00 PM" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm" />
                            </div>
                        </div>
                    </div>
                    <div class="p-4 border-t border-slate-100 bg-gradient-to-r from-slate-50 to-transparent flex items-center justify-end gap-3">
                        <button @click="closeModal" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-all">Cancel</button>
                        <button @click="saveDetails" :disabled="saving" class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-sm font-bold transition-all shadow-lg">
                            {{ saving ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                </template>

                <!-- Reject Modal -->
                <template v-if="activeModal === 'reject'">
                    <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-rose-50 to-transparent">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Reject Submission</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Let the applicant know why this submission was rejected.</p>
                        </div>
                        <button @click="closeModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                    <div class="p-5">
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Rejection Reason</label>
                        <textarea
                            v-model="rejectForm.reason"
                            rows="3"
                            placeholder="e.g. The document is unclear. Please upload a clearer scan."
                            class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-rose-500 focus:border-transparent shadow-sm"
                        ></textarea>
                    </div>
                    <div class="p-4 border-t border-slate-100 bg-gradient-to-r from-slate-50 to-transparent flex items-center justify-end gap-3">
                        <button @click="closeModal" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-all">Cancel</button>
                        <button @click="submitReject" :disabled="saving" class="px-5 py-2.5 bg-gradient-to-r from-rose-600 to-rose-700 hover:from-rose-500 hover:to-rose-600 text-white rounded-xl text-sm font-bold transition-all shadow-lg">
                            {{ saving ? 'Rejecting...' : 'Reject Submission' }}
                        </button>
                    </div>
                </template>

                <!-- Per-item modal: details + full status lifecycle -->
                <template v-if="activeModal === 'item' && selectedItem">
                    <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-blue-50 to-transparent">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">{{ selectedItem.name }}</h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                {{ selectedItem.is_required ? 'Required' : 'Optional' }} · {{ selectedItem.input_type }} · Owner: {{ selectedItem.responsible_party }}
                            </p>
                        </div>
                        <button @click="closeModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                    <div class="p-5 space-y-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span :class="['px-2 py-0.5 rounded-full text-[9px] font-bold border', selectedItem.status_badge]">{{ selectedItem.status }}</span>
                            <span v-if="!selectedItem.applicant_visible" class="px-1.5 py-0.5 rounded text-[9px] font-bold border bg-slate-50 text-slate-500 border-slate-200">INTERNAL</span>
                            <span v-if="selectedItem.due_date" class="text-[10px] text-slate-400">Due: {{ formatDate(selectedItem.due_date) }}</span>
                            <span v-if="selectedItem.submitted_at" class="text-[10px] text-slate-400">Submitted: {{ formatDateTime(selectedItem.submitted_at) }}</span>
                        </div>
                        <p v-if="selectedItem.description && selectedSection !== 'HR Processing'" class="text-xs text-slate-500">{{ selectedItem.description }}</p>
                        <div v-else-if="selectedItem.description" class="rounded-lg bg-slate-50/80 border border-slate-100 px-3 py-2">
                            <p v-if="descHead(selectedItem.description)" class="text-xs text-slate-500">{{ descHead(selectedItem.description) }}</p>
                            <ul v-if="descBullets(selectedItem.description).length" class="mt-1 space-y-1">
                                <li v-for="(line, li) in descBullets(selectedItem.description)" :key="li" class="flex items-start gap-1.5 text-xs text-slate-500">
                                    <span class="mt-1.5 w-1 h-1 rounded-full bg-violet-400 shrink-0"></span>
                                    <span>{{ line }}</span>
                                </li>
                            </ul>
                        </div>
                        <div v-if="selectedItem.latest_submission?.file_url" class="flex items-center gap-2">
                            <a :href="selectedItem.latest_submission.file_url" target="_blank"
                                class="inline-flex items-center gap-1 text-[10px] font-semibold text-blue-600 hover:text-blue-800 bg-blue-50 px-2 py-1 rounded-lg border border-blue-100">
                                <Download class="w-3 h-3" /> {{ selectedItem.latest_submission.original_name }}
                            </a>
                        </div>
                        <div v-if="selectedItem.status === 'Rejected'" class="text-[10px] font-semibold text-rose-600 bg-rose-50 px-2.5 py-1.5 rounded-lg border border-rose-100">
                            Rejected: {{ selectedItem.rejection_reason || 'No reason provided.' }} — applicant may resubmit.
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Status</label>
                            <select
                                v-model="itemModalStatus"
                                :class="['w-full px-3 py-2.5 rounded-xl border text-xs font-semibold focus:ring-2 focus:ring-blue-500 shadow-sm', itemStatusSelect(itemModalStatus)]"
                            >
                                <option v-for="s in itemStatuses" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div v-if="itemModalRejecting">
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Rejection Reason</label>
                            <textarea
                                v-model="itemModalReason"
                                rows="3"
                                placeholder="e.g. The document is unclear. Please upload a clearer scan."
                                class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-rose-500 focus:border-transparent shadow-sm"
                            ></textarea>
                        </div>
                    </div>
                    <div class="p-4 border-t border-slate-100 bg-gradient-to-r from-slate-50 to-transparent flex items-center justify-end gap-2 flex-wrap">
                        <button @click="saveItemModalStatus" :disabled="saving" class="px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold transition-all disabled:opacity-50">
                            {{ saving ? 'Saving...' : 'Save Status' }}
                        </button>
                        <template v-if="canReview(selectedItem)">
                            <button @click="approveFromItemModal" :disabled="saving" class="inline-flex items-center gap-1 px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-500 hover:to-emerald-600 text-white rounded-xl text-xs font-bold transition-all shadow-lg disabled:opacity-50">
                                <Check class="w-3.5 h-3.5" /> {{ saving ? 'Working...' : 'Approve' }}
                            </button>
                            <button v-if="!itemModalRejecting" @click="itemModalRejecting = true" class="inline-flex items-center gap-1 px-4 py-2.5 bg-white border border-rose-200 text-rose-600 hover:bg-rose-50 rounded-xl text-xs font-bold transition-all">
                                <X class="w-3.5 h-3.5" /> Reject
                            </button>
                            <button v-else @click="rejectFromItemModal" :disabled="saving" class="px-4 py-2.5 bg-gradient-to-r from-rose-600 to-rose-700 hover:from-rose-500 hover:to-rose-600 text-white rounded-xl text-xs font-bold transition-all shadow-lg disabled:opacity-50">
                                {{ saving ? 'Rejecting...' : 'Confirm Reject' }}
                            </button>
                        </template>
                    </div>
                </template>

                <!-- CREATE EMPLOYEE conversion modal (after confirmation) -->
                <template v-if="activeModal === 'create-employee'">
                    <div class="p-5 border-b border-slate-200 bg-white shrink-0">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-base font-bold tracking-tight text-slate-900">CREATE EMPLOYEE</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Convert the selected applicant into an employee. Information from the application is shown automatically.</p>
                            </div>
                            <button @click="closeModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all">
                                <X class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <div class="p-5 space-y-6 overflow-y-auto">
                        <!-- 1. APPLICANT INFORMATION -->
                        <section>
                            <h4 class="text-xs font-bold text-slate-900 tracking-wide">1. APPLICANT INFORMATION</h4>
                            <div class="border-t border-slate-200 mt-1 mb-3"></div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2 text-xs">
                                <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="text-slate-400">Applicant</span><span class="font-bold text-slate-800">{{ conversion?.applicant?.name || onboarding.name }}</span></div>
                                <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="text-slate-400">Application</span><span class="font-semibold text-slate-700">{{ conversion?.applicant?.position_applied || onboarding.position }}</span></div>
                                <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="text-slate-400">Job Posting</span><span class="font-semibold text-slate-700">{{ (conversion?.job_posting?.title || '—') + ' — ' + (conversion?.job_posting?.department || '') }}</span></div>
                                <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="text-slate-400">Application Status</span><span class="font-semibold text-emerald-600">✓ {{ conversion?.application?.status || conversion?.applicant?.status || 'Selected' }}</span></div>
                                <div class="flex justify-between border-b border-slate-50 py-1.5"><span class="text-slate-400">Application ID</span><span class="font-mono font-semibold text-slate-700">{{ conversion?.application?.code || conversion?.applicant?.code || '—' }}</span></div>
                            </div>
                        </section>

                        <!-- 2. EMPLOYMENT ASSIGNMENT -->
                        <section>
                            <h4 class="text-xs font-bold text-slate-900 tracking-wide">2. EMPLOYMENT ASSIGNMENT</h4>
                            <div class="border-t border-slate-200 mt-1 mb-3"></div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Position *</label>
                                    <input v-model="hireForm.position" type="text" readonly class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 shadow-sm cursor-not-allowed" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">System Position * (users.position)</label>
                                    <select v-model="hireForm.position_rank" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm">
                                        <option value="trainee">Trainee</option>
                                        <option value="staff">Staff</option>
                                        <option value="supervisor">Supervisor</option>
                                        <option value="manager">Manager</option>
                                        <option value="secretary">Secretary</option>
                                        <option value="special_officer">Special Officer</option>
                                        <option value="vice_president">Vice-President</option>
                                    </select>
                                    <p class="text-[10px] text-slate-500 mt-1">Controls login access rank. Job title above stays as org title.</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Department *</label>
                                    <input v-model="hireForm.department" type="text" readonly class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 shadow-sm cursor-not-allowed" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">System Role * (users.role)</label>
                                    <select v-model="hireForm.role" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm">
                                        <option value="" disabled>Select role</option>
                                        <option v-for="r in roleOptions" :key="r" :value="r">{{ r }}</option>
                                    </select>
                                    <p class="text-[10px] text-slate-500 mt-1">Written to the employee record. Prefilled from department — confirm it.</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Employment Type *</label>
                                    <input v-model="hireForm.employment_type" type="text" readonly class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 shadow-sm cursor-not-allowed" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Deployment start date *</label>
                                    <input v-model="hireForm.start_date" type="date" @change="recalcTrainingEnd" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm" />
                                    <p v-if="conversion?.defaults?.start_date_hint" class="text-[10px] text-slate-500 mt-1">{{ conversion.defaults.start_date_hint }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Training duration (weeks) *</label>
                                    <input v-model.number="hireForm.training_duration_weeks" type="number" min="1" max="52" @change="recalcTrainingEnd" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Training end date *</label>
                                    <input v-model="hireForm.training_end_date" type="date" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm" />
                                    <p class="text-[10px] text-slate-500 mt-1">Auto-set from start + duration — editable calendar.</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Reporting Manager *</label>
                                    <select v-model="hireForm.reporting_manager" @change="syncReportingManagerId" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm">
                                        <option value="">-- Select manager --</option>
                                        <option v-for="m in (managers || [])" :key="m.id" :value="m.name">{{ m.name }} — {{ m.department || m.role }} · {{ caps(m.seat || m.position) }}</option>
                                        <option v-if="hireForm.reporting_manager && !(managers || []).some((m) => m.name === hireForm.reporting_manager)" :value="hireForm.reporting_manager">{{ hireForm.reporting_manager }}</option>
                                    </select>
                                    <p v-if="(conversion?.reporting?.chain || []).length" class="text-[10px] text-slate-500 mt-1">Position chain: {{ conversion.reporting.chain.join(' → ') }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Employee Status</label>
                                    <input v-model="hireForm.employee_status" type="text" readonly class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 shadow-sm cursor-not-allowed" />
                                </div>
                            </div>
                        </section>

                        <!-- 3. WORK ASSIGNMENT -->
                        <section>
                            <h4 class="text-xs font-bold text-slate-900 tracking-wide">3. WORK ASSIGNMENT</h4>
                            <div class="border-t border-slate-200 mt-1 mb-3"></div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Work Location *</label>
                                    <select v-model="hireForm.work_location" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm">
                                        <option>Main Manufacturing Plant</option>
                                        <option>Annex Facility</option>
                                        <option>Head Office</option>
                                        <option>Warehouse Site</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Work Arrangement</label>
                                    <input v-model="hireForm.work_mode" type="text" readonly class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 shadow-sm cursor-not-allowed" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Shift *</label>
                                    <select v-model="hireForm.shift" @change="applyShiftHours" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm">
                                        <template v-if="(shifts || []).length">
                                            <option v-for="sh in shifts" :key="sh.id" :value="sh.name">{{ sh.name }}{{ sh.code ? ' (' + sh.code + ')' : '' }}</option>
                                        </template>
                                        <template v-else>
                                            <option>Morning Shift</option>
                                            <option>Afternoon Shift</option>
                                            <option>Night Shift</option>
                                            <option>Rotating Shift</option>
                                        </template>
                                        <option v-if="hireForm.shift && !(shifts || []).some((sh) => sh.name === hireForm.shift)" :value="hireForm.shift">{{ hireForm.shift }}</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Work Schedule *</label>
                                    <div class="flex items-center gap-2">
                                        <select v-model="hireForm.work_schedule_start" class="w-36 px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm">
                                            <option v-for="d in workDays" :key="'s-' + d" :value="d">{{ d }}</option>
                                        </select>
                                        <span class="text-xs text-slate-400 font-semibold">to</span>
                                        <select v-model="hireForm.work_schedule_end" class="w-36 px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm">
                                            <option v-for="d in workDays" :key="'e-' + d" :value="d">{{ d }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Work Hours</label>
                                    <div class="flex items-center gap-2">
                                        <select v-model="hireForm.work_hours_start" class="w-36 px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm">
                                            <option v-for="t in workHourOptions" :key="'hs-' + t" :value="t">{{ t }}</option>
                                        </select>
                                        <span class="text-xs text-slate-400 font-semibold">to</span>
                                        <select v-model="hireForm.work_hours_end" class="w-36 px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm">
                                            <option v-for="t in workHourOptions" :key="'he-' + t" :value="t">{{ t }}</option>
                                        </select>
                                    </div>
                                    <p v-if="shiftHours[hireForm.shift] || (shifts || []).some((sh) => sh.name === hireForm.shift)" class="text-[10px] text-slate-500 mt-1">Suggested for {{ hireForm.shift }} — editable</p>
                                </div>
                            </div>
                        </section>

                        <!-- 4. COMPENSATION -->
                        <section>
                            <h4 class="text-xs font-bold text-slate-900 tracking-wide">4. COMPENSATION</h4>
                            <div class="border-t border-slate-200 mt-1 mb-3"></div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Salary Type</label>
                                    <select v-model="hireForm.salary_type" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm">
                                        <option>Monthly</option>
                                        <option>Daily</option>
                                        <option>Hourly</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Basic Salary *</label>
                                    <input v-model="hireForm.basic_salary" type="number" min="0" step="0.01" placeholder="18000.00" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm" />
                                    <p v-if="hireForm.basic_salary !== ''" class="text-[10px] text-slate-500 mt-1">[ {{ peso(hireForm.basic_salary) }} ]</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Pay Frequency</label>
                                    <select v-model="hireForm.pay_frequency" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm">
                                        <option>Monthly</option>
                                        <option>Semi-Monthly</option>
                                        <option>Weekly</option>
                                    </select>
                                </div>
                            </div>
                        </section>

                        <!-- 5. EMPLOYEE ACCOUNT -->
                        <section>
                            <h4 class="text-xs font-bold text-slate-900 tracking-wide">5. EMPLOYEE ACCOUNT</h4>
                            <div class="border-t border-slate-200 mt-1 mb-3"></div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Company Email</label>
                                    <div class="flex items-center gap-2">
                                        <input v-model="hireForm.company_email" type="email" :disabled="!hireForm.create_account" placeholder="juan.delacruz@monti.com" class="flex-1 min-w-0 px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm disabled:bg-slate-50 disabled:text-slate-400 disabled:cursor-not-allowed" />
                                        <button type="button" @click="suggestCompanyEmail(true)" :disabled="!hireForm.create_account" title="Auto-generate from applicant name" class="shrink-0 px-3 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 rounded-xl text-xs font-bold text-slate-600 transition-all disabled:opacity-40 disabled:cursor-not-allowed">Generate</button>
                                    </div>
                                </div>
                                <div class="flex items-end gap-4 pb-1">
                                    <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                        <input v-model="hireForm.create_account" type="checkbox" @change="onToggleCreateAccount" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                        Create Company Account ✓ Yes
                                    </label>
                                </div>
                                <div class="md:col-span-2 text-xs text-slate-500">Account Status: <span class="font-bold text-amber-600">Pending Activation</span> — a company email is auto-generated when left blank, and a temporary password plus the training schedule are emailed to the employee's personal email.</div>
                            </div>
                        </section>

                        <!-- 6. EMERGENCY CONTACT -->
                        <section>
                            <h4 class="text-xs font-bold text-slate-900 tracking-wide">6. EMERGENCY CONTACT</h4>
                            <div class="border-t border-slate-200 mt-1 mb-3"></div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Emergency Contact</label>
                                    <input v-model="hireForm.emergency_contact_name" type="text" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Relationship</label>
                                    <input v-model="hireForm.emergency_contact_relationship" type="text" placeholder="Mother" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Contact Number</label>
                                    <input v-model="hireForm.emergency_contact_number" type="text" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm" />
                                </div>
                            </div>
                        </section>

                        <!-- 7. INITIAL REQUIREMENTS -->
                        <section>
                            <h4 class="text-xs font-bold text-slate-900 tracking-wide">7. INITIAL REQUIREMENTS</h4>
                            <div class="border-t border-slate-200 mt-1 mb-3"></div>
                            <p class="text-xs font-semibold text-slate-600 mt-4 mb-2">Required Documents</p>
                            <div class="space-y-1.5 text-xs">
                                <div v-for="docItem in (conversion?.documents || [])" :key="'doc-' + docItem.id" class="flex items-center gap-2">
                                    <span :class="docItem.completed ? 'text-emerald-600' : 'text-slate-400'">{{ docItem.completed ? '✓' : '○' }}</span>
                                    <span :class="docItem.completed ? 'text-slate-800 font-semibold' : 'text-slate-500'">{{ docItem.name }}</span>
                                </div>
                                <div v-if="!(conversion?.documents || []).length" class="text-[11px] text-slate-400">No required documents on this onboarding.</div>
                            </div>
                        </section>

                        <!-- 8. REVIEW -->
                        <!-- Review moved to its own confirmation modal after [ Create Employee ]. -->
                    </div>

                    <div class="p-4 border-t border-slate-100 bg-gradient-to-r from-slate-50 to-transparent flex items-center justify-end gap-3 shrink-0">
                        <button @click="closeModal" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-all">[ Cancel ]</button>
                        <button @click="submitHire" :disabled="saving" class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-sm font-bold transition-all shadow-lg">
                            {{ saving ? 'Creating...' : '[ Create Employee ]' }}
                        </button>
                    </div>
                </template>

                <!-- REVIEW confirmation modal (opens after [ Create Employee ]) -->
                <template v-if="activeModal === 'hire-review'">
                    <div class="p-5 border-b border-slate-200 bg-white shrink-0">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-base font-bold tracking-tight text-slate-900">REVIEW — CREATE EMPLOYEE</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Please review the details before creating the employee record.</p>
                            </div>
                            <button @click="closeModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all">
                                <X class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <div class="p-5 overflow-y-auto">
                        <section class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2 text-xs">
                                <div class="flex justify-between py-1"><span class="text-slate-400">Employee</span><span class="font-bold text-slate-800">{{ conversion?.applicant?.name || onboarding.name }}</span></div>
                                <div class="flex justify-between py-1"><span class="text-slate-400">Position</span><span class="font-semibold">{{ hireForm.position || '—' }}</span></div>
                                <div class="flex justify-between py-1"><span class="text-slate-400">System Position</span><span class="font-semibold">{{ hireForm.position_rank || '—' }}</span></div>
                                <div class="flex justify-between py-1"><span class="text-slate-400">Department</span><span class="font-semibold">{{ hireForm.department || '—' }}</span></div>
                                <div class="flex justify-between py-1"><span class="text-slate-400">Reporting Manager</span><span class="font-semibold">{{ hireForm.reporting_manager || '—' }}</span></div>
                                <div class="flex justify-between py-1"><span class="text-slate-400">Employment Type</span><span class="font-semibold">{{ hireForm.employment_type || '—' }}</span></div>
                                <div class="flex justify-between py-1"><span class="text-slate-400">Deployment start date</span><span class="font-semibold">{{ hireForm.start_date || '—' }}</span></div>
                                <div class="flex justify-between py-1"><span class="text-slate-400">Training duration</span><span class="font-semibold">{{ hireForm.training_duration_weeks ? hireForm.training_duration_weeks + ' weeks' : '—' }}</span></div>
                                <div class="flex justify-between py-1"><span class="text-slate-400">Training end date</span><span class="font-semibold">{{ hireForm.training_end_date || '—' }}</span></div>
                                <div class="flex justify-between py-1"><span class="text-slate-400">Work Location</span><span class="font-semibold">{{ hireForm.work_location || '—' }}</span></div>
                                <div class="flex justify-between py-1"><span class="text-slate-400">Work Schedule</span><span class="font-semibold">{{ hireScheduleLabel }}</span></div>
                                <div class="flex justify-between py-1"><span class="text-slate-400">Employment Status</span><span class="font-semibold">{{ hireForm.employee_status || '—' }}</span></div>
                            </div>
                        </section>
                    </div>

                    <div class="p-4 border-t border-slate-100 bg-gradient-to-r from-slate-50 to-transparent flex items-center justify-end gap-3 shrink-0">
                        <button @click="backToHireForm" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-all">[ Back ]</button>
                        <button @click="confirmHireSubmit" :disabled="saving" class="px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-500 hover:to-emerald-600 text-white rounded-xl text-sm font-bold transition-all shadow-lg">
                            {{ saving ? 'Creating...' : '[ Confirm Create ]' }}
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Swal from 'sweetalert2';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import {
    Pencil, CheckCircle2, UserPlus, User, Briefcase, ClipboardCheck, FileText,
    CalendarDays, StickyNote, Check, X, Plus, Download, Trash2,
} from 'lucide-vue-next';

const props = defineProps({
    onboarding: { type: Object, required: true },
    sections: { type: Object, default: () => ({}) },
    activities: { type: Array, default: () => [] },
    notes: { type: Array, default: () => [] },
    profile: { type: Object, default: null },
    canComplete: { type: Boolean, default: false },
    missing: { type: Array, default: () => [] },
    readiness: { type: Object, default: () => ({}) },
    progress: { type: Number, default: 0 },
    alreadyHired: { type: Boolean, default: false },
    templates: { type: Array, default: () => [] },
    templateName: { type: String, default: '' },
    conversion: { type: Object, default: null },
    managers: { type: Array, default: () => [] },
    employmentTypes: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
});

const page = usePage();
const pageLoading = ref(true);
const saving = ref(false);
const activeModal = ref(null);
const expandedHistory = ref({});

onMounted(() => {
    setTimeout(() => { pageLoading.value = false; }, 150);
});

// Toast flash
watch(() => page.props.flash, (flash) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
}, { immediate: true });

// Watch validation errors
watch(() => page.props.errors, (errors) => {
    if (Object.keys(errors).length > 0) {
        const first = Object.values(errors)[0];
        toast.error(String(first));
    }
}, { deep: true });

const displayTemplate = computed(() => props.templateName || props.onboarding.template || '');

const initials = computed(() => {
    const parts = props.onboarding.name.trim().split(/\s+/);
    const first = parts[0] ? parts[0][0] : '';
    const last = parts.length > 1 ? parts[parts.length - 1][0] : '';
    return (first + last).toUpperCase();
});

const hasSnapshot = computed(() =>
    Object.keys(props.sections).some((key) => props.sections[key].length > 0)
);

const taskStatuses = ['Pending', 'In Progress', 'Completed', 'Blocked'];

const sectionColor = (section) =>
    ({
        'Applicant Requirements': 'bg-blue-500',
        'HR Processing': 'bg-violet-500',
        'Company Preparation': 'bg-emerald-500',
    })[section] || 'bg-slate-400';

const toggleHistory = (id) => {
    expandedHistory.value[id] = !expandedHistory.value[id];
};

const canReview = (item) =>
    item.latest_submission && ['Submitted', 'Resubmitted', 'Under Review'].includes(item.status);

const displayValue = (value) => {
    if (value === true) return 'Yes';
    if (value === false) return 'No';
    return String(value);
};

// HR task display: split a description into intro text and "- " bullet
// lines (task contents) so each renders on its own line.
const descLines = (text) => String(text ?? '').split('\n').map((l) => l.trim()).filter((l) => l !== '');
const descBullets = (text) => descLines(text).filter((l) => l.startsWith('- ')).map((l) => l.slice(2).trim()).filter((l) => l !== '');
const descHead = (text) => descLines(text).filter((l) => !l.startsWith('- ')).join(' ');

// ============ DETAILS FORM ============
const detailsForm = ref({
    start_date: props.onboarding.start_date || '',
    expected_start_date: props.onboarding.expected_start_date || '',
    expected_completion_date: props.onboarding.expected_completion_date || '',
    assigned_manager: props.onboarding.assigned_manager || '',
    work_location: props.onboarding.work_location || '',
    work_schedule: props.onboarding.work_schedule || '',
    template_id: '',
});

const saveDetails = () => {
    if (detailsForm.value.template_id !== '' && hasSnapshot.value) {
        detailsForm.value.template_id = '';
    }
    saving.value = true;
    router.patch(route('hrm.onboarding.update', props.onboarding.id), detailsForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            toast.success('Onboarding details updated.');
        },
        onError: () => toast.error('Failed to update details.'),
        onFinish: () => { saving.value = false; },
    });
};

// ============ ITEMS ============
const updateTaskStatus = (item, status) => {
    router.patch(route('hrm.onboarding.items.status', { onboarding: props.onboarding.id, item: item.id }), { status }, {
        preserveScroll: true,
        onSuccess: () => toast.success('Task status updated.'),
        onError: () => toast.error('Failed to update task status.'),
    });
};

const itemStatusSelect = (status) =>
    ({
        Pending: 'bg-slate-50 text-slate-500 border-slate-200',
        Submitted: 'bg-blue-50 text-blue-600 border-blue-200',
        Resubmitted: 'bg-indigo-50 text-indigo-600 border-indigo-200',
        'Under Review': 'bg-amber-50 text-amber-700 border-amber-200',
        'In Progress': 'bg-amber-50 text-amber-700 border-amber-200',
        Verified: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        Completed: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        Rejected: 'bg-rose-50 text-rose-600 border-rose-200',
        Blocked: 'bg-rose-50 text-rose-600 border-rose-200',
    })[status] || 'bg-slate-50 text-slate-500 border-slate-200';

const rejectForm = ref({ item: null, reason: '' });

// Per-item modal: full lifecycle statuses (Pending, Submitted, Resubmitted,
// Under Review, In Progress, Completed, Verified, Rejected, Blocked) with
// approve / reject shortcuts for reviewable submissions.
const itemStatuses = ['Pending', 'Submitted', 'Resubmitted', 'Under Review', 'In Progress', 'Completed', 'Verified', 'Rejected', 'Blocked'];
const selectedItem = ref(null);
const selectedSection = ref('');
const itemModalStatus = ref('Pending');
const itemModalReason = ref('');
const itemModalRejecting = ref(false);

const openItemModal = (item, section = '') => {
    selectedItem.value = item;
    selectedSection.value = section;
    itemModalStatus.value = item.status;
    itemModalReason.value = '';
    itemModalRejecting.value = false;
    activeModal.value = 'item';
};

const saveItemModalStatus = () => {
    if (!selectedItem.value || saving.value) return;
    saving.value = true;
    router.patch(route('hrm.onboarding.items.status', { onboarding: props.onboarding.id, item: selectedItem.value.id }), { status: itemModalStatus.value }, {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            toast.success(`Item marked as ${itemModalStatus.value}.`);
        },
        onError: () => toast.error('Failed to update item status.'),
        onFinish: () => { saving.value = false; },
    });
};

const approveFromItemModal = () => {
    if (!selectedItem.value || saving.value) return;
    saving.value = true;
    router.post(route('hrm.onboarding.items.review', { onboarding: props.onboarding.id, item: selectedItem.value.id }), { decision: 'approve' }, {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            toast.success('Submission approved.');
        },
        onError: () => toast.error('Failed to approve submission.'),
        onFinish: () => { saving.value = false; },
    });
};

const rejectFromItemModal = () => {
    if (!selectedItem.value || saving.value) return;
    if (!itemModalReason.value.trim()) {
        toast.error('Please provide a rejection reason.');
        return;
    }
    saving.value = true;
    router.post(route('hrm.onboarding.items.review', { onboarding: props.onboarding.id, item: selectedItem.value.id }), { decision: 'reject', reason: itemModalReason.value }, {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            toast.success('Submission rejected. The applicant can resubmit.');
        },
        onError: () => toast.error('Failed to reject submission.'),
        onFinish: () => { saving.value = false; },
    });
};

const openRejectModal = (item) => {
    rejectForm.value = { item, reason: '' };
    activeModal.value = 'reject';
};

const reviewItem = (item, decision) => {
    router.post(route('hrm.onboarding.items.review', { onboarding: props.onboarding.id, item: item.id }), { decision }, {
        preserveScroll: true,
        // Silent on success (status badge updates in place); errors still toast.
        onError: () => toast.error('Failed to review submission.'),
    });
};

const submitReject = () => {
    if (!rejectForm.value.reason.trim()) {
        toast.error('Please provide a rejection reason.');
        return;
    }
    saving.value = true;
    router.post(
        route('hrm.onboarding.items.review', { onboarding: props.onboarding.id, item: rejectForm.value.item.id }),
        { decision: 'reject', reason: rejectForm.value.reason },
        {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                toast.success('Submission rejected. The applicant can resubmit.');
            },
            onError: () => toast.error('Failed to reject submission.'),
            onFinish: () => { saving.value = false; },
        },
    );
};

// ============ NOTES ============
const noteForm = ref({ content: '', is_hr_private: true });

const storeNote = () => {
    if (!noteForm.value.content.trim()) {
        toast.error('Note content is required.');
        return;
    }
    router.post(route('hrm.onboarding.notes.store', props.onboarding.id), noteForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Note added.');
            noteForm.value.content = '';
        },
        onError: () => toast.error('Failed to add note.'),
    });
};

const deleteNote = (note) => {
    Swal.fire({
        title: 'Delete note?',
        text: 'This note will be permanently removed.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#e11d48',
        customClass: { popup: 'rounded-[2rem]' },
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('hrm.onboarding.notes.destroy', { onboarding: props.onboarding.id, note: note.id }), {
                preserveScroll: true,
                onSuccess: () => toast.success('Note removed.'),
                onError: () => toast.error('Failed to remove note.'),
            });
        }
    });
};

// ============ COMPLETE / CREATE EMPLOYEE ============
const confirmComplete = () => {
    if (!props.canComplete) {
        toast.error('Complete all required items and mandatory activities first.');
        return;
    }
    Swal.fire({
        title: 'Complete Onboarding?',
        text: 'This marks the onboarding as completed. You can then create the employee record.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Complete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#059669',
        customClass: { popup: 'rounded-[2rem]' },
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('hrm.onboarding.complete', props.onboarding.id), {}, {
                preserveScroll: true,
                onSuccess: () => toast.success('Onboarding completed.'),
                onError: () => toast.error('Failed to complete onboarding.'),
            });
        }
    });
};

// ============ CREATE EMPLOYEE (confirmation -> full conversion modal) ============
const workDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const workHourOptions = [
    '12:00 AM', '1:00 AM', '2:00 AM', '3:00 AM', '4:00 AM', '5:00 AM',
    '6:00 AM', '7:00 AM', '8:00 AM', '9:00 AM', '10:00 AM', '11:00 AM',
    '12:00 PM', '1:00 PM', '2:00 PM', '3:00 PM', '4:00 PM', '5:00 PM',
    '6:00 PM', '7:00 PM', '8:00 PM', '9:00 PM', '10:00 PM', '11:00 PM',
];

const parseHoursRange = (value) => {
    const parts = String(value || '').split(/\s*[–—-]\s*/).map((p) => p.trim()).filter(Boolean);
    const norm = (p) => workHourOptions.find((t) => t.toLowerCase() === String(p || '').toLowerCase()) || null;
    return { start: norm(parts[0]) || '7:00 AM', end: norm(parts[1]) || '4:00 PM' };
};

const parseScheduleRange = (value) => {
    const parts = String(value || '').split(/\s*[–—-]\s*/).map((p) => p.trim()).filter(Boolean);
    const norm = (p) => {
        const hit = workDays.find((d) => d.toLowerCase() === String(p || '').toLowerCase());
        return hit || null;
    };
    return { start: norm(parts[0]) || 'Monday', end: norm(parts[1]) || 'Friday' };
};

// Suggested work hours from the Shift page roster (falls back to the
// legacy map when the roster hasn't loaded). Applied automatically when
// HR changes Shift; Work Hours stay editable.
const shiftHours = {
    'Morning Shift': { start: '8:00 AM', end: '5:00 PM' },
    'Afternoon Shift': { start: '2:00 PM', end: '11:00 PM' },
    'Night Shift': { start: '10:00 PM', end: '6:00 AM' },
    'Rotating Shift': { start: '8:00 AM', end: '5:00 PM' },
};

const applyShiftHours = () => {
    const live = (props.shifts || []).find((sh) => sh.name === hireForm.value.shift);
    if (live && (live.start_time || live.end_time)) {
        if (live.start_time) hireForm.value.work_hours_start = live.start_time;
        if (live.end_time) hireForm.value.work_hours_end = live.end_time;
        return;
    }
    const sug = shiftHours[hireForm.value.shift];
    if (!sug) return;
    hireForm.value.work_hours_start = sug.start;
    hireForm.value.work_hours_end = sug.end;
};

const hireForm = ref({
    position: '', position_rank: 'staff', department: '', role: '', employment_type: 'Regular', employment_type_id: '',
    start_date: '', training_duration_weeks: '', training_end_date: '', reporting_manager: '', reporting_manager_id: '', employee_status: 'Pending Training',
    work_location: 'Main Manufacturing Plant', work_mode: 'Onsite',
    shift: 'Morning Shift', work_schedule: 'Monday – Friday',
    work_schedule_start: 'Monday', work_schedule_end: 'Friday',
    work_hours: '7:00 AM – 4:00 PM',
    work_hours_start: '7:00 AM', work_hours_end: '4:00 PM',
    salary_type: 'Monthly', basic_salary: '', pay_frequency: 'Monthly',
    company_email: '', create_account: true,
    emergency_contact_name: '', emergency_contact_relationship: '', emergency_contact_number: '',
});

// Training end = deployment start + duration weeks (calendar stays editable).
// Blank duration stays blank — no auto default.
const recalcTrainingEnd = () => {
    const weeks = Number(hireForm.value.training_duration_weeks);
    if (!weeks || weeks < 1 || weeks > 52) return;
    const start = hireForm.value.start_date;
    if (!start) return;
    const d = new Date(start + 'T00:00:00');
    if (isNaN(d)) return;
    d.setDate(d.getDate() + weeks * 7);
    hireForm.value.training_end_date = d.toISOString().slice(0, 10);
};

const hireScheduleLabel = computed(() =>
    `${hireForm.value.work_schedule_start || 'Monday'} – ${hireForm.value.work_schedule_end || 'Friday'}`);
const hireErrors = ref({});

const peso = (v) => {
    const n = parseFloat(String(v ?? '').replace(/[^0-9.]/g, ''));
    if (isNaN(n)) return '';
    return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

// First-letter caps for seat/position labels (data may be lowercase).
const caps = (v) => {
    const s = String(v ?? '');
    return s ? s.charAt(0).toUpperCase() + s.slice(1) : s;
};

// Auto-generate company email from the applicant name (firstname.lastname@monti.com).
const suggestCompanyEmail = (force = false) => {
    if (!force && hireForm.value.company_email?.trim()) return;
    const raw = props.conversion?.applicant?.name || props.onboarding.name || '';
    const parts = String(raw).toLowerCase().split(/[^a-z0-9]+/).filter(Boolean);
    if (!parts.length) {
        toast.error('Cannot generate email — applicant name is empty.');
        return;
    }
    const local = (parts.length > 1 ? `${parts[0]}.${parts[parts.length - 1]}` : parts[0]).slice(0, 64);
    hireForm.value.company_email = `${local}@monti.com`;
};

const onToggleCreateAccount = () => {
    if (hireForm.value.create_account) {
        suggestCompanyEmail(false); // fill in only when the field is empty
    }
};

// Keep reporting_manager_id in sync with the chosen manager name so
// users.manager_id (training visibility) always points to the right row.
const syncReportingManagerId = () => {
    const hit = (props.managers || []).find((m) => m.name === hireForm.value.reporting_manager);
    hireForm.value.reporting_manager_id = hit ? hit.id : '';
};

// Explicit system-role options (users.role). Server list wins; hardcoded
// fallback mirrors the backend list so the select never renders empty.
const roleOptions = computed(() => (props.roles?.length ? props.roles : ['HRM', 'SCM', 'FIN', 'MAN', 'INV', 'ORD', 'WAR', 'CRM', 'ECO', 'PRO', 'PROJ', 'IT', 'LOG']));

// Best-guess role for the hire department: UPPER(name) when valid, else the
// department's stored module when valid, else HRM. Mirrors backend fallback.
const guessHireRole = (deptName) => {
    const list = roleOptions.value || [];
    const upper = String(deptName || '').toUpperCase();
    if (upper && list.includes(upper)) return upper;
    const dept = (props.departments || []).find((d) => d.name === deptName);
    const cat = String(dept?.category || '').toUpperCase();
    if (cat && list.includes(cat)) return cat;
    return 'HRM';
};

const openHireModal = () => {
    const c = props.conversion || {};
    const d = c.defaults || {};
    const range = parseScheduleRange(d.work_schedule || props.onboarding.work_schedule);
    const hours = parseHoursRange(props.onboarding.work_schedule);
    hireForm.value = {
        position: d.position || props.onboarding.position || '',
        position_rank: 'staff',
        department: d.department || props.onboarding.department || '',
        role: guessHireRole(d.department || props.onboarding.department || ''),
        employment_type: d.employment_type || props.onboarding.employment_type || 'Regular',
        employment_type_id: '',
        start_date: d.start_date || new Date().toISOString().slice(0, 10),
        training_duration_weeks: '',
        training_end_date: '',
        reporting_manager: d.reporting_manager || props.onboarding.assigned_manager || '',
        reporting_manager_id: d.reporting_manager_id || '',
        employee_status: 'Pending Training',
        work_location: d.work_location || 'Main Manufacturing Plant',
        work_mode: 'Onsite',
        shift: 'Morning Shift',
        work_schedule: `${range.start} – ${range.end}`,
        work_schedule_start: range.start, work_schedule_end: range.end,
        work_hours: `${hours.start} – ${hours.end}`,
        work_hours_start: hours.start, work_hours_end: hours.end,
        salary_type: 'Monthly',
        basic_salary: d.basic_salary ?? '',
        pay_frequency: 'Monthly',
        company_email: d.company_email || '',
        create_account: true,
        emergency_contact_name: d.emergency_name || props.profile?.emergency_contact?.name || '',
        emergency_contact_relationship: d.emergency_relationship || props.profile?.emergency_contact?.relationship || '',
        emergency_contact_number: d.emergency_phone || props.profile?.emergency_contact?.phone || '',
    };
    // Shift drives Work Hours: apply the suggestion on open too (not only on
    // change), so Morning opens at 8:00 AM – 5:00 PM right away. Still editable.
    // Duration stays blank — end is set once HR types start + duration.
    applyShiftHours();
    // Pre-select matching employment-type id when names line up.
    const match = (props.employmentTypes || []).find((t) => t.name === hireForm.value.employment_type);
    if (match) hireForm.value.employment_type_id = match.id;
    hireErrors.value = {};
    activeModal.value = 'create-employee';
};

const confirmCreateEmployee = () => {
    if (!props.canComplete && props.onboarding.status !== 'Completed') {
        toast.error('The onboarding must be completed before creating the employee record.');
        return;
    }
    Swal.fire({
        title: 'Create Employee Record?',
        html: 'This will generate an employee record from the verified applicant data, update the application to <b>Hired</b>, and finalize onboarding.',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Yes, Create Employee',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#2563eb',
        customClass: { popup: 'rounded-[2rem]' },
    }).then((result) => {
        // After confirmation, show the full CREATE EMPLOYEE conversion modal.
        if (result.isConfirmed) openHireModal();
    });
};

const hireTrainingDone = computed(() =>
    (props.conversion?.trainings || []).filter((t) => t.completed).length);
const hireTrainingTotal = computed(() =>
    (props.conversion?.trainings || []).length);
const hireDocsDone = computed(() =>
    (props.conversion?.documents || []).filter((t) => t.completed).length);
const hireDocsTotal = computed(() =>
    (props.conversion?.documents || []).length);

const submitHire = () => {
    const f = hireForm.value;
    if (!f.position?.trim()) { toast.error('Position is required.'); return; }
    const allowedRanks = ['trainee', 'staff', 'supervisor', 'manager', 'secretary', 'special_officer', 'vice_president'];
    if (!f.position_rank || !allowedRanks.includes(f.position_rank)) { toast.error('System Position is required.'); return; }
    if (!f.department?.trim()) { toast.error('Department is required.'); return; }
    if (!f.role) { toast.error('System Role is required.'); return; }
    if (!f.start_date) { toast.error('Start date is required.'); return; }
    if (!f.training_duration_weeks || Number(f.training_duration_weeks) < 1) { toast.error('Training duration is required.'); return; }
    if (!f.training_end_date) { toast.error('Training end date is required.'); return; }
    if (f.basic_salary !== '' && isNaN(parseFloat(f.basic_salary))) { toast.error('Basic salary must be numeric.'); return; }
    // Open the review step as its own modal — actual creation happens on confirm.
    activeModal.value = 'hire-review';
};

const backToHireForm = () => {
    activeModal.value = 'create-employee';
};

const confirmHireSubmit = () => {
    const f = hireForm.value;
    saving.value = true;
    // Combine the day + hour dropdowns into DB-ready strings; drop helper keys.
    const { work_schedule_start, work_schedule_end, work_hours_start, work_hours_end, ...rest } = f;
    void work_schedule_start; void work_schedule_end; void work_hours_start; void work_hours_end;
    router.post(route('hrm.onboarding.create-employee', props.onboarding.id), {
        ...rest,
        work_schedule: `${f.work_schedule_start || 'Monday'} – ${f.work_schedule_end || 'Friday'}`,
        work_hours: `${f.work_hours_start || '7:00 AM'} – ${f.work_hours_end || '4:00 PM'}`,
        company_email: f.create_account ? (f.company_email || null) : null,
        employment_type_id: f.employment_type_id || null,
        basic_salary: f.basic_salary === '' ? null : f.basic_salary,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            toast.success('Employee record created.');
        },
        onError: (e) => {
            hireErrors.value = e || {};
            toast.error(String(Object.values(e || {})[0] || 'Failed to create employee record.'));
        },
        onFinish: () => { saving.value = false; },
    });
};

// ============ HELPERS ============
const openModal = (type) => {
    if (type === 'details') {
        detailsForm.value = {
            start_date: props.onboarding.start_date || '',
            expected_start_date: props.onboarding.expected_start_date || '',
            expected_completion_date: props.onboarding.expected_completion_date || '',
            assigned_manager: props.onboarding.assigned_manager || '',
            work_location: props.onboarding.work_location || '',
            work_schedule: props.onboarding.work_schedule || '',
            template_id: props.onboarding.template_id || '',
        };
    }
    activeModal.value = type;
};

const closeModal = () => {
    activeModal.value = null;
    selectedItem.value = null;
    selectedSection.value = '';
    itemModalRejecting.value = false;
};

const formatDate = (value) => {
    if (!value) return '—';
    const d = new Date(value);
    if (isNaN(d)) return '—';
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};

const formatDateTime = (value) => {
    if (!value) return '—';
    const d = new Date(value);
    if (isNaN(d)) return '—';
    return d.toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
};
</script>

<style scoped>
.bg-grid-slate-100 {
    background-image: radial-gradient(circle, #cbd5e1 1px, transparent 1px);
    background-size: 24px 24px;
}
</style>