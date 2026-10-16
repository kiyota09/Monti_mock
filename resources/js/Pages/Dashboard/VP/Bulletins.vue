<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Megaphone, Sparkles, PlusCircle, Trash2 } from 'lucide-vue-next';

defineProps({ bulletins: Array });

const showForm = ref(false);
const form = useForm({ title: '', body: '', audience: 'company', priority: 'normal', expires_at: '' });
const submit = () => form.post(route('vp.bulletins.store'), { preserveScroll: true, onSuccess: () => { showForm.value = false; form.reset(); } });
const removeBulletin = (b) => { if (confirm(`Remove bulletin "${b.title}"?`)) router.delete(route('vp.bulletins.destroy', b.id), { preserveScroll: true }); };
</script>

<template>
    <Head title="Operational Bulletins" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-5xl mx-auto space-y-6 pb-16">
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><Megaphone class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> VP · Floor Broadcasts</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Operational Bulletins</h1>
                            <p class="text-sm text-blue-100/90">Execution notices to departments — supervisors see these on their overview</p>
                        </div>
                        <button @click="showForm = !showForm" class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:scale-105 active:scale-95 transition flex items-center gap-1.5"><PlusCircle class="h-4 w-4" /> New Bulletin</button>
                    </div>
                </div>

                <Transition name="modal">
                    <div v-if="showForm" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 py-4"><h2 class="text-lg font-black text-white">Publish Bulletin</h2></div>
                        <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-6">
                            <div class="sm:col-span-2"><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Title *</label>
                                <input v-model="form.title" required class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div class="sm:col-span-2"><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Message *</label>
                                <textarea v-model="form.body" rows="3" required class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Audience</label>
                                <select v-model="form.audience" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="company">Whole plant</option>
                                    <option v-for="d in ['knitting','dyeing','finishing','maintenance','boiler']" :key="d" :value="d">{{ d }}</option>
                                </select></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Priority</label>
                                <select v-model="form.priority" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option>
                                </select></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Expires (optional)</label>
                                <input v-model="form.expires_at" type="date" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div class="flex items-end gap-2">
                                <button type="submit" :disabled="form.processing" class="rounded-2xl bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase text-white shadow-lg hover:bg-indigo-700 active:scale-95 transition disabled:opacity-50">Publish</button>
                                <button type="button" @click="showForm = false" class="rounded-2xl bg-gray-100 dark:bg-zinc-800 px-4 py-2.5 text-xs font-black uppercase text-gray-600 dark:text-gray-300 transition active:scale-95">Cancel</button>
                            </div>
                        </form>
                    </div>
                </Transition>

                <div class="space-y-3">
                    <div v-for="b in bulletins" :key="b.id" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-5">
                        <div class="flex flex-wrap items-start gap-2">
                            <div class="min-w-0 flex-1">
                                <h3 class="font-black text-gray-900 dark:text-white">{{ b.title }}</h3>
                                <p class="text-sm text-gray-500 mt-1 whitespace-pre-line">{{ b.body }}</p>
                                <p class="text-[11px] text-gray-400 mt-1.5">To: {{ b.audience }} · {{ b.priority }}{{ b.expires_at ? ` · expires ${new Date(b.expires_at).toLocaleDateString()}` : '' }} · by {{ b.creator?.name ?? '—' }}</p>
                            </div>
                            <button @click="removeBulletin(b)" class="text-gray-300 hover:text-rose-500 transition"><Trash2 class="h-4 w-4" /></button>
                        </div>
                    </div>
                    <div v-if="!bulletins?.length" class="text-center text-sm text-gray-400 py-8">No bulletins published yet.</div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
