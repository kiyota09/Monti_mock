<template>
    <div class="space-y-2">
        <div class="relative rounded-2xl border-2 border-dashed border-gray-300 bg-white dark:border-zinc-700 dark:bg-zinc-950 overflow-hidden">
            <canvas
                ref="canvas"
                class="block w-full touch-none cursor-crosshair"
                style="height: 160px;"
                @mousedown="start"
                @mousemove="draw"
                @mouseup="end"
                @mouseleave="end"
                @touchstart.prevent="start"
                @touchmove.prevent="draw"
                @touchend.prevent="end"
            />
            <span v-if="!hasInk" class="pointer-events-none absolute inset-0 flex items-center justify-center text-xs font-bold uppercase tracking-widest text-gray-300 dark:text-zinc-600">
                Sign here
            </span>
        </div>
        <div class="flex items-center justify-between">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Recipient / Driver signature</p>
            <button type="button" @click="clear" class="text-[10px] font-black uppercase tracking-wider text-rose-500 hover:text-rose-600">
                Clear
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';

const emit = defineEmits(['update:modelValue']);

const canvas = ref(null);
const hasInk = ref(false);
let ctx = null;
let drawing = false;

const position = (event) => {
    const rect = canvas.value.getBoundingClientRect();
    const point = event.touches ? event.touches[0] : event;
    return {
        x: (point.clientX - rect.left) * (canvas.value.width / rect.width),
        y: (point.clientY - rect.top) * (canvas.value.height / rect.height),
    };
};

const start = (event) => {
    drawing = true;
    const { x, y } = position(event);
    ctx.beginPath();
    ctx.moveTo(x, y);
};

const draw = (event) => {
    if (!drawing) return;
    const { x, y } = position(event);
    ctx.lineWidth = 4;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#111827';
    ctx.lineTo(x, y);
    ctx.stroke();
    hasInk.value = true;
};

const end = () => {
    if (!drawing) return;
    drawing = false;
    ctx.closePath();
    emit('update:modelValue', canvas.value.toDataURL('image/png'));
};

const clear = () => {
    ctx.clearRect(0, 0, canvas.value.width, canvas.value.height);
    hasInk.value = false;
    emit('update:modelValue', '');
};

const resize = () => {
    if (!canvas.value || !ctx) return;
    const ratio = window.devicePixelRatio || 1;
    const rect = canvas.value.getBoundingClientRect();
    canvas.value.width = rect.width * ratio;
    canvas.value.height = 160 * ratio;
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
};

const onResize = () => {
    if (!canvas.value || !ctx) return;
    if (!hasInk.value) {
        resize();
        return;
    }
    const data = canvas.value.toDataURL();
    resize();
    const img = new Image();
    img.onload = () => {
        const rect = canvas.value.getBoundingClientRect();
        ctx.clearRect(0, 0, rect.width, rect.height);
        ctx.drawImage(img, 0, 0, rect.width, rect.height);
    };
    img.src = data;
};

onMounted(() => {
    ctx = canvas.value.getContext('2d');
    resize();
    window.addEventListener('resize', onResize);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
});
</script>
