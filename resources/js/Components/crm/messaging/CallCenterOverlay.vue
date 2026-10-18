<script setup>
import { onMounted, onUnmounted } from 'vue';
import { useCallCenter } from '@/composables/crm/useCallCenter';
import { createRingtone } from '@/composables/crm/useRingtone';
import CallModal from '@/Components/crm/messaging/CallModal.vue';
import { Phone, Video, PhoneIncoming, PhoneOff, Bell } from 'lucide-vue-next';

const { state, init, accept, decline, cancel, end, dismiss, openIncoming, postSignal, fetchSignals, setRingtone } = useCallCenter();

const ringtone = createRingtone();

const resumeAudio = () => { ringtone.resume(); };

onMounted(() => {
    setRingtone(ringtone.play);
    init();
    window.addEventListener('pointerdown', resumeAudio);
});

onUnmounted(() => {
    setRingtone(null);
    ringtone.dispose();
    window.removeEventListener('pointerdown', resumeAudio);
});

const peerName = (call) => (state.active?.role === 'caller' ? call?.callee?.name : call?.caller?.name) || 'Monti User';

const sendActiveSignal = (kind, payload) => postSignal(state.active?.call?.id, kind, payload);
const fetchActiveSignals = (after) => fetchSignals(state.active?.call?.id, after);
</script>

<template>
    <div>
        <!-- Incoming floating call button (any page in the dashboard) -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-4 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="translate-y-4 opacity-0"
        >
            <div v-if="state.incoming && !state.active"
                class="fixed bottom-5 right-5 z-[65] w-72 overflow-hidden rounded-2xl border border-emerald-400/30 bg-gradient-to-br from-emerald-600 to-teal-700 text-white shadow-2xl shadow-emerald-900/30">
                <button type="button" @click="openIncoming" class="flex w-full items-center gap-3 px-4 py-3 text-left">
                    <span class="relative flex h-11 w-11 flex-none items-center justify-center rounded-full bg-white/15 ring-1 ring-white/25">
                        <span class="absolute inset-0 animate-ping rounded-full bg-emerald-300/40" />
                        <PhoneIncoming class="h-5 w-5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-black">{{ state.incoming.caller?.name || 'Monti User' }}</span>
                        <span class="flex items-center gap-1 text-[11px] font-semibold text-emerald-100">
                            <Video v-if="state.incoming.type === 'video'" class="h-3 w-3" />
                            <Phone v-else class="h-3 w-3" />
                            Incoming {{ state.incoming.type === 'video' ? 'video' : 'voice' }} call…
                        </span>
                    </span>
                    <span class="flex h-7 w-7 animate-bounce items-center justify-center rounded-full bg-white/20">
                        <Bell class="h-4 w-4" />
                    </span>
                </button>
                <div class="flex items-center gap-2 border-t border-white/15 px-3 py-2">
                    <button type="button" @click="decline"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-rose-600 py-2 text-xs font-bold hover:bg-rose-700">
                        <PhoneOff class="h-4 w-4" /> Decline
                    </button>
                    <button type="button" @click="accept"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-white py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-50">
                        <Phone class="h-4 w-4" /> Answer
                    </button>
                </div>
            </div>
        </Transition>

        <!-- Incoming answer/decline modal (opened from the floating button) -->
        <CallModal v-if="state.incoming && state.showIncoming && !state.active"
            :type="state.incoming.type"
            :peer-name="state.incoming.caller?.name || 'Monti User'"
            :peer-subtitle="state.incoming.type === 'video' ? 'Incoming video call' : 'Incoming voice call'"
            phase="incoming"
            role="callee"
            @accept="accept"
            @decline="decline" />

        <!-- Active / outgoing call modal -->
        <CallModal v-if="state.active"
            :call-id="state.active.call.id"
            :type="state.active.call.type"
            :peer-name="peerName(state.active.call)"
            :peer-subtitle="state.active.call.type === 'video' ? 'Video call' : 'Voice call'"
            :phase="state.active.phase"
            :role="state.active.role"
            :started-at="state.active.startedAt || ''"
            :send-signal="sendActiveSignal"
            :fetch-signals="fetchActiveSignals"
            @cancel="cancel"
            @end="end"
            @remote-end="dismiss" />

        <!-- Transient notice -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="translate-y-2 opacity-0"
        >
            <div v-if="state.notice"
                class="fixed bottom-5 left-1/2 z-[66] -translate-x-1/2 rounded-full bg-zinc-900 px-5 py-2.5 text-sm font-semibold text-white shadow-xl ring-1 ring-white/10">
                {{ state.notice }}
            </div>
        </Transition>
    </div>
</template>
