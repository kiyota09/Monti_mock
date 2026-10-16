<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Hourglass, ShieldCheck, LogOut, RefreshCw } from 'lucide-vue-next'

const props = defineProps({
    user: Object,
    // Optional override for scoped-access states (e.g. a MAN plant staffer
    // holding Dashboard but not the Production grant their role workspace
    // requires). Defaults preserve the original "no pages yet" copy.
    title: { type: String, default: '' },
    message: { type: String, default: '' },
    hint: { type: String, default: '' },
})

const refresh = () => router.reload()
</script>

<template>
    <Head title="Awaiting Access" />

    <AuthenticatedLayout>
        <div class="flex flex-col items-center justify-center py-10 text-center">
            <!-- Icon -->
            <div class="w-20 h-20 rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 flex items-center justify-center shadow-xl shadow-indigo-500/30 mb-6">
                <Hourglass class="w-10 h-10 text-white animate-pulse" />
            </div>

            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-indigo-600 dark:text-indigo-400 mb-2">
                Monti Textile &bull; {{ user?.role }} &bull; {{ user?.position }}
            </p>
            <h1 class="text-2xl md:text-3xl font-black text-gray-900 dark:text-white tracking-tight mb-3">
                {{ title || (`Waiting for access, ${user?.name?.split(' ')[0] ?? 'there'}.`) }}
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 max-w-md leading-relaxed mb-2">
                {{ message || 'Your account is active, but an administrator has not granted you access to any pages yet. All of your page permissions are currently disabled (the default).' }}
            </p>
            <p v-if="hint" class="text-sm text-amber-600 dark:text-amber-400 max-w-md leading-relaxed mb-2 font-bold">
                {{ hint }}
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400 max-w-md leading-relaxed mb-8">
                Please wait for the admins to set up your access. Once they enable at least one
                page, your sidebar menu will appear here automatically.
            </p>

            <!-- Status card -->
            <div class="w-full max-w-md bg-indigo-50/60 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-900/40 rounded-2xl p-4 mb-6 flex items-start gap-3 text-left">
                <ShieldCheck class="w-5 h-5 text-indigo-600 dark:text-indigo-400 flex-shrink-0 mt-0.5" />
                <div class="text-xs leading-relaxed text-gray-600 dark:text-gray-300">
                    <p class="font-bold text-gray-900 dark:text-white mb-1">What happens next?</p>
                    <ol class="list-decimal list-inside space-y-1">
                        <li>An IT / module admin opens Access Control for your account.</li>
                        <li>They switch the pages you need from <span class="font-semibold">disabled</span> to <span class="font-semibold">view</span> or <span class="font-semibold">edit</span>.</li>
                        <li>You press <span class="font-semibold">Check again</span> below — your menu shows up.</li>
                    </ol>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 w-full max-w-md">
                <button
                    @click="refresh"
                    class="flex-1 inline-flex items-center justify-center gap-2 py-3 px-4 text-sm font-bold rounded-xl bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 text-white hover:opacity-95 transition shadow-lg shadow-indigo-500/25">
                    <RefreshCw class="w-4 h-4" />
                    Check again
                </button>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="flex-1 inline-flex items-center justify-center gap-2 py-3 px-4 text-sm font-bold rounded-xl border border-gray-200 dark:border-zinc-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-zinc-800 transition">
                    <LogOut class="w-4 h-4" />
                    Sign out
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
