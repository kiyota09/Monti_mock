<script>
import {
    Factory, LayoutDashboard, ClipboardList, XCircle, Boxes, CheckCircle2,
    Eye, Award, FileText, Sparkles, Palette, Wrench, History, Cog, Flag, FlaskConical,
    ClipboardCheck, ArrowRightLeft, Leaf, HardHat, Flame, Layers,
} from 'lucide-vue-next'

// Static config for the "single manufacturing role" staff view (Case 2 below)
// hasHistory mirrors the backend: only the 6 production roles expose a
// `history` route (maintenance + checker-quality have none).
const STAFF_ROLE_CONFIG = {
    knitting_yarn: { label: 'Knitting Yarn', icon: Sparkles, hasReports: true, hasHistory: true, hasFlags: true },
    knitting_mechanic: { label: 'Knitting Mechanic', icon: Cog, hasReports: true, hasHistory: true },
    dyeing_color: { label: 'Dyeing Color', icon: Palette, hasReports: true, hasHistory: true },
    dyeing_fabric_softener: { label: 'Dyeing Fabric Softener', icon: Palette, hasReports: true, hasHistory: true },
    dyeing_squeezer: { label: 'Dyeing Squeezer', icon: Palette, hasReports: true, hasHistory: true },
    dyeing_ironing: { label: 'Dyeing Ironing', icon: Palette, hasReports: true, hasHistory: true },
    dyeing_packaging: { label: 'Dyeing Packaging', icon: Palette, hasReports: true, hasHistory: true },
    dyeing_lab_chemist: { label: 'Lab Chemist', icon: FlaskConical, hasReports: false, hasHistory: true, hasLab: true },
    checker_quality: { label: 'Checker Quality', icon: CheckCircle2, hasReports: false, hasHistory: false },
    maintenance_checker: { label: 'Maintenance Checker', icon: Wrench, hasReports: true, hasHistory: false },
    pollution_control_operator: { label: 'Pollution Control', icon: Leaf, hasReports: false, hasHistory: true },
    safety_officer: { label: 'Safety Officer', icon: HardHat, hasReports: false, hasHistory: true },
    boiler_operator: { label: 'Boiler Operator', icon: Flame, hasReports: true, hasHistory: true },
}

// Which roles a supervisor sees, per department they supervise
// (4 departments — no manufacturing manager)
const DEPARTMENT_ROLES = {
    knitting: ['knitting_yarn', 'knitting_mechanic'],
    dyeing: [
        'dyeing_color', 'dyeing_fabric_softener', 'dyeing_squeezer',
        'dyeing_ironing', 'dyeing_packaging', 'dyeing_lab_chemist',
    ],
    finishing: ['checker_quality'],
    maintenance: ['maintenance_checker', 'pollution_control_operator', 'safety_officer'],
    boiler: ['boiler_operator'],
}

// Canonical route slugs per manufacturing_role.
// Most roles map 1:1 via underscores→hyphens, but pollution_control_operator
// uses the shorter `pollution-control` prefix (see routes/Man.php).
const ROLE_ROUTE_SLUGS = {
    pollution_control_operator: 'pollution-control',
}

function roleToSlug(roleWithUnderscores) {
    return ROLE_ROUTE_SLUGS[roleWithUnderscores] ?? roleWithUnderscores.replace(/_/g, '-')
}

function getRoleLinks(route, roleWithUnderscores, label, icon, hasReports = true, hasHistory = false, hasFlags = false, hasLab = false) {
    const roleWithHyphens = roleToSlug(roleWithUnderscores)
    const routePrefix = `man.staff.${roleWithHyphens}`
    const links = [
        { label: 'Dashboard', href: route(`${routePrefix}.dashboard`), icon: LayoutDashboard },
        { label: label, href: route(`${routePrefix}.page`), icon: icon },
    ]
    if (hasHistory) {
        links.push({ label: 'My History', href: route(`${routePrefix}.history`), icon: History })
    }
    if (hasFlags) {
        links.push({ label: 'Flag Reports', href: route(`${routePrefix}.flag-reports`), icon: Flag })
    }
    if (hasLab) {
        links.push({ label: 'Tests & CoA', href: route(`${routePrefix}.tests`), icon: ClipboardCheck })
        links.push({ label: 'Lab Inventory', href: route(`${routePrefix}.inventory`), icon: Boxes })
        links.push({ label: 'Bulk Transfer', href: route(`${routePrefix}.transfer`), icon: ArrowRightLeft })
        // Recipes live in Manufacturing now — the dyeing lab chemist owns them.
        try { links.push({ label: 'Recipes', href: route('man.recipes'), icon: Layers }) } catch (e) {}
    }
    if (hasReports) {
        links.push({ label: 'Reports', href: route(`${routePrefix}.reports`), icon: FileText })
    }
    return links
}

function createRoleDropdown(ctx, roleKey, roleLabel, roleIcon, hasReports = true, hasHistory = false, hasFlags = false, hasLab = false) {
    const { route, roleDropdowns } = ctx
    return {
        label: roleLabel,
        icon: roleIcon,
        isDropdown: true,
        isOpen: roleDropdowns.getState(roleKey, false),
        toggle: () => roleDropdowns.toggle(roleKey),
        children: getRoleLinks(route, roleKey, roleLabel, roleIcon, hasReports, hasHistory, hasFlags, hasLab),
    }
}

function departmentStaffDropdowns(ctx) {
    const dept = ctx.supervisedDepartment
    const roleKeys = DEPARTMENT_ROLES[dept] || []
    return roleKeys.map(roleKey => {
        const config = STAFF_ROLE_CONFIG[roleKey]
        return createRoleDropdown(ctx, roleKey, config.label, config.icon, config.hasReports, config.hasHistory, config.hasFlags, config.hasLab)
    })
}

export const manModule = {
    key: 'MAN',
    label: 'Manufacturing',
    icon: Factory,
    group: 'core',

    // Extra-module grants also display the section (filtered per page below).
    // When IT leaves every MAN page 'disabled' the filter below yields []
    // and buildNavItems skips this module entirely → empty sidebar →
    // AwaitingAccess landing page (same as every other module).
    condition: (ctx) => ctx.canAccessModule('MAN') || ctx.hasAnyModuleGrant('MAN'),

    getChildren(ctx) {
        const { route, isCEO, isSecretaryOrGM, userPosition, user, isManufacturingSupervisor, qualityChecker } = ctx
        // Usable (view/edit) MAN grants — the shared list already excludes
        // 'disabled' rows, so presence means IT actually approved the page.
        // Staff with no explicit rows keep native full access via the
        // backend auto-grant, which is also reflected in this list.
        const can = (permKey) => ctx.hasModulePermission('MAN', permKey);
        const hasStaffEntry = can('production') || can('dashboard');
        // Every plant role workspace link (including its Dashboard tab) is
        // gated by page.permission:production,view on the backend — a
        // Dashboard-only grant can never open any of them, so role links
        // require the production grant. Without it the menu stays empty
        // (module hidden) instead of offering links that only 403.
        const hasRoleWorkspace = can('production');

        const qualityCheckerChildren = [
            { label: 'Dashboard', href: '/dashboard/man/checker-quality', icon: LayoutDashboard },
            { label: 'Production', href: '/dashboard/man/checker-quality/production', icon: ClipboardList },
        ]

        const managerChildren = [
            { label: 'Dashboard', href: route('man.manager.dashboard'), icon: Factory, permKey: 'dashboard' },
            { label: 'Production Orders', href: route('man.manager.production'), icon: ClipboardList, permKey: 'production' },
            { label: 'Recipes', href: route('man.recipes'), icon: Layers, permKey: 'production' },
            { label: 'Rejected Items', href: route('man.manager.rejected'), icon: XCircle, permKey: 'reject' },
            { label: 'Production Inventory', href: route('man.inventory.index'), icon: Boxes, permKey: 'inventory' },
        ]

        // Quality Checker is its own Finishing department now: the Finishing
        // supervisor reaches it via their Department Staff dropdown below,
        // so the overview entry is shown only to CEO / root-MAN secretary / GM.
        // Its routes require production,view, so non-CEO viewers still need
        // that usable grant (locked roots always hold it).
        if (isCEO || (isSecretaryOrGM && ctx.rootModule === 'MAN')) {
            if (isCEO || hasStaffEntry) {
                managerChildren.push({
                    label: 'Quality Checker',
                    icon: CheckCircle2,
                    isDropdown: true,
                    isOpen: qualityChecker.isOpen,
                    toggle: qualityChecker.toggle,
                    children: qualityCheckerChildren,
                })
            }
        }

        // NOTE: no manufacturing manager exists anymore — legacy MAN-manager
        // accounts fall through to an empty menu (their routes now return
        // 403; reassign them as department supervisors).

        // CEO keeps the full overview.
        if (isCEO) {
            return managerChildren
        }

        // Secretary / special officer: auto-full on the root MAN module only
        // (mirrors backend CheckPagePermission); extra-module holders fall
        // through to the exact per-page set below.
        if (isSecretaryOrGM) {
            if (ctx.rootModule === 'MAN') {
                return managerChildren
            }
            return managerChildren.filter(child => !child.permKey || can(child.permKey))
        }

        let children = []

        // Case 1: department overview access (supervisor).
        // Each supervisor sees the shared overview pages they hold usable
        // grants for (data is scoped to their department server-side) plus
        // their own department's staff dropdowns (role pages require
        // production,view). All-'disabled' (IT default) yields [] → hidden.
        if (isManufacturingSupervisor) {
            children = managerChildren.filter(child => !child.permKey || can(child.permKey))

            if (ctx.supervisedDepartment && hasRoleWorkspace) {
                const staffDropdowns = departmentStaffDropdowns(ctx)
                if (staffDropdowns.length) {
                    children.push({ isDivider: true, label: '── Department Staff ──' })
                    children.push(...staffDropdowns)
                }
            }
        } else {
            // Case 2: regular staff with a single manufacturing_role.
            // Role pages require production — without a usable grant the
            // menu stays empty so the sidebar hides this module (a
            // Dashboard-only grant opens nothing: the staff entry only
            // redirects into the production-gated workspace).
            if (!hasRoleWorkspace) {
                return []
            }
            const manufacturingRole = user?.manufacturing_role
            const config = STAFF_ROLE_CONFIG[manufacturingRole]
            if (config) {
                children = [createRoleDropdown(ctx, manufacturingRole, config.label, config.icon, config.hasReports, config.hasHistory, config.hasFlags, config.hasLab)]
            }
        }

        return children
    },
}
</script>
