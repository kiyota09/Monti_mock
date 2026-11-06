<template>
    <AgencyLayout>
    <Head title="Referred Applicants" />
    <div class="space-y-6 bg-slate-50 px-1 py-2 text-slate-800 dark:bg-zinc-900 dark:text-slate-100 sm:px-2">
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-amber-700 dark:text-amber-400">Agency Portal</p>
                <h1 class="mt-1 text-2xl font-black text-slate-900 dark:text-white">Referred Applicants</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Review applicants referred by Monti HR and submit your response.</p>
            </div>
        </header>

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <article v-for="card in cards" :key="card.label" class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-zinc-800">
                <div class="flex items-start justify-between gap-2"><div><p class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ card.label }}</p><p class="mt-2 text-2xl font-black text-slate-900 dark:text-white">{{ card.value }}</p></div><span :class="card.tone" class="rounded-xl p-2.5"><component :is="card.icon" class="h-5 w-5" /></span></div>
            </article>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-700 dark:bg-zinc-800">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 dark:border-slate-700 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="font-bold text-slate-900 dark:text-white">Referred Applicants</h2><p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ filtered.length }} referrals on this page</p></div>
                <div class="grid gap-2 sm:grid-cols-[minmax(220px,1fr)_220px]">
                    <label class="relative"><Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" /><input v-model="search" type="search" placeholder="Search applicant, role or referral ID" class="w-full rounded-lg border-slate-200 py-2 pl-9 pr-3 text-sm dark:border-slate-600 dark:bg-zinc-900" /></label>
                    <select v-model="statusFilter" class="rounded-lg border-slate-200 py-2 text-sm dark:border-slate-600 dark:bg-zinc-900"><option value="">All statuses</option><option v-for="status in filterStatuses" :key="status" :value="status">{{ status }}</option></select>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-left">
                    <thead class="bg-slate-800 text-[10px] font-bold uppercase tracking-wider text-white/90"><tr><th class="px-4 py-3">Applicant Name</th><th class="px-4 py-3">Applied Position</th><th class="px-4 py-3">Department</th><th class="px-4 py-3">Referral Date</th><th class="px-4 py-3">Referral Status</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        <tr v-for="referral in filtered" :key="referral.id" class="hover:bg-slate-50 dark:hover:bg-zinc-700/40">
                            <td class="px-4 py-3 text-sm font-semibold"><div class="flex items-center gap-2.5"><img v-if="referral.profile_photo_url" :src="referral.profile_photo_url" :alt="referral.applicant_name" class="h-9 w-9 rounded-full object-cover" /><span v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-black text-amber-800">{{ applicantInitials(referral.applicant_name) }}</span><span>{{ referral.applicant_name }}</span></div></td><td class="px-4 py-3 text-sm">{{ referral.position || '—' }}</td><td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">{{ referral.department || '—' }}</td><td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500">{{ formatDate(referral.referral_date) }}</td><td class="px-4 py-3"><span :class="statusClass(referral.status)" class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-semibold">{{ displayStatus(referral.status) }}</span></td><td class="px-4 py-3 text-right"><button @click="openReferral(referral)" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-zinc-700">View Referral</button></td>
                        </tr>
                        <tr v-if="!filtered.length"><td colspan="6" class="px-4 py-14 text-center"><div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-700"><Users class="h-5 w-5" /></div><p class="mt-3 text-sm font-bold">No referrals found</p><p class="mt-1 text-xs text-slate-400">New referrals from Monti HR will appear here.</p></td></tr>
                    </tbody>
                </table>
            </div>
            <div v-if="referralPage.links?.length > 3" class="flex flex-wrap justify-end gap-1 border-t border-slate-100 p-3 dark:border-slate-700"><button v-for="link in referralPage.links" :key="link.label" :disabled="!link.url" @click="link.url && router.visit(link.url, { preserveScroll: true })" v-html="link.label" :class="link.active ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 dark:bg-zinc-800 dark:text-slate-300'" class="min-w-9 rounded-lg border border-slate-200 px-3 py-1.5 text-xs disabled:opacity-40 dark:border-slate-600" /></div>
        </section>
    </div>

    <div v-if="showDetails" class="fixed inset-0 z-[90] flex justify-end bg-slate-950/50 backdrop-blur-sm" @click.self="showDetails = false">
        <section class="flex h-full w-full max-w-2xl flex-col overflow-hidden bg-white shadow-2xl dark:bg-zinc-900">
            <header class="flex items-start justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-700"><div><p class="text-[10px] font-bold uppercase tracking-widest text-amber-700 dark:text-amber-400">{{ detail?.referral_id }}</p><h2 class="mt-1 text-lg font-black text-slate-900 dark:text-white">Referral Details</h2><p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ detail?.applicant?.name }} · {{ detail?.position?.title }}</p></div><button @click="showDetails = false" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800"><X class="h-4 w-4" /></button></header>
            <div v-if="loading" class="flex flex-1 items-center justify-center text-sm text-slate-400">Loading referral…</div>
            <div v-else-if="detail" class="flex-1 space-y-4 overflow-y-auto p-5">
                <div class="flex flex-wrap items-center justify-between gap-3"><span :class="statusClass(detail.status)" class="rounded-full px-3 py-1.5 text-xs font-bold">{{ displayStatus(detail.status) }}</span><button v-if="detail.status === 'New Referral'" @click="startReview" :disabled="busy" class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-bold text-white hover:bg-slate-700 disabled:opacity-50">Move to Under Review</button></div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <article class="rounded-xl border border-slate-100 p-3 dark:border-slate-700"><h3 class="text-xs font-black">Applicant Information</h3><p class="mt-2 text-sm font-bold">{{ detail.applicant?.name }}</p><p class="mt-1 text-xs text-slate-600 dark:text-slate-300">{{ detail.applicant?.email || 'Email not shared' }}</p><p class="text-xs text-slate-600 dark:text-slate-300">{{ detail.applicant?.phone || 'Phone not shared' }}</p></article>
                    <article class="rounded-xl border border-slate-100 p-3 dark:border-slate-700"><h3 class="text-xs font-black">Position & Department</h3><p class="mt-2 text-sm font-bold">{{ detail.position?.title || '—' }}</p><p class="mt-1 text-xs text-slate-500">{{ detail.position?.department || 'Department not specified' }}</p><p v-if="detail.position?.posting_code" class="mt-1 text-xs text-slate-500">Posting {{ detail.position.posting_code }}</p><p v-if="detail.position?.employment_type" class="mt-1 text-xs text-slate-500">{{ detail.position.employment_type }}</p></article>
                </div>
                
                
                <article v-if="detail.applicant?.profile" class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50/70 shadow-sm dark:border-slate-700 dark:bg-zinc-800/40">
                    <div class="flex items-center gap-4 border-b border-slate-200 bg-white px-5 py-4 dark:border-slate-700 dark:bg-zinc-900">
                        <img v-if="detail.applicant.profile.profile_photo_url" :src="detail.applicant.profile.profile_photo_url" :alt="`${detail.applicant.name} profile photo`" class="h-16 w-16 shrink-0 rounded-2xl border-2 border-white object-cover shadow-md ring-1 ring-slate-200 dark:border-zinc-800 dark:ring-slate-600" />
                        <div v-else class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-lg font-black text-amber-800 shadow-sm">{{ applicantInitials(detail.applicant.name) }}</div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-amber-700">Applicant Profile</p>
                            <h3 class="mt-0.5 truncate text-base font-black text-slate-900 dark:text-white">{{ detail.applicant.name }}</h3>
                            <p class="mt-0.5 text-xs text-slate-500">Personal and professional information</p>
                        </div>
                    </div>
                    <div class="grid gap-3 p-4 sm:grid-cols-2">
                        <section v-for="section in applicantProfileSections" :key="section.title" :class="['rounded-xl border border-slate-200/80 bg-white p-3.5 dark:border-slate-700 dark:bg-zinc-900', section.title === 'Work Experience' ? 'sm:col-span-2' : '']">
                            <h4 class="mb-3 border-b border-slate-100 pb-2 text-[10px] font-black uppercase tracking-[0.12em] text-slate-500 dark:border-slate-700">{{ section.title }}</h4>
                            <dl class="grid gap-2 sm:grid-cols-2">
                                <div v-for="item in section.items" :key="item.label" :class="['min-w-0 rounded-lg bg-slate-50/80 px-3 py-2 dark:bg-zinc-800/70', section.title === 'Work Experience' ? 'sm:col-span-2' : '']">
                                    <dt class="text-[9px] font-bold uppercase tracking-wide text-slate-400">{{ item.label }}</dt>
                                    <dd class="mt-1 whitespace-pre-wrap break-words text-xs font-semibold leading-relaxed text-slate-800 dark:text-slate-200">{{ item.value }}</dd>
                                </div>
                            </dl>
                        </section>
                    </div>
                </article>
                <article class="rounded-xl border border-slate-100 p-3 dark:border-slate-700"><h3 class="text-xs font-black">Monti Instructions</h3><p class="mt-2 whitespace-pre-wrap text-sm text-slate-600 dark:text-slate-300">{{ detail.instructions || 'No additional instructions.' }}</p><p class="mt-2 text-xs text-slate-500">Referral date: {{ formatDate(detail.referral_date) }}<span v-if="detail.expected_response_date"> · Response requested by {{ formatDate(detail.expected_response_date) }}</span></p></article>
                <article class="rounded-xl border border-slate-100 p-3 dark:border-slate-700"><h3 class="text-xs font-black">Authorized Documents</h3><div v-if="detail.documents?.length" class="mt-2 space-y-2"><div v-for="document in detail.documents" :key="document.id" class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-xs dark:bg-zinc-800"><span class="min-w-0 truncate font-semibold text-slate-700 dark:text-slate-200">{{ document.name }} <span class="font-normal text-slate-400">{{ document.type }}{{ document.value ? ' / ' + document.value : '' }}</span></span><span v-if="document.download_url" class="flex shrink-0 items-center gap-2"><button type="button" @click="openDocument(document)" class="inline-flex items-center gap-1 font-bold text-blue-600 hover:underline"><Eye class="h-3.5 w-3.5" /> View</button><a :href="document.download_url" class="inline-flex items-center gap-1 font-bold text-slate-600 hover:underline dark:text-slate-300"><Download class="h-3.5 w-3.5" /> Download</a></span><span v-else-if="document.value" class="shrink-0 text-slate-500">{{ document.value }}</span></div></div><p v-else class="mt-2 text-xs text-slate-400">No documents were authorized for sharing.</p></article>
                <article v-if="detail.agency_feedback" class="rounded-xl border border-blue-100 bg-blue-50/40 p-3 dark:border-blue-900 dark:bg-blue-950/20"><h3 class="text-xs font-black">Your Response to Monti</h3><p class="mt-2 whitespace-pre-wrap text-sm">{{ detail.agency_feedback }}</p><p v-if="detail.monti_decision" class="mt-2 text-xs font-semibold text-slate-500">Monti decision: {{ detail.monti_decision }}</p></article>

                <article v-if="detail.can_respond" class="rounded-xl border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-900 dark:bg-amber-950/20"><h3 class="text-sm font-black">Submit Feedback to Monti</h3><p class="mt-1 text-xs text-slate-500">Choose your response and include the feedback Monti HR needs for its decision.</p><label class="mt-3 block text-xs font-bold text-slate-600 dark:text-slate-300">Agency feedback (optional)<textarea v-model="feedback" rows="4" maxlength="5000" class="mt-1 w-full rounded-lg border-slate-200 text-sm dark:border-slate-600 dark:bg-zinc-900" placeholder="Optional feedback for Monti HR." /></label><div class="mt-3 flex flex-wrap gap-2"><button @click="submitResponse('Accepted')" :disabled="busy" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white disabled:opacity-50"><Check class="h-4 w-4" /> Accept Referral</button><button @click="submitResponse('Declined')" :disabled="busy" class="inline-flex items-center gap-1.5 rounded-lg bg-rose-700 px-3 py-2 text-xs font-bold text-white disabled:opacity-50"><X class="h-4 w-4" /> Decline Referral</button></div></article>

                <article v-if="showReferralHistory" id="agency-referral-history"><h3 class="mb-2 text-xs font-black">Referral History</h3><div v-if="detail.history?.length" class="space-y-2"><div v-for="(entry, i) in detail.history" :key="i" class="flex gap-3 rounded-xl border border-slate-100 p-3 dark:border-slate-700"><span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-amber-500" /><div><p class="text-xs font-bold">{{ entry.action }}<span v-if="entry.to_status" class="font-normal text-slate-500"> · {{ entry.to_status }}</span></p><p v-if="entry.notes" class="mt-1 whitespace-pre-wrap text-xs text-slate-500">{{ entry.notes }}</p><p class="mt-1 text-[10px] text-slate-400">{{ entry.actor }} · {{ formatDateTime(entry.at) }}</p></div></div></div><p v-else class="text-xs text-slate-400">No referral history yet.</p></article>
            </div>
            <footer class="flex justify-between border-t border-slate-100 px-5 py-3 dark:border-slate-700"><button @click="toggleReferralHistory" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 dark:text-slate-300"><History class="h-4 w-4" /> {{ showReferralHistory ? 'Hide Referral History' : 'View Referral History' }}</button><button @click="showDetails = false" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold dark:border-slate-600">Close</button></footer>
        </section>
    </div>
    <div v-if="showDocumentPreview" class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-950/70 p-3 backdrop-blur-sm sm:p-6" @click.self="closeDocumentPreview">
        <section class="flex h-[88vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
            <header class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                <div class="min-w-0"><h2 class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ previewDocument?.name || 'Document Preview' }}</h2><p class="text-xs text-slate-500">{{ previewDocument?.type || 'Authorized document' }}</p></div>
                <div class="flex shrink-0 items-center gap-2"><a v-if="previewDocument?.download_url" :href="previewDocument.download_url" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-800 px-3 py-2 text-xs font-bold text-white hover:bg-slate-700"><Download class="h-3.5 w-3.5" /> Download</a><button type="button" @click="closeDocumentPreview" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-zinc-800" aria-label="Close preview"><X class="h-4 w-4" /></button></div>
            </header>
            <iframe v-if="previewDocument?.view_url" :src="previewDocument.view_url" :title="previewDocument.name || 'Authorized document preview'" class="min-h-0 flex-1 bg-slate-100 dark:bg-zinc-800"></iframe>
            <div v-else class="flex flex-1 items-center justify-center text-sm text-slate-500">Preview is unavailable for this document.</div>
        </section>
    </div>
    </AgencyLayout>
</template>

<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import { Check, CheckCheck, Clock3, Download, Eye, History, Search, Send, Users, X } from 'lucide-vue-next'
import AgencyLayout from '@/Layouts/AgencyLayout.vue'

type Referral = { id: number; referral_id: string; applicant_name: string; profile_photo_url?: string | null; position?: string; department?: string; referral_date: string; interview_result: string; status: string }
const props = defineProps<{ referrals: any; summary: Record<string, number> }>()
const referralPage = computed(() => props.referrals ?? {})
const referrals = computed<Referral[]>(() => referralPage.value.data ?? [])
const search = ref('')
const statusFilter = ref('')
const showDetails = ref(false)
const showReferralHistory = ref(false)
const showDocumentPreview = ref(false)
const previewDocument = ref<any>(null)
const loading = ref(false)
const busy = ref(false)
const detail = ref<any>(null)
const feedback = ref('')
const profileText = (value: unknown): string => {
    if (value === null || value === undefined || value === '') return 'Not provided'
    if (Array.isArray(value)) return value.map(item => String(item)).filter(Boolean).join(', ') || 'Not provided'
    return String(value)
}
const applicantProfileSections = computed(() => {
    const profile = detail.value?.applicant?.profile
    if (!profile) return []
    const experienceItems = Array.isArray(profile.work_experience)
        ? profile.work_experience.map((experience: any, index: number) => ({
            label: `Experience ${index + 1}`,
            value: [
                experience?.company || experience?.company_name,
                experience?.position || experience?.job_title,
                [experience?.start_date, experience?.end_date].filter(Boolean).join(' – '),
                experience?.responsibilities,
            ].filter(Boolean).join(' · ') || 'Not provided',
        }))
        : []
    const previousEmployment = [
        profile.previous_employment_company,
        profile.previous_employment_position,
        profile.previous_employment_department,
        profile.previous_employment_when,
    ].filter(Boolean).join(' · ')
    const employment = experienceItems.length
        ? experienceItems
        : [{ label: 'Work Experience', value: previousEmployment || 'Not provided' }]

    return [
        { title: 'Personal Information', items: [
            { label: 'Full Name', value: [profile.first_name, profile.middle_name, profile.last_name, profile.suffix].filter(Boolean).join(' ') || profileText(detail.value.applicant.name) },
            { label: 'Date of Birth', value: profile.date_of_birth ? formatDate(profile.date_of_birth) : 'Not provided' },
            { label: 'Place of Birth', value: profileText(profile.place_of_birth) },
            { label: 'Gender', value: profileText(profile.gender) },
            { label: 'Civil Status', value: profileText(profile.civil_status) },
            { label: 'Citizenship', value: profileText(profile.citizenship) },
            { label: 'Religion', value: profileText(profile.religion) },
            { label: 'Height', value: profile.height ? `${profile.height} cm` : 'Not provided' },
            { label: 'Weight', value: profile.weight ? `${profile.weight} kg` : 'Not provided' },
        ] },
        { title: 'Contact & Address', items: [
            { label: 'Email', value: profileText(detail.value.applicant.email) },
            { label: 'Phone', value: profileText(detail.value.applicant.phone) },
            { label: 'Street / House', value: profileText(profile.street_address) },
            { label: 'Address Line 2', value: profileText(profile.street_address_line2) },
            { label: 'Barangay', value: profileText(profile.barangay) },
            { label: 'City / Municipality', value: profileText(profile.city) },
            { label: 'Province', value: profileText(profile.province) },
            { label: 'ZIP Code', value: profileText(profile.postal_zip_code) },
        ] },
        { title: 'Emergency Contact', items: [
            { label: 'Name', value: profileText(profile.emergency_contact?.name) },
            { label: 'Relationship', value: profileText(profile.emergency_contact?.relationship) },
            { label: 'Phone', value: profileText(profile.emergency_contact?.phone) },
            { label: 'Address', value: profileText(profile.emergency_contact?.address) },
        ] },
        { title: 'Education', items: [
            { label: 'Highest Education', value: profileText(profile.highest_education) },
            { label: 'School', value: profileText(profile.school) },
            { label: 'Course', value: profileText(profile.course) },
            { label: 'Graduation Year', value: profileText(profile.graduation_year) },
            { label: 'Elementary', value: [profile.elementary_school, profile.elementary_year].filter(Boolean).join(' · ') || 'Not provided' },
            { label: 'High School', value: [profile.high_school, profile.high_year].filter(Boolean).join(' · ') || 'Not provided' },
            { label: 'College', value: [profile.college, profile.college_year].filter(Boolean).join(' · ') || 'Not provided' },
            { label: 'Vocational', value: [profile.vocational, profile.vocational_year].filter(Boolean).join(' · ') || 'Not provided' },
        ] },
        { title: 'Skills', items: [
            { label: 'Special Skills', value: profileText(profile.special_skills) },
            { label: 'Machine Operation', value: profileText(profile.machine_operation) },
        ] },
        { title: 'Work Experience', items: employment },
    ]
})
const filterStatuses = ['New Referral', 'Under Review', 'Referral Accepted', 'Referral Denied', 'More Information Required', 'Response Sent to Monti', 'Confirmed for Onboarding', 'Cancelled']
const cards = computed(() => [
    { label: 'New Referrals', value: props.summary?.new ?? 0, icon: Send, tone: 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' },
    { label: 'Pending Review', value: props.summary?.review ?? 0, icon: Clock3, tone: 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' },
    { label: 'Accepted Referrals', value: props.summary?.accepted ?? 0, icon: CheckCheck, tone: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' },
    { label: 'Declined Referrals', value: props.summary?.declined ?? 0, icon: X, tone: 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300' },
    { label: 'Awaiting Monti', value: props.summary?.awaiting_monti ?? 0, icon: Users, tone: 'bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300' },
])
const filtered = computed(() => referrals.value.filter(item => {
    const term = search.value.trim().toLowerCase()
    return (!term || [item.referral_id, item.applicant_name, item.position, item.department].some(v => String(v ?? '').toLowerCase().includes(term))) && (!statusFilter.value || displayStatus(item.status) === statusFilter.value)
}))

function displayStatus(status: string) { return status === 'Withdrawn / Cancelled' ? 'Cancelled' : status }
function applicantInitials(name?: string) {
    return (name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0]).join('').toUpperCase()
}

function openDocument(document: any) {
    previewDocument.value = document
    showDocumentPreview.value = true
}

function closeDocumentPreview() {
    showDocumentPreview.value = false
    previewDocument.value = null
}

async function openReferral(item: Referral) {
    showDetails.value = true; showReferralHistory.value = false; loading.value = true; detail.value = null; feedback.value = ''
    try { detail.value = (await axios.get(route('agency.referrals.show', item.id))).data.data }
    finally { loading.value = false }
}
function refresh() { router.reload({ only: ['referrals', 'summary'] }) }
function startReview() {
    if (!detail.value) return
    busy.value = true
    router.post(route('agency.referrals.review', detail.value.id), {}, { preserveScroll: true, onSuccess: () => { refresh(); openReferral({ id: detail.value.id } as Referral) }, onFinish: () => { busy.value = false } })
}
function submitResponse(response: 'Accepted' | 'Declined') {
    if (!detail.value) return
    busy.value = true
    router.post(route('agency.referrals.respond', detail.value.id), { response, feedback: feedback.value }, { preserveScroll: true, onSuccess: () => { refresh(); openReferral({ id: detail.value.id } as Referral) }, onFinish: () => { busy.value = false } })
}
function formatDate(value?: string | null) { if (!value) return '—'; const date = new Date(`${value.slice(0, 10)}T00:00:00`); return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) }
function formatDateTime(value?: string | null) { if (!value) return '—'; const date = new Date(value); return Number.isNaN(date.getTime()) ? value : date.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) }
function statusClass(status: string) {
    if (status === 'New Referral') return 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'
    if (status === 'Under Review') return 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300'
    if (status === 'Accepted' || status === 'Referral Accepted') return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
    if (status === 'Declined' || status === 'Referral Denied' || status === 'Withdrawn / Cancelled' || status === 'Cancelled') return 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300'
    if (status === 'Confirmed for Onboarding') return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200'
    return 'bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300'
}
async function toggleReferralHistory() {
    showReferralHistory.value = !showReferralHistory.value
    if (showReferralHistory.value) {
        await nextTick()
        document.getElementById('agency-referral-history')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
}
</script>

