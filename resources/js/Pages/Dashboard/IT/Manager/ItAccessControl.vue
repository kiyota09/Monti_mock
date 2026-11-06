<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Search, X, ShieldCheck, ShieldOff, PauseCircle, RotateCcw, Save, Users, Bell,
    Ban, Truck, ChevronDown, ChevronsUpDown, Lock, Layers,
} from 'lucide-vue-next';

const props = defineProps({
    users: { type: Array, default: () => [] },
    modules: { type: Array, default: () => [] },
    modulePages: { type: Object, default: () => ({}) },
    manRolePages: { type: Object, default: () => ({}) },
    manOverviewPages: { type: Object, default: () => ({}) },
    manSupervisorAccountPages: { type: Object, default: () => ({}) },
    manSupervisorStaffAccess: { type: Object, default: () => ({}) },
    userPagePerms: { type: Object, default: () => ({}) },
    userModules: { type: Object, default: () => ({}) },
    assignableRoles: { type: Array, default: () => [] },
    assignablePositions: { type: Array, default: () => [] },
    pendingRequests: { type: Array, default: () => [] },
});

const search = ref('');
const roleFilter = ref('');
const statusFilter = ref('');
const managing = ref(null);
const activeTab = ref('account');
const suspendUntil = ref('');
const showNotifications = ref(false);
const commandButton = ref(null);
const commandMenuStyle = ref({});
const selectedRequestId = ref(null);

const statusForm = useForm({ user_id: '', action: '', suspended_until: '' });
const positionForm = useForm({ user_id: '', position: '', role: '', supervisor_department: '' });
const modulesForm = useForm({ user_id: '', modules: [] });
const pagesForm = useForm({ user_id: '', grants: [] });
const supervisorStaffForm = useForm({ user_id: '', grants: [] });
const accessForm = useForm({ user_id: '', modules: [], page_grants: [], supervisor_grants: [] });
const supervisorStaffGrants = ref({});

// Local editable page grants: { [MODULE]: { [page]: 'disabled'|'view'|'edit' } }
const pageGrants = ref({});

// Which module accordions are open in the combined "Modules & Pages" tab
const openModules = ref({});
// Snapshot used to detect unsaved changes in the combined tab
const accessSnapshot = ref('');
// Page levels as they were when the drawer opened, so save only submits edits
const initialGrants = ref({});

const ELEVATED = ['manager', 'secretary', 'special_officer', 'vice_president', 'president'];

const isElevatedUser = (u) => {
    if (!u) return false;
    return !!u.is_manufacturing_supervisor || ELEVATED.includes(u.position) || !!u.is_elevated;
};

const rootOf = (u) => {
    if (!u) return null;
    if (u.root_module) return u.root_module;
    return u.role || null;
};

const initials = (name = '') =>
    String(name)
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((n) => n[0].toUpperCase())
        .join('');

// MAN supervisors are stored as position='staff' + flag — never display them as Staff.
// Prefers backend display_position, falls back to the flag so stale props still render right.
const displayPositionKey = (u) => {
    if (!u) return '';
    if (u.display_position) return u.display_position;
    if (u.is_manufacturing_supervisor && u.position === 'staff') return 'manufacturing_supervisor';
    return u.position || '';
};

const formatPositionLabel = (pos) => {
    if (!pos) return '';
    if (pos === 'manufacturing_supervisor') return 'supervisor';
    return String(pos).replace('_', ' ');
};

const displayPositionLabel = (u) => formatPositionLabel(displayPositionKey(u));

// ── MAN department labels ────────────────────────────────────────────
// Backend sends `manufacturing_department`; the slug map below is a
// fallback so stale props still resolve. Mirrors
// ItAccessControlController::manufacturingDepartmentFor().
const MAN_ROLE_DEPARTMENT = {
    knitting_yarn: 'knitting', knitting_mechanic: 'knitting', knitting_inspector: 'knitting', knitting_sample: 'knitting',
    dyeing_color: 'dyeing', dyeing_bleaching: 'dyeing', dyeing_pre_treatment: 'dyeing', dyeing_treatment: 'dyeing', dyeing_lab_chemist: 'dyeing',
    finishing_squeezer: 'finishing', finishing_ironing: 'finishing', finishing_hydro: 'finishing', finishing_tubetex: 'finishing',
    finishing_packaging: 'finishing', finishing_inspector: 'finishing', checker_quality: 'finishing',
    maintenance_checker: 'maintenance', pollution_control_operator: 'maintenance', safety_officer: 'maintenance',
    boiler_operator: 'boiler',
};
const MAN_DEPT_LABELS = {
    knitting: 'Knitting',
    dyeing: 'Dyeing',
    finishing: 'Finishing',
    maintenance: 'Maintenance',
    boiler: 'Boiler',
};
const manDeptOf = (u) => {
    if (!u || u.role !== 'MAN') return null;
    if (u.manufacturing_department) return u.manufacturing_department;
    if (u.is_manufacturing_supervisor && u.supervisor_department) return u.supervisor_department;
    return MAN_ROLE_DEPARTMENT[u.manufacturing_role] || null;
};
const manDeptLabel = (dept) => (MAN_DEPT_LABELS[dept] ? `${MAN_DEPT_LABELS[dept]} Dept` : dept);
const MAN_ROLE_LABELS = {
    knitting_inspector: 'Knitting Inspector Staff',
    knitting_sample: 'Knitting Sample Staff',
    dyeing_pre_treatment: 'Dyeing Pre Treatment Staff',
    dyeing_treatment: 'Dyeing Post Treatment Staff',
    finishing_ironing: 'Finishing Heatsetting Staff',
    finishing_hydro: 'Finishing Hydro Staff',
    finishing_tubetex: 'Finishing Tubetex Staff',
    finishing_inspector: 'Finishing Shrinkage Inspector Staff',
};
const formatManRole = (role) => {
    if (!role) return '';
    return MAN_ROLE_LABELS[role] || String(role).replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
};

// ── LOG plant-staff labels (driver / conductor) ─────────────────────
// Backend resolves users.log_role (falling back to the driver/conductor
// records) and only returns a value for LOG accounts.
const logRoleLabel = (u) => {
    if (!u || String(u.role || '').toUpperCase() !== 'LOG') return '';
    if (u.log_role === 'driver') return 'Driver';
    if (u.log_role === 'conductor') return 'Conductor';
    return '';
};
const logRolePill = (u) => (u?.log_role === 'driver'
    ? 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300'
    : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300');

// All modules handled by an employee: explicit grants first, fallback to
// root_module / role so staff with no rows still show their home module.
const userModuleList = (u) => {
    if (!u) return [];
    const explicit = props.userModules?.[u.id] || [];
    if (explicit.length) return [...new Set(explicit.map((m) => String(m).toUpperCase()))];
    const fallback = u.root_module || u.role;
    return fallback ? [String(fallback).toUpperCase()] : [];
};

// Executive-command rows store raw current_position ('staff' for supervisors) —
// resolve to the target's display label so commands never read as Staff.
const requestCurrentLabel = (req) => {
    const target = (props.users || []).find((u) => Number(u.id) === Number(req?.target_user_id));
    if (target?.is_manufacturing_supervisor && req?.current_position === 'staff') return 'supervisor';
    return formatPositionLabel(req?.current_position);
};

const requestsForUser = (userId) => (props.pendingRequests || []).filter((r) => Number(r.target_user_id) === Number(userId));

const toggleCommands = () => {
    showNotifications.value = !showNotifications.value;
    if (!showNotifications.value) return;

    const rect = commandButton.value?.getBoundingClientRect();
    if (!rect) return;
    const viewportPadding = 16;
    const menuWidth = Math.min(448, window.innerWidth - viewportPadding * 2);
    commandMenuStyle.value = {
        top: `${rect.bottom + 8}px`,
        right: `${Math.max(viewportPadding, window.innerWidth - rect.right)}px`,
        width: `${menuWidth}px`,
        maxHeight: `calc(100dvh - ${Math.max(viewportPadding, rect.bottom + 24)}px)`,
    };
};

// Manufacturing is role-aware: a plant staff employee only sees the pages
// their role actually handles (e.g. Dyeing Lab Chemist -> Shades, Tests &
// CoA, Lab Inventory, Bulk Transfer, History, Recipes). Supervisors and
// role-less MAN accounts keep the shared overview.
const pagesForModule = (module, user = managing.value) => {
    const key = String(module || '').toUpperCase();
    if (key === 'MAN') {
        if (user?.is_manufacturing_supervisor) {
            return props.manSupervisorAccountPages?.[user.supervisor_department] || props.manOverviewPages || {};
        }
        const role = user?.manufacturing_role;
        if (role && !user?.is_manufacturing_supervisor) {
            return props.manRolePages?.[role] || props.manOverviewPages || {};
        }
        return props.manOverviewPages || props.modulePages?.[module] || props.modulePages?.MAN || {};
    }
    return props.modulePages?.[module] || props.modulePages?.[key] || {};
};

// Normalized [{ key, label }] rows for a module.
const normalizedPages = (module, user = managing.value) =>
    Object.entries(pagesForModule(module, user)).map(([key, val]) => ({
        key: String(key).toLowerCase(),
        label: typeof val === 'string' ? val : val.label,
    }));

const buildAccessSnapshot = () =>
    JSON.stringify({
        m: [...(modulesForm.modules || [])].map((m) => String(m).toUpperCase()).sort(),
        g: pageGrants.value,
        s: supervisorStaffGrants.value,
    });

const openManage = (user) => {
    managing.value = user;
    selectedRequestId.value = null;
    activeTab.value = 'account';
    suspendUntil.value = '';
    statusForm.user_id = user.id;
    positionForm.user_id = user.id;
    positionForm.position = user.is_manufacturing_supervisor ? 'supervisor' : user.position;
    positionForm.role = user.role;
    positionForm.supervisor_department = user.supervisor_department || '';
    modulesForm.user_id = user.id;
    modulesForm.modules = [...(props.userModules[user.id] || [])];
    // Staff with no explicit module rows still show their root as granted.
    const root = rootOf(user);
    if (root && !modulesForm.modules.includes(root)) {
        // Keep the model truthful (backend re-adds root anyway) but do NOT
        // push extra rows for staff — the UI below renders root as locked.
    }
    pagesForm.user_id = user.id;

    const grants = {};
    const existing = {};
    Object.entries(props.userPagePerms[user.id] || {}).forEach(([mod, pages]) => {
        existing[String(mod).toUpperCase()] = {};
        Object.entries(pages || {}).forEach(([pg, lvl]) => {
            const norm = String(lvl).toLowerCase();
            existing[String(mod).toUpperCase()][String(pg).toLowerCase()] = ['view', 'edit', 'disabled'].includes(norm) ? norm : 'edit';
        });
    });
    Object.keys(props.modulePages).forEach((module) => {
        const moduleKey = String(module).toUpperCase();
        const pages = pagesForModule(moduleKey, user);
        grants[moduleKey] = {};
        Object.keys(pages || {}).forEach((key) => {
            const pageKey = String(key).toLowerCase();
            const lvl = existing[moduleKey]?.[pageKey] || 'disabled';
            grants[moduleKey][pageKey] = lvl;
        });
    });
    pageGrants.value = grants;
    initialGrants.value = JSON.parse(JSON.stringify(grants));

    const staffAccess = props.manSupervisorStaffAccess?.[user.id]?.roles || [];
    supervisorStaffGrants.value = Object.fromEntries(staffAccess.map((role) => [
        role.key,
        Object.fromEntries(Object.keys(role.pages || {}).map((page) => [page, role.permissions?.[page] || 'disabled'])),
    ]));
    supervisorStaffForm.user_id = user.id;

    // Home module accordion starts open
    openModules.value = root ? { [String(root).toUpperCase()]: true } : {};
    accessSnapshot.value = buildAccessSnapshot();
};

// Executive requests can prefill the direct IT position form.
const openRequest = (req) => {
    const user = props.users.find((u) => Number(u.id) === Number(req.target_user_id));
    if (!user) return;
    openManage(user);
    activeTab.value = 'position';
    positionForm.position = req.requested_position === 'supervisor' ? 'supervisor' : req.requested_position;
    positionForm.role = req.requested_role || user.role;
    positionForm.supervisor_department = req.supervisor_department || '';
    showNotifications.value = false;
};

// Selecting a pending request only prefills the form; it is not required.
const selectRequest = (req) => {
    selectedRequestId.value = req.id;
    positionForm.position = req.requested_position === 'supervisor' ? 'supervisor' : req.requested_position;
    positionForm.role = req.requested_role || managing.value?.role;
    positionForm.supervisor_department = req.supervisor_department || '';
};

const accountStatus = (u) => {
    if (!u.is_active && u.suspended_until) return 'suspended';
    if (!u.is_active) return 'disabled';
    return 'active';
};

const filteredUsers = computed(() => {
    let list = props.users;
    if (search.value) {
        const q = search.value.toLowerCase();
        list = list.filter((u) =>
            u.name.toLowerCase().includes(q) ||
            u.email.toLowerCase().includes(q) ||
            (u.employee_id || '').toLowerCase().includes(q) ||
            (u.manufacturing_role || '').toLowerCase().includes(q) ||
            (manDeptOf(u) || '').toLowerCase().includes(q) ||
            (logRoleLabel(u) || '').toLowerCase().includes(q)
        );
    }
    if (roleFilter.value) list = list.filter((u) => userModuleList(u).includes(String(roleFilter.value).toUpperCase()));
    if (statusFilter.value) list = list.filter((u) => accountStatus(u) === statusFilter.value);
    return list;
});

const counts = computed(() => ({
    total: props.users.length,
    active: props.users.filter((u) => accountStatus(u) === 'active').length,
    disabled: props.users.filter((u) => accountStatus(u) === 'disabled').length,
    suspended: props.users.filter((u) => accountStatus(u) === 'suspended').length,
}));

const statusPill = (u) => ({
    active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    disabled: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    suspended: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
}[accountStatus(u)]);

const pageCount = (userId) => {
    const mods = props.userPagePerms[userId] || {};
    return Object.values(mods).reduce((n, pages) => n + Object.keys(pages || {}).filter((k) => (pages[k] || '').toLowerCase() !== 'disabled').length, 0);
};

const setStatus = (action) => {
    statusForm.action = action;
    statusForm.suspended_until = action === 'suspend' ? suspendUntil.value : '';
    statusForm.post(route('it.access-control.status'), {
        preserveScroll: true,
        onSuccess: () => { managing.value = null; },
    });
};

const savePosition = () => {
    positionForm.clearErrors();
    positionForm.post(route('it.access-control.position'), {
        preserveScroll: true,
        onSuccess: () => { managing.value = null; },
    });
};

const buildGrants = () => {
    const grants = [];
    Object.entries(pageGrants.value).forEach(([module, pages]) => {
        Object.entries(pages).forEach(([page, level]) => {
            const before = initialGrants.value[module]?.[page];
            if (before === level) return;
            if (['view', 'edit', 'disabled'].includes(level)) {
                grants.push({ module, page, level });
            }
        });
    });
    return grants;
};

// Combined save: modules, page levels and supervisor pages are applied in one
// transaction; the submitted page levels are authoritative.
const saveAccess = () => {
    if (!managing.value) return;
    accessForm.clearErrors();
    accessForm.user_id = managing.value.id;
    accessForm.modules = [...(modulesForm.modules || [])];
    accessForm.page_grants = buildGrants();
    accessForm.supervisor_grants = managing.value.is_manufacturing_supervisor
        ? Object.entries(supervisorStaffGrants.value).flatMap(([role, pages]) =>
            Object.entries(pages).map(([page, level]) => ({ role, page, level }))
        )
        : [];

    accessForm.post(route('it.access-control.save'), {
        preserveScroll: true,
        onSuccess: () => { managing.value = null; },
    });
};

const tabs = [
    { key: 'account', label: 'Account' },
    { key: 'position', label: 'Position' },
    { key: 'access', label: 'Modules & Pages' },
];

// ── Modules & Pages rules ─────────────────────────────────────────
// Staff can hold their home module only. Elevated roles can also hold add-on modules.
const visibleModules = computed(() => {
    if (!managing.value) return [];
    const root = rootOf(managing.value);
    if (!isElevatedUser(managing.value)) {
        return (props.modules || []).filter((m) => m.key === root || m.key === managing.value.role);
    }
    return props.modules || [];
});

const isRootLocked = (modKey) => {
    if (!managing.value) return false;
    const root = rootOf(managing.value);
    if (modKey === root) return true;
    if (managing.value.is_manufacturing_supervisor && modKey === 'MAN') return true;
    return false;
};

const isGranted = (modKey) => isRootLocked(modKey) || (modulesForm.modules || []).includes(modKey);

const enabledPageCount = (modKey) => {
    const grants = pageGrants.value[String(modKey).toUpperCase()] || {};
    return Object.values(grants).filter((l) => l === 'view' || l === 'edit').length;
};

// One row per module with its pages, grant state and enabled-count.
const accessModules = computed(() =>
    visibleModules.value.map((m) => {
        const upper = String(m.key).toUpperCase();
        const pages = normalizedPages(m.key);
        return {
            ...m,
            upper,
            pages,
            home: isRootLocked(m.key),
            granted: isGranted(m.key),
            enabled: enabledPageCount(m.key),
        };
    })
);

const totalEnabledPages = computed(() => accessModules.value.reduce((n, m) => n + (m.granted ? m.enabled : 0), 0));
const grantedModuleCount = computed(() => accessModules.value.filter((m) => m.granted).length);
const accessDirty = computed(() => !!managing.value && buildAccessSnapshot() !== accessSnapshot.value);
const accessSaveError = computed(() => accessForm.errors.error || accessForm.errors.modules
    || accessForm.errors.page_grants || accessForm.errors.supervisor_grants
    || accessForm.errors.grants || Object.values(accessForm.errors)[0] || '');
const supervisorStaffRoles = computed(() => managing.value?.is_manufacturing_supervisor
    ? (props.manSupervisorStaffAccess?.[managing.value.id]?.roles || [])
    : []);
const setSupervisorStaffLevel = (role, page, level) => {
    if (!supervisorStaffGrants.value[role]) supervisorStaffGrants.value[role] = {};
    supervisorStaffGrants.value[role][page] = level;
};
const setAllSupervisorStaffLevels = (role, level) => {
    const group = supervisorStaffRoles.value.find((item) => item.key === role);
    if (!group) return;
    supervisorStaffGrants.value[role] = Object.fromEntries(Object.keys(group.pages || {}).map((page) => [page, level]));
};

const allOpen = computed(() => accessModules.value.length > 0 && accessModules.value.every((m) => openModules.value[m.upper]));
const toggleOpen = (upper) => { openModules.value[upper] = !openModules.value[upper]; };
const toggleAllOpen = () => {
    const next = !allOpen.value;
    accessModules.value.forEach((m) => { openModules.value[m.upper] = next; });
};

const toggleModule = (modKey) => {
    if (isRootLocked(modKey)) return;
    const i = modulesForm.modules.indexOf(modKey);
    if (i === -1) {
        modulesForm.modules.push(modKey);
        openModules.value[String(modKey).toUpperCase()] = true;
    } else {
        modulesForm.modules.splice(i, 1);
        // Revoking a module revokes its pages too: reset every page of the
        // removed module to DISABLED so stale view/edit levels can't linger,
        // inflate counts, or be resubmitted (and re-provision the module) on
        // the next save.
        const gKey = Object.keys(pageGrants.value).find((k) => String(k).toUpperCase() === String(modKey).toUpperCase());
        if (gKey && pageGrants.value[gKey]) {
            Object.keys(pageGrants.value[gKey]).forEach((p) => {
                pageGrants.value[gKey][p] = 'disabled';
            });
        }
    }
};

const levelOf = (upper, page) => pageGrants.value[upper]?.[page] || 'disabled';

const setLevel = (upper, page, level) => {
    if (!pageGrants.value[upper]) pageGrants.value[upper] = {};
    pageGrants.value[upper][page] = level;
    // Enabling a page is enough to grant its module: the backend provisions
    // the module shell for any usable page level, so mirror that here instead
    // of silently dropping the click while the module switch is off.
    if (['view', 'edit'].includes(level) && !isGranted(upper)) {
        if (!(modulesForm.modules || []).some((m) => String(m).toUpperCase() === upper)) {
            modulesForm.modules.push(upper);
        }
        openModules.value[upper] = true;
    }
};

const setAllLevels = (mod, level) => {
    mod.pages.forEach((p) => setLevel(mod.upper, p.key, level));
};

// The staff landing (/dashboard/man/staff) requires the Dashboard page and
// redirects into the role dashboard. If IT enables role pages but leaves
// Dashboard DISABLED, the account can still open those pages by URL but the
// normal login entry shows the limited-access notice.
const manPlantDashboardWarning = computed(() => {
    const u = managing.value;
    if (!u || u.role !== 'MAN' || u.position !== 'staff' || u.is_manufacturing_supervisor) return false;
    const gKey = Object.keys(pageGrants.value).find((k) => String(k).toLowerCase() === 'man');
    const grants = (gKey && pageGrants.value[gKey]) || {};
    const dashboardOn = ['view', 'edit'].includes(grants.dashboard);
    const anyRolePageOn = Object.entries(grants).some(([key, level]) => key !== 'dashboard' && ['view', 'edit'].includes(level));
    return anyRolePageOn && !dashboardOn;
});

// The manage drawer is teleported to <body> (fullscreen, above every
// navbar/sidebar layer). Lock background scroll while it is open so the
// panel — never the page behind it — is what scrolls on any viewport.
watch(managing, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});
onUnmounted(() => {
    document.body.style.overflow = '';
});
</script>

<template>
    <Head title="IT Access Control | Monti Textile" />
    <AuthenticatedLayout>
        <div class="it-page-hero mb-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    IT Access <span class="text-blue-600">Control</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">
                    Organization-wide accounts, direct position changes for eligible employees, modules and per-page view/edit/disabled grants.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex flex-wrap gap-2 text-xs font-black">
                    <span class="px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">{{ counts.total }} total</span>
                    <span class="px-3 py-1.5 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">{{ counts.active }} active</span>
                    <span class="px-3 py-1.5 rounded-full bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">{{ counts.disabled }} disabled</span>
                    <span class="px-3 py-1.5 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">{{ counts.suspended }} suspended</span>
                </div>
                <!-- Executive commands notification -->
                <div class="relative">
                    <button ref="commandButton" @click="toggleCommands"
                        class="relative inline-flex items-center gap-1.5 px-3 py-2 text-xs font-black text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 active:scale-95 shadow-sm shadow-indigo-600/20">
                        <Bell class="w-4 h-4" />
                        Position Requests
                        <span v-if="pendingRequests.length" class="absolute -top-2 -right-2 min-w-[20px] h-5 px-1 rounded-full bg-red-600 text-white text-[10px] font-black flex items-center justify-center ring-2 ring-white dark:ring-slate-900">
                            {{ pendingRequests.length }}
                        </span>
                    </button>
                    <Teleport to="body">
                    <div v-if="showNotifications" class="fixed inset-0 z-[69]" @click="showNotifications = false"></div>
                    <section v-if="showNotifications" :style="commandMenuStyle" class="fixed z-[70] flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900" role="dialog" aria-modal="true" aria-labelledby="it-commands-title">
                        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                            <p id="it-commands-title" class="text-xs font-black uppercase tracking-widest text-slate-500">President / VP Commands</p>
                            <button @click="showNotifications = false" class="text-slate-400 hover:text-slate-600"><X class="w-4 h-4" /></button>
                        </div>
                        <div v-if="pendingRequests.length === 0" class="overflow-y-auto px-4 py-8 text-center text-xs text-slate-400 font-bold">
                            No pending requests. IT can update positions directly.
                        </div>
                        <div v-else class="min-h-0 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
                            <button v-for="req in pendingRequests" :key="req.id" @click="openRequest(req)"
                                class="w-full text-left px-4 py-3 hover:bg-indigo-50 dark:hover:bg-slate-800 transition-colors">
                                <div class="flex items-center gap-2">
                                    <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase', ['promote', 'assign_supervisor'].includes(req.action) ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700']">
                                        {{ req.action }}
                                    </span>
                                    <span class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate">{{ req.target_name }}</span>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-1">
                                    {{ requestCurrentLabel(req) }} → <strong>{{ req.requested_position }}</strong><span v-if="req.supervisor_department"> ({{ req.supervisor_department }})</span>
                                    <span v-if="req.requested_role"> · {{ req.requested_role }}</span>
                                </p>
                                <p class="text-[10px] text-slate-400 mt-0.5">
                                    by {{ req.requested_by_name }} ({{ req.requested_by_position }})
                                    <span v-if="req.reason"> — “{{ req.reason }}”</span>
                                </p>
                            </button>
                        </div>
                    </section>
                    </Teleport>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex flex-col lg:flex-row gap-3 bg-slate-50/50 dark:bg-slate-800/50">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input v-model="search" type="text" placeholder="Search name, email, employee ID..."
                        class="w-full pl-9 pr-4 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 text-slate-700 dark:text-slate-200 placeholder-slate-400" />
                </div>
                <div class="flex flex-wrap gap-2 [&>select]:flex-1 [&>select]:sm:flex-none [&>select]:min-w-[130px]">
                    <select v-model="roleFilter" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        <option value="">All modules</option>
                        <option v-for="m in modules" :key="m.key" :value="m.key">{{ m.key }} — {{ m.name }}</option>
                    </select>
                    <select v-model="statusFilter" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="disabled">Disabled</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="it-responsive-table w-full text-left min-w-[680px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900/40 border-b border-slate-100 dark:border-slate-700">
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Employee</th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Module · Position</th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Grants</th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Manage</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="u in filteredUsers" :key="u.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/60 transition-colors">
                            <td data-label="Employee" class="px-4 sm:px-6 py-3 sm:py-3.5">
                                <div class="flex items-center gap-3">
                                    <span class="w-9 h-9 shrink-0 rounded-full bg-blue-600/10 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xs font-black">
                                        {{ initials(u.name) }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2 flex-wrap">
                                            {{ u.name }}
                                            <span v-if="requestsForUser(u.id).length" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 text-[10px] font-black">
                                                <Bell class="w-3 h-3" /> {{ requestsForUser(u.id).length }} command
                                            </span>
                                        </p>
                                        <p class="text-[11px] text-slate-400">{{ u.email }}{{ u.employee_id ? ` · ${u.employee_id}` : '' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Module / Position" class="px-4 sm:px-6 py-3 sm:py-3.5">
                                <div class="flex flex-wrap gap-1 max-w-[320px]">
                                    <span v-for="mod in userModuleList(u)" :key="mod"
                                        class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[10px] font-black">{{ mod }}</span>
                                </div>
                                <div class="mt-1 text-xs text-slate-500 capitalize">{{ displayPositionLabel(u) }}</div>
                                <span v-if="u.is_manufacturing_supervisor" class="ml-1.5 px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 text-[10px] font-black uppercase">Supervisor{{ (u.supervisor_department || manDeptOf(u)) ? ` · ${u.supervisor_department || manDeptOf(u)}` : '' }}</span>
                                <span v-else-if="u.role === 'MAN' && u.manufacturing_role" class="ml-1.5 text-xs text-slate-500 capitalize">· {{ formatManRole(u.manufacturing_role) }}</span>
                                <span v-if="logRoleLabel(u)" :class="logRolePill(u)"
                                    class="ml-1.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wide">
                                    <component :is="u.log_role === 'driver' ? Truck : Users" class="w-3 h-3" />
                                    {{ logRoleLabel(u) }}
                                </span>
                                <div v-if="u.role === 'MAN'" class="mt-1">
                                    <span v-if="manDeptOf(u)" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 text-[10px] font-black uppercase tracking-wide">
                                        {{ manDeptLabel(manDeptOf(u)) }}
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-400 text-[10px] font-black uppercase tracking-wide">
                                        No dept assigned
                                    </span>
                                </div>
                            </td>
                            <td data-label="Status" class="px-4 sm:px-6 py-3 sm:py-3.5">
                                <span :class="['px-2.5 py-1 rounded-full text-[10px] font-black uppercase', statusPill(u)]">
                                    {{ accountStatus(u) }}
                                </span>
                                <p v-if="u.suspended_until" class="text-[10px] text-slate-400 mt-0.5">until {{ new Date(u.suspended_until).toLocaleString() }}</p>
                            </td>
                            <td data-label="Grants" class="px-4 sm:px-6 py-3 sm:py-3.5 text-xs text-slate-500 whitespace-nowrap">
                                {{ userModuleList(u).length }} modules · {{ pageCount(u.id) }} pages
                            </td>
                            <td data-label="Manage" class="px-4 sm:px-6 py-3 sm:py-3.5 text-right">
                                <button @click="openManage(u)"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1.5 text-xs font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700 active:scale-95 shadow-sm shadow-blue-600/20">
                                    <Users class="w-3.5 h-3.5" /> Manage
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="filteredUsers.length === 0" class="py-14 text-center">
                    <div class="mx-auto w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-400 mb-3">
                        <Search class="w-5 h-5" />
                    </div>
                    <p class="text-sm font-bold text-slate-500 dark:text-slate-300">No employees match.</p>
                    <p class="text-xs text-slate-400 mt-1">Try a different search or clear the filters.</p>
                </div>
            </div>
        </div>

        <!-- Manage drawer (teleported: fullscreen overlay above all nav layers,
             viewport-height panel with its own scroll so nothing gets cut off) -->
        <Teleport to="body">
        <div v-if="managing" class="fixed inset-0 z-[70] flex justify-end bg-black/50 backdrop-blur-sm" @click.self="managing = null">
            <div class="bg-white dark:bg-slate-800 w-full sm:max-w-2xl h-dvh max-h-dvh overflow-y-auto overscroll-contain border-l border-slate-200 dark:border-slate-700 shadow-2xl flex flex-col">
                <!-- Sticky header + tabs: the Account / Position / Modules & Pages
                     buttons stay visible while the (long) access list scrolls underneath. -->
                <div class="sticky top-0 z-10 bg-white dark:bg-slate-800">
                <div class="p-4 sm:p-6 pb-3 flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3 min-w-0">
                        <span class="w-12 h-12 shrink-0 rounded-2xl bg-blue-600 text-white flex items-center justify-center text-sm font-black">
                            {{ initials(managing.name) }}
                        </span>
                        <div class="min-w-0">
                            <p class="text-[11px] font-mono font-bold text-slate-400">{{ managing.role }} · {{ displayPositionLabel(managing) }}<span v-if="managing.is_manufacturing_supervisor && (managing.supervisor_department || manDeptOf(managing))"> · {{ managing.supervisor_department || manDeptOf(managing) }} dept</span><span v-else-if="managing.role === 'MAN' && managing.manufacturing_role"> · {{ formatManRole(managing.manufacturing_role) }}</span><span v-if="managing.role === 'MAN' && !managing.is_manufacturing_supervisor && manDeptOf(managing)"> · {{ manDeptOf(managing) }} dept</span></p>
                            <h2 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2 flex-wrap">
                                {{ managing.name }}
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase', statusPill(managing)]">{{ accountStatus(managing) }}</span>
                            </h2>
                            <p class="text-xs text-slate-400 truncate">{{ managing.email }}</p>
                            <div v-if="managing.role === 'MAN'" class="mt-1.5">
                                <span v-if="manDeptOf(managing)" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 text-[10px] font-black uppercase tracking-wide">
                                    {{ manDeptLabel(manDeptOf(managing)) }}
                                </span>
                                <span v-else class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-400 text-[10px] font-black uppercase tracking-wide">
                                    No dept assigned
                                </span>
                            </div>
                        </div>
                    </div>
                    <button @click="managing = null" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700"><X class="w-5 h-5" /></button>
                </div>

                <div class="px-4 sm:px-6 pt-1 flex gap-2 border-b border-slate-100 dark:border-slate-700 overflow-x-auto">
                    <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
                        :class="['shrink-0 px-4 py-2.5 text-xs font-black uppercase tracking-wider border-b-2 -mb-px transition-colors',
                            activeTab === t.key ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-400 hover:text-slate-600']">
                        {{ t.label }}
                        <span v-if="t.key === 'position' && requestsForUser(managing.id).length" class="ml-1 px-1.5 py-0.5 rounded-full bg-indigo-600 text-white text-[10px]">{{ requestsForUser(managing.id).length }}</span>
                        <span v-if="t.key === 'access' && accessDirty" class="ml-1.5 inline-block w-2 h-2 rounded-full bg-amber-500 align-middle" title="Unsaved changes"></span>
                    </button>
                </div>
                </div>

                <div class="p-4 sm:p-6 flex-1 flex flex-col">
                    <!-- ACCOUNT (unchanged) -->
                    <div v-if="activeTab === 'account'" class="space-y-3">
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-sm">
                            <p class="font-bold text-slate-700 dark:text-slate-200">Current status:
                                <span class="uppercase">{{ accountStatus(managing) }}</span>
                            </p>
                            <p v-if="managing.suspended_until" class="text-xs text-slate-400 mt-1">Suspended until {{ new Date(managing.suspended_until).toLocaleString() }}</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <button @click="setStatus('enable')" :disabled="statusForm.processing"
                                class="inline-flex items-center justify-center gap-2 py-2.5 text-sm font-bold text-white bg-emerald-600 rounded-xl hover:bg-emerald-700 active:scale-95 disabled:opacity-50">
                                <ShieldCheck class="w-4 h-4" /> Enable
                            </button>
                            <button @click="setStatus('disable')" :disabled="statusForm.processing"
                                class="inline-flex items-center justify-center gap-2 py-2.5 text-sm font-bold text-white bg-red-600 rounded-xl hover:bg-red-700 active:scale-95 disabled:opacity-50">
                                <ShieldOff class="w-4 h-4" /> Disable / Block
                            </button>
                        </div>
                        <p v-if="positionForm.errors.error || positionForm.errors.position || positionForm.errors.role || positionForm.errors.supervisor_department"
                            class="text-xs font-bold text-red-600 dark:text-red-400">
                            {{ positionForm.errors.error || positionForm.errors.position || positionForm.errors.role || positionForm.errors.supervisor_department }}
                        </p>
                        <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800 space-y-3">
                            <p class="text-xs font-black uppercase tracking-widest text-amber-700 dark:text-amber-400 inline-flex items-center gap-1.5">
                                <PauseCircle class="w-4 h-4" /> Temporary suspension
                            </p>
                            <input v-model="suspendUntil" type="datetime-local"
                                class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <button @click="setStatus('suspend')" :disabled="statusForm.processing || !suspendUntil"
                                    class="py-2.5 text-sm font-bold text-white bg-amber-600 rounded-xl hover:bg-amber-700 active:scale-95 disabled:opacity-50">
                                    Suspend Until Date
                                </button>
                                <button @click="setStatus('restore')" :disabled="statusForm.processing"
                                    class="inline-flex items-center justify-center gap-2 py-2.5 text-sm font-bold text-white bg-blue-600 rounded-xl hover:bg-blue-700 active:scale-95 disabled:opacity-50">
                                    <RotateCcw class="w-4 h-4" /> Restore Access
                                </button>
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-400">Disabled accounts cannot log in anywhere. Suspended accounts show their release date. Every action is written to Access Logs.</p>
                    </div>

                    <!-- POSITION (direct IT control) -->
                    <div v-if="activeTab === 'position'" class="space-y-4">
                        <div v-if="managing.is_executive" class="p-4 rounded-xl bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-200 font-bold">
                            CEO and COO accounts are protected from IT position changes.
                        </div>
                        <div v-else-if="requestsForUser(managing.id).length" class="space-y-2">
                            <p class="text-xs font-black uppercase tracking-widest text-indigo-600">Optional executive requests</p>
                            <button v-for="req in requestsForUser(managing.id)" :key="req.id" @click="selectRequest(req)"
                                :class="['w-full text-left p-3 rounded-xl border text-xs transition-colors',
                                    Number(selectedRequestId) === Number(req.id)
                                        ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-900/20'
                                        : 'border-slate-200 dark:border-slate-700 hover:border-indigo-300']">
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase', ['promote', 'assign_supervisor'].includes(req.action) ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700']">
                                    {{ req.action }}
                                </span>
                                <span class="ml-2 font-bold text-slate-700 dark:text-slate-200">{{ requestCurrentLabel(req) }} → {{ req.requested_position }}<span v-if="req.supervisor_department"> ({{ req.supervisor_department }})</span></span>
                                <span v-if="req.requested_role" class="ml-1 font-mono text-slate-400">· {{ req.requested_role }}</span>
                                <span class="block text-[11px] text-slate-400 mt-1">by {{ req.requested_by_name }} ({{ req.requested_by_position }})<span v-if="req.reason"> — “{{ req.reason }}”</span></span>
                            </button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-bold text-slate-500 uppercase">Position</label>
                                <select v-model="positionForm.position" class="mt-1 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                                    <option v-for="p in assignablePositions" :key="p" :value="p">{{ p.replace('_', ' ') }}</option>
                                </select>
                            </div>
                            <div v-if="positionForm.position === 'supervisor'">
                                <label class="text-xs font-bold text-slate-500 uppercase">Department seat *</label>
                                <select v-model="positionForm.supervisor_department" class="mt-1 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                                    <option value="" disabled>Select department…</option>
                                    <option v-for="d in ['knitting', 'dyeing', 'finishing', 'maintenance', 'boiler']" :key="d" :value="d">{{ d }}</option>
                                </select>
                            </div>
                            <div v-else>
                                <label class="text-xs font-bold text-slate-500 uppercase">Home Module</label>
                                <select v-model="positionForm.role" class="mt-1 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                                    <option v-for="r in assignableRoles" :key="r" :value="r">{{ r }}</option>
                                </select>
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-400">IT can promote or demote employees directly; no CEO command is required. CEO and COO accounts are excluded. Demoting to staff clears module and page grants. Promoting to manager clears page grants. Supervisor seats are MAN-only, limited to one per department. One manager per module and one secretary org-wide are enforced.</p>
                        <div v-if="!managing.is_executive" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <button @click="savePosition" :disabled="positionForm.processing"
                                class="py-2.5 text-sm font-bold text-white bg-blue-600 rounded-xl hover:bg-blue-700 active:scale-95 disabled:opacity-50">
                                {{ positionForm.processing ? 'Saving…' : 'Save Position' }}
                            </button>
                        </div>
                    </div>

                    <!-- MODULES & PAGES (combined) -->
                    <div v-if="activeTab === 'access'" class="flex-1 flex flex-col gap-4">
                        <!-- Info banner -->
                        <div class="p-3 rounded-xl bg-indigo-50 dark:bg-indigo-900/10 border border-indigo-200 dark:border-indigo-800 text-[11px] text-indigo-700 dark:text-indigo-300 font-bold leading-relaxed">
                            <template v-if="isElevatedUser(managing)">
                                Home module <strong>{{ rootOf(managing) }}</strong> is always included. Switch add-on modules on or off, then open a module to set each page to Disable, View or Edit. New add-on pages start DISABLED.
                            </template>
                            <template v-else>
                                Staff keep their home module <strong>{{ rootOf(managing) }}</strong> and see only its pages. Open the module to set each page to Disable, View or Edit.
                            </template>
                        </div>

                        <!-- Summary + expand all -->
                        <div class="flex items-center justify-between gap-3 flex-wrap">
                            <div class="flex flex-wrap items-center gap-2 text-[11px] font-black">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                    <Layers class="w-3.5 h-3.5" /> {{ grantedModuleCount }} / {{ accessModules.length }} modules
                                </span>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300">
                                    <ShieldCheck class="w-3.5 h-3.5" /> {{ totalEnabledPages }} pages enabled
                                </span>
                            </div>
                            <button v-if="accessModules.length" @click="toggleAllOpen"
                                class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:text-blue-700">
                                <ChevronsUpDown class="w-3.5 h-3.5" />
                                {{ allOpen ? 'Collapse all' : 'Expand all' }}
                            </button>
                        </div>

                        <div v-if="accessModules.length === 0" class="p-6 rounded-xl border border-dashed border-slate-300 dark:border-slate-600 text-center text-xs text-slate-400 font-bold">
                            No modules available for this employee.
                        </div>

                        <!-- Module accordion -->
                        <div v-else class="border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden divide-y divide-slate-200 dark:divide-slate-700">
                            <div v-for="mod in accessModules" :key="mod.key">
                                <!-- Module header -->
                                <div :class="['flex items-center gap-3 px-3 sm:px-4 py-3 transition-colors',
                                    openModules[mod.upper] ? 'bg-slate-50 dark:bg-slate-900/40' : 'hover:bg-slate-50/70 dark:hover:bg-slate-800/60']">
                                    <!-- Grant switch -->
                                    <button type="button" role="switch" :aria-checked="mod.granted"
                                        :disabled="mod.home" @click="toggleModule(mod.key)"
                                        :title="mod.home ? 'Home module — always granted' : (mod.granted ? 'Revoke module' : 'Grant module')"
                                        :class="['relative w-10 h-6 shrink-0 rounded-full transition-colors',
                                            mod.granted ? 'bg-blue-600' : 'bg-slate-300 dark:bg-slate-600',
                                            mod.home ? 'opacity-70 cursor-not-allowed' : 'cursor-pointer']">
                                        <span :class="['absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform', mod.granted ? 'translate-x-4' : 'translate-x-0']"></span>
                                    </button>

                                    <!-- Clickable title → opens dropdown -->
                                    <button type="button" @click="toggleOpen(mod.upper)" :aria-expanded="!!openModules[mod.upper]"
                                        class="flex-1 min-w-0 flex items-center justify-between gap-3 text-left">
                                        <span class="min-w-0">
                                            <span class="flex items-center gap-2 flex-wrap">
                                                <span class="text-sm font-black text-slate-900 dark:text-white">{{ mod.key }}</span>
                                                <span v-if="mod.name" class="text-xs font-semibold text-slate-500 dark:text-slate-400 truncate">{{ mod.name }}</span>
                                                <span v-if="mod.home" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300 text-[9px] font-black uppercase">
                                                    <Lock class="w-2.5 h-2.5" /> Home
                                                </span>
                                            </span>
                                            <span class="block text-[11px] text-slate-400 mt-0.5">
                                                <template v-if="mod.granted">{{ mod.enabled }} of {{ mod.pages.length }} pages enabled</template>
                                                <template v-else>Not granted · {{ mod.pages.length }} pages</template>
                                            </span>
                                        </span>
                                        <ChevronDown :class="['w-4 h-4 shrink-0 text-slate-400 transition-transform duration-300', openModules[mod.upper] ? 'rotate-180' : '']" />
                                    </button>
                                </div>

                                <!-- Dropdown: pages of this module -->
                                <div :class="['grid transition-[grid-template-rows] duration-300 ease-out', openModules[mod.upper] ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]']">
                                    <div class="overflow-hidden">
                                        <div class="px-3 sm:px-4 pb-4 pt-3 bg-white dark:bg-slate-800 space-y-3">
                                            <div v-if="mod.key === 'MAN' && manPlantDashboardWarning" class="p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-[11px] font-bold text-amber-700 dark:text-amber-300 leading-relaxed">
                                                Role pages are enabled but Dashboard is DISABLED. The employee can open those pages by URL,
                                                but the normal staff landing shows a limited-access notice. Enable Dashboard (VIEW or EDIT)
                                                so the account can sign in to its plant workspace.
                                            </div>

                                            <div v-if="!mod.granted" class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-dashed border-slate-300 dark:border-slate-600 text-[11px] font-bold text-slate-500">
                                                Module is off. Setting any page to VIEW or EDIT grants the module automatically.
                                            </div>

                                            <div v-if="mod.pages.length === 0" class="text-xs text-slate-400 font-bold">No pages defined for this module.</div>

                                            <template v-else>
                                                <!-- Bulk actions -->
                                                <div class="flex flex-wrap items-center gap-1.5 text-[10px] font-black uppercase tracking-wide">
                                                    <span class="text-slate-400 mr-1">Set all:</span>
                                                    <button type="button" @click="setAllLevels(mod, 'disabled')" class="px-2.5 py-1 rounded-md bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">Disable</button>
                                                    <button type="button" @click="setAllLevels(mod, 'view')" class="px-2.5 py-1 rounded-md bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 hover:bg-amber-200 dark:hover:bg-amber-900/50">View</button>
                                                    <button type="button" @click="setAllLevels(mod, 'edit')" class="px-2.5 py-1 rounded-md bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200 dark:hover:bg-emerald-900/50">Edit</button>
                                                </div>

                                                <ul class="divide-y divide-slate-100 dark:divide-slate-700/50 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
                                                    <li v-for="p in mod.pages" :key="p.key" class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-3.5 py-2.5">
                                                        <div class="min-w-0">
                                                            <p class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ p.label }}</p>
                                                            <p class="text-[10px] font-mono text-slate-400">{{ p.key }}</p>
                                                        </div>
                                                        <div class="inline-flex shrink-0 rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700 text-[11px] font-black self-start sm:self-auto">
                                                            <button type="button" @click="setLevel(mod.upper, p.key, 'disabled')" :aria-pressed="levelOf(mod.upper, p.key) === 'disabled'"
                                                                :class="['px-3 py-1.5 transition-colors', levelOf(mod.upper, p.key) === 'disabled' ? 'bg-slate-500 text-white' : 'text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700']">DISABLE</button>
                                                            <button type="button" @click="setLevel(mod.upper, p.key, 'view')" :aria-pressed="levelOf(mod.upper, p.key) === 'view'"
                                                                :class="['px-3 py-1.5 transition-colors', levelOf(mod.upper, p.key) === 'view' ? 'bg-amber-500 text-white' : 'text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700']">VIEW</button>
                                                            <button type="button" @click="setLevel(mod.upper, p.key, 'edit')" :aria-pressed="levelOf(mod.upper, p.key) === 'edit'"
                                                                :class="['px-3 py-1.5 transition-colors', levelOf(mod.upper, p.key) === 'edit' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700']">EDIT</button>
                                                        </div>
                                                    </li>
                                                </ul>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <section v-if="supervisorStaffRoles.length" class="mt-6 space-y-4">
                            <div class="rounded-2xl border border-indigo-200 dark:border-indigo-800 bg-indigo-50/70 dark:bg-indigo-950/30 p-4">
                                <h3 class="text-sm font-black text-indigo-900 dark:text-indigo-100">Department staff page access</h3>
                                <p class="mt-1 text-xs text-indigo-800 dark:text-indigo-200">Control the pages this supervisor can access in each staff role workspace under their department. These settings apply only to this supervisor.</p>
                            </div>
                            <article v-for="role in supervisorStaffRoles" :key="role.key" class="rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                                <header class="p-4 bg-slate-50 dark:bg-slate-800/70">
                                    <div class="flex flex-wrap justify-between gap-2">
                                        <div>
                                            <h4 class="text-sm font-black text-slate-900 dark:text-white">{{ role.label }} pages</h4>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Controls the pages this supervisor can open in the {{ role.label }} workspace.</p>
                                        </div>
                                        <div class="flex flex-wrap gap-1 self-start text-[10px] font-black uppercase">
                                            <button v-for="level in ['disabled', 'view', 'edit']" :key="level" type="button" @click="setAllSupervisorStaffLevels(role.key, level)" class="px-2.5 py-1 rounded-md bg-white dark:bg-slate-700 text-slate-600 dark:text-slate-200 border border-slate-200 dark:border-slate-600">All {{ level === 'disabled' ? 'disable' : level }}</button>
                                        </div>
                                    </div>
                                </header>
                                <ul class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                    <li v-for="(label, page) in role.pages" :key="page" class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-4 py-3">
                                        <div><p class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ label }}</p><p class="text-[10px] font-mono text-slate-400">{{ page }}</p></div>
                                        <div class="inline-flex shrink-0 rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700 text-[11px] font-black">
                                            <button v-for="level in ['disabled', 'view', 'edit']" :key="level" type="button" @click="setSupervisorStaffLevel(role.key, page, level)" :aria-pressed="(supervisorStaffGrants[role.key]?.[page] || 'disabled') === level" :class="['px-3 py-1.5 transition-colors', (supervisorStaffGrants[role.key]?.[page] || 'disabled') === level ? (level === 'disabled' ? 'bg-slate-500' : level === 'view' ? 'bg-amber-500' : 'bg-emerald-600') + ' text-white' : 'text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700']">{{ level === 'disabled' ? 'DISABLE' : level.toUpperCase() }}</button>
                                        </div>
                                    </li>
                                </ul>
                            </article>
                        </section>

                        <p class="text-[11px] text-slate-400">Saving grants new modules with every page DISABLED, then applies the page levels you chose above. Switching a module off also revokes all of its page grants.</p>

                        <p v-if="accessSaveError" role="alert" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-bold text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ accessSaveError }}</p>

                        <!-- Sticky save bar -->
                        <div class="sticky bottom-0 -mx-4 sm:-mx-6 -mb-4 sm:-mb-6 mt-auto px-4 sm:px-6 py-3 bg-white/95 dark:bg-slate-800/95 backdrop-blur border-t border-slate-100 dark:border-slate-700 flex items-center justify-between gap-3">
                            <span :class="['text-xs font-bold', accessDirty ? 'text-amber-600' : 'text-slate-400']">
                                {{ accessDirty ? 'You have unsaved changes' : 'No changes yet' }}
                            </span>
                            <button @click="saveAccess" :disabled="accessForm.processing || !accessDirty"
                                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm font-bold text-white bg-blue-600 rounded-xl hover:bg-blue-700 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
                                <Save class="w-4 h-4" />
                                {{ accessForm.processing ? 'Saving…' : 'Save Modules & Pages' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </Teleport>


    </AuthenticatedLayout>
</template>
