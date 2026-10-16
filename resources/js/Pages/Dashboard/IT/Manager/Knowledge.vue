<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePageAccess } from '@/composables/usePageAccess';
import { Search, Plus, X, Eye, Pencil, Trash2, BookOpen } from 'lucide-vue-next';

const props = defineProps({
    articles: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    isManager: { type: Boolean, default: false },
    categories: { type: Array, default: () => [] },
});

const showForm = ref(false);
const editing = ref(null);
const reading = ref(null);

const { canEdit } = usePageAccess();
const canEditKnowledge = computed(() => canEdit('IT', 'knowledge'));

const form = useForm({
    title: '', category: 'general', body: '', audience: 'all', is_published: true,
});

const openCreate = () => {
    editing.value = null;
    form.reset();
    showForm.value = true;
};

const openEdit = (article) => {
    editing.value = article;
    form.title = article.title;
    form.category = article.category;
    form.body = article.body;
    form.audience = article.audience;
    form.is_published = !!article.is_published;
    showForm.value = true;
};

const submitForm = () => {
    if (editing.value) {
        form.put(route('it.knowledge.update', editing.value.id), {
            preserveScroll: true, onSuccess: () => { showForm.value = false; },
        });
    } else {
        form.post(route('it.knowledge.store'), {
            preserveScroll: true, onSuccess: () => { showForm.value = false; },
        });
    }
};

const removeArticle = (article) => {
    if (!confirm(`Delete "${article.title}"?`)) return;
    router.delete(route('it.knowledge.destroy', article.id), { preserveScroll: true });
};

const openArticle = (article) => {
    reading.value = article;
    router.post(route('it.knowledge.read', article.id), {}, { preserveScroll: true, preserveState: true });
};

const applyFilters = (patch) => {
    router.get(route('it.knowledge'), { ...props.filters, ...patch }, { preserveScroll: true, replace: true });
};

const rows = computed(() => props.articles.data || []);
</script>

<template>
    <Head title="Knowledge Base | IT Department" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    Knowledge <span class="text-blue-600">Base</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Self-help for office and plant — deflect tickets before they happen.</p>
            </div>
            <button v-if="canEditKnowledge" @click="openCreate"
                class="inline-flex w-full sm:w-auto items-center justify-center gap-2 px-4 py-3 sm:py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 active:scale-95">
                <Plus class="w-4 h-4" /> New Article
            </button>
            <span v-else class="text-xs font-bold text-amber-600 bg-amber-50 px-3 py-1.5 rounded-full">View only</span>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 mb-6">
            <div class="relative flex-1">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                <input :value="filters.search || ''" @input="applyFilters({ search: $event.target.value })" type="text"
                    placeholder="Search guides..."
                    class="w-full pl-9 pr-4 py-2.5 text-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200 placeholder-slate-400" />
            </div>
            <select :value="filters.category || ''" @change="applyFilters({ category: $event.target.value })"
                class="px-3 py-2.5 text-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                <option value="">All categories</option>
                <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
            </select>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="a in rows" :key="a.id"
                class="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm hover:shadow-md transition-all flex flex-col">
                <div class="flex items-center gap-2 mb-2">
                    <span class="px-2 py-0.5 rounded-full bg-blue-50 dark:bg-blue-900/20 text-blue-600 text-[10px] font-black uppercase">{{ a.category }}</span>
                    <span v-if="!a.is_published" class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-black uppercase">Draft</span>
                    <span class="ml-auto inline-flex items-center gap-1 text-[11px] text-slate-400"><Eye class="w-3.5 h-3.5" />{{ a.views }}</span>
                </div>
                <p class="text-sm font-black text-slate-900 dark:text-white">{{ a.title }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-2">{{ a.body }}</p>
                <div class="flex items-center gap-2 mt-4 pt-3 border-t border-slate-100 dark:border-slate-700">
                    <button @click="openArticle(a)" class="text-xs font-bold text-blue-600 hover:text-blue-500">Read →</button>
                    <span class="flex-1"></span>
                    <button v-if="canEditKnowledge && (isManager || a.author_id === $page.props.auth.user.id)" @click="openEdit(a)" class="p-2.5 sm:p-1.5 text-slate-400 hover:text-blue-600"><Pencil class="w-4 h-4" /></button>
                    <button v-if="isManager && canEditKnowledge" @click="removeArticle(a)" class="p-2.5 sm:p-1.5 text-slate-400 hover:text-red-500"><Trash2 class="w-4 h-4" /></button>
                </div>
            </div>
        </div>
        <div v-if="rows.length === 0" class="py-12 text-center">
            <BookOpen class="w-12 h-12 mx-auto mb-3 text-slate-300" />
            <p class="text-sm font-bold text-slate-400">No articles found.</p>
        </div>

        <!-- Editor modal -->
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-lg max-h-[90vh] overflow-y-auto p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ editing ? 'Edit Article' : 'New Article' }}</h2>
                    <button @click="showForm = false" class="p-1.5 text-slate-400 hover:text-slate-600"><X class="w-5 h-5" /></button>
                </div>
                <form @submit.prevent="submitForm" class="space-y-3">
                    <input v-model="form.title" required placeholder="Article title" class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input v-model="form.category" required placeholder="Category (erp, network…)" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                        <select v-model="form.audience" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                            <option value="all">Everyone</option><option value="office">Office</option>
                            <option value="plant">Plant floor</option><option value="it_staff">IT staff</option>
                        </select>
                    </div>
                    <textarea v-model="form.body" required rows="8" placeholder="Steps, commands, screenshots description… (one step per line)"
                        class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200"></textarea>
                    <label v-if="isManager" class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" v-model="form.is_published" class="rounded" /> Published
                    </label>
                    <p v-else class="text-xs text-slate-400">Staff submissions are saved as drafts for manager review.</p>
                    <button type="submit" :disabled="form.processing" class="w-full py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 active:scale-95 disabled:opacity-50">
                        {{ editing ? 'Save Changes' : 'Save Article' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Reader modal -->
        <div v-if="reading" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-lg max-h-[90vh] overflow-y-auto p-6">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <p class="text-[10px] font-black uppercase text-blue-600">{{ reading.category }} · {{ reading.audience }}</p>
                        <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ reading.title }}</h2>
                    </div>
                    <button @click="reading = null" class="p-1.5 text-slate-400 hover:text-slate-600"><X class="w-5 h-5" /></button>
                </div>
                <p class="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ reading.body }}</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
