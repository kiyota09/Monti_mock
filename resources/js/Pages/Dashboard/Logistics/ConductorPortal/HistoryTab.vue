<template>
    <div class="space-y-5">
        <section class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Completed trips</p>
                <p class="mt-1 text-2xl font-black text-gray-900 dark:text-white">{{ history.length }}</p>
                <p class="text-[11px] text-gray-400">Recent runs</p>
            </div>
            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Total collected</p>
                <p class="mt-1 text-2xl font-black text-emerald-600 dark:text-emerald-300">{{ money(totalCollected) }}</p>
                <p class="text-[11px] text-gray-400">Across listed trips</p>
            </div>
            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Returns logged</p>
                <p class="mt-1 text-2xl font-black text-rose-600 dark:text-rose-300">{{ totalReturns }}</p>
                <p class="text-[11px] text-gray-400">Across listed trips</p>
            </div>
        </section>

        <div class="space-y-3">
            <article v-for="delivery in history" :key="delivery.id"
                class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span :class="delivery.status === 'delivered' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-300'"
                                class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase">{{ delivery.status }}</span>
                            <span class="text-xs font-bold text-gray-400">{{ delivery.delivery_number }}</span>
                        </div>
                        <p class="mt-1 truncate text-sm font-black text-gray-900 dark:text-white">{{ delivery.route?.name || 'Route' }}</p>
                        <p class="text-xs text-gray-500">
                            {{ delivery.driver?.user?.name || '—' }} · {{ delivery.truck?.truck_number || '—' }} · {{ delivery.stops?.length || 0 }} stops · {{ Number(delivery.total_distance_km || 0).toFixed(1) }} km
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Completed</p>
                        <p class="text-xs font-bold text-gray-900 dark:text-white">{{ formatDateTime(delivery.arrival_time || delivery.updated_at) }}</p>
                    </div>
                </div>

                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                    <div class="rounded-2xl bg-gray-50 px-3 py-2 dark:bg-zinc-800/60">
                        <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Collected</p>
                        <p class="text-sm font-black text-gray-900 dark:text-white">{{ money(collectedFor(delivery)) }}</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 px-3 py-2 dark:bg-zinc-800/60">
                        <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Returns</p>
                        <p class="text-sm font-black text-gray-900 dark:text-white">{{ returnsFor(delivery).length }}</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 px-3 py-2 dark:bg-zinc-800/60">
                        <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Packages</p>
                        <p class="text-sm font-black text-gray-900 dark:text-white">{{ delivery.packages?.length || 0 }}</p>
                    </div>
                </div>

                <div v-if="returnsFor(delivery).length" class="mt-2 flex flex-wrap gap-1.5">
                    <span v-for="entry in returnsFor(delivery)" :key="entry.id"
                        class="rounded-lg bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-600 dark:bg-rose-900/20 dark:text-rose-300">
                        {{ entry.reason.replace('_', ' ') }} · {{ entry.quantity }} pc(s)
                    </span>
                </div>
            </article>

            <div v-if="!history.length" class="rounded-3xl border border-dashed border-gray-200 bg-white py-16 text-center dark:border-zinc-800 dark:bg-zinc-900">
                <RouteIcon class="mx-auto h-8 w-8 text-gray-300" />
                <p class="mt-2 text-sm font-bold text-gray-500 dark:text-gray-400">No completed trips yet.</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Route as RouteIcon } from 'lucide-vue-next';

const props = defineProps({
    history: { type: Array, default: () => [] },
    collections: { type: Array, default: () => [] },
    returns: { type: Array, default: () => [] },
    notify: { type: Function, required: true },
});

const collectedFor = (delivery) =>
    props.collections
        .filter((collection) => Number(collection.delivery_id) === Number(delivery.id) && collection.status !== 'void')
        .reduce((sum, collection) => sum + (Number(collection.amount) || 0), 0);

const returnsFor = (delivery) =>
    props.returns.filter((entry) => Number(entry.delivery_id) === Number(delivery.id));

const totalCollected = computed(() =>
    props.collections
        .filter((collection) => collection.status !== 'void')
        .reduce((sum, collection) => sum + (Number(collection.amount) || 0), 0)
);

const totalReturns = computed(() => props.returns.length);

const money = (value) => {
    const amount = Number(value) || 0;
    return `₱${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const formatDateTime = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>
