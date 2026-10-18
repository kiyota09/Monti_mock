<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const page = usePage();

const info = computed(() => page.props.password_change ?? { required: false, route: null });
const show = computed(() => !!info.value?.required && !!info.value?.route);

const form = useForm({
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);
const showConfirm = ref(false);

const submit = () => {
    form.post(route(info.value.route), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            toast.success('Your password has been updated.');
        },
        onError: () => {
            // Errors render inline; nothing else to do.
        },
    });
};

// A partially-typed confirmation is only flagged once the user has typed
// something in the "new password" field, to avoid a premature error.
const mismatch = computed(
    () =>
        form.password.length > 0 &&
        form.password_confirmation.length > 0 &&
        form.password !== form.password_confirmation,
);
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="show"
                class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-950/90 p-4 backdrop-blur-sm"
                role="dialog" aria-modal="true" aria-labelledby="force-pw-title">
                <div class="w-full max-w-md overflow-hidden rounded-2xl border border-white/10 bg-slate-900 shadow-2xl ring-1 ring-white/5">
                    <!-- Header -->
                    <div class="flex items-center gap-3 bg-gradient-to-r from-amber-500/15 to-transparent px-6 py-5">
                        <span class="flex h-11 w-11 flex-none items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 text-white shadow-lg">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 id="force-pw-title" class="text-lg font-black tracking-tight text-white">
                                Set a New Password
                            </h3>
                            <p class="mt-0.5 text-xs font-medium text-amber-300/90">
                                Required before you can continue
                            </p>
                        </div>
                    </div>

                    <!-- Body -->
                    <form @submit.prevent="submit" class="px-6 py-5">
                        <p class="text-sm leading-relaxed text-slate-300">
                            For your security, you must replace your temporary / recovery password with a
                            new password that only you know.
                        </p>

                        <!-- New password -->
                        <label for="force-new-password"
                            class="mt-5 block text-xs font-bold uppercase tracking-wider text-slate-400">
                            New Password
                        </label>
                        <div class="relative mt-1.5">
                            <input id="force-new-password" :type="showPassword ? 'text' : 'password'"
                                v-model="form.password" autofocus autocomplete="new-password"
                                placeholder="Enter new password"
                                class="block w-full rounded-xl border border-white/15 bg-white/5 px-4 py-3 pr-12 text-sm text-white placeholder-slate-500 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-400/30" />
                            <button type="button" @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-3 flex items-center text-slate-400 hover:text-white"
                                tabindex="-1" aria-label="Toggle password visibility">
                                <svg v-if="!showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3 3l18 18M10.584 10.587a2 2 0 002.828 2.83M9.363 5.365A9.466 9.466 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.77 9.77 0 01-4.293 5.122M6.228 6.228A9.77 9.77 0 002.458 12c1.274 4.057 5.064 7 9.542 7 1.02 0 1.997-.153 2.906-.435" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1.5 text-xs font-semibold text-red-400">
                            {{ form.errors.password }}
                        </p>

                        <!-- Confirm password -->
                        <label for="force-confirm-password"
                            class="mt-4 block text-xs font-bold uppercase tracking-wider text-slate-400">
                            Confirm Password
                        </label>
                        <div class="relative mt-1.5">
                            <input id="force-confirm-password" :type="showConfirm ? 'text' : 'password'"
                                v-model="form.password_confirmation" autocomplete="new-password"
                                placeholder="Re-type new password"
                                class="block w-full rounded-xl border border-white/15 bg-white/5 px-4 py-3 pr-12 text-sm text-white placeholder-slate-500 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-400/30" />
                            <button type="button" @click="showConfirm = !showConfirm"
                                class="absolute inset-y-0 right-3 flex items-center text-slate-400 hover:text-white"
                                tabindex="-1" aria-label="Toggle password visibility">
                                <svg v-if="!showConfirm" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3 3l18 18M10.584 10.587a2 2 0 002.828 2.83M9.363 5.365A9.466 9.466 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.77 9.77 0 01-4.293 5.122M6.228 6.228A9.77 9.77 0 002.458 12c1.274 4.057 5.064 7 9.542 7 1.02 0 1.997-.153 2.906-.435" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="mismatch" class="mt-1.5 text-xs font-semibold text-red-400">
                            Passwords do not match.
                        </p>

                        <p class="mt-4 text-[11px] leading-relaxed text-slate-500">
                            Use at least 8 characters. Choose something you have not used before and never
                            share it with anyone.
                        </p>

                        <button type="submit" :disabled="form.processing"
                            class="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 px-5 py-3 text-sm font-bold text-white shadow-lg transition hover:brightness-110 disabled:cursor-wait disabled:opacity-60">
                            <svg v-if="form.processing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            <span>{{ form.processing ? 'Saving...' : 'Save Password' }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
