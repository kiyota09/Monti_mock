<template>
    <AuthenticatedLayout>
        <div class="min-h-screen bg-slate-50 px-4 py-6 text-slate-800 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-[1500px] space-y-5">
                <header class="flex flex-col justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:px-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">HRM · Recruitment</p>
                        <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Agency Referrals</h1>
                        <p class="mt-1 text-sm text-slate-500">Refer Monti interview passers to an accredited agency and follow each response.</p>
                    </div>

                    <button @click="openCreate()" class="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-amber-900/10 transition hover:-translate-y-0.5 hover:bg-amber-700 focus:outline-none focus:ring-4 focus:ring-amber-200">
                        <Plus class="h-4 w-4" /> Create Referral
                    </button>
                </header>

                <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Referral summary">
                    <article v-for="card in cards" :key="card.label" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold text-slate-500">{{ card.label }}</p>
                                <p class="mt-2 text-3xl font-black tabular-nums text-slate-900">{{ card.value }}</p>
                            </div>
                            <span :class="card.iconClass" class="flex h-11 w-11 items-center justify-center rounded-2xl"><component :is="card.icon" class="h-5 w-5" /></span>
                        </div>
                    </article>
                </section>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-4 border-b border-slate-200 p-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div class="flex items-center gap-2"><h2 class="text-base font-bold text-slate-900">Referrals</h2><span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">{{ filteredReferrals.length }}</span></div>
                            <p class="mt-1 text-xs text-slate-500">Search and filter referral activity.</p>
                        </div>
                        <div class="grid w-full grid-cols-1 gap-2 sm:grid-cols-2 lg:max-w-3xl lg:grid-cols-[minmax(240px,1fr)_190px_190px]">
                            <label class="relative">
                                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input v-model="search" type="search" placeholder="Search applicant, role or ID" class="w-full rounded-xl border-slate-200 bg-slate-50 py-2.5 pl-9 pr-3 text-sm text-slate-700 placeholder:text-slate-400 focus:border-amber-500 focus:bg-white focus:ring-amber-500" />
                            </label>
                            <select v-model="statusFilter" class="rounded-xl border-slate-200 bg-slate-50 py-2.5 text-sm text-slate-700 focus:border-amber-500 focus:bg-white focus:ring-amber-500">
                                <option value="">All statuses</option><option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                            </select>
                            <select v-model="agencyFilter" class="rounded-xl border-slate-200 bg-slate-50 py-2.5 text-sm text-slate-700 focus:border-amber-500 focus:bg-white focus:ring-amber-500">
                                <option value="">All agencies</option><option v-for="agency in agencies" :key="agency.id" :value="String(agency.id)">{{ agency.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1180px] text-left">
                            <thead class="bg-slate-800 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Applicant</th><th class="px-4 py-3">Applied Position</th><th class="px-4 py-3">Department</th><th class="px-4 py-3">Interview Result</th><th class="px-4 py-3">Assigned Agency</th><th class="px-4 py-3">Referral Date</th><th class="px-4 py-3">Referral Status</th><th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="item in filteredReferrals" :key="item.id" class="cursor-pointer transition-colors hover:bg-amber-50/40" @click="openDetails(item)">
                                    <td class="px-4 py-4"><div class="text-sm font-bold text-slate-900">{{ item.applicant_name || 'Applicant' }}</div></td>
                                    <td class="px-4 py-4 text-sm font-medium text-slate-700">{{ item.position || '—' }}</td>
                                    <td class="px-4 py-4 text-sm text-slate-500">{{ item.department || '—' }}</td>
                                    <td class="px-4 py-3"><span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">{{ item.interview_result }}<span v-if="item.interview_stage"> · {{ item.interview_stage }}</span></span></td>
                                    <td class="px-4 py-4 text-sm font-semibold text-slate-700">{{ item.agency?.name || '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-4 text-xs font-medium text-slate-500">{{ formatDate(item.referral_date) }}</td>
                                    <td class="px-4 py-3"><span :class="statusClass(item.status)" class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-semibold">{{ item.status }}</span></td>
                                    <td class="px-4 py-4"><div class="flex justify-end gap-1.5">
                                        <button @click.stop="openDetails(item)" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50">View details</button>
                                        <button v-if="item.status === 'Ready to Refer'" @click.stop="sendReferral(item)" :disabled="busy" class="rounded-lg bg-amber-600 px-2.5 py-1.5 text-xs font-bold text-white transition hover:bg-amber-700 disabled:opacity-50">Send</button>
                                    </div></td>
                                </tr>
                                <tr v-if="!filteredReferrals.length"><td colspan="8" class="px-4 py-14 text-center"><div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-700"><Send class="h-5 w-5" /></div><p class="mt-3 text-sm font-semibold text-slate-700">No referrals found</p><p class="mt-1 text-xs text-slate-400">Create a referral for an applicant who completed onboarding.</p></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="referralPage.links?.length > 3" class="flex flex-wrap justify-end gap-1 border-t border-slate-100 p-3">
                        <button v-for="link in referralPage.links" :key="link.label" :disabled="!link.url" @click="link.url && router.visit(link.url, { preserveScroll: true })" v-html="link.label" :class="link.active ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" class="min-w-9 rounded-lg border border-slate-200 px-3 py-1.5 text-xs disabled:opacity-40" />
                    </div>
                </section>
            </div>
        </div>

        <!-- Create referral -->
        <div v-if="showCreate" class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto bg-slate-950/50 p-3 backdrop-blur-sm" @click.self="requestCloseCreate">
            <section class="my-auto w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="font-bold text-slate-900">Create Agency Referral</h2><p class="mt-0.5 text-xs text-slate-500">Choose an eligible applicant and verify consent before sharing data.</p></div><button type="button" @click="requestCloseCreate" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100"><X class="h-4 w-4" /></button></header>
                <form @submit.prevent="requestReferralSubmit" class="space-y-4 p-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="text-xs font-semibold text-slate-600 sm:col-span-2">Applicant
                            <span v-if="eligibleLoading" class="mt-1 flex items-center gap-2 rounded-xl border border-amber-100 bg-amber-50/60 px-3 py-3 text-xs font-medium text-amber-800" role="status" aria-live="polite">
                                <Loader2 class="h-4 w-4 animate-spin" /> Loading eligible applicants…
                            </span>
                            <span class="relative mt-1 block">
                                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-amber-600"><Users class="h-4 w-4" /></span>
                                <select v-model="createForm.applicant_job_application_id" required :disabled="eligibleLoading || !!eligibleLoadError" class="w-full appearance-none rounded-xl border-slate-200 bg-white py-3 pl-10 pr-10 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 disabled:cursor-wait disabled:bg-slate-50">
                                    <option value="" disabled>{{ eligibleLoading ? 'Loading applicants…' : eligibleLoadError ? 'Unable to load applicants' : 'Select an eligible applicant' }}</option>
                                    <option v-for="person in eligibleApplicants" :key="person.job_application_id" :value="person.job_application_id">{{ person.name }} · {{ person.position }} · {{ person.eligibility_status }}{{ person.interview_stage ? ` · ${person.interview_stage}` : '' }}</option>
                                </select>
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400"><ChevronDown class="h-4 w-4" /></span>
                            </span>
                            <span class="mt-1.5 block font-normal text-slate-400">Only applicants with completed onboarding are listed.</span>
                            <span v-if="eligibleLoadError" class="mt-1.5 block text-xs font-medium text-rose-600">{{ eligibleLoadError }} <button type="button" @click="loadEligibleApplicants()" class="font-bold underline">Try again</button></span>
                            <span v-else-if="!eligibleLoading && eligibleApplicants.length === 0" class="mt-1.5 block text-xs text-slate-400">No eligible applicants are available.</span>
                        </label>
                        <label class="text-xs font-semibold text-slate-600">Applied Position<input :value="selectedApplicant?.position || ''" readonly class="mt-1 w-full rounded-lg border-slate-200 bg-slate-50 text-sm text-slate-600" /></label>
                        <label class="text-xs font-semibold text-slate-600">Department<input :value="selectedApplicant?.department || ''" readonly class="mt-1 w-full rounded-lg border-slate-200 bg-slate-50 text-sm text-slate-600" /></label>
                        <label class="text-xs font-semibold text-slate-600 sm:col-span-2">Select Agency
                            <select v-model="createForm.agency_id" required class="mt-1 w-full rounded-lg border-slate-200 text-sm focus:border-amber-500 focus:ring-amber-500"><option value="">Choose active agency</option><option v-for="agency in agencies" :key="agency.id" :value="agency.id">{{ agency.name }}</option></select>
                        </label>
                        <label class="text-xs font-semibold text-slate-600 sm:col-span-2">Referral Reason <span class="font-normal text-slate-400">(Optional)</span><textarea v-model="createForm.referral_reason" rows="2" maxlength="5000" class="mt-1 w-full rounded-lg border-slate-200 text-sm focus:border-amber-500 focus:ring-amber-500" placeholder="Why is this applicant being referred?" /></label>
                        <label class="text-xs font-semibold text-slate-600 sm:col-span-2">Instructions for Agency <span class="font-normal text-slate-400">(Optional)</span><textarea v-model="createForm.agency_instructions" rows="2" maxlength="5000" class="mt-1 w-full rounded-lg border-slate-200 text-sm focus:border-amber-500 focus:ring-amber-500" placeholder="Include next steps, interview availability, or other guidance." /></label>
                        <label class="text-xs font-semibold text-slate-600 sm:col-span-2">Approved Interview Feedback for Agency <span class="font-normal text-slate-400">(Optional)</span><textarea v-model="createForm.monti_approved_feedback" rows="2" maxlength="5000" class="mt-1 w-full rounded-lg border-slate-200 text-sm focus:border-amber-500 focus:ring-amber-500" placeholder="Share only feedback approved for the agency." /></label>
                        <div class="text-xs font-semibold text-slate-600">Applicant Eligibility<div class="mt-2 inline-flex rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700">{{ selectedApplicant?.eligibility_status || 'Select an applicant' }}</div></div>
                    </div>
                    <section class="rounded-xl border border-slate-200 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-xs font-bold text-slate-700">Applicant Documents to Share <span class="font-normal text-slate-400">(Optional but Required)</span></h3>
                                <p class="mt-0.5 text-[11px] text-slate-400">Choose applicant files or approved files submitted during onboarding.</p>
                            </div>
                            <label class="inline-flex shrink-0 cursor-pointer items-center gap-2 text-xs font-semibold text-slate-600">
                                <input type="checkbox" :checked="allShareableDocumentsSelected" :disabled="!shareableDocuments.length" @change="toggleAllShareableDocuments" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500 disabled:opacity-40" />
                                Select all
                            </label>
                            <span v-if="createForm.shared_documents.length" class="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-800">{{ createForm.shared_documents.length }} selected</span>
                        </div>
                        <div v-if="selectedApplicant?.documents?.length" class="mt-3 grid gap-2 sm:grid-cols-2">
                            <div v-for="document in selectedApplicant.documents" :key="document.key" :class="['flex items-start gap-2 rounded-lg border border-slate-100 p-2.5', document.shareable === false ? 'opacity-60' : 'hover:border-amber-200 hover:bg-amber-50/40']">
                                <input v-model="createForm.shared_documents" type="checkbox" :value="document.key" :disabled="document.shareable === false" class="mt-0.5 rounded border-slate-300 text-amber-600 focus:ring-amber-500" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-xs font-semibold text-slate-700">{{ document.name }}</span>
                                    <span class="mt-0.5 block text-[10px] text-slate-400">{{ document.source }} / {{ document.type }} / {{ document.status || (document.verified ? 'Verified' : 'Not verified') }}{{ document.shareable === false ? ' / No file or value to share' : '' }}<span v-if="document.verified"> · Verified</span></span>
                                </span>
                                <button type="button" @click="openDocumentPreview(document)" class="shrink-0 rounded-md border border-slate-200 px-2 py-1 text-[10px] font-bold text-blue-700 hover:bg-blue-50">View</button>
                            </div>
                        </div>
                        <p v-else class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">{{ selectedApplicant ? 'No applicant files or onboarding requirements are available.' : 'Select an applicant to view available documents.' }}</p>
                    </section>
                    <label class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50/60 p-3 text-xs leading-5 text-slate-700"><input v-model="createForm.consent_verified" required type="checkbox" class="mt-0.5 rounded border-amber-400 text-amber-600 focus:ring-amber-500" /><span><strong>Applicant Consent / Data-Sharing Verification</strong><br />I confirm the applicant has consented to sharing their referral information with this agency.</span></label>
                    <p v-if="createForm.errors.applicant_job_application_id || createForm.errors.agency_id || createForm.errors.consent_verified || createForm.errors.shared_documents" class="text-xs font-medium text-rose-600">{{ createForm.errors.applicant_job_application_id || createForm.errors.agency_id || createForm.errors.consent_verified || createForm.errors.shared_documents }}</p>
                    <footer class="flex justify-end gap-2 border-t border-slate-100 pt-4"><button type="button" @click="requestCloseCreate" :disabled="createForm.processing" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 disabled:opacity-50">Cancel</button><button type="submit" :disabled="createForm.processing" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white shadow-md hover:bg-slate-700 disabled:opacity-50">{{ createForm.processing ? 'Saving…' : 'Create Referral' }}</button></footer>
                </form>
            </section>
        </div>

        <div v-if="showDiscardConfirm" class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="discard-referral-title" @click.self="showDiscardConfirm = false">
            <section class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                <h2 id="discard-referral-title" class="text-lg font-bold text-slate-900">Discard this referral?</h2>
                <p class="mt-2 text-sm leading-5 text-slate-600">Your referral details will be lost if you close this form.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="showDiscardConfirm = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Keep Editing</button>
                    <button type="button" @click="discardCreate" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Discard</button>
                </div>
            </section>
        </div>

        <div v-if="showCreateSubmitConfirm" class="fixed inset-0 z-[95] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="submit-referral-title" @click.self="showCreateSubmitConfirm = false">
            <section class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                <h2 id="submit-referral-title" class="text-lg font-bold text-slate-900">Create this agency referral?</h2>
                <p class="mt-2 text-sm leading-5 text-slate-600">The referral details and selected documents will be recorded for {{ selectedApplicant?.name || 'the applicant' }} and {{ agencies.find(agency => String(agency.id) === String(createForm.agency_id))?.name || 'the selected agency' }}.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="showCreateSubmitConfirm = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Review</button>
                    <button type="button" @click="submitReferral" :disabled="createForm.processing" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-50">Confirm &amp; Create</button>
                </div>
            </section>
        </div>

        <div v-if="pendingSendReferral" class="fixed inset-0 z-[95] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="send-referral-title" @click.self="pendingSendReferral = null">
            <section class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                <h2 id="send-referral-title" class="text-lg font-bold text-slate-900">Send this referral to the agency?</h2>
                <p class="mt-2 text-sm leading-5 text-slate-600">Send {{ pendingSendReferral.applicant_name }}’s referral to {{ pendingSendReferral.agency?.name || 'the assigned agency' }}?</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="pendingSendReferral = null" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Review</button>
                    <button type="button" @click="confirmSendReferral" :disabled="busy" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-50">Confirm &amp; Send</button>
                </div>
            </section>
        </div>

        <div v-if="documentPreview" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/70 p-4" @click.self="documentPreview = null">
            <section class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <header class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                    <div class="min-w-0"><h2 class="truncate text-sm font-bold text-slate-900">{{ documentPreview.name }}</h2><p class="mt-0.5 text-[10px] text-slate-500">{{ documentPreview.source }} / {{ documentPreview.type }} / {{ documentPreview.status || (documentPreview.verified ? 'Verified' : 'Not verified') }}</p></div>
                    <button type="button" @click="documentPreview = null" class="ml-3 rounded-lg p-2 text-slate-400 hover:bg-slate-100" aria-label="Close preview"><X class="h-4 w-4" /></button>
                </header>
                <iframe v-if="documentPreview.preview_url" :src="documentPreview.preview_url" :title="`Preview ${documentPreview.name}`" class="min-h-[70vh] w-full flex-1 bg-slate-100" />
                <div v-else class="overflow-y-auto p-6">
                    <p class="text-xs font-semibold text-slate-500">{{ documentPreview.shareable ? 'Submitted information' : 'No shareable submission' }}</p>
                    <pre v-if="documentPreview.value" class="mt-3 whitespace-pre-wrap break-words rounded-xl bg-slate-50 p-4 text-sm text-slate-700">{{ documentPreview.value }}</pre>
                    <p v-else class="mt-3 text-sm text-slate-500">No uploaded file or submitted value is available for this item.</p>
                </div>
            </section>
        </div>

        <!-- Referral details, agency response, decisions, and audit history -->
        <div v-if="showDetails" class="fixed inset-0 z-[80] flex justify-end bg-slate-950/50 backdrop-blur-sm" @click.self="showDetails = false">
            <section class="flex h-full w-full max-w-2xl flex-col overflow-hidden bg-white shadow-2xl">
                <header class="flex items-start justify-between border-b border-slate-100 px-5 py-4"><div><p class="text-[10px] font-bold uppercase tracking-widest text-amber-700">{{ detail?.referral_id }}</p><h2 class="mt-1 text-lg font-bold text-slate-900">Referral Details</h2><p class="mt-0.5 text-xs text-slate-500">{{ detail?.applicant_name }} · {{ detail?.position }}</p></div><button @click="showDetails = false" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100"><X class="h-4 w-4" /></button></header>
                <div v-if="detailsLoading" class="flex flex-1 items-center justify-center text-sm text-slate-400">Loading referral…</div>
                <div v-else-if="detail" class="flex-1 space-y-5 overflow-y-auto p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3"><span :class="statusClass(detail.status)" class="rounded-full px-3 py-1.5 text-xs font-semibold">{{ detail.status }}</span><div class="flex flex-wrap gap-2"><button v-if="detail.status === 'Ready to Refer'" @click="sendReferral(detail)" class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-white">Send Referral</button><button v-if="!['Confirmed for Onboarding','Cancelled','Agency Accepted'].includes(detail.status)" @click="openWithdraw" class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50">Cancel Referral</button></div></div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <article class="rounded-xl border border-slate-100 p-3"><h3 class="text-xs font-bold text-slate-800">Applicant Information</h3><p class="mt-2 text-sm font-semibold">{{ detail.applicant_info?.name }}</p><p class="mt-1 text-xs text-slate-500">Applicant #{{ detail.applicant_info?.id }}</p><p class="text-xs text-slate-500">{{ detail.applicant_info?.email }}</p><p class="text-xs text-slate-500">{{ detail.applicant_info?.phone }}</p><p class="mt-1 text-xs text-slate-500">{{ detail.applicant_info?.address }}</p></article>
                        <article class="rounded-xl border border-slate-100 p-3"><h3 class="text-xs font-bold text-slate-800">Job Application Details</h3><p class="mt-2 text-sm font-semibold">{{ detail.application?.position }}</p><p class="mt-1 text-xs text-slate-500">{{ detail.application?.department || 'Department not set' }}</p><p class="text-xs text-slate-500">Application status: {{ detail.application?.status }}</p><p class="text-xs text-slate-500">Applied: {{ formatDate(detail.application?.applied_at) }}</p><p class="mt-1 text-xs font-semibold text-emerald-700">Monti interview: Passed</p></article>
                    </div>
                    <article class="rounded-xl border border-slate-100 p-3"><h3 class="text-xs font-bold text-slate-800">Assigned Agency</h3><p class="mt-2 text-sm font-semibold">{{ detail.agency?.name }}</p><p class="text-xs text-slate-500">{{ detail.agency?.contact_email || 'No contact email on file' }}</p><p class="mt-2 text-xs text-slate-500">Referral date {{ formatDate(detail.referral_date) }}<span v-if="detail.expected_response_date"> · Response due {{ formatDate(detail.expected_response_date) }}</span></p></article>
                    <article class="rounded-xl border border-slate-100 p-3"><h3 class="text-xs font-bold text-slate-800">Referral Instructions</h3><p class="mt-2 whitespace-pre-wrap text-sm text-slate-600">{{ detail.referral_reason }}</p><p v-if="detail.agency_instructions" class="mt-2 whitespace-pre-wrap text-xs text-slate-500">{{ detail.agency_instructions }}</p><p class="mt-2 text-[11px] font-semibold text-emerald-700">Applicant data-sharing consent verified</p></article>
                    <article class="rounded-xl border border-slate-100 p-3"><h3 class="text-xs font-bold text-slate-800">Shared Documents</h3><div v-if="detail.documents?.length" class="mt-2 flex flex-wrap gap-2"><span v-for="doc in detail.documents" :key="doc.id" class="rounded-lg bg-slate-50 px-2.5 py-1.5 text-xs text-slate-600">{{ doc.name }} · {{ doc.type }}</span></div><p v-else class="mt-2 text-xs text-slate-400">No documents selected.</p></article>
                    <article><h3 class="mb-2 text-xs font-bold text-slate-800">Monti Interview Results</h3><div v-if="detail.interviews?.length" class="space-y-2"><div v-for="(interview, i) in detail.interviews" :key="i" class="rounded-xl border border-slate-100 p-3 text-xs"><div class="flex justify-between gap-3"><span class="font-semibold">{{ interview.type || 'Interview' }} · {{ interview.result || interview.status || 'No result' }}</span><span class="text-slate-400">{{ formatDate(interview.date) }}</span></div><p v-if="interview.recommendation" class="mt-1 text-slate-500">Recommendation: {{ interview.recommendation }}</p><p v-if="interview.notes" class="mt-1 whitespace-pre-wrap text-slate-500">{{ interview.notes }}</p></div></div><p v-else class="text-xs text-slate-400">No interview record found.</p></article>
                    <article class="rounded-xl border border-slate-100 p-3"><h3 class="text-xs font-bold text-slate-800">Agency Feedback</h3><p class="mt-2 whitespace-pre-wrap text-sm text-slate-600">{{ detail.agency_feedback || 'No agency response recorded yet.' }}</p><p v-if="detail.response_received_at" class="mt-1 text-[11px] text-slate-400">Received {{ formatDateTime(detail.response_received_at) }}</p></article>


                    <article v-if="detail.decision_reason" class="rounded-xl border border-slate-100 p-3"><h3 class="text-xs font-bold text-slate-800">Monti Decision Recorded</h3><p class="mt-2 text-sm text-slate-600">{{ detail.monti_decision }} · {{ detail.decision_reason }}</p><p class="mt-1 text-[11px] text-slate-400">{{ detail.decided_by }} · {{ formatDateTime(detail.decided_at) }}</p></article>

                    <article v-if="showReferralHistory" id="referral-history"><h3 class="mb-2 text-xs font-bold text-slate-800">Referral History and Audit Log</h3><div v-if="detail.history?.length" class="space-y-2"><div v-for="(entry, i) in detail.history" :key="i" class="flex gap-3 rounded-xl border border-slate-100 p-3"><span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-amber-500" /><div class="min-w-0"><p class="text-xs font-semibold text-slate-700">{{ entry.action }}<span v-if="entry.to_status" class="font-normal text-slate-500"> · {{ entry.to_status }}</span></p><p v-if="entry.notes" class="mt-1 whitespace-pre-wrap text-xs text-slate-500">{{ entry.notes }}</p><p class="mt-1 text-[10px] text-slate-400">{{ entry.actor || 'System' }} · {{ formatDateTime(entry.at) }}</p></div></div></div><p v-else class="text-xs text-slate-400">No history yet.</p></article>
                </div>
                <footer class="flex justify-between border-t border-slate-100 px-5 py-3"><button @click="toggleReferralHistory" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-slate-900"><History class="h-4 w-4" /> {{ showReferralHistory ? 'Hide History' : 'View History' }}</button><button @click="showDetails = false" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">Close</button></footer>
            </section>
        </div>

        <!-- Withdraw confirmation -->
        <div v-if="showWithdraw" class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/50 p-4"><form @submit.prevent="withdrawReferral" class="w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl"><h2 class="font-bold">Cancel Referral</h2><p class="mt-1 text-xs text-slate-500">Add a reason to the referral history.</p><textarea v-model="withdrawReason" required rows="3" class="mt-3 w-full rounded-lg border-slate-200 text-sm" placeholder="Cancellation reason" /><div class="mt-4 flex justify-end gap-2"><button type="button" @click="showWithdraw = false" class="rounded-lg border px-3 py-2 text-xs">Back</button><button type="submit" :disabled="busy" class="rounded-lg bg-rose-700 px-3 py-2 text-xs font-semibold text-white">Cancel Referral</button></div></form></div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, ref, watch, onMounted, nextTick } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import { CheckCircle2, ChevronDown, Clock3, FileCheck2, History, Loader2, Plus, Search, Send, Users, X } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

type Agency = { id: number; code?: string; name: string; contact_email?: string | null }
type ShareableDocument = { id: number; key: string; source: string; name: string; type: string; verified: boolean; shareable?: boolean; status?: string; value?: string | null; preview_url?: string | null }
type EligibleApplicant = { job_application_id: number; applicant_id: number; name: string; position: string; department?: string; eligibility_status: string; interview_stage?: string | null; documents: ShareableDocument[] }
type Referral = { id: number; referral_id: string; applicant_id: number; applicant_name: string; position?: string; department?: string; agency?: Agency; referral_date: string; expected_response_date?: string; status: string; [key: string]: any }

const props = defineProps<{ referrals: any; agencies: Agency[]; summary: Record<string, number>; statuses: string[] }>()
const referralPage = computed(() => props.referrals ?? {})
const referrals = computed<Referral[]>(() => referralPage.value.data ?? [])
const eligibleApplicants = ref<EligibleApplicant[]>([])
const eligibleLoading = ref(false)
const eligibleLoadError = ref('')
const agencies = computed(() => props.agencies ?? [])
const statuses = computed(() => props.statuses ?? [])
const search = ref('')
const statusFilter = ref('')
const agencyFilter = ref('')
const showCreate = ref(false)
const showDiscardConfirm = ref(false)
const showCreateSubmitConfirm = ref(false)
const pendingSendReferral = ref<Referral | null>(null)
const showDetails = ref(false)
const showReferralHistory = ref(false)
const showWithdraw = ref(false)
const documentPreview = ref<ShareableDocument | null>(null)
const detail = ref<any>(null)
const detailsLoading = ref(false)
const busy = ref(false)
const withdrawReason = ref('')

const createForm = useForm({
    applicant_job_application_id: '' as number | string,
    agency_id: '' as number | string,
    referral_reason: '',
    agency_instructions: '',
    monti_approved_feedback: '',
    consent_verified: false,
    shared_documents: [] as string[],
})

const cards = computed(() => [
    { label: 'Eligible Applicants', value: props.summary?.eligible ?? 0, icon: Users, iconClass: 'bg-blue-50 text-blue-700' },
    { label: 'Pending Referrals', value: props.summary?.pending ?? 0, icon: Clock3, iconClass: 'bg-amber-50 text-amber-700' },
    { label: 'Agency Accepted', value: props.summary?.accepted ?? 0, icon: CheckCircle2, iconClass: 'bg-emerald-50 text-emerald-700' },
    { label: 'Awaiting Monti Decision', value: props.summary?.decision ?? 0, icon: FileCheck2, iconClass: 'bg-violet-50 text-violet-700' },
])

const filteredReferrals = computed(() => referrals.value.filter((item) => {
    const term = search.value.trim().toLowerCase()
    const matchesSearch = !term || [item.referral_id, item.applicant_name, item.position, item.department, String(item.applicant_id), item.agency?.name].some(value => String(value ?? '').toLowerCase().includes(term))
    return matchesSearch && (!statusFilter.value || item.status === statusFilter.value) && (!agencyFilter.value || String(item.agency?.id) === agencyFilter.value)
}))
const selectedApplicant = computed(() => eligibleApplicants.value.find(person => String(person.job_application_id) === String(createForm.applicant_job_application_id)))
const shareableDocuments = computed(() => (selectedApplicant.value?.documents || []).filter((document: ShareableDocument) => document.shareable !== false))
const allShareableDocumentsSelected = computed(() => shareableDocuments.value.length > 0 && shareableDocuments.value.every(document => createForm.shared_documents.includes(document.key)))

function toggleAllShareableDocuments() {
    const keys = shareableDocuments.value.map(document => document.key)
    if (allShareableDocumentsSelected.value) {
        createForm.shared_documents = createForm.shared_documents.filter(key => !keys.includes(key))
    } else {
        createForm.shared_documents = [...new Set([...createForm.shared_documents, ...keys])]
    }
}

watch(() => createForm.applicant_job_application_id, () => {
    createForm.shared_documents = []
})

async function loadEligibleApplicants(requestedApplicationId?: string | null) {
    eligibleLoading.value = true
    eligibleLoadError.value = ''
    try {
        const { data } = await axios.get(route('hrm.recruitment.agency-referrals.eligible'))
        eligibleApplicants.value = data.data || []
        if (requestedApplicationId) {
            const applicant = eligibleApplicants.value.find(person => String(person.job_application_id) === requestedApplicationId)
            if (applicant) createForm.applicant_job_application_id = applicant.job_application_id
        }
    } catch {
        eligibleApplicants.value = []
        eligibleLoadError.value = 'Could not load eligible applicants.'
    } finally {
        eligibleLoading.value = false
    }
}
function openCreate(requestedApplicationId?: string | null) {
    createForm.reset()
    createForm.clearErrors()
    showCreate.value = true
    void loadEligibleApplicants(requestedApplicationId)
}
function requestCloseCreate() {
    if (createForm.processing) return
    showDiscardConfirm.value = true
}
function discardCreate() {
    showDiscardConfirm.value = false
    showCreate.value = false
    createForm.reset()
    createForm.clearErrors()
}
onMounted(() => {
    const requestedApplicationId = new URLSearchParams(window.location.search).get('applicant_job_application_id')
    if (!requestedApplicationId) return
    openCreate(requestedApplicationId)
})
function requestReferralSubmit() {
    if (createForm.processing) return
    showCreateSubmitConfirm.value = true
}
function submitReferral() {
    showCreateSubmitConfirm.value = false
    createForm.post(route('hrm.recruitment.agency-referrals.store'), {
        preserveScroll: true,
        onSuccess: () => { showCreate.value = false; createForm.reset() },
    })
}
function openDocumentPreview(document: ShareableDocument) { documentPreview.value = document }
async function openDetails(item: Referral) {
    showDetails.value = true; showReferralHistory.value = false; detailsLoading.value = true; detail.value = null
    try { detail.value = (await axios.get(route('hrm.recruitment.agency-referrals.show', item.id))).data.data }
    finally { detailsLoading.value = false }
}
function refresh() { router.reload({ only: ['referrals', 'agencies', 'summary'] }) }
function mutate(url: string, data: Record<string, any> = {}, success?: () => void) {
    busy.value = true
    router.post(url, data, { preserveScroll: true, onSuccess: () => { success?.(); refresh(); if (detail.value && showDetails.value) openDetails({ id: detail.value.id } as Referral) }, onFinish: () => { busy.value = false } })
}
function sendReferral(item: Referral) { pendingSendReferral.value = item }
function confirmSendReferral() {
    const item = pendingSendReferral.value
    if (!item || busy.value) return
    pendingSendReferral.value = null
    mutate(route('hrm.recruitment.agency-referrals.send', item.id))
}
function openWithdraw() { withdrawReason.value = ''; showWithdraw.value = true }
function withdrawReferral() { if (!detail.value) return; mutate(route('hrm.recruitment.agency-referrals.withdraw', detail.value.id), { reason: withdrawReason.value }, () => { showWithdraw.value = false; showDetails.value = false }) }
async function toggleReferralHistory() {
    showReferralHistory.value = !showReferralHistory.value
    if (showReferralHistory.value) {
        await nextTick()
        document.getElementById('referral-history')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
}
function formatDate(value?: string | null) { if (!value) return '—'; const date = new Date(`${value.slice(0, 10)}T00:00:00`); return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) }
function formatDateTime(value?: string | null) { if (!value) return '—'; const date = new Date(value); return Number.isNaN(date.getTime()) ? value : date.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) }
function statusClass(status: string) {
    if (status === 'Ready to Refer') return 'bg-slate-100 text-slate-700'
    if (status === 'Pending Agency Response') return 'bg-amber-50 text-amber-800'
    if (status === 'Agency Accepted' || status === 'Confirmed for Onboarding') return 'bg-emerald-50 text-emerald-700'
    if (status === 'Awaiting Monti Decision') return 'bg-violet-50 text-violet-700'
    if (status === 'More Information Required') return 'bg-blue-50 text-blue-700'
    if (status === 'Agency Declined' || status === 'Cancelled') return 'bg-rose-50 text-rose-700'
    return 'bg-slate-100 text-slate-600'
}
</script>
