<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Building2, Eye, X, Sparkles, Clock, Search, User, MapPin, Phone, Mail, CreditCard } from 'lucide-vue-next';

const props = defineProps({
    clients: { type: Array, default: () => [] },
});

// Billy badap

const searchTerm = ref('');
const statusFilter = ref('all');

const filteredClients = computed(() => {
    let list = props.clients ?? [];
    if (statusFilter.value !== 'all') {
        list = list.filter((c) => c.status === statusFilter.value);
    }
    if (!searchTerm.value) return list;
    const term = searchTerm.value.toLowerCase();
    return list.filter((c) =>
        (c.company_name ?? '').toLowerCase().includes(term) ||
        (c.contact_person ?? '').toLowerCase().includes(term) ||
        (c.email ?? '').toLowerCase().includes(term),
    );
});

const countBy = (s) => (props.clients ?? []).filter((c) => c.status === s).length;

const selectedClient = ref(null);
const showModal = ref(false);

const openDetails = (client) => {
    selectedClient.value = client;
    showModal.value = true;
};

const statusConfig = (s) => ({
    active:    { classes: 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30', dot: 'bg-emerald-500' },
    pending:   { classes: 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30', dot: 'bg-amber-500' },
    suspended: { classes: 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30', dot: 'bg-rose-500' },
    rejected:  { classes: 'bg-gray-100 text-gray-500 ring-gray-200 dark:bg-zinc-800 dark:text-gray-400 dark:ring-zinc-700', dot: 'bg-gray-400' },
}[s] || { classes: 'bg-gray-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400', dot: 'bg-gray-400' });

const formatDate = (d) => {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};

const formatMoney = (val) => '₱' + Number(val ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });

const fullAddress = (c) => [c.company_address, c.city, c.province, c.postal_code].filter(Boolean).join(', ') || '—';

const refreshData = () => {
    router.reload({ only: ['clients'] });
};

const filters = [
    { key: 'all', label: 'All' },
    { key: 'active', label: 'Active' },
    { key: 'pending', label: 'Pending' },
    { key: 'suspended', label: 'Suspended' },
    { key: 'rejected', label: 'Rejected' },
];
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Clients - ECO Module" />

        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <Building2 class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> ECO · Client Directory
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Clients Directory</h1>
                            <p class="text-sm text-blue-100/90">All MontiTextile clients · information viewing only.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse" /> {{ countBy('active') }} active
                            </span>
                            <button @click="refreshData" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/25 backdrop-blur hover:bg-white/25 transition active:scale-95" title="Refresh">
                                <Clock class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Stat strip -->
                    <div class="relative mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:80ms">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Total</p>
                            <p class="mt-1 text-lg font-black">{{ clients.length }}</p>
                        </div>
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:160ms">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Active</p>
                            <p class="mt-1 text-lg font-black">{{ countBy('active') }}</p>
                        </div>
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:240ms">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Pending</p>
                            <p class="mt-1 text-lg font-black">{{ countBy('pending') }}</p>
                        </div>
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:320ms">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Suspended</p>
                            <p class="mt-1 text-lg font-black">{{ countBy('suspended') }}</p>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="relative mt-6">
                        <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input v-model="searchTerm" type="text" placeholder="Search by company name, contact person, or email..."
                            class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                    </div>
                </div>

                <!-- Status filter tabs -->
                <div class="animate-fade-up flex flex-wrap items-center gap-2" style="animation-delay:120ms">
                    <button
                        v-for="f in filters"
                        :key="f.key"
                        @click="statusFilter = f.key"
                        :class="statusFilter === f.key
                            ? 'bg-gradient-to-br from-indigo-600 to-violet-700 text-white shadow-lg shadow-indigo-500/25'
                            : 'bg-white dark:bg-zinc-900 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-zinc-700 hover:border-indigo-200'"
                        class="px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition-all active:scale-95"
                    >
                        {{ f.label }}
                        <span class="ml-1 opacity-70">{{ f.key === 'all' ? clients.length : countBy(f.key) }}</span>
                    </button>
                </div>

                <!-- Empty state -->
                <div v-if="filteredClients.length === 0"
                    class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                        <Building2 class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">{{ clients.length === 0 ? 'No clients yet' : 'No matches found' }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ clients.length === 0 ? 'Clients will appear here.' : 'Try a different search or filter.' }}</p>
                    <button v-if="searchTerm || statusFilter !== 'all'" @click="searchTerm=''; statusFilter='all'"
                        class="mt-4 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition active:scale-95">Clear filters</button>
                </div>

                <!-- Desktop Table -->
                <div v-else class="animate-fade-up hidden md:block bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden" style="animation-delay:120ms">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                        <Building2 class="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                        <h2 class="text-sm font-black uppercase tracking-widest text-gray-900 dark:text-white">Clients</h2>
                        <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-1">{{ filteredClients.length }}</span>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 dark:border-zinc-800">
                                <th class="px-5 py-4 text-left">Company</th>
                                <th class="px-5 py-4 text-left">Contact</th>
                                <th class="px-5 py-4 text-center">Status</th>
                                <th class="px-5 py-4 text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-zinc-800">
                            <tr
                                v-for="client in filteredClients"
                                :key="client.id"
                                class="group hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors duration-150"
                            >
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 overflow-hidden rounded-xl bg-gradient-to-br from-indigo-500 via-blue-600 to-cyan-600 flex items-center justify-center flex-shrink-0 shadow-lg ring-1 ring-white/40 group-hover:scale-110 transition-transform duration-300">
                                            <img v-if="client.logo_url" :src="client.logo_url" :alt="client.company_name" class="h-full w-full object-cover" loading="lazy" />
                                            <span v-else class="text-xs font-black text-white uppercase">{{ client.company_name?.charAt(0)?.toUpperCase() }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <span class="font-bold text-gray-900 dark:text-white block truncate">{{ client.company_name }}</span>
                                            <span class="text-[11px] text-gray-400">{{ client.business_type }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="text-gray-700 dark:text-gray-200 text-sm font-medium flex items-center gap-1.5"><User class="h-3.5 w-3.5 text-violet-500" />{{ client.contact_person }}</p>
                                    <p class="text-gray-400 text-xs mt-0.5">{{ client.email }}</p>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <span :class="statusConfig(client.status).classes" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1">
                                        <span :class="statusConfig(client.status).dot" class="w-1.5 h-1.5 rounded-full animate-pulse"></span>
                                        {{ client.status }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            @click="openDetails(client)"
                                            class="flex h-8 w-8 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 hover:bg-indigo-600 hover:text-white transition-all"
                                            title="View Details"
                                        >
                                            <Eye class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </div>

                <!-- Mobile Cards -->
                <TransitionGroup v-if="filteredClients.length > 0" name="card" tag="div" class="md:hidden space-y-3">
                    <div
                        v-for="(client, i) in filteredClients"
                        :key="client.id"
                        :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                        class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur border border-gray-100 dark:border-zinc-800 rounded-3xl p-4 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 overflow-hidden"
                    >
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="relative flex items-start justify-between gap-3 mb-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="h-9 w-9 overflow-hidden rounded-xl bg-gradient-to-br from-indigo-500 via-blue-600 to-cyan-600 flex items-center justify-center flex-shrink-0 shadow-lg">
                                    <img v-if="client.logo_url" :src="client.logo_url" :alt="client.company_name" class="h-full w-full object-cover" loading="lazy" />
                                    <span v-else class="text-sm font-black text-white uppercase">{{ client.company_name?.charAt(0)?.toUpperCase() }}</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-gray-900 dark:text-white text-sm truncate">{{ client.company_name }}</p>
                                    <p class="text-xs text-gray-400 truncate">{{ client.email }}</p>
                                </div>
                            </div>
                            <span :class="statusConfig(client.status).classes" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1 flex-shrink-0">
                                <span :class="statusConfig(client.status).dot" class="w-1.5 h-1.5 rounded-full animate-pulse"></span>
                                {{ client.status }}
                            </span>
                        </div>
                        <p class="relative text-xs text-gray-500 dark:text-gray-400 mb-4">{{ client.contact_person }} · {{ client.phone }}</p>
                        <div class="relative flex items-center gap-2 pt-3 border-t border-gray-100 dark:border-zinc-800">
                            <button
                                @click="openDetails(client)"
                                class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2.5 border border-gray-200 dark:border-zinc-700 rounded-xl text-xs font-bold text-gray-600 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors active:scale-95"
                            >
                                <Eye class="w-3.5 h-3.5" /> View Details
                            </button>
                        </div>
                    </div>
                </TransitionGroup>

            </div>
        </div>

        <!-- ─── VIEW DETAILS MODAL (information only) ─── -->
        <Teleport to="body">
            <Transition name="modal">
                <div
                    v-if="showModal"
                    class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-zinc-950/60 backdrop-blur-sm"
                    @click.self="showModal = false"
                >
                    <div
                        class="relative bg-white dark:bg-zinc-900 w-full sm:max-w-lg sm:rounded-3xl rounded-t-3xl shadow-2xl max-h-[92vh] sm:max-h-[85vh] flex flex-col overflow-hidden border border-gray-100 dark:border-zinc-800"
                    >
                        <div class="flex justify-center pt-3 pb-1 sm:hidden">
                            <div class="w-10 h-1 bg-gray-200 dark:bg-zinc-700 rounded-full"></div>
                        </div>

                        <!-- Modal Header -->
                        <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-5 sm:px-6 py-5 text-white">
                            <div class="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/10 blur-3xl animate-float" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                            <div class="relative flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur flex items-center justify-center flex-shrink-0 animate-pop">
                                        <Eye class="w-4 h-4" />
                                    </div>
                                    <div>
                                        <h3 class="text-sm sm:text-base font-black tracking-tight">Client Details</h3>
                                        <p class="text-xs text-blue-100/90 leading-tight">{{ selectedClient?.company_name }}</p>
                                    </div>
                                </div>
                                <button
                                    @click="showModal = false"
                                    class="w-8 h-8 rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 flex items-center justify-center transition-all backdrop-blur"
                                >
                                    <X class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Modal Body -->
                        <div class="flex-1 overflow-y-auto px-5 sm:px-6 py-5 space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="bg-gray-50 dark:bg-zinc-800/60 rounded-2xl p-3.5 border border-gray-100 dark:border-zinc-800">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1 flex items-center gap-1"><Building2 class="h-3 w-3" /> Company Name</p>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedClient?.company_name }}</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-zinc-800/60 rounded-2xl p-3.5 border border-gray-100 dark:border-zinc-800">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Business Type</p>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedClient?.business_type }}</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-zinc-800/60 rounded-2xl p-3.5 border border-gray-100 dark:border-zinc-800">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1 flex items-center gap-1"><User class="h-3 w-3" /> Contact Person</p>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedClient?.contact_person }}</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-zinc-800/60 rounded-2xl p-3.5 border border-gray-100 dark:border-zinc-800">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1 flex items-center gap-1"><Phone class="h-3 w-3" /> Phone</p>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedClient?.phone }}</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-zinc-800/60 rounded-2xl p-3.5 border border-gray-100 dark:border-zinc-800">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1 flex items-center gap-1"><Mail class="h-3 w-3" /> Email Address</p>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white break-all">{{ selectedClient?.email }}</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-zinc-800/60 rounded-2xl p-3.5 border border-gray-100 dark:border-zinc-800">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Status</p>
                                    <span :class="statusConfig(selectedClient?.status).classes" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1">
                                        <span :class="statusConfig(selectedClient?.status).dot" class="w-1.5 h-1.5 rounded-full animate-pulse"></span>
                                        {{ selectedClient?.status }}
                                    </span>
                                </div>
                                <div class="bg-gray-50 dark:bg-zinc-800/60 rounded-2xl p-3.5 sm:col-span-2 border border-gray-100 dark:border-zinc-800">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1 flex items-center gap-1"><MapPin class="h-3 w-3" /> Address</p>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedClient ? fullAddress(selectedClient) : '—' }}</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-zinc-800/60 rounded-2xl p-3.5 border border-gray-100 dark:border-zinc-800">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1 flex items-center gap-1"><CreditCard class="h-3 w-3" /> Credit Limit</p>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedClient ? formatMoney(selectedClient.credit_limit) : '—' }}</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-zinc-800/60 rounded-2xl p-3.5 border border-gray-100 dark:border-zinc-800">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Payment Terms</p>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">Net {{ selectedClient?.payment_terms_days ?? '—' }} days</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-zinc-800/60 rounded-2xl p-3.5 border border-gray-100 dark:border-zinc-800">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Client Since</p>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ formatDate(selectedClient?.created_at) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="px-5 sm:px-6 py-4 border-t border-gray-100 dark:border-zinc-800 flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                            <button
                                @click="showModal = false"
                                class="w-full sm:w-auto px-5 py-2.5 text-sm font-bold text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white border border-gray-200 dark:border-zinc-700 hover:border-indigo-200 dark:hover:border-indigo-800 rounded-xl transition-all duration-150 active:scale-95"
                            >
                                Close
                            </button>
                        </div>

                    </div>
                </div>
            </Transition>
        </Teleport>

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
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-active > div, .modal-leave-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from > div, .modal-leave-to > div { opacity: 0; transform: scale(0.95) translateY(10px); }
</style>
