<template>
    <AuthenticatedLayout>
        <Head :title="`Track ${delivery.delivery_number}`" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6 pb-16">

                <div class="flex flex-wrap items-center gap-2">
                    <Link :href="route('ord.delivery')" class="inline-flex items-center gap-1 text-sm font-bold text-indigo-600 hover:underline">
                        <ChevronLeft class="h-4 w-4" /> All deliveries
                    </Link>
                    <button v-if="canEditDelivery" @click="sync" :disabled="syncing" class="ml-auto rounded-xl bg-indigo-600 px-4 py-2 text-xs font-black text-white hover:bg-indigo-500 disabled:opacity-50">
                        {{ syncing ? 'Syncing…' : 'Sync POD to job orders' }}
                    </button>
                </div>

                <!-- Header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="relative flex flex-wrap items-start gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30"><Truck class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">Shipment tracking · {{ delivery.client_name || 'No client' }}</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">{{ delivery.delivery_number }}</h1>
                            <p class="text-sm text-blue-100/90">{{ delivery.origin || 'Plant' }} → {{ delivery.destination || 'Client site' }}</p>
                        </div>
                        <span class="rounded-full bg-white/95 px-3 py-1.5 text-xs font-black uppercase text-indigo-700">{{ formatStatus(delivery.status) }}</span>
                    </div>
                    <!-- Milestone timeline -->
                    <div class="relative mt-6 flex items-center">
                        <div v-for="(t, i) in timeline" :key="t.key" class="flex-1 flex items-center last:flex-none">
                            <div class="flex flex-col items-center text-center">
                                <div class="flex h-9 w-9 items-center justify-center rounded-full ring-2 font-black text-xs" :class="t.done ? 'bg-emerald-400 text-emerald-950 ring-emerald-200' : 'bg-white/15 text-white ring-white/30'">
                                    <Check v-if="t.done" class="h-4 w-4" /><span v-else>{{ i + 1 }}</span>
                                </div>
                                <p class="mt-1 text-[10px] font-black uppercase tracking-wide">{{ t.label }}</p>
                                <p class="text-[10px] text-blue-100">{{ t.at || '—' }}</p>
                            </div>
                            <div v-if="i < timeline.length - 1" class="mx-1 mb-8 h-0.5 flex-1 rounded" :class="t.done ? 'bg-emerald-300' : 'bg-white/25'" />
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                    <!-- Crew + cargo -->
                    <div class="xl:col-span-2 space-y-4">
                        <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 p-5">
                            <h2 class="font-black mb-3">Crew & vehicle</h2>
                            <div class="grid sm:grid-cols-3 gap-2 text-sm">
                                <div class="rounded-2xl bg-slate-50 dark:bg-zinc-800 p-3"><p class="text-[10px] font-bold uppercase text-gray-400">Driver</p><p class="font-bold">{{ delivery.driver_name || '—' }}</p></div>
                                <div class="rounded-2xl bg-slate-50 dark:bg-zinc-800 p-3"><p class="text-[10px] font-bold uppercase text-gray-400">Truck</p><p class="font-bold">{{ delivery.truck_number || '—' }}</p></div>
                                <div class="rounded-2xl bg-slate-50 dark:bg-zinc-800 p-3"><p class="text-[10px] font-bold uppercase text-gray-400">Route</p><p class="font-bold">{{ delivery.route_name || '—' }}</p></div>
                            </div>
                            <div v-if="delivery.notes" class="mt-2 text-xs text-gray-500 italic">{{ delivery.notes }}</div>
                        </div>

                        <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 p-5">
                            <h2 class="font-black mb-3">Cargo — {{ delivery.package_count }} packages</h2>
                            <div class="space-y-1.5">
                                <div v-for="p in delivery.packages" :key="p.package_number" class="flex items-center gap-3 rounded-2xl border border-gray-100 dark:border-zinc-800 px-3 py-2 text-sm">
                                    <Package class="h-4 w-4 text-indigo-500" />
                                    <span class="font-black">{{ p.package_number }}</span>
                                    <span class="text-gray-500 truncate">{{ p.product_name }}</span>
                                    <span class="ml-auto font-black">{{ p.quantity }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 p-5">
                            <h2 class="font-black mb-3">Linked job orders</h2>
                            <div v-if="!linkedOrders.length" class="text-sm text-gray-400">No job orders resolved from cargo packages.</div>
                            <div v-else class="space-y-1.5">
                                <Link v-for="o in linkedOrders" :key="o.id" :href="route('ord.orders.show', { type: 'so', id: o.id })" class="flex items-center gap-3 rounded-2xl border border-gray-100 dark:border-zinc-800 px-3 py-2 text-sm hover:border-indigo-300 hover:shadow transition-all">
                                    <span class="font-black">{{ o.jo_number }}</span>
                                    <span class="text-gray-500 truncate">{{ o.client_name }}</span>
                                    <span class="ml-auto rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-black uppercase text-indigo-700">{{ formatStatus(o.status) }}</span>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- POD -->
                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 p-5 h-fit">
                        <h2 class="font-black mb-3 flex items-center gap-2"><BadgeCheck class="h-4 w-4 text-emerald-500" /> Proof of delivery</h2>
                        <div v-if="delivery.proof" class="text-sm space-y-1.5">
                            <p><span class="text-gray-400 text-xs font-bold uppercase">Received at</span><br /><span class="font-bold">{{ delivery.proof.received_at || '—' }}</span></p>
                            <p v-if="delivery.proof.notes"><span class="text-gray-400 text-xs font-bold uppercase">Notes</span><br />{{ delivery.proof.notes }}</p>
                            <p v-if="delivery.proof.image" class="text-xs font-black text-indigo-600">Photo evidence on file ({{ delivery.proof.image }})</p>
                        </div>
                        <p v-else class="text-sm text-gray-400">No proof of delivery captured yet.</p>
                        <div class="mt-4 rounded-2xl bg-slate-50 dark:bg-zinc-800 p-3 text-xs space-y-1">
                            <p class="flex justify-between"><span class="text-gray-400 font-bold">Scheduled</span><span class="font-bold">{{ delivery.scheduled_departure || '—' }}</span></p>
                            <p class="flex justify-between"><span class="text-gray-400 font-bold">Departed</span><span class="font-bold">{{ delivery.actual_departure || '—' }}</span></p>
                            <p class="flex justify-between"><span class="text-gray-400 font-bold">Arrived</span><span class="font-bold">{{ delivery.arrival_time || '—' }}</span></p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePageAccess } from '@/composables/usePageAccess';
import { BadgeCheck, Check, ChevronLeft, Package, Truck } from 'lucide-vue-next';

const props = defineProps({ delivery: Object, linkedOrders: Array, timeline: Array });

const { canEdit } = usePageAccess();
const canEditDelivery = computed(() => canEdit('ORD', 'delivery'));

const syncing = ref(false);
const sync = () => {
    syncing.value = true;
    router.post(route('ord.delivery.sync', { id: props.delivery.id }), {}, {
        preserveScroll: true,
        onFinish: () => { syncing.value = false; },
    });
};

const formatStatus = (s) => String(s || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
</script>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
</style>
