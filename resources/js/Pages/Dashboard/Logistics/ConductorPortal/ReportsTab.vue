<template>
    <div class="grid gap-5 lg:grid-cols-5">
        <section class="space-y-4 rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-3">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">On-road reporting</p>
                <h2 class="mt-1 text-lg font-black text-gray-900 dark:text-white">Report an issue</h2>
                <p class="text-xs text-gray-500">The Logistics Manager is notified immediately. For emergencies, call local authorities first.</p>
            </div>

            <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                <button v-for="category in categories" :key="category.value" type="button" @click="form.category = category.value"
                    :class="form.category === category.value ? 'bg-rose-600 text-white ring-rose-600' : 'bg-gray-50 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700'"
                    class="flex flex-col items-center gap-1 rounded-2xl py-3 text-[9px] font-black uppercase tracking-wide ring-1 transition">
                    <component :is="category.icon" class="h-4 w-4" />
                    {{ category.label }}
                </button>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Trip *</label>
                        <select v-model="form.delivery_id" required class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                            <option value="">Select trip</option>
                            <option v-for="delivery in deliveryOptions" :key="delivery.id" :value="delivery.id">{{ delivery.delivery_number }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Severity</label>
                        <select v-model="form.severity" class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">What happened? *</label>
                    <textarea v-model="form.report_text" rows="4" required placeholder="Delay, accident, breakdown, customer concern, safety issue..."
                        class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                </div>

                <div>
                    <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Photo</label>
                    <input type="file" accept="image/*" @change="onPhoto" class="w-full text-xs text-gray-500 dark:text-gray-400" />
                </div>

                <button type="submit" :disabled="form.processing"
                    class="w-full rounded-2xl bg-rose-600 py-3 text-[10px] font-black uppercase tracking-widest text-white hover:bg-rose-700 disabled:opacity-50">
                    {{ form.processing ? 'Sending…' : 'Submit report' }}
                </button>
            </form>
        </section>

        <aside class="rounded-3xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-2">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800">
                <h3 class="text-sm font-black uppercase tracking-widest text-gray-500">My recent reports</h3>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-zinc-800">
                <div v-for="report in reports" :key="report.id" class="px-5 py-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-black capitalize text-gray-900 dark:text-white">{{ report.category || 'Other' }}</p>
                        <span :class="severityClass(report.severity)" class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase">{{ report.severity }}</span>
                    </div>
                    <p class="mt-1 line-clamp-2 text-[11px] text-gray-500">{{ report.report_text }}</p>
                    <p class="mt-1 text-[10px] text-gray-400">
                        {{ report.delivery?.delivery_number }} · {{ formatDateTime(report.reported_at || report.created_at) }} · {{ report.status }}
                    </p>
                    <a v-if="report.photo_path" :href="`/storage/${report.photo_path}`" target="_blank" class="text-[10px] font-black uppercase text-indigo-500">Photo</a>
                </div>
                <div v-if="!reports.length" class="px-5 py-8 text-center text-xs font-bold text-gray-400">No reports filed.</div>
            </div>
        </aside>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Clock, AlertTriangle, Wrench, Users, ShieldCheck, CircleEllipsis } from 'lucide-vue-next';

const props = defineProps({
    deliveries: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
    reports: { type: Array, default: () => [] },
    notify: { type: Function, required: true },
});

const categories = [
    { value: 'delay', label: 'Delay', icon: Clock },
    { value: 'accident', label: 'Accident', icon: AlertTriangle },
    { value: 'breakdown', label: 'Breakdown', icon: Wrench },
    { value: 'customer', label: 'Customer', icon: Users },
    { value: 'safety', label: 'Safety', icon: ShieldCheck },
    { value: 'other', label: 'Other', icon: CircleEllipsis },
];

const deliveryOptions = computed(() => [...props.deliveries, ...props.history]);

const form = useForm({
    delivery_id: props.deliveries[0]?.id || '',
    category: 'delay',
    severity: 'medium',
    report_text: '',
    photo: null,
});

const onPhoto = (event) => {
    form.photo = event.target.files[0] || null;
};

const submit = () => {
    if (!form.delivery_id) {
        props.notify('error', 'Select the trip this report belongs to.');
        return;
    }

    form.post(route('logistics.conductor.report', form.delivery_id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            props.notify('success', 'Report submitted to the Logistics Manager.');
            form.reset('report_text', 'photo');
        },
        onError: (errors) => props.notify('error', Object.values(errors)[0] || 'Could not submit the report.'),
    });
};

const severityClass = (severity) => ({
    low: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    medium: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    high: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
    critical: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
}[severity] || 'bg-gray-100 text-gray-500');

const formatDateTime = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>
