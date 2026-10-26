<script setup>
import { computed, nextTick, ref } from 'vue';
import { route } from 'ziggy-js';
import {
    Bookmark, X, Plus, Copy, Check, Trash2, Loader2, AlertTriangle, ChevronRight, Pencil,
} from 'lucide-vue-next';

/**
 * Floating message-template widget.
 *
 * Always visible in the bottom-right corner of the CRM pages it is mounted on.
 * Templates are stored per-user AND per-page (`pageKey`), so what a user saves
 * from Opportunities never leaks onto Leads.
 */
const props = defineProps({
    pageKey: { type: String, required: true },
    label: { type: String, default: 'Templates' },
});

const open = ref(false);
const loading = ref(false);
const saving = ref(false);
const templates = ref([]);
const error = ref('');
const copiedId = ref(null);
const confirmId = ref(null);
const expandedId = ref(null);
const editingId = ref(null);
const showForm = ref(false);
const form = ref({ title: '', body: '' });
const titleEl = ref(null);
const bodyEl = ref(null);

const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const count = computed(() => templates.value.length);

const load = async () => {
    loading.value = true;
    error.value = '';
    try {
        const res = await fetch(route('crm.templates.index', { page: props.pageKey }), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('Unable to load templates.');
        const data = await res.json();
        templates.value = data.templates ?? [];
    } catch (e) {
        error.value = e.message || 'Unable to load templates.';
    } finally {
        loading.value = false;
    }
};

const toggle = () => {
    open.value = !open.value;
    if (open.value) {
        confirmId.value = null;
        expandedId.value = null;
        load();
    }
};

const toggleExpanded = (id) => {
    expandedId.value = expandedId.value === id ? null : id;
};

const resetForm = () => {
    showForm.value = false;
    editingId.value = null;
    form.value = { title: '', body: '' };
};

/**
 * Grow the body field with its content so pasted messages are fully
 * readable while editing (capped at 45% of the viewport).
 */
const resizeBody = () => {
    const el = bodyEl.value;
    if (!el) return;
    el.style.height = 'auto';
    const max = Math.round(window.innerHeight * 0.45);
    el.style.height = `${Math.min(Math.max(el.scrollHeight, 144), max)}px`;
};

const onBodyInput = () => nextTick(resizeBody);

const focusForm = () => nextTick(() => {
    titleEl.value?.focus();
    resizeBody();
});

const startNew = () => {
    form.value = { title: '', body: '' };
    editingId.value = null;
    error.value = '';
    showForm.value = true;
    focusForm();
};

const startEdit = (template) => {
    form.value = { title: template.title ?? '', body: template.body ?? '' };
    editingId.value = template.id;
    expandedId.value = template.id;
    error.value = '';
    showForm.value = true;
    focusForm();
};

const cancelForm = () => {
    resetForm();
    error.value = '';
};

const save = async () => {
    if (!form.value.title.trim() || !form.value.body.trim()) {
        error.value = 'A title and body are both required.';
        return;
    }
    saving.value = true;
    error.value = '';
    try {
        const editing = editingId.value;
        const res = await fetch(
            editing ? route('crm.templates.update', editing) : route('crm.templates.store'),
            {
                method: editing ? 'PATCH' : 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    ...(editing ? {} : { page_key: props.pageKey }),
                    title: form.value.title.trim(),
                    body: form.value.body,
                }),
            },
        );
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            error.value = data.message
                || Object.values(data.errors ?? {})?.[0]?.[0]
                || 'Unable to save the template.';
            return;
        }

        if (editing) {
            const index = templates.value.findIndex((t) => t.id === editing);
            if (index !== -1) templates.value[index] = data.template;
        } else {
            templates.value.unshift(data.template);
        }

        expandedId.value = data.template?.id ?? null;
        resetForm();
    } catch (e) {
        error.value = e.message || 'Unable to save the template.';
    } finally {
        saving.value = false;
    }
};

const copy = async (template) => {
    const text = template.body ?? '';
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
        } else {
            const el = document.createElement('textarea');
            el.value = text;
            el.style.position = 'fixed';
            el.style.opacity = '0';
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
        }
        copiedId.value = template.id;
        setTimeout(() => {
            if (copiedId.value === template.id) copiedId.value = null;
        }, 1800);
    } catch {
        error.value = 'Could not copy to the clipboard.';
    }
};

const remove = async (template) => {
    try {
        const res = await fetch(route('crm.templates.destroy', template.id), {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('Unable to delete the template.');
        templates.value = templates.value.filter((t) => t.id !== template.id);
        if (expandedId.value === template.id) expandedId.value = null;
    } catch (e) {
        error.value = e.message || 'Unable to delete the template.';
    } finally {
        confirmId.value = null;
    }
};
</script>

<template>
    <Teleport to="body">
        <!-- Floating trigger — fixed to the viewport bottom-right, teleported to
             <body> so no scroll container can detach it. -->
        <button
            v-if="!open"
            type="button"
            @click="toggle"
            class="fixed bottom-6 right-4 z-[80] inline-flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-violet-600 to-fuchsia-700 text-white shadow-xl shadow-violet-500/30 transition hover:scale-105 active:scale-95 sm:right-6"
            :title="`${label} for this page`"
            aria-label="Open message templates"
        >
            <Bookmark class="h-6 w-6" />
            <span
                v-if="count"
                class="absolute -right-1 -top-1 inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-full bg-indigo-600 px-1.5 text-[10px] font-black text-white ring-2 ring-white dark:ring-zinc-950"
            >
                {{ count }}
            </span>
        </button>

        <!-- Drawer -->
        <div v-if="open" class="fixed inset-0 z-[90]">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="open = false" />
            <div class="absolute inset-y-0 right-0 flex w-full max-w-lg flex-col bg-white shadow-2xl dark:bg-zinc-900">
                <div class="flex items-center justify-between bg-gradient-to-r from-violet-700 to-fuchsia-800 px-5 py-4 text-white">
                    <div class="min-w-0">
                        <h3 class="flex items-center gap-2 font-black"><Bookmark class="h-4 w-4" /> {{ label }}</h3>
                        <p class="truncate text-xs text-violet-100">Click a title to view the full message, copy it, or edit it</p>
                    </div>
                    <button type="button" @click="open = false" aria-label="Close templates">
                        <X class="h-5 w-5 shrink-0" />
                    </button>
                </div>

                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-zinc-800">
                    <p class="text-xs font-black uppercase tracking-wider text-gray-500">{{ count }} template{{ count === 1 ? '' : 's' }}</p>
                    <button
                        type="button"
                        @click="startNew"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-violet-600 px-3 py-1.5 text-xs font-black uppercase text-white transition hover:bg-violet-700"
                    >
                        <Plus class="h-3.5 w-3.5" /> New
                    </button>
                </div>

                <p v-if="error" class="mx-4 mt-3 rounded-xl bg-rose-50 px-3 py-2 text-xs font-bold text-rose-600 dark:bg-rose-500/10">
                    {{ error }}
                </p>

                <!-- Create / edit form -->
                <form v-if="showForm" @submit.prevent="save" class="space-y-3 border-b border-gray-100 p-4 dark:border-zinc-800">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[10px] font-black uppercase tracking-wider text-violet-600 dark:text-violet-400">
                            {{ editingId ? 'Edit template' : 'New template' }}
                        </p>
                        <button
                            type="button"
                            @click="cancelForm"
                            class="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-wider text-gray-400 transition hover:text-gray-600 dark:hover:text-zinc-200"
                        >
                            <X class="h-3 w-3" /> Close
                        </button>
                    </div>
                    <input
                        ref="titleEl"
                        v-model="form.title"
                        type="text"
                        maxlength="255"
                        placeholder="Template title (e.g. Price list reply)"
                        class="w-full rounded-xl border-0 bg-gray-50 px-4 py-3 text-sm font-bold outline-none focus:ring-2 focus:ring-violet-500 dark:bg-zinc-800 dark:text-white"
                    />
                    <div>
                        <textarea
                            ref="bodyEl"
                            v-model="form.body"
                            rows="8"
                            placeholder="Message body…"
                            class="w-full min-h-[9rem] resize-y overflow-y-auto rounded-xl border-0 bg-gray-50 px-4 py-3 text-sm leading-relaxed outline-none focus:ring-2 focus:ring-violet-500 dark:bg-zinc-800 dark:text-white"
                            @input="onBodyInput"
                        />
                        <p class="mt-1 text-[10px] font-bold text-gray-400">The field grows automatically as you type or paste long text.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="submit"
                            :disabled="saving"
                            class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-violet-600 py-2.5 text-xs font-black uppercase text-white transition hover:bg-violet-700 disabled:opacity-50"
                        >
                            <Loader2 v-if="saving" class="h-3.5 w-3.5 animate-spin" />
                            {{ saving ? 'Saving…' : (editingId ? 'Save changes' : 'Save template') }}
                        </button>
                        <button
                            type="button"
                            @click="cancelForm"
                            class="rounded-xl border border-gray-200 px-4 py-2.5 text-xs font-black uppercase text-gray-500 transition hover:bg-gray-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                        >
                            Cancel
                        </button>
                    </div>
                </form>

                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                    <div v-if="loading" class="flex items-center justify-center py-16 text-gray-400">
                        <Loader2 class="h-5 w-5 animate-spin" />
                    </div>
                    <p v-else-if="!templates.length" class="py-16 text-center text-xs font-bold text-gray-400">
                        No templates saved on this page yet.
                    </p>
                    <div
                        v-for="t in templates"
                        v-else
                        :key="t.id"
                        class="overflow-hidden rounded-2xl border border-gray-100 bg-slate-50/60 dark:border-zinc-800 dark:bg-zinc-800/40"
                    >
                        <div class="flex items-start gap-1.5 p-3">
                            <button
                                type="button"
                                @click="toggleExpanded(t.id)"
                                :aria-expanded="expandedId === t.id"
                                class="flex min-w-0 flex-1 items-center gap-2 rounded-lg py-1 text-left transition hover:text-violet-700 dark:hover:text-violet-300"
                            >
                                <ChevronRight
                                    :class="['h-4 w-4 shrink-0 text-gray-400 transition-transform', expandedId === t.id ? 'rotate-90' : '']"
                                />
                                <span class="min-w-0 flex-1 truncate text-sm font-black text-slate-800 dark:text-slate-100">{{ t.title }}</span>
                            </button>
                            <div class="flex shrink-0 items-center gap-1">
                                <button
                                    type="button"
                                    @click="copy(t)"
                                    :title="copiedId === t.id ? 'Copied!' : 'Copy text'"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-white hover:text-violet-600 dark:hover:bg-zinc-700"
                                >
                                    <Check v-if="copiedId === t.id" class="h-4 w-4 text-emerald-500" />
                                    <Copy v-else class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    @click="startEdit(t)"
                                    title="Edit template"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-white hover:text-violet-600 dark:hover:bg-zinc-700"
                                >
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    @click="confirmId = t.id"
                                    title="Delete template"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-300 transition hover:bg-rose-50 hover:text-rose-500 dark:hover:bg-rose-500/10"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Expanded full text -->
                        <div v-if="expandedId === t.id" class="border-t border-gray-100 bg-white px-3.5 py-3 dark:border-zinc-700 dark:bg-zinc-900/60">
                            <div class="max-h-72 overflow-y-auto rounded-xl bg-slate-50 px-3.5 py-3 dark:bg-zinc-800/60">
                                <p class="whitespace-pre-wrap break-words text-sm leading-relaxed text-slate-700 selection:bg-violet-200 dark:text-slate-200">{{ t.body }}</p>
                            </div>
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ t.body?.length ?? 0 }} characters</span>
                                <button
                                    type="button"
                                    @click="copy(t)"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-3 py-1.5 text-[10px] font-black uppercase text-white transition hover:bg-violet-700"
                                >
                                    <Check v-if="copiedId === t.id" class="h-3 w-3" />
                                    <Copy v-else class="h-3 w-3" />
                                    {{ copiedId === t.id ? 'Copied' : 'Copy text' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete confirmation -->
        <div v-if="confirmId" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="confirmId = null" />
            <div class="relative w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl dark:bg-zinc-900">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-500/15">
                        <AlertTriangle class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h4 class="font-black text-slate-900 dark:text-white">Delete this template?</h4>
                        <p class="mt-1 text-sm text-gray-500">This action cannot be undone.</p>
                    </div>
                </div>
                <div class="mt-5 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="confirmId = null"
                        class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-black uppercase text-gray-500 transition hover:bg-gray-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="remove(templates.find((t) => t.id === confirmId))"
                        class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-black uppercase text-white transition hover:bg-rose-700"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
