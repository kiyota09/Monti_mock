<template>
    <div class="space-y-5">
        <!-- Timers -->
        <section class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Driving time</p>
                    <Truck class="h-4 w-4 text-blue-500" />
                </div>
                <p class="mt-2 text-2xl font-black text-gray-900 dark:text-white">{{ formatMinutes(driving) }}</p>
                <p class="text-[11px] text-gray-400">of {{ formatMinutes(DRIVE_LIMIT) }} daily limit</p>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-zinc-800">
                    <div class="h-full rounded-full transition-all" :class="drivingPct >= 100 ? 'bg-rose-500' : drivingPct >= 80 ? 'bg-amber-500' : 'bg-blue-500'" :style="{ width: `${Math.min(drivingPct, 100)}%` }" />
                </div>
            </div>
            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">On-duty time</p>
                    <Clock class="h-4 w-4 text-indigo-500" />
                </div>
                <p class="mt-2 text-2xl font-black text-gray-900 dark:text-white">{{ formatMinutes(onDutyTotal) }}</p>
                <p class="text-[11px] text-gray-400">of {{ formatMinutes(DUTY_LIMIT) }} daily limit</p>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-zinc-800">
                    <div class="h-full rounded-full bg-indigo-500 transition-all" :style="{ width: `${Math.min(dutyPct, 100)}%` }" />
                </div>
            </div>
            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Break time</p>
                    <Coffee class="h-4 w-4 text-amber-500" />
                </div>
                <p class="mt-2 text-2xl font-black text-gray-900 dark:text-white">{{ formatMinutes(breakMinutes) }}</p>
                <p class="text-[11px] text-gray-400">Take {{ formatMinutes(BREAK_REQUIRED) }} after 4h driving</p>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-zinc-800">
                    <div class="h-full rounded-full bg-amber-500 transition-all" :style="{ width: `${Math.min(breakPct, 100)}%` }" />
                </div>
            </div>
        </section>

        <!-- Current session -->
        <section class="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Current session</p>
                <h2 class="mt-1 flex items-center gap-2 text-lg font-black text-gray-900 dark:text-white">
                    <span class="h-2.5 w-2.5 rounded-full" :class="statusDot(currentStatus)" />
                    {{ statusLabel(currentStatus) }}
                </h2>
                <p class="text-xs text-gray-500">
                    {{ currentLog ? `Started ${formatDateTime(currentLog.started_at)} · ${elapsed}` : 'No open duty session' }}
                </p>
            </div>
            <div class="rounded-2xl bg-gray-50 px-4 py-2 text-center dark:bg-zinc-800/60">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Driver</p>
                <p class="text-sm font-black text-gray-900 dark:text-white">{{ driver.user?.name || '—' }}</p>
                <p class="text-[11px] text-gray-400">Rating {{ Number(driver.rating || 0).toFixed(1) }} ★</p>
            </div>
        </section>

        <!-- Logs + signoff -->
        <div class="grid gap-5 lg:grid-cols-5">
            <section class="rounded-3xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-3">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800">
                    <h3 class="text-sm font-black uppercase tracking-widest text-gray-500">Duty logs</h3>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-zinc-800">
                    <div v-for="log in duty.logs || []" :key="log.id" class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <p class="text-xs font-black capitalize text-gray-900 dark:text-white">{{ statusLabel(log.status) }}</p>
                            <p class="text-[11px] text-gray-500">{{ formatDateTime(log.started_at) }} → {{ log.ended_at ? formatDateTime(log.ended_at) : 'open' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-black text-gray-900 dark:text-white">{{ log.duration_minutes != null ? formatMinutes(log.duration_minutes) : liveDuration(log) }}</p>
                            <span v-if="log.signature_path" class="text-[10px] font-bold text-emerald-600">Signed</span>
                            <span v-else class="text-[10px] font-bold text-gray-400">Unsigned</span>
                        </div>
                    </div>
                    <div v-if="!(duty.logs || []).length" class="px-5 py-8 text-center text-xs font-bold text-gray-400">No duty logs yet.</div>
                </div>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-2">
                <h3 class="text-sm font-black uppercase tracking-widest text-gray-500">End-of-day sign off</h3>
                <p class="mt-1 text-xs text-gray-500">Sign to certify your hours for this session.</p>
                <form @submit.prevent="signOff" class="mt-3 space-y-3">
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Notes</label>
                        <textarea v-model="form.notes" rows="2" placeholder="Optional remarks"
                            class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                    </div>
                    <SignaturePad v-model="form.signature" />
                    <button type="submit" :disabled="form.processing || !currentLog"
                        class="w-full rounded-2xl bg-indigo-600 py-3 text-[10px] font-black uppercase tracking-widest text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                        {{ form.processing ? 'Signing…' : 'Sign & close session' }}
                    </button>
                </form>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Truck, Clock, Coffee } from 'lucide-vue-next';
import SignaturePad from './SignaturePad.vue';

const props = defineProps({
    duty: { type: Object, default: () => ({ current: null, logs: [], today_minutes: {} }) },
    stats: { type: Object, default: () => ({}) },
    driver: { type: Object, default: () => ({}) },
    notify: { type: Function, required: true },
});

const DRIVE_LIMIT = 480;
const DUTY_LIMIT = 720;
const BREAK_REQUIRED = 45;

const now = ref(Date.now());
let timer = null;

onMounted(() => {
    timer = setInterval(() => { now.value = Date.now(); }, 30000);
});
onBeforeUnmount(() => clearInterval(timer));

const form = useForm({ signature: '', notes: '' });

const currentLog = computed(() => props.duty?.current || null);
const currentStatus = computed(() => currentLog.value?.status || 'off_duty');

const driving = computed(() => Number(props.duty?.today_minutes?.driving || 0));
const onDutyBase = computed(() => Number(props.duty?.today_minutes?.on_duty || 0));
const breakMinutes = computed(() => Number(props.duty?.today_minutes?.on_break || 0));

const onDutyTotal = computed(() => onDutyBase.value + driving.value);
const drivingPct = computed(() => Math.round((driving.value / DRIVE_LIMIT) * 100));
const dutyPct = computed(() => Math.round((onDutyTotal.value / DUTY_LIMIT) * 100));
const breakPct = computed(() => Math.round((breakMinutes.value / BREAK_REQUIRED) * 100));

const elapsed = computed(() => {
    if (!currentLog.value) return '';
    const start = new Date(currentLog.value.started_at).getTime();
    return formatMinutes(Math.max(0, Math.floor((now.value - start) / 60000)));
});

const statusLabel = (status) => ({
    on_duty: 'On Duty', driving: 'Driving', on_break: 'On Break', off_duty: 'Off Duty',
}[status] || 'Off Duty');

const statusDot = (status) => ({
    on_duty: 'bg-emerald-500', driving: 'bg-blue-500 animate-pulse', on_break: 'bg-amber-500', off_duty: 'bg-gray-400',
}[status] || 'bg-gray-400');

const formatMinutes = (minutes) => {
    const total = Math.max(0, Math.round(Number(minutes) || 0));
    const hours = Math.floor(total / 60);
    const mins = total % 60;
    return hours > 0 ? `${hours}h ${mins}m` : `${mins}m`;
};

const liveDuration = (log) => {
    if (!log.started_at) return '—';
    const start = new Date(log.started_at).getTime();
    return formatMinutes(Math.max(0, Math.floor((now.value - start) / 60000)));
};

const formatDateTime = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const signOff = () => {
    if (!currentLog.value) return;
    form.post(route('logistics.driver.duty.end'), {
        preserveScroll: true,
        onSuccess: () => {
            props.notify('success', 'Duty log signed and closed.');
            form.reset();
        },
        onError: (errors) => props.notify('error', Object.values(errors)[0] || 'Could not sign off.'),
    });
};
</script>
