<template>
    <Head title="Credit Management - ECO Module" />
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
                            <CreditCard class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> ECO · Financial Risk Monitoring
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Credit Ledger</h1>
                            <p class="text-sm text-blue-100/90">Monitor outstanding balances, approve reviews, and view order history.</p>
                            <span v-if="!canEditCredit" class="mt-2 inline-block text-xs font-bold text-amber-700 bg-amber-50 px-3 py-1.5 rounded-full">View only</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-rose-300 animate-pulse" /> ₱{{ formatCurrency(totalOutstanding) }} outstanding
                            </span>
                            <button @click="refreshData" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/25 backdrop-blur hover:bg-white/25 transition active:scale-95">
                                <RefreshCw class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Search + filters -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchTerm" type="text" placeholder="Search by company name..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <div class="flex gap-2">
                            <button v-for="key in ['all','good','risk']" :key="key" @click="filterStatus = key"
                                :class="filterStatus === key ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                                class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                                {{ key === 'all' ? 'All' : key === 'good' ? 'Good Payers' : 'At Risk' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:80ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-rose-400/20 to-orange-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-rose-500 to-orange-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-rose-500 via-red-600 to-orange-600 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <CreditCard class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">Total Outstanding</p>
                                <h3 class="text-2xl font-black text-rose-600 tracking-tight">₱{{ formatCurrency(totalOutstanding) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:160ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-emerald-400/20 to-teal-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-emerald-500 to-teal-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <CheckCircle class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">Good Payers</p>
                                <h3 class="text-3xl font-black text-emerald-600 tracking-tight">{{ goodPayersCount }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:240ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-amber-400/20 to-orange-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-amber-500 to-orange-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <AlertCircle class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">At Risk</p>
                                <h3 class="text-3xl font-black text-amber-600 tracking-tight">{{ atRiskCount }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:320ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Building2 class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">Total Clients</p>
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ clients.length }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Credit Reviews Section -->
                <div class="animate-fade-up rounded-3xl border border-indigo-200 bg-indigo-50 p-4 text-xs font-bold text-indigo-800 dark:border-indigo-800/50 dark:bg-indigo-900/20 dark:text-indigo-200" style="animation-delay:100ms">
                    ✓ Pre-acceptance checklist (aligned with Push Center): before a PO is accepted, verify <strong>inventory</strong> (Push Center → Check Inv) and <strong>credits</strong> below (good payer vs pending payments). Credit flags are advisory only — Monti may still accept at their judgement.
                </div>
                <div v-if="pendingCreditReviews.length > 0" class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-amber-200 dark:border-amber-800/50 overflow-hidden hover:shadow-xl transition-shadow duration-300" style="animation-delay:120ms">
                    <div class="p-6 border-b border-amber-200 dark:border-amber-800/50 bg-gradient-to-r from-amber-50 to-transparent dark:from-amber-900/20 flex items-center gap-2">
                        <AlertCircle class="h-5 w-5 text-amber-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest text-amber-800 dark:text-amber-300">Pending Credit Review ({{ pendingCreditReviews.length }})</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 dark:border-zinc-800">
                                <tr>
                                    <th class="px-6 py-4">PO Number</th>
                                    <th class="px-6 py-4">Client</th>
                                    <th class="px-6 py-4 text-right">Total Amount</th>
                                    <th class="px-6 py-4 text-center">Order Date</th>
                                    <th class="px-6 py-4 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="order in pendingCreditReviews" :key="order.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors">
                                    <td class="px-6 py-4">
                                        <span class="font-mono text-sm font-black text-gray-900 dark:text-white">{{ order.po_number }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-900/20"><Building2 class="h-4 w-4 text-indigo-600" /></span>
                                            <span class="text-sm font-bold text-gray-900 dark:text-white">{{ order.client?.company_name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right font-black text-gray-900 dark:text-white">₱{{ formatCurrency(order.total_amount) }}</td>
                                    <td class="px-6 py-4 text-center text-xs text-gray-500 dark:text-gray-400">{{ formatDate(order.created_at) }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <button v-if="canEditCredit" @click="approveCreditReview(order)" :disabled="approving[order.id]"
                                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 text-white rounded-xl text-[10px] font-black uppercase tracking-wide hover:bg-emerald-700 active:scale-95 transition disabled:opacity-50 shadow-lg shadow-emerald-500/20">
                                            <CheckCircle v-if="!approving[order.id]" class="h-3 w-3" />
                                            <Loader2 v-else class="h-3 w-3 animate-spin" />
                                            {{ approving[order.id] ? 'Approving...' : 'Approve' }}
                                        </button>
                                        <span v-else class="text-[10px] font-black uppercase text-amber-600 bg-amber-50 px-2.5 py-1 rounded-full">View only</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Clients Table -->
                <div class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden hover:shadow-xl transition-shadow duration-300" style="animation-delay:200ms">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                        <Building2 class="h-5 w-5 text-indigo-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">Client Balances</h2>
                        <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-1">{{ filteredClients.length }} showing</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="text-[10px] font-black uppercase text-gray-400 tracking-widest border-b border-gray-100 dark:border-zinc-800">
                                <tr>
                                    <th class="px-6 py-4">Client</th>
                                    <th class="px-6 py-4 text-right">Outstanding Balance</th>
                                    <th class="px-6 py-4 text-center">Payment Standing</th>
                                    <th class="px-6 py-4 text-center">Total Orders</th>
                                    <th class="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="client in filteredClients" :key="client.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 flex items-center justify-center text-white font-black shadow-lg uppercase">
                                                {{ (client.company_name ?? '?').charAt(0) }}
                                            </div>
                                            <div>
                                                <p class="text-sm font-black text-gray-900 dark:text-white">{{ client.company_name }}</p>
                                                <p class="text-[10px] font-bold text-gray-400">{{ client.contact_person }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="text-lg font-black text-rose-600">₱{{ formatCurrency(client.outstanding_balance || 0) }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span :class="client.is_good_payer ? 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30' : 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30'"
                                            class="px-2.5 py-1 rounded-full ring-1 text-[9px] font-black uppercase inline-flex items-center gap-1">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ client.is_good_payer ? 'Good Payer' : 'At Risk' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center font-bold text-gray-700 dark:text-gray-300">{{ client.orders_count || 0 }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <button @click="openHistoryModal(client)" class="p-2.5 rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-500 hover:bg-indigo-600 hover:text-white transition active:scale-95">
                                            <History class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredClients.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <Search class="mx-auto h-10 w-10 text-gray-200 dark:text-zinc-700 animate-bounce-soft" />
                                        <p class="mt-2 text-sm font-bold text-gray-400">No clients found.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Order History Modal -->
            <Teleport to="body">
                <Transition name="modal">
                    <div v-if="showHistoryModal && selectedClient" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showHistoryModal = false">
                        <div class="bg-white dark:bg-zinc-900 w-full max-w-4xl rounded-3xl shadow-2xl overflow-hidden max-h-[90vh] flex flex-col animate-pop">
                            <div class="relative overflow-hidden px-6 py-5 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white flex justify-between items-center">
                                <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                                <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                                <div class="relative">
                                    <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><History class="h-3.5 w-3.5" /> Order History</p>
                                    <h3 class="text-xl font-black tracking-tight">{{ selectedClient.company_name }}</h3>
                                </div>
                                <button @click="showHistoryModal = false" class="relative p-2 bg-white/10 rounded-xl hover:bg-white/20 transition"><X class="h-5 w-5" /></button>
                            </div>
                            <div class="p-6 overflow-y-auto">
                                <div class="mb-4 p-4 bg-gray-50 dark:bg-zinc-800/60 rounded-2xl flex flex-wrap justify-between gap-2">
                                    <div><span class="text-sm text-gray-500 dark:text-gray-400">Outstanding:</span> <span class="font-black text-rose-600">₱{{ formatCurrency(selectedClient.outstanding_balance) }}</span></div>
                                    <div><span class="text-sm text-gray-500 dark:text-gray-400">Status:</span> <span :class="selectedClient.is_good_payer ? 'text-emerald-600' : 'text-amber-600'" class="font-black inline-flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ selectedClient.is_good_payer ? 'Good Payer' : 'At Risk' }}</span></div>
                                </div>
                                <TransitionGroup name="card" tag="div" class="space-y-3">
                                    <div v-for="order in selectedClient.orders" :key="order.id" class="rounded-2xl border border-gray-100 dark:border-zinc-800 p-4 hover:shadow-lg hover:-translate-y-0.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <p class="font-mono text-sm font-bold text-gray-900 dark:text-white">{{ order.po_number }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(order.created_at) }}</p>
                                            </div>
                                            <span :class="order.status === 'approved' ? 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30' : 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30'" class="px-2.5 py-1 rounded-full ring-1 text-[9px] font-black uppercase inline-flex items-center gap-1">
                                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ order.status }}
                                            </span>
                                        </div>
                                        <div class="mt-2 flex justify-between items-center">
                                            <span class="text-sm text-gray-600 dark:text-gray-300">Total: <span class="font-black text-gray-900 dark:text-white">₱{{ formatCurrency(order.total_amount) }}</span></span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ order.items_count || 0 }} items</span>
                                        </div>
                                    </div>
                                </TransitionGroup>
                                <div v-if="!selectedClient.orders || selectedClient.orders.length === 0" class="text-center py-8 text-gray-400">
                                    <p class="text-sm font-bold">No orders found for this client.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>
            </Teleport>

            <Transition name="toast">
                <div v-if="toast.show" class="fixed bottom-8 right-8 z-50 px-6 py-3 rounded-2xl shadow-lg text-white font-bold text-sm"
                    :class="toast.type === 'success' ? 'bg-emerald-600' : 'bg-red-600'">
                    {{ toast.message }}
                </div>
            </Transition>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { usePageAccess } from '@/composables/usePageAccess';
import { CreditCard, RefreshCw, Search, Building2, History, X, AlertCircle, CheckCircle, Loader2, Sparkles } from 'lucide-vue-next';

const { canEdit } = usePageAccess();
const canEditCredit = computed(() => canEdit('ECO', 'credit'));

const props = defineProps({
    clients: { type: Array, default: () => [] },
    pendingCreditReviews: { type: Array, default: () => [] }
});

const searchTerm = ref('');
const filterStatus = ref('all');
const showHistoryModal = ref(false);
const selectedClient = ref(null);
const approving = ref({});
const toast = ref({ show: false, type: 'success', message: '' });

const filteredClients = computed(() => {
    let list = props.clients;
    if (searchTerm.value) {
        const term = searchTerm.value.toLowerCase();
        list = list.filter(c => c.company_name.toLowerCase().includes(term));
    }
    if (filterStatus.value === 'good') {
        list = list.filter(c => c.is_good_payer);
    } else if (filterStatus.value === 'risk') {
        list = list.filter(c => !c.is_good_payer);
    }
    return list;
});

const totalOutstanding = computed(() => {
    return props.clients.reduce((sum, c) => sum + (parseFloat(c.outstanding_balance) || 0), 0);
});

const goodPayersCount = computed(() => props.clients.filter(c => c.is_good_payer).length);
const atRiskCount = computed(() => props.clients.filter(c => !c.is_good_payer).length);

const formatCurrency = (val) => {
    return Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
};

const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

const openHistoryModal = (client) => {
    selectedClient.value = client;
    showHistoryModal.value = true;
};

const approveCreditReview = (order) => {
    if (!confirm(`Approve order ${order.po_number}? This will run the credit check (advisory only) and move it toward the Push Center. At-risk clients can still be accepted at Monti's judgement.`)) return;
    approving.value[order.id] = true;
    router.post(route('eco.credit.approve-order', order.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            showToast('success', `Order ${order.po_number} approved.`);
            refreshData();
        },
        onError: (errors) => {
            showToast('error', errors.error || 'Failed to approve order.');
        },
        onFinish: () => {
            approving.value[order.id] = false;
        }
    });
};

const refreshData = () => {
    router.reload({ only: ['clients', 'pendingCreditReviews'] });
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
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-active > div, .modal-leave-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from > div, .modal-leave-to > div { opacity: 0; transform: scale(0.95) translateY(10px); }
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }
</style>
