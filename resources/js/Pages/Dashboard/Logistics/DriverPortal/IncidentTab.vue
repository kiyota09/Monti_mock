<template>
    <div class="grid gap-5 lg:grid-cols-5">
        <section class="space-y-4 rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-3">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Incident &amp; Delay</p>
                <h2 class="mt-1 text-lg font-black text-gray-900 dark:text-white">Report a problem</h2>
                <p class="text-xs text-gray-500">Dispatch is notified immediately. For emergencies, call local authorities first.</p>
            </div>

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <button v-for="type in types" :key="type.value" type="button" @click="form.type = type.value"
                    :class="form.type === type.value ? 'bg-rose-600 text-white ring-rose-600' : 'bg-gray-50 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700'"
                    class="flex flex-col items-center gap-1 rounded-2xl py-3 text-[10px] font-black uppercase tracking-wide ring-1 transition">
                    <component :is="type.icon" class="h-4 w-4" />
                    {{ type.label }}
                </button>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div v-if="form.type === 'delay'" class="flex flex-wrap gap-2">
                    <button v-for="reason in delayReasons" :key="reason" type="button" @click="form.category = reason"
                        :class="form.category === reason ? 'bg-amber-500 text-white' : 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300'"
                        class="rounded-full px-3 py-1.5 text-[10px] font-black uppercase tracking-wide">{{ reason }}</button>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Severity</label>
                        <select v-model="form.severity" class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Related trip (optional)</label>
                        <select v-model="form.delivery_id" class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                            <option value="">None</option>
                            <option v-for="delivery in deliveries" :key="delivery.id" :value="delivery.id">{{ delivery.delivery_number }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">What happened? *</label>
                    <textarea v-model="form.description" rows="3" required placeholder="Describe the delay, accident, or breakdown..."
                        class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Location</label>
                        <div class="flex gap-2">
                            <input v-model="form.location_text" type="text" placeholder="Landmark / address"
                                class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                            <button type="button" @click="locate" :disabled="locating"
                                class="shrink-0 rounded-xl bg-indigo-50 px-3 text-[10px] font-black uppercase text-indigo-600 transition hover:bg-indigo-100 disabled:opacity-50 dark:bg-indigo-900/30 dark:text-indigo-300">
                                {{ locating ? '…' : 'GPS' }}
                            </button>
                        </div>
                        <p v-if="form.location_lat" class="mt-1 text-[10px] font-bold text-emerald-600">GPS: {{ Number(form.location_lat).toFixed(5) }}, {{ Number(form.location_lng).toFixed(5) }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Photo</label>
                        <input type="file" accept="image/*" @change="onPhoto" class="w-full text-xs text-gray-500 dark:text-gray-400" />
                    </div>
                </div>

                <div v-if="form.type === 'accident'" >
                    <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Third-party information</label>
                    <textarea v-model="form.third_party_info" rows="2" placeholder="Other vehicle plate, driver, insurance, contact..."
                        class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                </div>

                <button type="submit" :disabled="form.processing"
                    class="w-full rounded-2xl bg-rose-600 py-3 text-[10px] font-black uppercase tracking-widest text-white hover:bg-rose-700 disabled:opacity-50">
                    {{ form.processing ? 'Sending…' : 'Submit report' }}
                </button>
            </form>
        </section>

        <aside class="space-y-4 lg:col-span-2">
            <section class="rounded-3xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800">
                    <h3 class="text-sm font-black uppercase tracking-widest text-gray-500">My recent reports</h3>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-zinc-800">
                    <div v-for="incident in incidents" :key="incident.id" class="px-5 py-3">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs font-black capitalize text-gray-900 dark:text-white">{{ incident.type }} · {{ incident.category || 'General' }}</p>
                            <span :class="severityClass(incident.severity)" class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase">{{ incident.severity }}</span>
                        </div>
                        <p class="mt-1 line-clamp-2 text-[11px] text-gray-500">{{ incident.description }}</p>
                        <p class="mt-1 text-[10px] text-gray-400">{{ formatDateTime(incident.reported_at) }} · {{ incident.status }}</p>
                    </div>
                    <div v-if="!incidents.length" class="px-5 py-8 text-center text-xs font-bold text-gray-400">No reports filed.</div>
                </div>
            </section>
        </aside>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Clock, AlertTriangle, Wrench, CircleEllipsis } from 'lucide-vue-next';

const props = defineProps({
    deliveries: { type: Array, default: () => [] },
    incidents: { type: Array, default: () => [] },
    currentTruck: { type: Object, default: null },
    notify: { type: Function, required: true },
});

const types = [
    { value: 'delay', label: 'Delay', icon: Clock },
    { value: 'accident', label: 'Accident', icon: AlertTriangle },
    { value: 'breakdown', label: 'Breakdown', icon: Wrench },
    { value: 'other', label: 'Other', icon: CircleEllipsis },
];

const delayReasons = ['Traffic', 'Weather', 'Customer', 'Vehicle', 'Other'];
const locating = ref(false);

const form = useForm({
    type: 'delay',
    category: '',
    severity: 'medium',
    description: '',
    location_lat: null,
    location_lng: null,
    location_text: '',
    third_party_info: '',
    delivery_id: '',
    truck_id: props.currentTruck?.id || '',
    photo: null,
});

watch(() => form.type, (type) => {
    if (type !== 'delay') form.category = '';
});

const onPhoto = (event) => {
    form.photo = event.target.files[0] || null;
};

const locate = () => {
    if (!navigator.geolocation) {
        props.notify('error', 'Geolocation is not available on this device.');
        return;
    }
    locating.value = true;
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            form.location_lat = pos.coords.latitude;
            form.location_lng = pos.coords.longitude;
            locating.value = false;
            props.notify('success', 'GPS location captured.');
        },
        () => {
            locating.value = false;
            props.notify('error', 'Unable to get GPS location.');
        },
        { timeout: 8000 }
    );
};

const submit = () => {
    form.post(route('logistics.driver.incident.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            props.notify('success', 'Report submitted.');
            form.reset('description', 'category', 'third_party_info', 'photo', 'location_text', 'location_lat', 'location_lng');
        },
        onError: (errors) => props.notify('error', Object.values(errors)[0] || 'Could not submit report.'),
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
