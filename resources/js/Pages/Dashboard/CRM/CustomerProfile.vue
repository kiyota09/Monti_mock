<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, useForm, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Building2, User, Mail, Phone, MapPin, CreditCard, Calendar,
    MessageSquare, AlertCircle, Plus,
    FileText, ChevronLeft, Sparkles, CalendarCheck, BadgeCheck,
    Wallet, Inbox, Users, Star, Pencil, Crown, KanbanSquare, Package, Activity, Briefcase,
    FolderOpen, Download, History, Eye, X, FileImage, Image as ImageIcon, Receipt, Hash, Clock,
    KeyRound, CheckCircle2, XCircle, ShieldCheck
} from 'lucide-vue-next';

const inertiaPage = usePage();
const toast = ref({ show: false, type: 'success', message: '' });
const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 6000);
};
watch(() => inertiaPage.props.flash?.message, (msg) => { if (msg) showToast('success', msg); });

// Partner-registered representatives awaiting CRM approval.
const pendingReps = computed(() => (props.client.contacts || []).filter((c) => c.origin === 'partner' && c.status === 'pending'));
const representatives = computed(() => (props.client.contacts || []).filter((c) => c.origin === 'partner' || c.is_primary));
const contactStatus = (c) => {
    if (c.origin !== 'partner') return null;
    if (c.status === 'pending') return { label: 'Pending employee', cls: 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30' };
    if (c.status === 'rejected') return { label: 'Rejected', cls: 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30' };
    if (c.has_account) return { label: 'Has login', cls: 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30' };
    return null;
};

const props = defineProps({
    client: { type: Object, required: true },
    meetings: { type: Array, default: () => [] },
    feedback: { type: Array, default: () => [] },
    openPipeline: { type: Number, default: 0 },
    permissions: { type: Object, default: () => ({}) }
});

const canEdit = computed(() => props.permissions?.customer_profiles === 'edit');

const feedbackForm = useForm({
    client_id: props.client.id,
    type: 'feedback',
    subject: '',
    message: ''
});

const showFeedbackModal = ref(false);
const activeTab = ref('overview');

const openFeedbackModal = () => {
    if (!canEdit.value) return;
    feedbackForm.reset();
    feedbackForm.client_id = props.client.id;
    showFeedbackModal.value = true;
};

const submitFeedback = () => {
    if (!canEdit.value) return;
    feedbackForm.post(route('crm.investigation.feedback.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showFeedbackModal.value = false;
            feedbackForm.reset();
            feedbackForm.client_id = props.client.id;
        }
    });
};

const formatDate = (date) => date ? new Date(date).toLocaleDateString() : 'N/A';
const formatDateTime = (date) => date ? new Date(date).toLocaleString() : 'N/A';
const formatCurrency = (value) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value || 0);

const getStatusStyle = (status) => {
    switch (status) {
        case 'active': return 'bg-emerald-400/20 text-emerald-100 ring-emerald-300/40';
        case 'pending': return 'bg-amber-400/20 text-amber-100 ring-amber-300/40';
        case 'suspended': return 'bg-rose-400/20 text-rose-100 ring-rose-300/40';
        default: return 'bg-white/15 text-white ring-white/30';
    }
};

const getMeetingDot = (status) => {
    switch (status) {
        case 'done': return 'bg-emerald-500 shadow-emerald-500/40';
        case 'scheduled': return 'bg-amber-400 shadow-amber-400/40 animate-pulse';
        case 'cancelled': return 'bg-rose-500 shadow-rose-500/40';
        default: return 'bg-blue-500 shadow-blue-500/40';
    }
};

const getFeedbackIcon = (type) => type === 'complaint' ? AlertCircle : MessageSquare;
const initials = computed(() => (props.client.company_name ?? '?').charAt(0).toUpperCase());
const openFeedbackCount = computed(() => props.feedback.filter(f => f.status === 'open').length);
const openDeals = computed(() => (props.client.opportunities || []).filter((o) => !['won', 'lost'].includes(o.stage)));
const openCases = computed(() => (props.client.cases || []).filter((c) => ['open', 'in_progress'].includes(c.status)));

// ── Files & images (from opportunity cards + approval page uploads) ──
const documents = computed(() => props.client.documents || []);
const allFiles = computed(() => {
    const docs = documents.value.map((d) => ({
        id: 'doc-' + d.id,
        name: d.original_name || 'Untitled file',
        url: d.file_url,
        isImage: !!d.is_image,
        mime: d.mime,
        size: d.size,
        uploader: d.uploader?.name || '—',
        at: d.created_at,
        source: d.opportunity?.title ? 'Deal · ' + d.opportunity.title : 'Approval / Client upload',
    }));
    const attachments = (props.feedback || []).flatMap((fb) =>
        (fb.attachments || []).map((a) => ({
            id: 'fb-' + a.id,
            name: a.original_name || 'Attachment',
            url: a.file_url,
            isImage: !!a.is_image,
            mime: a.mime,
            size: a.size,
            uploader: fb.assignee?.name || 'Client',
            at: a.created_at,
            source: 'Feedback · ' + (fb.subject || ''),
        }))
    );
    return [...docs, ...attachments];
});
const imageFiles = computed(() => allFiles.value.filter((f) => f.isImage));
const nonImageFiles = computed(() => allFiles.value.filter((f) => !f.isImage));

const lightbox = ref(null);
const openLightbox = (file) => { lightbox.value = file; };

const formatSize = (bytes) => {
    if (!bytes) return '—';
    const units = ['B', 'KB', 'MB', 'GB'];
    let n = Number(bytes), i = 0;
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return n.toFixed(n < 10 && i > 0 ? 1 : 0) + ' ' + units[i];
};

const ordersTotal = computed(() => (props.client.purchase_orders || []).reduce((s, o) => s + parseFloat(o.total_amount || 0), 0));

// ── Unified history timeline (registration → orders) ─────────────
const timeline = computed(() => {
    const client = props.client;
    const events = [];
    const push = (at, icon, tone, title, sub) => { if (at) events.push({ at, icon, tone, title, sub }); };

    push(client.created_at, User, 'indigo', 'Client registered', (client.company_name || '') + ' onboarded as ' + (client.business_type || 'a client'));

    (client.opportunities || []).forEach((o) => {
        push(o.created_at, KanbanSquare, 'emerald', 'Deal opened · ' + o.title, 'Stage: ' + o.stage + ' · ' + formatCurrency(o.value));
        (o.histories || []).forEach((h) => push(h.created_at, KanbanSquare, 'emerald',
            'Stage moved: ' + (h.from_stage || '—') + ' → ' + h.to_stage,
            [h.changer?.name, h.notes].filter(Boolean).join(' · ')));
    });

    (client.quotations || []).forEach((q) => push(q.issue_date || q.created_at, Receipt, 'amber',
        'Quotation ' + q.quotation_number, (q.status || '') + ' · ' + formatCurrency(q.grand_total)));

    (client.purchase_orders || []).forEach((o) => push(o.created_at, Package, 'amber',
        'Order ' + o.po_number, (o.status || '') + ' · ' + formatCurrency(o.total_amount)));

    (client.activities || []).forEach((a) => push(a.created_at, Activity, 'cyan',
        (a.type ? a.type + ': ' : '') + (a.title || a.subject || 'Activity'), a.owner?.name || ''));

    (props.meetings || []).forEach((m) => push(m.created_at, Calendar, 'blue',
        'Meeting · ' + m.meeting_type, formatDateTime(m.scheduled_at) + ' · ' + m.status));

    (props.feedback || []).forEach((f) => push(f.created_at, MessageSquare, f.type === 'complaint' ? 'rose' : 'violet',
        (f.type === 'complaint' ? 'Complaint: ' : 'Feedback: ') + f.subject, f.message));

    (client.cases || []).forEach((c) => push(c.created_at, Briefcase, 'rose',
        'Case: ' + c.subject, [c.status, c.severity].filter(Boolean).join(' · ')));

    (client.documents || []).forEach((d) => push(d.created_at, FolderOpen, 'slate',
        'File uploaded: ' + d.original_name, d.uploader?.name || ''));

    return events.filter((e) => e.at).sort((a, b) => new Date(b.at) - new Date(a.at));
});

const timelineDot = (tone) => ({
    indigo: 'bg-indigo-500 shadow-indigo-500/40',
    emerald: 'bg-emerald-500 shadow-emerald-500/40',
    amber: 'bg-amber-500 shadow-amber-500/40',
    cyan: 'bg-cyan-500 shadow-cyan-500/40',
    blue: 'bg-blue-500 shadow-blue-500/40',
    violet: 'bg-violet-500 shadow-violet-500/40',
    rose: 'bg-rose-500 shadow-rose-500/40',
    slate: 'bg-slate-400 shadow-slate-400/40',
}[tone] || 'bg-gray-400');

// ── Contacts manager ─────────────────────────────────────────────
const showContactModal = ref(false);
const editingContact = ref(null);
const contactForm = useForm({
    client_id: props.client.id,
    name: '', title: '', email: '', phone: '',
    is_decision_maker: false, is_primary: false, notes: '',
});
const openContactModal = (c = null) => {
    if (!canEdit.value) return;
    editingContact.value = c;
    contactForm.reset();
    contactForm.clearErrors();
    contactForm.client_id = props.client.id;
    if (c) {
        contactForm.name = c.name || '';
        contactForm.title = c.title || '';
        contactForm.email = c.email || '';
        contactForm.phone = c.phone || '';
        contactForm.is_decision_maker = !!c.is_decision_maker;
        contactForm.is_primary = !!c.is_primary;
        contactForm.notes = c.notes || '';
    }
    showContactModal.value = true;
};
const submitContact = () => {
    if (editingContact.value) {
        contactForm.patch(route('crm.contacts.update', editingContact.value.id), {
            preserveScroll: true, onSuccess: () => { showContactModal.value = false; },
        });
    } else {
        contactForm.post(route('crm.contacts.store'), {
            preserveScroll: true, onSuccess: () => { showContactModal.value = false; },
        });
    }
};
const showPrimaryModal = ref(false);
const primaryTarget = ref(null);
const primaryProcessing = ref(false);
const currentPrimary = computed(() => (props.client.contacts || []).find((c) => c.is_primary) || null);
const primaryContact = (c) => {
    primaryTarget.value = c;
    showPrimaryModal.value = true;
};
const confirmPrimary = () => {
    if (!primaryTarget.value) return;
    primaryProcessing.value = true;
    router.post(route('crm.contacts.primary', primaryTarget.value.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            primaryProcessing.value = false;
            showPrimaryModal.value = false;
            primaryTarget.value = null;
        },
    });
};
const showApproveModal = ref(false);
const approveTarget = ref(null);
const approveProcessing = ref(false);
const approveContact = (c) => {
    approveTarget.value = c;
    showApproveModal.value = true;
};
const confirmApprove = () => {
    if (!approveTarget.value) return;
    approveProcessing.value = true;
    router.post(route('crm.contacts.create-account', approveTarget.value.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            approveProcessing.value = false;
            showApproveModal.value = false;
            approveTarget.value = null;
        },
    });
};
const rejectContact = (c) => {
    if (!confirm(`Reject ${c.name}'s representative registration?`)) return;
    router.post(route('crm.contacts.reject-account', c.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head :title="client.company_name + ' - Profile'" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-indigo-50/50 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <!-- Hero -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-700 via-blue-700 to-violet-800 text-white shadow-xl shadow-indigo-500/25">
                    <div class="absolute -top-24 right-0 h-72 w-72 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-28 -left-8 h-72 w-72 rounded-full bg-fuchsia-400/25 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.12]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative p-6 sm:p-8">
                        <div class="flex items-center gap-3">
                            <Link :href="route('crm.customerprofile.index')"
                                class="group flex h-10 w-10 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25 backdrop-blur hover:bg-white/30 hover:-translate-x-0.5 transition-all active:scale-95">
                                <ChevronLeft class="h-5 w-5 group-hover:-translate-x-0.5 transition-transform" />
                            </Link>
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> Client profile & history
                            </p>
                            <div class="ml-auto flex items-center gap-2">
                                <span :class="['rounded-full px-3 py-1.5 text-[11px] font-black uppercase tracking-wide ring-1 backdrop-blur flex items-center gap-1.5', getStatusStyle(client.status)]">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ client.status }}
                                </span>
                                <span v-if="canEdit" class="hidden sm:inline rounded-full bg-emerald-400 px-3 py-1.5 text-[11px] font-black text-emerald-950">Full access</span>
                                <span v-else class="hidden sm:inline rounded-full bg-amber-300 px-3 py-1.5 text-[11px] font-black text-amber-950">View only</span>
                            </div>
                        </div>
                        <div class="mt-5 flex flex-col sm:flex-row sm:items-center gap-5">
                            <div class="animate-pop flex h-20 w-20 items-center justify-center rounded-3xl bg-white/15 text-4xl font-black ring-2 ring-white/30 shadow-2xl backdrop-blur overflow-hidden">
                                <img v-if="client.logo?.logo_url" :src="client.logo.logo_url" :alt="client.company_name" class="h-full w-full object-cover" />
                                <span v-else>{{ initials }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h1 class="text-2xl sm:text-4xl font-black tracking-tight truncate">{{ client.company_name }}</h1>
                                <p class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-blue-100">
                                    <span class="flex items-center gap-1.5"><User class="h-4 w-4" /> {{ client.contact_person }}</span>
                                    <span class="flex items-center gap-1.5"><Mail class="h-4 w-4" /> {{ client.email }}</span>
                                    <span class="hidden md:flex items-center gap-1.5"><Phone class="h-4 w-4" /> {{ client.phone }}</span>
                                </p>
                            </div>
                        </div>
                        <!-- stat strip -->
                        <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:80ms">
                                <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-blue-100"><Wallet class="h-3.5 w-3.5" /> Credit limit</p>
                                <p class="mt-1 text-lg font-black">{{ formatCurrency(client.credit_limit) }}</p>
                            </div>
                            <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:160ms">
                                <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-blue-100"><CalendarCheck class="h-3.5 w-3.5" /> Meetings</p>
                                <p class="mt-1 text-lg font-black">{{ meetings.length }}</p>
                            </div>
                            <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:240ms">
                                <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-blue-100"><Inbox class="h-3.5 w-3.5" /> Open issues</p>
                                <p class="mt-1 text-lg font-black">{{ openFeedbackCount }}</p>
                            </div>
                            <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:320ms">
                                <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-blue-100"><BadgeCheck class="h-3.5 w-3.5" /> Member since</p>
                                <p class="mt-1 text-lg font-black">{{ formatDate(client.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                    <!-- tabs -->
                    <div class="relative flex gap-2 px-6 sm:px-8 pb-5 overflow-x-auto">
                        <button v-for="t in [['overview','Overview'],['contacts','Contacts'],['deals','Deals'],['orders','Orders'],['activities','Activities'],['cases','Cases'],['meetings','Meetings'],['feedback','Feedback'],['files','Files'],['history','History']]" :key="t[0]" @click="activeTab = t[0]"
                            :class="activeTab === t[0] ? 'bg-white text-indigo-700 shadow-lg' : 'bg-white/15 text-white hover:bg-white/25'"
                            class="rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wide backdrop-blur transition-all active:scale-95 whitespace-nowrap">
                            {{ t[1] }}
                            <span v-if="t[0]==='contacts'" class="opacity-70">· {{ client.contacts?.length ?? 0 }}</span><span v-if="t[0]==='deals'" class="opacity-70">· {{ openDeals.length }}</span><span v-if="t[0]==='orders'" class="opacity-70">· {{ client.purchase_orders?.length ?? 0 }}</span><span v-if="t[0]==='meetings'" class="opacity-70">· {{ meetings.length }}</span><span v-if="t[0]==='feedback'" class="opacity-70">· {{ feedback.length }}</span><span v-if="t[0]==='cases'" class="opacity-70">· {{ openCases.length }}</span><span v-if="t[0]==='files'" class="opacity-70">· {{ allFiles.length }}</span>
                        </button>
                    </div>
                </div>

                <!-- Overview / company info -->
                <div v-show="activeTab === 'overview'" class="animate-fade-up grid grid-cols-1 lg:grid-cols-3 gap-5">
                    <div class="lg:col-span-2 bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden hover:shadow-xl transition-shadow duration-300">
                        <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                            <Building2 class="w-5 h-5 text-indigo-600" />
                            <h2 class="text-sm font-black uppercase tracking-widest">Company Information</h2>
                        </div>
                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div v-for="(item, idx) in [
                                {icon: Building2, label: 'Company Name', value: client.company_name, bold: true, tint: 'bg-blue-50 dark:bg-blue-900/20 text-blue-600'},
                                {icon: Briefcase, label: 'Business Type', value: client.business_type, tint: 'bg-fuchsia-50 dark:bg-fuchsia-900/20 text-fuchsia-600'},
                                {icon: User, label: 'Contact Person', value: client.contact_person, bold: true, tint: 'bg-violet-50 dark:bg-violet-900/20 text-violet-600'},
                                {icon: Mail, label: 'Email Address', value: client.email, tint: 'bg-cyan-50 dark:bg-cyan-900/20 text-cyan-600'},
                                {icon: Phone, label: 'Phone Number', value: client.phone, tint: 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600'},
                                {icon: MapPin, label: 'Business Address', value: client.company_address, tint: 'bg-orange-50 dark:bg-orange-900/20 text-orange-600'},
                                {icon: MapPin, label: 'City / Province', value: [client.city, client.province].filter(Boolean).join(', '), tint: 'bg-amber-50 dark:bg-amber-900/20 text-amber-600'},
                                {icon: Hash, label: 'Postal Code', value: client.postal_code, tint: 'bg-slate-100 dark:bg-zinc-800 text-slate-500'},
                                {icon: FileText, label: 'TIN Number', value: client.tin_number, tint: 'bg-slate-100 dark:bg-zinc-800 text-slate-500'},
                                {icon: CreditCard, label: 'Credit Limit', value: formatCurrency(client.credit_limit), bold: true, tint: 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600'},
                                {icon: Clock, label: 'Payment Terms', value: client.payment_terms_days ? client.payment_terms_days + ' days' : null, tint: 'bg-teal-50 dark:bg-teal-900/20 text-teal-600'},
                                {icon: Calendar, label: 'Registered On', value: formatDate(client.created_at), tint: 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600'},
                            ]" :key="idx" class="animate-fade-up group flex items-start gap-3 rounded-2xl border border-transparent hover:border-indigo-100 dark:hover:border-indigo-900 hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 p-3 -m-3 transition-all" :style="{animationDelay: `${idx*50}ms`}">
                                <span :class="['flex h-10 w-10 shrink-0 items-center justify-center rounded-xl group-hover:scale-110 transition-transform', item.tint]"><component :is="item.icon" class="w-5 h-5" /></span>
                                <span class="min-w-0">
                                    <span class="block text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ item.label }}</span>
                                    <span :class="['block truncate text-sm text-gray-800 dark:text-gray-100', item.bold ? 'font-black' : 'font-medium']">{{ item.value || '—' }}</span>
                                </span>
                            </div>
                        </div>
                        <div v-if="client.notes" class="mx-6 mb-4 rounded-2xl border border-indigo-100 dark:border-indigo-900 bg-indigo-50/60 dark:bg-indigo-900/10 p-4">
                            <p class="flex items-center gap-1.5 text-[10px] font-black text-indigo-600 uppercase tracking-widest"><MessageSquare class="h-3.5 w-3.5" /> Account Notes</p>
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-200 whitespace-pre-line">{{ client.notes }}</p>
                        </div>
                        <div v-if="client.rejection_reason" class="mx-6 mb-6 rounded-2xl border border-red-200 bg-red-50 dark:bg-red-900/10 dark:border-red-900 p-4 animate-fade-up">
                            <p class="text-[10px] font-black text-red-600 uppercase tracking-widest">Rejection Reason</p>
                            <p class="text-sm text-red-700 dark:text-red-300 mt-1">{{ client.rejection_reason }}</p>
                        </div>
                    </div>
                    <!-- side mini timeline -->
                    <div class="space-y-5">
                        <div class="animate-fade-up bg-gradient-to-br from-indigo-600 to-violet-700 rounded-3xl p-6 text-white shadow-lg shadow-indigo-500/25 relative overflow-hidden group hover:scale-[1.01] transition-transform" style="animation-delay:150ms">
                            <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10 blur-2xl group-hover:scale-125 transition-transform duration-500" />
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-indigo-100">Relationship health</p>
                            <p class="mt-2 text-3xl font-black">{{ openFeedbackCount === 0 ? 'Excellent' : openFeedbackCount + ' open' }}</p>
                            <p class="text-xs text-indigo-100 mt-1">{{ meetings.length }} meetings · {{ feedback.length }} notes on record</p>
                            <button v-if="canEdit" @click="activeTab='feedback'; openFeedbackModal()" class="mt-4 w-full rounded-xl bg-white text-indigo-700 py-2.5 text-xs font-black uppercase tracking-wide hover:bg-indigo-50 active:scale-95 transition">+ Log feedback</button>
                        </div>
                        <div v-if="representatives.length" class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl border border-gray-100 dark:border-zinc-800 p-6" style="animation-delay:200ms">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 flex items-center gap-1.5"><ShieldCheck class="h-3.5 w-3.5 text-indigo-500" /> Representatives</p>
                            <div class="mt-3 space-y-2">
                                <div v-for="r in representatives" :key="'rep'+r.id" class="flex items-center gap-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800/60 p-2.5">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-[11px] font-black text-white uppercase">{{ (r.name || '?').charAt(0) }}</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-black truncate">{{ r.name }}</p>
                                        <p class="text-[11px] text-gray-400 truncate">{{ r.email || 'No email' }}</p>
                                    </div>
                                    <span v-if="r.is_primary" class="shrink-0 rounded-full bg-indigo-600 text-white px-2 py-0.5 text-[9px] font-black uppercase">Primary</span>
                                    <span v-else-if="contactStatus(r)" :class="['shrink-0 rounded-full px-2 py-0.5 text-[9px] font-black uppercase ring-1', contactStatus(r).cls]">{{ contactStatus(r).label }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl border border-gray-100 dark:border-zinc-800 p-6" style="animation-delay:250ms">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Latest activity</p>
                            <div class="mt-3 space-y-3">
                                <div v-for="(m, i) in meetings.slice(0,3)" :key="'m'+i" class="flex gap-3 items-start text-sm">
                                    <span :class="['mt-1.5 h-2.5 w-2.5 rounded-full shadow-lg shrink-0', getMeetingDot(m.status)]" />
                                    <div class="min-w-0"><p class="font-bold text-gray-800 dark:text-gray-100 truncate">{{ m.meeting_type }}</p><p class="text-xs text-gray-400">{{ formatDateTime(m.scheduled_at) }}</p></div>
                                </div>
                                <p v-if="meetings.length===0" class="text-xs text-gray-400 italic">No meetings yet.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contacts -->
                <div v-show="activeTab === 'contacts'" class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 flex justify-between items-center bg-gradient-to-r from-blue-50 to-transparent dark:from-blue-900/10">
                        <h2 class="text-sm font-black uppercase tracking-widest flex items-center gap-2">
                            <Users class="w-5 h-5 text-blue-600" /> People at this account
                        </h2>
                        <span v-if="pendingReps.length" class="rounded-full bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 px-3 py-1 text-[10px] font-black uppercase ring-1 ring-amber-200 dark:ring-amber-500/30">
                            {{ pendingReps.length }} awaiting approval
                        </span>
                    </div>
                    <div class="p-6">
                        <div v-if="client.contacts?.length" class="grid gap-3 md:grid-cols-2">
                            <div v-for="c in client.contacts" :key="c.id"
                                class="rounded-2xl border p-4 transition-all hover:shadow-lg"
                                :class="c.is_primary ? 'border-indigo-300 dark:border-indigo-700 bg-indigo-50/50 dark:bg-indigo-900/10' : (c.status === 'pending' ? 'border-amber-200 dark:border-amber-700/50 bg-amber-50/40 dark:bg-amber-900/10' : 'border-gray-100 dark:border-zinc-800')">
                                <div class="flex items-start gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-black text-sm flex items-center gap-1.5 flex-wrap">
                                            {{ c.name }}
                                            <Star v-if="c.is_decision_maker" class="h-3.5 w-3.5 text-amber-500" />
                                            <span v-if="contactStatus(c)" :class="['rounded-full px-2 py-0.5 text-[9px] font-black uppercase ring-1', contactStatus(c).cls]">{{ contactStatus(c).label }}</span>
                                        </p>
                                        <p class="text-[11px] font-bold text-gray-400">{{ c.title || 'No title' }}</p>
                                        <p class="text-xs text-gray-500 mt-1">{{ c.email || '—' }} · {{ c.phone || '—' }}</p>
                                    </div>
                                    <span v-if="c.is_primary" class="shrink-0 rounded-full bg-indigo-600 text-white px-2.5 py-0.5 text-[9px] font-black uppercase">Primary</span>
                                </div>

                                <!-- Partner-registered representative: CRM decides on the account -->
                                <div v-if="canEdit && c.origin === 'partner' && c.status === 'pending'" class="mt-3 flex flex-wrap gap-1.5">
                                    <button @click="approveContact(c)" class="inline-flex items-center gap-1 rounded-lg bg-gradient-to-r from-emerald-600 to-teal-600 px-3 py-1.5 text-[10px] font-black uppercase text-white shadow hover:scale-105 active:scale-95 transition"><KeyRound class="h-3 w-3" /> Create account</button>
                                    <button @click="rejectContact(c)" class="inline-flex items-center gap-1 rounded-lg bg-rose-50 dark:bg-rose-900/20 px-3 py-1.5 text-[10px] font-black uppercase text-rose-600 hover:text-rose-700 transition"><XCircle class="h-3 w-3" /> Reject</button>
                                </div>
                                <div v-else-if="canEdit && c.origin === 'partner' && c.status === 'rejected'" class="mt-3">
                                    <button @click="approveContact(c)" class="inline-flex items-center gap-1 rounded-lg bg-gray-100 dark:bg-zinc-800 px-3 py-1.5 text-[10px] font-black uppercase text-gray-500 hover:text-emerald-600 transition"><KeyRound class="h-3 w-3" /> Reconsider</button>
                                </div>

                                <div v-if="canEdit && (c.origin !== 'partner' || (c.status !== 'pending' && c.status !== 'rejected'))" class="mt-3 flex flex-wrap gap-1.5">
                                    <button v-if="c.origin !== 'partner'" @click="openContactModal(c)" class="inline-flex items-center gap-1 rounded-lg bg-gray-100 dark:bg-zinc-800 px-2.5 py-1 text-[10px] font-black uppercase text-gray-500 hover:text-indigo-600"><Pencil class="h-3 w-3" /> Edit</button>
                                    <!-- Move the main-account label from the current primary to this user -->
                                    <button v-if="!c.is_primary" @click="primaryContact(c)" class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 px-2.5 py-1 text-[10px] font-black uppercase text-indigo-600 hover:bg-indigo-100 dark:hover:bg-indigo-900/40 transition"><Crown class="h-3 w-3" /> Make main account</button>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-center text-sm font-bold text-gray-400 py-8">No contacts yet — representatives register from the partner portal.</p>
                    </div>
                </div>

                <!-- Deals -->
                <div v-show="activeTab === 'deals'" class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 flex justify-between items-center bg-gradient-to-r from-emerald-50 to-transparent dark:from-emerald-900/10">
                        <h2 class="text-sm font-black uppercase tracking-widest flex items-center gap-2">
                            <KanbanSquare class="w-5 h-5 text-emerald-600" /> Open deals · {{ formatCurrency(openPipeline) }}
                        </h2>
                        <Link :href="route('crm.opportunities')" class="text-[11px] font-black uppercase text-indigo-600 hover:underline">Pipeline →</Link>
                    </div>
                    <div class="p-6 space-y-2.5">
                        <Link v-for="o in client.opportunities" :key="o.id" :href="route('crm.opportunities.show', o.id)"
                            class="flex items-center gap-3 rounded-2xl border border-gray-100 dark:border-zinc-800 p-4 hover:shadow-lg hover:-translate-y-0.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all">
                            <span class="min-w-0 flex-1">
                                <span class="block font-black text-sm truncate">{{ o.title }}</span>
                                <span class="block text-[11px] text-gray-400">{{ o.owner?.name }} · {{ o.expected_close || 'no close date' }}</span>
                            </span>
                            <span class="rounded-full bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 px-3 py-1 text-[10px] font-black uppercase">{{ o.stage }}</span>
                            <span class="font-black text-sm">{{ formatCurrency(o.value) }}</span>
                        </Link>
                        <p v-if="!client.opportunities?.length" class="text-center text-sm font-bold text-gray-400 py-8">No deals for this account yet.</p>
                    </div>
                </div>

                <!-- Orders -->
                <div v-show="activeTab === 'orders'" class="animate-fade-up space-y-5">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                        <div class="p-6 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2 bg-gradient-to-r from-amber-50 to-transparent dark:from-amber-900/10">
                            <Package class="w-5 h-5 text-amber-600" />
                            <h2 class="text-sm font-black uppercase tracking-widest">Purchase Orders</h2>
                            <span class="ml-auto flex items-center gap-3 text-[11px] font-black">
                                <span class="rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 px-2.5 py-1">{{ client.purchase_orders?.length ?? 0 }} orders</span>
                                <span class="rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 px-2.5 py-1">{{ formatCurrency(ordersTotal) }} total</span>
                            </span>
                        </div>
                        <div class="p-6 space-y-2.5">
                            <div v-for="o in client.purchase_orders" :key="o.id"
                                class="flex flex-wrap items-center gap-3 rounded-2xl border border-gray-100 dark:border-zinc-800 p-4 hover:shadow-lg hover:border-amber-200 dark:hover:border-amber-800 transition-all">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-900/20 text-amber-600"><Package class="h-5 w-5" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-mono font-black text-sm">{{ o.po_number }}</span>
                                    <span class="block text-[11px] text-gray-400">
                                        {{ formatDate(o.created_at) }} · {{ o.items?.length ?? 0 }} item(s)
                                        <template v-if="o.delivery_date"> · delivery {{ formatDate(o.delivery_date) }}</template>
                                    </span>
                                </span>
                                <span class="rounded-full bg-gray-100 dark:bg-zinc-800 px-3 py-1 text-[10px] font-black uppercase text-gray-500">{{ (o.status || 'n/a').replace('_',' ') }}</span>
                                <span class="font-black text-sm">{{ formatCurrency(o.total_amount) }}</span>
                            </div>
                            <p v-if="!client.purchase_orders?.length" class="text-center text-sm font-bold text-gray-400 py-8">No orders yet.</p>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                        <div class="p-6 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/10">
                            <Receipt class="w-5 h-5 text-indigo-600" />
                            <h2 class="text-sm font-black uppercase tracking-widest">Quotations</h2>
                            <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-1">{{ client.quotations?.length ?? 0 }}</span>
                        </div>
                        <div class="p-6 space-y-2.5">
                            <div v-for="q in client.quotations" :key="q.id"
                                class="flex flex-wrap items-center gap-3 rounded-2xl border border-gray-100 dark:border-zinc-800 p-4">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600"><Receipt class="h-5 w-5" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-mono font-black text-sm">{{ q.quotation_number }}</span>
                                    <span class="block text-[11px] text-gray-400">
                                        {{ formatDate(q.issue_date || q.created_at) }}
                                        <template v-if="q.valid_until"> · valid until {{ formatDate(q.valid_until) }}</template>
                                        <template v-if="q.items?.length"> · {{ q.items.length }} item(s)</template>
                                    </span>
                                </span>
                                <span class="rounded-full bg-gray-100 dark:bg-zinc-800 px-3 py-1 text-[10px] font-black uppercase text-gray-500">{{ (q.status || 'draft').replace('_',' ') }}</span>
                                <span class="font-black text-sm">{{ formatCurrency(q.grand_total) }}</span>
                            </div>
                            <p v-if="!client.quotations?.length" class="text-center text-sm font-bold text-gray-400 py-8">No quotations issued.</p>
                        </div>
                    </div>
                </div>

                <!-- Activities -->
                <div v-show="activeTab === 'activities'" class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 flex justify-between items-center bg-gradient-to-r from-cyan-50 to-transparent dark:from-cyan-900/10">
                        <h2 class="text-sm font-black uppercase tracking-widest flex items-center gap-2">
                            <Activity class="w-5 h-5 text-cyan-600" /> Activity timeline
                        </h2>
                        <Link :href="route('crm.activities')" class="text-[11px] font-black uppercase text-indigo-600 hover:underline">All activities →</Link>
                    </div>
                    <div class="p-6 space-y-2.5">
                        <div v-for="a in client.activities" :key="a.id" class="rounded-2xl bg-slate-50 dark:bg-zinc-800 px-4 py-3 text-xs">
                            <p class="font-black">{{ a.subject }} <span class="ml-1 rounded-full bg-gray-200 dark:bg-zinc-700 px-2 py-0.5 text-[10px] uppercase">{{ a.type }}</span></p>
                            <p class="text-gray-400 mt-0.5">{{ a.owner?.name }} · {{ formatDateTime(a.created_at) }}{{ a.done_at ? ' · done' : '' }}</p>
                        </div>
                        <p v-if="!client.activities?.length" class="text-center text-sm font-bold text-gray-400 py-8">No activities logged.</p>
                    </div>
                </div>

                <!-- Cases -->
                <div v-show="activeTab === 'cases'" class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 flex justify-between items-center bg-gradient-to-r from-rose-50 to-transparent dark:from-rose-900/10">
                        <h2 class="text-sm font-black uppercase tracking-widest flex items-center gap-2">
                            <Briefcase class="w-5 h-5 text-rose-600" /> Cases
                        </h2>
                        <Link :href="route('crm.cases')" class="text-[11px] font-black uppercase text-indigo-600 hover:underline">All cases →</Link>
                    </div>
                    <div class="p-6 space-y-2.5">
                        <div v-for="c in client.cases" :key="c.id" class="flex items-center gap-3 rounded-2xl border border-gray-100 dark:border-zinc-800 p-4">
                            <span class="min-w-0 flex-1">
                                <span class="block font-black text-sm truncate">{{ c.subject }}</span>
                                <span class="block text-[11px] text-gray-400">{{ c.category }} · {{ c.severity }} · {{ c.owner?.name || 'unassigned' }}</span>
                            </span>
                            <span class="rounded-full bg-gray-100 dark:bg-zinc-800 px-3 py-1 text-[10px] font-black uppercase text-gray-500">{{ c.status.replace('_',' ') }}</span>
                        </div>
                        <p v-if="!client.cases?.length" class="text-center text-sm font-bold text-gray-400 py-8">No cases for this account.</p>
                    </div>
                </div>

                <!-- Meetings timeline -->
                <div v-show="activeTab === 'meetings'" class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2 bg-gradient-to-r from-blue-50 to-transparent dark:from-blue-900/10">
                        <Calendar class="w-5 h-5 text-blue-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">Meeting History</h2>
                        <span class="ml-auto rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 text-[11px] font-black px-2.5 py-1">{{ meetings.length }}</span>
                    </div>
                    <div class="p-6">
                        <div v-if="meetings.length === 0" class="text-center py-12">
                            <Calendar class="mx-auto h-10 w-10 text-gray-200 dark:text-zinc-700" />
                            <p class="mt-2 text-sm font-bold text-gray-400">No meetings scheduled for this client.</p>
                        </div>
                        <div v-else class="relative ml-2 space-y-5 before:absolute before:left-[7px] before:top-2 before:bottom-2 before:w-0.5 before:bg-gradient-to-b before:from-indigo-300 before:via-blue-200 before:to-transparent dark:before:from-indigo-800">
                            <div v-for="(meeting, i) in meetings" :key="meeting.id" class="animate-fade-up relative pl-8 group" :style="{animationDelay: `${Math.min(i*60,400)}ms`}">
                                <span :class="['absolute left-0 top-1.5 h-4 w-4 rounded-full ring-4 ring-white dark:ring-zinc-900 shadow-lg group-hover:scale-125 transition-transform', getMeetingDot(meeting.status)]" />
                                <div class="rounded-2xl border border-gray-100 dark:border-zinc-800 p-4 hover:shadow-lg hover:-translate-y-0.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all">
                                    <div class="flex justify-between items-start gap-3">
                                        <div class="min-w-0">
                                            <p class="font-black text-gray-900 dark:text-white">{{ meeting.meeting_type }}</p>
                                            <p class="text-xs text-gray-500">{{ formatDateTime(meeting.scheduled_at) }}</p>
                                            <p v-if="meeting.location" class="mt-1 text-xs text-gray-500 flex items-center gap-1"><MapPin class="h-3 w-3" /> {{ meeting.location }}</p>
                                            <p v-if="meeting.notes" class="mt-1 text-xs text-gray-500">📝 {{ meeting.notes }}</p>
                                        </div>
                                        <span class="shrink-0 px-2.5 py-1 rounded-full text-[10px] font-black uppercase" :class="{
                                            'bg-emerald-100 text-emerald-700': meeting.status === 'done',
                                            'bg-amber-100 text-amber-700': meeting.status === 'scheduled',
                                            'bg-rose-100 text-rose-700': meeting.status === 'cancelled',
                                            'bg-blue-100 text-blue-700': meeting.status === 'rescheduled' }">{{ meeting.status }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Feedback -->
                <div v-show="activeTab === 'feedback'" class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 flex justify-between items-center bg-gradient-to-r from-violet-50 to-transparent dark:from-violet-900/10">
                        <h2 class="text-sm font-black uppercase tracking-widest flex items-center gap-2">
                            <MessageSquare class="w-5 h-5 text-violet-600" /> Feedback & Complaints
                        </h2>
                        <button v-if="canEdit" @click="openFeedbackModal" class="flex items-center gap-1.5 px-4 py-2 bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-xl text-xs font-black uppercase tracking-wide hover:shadow-lg hover:shadow-indigo-500/30 hover:scale-105 active:scale-95 transition-all">
                            <Plus class="w-4 h-4" /> Add
                        </button>
                    </div>
                    <div class="p-6">
                        <div v-if="feedback.length === 0" class="text-center py-12">
                            <MessageSquare class="mx-auto h-10 w-10 text-gray-200 dark:text-zinc-700" />
                            <p class="mt-2 text-sm font-bold text-gray-400">No feedback or complaints recorded.</p>
                        </div>
                        <div v-else class="grid gap-4 md:grid-cols-2">
                            <div v-for="(fb, i) in feedback" :key="fb.id" class="animate-fade-up rounded-2xl border-l-4 p-4 hover:-translate-y-1 hover:shadow-xl transition-all duration-300"
                                :class="fb.type === 'complaint' ? 'border-l-rose-500 bg-rose-50/70 dark:bg-rose-900/10' : 'border-l-indigo-500 bg-indigo-50/70 dark:bg-indigo-900/10'"
                                :style="{animationDelay: `${Math.min(i*60,400)}ms`}">
                                <div class="flex justify-between items-start gap-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <component :is="getFeedbackIcon(fb.type)" class="w-5 h-5 shrink-0" :class="fb.type==='complaint'?'text-rose-500':'text-indigo-500'" />
                                        <h3 class="font-black text-sm text-gray-900 dark:text-white truncate">{{ fb.subject }}</h3>
                                    </div>
                                    <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-black uppercase" :class="{
                                        'bg-rose-100 text-rose-700': fb.status === 'open',
                                        'bg-amber-100 text-amber-700': fb.status === 'in_progress',
                                        'bg-emerald-100 text-emerald-700': fb.status === 'resolved' }">{{ fb.status }}</span>
                                </div>
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">{{ fb.message }}</p>
                                <div class="mt-3 flex justify-between items-center text-[11px] text-gray-400 font-medium">
                                    <span>{{ fb.assignee?.name || 'Unassigned' }}</span>
                                    <span>{{ formatDate(fb.created_at) }}</span>
                                </div>
                                <div v-if="fb.resolution_notes" class="mt-2 rounded-xl bg-white dark:bg-zinc-800 p-2.5 text-xs shadow-sm">
                                    <span class="font-black text-emerald-600">Resolution:</span> {{ fb.resolution_notes }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Files & Images -->
                <div v-show="activeTab === 'files'" class="animate-fade-up">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                        <div class="p-6 border-b border-gray-100 dark:border-zinc-800 flex flex-wrap items-center gap-2 bg-gradient-to-r from-sky-50 to-transparent dark:from-sky-900/10">
                            <FolderOpen class="w-5 h-5 text-sky-600" />
                            <h2 class="text-sm font-black uppercase tracking-widest">Files &amp; Images</h2>
                            <span class="ml-auto flex gap-2 text-[11px] font-black">
                                <span class="rounded-full bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 px-2.5 py-1">{{ allFiles.length }} total</span>
                                <span class="rounded-full bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 px-2.5 py-1">{{ imageFiles.length }} images</span>
                            </span>
                        </div>
                        <div class="p-6">
                            <p class="mb-3 flex items-center gap-1.5 text-[10px] font-black text-gray-400 uppercase tracking-widest"><ImageIcon class="h-3.5 w-3.5" /> Images</p>
                            <div v-if="imageFiles.length" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                <button v-for="f in imageFiles" :key="f.id" @click="openLightbox(f)"
                                    class="group relative aspect-square overflow-hidden rounded-2xl border border-gray-100 dark:border-zinc-800 hover:shadow-xl transition-all">
                                    <img :src="f.url" :alt="f.name" class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" />
                                    <span class="absolute inset-0 flex items-end bg-gradient-to-t from-black/70 via-black/10 to-transparent p-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <span class="text-left text-white min-w-0">
                                            <span class="block truncate text-xs font-bold">{{ f.name }}</span>
                                            <span class="block truncate text-[10px] text-white/70">{{ f.source }}</span>
                                        </span>
                                    </span>
                                    <span class="absolute top-2 right-2 flex h-7 w-7 items-center justify-center rounded-lg bg-black/50 text-white opacity-0 group-hover:opacity-100 transition"><Eye class="h-4 w-4" /></span>
                                </button>
                            </div>
                            <p v-else class="text-center text-sm font-bold text-gray-400 py-6">No images linked to this account.</p>

                            <p class="mt-6 mb-3 flex items-center gap-1.5 text-[10px] font-black text-gray-400 uppercase tracking-widest"><FileText class="h-3.5 w-3.5" /> Documents</p>
                            <div v-if="nonImageFiles.length" class="grid gap-2.5 md:grid-cols-2">
                                <a v-for="f in nonImageFiles" :key="f.id" :href="f.url" target="_blank"
                                    class="flex items-center gap-3 rounded-2xl border border-gray-100 dark:border-zinc-800 p-3.5 hover:shadow-lg hover:border-sky-200 dark:hover:border-sky-800 transition-all">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 dark:bg-sky-900/20 text-sky-600"><FileImage class="h-5 w-5" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-bold">{{ f.name }}</span>
                                        <span class="block truncate text-[11px] text-gray-400">{{ f.source }} · {{ formatSize(f.size) }} · {{ formatDate(f.at) }}</span>
                                    </span>
                                    <Download class="h-4 w-4 shrink-0 text-gray-300" />
                                </a>
                            </div>
                            <p v-else class="text-center text-sm font-bold text-gray-400 py-6">No documents linked to this account.</p>

                            <p v-if="!allFiles.length" class="text-center text-sm font-bold text-gray-400 py-10">No files or images uploaded for this client yet.</p>
                        </div>
                    </div>
                </div>

                <!-- Full history -->
                <div v-show="activeTab === 'history'" class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2 bg-gradient-to-r from-violet-50 to-transparent dark:from-violet-900/10">
                        <History class="w-5 h-5 text-violet-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">Full history</h2>
                        <span class="ml-auto rounded-full bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 text-[11px] font-black px-2.5 py-1">{{ timeline.length }} events</span>
                    </div>
                    <div class="p-6">
                        <div v-if="!timeline.length" class="text-center py-12">
                            <History class="mx-auto h-10 w-10 text-gray-200 dark:text-zinc-700" />
                            <p class="mt-2 text-sm font-bold text-gray-400">No history recorded yet.</p>
                        </div>
                        <div v-else class="relative ml-2 space-y-5 before:absolute before:left-[7px] before:top-2 before:bottom-2 before:w-0.5 before:bg-gradient-to-b before:from-indigo-300 before:via-blue-200 before:to-transparent dark:before:from-indigo-800">
                            <div v-for="(ev, i) in timeline" :key="i" class="animate-fade-up relative pl-8 group" :style="{animationDelay: `${Math.min(i*40,400)}ms`}">
                                <span :class="['absolute left-0 top-1.5 flex h-4 w-4 items-center justify-center rounded-full ring-4 ring-white dark:ring-zinc-900 shadow-lg group-hover:scale-125 transition-transform', timelineDot(ev.tone)]" />
                                <div class="rounded-2xl border border-gray-100 dark:border-zinc-800 p-4 hover:shadow-lg hover:-translate-y-0.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all">
                                    <div class="flex items-start gap-3">
                                        <component :is="ev.icon" class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                        <div class="min-w-0 flex-1">
                                            <p class="font-black text-sm text-gray-900 dark:text-white">{{ ev.title }}</p>
                                            <p v-if="ev.sub" class="mt-0.5 text-xs text-gray-500 whitespace-pre-line">{{ ev.sub }}</p>
                                        </div>
                                        <span class="shrink-0 text-[11px] font-bold text-gray-400">{{ formatDateTime(ev.at) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Make main account confirmation -->
            <Transition name="modal">
                <div v-if="showPrimaryModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showPrimaryModal = false">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden animate-fade-up">
                        <div class="bg-gradient-to-r from-indigo-600 via-blue-600 to-violet-600 p-5 flex items-center justify-between">
                            <h2 class="text-white font-black flex items-center gap-2"><Crown class="h-5 w-5" /> Make main account</h2>
                            <button @click="showPrimaryModal = false" class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 text-white hover:bg-white/35">&times;</button>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="flex items-start gap-3 rounded-2xl bg-indigo-50 dark:bg-indigo-900/15 border border-indigo-100 dark:border-indigo-900 p-4">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 text-white font-black uppercase">{{ (primaryTarget?.name || '?').charAt(0) }}</span>
                                <div class="min-w-0">
                                    <p class="font-black text-sm dark:text-white">{{ primaryTarget?.name }}</p>
                                    <p class="text-[11px] font-bold text-gray-400">{{ primaryTarget?.title || 'Contact' }}</p>
                                    <p class="text-xs text-gray-500 mt-1 flex items-center gap-1"><Mail class="h-3 w-3" /> {{ primaryTarget?.email || 'No email on file' }}</p>
                                </div>
                            </div>

                            <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                                You are about to make <strong>{{ primaryTarget?.name }}</strong> the main account for
                                <strong>{{ client.company_name }}</strong>. They will be granted full access to the partner portal.
                            </p>

                            <div v-if="currentPrimary" class="rounded-2xl bg-amber-50 dark:bg-amber-900/15 border border-amber-200 dark:border-amber-900 p-3">
                                <p class="text-[11px] font-bold text-amber-700 dark:text-amber-300 flex items-start gap-1.5">
                                    <AlertCircle class="h-3.5 w-3.5 mt-0.5 shrink-0" />
                                    {{ currentPrimary.name }} is currently the main account and will lose that status.
                                </p>
                            </div>

                            <div class="flex gap-3 pt-1">
                                <button type="button" @click="showPrimaryModal = false" class="flex-1 py-3 rounded-2xl bg-gray-100 dark:bg-zinc-800 text-gray-500 font-bold text-sm hover:bg-gray-200 active:scale-95 transition">Cancel</button>
                                <button type="button" @click="confirmPrimary" :disabled="primaryProcessing"
                                    class="flex-1 py-3 rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white font-black text-sm shadow-lg disabled:opacity-60 hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-2">
                                    <svg v-if="primaryProcessing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    <Crown v-else class="h-4 w-4" />
                                    {{ primaryProcessing ? 'Setting…' : 'Make main account' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Create representative account confirmation -->
            <Transition name="modal">
                <div v-if="showApproveModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showApproveModal = false">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden animate-fade-up">
                        <div class="bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-600 p-5 flex items-center justify-between">
                            <h2 class="text-white font-black flex items-center gap-2"><KeyRound class="h-5 w-5" /> Create representative account</h2>
                            <button @click="showApproveModal = false" class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 text-white hover:bg-white/35">&times;</button>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="flex items-start gap-3 rounded-2xl bg-emerald-50 dark:bg-emerald-900/15 border border-emerald-100 dark:border-emerald-900 p-4">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-600 to-teal-600 text-white font-black uppercase">{{ (approveTarget?.name || '?').charAt(0) }}</span>
                                <div class="min-w-0">
                                    <p class="font-black text-sm dark:text-white">{{ approveTarget?.name }}</p>
                                    <p class="text-[11px] font-bold text-gray-400">{{ approveTarget?.title || 'Representative' }}</p>
                                    <p class="text-xs text-gray-500 mt-1 flex items-center gap-1"><Mail class="h-3 w-3" /> {{ approveTarget?.email || 'No email on file' }}</p>
                                </div>
                            </div>

                            <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                                You are about to grant <strong>{{ approveTarget?.name }}</strong> a partner portal login.
                                A temporary password will be generated automatically and their sign-in credentials will be
                                emailed to them with a welcome message.
                            </p>

                            <div v-if="!approveTarget?.email" class="rounded-2xl bg-amber-50 dark:bg-amber-900/15 border border-amber-200 dark:border-amber-900 p-3">
                                <p class="text-[11px] font-bold text-amber-700 dark:text-amber-300 flex items-start gap-1.5">
                                    <AlertCircle class="h-3.5 w-3.5 mt-0.5 shrink-0" />
                                    This representative has no email address on file — the credentials will be shown here instead, so please record them.
                                </p>
                            </div>

                            <div class="flex gap-3 pt-1">
                                <button type="button" @click="showApproveModal = false" class="flex-1 py-3 rounded-2xl bg-gray-100 dark:bg-zinc-800 text-gray-500 font-bold text-sm hover:bg-gray-200 active:scale-95 transition">Cancel</button>
                                <button type="button" @click="confirmApprove" :disabled="approveProcessing"
                                    class="flex-1 py-3 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-black text-sm shadow-lg disabled:opacity-60 hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-2">
                                    <svg v-if="approveProcessing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    <KeyRound v-else class="h-4 w-4" />
                                    {{ approveProcessing ? 'Creating…' : 'Create account & send login' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Contact modal -->
            <Transition name="modal">
                <div v-if="showContactModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showContactModal = false">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden">
                        <div class="bg-gradient-to-r from-indigo-600 via-blue-600 to-violet-600 p-5 flex items-center justify-between">
                            <h2 class="text-white font-black">{{ editingContact ? 'Edit Contact' : 'Add Contact' }}</h2>
                            <button @click="showContactModal = false" class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 text-white hover:bg-white/35">&times;</button>
                        </div>
                        <form @submit.prevent="submitContact" class="p-6 space-y-4">
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Full name *</label>
                                <input v-model="contactForm.name" required placeholder="e.g. Maria Santos"
                                    class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent focus:border-indigo-400 outline-none text-sm dark:text-white" />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Title / role</label>
                                    <input v-model="contactForm.title" placeholder="e.g. Purchasing head"
                                        class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-sm dark:text-white" />
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Phone</label>
                                    <input v-model="contactForm.phone" placeholder="+63…"
                                        class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-sm dark:text-white" />
                                </div>
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Email</label>
                                <input v-model="contactForm.email" type="email" placeholder="name@company.com"
                                    class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-sm dark:text-white" />
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Notes</label>
                                <textarea v-model="contactForm.notes" rows="2" placeholder="Preferences, best time to call…"
                                    class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-sm resize-none dark:text-white"></textarea>
                            </div>
                            <label class="flex items-center gap-2 text-xs font-bold text-gray-600 dark:text-gray-300">
                                <input type="checkbox" v-model="contactForm.is_decision_maker" class="rounded border-gray-300 text-indigo-600" /> Decision maker
                            </label>
                            <label class="flex items-center gap-2 text-xs font-bold text-gray-600 dark:text-gray-300">
                                <input type="checkbox" v-model="contactForm.is_primary" class="rounded border-gray-300 text-indigo-600" /> Primary contact
                            </label>
                            <div class="flex gap-3 pt-1">
                                <button type="button" @click="showContactModal = false" class="flex-1 py-2.5 rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-500 font-bold text-sm hover:bg-gray-200 active:scale-95 transition">Cancel</button>
                                <button type="submit" :disabled="contactForm.processing" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white font-black text-sm shadow-lg disabled:opacity-60 hover:scale-[1.02] active:scale-95 transition-all">
                                    {{ contactForm.processing ? 'Saving…' : 'Save' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>

            <!-- Feedback modal -->
            <Transition name="modal">
                <div v-if="showFeedbackModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showFeedbackModal = false">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden">
                        <div class="bg-gradient-to-r from-indigo-600 via-blue-600 to-violet-600 p-5 flex items-center justify-between relative overflow-hidden">
                            <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl" />
                            <h2 class="relative text-white font-black">Add Feedback / Complaint</h2>
                            <button @click="showFeedbackModal = false" class="relative flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 text-white hover:bg-white/35 hover:rotate-90 transition-all text-xl leading-none">&times;</button>
                        </div>
                        <form @submit.prevent="submitFeedback" class="p-6 space-y-4">
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-2">Type</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <label :class="feedbackForm.type==='feedback' ? 'ring-2 ring-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'bg-gray-50 dark:bg-zinc-800'"
                                        class="flex cursor-pointer items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-bold transition-all active:scale-95">
                                        <input type="radio" value="feedback" v-model="feedbackForm.type" class="hidden" /> 💬 Feedback
                                    </label>
                                    <label :class="feedbackForm.type==='complaint' ? 'ring-2 ring-rose-500 bg-rose-50 dark:bg-rose-900/20' : 'bg-gray-50 dark:bg-zinc-800'"
                                        class="flex cursor-pointer items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-bold transition-all active:scale-95">
                                        <input type="radio" value="complaint" v-model="feedbackForm.type" class="hidden" /> ⚠️ Complaint
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Subject</label>
                                <input type="text" v-model="feedbackForm.subject" placeholder="e.g. Late delivery follow-up"
                                    class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 outline-none text-sm transition" required />
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Message</label>
                                <textarea v-model="feedbackForm.message" rows="3" placeholder="Write details..."
                                    class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 outline-none text-sm transition resize-none" required></textarea>
                            </div>
                            <div class="flex gap-3 pt-1">
                                <button type="button" @click="showFeedbackModal = false" class="flex-1 py-2.5 rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-500 font-bold text-sm hover:bg-gray-200 active:scale-95 transition">Cancel</button>
                                <button type="submit" :disabled="feedbackForm.processing" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white font-black text-sm shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:scale-[1.02] active:scale-95 transition-all disabled:opacity-60">
                                    {{ feedbackForm.processing ? 'Sending…' : 'Submit' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>

            <!-- Image lightbox -->
            <Transition name="modal">
                <div v-if="lightbox" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/85 backdrop-blur-sm" @click.self="lightbox = null">
                    <button @click="lightbox = null" class="absolute top-4 right-4 flex h-10 w-10 items-center justify-center rounded-2xl bg-white/15 text-white hover:bg-white/30 hover:rotate-90 transition-all"><X class="h-5 w-5" /></button>
                    <div class="max-w-4xl w-full">
                        <img :src="lightbox.url" :alt="lightbox.name" class="mx-auto max-h-[75vh] w-auto rounded-2xl shadow-2xl object-contain" />
                        <div class="mt-4 flex flex-wrap items-center justify-center gap-3 text-center text-white">
                            <p class="font-black">{{ lightbox.name }}</p>
                            <span class="text-xs text-white/60">{{ lightbox.source }} · {{ formatSize(lightbox.size) }} · {{ formatDate(lightbox.at) }}</span>
                            <a :href="lightbox.url" target="_blank" class="flex items-center gap-1.5 rounded-xl bg-white/15 px-4 py-2 text-xs font-black uppercase tracking-wide hover:bg-white/30 transition"><Download class="h-4 w-4" /> Download</a>
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
@keyframes pop { 0% { transform: scale(0.7); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-float-delayed { animation: float 8s ease-in-out 1.2s infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.modal-enter-active { transition: opacity 0.25s ease; }
.modal-enter-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from { opacity: 0; }
.modal-enter-from > div { transform: scale(0.92) translateY(14px); opacity: 0; }
.modal-leave-active { transition: opacity 0.2s ease; }
.modal-leave-to { opacity: 0; }
</style>
