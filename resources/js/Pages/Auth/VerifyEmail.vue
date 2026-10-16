<script setup lang="ts">
import { computed } from 'vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <AuthLayout
        title="Verify Email"
        highlight="Email"
        subtitle="Thanks for signing up! Verify your email address by clicking the link we just sent you."
        badge="Almost There"
        accent="blue"
    >
        <Head title="Email Verification | MontiERP" />

        <div
            class="auth-status mb-6 rounded-lg border border-emerald-400/30 bg-emerald-500/20 p-4 text-sm font-medium text-emerald-200 backdrop-blur-sm"
            v-if="verificationLinkSent"
        >
            A new verification link has been sent to the email address you
            provided during registration.
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="flex flex-col items-center justify-between gap-6 border-t border-white/10 pt-8 sm:flex-row">
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="text-sm font-medium text-slate-300 underline decoration-white/30 underline-offset-4 transition-colors hover:text-white"
                >
                    Log Out
                </Link>

                <PrimaryButton
                    class="auth-btn w-full px-5 py-2.5 text-xs font-bold text-white sm:w-auto"
                    :class="{ 'cursor-wait opacity-60': form.processing }"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing">Sending…</span>
                    <span v-else>Resend Verification Email</span>
                </PrimaryButton>
            </div>
        </form>
    </AuthLayout>
</template>
