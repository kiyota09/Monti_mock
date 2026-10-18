<script setup>
import { useForm } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import ModalShell from './ModalShell.vue';

// Edit stage: name, default probability, folded toggle, won-stage toggle.
const props = defineProps({ stage: { type: Object, required: true } });
const emit = defineEmits(['close']);

const form = useForm({
    name: props.stage.name,
    default_probability: props.stage.default_probability,
    is_folded: !!props.stage.is_folded,
    is_won: !!props.stage.is_won,
    is_lost: !!props.stage.is_lost,
    allows_uploads: !!props.stage.allows_uploads,
});

// Won and Lost are mutually exclusive terminal states — ticking one clears the other.
const setWon = (v) => {
    form.is_won = v;
    if (v) form.is_lost = false;
};
const setLost = (v) => {
    form.is_lost = v;
    if (v) form.is_won = false;
};

const submit = () => {
    form.patch(route('crm.stages.update', props.stage.id), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <ModalShell size="sm" @close="emit('close')">
        <template #header>
            <div class="flex items-center justify-between px-6 py-4">
                <h3 class="font-black dark:text-white">Edit Stage</h3>
                <button @click="emit('close')" aria-label="Close"><X class="h-5 w-5 text-gray-400" /></button>
            </div>
        </template>
        <form @submit.prevent="submit" class="space-y-4 px-6 pb-6">
            <div>
                <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400" for="se-name">Name *</label>
                <input id="se-name" v-model="form.name" required autofocus class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none focus:border-indigo-400 dark:bg-zinc-800 dark:text-white" />
            </div>
            <div>
                <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400" for="se-prob">
                    Default probability — {{ form.default_probability }}%
                </label>
                <input id="se-prob" v-model.number="form.default_probability" type="range" min="0" max="100" class="w-full accent-indigo-600" />
                <p class="text-[10px] text-gray-400">New and moved-in deals inherit this (still editable per deal).</p>
            </div>
            <label class="flex cursor-pointer items-center justify-between rounded-xl bg-slate-50 px-4 py-3 transition hover:bg-slate-100 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                <span class="text-xs font-bold dark:text-slate-200">Folded by default <span class="block text-[10px] font-normal text-gray-400">Collapse into a narrow column</span></span>
                <input v-model="form.is_folded" type="checkbox" class="h-5 w-5 accent-indigo-600" />
            </label>
            <label class="flex cursor-pointer items-center justify-between rounded-xl bg-slate-50 px-4 py-3 transition hover:bg-slate-100 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                <span class="text-xs font-bold dark:text-slate-200">Won stage <span class="block text-[10px] font-normal text-gray-400">Deals here count as won (100%)</span></span>
                <input :checked="form.is_won" @change="setWon($event.target.checked)" type="checkbox" class="h-5 w-5 accent-amber-500" />
            </label>
            <label class="flex cursor-pointer items-center justify-between rounded-xl bg-slate-50 px-4 py-3 transition hover:bg-slate-100 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                <span class="text-xs font-bold dark:text-slate-200">Lost stage <span class="block text-[10px] font-normal text-gray-400">Deals here can only be archived — no meetings or activities</span></span>
                <input :checked="form.is_lost" @change="setLost($event.target.checked)" type="checkbox" class="h-5 w-5 accent-rose-500" />
            </label>
            <label class="flex cursor-pointer items-center justify-between rounded-xl bg-slate-50 px-4 py-3 transition hover:bg-slate-100 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                <span class="text-xs font-bold dark:text-slate-200">Allow file &amp; image uploads <span class="block text-[10px] font-normal text-gray-400">Adds an “Upload files/images” button to every card in this stage — files are linked to the deal's client</span></span>
                <input v-model="form.allows_uploads" type="checkbox" class="h-5 w-5 accent-sky-600" />
            </label>
            <button :disabled="form.processing" class="w-full rounded-xl bg-indigo-600 py-3 text-xs font-black uppercase text-white transition hover:bg-indigo-700 disabled:opacity-50">Save</button>
        </form>
    </ModalShell>
</template>
