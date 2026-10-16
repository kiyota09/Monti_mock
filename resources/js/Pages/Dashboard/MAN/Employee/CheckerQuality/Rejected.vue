<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { XCircle, ArrowLeft, RefreshCw, Archive, Shirt, Package, Sparkles, ClipboardList } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditReject = computed(() => canEdit('MAN', 'reject'));

const props = defineProps({
    rejectedFabrics: Array,  // { id, code, yarn_type, weight, rejection_reason, rejection_action, rejected_at, sales_order }
    rejectedForms: Array,    // existing structure
    warehouses: Array,       // { id, name, location }
});

const activeTab = ref('fabrics'); // 'fabrics' or 'forms'

// Modal for total reject
const showWarehouseModal = ref(false);
const selectedFabric = ref(null);
const warehouseForm = ref({ warehouse_id: '' });

const recolorFabric = (fabricId) => {
    router.post(route('man.manager.rejected.recolor', fabricId), {}, {
        preserveScroll: true,
    });
};

const openWarehouseModal = (fabric) => {
    selectedFabric.value = fabric;
    warehouseForm.value.warehouse_id = '';
    showWarehouseModal.value = true;
};

const submitTotalReject = () => {
    if (!warehouseForm.value.warehouse_id) {
        alert('Please select a warehouse.');
        return;
    }
    router.post(route('man.manager.rejected.total-reject', selectedFabric.value.id), warehouseForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            showWarehouseModal.value = false;
        },
    });
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
                                <Sparkles class="h-3.5 w-3.5" /> MAN · Quality Rejects
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Rejected Items</h1>
                            <span v-if="!canEditReject" class="mt-2 inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-amber-800">View only</span>
                            <p class="text-sm text-blue-100/90">{{ (rejectedFabrics?.length || 0) + (rejectedForms?.length || 0) }} items rejected</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link :href="route('man.manager.production')"
                                class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white ring-1 ring-white/25 backdrop-blur hover:bg-white/25 transition-all active:scale-95 flex items-center gap-1.5">
                                <ArrowLeft class="w-4 h-4" /> Back to Production
                            </Link>
                            <span class="rounded-full bg-rose-400/90 px-3 py-1.5 text-xs font-black text-rose-950">{{ (rejectedFabrics?.length || 0) + (rejectedForms?.length || 0) }} rejected</span>
                        </div>
                    </div>

                    <!-- Tabs inside hero -->
                    <div class="relative mt-6 flex gap-2">
                        <button
                            @click="activeTab = 'fabrics'"
                            :class="activeTab === 'fabrics' ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                            class="flex items-center gap-1.5 rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                            <Shirt class="w-4 h-4" /> Fabrics · {{ rejectedFabrics?.length || 0 }}
                        </button>
                        <button
                            @click="activeTab = 'forms'"
                            :class="activeTab === 'forms' ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                            class="flex items-center gap-1.5 rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                            <Package class="w-4 h-4" /> Forms · {{ rejectedForms?.length || 0 }}
                        </button>
                    </div>
                </div>

                <!-- Fabrics Tab -->
                <div v-if="activeTab === 'fabrics'" class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden">
                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                    <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full" />
                    <div class="relative flex items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg"><Shirt class="h-4 w-4" /></span>
                        <h2 class="text-sm font-black tracking-tight text-gray-900 dark:text-white">Rejected Fabrics</h2>
                        <span class="ml-auto rounded-full bg-rose-50 dark:bg-rose-900/20 px-3 py-1 text-[11px] font-black text-rose-700 dark:text-rose-300">{{ rejectedFabrics?.length || 0 }} items</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Code</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Yarn Type</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Weight (kg)</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">JO / Color</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Rejection Reason</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Actions</th>
                                </tr>
                            </thead>
                            <TransitionGroup name="card" tag="tbody" class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr v-for="(fabric, i) in rejectedFabrics" :key="fabric.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors">
                                    <td class="px-6 py-4 font-mono text-sm font-bold text-gray-900 dark:text-white">{{ fabric.code }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ fabric.yarn_type }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ fabric.weight }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                        <span v-if="fabric.sales_order">
                                            {{ fabric.sales_order.jo_number }} / {{ fabric.sales_order.color }}
                                        </span>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300 max-w-xs truncate" :title="fabric.rejection_reason">
                                        {{ fabric.rejection_reason || 'No reason provided' }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex gap-2">
                                            <button v-if="canEditReject"
                                                @click="recolorFabric(fabric.id)"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-600 text-white rounded-xl text-[11px] font-black uppercase hover:bg-indigo-700 shadow-lg shadow-indigo-500/25 transition hover:-translate-y-0.5 active:scale-95"
                                                title="Send back for recoloring"
                                            >
                                                <RefreshCw class="w-3.5 h-3.5" /> Recolor
                                            </button>
                                            <button v-if="canEditReject"
                                                @click="openWarehouseModal(fabric)"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-100 dark:bg-zinc-800 text-gray-700 dark:text-gray-300 rounded-xl text-[11px] font-black uppercase hover:bg-gray-200 dark:hover:bg-zinc-700 transition active:scale-95"
                                                title="Totally reject and send to warehouse"
                                            >
                                                <Archive class="w-3.5 h-3.5" /> Total Reject
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </TransitionGroup>
                        </table>
                        <div v-if="!rejectedFabrics || rejectedFabrics.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                            <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                                <Shirt class="h-9 w-9 text-indigo-400" />
                            </div>
                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No rejected fabrics found</p>
                            <p class="text-xs text-gray-400 mt-1">Rejected fabrics will appear here.</p>
                        </div>
                    </div>
                </div>

                <!-- Forms Tab -->
                <div v-if="activeTab === 'forms'" class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden">
                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                    <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full" />
                    <div class="relative flex items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg"><ClipboardList class="h-4 w-4" /></span>
                        <h2 class="text-sm font-black tracking-tight text-gray-900 dark:text-white">Rejected Forms</h2>
                        <span class="ml-auto rounded-full bg-rose-50 dark:bg-rose-900/20 px-3 py-1 text-[11px] font-black text-rose-700 dark:text-rose-300">{{ rejectedForms?.length || 0 }} items</span>
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
                                <tr v-for="(form, i) in rejectedForms" :key="form.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors">
                                    <td class="px-6 py-4 font-mono text-sm font-bold text-gray-900 dark:text-white">{{ form.code }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ form.product_name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ form.quantity }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ form.rejected_by }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300 max-w-md truncate" :title="form.reason">{{ form.reason }}</td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400 text-sm">{{ new Date(form.rejected_at).toLocaleString() }}</td>
                                </tr>
                            </TransitionGroup>
                        </table>
                        <div v-if="!rejectedForms || rejectedForms.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                            <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                                <Package class="h-9 w-9 text-indigo-400" />
                            </div>
                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No rejected forms found</p>
                            <p class="text-xs text-gray-400 mt-1">Rejected forms will appear here.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Warehouse Selection Modal for Total Reject -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showWarehouseModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="showWarehouseModal = false">
                    <div class="bg-white/95 dark:bg-zinc-900/95 backdrop-blur rounded-3xl w-full max-w-md shadow-2xl overflow-hidden border border-gray-100 dark:border-zinc-800">
                        <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <h3 class="relative text-lg font-black text-white tracking-tight">Send to Warehouse</h3>
                            <p class="relative text-sm text-blue-100/90 mt-1">
                                Select destination warehouse for rejected fabric <strong>{{ selectedFabric?.code }}</strong>.
                            </p>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="block text-xs font-black uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1.5">Warehouse</label>
                                <select v-model="warehouseForm.warehouse_id" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                                    <option value="">Select Warehouse</option>
                                    <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">
                                        {{ wh.name }} ({{ wh.location }})
                                    </option>
                                </select>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button @click="showWarehouseModal = false" class="px-4 py-2.5 rounded-2xl border border-gray-200 dark:border-zinc-700 text-xs font-black uppercase tracking-wide hover:bg-gray-50 dark:hover:bg-zinc-800 transition active:scale-95">Cancel</button>
                                <button v-if="canEditReject" @click="submitTotalReject" class="px-4 py-2.5 rounded-2xl bg-rose-600 text-white text-xs font-black uppercase tracking-wide hover:bg-rose-700 shadow-lg shadow-rose-500/25 transition active:scale-95">Confirm</button>
                            </div>
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
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: translateY(16px) scale(0.98); }
</style>
