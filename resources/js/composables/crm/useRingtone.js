/**
 * Dependency-free ringtone/ringback built on the Web Audio API. Browsers block
 * audio until the user has interacted with the page, so callers should attach
 * `resume` to a one-off pointer/keydown gesture and call `play` afterwards.
 */
export function createRingtone() {
    let audioCtx = null;
    let timer = null;
    let current = null;

    const ensure = () => {
        const Ctx = typeof window !== 'undefined' && (window.AudioContext || window.webkitAudioContext);
        if (!Ctx) return null;
        if (!audioCtx) audioCtx = new Ctx();
        if (audioCtx.state === 'suspended') audioCtx.resume().catch(() => {});
        return audioCtx;
    };

    const beep = (freq, at, duration, volume) => {
        const ctx = ensure();
        if (!ctx) return;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.value = freq;
        gain.gain.setValueAtTime(0, at);
        gain.gain.linearRampToValueAtTime(volume, at + 0.04);
        gain.gain.linearRampToValueAtTime(0, at + duration);
        osc.connect(gain).connect(ctx.destination);
        osc.start(at);
        osc.stop(at + duration + 0.05);
    };

    const pattern = (kind) => {
        const ctx = ensure();
        if (!ctx) return;
        const now = ctx.currentTime + 0.02;
        if (kind === 'incoming') {
            beep(659, now, 0.35, 0.2);
            beep(880, now + 0.45, 0.35, 0.2);
        } else {
            beep(440, now, 0.5, 0.07);
        }
    };

    // kind: 'incoming' | 'ringback' | false/null to stop
    const play = (kind) => {
        if (kind === false || kind == null) {
            current = null;
            if (timer) clearInterval(timer);
            timer = null;
            return;
        }
        if (current === kind && timer) return;
        current = kind;
        if (timer) clearInterval(timer);
        pattern(kind);
        timer = setInterval(() => pattern(kind), kind === 'incoming' ? 2200 : 3000);
    };

    const resume = () => { ensure(); };

    const dispose = () => {
        play(false);
        try { audioCtx?.close?.(); } catch (e) { /* ignore */ }
        audioCtx = null;
    };

    return { play, resume, dispose };
}
