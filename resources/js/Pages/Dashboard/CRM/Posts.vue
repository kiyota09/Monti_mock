<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { usePageAccess } from '@/composables/usePageAccess';
import { Archive, Edit3, Image, Megaphone, RotateCcw, Trash2, Upload, Video, X } from 'lucide-vue-next';

const props = defineProps({ posts: { type: Array, default: () => [] } });
const { canEdit } = usePageAccess();
const canPublish = computed(() => canEdit('CRM', 'campaigns'));
const activeTab = ref('published');
const editingPostId = ref(null);
const createInput = ref(null);
const createPreviews = ref([]);
const editPreviews = ref([]);
const existingMedia = ref([]);

const form = useForm({ caption: '', media: [] });
const editForm = useForm({ caption: '', media: [], remove_media: [] });

const publishedPosts = computed(() => props.posts.filter(post => !post.archived_at));
const archivedPosts = computed(() => props.posts.filter(post => !!post.archived_at));
const visiblePosts = computed(() => activeTab.value === 'archived' ? archivedPosts.value : publishedPosts.value);
const visibleExistingMedia = computed(() => existingMedia.value.filter(item => !editForm.remove_media.includes(item.path)));

const selectMedia = (event, targetForm, previewTarget, existingCount = 0) => {
    const files = Array.from(event.target.files || []);
    const remaining = Math.max(0, 8 - existingCount - targetForm.media.length);
    const selected = files.slice(0, remaining);
    targetForm.media = [...targetForm.media, ...selected];
    previewTarget.push(...selected.map(file => ({
        name: file.name,
        url: URL.createObjectURL(file),
        type: file.type.startsWith('video/') ? 'video' : 'image',
    })));
    event.target.value = '';
};

const clearPreviews = (target) => {
    target.value.forEach(item => URL.revokeObjectURL(item.url));
    target.value = [];
};

const removeSelected = (targetForm, previewTarget, index) => {
    URL.revokeObjectURL(previewTarget[index]?.url);
    previewTarget.splice(index, 1);
    targetForm.media.splice(index, 1);
};

const publish = () => form.post(route('crm.posts.store'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
        clearPreviews(createPreviews);
        form.reset();
    },
});

const startEdit = (post) => {
    editingPostId.value = post.id;
    editForm.reset();
    editForm.caption = post.caption;
    editForm.media = [];
    editForm.remove_media = [];
    clearPreviews(editPreviews);
    existingMedia.value = (post.media || []).map(item => ({ ...item, path: item.path || '' }));
};

const cancelEdit = () => {
    clearPreviews(editPreviews);
    editForm.reset();
    existingMedia.value = [];
    editingPostId.value = null;
};

const removeExistingMedia = (item) => {
    if (item.path && !editForm.remove_media.includes(item.path)) editForm.remove_media.push(item.path);
};

const openEditMediaPicker = (postId) => {
    document.getElementById(`edit-media-${postId}`)?.click();
};

const saveEdit = (post) => editForm.post(route('crm.posts.update', post.id), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => cancelEdit(),
});

const archivePost = (post) => {
    router.post(route('crm.posts.archive', post.id), {}, { preserveScroll: true });
};

const restorePost = (post) => {
    router.post(route('crm.posts.restore', post.id), {}, { preserveScroll: true });
};

const deletePost = (post) => {
    if (window.confirm('Permanently delete this post and its uploaded media?')) {
        router.delete(route('crm.posts.destroy', post.id), { preserveScroll: true });
    }
};

const postedAt = (date) => date ? new Date(date).toLocaleString() : '';
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Company Posts" />
        <div class="crm-posts-page min-h-screen">
            <div class="mx-auto max-w-5xl space-y-6 p-4 pb-16 sm:p-6">
                <header class="posts-header rounded-3xl bg-gradient-to-br from-indigo-700 via-blue-700 to-cyan-700 p-6 text-white shadow-xl sm:p-8">
                    <div class="flex items-center gap-4">
                        <div class="rounded-2xl bg-white/15 p-3"><Megaphone class="h-7 w-7" /></div>
                        <div><p class="text-xs font-bold uppercase tracking-[.2em] opacity-80">CRM · Company page</p><h1 class="mt-1 text-2xl font-black sm:text-3xl">Monti Textile Posts</h1><p class="mt-1 text-sm opacity-85">Share company news, promotions, events, and celebrations on the public welcome page.</p></div>
                    </div>
                </header>

                <nav class="posts-tabs flex gap-2 rounded-2xl border p-2" aria-label="Post status">
                    <button type="button" class="posts-tab rounded-xl px-4 py-2 text-sm font-bold transition" :class="activeTab === 'published' ? 'is-active' : ''" @click="activeTab = 'published'">Published <span class="ml-1 opacity-70">{{ publishedPosts.length }}</span></button>
                    <button type="button" class="posts-tab rounded-xl px-4 py-2 text-sm font-bold transition" :class="activeTab === 'archived' ? 'is-active' : ''" @click="activeTab = 'archived'">Archive <span class="ml-1 opacity-70">{{ archivedPosts.length }}</span></button>
                </nav>

                <form v-if="canPublish && activeTab === 'published'" class="posts-surface space-y-4 rounded-3xl border p-5 shadow-sm" @submit.prevent="publish">
                    <label class="posts-heading block text-sm font-bold" for="post-caption">Write a post</label>
                    <textarea id="post-caption" v-model="form.caption" rows="4" maxlength="5000" required placeholder="What's happening at Monti Textile?" class="posts-input w-full resize-y rounded-2xl border p-4 text-sm outline-none focus:ring-2" />
                    <div v-if="createPreviews.length" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div v-for="(item, index) in createPreviews" :key="item.url" class="relative overflow-hidden rounded-xl">
                            <video v-if="item.type === 'video'" :src="item.url" class="h-32 w-full object-cover" muted />
                            <img v-else :src="item.url" :alt="item.name" class="h-32 w-full object-cover" />
                            <button type="button" class="absolute right-2 top-2 rounded-lg bg-black/70 p-1.5 text-white" aria-label="Remove attachment" @click="removeSelected(form, createPreviews, index)"><X class="h-4 w-4" /></button>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <input ref="createInput" type="file" class="hidden" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime" multiple @change="selectMedia($event, form, createPreviews)" />
                            <button type="button" :disabled="form.media.length >= 8" class="posts-secondary-button inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-bold disabled:opacity-50" @click="createInput?.click()"><Upload class="h-4 w-4" /> Add media</button>
                            <span class="posts-muted hidden items-center gap-1 text-xs sm:inline-flex"><Image class="h-3.5 w-3.5" /><Video class="h-3.5 w-3.5" /> Images and video</span>
                        </div>
                        <button :disabled="form.processing || !form.caption.trim()" class="posts-primary-button rounded-xl px-5 py-2.5 text-sm font-black shadow-lg disabled:opacity-50">{{ form.processing ? 'Publishing…' : 'Post' }}</button>
                    </div>
                    <p v-if="form.errors.caption || form.errors.media || form.errors['media.0']" class="text-xs font-semibold text-rose-600">{{ form.errors.caption || form.errors.media || form.errors['media.0'] }}</p>
                    <p class="posts-muted text-xs">Up to 8 image or video files, 50 MB each.</p>
                </form>

                <section class="space-y-4">
                    <div class="flex items-center justify-between"><h2 class="posts-heading text-lg font-black">{{ activeTab === 'archived' ? 'Archived posts' : 'Published posts' }}</h2><span class="posts-muted text-xs font-semibold">{{ visiblePosts.length }} total</span></div>
                    <article v-for="post in visiblePosts" :key="post.id" class="posts-surface overflow-hidden rounded-3xl border shadow-sm">
                        <div class="flex items-start justify-between gap-4 p-5">
                            <div><p class="posts-heading text-sm font-black">{{ post.creator || 'Monti Textile' }}</p><p class="posts-muted mt-1 text-xs">{{ postedAt(post.created_at) }}<span v-if="post.archived_at"> · Archived {{ postedAt(post.archived_at) }}</span></p></div>
                            <div v-if="canPublish" class="flex flex-wrap items-center justify-end gap-2">
                                <template v-if="activeTab === 'published'">
                                    <button type="button" class="posts-secondary-button inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-bold" @click="startEdit(post)"><Edit3 class="h-3.5 w-3.5" /> Edit</button>
                                    <button type="button" class="posts-secondary-button inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-bold" @click="archivePost(post)"><Archive class="h-3.5 w-3.5" /> Archive</button>
                                </template>
                                <button v-else type="button" class="posts-secondary-button inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-bold" @click="restorePost(post)"><RotateCcw class="h-3.5 w-3.5" /> Restore</button>
                                <button type="button" class="posts-danger-button rounded-lg p-2" aria-label="Permanently delete post" @click="deletePost(post)"><Trash2 class="h-4 w-4" /></button>
                            </div>
                        </div>

                        <form v-if="editingPostId === post.id" class="space-y-4 px-5 pb-5" @submit.prevent="saveEdit(post)">
                            <textarea v-model="editForm.caption" rows="4" maxlength="5000" required class="posts-input w-full resize-y rounded-2xl border p-4 text-sm outline-none focus:ring-2" />
                            <div v-if="visibleExistingMedia.length || editPreviews.length" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <div v-for="item in visibleExistingMedia" :key="item.path || item.url" class="relative overflow-hidden rounded-xl">
                                    <video v-if="item.type === 'video'" :src="item.url" class="h-32 w-full object-cover" muted />
                                    <img v-else :src="item.url" alt="Post attachment" class="h-32 w-full object-cover" />
                                    <button type="button" class="absolute right-2 top-2 rounded-lg bg-black/70 p-1.5 text-white" aria-label="Remove attachment" @click="removeExistingMedia(item)"><X class="h-4 w-4" /></button>
                                </div>
                                <div v-for="(item, index) in editPreviews" :key="item.url" class="relative overflow-hidden rounded-xl">
                                    <video v-if="item.type === 'video'" :src="item.url" class="h-32 w-full object-cover" muted />
                                    <img v-else :src="item.url" :alt="item.name" class="h-32 w-full object-cover" />
                                    <button type="button" class="absolute right-2 top-2 rounded-lg bg-black/70 p-1.5 text-white" aria-label="Remove attachment" @click="removeSelected(editForm, editPreviews, index)"><X class="h-4 w-4" /></button>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <input :id="`edit-media-${post.id}`" type="file" class="hidden" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime" multiple @change="selectMedia($event, editForm, editPreviews, visibleExistingMedia.length)" />
                                <button type="button" :disabled="visibleExistingMedia.length + editForm.media.length >= 8" class="posts-secondary-button inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-bold disabled:opacity-50" @click="openEditMediaPicker(post.id)"><Upload class="h-4 w-4" /> Add media</button>
                                <div class="flex gap-2">
                                    <button type="button" class="posts-secondary-button rounded-xl border px-4 py-2 text-xs font-bold" @click="cancelEdit">Cancel</button>
                                    <button :disabled="editForm.processing" class="posts-primary-button rounded-xl px-4 py-2 text-xs font-black disabled:opacity-50">{{ editForm.processing ? 'Saving…' : 'Save changes' }}</button>
                                </div>
                            </div>
                            <p v-if="editForm.errors.caption || editForm.errors.media || editForm.errors['media.0']" class="text-xs font-semibold text-rose-600">{{ editForm.errors.caption || editForm.errors.media || editForm.errors['media.0'] }}</p>
                        </form>

                        <template v-else>
                            <p class="posts-body whitespace-pre-wrap px-5 pb-4 text-sm leading-6">{{ post.caption }}</p>
                            <div v-if="post.media?.length" class="grid grid-cols-1 gap-1">
                                <template v-for="(media, i) in post.media" :key="media.path || media.url">
                                    <video v-if="media.type === 'video'" :src="media.url" controls class="posts-media max-h-[32rem] w-full object-contain" />
                                    <img v-else :src="media.url" :alt="`Post image ${i + 1}`" class="posts-media max-h-[32rem] w-full object-contain" />
                                </template>
                            </div>
                        </template>
                    </article>
                    <div v-if="!visiblePosts.length" class="posts-empty rounded-3xl border border-dashed p-10 text-center text-sm">{{ activeTab === 'archived' ? 'No archived posts.' : 'No published posts yet. Publish the first company update above.' }}</div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
