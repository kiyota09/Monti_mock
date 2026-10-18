import { reactive } from 'vue';
import { route } from 'ziggy-js';

/**
 * Partner (CRM ↔ client) call centre — a module singleton so a ringing call is
 * surfaced from ANY page, not just the thread that started it.
 *
 * Context is derived from the URL: staff pages (`/dashboard…`) ring for any
 * partner call and answer via the CRM routes; portal pages (`/partner…`) ring
 * for that partner's own calls and answer via the client routes. The WebRTC
 * offer/answer/ICE relay runs over the per-call signalling endpoints.
 */
const state = reactive({
    context: null,  // 'crm' | 'client' | null
    incoming: null, // { id, type, created_at, peerLabel, clientKey }
    call: null,     // { message, type, role, phase, startedAt, peerLabel, clientKey }
    notice: null,
    ready: false,
});

let started = false;
let pollTimer = null;
let statusPoll = null;
let noticeTimer = null;
let ringHandler = null;

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const request = async (url, method = 'POST', body) => {
    const res = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(method === 'GET' ? {} : { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() }),
        },
        credentials: 'same-origin',
        body: method === 'GET' ? undefined : JSON.stringify(body ?? {}),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const error = new Error(data.message ?? `Request failed (HTTP ${res.status})`);
        error.status = res.status;
        throw error;
    }
    return data;
};

const ring = (kind) => ringHandler?.(kind);

const detectContext = () => {
    if (typeof window === 'undefined') return null;
    const path = window.location.pathname;
    if (path.includes('/dashboard')) return 'crm';
    if (path.includes('/partner') || path.includes('/client')) return 'client';
    return null;
};

const callUrls = (clientKey) => (state.context === 'client'
    ? {
        start: () => route('client.messages.calls.start'),
        answer: (id) => route('client.messages.calls.answer', id),
        end: (id) => route('client.messages.calls.end', id),
        signal: (id) => route('client.messages.calls.signal', id),
        signals: (id) => route('client.messages.calls.signals', id),
    }
    : {
        start: () => route('crm.messages.calls.start', clientKey),
        answer: (id) => route('crm.messages.calls.answer', { client: clientKey, message: id }),
        end: (id) => route('crm.messages.calls.end', { client: clientKey, message: id }),
        signal: (id) => route('crm.messages.calls.signal', { client: clientKey, message: id }),
        signals: (id) => route('crm.messages.calls.signals', { client: clientKey, message: id }),
    });

const setNotice = (message) => {
    state.notice = message;
    if (noticeTimer) clearTimeout(noticeTimer);
    noticeTimer = setTimeout(() => { state.notice = null; }, 4000);
};

const statusLabel = (status) => ({
    declined: 'Call declined',
    missed: 'No answer',
    failed: 'Call failed',
    completed: 'Call ended',
}[status] ?? 'Call ended');

const stopStatusPoll = () => {
    if (statusPoll) clearInterval(statusPoll);
    statusPoll = null;
};

const normalizeIncoming = (call) => ({
    id: call.id,
    type: call.type,
    created_at: call.created_at,
    peerLabel: state.context === 'client' ? 'Monti Textiles' : (call.client_name || 'Business Partner'),
    clientKey: call.client_hash_key ?? null,
});

const poll = async () => {
    if (!state.context) return;
    const url = state.context === 'client'
        ? route('client.messages.incoming-calls')
        : route('crm.messages.incoming-calls');
    try {
        const data = await request(url, 'GET');
        const ringing = (data.calls ?? [])[0] ?? null;
        if (state.call) return;
        if (ringing) {
            if (!state.incoming || state.incoming.id !== ringing.id) {
                state.incoming = normalizeIncoming(ringing);
                ring('incoming');
            }
        } else if (state.incoming) {
            state.incoming = null;
            ring(false);
        }
    } catch (error) {
        if (error.status === 401 || error.status === 403) {
            state.context = null;
            stopPolling();
        }
    }
};

const stopPolling = () => {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = null;
};

const watchForAnswer = (urls, messageId) => {
    stopStatusPoll();
    statusPoll = setInterval(async () => {
        if (!state.call || state.call.role !== 'caller') {
            stopStatusPoll();
            return;
        }
        try {
            const data = await request(`${urls.signals(messageId)}?after=0`, 'GET');
            const status = data.message?.call?.status;
            if (status === 'answered') {
                ring(false);
                stopStatusPoll();
                state.call.phase = 'active';
            } else if (['completed', 'declined', 'missed', 'failed'].includes(status)) {
                ring(false);
                stopStatusPoll();
                setNotice(statusLabel(status));
                state.call = null;
            }
        } catch (e) {
            /* retry next tick */
        }
    }, 1500);
};

// --- Actions ---------------------------------------------------------------

const startOutgoing = async ({ type, clientKey = null, peerLabel = null }) => {
    if (!state.context) init();
    if (!state.context || state.call) return null;
    state.incoming = null;
    const urls = callUrls(clientKey);
    try {
        const data = await request(urls.start(), 'POST', { type });
        state.call = {
            message: data.message,
            type,
            role: 'caller',
            phase: 'calling',
            startedAt: null,
            clientKey,
            peerLabel: peerLabel || (state.context === 'client' ? 'Monti Textiles' : 'Business Partner'),
        };
        ring('ringback');
        watchForAnswer(urls, data.message.id);
        return data.message;
    } catch (error) {
        setNotice(error.message);
        return null;
    }
};

const acceptIncoming = async () => {
    const incoming = state.incoming;
    if (!incoming || state.call) return null;
    ring(false);
    const urls = callUrls(incoming.clientKey);
    try {
        const data = await request(urls.answer(incoming.id), 'POST');
        state.call = {
            message: data.message,
            type: data.message?.call?.type ?? incoming.type ?? 'audio',
            role: 'callee',
            phase: 'active',
            startedAt: null,
            clientKey: incoming.clientKey,
            peerLabel: incoming.peerLabel,
        };
        state.incoming = null;
        return data.message;
    } catch (error) {
        setNotice(error.message);
        return null;
    }
};

const declineIncoming = async () => {
    const incoming = state.incoming;
    if (!incoming) return;
    ring(false);
    const urls = callUrls(incoming.clientKey);
    try {
        await request(urls.end(incoming.id), 'POST', { status: 'declined', duration: 0 });
    } catch (e) { /* ignore */ }
    state.incoming = null;
};

const cancelOutgoing = async () => {
    const call = state.call;
    if (!call) return null;
    ring(false);
    stopStatusPoll();
    state.call = null;
    try {
        const data = await request(callUrls(call.clientKey).end(call.message.id), 'POST', { status: 'missed', duration: 0 });
        return data.message ?? null;
    } catch (e) {
        return null;
    }
};

const hangUp = async ({ duration = 0, status = 'completed' } = {}) => {
    const call = state.call;
    if (!call) return null;
    ring(false);
    stopStatusPoll();
    state.call = null;
    try {
        const data = await request(callUrls(call.clientKey).end(call.message.id), 'POST', { duration, status });
        return data.message ?? null;
    } catch (e) {
        return null;
    }
};

const dismiss = () => {
    ring(false);
    stopStatusPoll();
    state.call = null;
};

// CallModal signalling props (bound to the current call).
const sendSignal = (kind, payload) => {
    const call = state.call;
    if (!call) return Promise.resolve();
    return request(callUrls(call.clientKey).signal(call.message.id), 'POST', { kind, payload }).catch(() => {});
};

const fetchSignals = async (after) => {
    const call = state.call;
    if (!call) return { signals: [], last_id: after, call: {} };
    const data = await request(`${callUrls(call.clientKey).signals(call.message.id)}?after=${after}`, 'GET');
    return {
        signals: data.signals ?? [],
        last_id: data.last_id ?? after,
        call: { status: data.message?.call?.status },
        message: data.message,
    };
};

const init = () => {
    if (started) return;
    started = true;
    state.context = detectContext();
    if (!state.context) {
        state.ready = true;
        return;
    }
    poll();
    pollTimer = setInterval(poll, 3000);
    state.ready = true;
};

const dispose = () => {
    stopPolling();
    stopStatusPoll();
    ring(false);
    if (noticeTimer) clearTimeout(noticeTimer);
    started = false;
};

export function usePartnerCallCenter() {
    return {
        state,
        init,
        dispose,
        startOutgoing,
        acceptIncoming,
        declineIncoming,
        cancelOutgoing,
        hangUp,
        dismiss,
        sendSignal,
        fetchSignals,
        setRingtone: (handler) => { ringHandler = handler; },
    };
}
