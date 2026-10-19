<template>
    <div class="grid gap-5 lg:grid-cols-5">
        <section class="space-y-4 rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-3">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Load Verification</p>
                <h2 class="mt-1 text-lg font-black text-gray-900 dark:text-white">Pre-trip / post-trip check</h2>
                <p class="text-xs text-gray-500">Verify the load and vehicle before leaving Monti and after the last stop.</p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Trip *</label>
                        <select v-model="form.delivery_id" required class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                            <option value="">Select trip</option>
                            <option v-for="delivery in deliveryOptions" :key="delivery.id" :value="delivery.id">
                                {{ delivery.delivery_number }} · {{ delivery.status }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Check type</label>
                        <select v-model="form.type" class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                            <option value="pre_trip">Pre-trip (departure)</option>
                            <option value="post_trip">Post-trip (arrival)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-[10px] font-black uppercase tracking-wider text-gray-500">Checklist</label>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <button v-for="item in checklistItems" :key="item.key" type="button"
                            @click="checks[item.key] = !checks[item.key]"
                            :aria-pressed="checks[item.key]"
                            :class="checks[item.key] ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-700 dark:bg-emerald-900/20' : 'border-gray-200 bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800'"
                            class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2.5 text-left text-xs font-bold text-gray-700 transition dark:text-gray-200">
                            <span :class="checks[item.key] ? 'border-emerald-600 bg-emerald-600' : 'border-gray-300 bg-white dark:border-zinc-600 dark:bg-zinc-700'"
                                class="flex h-4 w-4 shrink-0 items-center justify-center rounded border">
                                <svg v-if="checks[item.key]" class="h-3 w-3 text-white" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                    <path d="M12.207 4.793a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L6.5 9.086l4.293-4.293a1 1 0 011.414 0z" />
                                </svg>
                            </span>
                            <component :is="item.icon" class="h-3.5 w-3.5 text-gray-400" />
                            {{ item.label }}
                        </button>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Severity</label>
                        <select v-model="form.severity" class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                            <option value="none">No discrepancy</option>
                            <option value="minor">Minor</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Photo evidence</label>
                        <input type="file" accept="image/*" @change="onPhoto" class="w-full text-xs text-gray-500 dark:text-gray-400" />
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Discrepancies / remarks</label>
                    <textarea v-model="form.discrepancies" rows="3" placeholder="Missing packages, wet load, torn packaging, wrong labels..."
                        class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                </div>

                <button type="submit" :disabled="form.processing"
                    class="w-full rounded-2xl bg-indigo-600 py-3 text-[10px] font-black uppercase tracking-widest text-white hover:bg-indigo-700 disabled:opacity-50">
                    {{ form.processing ? 'Submitting…' : 'Submit load check' }}
                </button>
            </form>
        </section>

        <aside class="space-y-4 lg:col-span-2">
            <section v-if="activeLoad" class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Current load</p>
                <p class="mt-1 text-lg font-black text-gray-900 dark:text-white">{{ activeLoad.delivery_number }}</p>
                <p class="text-xs text-gray-500">{{ activeLoad.packages?.length || 0 }} package(s) · {{ totalQuantity(activeLoad) }} pcs</p>
                <div class="mt-3 space-y-1.5">
                    <div v-for="stop in activeLoad.stops || []" :key="stop.id" class="flex items-center justify-between rounded-xl bg-gray-50 px-3 py-2 text-[11px] dark:bg-zinc-800/60">
                        <span class="min-w-0 truncate font-bold text-gray-700 dark:text-gray-200">{{ stop.sequence }}. {{ stop.label || stop.client?.company_name }}</span>
                        <span class="ml-2 shrink-0 text-gray-400">{{ stopPackages(activeLoad, stop).length }} pkg</span>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800">
                    <h3 class="text-sm font-black uppercase tracking-widest text-gray-500">Recent checks</h3>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-zinc-800">
                    <div v-for="check in loadChecks" :key="check.id" class="px-5 py-3">
                        <div class="flex items-center justify-between gap-2">
                            <p class="truncate text-xs font-black text-gray-900 dark:text-white">{{ check.delivery?.delivery_number }} · {{ check.type.replace('_', ' ') }}</p>
                            <span :class="severityClass(check.severity)" class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase">{{ check.severity }}</span>
                        </div>
                        <p class="text-[11px] text-gray-500">{{ formatDateTime(check.checked_at) }}</p>
                        <p v-if="check.discrepancies" class="mt-1 text-[11px] text-gray-500">{{ check.discrepancies }}</p>
                    </div>
                    <div v-if="!loadChecks.length" class="px-5 py-8 text-center text-xs font-bold text-gray-400">No load checks logged yet.</div>
                </div>
            </section>
        </aside>
    </div>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { PackageCheck, Tags, Ruler, ShieldCheck, Droplets, FileText, Truck, AlertTriangle } from 'lucide-vue-next';

const props = defineProps({
    deliveries: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
    loadChecks: { type: Array, default: () => [] },
    currentDelivery: { type: Object, default: null },
    notify: { type: Function, required: true },
});

const checklistItems = [
    { key: 'count', label: 'Package count', icon: PackageCheck },
    { key: 'labels', label: 'Labels / DR match', icon: Tags },
    { key: 'quantity', label: 'Quantities / meters', icon: Ruler },
    { key: 'secured', label: 'Load secured', icon: ShieldCheck },
    { key: 'clean_dry', label: 'Clean & dry', icon: Droplets },
    { key: 'no_damage', label: 'No damage', icon: AlertTriangle },
    { key: 'documents', label: 'DR / BOL complete', icon: FileText },
    { key: 'vehicle', label: 'Vehicle condition', icon: Truck },
];

const blankChecklist = () => checklistItems.reduce((acc, item) => ({ ...acc, [item.key]: false }), {});

const checks = reactive(blankChecklist());
const resetChecks = () => checklistItems.forEach((item) => { checks[item.key] = false; });

const form = useForm({
    delivery_id: props.currentDelivery?.id || '',
    type: 'pre_trip',
    checklist: blankChecklist(),
    discrepancies: '',
    severity: 'none',
    photo: null,
});

const deliveryOptions = computed(() => [...props.deliveries, ...props.history]);

const activeLoad = computed(() =>
    props.currentDelivery || props.deliveries[0] || null
);

const totalQuantity = (delivery) =>
    (delivery.packages || []).reduce((sum, p) => sum + (Number(p.quantity) || 0), 0);

const stopPackages = (delivery, stop) =>
    (delivery.packages || []).filter((pkg) => Number(pkg.pivot?.delivery_stop_id) === Number(stop.id));

const onPhoto = (event) => {
    form.photo = event.target.files[0] || null;
};

const submit = () => {
    form.checklist = { ...checks };
    form.post(route('logistics.conductor.load-check.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            props.notify('success', 'Load check recorded.');
            form.reset('discrepancies', 'photo');
            resetChecks();
            form.severity = 'none';
        },
        onError: (errors) => props.notify('error', Object.values(errors)[0] || 'Could not save load check.'),
    });
};

const severityClass = (severity) => ({
    none: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    minor: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    critical: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
}[severity] || 'bg-gray-100 text-gray-500');

const formatDateTime = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>
