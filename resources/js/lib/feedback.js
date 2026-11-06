import { reactive } from 'vue';

const state = reactive({ current: null, queue: [], online: typeof navigator === 'undefined' ? true : navigator.onLine, slow: false });
let requestId = 0;
let slowTimer;
let feedbackRevision = 0;
let successFeedbackSuppressionCount = 0;

export function holdSuccessFeedback() {
    successFeedbackSuppressionCount += 1;
    let released = false;

    return () => {
        if (released) return;
        released = true;
        successFeedbackSuppressionCount = Math.max(0, successFeedbackSuppressionCount - 1);
    };
}

export function isSuccessFeedbackSuppressed() {
    return successFeedbackSuppressionCount > 0;
}

function present(item) {
    if (['offline', 'slow', 'session', 'permission'].includes(item.kind)
        && (state.current?.kind === item.kind || state.queue.some((queued) => queued.kind === item.kind))) return;
    if (state.current) state.queue.push(item);
    else state.current = item;
}

function dismiss(result = { isConfirmed: false, isDismissed: true }) {
    const current = state.current;
    state.current = state.queue.shift() || null;
    current?.resolve?.(result);
}

export function notify(options = {}) {
    feedbackRevision += 1;
    const normalized = typeof options === 'string' ? { message: options } : options;
    present({ id: ++requestId, kind: normalized.kind || normalized.type || 'info', title: normalized.title || '', message: normalized.message || '', actionLabel: normalized.actionLabel || 'OK', action: normalized.action, cancelLabel: normalized.cancelLabel || '', input: normalized.input || null });
}

export const toast = {
    success: (message, options = {}) => notify({ ...options, kind: 'success', title: options.title || 'Success', message }),
    error: (message, options = {}) => notify({ ...options, kind: 'error', title: options.title || 'Something went wrong', message }),
    warning: (message, options = {}) => notify({ ...options, kind: 'warning', title: options.title || 'Notice', message }),
    info: (message, options = {}) => notify({ ...options, kind: 'info', title: options.title || 'Information', message }),
};

export function showNotice(message, options = {}) {
    notify({ ...options, kind: options.kind || 'info', title: options.title || 'Notification', message: String(message ?? '') });
}

export function successModal(message, options = {}) {
    notify({ ...options, kind: 'success', title: options.title || 'Success', message: String(message ?? 'Your changes were completed successfully.') });
}

export function confirmModal(message, options = {}) {
    return showDialog({
        title: options.title || 'Please confirm',
        text: String(message ?? ''),
        icon: options.icon || 'warning',
        showCancelButton: true,
        confirmButtonText: options.confirmButtonText || 'Continue',
        cancelButtonText: options.cancelButtonText || 'Cancel',
    }).then((result) => result.isConfirmed);
}

export function requestInput(message, options = {}) {
    return showDialog({
        title: options.title || 'Enter details',
        text: String(message ?? ''),
        icon: 'info',
        input: options.input || 'text',
        inputLabel: options.inputLabel || '',
        inputPlaceholder: options.inputPlaceholder || '',
        inputValue: options.inputValue || '',
        showCancelButton: true,
        confirmButtonText: options.confirmButtonText || 'Continue',
        cancelButtonText: options.cancelButtonText || 'Cancel',
    }).then((result) => result.isConfirmed ? result.value : null);
}

export function showDialog(options = {}) {
    feedbackRevision += 1;
    return new Promise((resolve) => {
        const kind = options.showCancelButton ? 'confirm' : (options.icon || 'info');
        present({
            id: ++requestId,
            kind,
            title: options.title || '',
            message: options.text || (options.html ? String(options.html).replace(/<[^>]*>/g, ' ') : ''),
            actionLabel: options.confirmButtonText || 'OK',
            cancelLabel: options.showCancelButton ? (options.cancelButtonText || 'Cancel') : '',
            input: options.input ? { type: options.input === 'textarea' ? 'textarea' : 'text', label: options.inputLabel || '', placeholder: options.inputPlaceholder || '', value: options.inputValue || '' } : null,
            resolve,
        });
    });
}

export const dialog = { fire: showDialog };

export function beginRequest() {
    if (typeof window === 'undefined') return () => {};
    const started = Date.now();
    let ended = false;
    clearTimeout(slowTimer);
    slowTimer = setTimeout(() => { if (!ended) state.slow = true; }, 5000);
    return () => {
        if (ended) return;
        ended = true;
        clearTimeout(slowTimer);
        if (Date.now() - started < 5000) state.slow = false;
    };
}

export function setOnline(online) { state.online = online; }
export function setSlow(slow) { state.slow = slow; }
export function settleFeedback(result) { dismiss(result); }
export function getFeedbackState() { return state; }
export function getFeedbackRevision() { return feedbackRevision; }

