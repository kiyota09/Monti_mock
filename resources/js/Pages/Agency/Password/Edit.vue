<script setup lang="ts">
// Forced password change (first login / after reset)
import { Head, useForm } from '@inertiajs/vue3';
import AgencyLayout from '@/Layouts/AgencyLayout.vue';

const form = useForm({ current_password: '', password: '', password_confirmation: '' });

function submit() {
    form.put(route('agency.password.update'), {
        onFinish: () => form.reset('password', 'password_confirmation', 'current_password'),
    });
}

const inputCls = 'w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200';
const labelCls = 'block mb-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase';
</script>

<template>
    <Head title="Change Password - Monti ERP" />
    <AgencyLayout>
        <h1 class="text-2xl font-black text-slate-900 dark:text-white mb-1">Change password</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">For your security, please set a new password before continuing.</p>

        <form @submit.prevent="submit" class="max-w-md space-y-4 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-5">
            <div>
                <label :class="labelCls">Current (temporary) password</label>
                <input v-model="form.current_password" type="password" :class="inputCls" autocomplete="current-password" />
                <p v-if="form.errors.current_password" class="mt-1 text-xs text-rose-600">{{ form.errors.current_password }}</p>
            </div>
            <div>
                <label :class="labelCls">New password</label>
                <input v-model="form.password" type="password" :class="inputCls" autocomplete="new-password" />
                <p v-if="form.errors.password" class="mt-1 text-xs text-rose-600">{{ form.errors.password }}</p>
            </div>
            <div>
                <label :class="labelCls">Confirm new password</label>
                <input v-model="form.password_confirmation" type="password" :class="inputCls" autocomplete="new-password" />
            </div>
            <button type="submit" :disabled="form.processing" class="w-full px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold disabled:opacity-60">
                {{ form.processing ? 'Saving...' : 'Update password' }}
            </button>
        </form>
    </AgencyLayout>
</template>
