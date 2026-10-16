<template>
    <Head title="Logistics Interview Management" />

    <AuthenticatedLayout>
        <!-- Toast Notification -->
        <Transition name="toast">
            <div v-if="showToast" class="fixed top-4 left-1/2 -translate-x-1/2 z-[100] flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-2xl border max-w-xs w-full mx-4"
                :class="toastType === 'error' ? 'bg-rose-600 border-rose-500 text-white' : 'bg-slate-900 border-slate-700 text-white'">
                <CheckCircle v-if="toastType !== 'error'" class="h-4 w-4 text-emerald-400 shrink-0" />
                <XCircle v-else class="h-4 w-4 text-rose-300 shrink-0" />
                <p class="text-xs font-semibold tracking-wide flex-1">{{ toastMessage }}</p>
            </div>
        </Transition>

        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30 pb-24">
            <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <Calendar class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Briefcase class="h-3.5 w-3.5" /> Logistics · Interviews
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Logistics Interviews</h1>
                            <p class="text-sm text-blue-100/90">{{ applicants.length }} candidate{{ applicants.length !== 1 ? 's' : '' }} assigned</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span v-if="canEdit" class="flex items-center gap-1.5 rounded-full bg-emerald-400/90 px-3 py-1.5 text-xs font-black text-emerald-950">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-950 animate-pulse"></span> Full Access
                            </span>
                            <span v-else class="flex items-center gap-1.5 rounded-full bg-amber-300/90 px-3 py-1.5 text-xs font-black text-amber-950">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-900 animate-pulse"></span> View Only
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Applicants List -->
                <div v-if="applicants.length === 0"
                    class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                        <Calendar class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">No applicants assigned for interview</p>
                    <p class="text-xs text-gray-400 mt-1">When HR accepts an applicant and assigns them to Logistics department, they will appear here.</p>
                </div>

                <TransitionGroup v-else name="card" tag="div" class="space-y-4">
                    <div v-for="(applicant, i) in applicants" :key="applicant.id"
                        :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                        class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 overflow-hidden cursor-pointer"
                        @click="openDetailPanel(applicant)">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="relative p-5">
                            <div class="flex items-start gap-3.5">
                                <!-- Profile Photo -->
                                <div class="shrink-0 relative">
                                    <img v-if="applicant.profile_photo" :src="applicant.profile_photo" :alt="applicant.name"
                                        class="h-14 w-14 rounded-2xl object-cover ring-2 ring-indigo-100 dark:ring-zinc-700 shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300" />
                                    <div v-else
                                        class="h-14 w-14 rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 flex items-center justify-center text-white text-base font-black shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                        {{ getInitials(applicant.name) }}
                                    </div>
                                    <span v-if="applicant.scheduled_at && new Date(applicant.scheduled_at).toDateString() === new Date().toDateString()"
                                        class="absolute -top-1 -right-1 h-3.5 w-3.5 rounded-full bg-emerald-400 border-2 border-white dark:border-zinc-800 animate-pulse"></span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-sm font-black text-slate-900 dark:text-white tracking-tight group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors">{{ applicant.name }}</h3>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ applicant.email }}</p>
                                    <p class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 mt-0.5 uppercase tracking-wide">{{ applicant.position_applied }}</p>
                                    <div v-if="applicant.scheduled_at" class="flex items-center gap-1.5 mt-2">
                                        <Calendar class="h-3 w-3 text-indigo-400" />
                                        <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ formatDateTime(applicant.scheduled_at) }}</span>
                                        <span v-if="applicant.interview_type" class="text-[10px] font-bold text-slate-500 bg-slate-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded-lg ml-1 capitalize">
                                            {{ getInterviewTypeLabel(applicant.interview_type) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action buttons (only if canEdit) -->
                        <div v-if="canEdit" class="relative px-5 pb-4" @click.stop>
                            <div v-if="!applicant.scheduled_at" class="flex gap-2">
                                <button @click="openScheduleModal(applicant)" class="flex-1 flex items-center justify-center gap-2 py-2.5 px-3 bg-gradient-to-r from-indigo-600 to-violet-600 hover:shadow-lg hover:-translate-y-0.5 text-white rounded-2xl text-xs font-bold tracking-wide transition-all active:scale-95 shadow-sm">
                                    <Calendar class="h-3.5 w-3.5" /> Set Schedule
                                </button>
                            </div>
                            <div v-else class="flex gap-2">
                                <button @click="openScheduleModal(applicant)" class="flex items-center justify-center gap-1.5 py-2.5 px-3 bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-600 dark:text-slate-300 rounded-2xl text-xs font-bold tracking-wide transition-all active:scale-95">
                                    <Calendar class="h-3.5 w-3.5" /> Reschedule
                                </button>
                                <button @click="openPassModal(applicant)" class="flex-1 flex items-center justify-center gap-2 py-2.5 px-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl text-xs font-bold tracking-wide transition-all active:scale-95 shadow-sm shadow-emerald-200">
                                    <CheckCircle class="h-3.5 w-3.5" /> Pass
                                </button>
                                <button @click="openFailModal(applicant)" class="flex-1 flex items-center justify-center gap-2 py-2.5 px-3 bg-rose-600 hover:bg-rose-700 text-white rounded-2xl text-xs font-bold tracking-wide transition-all active:scale-95 shadow-sm shadow-rose-200">
                                    <XCircle class="h-3.5 w-3.5" /> Fail
                                </button>
                                <button @click="openPassToOtherModal(applicant)" class="flex items-center justify-center gap-1.5 py-2.5 px-3 bg-purple-600 hover:bg-purple-700 text-white rounded-2xl text-xs font-bold tracking-wide transition-all active:scale-95 shadow-sm shadow-purple-200">
                                    <ArrowRight class="h-3.5 w-3.5" /> Pass to Other
                                </button>
                            </div>
                        </div>
                        <div v-else class="relative px-5 pb-4 text-center text-[11px] text-slate-400 italic">View only · Contact Logistics manager to manage this interview</div>

                        <!-- Tap hint -->
                        <div class="relative flex items-center justify-end gap-1 px-5 pb-3">
                            <p class="text-[10px] text-slate-300 dark:text-slate-600">Tap to view profile</p>
                            <ChevronRight class="h-3 w-3 text-slate-300 dark:text-slate-600 group-hover:translate-x-1 group-hover:text-indigo-500 transition-all" />
                        </div>
                    </div>
                </TransitionGroup>
            </div>
        </div>

        <!-- Detail Side Panel -->
        <Teleport to="body">
            <Transition name="panel-backdrop">
                <div v-if="detailPanelOpen" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm" @click="closeDetailPanel"></div>
            </Transition>
            <Transition name="panel-slide">
                <div v-if="detailPanelOpen" class="fixed top-0 right-0 bottom-0 z-50 w-full max-w-md bg-white dark:bg-zinc-900 shadow-2xl flex flex-col overflow-hidden">
                    <!-- Header with photo -->
                    <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-5 pt-10 pb-6">
                        <div class="absolute -top-16 -right-16 h-48 w-48 rounded-full bg-white/10 blur-3xl animate-float" />
                        <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 20px 20px;"></div>
                        <button @click="closeDetailPanel" class="absolute top-4 right-4 h-8 w-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-colors">
                            <X class="h-4 w-4 text-white" />
                        </button>
                        <div class="relative flex items-center gap-4">
                            <img v-if="detailPanelApplicant?.profile_photo" :src="detailPanelApplicant.profile_photo" :alt="detailPanelApplicant?.name" class="h-20 w-20 rounded-2xl object-cover ring-4 ring-white/30 shadow-xl animate-pop" />
                            <div v-else class="h-20 w-20 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-white text-2xl font-black ring-4 ring-white/20 shadow-xl animate-pop">
                                {{ getInitials(detailPanelApplicant?.name) }}
                            </div>
                            <div>
                                <h2 class="text-lg font-black text-white tracking-tight">{{ detailPanelApplicant?.name }}</h2>
                                <p class="text-indigo-200 text-sm font-medium">{{ detailPanelApplicant?.position_applied }}</p>
                                <div class="mt-2">
                                    <span class="inline-flex items-center gap-1.5 text-[10px] font-black px-2.5 py-1 rounded-full uppercase tracking-widest bg-white/15 text-white ring-1 ring-white/25 backdrop-blur">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse"></span> {{ detailPanelApplicant?.scheduled_at ? 'Scheduled' : 'Pending' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div class="flex gap-1 bg-white dark:bg-zinc-900 border-b border-slate-200 dark:border-zinc-800 px-4 pt-3 overflow-x-auto">
                        <button @click="detailPanelTab = 'personal'" :class="['px-4 py-2 text-xs font-bold rounded-t-xl transition-all', detailPanelTab === 'personal' ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400' : 'text-slate-500 hover:text-slate-700']">Personal</button>
                        <button @click="detailPanelTab = 'contact'" :class="['px-4 py-2 text-xs font-bold rounded-t-xl transition-all', detailPanelTab === 'contact' ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400' : 'text-slate-500 hover:text-slate-700']">Contact</button>
                        <button @click="detailPanelTab = 'ids'" :class="['px-4 py-2 text-xs font-bold rounded-t-xl transition-all', detailPanelTab === 'ids' ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400' : 'text-slate-500 hover:text-slate-700']">IDs</button>
                        <button @click="detailPanelTab = 'education'" :class="['px-4 py-2 text-xs font-bold rounded-t-xl transition-all', detailPanelTab === 'education' ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400' : 'text-slate-500 hover:text-slate-700']">Education</button>
                        <button @click="detailPanelTab = 'employment'" :class="['px-4 py-2 text-xs font-bold rounded-t-xl transition-all', detailPanelTab === 'employment' ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400' : 'text-slate-500 hover:text-slate-700']">Employment</button>
                        <button @click="detailPanelTab = 'family'" :class="['px-4 py-2 text-xs font-bold rounded-t-xl transition-all', detailPanelTab === 'family' ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400' : 'text-slate-500 hover:text-slate-700']">Family</button>
                    </div>

                    <!-- Panel body -->
                    <div class="flex-1 overflow-y-auto p-5 space-y-5">
                        <!-- Personal -->
                        <div v-if="detailPanelTab === 'personal'" class="space-y-4">
                            <div class="bg-slate-50 dark:bg-zinc-800/60 rounded-3xl p-4 space-y-3 border border-gray-100 dark:border-zinc-800">
                                <div class="flex items-center gap-2 border-b border-slate-200 dark:border-zinc-700 pb-2">
                                    <UserIcon class="h-4 w-4 text-indigo-500" />
                                    <p class="text-xs font-black text-slate-700 dark:text-slate-200 uppercase tracking-wider">Basic Information</p>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div><p class="text-[10px] text-slate-400">Full Name</p><p class="text-sm font-medium">{{ detailPanelApplicant?.first_name }} {{ detailPanelApplicant?.middle_name }} {{ detailPanelApplicant?.last_name }}</p></div>
                                    <div><p class="text-[10px] text-slate-400">Date of Birth</p><p class="text-sm">{{ formatDateFull(detailPanelApplicant?.date_of_birth) }}</p></div>
                                    <div><p class="text-[10px] text-slate-400">Place of Birth</p><p class="text-sm">{{ detailPanelApplicant?.place_of_birth || '—' }}</p></div>
                                    <div><p class="text-[10px] text-slate-400">Age</p><p class="text-sm">{{ detailPanelApplicant?.age || '—' }}</p></div>
                                    <div><p class="text-[10px] text-slate-400">Sex</p><p class="text-sm capitalize">{{ detailPanelApplicant?.sex || '—' }}</p></div>
                                    <div><p class="text-[10px] text-slate-400">Civil Status</p><p class="text-sm capitalize">{{ detailPanelApplicant?.civil_status || '—' }}</p></div>
                                    <div><p class="text-[10px] text-slate-400">Citizenship</p><p class="text-sm">{{ detailPanelApplicant?.citizenship || '—' }}</p></div>
                                    <div><p class="text-[10px] text-slate-400">Religion</p><p class="text-sm">{{ detailPanelApplicant?.religion || '—' }}</p></div>
                                    <div><p class="text-[10px] text-slate-400">Weight / Height</p><p class="text-sm">{{ detailPanelApplicant?.weight }} kg / {{ detailPanelApplicant?.height }} cm</p></div>
                                    <div><p class="text-[10px] text-slate-400">Languages</p><p class="text-sm">{{ detailPanelApplicant?.languages || '—' }}</p></div>
                                    <div class="col-span-2"><p class="text-[10px] text-slate-400">Special Skills</p><p class="text-sm">{{ detailPanelApplicant?.special_skills || '—' }}</p></div>
                                    <div class="col-span-2"><p class="text-[10px] text-slate-400">Machine Operation</p><p class="text-sm">{{ detailPanelApplicant?.machine_operation || '—' }}</p></div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact -->
                        <div v-if="detailPanelTab === 'contact'" class="space-y-4">
                            <div class="bg-slate-50 dark:bg-zinc-800/60 rounded-3xl p-4 space-y-3 border border-gray-100 dark:border-zinc-800">
                                <div class="flex items-center gap-2 border-b border-slate-200 dark:border-zinc-700 pb-2">
                                    <PhoneIcon class="h-4 w-4 text-indigo-500" />
                                    <p class="text-xs font-black text-slate-700 dark:text-slate-200 uppercase tracking-wider">Contact & Address</p>
                                </div>
                                <div><p class="text-[10px] text-slate-400">Email</p><p class="text-sm">{{ detailPanelApplicant?.email }}</p></div>
                                <div><p class="text-[10px] text-slate-400">Phone</p><p class="text-sm">{{ detailPanelApplicant?.phone }}</p></div>
                                <div><p class="text-[10px] text-slate-400">Address</p><p class="text-sm">{{ detailPanelApplicant?.street_address }}, {{ detailPanelApplicant?.city }}, {{ detailPanelApplicant?.state_province }} {{ detailPanelApplicant?.postal_zip_code }}</p></div>
                            </div>
                            <div class="bg-slate-50 dark:bg-zinc-800/60 rounded-3xl p-4 space-y-3 border border-gray-100 dark:border-zinc-800">
                                <div class="flex items-center gap-2 border-b border-slate-200 dark:border-zinc-700 pb-2">
                                    <AlertTriangle class="h-4 w-4 text-rose-500" />
                                    <p class="text-xs font-black text-slate-700 dark:text-slate-200 uppercase tracking-wider">Emergency Contact</p>
                                </div>
                                <div><p class="text-[10px] text-slate-400">Name</p><p class="text-sm">{{ detailPanelApplicant?.emergency_name || '—' }}</p></div>
                                <div><p class="text-[10px] text-slate-400">Relationship</p><p class="text-sm">{{ detailPanelApplicant?.emergency_relationship || '—' }}</p></div>
                                <div><p class="text-[10px] text-slate-400">Phone</p><p class="text-sm">{{ detailPanelApplicant?.emergency_phone || '—' }}</p></div>
                                <div><p class="text-[10px] text-slate-400">Address</p><p class="text-sm">{{ detailPanelApplicant?.emergency_address || '—' }}</p></div>
                            </div>
                        </div>

                        <!-- Government IDs -->
                        <div v-if="detailPanelTab === 'ids'" class="space-y-4">
                            <div class="bg-slate-50 dark:bg-zinc-800/60 rounded-3xl p-4 space-y-3 border border-gray-100 dark:border-zinc-800">
                                <div class="flex items-center gap-2 border-b border-slate-200 dark:border-zinc-700 pb-2">
                                    <CreditCard class="h-4 w-4 text-indigo-500" />
                                    <p class="text-xs font-black text-slate-700 dark:text-slate-200 uppercase tracking-wider">Government IDs</p>
                                </div>
                                <div><p class="text-[10px] text-slate-400">SSS Number</p><p class="text-sm">{{ detailPanelApplicant?.sss_number || '—' }}</p>
                                    <div v-if="detailPanelApplicant?.sss_file_url" class="mt-2"><button @click="openImagePreview(detailPanelApplicant.sss_file_url, 'SSS ID')" class="text-xs text-indigo-600 underline">View SSS ID</button></div>
                                </div>
                                <div><p class="text-[10px] text-slate-400">PhilHealth Number</p><p class="text-sm">{{ detailPanelApplicant?.philhealth_number || '—' }}</p>
                                    <div v-if="detailPanelApplicant?.philhealth_file_url" class="mt-2"><button @click="openImagePreview(detailPanelApplicant.philhealth_file_url, 'PhilHealth ID')" class="text-xs text-indigo-600 underline">View PhilHealth ID</button></div>
                                </div>
                                <div><p class="text-[10px] text-slate-400">Pag-IBIG Number</p><p class="text-sm">{{ detailPanelApplicant?.pagibig_number || '—' }}</p>
                                    <div v-if="detailPanelApplicant?.pagibig_file_url" class="mt-2"><button @click="openImagePreview(detailPanelApplicant.pagibig_file_url, 'Pag-IBIG ID')" class="text-xs text-indigo-600 underline">View Pag-IBIG ID</button></div>
                                </div>
                            </div>
                        </div>

                        <!-- Education -->
                        <div v-if="detailPanelTab === 'education'" class="space-y-4">
                            <div class="bg-slate-50 dark:bg-zinc-800/60 rounded-3xl p-4 space-y-3 border border-gray-100 dark:border-zinc-800">
                                <div class="flex items-center gap-2 border-b border-slate-200 dark:border-zinc-700 pb-2">
                                    <BookOpen class="h-4 w-4 text-indigo-500" />
                                    <p class="text-xs font-black text-slate-700 dark:text-slate-200 uppercase tracking-wider">Educational Background</p>
                                </div>
                                <div><p class="text-[10px] text-slate-400">Elementary</p><p class="text-sm">{{ detailPanelApplicant?.elementary_school || '—' }} {{ detailPanelApplicant?.elementary_year ? `(${detailPanelApplicant.elementary_year})` : '' }}</p></div>
                                <div><p class="text-[10px] text-slate-400">High School</p><p class="text-sm">{{ detailPanelApplicant?.high_school || '—' }} {{ detailPanelApplicant?.high_year ? `(${detailPanelApplicant.high_year})` : '' }}</p></div>
                                <div><p class="text-[10px] text-slate-400">College</p><p class="text-sm">{{ detailPanelApplicant?.college || '—' }} {{ detailPanelApplicant?.college_year ? `(${detailPanelApplicant.college_year})` : '' }}</p></div>
                                <div><p class="text-[10px] text-slate-400">Vocational</p><p class="text-sm">{{ detailPanelApplicant?.vocational || '—' }} {{ detailPanelApplicant?.vocational_year ? `(${detailPanelApplicant.vocational_year})` : '' }}</p></div>
                            </div>
                        </div>

                        <!-- Employment -->
                        <div v-if="detailPanelTab === 'employment'" class="space-y-4">
                            <div class="bg-slate-50 dark:bg-zinc-800/60 rounded-3xl p-4 space-y-3 border border-gray-100 dark:border-zinc-800">
                                <div class="flex items-center gap-2 border-b border-slate-200 dark:border-zinc-700 pb-2">
                                    <Briefcase class="h-4 w-4 text-indigo-500" />
                                    <p class="text-xs font-black text-slate-700 dark:text-slate-200 uppercase tracking-wider">Employment History</p>
                                </div>
                                <div v-if="detailPanelApplicant?.employment_records && detailPanelApplicant.employment_records.length">
                                    <div v-for="(rec, idx) in detailPanelApplicant.employment_records" :key="idx" class="mb-3 pb-2 border-b border-slate-200 last:border-0">
                                        <p class="text-sm font-bold">{{ rec.company }}</p>
                                        <p class="text-xs">Years: {{ rec.years }} | Salary: {{ rec.salary }} | Position: {{ rec.position }}</p>
                                        <p class="text-xs text-slate-500">Reason: {{ rec.reason }}</p>
                                    </div>
                                </div>
                                <div v-else-if="detailPanelApplicant?.previous_employment_company">
                                    <p><span class="text-[10px] text-slate-400">Company:</span> {{ detailPanelApplicant.previous_employment_company }}</p>
                                    <p><span class="text-[10px] text-slate-400">When:</span> {{ detailPanelApplicant.previous_employment_when }}</p>
                                    <p><span class="text-[10px] text-slate-400">Position:</span> {{ detailPanelApplicant.previous_employment_position }}</p>
                                    <p><span class="text-[10px] text-slate-400">Department:</span> {{ detailPanelApplicant.previous_employment_department }}</p>
                                </div>
                                <div v-else class="text-sm text-slate-500 italic">No employment history provided.</div>
                            </div>
                        </div>

                        <!-- Family -->
                        <div v-if="detailPanelTab === 'family'" class="space-y-4">
                            <div class="bg-slate-50 dark:bg-zinc-800/60 rounded-3xl p-4 space-y-3 border border-gray-100 dark:border-zinc-800">
                                <div class="flex items-center gap-2 border-b border-slate-200 dark:border-zinc-700 pb-2">
                                    <Heart class="h-4 w-4 text-rose-500" />
                                    <p class="text-xs font-black text-slate-700 dark:text-slate-200 uppercase tracking-wider">Family Background</p>
                                </div>
                                <div><p class="text-[10px] text-slate-400">Father</p><p class="text-sm">{{ detailPanelApplicant?.father_name || '—' }}<br/>{{ detailPanelApplicant?.father_address || '—' }}</p></div>
                                <div><p class="text-[10px] text-slate-400">Mother</p><p class="text-sm">{{ detailPanelApplicant?.mother_name || '—' }}<br/>{{ detailPanelApplicant?.mother_address || '—' }}</p></div>
                                <div><p class="text-[10px] text-slate-400">Spouse</p><p class="text-sm">{{ detailPanelApplicant?.spouse_name || '—' }} ({{ detailPanelApplicant?.spouse_occupation || '—' }})<br/>{{ detailPanelApplicant?.spouse_address || '—' }}</p></div>
                                <div><p class="text-[10px] text-slate-400">Number of Children</p><p class="text-sm">{{ detailPanelApplicant?.number_of_children || 0 }}</p></div>
                                <div v-if="detailPanelApplicant?.children && detailPanelApplicant.children.length">
                                    <p class="text-[10px] text-slate-400">Children</p>
                                    <div v-for="(child, i) in detailPanelApplicant.children" :key="i" class="text-sm">- {{ typeof child === 'object' ? child.name : child }}</div>
                                </div>
                            </div>
                            <div class="bg-slate-50 dark:bg-zinc-800/60 rounded-3xl p-4 space-y-3 border border-gray-100 dark:border-zinc-800">
                                <div class="flex items-center gap-2 border-b border-slate-200 dark:border-zinc-700 pb-2">
                                    <Globe class="h-4 w-4 text-indigo-500" />
                                    <p class="text-xs font-black text-slate-700 dark:text-slate-200 uppercase tracking-wider">Referral & Relations</p>
                                </div>
                                <div><p class="text-[10px] text-slate-400">Referred By</p><p class="text-sm">{{ detailPanelApplicant?.referred_by || '—' }}<br/>{{ detailPanelApplicant?.referred_by_address || '—' }}</p></div>
                                <div><p class="text-[10px] text-slate-400">Related Employees</p><p class="text-sm">{{ detailPanelApplicant?.related_employees || '—' }}</p></div>
                            </div>
                        </div>

                        <!-- Quick actions -->
                        <div v-if="canEdit" class="space-y-2 pt-2" @click.stop>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Quick Actions</p>
                            <button v-if="!detailPanelApplicant?.scheduled_at" @click="openScheduleModal(detailPanelApplicant)" class="w-full flex items-center justify-center gap-2 py-3 bg-gradient-to-r from-indigo-600 to-violet-600 hover:shadow-lg text-white rounded-2xl text-xs font-bold tracking-wide transition-all active:scale-95">
                                <Calendar class="h-4 w-4" /> Set Schedule
                            </button>
                            <div v-else class="grid grid-cols-2 gap-2">
                                <button @click="openPassModal(detailPanelApplicant)" class="flex items-center justify-center gap-2 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl text-xs font-bold tracking-wide transition-all active:scale-95">
                                    <CheckCircle class="h-4 w-4" /> Pass
                                </button>
                                <button @click="openFailModal(detailPanelApplicant)" class="flex items-center justify-center gap-2 py-3 bg-rose-600 hover:bg-rose-700 text-white rounded-2xl text-xs font-bold tracking-wide transition-all active:scale-95">
                                    <XCircle class="h-4 w-4" /> Fail
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Image Preview Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="imagePreview" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" @click.self="closeImagePreview">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl max-w-3xl w-full overflow-hidden border border-gray-100 dark:border-zinc-800">
                        <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white relative overflow-hidden">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <h3 class="relative text-lg font-black tracking-tight">{{ imagePreview.title }}</h3>
                            <button @click="closeImagePreview" class="relative p-1.5 rounded-xl hover:bg-white/20 transition"><X class="w-5 h-5 text-white" /></button>
                        </div>
                        <div class="p-4 flex justify-center bg-slate-100 dark:bg-zinc-950">
                            <img :src="imagePreview.url" :alt="imagePreview.title" class="max-w-full max-h-[70vh] object-contain rounded-2xl" />
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Schedule Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="isScheduleModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md" @click.self="closeModals">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden border border-gray-100 dark:border-zinc-800">
                        <div class="bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white relative overflow-hidden">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <h2 class="relative text-xl font-black uppercase tracking-tight">Schedule Interview</h2>
                            <p class="relative text-blue-100/90 text-xs mt-1">For {{ selectedApplicant?.name }}</p>
                        </div>
                        <div class="p-6 space-y-4">
                            <div><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Date & Time *</label><input type="datetime-local" v-model="scheduleForm.scheduled_at" class="w-full px-4 py-3 rounded-2xl bg-slate-50 dark:bg-zinc-800 border-none ring-1 ring-slate-200 dark:ring-zinc-700 focus:ring-2 focus:ring-indigo-500 outline-none transition" required /></div>
                            <div><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Interview Type *</label><select v-model="scheduleForm.interview_type" class="w-full px-4 py-3 rounded-2xl bg-slate-50 dark:bg-zinc-800 border-none ring-1 ring-slate-200 dark:ring-zinc-700 focus:ring-2 focus:ring-indigo-500 outline-none transition"><option value="">Select type</option><option value="phone">Phone Screen</option><option value="technical">Technical Interview</option><option value="behavioral">Behavioral Interview</option><option value="onsite">On-site Interview</option><option value="video">Video Conference</option></select></div>
                            <div class="grid grid-cols-2 gap-4"><div><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Duration (min)</label><select v-model="scheduleForm.duration" class="w-full px-4 py-3 rounded-2xl bg-slate-50 dark:bg-zinc-800 border-none ring-1 ring-slate-200 dark:ring-zinc-700 outline-none"><option value="15">15</option><option value="30">30</option><option value="45">45</option><option value="60">60</option></select></div><div><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Location</label><input type="text" v-model="scheduleForm.location" class="w-full px-4 py-3 rounded-2xl bg-slate-50 dark:bg-zinc-800 border-none ring-1 ring-slate-200 dark:ring-zinc-700 outline-none" placeholder="Office/Meeting link" /></div></div>
                            <div><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Interviewer(s)</label><input type="text" v-model="scheduleForm.interviewer" class="w-full px-4 py-3 rounded-2xl bg-slate-50 dark:bg-zinc-800 border-none ring-1 ring-slate-200 dark:ring-zinc-700 outline-none" placeholder="Name of interviewer(s)" /></div>
                            <div><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Notes</label><textarea v-model="scheduleForm.notes" rows="2" class="w-full px-4 py-3 rounded-2xl bg-slate-50 dark:bg-zinc-800 border-none ring-1 ring-slate-200 dark:ring-zinc-700 outline-none" placeholder="Additional instructions..."></textarea></div>
                            <div class="flex gap-3 pt-4"><button @click="closeModals" class="flex-1 py-3 text-slate-500 font-bold text-xs uppercase">Cancel</button><button @click="scheduleInterview" class="flex-1 py-3 bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-2xl text-xs font-black uppercase shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all active:scale-95">Confirm Schedule</button></div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Pass Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="isPassModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md" @click.self="closeModals">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden text-center border border-gray-100 dark:border-zinc-800">
                        <div class="bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 p-8 text-white relative overflow-hidden"><div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" /><div class="relative inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white/20 mb-4 animate-pop"><UserCheck class="h-8 w-8" /></div><h2 class="relative text-xl font-black uppercase tracking-tight">Pass Candidate</h2></div>
                        <div class="p-6"><p class="text-slate-600 dark:text-slate-300 mb-6">Are you sure you want to pass <strong>{{ selectedApplicant?.name }}</strong>? This will convert them into a trainee of Logistics department.</p><div class="flex gap-3"><button @click="closeModals" class="flex-1 py-3 text-slate-500 font-bold text-xs uppercase">Cancel</button><button @click="passApplicant" class="flex-1 py-3 bg-emerald-600 text-white rounded-2xl text-xs font-black uppercase shadow-lg hover:bg-emerald-700 transition-all active:scale-95">Confirm Pass</button></div></div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Fail Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="isFailModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md" @click.self="closeModals">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden border border-gray-100 dark:border-zinc-800">
                        <div class="bg-gradient-to-br from-rose-600 via-red-600 to-orange-600 p-6 text-white relative overflow-hidden"><div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" /><h2 class="relative text-xl font-black uppercase tracking-tight">Fail Candidate</h2><p class="relative text-red-100 text-xs mt-1">Provide reason for failure</p></div>
                        <div class="p-6 space-y-4"><div><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Reason *</label><textarea v-model="failReason" rows="3" class="w-full px-4 py-3 rounded-2xl bg-slate-50 dark:bg-zinc-800 border-none ring-1 ring-slate-200 dark:ring-zinc-700 focus:ring-2 focus:ring-red-500 outline-none" placeholder="e.g., Insufficient technical skills..."></textarea></div><div class="flex gap-3"><button @click="closeModals" class="flex-1 py-3 text-slate-500 font-bold text-xs uppercase">Cancel</button><button @click="failApplicant" :disabled="!failReason.trim()" class="flex-1 py-3 bg-rose-600 text-white rounded-2xl text-xs font-black uppercase shadow-lg hover:bg-rose-700 transition-all disabled:opacity-50 active:scale-95">Confirm Fail</button></div></div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Pass to Other Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="isPassToOtherModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md" @click.self="closeModals">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden border border-gray-100 dark:border-zinc-800">
                        <div class="bg-gradient-to-br from-violet-600 via-purple-600 to-fuchsia-600 p-6 text-white relative overflow-hidden"><div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" /><h2 class="relative text-xl font-black uppercase tracking-tight">Pass to Another Module</h2><p class="relative text-purple-100 text-xs mt-1">Select department for further interview</p></div>
                        <div class="p-6 space-y-4"><div><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Select Module *</label><select v-model="otherModule" class="w-full px-4 py-3 rounded-2xl bg-slate-50 dark:bg-zinc-800 border-none ring-1 ring-slate-200 dark:ring-zinc-700 focus:ring-2 focus:ring-purple-500 outline-none"><option value="" disabled>Choose department</option><option v-for="mod in modules" :key="mod.value" :value="mod.value">{{ mod.label }}</option></select></div><div class="flex gap-3"><button @click="closeModals" class="flex-1 py-3 text-slate-500 font-bold text-xs uppercase">Cancel</button><button @click="passToOtherModule" :disabled="!otherModule" class="flex-1 py-3 bg-purple-600 text-white rounded-2xl text-xs font-black uppercase shadow-lg hover:bg-purple-700 transition-all disabled:opacity-50 active:scale-95">Confirm</button></div></div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Calendar, Clock, Video, MapPin, XCircle, CheckCircle, ExternalLink,
    PlayCircle, CalendarClock, Eye, Edit3, User, Mail, Phone, Briefcase,
    Send, AlertTriangle, X, UserCheck, UserMinus, ArrowRight, MessageSquare,
    Heart, BookOpen, Factory, CreditCard, Phone as PhoneIcon, MapPin as MapPinIcon,
    User as UserIcon, Award, ShieldCheck, Pencil, Save, Loader2, Trash2, Upload, Image as ImageIcon,
    Globe, ChevronRight
} from 'lucide-vue-next';

const props = defineProps({
    applicants: {
        type: Array,
        default: () => []
    },
    permissions: {
        type: Object,
        default: () => ({})
    }
});

const canEdit = computed(() => props.permissions?.interview === 'edit');

// Toast notification
const showToast = ref(false);
const toastMessage = ref('');
const toastType = ref('success');
const triggerToast = (msg, type = 'success') => {
    toastMessage.value = msg;
    toastType.value = type;
    showToast.value = true;
    setTimeout(() => { showToast.value = false; }, 4000);
};

// Flash messages from server
const page = usePage();
if (page.props.flash?.message) triggerToast(page.props.flash.message);

// Modal states
const isScheduleModalOpen = ref(false);
const isPassModalOpen = ref(false);
const isFailModalOpen = ref(false);
const isPassToOtherModalOpen = ref(false);
const selectedApplicant = ref(null);

// Detail side panel
const detailPanelOpen = ref(false);
const detailPanelApplicant = ref(null);
const detailPanelTab = ref('personal');

// Image preview modal
const imagePreview = ref(null);
const openImagePreview = (url, title) => { imagePreview.value = { url, title }; };
const closeImagePreview = () => { imagePreview.value = null; };

// Form for scheduling
const scheduleForm = ref({
    scheduled_at: '',
    interview_type: '',
    duration: 45,
    interviewer: '',
    location: '',
    notes: ''
});

// Form for failing
const failReason = ref('');

// Form for passing to other module
const otherModule = ref('');

// Module options for "pass to other"
const modules = [
    { value: 'HRM', label: 'Human Resource' },
    { value: 'CRM', label: 'Customer Relationship' },
    { value: 'ECO', label: 'E-Commerce' },
    { value: 'SCM', label: 'Supply Chain' },
    { value: 'MAN', label: 'Manufacturing' },
    { value: 'PROJ', label: 'Project Management' },
    { value: 'FIN', label: 'Finance' },
    { value: 'IT', label: 'Information Technology' }
];

// Helper functions
const getInitials = (name) => name ? name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) : '?';
const formatDate = (date) => date ? new Date(date).toLocaleDateString() : 'N/A';
const formatDateTime = (date) => date ? new Date(date).toLocaleString() : 'N/A';
const formatDateFull = (date) => date ? new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
const getInterviewTypeIcon = (type) => {
    const t = type?.toLowerCase();
    if (t === 'phone') return Phone;
    if (t === 'video') return Video;
    if (t === 'onsite') return MapPin;
    return Calendar;
};
const getInterviewTypeLabel = (type) => {
    const map = {
        phone: 'Phone Screen', video: 'Video Call',
        onsite: 'On-site', technical: 'Technical', behavioral: 'Behavioral'
    };
    return map[type?.toLowerCase()] || type || 'Interview';
};

// Open modals
const openScheduleModal = (applicant) => {
    if (!canEdit.value) { triggerToast('No permission to schedule interviews.', 'error'); return; }
    selectedApplicant.value = applicant;
    scheduleForm.value = {
        scheduled_at: applicant.scheduled_at ? new Date(applicant.scheduled_at).toISOString().slice(0, 16) : '',
        interview_type: applicant.interview_type || '',
        duration: applicant.duration || 45,
        interviewer: applicant.interviewer || '',
        location: applicant.location || '',
        notes: applicant.notes || ''
    };
    isScheduleModalOpen.value = true;
};

const openPassModal = (applicant) => {
    if (!canEdit.value) { triggerToast('No permission.', 'error'); return; }
    selectedApplicant.value = applicant;
    isPassModalOpen.value = true;
};

const openFailModal = (applicant) => {
    if (!canEdit.value) { triggerToast('No permission.', 'error'); return; }
    selectedApplicant.value = applicant;
    failReason.value = '';
    isFailModalOpen.value = true;
};

const openPassToOtherModal = (applicant) => {
    if (!canEdit.value) { triggerToast('No permission.', 'error'); return; }
    selectedApplicant.value = applicant;
    otherModule.value = '';
    isPassToOtherModalOpen.value = true;
};

const openDetailPanel = (applicant) => {
    detailPanelApplicant.value = applicant;
    detailPanelOpen.value = true;
    detailPanelTab.value = 'personal';
};

const closeModals = () => {
    isScheduleModalOpen.value = false;
    isPassModalOpen.value = false;
    isFailModalOpen.value = false;
    isPassToOtherModalOpen.value = false;
    selectedApplicant.value = null;
    scheduleForm.value = { scheduled_at: '', interview_type: '', duration: 45, interviewer: '', location: '', notes: '' };
    failReason.value = '';
    otherModule.value = '';
};

const closeDetailPanel = () => {
    detailPanelOpen.value = false;
    setTimeout(() => { detailPanelApplicant.value = null; }, 350);
};

// API calls
const scheduleInterview = () => {
    if (!scheduleForm.value.scheduled_at || !scheduleForm.value.interview_type) {
        triggerToast('Please fill in all required fields.', 'error');
        return;
    }
    router.post(route('logistics.interview.schedule', selectedApplicant.value.id), scheduleForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast('Interview scheduled successfully.');
            closeModals();
        },
        onError: (errors) => {
            triggerToast(Object.values(errors)[0] || 'Failed to schedule interview.', 'error');
        }
    });
};

const passApplicant = () => {
    router.post(route('logistics.interview.pass', selectedApplicant.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast(`${selectedApplicant.value.name} passed interview and became trainee.`);
            closeModals();
        },
        onError: (errors) => {
            triggerToast(Object.values(errors)[0] || 'Failed to process.', 'error');
        }
    });
};

const failApplicant = () => {
    if (!failReason.value.trim()) {
        triggerToast('Please provide a reason for failure.', 'error');
        return;
    }
    router.post(route('logistics.interview.fail', selectedApplicant.value.id), {
        reason: failReason.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast(`${selectedApplicant.value.name} failed interview.`);
            closeModals();
        },
        onError: (errors) => {
            triggerToast(Object.values(errors)[0] || 'Failed to process.', 'error');
        }
    });
};

const passToOtherModule = () => {
    if (!otherModule.value) {
        triggerToast('Please select a module to pass to.', 'error');
        return;
    }
    router.post(route('logistics.interview.pass-to-other', selectedApplicant.value.id), {
        module: otherModule.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast(`Applicant passed to ${otherModule.value} for interview.`);
            closeModals();
        },
        onError: (errors) => {
            triggerToast(Object.values(errors)[0] || 'Failed to pass to other module.', 'error');
        }
    });
};
</script>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
@keyframes bounceSoft { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-float-delayed { animation: float 8s ease-in-out 1.2s infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.animate-bounce-soft { animation: bounceSoft 2.4s ease-in-out infinite; }
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active, .modal-leave-active { transition: all 0.3s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: scale(0.95) translateY(12px); }
.toast-enter-active, .toast-leave-active { transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(-16px); }
.panel-slide-enter-active { transition: transform 0.35s cubic-bezier(0.32, 0.72, 0, 1); }
.panel-slide-leave-active { transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1); }
.panel-slide-enter-from, .panel-slide-leave-to { transform: translateX(100%); }
.panel-backdrop-enter-active, .panel-backdrop-leave-active { transition: opacity 0.3s ease; }
.panel-backdrop-enter-from, .panel-backdrop-leave-to { opacity: 0; }
::-webkit-scrollbar { width: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 999px; }
::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
</style>
