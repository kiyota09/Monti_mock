<script setup lang="ts">
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <AuthLayout
        title="Forgot Password"
        highlight="Password"
        subtitle="Enter your account email and we'll send you a secure reset link."
        badge="Account Recovery"
        accent="blue"
    >
        <Head title="Forgot Password | MontiERP" />

        <div
            v-if="status"
            class="auth-status mb-6 rounded-lg border border-emerald-400/30 bg-emerald-500/20 p-4 text-sm font-medium text-emerald-200 backdrop-blur-sm"
        >
            {{ status }}
        </div>

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
                    <span v-if="form.processing">Sending…</span>
                    <span v-else>Email Reset Link</span>
                </PrimaryButton>
            </div>
        </form>
    </AuthLayout>
</template>
