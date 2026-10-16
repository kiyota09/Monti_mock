<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Package,
    Truck,
    CheckCircle,
    Clock,
    AlertCircle,
    Search,
    ChevronDown,
    Filter,
} from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditPackages = computed(() => canEdit('WAR', 'packages'));

const props = defineProps({
    packages: {
        type: Array,
        default: () => [],
    },
    auth: Object,
});

// State
const searchQuery = ref('');
const statusFilter = ref('all');
const processing = ref(false);
const pushingId = ref(null);

// Filtered packages
const filteredPackages = computed(() => {
    let items = [...props.packages];
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        items = items.filter(pkg =>
            pkg.package_number.toLowerCase().includes(q) ||
            pkg.product?.name?.toLowerCase().includes(q) ||
            pkg.product?.sku?.toLowerCase().includes(q)
        );
    }
    if (statusFilter.value !== 'all') {
        items = items.filter(pkg => pkg.status === statusFilter.value);
    }
    return items;
});

// Push to logistics
const pushToLogistics = (pkg) => {
    if (!confirm(`Push package ${pkg.package_number} to Logistics?`)) return;
    pushingId.value = pkg.id;
    router.post(route('warehouse.packages.push', pkg.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            pushingId.value = null;
        },
    });
};

// Status badge style
const statusBadge = (status) => {
    const styles = {
        pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        pushed_to_logistics: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        delivered: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    };
    return styles[status] || 'bg-gray-100 text-gray-700';
};

const statusIcon = (status) => {
    if (status === 'pending') return Clock;
    if (status === 'pushed_to_logistics') return Truck;
    return CheckCircle;
};

// Format date
const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleString();
};
</script>

<template>
    <Head title="Warehouse Packages | Monti Textile" />
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
                            <Package class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Truck class="h-3.5 w-3.5" /> Warehouse · Dispatch
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Packages <span v-if="!canEditPackages" class="ml-2 inline-block rounded-full bg-amber-400/90 px-3 py-1 align-middle text-xs font-black uppercase tracking-widest text-amber-950">View only</span></h1>
                            <p class="text-sm text-blue-100/90">{{ filteredPackages.length }} of {{ packages.length }} package{{ packages.length !== 1 ? 's' : '' }} showing · finished goods ready for dispatch</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ props.packages.filter(p => p.status === 'pending').length }} pending
                            </span>
                            <span class="rounded-full bg-emerald-400/90 px-3 py-1.5 text-xs font-black text-emerald-950">{{ packages.length }} total</span>
                        </div>
                    </div>

                    <!-- Search + filters -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchQuery" type="text" placeholder="Search package #, product, SKU..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <div class="flex gap-2 flex-wrap">
                            <button v-for="key in ['all','pending','pushed_to_logistics','delivered']" :key="key" @click="statusFilter = key"
                                :class="statusFilter === key ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                                class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                                {{ key.replace(/_/g, ' ') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="filteredPackages.length === 0"
                    class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                        <Package class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">{{ packages.length === 0 ? 'No packages yet' : 'No matches found' }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ packages.length === 0 ? 'Finished goods will appear here.' : 'Try a different search or filter.' }}</p>
                    <button v-if="searchQuery || statusFilter !== 'all'" @click="searchQuery=''; statusFilter='all'"
                        class="mt-4 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition active:scale-95">Clear filters</button>
                </div>

                <!-- Packages table -->
                <div v-else class="animate-fade-up relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden">
                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl" />
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50/80 dark:bg-zinc-800/60 border-b border-gray-100 dark:border-zinc-800">
                                <tr>
                                    <th class="px-5 py-3.5 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest">Package #</th>
                                    <th class="px-5 py-3.5 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest">Product</th>
                                    <th class="px-5 py-3.5 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest text-center">Quantity</th>
                                    <th class="px-5 py-3.5 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest">Manufacturing Order</th>
                                    <th class="px-5 py-3.5 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest">Created At</th>
                                    <th class="px-5 py-3.5 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest">Status</th>
                                    <th class="px-5 py-3.5 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest text-center">Action</th>
                                </tr>
                            </thead>
                            <TransitionGroup name="card" tag="tbody" class="divide-y divide-slate-100 dark:divide-zinc-800">
                                <tr v-for="(pkg, i) in filteredPackages" :key="pkg.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }" class="group relative hover:bg-indigo-50/40 dark:hover:bg-indigo-950/20 transition-colors">
                                    <td class="px-5 py-4">
                                        <span class="font-mono text-xs font-black text-indigo-600 dark:text-indigo-400">{{ pkg.package_number }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-2.5">
                                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white text-xs font-black shadow flex-shrink-0 group-hover:scale-110 transition-transform">{{ (pkg.product?.name ?? '?').charAt(0) }}</span>
                                            <div class="min-w-0">
                                                <p class="font-bold text-slate-800 dark:text-slate-200 truncate">{{ pkg.product?.name }}</p>
                                                <p class="text-[10px] text-slate-400">{{ pkg.product?.sku }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-center font-black text-slate-700 dark:text-slate-300">{{ pkg.quantity.toLocaleString() }} pcs</td>
                                    <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ pkg.manufacturing_order?.id || '—' }}</td>
                                    <td class="px-5 py-4 text-slate-600 dark:text-slate-400 text-xs">{{ formatDate(pkg.created_at) }}</td>
                                    <td class="px-5 py-4">
                                        <span :class="['inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full ring-1', statusBadge(pkg.status)]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />
                                            <component :is="statusIcon(pkg.status)" class="w-3 h-3" />
                                            {{ pkg.status.replace(/_/g, ' ') }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-center">
                                        <button
                                            v-if="canEditPackages && pkg.status === 'pending'"
                                            @click="pushToLogistics(pkg)"
                                            :disabled="processing && pushingId === pkg.id"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-2 text-[10px] font-black uppercase tracking-wide rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white hover:opacity-90 hover:scale-105 active:scale-95 transition-all shadow-lg shadow-emerald-500/25 disabled:opacity-50"
                                        >
                                            <Truck class="w-3.5 h-3.5" />
                                            {{ pushingId === pkg.id ? 'Pushing...' : 'Push to Logistics' }}
                                        </button>
                                        <span v-else class="text-slate-400 text-xs">—</span>
                                    </td>
                                </tr>
                            </TransitionGroup>
                        </table>
                    </div>

                    <!-- Footer stats -->
                    <div class="px-5 py-3.5 border-t border-gray-100 dark:border-zinc-800 flex items-center justify-between text-xs text-slate-400">
                        <span class="font-bold">Total: {{ filteredPackages.length }} packages</span>
                        <span class="flex items-center gap-1.5 font-bold">
                            <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                            Pending: {{ props.packages.filter(p => p.status === 'pending').length }}
                        </span>
                    </div>
                </div>
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
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
</style>
