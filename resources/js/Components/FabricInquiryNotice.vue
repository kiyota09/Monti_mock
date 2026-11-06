<script setup>
import { onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: 'Your inquiry has been sent!' },
    message: {
        type: String,
        default: 'Our fabric team has received your request and will follow up soon. You can continue the conversation in My Conversations.',
    },
    buttonText: { type: String, default: 'Got it' },
});

const emit = defineEmits(['update:modelValue', 'close']);

const close = () => {
    emit('update:modelValue', false);
    emit('close');
};

const onKeydown = (event) => {
    if (event.key === 'Escape' && props.modelValue) close();
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <Transition name="fabric-notice">
            <div v-if="modelValue" class="fabric-notice-backdrop" @click.self="close">
                <section
                    class="fabric-notice-card"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="fabric-notice-title"
                    aria-describedby="fabric-notice-message"
                >
                    <div class="fabric-notice-art">
                        <svg viewBox="0 0 320 190" role="img" aria-label="Folded blue fabric with a confirmation checkmark">
                            <defs>
                                <linearGradient id="fabric-bg" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0" stop-color="#eaf4ff" />
                                    <stop offset="1" stop-color="#dce9ff" />
                                </linearGradient>
                                <linearGradient id="fabric-top" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0" stop-color="#7db8f2" />
                                    <stop offset="1" stop-color="#397fc4" />
                                </linearGradient>
                                <pattern id="fabric-weave" width="8" height="8" patternUnits="userSpaceOnUse">
                                    <path d="M0 2H8M0 6H8" stroke="#fff" stroke-opacity=".19" stroke-width="1" />
                                    <path d="M2 0V8M6 0V8" stroke="#174e86" stroke-opacity=".10" stroke-width="1" />
                                </pattern>
                            </defs>
                            <rect width="320" height="190" fill="url(#fabric-bg)" />
                            <circle cx="48" cy="40" r="18" fill="#fff" opacity=".55" />
                            <circle cx="272" cy="52" r="27" fill="#fff" opacity=".45" />
                            <path d="M0 151Q70 130 143 151T320 145V190H0Z" fill="#c6dcf7" />

                            <!-- Layered folded fabric swatches -->
                            <path d="M70 125 112 78l142 10-39 58-145-4Z" fill="#28669f" />
                            <path d="m70 125 42-47 18 2-35 49Z" fill="#4b8fc9" />
                            <path d="m95 129 36-49 142 10-41 56Z" fill="url(#fabric-top)" />
                            <path d="m95 129 36-49 142 10-41 56Z" fill="url(#fabric-weave)" />
                            <path d="m113 142 38-47 122 9-36 50Z" fill="#87bde9" />
                            <path d="m113 142 38-47 122 9-36 50Z" fill="url(#fabric-weave)" opacity=".8" />
                            <path d="m151 95 13 1-37 47-14-1Z" fill="#d9edff" opacity=".48" />
                            <path d="m95 129 137 17" stroke="#e9f5ff" stroke-width="2" stroke-dasharray="3 5" opacity=".9" />

                            <!-- Thread spool -->
                            <g transform="translate(47 103) rotate(-12)">
                                <path d="M5 7h27v44H5z" rx="5" fill="#f3b55d" />
                                <path d="M5 7h27v44H5z" fill="url(#fabric-weave)" opacity=".65" />
                                <path d="M2 6q0-5 4-5h25q4 0 4 5v4H2Zm0 42h33v4q0 5-4 5H6q-4 0-4-5Z" fill="#e69b3e" />
                                <path d="M8 14h21M8 20h21M8 26h21M8 32h21M8 38h21M8 44h21" stroke="#fff4dd" stroke-width="2" opacity=".8" />
                            </g>

                            <!-- Confirmation badge -->
                            <circle cx="247" cy="54" r="24" fill="#fff" />
                            <circle cx="247" cy="54" r="19" fill="#23966a" />
                            <path d="m238 54 6 6 12-13" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="m85 48 3 7 7 3-7 3-3 7-3-7-7-3 7-3Zm177 60 2 5 5 2-5 2-2 5-2-5-5-2 5-2Z" fill="#fff" opacity=".9" />
                        </svg>
                    </div>

                    <div class="fabric-notice-body">
                        <span class="fabric-notice-kicker">Request received</span>
                        <h2 id="fabric-notice-title">{{ title }}</h2>
                        <p id="fabric-notice-message">{{ message }}</p>
                    </div>

                    <button type="button" class="fabric-notice-action" @click="close">{{ buttonText }}</button>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fabric-notice-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: grid;
    place-items: center;
    padding: 24px;
    background: rgb(15 35 65 / 58%);
    backdrop-filter: blur(5px);
}

.fabric-notice-card {
    width: min(100%, 360px);
    overflow: hidden;
    border: 1px solid rgb(255 255 255 / 75%);
    border-radius: 24px;
    background: #fff;
    box-shadow: 0 28px 90px rgb(15 35 65 / 28%);
}

.fabric-notice-art { height: 190px; background: #eaf4ff; }
.fabric-notice-art svg { display: block; width: 100%; height: 100%; }
.fabric-notice-body { padding: 24px 28px 22px; text-align: center; }
.fabric-notice-kicker {
    display: inline-flex;
    margin-bottom: 9px;
    color: #2873b5;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .16em;
    text-transform: uppercase;
}
.fabric-notice-body h2 { margin: 0 0 10px; color: #172b43; font-size: 20px; font-weight: 800; letter-spacing: -.02em; }
.fabric-notice-body p { margin: 0; color: #64748b; font-size: 13px; line-height: 1.65; }
.fabric-notice-action {
    display: block;
    width: calc(100% - 40px);
    margin: 0 20px 20px;
    padding: 12px 18px;
    border: 0;
    border-radius: 13px;
    background: linear-gradient(110deg, #2879bb, #4656b8);
    color: white;
    font: inherit;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    transition: filter .15s ease, transform .15s ease;
}
.fabric-notice-action:hover { filter: brightness(1.08); }
.fabric-notice-action:active { transform: scale(.98); }
.fabric-notice-action:focus-visible { outline: 3px solid #7db8f2; outline-offset: 3px; }

.fabric-notice-enter-active, .fabric-notice-leave-active { transition: opacity .2s ease; }
.fabric-notice-enter-active .fabric-notice-card, .fabric-notice-leave-active .fabric-notice-card { transition: transform .2s ease; }
.fabric-notice-enter-from, .fabric-notice-leave-to { opacity: 0; }
.fabric-notice-enter-from .fabric-notice-card, .fabric-notice-leave-to .fabric-notice-card { transform: translateY(12px) scale(.97); }

@media (prefers-reduced-motion: reduce) {
    .fabric-notice-enter-active, .fabric-notice-leave-active,
    .fabric-notice-enter-active .fabric-notice-card, .fabric-notice-leave-active .fabric-notice-card {
        transition: none;
    }
}
</style>
