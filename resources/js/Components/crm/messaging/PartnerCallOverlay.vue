<script setup>
import { onMounted, onUnmounted } from 'vue';
import { usePartnerCallCenter } from '@/composables/crm/usePartnerCallCenter';
import { createRingtone } from '@/composables/crm/useRingtone';
import CallModal from '@/Components/crm/messaging/CallModal.vue';
import { Phone, Video, PhoneIncoming, PhoneOff, Bell } from 'lucide-vue-next';

const pc = usePartnerCallCenter();
const ringtone = createRingtone();

const resumeAudio = () => { ringtone.resume(); };

onMounted(() => {
    pc.setRingtone(ringtone.play);
    pc.init();
    window.addEventListener('pointerdown', resumeAudio);
});

onUnmounted(() => {
    pc.setRingtone(null);
    ringtone.dispose();
    window.removeEventListener('pointerdown', resumeAudio);
    pc.dispose();
});
</script>

<template>
    <div v-if="pc.state.context">
        <!-- Floating incoming partner call (visible on any page) -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-4 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="translate-y-4 opacity-0">
            <div v-if="pc.state.incoming && !pc.state.call"
                class="fixed bottom-5 right-5 z-[65] w-72 overflow-hidden rounded-2xl border border-emerald-400/30 bg-gradient-to-br from-emerald-600 to-teal-700 text-white shadow-2xl shadow-emerald-900/30">
                <div class="flex items-center gap-3 px-4 py-3">
                    <span class="relative flex h-11 w-11 flex-none items-center justify-center rounded-full bg-white/15 ring-1 ring-white/25">
                        <span class="absolute inset-0 animate-ping rounded-full bg-emerald-300/40" />
                        <PhoneIncoming class="h-5 w-5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-black">{{ pc.state.incoming.peerLabel }}</span>
                        <span class="flex items-center gap-1 text-[11px] font-semibold text-emerald-100">
                            <Video v-if="pc.state.incoming.type === 'video'" class="h-3 w-3" />
                            <Phone v-else class="h-3 w-3" />
                            Incoming {{ pc.state.incoming.type === 'video' ? 'video' : 'voice' }} call…
                        </span>
                    </span>
                    <Bell class="h-4 w-4 animate-bounce" />
                </div>
                <div class="flex items-center gap-2 border-t border-white/15 px-3 py-2">
                    <button type="button" @click="pc.declineIncoming()"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-rose-600 py-2 text-xs font-bold hover:bg-rose-700">
                        <PhoneOff class="h-4 w-4" /> Decline
                    </button>
                    <button type="button" @click="pc.acceptIncoming()"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-white py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-50">
                        <Phone class="h-4 w-4" /> Answer
                    </button>
                </div>
            </div>
        </Transition>

        <!-- Active / outgoing partner call -->
        <CallModal v-if="pc.state.call"
            :call-id="pc.state.call.message.id"
            :type="pc.state.call.type"
            :peer-name="pc.state.call.peerLabel"
            :peer-subtitle="pc.state.call.type === 'video' ? 'Video call' : 'Voice call'"
            :phase="pc.state.call.phase"
            :role="pc.state.call.role"
            :started-at="pc.state.call.startedAt || ''"
            :send-signal="pc.sendSignal"
            :fetch-signals="pc.fetchSignals"
            @cancel="pc.cancelOutgoing()"
            @end="pc.hangUp($event)"
            @remote-end="pc.dismiss()" />

        <!-- Notice -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="translate-y-2 opacity-0">
            <div v-if="pc.state.notice"
                class="fixed bottom-5 left-1/2 z-[66] -translate-x-1/2 rounded-full bg-zinc-900 px-5 py-2.5 text-sm font-semibold text-white shadow-xl ring-1 ring-white/10">
                {{ pc.state.notice }}
            </div>
        </Transition>
    </div>
</template>
