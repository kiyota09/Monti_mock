<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { MoreHorizontal } from 'lucide-vue-next'

const props = defineProps({
    items: { type: Array, default: () => [] },
    activeRoute: { type: String, default: '' },
    moreActive: { type: Boolean, default: false },
    hasMore: { type: Boolean, default: false },
    unreadCount: { type: Number, default: 0 },
})

const emit = defineEmits(['open-more'])

const isActive = (item) => {
    if (!item.href) return false
    return props.activeRoute === item.href || props.activeRoute.startsWith(item.href + '/')
}

const badgeText = (count) => {
    if (!count) return ''
    return count > 99 ? '99+' : String(count)
}
</script>

<template>
    <nav aria-label="Primary" class="fixed bottom-0 left-0 right-0 z-50 bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl border-t border-gray-200/60 dark:border-gray-700/60 pb-[env(safe-area-inset-bottom)]">
        <div class="flex items-stretch justify-around h-14">
            <Link
                v-for="item in items"
                :key="item.id"
                :href="item.href"
                :aria-current="isActive(item) ? 'page' : undefined"
                :class="[
                    'flex flex-col items-center justify-center flex-1 min-w-0 gap-0 transition-all duration-200',
                    isActive(item)
                        ? 'text-indigo-700 dark:text-indigo-300'
                        : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'
                ]"
            >
                <div :class="[
                    'relative p-1 rounded-xl transition-all duration-200',
                    isActive(item) ? 'bg-indigo-100 dark:bg-indigo-900/40' : ''
                ]">
                    <component :is="item.icon" class="h-[18px] w-[18px]" />
                    <span
                        v-if="item.badge || (item.showUnread && unreadCount > 0)"
                        class="absolute -top-1 -right-1.5 min-w-[16px] h-[16px] px-1 rounded-full bg-rose-500 text-white text-[8px] font-bold inline-flex items-center justify-center leading-none"
                    >{{ badgeText(item.badge || (item.showUnread ? unreadCount : 0)) }}</span>
                </div>
                <span :class="[
                    'text-[10px] leading-tight truncate max-w-full',
                    isActive(item) ? 'font-semibold' : 'font-medium'
                ]">{{ item.label }}</span>
            </Link>

            <button
                v-if="hasMore"
                @click="emit('open-more')"
                aria-haspopup="dialog"
                :class="[
                    'flex flex-col items-center justify-center flex-1 min-w-0 gap-0 transition-all duration-200',
                    moreActive
                        ? 'text-indigo-700 dark:text-indigo-300'
                        : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'
                ]"
            >
                <div :class="[
                    'relative p-1 rounded-xl transition-all duration-200',
                    moreActive ? 'bg-indigo-100 dark:bg-indigo-900/40' : ''
                ]">
                    <MoreHorizontal class="h-[18px] w-[18px]" />
                </div>
                <span class="text-[10px] font-medium leading-tight">More</span>
            </button>
        </div>
    </nav>
</template>
