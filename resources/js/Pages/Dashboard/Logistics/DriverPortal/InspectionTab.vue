<template>
    <div class="grid gap-5 lg:grid-cols-5">
        <section class="space-y-4 rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-3">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">DVIR</p>
                <h2 class="mt-1 text-lg font-black text-gray-900 dark:text-white">Driver Vehicle Inspection Report</h2>
                <p class="text-xs text-gray-500">Complete before and after every trip.</p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Inspection type</label>
                        <select v-model="form.type" class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                            <option value="pre_trip">Pre-trip</option>
                            <option value="post_trip">Post-trip</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Vehicle</label>
                        <select v-model="form.truck_id" class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                            <option value="">Select vehicle</option>
                            <option v-for="truck in trucks" :key="truck.id" :value="truck.id">
                                {{ truck.truck_number }} · {{ truck.plate_number }}
                            </option>
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
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Defect severity</label>
                        <select v-model="form.severity" class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                            <option value="none">No defects</option>
                            <option value="minor">Minor</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Photo of defect</label>
                        <input type="file" accept="image/*" @change="onPhoto" class="w-full text-xs text-gray-500 dark:text-gray-400" />
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Defect description</label>
                    <textarea v-model="form.defects" rows="3" placeholder="Describe any damage, warning lights, or unsafe conditions..."
                        class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                </div>

                <button type="submit" :disabled="form.processing"
                    class="w-full rounded-2xl bg-indigo-600 py-3 text-[10px] font-black uppercase tracking-widest text-white hover:bg-indigo-700 disabled:opacity-50">
                    {{ form.processing ? 'Submitting…' : 'Submit inspection' }}
                </button>
            </form>
        </section>

        <aside class="space-y-4 lg:col-span-2">
            <section v-if="currentTruck" class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Assigned vehicle</p>
                <p class="mt-1 text-lg font-black text-gray-900 dark:text-white">{{ currentTruck.truck_number }}</p>
                <p class="text-xs text-gray-500">{{ currentTruck.model }} · {{ currentTruck.plate_number }}</p>
                <div class="mt-3 flex items-center justify-between rounded-2xl bg-gray-50 px-3 py-2 dark:bg-zinc-800/60">
                    <span class="text-[11px] font-bold text-gray-500">Maintenance status</span>
                    <span :class="truckStatusClass(currentTruck.status)" class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase">{{ currentTruck.status.replace('_', ' ') }}</span>
                </div>
                <p class="mt-2 text-[11px] text-gray-400">Mileage: {{ Number(currentTruck.mileage || 0).toLocaleString() }} km</p>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800">
                    <h3 class="text-sm font-black uppercase tracking-widest text-gray-500">Recent inspections</h3>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-zinc-800">
                    <div v-for="inspection in inspections" :key="inspection.id" class="px-5 py-3">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs font-black text-gray-900 dark:text-white">{{ inspection.truck?.truck_number || 'Vehicle' }} · {{ inspection.type.replace('_', ' ') }}</p>
                            <span :class="inspection.severity === 'critical' ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300' : inspection.severity === 'minor' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'"
                                class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase">{{ inspection.severity }}</span>
                        </div>
                        <p class="text-[11px] text-gray-500">{{ formatDateTime(inspection.inspected_at) }}</p>
                        <p v-if="inspection.defects" class="mt-1 text-[11px] text-gray-500">{{ inspection.defects }}</p>
                    </div>
                    <div v-if="!inspections.length" class="px-5 py-8 text-center text-xs font-bold text-gray-400">No inspections logged yet.</div>
                </div>
            </section>
        </aside>
    </div>
</template>

<script setup>
import { reactive, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { CircleDot, Disc, Lightbulb, Droplets, Link, Eye, Volume2, Wind, ShieldCheck, FileText } from 'lucide-vue-next';

const props = defineProps({
    trucks: { type: Array, default: () => [] },
    currentTruck: { type: Object, default: null },
    inspections: { type: Array, default: () => [] },
    deliveries: { type: Array, default: () => [] },
    notify: { type: Function, required: true },
});

const checklistItems = [
    { key: 'tires', label: 'Tires', icon: CircleDot },
    { key: 'brakes', label: 'Brakes', icon: Disc },
    { key: 'lights', label: 'Lights', icon: Lightbulb },
    { key: 'fluids', label: 'Fluids', icon: Droplets },
    { key: 'hitch', label: 'Hitch', icon: Link },
    { key: 'mirrors', label: 'Mirrors', icon: Eye },
    { key: 'horn', label: 'Horn', icon: Volume2 },
    { key: 'wipers', label: 'Wipers', icon: Wind },
    { key: 'emergency_kit', label: 'Emergency kit', icon: ShieldCheck },
    { key: 'documents', label: 'Documents', icon: FileText },
];

const blankChecklist = () => checklistItems.reduce((acc, item) => ({ ...acc, [item.key]: false }), {});

const checks = reactive(blankChecklist());
const resetChecks = () => checklistItems.forEach((item) => { checks[item.key] = false; });

const form = useForm({
    type: 'pre_trip',
    truck_id: props.currentTruck?.id || props.trucks[0]?.id || '',
    delivery_id: '',
    checklist: blankChecklist(),
    defects: '',
    severity: 'none',
    photo: null,
});

watch(() => props.currentTruck, (truck) => {
    if (truck && !form.truck_id) form.truck_id = truck.id;
});

const onPhoto = (event) => {
    form.photo = event.target.files[0] || null;
};

const submit = () => {
    form.checklist = { ...checks };
    form.post(route('logistics.driver.inspection.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            props.notify('success', 'Inspection submitted.');
            form.reset('defects', 'photo');
            resetChecks();
            form.severity = 'none';
        },
        onError: (errors) => props.notify('error', Object.values(errors)[0] || 'Could not submit inspection.'),
    });
};

const truckStatusClass = (status) => ({
    available: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    in_use: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    under_maintenance: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    retired: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
}[status] || 'bg-gray-100 text-gray-500');

const formatDateTime = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>
