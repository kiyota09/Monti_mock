<script setup>
defineProps({
    kind: { type: String, default: 'success' },
    label: { type: String, default: 'Fabric swatches' },
});
</script>

<template>
    <div class="fabric-modal-art">
        <svg viewBox="0 0 320 190" role="img" :aria-label="label">
            <defs>
                <linearGradient id="fabric-modal-bg" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#eaf4ff" />
                    <stop offset="1" stop-color="#dce9ff" />
                </linearGradient>
                <linearGradient id="fabric-modal-top" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#7db8f2" />
                    <stop offset="1" stop-color="#397fc4" />
                </linearGradient>
                <pattern id="fabric-modal-weave" width="8" height="8" patternUnits="userSpaceOnUse">
                    <path d="M0 2H8M0 6H8" stroke="#fff" stroke-opacity=".19" stroke-width="1" />
                    <path d="M2 0V8M6 0V8" stroke="#174e86" stroke-opacity=".10" stroke-width="1" />
                </pattern>
            </defs>
            <rect width="320" height="190" fill="url(#fabric-modal-bg)" />
            <circle cx="48" cy="40" r="18" fill="#fff" opacity=".55" />
            <circle cx="272" cy="52" r="27" fill="#fff" opacity=".45" />
            <path d="M0 151Q70 130 143 151T320 145V190H0Z" fill="#c6dcf7" />
            <path d="m70 125 42-47 142 10-39 58-145-4Z" fill="#28669f" />
            <path d="m70 125 42-47 18 2-35 49Z" fill="#4b8fc9" />
            <path d="m95 129 36-49 142 10-41 56Z" fill="url(#fabric-modal-top)" />
            <path d="m95 129 36-49 142 10-41 56Z" fill="url(#fabric-modal-weave)" />
            <path d="m113 142 38-47 122 9-36 50Z" fill="#87bde9" />
            <path d="m113 142 38-47 122 9-36 50Z" fill="url(#fabric-modal-weave)" opacity=".8" />
            <path d="m95 129 137 17" stroke="#e9f5ff" stroke-width="2" stroke-dasharray="3 5" />
            <g transform="translate(47 103) rotate(-12)">
                <rect x="5" y="7" width="27" height="44" rx="5" fill="#f3b55d" />
                <path d="M2 6q0-5 4-5h25q4 0 4 5v4H2Zm0 42h33v4q0 5-4 5H6q-4 0-4-5Z" fill="#e69b3e" />
                <path d="M8 14h21M8 20h21M8 26h21M8 32h21M8 38h21M8 44h21" stroke="#fff4dd" stroke-width="2" />
            </g>
            <circle cx="247" cy="54" r="24" fill="#fff" />
            <circle cx="247" cy="54" r="19" :fill="kind === 'success' ? '#23966a' : '#397fc4'" />
            <path v-if="kind === 'success'" d="m238 54 6 6 12-13" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
            <path v-else d="M247 44v12m0 6h.01" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" />
            <path d="m85 48 3 7 7 3-7 3-3 7-3-7-7-3 7-3Zm177 60 2 5 5 2-5 2-2 5-2-5-5-2 5-2Z" fill="#fff" opacity=".9" />
        </svg>
    </div>
</template>

<style scoped>
.fabric-modal-art { height: 160px; background: #eaf4ff; }
.fabric-modal-art svg { display: block; width: 100%; height: 100%; }
@media (max-width: 420px) { .fabric-modal-art { height: 145px; } }
</style>
