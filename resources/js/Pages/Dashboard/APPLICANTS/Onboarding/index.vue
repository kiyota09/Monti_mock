<template>
    <AuthenticatedLayout>
        <Head title="My Onboarding" />
        <div class="h-full flex flex-col">
            <!-- ============ ACTIVE ONBOARDING ============ -->
            <template v-if="active && onboarding">
                <!-- HEADER -->
                <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shrink-0">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                            Welcome, <span class="text-blue-600">{{ firstName }}</span>
                        </h1>
                        <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">{{ onboarding.position }} · {{ onboarding.department }}</p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-flex px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wide"
                            :class="onboardingStatusBadge(onboarding.status)">
                            {{ onboarding.status }}
                        </span>
                        <span class="inline-flex px-3 py-1.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                            {{ onboarding.progress }}% complete
                        </span>
                    </div>
                </div>

                <!-- PER-JOB ONBOARDING TABLE -->
                <div v-if="(onboardings || []).length" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden mb-4 shrink-0">
                    <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">My Onboardings per Job Applied</h3>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">One row per job application in onboarding. Click a row to open its requirements.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-left text-[10px] uppercase tracking-wider text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-slate-700">
                                    <th class="px-4 py-2.5 font-bold">Job Applied</th>
                                    <th class="px-4 py-2.5 font-bold">Position</th>
                                    <th class="px-4 py-2.5 font-bold">Department</th>
                                    <th class="px-4 py-2.5 font-bold">Status</th>
                                    <th class="px-4 py-2.5 font-bold">Progress</th>
                                    <th class="px-4 py-2.5 font-bold">Start Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                <tr v-for="row in (onboardings || [])" :key="row.id" @click="openRowModal(row)"
                                    :class="['cursor-pointer transition hover:bg-blue-50/50 dark:hover:bg-blue-900/10', row.id === (openRowId ?? selectedId) ? 'bg-blue-50/70 dark:bg-blue-900/20' : '']">
                                    <td class="px-4 py-2.5 font-bold text-slate-900 dark:text-white">{{ row.job }}</td>
                                    <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ row.position }}</td>
                                    <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ row.department }}</td>
                                    <td class="px-4 py-2.5">
                                        <span :class="['inline-flex px-2 py-0.5 rounded-full text-[9px] font-bold', onboardingStatusBadge(row.status)]">{{ row.status }}</span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-2">
                                            <div class="w-16 h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                                <div class="h-full bg-blue-600 rounded-full" :style="{ width: row.progress + '%' }"></div>
                                            </div>
                                            <span class="text-[10px] font-bold text-slate-600 dark:text-slate-300">{{ row.progress }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ formatDate(row.start_date) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </template>

            <!-- ============ NO ACTIVE ONBOARDING ============ -->
            <template v-else>
                <div class="flex-1 flex items-center justify-center p-8">
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-12 text-center max-w-md">
                        <div class="w-16 h-16 rounded-2xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center mx-auto mb-4">
                            <ClipboardDocumentCheckIcon class="w-7 h-7 text-blue-600 dark:text-blue-400" />
                        </div>
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-2">No Active Onboarding</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mb-6">You don't currently have any application in the onboarding stage. Once HR advances your application, you will be able to submit your requirements here.</p>
                        <a href="/applicant/applications" class="inline-flex items-center gap-1.5 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-lg shadow-blue-500/30 active:scale-95">
                            View My Applications
                        </a>
                    </div>
                </div>
            </template>
        </div>

        <!-- ============ ROW-CLICK DETAIL MODAL ============ -->
        <div v-if="openRowId !== null" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeRowModal">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden shadow-2xl">
                <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 flex items-start justify-between gap-3 shrink-0">
                    <div v-if="rowModal">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ rowModal.job || rowModal.position }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ rowModal.position }} · {{ rowModal.department }}</p>
                    </div>
                    <div v-else>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Onboarding Details</h3>
                    </div>
                    <button @click="closeRowModal" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl text-slate-400 hover:text-slate-600 transition-all shrink-0">✕</button>
                </div>

                <div v-if="rowLoading" class="p-10 text-center">
                    <div class="w-8 h-8 rounded-full border-4 border-slate-300 border-t-blue-600 animate-spin mx-auto mb-3"></div>
                    <p class="text-xs font-semibold text-slate-500">Loading onboarding details...</p>
                </div>

                <div v-else-if="rowModal" class="p-5 space-y-5 overflow-y-auto">
                    <!-- Progress -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-bold text-slate-900 dark:text-white">Onboarding Progress</span>
                            <span class="inline-flex items-center gap-2">
                                <span :class="['inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold', onboardingStatusBadge(rowModal.status)]">{{ rowModal.status }}</span>
                                <span class="text-sm font-bold text-blue-600 dark:text-blue-400">{{ rowModal.progress }}%</span>
                            </span>
                        </div>
                        <div class="w-full h-3 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-600 rounded-full transition-all duration-500" :style="{ width: rowModal.progress + '%' }"></div>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">Complete your required items and wait for HR to verify each one. You will be notified when everything is done.</p>
                    </div>

                    <!-- Employment Details -->
                    <div class="rounded-2xl border border-slate-100 dark:border-slate-700 overflow-hidden">
                        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white">Employment Details</h4>
                        </div>
                        <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-x-6">
                            <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-2"><span class="text-[11px] text-slate-400">Position</span><span class="text-xs font-semibold text-slate-900 dark:text-white text-right">{{ rowModal.position || '—' }}</span></div>
                            <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-2"><span class="text-[11px] text-slate-400">Department</span><span class="text-xs font-semibold text-slate-900 dark:text-white text-right">{{ rowModal.department || '—' }}</span></div>
                            <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-2"><span class="text-[11px] text-slate-400">Onboarding Template</span><span class="text-xs font-semibold text-slate-900 dark:text-white text-right">{{ rowModal.template || '—' }}</span></div>
                            <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-2"><span class="text-[11px] text-slate-400">Start Date</span><span class="text-xs font-semibold text-slate-900 dark:text-white text-right">{{ formatDate(rowModal.start_date) }}</span></div>
                            <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-2"><span class="text-[11px] text-slate-400">Expected Start</span><span class="text-xs font-semibold text-slate-900 dark:text-white text-right">{{ formatDate(rowModal.expected_start_date) }}</span></div>
                            <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-2"><span class="text-[11px] text-slate-400">Work Location</span><span class="text-xs font-semibold text-slate-900 dark:text-white text-right">{{ rowModal.work_location || '—' }}</span></div>
                            <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-2"><span class="text-[11px] text-slate-400">Schedule</span><span class="text-xs font-semibold text-slate-900 dark:text-white text-right">{{ rowModal.work_schedule || '—' }}</span></div>
                            <div class="flex justify-between gap-3 py-2"><span class="text-[11px] text-slate-400">Assigned Manager</span><span class="text-xs font-semibold text-slate-900 dark:text-white text-right">{{ rowModal.assigned_manager || 'To be assigned' }}</span></div>
                        </div>
                    </div>

                    <!-- Activities -->
                    <div class="rounded-2xl border border-slate-100 dark:border-slate-700 overflow-hidden">
                        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white">Orientation & Training</h4>
                        </div>
                        <div class="p-4">
                            <div v-if="!(rowModal.activities || []).length" class="text-center py-4">
                                <p class="text-xs text-slate-500 dark:text-slate-400">No activities scheduled yet. HR will notify you once schedules are set.</p>
                            </div>
                            <div v-else class="space-y-2">
                                <div v-for="a in rowModal.activities" :key="a.id" class="p-3 rounded-xl border border-slate-200 dark:border-slate-700">
                                    <div class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-2 flex-wrap">
                                        {{ a.title }}
                                        <span v-if="a.is_required" class="bg-rose-100 text-rose-700 px-1.5 py-0.5 rounded text-[9px] font-bold">REQUIRED</span>
                                        <span :class="['px-2 py-0.5 rounded-full text-[9px] font-bold', activityStatusBadge(a.status)]">{{ a.status }}</span>
                                    </div>
                                    <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">
                                        {{ a.type }}
                                        <template v-if="a.schedule_date"> · {{ formatDate(a.schedule_date) }}<template v-if="a.start_time"> · {{ a.start_time }}<template v-if="a.end_time"> - {{ a.end_time }}</template></template></template>
                                        <template v-if="a.method"> · {{ a.method }}</template>
                                        <template v-if="a.location"> · {{ a.location }}</template>
                                        <template v-if="a.facilitator"> · Facilitator: {{ a.facilitator }}</template>
                                    </div>
                                    <div v-if="a.schedule_date" class="mt-1.5 inline-flex items-center gap-1 text-[9px] font-semibold px-2 py-0.5 rounded"
                                        :class="a.attendance_status === 'Confirmed' ? 'text-emerald-700 bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-400' : 'text-blue-700 bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400'">
                                        <CheckIcon v-if="a.attendance_status === 'Confirmed'" class="w-3 h-3" />
                                        <BellIcon v-else class="w-3 h-3" />
                                        <template v-if="a.attendance_status === 'Confirmed'">Attendance confirmed</template>
                                        <template v-else>
                                            Will you attend?
                                            <button @click="confirmAttendance(a)" class="underline font-bold hover:text-blue-700 dark:hover:text-blue-300">Confirm</button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Requirements -->
                    <div class="rounded-2xl border border-slate-100 dark:border-slate-700 overflow-hidden">
                        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white">My Onboarding Requirements</h4>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">Submit each requested item. You can resubmit anytime until everything is verified.</p>
                        </div>
                        <div class="p-4 space-y-5">
                            <div v-for="(items, section) in (rowModal.sections || {})" :key="section">
                                <h5 class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">{{ section }}</h5>
                                <div class="space-y-2">
                                    <div v-for="item in items" :key="item.id" class="p-3 rounded-xl border border-slate-200 dark:border-slate-700">
                                        <div class="text-xs font-semibold text-slate-900 dark:text-white flex items-center gap-2 flex-wrap">
                                            {{ item.name }}
                                            <span :class="item.is_required ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600'" class="px-1.5 py-0.5 rounded text-[9px] font-bold">{{ item.is_required ? 'REQUIRED' : 'OPTIONAL' }}</span>
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">{{ inputTypeLabels[normType(item)] }}</span>
                                            <span :class="['px-2 py-0.5 rounded-full text-[9px] font-bold', itemStatusBadge(item.status)]">{{ itemStatusLabel(item.status) }}</span>
                                        </div>
                                        <p v-if="item.description" class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">{{ item.description }}</p>
                                        <div v-if="item.latest_submission" class="mt-2 flex flex-wrap items-center gap-2">
                                            <a v-if="item.latest_submission.file_url" :href="item.latest_submission.file_url" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-semibold text-blue-700 bg-blue-50 px-2 py-1 rounded-lg border border-blue-200">{{ item.latest_submission.original_name }}</a>
                                            <span v-if="item.latest_submission.value" class="text-[10px] text-slate-600 bg-slate-50 px-2 py-1 rounded-lg border border-slate-200">{{ displayValue(item.latest_submission.value) }}</span>
                                            <span v-if="item.latest_submission.submitted_at" class="text-[9px] text-slate-400">Submitted {{ formatDateTime(item.latest_submission.submitted_at) }}</span>
                                        </div>
                                        <div v-if="item.status === 'Verified' || item.status === 'Completed'" class="mt-2 inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-1 rounded-lg">Verified by HR</div>

                                        <!-- Submission inputs -->
                                        <div v-if="canSubmit(item)" class="mt-3">
                                            <div v-if="normType(item) === 'file'" class="flex flex-col gap-2">
                                                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                                                    <label class="flex-1 flex items-center gap-2 cursor-pointer">
                                                        <input type="file" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp" :ref="(el) => { if (el) pickedFileInputs[item.id] = el; }" @change="handleFilePick(item, $event)" class="hidden" />
                                                        <span class="inline-flex items-center gap-1.5 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-[10px] font-bold transition shadow-sm active:scale-95">
                                                            <ArrowUpTrayIcon class="w-3.5 h-3.5" /> {{ pickedFiles[item.id]?.name || (item.status === 'Rejected' ? 'Resubmit Document' : 'Choose Document') }}
                                                        </span>
                                                    </label>
                                                    <input v-model="itemNotes[item.id]" type="text" placeholder="Optional note..." class="flex-1 px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-[10px] focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition" />
                                                </div>
                                                <div v-if="pickedFiles[item.id]" class="flex flex-wrap items-center gap-2 rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50/60 dark:bg-blue-900/20 px-3 py-2">
                                                    <span class="text-[10px] font-semibold text-slate-700 dark:text-slate-200 break-all">{{ pickedFiles[item.id].name }} <span class="font-normal text-slate-400">({{ formatFileSize(pickedFiles[item.id].size) }})</span></span>
                                                    <span class="flex-1"></span>
                                                    <button @click="clearPickedFile(item)" class="px-2.5 py-1.5 text-[10px] font-bold text-slate-500 hover:text-rose-600 transition">Remove</button>
                                                    <button @click="submitPickedFile(item)" class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-[10px] font-bold transition shadow-sm active:scale-95">
                                                        <PaperAirplaneIcon class="w-3.5 h-3.5" /> Submit
                                                    </button>
                                                </div>
                                            </div>
                                            <div v-else-if="normType(item) === 'checkbox'" class="flex flex-col gap-2">
                                                <div class="flex flex-wrap items-center gap-3">
                                                    <label class="inline-flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-300 cursor-pointer">
                                                        <input v-model="itemChecks[item.id]" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                                        I confirm / agree
                                                    </label>
                                                    <button @click="submitCheckbox(item)" class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-[10px] font-bold transition shadow-sm active:scale-95">
                                                        <CheckIcon class="w-3.5 h-3.5" /> Submit Confirmation
                                                    </button>
                                                </div>
                                                <label class="inline-flex items-center gap-1.5 text-[10px] font-semibold text-slate-500 hover:text-blue-600 cursor-pointer transition w-fit">
                                                    <PaperClipIcon class="w-3.5 h-3.5" />
                                                    <input type="file" :ref="(el) => { if (el) valueFileInputs[item.id] = el; }" @change="handleValueFileSelect(item, $event)" class="hidden" />
                                                    {{ itemFiles[item.id]?.name || 'Attach supporting file (optional)' }}
                                                    <span v-if="itemFiles[item.id]" @click.prevent.stop="clearValueFile(item)" class="text-rose-500 font-bold">✕</span>
                                                </label>
                                            </div>
                                            <div v-else-if="normType(item) === 'select'" class="flex flex-col gap-2">
                                                <div class="flex flex-col sm:flex-row gap-2">
                                                    <select v-model="itemValues[item.id]" class="flex-1 px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-[10px] focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition">
                                                        <option value="">Select an option</option>
                                                        <option v-for="opt in (item.options || [])" :key="opt" :value="opt">{{ opt }}</option>
                                                    </select>
                                                    <button @click="submitValue(item)" class="inline-flex items-center justify-center gap-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-[10px] font-bold transition shadow-sm active:scale-95">
                                                        <PaperAirplaneIcon class="w-3.5 h-3.5" /> Submit
                                                    </button>
                                                </div>
                                                <label class="inline-flex items-center gap-1.5 text-[10px] font-semibold text-slate-500 hover:text-blue-600 cursor-pointer transition w-fit">
                                                    <PaperClipIcon class="w-3.5 h-3.5" />
                                                    <input type="file" :ref="(el) => { if (el) valueFileInputs[item.id] = el; }" @change="handleValueFileSelect(item, $event)" class="hidden" />
                                                    {{ itemFiles[item.id]?.name || 'Attach supporting file (optional)' }}
                                                    <span v-if="itemFiles[item.id]" @click.prevent.stop="clearValueFile(item)" class="text-rose-500 font-bold">✕</span>
                                                </label>
                                            </div>
                                            <div v-else class="flex flex-col gap-2">
                                                <div class="flex flex-col sm:flex-row gap-2">
                                                    <input v-model="itemValues[item.id]" :type="normType(item) === 'date' ? 'date' : 'text'" placeholder="Type your answer..." class="flex-1 px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-[10px] focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition" />
                                                    <button @click="submitValue(item)" class="inline-flex items-center justify-center gap-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-[10px] font-bold transition shadow-sm active:scale-95">
                                                        <PaperAirplaneIcon class="w-3.5 h-3.5" /> Submit
                                                    </button>
                                                </div>
                                                <label class="inline-flex items-center gap-1.5 text-[10px] font-semibold text-slate-500 hover:text-blue-600 cursor-pointer transition w-fit">
                                                    <PaperClipIcon class="w-3.5 h-3.5" />
                                                    <input type="file" :ref="(el) => { if (el) valueFileInputs[item.id] = el; }" @change="handleValueFileSelect(item, $event)" class="hidden" />
                                                    {{ itemFiles[item.id]?.name || 'Attach supporting file (optional)' }}
                                                    <span v-if="itemFiles[item.id]" @click.prevent.stop="clearValueFile(item)" class="text-rose-500 font-bold">✕</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="!Object.keys(rowModal.sections || {}).length" class="text-center text-xs text-slate-400 py-4">No requirements assigned yet.</div>
                        </div>
                    </div>
                </div>

                <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700 flex items-center justify-end gap-3 shrink-0">
                    <button @click="closeRowModal" class="px-5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold transition-all">Close</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import {
    CheckIcon, ArrowUpTrayIcon, DocumentTextIcon, CalendarIcon, ClipboardDocumentCheckIcon,
    PaperAirplaneIcon, BellIcon, PaperClipIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    active: { type: Boolean, default: false },
    onboarding: { type: Object, default: null },
    onboardings: { type: Array, default: () => [] },
    selectedId: { type: [Number, String], default: null },
});

const rowModal = ref(null);
const rowLoading = ref(false);
const openRowId = ref(null);

const openRowModal = async (row) => {
    openRowId.value = row.id;
    rowModal.value = null;
    rowLoading.value = true;
    try {
        const res = await axios.get(route('applicant.onboarding.show', row.id));
        rowModal.value = res.data?.data ?? null;
    } catch (e) {
        toast.error("Could not load this onboarding record.");
        openRowId.value = null;
    } finally {
        rowLoading.value = false;
    }
};

const closeRowModal = () => {
    rowModal.value = null;
    openRowId.value = null;
};

// Re-pull the open record so the modal reflects a just-made submission.
const refreshRowModal = async () => {
    if (!openRowId.value) return;
    try {
        const res = await axios.get(route('applicant.onboarding.show', openRowId.value));
        rowModal.value = res.data?.data ?? null;
    } catch (e) { /* keep stale data on refresh failure */ }
};

const page = usePage();
const itemNotes = ref({});
const itemValues = ref({});
const itemChecks = ref({});
// Optional supporting file per requirement (text/date/select/checkbox can
// all carry an attachment — the backend accepts an optional file for every
// input type, mirroring the HRM template builder's input_type + options).
const itemFiles = ref({});
const valueFileInputs = {};
const inputTypeLabels = computed(() => ({
    'text': 'Text',
    'file': 'Document Upload',
    'date': 'Date',
    'checkbox': 'Checkbox',
    'select': 'Select Dropdown',
}));

// Normalise input_type case-insensitively + legacy labels, so the
// template builder (text/file/date/checkbox/select) always matches.
const normType = (item) => {
    const raw = String(item?.input_type || 'text').trim().toLowerCase();
    if (['file', 'document upload', 'document', 'upload'].includes(raw)) return 'file';
    if (['date', 'datetime', 'time'].includes(raw)) return 'date';
    if (['checkbox', 'acknowledgement', 'acknowledgment', 'boolean'].includes(raw)) return 'checkbox';
    if (['select', 'form', 'dropdown', 'selection'].includes(raw)) return 'select';
    return 'text';
};

watch(() => page.props.flash, (flash) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
}, { immediate: true });

watch(() => page.props.errors, (errors) => {
    if (Object.keys(errors).length > 0) {
        toast.error(String(Object.values(errors)[0]));
    }
}, { deep: true });

const initials = computed(() => {
    if (!props.onboarding) return 'A';
    const parts = props.onboarding.name.trim().split(/\s+/);
    const first = parts[0] ? parts[0][0] : '';
    const last = parts.length > 1 ? parts[parts.length - 1][0] : '';
    return (first + last).toUpperCase();
});

const firstName = computed(() => {
    if (!props.onboarding) return '';
    const parts = props.onboarding.name.trim().split(/\s+/);
    return parts[0] || '';
});

const canSubmit = (item) =>
    item.status !== 'Verified' && item.status !== 'Completed' && !item.is_task;

// Online activities show the link and hide the location; onsite activities
// show the location and hide the link. Without a stored method, a present
// link with no real location still counts as online.
const isOnlineActivity = (activity) => String(activity?.method || '').toLowerCase() === 'online';
const isOnsiteActivity = (activity) => String(activity?.method || '').toLowerCase() === 'onsite';
const activityLocationMeaningful = (value) => {
    const s = String(value ?? '').trim();
    return !!s && s.toLowerCase() !== 'n/a' && s !== '-';
};
const showActivityLocation = (activity) => {
    if (isOnlineActivity(activity)) return false;
    return activityLocationMeaningful(activity?.location);
};

const isOverdue = (item) => {
    if (!item.due_date) return false;
    return new Date(item.due_date) < new Date(new Date().toISOString().slice(0, 10));
};

const handleFilePick = (item, event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    // Mirror the server rules (pdf/jpg/jpeg/png/gif/webp, 5MB). Check the
    // real MIME type too — a renamed file passes the extension test but
    // is rejected by the server.
    if (!/\.(pdf|jpe?g|png|gif|webp)$/i.test(file.name) || !['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) {
        toast.error('Only PDF, JPG, PNG, GIF or WEBP files are allowed.');
        event.target.value = '';
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        toast.error('File must be 5MB or less.');
        event.target.value = '';
        return;
    }
    pickedFiles.value[item.id] = file;
};

// Staged document per requirement (pick first, submit on button click —
// nothing uploads until Submit is pressed).
const pickedFiles = ref({});
const pickedFileInputs = {};

const clearPickedFile = (item) => {
    pickedFiles.value[item.id] = null;
    const input = pickedFileInputs[item.id];
    if (input) input.value = '';
};

const formatFileSize = (bytes) => {
    if (!bytes && bytes !== 0) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
};

const submitPickedFile = (item) => {
    const file = pickedFiles.value[item.id];
    if (!file) {
        toast.error('Please choose a document first.');
        return;
    }

    const formData = new FormData();
    formData.append('file', file);
    if (itemNotes.value[item.id]) {
        formData.append('notes', itemNotes.value[item.id]);
    }

    router.post(
        route('applicant.onboarding.items.submit', { item: item.id }),
        formData,
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Document uploaded. HR will review it soon.');
                itemNotes.value[item.id] = '';
                clearPickedFile(item);
                refreshRowModal();
            },
            onError: (errors) => {
                const first = Object.values(errors || {})[0];
                toast.error(typeof first === 'string' ? first : 'Upload failed. Check the file format and size.');
            },
        },
    );
};

const submitCheckbox = (item) => {
    if (!itemChecks.value[item.id]) {
        toast.error('Please tick the checkbox to confirm first.');
        return;
    }
    submitValue(item, true);
};

// Optional attachment picker shared by the non-file input branches.
const handleValueFileSelect = (item, event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
        toast.error('Attachment must be 5MB or less.');
        event.target.value = '';
        return;
    }
    itemFiles.value[item.id] = file;
};

const clearValueFile = (item) => {
    itemFiles.value[item.id] = null;
    const input = valueFileInputs[item.id];
    if (input) input.value = '';
};

const submitValue = (item, value) => {
    let val;
    if (value !== undefined) {
        val = value === true ? '1' : value;
    } else {
        const raw = itemValues.value[item.id];
        if (raw === undefined || raw === '' || raw === null) {
            toast.error('Please provide a value first.');
            return;
        }
        // Convert value based on input type
        const kind = normType(item);
        if (kind === 'checkbox') {
            val = '1';
        } else if (kind === 'date') {
            // Date input already returns a string in YYYY-MM-DD format
            val = raw;
        } else if (kind === 'select') {
            // Select value is already a string; enforce defined options client-side too.
            if (item.options?.length && !item.options.includes(raw)) {
                toast.error('Please select a valid option.');
                return;
            }
            val = raw;
        } else {
            // Default for text and others
            val = raw;
        }
    }

    // FormData so an optional supporting file rides along with the value.
    const formData = new FormData();
    formData.append('value', val);
    if (itemFiles.value[item.id]) {
        formData.append('file', itemFiles.value[item.id]);
    }

    router.post(
        route('applicant.onboarding.items.submit', { item: item.id }),
        formData,
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Submission saved. HR will review it soon.');
                itemValues.value[item.id] = '';
                clearValueFile(item);
                refreshRowModal();
            },
            onError: () => toast.error('Failed to submit. Please try again.'),
        },
    );
};

const confirmAttendance = (activity) => {
    router.post(route('applicant.onboarding.activities.attend', { activity: activity.id }), {}, {
        preserveScroll: true,
        onSuccess: () => { toast.success('Attendance confirmed. See you there!'); refreshRowModal(); },
        onError: () => toast.error('Failed to confirm attendance. Please try again.'),
    });
};

const displayValue = (value) => {
    if (value === true || value === '1' || value === 1) return 'Yes / Confirmed';
    if (value === false || value === '0' || value === 0) return 'No';
    return String(value);
};

const itemDone = (status) =>
    status === 'Verified' || status === 'Completed'
        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
        : status === 'Rejected'
            ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'
            : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400';

const itemStatusBadge = (status) =>
    ({
        Pending: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        Submitted: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'Under Review': 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        'In Progress': 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        Verified: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        Completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        Rejected: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
        Resubmitted: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        Blocked: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
    })[status] || 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300';

const itemStatusLabel = (status) =>
    ({ Pending: 'Not Submitted', Submitted: 'Awaiting Review', 'Under Review': 'Under Review', Rejected: 'Rejected', Verified: 'Verified', Resubmitted: 'Resubmitted', 'In Progress': 'In Progress', Blocked: 'Blocked' })[status] || status;

const activityStatusBadge = (status) =>
    ({
        Draft: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        Scheduled: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'In Progress': 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        Completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        Cancelled: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
        Rescheduled: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
        'No Show': 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
    })[status] || 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300';

const onboardingStatusBadge = (status) =>
    ({
        'In Progress': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'Pending Requirements': 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        'Ready to Hire': 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
        Completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    })[status] || 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300';

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
