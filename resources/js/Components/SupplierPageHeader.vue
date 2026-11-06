<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { Building2 } from 'lucide-vue-next';

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    supplierName: { type: String, default: 'Supplier' },
    icon: { type: [Object, Function], default: () => Building2 },
});

const { currentTheme } = useTheme();
const activeTheme = ref(currentTheme.value);
let themeObserver;

const palettes = {
    light: { background: 'linear-gradient(135deg, #f8fafc 0%, #e8eef5 58%, #dbeafe 100%)', ink: '#0f172a', accent: '#334155', subtitle: '#475569', glow: 'rgba(59,130,246,.14)', iconBg: 'rgba(255,255,255,.78)', iconBorder: 'rgba(15,23,42,.12)', iconInk: '#334155' },
    dark: { background: 'linear-gradient(135deg, #111827 0%, #1e293b 58%, #334155 100%)', ink: '#f8fafc', accent: '#cbd5e1', subtitle: '#e2e8f0', glow: 'rgba(148,163,184,.16)', iconBg: 'rgba(255,255,255,.12)', iconBorder: 'rgba(255,255,255,.28)', iconInk: '#fff' },
    blue: { background: 'linear-gradient(135deg, #12365b 0%, #1d4e89 55%, #1e40af 100%)', ink: '#fff', accent: '#dbeafe', subtitle: '#eff6ff', glow: 'rgba(147,197,253,.20)', iconBg: 'rgba(255,255,255,.15)', iconBorder: 'rgba(255,255,255,.3)', iconInk: '#fff' },
    red: { background: 'linear-gradient(135deg, #641b27 0%, #9f233d 55%, #881337 100%)', ink: '#fff', accent: '#ffe4e6', subtitle: '#fff1f2', glow: 'rgba(253,164,175,.18)', iconBg: 'rgba(255,255,255,.15)', iconBorder: 'rgba(255,255,255,.3)', iconInk: '#fff' },
    green: { background: 'linear-gradient(135deg, #1b4332 0%, #2d6a4f 55%, #40916c 100%)', ink: '#fff', accent: '#d8f3dc', subtitle: '#f0fff4', glow: 'rgba(183,228,199,.22)', iconBg: 'rgba(255,255,255,.15)', iconBorder: 'rgba(255,255,255,.3)', iconInk: '#fff' },
};

const palette = computed(() => palettes[activeTheme.value] || palettes.light);
const bannerStyle = computed(() => ({
    '--supplier-page-header-background': palette.value.background,
    '--supplier-page-header-ink': palette.value.ink,
}));
const syncTheme = () => {
    activeTheme.value = document.documentElement.getAttribute('data-theme') || currentTheme.value || 'light';
};

watch(currentTheme, (theme) => { activeTheme.value = theme; });

onMounted(() => {
    syncTheme();
    themeObserver = new MutationObserver(syncTheme);
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
});

onUnmounted(() => themeObserver?.disconnect());
</script>

<template>
    <header :data-theme="activeTheme" :style="bannerStyle" class="supplier-page-header relative isolate overflow-hidden rounded-3xl p-5 shadow-xl sm:p-7">
        <div class="pointer-events-none absolute -right-16 -top-20 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
        <div class="pointer-events-none absolute -bottom-24 -left-10 h-64 w-64 rounded-full blur-3xl" :style="{ backgroundColor: palette.glow }" />
        <div class="relative flex flex-wrap items-center gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl shadow-lg ring-1 backdrop-blur sm:h-14 sm:w-14" :style="{ backgroundColor: palette.iconBg, borderColor: palette.iconBorder, color: palette.iconInk }">
                <component :is="props.icon" class="h-6 w-6 sm:h-7 sm:w-7" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="flex items-center gap-1.5 truncate text-[11px] font-bold uppercase tracking-[0.18em]" :style="{ color: palette.accent }">
                    <Building2 class="h-3.5 w-3.5 shrink-0" /> {{ props.supplierName }}
                </p>
                <h1 class="supplier-page-header-title mt-1 text-2xl font-black tracking-tight sm:text-3xl">{{ props.title }}</h1>
                <p v-if="props.subtitle" class="mt-1 text-sm" :style="{ color: palette.subtitle }">{{ props.subtitle }}</p>
            </div>
            <div v-if="$slots.aside" class="supplier-page-header-aside relative flex shrink-0 items-center gap-2">
                <slot name="aside" />
            </div>
        </div>
        <div v-if="$slots.filters" class="relative mt-6">
            <slot name="filters" />
        </div>
    </header>
</template>
