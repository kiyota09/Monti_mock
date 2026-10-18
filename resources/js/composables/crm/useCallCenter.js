import { reactive } from 'vue';
import { route } from 'ziggy-js';
import { request } from '@/lib/http';

/**
 * App-wide call centre. A module singleton (no Pinia): the overlay mounted in
 * AuthenticatedLayout reflects this reactive state on every page, so an
 * incoming call reaches the callee wherever they are in the dashboard.
 */
const state = reactive({
    eligible: null,
    incoming: null,
    active: null,
    showIncoming: false,
    starting: false,
    notice: null,
});

let started = false;
let pollTimer = null;
let statusTimer = null;
let ringHandler = null;
let noticeTimer = null;

const ring = (kind) => ringHandler?.(kind);

const setNotice = (message) => {
    state.notice = message;
    if (noticeTimer) clearTimeout(noticeTimer);
    noticeTimer = setTimeout(() => { state.notice = null; }, 4000);
};

const stopPolling = () => {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = null;
};

const stopStatusPoll = () => {
    if (statusTimer) clearInterval(statusTimer);
    statusTimer = null;
};

const poll = async () => {
    if (typeof window === 'undefined' || !window.location.pathname.includes('/dashboard')) return;
    try {
        const data = await request(route('crm.calls.incoming'), 'GET');
        state.eligible = data.eligible !== false;
        if (!state.eligible) {
            stopPolling();
            return;
        }
        const ringing = (data.calls ?? [])[0] ?? null;
        if (state.active) return;
        if (ringing) {
            if (!state.incoming || state.incoming.id !== ringing.id) {
                state.incoming = ringing;
                state.showIncoming = false;
                ring('incoming');
            }
        } else if (state.incoming) {
            state.incoming = null;
            state.showIncoming = false;
            ring(false);
        }
    } catch (error) {
        if (error.status === 401 || error.status === 403) {
            state.eligible = false;
            stopPolling();
        }
    }
};

const init = () => {
    if (started) return;
    started = true;
    poll();
    pollTimer = setInterval(poll, 3000);
};

const statusLabel = (status) => ({
    declined: 'Call declined',
    missed: 'No answer',
    failed: 'Call failed',
    completed: 'Call ended',
}[status] ?? 'Call ended');

const startStatusPoll = () => {
    stopStatusPoll();
    statusTimer = setInterval(async () => {
        const active = state.active;
        if (!active) {
            stopStatusPoll();
            return;
        }
        try {
            const data = await request(route('crm.calls.show', active.call.id), 'GET');
            const call = data.call;
            if (call.status === 'answered') {
                ring(false);
                state.active = { ...active, call, phase: 'active', startedAt: call.answered_at };
                stopStatusPoll();
            } else if (['declined', 'missed', 'completed', 'failed'].includes(call.status)) {
                ring(false);
                stopStatusPoll();
                state.active = null;
                setNotice(statusLabel(call.status));
            } else {
                state.active = { ...active, call };
            }
        } catch (error) {
            if (error.status >= 400) stopStatusPoll();
        }
    }, 1500);
};

const call = async (contact, type, clientId = null) => {
    if (state.active || state.starting) return;
    state.starting = true;
    try {
        const data = await request(route('crm.calls.store'), 'POST', {
            user_id: contact.id,
            type,
            client_id: clientId,
        });
        state.incoming = null;
        state.showIncoming = false;
        state.active = { call: data.call, role: 'caller', phase: 'calling', startedAt: data.call.started_at };
        ring('ringback');
        startStatusPoll();
    } catch (error) {
        setNotice(error.message);
    } finally {
        state.starting = false;
    }
};

const accept = async () => {
    const incoming = state.incoming;
    if (!incoming) return;
    ring(false);
    try {
        const data = await request(route('crm.calls.answer', incoming.id), 'POST');
        state.active = { call: data.call, role: 'callee', phase: 'active', startedAt: data.call.answered_at };
        state.incoming = null;
        state.showIncoming = false;
    } catch (error) {
        setNotice(error.message);
    }
};

const decline = async () => {
    const incoming = state.incoming;
    if (!incoming) return;
    ring(false);
    try {
        await request(route('crm.calls.decline', incoming.id), 'POST');
    } catch {
        /* ignore */
    }
    state.incoming = null;
    state.showIncoming = false;
};

const cancel = async () => {
    const active = state.active;
    if (!active) return;
    stopStatusPoll();
    ring(false);
    state.active = null;
    try {
        await request(route('crm.calls.end', active.call.id), 'POST', { duration: 0, status: 'missed' });
    } catch {
        /* ignore */
    }
};

const end = async ({ duration = 0, status = 'completed' } = {}) => {
    const active = state.active;
    if (!active) return;
    stopStatusPoll();
    ring(false);
    state.active = null;
    try {
        await request(route('crm.calls.end', active.call.id), 'POST', { duration, status });
    } catch {
        /* ignore */
    }
};

const openIncoming = () => {
    state.showIncoming = true;
};

// WebRTC signalling relay (offer/answer/ICE) over the polling channel.
const postSignal = (callId, kind, payload) =>
    request(route('crm.calls.signal', callId), 'POST', { kind, payload });

const fetchSignals = (callId, after = 0) =>
    request(`${route('crm.calls.signals', callId)}?after=${after}`, 'GET');

// Dismiss the active call without writing it back (the remote peer already
// ended it, so re-POSTing `end` would be redundant).
const dismiss = () => {
    stopStatusPoll();
    ring(false);
    state.active = null;
    state.incoming = null;
    state.showIncoming = false;
};

export function useCallCenter() {
    return {
        state,
        init,
        call,
        accept,
        decline,
        cancel,
        end,
        dismiss,
        openIncoming,
        postSignal,
        fetchSignals,
        setRingtone: (handler) => { ringHandler = handler; },
    };
}
