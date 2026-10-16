<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { route } from 'ziggy-js';
import {
    Building2, Phone, Mail, MapPin, ChevronRight, Users,
    Search, Sparkles, ArrowUpRight, CalendarCheck, MessageSquareHeart
} from 'lucide-vue-next';

const props = defineProps({
    clients: {
        type: Array,
        default: () => []
    },
    permissions: {
        type: Object,
        default: () => ({})
    }
});

const searchQuery = ref('');
const statusFilter = ref('all');

const statuses = computed(() => {
    const counts = { all: props.clients.length, active: 0, pending: 0, inactive: 0 };
    props.clients.forEach(c => {
        if (counts[c.status] !== undefined) counts[c.status]++;
    });
    return counts;
});

const filteredClients = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return props.clients.filter(c => {
        const matchStatus = statusFilter.value === 'all' || c.status === statusFilter.value;
        if (!matchStatus) return false;
        if (!q) return true;
        return [c.company_name, c.name, c.email, c.phone, c.business_type]
            .filter(Boolean).some(v => String(v).toLowerCase().includes(q));
    });
});

const getStatusClass = (status) => {
    switch (status) {
        case 'active':   return 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30';
        case 'pending':  return 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
        case 'inactive': return 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30';
        default:         return 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
    }
};

const getAvatarGradient = (status) => {
    switch (status) {
        case 'active': return 'from-emerald-500 via-teal-600 to-cyan-700';
        case 'pending': return 'from-amber-500 via-orange-600 to-rose-600';
        case 'inactive': return 'from-slate-500 via-gray-600 to-zinc-700';
        default: return 'from-blue-600 via-indigo-600 to-violet-700';
    }
};

const canEdit = computed(() => props.permissions?.customer_profiles === 'edit');
</script>

<template>
    <Head title="Customer Profiles" />
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
                            <Users class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> CRM · Relationships
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Customer Profiles</h1>
                            <p class="text-sm text-blue-100/90">{{ filteredClients.length }} of {{ clients.length }} client{{ clients.length !== 1 ? 's' : '' }} showing</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ statuses.active }} active
                            </span>
                            <span v-if="canEdit" class="rounded-full bg-emerald-400/90 px-3 py-1.5 text-xs font-black text-emerald-950">Full access</span>
                            <span v-else class="rounded-full bg-amber-300/90 px-3 py-1.5 text-xs font-black text-amber-950">View only</span>
                        </div>
                    </div>

                    <!-- Search + filters -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchQuery" type="text" placeholder="Search company, email, phone..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <div class="flex gap-2">
                            <button v-for="key in ['all','active','pending','inactive']" :key="key" @click="statusFilter = key"
                                :class="statusFilter === key ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                                class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                                {{ key }} · {{ statuses[key] ?? 0 }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="filteredClients.length === 0"
                    class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                        <Users class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">{{ clients.length === 0 ? 'No clients yet' : 'No matches found' }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ clients.length === 0 ? 'Converted leads will appear here.' : 'Try a different search or filter.' }}</p>
                    <button v-if="searchQuery || statusFilter !== 'all'" @click="searchQuery=''; statusFilter='all'"
                        class="mt-4 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition active:scale-95">Clear filters</button>
                </div>

                <!-- Client grid -->
                <TransitionGroup v-else name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Link
                        v-for="(client, i) in filteredClients"
                        :key="client.id"
                        :href="route('crm.customerprofile.show', client.hash_key)"
                        :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                        class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden"
                    >
                        <!-- hover glow -->
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />

                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div :class="['h-12 w-12 rounded-2xl bg-gradient-to-br flex items-center justify-center text-white text-lg font-black shadow-lg uppercase flex-shrink-0 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300', getAvatarGradient(client.status)]">
                                    {{ (client.company_name ?? client.name ?? '?').charAt(0) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-black text-gray-900 dark:text-white truncate tracking-tight group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors">
                                        {{ client.company_name ?? client.name }}
                                    </p>
                                    <p v-if="client.business_type" class="flex items-center gap-1 text-[10px] text-indigo-600 dark:text-indigo-400 font-bold uppercase truncate">
                                        <Building2 class="h-3 w-3" /> {{ client.business_type }}
                                    </p>
                                </div>
                            </div>
                            <span v-if="client.status" :class="getStatusClass(client.status)"
                                class="text-[9px] font-black uppercase px-2.5 py-1 rounded-full ring-1 flex-shrink-0 ml-2 flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ client.status }}
                            </span>
                        </div>

                        <div class="space-y-1.5 mb-4 text-[12px]">
                            <div v-if="client.email" class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20"><Mail class="h-3 w-3 text-blue-500" /></span>
                                <span class="truncate font-medium">{{ client.email }}</span>
                            </div>
                            <div v-if="client.phone" class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-900/20"><Phone class="h-3 w-3 text-emerald-500" /></span>
                                <span class="truncate font-medium">{{ client.phone }}</span>
                            </div>
                            <div v-if="client.address || client.company_address" class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-900/20"><MapPin class="h-3 w-3 text-violet-500" /></span>
                                <span class="truncate font-medium">{{ client.address ?? client.company_address }}</span>
                            </div>
                        </div>

                        <div class="mt-auto flex items-center justify-between pt-3 border-t border-gray-100 dark:border-zinc-800">
                            <div class="flex gap-4">
                                <div class="flex items-center gap-1.5">
                                    <CalendarCheck class="h-3.5 w-3.5 text-indigo-400" />
                                    <span class="text-sm font-black text-gray-900 dark:text-white">{{ client.meetings?.length ?? 0 }}</span>
                                    <span class="text-[9px] text-gray-400 font-bold uppercase">Meetings</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <MessageSquareHeart class="h-3.5 w-3.5 text-pink-400" />
                                    <span class="text-sm font-black text-gray-900 dark:text-white">{{ client.feedback?.length ?? 0 }}</span>
                                    <span class="text-[9px] text-gray-400 font-bold uppercase">Feedback</span>
                                </div>
                            </div>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 group-hover:bg-indigo-600 group-hover:text-white group-hover:translate-x-1 transition-all duration-300">
                                <ChevronRight class="h-4 w-4" />
                            </span>
                        </div>
                        <ArrowUpRight class="absolute top-4 right-4 h-4 w-4 text-indigo-300 opacity-0 group-hover:opacity-100 transition-opacity" />
                    </Link>
                </TransitionGroup>

            </div>
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
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
</style>
