<template>
    <Head title="Load Packages" />
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
                                <Sparkles class="h-3.5 w-3.5" /> Logistics · Operations
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Load Packages <span v-if="!canEditLoad" class="ml-2 inline-block rounded-full bg-amber-400/90 px-3 py-1 align-middle text-xs font-black uppercase tracking-widest text-amber-950">View only</span></h1>
                            <p class="text-sm text-blue-100/90">{{ selectedIds.length }} of {{ packages.length }} packages selected</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-300 animate-pulse" /> {{ packages.length }} pending
                            </span>
                            <button @click="refreshData" title="Refresh"
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/25 backdrop-blur hover:bg-white/25 hover:scale-105 active:scale-95 transition-all">
                                <RefreshCw class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Search + bulk actions -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchTerm" type="text" placeholder="Search by package number or product..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <div class="flex gap-2">
                            <button v-if="canEditLoad" @click="selectAllPackages"
                                class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:bg-indigo-50 hover:scale-105 active:scale-95 transition-all">
                                Select All
                            </button>
                            <button v-if="selectedIds.length > 0" @click="clearSelection"
                                class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white ring-1 ring-white/25 backdrop-blur hover:bg-white/25 active:scale-95 transition-all">
                                Clear
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats Summary -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:80ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 via-blue-600 to-cyan-600 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Package class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Pending Packages</p>
                                <h3 class="text-3xl font-black text-indigo-600 tracking-tight">{{ packages.length }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:160ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-amber-400/20 to-orange-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-amber-500 to-orange-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <CheckSquare class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-1.5">Selected <span v-if="selectedIds.length" class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse" /></p>
                                <h3 class="text-3xl font-black text-amber-600 tracking-tight">{{ selectedIds.length }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 p-5 text-white shadow-lg shadow-emerald-500/20 hover:shadow-2xl hover:-translate-y-1.5 transition-all duration-300" style="animation-delay:240ms">
                        <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10 blur-2xl group-hover:scale-125 transition-transform duration-500" />
                        <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                        <div class="relative flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Send class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-emerald-100">Ready to Dispatch</p>
                                <h3 class="text-3xl font-black leading-none">{{ selectedIds.length }}</h3>
                                <p class="text-xs text-emerald-100/80 mt-1">Packages will be moved to Dispatch</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Packages Table -->
                <div class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden hover:shadow-xl transition-shadow duration-300" style="animation-delay:120ms">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                        <Package class="h-5 w-5 text-indigo-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">Pending Packages</h2>
                        <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-1">{{ filteredPackages.length }}</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 dark:border-zinc-800">
                                <tr>
                                    <th class="px-6 py-4 w-12">
                                        <input v-if="canEditLoad" type="checkbox" @change="toggleSelectAll" v-model="selectAllFlag" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    </th>
                                    <th class="px-6 py-4">Package #</th>
                                    <th class="px-6 py-4">Product</th>
                                    <th class="px-6 py-4 text-center">Quantity</th>
                                    <th class="px-6 py-4">From Order</th>
                                    <th class="px-6 py-4 text-center">Created At</th>
                                </tr>
                            </thead>
                            <TransitionGroup name="card" tag="tbody" class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="(pkg, i) in filteredPackages" :key="pkg.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }" class="group text-sm hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors">
                                    <td class="px-6 py-3">
                                        <input v-if="canEditLoad" type="checkbox" v-model="selectedIds" :value="pkg.id" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 group-hover:scale-110 transition-transform">
                                                <Package class="h-5 w-5" />
                                            </div>
                                            <span class="font-mono text-sm font-black text-gray-900 dark:text-white">{{ pkg.package_number }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <p class="font-bold text-gray-900 dark:text-white">{{ pkg.product?.name || '—' }}</p>
                                        <p class="text-[10px] text-gray-400">{{ pkg.product?.sku }}</p>
                                    </td>
                                    <td class="px-6 py-3 text-center font-bold text-gray-900 dark:text-white">{{ pkg.quantity }} pcs</td>
                                    <td class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400">
                                        {{ pkg.manufacturing_order?.purchase_order?.po_number || '—' }}
                                    </td>
                                    <td class="px-6 py-3 text-center text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDate(pkg.created_at) }}
                                    </td>
                                </tr>
                            </TransitionGroup>
                        </table>
                        <div v-if="filteredPackages.length === 0" class="px-6 py-12 text-center">
                            <Package class="mx-auto h-10 w-10 text-gray-200 dark:text-zinc-700 animate-bounce-soft" />
                            <p class="mt-2 text-sm font-bold text-gray-400">No pending packages from Manufacturing.</p>
                            <p class="text-xs text-gray-400">Finished goods will appear here automatically.</p>
                        </div>
                    </div>

                    <!-- Action Footer -->
                    <div v-if="filteredPackages.length > 0" class="px-6 py-5 border-t border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50/50 to-transparent dark:from-indigo-900/10 flex flex-wrap items-center justify-between gap-3">
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            <span class="font-black text-gray-900 dark:text-white">{{ selectedIds.length }}</span> of <span class="font-black text-gray-900 dark:text-white">{{ filteredPackages.length }}</span> packages selected
                        </div>
                        <button v-if="canEditLoad" @click="passToDispatch"
                            :disabled="selectedIds.length === 0 || processing"
                            class="px-8 py-3 bg-indigo-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-700 hover:shadow-lg hover:-translate-y-0.5 active:scale-95 transition-all disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0 flex items-center gap-2">
                            <Loader2 v-if="processing" class="h-4 w-4 animate-spin" />
                            <Send v-else class="h-4 w-4" />
                            {{ processing ? 'Processing...' : 'Pass to Dispatch' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast Notification -->
        <Transition name="toast">
            <div v-if="toast.show" class="fixed bottom-8 right-8 z-50 px-6 py-3 rounded-2xl shadow-lg text-white font-bold text-sm"
                :class="toast.type === 'success' ? 'bg-emerald-600' : 'bg-red-600'">
                {{ toast.message }}
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { Package, RefreshCw, Search, Send, Loader2, Sparkles, CheckSquare } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditLoad = computed(() => canEdit('LOG', 'load'));

const props = defineProps({
    packages: {
        type: Array,
        default: () => []
    }
});

const selectedIds = ref([]);
const selectAllFlag = ref(false);
const searchTerm = ref('');
const processing = ref(false);
const toast = ref({ show: false, type: 'success', message: '' });

const filteredPackages = computed(() => {
    if (!searchTerm.value) return props.packages;
    const term = searchTerm.value.toLowerCase();
    return props.packages.filter(pkg =>
        pkg.package_number.toLowerCase().includes(term) ||
        pkg.product?.name?.toLowerCase().includes(term) ||
        pkg.product?.sku?.toLowerCase().includes(term)
    );
});

// Select All logic
const toggleSelectAll = () => {
    if (selectAllFlag.value) {
        selectedIds.value = filteredPackages.value.map(p => p.id);
    } else {
        selectedIds.value = [];
    }
};

const selectAllPackages = () => {
    selectedIds.value = filteredPackages.value.map(p => p.id);
    selectAllFlag.value = true;
};

const clearSelection = () => {
    selectedIds.value = [];
    selectAllFlag.value = false;
};

// Watch filtered packages to update selectAllFlag
watch([filteredPackages, selectedIds], () => {
    if (filteredPackages.value.length === 0) {
        selectAllFlag.value = false;
        return;
    }
    const allSelected = filteredPackages.value.every(p => selectedIds.value.includes(p.id));
    selectAllFlag.value = allSelected;
});

const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

const refreshData = () => {
    router.reload({ only: ['packages'] });
};

const passToDispatch = async () => {
    if (selectedIds.value.length === 0) return;
    processing.value = true;
    try {
        await router.post(route('logistics.load.pass'), { package_ids: selectedIds.value });
        showToast('success', `${selectedIds.value.length} package(s) passed to dispatch.`);
        selectedIds.value = [];
        selectAllFlag.value = false;
        refreshData();
    } catch (error) {
        showToast('error', 'Failed to pass packages. Please try again.');
    } finally {
        processing.value = false;
    }
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
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
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }
</style>
