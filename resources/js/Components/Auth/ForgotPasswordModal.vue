<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    routeName: { type: String, required: true },
    portalLabel: { type: String, default: 'Portal' },
    accent: { type: String, default: 'sky' },
});

const emit = defineEmits(['update:modelValue']);

const phase = ref('form'); // form | sent | not_found
const email = ref('');
const processing = ref(false);
const error = ref('');

const accentClasses = {
    blue: 'from-blue-600 to-blue-500',
    sky: 'from-sky-600 to-sky-500',
    emerald: 'from-emerald-600 to-emerald-500',
    red: 'from-red-600 to-red-500',
};

const iconGradient = () => accentClasses[props.accent] ?? accentClasses.sky;

watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            phase.value = 'form';
            error.value = '';
        }
    },
);

const close = () => {
    emit('update:modelValue', false);
    setTimeout(() => {
        phase.value = 'form';
        email.value = '';
        error.value = '';
        processing.value = false;
    }, 200);
};

const emailIsValid = (value) => /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(value);

const submit = async () => {
    error.value = '';

    if (!email.value.trim()) {
        error.value = 'Please enter your email address.';
        return;
    }
    if (!emailIsValid(email.value.trim())) {
        error.value = 'Please enter a valid email address.';
        return;
    }

    processing.value = true;

    try {
        const { data } = await axios.post(route(props.routeName), {
            email: email.value.trim(),
        });

        if (data?.status === 'sent') {
            phase.value = 'sent';
            toast.success('Recovery email sent. Please check your inbox.');
        } else {
            phase.value = 'not_found';
        }
    } catch (e) {
        const status = e?.response?.status;
        const data = e?.response?.data;

        if (status === 404 || data?.status === 'not_found') {
            phase.value = 'not_found';
        } else if (status === 422) {
            const first = Object.values(data?.errors ?? {}).flat()?.[0];
            error.value = first || 'Please enter a valid email address.';
        } else if (status === 429) {
            error.value = 'Too many attempts. Please wait a minute and try again.';
        } else {
            toast.error('Something went wrong. Please try again later.');
        }
    } finally {
        processing.value = false;
    }
};

const onKeydown = (e) => {
    if (e.key === 'Escape' && props.modelValue) {
        close();
    }
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="modelValue"
                class="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"
                @click.self="close">
                <div
                    class="w-full max-w-md overflow-hidden rounded-2xl border border-white/10 bg-slate-900/95 shadow-2xl ring-1 ring-white/5">
                    <!-- Header -->
                    <div class="flex items-start gap-3 border-b border-white/10 px-6 py-5">
                        <span
                            class="flex h-11 w-11 flex-none items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-lg"
                            :class="iconGradient()">
                            <svg v-if="phase === 'sent'" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <svg v-else-if="phase === 'not_found'" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                            </svg>
                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black tracking-tight text-white">
                                <span v-if="phase === 'sent'">Recovery Email Sent</span>
                                <span v-else-if="phase === 'not_found'">Email Not Found</span>
                                <span v-else>Forgot Password</span>
                            </h3>
                            <p class="mt-0.5 text-xs font-medium text-slate-400">{{ portalLabel }}</p>
                        </div>
                        <button type="button" @click="close"
                            class="flex h-8 w-8 flex-none items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-white/10 hover:text-white"
                            aria-label="Close">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Body: form -->
                    <div v-if="phase === 'form'" class="px-6 py-5">
                        <p class="text-sm leading-relaxed text-slate-300">
                            Enter the email address linked to your {{ portalLabel }} account. We will send a
                            new password to that email.
                        </p>

                        <label for="forgot-password-email"
                            class="mt-4 block text-xs font-bold uppercase tracking-wider text-slate-400">
                            Email Address
                        </label>
                        <input id="forgot-password-email" type="email" v-model="email" autofocus
                            autocomplete="email" placeholder="you@example.com"
                            class="mt-1.5 block w-full rounded-xl border border-white/15 bg-white/5 px-4 py-3 text-sm text-white placeholder-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-400/30"
                            @keydown.enter.prevent="submit" />
                        <p v-if="error" class="mt-2 text-xs font-semibold text-red-400">{{ error }}</p>
                    </div>

                    <!-- Body: sent -->
                    <div v-else-if="phase === 'sent'" class="px-6 py-5">
                        <p class="text-sm leading-relaxed text-slate-300">
                            We sent the recovery password to
                            <span class="font-semibold text-white">{{ email }}</span>. Please check your inbox
                            (and spam folder).
                        </p>
                        <div class="mt-4 rounded-xl border border-amber-400/30 bg-amber-500/10 px-4 py-3 text-xs font-semibold text-amber-200">
                            Please change your password immediately after logging in.
                        </div>
                    </div>

                    <!-- Body: not found -->
                    <div v-else class="px-6 py-5">
                        <p class="text-sm leading-relaxed text-slate-300">
                            Sorry, the email
                            <span class="font-semibold text-white">{{ email }}</span>
                            you entered does not exist in our records.
                        </p>
                        <p class="mt-3 text-xs text-slate-400">
                            Please double-check the spelling, or contact Monti Textile support for help.
                        </p>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-end gap-3 border-t border-white/10 bg-white/[0.02] px-6 py-4">
                        <template v-if="phase === 'form'">
                            <button type="button" @click="close"
                                class="rounded-xl border border-white/15 px-4 py-2.5 text-xs font-bold text-slate-300 transition-colors hover:bg-white/10 hover:text-white">
                                Cancel
                            </button>
                            <button type="button" @click="submit"
                                :disabled="processing"
                                class="rounded-xl bg-gradient-to-br px-5 py-2.5 text-xs font-bold text-white shadow-lg transition hover:brightness-110 disabled:opacity-60"
                                :class="[iconGradient(), { 'opacity-60 cursor-wait': processing }]">
                                <span v-if="processing">Sending...</span>
                                <span v-else>Submit</span>
                            </button>
                        </template>
                        <template v-else-if="phase === 'not_found'">
                            <button type="button" @click="phase = 'form'"
                                class="rounded-xl border border-white/15 px-4 py-2.5 text-xs font-bold text-slate-300 transition-colors hover:bg-white/10 hover:text-white">
                                Try Again
                            </button>
                            <button type="button" @click="close"
                                class="rounded-xl bg-gradient-to-br from-sky-600 to-sky-500 px-5 py-2.5 text-xs font-bold text-white shadow-lg transition hover:brightness-110">
                                Close
                            </button>
                        </template>
                        <template v-else>
                            <button type="button" @click="close"
                                class="rounded-xl bg-gradient-to-br from-sky-600 to-sky-500 px-5 py-2.5 text-xs font-bold text-white shadow-lg transition hover:brightness-110">
                                Done
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
