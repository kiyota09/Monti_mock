<template>
    <Head title="Client Dashboard" />
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
                            <Layers class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> Partner Portal
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Welcome back, {{ client?.company_name || 'Partner' }}</h1>
                            <p class="text-sm text-blue-100/90">Track your orders, manage inquiries, and access quotations in one place.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse" /> {{ client?.status || 'Active' }}
                            </span>
                        </div>
                    </div>

                    <!-- Stat strip -->
                    <div class="relative mt-6 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:80ms">
                            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-blue-100"><Package class="h-3.5 w-3.5" /> Total orders</p>
                            <p class="mt-1 text-lg font-black">{{ stats.totalOrders }}</p>
                        </div>
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:160ms">
                            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-blue-100"><MessageSquare class="h-3.5 w-3.5" /> Active inquiries</p>
                            <p class="mt-1 text-lg font-black">{{ stats.activeInquiries }}</p>
                        </div>
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:240ms">
                            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-blue-100"><FileText class="h-3.5 w-3.5" /> Pending quotations</p>
                            <p class="mt-1 text-lg font-black">{{ stats.pendingQuotations }}</p>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:80ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 via-blue-600 to-cyan-600 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Package class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Total Orders</p>
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ stats.totalOrders }}</h3>
                            </div>
                            <ShoppingBag class="ml-auto h-8 w-8 text-indigo-200 dark:text-zinc-700 group-hover:scale-110 transition-transform" />
                        </div>
                    </div>

                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:160ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-cyan-400/20 to-blue-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-cyan-500 to-blue-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-500 via-sky-600 to-blue-700 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <MessageSquare class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Active Inquiries</p>
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ stats.activeInquiries }}</h3>
                            </div>
                        </div>
                    </div>

                    <div class="animate-fade-up group relative flex flex-col rounded-3xl bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 p-5 text-white shadow-lg shadow-orange-500/20 hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 transition-all duration-300 overflow-hidden" style="animation-delay:240ms">
                        <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10 blur-2xl group-hover:scale-125 transition-transform duration-500" />
                        <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                        <div class="relative flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <FileText class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-amber-100">Pending Quotations</p>
                                <h3 class="text-3xl font-black leading-none">{{ stats.pendingQuotations }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders & Quick Actions -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    <!-- Recent Orders Table -->
                    <div class="animate-fade-up lg:col-span-2 bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden hover:shadow-xl transition-shadow duration-300" style="animation-delay:120ms">
                        <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                            <Clock class="h-5 w-5 text-indigo-600" />
                            <h2 class="text-sm font-black uppercase tracking-widest">Recent Orders</h2>
                            <Link :href="route('client.orders')" class="ml-auto text-xs font-bold text-indigo-600 hover:underline">View All</Link>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 dark:border-zinc-800">
                                    <tr>
                                        <th class="px-6 py-4">Order #</th>
                                        <th class="px-6 py-4">Date</th>
                                        <th class="px-6 py-4 text-right">Total</th>
                                        <th class="px-6 py-4 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-zinc-800">
                                    <tr v-for="order in recentOrders" :key="order.id" class="text-sm hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors">
                                        <td class="px-6 py-3 font-mono font-bold text-gray-900 dark:text-white">{{ order.po_number }}</td>
                                        <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ formatDate(order.created_at) }}</td>
                                        <td class="px-6 py-3 text-right font-black text-gray-900 dark:text-white">₱{{ formatCurrency(order.total_amount) }}</td>
                                        <td class="px-6 py-3 text-center">
                                            <span :class="order.status === 'approved' ? 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30' : 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30'"
                                                class="px-2.5 py-1 rounded-full ring-1 text-[9px] font-black uppercase inline-flex items-center gap-1">
                                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ order.status.replace('_', ' ') }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="recentOrders.length === 0">
                                        <td colspan="4" class="px-6 py-12 text-center">
                                            <ShoppingBag class="mx-auto h-10 w-10 text-gray-200 dark:text-zinc-700" />
                                            <p class="mt-2 text-sm font-bold text-gray-400">No orders yet.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Quick Actions Sidebar -->
                    <div class="space-y-5">
                        <div class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl border border-gray-100 dark:border-zinc-800 p-6 hover:shadow-xl transition-shadow duration-300" style="animation-delay:200ms">
                            <h3 class="text-sm font-black uppercase tracking-widest mb-4">Quick Actions</h3>
                            <div class="space-y-3">
                                <Link :href="route('client.products')" class="group flex items-center justify-between p-3 rounded-2xl border border-transparent bg-indigo-50/70 dark:bg-indigo-900/10 hover:border-indigo-200 dark:hover:border-indigo-800 hover:-translate-y-0.5 hover:shadow-lg transition-all">
                                    <span class="font-bold text-sm text-indigo-700 dark:text-indigo-300">Browse Products</span>
                                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 group-hover:bg-indigo-600 group-hover:text-white transition-all"><ShoppingBag class="h-4 w-4" /></span>
                                </Link>
                                <Link :href="route('client.conversations')" class="group flex items-center justify-between p-3 rounded-2xl border border-transparent bg-cyan-50/70 dark:bg-cyan-900/10 hover:border-cyan-200 dark:hover:border-cyan-800 hover:-translate-y-0.5 hover:shadow-lg transition-all">
                                    <span class="font-bold text-sm text-cyan-700 dark:text-cyan-300">View Conversations</span>
                                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 group-hover:bg-cyan-600 group-hover:text-white transition-all"><MessageSquare class="h-4 w-4" /></span>
                                </Link>
                                <Link :href="route('client.invoices')" class="group flex items-center justify-between p-3 rounded-2xl border border-transparent bg-emerald-50/70 dark:bg-emerald-900/10 hover:border-emerald-200 dark:hover:border-emerald-800 hover:-translate-y-0.5 hover:shadow-lg transition-all">
                                    <span class="font-bold text-sm text-emerald-700 dark:text-emerald-300">Invoices &amp; Payments</span>
                                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 group-hover:bg-emerald-600 group-hover:text-white transition-all"><CreditCard class="h-4 w-4" /></span>
                                </Link>
                            </div>
                        </div>

                        <!-- Support Widget -->
                        <div class="animate-fade-up bg-gradient-to-br from-indigo-600 to-violet-700 rounded-3xl p-6 text-white shadow-lg shadow-indigo-500/25 relative overflow-hidden group hover:scale-[1.01] transition-transform" style="animation-delay:280ms">
                            <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10 blur-2xl group-hover:scale-125 transition-transform duration-500" />
                            <p class="relative text-[10px] font-black uppercase tracking-[0.2em] text-indigo-100">Need help?</p>
                            <p class="relative text-xs text-indigo-100 mt-1">Our support team is ready to assist you.</p>
                            <Link :href="route('client.support')" class="relative mt-4 inline-flex items-center gap-2 text-xs font-black uppercase tracking-wide bg-white text-indigo-700 px-4 py-2.5 rounded-xl hover:bg-indigo-50 active:scale-95 transition">
                                Contact Support <ArrowRight class="h-3 w-3" />
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Deliveries -->
                <div class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden" style="animation-delay:160ms">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2 bg-gradient-to-r from-blue-50 to-transparent dark:from-blue-900/10">
                        <Truck class="h-5 w-5 text-blue-600" />
                        <h3 class="text-sm font-black uppercase tracking-widest">Upcoming Deliveries</h3>
                        <span class="ml-auto rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 text-[11px] font-black px-2.5 py-1">{{ pendingDeliveries.length }}</span>
                    </div>
                    <div class="p-6">
                        <div v-if="pendingDeliveries.length === 0" class="text-center py-8">
                            <Truck class="mx-auto h-10 w-10 text-gray-200 dark:text-zinc-700 animate-bounce-soft" />
                            <p class="mt-2 text-sm font-bold text-gray-400">No upcoming deliveries at the moment.</p>
                        </div>
                        <TransitionGroup v-else name="card" tag="div" class="space-y-3">
                            <div v-for="(delivery, i) in pendingDeliveries" :key="delivery.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }" class="group flex items-center justify-between rounded-2xl border border-gray-100 dark:border-zinc-800 p-4 hover:shadow-lg hover:-translate-y-0.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="h-2.5 w-2.5 rounded-full bg-blue-500 shadow-lg shadow-blue-500/40 animate-pulse shrink-0" />
                                    <div class="min-w-0">
                                        <p class="font-bold text-sm text-gray-900 dark:text-white truncate">{{ delivery.po_number }}</p>
                                        <p class="text-xs text-gray-400">Expected: {{ formatDate(delivery.expected_date) }}</p>
                                    </div>
                                </div>
                                <span class="px-2.5 py-1 bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300 rounded-full ring-1 ring-blue-200 dark:ring-blue-500/30 text-[9px] font-black uppercase flex items-center gap-1 shrink-0 ml-2"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> In Transit</span>
                            </div>
                        </TransitionGroup>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Layers, ShoppingBag, Package, MessageSquare, FileText, Clock, CreditCard, ArrowRight, Truck, Sparkles } from 'lucide-vue-next';

const page = usePage();
const client = computed(() => page.props.auth?.client);

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            totalOrders: 0,
            activeInquiries: 0,
            pendingQuotations: 0
        })
    },
    recentOrders: {
        type: Array,
        default: () => []
    },
    pendingDeliveries: {
        type: Array,
        default: () => []
    }
});

const formatCurrency = (val) => Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2 });
const formatDate = (date) => new Date(date).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
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
</style>
