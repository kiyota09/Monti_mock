<script setup>
import { onMounted, onUnmounted } from 'vue';
import { X } from 'lucide-vue-next';

// Shared modal chrome: overlay, ESC-to-close, body scroll-lock (nest-safe),
// consistent panel shape. Parents render with v-if and listen to @close.
const props = defineProps({
    size: { type: String, default: 'md' }, // sm | md | lg
    dismissible: { type: Boolean, default: true },
});
const emit = defineEmits(['close']);

const sizes = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
};

let lockCount = 0;
const lock = () => {
    lockCount += 1;
    if (lockCount === 1) {
        document.body.dataset.modalScroll = document.body.style.overflow || '';
        document.body.style.overflow = 'hidden';
    }
};
const unlock = () => {
    lockCount = Math.max(0, lockCount - 1);
    if (lockCount === 0) document.body.style.overflow = document.body.dataset.modalScroll || '';
};

const onKey = (e) => {
    if (e.key === 'Escape' && props.dismissible) emit('close');
};

onMounted(() => {
    lock();
    window.addEventListener('keydown', onKey);
});
onUnmounted(() => {
    window.removeEventListener('keydown', onKey);
    unlock();
});
</script>

<template>
    <Teleport to="body">
        <Transition name="modal-shell" appear>
            <div
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                @click.self="dismissible && emit('close')"
                role="dialog"
                aria-modal="true"
            >
                <div
                    :class="['max-h-[92vh] w-full overflow-y-auto rounded-3xl bg-white shadow-2xl dark:bg-zinc-900', sizes[size] || sizes.md]"
                >
                    <slot name="header" />
                    <slot />
                    <slot name="footer" />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-shell-enter-active, .modal-shell-leave-active { transition: opacity 0.2s ease; }
.modal-shell-enter-active > div, .modal-shell-leave-active > div { transition: transform 0.25s cubic-bezier(0.22,1,0.36,1), opacity 0.25s ease; }
.modal-shell-enter-from, .modal-shell-leave-to { opacity: 0; }
.modal-shell-enter-from > div, .modal-shell-leave-to > div { opacity: 0; transform: scale(0.96) translateY(8px); }
</style>
