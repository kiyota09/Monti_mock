import { computed } from 'vue';

/**
 * Shared pipeline helpers (pure — no Pinia needed; the board page owns state
 * and these helpers derive everything so totals/bars recalc instantly).
 */

export const ACTIVITY_TYPES = [
    { key: 'call', label: 'Call' },
    { key: 'meeting', label: 'Meeting' },
    { key: 'reminder', label: 'Reminder' },
    { key: 'todo', label: 'To-Do' },
];

const startOfDay = (d) => {
    const x = new Date(d);
    x.setHours(0, 0, 0, 0);
    return x.getTime();
};

/** Resolve an opportunity's activity state from its next_activity payload. */
export function activityStateOf(opp) {
    if (opp?.activity_state) return opp.activity_state;
    const next = opp?.next_activity;
    if (!next || next.status !== 'planned') return next ? 'planned' : 'none';
    if (!next.due) return 'planned';
    const due = startOfDay(next.due);
    const today = startOfDay(new Date());
    if (due < today) return 'overdue';
    if (due === today) return 'today';
    return 'planned';
}

export const STATE_META = {
    planned: { label: 'Planned', bar: 'bg-emerald-500', text: 'text-emerald-600', dot: 'bg-emerald-500' },
    today: { label: 'Due today', bar: 'bg-amber-400', text: 'text-amber-500', dot: 'bg-amber-400' },
    overdue: { label: 'Overdue', bar: 'bg-rose-500', text: 'text-rose-600', dot: 'bg-rose-500' },
    none: { label: 'No activity', bar: 'bg-slate-300 dark:bg-zinc-600', text: 'text-slate-400', dot: 'bg-slate-300 dark:bg-zinc-600' },
};

export const peso = (v) =>
    '₱' + Number(v || 0).toLocaleString('en-PH', { maximumFractionDigits: 0 });

export const initialsOf = (name) =>
    String(name || '?')
        .split(' ')
        .map((w) => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

/** Human due label: "due today" / "overdue by 2d" / "in 3d" / "no due date". */
export function dueLabel(due) {
    if (!due) return 'no due date';
    const day = 86400000;
    const diff = Math.round((startOfDay(due) - startOfDay(new Date())) / day);
    if (diff === 0) return 'due today';
    if (diff === 1) return 'due tomorrow';
    if (diff > 1) return `in ${diff}d`;
    if (diff === -1) return 'overdue by 1d';
    return `overdue by ${Math.abs(diff)}d`;
}

/** Local "YYYY-MM-DDTHH:mm" string for datetime-local inputs. */
export function toLocalInput(v) {
    if (!v) return '';
    const d = new Date(v);
    const p = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
}

/** Next occurrence of a weekday (0=Sun) at HH:mm, as a Date. */
export function nextWeekday(weekday, hour = 9, minute = 0) {
    const d = new Date();
    d.setHours(hour, minute, 0, 0);
    let delta = (weekday - d.getDay() + 7) % 7;
    if (delta === 0 && d <= new Date()) delta = 7;
    d.setDate(d.getDate() + delta);
    return d;
}

/** Per-stage activity counts for the progress bar. */
export function activityCounts(opps) {
    const c = { planned: 0, today: 0, overdue: 0, none: 0 };
    for (const o of opps || []) {
        const s = activityStateOf(o);
        if (c[s] !== undefined) c[s] += 1;
    }
    return c;
}

export const stageTotal = (opps) => Math.round((opps || []).reduce((n, o) => n + Number(o.value || 0), 0));

/** Board-level filtering: My Pipeline + search (title or contact name). */
export function filterOpps(opps, { mine, userId, search }) {
    let list = opps || [];
    if (mine && userId) list = list.filter((o) => Number(o.owner_id) === Number(userId));
    const q = (search || '').trim().toLowerCase();
    if (q) {
        list = list.filter(
            (o) =>
                (o.title || '').toLowerCase().includes(q) ||
                (o.contact_name || '').toLowerCase().includes(q) ||
                (o.organization || '').toLowerCase().includes(q),
        );
    }
    return list;
}

/** Composable: derived per-stage map from a flat opportunity list. */
export function useStageMap(stages, opps) {
    return computed(() => {
        const map = {};
        for (const s of stages?.value ?? stages ?? []) map[s.id] = [];
        for (const o of opps?.value ?? opps ?? []) {
            if (o.stage_id && map[o.stage_id]) map[o.stage_id].push(o);
        }
        return map;
    });
}
