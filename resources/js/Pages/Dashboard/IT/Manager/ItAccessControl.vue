<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Search, X, ShieldCheck, ShieldOff, PauseCircle, RotateCcw, Save, Users, Bell, CheckCircle2, Ban, Lock } from 'lucide-vue-next';

const props = defineProps({
    users: { type: Array, default: () => [] },
    modules: { type: Array, default: () => [] },
    modulePages: { type: Object, default: () => ({}) },
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
const selectedRequestId = ref(null);
const confirmPosition = ref(null);

const statusForm = useForm({ user_id: '', action: '', suspended_until: '' });
const positionForm = useForm({ user_id: '', position: '', role: '', supervisor_department: '', request_id: null });
const modulesForm = useForm({ user_id: '', modules: [] });
const pagesForm = useForm({ user_id: '', grants: [] });

// Local editable page grants: { [module]: { [page]: 'disabled'|'view'|'edit' } }
const pageGrants = ref({});

const ELEVATED = ['manager', 'secretary', 'special_officer', 'vice_president'];

const isElevatedUser = (u) => {
    if (!u) return false;
    return !!u.is_manufacturing_supervisor || ELEVATED.includes(u.position) || !!u.is_elevated;
};

const rootOf = (u) => {
    if (!u) return null;
    if (u.root_module) return u.root_module;
    return u.role || null;
};

// Higher-ups with automatic default-on root access (secretary,
// special_officer, manager, manufacturing supervisor). Their root pages can
// never be disabled — IT chooses view vs edit only. Mirrors backend
// lockedRootModuleFor().
const isHigherUp = (u) => {
    if (!u) return false;
    return !!u.is_manufacturing_supervisor || ['manager', 'secretary', 'special_officer'].includes(u.position);
};

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
    knitting_yarn: 'knitting', knitting_mechanic: 'knitting',
    dyeing_color: 'dyeing', dyeing_fabric_softener: 'dyeing', dyeing_squeezer: 'dyeing',
    dyeing_ironing: 'dyeing', dyeing_packaging: 'dyeing', dyeing_lab_chemist: 'dyeing',
    checker_quality: 'finishing',
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
const formatManRole = (role) => (role ? String(role).replace(/_/g, ' ') : '');

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

const lockedRootOf = (u) => {
    if (!isHigherUp(u)) return null;
    const root = rootOf(u);
    return root ? String(root).toUpperCase() : null;
};

// Whether the currently shown module tab is a higher-up locked root.
const isPageLocked = (moduleKey) => {
    if (!managing.value || !moduleKey) return false;
    const locked = lockedRootOf(managing.value);
    return !!locked && String(moduleKey).toUpperCase() === locked;
};

const requestsForUser = (userId) => (props.pendingRequests || []).filter((r) => Number(r.target_user_id) === Number(userId));

const openManage = (user) => {
    managing.value = user;
    activeTab.value = 'account';
    suspendUntil.value = '';
    selectedRequestId.value = null;
    confirmPosition.value = null;

    statusForm.user_id = user.id;
    positionForm.user_id = user.id;
    positionForm.position = user.position;
    positionForm.role = user.role;
    positionForm.supervisor_department = user.supervisor_department || '';
    positionForm.request_id = null;
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
    // A higher-up's locked root can never be disabled: legacy 'disabled'
    // rows there open as 'view' so DISABLE is never the selected state.
    const locked = lockedRootOf(user);
    Object.entries(props.modulePages).forEach(([module, pages]) => {
        grants[module] = {};
        const isLockedModule = !!locked && String(module).toUpperCase() === locked;
        Object.entries(pages || {}).forEach(([key]) => {
            const pageKey = String(key).toLowerCase();
            let lvl = existing[module]?.[pageKey] || 'disabled';
            if (isLockedModule && lvl === 'disabled') lvl = 'view';
            grants[module][pageKey] = lvl;
        });
    });
    pageGrants.value = grants;
};

// Notification → open the employee's manage modal on the Position tab with
// the command pre-selected. No confirmation modal here — IT reviews first,
// then clicks "Apply Command…" to officially promote/demote.
const openRequest = (req) => {
    const user = props.users.find((u) => Number(u.id) === Number(req.target_user_id));
    if (!user) return;
    openManage(user);
    activeTab.value = 'position';
    selectedRequestId.value = req.id;
    positionForm.position = req.requested_position;
    positionForm.role = req.requested_role || user.role;
    positionForm.supervisor_department = req.supervisor_department || '';
    positionForm.request_id = req.id;
    confirmPosition.value = null;
    showNotifications.value = false;
};

const selectRequest = (req) => {
    selectedRequestId.value = req.id;
    positionForm.position = req.requested_position;
    positionForm.role = req.requested_role || managing.value?.role;
    positionForm.supervisor_department = req.supervisor_department || '';
    positionForm.request_id = req.id;
    confirmPosition.value = req;
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
            (manDeptOf(u) || '').toLowerCase().includes(q)
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

const askPositionConfirm = () => {
    if (!selectedRequestId.value) return;
    const req = (props.pendingRequests || []).find((r) => Number(r.id) === Number(selectedRequestId.value));
    if (req) confirmPosition.value = req;
};

const savePosition = () => {
    positionForm.post(route('it.access-control.position'), {
        preserveScroll: true,
        onSuccess: () => { managing.value = null; confirmPosition.value = null; },
    });
};

const rejectSelected = () => {
    if (!selectedRequestId.value) return;
    router.post(route('it.access-control.requests.reject', selectedRequestId.value), {}, {
        preserveScroll: true,
        onSuccess: () => { managing.value = null; confirmPosition.value = null; selectedRequestId.value = null; },
    });
};

const saveModules = () => {
    modulesForm.post(route('it.access-control.modules'), {
        preserveScroll: true,
        onSuccess: () => { managing.value = null; },
    });
};

const savePages = () => {
    const grants = [];
    Object.entries(pageGrants.value).forEach(([module, pages]) => {
        Object.entries(pages).forEach(([page, level]) => {
            if (['view', 'edit', 'disabled'].includes(level)) {
                grants.push({ module, page, level });
            }
        });
    });
    pagesForm.grants = grants;
    pagesForm.post(route('it.access-control.pages'), {
        preserveScroll: true,
        onSuccess: () => { managing.value = null; },
    });
};

const tabs = [
    { key: 'account', label: 'Account' },
    { key: 'position', label: 'Position' },
    { key: 'modules', label: 'Modules' },
    { key: 'pages', label: 'Page Access' },
];

// ── Modules tab rules ─────────────────────────────────────────────
// Staff (non-elevated): ONLY the root module is shown, auto-checked + locked.
// Elevated (manager/supervisor/secretary/special_officer/VP): root locked +
// every module shown so IT can grant extras.
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

const toggleModule = (modKey) => {
    if (isRootLocked(modKey)) return;
    const i = modulesForm.modules.indexOf(modKey);
    if (i === -1) {
        modulesForm.modules.push(modKey);
    } else {
        modulesForm.modules.splice(i, 1);
        // Revoking a module revokes its pages too: reset every page of the
        // removed module to DISABLED so stale view/edit levels can't linger,
        // inflate counts, or be resubmitted (and re-provision the module) on
        // the next Page Access save.
        const gKey = Object.keys(pageGrants.value).find((k) => String(k).toUpperCase() === String(modKey).toUpperCase());
        if (gKey && pageGrants.value[gKey]) {
            Object.keys(pageGrants.value[gKey]).forEach((p) => {
                pageGrants.value[gKey][p] = 'disabled';
            });
        }
    }
};

// ── Page Access tab rules ─────────────────────────────────────────
// Staff: ONLY home-module pages are displayed.
// Elevated: root module + every module checked in the Modules tab.
const visiblePageModules = computed(() => {
    if (!managing.value) return [];
    if (!isElevatedUser(managing.value)) {
        return [managing.value.role].filter(Boolean).map((m) => String(m).toUpperCase());
    }
    const root = rootOf(managing.value);
    const checked = (modulesForm.modules || []).map((m) => String(m).toUpperCase());
    return [...new Set([root, ...checked].filter(Boolean).map((m) => String(m).toUpperCase()))];
});

const moduleTabKeys = computed(() => Object.keys(props.modulePages).filter((k) => visiblePageModules.value.includes(String(k).toUpperCase())));
const activeModule = ref('');
const currentModule = computed(() => {
    if (activeModule.value && moduleTabKeys.value.includes(activeModule.value)) return activeModule.value;
    return moduleTabKeys.value[0] || '';
});

// Normalized [{key, label}] rows for the active module tab.
const currentPages = computed(() => {
    const pages = props.modulePages[currentModule.value] || {};
    return Object.entries(pages).map(([key, val]) => ({
        key: String(key).toLowerCase(),
        label: typeof val === 'string' ? val : val.label,
    }));
});

// Plant-staff trap guard: every MAN role workspace (including its Dashboard
// tab) is gated by the Production page on the backend, so enabling Dashboard
// while Production stays DISABLED leaves the account with no openable page —
// login bounces into a "production disabled" 403. Supervisors are exempt:
// their overview dashboard is dashboard-gated and works standalone.
const manPlantDashboardWarning = computed(() => {
    const u = managing.value;
    if (!u || u.role !== 'MAN' || u.position !== 'staff' || u.is_manufacturing_supervisor) return false;
    if (String(currentModule.value || '').toLowerCase() !== 'man') return false;
    const gKey = Object.keys(pageGrants.value).find((k) => String(k).toLowerCase() === 'man');
    const grants = (gKey && pageGrants.value[gKey]) || {};
    const dashboardOn = ['view', 'edit'].includes(grants.dashboard);
    const productionOn = ['view', 'edit'].includes(grants.production);
    return dashboardOn && !productionOn;
});

const setLevel = (page, level) => {
    // Locked root pages of higher-ups can never be disabled.
    if (level === 'disabled' && isPageLocked(currentModule.value)) return;
    if (!pageGrants.value[currentModule.value]) pageGrants.value[currentModule.value] = {};
    pageGrants.value[currentModule.value][page] = level;
};

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
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    IT Access <span class="text-blue-600">Control</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">
                    Organization-wide accounts, promotion (by executive command), modules and per-page view/edit/disabled grants.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <div class="flex flex-wrap gap-2 text-xs font-black">
                    <span class="px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">{{ counts.total }} total</span>
                    <span class="px-3 py-1.5 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">{{ counts.active }} active</span>
                    <span class="px-3 py-1.5 rounded-full bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">{{ counts.disabled }} disabled</span>
                    <span class="px-3 py-1.5 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">{{ counts.suspended }} suspended</span>
                </div>
                <!-- Executive commands notification -->
                <div class="relative">
                    <button @click="showNotifications = !showNotifications"
                        class="relative inline-flex items-center gap-1.5 px-3 py-2 text-xs font-black text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 active:scale-95">
                        <Bell class="w-4 h-4" />
                        Commands
                        <span v-if="pendingRequests.length" class="absolute -top-2 -right-2 min-w-[20px] h-5 px-1 rounded-full bg-red-600 text-white text-[10px] font-black flex items-center justify-center ring-2 ring-white dark:ring-slate-900">
                            {{ pendingRequests.length }}
                        </span>
                    </button>
                    <div v-if="showNotifications" class="absolute right-0 mt-2 w-96 max-w-[90vw] bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-2xl z-40 overflow-hidden">
                        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                            <p class="text-xs font-black uppercase tracking-widest text-slate-500">President / VP Commands</p>
                            <button @click="showNotifications = false" class="text-slate-400 hover:text-slate-600"><X class="w-4 h-4" /></button>
                        </div>
                        <div v-if="pendingRequests.length === 0" class="px-4 py-8 text-center text-xs text-slate-400 font-bold">
                            No pending commands. Position changes require a President/VP request.
                        </div>
                        <div v-else class="max-h-96 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
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
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex flex-col lg:flex-row gap-3 bg-slate-50/50 dark:bg-slate-800/50">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input v-model="search" type="text" placeholder="Search name, email, employee ID..."
                        class="w-full pl-9 pr-4 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200 placeholder-slate-400" />
                </div>
                <div class="flex flex-wrap gap-2 [&>select]:flex-1 [&>select]:sm:flex-none [&>select]:min-w-[130px]">
                    <select v-model="roleFilter" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                        <option value="">All modules</option>
                        <option v-for="m in modules" :key="m.key" :value="m.key">{{ m.key }} — {{ m.name }}</option>
                    </select>
                    <select v-model="statusFilter" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="disabled">Disabled</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left min-w-[680px]">
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
                        <tr v-for="u in filteredUsers" :key="u.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/60">
                            <td class="px-4 sm:px-6 py-3 sm:py-3.5">
                                <p class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    {{ u.name }}
                                    <span v-if="requestsForUser(u.id).length" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 text-[10px] font-black">
                                        <Bell class="w-3 h-3" /> {{ requestsForUser(u.id).length }} command
                                    </span>
                                </p>
                                <p class="text-[11px] text-slate-400">{{ u.email }}{{ u.employee_id ? ` · ${u.employee_id}` : '' }}</p>
                            </td>
                            <td class="px-4 sm:px-6 py-3 sm:py-3.5">
                                <div class="flex flex-wrap gap-1 max-w-[320px]">
                                    <span v-for="mod in userModuleList(u)" :key="mod"
                                        class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[10px] font-black">{{ mod }}</span>
                                </div>
                                <div class="mt-1 text-xs text-slate-500 capitalize">{{ displayPositionLabel(u) }}</div>
                                <span v-if="u.is_manufacturing_supervisor" class="ml-1.5 px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 text-[10px] font-black uppercase">Supervisor{{ (u.supervisor_department || manDeptOf(u)) ? ` · ${u.supervisor_department || manDeptOf(u)}` : '' }}</span>
                                <span v-else-if="u.role === 'MAN' && u.manufacturing_role" class="ml-1.5 text-xs text-slate-500 capitalize">· {{ formatManRole(u.manufacturing_role) }}</span>
                                <div v-if="u.role === 'MAN'" class="mt-1">
                                    <span v-if="manDeptOf(u)" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 text-[10px] font-black uppercase tracking-wide">
                                        {{ manDeptLabel(manDeptOf(u)) }}
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-400 text-[10px] font-black uppercase tracking-wide">
                                        No dept assigned
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-3 sm:py-3.5">
                                <span :class="['px-2.5 py-1 rounded-full text-[10px] font-black uppercase', statusPill(u)]">
                                    {{ accountStatus(u) }}
                                </span>
                                <p v-if="u.suspended_until" class="text-[10px] text-slate-400 mt-0.5">until {{ new Date(u.suspended_until).toLocaleString() }}</p>
                            </td>
                            <td class="px-4 sm:px-6 py-3 sm:py-3.5 text-xs text-slate-500 whitespace-nowrap">
                                {{ userModuleList(u).length }} modules · {{ pageCount(u.id) }} pages
                            </td>
                            <td class="px-4 sm:px-6 py-3 sm:py-3.5 text-right">
                                <button @click="openManage(u)"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1.5 text-xs font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700 active:scale-95">
                                    <Users class="w-3.5 h-3.5" /> Manage
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="filteredUsers.length === 0" class="py-12 text-center text-sm text-slate-400 font-bold">No employees match.</div>
            </div>
        </div>

        <!-- Manage drawer (teleported: fullscreen overlay above all nav layers,
             viewport-height panel with its own scroll so nothing gets cut off) -->
        <Teleport to="body">
        <div v-if="managing" class="fixed inset-0 z-[70] flex justify-end bg-black/50 backdrop-blur-sm" @click.self="managing = null">
            <div class="bg-white dark:bg-slate-800 w-full sm:max-w-2xl h-dvh max-h-dvh overflow-y-auto overscroll-contain border-l border-slate-200 dark:border-slate-700 shadow-2xl flex flex-col">
                <!-- Sticky header + tabs: the Account / Position / Modules / Page
                     Access buttons stay visible while the (long) Page Access
                     list scrolls underneath. -->
                <div class="sticky top-0 z-10 bg-white dark:bg-slate-800">
                <div class="p-4 sm:p-6 pb-3 flex items-start justify-between">
                    <div>
                        <p class="text-[11px] font-mono font-bold text-slate-400">{{ managing.role }} · {{ displayPositionLabel(managing) }}<span v-if="managing.is_manufacturing_supervisor && (managing.supervisor_department || manDeptOf(managing))"> · {{ managing.supervisor_department || manDeptOf(managing) }} dept</span><span v-else-if="managing.role === 'MAN' && managing.manufacturing_role"> · {{ formatManRole(managing.manufacturing_role) }}</span><span v-if="managing.role === 'MAN' && !managing.is_manufacturing_supervisor && manDeptOf(managing)"> · {{ manDeptOf(managing) }} dept</span></p>
                        <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ managing.name }}</h2>
                        <p class="text-xs text-slate-400">{{ managing.email }}</p>
                        <div v-if="managing.role === 'MAN'" class="mt-1.5">
                            <span v-if="manDeptOf(managing)" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 text-[10px] font-black uppercase tracking-wide">
                                {{ manDeptLabel(manDeptOf(managing)) }}
                            </span>
                            <span v-else class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-400 text-[10px] font-black uppercase tracking-wide">
                                No dept assigned
                            </span>
                        </div>
                    </div>
                    <button @click="managing = null" class="p-1.5 text-slate-400 hover:text-slate-600"><X class="w-5 h-5" /></button>
                </div>

                <div class="px-4 sm:px-6 pt-1 flex gap-2 border-b border-slate-100 dark:border-slate-700 overflow-x-auto">
                    <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
                        :class="['shrink-0 px-4 py-2.5 text-xs font-black uppercase tracking-wider border-b-2 -mb-px transition-colors',
                            activeTab === t.key ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-400 hover:text-slate-600']">
                        {{ t.label }}
                        <span v-if="t.key === 'position' && requestsForUser(managing.id).length" class="ml-1 px-1.5 py-0.5 rounded-full bg-indigo-600 text-white text-[10px]">{{ requestsForUser(managing.id).length }}</span>
                    </button>
                </div>
                </div>

                <div class="p-4 sm:p-6 flex-1">
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

                    <!-- POSITION (command-gated) -->
                    <div v-if="activeTab === 'position'" class="space-y-4">
                        <div v-if="requestsForUser(managing.id).length === 0" class="p-4 rounded-xl bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 text-xs text-red-700 dark:text-red-300 font-bold">
                            No pending President/VP command for this employee. IT cannot promote or demote without an executive command.
                        </div>
                        <div v-else class="space-y-2">
                            <p class="text-xs font-black uppercase tracking-widest text-indigo-600">Executive commands (select one)</p>
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
                                <label class="text-xs font-bold text-slate-500 uppercase">Position (from command)</label>
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
                        <p class="text-[11px] text-slate-400">Position changes REQUIRE a pending executive command and must match it exactly. Demoting to staff clears module + page grants. Promoting to manager clears page grants. Supervisor seats are MAN-only, one per department — grants untouched. One manager per module and one secretary org-wide are enforced.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <button @click="askPositionConfirm" :disabled="positionForm.processing || !selectedRequestId"
                                class="py-2.5 text-sm font-bold text-white bg-blue-600 rounded-xl hover:bg-blue-700 active:scale-95 disabled:opacity-50">
                                Apply Command…
                            </button>
                            <button @click="rejectSelected" :disabled="positionForm.processing || !selectedRequestId"
                                class="py-2.5 text-sm font-bold text-red-600 bg-red-50 border border-red-200 rounded-xl hover:bg-red-100 active:scale-95 disabled:opacity-50">
                                Reject Command
                            </button>
                        </div>
                    </div>

                    <!-- MODULES (root locked) -->
                    <div v-if="activeTab === 'modules'" class="space-y-4">
                        <div class="p-3 rounded-xl bg-indigo-50 dark:bg-indigo-900/10 border border-indigo-200 dark:border-indigo-800 text-[11px] text-indigo-700 dark:text-indigo-300 font-bold">
                            <template v-if="isElevatedUser(managing)">
                                Root module <strong>{{ rootOf(managing) }}</strong> is auto-checked + locked. All modules are shown — grant extras as commanded.
                            </template>
                            <template v-else>
                                Staff hold ONLY their root module <strong>{{ rootOf(managing) }}</strong> (auto-checked + locked). Extra modules are for elevated roles only.
                            </template>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            <label v-for="m in visibleModules" :key="m.key"
                                class="flex items-center gap-2 p-2.5 rounded-xl border text-xs font-bold transition-colors"
                                :class="[(modulesForm.modules.includes(m.key) || isRootLocked(m.key))
                                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300'
                                    : 'border-slate-200 dark:border-slate-700 text-slate-500',
                                    isRootLocked(m.key) ? 'opacity-90 cursor-not-allowed' : 'cursor-pointer']">
                                <input type="checkbox" :value="m.key" :checked="modulesForm.modules.includes(m.key) || isRootLocked(m.key)"
                                    :disabled="isRootLocked(m.key)" @change="toggleModule(m.key)" class="rounded text-blue-600" />
                                {{ m.key }}
                                <span v-if="isRootLocked(m.key)" class="ml-auto text-[9px] font-black uppercase text-blue-500">root · locked</span>
                            </label>
                        </div>
                        <button @click="saveModules" :disabled="modulesForm.processing"
                            class="w-full py-2.5 text-sm font-bold text-white bg-blue-600 rounded-xl hover:bg-blue-700 active:scale-95 disabled:opacity-50">
                            Save Module Grants
                        </button>
                        <p class="text-[11px] text-slate-400">Saving grants seeds every page of each granted module as DISABLED — enable pages manually in Page Access. Removing a module also revokes all of its page grants.</p>
                    </div>

                    <!-- PAGES (disabled / view / edit) -->
                    <div v-if="activeTab === 'pages'" class="space-y-4">
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-[11px] text-slate-500 font-bold">
                            <template v-if="isElevatedUser(managing)">
                                Showing root <strong>{{ rootOf(managing) }}</strong> + checked modules ({{ visiblePageModules.join(', ') || 'none' }}). New pages start DISABLED — IT enables each one.
                                <span v-if="lockedRootOf(managing)" class="mt-1 flex items-center gap-1.5 text-amber-600 dark:text-amber-400">
                                    <Lock class="w-3.5 h-3.5" /> {{ managing.position === 'manager' ? 'Manager' : managing.is_manufacturing_supervisor ? 'Supervisor' : 'Higher-up' }} root
                                    (<strong>{{ lockedRootOf(managing) }}</strong>) is default-on and cannot be disabled — VIEW or EDIT only.
                                </span>
                            </template>
                            <template v-else>
                                Staff see ONLY their home module pages (<strong>{{ managing.role }}</strong>).
                            </template>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button v-for="key in moduleTabKeys" :key="key" @click="activeModule = key"
                                :class="['px-3 py-1.5 text-[11px] font-black rounded-lg transition-colors',
                                    currentModule === key ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-500 hover:text-slate-700']">
                                {{ key }}
                            </button>
                        </div>
                        <div v-if="!currentModule" class="p-4 text-xs text-slate-400 font-bold">No page modules available for this employee.</div>
                        <template v-else>
                        <div v-if="manPlantDashboardWarning" class="mb-3 p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-[11px] font-bold text-amber-700 dark:text-amber-300 leading-relaxed">
                            Dashboard alone opens nothing for plant staff — every role workspace (including its Dashboard tab) requires the Production page.
                            Enable Production (VIEW or EDIT) as well, or this account lands on a limited-access notice after login.
                        </div>
                        <div class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-900/40 border-b border-slate-200 dark:border-slate-700">
                                        <th class="px-4 py-2.5 text-[10px] font-black text-slate-400 uppercase">Page</th>
                                        <th class="px-4 py-2.5 text-[10px] font-black text-slate-400 uppercase text-right">Access</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                    <tr v-for="p in currentPages" :key="p.key">
                                        <td class="px-4 py-2.5 text-sm font-bold text-slate-700 dark:text-slate-200">
                                            {{ p.label }}
                                            <span class="block text-[10px] font-mono font-normal text-slate-400">{{ p.key }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                            <div class="inline-flex rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700 text-[11px] font-black">
                                                <span v-if="isPageLocked(currentModule)" title="Root pages of higher-ups are default-on and cannot be disabled"
                                                    class="px-3 py-1.5 inline-flex items-center gap-1 bg-slate-100 dark:bg-slate-800 text-slate-400 cursor-not-allowed">
                                                    <Lock class="w-3 h-3" />LOCKED
                                                </span>
                                                <button v-else @click="setLevel(p.key, 'disabled')"
                                                    :class="['px-3 py-1.5 transition-colors', (pageGrants[currentModule]?.[p.key] || 'disabled') === 'disabled' ? 'bg-slate-500 text-white' : 'text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700']">DISABLE</button>
                                                <button @click="setLevel(p.key, 'view')"
                                                    :class="['px-3 py-1.5 transition-colors', (pageGrants[currentModule]?.[p.key] === 'view') ? 'bg-amber-500 text-white' : 'text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700']">VIEW</button>
                                                <button @click="setLevel(p.key, 'edit')"
                                                    :class="['px-3 py-1.5 transition-colors', (pageGrants[currentModule]?.[p.key] === 'edit') ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700']">EDIT</button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        </template>
                        <button @click="savePages" :disabled="pagesForm.processing"
                            class="w-full inline-flex items-center justify-center gap-2 py-2.5 text-sm font-bold text-white bg-blue-600 rounded-xl hover:bg-blue-700 active:scale-95 disabled:opacity-50">
                            <Save class="w-4 h-4" /> Save Page Permissions
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- Final confirmation modal for position commands (teleported with
             the drawer so it always renders above it, fully in view) -->
        <Teleport to="body">
        <div v-if="confirmPosition && managing" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 overflow-y-auto" @click.self="confirmPosition = null">
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl w-full max-w-md p-6 my-auto">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                        <CheckCircle2 class="w-5 h-5 text-indigo-600" />
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase">Confirm {{ confirmPosition.action }}</h3>
                        <p class="text-[11px] text-slate-400">Executive command #{{ confirmPosition.id }} · by {{ confirmPosition.requested_by_name }}</p>
                    </div>
                </div>
                <p class="text-sm text-slate-600 dark:text-slate-300 mt-4">
                    Apply <strong>{{ confirmPosition.action }}</strong> for <strong>{{ managing.name }}</strong>:
                    {{ requestCurrentLabel(confirmPosition) }} → <strong>{{ positionForm.position }}</strong><span v-if="positionForm.position === 'supervisor' && positionForm.supervisor_department"> ({{ positionForm.supervisor_department }})</span>
                    <span v-if="positionForm.role && positionForm.position !== 'supervisor'"> ({{ positionForm.role }})</span>?
                </p>
                <p v-if="confirmPosition.reason" class="text-xs text-slate-400 mt-2">Reason: “{{ confirmPosition.reason }}”</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-6">
                    <button @click="confirmPosition = null" class="py-2.5 text-sm font-bold text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200">Cancel</button>
                    <button @click="savePosition" :disabled="positionForm.processing" class="inline-flex items-center justify-center gap-2 py-2.5 text-sm font-bold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 disabled:opacity-50">
                        <Ban v-if="false" /> Confirm &amp; Apply
                    </button>
                </div>
            </div>
        </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
