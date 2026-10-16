<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { KeyRound } from 'lucide-vue-next';

const passwordInput = ref<HTMLInputElement | null>(null);
const currentPasswordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
                <KeyRound class="h-4 w-4 text-indigo-500" /> Update password
            </h2>

            <p class="mt-1 text-[11px] text-slate-400">
                Ensure your account is using a long, random password to stay
                secure.
            </p>
        </header>

        <form @submit.prevent="updatePassword" class="mt-4 space-y-4">
            <div>
                <label for="current_password" class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">Current password</label>

                <input
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                    autocomplete="current-password"
                />

                <InputError
                    :message="form.errors.current_password"
                    class="mt-1"
                />
            </div>

            <div>
                <label for="password" class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">New password</label>

                <input
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                    autocomplete="new-password"
                />

                <InputError :message="form.errors.password" class="mt-1" />
            </div>

            <div>
                <label
                    for="password_confirmation"
                    class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400"
                >Confirm password</label>

                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                    autocomplete="new-password"
                />

                <InputError
                    :message="form.errors.password_confirmation"
                    class="mt-1"
                />
            </div>

            <div class="flex items-center gap-3 border-t border-gray-100 pt-4 dark:border-zinc-800">
                <button type="submit" :disabled="form.processing"
                    class="flex-1 rounded-xl bg-indigo-600 py-2.5 text-sm font-black text-white shadow hover:bg-indigo-700 disabled:opacity-50 active:scale-95">
                    {{ form.processing ? 'Saving…' : 'Save changes' }}
                </button>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="shrink-0 text-xs font-black uppercase text-emerald-600 dark:text-emerald-400"
                    >
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
