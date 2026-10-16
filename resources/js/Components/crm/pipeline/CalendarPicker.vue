<script setup>
import { ref, computed } from 'vue';
import { X, Check } from 'lucide-vue-next';

// Minimal calendar: 14-day strip + working-hour slots (8:00–17:00).
// Click a slot → small confirm popup (Save & Close) → emits pick { at }.
const emit = defineEmits(['pick', 'close']);

const days = computed(() => {
    const out = [];
    const now = new Date();
    for (let i = 0; i < 14; i++) {
        const d = new Date(now);
        d.setDate(now.getDate() + i);
        out.push(d);
    }
    return out;
});
const slots = ['08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00'];

const selDay = ref(new Date());
const selKey = ref(null); // `${dayISO}|${slot}` — fixes always-on slot highlight
const pending = ref(null); // { at, label } awaiting confirm

const dayISO = (d) => `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
const sameDay = (a, b) =>
    a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();

const choose = (day, slot) => {
    selDay.value = day;
    selKey.value = `${dayISO(day)}|${slot}`;
    const [h, m] = slot.split(':').map(Number);
    const at = new Date(day);
    at.setHours(h, m, 0, 0);
    pending.value = { at: at.toISOString(), label: `${at.toLocaleDateString()} ${slot}` };
};

const fmtDay = (d) => d.toLocaleDateString('en-US', { weekday: 'short' });
const fmtNum = (d) => d.getDate();
</script>

<template>
    <div class="rounded-2xl border border-indigo-100 bg-indigo-50/50 p-4 dark:border-indigo-900/40 dark:bg-indigo-950/20">
        <div class="mb-2 flex items-center justify-between">
            <p class="text-[11px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-300">Pick a slot — next 14 days</p>
            <button @click="emit('close')" class="rounded-full p-0.5 text-gray-400 hover:bg-white hover:text-gray-600" aria-label="Hide calendar"><X class="h-4 w-4" /></button>
        </div>
        <div class="flex gap-1.5 overflow-x-auto pb-2" role="listbox" aria-label="Day">
            <button
                v-for="d in days"
                :key="d.toISOString()"
                @click="selDay = d"
                :class="[
                    'flex w-12 shrink-0 flex-col items-center rounded-xl border py-2 text-xs font-bold transition',
                    sameDay(d, selDay)
                        ? 'border-indigo-500 bg-indigo-600 text-white'
                        : 'border-gray-200 bg-white text-gray-500 hover:border-indigo-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-slate-300',
                ]"
                :aria-selected="sameDay(d, selDay)"
            >
                <span class="text-[9px] uppercase">{{ fmtDay(d) }}</span>
                <span class="text-sm font-black">{{ fmtNum(d) }}</span>
            </button>
        </div>
        <div class="mt-2 grid grid-cols-3 gap-1.5">
            <button
                v-for="s in slots"
                :key="s"
                @click="choose(selDay, s)"
                :class="[
                    'rounded-lg border px-2 py-1.5 text-xs font-bold transition',
                    selKey === `${dayISO(selDay)}|${s}`
                        ? 'border-indigo-500 bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-200'
                        : 'border-gray-200 bg-white text-gray-500 hover:border-indigo-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-slate-300',
                ]"
                :aria-pressed="selKey === `${dayISO(selDay)}|${s}`"
            >
                {{ s }}
            </button>
        </div>

        <!-- Confirm popup -->
        <div v-if="pending" class="mt-3 rounded-xl border border-indigo-200 bg-white p-3 shadow dark:border-indigo-900 dark:bg-zinc-900">
            <p class="text-xs font-bold dark:text-white">Schedule for {{ pending.label }}?</p>
            <p class="text-[11px] text-gray-400">Default settings · linked to this opportunity</p>
            <div class="mt-2 flex gap-2">
                <button @click="pending = null; selKey = null" class="flex-1 rounded-lg bg-gray-100 py-2 text-[11px] font-black uppercase text-gray-500 hover:bg-gray-200 dark:bg-zinc-800">Back</button>
                <button @click="emit('pick', pending)" class="inline-flex flex-1 items-center justify-center gap-1 rounded-lg bg-indigo-600 py-2 text-[11px] font-black uppercase text-white hover:bg-indigo-700">
                    <Check class="h-3.5 w-3.5" /> Save &amp; Close
                </button>
            </div>
        </div>
    </div>
</template>
