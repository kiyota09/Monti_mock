<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Boxes,
    Warehouse,
    TrendingUp,
    TrendingDown,
    AlertTriangle,
    CheckCircle,
    ArrowRight,
    Package,
    RefreshCw,
    BarChart2,
    Activity,
    Clock,
    ChevronRight,
    DollarSign,
    LineChart,
    ShoppingCart,
    Sparkles,
    Search,
} from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditChecker = computed(() => canEdit('INV', 'checker'));

const props = defineProps({
    auth: Object,
    kpis: Object,
    warehouses: Array,
    materials: Array,        // List of materials with stock per warehouse
    alertItems: Array,
    recentActivity: Array,
    categoryBreakdown: Array,
    valueTrend: Object,
});

const user = computed(() => props.auth?.user);
const isSupervisor = computed(() => {
    const pos = user.value?.position;
    return pos === 'supervisor' || pos === 'manager';
});

// For supervisors: selected warehouse
const selectedWarehouseId = ref(null);
const warehouseOptions = computed(() => {
    if (!isSupervisor.value) return [];
    // User's assigned warehouses (provided from backend)
    return props.warehouses.filter(w => w.assigned_to_user === true);
});

// Filter materials based on selected warehouse (for supervisor)
const filteredMaterials = computed(() => {
    if (!isSupervisor.value || !selectedWarehouseId.value) {
        // CEO/Secretary sees overall stock (sum across all warehouses)
        return props.materials.map(m => ({
            ...m,
            total_stock: Object.values(m.stock_per_warehouse || {}).reduce((a, b) => a + b, 0),
        }));
    }
    return props.materials.map(m => ({
        ...m,
        total_stock: m.stock_per_warehouse?.[selectedWarehouseId.value] || 0,
    }));
});

const isLoaded = ref(false);
onMounted(() => setTimeout(() => (isLoaded.value = true), 50));

// Helper to get status color
const statusColor = (status) => {
    if (status === 'In Stock') return 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30';
    if (status === 'Low Stock') return 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
    return 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30';
};

// Request procurement for a material
const requestProcurement = (materialId) => {
    if (confirm(`Request procurement for ${materialId}? This will create a material request in SCM.`)) {
        router.post(route('inv.checker.procurement', materialId), {}, {
            preserveScroll: true,
        });
    }
};

// Format currency
const formatCurrency = (value) => {
    return '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2 });
};
</script>

<template>
    <Head title="Inventory Dashboard | Monti Textile" />
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
                            <Boxes class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> Inventory · Overview
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Inventory Dashboard <span v-if="!canEditChecker" class="ml-2 inline-block rounded-full bg-amber-400/90 px-3 py-1 align-middle text-xs font-black uppercase tracking-widest text-amber-950">View only</span></h1>
                            <p class="text-sm text-blue-100/90">Real‑time stock overview and material tracking.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ kpis.totalSkus || 0 }} SKUs
                            </span>
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur hidden sm:inline-flex">
                                {{ formatCurrency(kpis.totalValue || 0) }}
                            </span>
                            <Link :href="route('inv.checker')" class="inline-flex items-center gap-2 rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg transition-all duration-200 hover:scale-105 active:scale-95">
                                <ShoppingCart class="w-4 h-4" /> Checker
                            </Link>
                        </div>
                    </div>

                    <!-- Supervisor warehouse toggle inside hero -->
                    <div v-if="isSupervisor && warehouseOptions.length > 0" class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Warehouse class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <select v-model="selectedWarehouseId" class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg outline-none transition focus:ring-2 focus:ring-white/70">
                                <option :value="null">All Warehouses (Overall)</option>
                                <option v-for="wh in warehouseOptions" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- KPI Cards -->
                <TransitionGroup name="card" tag="div" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div key="skus" class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-2 text-slate-500 dark:text-gray-400 text-xs font-bold mb-2"><Boxes class="w-4 h-4" /> Total SKUs</div>
                        <p class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">{{ kpis.totalSkus || 0 }}</p>
                    </div>
                    <div key="instock" class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-emerald-500 to-teal-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-2 text-emerald-600 text-xs font-bold mb-2"><CheckCircle class="w-4 h-4" /> In Stock</div>
                        <p class="text-2xl font-black text-emerald-600">{{ kpis.inStock || 0 }}</p>
                    </div>
                    <div key="low" class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-amber-500 to-orange-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-2 text-amber-600 text-xs font-bold mb-2"><AlertTriangle class="w-4 h-4" /> Low Stock</div>
                        <p class="text-2xl font-black text-amber-600">{{ kpis.lowStock || 0 }}</p>
                    </div>
                    <div key="out" class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-rose-500 to-red-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-2 text-red-500 text-xs font-bold mb-2"><TrendingDown class="w-4 h-4" /> Out of Stock</div>
                        <p class="text-2xl font-black text-red-500">{{ kpis.outOfStock || 0 }}</p>
                    </div>
                    <div key="wh" class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-2 text-slate-500 dark:text-gray-400 text-xs font-bold mb-2"><Warehouse class="w-4 h-4" /> Warehouses</div>
                        <p class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">{{ kpis.totalWarehouses || 0 }}</p>
                    </div>
                    <div key="val" class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-violet-500 to-purple-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-2 text-slate-500 dark:text-gray-400 text-xs font-bold mb-2"><DollarSign class="w-4 h-4" /> Total Value</div>
                        <p class="text-lg font-black text-gray-900 dark:text-white tracking-tight">{{ formatCurrency(kpis.totalValue || 0) }}</p>
                    </div>
                </TransitionGroup>

                <!-- Materials Table -->
                <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/10 transition-all duration-300 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-zinc-800 flex items-center justify-between">
                        <h2 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-wider">Materials Stock</h2>
                        <span class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full ring-1 bg-indigo-100 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30 flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ filteredMaterials.length }} items</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50/80 dark:bg-zinc-800/60">
                                <tr>
                                    <th class="px-5 py-3 text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase">Material ID</th>
                                    <th class="px-5 py-3 text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase">Name</th>
                                    <th class="px-5 py-3 text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase">Category</th>
                                    <th class="px-5 py-3 text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase">Stock</th>
                                    <th class="px-5 py-3 text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase">Status</th>
                                    <th class="px-5 py-3 text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr v-if="filteredMaterials.length === 0">
                                    <td colspan="6" class="px-5 py-12 text-center text-slate-400">No materials found.</td>
                                </tr>
                                <tr v-for="mat in filteredMaterials" :key="mat.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors">
                                    <td class="px-5 py-3 font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ mat.mat_id }}</td>
                                    <td class="px-5 py-3 font-bold text-gray-900 dark:text-white">{{ mat.name }}</td>
                                    <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ mat.category }}</td>
                                    <td class="px-5 py-3 font-bold text-gray-900 dark:text-white">{{ mat.total_stock }} {{ mat.unit }}</td>
                                    <td class="px-5 py-3">
                                        <span :class="['inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black ring-1', statusColor(mat.status)]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ mat.status }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <button
                                            v-if="canEditChecker && mat.status !== 'In Stock'"
                                            @click="requestProcurement(mat.id)"
                                            class="px-3 py-1.5 text-[10px] font-black uppercase tracking-wide rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 hover:scale-105 active:scale-95 transition-all shadow-lg shadow-indigo-500/20"
                                        >
                                            Request Procurement
                                        </button>
                                        <span v-else class="text-slate-400 text-xs">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Charts Row (Category & Value Trend) -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="flex items-center gap-2 mb-4">
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/20"><BarChart2 class="w-4 h-4 text-blue-500" /></span>
                            <h2 class="text-sm font-black uppercase tracking-wider text-gray-900 dark:text-white">Materials by Category</h2>
                        </div>
                        <div v-if="!categoryBreakdown?.length" class="flex flex-col items-center py-8 text-slate-400"><div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-3 animate-bounce-soft"><BarChart2 class="h-6 w-6 text-indigo-400" /></div><span class="text-xs font-bold">No data</span></div>
                        <div v-else class="space-y-3">
                            <div class="flex h-2.5 rounded-full overflow-hidden bg-slate-100 dark:bg-zinc-800">
                                <div v-for="cat in categoryBreakdown" :key="cat.name" :class="cat.color" :style="{ width: cat.pct + '%' }"></div>
                            </div>
                            <div v-for="cat in categoryBreakdown" :key="cat.name" class="flex justify-between text-sm">
                                <span class="font-bold text-gray-900 dark:text-white">{{ cat.name }}</span>
                                <span class="text-slate-500 dark:text-gray-400">{{ cat.count }} SKUs ({{ cat.pct }}%)</span>
                            </div>
                        </div>
                    </div>

                    <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 transition-all duration-300 p-5 overflow-hidden" style="animation-delay: 80ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="flex items-center gap-2 mb-4">
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-violet-50 dark:bg-violet-900/20"><LineChart class="w-4 h-4 text-violet-500" /></span>
                            <h2 class="text-sm font-black uppercase tracking-wider text-gray-900 dark:text-white">Inventory Value Trend (₱M)</h2>
                        </div>
                        <div v-if="!valueTrend?.values?.length" class="flex flex-col items-center py-8 text-slate-400"><div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-3 animate-bounce-soft"><LineChart class="h-6 w-6 text-indigo-400" /></div><span class="text-xs font-bold">No data</span></div>
                        <div v-else class="flex items-end gap-2 h-40">
                            <div v-for="(val, idx) in valueTrend.values" :key="idx" class="flex-1 flex flex-col items-center">
                                <div class="w-full bg-gradient-to-t from-blue-600 to-indigo-400 rounded-t-lg" :style="{ height: (val / Math.max(...valueTrend.values) * 100) + '%', minHeight: '4px' }"></div>
                                <span class="text-[10px] mt-1 text-gray-500 dark:text-gray-400">{{ valueTrend.months[idx] }}</span>
                                <span class="text-[9px] font-bold text-gray-900 dark:text-white">{{ val.toFixed(1) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity & Alerts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2">
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-900/20"><Activity class="w-4 h-4 text-indigo-500" /></span>
                            <h2 class="text-sm font-black uppercase tracking-wider text-gray-900 dark:text-white">Recent Activity</h2>
                        </div>
                        <div v-if="!recentActivity?.length" class="p-8 text-center text-slate-400 text-sm font-medium">No recent activity</div>
                        <div v-else class="divide-y divide-gray-100 dark:divide-zinc-800 max-h-72 overflow-y-auto">
                            <div v-for="act in recentActivity" :key="act.time" class="px-5 py-3 flex items-start gap-3 hover:bg-indigo-50/40 dark:hover:bg-indigo-950/10 transition-colors">
                                <div :class="['w-2 h-2 rounded-full mt-1.5 animate-pulse flex-shrink-0', act.color === 'red' ? 'bg-red-500' : act.color === 'amber' ? 'bg-amber-500' : 'bg-emerald-500']"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ act.action }}</p>
                                    <p class="text-xs text-slate-500 dark:text-gray-400">{{ act.item }} · {{ act.qty }} · {{ act.warehouse }}</p>
                                </div>
                                <span class="text-[10px] text-slate-400 flex-shrink-0">{{ act.time }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden" style="animation-delay: 80ms">
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2">
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-900/20"><AlertTriangle class="w-4 h-4 text-amber-500" /></span>
                            <h2 class="text-sm font-black uppercase tracking-wider text-gray-900 dark:text-white">Stock Alerts</h2>
                        </div>
                        <div v-if="!alertItems?.length" class="p-8 text-center text-slate-400 text-sm font-medium">All stocks are healthy.</div>
                        <div v-else class="divide-y divide-gray-100 dark:divide-zinc-800 max-h-72 overflow-y-auto">
                            <div v-for="alert in alertItems" :key="alert.sku" class="px-5 py-3 flex items-center justify-between gap-3 hover:bg-indigo-50/40 dark:hover:bg-indigo-950/10 transition-colors">
                                <div class="min-w-0">
                                    <p class="font-bold text-sm text-gray-900 dark:text-white truncate">{{ alert.name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-gray-400">{{ alert.sku }} · {{ alert.warehouse }}</p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="font-bold text-sm text-red-500">{{ alert.qty }} units</p>
                                    <p class="text-[10px] text-slate-400">Reorder: {{ alert.reorder }}</p>
                                </div>
                                <span :class="['text-[9px] font-black uppercase px-2.5 py-1 rounded-full ring-1 flex items-center gap-1 flex-shrink-0', alert.type === 'out' ? 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30' : 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30']">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ alert.type === 'out' ? 'Out' : 'Low' }}
                                </span>
                            </div>
                        </div>
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
</style>
