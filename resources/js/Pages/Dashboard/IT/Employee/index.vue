<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePageAccess } from '@/composables/usePageAccess';
import { LifeBuoy, BookOpen, Plus, X, ArrowRight } from 'lucide-vue-next';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    myTickets: { type: Array, default: () => [] },
    knowledge: { type: Array, default: () => [] },
});

const showCreate = ref(false);

const { canEdit } = usePageAccess();
const canEditTickets = computed(() => canEdit('IT', 'tickets'));

const form = useForm({
    title: '', description: '', category: 'incident',
    system_area: 'erp', priority: 'P3', location: '', requester_name: '',
});

const submitTicket = () => {
    form.post(route('it.tickets.store'), {
        preserveScroll: true,
        onSuccess: () => { showCreate.value = false; form.reset(); },
    });
};

const priorityClass = (p) => ({
    P1: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    P2: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    P3: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    P4: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
}[p]);
</script>

<template>
    <Head title="My IT Portal | Monti Textile" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    My IT <span class="text-blue-600">Portal</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Your queue, quick ticket filing, and self-help guides.</p>
            </div>
            <button v-if="canEditTickets" @click="showCreate = true"
                class="inline-flex w-full sm:w-auto items-center justify-center gap-2 px-4 py-3 sm:py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 active:scale-95">
                <Plus class="w-4 h-4" /> File Ticket
            </button>
            <span v-else class="text-xs font-bold text-amber-600 bg-amber-50 px-3 py-1.5 rounded-full">View only</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                    <h2 class="text-sm font-black uppercase tracking-widest text-slate-500 inline-flex items-center gap-2">
                        <LifeBuoy class="w-4 h-4" /> My Open Tickets ({{ myTickets.length }})
                    </h2>
                    <Link :href="route('it.tickets')" class="text-xs font-bold text-blue-600 hover:text-blue-500">Service Desk →</Link>
                </div>
                <div v-if="myTickets.length === 0" class="p-8 text-center text-sm text-slate-400">Nothing assigned. Queue clear.</div>
                <ul v-else class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    <li v-for="t in myTickets" :key="t.id" class="px-4 sm:px-6 py-3 sm:py-3.5 flex items-center gap-3">
                        <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black', priorityClass(t.priority)]">{{ t.priority }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ t.ticket_no }} — {{ t.title }}</p>
                            <p class="text-[11px] text-slate-400">{{ t.status.replace('_', ' ') }} · {{ t.assignee?.name || 'Unassigned' }}</p>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                    <h2 class="text-sm font-black uppercase tracking-widest text-slate-500 inline-flex items-center gap-2">
                        <BookOpen class="w-4 h-4" /> Self-Help Guides
                    </h2>
                    <Link :href="route('it.knowledge')" class="text-xs font-bold text-blue-600 hover:text-blue-500">Library →</Link>
                </div>
                <div v-if="knowledge.length === 0" class="p-8 text-center text-sm text-slate-400">No guides published yet.</div>
                <ul v-else class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    <li v-for="a in knowledge" :key="a.id" class="px-4 sm:px-6 py-3 sm:py-3.5 flex items-center gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ a.title }}</p>
                            <p class="text-[11px] text-slate-400">{{ a.category }} · {{ a.views }} views</p>
                        </div>
                        <Link :href="route('it.knowledge')" class="text-slate-400 hover:text-blue-600"><ArrowRight class="w-4 h-4" /></Link>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Quick file modal -->
        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-lg max-h-[90vh] overflow-y-auto p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-black text-slate-900 dark:text-white">File Ticket</h2>
                    <button @click="showCreate = false" class="p-1.5 text-slate-400 hover:text-slate-600"><X class="w-5 h-5" /></button>
                </div>
                <form @submit.prevent="submitTicket" class="space-y-3">
                    <input v-model="form.title" required placeholder="Short summary" class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                    <textarea v-model="form.description" required rows="3" placeholder="What happened, where, since when..."
                        class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200"></textarea>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <select v-model="form.category" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                            <option value="incident">Incident</option>
                            <option value="service_request">Service Request</option>
                        </select>
                        <select v-model="form.priority" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                            <option value="P1">P1 — Critical</option>
                            <option value="P2">P2 — High</option>
                            <option value="P3">P3 — Normal</option>
                            <option value="P4">P4 — Low</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <select v-model="form.system_area" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                            <option value="erp">ERP / MontiERP</option>
                            <option value="network">Network / WiFi</option>
                            <option value="hardware">Hardware</option>
                            <option value="software">Software</option>
                            <option value="plant_ot">Plant OT</option>
                            <option value="peripheral">Printers / Scanners</option>
                            <option value="access">Access / Accounts</option>
                        </select>
                        <input v-model="form.location" placeholder="Location (optional)" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                    </div>
                    <p class="text-[11px] text-slate-400">P1 = production/dispatch stopped. SLA clock starts immediately.</p>
                    <button type="submit" :disabled="form.processing" class="w-full py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 active:scale-95 disabled:opacity-50">Submit Ticket</button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
