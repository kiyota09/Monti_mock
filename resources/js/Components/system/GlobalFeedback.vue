<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowRight, Check, CircleHelp, Info, LoaderCircle, SearchX, ShieldAlert, WifiOff, X, Clock3 } from 'lucide-vue-next';
import { getFeedbackState, notify, setOnline, setSlow, settleFeedback } from '@/lib/feedback';
import FabricModalArtwork from '@/Components/FabricModalArtwork.vue';

const state = getFeedbackState();
const dialog = computed(() => state.current);
const inputValue = ref('');
let onlineHandler;
let offlineHandler;
let slowTimer;
let feedbackHandler;
let removeInertiaStart;
let removeInertiaFinish;

const presets = {
    empty: ['No data yet', 'There is nothing to show here.'],
    loading: ['Loading', 'Please wait while we load this page.'],
    error: ['Something went wrong', 'We could not complete that request. Please try again.'],
    offline: ['No internet connection', 'You are offline. Check your connection and try again.'],
    slow: ['Slow connection', 'This is taking longer than usual. Your request is still in progress.'],
    'no-results': ['No results found', 'Try changing or clearing your search filters.'],
    permission: ['Access denied', 'You do not have permission to view or change this information.'],
    session: ['Session expired', 'Sign in again to continue.'],
    success: ['Success', 'Your changes have been saved.'],
};
const title = computed(() => dialog.value?.title || presets[dialog.value?.kind]?.[0] || 'Notification');
const message = computed(() => dialog.value?.message || presets[dialog.value?.kind]?.[1] || '');
const kind = computed(() => dialog.value?.kind || 'info');
const dialogIcon = computed(() => ({
    success: Check,
    error: AlertTriangle,
    warning: AlertTriangle,
    permission: ShieldAlert,
    offline: WifiOff,
    session: Clock3,
    loading: LoaderCircle,
    empty: Info,
    'no-results': SearchX,
    confirm: CircleHelp,
    question: CircleHelp,
    info: Info,
}[kind.value] || Info));
const accentClasses = computed(() => ({
    success: 'from-emerald-500 to-teal-400',
    error: 'from-rose-600 to-orange-400',
    permission: 'from-rose-600 to-pink-400',
    warning: 'from-amber-500 to-orange-400',
    offline: 'from-amber-500 to-orange-400',
    session: 'from-amber-500 to-yellow-400',
    loading: 'from-blue-600 to-cyan-400',
    empty: 'from-slate-500 to-slate-400',
    'no-results': 'from-slate-500 to-indigo-400',
    confirm: 'from-blue-600 to-indigo-400',
    question: 'from-blue-600 to-indigo-400',
    info: 'from-blue-600 to-cyan-400',
}[kind.value] || 'from-blue-600 to-cyan-400'));
const iconClasses = computed(() => ({
    success: 'bg-emerald-50 text-emerald-700 ring-emerald-100 dark:bg-emerald-950/70 dark:text-emerald-300 dark:ring-emerald-900',
    error: 'bg-rose-50 text-rose-700 ring-rose-100 dark:bg-rose-950/70 dark:text-rose-300 dark:ring-rose-900',
    permission: 'bg-rose-50 text-rose-700 ring-rose-100 dark:bg-rose-950/70 dark:text-rose-300 dark:ring-rose-900',
    warning: 'bg-amber-50 text-amber-700 ring-amber-100 dark:bg-amber-950/70 dark:text-amber-300 dark:ring-amber-900',
    offline: 'bg-amber-50 text-amber-700 ring-amber-100 dark:bg-amber-950/70 dark:text-amber-300 dark:ring-amber-900',
    session: 'bg-amber-50 text-amber-700 ring-amber-100 dark:bg-amber-950/70 dark:text-amber-300 dark:ring-amber-900',
    loading: 'bg-blue-50 text-blue-700 ring-blue-100 dark:bg-blue-950/70 dark:text-blue-300 dark:ring-blue-900',
    empty: 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700',
    'no-results': 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700',
    confirm: 'bg-blue-50 text-blue-700 ring-blue-100 dark:bg-blue-950/70 dark:text-blue-300 dark:ring-blue-900',
    question: 'bg-blue-50 text-blue-700 ring-blue-100 dark:bg-blue-950/70 dark:text-blue-300 dark:ring-blue-900',
    info: 'bg-blue-50 text-blue-700 ring-blue-100 dark:bg-blue-950/70 dark:text-blue-300 dark:ring-blue-900',
}[kind.value] || 'bg-blue-50 text-blue-700 ring-blue-100 dark:bg-blue-950/70 dark:text-blue-300 dark:ring-blue-900'));
const actionClasses = computed(() => ({
    success: 'bg-emerald-600 hover:bg-emerald-700 focus-visible:ring-emerald-300',
    error: 'bg-rose-600 hover:bg-rose-700 focus-visible:ring-rose-300',
    permission: 'bg-rose-600 hover:bg-rose-700 focus-visible:ring-rose-300',
    warning: 'bg-amber-600 hover:bg-amber-700 focus-visible:ring-amber-300',
    offline: 'bg-amber-600 hover:bg-amber-700 focus-visible:ring-amber-300',
    session: 'bg-amber-600 hover:bg-amber-700 focus-visible:ring-amber-300',
}[kind.value] || 'bg-blue-600 hover:bg-blue-700 focus-visible:ring-blue-300'));

function close(confirmed) {
    const value = inputValue.value;
    const action = dialog.value?.action;
    inputValue.value = '';
    settleFeedback({ isConfirmed: confirmed, isDismissed: !confirmed, value: confirmed && dialog.value?.input ? value : undefined });
    if (confirmed && typeof action === 'function') action();
}
function keyHandler(event) { if (event.key === 'Escape' && dialog.value?.kind !== 'session') close(false); }
function handleStart() { clearTimeout(slowTimer); slowTimer = setTimeout(() => setSlow(true), 10000); }
function handleFinish() { clearTimeout(slowTimer); setSlow(false); }

watch(dialog, (next) => { inputValue.value = next?.input ? (next.input.value || '') : ''; });
onMounted(() => {
    onlineHandler = () => setOnline(true);
    offlineHandler = () => setOnline(false);
    if (typeof navigator !== 'undefined' && !navigator.onLine) setOnline(false);
    window.addEventListener('online', onlineHandler);
    window.addEventListener('offline', offlineHandler);
    window.addEventListener('keydown', keyHandler);
    feedbackHandler = (event) => notify(event.detail || {});
    window.addEventListener('erp:feedback', feedbackHandler);
    removeInertiaStart = router.on('start', handleStart);
    removeInertiaFinish = router.on('finish', handleFinish);
});
onUnmounted(() => {
    clearTimeout(slowTimer);
    window.removeEventListener('online', onlineHandler);
    window.removeEventListener('offline', offlineHandler);
    window.removeEventListener('keydown', keyHandler);
    window.removeEventListener('erp:feedback', feedbackHandler);
    removeInertiaStart?.();
    removeInertiaFinish?.();
});
</script>

<template>
    <Teleport to="body">
        <Transition name="slow-connection">
            <div v-if="state.slow && state.online" class="pointer-events-none fixed inset-x-0 top-0 z-[10001] flex justify-center px-3 pt-3" role="status" aria-live="polite">
                <div class="flex items-center gap-2 rounded-full border border-amber-300/80 bg-amber-50/95 px-4 py-2 text-xs font-bold text-amber-800 shadow-lg backdrop-blur dark:border-amber-700 dark:bg-amber-950/95 dark:text-amber-200">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-500 opacity-70"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-amber-600"></span>
                    </span>
                    Slow connection
                </div>
            </div>
        </Transition>
        <Transition name="slow-connection">
            <div v-if="!state.online" class="pointer-events-none fixed inset-x-0 top-0 z-[10002] flex justify-center px-3 pt-3" role="status" aria-live="assertive">
                <div class="flex items-center gap-3 rounded-full border border-rose-300/80 bg-rose-50/95 px-4 py-2 text-xs font-bold text-rose-800 shadow-lg backdrop-blur dark:border-rose-700 dark:bg-rose-950/95 dark:text-rose-200">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-rose-500 opacity-70"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-rose-600"></span>
                    </span>
                    No internet connection
                </div>
            </div>
        </Transition>
        <Transition name="feedback">
            <div v-if="dialog && dialog.kind !== 'slow'" class="fixed inset-0 z-[10000] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm" role="presentation" @click.self="dialog.kind !== 'session' && close(false)">
                <section class="feedback-card relative w-full max-w-md overflow-hidden rounded-[1.75rem] border border-white/70 bg-white text-slate-900 shadow-[0_28px_90px_-24px_rgba(15,23,42,.55)] dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" role="alertdialog" aria-modal="true" :aria-labelledby="`feedback-title-${dialog.id}`">
                    <FabricModalArtwork :kind="kind === 'success' ? 'success' : 'info'" :label="kind === 'success' ? 'Fabric swatches with a success checkmark' : 'Fabric swatches'" />
                    <div class="p-6 text-center sm:p-7">
                        <p class="mb-1 text-[10px] font-black uppercase tracking-[0.18em] text-sky-700">{{ kind === 'confirm' || kind === 'question' ? 'Confirmation' : 'Notification' }}</p>
                        <div class="relative">
                            <h2 :id="`feedback-title-${dialog.id}`" class="pr-10 text-lg font-extrabold tracking-tight text-slate-900 dark:text-white">{{ title }}</h2>
                            <button v-if="dialog.kind !== 'session'" type="button" aria-label="Close notification" class="absolute -right-3 -top-3 flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 dark:hover:bg-zinc-800 dark:hover:text-white" @click="close(false)">
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                        <p v-if="message" class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600 dark:text-zinc-300">{{ message }}</p>
                        <label v-if="dialog.input" class="mt-5 block text-sm font-semibold text-slate-700 dark:text-zinc-200">
                            <span v-if="dialog.input.label" class="mb-2 block">{{ dialog.input.label }}</span>
                            <textarea v-if="dialog.input.type === 'textarea'" v-model="inputValue" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm font-normal text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-400 focus:bg-white focus:ring-4 focus:ring-blue-500/10 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:focus:bg-zinc-800" :placeholder="dialog.input.placeholder" rows="3" />
                            <input v-else v-model="inputValue" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm font-normal text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-400 focus:bg-white focus:ring-4 focus:ring-blue-500/10 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:focus:bg-zinc-800" :placeholder="dialog.input.placeholder" />
                        </label>
                        <div class="mt-6 flex flex-col-reverse gap-2 border-t border-sky-100 pt-5 dark:border-zinc-800 sm:flex-row sm:justify-center sm:gap-3">
                            <button v-if="dialog.cancelLabel" type="button" class="rounded-xl border border-sky-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-sky-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-sky-200 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800" @click="close(false)">{{ dialog.cancelLabel }}</button>
                            <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-sky-700 to-indigo-600 px-6 py-2.5 text-sm font-bold text-white shadow-lg shadow-sky-900/15 transition duration-150 hover:-translate-y-0.5 hover:shadow-xl focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-sky-300 active:translate-y-0 active:scale-[.98]" autofocus @click="close(true)">
                                {{ dialog.actionLabel || 'OK' }}<ArrowRight class="h-4 w-4" aria-hidden="true" />
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.feedback-enter-active, .feedback-leave-active { transition: opacity .16s ease; }
.feedback-enter-from, .feedback-leave-to { opacity: 0; }
.feedback-enter-active .feedback-card, .feedback-leave-active .feedback-card { transition: transform .22s cubic-bezier(.2,.8,.2,1), opacity .22s ease; }
.feedback-enter-from .feedback-card, .feedback-leave-to .feedback-card { opacity: 0; transform: translateY(12px) scale(.97); }
.slow-connection-enter-active, .slow-connection-leave-active { transition: opacity .2s ease, transform .2s ease; }
.slow-connection-enter-from, .slow-connection-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
