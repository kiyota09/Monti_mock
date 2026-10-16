import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

/**
 * Real-time conversation via lightweight polling (works on plain XAMPP —
 * no websockets / Pusher / Reverb required).
 *
 * - polls `feedUrl?after=<lastId>` every `pollInterval` ms and appends
 *   only new messages → no page reload needed on either side
 * - exposes `notifyTyping()` — call (debounced) from the message input;
 *   the other side's `otherTyping` flag flips to true while they type
 * - `sendMessage(formData)` POSTs via axios and appends the returned
 *   message instantly (optimistic, no Inertia reload)
 *
 * @param {() => Array} getMessages  getter returning the reactive message list
 * @param {(msgs: Array) => void} setMessages setter (e.g. push into prop copy)
 * @param {string} feedUrl   GET endpoint returning { messages, typing }
 * @param {string} typingUrl POST heartbeat endpoint
 * @param {string} sendUrl   POST endpoint returning { message }
 * @param {() => void} onNewMessages  called after new rows arrive (e.g. scroll)
 */
export function useRealtimeConversation({
    getMessages,
    appendMessages,
    feedUrl,
    typingUrl,
    sendUrl,
    onNewMessages = () => {},
    pollInterval = 2500,
}) {
    const otherTyping = ref(false);
    const sending = ref(false);
    const connected = ref(true);

    let pollTimer = null;
    let typingTimer = null;
    let lastTypingSent = 0;
    let stopped = false;

    const lastId = () => {
        const list = getMessages() || [];
        if (!list.length) return 0;
        return Math.max(...list.map((m) => Number(m.id) || 0), 0);
    };

    const poll = async () => {
        if (stopped || document.hidden) return;
        try {
            const { data } = await axios.get(feedUrl, {
                params: { after: lastId() },
            });
            otherTyping.value = !!data.typing;
            if (Array.isArray(data.messages) && data.messages.length) {
                appendMessages(data.messages);
                onNewMessages(data.messages);
            }
            connected.value = true;
        } catch (e) {
            connected.value = false;
        }
    };

    /** Fire a typing heartbeat, throttled to at most once per 2s. */
    const notifyTyping = () => {
        if (!typingUrl) return;
        const now = Date.now();
        if (now - lastTypingSent < 2000) return;
        lastTypingSent = now;
        axios.post(typingUrl).catch(() => {});
    };

    /**
     * Send a chat message (text + optional files) without reload.
     * Returns the created message, or null on failure.
     */
    const sendMessage = async ({ text = '', files = [] } = {}) => {
        if ((!text || !text.trim()) && !files.length) return null;
        sending.value = true;
        try {
            const fd = new FormData();
            fd.append('message', text || '');
            files.forEach((f, i) => fd.append(`files[${i}]`, f));
            const { data } = await axios.post(sendUrl, fd, {
                headers: { Accept: 'application/json' },
            });
            if (data?.message) appendMessages([data.message]);
            onNewMessages(data?.message ? [data.message] : []);
            return data?.message ?? null;
        } finally {
            sending.value = false;
        }
    };

    onMounted(() => {
        stopped = false;
        // Small initial delay so first paint isn't blocked, then poll.
        typingTimer = null;
        pollTimer = setInterval(poll, pollInterval);
        // Fetch immediately (catch-up) shortly after mount.
        setTimeout(() => poll(), 600);
        const onVis = () => {
            if (!document.hidden) poll();
        };
        document.addEventListener('visibilitychange', onVis);
        typingTimer = onVis; // reuse slot for cleanup ref
    });

    onUnmounted(() => {
        stopped = true;
        if (pollTimer) clearInterval(pollTimer);
        if (typingTimer && typeof typingTimer === 'function') {
            document.removeEventListener('visibilitychange', typingTimer);
        }
    });

    return { otherTyping, sending, connected, poll, notifyTyping, sendMessage };
}
