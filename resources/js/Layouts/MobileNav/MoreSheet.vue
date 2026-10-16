<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { X, Search } from 'lucide-vue-next'

const props = defineProps({
    show: { type: Boolean, default: false },
    groups: { type: Array, default: () => [] },
    activeRoute: { type: String, default: '' },
})

const emit = defineEmits(['close'])

const search = ref('')
const sheetRef = ref(null)
const moreButtonRef = ref(null)
const dragStartY = ref(0)
const isDragging = ref(false)

const filteredGroups = computed(() => {
    if (!search.value.trim()) return props.groups
    const q = search.value.toLowerCase().trim()
    return props.groups
        .map(g => ({
            ...g,
            pages: g.pages.filter(p => p.label.toLowerCase().includes(q)),
        }))
        .filter(g => g.pages.length > 0)
})

const totalPages = computed(() => props.groups.reduce((sum, g) => sum + g.pages.length, 0))
const showSearch = computed(() => totalPages.value > 10)

const isActive = (page) => {
    if (!page.href) return false
    return props.activeRoute === page.href || props.activeRoute.startsWith(page.href + '/')
}

const handleEsc = (e) => {
    if (e.key === 'Escape') emit('close')
}

watch(() => props.show, async (val) => {
    if (val) {
        document.body.style.overflow = 'hidden'
        window.addEventListener('keydown', handleEsc)
        await nextTick()
        if (sheetRef.value) {
            const focusable = sheetRef.value.querySelector('button, a[href], input')
            if (focusable) focusable.focus()
        }
    } else {
        document.body.style.overflow = ''
        window.removeEventListener('keydown', handleEsc)
        if (moreButtonRef.value) moreButtonRef.value.focus()
    }
})

onUnmounted(() => {
    document.body.style.overflow = ''
    window.removeEventListener('keydown', handleEsc)
})

const handleDragStart = (e) => {
    dragStartY.value = e.touches[0].clientY
    isDragging.value = true
}

const handleDragMove = (e) => {
    if (!isDragging.value) return
    const currentY = e.touches[0].clientY
    if (currentY - dragStartY.value > 90) {
        isDragging.value = false
        emit('close')
    }
}

const handleDragEnd = () => {
    isDragging.value = false
}

const handleBackdropClick = () => {
    emit('close')
}

defineExpose({ moreButtonRef })
</script>

<template>
    <Teleport to="body">
        <Transition name="sheet">
            <div
                v-if="show"
                class="fixed inset-0 z-[100] flex items-end justify-center"
                role="dialog"
                aria-modal="true"
                aria-label="All pages"
            >
                <div
                    class="absolute inset-0 bg-black/40 backdrop-blur-sm"
                    @click="handleBackdropClick"
                    aria-hidden="true"
                ></div>

                <div
                    ref="sheetRef"
                    class="relative w-full max-w-lg bg-white dark:bg-gray-900 rounded-t-3xl shadow-2xl flex flex-col max-h-[86vh] overflow-hidden"
                    @touchstart.passive="handleDragStart"
                    @touchmove.passive="handleDragMove"
                    @touchend="handleDragEnd"
                >
                    <div class="flex items-center justify-between px-5 pt-3 pb-2">
                        <div class="w-10 h-1 bg-gray-300 dark:bg-gray-600 rounded-full mx-auto"></div>
                        <button
                            @click="emit('close')"
                            class="p-2 rounded-xl text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                            aria-label="Close"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div v-if="showSearch" class="px-5 pb-3">
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input
                                v-model="search"
                                type="search"
                                placeholder="Search pages..."
                                class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500"
                            />
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto px-2 pb-6">
                        <div v-if="filteredGroups.length === 0" class="py-12 text-center">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                No pages match "{{ search }}". Try a shorter word.
                            </p>
                        </div>

                        <div v-for="group in filteredGroups" :key="group.moduleId" class="mb-2">
                            <div class="px-3 py-2">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ group.moduleLabel }}</span>
                            </div>
                            <Link
                                v-for="page in group.pages"
                                :key="page.id"
                                :href="page.href"
                                @click="emit('close')"
                                :class="[
                                    'flex items-center gap-3 px-3 py-3 rounded-xl transition-colors',
                                    isActive(page)
                                        ? 'bg-indigo-50 dark:bg-indigo-900/30'
                                        : 'hover:bg-gray-50 dark:hover:bg-gray-800/50'
                                ]"
                            >
                                <div :class="[
                                    'p-2 rounded-xl flex-shrink-0',
                                    isActive(page)
                                        ? 'bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white'
                                        : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400'
                                ]">
                                    <component :is="page.icon" class="h-4 w-4" />
                                </div>
                                <span :class="[
                                    'text-sm truncate',
                                    isActive(page) ? 'text-indigo-700 dark:text-indigo-300 font-semibold' : 'text-gray-700 dark:text-gray-300 font-medium'
                                ]">{{ page.label }}</span>
                                <span
                                    v-if="page.badge"
                                    class="ml-auto min-w-[20px] h-5 px-1.5 rounded-full bg-rose-500 text-white text-[10px] font-bold inline-flex items-center justify-center"
                                >{{ page.badge > 99 ? '99+' : page.badge }}</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.sheet-enter-active,
.sheet-leave-active {
    transition: opacity 0.25s ease;
}
.sheet-enter-active > div:last-child,
.sheet-leave-active > div:last-child {
    transition: transform 0.25s ease-out;
}
.sheet-enter-from,
.sheet-leave-to {
    opacity: 0;
}
.sheet-enter-from > div:last-child,
.sheet-leave-to > div:last-child {
    transform: translateY(100%);
}
</style>
