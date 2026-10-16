<script setup>
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, computed } from 'vue';
import { ShieldCheck, Sparkles, ArrowRight, Briefcase } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditAccess = computed(() => canEdit('MAN', 'access'));

const props = defineProps({
    assignedRoles: Array,
    activeRole: String,
});

const selectedRole = ref(props.activeRole || '');

const switchRole = () => {
    if (!selectedRole.value) return;
    router.post(route('man.supervisor.switch'), { role: selectedRole.value });
};

const formatRole = (role) => role.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
</script>

<template>
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-2xl mx-auto space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <ShieldCheck class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> MAN · Supervisor
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Manufacturing Supervisor</h1>
                            <span v-if="!canEditAccess" class="mt-2 inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-amber-800">View only</span>
                            <p class="text-sm text-blue-100/90">{{ assignedRoles?.length ?? 0 }} assigned role{{ (assignedRoles?.length ?? 0) !== 1 ? 's' : '' }}</p>
                        </div>
                        <span v-if="activeRole" class="rounded-full bg-emerald-400/90 px-3 py-1.5 text-xs font-black text-emerald-950 flex items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ formatRole(activeRole) }}
                        </span>
                    </div>
                </div>

                <!-- Role picker card -->
                <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 p-6 sm:p-8 text-center overflow-hidden" style="animation-delay: 100ms">
                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                    <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full" />
                    <div class="relative">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full w-fit mx-auto mb-4 animate-bounce-soft">
                            <Briefcase class="h-9 w-9 text-indigo-400" />
                        </div>
                        <h2 class="text-lg font-black tracking-tight text-gray-900 dark:text-white">Select your working role</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 mb-6">
                            You have been assigned multiple roles. Select which role you want to work as.
                        </p>

                        <select v-model="selectedRole"
                            class="w-full max-w-xs mx-auto rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 mb-4 text-sm font-medium focus:ring-2 focus:ring-indigo-500 outline-none transition">
                            <option value="">-- Choose a role --</option>
                            <option v-for="role in assignedRoles" :key="role" :value="role">
                                {{ formatRole(role) }}
                            </option>
                        </select>

                        <TransitionGroup name="card" tag="div" class="flex flex-wrap justify-center gap-2 mb-6 max-w-md mx-auto">
                            <button v-for="(role, i) in assignedRoles" :key="role" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                                @click="selectedRole = role" type="button"
                                :class="selectedRole === role ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25 scale-105' : 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/40'"
                                class="rounded-2xl px-4 py-2 text-xs font-black uppercase tracking-wide transition-all duration-200 active:scale-95 flex items-center gap-1.5">
                                <span v-if="selectedRole === role" class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ formatRole(role) }}
                            </button>
                        </TransitionGroup>

                        <button v-if="canEditAccess" @click="switchRole" :disabled="!selectedRole"
                            class="rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 text-xs font-black uppercase tracking-wide shadow-lg shadow-indigo-500/25 hover:-translate-y-0.5 transition-all disabled:opacity-50 active:scale-95 inline-flex items-center gap-2">
                            Switch to this role <ArrowRight class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
@keyframes bounceSoft { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-float-delayed { animation: float 8s ease-in-out 1.2s infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.animate-bounce-soft { animation: bounceSoft 2.4s ease-in-out infinite; }
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: translateY(16px) scale(0.98); }
</style>
