<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';
import { LifeBuoy, ChevronDown, ChevronRight, Send, CircleCheck, Flag, X, MessageSquare } from 'lucide-vue-next';

defineProps({
    reports: { type: Array, default: () => [] },
    status: { type: String, default: '' },
    ticket_no: { type: String, default: '' },
    categories: { type: Array, default: () => [] },
});

const openFaq = ref(0);

const faqs = [
    {
        q: 'How do I change the color theme?',
        a: 'Click the gear icon in the top navigation bar, open Settings, and pick Light, Dark, Blue, Red or Green. Your choice is remembered on this device; on a fresh device the system follows your browser theme until you pick one.',
    },
    {
        q: 'How do I update my profile photo and personal details?',
        a: 'Open Settings → My Profile. Your photo, name and email update under Profile information, while everything from your hiring application (address, civil status, government IDs, emergency contact, family, education, work history) is viewable on the same page and editable through the Update buttons on each section.',
    },
    {
        q: 'My sidebar says "No pages assigned yet". What do I do?',
        a: 'Every page permission on your account is still disabled. Ask your administrator or HR to grant you access — or file an Access Request below so IT sees exactly which module and pages you need.',
    },
    {
        q: 'Where do I read company memos?',
        a: 'Click the megaphone icon in the top navigation bar. Published memos addressed to you appear there newest-first; urgent ones carry a red badge.',
    },
    {
        q: 'How does the Messenger in the top bar work?',
        a: 'Click the chat icon to see personal and group conversations. Use the + button to start a new one (up to 8 people), send images or files up to 10MB, and open group settings to rename the group, change its photo or manage members.',
    },
    {
        q: 'How do I clock in from a company site?',
        a: 'Clock-in works only inside an active site radius set by IT (see IT → Geolocation). Stand inside the site perimeter with GPS on; if you are outside every radius the system blocks the attempt as a security violation.',
    },
    {
        q: 'I forgot my password. What now?',
        a: 'On the login screen click "Forgot Key?" and follow the reset email. If your account was just created, it stays inactive until HR approves it — contact HR if you cannot log in at all.',
    },
    {
        q: 'Who handles account suspension or deactivation?',
        a: 'Only the IT department can suspend or disable accounts (IT → Access Control). Employees cannot delete their own accounts — contact your IT administrator if your access needs to change.',
    },
];

const categoryLabels = {
    bug: 'Bug / Error',
    access: 'Access Request',
    data: 'Data Correction',
    feature: 'Feature Request',
    other: 'Other',
};

const statusPill = (s) => ({
    open: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    in_progress: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    resolved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
}[s] || 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300');

const fmtDate = (d) => d
    ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
    : '—';

const form = useForm({
    subject: '',
    category: 'bug',
    description: '',
    page_url: '',
});

const submitReport = () => {
    form.post(route('help.report.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('subject', 'description', 'page_url'),
    });
};

// Report detail modal: status + everything IT sent back on the ticket.
const detail = ref(null);
const detailLoading = ref(false);
const showDetail = ref(false);

const openReport = async (id) => {
    showDetail.value = true;
    detailLoading.value = true;
    detail.value = null;
    try {
        const { data } = await axios.get(route('help.report.show', id));
        detail.value = data;
    } catch {
        detail.value = { error: true };
    }
    detailLoading.value = false;
};
const closeDetail = () => {
    showDetail.value = false;
    detail.value = null;
};
</script>

<template>
    <Head title="Help Center" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="mx-auto max-w-5xl space-y-5 p-4 pb-16 sm:p-6">

                <!-- Hero -->
                <div class="relative overflow-hidden rounded-3xl bg-slate-900 p-6 text-white shadow-xl sm:p-7">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800" />
                    <div class="absolute -top-20 -right-16 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30">
                            <LifeBuoy class="h-8 w-8" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">Support</p>
                            <h1 class="mt-0.5 text-2xl font-extrabold tracking-tight sm:text-3xl">Help Center</h1>
                            <p class="mt-0.5 text-sm text-blue-100/90">Answers to common questions — and a direct line to report a problem.</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-5 lg:grid-cols-5">
                    <!-- FAQs -->
                    <div class="rounded-3xl border border-gray-100 bg-white/80 p-5 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80 sm:p-6 lg:col-span-3">
                        <h2 class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Frequently asked questions</h2>
                        <div class="mt-4 space-y-2">
                            <div v-for="(f, i) in faqs" :key="i"
                                class="overflow-hidden rounded-2xl border border-slate-100 bg-slate-50/60 dark:border-zinc-800 dark:bg-zinc-800/50">
                                <button type="button" @click="openFaq = openFaq === i ? -1 : i"
                                    class="flex w-full items-center gap-3 px-4 py-3 text-left">
                                    <span class="min-w-0 flex-1 text-sm font-black text-slate-800 dark:text-zinc-100">{{ f.q }}</span>
                                    <ChevronDown :class="['h-4 w-4 shrink-0 text-indigo-500 transition-transform', openFaq === i && 'rotate-180']" />
                                </button>
                                <p v-show="openFaq === i" class="px-4 pb-4 text-sm leading-relaxed text-slate-500 dark:text-slate-300">{{ f.a }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Report a problem -->
                    <div class="rounded-3xl border border-gray-100 bg-white/80 p-5 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80 sm:p-6 lg:col-span-2">
                        <h2 class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
                            <Flag class="h-4 w-4 text-indigo-500" /> Report a problem
                        </h2>
                        <p class="mt-1 text-[11px] text-slate-400">Goes straight into the <span class="font-black">IT Service Desk</span> queue with its own ticket number and SLA.</p>
                        <div v-if="status === 'report-submitted'"
                            class="mt-4 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300">
                            <CircleCheck class="h-4 w-4 shrink-0" />
                            <span>Report received — IT Helpdesk ticket
                                <span v-if="ticket_no" class="font-mono font-black">{{ ticket_no }}</span>
                                <span v-else>created</span>. Track it below.
                            </span>
                        </div>
                        <form @submit.prevent="submitReport" class="mt-4 space-y-3">
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Subject</label>
                                <input v-model="form.subject" type="text" placeholder="e.g. Cannot open payroll page"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                <p v-if="form.errors.subject" class="mt-1 text-[11px] font-bold text-rose-500">{{ form.errors.subject }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Category</label>
                                <select v-model="form.category"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                                    <option v-for="c in categories" :key="c" :value="c">{{ categoryLabels[c] || c }}</option>
                                </select>
                                <p v-if="form.errors.category" class="mt-1 text-[11px] font-bold text-rose-500">{{ form.errors.category }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">What happened?</label>
                                <textarea v-model="form.description" rows="4" placeholder="Describe the problem, what you clicked, and what you expected…"
                                    class="w-full resize-none rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                <p v-if="form.errors.description" class="mt-1 text-[11px] font-bold text-rose-500">{{ form.errors.description }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Page link (optional)</label>
                                <input v-model="form.page_url" type="text" placeholder="Paste the page link where it happened"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                <p v-if="form.errors.page_url" class="mt-1 text-[11px] font-bold text-rose-500">{{ form.errors.page_url }}</p>
                            </div>
                            <button type="submit" :disabled="form.processing"
                                class="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 py-2.5 text-sm font-black text-white shadow hover:bg-indigo-500 disabled:opacity-50 active:scale-95">
                                <Send class="h-4 w-4" /> {{ form.processing ? 'Sending…' : 'Send report' }}
                            </button>
                        </form>
                    </div>
                </div>

                <!-- My reports -->
                <div class="rounded-3xl border border-gray-100 bg-white/80 p-5 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80 sm:p-6">
                    <h2 class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">My reports ({{ reports.length }})</h2>
                    <div v-if="reports.length" class="mt-4 space-y-2">
                        <button v-for="r in reports" :key="r.id" type="button" @click="openReport(r.id)" title="View status and IT responses"
                            class="flex w-full flex-wrap items-center gap-3 rounded-2xl border border-slate-100 bg-slate-50/60 px-4 py-3 text-left transition hover:border-indigo-200 hover:bg-indigo-50/40 active:scale-[0.99] dark:border-zinc-800 dark:bg-zinc-800/50 dark:hover:border-indigo-800">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-black text-slate-800 dark:text-zinc-100">{{ r.subject }}</span>
                                <span class="block text-[11px] font-bold uppercase tracking-widest text-slate-400">{{ categoryLabels[r.category] || r.category }} · {{ fmtDate(r.created_at) }}</span>
                                <span v-if="r.ticket_no" class="mt-1 inline-block rounded-full bg-indigo-100 px-2 py-0.5 font-mono text-[10px] font-black text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">IT {{ r.ticket_no }}</span>
                            </span>
                            <span :class="['rounded-full px-2.5 py-0.5 text-[11px] font-black uppercase', statusPill(r.status)]">
                                {{ (r.status || 'open').replace('_', ' ') }}
                            </span>
                            <ChevronRight class="h-4 w-4 shrink-0 text-slate-300 dark:text-zinc-600" />
                        </button>
                    </div>
                    <p v-else class="mt-4 rounded-2xl border border-dashed border-slate-200 p-5 text-center text-xs font-bold text-slate-400 dark:border-zinc-700">
                        No reports filed yet — the form above sends your first one.
                    </p>
                </div>

            </div>
        </div>

        <!-- Report detail: status + everything IT sent back -->
        <Teleport to="body">
        <Transition name="modal">
            <div v-if="showDetail" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="closeDetail">
                <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-zinc-900">
                    <div class="flex shrink-0 items-center gap-2 border-b border-gray-100 bg-white/95 px-5 py-4 dark:border-zinc-800 dark:bg-zinc-900/95">
                        <Flag class="h-4 w-4 text-indigo-500" />
                        <p class="text-sm font-black text-slate-900 dark:text-white">Report details</p>
                        <button @click="closeDetail" title="Close"
                            class="ml-auto rounded-xl p-2 text-slate-400 hover:bg-gray-100 dark:hover:bg-zinc-800">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
                        <div v-if="detailLoading" class="flex flex-col items-center gap-3 py-10">
                            <span class="h-8 w-8 animate-spin rounded-full border-[3px] border-slate-200 border-t-indigo-600 dark:border-zinc-700" />
                            <p class="text-xs font-bold text-slate-400">Loading report…</p>
                        </div>
                        <div v-else-if="detail?.error" class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-xs font-bold text-slate-400 dark:border-zinc-700">
                            Could not load this report. It may have been removed.
                        </div>
                        <div v-else-if="detail" class="space-y-4">
                            <div>
                                <p class="text-base font-black text-slate-900 dark:text-white">{{ detail.subject }}</p>
                                <p class="mt-0.5 text-[11px] font-bold uppercase tracking-widest text-slate-400">
                                    {{ categoryLabels[detail.category] || detail.category }} · filed {{ fmtDate(detail.created_at) }}
                                </p>
                                <span class="mt-2 flex flex-wrap gap-1.5">
                                    <span :class="['rounded-full px-2.5 py-0.5 text-[11px] font-black uppercase', statusPill(detail.status)]">
                                        Report: {{ (detail.status || 'open').replace('_', ' ') }}
                                    </span>
                                    <span v-if="detail.ticket_no" class="rounded-full bg-indigo-100 px-2.5 py-0.5 font-mono text-[11px] font-black text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                        IT {{ detail.ticket_no }}
                                    </span>
                                </span>
                            </div>
                            <div class="rounded-2xl border border-slate-100 bg-slate-50/60 p-4 dark:border-zinc-800 dark:bg-zinc-800/50">
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">What you sent</p>
                                <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-slate-700 dark:text-slate-200">{{ detail.description }}</p>
                                <p v-if="detail.page_url" class="mt-2 truncate font-mono text-[11px] text-slate-400">{{ detail.page_url }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-100 bg-slate-50/60 p-4 dark:border-zinc-800 dark:bg-zinc-800/50">
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">IT Helpdesk ticket</p>
                                <div v-if="detail.ticket_no" class="mt-2 grid grid-cols-3 gap-2 text-center">
                                    <div class="rounded-xl bg-white px-2 py-2 dark:bg-zinc-900">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Status</p>
                                        <p class="text-xs font-black capitalize text-slate-800 dark:text-zinc-100">{{ (detail.ticket_status || '—').replace('_', ' ') }}</p>
                                    </div>
                                    <div class="rounded-xl bg-white px-2 py-2 dark:bg-zinc-900">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Priority</p>
                                        <p class="text-xs font-black text-slate-800 dark:text-zinc-100">{{ detail.ticket_priority || '—' }}</p>
                                    </div>
                                    <div class="rounded-xl bg-white px-2 py-2 dark:bg-zinc-900">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Handler</p>
                                        <p class="truncate text-xs font-black text-slate-800 dark:text-zinc-100">{{ detail.ticket_assignee || 'Unassigned' }}</p>
                                    </div>
                                </div>
                                <p v-else class="mt-2 text-xs font-bold text-slate-400">No ticket linked yet.</p>
                            </div>
                            <div>
                                <p class="mb-2 flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-slate-400">
                                    <MessageSquare class="h-3.5 w-3.5" /> Responses from IT ({{ (detail.responses || []).length }})
                                </p>
                                <div v-if="(detail.responses || []).length" class="space-y-2">
                                    <div v-for="m in detail.responses" :key="m.id"
                                        class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-3 dark:border-indigo-900/40 dark:bg-indigo-900/10">
                                        <p class="whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ m.body }}</p>
                                        <p class="mt-1 text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ m.author || 'IT Support' }} · {{ fmtDate(m.created_at) }}</p>
                                    </div>
                                </div>
                                <p v-else class="rounded-2xl border border-dashed border-slate-200 p-4 text-center text-xs font-bold text-slate-400 dark:border-zinc-700">
                                    No response from IT yet — your ticket is in the Service Desk queue.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style scoped>
.modal-enter-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.modal-enter-from { opacity: 0; transform: scale(0.97); }
.modal-leave-active { transition: opacity 0.2s ease; }
.modal-leave-to { opacity: 0; }
</style>
