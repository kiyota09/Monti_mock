<script setup lang="ts">
import { ref } from 'vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    email: string;
    token: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);
const showConfirmPassword = ref(false);

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <AuthLayout
        title="Reset Password"
        highlight="Password"
        subtitle="Choose a strong new password for your account."
        badge="Account Recovery"
        accent="blue"
    >
        <Head title="Reset Password | MontiERP" />

        <form @submit.prevent="submit" class="space-y-6">
            <div>
                <InputLabel for="email" value="Email Address" class="text-white/90" />

                <TextInput
                    id="email"
                    type="email"
                    class="auth-input mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    spellcheck="false"
                    placeholder="you@monticorp.com"
                />

                <InputError class="mt-1 text-red-300" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="New Password" class="text-white/90" />
                <div class="relative">
                    <TextInput
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        class="auth-input mt-1 block w-full pr-12"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        class="absolute inset-y-0 right-4 flex items-center text-slate-300 transition-colors hover:text-white"
                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                    >
                        <svg v-if="!showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                            />
                        </svg>
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"
                            />
                        </svg>
                    </button>
                </div>
                <InputError class="mt-1 text-red-300" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel for="password_confirmation" value="Confirm Password" class="text-white/90" />
                <div class="relative">
                    <TextInput
                        id="password_confirmation"
                        :type="showConfirmPassword ? 'text' : 'password'"
                        class="auth-input mt-1 block w-full pr-12"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                    <button
                        type="button"
                        @click="showConfirmPassword = !showConfirmPassword"
                        class="absolute inset-y-0 right-4 flex items-center text-slate-300 transition-colors hover:text-white"
                        :aria-label="showConfirmPassword ? 'Hide password' : 'Show password'"
                    >
                        <svg v-if="!showConfirmPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                            />
                        </svg>
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"
                            />
                        </svg>
                    </button>
                </div>
                <InputError class="mt-1 text-red-300" :message="form.errors.password_confirmation" />
            </div>

            <div class="flex flex-col items-center justify-between gap-6 border-t border-white/10 pt-8 sm:flex-row">
                <Link
                    :href="route('login')"
                    class="group flex items-center text-sm font-medium text-slate-300 transition-colors hover:text-white"
                >
                    <svg
                        class="mr-2 h-4 w-4 transition-transform group-hover:-translate-x-1"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path d="M15 19l-7-7 7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Back to Sign in
                </Link>

                <PrimaryButton
                    class="auth-btn w-full px-5 py-2.5 text-xs font-bold text-white sm:w-auto"
                    :class="{ 'cursor-wait opacity-60': form.processing }"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing">Resetting…</span>
                    <span v-else>Reset Password</span>
                </PrimaryButton>
            </div>
        </form>
    </AuthLayout>
</template>
