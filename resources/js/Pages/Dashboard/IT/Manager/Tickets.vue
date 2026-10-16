<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePageAccess } from '@/composables/usePageAccess';
import { Search, Plus, X, Send, Trash2, Clock, Ticket, SearchX, MessageSquare, ChevronLeft, ChevronRight } from 'lucide-vue-next';

const { canEdit } = usePageAccess();
const canEditTickets = computed(() => canEdit('IT', 'tickets'));

const props = defineProps({
    tickets: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    itStaff: { type: Array, default: () => [] },
    isManager: { type: Boolean, default: false },
});

const showCreate = ref(false);
const selected = ref(null);
const commentBody = ref('');
const commentInternal = ref(false);
const pendingDelete = ref(null);

// Debounced search — one request per pause, not per keystroke.
const searchBox = ref(props.filters.search || '');
let searchTimer = null;
const onSearchInput = () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters({ search: searchBox.value }), 400);
};

const form = useForm({
    title: '', description: '', category: 'incident',
    system_area: 'erp', priority: 'P3', location: '',
    requester_name: '', assignee_id: '',
});

const editForm = useForm({ status: '', assignee_id: '', priority: '' });

const openDetail = (ticket) => {
    selected.value = ticket;
    editForm.status = ticket.status;
    editForm.assignee_id = ticket.assignee_id || '';
    editForm.priority = ticket.priority;
    commentBody.value = '';
};

const submitTicket = () => {
    form.post(route('it.tickets.store'), {
        preserveScroll: true,
        onSuccess: () => { showCreate.value = false; form.reset(); },
    });
};

const saveTicket = () => {
    if (!selected.value) return;
    editForm.put(route('it.tickets.update', selected.value.id), {
        preserveScroll: true,
        onSuccess: () => { selected.value = null; },
    });
};

const postComment = () => {
    if (!selected.value || !commentBody.value.trim()) return;
    router.post(route('it.tickets.comment', selected.value.id), {
        body: commentBody.value,
        is_internal: commentInternal.value,
    }, { preserveScroll: true, onSuccess: () => { commentBody.value = ''; } });
};

const askDeleteTicket = (t) => { pendingDelete.value = t; };
const closeDeleteTicket = () => { pendingDelete.value = null; };
const confirmDeleteTicket = () => {
    if (!pendingDelete.value) return;
    const id = pendingDelete.value.id;
    pendingDelete.value = null;
    if (selected.value && selected.value.id === id) selected.value = null;
    router.delete(route('it.tickets.destroy', id), { preserveScroll: true });
};

const applyFilters = (patch) => {
    router.get(route('it.tickets'), { ...props.filters, ...patch, page: 1 }, { preserveScroll: true, replace: true });
};

// Backend paginates (15/page) — previously unreachable past page 1.
const pagination = computed(() => ({
    current: props.tickets.current_page || 1,
    last: props.tickets.last_page || 1,
    from: props.tickets.from || 0,
    to: props.tickets.to || 0,
    total: props.tickets.total || 0,
}));
const goPage = (n) => {
    if (n < 1 || n > pagination.value.last || n === pagination.value.current) return;
    router.get(route('it.tickets'), { ...props.filters, page: n }, { preserveScroll: true, replace: true });
};

// Escape closes topmost layer first: delete confirm → detail → create.
const onEsc = (e) => {
    if (e.key !== 'Escape') return;
    if (pendingDelete.value) pendingDelete.value = null;
    else if (selected.value) selected.value = null;
    else if (showCreate.value) showCreate.value = false;
};
onMounted(() => window.addEventListener('keydown', onEsc));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onEsc);
    if (searchTimer) clearTimeout(searchTimer);
});

const priorityClass = (p) => ({
    P1: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    P2: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    P3: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    P4: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
}[p]);

const statusClass = (s) => ({
    open: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    assigned: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
    in_progress: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    resolved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    closed: 'bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-400',
    reopened: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
}[s]);

const rows = computed(() => props.tickets.data || []);
const selectedFresh = computed(() => rows.value.find(t => selected.value && t.id === selected.value.id) || selected.value);
</script>

<template>
    <Head title="Service Desk | IT Department" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    Service <span class="text-blue-600">Desk</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Incidents & service requests with SLA tracking.</p>
            </div>
            <button v-if="canEditTickets" @click="showCreate = true"
                class="inline-flex w-full sm:w-auto items-center justify-center gap-2 px-4 py-3 sm:py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 active:scale-95">
                <Plus class="w-4 h-4" /> Log Ticket
            </button>
            <span v-else class="text-xs font-bold text-amber-600 bg-amber-50 px-3 py-1.5 rounded-full">View only</span>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex flex-col lg:flex-row gap-3 bg-slate-50/50 dark:bg-slate-800/50">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input v-model="searchBox" @input="onSearchInput" type="text"
                        placeholder="Search ticket no, title, location..."
                        class="w-full pl-9 pr-4 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200 placeholder-slate-400" />
                </div>
                <div class="flex flex-wrap gap-2 [&>select]:flex-1 [&>select]:sm:flex-none [&>select]:min-w-[130px]">
                    <select :value="filters.status || ''" @change="applyFilters({ status: $event.target.value })"
                        class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                        <option value="">All statuses</option>
                        <option value="open">Open queue</option>
                        <option value="assigned">Assigned</option>
                        <option value="in_progress">In Progress</option>
                        <option value="pending">Pending</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                    <select :value="filters.priority || ''" @change="applyFilters({ priority: $event.target.value })"
                        class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                        <option value="">All priorities</option>
                        <option value="P1">P1 Critical</option>
                        <option value="P2">P2 High</option>
                        <option value="P3">P3 Normal</option>
                        <option value="P4">P4 Low</option>
                    </select>
                    <select :value="filters.category || ''" @change="applyFilters({ category: $event.target.value })"
                        class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                        <option value="">Incidents + Requests</option>
                        <option value="incident">Incidents</option>
                        <option value="service_request">Service Requests</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left min-w-[720px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900/40 border-b border-slate-100 dark:border-slate-700">
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Ticket</th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Priority</th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Assignee</th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">SLA Due</th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="t in rows" :key="t.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/60">
                            <td class="px-4 sm:px-6 py-3 sm:py-4">
                                <button @click="openDetail(t)" class="text-left">
                                    <p class="text-sm font-bold text-slate-900 dark:text-white hover:text-blue-600">{{ t.ticket_no }} — {{ t.title }}</p>
                                    <p class="flex items-center gap-1 text-[11px] text-slate-400">{{ t.category.replace('_', ' ') }} · {{ t.system_area }} · <MessageSquare class="h-3 w-3" /> {{ t.comments_count ?? 0 }}</p>
                                </button>
                            </td>
                            <td class="px-4 sm:px-6 py-3 sm:py-4"><span :class="['px-2 py-0.5 rounded-full text-[10px] font-black', priorityClass(t.priority)]">{{ t.priority }}</span></td>
                            <td class="px-4 sm:px-6 py-3 sm:py-4"><span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase', statusClass(t.status)]">{{ t.status.replace('_', ' ') }}</span></td>
                            <td class="px-4 sm:px-6 py-3 sm:py-4 text-sm text-slate-600 dark:text-slate-300">{{ t.assignee?.name || '—' }}</td>
                            <td class="px-4 sm:px-6 py-3 sm:py-4">
                                <span v-if="t.sla_due_at" class="inline-flex items-center gap-1 text-xs font-mono"
                                    :class="t.sla_due_at && new Date(t.sla_due_at) < new Date() && ['open','assigned','in_progress','pending','reopened'].includes(t.status) ? 'text-red-500 font-bold' : 'text-slate-500'">
                                    <Clock class="w-3.5 h-3.5" /> {{ new Date(t.sla_due_at).toLocaleString() }}
                                </span>
                                <span v-else class="text-xs text-slate-400">—</span>
                            </td>
                            <td class="px-4 sm:px-6 py-3 sm:py-4 text-right whitespace-nowrap">
                                <button @click="openDetail(t)" class="px-3 py-2 sm:py-1.5 text-xs font-bold text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg">Open</button>
                                <button v-if="isManager && canEditTickets" @click="askDeleteTicket(t)" title="Delete ticket" class="p-2.5 sm:p-1.5 text-slate-400 hover:text-red-500"><Trash2 class="w-4 h-4" /></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="rows.length === 0" class="flex flex-col items-center gap-2 py-12 text-center">
                    <SearchX class="h-8 w-8 text-slate-300 dark:text-slate-600" />
                    <p class="text-sm text-slate-400 font-bold">No tickets match.</p>
                    <p class="text-[11px] text-slate-400">Try clearing the search or choosing different filters.</p>
                </div>
            </div>
            <!-- Pager (backend paginates 15/page) -->
            <div v-if="pagination.total > 0"
                class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/60 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/40 sm:px-6">
                <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">
                    {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}
                </p>
                <div class="flex items-center gap-1.5">
                    <button @click="goPage(pagination.current - 1)" :disabled="pagination.current <= 1" title="Previous page"
                        class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-40 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <ChevronLeft class="h-3.5 w-3.5" /> Prev
                    </button>
                    <span class="rounded-xl bg-indigo-50 px-3 py-1.5 font-mono text-xs font-black text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                        {{ pagination.current }} / {{ pagination.last }}
                    </span>
                    <button @click="goPage(pagination.current + 1)" :disabled="pagination.current >= pagination.last" title="Next page"
                        class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-40 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        Next <ChevronRight class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Create modal -->
        <Teleport to="body">
        <Transition name="modal">
            <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="showCreate = false">
                <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-800">
                    <div class="flex shrink-0 items-center gap-2.5 border-b border-slate-100 bg-white/95 px-5 py-4 dark:border-slate-700 dark:bg-slate-800/95">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-white shadow">
                            <Ticket class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-sm font-black text-slate-900 dark:text-white">Log Ticket</h2>
                            <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">New incident or request</p>
                        </div>
                        <button @click="showCreate = false" title="Close"
                            class="ml-auto rounded-xl p-2 text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <form id="ticket-create-form" @submit.prevent="submitTicket" class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Title</label>
                            <input v-model="form.title" type="text" required placeholder="e.g. Label printer offline at Packaging Stn 2"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" />
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Description</label>
                            <textarea v-model="form.description" required rows="3" placeholder="What happened, since when, business impact..."
                                class="w-full resize-none rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"></textarea>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Category</label>
                                <select v-model="form.category" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                    <option value="incident">Incident</option>
                                    <option value="service_request">Service Request</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Priority</label>
                                <select v-model="form.priority" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:border-zinc-700 dark:bg-slate-900 dark:text-slate-200">
                                    <option value="P1">P1 — Critical / plant-stopping</option>
                                    <option value="P2">P2 — High</option>
                                    <option value="P3">P3 — Normal</option>
                                    <option value="P4">P4 — Low</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">System area</label>
                                <select v-model="form.system_area" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                    <option value="erp">ERP / MontiERP</option>
                                    <option value="network">Network / WiFi</option>
                                    <option value="hardware">Hardware</option>
                                    <option value="software">Software</option>
                                    <option value="plant_ot">Plant OT / Floor Systems</option>
                                    <option value="peripheral">Printers / Scanners</option>
                                    <option value="access">Access / Accounts</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Location</label>
                                <input v-model="form.location" type="text" placeholder="e.g. Dyeing floor, Lab terminal 2"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Caller (if phone-in)</label>
                                <input v-model="form.requester_name" type="text" placeholder="Name of plant caller"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" />
                            </div>
                            <div v-if="isManager">
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Assign to</label>
                                <select v-model="form.assignee_id" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                    <option value="">Unassigned</option>
                                    <option v-for="u in itStaff" :key="u.id" :value="u.id">{{ u.name }}</option>
                                </select>
                            </div>
                        </div>
                    </form>
                    <div class="flex shrink-0 gap-3 border-t border-slate-100 bg-white/95 px-5 py-4 dark:border-slate-700 dark:bg-slate-800/95">
                        <button type="button" @click="showCreate = false"
                            class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-bold text-slate-500 hover:bg-gray-50 dark:border-slate-700 dark:hover:bg-slate-700">
                            Cancel
                        </button>
                        <button type="submit" form="ticket-create-form" :disabled="form.processing"
                            class="flex-1 rounded-xl bg-blue-600 py-2.5 text-sm font-black text-white shadow hover:bg-blue-700 disabled:opacity-50 active:scale-95">
                            {{ form.processing ? 'Logging…' : 'Log Ticket' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
        </Teleport>

        <!-- Detail drawer: bottom sheet on mobile, side panel on desktop -->
        <Teleport to="body">
        <Transition name="drawer">
            <div v-if="selectedFresh" class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 backdrop-blur-sm sm:items-stretch sm:justify-end" @click.self="selected = null">
                <div class="flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl dark:bg-slate-800 sm:h-full sm:max-h-none sm:max-w-md sm:rounded-l-3xl sm:rounded-tr-none sm:border-l sm:border-slate-200 sm:dark:border-slate-700">
                    <div class="flex shrink-0 items-start gap-2 border-b border-slate-100 bg-white/95 px-5 py-4 dark:border-slate-700 dark:bg-slate-800/95">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow">
                            <Ticket class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-mono text-[11px] font-bold text-slate-400">{{ selectedFresh.ticket_no }}</p>
                            <h2 class="truncate text-sm font-black text-slate-900 dark:text-white">{{ selectedFresh.title }}</h2>
                            <span class="mt-1 flex flex-wrap gap-1.5">
                                <span :class="['rounded-full px-2 py-0.5 text-[10px] font-black', priorityClass(selectedFresh.priority)]">{{ selectedFresh.priority }}</span>
                                <span :class="['rounded-full px-2 py-0.5 text-[10px] font-black uppercase', statusClass(selectedFresh.status)]">{{ selectedFresh.status.replace('_', ' ') }}</span>
                            </span>
                        </div>
                        <button @click="selected = null" title="Close"
                            class="shrink-0 rounded-xl p-2 text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5">
                        <p class="whitespace-pre-line text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ selectedFresh.description }}</p>

                        <div>
                            <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Assignment</p>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Status</label>
                                    <select v-model="editForm.status" :disabled="!canEditTickets" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 disabled:opacity-60">
                                        <option value="open">Open</option>
                                        <option value="assigned">Assigned</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="pending">Pending</option>
                                        <option value="resolved">Resolved</option>
                                        <option value="closed">Closed</option>
                                        <option value="reopened">Reopened</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Priority</label>
                                    <select v-model="editForm.priority" :disabled="!canEditTickets" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 disabled:opacity-60">
                                        <option value="P1">P1</option><option value="P2">P2</option>
                                        <option value="P3">P3</option><option value="P4">P4</option>
                                    </select>
                                </div>
                                <div v-if="isManager" class="sm:col-span-2">
                                    <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Assignee</label>
                                    <select v-model="editForm.assignee_id" :disabled="!canEditTickets" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 disabled:opacity-60">
                                        <option value="">Unassigned</option>
                                        <option v-for="u in itStaff" :key="u.id" :value="u.id">{{ u.name }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div>
                            <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Work log ({{ (selectedFresh.comments || []).length }})</p>
                            <div class="space-y-2">
                                <div v-for="c in (selectedFresh.comments || [])" :key="c.id"
                                    :class="['rounded-2xl p-3 text-sm', c.is_internal ? 'border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20' : 'border border-slate-100 bg-slate-50/60 dark:border-slate-700 dark:bg-slate-900']">
                                    <p class="whitespace-pre-line text-slate-700 dark:text-slate-200">{{ c.body }}</p>
                                    <p class="mt-1 text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ c.user?.name }} · {{ new Date(c.created_at).toLocaleString() }}{{ c.is_internal ? ' · internal' : '' }}</p>
                                </div>
                                <p v-if="!(selectedFresh.comments || []).length" class="rounded-2xl border border-dashed border-slate-200 p-4 text-center text-xs font-bold text-slate-400 dark:border-slate-700">No updates yet.</p>
                            </div>
                            <div v-if="canEditTickets" class="mt-3 flex gap-2">
                                <input v-model="commentBody" type="text" placeholder="Post an update..." @keyup.enter="postComment"
                                    class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" />
                                <button @click="postComment" title="Send update"
                                    class="shrink-0 rounded-xl bg-blue-600 p-2.5 text-white shadow hover:bg-blue-700 active:scale-95">
                                    <Send class="h-4 w-4" />
                                </button>
                            </div>
                            <label v-if="isManager" class="mt-2 flex cursor-pointer items-center gap-2 text-xs font-bold text-slate-500">
                                <input type="checkbox" v-model="commentInternal" class="h-3.5 w-3.5 rounded accent-blue-600" /> Internal note
                            </label>
                            <p v-else class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-xs font-bold text-amber-600 dark:bg-amber-900/20">View only — status changes disabled.</p>
                        </div>
                    </div>
                    <div v-if="canEditTickets" class="flex shrink-0 gap-3 border-t border-slate-100 bg-white/95 px-5 py-4 dark:border-slate-700 dark:bg-slate-800/95">
                        <button @click="selected = null"
                            class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-bold text-slate-500 hover:bg-gray-50 dark:border-slate-700 dark:hover:bg-slate-700">
                            Close
                        </button>
                        <button @click="saveTicket" :disabled="editForm.processing"
                            class="flex-1 rounded-xl bg-blue-600 py-2.5 text-sm font-black text-white shadow hover:bg-blue-700 disabled:opacity-50 active:scale-95">
                            {{ editForm.processing ? 'Saving…' : 'Save Changes' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
        </Teleport>

        <!-- Delete confirmation (replaces the native confirm dialog) -->
        <Teleport to="body">
        <Transition name="modal">
            <div v-if="pendingDelete" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="closeDeleteTicket">
                <div class="w-full max-w-sm overflow-hidden rounded-3xl bg-white text-center shadow-2xl dark:bg-slate-800">
                    <div class="flex flex-col items-center p-6">
                        <span class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-900/30">
                            <Trash2 class="h-6 w-6 text-rose-500" />
                        </span>
                        <h2 class="text-lg font-black text-slate-900 dark:text-white">Delete ticket?</h2>
                        <p class="mt-1 font-mono text-xs font-bold text-slate-400">{{ pendingDelete.ticket_no }} — {{ pendingDelete.title }}</p>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Its work-log comments will be removed too. This cannot be undone.</p>
                    </div>
                    <div class="flex gap-3 border-t border-slate-100 bg-slate-50/60 px-5 py-4 dark:border-slate-700 dark:bg-slate-900/40">
                        <button @click="closeDeleteTicket"
                            class="flex-1 rounded-xl border border-slate-200 bg-white py-2.5 text-sm font-bold text-slate-500 hover:bg-gray-50 dark:border-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700">
                            Cancel
                        </button>
                        <button @click="confirmDeleteTicket"
                            class="flex-1 rounded-xl bg-rose-600 py-2.5 text-sm font-black text-white shadow hover:bg-rose-500 active:scale-95">
                            Delete
                        </button>
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
.drawer-enter-active { transition: opacity 0.25s ease, transform 0.3s cubic-bezier(0.22, 1, 0.36, 1); }
.drawer-enter-from { opacity: 0; transform: translateX(40px); }
.drawer-leave-active { transition: opacity 0.2s ease, transform 0.25s ease; }
.drawer-leave-to { opacity: 0; transform: translateX(40px); }
@media (max-width: 639px) {
  .drawer-enter-from { transform: translateY(40px); }
  .drawer-leave-to { transform: translateY(40px); }
}
</style>
