<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm, usePage, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Search, UserPlus, MessageSquare, AlertCircle, CheckCircle, Clock,
    Eye, Edit, X, Plus, Trash2, ShieldCheck, UserCheck, ExternalLink,
    Paperclip, Download, FileText, Maximize2,
} from 'lucide-vue-next';

const props = defineProps({
    clients: {
        type: Array,
        default: () => []
    },
    staff: {
        type: Array,
        default: () => []
    },
    message: {
        type: String,
        default: null
    },
    permissions: {
        type: Object,
        default: () => ({})
    }
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const isManager = computed(() => user.value?.position === 'manager' || user.value?.role === 'CEO');
const canEdit = computed(() => props.permissions?.investigation === 'edit');
const canView = computed(() => props.permissions?.investigation === 'view' || canEdit.value);

// Search
const searchQuery = ref('');

const filteredClients = computed(() => {
    if (!searchQuery.value) return props.clients;
    const q = searchQuery.value.toLowerCase();
    return props.clients.filter(c =>
        c.company_name?.toLowerCase().includes(q) ||
        c.contact_person?.toLowerCase().includes(q)
    );
});

// Modals
const showFeedbackModal = ref(false);
const showAssignModal = ref(false);
const selectedClient = ref(null);

// Detail modal: track by id so the content stays fresh after status updates.
const detailFbId = ref(null);
const detailClient = ref(null);
const lightbox = ref(null); // file_url of the enlarged image
const fileViewer = ref(null); // attachment object opened in the file viewer modal
const isPdf = (a) => a?.mime === 'application/pdf' || /\.pdf$/i.test(a?.original_name || '');
const selectedFeedback = computed(() => {
    if (!detailFbId.value || !detailClient.value) return null;
    const client = (props.clients || []).find((c) => c.id === detailClient.value.id);
    return client?.feedback?.find((f) => f.id === detailFbId.value) || null;
});
const openDetail = (client, fb) => {
    detailClient.value = client;
    detailFbId.value = fb.id;
    lightbox.value = null;
};
const closeDetail = () => {
    detailFbId.value = null;
    detailClient.value = null;
    lightbox.value = null;
    fileViewer.value = null;
};

const fileSize = (bytes) => {
    const n = Number(bytes || 0);
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
    return `${(n / 1024 / 1024).toFixed(1)} MB`;
};
const fdate = (d) => d ? new Date(d).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—';

// Feedback form
const feedbackForm = useForm({
    client_id: null,
    type: 'feedback',
    subject: '',
    message: '',
    attachments: [],
});

// Pending files picked in the Add Feedback modal (with previews).
const pendingFiles = ref([]);
const fbFileInput = ref(null);
const onFbFiles = (e) => {
    const files = [...(e.target.files || [])];
    for (const f of files) {
        if (f.size > 10 * 1024 * 1024) continue; // validated again server-side
        if (pendingFiles.value.length >= 5) break;
        pendingFiles.value.push(f);
    }
    feedbackForm.attachments = [...pendingFiles.value];
    if (fbFileInput.value) fbFileInput.value.value = '';
};
const removePending = (i) => {
    pendingFiles.value.splice(i, 1);
    feedbackForm.attachments = [...pendingFiles.value];
};

// Assign form
const assignForm = useForm({
    client_id: null,
    staff_id: null
});

// Open feedback modal (only if can edit)
const openFeedbackModal = (client) => {
    if (!canEdit.value) return;
    selectedClient.value = client;
    feedbackForm.reset();
    feedbackForm.client_id = client.id;
    pendingFiles.value = [];
    showFeedbackModal.value = true;
};

// Submit feedback
const submitFeedback = () => {
    feedbackForm.post(route('crm.investigation.feedback.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            showFeedbackModal.value = false;
            feedbackForm.reset();
            pendingFiles.value = [];
        }
    });
};

// Open assign modal (only if manager and can edit)
const openAssignModal = (client) => {
    if (!isManager.value || !canEdit.value) return;
    selectedClient.value = client;
    assignForm.reset();
    assignForm.client_id = client.id;
    showAssignModal.value = true;
};

// Submit assignment
const assignStaff = () => {
    assignForm.post(route('crm.investigation.assign'), {
        preserveScroll: true,
        onSuccess: () => {
            showAssignModal.value = false;
            assignForm.reset();
        }
    });
};

// Update feedback status (only if can edit)
const updateStatus = (feedbackId, newStatus) => {
    if (!canEdit.value) return;
    router.patch(route('crm.investigation.feedback.status', feedbackId), { status: newStatus, resolution_notes: null }, {
        preserveScroll: true
    });
};

// Helper to get status badge class
const getStatusClass = (status) => {
    switch (status) {
        case 'open': return 'bg-red-100 text-red-700';
        case 'in_progress': return 'bg-yellow-100 text-yellow-700';
        case 'resolved': return 'bg-green-100 text-green-700';
        default: return 'bg-gray-100 text-gray-700';
    }
};
</script>

<template>
    <Head title="Due Diligence" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">
                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <ShieldCheck class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Eye class="h-3.5 w-3.5" /> CRM · Oversight
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">
                                Due Diligence <span class="text-blue-200">Center</span>
                            </h1>
                            <p class="text-sm text-blue-100/90">
                                Credit-worthiness checks, client feedback and staff assignments before big orders.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ filteredClients.length }} clients
                            </span>
                            <span v-if="!canEdit && permissions.investigation === 'view'" class="rounded-full bg-amber-300/90 px-3 py-1.5 text-xs font-black text-amber-950">View only</span>
                            <span v-else-if="canEdit" class="rounded-full bg-emerald-400/90 px-3 py-1.5 text-xs font-black text-emerald-950">Full access</span>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="relative mt-6">
                        <div class="relative max-w-xl">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchQuery" type="text" placeholder="Search clients..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                    </div>
                </div>

                <!-- Message (e.g., no assigned clients for staff) -->
                <div v-if="message" class="animate-fade-up rounded-2xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/10 p-4 text-sm text-amber-800 dark:text-amber-300 flex items-center gap-2">
                    <AlertCircle class="w-4 h-4 shrink-0" /> {{ message }}
                </div>

                <!-- Clients List -->
                <div v-if="filteredClients.length === 0"
                    class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                        <AlertCircle class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">No clients found.</p>
                    <p class="text-xs text-gray-400 mt-1">Try adjusting your search or check back later.</p>
                </div>

                <div v-else class="space-y-4">
                    <div v-for="(client, i) in filteredClients" :key="client.id"
                        :style="{ animationDelay: `${Math.min(i * 70, 400)}ms` }"
                        class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 overflow-hidden">
                        <!-- hover glow + accent -->
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <!-- Client Header with clickable name -->
                        <div class="relative p-5 sm:p-6 border-b border-gray-100 dark:border-zinc-800 flex flex-wrap justify-between items-center gap-4">
                            <Link :href="route('crm.customerprofile.show', client.hash_key)" class="group/link cursor-pointer min-w-0">
                                <div class="flex items-center gap-3">
                                    <div :class="['flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl overflow-hidden text-white font-black shadow-lg uppercase group-hover/link:scale-110 group-hover/link:rotate-3 transition-transform duration-300', client.logo?.logo_url ? 'bg-white' : 'bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700']">
                                        <img v-if="client.logo?.logo_url" :src="client.logo.logo_url" :alt="client.company_name" class="h-full w-full object-cover" />
                                        <span v-else>{{ (client.company_name ?? '?').charAt(0) }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <h2 class="text-base font-black tracking-tight text-gray-900 dark:text-white group-hover/link:text-indigo-600 dark:group-hover/link:text-indigo-300 transition truncate">
                                                {{ client.company_name }}
                                            </h2>
                                            <ExternalLink class="h-4 w-4 shrink-0 text-gray-400 group-hover/link:text-indigo-500 group-hover/link:translate-x-0.5 group-hover/link:-translate-y-0.5 transition-all" />
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium truncate">{{ client.contact_person }} | {{ client.email }}</p>
                                    </div>
                                </div>
                            </Link>
                            <div class="flex flex-wrap gap-2">
                                <button v-if="canEdit" @click="openFeedbackModal(client)" class="flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-3.5 py-2 text-xs font-black uppercase tracking-wide text-white shadow-lg shadow-indigo-500/25 hover:shadow-xl hover:scale-105 active:scale-95 transition-all">
                                    <MessageSquare class="w-4 h-4" /> Add Feedback
                                </button>
                                <button v-if="isManager && canEdit" @click="openAssignModal(client)" class="flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-violet-600 to-fuchsia-600 px-3.5 py-2 text-xs font-black uppercase tracking-wide text-white shadow-lg shadow-violet-500/25 hover:shadow-xl hover:scale-105 active:scale-95 transition-all">
                                    <UserPlus class="w-4 h-4" /> Assign Staff
                                </button>
                            </div>
                        </div>

                        <!-- Feedback/Complaints Table -->
                        <div class="relative overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50/80 dark:bg-zinc-800/50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Type</th>
                                        <th class="px-6 py-3 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Subject</th>
                                        <th class="px-6 py-3 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Message</th>
                                        <th class="px-6 py-3 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Status</th>
                                        <th class="px-6 py-3 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Assigned To</th>
                                        <th class="px-6 py-3 text-right text-[10px] font-black text-gray-400 uppercase tracking-widest">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                    <tr v-if="!client.feedback || client.feedback.length === 0">
                                        <td colspan="6" class="px-6 py-8 text-center text-gray-400 italic text-xs">No feedback or complaints recorded.</td>
                                    </tr>
                                    <tr v-for="fb in client.feedback" :key="fb.id" @click="openDetail(client, fb)" class="cursor-pointer hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition" title="Click to view full details">
                                        <td class="px-6 py-4">
                                            <span :class="fb.type === 'complaint' ? 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30' : 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30'" class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1">
                                                {{ fb.type }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 font-bold text-gray-900 dark:text-white">
                                            <span class="hover:text-indigo-600 dark:hover:text-indigo-300 transition">{{ fb.subject }}</span>
                                            <span v-if="fb.attachments?.length" class="ml-2 inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-zinc-800 px-2 py-0.5 text-[10px] font-black text-slate-500 dark:text-slate-300" :title="`${fb.attachments.length} file(s) attached`">
                                                <Paperclip class="w-3 h-3" /> {{ fb.attachments.length }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400 max-w-md truncate">{{ fb.message }}</td>
                                        <td class="px-6 py-4">
                                            <span :class="getStatusClass(fb.status)" class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1 ring-black/5">
                                                {{ fb.status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400 text-xs font-medium">
                                            <span class="inline-flex items-center gap-1.5"><UserCheck class="w-3.5 h-3.5 text-slate-400" />{{ fb.assignee?.name || 'Unassigned' }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-right" @click.stop>
                                            <div v-if="canEdit" class="flex justify-end gap-1.5">
                                                <button @click="openDetail(client, fb)" class="p-2 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-200 hover:scale-110 active:scale-95 transition-all" title="View full details">
                                                    <Eye class="w-4 h-4" />
                                                </button>
                                                <button v-if="fb.status !== 'resolved'" @click="updateStatus(fb.id, 'resolved')" class="p-2 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200 hover:scale-110 active:scale-95 transition-all" title="Mark Resolved">
                                                    <CheckCircle class="w-4 h-4" />
                                                </button>
                                                <button v-if="fb.status === 'open'" @click="updateStatus(fb.id, 'in_progress')" class="p-2 rounded-xl bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 hover:bg-amber-200 hover:scale-110 active:scale-95 transition-all" title="Mark In Progress">
                                                    <Clock class="w-4 h-4" />
                                                </button>
                                            </div>
                                            <button v-else @click="openDetail(client, fb)" class="p-2 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-500 hover:bg-indigo-100 hover:text-indigo-600 transition-all" title="View full details">
                                                <Eye class="w-4 h-4" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Feedback Modal -->
            <Transition name="modal">
                <div v-if="showFeedbackModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showFeedbackModal = false">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 p-5 text-white flex justify-between items-center relative overflow-hidden">
                            <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl" />
                            <h2 class="relative text-base font-black flex items-center gap-2"><MessageSquare class="w-4 h-4" /> Add Feedback / Complaint</h2>
                            <button @click="showFeedbackModal = false" class="relative flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 hover:bg-white/35 hover:rotate-90 transition-all"><X class="w-4 h-4" /></button>
                        </div>
                        <form @submit.prevent="submitFeedback" class="p-6 space-y-4">
                            <input type="hidden" v-model="feedbackForm.client_id" />
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Type</label>
                                <div class="grid grid-cols-2 gap-2 mt-2">
                                    <label :class="feedbackForm.type==='feedback' ? 'ring-2 ring-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'bg-gray-50 dark:bg-zinc-800'"
                                        class="flex cursor-pointer items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-bold transition-all active:scale-95">
                                        <input type="radio" value="feedback" v-model="feedbackForm.type" class="hidden" /> Feedback
                                    </label>
                                    <label :class="feedbackForm.type==='complaint' ? 'ring-2 ring-rose-500 bg-rose-50 dark:bg-rose-900/20' : 'bg-gray-50 dark:bg-zinc-800'"
                                        class="flex cursor-pointer items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-bold transition-all active:scale-95">
                                        <input type="radio" value="complaint" v-model="feedbackForm.type" class="hidden" /> Complaint
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Subject</label>
                                <input type="text" v-model="feedbackForm.subject" required placeholder="e.g. Delivery follow-up"
                                    class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-900 outline-none text-sm transition" />
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Message</label>
                                <textarea v-model="feedbackForm.message" rows="3" required placeholder="Write details..."
                                    class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-900 outline-none text-sm transition resize-none"></textarea>
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Photos / Files (up to 5)</label>
                                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-200 dark:border-zinc-700 px-4 py-3 text-xs font-bold text-gray-400 hover:border-indigo-300 hover:text-indigo-500 transition">
                                    <Paperclip class="w-4 h-4" /> Attach images or documents
                                    <input ref="fbFileInput" type="file" multiple accept="image/*,.pdf,.doc,.docx" @change="onFbFiles" class="hidden" />
                                </label>
                                <div v-if="pendingFiles.length" class="mt-2 space-y-1.5">
                                    <div v-for="(f, i) in pendingFiles" :key="i" class="flex items-center gap-2 rounded-xl bg-slate-50 dark:bg-zinc-800 px-3 py-1.5 text-xs">
                                        <FileText class="w-3.5 h-3.5 shrink-0 text-indigo-500" />
                                        <span class="min-w-0 flex-1 truncate font-bold text-gray-600 dark:text-gray-300">{{ f.name }}</span>
                                        <span class="shrink-0 text-[10px] text-gray-400">{{ fileSize(f.size) }}</span>
                                        <button type="button" @click="removePending(i)" class="shrink-0 rounded-full p-0.5 text-gray-300 hover:bg-rose-100 hover:text-rose-500" title="Remove"><X class="w-3.5 h-3.5" /></button>
                                    </div>
                                </div>
                                <p class="text-[10px] text-gray-400 mt-1">Max 10MB each. JPG, PNG, GIF, WebP, PDF, DOC.</p>
                            </div>
                            <div class="flex gap-3 pt-1">
                                <button type="button" @click="showFeedbackModal = false" class="flex-1 py-2.5 rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-500 font-bold text-sm hover:bg-gray-200 active:scale-95 transition">Cancel</button>
                                <button type="submit" :disabled="feedbackForm.processing" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-black text-sm shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:scale-[1.02] active:scale-95 transition-all disabled:opacity-60">Submit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>

            <!-- Assign Staff Modal -->
            <Transition name="modal">
                <div v-if="showAssignModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showAssignModal = false">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden">
                        <div class="bg-gradient-to-r from-violet-700 via-purple-700 to-fuchsia-700 p-5 text-white flex justify-between items-center relative overflow-hidden">
                            <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl" />
                            <h2 class="relative text-base font-black flex items-center gap-2"><UserPlus class="w-4 h-4" /> Assign Staff to Client</h2>
                            <button @click="showAssignModal = false" class="relative flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 hover:bg-white/35 hover:rotate-90 transition-all"><X class="w-4 h-4" /></button>
                        </div>
                        <form @submit.prevent="assignStaff" class="p-6 space-y-4">
                            <input type="hidden" v-model="assignForm.client_id" />
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Select Staff</label>
                                <select v-model="assignForm.staff_id" required
                                    class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent focus:border-violet-400 focus:ring-2 focus:ring-violet-200 dark:focus:ring-violet-900 outline-none text-sm transition">
                                    <option value="">-- Choose staff --</option>
                                    <option v-for="staffMember in staff" :key="staffMember.id" :value="staffMember.id">
                                        {{ staffMember.name }} ({{ staffMember.email }})
                                    </option>
                                </select>
                            </div>
                            <div class="flex gap-3 pt-1">
                                <button type="button" @click="showAssignModal = false" class="flex-1 py-2.5 rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-500 font-bold text-sm hover:bg-gray-200 active:scale-95 transition">Cancel</button>
                                <button type="submit" :disabled="assignForm.processing" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white font-black text-sm shadow-lg shadow-violet-500/30 hover:shadow-xl hover:scale-[1.02] active:scale-95 transition-all disabled:opacity-60">Assign</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>

            <!-- Feedback / Complaint Detail Modal -->
            <Transition name="modal">
                <div v-if="selectedFeedback" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeDetail">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl max-w-2xl w-full overflow-hidden max-h-[92vh] flex flex-col">
                        <div :class="selectedFeedback.type === 'complaint' ? 'from-rose-700 via-red-700 to-orange-700' : 'from-blue-700 via-indigo-700 to-violet-800'" class="bg-gradient-to-r p-5 sm:p-6 text-white flex justify-between items-start gap-3 relative overflow-hidden shrink-0">
                            <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl" />
                            <div class="relative min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-white/20 ring-1 ring-white/30">{{ selectedFeedback.type }}</span>
                                    <span :class="getStatusClass(selectedFeedback.status)" class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase">{{ selectedFeedback.status.replace('_', ' ') }}</span>
                                </div>
                                <h2 class="mt-2 text-lg font-black leading-snug">{{ selectedFeedback.subject }}</h2>
                                <p class="text-xs text-white/80 mt-0.5">{{ detailClient?.company_name }} · {{ detailClient?.contact_person }} · {{ detailClient?.email }}</p>
                            </div>
                            <button @click="closeDetail" class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white/20 hover:bg-white/35 hover:rotate-90 transition-all"><X class="w-4 h-4" /></button>
                        </div>

                        <div class="overflow-y-auto p-5 sm:p-6 space-y-5">
                            <!-- Full message -->
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Full details</p>
                                <p class="text-sm text-gray-700 dark:text-gray-200 whitespace-pre-wrap leading-relaxed rounded-2xl bg-slate-50 dark:bg-zinc-800 p-4">{{ selectedFeedback.message }}</p>
                            </div>

                            <!-- Meta -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                                <div class="rounded-xl bg-slate-50 dark:bg-zinc-800 px-3 py-2">
                                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Reported</p>
                                    <p class="font-bold text-gray-700 dark:text-gray-200 mt-0.5">{{ fdate(selectedFeedback.created_at) }}</p>
                                </div>
                                <div class="rounded-xl bg-slate-50 dark:bg-zinc-800 px-3 py-2">
                                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Assignee</p>
                                    <p class="font-bold text-gray-700 dark:text-gray-200 mt-0.5">{{ selectedFeedback.assignee?.name || 'Unassigned' }}</p>
                                </div>
                                <div class="rounded-xl bg-slate-50 dark:bg-zinc-800 px-3 py-2">
                                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Resolved</p>
                                    <p class="font-bold text-gray-700 dark:text-gray-200 mt-0.5">{{ selectedFeedback.resolved_at ? fdate(selectedFeedback.resolved_at) : '—' }}</p>
                                </div>
                                <div class="rounded-xl bg-slate-50 dark:bg-zinc-800 px-3 py-2">
                                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Files</p>
                                    <p class="font-bold text-gray-700 dark:text-gray-200 mt-0.5">{{ selectedFeedback.attachments?.length || 0 }}</p>
                                </div>
                            </div>

                            <!-- Resolution notes -->
                            <div v-if="selectedFeedback.resolution_notes">
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Resolution notes</p>
                                <p class="text-sm text-emerald-700 dark:text-emerald-300 whitespace-pre-wrap leading-relaxed rounded-2xl bg-emerald-50 dark:bg-emerald-900/15 border border-emerald-100 dark:border-emerald-900/40 p-4">{{ selectedFeedback.resolution_notes }}</p>
                            </div>

                            <!-- Attachments: image gallery + file rows -->
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">
                                    Photos &amp; files {{ selectedFeedback.attachments?.length ? `(${selectedFeedback.attachments.length})` : '' }}
                                </p>
                                <div v-if="selectedFeedback.attachments?.length" class="space-y-3">
                                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                                        <button
                                            v-for="a in selectedFeedback.attachments.filter((x) => x.is_image)"
                                            :key="a.id"
                                            @click="lightbox = a.file_url"
                                            class="group relative aspect-square overflow-hidden rounded-2xl bg-slate-100 dark:bg-zinc-800 ring-1 ring-black/5"
                                            :title="`${a.original_name} — click to enlarge`"
                                        >
                                            <img :src="a.file_url" :alt="a.original_name" loading="lazy" class="h-full w-full object-cover transition group-hover:scale-105" />
                                            <span class="absolute inset-0 flex items-center justify-center bg-black/0 transition group-hover:bg-black/30">
                                                <Maximize2 class="h-5 w-5 text-white opacity-0 transition group-hover:opacity-100" />
                                            </span>
                                        </button>
                                    </div>
                                    <div class="space-y-1.5">
                                        <button
                                            v-for="a in selectedFeedback.attachments.filter((x) => !x.is_image)"
                                            :key="a.id"
                                            @click="fileViewer = a"
                                            class="flex w-full items-center gap-2.5 rounded-2xl bg-slate-50 dark:bg-zinc-800 px-3.5 py-2.5 text-xs transition hover:bg-indigo-50 dark:hover:bg-indigo-900/20"
                                            :title="`View ${a.original_name}`"
                                        >
                                            <FileText class="w-4 h-4 shrink-0 text-indigo-500" />
                                            <span class="min-w-0 flex-1 truncate text-left font-bold text-gray-700 dark:text-gray-200">{{ a.original_name }}</span>
                                            <span class="shrink-0 text-[10px] text-gray-400">{{ fileSize(a.size) }}</span>
                                            <Eye class="w-3.5 h-3.5 shrink-0 text-gray-400" />
                                        </button>
                                    </div>
                                </div>
                                <p v-else class="rounded-2xl border border-dashed border-gray-200 dark:border-zinc-700 py-5 text-center text-xs text-gray-400">No photos or files were sent with this {{ selectedFeedback.type }}.</p>
                            </div>

                            <!-- Quick status actions -->
                            <div v-if="canEdit && selectedFeedback.status !== 'resolved'" class="flex gap-2 pt-1">
                                <button v-if="selectedFeedback.status === 'open'" @click="updateStatus(selectedFeedback.id, 'in_progress')" class="flex-1 py-2.5 rounded-xl bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 font-black text-xs uppercase hover:bg-amber-200 active:scale-95 transition">Mark In Progress</button>
                                <button @click="updateStatus(selectedFeedback.id, 'resolved')" class="flex-1 py-2.5 rounded-xl bg-emerald-600 text-white font-black text-xs uppercase hover:bg-emerald-700 active:scale-95 transition">Mark Resolved</button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Image lightbox -->
            <Transition name="modal">
                <div v-if="lightbox" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/85 backdrop-blur-sm" @click.self="lightbox = null">
                    <button @click="lightbox = null" class="absolute top-4 right-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-white hover:bg-white/30 hover:rotate-90 transition-all"><X class="w-5 h-5" /></button>
                    <img :src="lightbox" alt="Attachment preview" class="max-h-[88vh] max-w-[92vw] rounded-2xl object-contain shadow-2xl" />
                </div>
            </Transition>

            <!-- File viewer modal (PDF/DOC preview + Close / Download) -->
            <Transition name="modal">
                <div v-if="fileViewer" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" @click.self="fileViewer = null">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl max-w-3xl w-full overflow-hidden max-h-[90vh] flex flex-col">
                        <div class="flex items-center gap-2.5 px-5 py-4 border-b border-gray-100 dark:border-zinc-800 shrink-0">
                            <FileText class="w-5 h-5 shrink-0 text-indigo-500" />
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate text-sm font-black text-gray-900 dark:text-white">{{ fileViewer.original_name }}</h3>
                                <p class="text-[10px] text-gray-400">{{ fileViewer.mime || 'file' }} · {{ fileSize(fileViewer.size) }}</p>
                            </div>
                            <button @click="fileViewer = null" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-500 hover:bg-gray-200 hover:rotate-90 transition-all" title="Close" aria-label="Close viewer"><X class="w-4 h-4" /></button>
                        </div>

                        <!-- PDF renders inline; office docs can't render locally -->
                        <div class="min-h-[240px] flex-1 overflow-hidden bg-slate-100 dark:bg-zinc-800/50">
                            <iframe
                                v-if="isPdf(fileViewer)"
                                :src="fileViewer.file_url"
                                title="PDF preview"
                                class="h-[60vh] w-full border-0"
                            />
                            <div v-else class="flex h-[40vh] flex-col items-center justify-center gap-2 p-8 text-center">
                                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-100 dark:bg-indigo-900/30">
                                    <FileText class="h-7 w-7 text-indigo-500" />
                                </div>
                                <p class="text-sm font-black text-gray-700 dark:text-gray-200">No inline preview for this file type</p>
                                <p class="max-w-xs text-xs text-gray-400">Word documents can't be previewed in the browser here — download it to view the full contents.</p>
                            </div>
                        </div>

                        <div class="flex gap-2 p-4 border-t border-gray-100 dark:border-zinc-800 shrink-0">
                            <button @click="fileViewer = null" class="flex-1 py-2.5 rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-500 font-bold text-xs uppercase hover:bg-gray-200 active:scale-95 transition">Close</button>
                            <a :href="fileViewer.file_url" :download="fileViewer.original_name" class="flex flex-1 items-center justify-center gap-1.5 py-2.5 rounded-xl bg-indigo-600 text-white font-black text-xs uppercase hover:bg-indigo-700 active:scale-95 transition">
                                <Download class="w-4 h-4" /> Download
                            </a>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </AuthenticatedLayout>
</template>

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
.modal-enter-active { transition: opacity 0.25s ease; }
.modal-enter-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from { opacity: 0; }
.modal-enter-from > div { transform: scale(0.92) translateY(14px); opacity: 0; }
.modal-leave-active { transition: opacity 0.2s ease; }
.modal-leave-to { opacity: 0; }
</style>
