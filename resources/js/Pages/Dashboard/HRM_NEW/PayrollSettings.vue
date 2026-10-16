<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    settings: { type: Object, default: () => ({}) },
    history: { type: Array, default: () => [] },
    is_hr: { type: Boolean, default: false },
    permissions: { type: Object, default: () => ({}) },
});

const canEdit = computed(() => props.is_hr && props.permissions?.payroll === 'edit');
const tab = ref('settings');

const groupTitles = { earnings: 'Earning rates', shifts: 'Shift times', calendar: 'Pay calendar', attendance: 'Attendance view', payroll: 'Payroll controls', general: 'General' };

// Seed the form from server raw values (strings keep time/boolean round-trip exact).
const seed = {};
Object.values(props.settings || {}).flat().forEach((s) => { seed[s.key] = s.raw ?? String(s.value ?? ''); });
const form = useForm({ values: { ...seed } });

const dirtyCount = computed(() => Object.keys(form.values).filter((k) => String(form.values[k] ?? '') !== String(seed[k] ?? '')).length);

const save = () => {
    if (!dirtyCount.value) return;
    form.put(route('hrm.payroll.settings.update'), { preserveScroll: true });
};

const boolLabel = (v) => String(v) === '1' ? 'Yes' : 'No';
</script>

<template>
    <Head title="Payroll settings" />
    <AuthenticatedLayout>
        <div class="flex min-h-screen bg-gradient-to-br from-slate-100 via-slate-50 to-blue-50">
            <div class="flex-1 p-8 text-slate-900 font-sans min-w-0 relative">
                <div class="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,transparent,black,transparent)] pointer-events-none"></div>
                <header class="mb-8 relative">
                    <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3">
                        <Link :href="route('hrm.payroll')" class="hover:text-slate-700 transition-colors">Payroll</Link>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        <span class="text-slate-800 font-semibold">Settings</span>
                    </nav>
                    <h1 class="text-4xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">Payroll settings</h1>
                    <p class="text-sm text-slate-500 mt-1.5">Rates, divisors, shift times and calendar used by payroll math, attendance and payslips. Changes apply to new calculations immediately.</p>
                </header>

                <div class="flex gap-2 mb-4 relative">
                    <button type="button" @click="tab = 'settings'" class="px-4 py-2 rounded-xl text-xs font-bold border transition"
                        :class="tab === 'settings' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'">Settings</button>
                    <button type="button" @click="tab = 'history'" class="px-4 py-2 rounded-xl text-xs font-bold border transition"
                        :class="tab === 'history' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'">History ({{ history.length }})</button>
                    <div class="ml-auto flex items-center gap-2">
                        <span v-if="dirtyCount" class="text-xs font-bold text-amber-600">{{ dirtyCount }} unsaved change{{ dirtyCount === 1 ? '' : 's' }}</span>
                        <button v-if="canEdit" type="button" @click="save" :disabled="!dirtyCount || form.processing"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold transition disabled:opacity-50">{{ form.processing ? 'Saving…' : 'Save changes' }}</button>
                    </div>
                </div>

                <div v-if="!canEdit" class="mb-4 px-4 py-3 rounded-2xl bg-amber-50 border border-amber-200 text-amber-700 text-xs font-medium relative">
                    Read-only. Only HR can edit payroll settings.
                </div>

                <div v-if="tab === 'settings'" class="space-y-6 relative">
                    <div v-for="(items, group) in settings" :key="group" class="bg-white rounded-2xl border border-slate-200/60 shadow-lg overflow-hidden">
                        <div class="p-4 border-b border-slate-100">
                            <h3 class="font-bold text-slate-900 capitalize">{{ groupTitles[group] || group }}</h3>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <div v-for="s in items" :key="s.key" class="p-4 grid grid-cols-1 md:grid-cols-2 gap-3 items-center">
                                <div>
                                    <p class="text-sm font-bold text-slate-800">{{ s.label }}</p>
                                    <p class="text-xs text-slate-500 mt-0.5 font-mono">{{ s.key }}</p>
                                    <p v-if="s.description" class="text-xs text-slate-500 mt-0.5">{{ s.description }}</p>
                                </div>
                                <div>
                                    <input v-if="s.value_type === 'number'" v-model="form.values[s.key]" type="number" min="0" step="any"
                                        :disabled="!canEdit" :aria-label="s.label"
                                        class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60" />
                                    <input v-else-if="s.value_type === 'time'" v-model="form.values[s.key]" type="text" placeholder="HH:MM:SS"
                                        :disabled="!canEdit" :aria-label="s.label"
                                        class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60" />
                                    <select v-else-if="s.value_type === 'boolean'" v-model="form.values[s.key]"
                                        :disabled="!canEdit" :aria-label="s.label"
                                        class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60">
                                        <option value="1">Yes</option>
                                        <option value="0">No</option>
                                    </select>
                                    <input v-else v-model="form.values[s.key]" type="text" maxlength="40"
                                        :disabled="!canEdit" :aria-label="s.label"
                                        class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60" />
                                    <p v-if="s.value_type === 'boolean'" class="text-xs text-slate-500 mt-1">Current: {{ boolLabel(s.raw) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="bg-white rounded-2xl border border-slate-200/60 shadow-lg overflow-hidden relative">
                    <div class="p-4 border-b border-slate-100">
                        <h3 class="font-bold text-slate-900">Change history</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Every saved change, newest first.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gradient-to-r from-slate-50 to-transparent border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                    <th class="py-3 px-5">When</th>
                                    <th class="py-3 px-5">By</th>
                                    <th class="py-3 px-5">Setting</th>
                                    <th class="py-3 px-5">Old</th>
                                    <th class="py-3 px-5">New</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(h, i) in history" :key="i" class="hover:bg-blue-50/50 text-xs">
                                    <td class="py-3 px-5 text-slate-500 whitespace-nowrap">{{ h.at }}</td>
                                    <td class="py-3 px-5 font-semibold">{{ h.by }}</td>
                                    <td class="py-3 px-5 font-mono text-slate-600">{{ h.key }}</td>
                                    <td class="py-3 px-5 font-mono text-rose-600">{{ h.old_value ?? '—' }}</td>
                                    <td class="py-3 px-5 font-mono text-emerald-600">{{ h.new_value ?? '—' }}</td>
                                </tr>
                                <tr v-if="!history.length">
                                    <td colspan="5" class="py-10 text-center text-sm text-slate-400">No changes recorded yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.bg-grid-slate-100 { background-image: radial-gradient(circle, #cbd5e1 1px, transparent 1px); background-size: 24px 24px; }
.bg-clip-text { -webkit-background-clip: text; background-clip: text; }
</style>
