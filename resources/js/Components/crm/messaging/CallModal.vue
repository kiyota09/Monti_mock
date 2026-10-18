<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import {
    Phone, Video, Mic, MicOff, VideoOff, PhoneOff, Loader2, Volume2,
    PhoneIncoming, PhoneOutgoing,
} from 'lucide-vue-next';
import { createWebRTC } from '@/composables/crm/useWebRTC';

const props = defineProps({
    type: { type: String, default: 'audio' }, // audio | video
    peerName: { type: String, default: 'Business Partner' },
    peerSubtitle: { type: String, default: '' },
    startedAt: { type: String, default: '' },
    phase: { type: String, default: 'active' }, // incoming | calling | active
    role: { type: String, default: 'caller' }, // caller | callee
    // A real WebRTC connection is established when `callId` plus both signalling
    // callbacks are supplied (internal team calls and partner calls). Without
    // them the modal is a local-preview-only fallback.
    callId: { type: [Number, String], default: null },
    sendSignal: { type: Function, default: null },
    fetchSignals: { type: Function, default: null },
});

const emit = defineEmits(['accept', 'decline', 'cancel', 'end', 'remote-end']);

const isVideo = computed(() => props.type === 'video');
const webrtcEnabled = computed(() => !!props.callId && typeof props.sendSignal === 'function' && typeof props.fetchSignals === 'function');

const localVideo = ref(null);
const remoteVideo = ref(null);
const remoteAudio = ref(null);

const stream = ref(null);
const mediaError = ref('');
const connecting = ref(false);
const hasRemote = ref(false);
const micOn = ref(true);
const camOn = ref(true);
const elapsed = ref(0);
const remoteState = ref('');

let timer = null;
let ended = false;
let activeStarted = false;
let mediaStarted = false;
let webrtc = null;
let startTs = Date.now();

const tick = () => {
    elapsed.value = Math.max(0, Math.floor((Date.now() - startTs) / 1000));
};

const formatDuration = (total) => {
    const s = Math.max(0, Math.floor(total || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    const mm = String(m).padStart(2, '0');
    const ss = String(sec).padStart(2, '0');
    return h > 0 ? `${h}:${mm}:${ss}` : `${mm}:${ss}`;
};

const statusText = computed(() => {
    if (props.phase === 'incoming') return `Incoming ${isVideo.value ? 'video' : 'voice'} call`;
    if (props.phase === 'calling') return 'Ringing…';
    return formatDuration(elapsed.value);
});

const stopTracks = () => {
    stream.value?.getTracks?.().forEach((t) => t.stop());
    stream.value = null;
};

const cleanup = () => {
    if (timer) clearInterval(timer);
    timer = null;
    stopTracks();
    webrtc?.close();
    webrtc = null;
};

const hangUp = (status) => {
    if (ended) return;
    ended = true;
    cleanup();
    emit('end', { duration: elapsed.value, status: status || (mediaError.value ? 'failed' : 'completed') });
};

const remoteEnd = () => {
    if (ended) return;
    ended = true;
    cleanup();
    emit('remote-end');
};

const startMedia = async () => {
    if (mediaStarted) return;
    mediaStarted = true;
    connecting.value = true;

    if (!navigator.mediaDevices?.getUserMedia) {
        connecting.value = false;
        mediaError.value = 'This device/browser does not expose a microphone or camera (a secure https/localhost context is required).';
        return;
    }

    try {
        const constraints = isVideo.value
            ? { audio: true, video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } } }
            : { audio: true, video: false };

        stream.value = await navigator.mediaDevices.getUserMedia(constraints);
        connecting.value = false;

        if (isVideo.value) {
            await nextTick();
            if (localVideo.value) {
                localVideo.value.srcObject = stream.value;
                await localVideo.value.play().catch(() => {});
            }
        }
    } catch (e) {
        connecting.value = false;
        mediaError.value = e?.name === 'NotAllowedError'
            ? 'Permission denied. Allow microphone/camera access to continue.'
            : (e?.message || 'Could not access your microphone/camera.');
    }
};

const attachRemote = async (remote) => {
    await nextTick();
    hasRemote.value = true;
    if (isVideo.value) {
        if (remoteVideo.value) {
            remoteVideo.value.srcObject = remote;
            await remoteVideo.value.play().catch(() => {});
        }
    } else if (remoteAudio.value) {
        remoteAudio.value.srcObject = remote;
        await remoteAudio.value.play().catch(() => {});
    }
};

const setupWebRTC = async () => {
    if (!webrtcEnabled.value) {
        await startMedia();
        return;
    }

    await startMedia();

    webrtc = createWebRTC({
        role: props.role,
        sendSignal: (kind, payload) => props.sendSignal(kind, payload),
        fetchSignals: (after) => props.fetchSignals(after),
        onRemoteStream: (remote) => { attachRemote(remote); },
        onConnectionChange: (state) => {
            remoteState.value = state;
            if (state === 'failed') hangUp('failed');
        },
        onRemoteStatus: (status) => {
            if (['completed', 'declined', 'missed', 'failed'].includes(status)) remoteEnd();
        },
    });

    webrtc.init(stream.value);
    if (props.role === 'caller') await webrtc.start();
    webrtc.startPolling();
};

const beginActive = () => {
    if (activeStarted) return;
    activeStarted = true;
    startTs = props.startedAt ? new Date(props.startedAt).getTime() : Date.now();
    tick();
    if (timer) clearInterval(timer);
    timer = setInterval(tick, 1000);
    setupWebRTC();
};

const toggleMic = () => {
    micOn.value = !micOn.value;
    stream.value?.getAudioTracks?.().forEach((t) => { t.enabled = micOn.value; });
};

const toggleCam = () => {
    camOn.value = !camOn.value;
    stream.value?.getVideoTracks?.().forEach((t) => { t.enabled = camOn.value; });
};

watch(() => props.phase, (phase) => {
    if (phase === 'active') beginActive();
}, { immediate: true });

onMounted(() => {
    if (props.phase === 'active') beginActive();
});

onUnmounted(() => {
    cleanup();
});
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/80 p-3 backdrop-blur">
            <div class="flex w-full max-w-lg flex-col overflow-hidden rounded-3xl bg-gradient-to-b from-zinc-900 to-zinc-950 text-white shadow-2xl ring-1 ring-white/10">
                <!-- Header -->
                <div class="flex items-center gap-3 border-b border-white/10 px-5 py-4">
                    <div class="flex h-11 w-11 flex-none items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/15">
                        <Video v-if="isVideo" class="h-5 w-5" />
                        <Phone v-else class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-black">{{ peerName }}</p>
                        <p class="truncate text-xs text-zinc-400">{{ peerSubtitle || (isVideo ? 'Video call' : 'Voice call') }}</p>
                    </div>
                    <span :class="phase === 'active' ? 'bg-emerald-500/15 text-emerald-300 ring-emerald-400/30' : 'bg-amber-500/15 text-amber-300 ring-amber-400/30'"
                        class="flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold ring-1">
                        <span :class="phase === 'active' ? 'bg-emerald-400' : 'bg-amber-400'" class="h-1.5 w-1.5 animate-pulse rounded-full" />
                        {{ statusText }}
                    </span>
                </div>

                <!-- Stage -->
                <div class="relative flex min-h-[320px] flex-1 items-center justify-center bg-black/60">
                    <!-- Remote video (video calls) -->
                    <video
                        v-show="phase === 'active' && isVideo && hasRemote"
                        ref="remoteVideo"
                        autoplay
                        playsinline
                        class="h-[320px] w-full object-cover sm:h-[380px]"
                    />

                    <!-- Remote audio (audio calls) -->
                    <audio ref="remoteAudio" autoplay></audio>

                    <!-- Overlay: incoming / calling / waiting for media -->
                    <div v-if="phase !== 'active' || !hasRemote || (!isVideo && phase === 'active')" class="absolute inset-0 flex flex-col items-center justify-center gap-3 p-6 text-center">
                        <div class="relative flex h-28 w-28 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 text-4xl font-black shadow-xl">
                            {{ (peerName || '?').charAt(0).toUpperCase() }}
                            <span v-if="phase !== 'active' || !hasRemote" class="absolute inset-0 animate-ping rounded-full bg-indigo-500/40" />
                        </div>
                        <p class="max-w-xs text-sm text-zinc-300">
                            <template v-if="phase === 'incoming'">{{ statusText }}</template>
                            <template v-else-if="phase === 'calling'">Calling {{ peerName }}…</template>
                            <template v-else-if="mediaError">{{ mediaError }}</template>
                            <template v-else-if="isVideo === false && hasRemote">Connected · voice call in progress</template>
                            <template v-else>Connecting{{ peerName ? ' to ' + peerName : '' }}…</template>
                        </p>
                        <Loader2 v-if="connecting || (phase === 'active' && !hasRemote && !mediaError)" class="h-5 w-5 animate-spin text-indigo-300" />
                    </div>

                    <!-- Local preview PiP (video calls) -->
                    <div v-show="phase === 'active' && isVideo" class="absolute bottom-3 right-3 h-28 w-20 overflow-hidden rounded-xl ring-2 ring-white/30 shadow-lg sm:h-32 sm:w-24">
                        <video ref="localVideo" autoplay muted playsinline class="h-full w-full object-cover" :class="camOn ? '' : 'opacity-0 bg-zinc-800'" />
                        <span v-if="!camOn" class="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-zinc-300">Cam off</span>
                    </div>
                </div>

                <!-- Controls -->
                <div class="flex items-center justify-center gap-3 border-t border-white/10 px-5 py-5">
                    <!-- Incoming -->
                    <template v-if="phase === 'incoming'">
                        <button type="button" @click="emit('decline')"
                            class="flex h-14 flex-1 max-w-[8rem] items-center justify-center gap-2 rounded-full bg-rose-600 font-bold text-white shadow-lg transition hover:bg-rose-700">
                            <PhoneOff class="h-5 w-5" /> Decline
                        </button>
                        <button type="button" @click="emit('accept')"
                            class="flex h-14 flex-1 max-w-[8rem] items-center justify-center gap-2 rounded-full bg-emerald-600 font-bold text-white shadow-lg transition hover:bg-emerald-700">
                            <PhoneIncoming class="h-5 w-5" /> Accept
                        </button>
                    </template>

                    <!-- Calling (caller waiting) -->
                    <template v-else-if="phase === 'calling'">
                        <button type="button" @click="emit('cancel')"
                            class="flex h-14 items-center justify-center gap-2 rounded-full bg-rose-600 px-6 font-bold text-white shadow-lg transition hover:bg-rose-700">
                            <PhoneOff class="h-5 w-5" /> Cancel
                        </button>
                    </template>

                    <!-- Active -->
                    <template v-else>
                        <button type="button" @click="toggleMic"
                            :class="micOn ? 'bg-white/10 hover:bg-white/20' : 'bg-rose-500/90 hover:bg-rose-500'"
                            class="flex h-12 w-12 items-center justify-center rounded-full text-white transition">
                            <Mic v-if="micOn" class="h-5 w-5" />
                            <MicOff v-else class="h-5 w-5" />
                        </button>

                        <button type="button" v-if="isVideo" @click="toggleCam"
                            :class="camOn ? 'bg-white/10 hover:bg-white/20' : 'bg-rose-500/90 hover:bg-rose-500'"
                            class="flex h-12 w-12 items-center justify-center rounded-full text-white transition">
                            <Video v-if="camOn" class="h-5 w-5" />
                            <VideoOff v-else class="h-5 w-5" />
                        </button>

                        <button type="button" v-if="!isVideo" class="flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white/70">
                            <Volume2 class="h-5 w-5" />
                        </button>

                        <button type="button" @click="hangUp(mediaError ? 'failed' : 'completed')"
                            class="flex h-14 w-14 items-center justify-center rounded-full bg-rose-600 text-white shadow-lg transition hover:bg-rose-700">
                            <PhoneOff class="h-6 w-6" />
                        </button>
                    </template>
                </div>

                <p class="pb-4 text-center text-[11px] text-zinc-500">
                    <template v-if="role === 'callee'"><PhoneIncoming class="inline h-3 w-3" /> Incoming · </template>
                    <template v-else><PhoneOutgoing class="inline h-3 w-3" /> Outgoing · </template>
                    <template v-if="webrtcEnabled">Live peer-to-peer {{ isVideo ? 'video' : 'audio' }} · duration logged to CRM</template>
                    <template v-else>Local preview · duration logged to CRM</template>
                </p>
            </div>
        </div>
    </Teleport>
</template>
