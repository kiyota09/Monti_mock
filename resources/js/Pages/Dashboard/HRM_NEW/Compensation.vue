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
                    <p class="text-sm font-semibold text-slate-600">Loading compensation…</p>
                </div>
            </div>
        </Transition>

        <div class="flex min-h-screen bg-gradient-to-br from-slate-100 via-slate-50 to-blue-50">
            <div class="flex-1 p-8 text-slate-900 font-sans min-w-0 relative flex flex-col">
                <div class="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,transparent,black,transparent)] pointer-events-none"></div>

                <!-- Header -->
                <header class="mb-6 relative shrink-0">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div>
                            <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
                                <span class="hover:text-slate-700 cursor-pointer transition-colors">Dashboard</span>
                                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="hover:text-slate-700 cursor-pointer transition-colors">Human Resource</span>
                                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="text-blue-600 font-semibold">Compensation</span>
                            </nav>
                            <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">Compensation &amp; Salary Management</h1>
                            <p class="text-sm text-slate-500 mt-0.5">Salary records, adjustments, approvals and history. Applied salaries update payroll automatically.</p>
                        </div>
                    </div>
                </header>

                <!-- Tabs -->
                <div class="relative shrink-0 mb-4">
                    <div class="flex items-center gap-1 p-1 bg-white border border-slate-200/60 rounded-xl shadow-md overflow-x-auto">
                        <button
                            v-for="t in tabs"
                            :key="t.key"
                            @click="activeTab = t.key"
                            :class="['flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-xs font-bold whitespace-nowrap transition-all',
                                activeTab === t.key
                                    ? 'bg-gradient-to-r from-blue-600 to-blue-700 text-white shadow-md'
                                    : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50']"
                        >
                            <component :is="t.icon" class="w-3.5 h-3.5" /> {{ t.label }}
                        </button>
                    </div>
                </div>

                <!-- ============ DASHBOARD TAB ============ -->
                <div v-if="activeTab === 'dashboard'" class="relative">
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
                        <div v-for="m in summaryCards" :key="m.label" class="bg-white rounded-xl border border-slate-200/60 p-5 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">{{ m.label }}</span>
                                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center shadow-inner">
                                    <component :is="m.icon" class="w-5 h-5 text-blue-600" />
                                </div>
                            </div>
                            <div class="text-3xl font-bold text-slate-900">{{ m.value }}</div>
                            <div class="text-xs text-slate-400 mt-0.5">{{ m.subtext }}</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                        <div class="bg-white rounded-xl border border-slate-200/60 p-5 shadow-md">
                            <h3 class="text-sm font-bold text-slate-900 mb-1">Requests by Status</h3>
                            <p class="text-[11px] text-slate-400 mb-4">Live approval-queue snapshot.</p>
                            <div class="space-y-2.5">
                                <div v-for="s in statusBreakdown" :key="s.status" class="flex items-center gap-3">
                                    <span :class="['inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold border w-32 justify-center', s.badge]">{{ s.status }}</span>
                                    <div class="flex-1 h-2.5 rounded-full bg-slate-100 overflow-hidden">
                                        <div class="h-full rounded-full bg-gradient-to-r from-blue-500 to-blue-600" :style="{ width: s.pct + '%' }"></div>
                                    </div>
                                    <span class="text-xs font-bold text-slate-700 w-6 text-right">{{ s.count }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl border border-slate-200/60 p-5 shadow-md">
                            <h3 class="text-sm font-bold text-slate-900 mb-1">Upcoming Effective Dates</h3>
                            <p class="text-[11px] text-slate-400 mb-4">Approved adjustments awaiting activation.</p>
                            <div class="space-y-2">
                                <div v-for="u in upcomingEffective" :key="u.id" class="flex items-center justify-between px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl">
                                    <div>
                                        <div class="text-xs font-bold text-slate-800">{{ u.name }}</div>
                                        <div class="text-[10px] text-slate-400">{{ u.oldSalary }} → {{ u.newSalary }}</div>
                                    </div>
                                    <span class="text-[10px] font-bold text-blue-700 bg-blue-50 border border-blue-200 px-2 py-1 rounded-full">{{ u.date }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============ EMPLOYEES TAB ============ -->
                <div v-if="activeTab === 'employees'" class="relative">
                    <div class="bg-white rounded-xl border border-slate-200/60 shadow-md p-4 mb-4">
                        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                            <div class="flex-1 relative">
                                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                                </svg>
                                <input v-model="empSearch" type="text" placeholder="Search by name or employee ID…" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm" />
                            </div>
                            <select v-model="empDept" class="px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm">
                                <option value="">All departments</option>
                                <option v-for="d in departmentOptions" :key="d" :value="d">{{ d }}</option>
                            </select>
                            <select v-model="empType" class="px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm">
                                <option value="">All types</option>
                                <option v-for="t in employmentTypeOptions" :key="t" :value="t">{{ t }}</option>
                            </select>
                            <button @click="activeTab = 'adjustments'" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-xs font-bold shadow-lg transition-all">
                                <Plus class="w-3.5 h-3.5" /> Add Salary Record
                            </button>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200/60 shadow-md overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left min-w-[860px]">
                                <thead>
                                    <tr class="bg-gradient-to-r from-slate-50 to-transparent border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                        <th class="py-3 px-4">Employee</th>
                                        <th class="py-3 px-4">Position</th>
                                        <th class="py-3 px-4">Type</th>
                                        <th class="py-3 px-4">Monthly Basic</th>
                                        <th class="py-3 px-4">Last Adjustment</th>
                                        <th class="py-3 px-4 text-center">Status</th>
                                        <th class="py-3 px-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="e in filteredEmployees" :key="e.id" class="hover:bg-blue-50/40 transition-colors">
                                        <td class="py-3 px-4">
                                            <div class="flex items-center gap-2">
                                                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-50 to-indigo-50 flex items-center justify-center text-[10px] font-black text-blue-700 shrink-0">{{ initials(e.name) }}</div>
                                                <div class="min-w-0">
                                                    <div class="text-xs font-bold text-slate-800 truncate">{{ e.name }}</div>
                                                    <div class="text-[10px] text-slate-400">{{ e.employeeId }} · {{ e.department }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 text-xs text-slate-600">{{ e.position }}</td>
                                        <td class="py-3 px-4">
                                            <span :class="['inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold border whitespace-nowrap', e.type === 'Regular' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200']">{{ e.type }}</span>
                                        </td>
                                        <td class="py-3 px-4 text-sm font-black text-slate-900 whitespace-nowrap">{{ peso(e.salary) }}</td>
                                        <td class="py-3 px-4 text-xs text-slate-500 whitespace-nowrap">{{ e.lastAdjustment || '—' }}</td>
                                        <td class="py-3 px-4 text-center">
                                            <span v-if="e.hasOpen" class="inline-flex px-2 py-0.5 rounded-full text-[9px] font-bold bg-amber-50 text-amber-700 border border-amber-200 whitespace-nowrap">PROPOSAL OPEN</span>
                                            <span v-else-if="e.salary === null || e.salary === undefined || e.salary === ''" class="inline-flex px-2 py-0.5 rounded-full text-[9px] font-bold bg-slate-100 text-slate-500 border border-slate-200 whitespace-nowrap">NO SALARY SET</span>
                                            <span v-else class="inline-flex px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 whitespace-nowrap">ACTIVE</span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex items-center justify-end gap-2">
                                                <button @click="openDetail(e)" class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-800 hover:bg-slate-900 text-white rounded-lg text-[10px] font-bold transition-all whitespace-nowrap">
                                                    <Eye class="w-3 h-3" /> View Details
                                                </button>
                                                <button @click="startAdjustment(e)" class="inline-flex items-center gap-1 px-3 py-1.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-lg text-[10px] font-bold transition-all whitespace-nowrap">
                                                    Adjust Salary <ArrowRight class="w-3 h-3" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredEmployees.length === 0">
                                        <td colspan="7" class="py-12 text-center">
                                            <p class="text-sm font-bold text-slate-700">No records match</p>
                                            <p class="text-xs text-slate-400 mt-1">Try a different search or filter.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ============ ADJUSTMENTS TAB ============ -->
                <div v-if="activeTab === 'adjustments'" class="relative">
                    <div class="grid grid-cols-1 xl:grid-cols-5 gap-4">
                        <div class="xl:col-span-3 bg-white rounded-xl border border-slate-200/60 shadow-md p-5">
                            <h3 class="text-sm font-bold text-slate-900 mb-1">Salary Adjustment — Preview</h3>
                            <p class="text-[11px] text-slate-400 mb-4">Proposals never touch active payroll in this preview.</p>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Employee</label>
                                    <select v-model="adjForm.employeeId" @change="onAdjEmployeeChange" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm">
                                        <option value="" disabled>Select employee</option>
                                        <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }} · {{ e.employeeId }}</option>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Current Monthly Salary (₱)</label>
                                        <input :value="pesoPlain(adjForm.currentSalary)" type="text" readonly class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-500 cursor-not-allowed" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Proposed Monthly Salary (₱)</label>
                                        <input v-model="proposedDisplay" type="text" inputmode="numeric" placeholder="e.g., 27,000" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm" />
                                        <p v-if="bandWarning" class="text-[10px] text-amber-600 font-semibold mt-1">{{ bandWarning }}</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Adjustment Type</label>
                                        <select v-model="adjForm.type" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm">
                                            <option v-for="t in adjustmentTypes" :key="t" :value="t">{{ t }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Effective Date</label>
                                        <input v-model="adjForm.effectiveDate" type="date" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm" />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Reason</label>
                                    <input v-model="adjForm.reason" type="text" placeholder="Performance-based increase" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Supporting Notes / Justification</label>
                                    <textarea v-model="adjForm.notes" rows="2" placeholder="Review summary, ratings, approvals…" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm"></textarea>
                                </div>
                                <div class="flex items-center gap-3 flex-wrap">
                                    <button @click="copyDraft" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold transition-all">
                                        <Copy class="w-3.5 h-3.5" /> Copy Draft for Review
                                    </button>
                                    <button @click="submitDraft(false)" :disabled="saving" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold transition-all disabled:opacity-50">
                                        {{ saving ? 'Saving…' : 'Save as Draft' }}
                                    </button>
                                    <button @click="submitDraft(true)" :disabled="saving" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-xs font-bold shadow-lg transition-all disabled:opacity-50">
                                        {{ saving ? 'Submitting…' : 'Submit for Approval' }}
                                    </button>
                                </div>
                                <p class="text-[11px] text-slate-400">Saving a draft never changes the active salary — it only takes effect after approval + effective date.</p>
                            </div>
                        </div>
                        <div class="xl:col-span-2 space-y-4">
                            <div class="bg-white rounded-xl border border-slate-200/60 shadow-md p-5">
                                <h3 class="text-sm font-bold text-slate-900 mb-3">Live Preview</h3>
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl">
                                        <span class="text-xs text-slate-500">Monthly difference</span>
                                        <span :class="['text-sm font-black', monthlyDiff > 0 ? 'text-emerald-600' : monthlyDiff < 0 ? 'text-rose-600' : 'text-slate-500']">{{ monthlyDiff > 0 ? '+' : '' }}{{ pesoSigned(monthlyDiff) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl">
                                        <span class="text-xs text-slate-500">Percentage change</span>
                                        <span class="text-sm font-black text-slate-800">{{ pctChange }}%</span>
                                    </div>
                                    <div class="flex items-center justify-between px-3 py-2.5 bg-blue-50 border border-blue-100 rounded-xl">
                                        <span class="text-xs text-blue-700 font-semibold">Proposed annual basic</span>
                                        <span class="text-sm font-black text-blue-800">{{ peso((adjForm.proposedSalary || 0) * 12) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                                <p class="text-[11px] text-amber-800 leading-relaxed"><b>Workflow:</b> Draft → Pending Approval → Approved → Scheduled → Applied. Payroll uses the new salary only once the request is Applied on its effective date.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============ APPROVAL QUEUE TAB ============ -->
                <div v-if="activeTab === 'approvals'" class="relative">
                    <div class="flex items-center justify-end mb-3">
                        <button @click="applyDue" :disabled="saving" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-500 hover:to-emerald-600 text-white rounded-xl text-xs font-bold shadow-lg transition-all disabled:opacity-50">
                            <CheckCircle2 class="w-3.5 h-3.5" /> {{ saving ? 'Applying…' : 'Apply Due (effective dates arrived)' }}
                        </button>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200/60 shadow-md overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left min-w-[760px]">
                                <thead>
                                    <tr class="bg-gradient-to-r from-slate-50 to-transparent border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                        <th class="py-3 px-4">Employee</th>
                                        <th class="py-3 px-4">Change</th>
                                        <th class="py-3 px-4">Type</th>
                                        <th class="py-3 px-4">Effective</th>
                                        <th class="py-3 px-4 text-center">Status</th>
                                        <th class="py-3 px-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="q in approvalQueue" :key="q.id" class="hover:bg-blue-50/40 transition-colors">
                                        <td class="py-3 px-4">
                                            <div class="text-xs font-bold text-slate-800">{{ q.name }}</div>
                                            <div class="text-[10px] text-slate-400">{{ q.employeeId }} · requested by {{ q.requestedBy }}</div>
                                            <div v-if="q.approvedBy" class="text-[10px] text-slate-400">decided by {{ q.approvedBy }}{{ q.decidedAt ? ' · ' + q.decidedAt : '' }}</div>
                                        </td>
                                        <td class="py-3 px-4 text-xs text-slate-600">{{ peso(q.oldSalary) }} → <b>{{ peso(q.newSalary) }}</b></td>
                                        <td class="py-3 px-4 text-xs text-slate-600">{{ q.type }}</td>
                                        <td class="py-3 px-4 text-xs text-slate-600">{{ q.effectiveDate }}</td>
                                        <td class="py-3 px-4 text-center">
                                            <span :class="['inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold border', statusBadge(q.status)]">{{ q.status }}</span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex items-center justify-end gap-2">
                                                <button v-if="q.status === 'Pending Approval'" @click="setQueueStatus(q, 'Approved')" :disabled="saving" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-[10px] font-bold transition-all disabled:opacity-50">Approve</button>
                                                <button v-if="q.status === 'Pending Approval'" @click="setQueueStatus(q, 'Rejected')" :disabled="saving" class="px-3 py-1.5 bg-white border border-rose-200 hover:bg-rose-50 text-rose-600 rounded-lg text-[10px] font-bold transition-all disabled:opacity-50">Reject</button>
                                                <button v-if="q.status === 'Draft'" @click="submitProposal(q)" :disabled="saving" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-[10px] font-bold transition-all disabled:opacity-50">Submit</button>
                                                <button v-if="['Draft', 'Pending Approval', 'Approved', 'Scheduled'].includes(q.status)" @click="cancelProposal(q)" :disabled="saving" class="px-3 py-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-500 rounded-lg text-[10px] font-bold transition-all disabled:opacity-50">Cancel</button>
                                                <span v-if="['Applied', 'Rejected', 'Cancelled'].includes(q.status)" class="text-[10px] text-slate-400">No actions</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-2">Approvals update basic salary only on the effective date (Apply Due or daily schedule). Rejected and cancelled rows stay for audit.</p>
                </div>

                <!-- ============ HISTORY TAB ============ -->
                <div v-if="activeTab === 'history'" class="relative">
                    <div class="bg-white rounded-xl border border-slate-200/60 shadow-md p-5">
                        <div class="flex flex-col md:flex-row md:items-center gap-3 mb-4">
                            <h3 class="text-sm font-bold text-slate-900">Salary History <span class="text-[10px] font-semibold text-slate-400">(read-only preview)</span></h3>
                            <select v-model="historyEmployeeId" class="md:ml-auto px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm">
                                <option value="" disabled>Select employee</option>
                                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }} · {{ e.employeeId }}</option>
                            </select>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left min-w-[560px]">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                        <th class="py-2.5 px-4">Effective Date</th>
                                        <th class="py-2.5 px-4">Previous Salary</th>
                                        <th class="py-2.5 px-4">New Salary</th>
                                        <th class="py-2.5 px-4">Reason</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(h, i) in selectedHistory" :key="i" class="hover:bg-blue-50/40 transition-colors">
                                        <td class="py-2.5 px-4 text-xs text-slate-600">{{ h.date }}</td>
                                        <td class="py-2.5 px-4 text-xs text-slate-500">{{ h.prev ? peso(h.prev) : '—' }}</td>
                                        <td class="py-2.5 px-4 text-xs font-bold text-slate-800">{{ peso(h.next) }}</td>
                                        <td class="py-2.5 px-4 text-xs text-slate-600">{{ h.reason }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-3">Corrections use an authorized workflow — history rows are never edited directly.</p>
                    </div>
                </div>

                <!-- ============ REPORTS TAB ============ -->
                <div v-if="activeTab === 'reports'" class="relative">
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                        <div class="bg-white rounded-xl border border-slate-200/60 shadow-md p-5">
                            <h3 class="text-sm font-bold text-slate-900 mb-1">Average Monthly Basic by Department</h3>
                            <p class="text-[11px] text-slate-400 mb-4">Computed live from salary records.</p>
                            <div class="space-y-2.5">
                                <div v-for="r in avgByDept" :key="r.dept" class="flex items-center gap-3">
                                    <span class="text-[11px] font-bold text-slate-600 w-32 truncate">{{ r.dept }}</span>
                                    <div class="flex-1 h-2.5 rounded-full bg-slate-100 overflow-hidden">
                                        <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-emerald-600" :style="{ width: r.pct + '%' }"></div>
                                    </div>
                                    <span class="text-xs font-bold text-slate-700 w-20 text-right">{{ peso(r.avg) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl border border-slate-200/60 shadow-md p-5">
                            <h3 class="text-sm font-bold text-slate-900 mb-1">Compensation Components</h3>
                            <p class="text-[11px] text-slate-400 mb-4">Basic stays separate from everything payroll calculates.</p>
                            <div class="space-y-2">
                                <div v-for="c in componentGuide" :key="c.name" class="flex items-start gap-3 px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl">
                                    <component :is="c.icon" class="w-4 h-4 mt-0.5 text-blue-600 shrink-0" />
                                    <div>
                                        <div class="text-xs font-bold text-slate-800">{{ c.name }}</div>
                                        <div class="text-[11px] text-slate-500">{{ c.desc }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail modal -->
        <div v-if="detailEmployee" class="fixed inset-0 z-[80] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="detailEmployee = null">
            <div class="bg-white rounded-2xl border border-slate-200/60 w-full max-w-lg overflow-hidden shadow-2xl">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-transparent">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">{{ detailEmployee.name }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">{{ detailEmployee.employeeId }} · {{ detailEmployee.department }} · {{ detailEmployee.position }}</p>
                    </div>
                    <button @click="detailEmployee = null" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <div class="p-5 space-y-2">
                    <div class="flex items-center justify-between px-3 py-2.5 bg-blue-50 border border-blue-100 rounded-xl">
                        <span class="text-xs font-semibold text-blue-700">Monthly basic salary</span>
                        <span class="text-sm font-black text-blue-800">{{ peso(detailEmployee.salary) }}</span>
                    </div>
                    <div v-if="detailAllowances.length === 0" class="px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl">
                        <p class="text-[11px] text-slate-400">No allowance components recorded.</p>
                    </div>
                    <div v-for="a in detailAllowances" :key="a.id" class="flex items-center justify-between gap-2 px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl">
                        <div>
                            <div class="text-xs font-bold text-slate-700">{{ a.name }}</div>
                            <div class="text-[10px] text-slate-400">{{ a.frequency }}{{ a.isActive ? '' : ' · inactive' }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-slate-700">{{ peso(a.amount) }}</span>
                            <button @click="toggleComponent(a)" :disabled="saving" class="px-2 py-1 bg-white border border-slate-200 hover:bg-slate-100 rounded-lg text-[10px] font-bold text-slate-500 transition-all disabled:opacity-50">{{ a.isActive ? 'Deactivate' : 'Activate' }}</button>
                        </div>
                    </div>
                    <div class="grid grid-cols-5 gap-2 pt-1">
                        <input v-model="compForm.name" type="text" placeholder="Allowance name" class="col-span-3 px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm" />
                        <input v-model="compForm.amount" type="text" inputmode="decimal" placeholder="Amount" class="col-span-2 px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm" />
                    </div>
                    <button @click="submitComponent" :disabled="saving" class="w-full py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-[11px] font-bold transition-all disabled:opacity-50">Add Allowance Component</button>
                    <p class="text-[11px] text-slate-400 pt-1">Allowances stay separate from basic salary. Overtime, bonuses and deductions are calculated in payroll.</p>
                </div>
                <div class="p-4 border-t border-slate-100 flex items-center justify-end gap-3">
                    <button @click="detailEmployee = null" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-all">Close</button>
                    <button @click="startAdjustment(detailEmployee)" class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-sm font-bold transition-all shadow-lg">Adjust Salary</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import {
    LayoutDashboard, Users, Settings2, Hourglass, History, BarChart3,
    Plus, Search, Eye, ArrowRight, X, Copy, Wallet, Banknote,
    TrendingUp, CalendarClock, CheckCircle2,
} from 'lucide-vue-next';

/* ------------------------------------------------------------------ */
/* Server-driven page. Employees, adjustments, history and components   */
/* come from the backend; actions post to compensation routes. No      */
/* payroll logic lives here — applying an adjustment only updates      */
/* users.basic_salary (which payroll already reads) + history.         */
/* ------------------------------------------------------------------ */

const props = defineProps({
    employees: { type: Array, default: () => [] },
    adjustments: { type: Array, default: () => [] },
    histories: { type: Object, default: () => ({}) },
    components: { type: Object, default: () => ({}) },
    positions: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    adjustmentTypes: { type: Array, default: () => [] },
    approvedThisMonth: { type: Number, default: 0 },
    permissions: { type: Object, default: () => ({}) },
});

const page = usePage();

const tabs = [
    { key: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { key: 'employees', label: 'Employee Compensation', icon: Users },
    { key: 'adjustments', label: 'Salary Adjustments', icon: Settings2 },
    { key: 'approvals', label: 'Approval Queue', icon: Hourglass },
    { key: 'history', label: 'Salary History', icon: History },
    { key: 'reports', label: 'Reports', icon: BarChart3 },
];

const activeTab = ref('dashboard');
const pageLoading = ref(true);
const saving = ref(false);

const employees = computed(() => props.employees || []);
const approvalQueue = computed(() => props.adjustments || []);
const histories = computed(() => props.histories || {});
const componentsByUser = computed(() => props.components || {});
const adjustmentTypes = computed(() => props.adjustmentTypes?.length
    ? props.adjustmentTypes
    : ['Merit Increase', 'Annual Review', 'Promotion', 'Probation Pass', 'Allowance Change', 'Market Adjustment']);

/* ------------------------------ state ------------------------------ */

const empSearch = ref('');
const empDept = ref('');
const empType = ref('');
const detailEmployee = ref(null);
const historyEmployeeId = ref(props.employees?.[0]?.id ?? '');
const compForm = ref({ name: '', amount: '' });

const adjForm = ref({
    employeeId: '', currentSalary: null, proposedSalary: '',
    type: 'Merit Increase', reason: '',
    effectiveDate: new Date().toISOString().slice(0, 10), notes: '',
});

/* ---------------------------- computed ----------------------------- */

const peso = (v) => {
    if (v === null || v === undefined || v === '') return '—';
    return '₱' + Number(v).toLocaleString('en-PH');
};
const pesoPlain = (v) => {
    if (v === null || v === undefined || v === '') return '';
    return Number(v).toLocaleString('en-US');
};
const pesoSigned = (v) => {
    if (v === null || v === undefined || v === '') return '₱0';
    return '₱' + Math.abs(Number(v)).toLocaleString('en-PH');
};
const initials = (name) => String(name || '?').split(' ').map((n) => n[0]).join('').toUpperCase().slice(0, 2);

const summaryCards = computed(() => [
    { label: 'Employees w/ Salary Records', value: String(employees.value.filter((e) => e.salary !== null && e.salary !== undefined && e.salary !== '').length), subtext: employees.value.length + ' active employees', icon: Users },
    { label: 'Pending Approvals', value: String(approvalQueue.value.filter((q) => q.status === 'Pending Approval').length), subtext: 'awaiting approver', icon: Hourglass },
    { label: 'Approved Adjustments', value: String(props.approvedThisMonth || 0), subtext: 'decided this month', icon: CheckCircle2 },
    { label: 'Upcoming Effective Dates', value: String(upcomingEffective.value.length), subtext: 'scheduled activations', icon: CalendarClock },
]);

const STATUS_META = {
    'Draft': 'bg-slate-100 text-slate-500 border-slate-200',
    'Pending Approval': 'bg-amber-50 text-amber-700 border-amber-200',
    'Approved': 'bg-blue-50 text-blue-700 border-blue-200',
    'Scheduled': 'bg-violet-50 text-violet-700 border-violet-200',
    'Applied': 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'Rejected': 'bg-rose-50 text-rose-700 border-rose-200',
    'Cancelled': 'bg-slate-100 text-slate-400 border-slate-200',
};
const statusBadge = (s) => STATUS_META[s] || STATUS_META['Draft'];

const statusBreakdown = computed(() => {
    const total = approvalQueue.value.length || 1;
    const order = ['Draft', 'Pending Approval', 'Approved', 'Scheduled', 'Applied', 'Rejected', 'Cancelled'];
    return order.map((s) => {
        const count = approvalQueue.value.filter((q) => q.status === s).length;
        return { status: s, count, pct: Math.round((count / total) * 100), badge: statusBadge(s) };
    });
});

const upcomingEffective = computed(() => approvalQueue.value
    .filter((q) => q.status === 'Approved' || q.status === 'Scheduled')
    .map((q) => ({ id: q.id, name: q.name, oldSalary: peso(q.oldSalary), newSalary: peso(q.newSalary), date: q.effectiveDate })));

const departmentOptions = computed(() => [...new Set(employees.value.map((e) => e.department))]);
const employmentTypeOptions = computed(() => [...new Set(employees.value.map((e) => e.type))]);

const filteredEmployees = computed(() => {
    const q = empSearch.value.trim().toLowerCase();
    return employees.value.filter((e) => {
        const okSearch = !q || e.name.toLowerCase().includes(q) || e.employeeId.toLowerCase().includes(q);
        const okDept = !empDept.value || e.department === empDept.value;
        const okType = !empType.value || e.type === empType.value;
        return okSearch && okDept && okType;
    });
});

const monthlyDiff = computed(() => (Number(adjForm.value.proposedSalary) || 0) - (Number(adjForm.value.currentSalary) || 0));
const pctChange = computed(() => {
    const cur = Number(adjForm.value.currentSalary) || 0;
    if (!cur) return '0.00';
    return (((Number(adjForm.value.proposedSalary) || 0) - cur) / cur * 100).toFixed(2);
});
const proposedDisplay = computed({
    get: () => {
        const v = adjForm.value.proposedSalary;
        if (v === null || v === undefined || v === '') return '';
        return Number(String(v).replace(/[^0-9]/g, '') || 0).toLocaleString('en-US');
    },
    set: (v) => {
        const d = String(v ?? '').replace(/[^0-9]/g, '');
        adjForm.value.proposedSalary = d === '' ? '' : Number(d);
    },
});

const selectedHistory = computed(() => histories.value[historyEmployeeId.value] || [{ date: 'Jan 1, 2026', prev: null, next: (employees.value.find((e) => e.id === historyEmployeeId.value)?.salary || 0), reason: 'Initial salary' }]);

const avgByDept = computed(() => {
    const groups = {};
    employees.value.forEach((e) => {
        if (e.salary === null || e.salary === undefined || e.salary === '') return;
        if (!groups[e.department]) groups[e.department] = [];
        groups[e.department].push(Number(e.salary));
    });
    if (!Object.keys(groups).length) return [];
    const max = Math.max(...Object.values(groups).map((a) => a.reduce((s, v) => s + v, 0) / a.length), 1);
    return Object.entries(groups).map(([dept, arr]) => {
        const avg = Math.round(arr.reduce((s, v) => s + v, 0) / arr.length);
        return { dept, avg, pct: Math.round((avg / max) * 100) };
    }).sort((a, b) => b.avg - a.avg);
});

const componentGuide = [
    { name: 'Basic salary', desc: 'Contractual base pay — the only figure stored as the salary.', icon: Wallet },
    { name: 'Allowances', desc: 'Transport, meal, communication and other authorized allowances.', icon: Banknote },
    { name: 'Overtime & night differential', desc: 'Calculated from payroll rules and eligible hours.', icon: TrendingUp },
    { name: 'Bonuses & incentives', desc: 'Separate approved payments, never part of base salary.', icon: TrendingUp },
    { name: 'Statutory deductions', desc: 'Calculated in payroll per applicable rules.', icon: Settings2 },
    { name: 'Gross & net pay', desc: 'Calculated in payroll per pay period — not entered as base salary.', icon: BarChart3 },
];

const detailAllowances = computed(() => {
    if (!detailEmployee.value) return [];
    return (componentsByUser.value[detailEmployee.value.id] || []).filter((c) => c.isActive);
});

// Position salary band (soft guide) for the selected adjustment employee.
const selectedBand = computed(() => {
    const e = employees.value.find((x) => x.id === adjForm.value.employeeId);
    if (!e?.positionId) return null;
    const p = (props.positions || []).find((x) => String(x.id) === String(e.positionId));
    if (!p || (p.salary_min === null && p.salary_max === null)) return null;
    return { min: p.salary_min, max: p.salary_max, name: p.name };
});
const bandWarning = computed(() => {
    if (!selectedBand.value || adjForm.value.proposedSalary === '' || adjForm.value.proposedSalary === null) return '';
    const v = Number(adjForm.value.proposedSalary);
    const { min, max } = selectedBand.value;
    if (min !== null && v < Number(min)) return 'Below the ' + selectedBand.value.name + ' band minimum of ' + peso(min) + '.';
    if (max !== null && v > Number(max)) return 'Above the ' + selectedBand.value.name + ' band maximum of ' + peso(max) + '.';
    return '';
});

/* ----------------------------- actions ----------------------------- */

watch(() => page.props.flash, (flash) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
}, { immediate: true });

watch(() => page.props.errors, (errors) => {
    if (errors && Object.keys(errors).length > 0) {
        toast.error(String(Object.values(errors)[0]));
    }
}, { deep: true });

const openDetail = (e) => {
    detailEmployee.value = e;
    compForm.value = { name: '', amount: '' };
};

const startAdjustment = (e) => {
    detailEmployee.value = null;
    adjForm.value.employeeId = e.id;
    adjForm.value.currentSalary = e.salary;
    adjForm.value.proposedSalary = e.salary;
    adjForm.value.reason = '';
    activeTab.value = 'adjustments';
};

const onAdjEmployeeChange = () => {
    const e = employees.value.find((x) => x.id === adjForm.value.employeeId);
    if (e) {
        adjForm.value.currentSalary = e.salary;
        adjForm.value.proposedSalary = e.salary;
    }
};

const copyDraft = async () => {
    const e = employees.value.find((x) => x.id === adjForm.value.employeeId);
    const text = 'Salary Adjustment Draft — ' + (e ? e.name + ' (' + e.employeeId + ')' : '') +
        ' | Current: ' + pesoPlain(adjForm.value.currentSalary) +
        ' | Proposed: ' + pesoPlain(adjForm.value.proposedSalary) +
        ' (' + (monthlyDiff.value > 0 ? '+' : '') + pesoPlain(monthlyDiff.value) + ', ' + pctChange.value + '%)' +
        ' | Type: ' + adjForm.value.type + ' | Effective: ' + (adjForm.value.effectiveDate || '—') +
        ' | Reason: ' + (adjForm.value.reason || '—');
    try {
        await navigator.clipboard.writeText(text);
        toast.success('Draft copied for review.');
    } catch (err) {
        toast.info('Copy blocked by browser — select the text manually.');
    }
};

const submitDraft = (submit) => {
    const e = employees.value.find((x) => x.id === adjForm.value.employeeId);
    if (!e) { toast.error('Select an employee first.'); return; }
    if (adjForm.value.proposedSalary === '' || adjForm.value.proposedSalary === null) { toast.error('Enter a proposed salary.'); return; }
    if (!adjForm.value.effectiveDate) { toast.error('Effective date is required.'); return; }
    saving.value = true;
    router.post(route('hrm.compensation.adjustments.store'), {
        user_id: e.id,
        new_salary: Number(adjForm.value.proposedSalary),
        adjustment_type: adjForm.value.type,
        reason: adjForm.value.reason || null,
        notes: adjForm.value.notes || null,
        effective_date: adjForm.value.effectiveDate,
        submit: !!submit,
    }, {
        preserveScroll: true,
        onFinish: () => { saving.value = false; },
    });
};

const setQueueStatus = (q, status, remarks = '') => {
    const action = status === 'Approved' ? 'approve' : 'reject';
    saving.value = true;
    router.post(route('hrm.compensation.adjustments.' + action, q.id), { remarks: remarks || null }, {
        preserveScroll: true,
        onFinish: () => { saving.value = false; },
    });
};

const cancelProposal = (q) => {
    saving.value = true;
    router.post(route('hrm.compensation.adjustments.cancel', q.id), {}, {
        preserveScroll: true,
        onFinish: () => { saving.value = false; },
    });
};

const submitProposal = (q) => {
    saving.value = true;
    router.post(route('hrm.compensation.adjustments.submit', q.id), {}, {
        preserveScroll: true,
        onFinish: () => { saving.value = false; },
    });
};

const applyDue = () => {
    saving.value = true;
    router.post(route('hrm.compensation.apply-due'), {}, {
        preserveScroll: true,
        onFinish: () => { saving.value = false; },
    });
};

const submitComponent = () => {
    if (!detailEmployee.value) return;
    if (!compForm.value.name.trim()) { toast.error('Component name is required.'); return; }
    if (compForm.value.amount === '' || compForm.value.amount === null) { toast.error('Amount is required.'); return; }
    saving.value = true;
    router.post(route('hrm.compensation.components.store'), {
        user_id: detailEmployee.value.id,
        name: compForm.value.name.trim(),
        amount: Number(String(compForm.value.amount).replace(/[^0-9.]/g, '')) || 0,
    }, {
        preserveScroll: true,
        onFinish: () => { saving.value = false; },
    });
};

const toggleComponent = (c) => {
    saving.value = true;
    router.post(route('hrm.compensation.components.toggle', c.id), {}, {
        preserveScroll: true,
        onFinish: () => { saving.value = false; },
    });
};

onMounted(() => {
    setTimeout(() => { pageLoading.value = false; }, 200);
});
</script>

<style scoped>
.bg-grid-slate-100 {
    background-image: radial-gradient(circle, #cbd5e1 1px, transparent 1px);
    background-size: 24px 24px;
}
</style>
