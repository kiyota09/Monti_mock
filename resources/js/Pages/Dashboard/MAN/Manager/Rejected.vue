<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { XCircle, ArrowLeft, Search, Sparkles, ClipboardList } from 'lucide-vue-next';

const props = defineProps({
    rejectedItems: {
        type: Array,
        default: () => [],
    },
    warehouses: {
        type: Array,
        default: () => [],
    },
});

const searchTerm = ref('');

const filteredItems = computed(() => {
    if (!props.rejectedItems || !Array.isArray(props.rejectedItems)) return [];
    if (!searchTerm.value) return props.rejectedItems;
    return props.rejectedItems.filter(item =>
        item.code?.toLowerCase().includes(searchTerm.value.toLowerCase()) ||
        item.product_name?.toLowerCase().includes(searchTerm.value.toLowerCase()) ||
        (item.reason && item.reason.toLowerCase().includes(searchTerm.value.toLowerCase()))
    );
});

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleString();
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Rejected Items" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <XCircle class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> MAN · Manager
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Rejected Items</h1>
                            <p class="text-sm text-blue-100/90">{{ filteredItems.length }} of {{ Array.isArray(rejectedItems) ? rejectedItems.length : 0 }} item{{ (Array.isArray(rejectedItems) ? rejectedItems.length : 0) !== 1 ? 's' : '' }} showing</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link :href="route('man.manager.dashboard')"
                                class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white ring-1 ring-white/25 backdrop-blur hover:bg-white/25 transition-all active:scale-95 flex items-center gap-1.5">
                                <ArrowLeft class="w-4 h-4" /> Back to Dashboard
                            </Link>
                            <span class="rounded-full bg-rose-400/90 px-3 py-1.5 text-xs font-black text-rose-950">{{ Array.isArray(rejectedItems) ? rejectedItems.length : 0 }} rejected</span>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="relative mt-6">
                        <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input v-model="searchTerm" type="text" placeholder="Search by code, product, or reason..."
                            class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                    </div>
                </div>

                <!-- Table card -->
                <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden" style="animation-delay: 100ms">
                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                    <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full" />
                    <div class="relative flex items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg"><ClipboardList class="h-4 w-4" /></span>
                        <h2 class="text-sm font-black tracking-tight text-gray-900 dark:text-white">All Rejected Items</h2>
                        <span class="ml-auto rounded-full bg-indigo-50 dark:bg-indigo-900/20 px-3 py-1 text-[11px] font-black text-indigo-700 dark:text-indigo-300">{{ filteredItems.length }} showing</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Code</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Product</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Quantity</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Rejected By</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Reason</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Date</th>
                                </tr>
                            </thead>
                            <TransitionGroup name="card" tag="tbody" class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr v-for="(item, i) in filteredItems" :key="item.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                                    class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition">
                                    <td class="px-6 py-4 font-mono text-sm font-bold text-gray-900 dark:text-white">{{ item.code || 'N/A' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ item.product_name || 'Unknown' }}</td>
                                    <td class="px-6 py-4 text-sm font-black text-gray-900 dark:text-white">{{ item.quantity ?? 0 }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ item.rejected_by || 'System' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300 max-w-md truncate" :title="item.reason || ''">{{ item.reason || 'No reason' }}</td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400 text-sm">{{ formatDate(item.rejected_at) }}</td>
                                </tr>
                            </TransitionGroup>
                        </table>
                        <div v-if="filteredItems.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                            <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                                <XCircle class="h-9 w-9 text-indigo-400" />
                            </div>
                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No rejected items found</p>
                            <p class="text-xs text-gray-400 mt-1">Try a different search.</p>
                            <button v-if="searchTerm" @click="searchTerm=''"
                                class="mt-4 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition active:scale-95">Clear search</button>
                        </div>
                    </div>
                </div>

                <!-- Summary Card -->
                <div class="animate-fade-up group relative overflow-hidden rounded-3xl bg-white/80 dark:bg-zinc-900/80 backdrop-blur p-5 border border-rose-100 dark:border-rose-900/40 shadow-sm hover:shadow-2xl hover:shadow-rose-500/15 hover:-translate-y-1 transition-all duration-300" style="animation-delay: 150ms">
                    <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-rose-500 to-orange-500 rounded-r-full" />
                    <div class="relative flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-rose-500 via-red-600 to-orange-600 text-white shadow-lg"><XCircle class="w-6 h-6" /></span>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.15em] text-gray-400">Total Rejected Items</p>
                            <p class="text-2xl font-black text-gray-900 dark:text-white">{{ Array.isArray(rejectedItems) ? rejectedItems.length : 0 }}</p>
                        </div>
                        <span class="ml-auto flex items-center gap-1 rounded-full bg-rose-50 dark:bg-rose-900/20 px-3 py-1.5 text-[11px] font-black text-rose-700 dark:text-rose-300"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />Attention</span>
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
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: translateY(16px) scale(0.98); }
</style>
