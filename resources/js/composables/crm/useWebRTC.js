/**
 * Peer-connection wrapper for CRM internal calls.
 *
 * There is no dedicated signalling/media server, so SDP offers/answers and ICE
 * candidates are relayed through the existing HTTP polling channel: each side
 * writes signals via `sendSignal` and reads the other side's via `fetchSignals`.
 * Only a public STUN server is used — fine for most networks, but calls can
 * still fail behind symmetric/corporate NATs without a TURN server.
 */
export function createWebRTC({
    role,
    sendSignal,
    fetchSignals,
    onRemoteStream,
    onConnectionChange,
    onRemoteStatus,
    pollMs = 1000,
}) {
    const iceServers = [
        { urls: ['stun:stun.l.google.com:19302', 'stun:stun1.l.google.com:19302'] },
    ];

    let pc = null;
    let remoteDescSet = false;
    const pendingCandidates = [];
    let cursor = 0;
    let timer = null;
    let closed = false;
    let negotiating = false;

    const flushCandidates = async () => {
        while (pendingCandidates.length) {
            const candidate = pendingCandidates.shift();
            try {
                await pc.addIceCandidate(candidate);
            } catch (e) {
                /* a stale candidate is harmless */
            }
        }
    };

    const init = (localStream) => {
        pc = new RTCPeerConnection({ iceServers });

        if (localStream) {
            localStream.getTracks().forEach((track) => pc.addTrack(track, localStream));
        }

        pc.ontrack = (event) => {
            if (event.streams && event.streams[0]) onRemoteStream?.(event.streams[0]);
        };

        pc.onicecandidate = (event) => {
            if (event.candidate) sendSignal('candidate', event.candidate.toJSON());
        };

        pc.onconnectionstatechange = () => {
            onConnectionChange?.(pc.connectionState);
        };

        return pc;
    };

    const handleSignal = async (signal) => {
        if (!pc || closed) return;
        const payload = signal.payload;

        if (signal.kind === 'candidate') {
            if (remoteDescSet) {
                try { await pc.addIceCandidate(payload); } catch (e) { /* ignore */ }
            } else {
                pendingCandidates.push(payload);
            }
            return;
        }

        if (signal.kind === 'offer') {
            await pc.setRemoteDescription(new RTCSessionDescription({ type: 'offer', sdp: payload.sdp }));
            remoteDescSet = true;
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            await sendSignal('answer', { type: 'answer', sdp: answer.sdp });
            await flushCandidates();
            return;
        }

        if (signal.kind === 'answer') {
            await pc.setRemoteDescription(new RTCSessionDescription({ type: 'answer', sdp: payload.sdp }));
            remoteDescSet = true;
            await flushCandidates();
        }
    };

    const start = async () => {
        if (!pc || negotiating) return;
        negotiating = true;
        try {
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            await sendSignal('offer', { type: 'offer', sdp: offer.sdp });
        } finally {
            negotiating = false;
        }
    };

    const tick = async () => {
        if (closed || !pc) return;
        try {
            const data = await fetchSignals(cursor);
            for (const signal of data.signals ?? []) {
                cursor = Math.max(cursor, signal.id);
                await handleSignal(signal);
            }
            if (data.call?.status) onRemoteStatus?.(data.call.status);
        } catch (e) {
            /* transient network hiccup — retry next tick */
        }
    };

    const startPolling = () => {
        tick();
        timer = setInterval(tick, pollMs);
    };

    const close = () => {
        closed = true;
        if (timer) clearInterval(timer);
        timer = null;
        try { pc?.close(); } catch (e) { /* ignore */ }
        pc = null;
    };

    return {
        init,
        start,
        handleSignal,
        startPolling,
        close,
        get peer() { return pc; },
        get isOfferer() { return role === 'caller'; },
    };
}
