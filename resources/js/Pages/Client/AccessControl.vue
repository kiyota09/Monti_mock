<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, watch, reactive, computed } from 'vue';
import { ShieldCheck, Lock, KeyRound, Save, Loader2, Star, Check } from 'lucide-vue-next';

const props = defineProps({
    representatives: { type: Array, default: () => [] },
    pages: { type: Array, default: () => [] },
});

const page = usePage();
const toast = ref({ show: false, type: 'success', message: '' });
const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 4000);
};
watch(() => page.props.flash?.message, (msg) => { if (msg) showToast('success', msg); });

// Every non-primary contact of the account is controllable here — whether or
// not CRM has granted them a login yet. The primary always holds full access
// and is therefore shown locked.
const manageable = computed(() => props.representatives.filter((r) => !r.is_primary));
const locked = computed(() => props.representatives.filter((r) => r.is_primary));

const accountState = (r) => {
    if (r.status === 'pending') return { label: 'Awaiting approval', cls: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' };
    if (r.status === 'rejected') return { label: 'Rejected', cls: 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300' };
    if (r.has_account) return { label: 'Has login', cls: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' };
    return { label: 'No login yet', cls: 'bg-gray-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400' };
};

const state = reactive({});
props.representatives.forEach((r) => { state[r.id] = [...(r.access_pages || [])]; });

const toggle = (id, key) => {
    const list = state[id];
    const i = list.indexOf(key);
    if (i === -1) list.push(key); else list.splice(i, 1);
};

const allOn = (id) => state[id].length === props.pages.length;
const toggleAll = (id) => {
    state[id] = allOn(id) ? [] : props.pages.map((p) => p.key);
};

const saving = ref(false);
const save = () => {
    saving.value = true;
    const representatives = manageable.value.map((r) => ({ id: r.id, pages: state[r.id] }));
    router.post(route('client.access-control.update'), { representatives }, {
        preserveScroll: true,
        onSuccess: () => showToast('success', 'Access control saved.'),
        onError: () => showToast('error', 'Could not save changes.'),
        onFinish: () => { saving.value = false; },
    });
};

const lockLabel = () => 'Primary — always full access';
</script>

<template>
    <Head title="Access Control" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-5xl mx-auto space-y-6 pb-16">

                <!-- Hero -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-800 via-indigo-800 to-violet-900 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <ShieldCheck class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-indigo-100">
                                <KeyRound class="h-3.5 w-3.5" /> Account Management · Access Control
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Page Access Control</h1>
                            <p class="text-sm text-indigo-100/90">Choose exactly which pages each representative may open in the partner portal.</p>
                        </div>
                        <button @click="save" :disabled="saving || !manageable.length"
                            class="flex items-center gap-2 rounded-2xl bg-white px-5 py-3 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:scale-105 active:scale-95 transition disabled:opacity-50">
                            <Loader2 v-if="saving" class="h-4 w-4 animate-spin" /> <Save v-else class="h-4 w-4" /> Save changes
                        </button>
                    </div>
                </div>

                <!-- Locked / informational rows -->
                <div v-if="locked.length" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div v-for="rep in locked" :key="rep.id" class="animate-fade-up rounded-2xl border border-indigo-200 dark:border-indigo-800 bg-indigo-50/50 dark:bg-indigo-900/10 p-4 flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
                            <Lock class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-black text-sm flex items-center gap-1.5">{{ rep.name }} <Star class="h-3.5 w-3.5 text-amber-500" /></p>
                            <p class="text-[11px] font-bold text-indigo-500 flex items-center gap-1">{{ lockLabel() }}</p>
                        </div>
                    </div>
                </div>

                <!-- Manageable representatives -->
                <div v-if="manageable.length" class="space-y-4">
                    <div v-for="rep in manageable" :key="rep.id" class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                        <div class="p-5 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-3 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/10">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 via-blue-600 to-violet-700 text-sm font-black text-white uppercase">{{ (rep.name || '?').charAt(0) }}</div>
                            <div class="min-w-0 flex-1">
                                <p class="font-black text-sm">{{ rep.name }}</p>
                                <p class="text-[11px] font-bold text-gray-400">{{ rep.email || 'No email' }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-black uppercase" :class="accountState(rep).cls">{{ accountState(rep).label }}</span>
                            <button @click="toggleAll(rep.id)" class="rounded-xl px-3 py-1.5 text-[10px] font-black uppercase ring-1 transition"
                                :class="allOn(rep.id) ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-indigo-50 text-indigo-600 ring-indigo-200 dark:bg-indigo-900/20 dark:text-indigo-300 dark:ring-indigo-800'">
                                {{ allOn(rep.id) ? 'Clear all' : 'Select all' }}
                            </button>
                        </div>
                        <div class="p-5 grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                            <label v-for="p in pages" :key="p.key"
                                class="flex cursor-pointer items-center gap-2.5 rounded-xl px-3 py-3 text-xs font-bold transition-all active:scale-95 ring-1"
                                :class="state[rep.id].includes(p.key) ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white ring-transparent shadow-md shadow-indigo-500/25' : 'bg-gray-50 dark:bg-zinc-800 text-gray-600 dark:text-gray-300 ring-gray-100 dark:ring-zinc-700 hover:bg-gray-100 dark:hover:bg-zinc-700'">
                                <input type="checkbox" class="hidden" :checked="state[rep.id].includes(p.key)" @change="toggle(rep.id, p.key)" />
                                <span class="flex h-4 w-4 items-center justify-center rounded border" :class="state[rep.id].includes(p.key) ? 'bg-white border-white' : 'border-gray-300 dark:border-zinc-600'">
                                    <Check v-if="state[rep.id].includes(p.key)" class="h-3 w-3 text-indigo-600" />
                                </span>
                                {{ p.label }}
                            </label>
                        </div>
                    </div>
                </div>

                <div v-else class="animate-fade-up rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 bg-white/60 dark:bg-zinc-900/60 p-12 text-center">
                    <ShieldCheck class="mx-auto h-10 w-10 text-gray-300" />
                    <p class="mt-3 text-sm font-bold text-gray-400">No other contacts on this account yet.</p>
                    <p class="text-xs text-gray-400">Representatives you add (or that register from this account) will appear here so you can set their page access.</p>
                </div>
            </div>

            <Transition name="toast">
                <div v-if="toast.show" class="fixed bottom-8 right-8 z-50 px-6 py-3 rounded-2xl shadow-xl text-white font-bold text-sm ring-1 ring-white/20"
                    :class="toast.type === 'success' ? 'bg-gradient-to-r from-emerald-600 to-teal-600' : 'bg-gradient-to-r from-rose-600 to-red-600'">
                    {{ toast.message }}
                </div>
            </Transition>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-float-delayed { animation: float 8s ease-in-out 1.2s infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }
</style>
