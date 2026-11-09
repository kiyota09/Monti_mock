<script>
import {
    Factory, LayoutDashboard, ClipboardList, XCircle, Boxes, CheckCircle2,
    Eye, Award, FileText, Sparkles, Palette, Wrench, History, Cog, Flag, FlaskConical,
    ClipboardCheck, ArrowRightLeft, Leaf, HardHat, Flame, Layers, Package, Scissors, Waves, UserCheck, CalendarDays,
} from 'lucide-vue-next'

// Static config for the "single manufacturing role" staff view (Case 2 below)
// hasReports and hasHistory mirror the role-specific routes in routes/Man.php.
const STAFF_ROLE_CONFIG = {
    knitting_yarn: { label: 'Knitting Yarn', icon: Sparkles, hasReports: true, hasHistory: true, hasFlags: true },
    knitting_mechanic: { label: 'Knitting Mechanic', icon: Cog, hasReports: true, hasHistory: true },
    knitting_inspector: { label: 'Knitting Inspector', icon: CheckCircle2, hasReports: true, hasHistory: true },
    knitting_sample: { label: 'Knitting Sample', icon: Scissors, hasReports: true, hasHistory: true },
    dyeing_color: { label: 'Dyeing Color', icon: Palette, hasReports: true, hasHistory: true },
    dyeing_bleaching: { label: 'Dyeing Bleaching', icon: Palette, hasReports: true, hasHistory: true },
    dyeing_pre_treatment: { label: 'Dyeing Pre Treatment', icon: Palette, hasReports: true, hasHistory: true },
    dyeing_treatment: { label: 'Dyeing Post Treatment', icon: Palette, hasReports: true, hasHistory: true },
    finishing_squeezer: { label: 'Finishing Squeezer', icon: Palette, hasReports: true, hasHistory: true },
    finishing_ironing: { label: 'Finishing Heatsetting', icon: Palette, hasReports: true, hasHistory: true },
    finishing_hydro: { label: 'Finishing Hydro', icon: Waves, hasReports: true, hasHistory: true },
    finishing_tubetex: { label: 'Finishing Tubetex', icon: Layers, hasReports: true, hasHistory: true },
    finishing_packaging: { label: 'Finishing Packaging', icon: Package, hasReports: true, hasHistory: true },
    finishing_inspector: { label: 'Finishing Shrinkage Inspector', icon: CheckCircle2, hasReports: true, hasHistory: true },
    dyeing_lab_chemist: { label: 'Color Request', icon: FlaskConical, hasReports: false, hasHistory: true, hasLab: true },
    checker_quality: { label: 'Finishing Quality Checker', icon: CheckCircle2, hasReports: true, hasHistory: true },
    maintenance_checker: { label: 'Maintenance Checker', icon: Wrench, hasReports: true, hasHistory: false },
    pollution_control_operator: { label: 'Pollution Control', icon: Leaf, hasReports: false, hasHistory: true },
    safety_officer: { label: 'Safety Officer', icon: HardHat, hasReports: false, hasHistory: true },
    boiler_operator: { label: 'Boiler Operator', icon: Flame, hasReports: true, hasHistory: true },
}

// Which roles a supervisor sees, per department they supervise
// (4 departments — no manufacturing manager)
const DEPARTMENT_ROLES = {
    knitting: ['knitting_yarn', 'knitting_mechanic', 'knitting_inspector', 'knitting_sample'],
    dyeing: [
        'dyeing_color', 'dyeing_bleaching', 'dyeing_pre_treatment', 'dyeing_treatment', 'dyeing_lab_chemist',
    ],
    finishing: ['finishing_squeezer', 'finishing_ironing', 'finishing_hydro', 'finishing_tubetex', 'finishing_packaging', 'finishing_inspector', 'checker_quality'],
    maintenance: ['maintenance_checker', 'pollution_control_operator', 'safety_officer'],
    boiler: ['boiler_operator'],
}

// Canonical route slugs per manufacturing_role.
// Most roles map 1:1 via underscores→hyphens, but pollution_control_operator
// uses the shorter `pollution-control` prefix (see routes/Man.php).
const ROLE_ROUTE_SLUGS = {
    pollution_control_operator: 'pollution-control',
    finishing_squeezer: 'finishing-squeezer',
    finishing_ironing: 'finishing-ironing',
}

// A role's work page key normally equals its manufacturing_role slug. The
// pollution-control route/page uses the shorter `pollution_control` key.
const ROLE_WORK_PAGE_KEYS = {
    pollution_control_operator: 'pollution_control',
}

function roleToSlug(roleWithUnderscores) {
    return ROLE_ROUTE_SLUGS[roleWithUnderscores] ?? roleWithUnderscores.replace(/_/g, '-')
}

function getRoleLinks(route, roleWithUnderscores, label, icon, hasReports = true, hasHistory = false, hasFlags = false, hasLab = false) {
    const roleWithHyphens = roleToSlug(roleWithUnderscores)
    const routePrefix = `man.staff.${roleWithHyphens}`
    const links = [
        { label: 'Dashboard', href: route(`${routePrefix}.dashboard`), icon: LayoutDashboard, permKey: 'dashboard' },
        { label: label, href: route(`${routePrefix}.page`), icon: icon, permKey: ROLE_WORK_PAGE_KEYS[roleWithUnderscores] || roleWithUnderscores },
    ]
    if (hasHistory) {
        links.push({ label: 'My History', href: route(`${routePrefix}.history`), icon: History, permKey: 'history' })
    }
    if (hasFlags) {
        links.push({ label: 'Flag Reports', href: route(`${routePrefix}.flag-reports`), icon: Flag, permKey: 'flag_reports' })
    }
    if (hasLab) {
        links.push({ label: 'Pantones', href: route(`${routePrefix}.pantones`), icon: Palette, permKey: 'pantones' })
        links.push({ label: 'Receive Fabric Sample Knit', href: route(`${routePrefix}.knit-fabric-requests`), icon: Scissors, permKey: 'knit_fabric_requests' })
        links.push({ label: 'Tests & CoA', href: route(`${routePrefix}.tests`), icon: FlaskConical, permKey: 'lab_tests' })
        links.push({ label: 'Bulk Transfer', href: route(`${routePrefix}.transfer`), icon: ArrowRightLeft, permKey: 'lab_transfer' })
        links.push({ label: 'Lab Inventory', href: route(`${routePrefix}.inventory`), icon: Boxes, permKey: 'lab_inventory' })
        // Recipes live in Manufacturing now — the dyeing lab chemist owns them.
        try { links.push({ label: 'Recipes', href: route('man.recipes'), icon: Layers, permKey: 'recipes' }) } catch (e) {}
    }
    if (hasReports) {
        links.push({ label: 'Reports', href: route(`${routePrefix}.reports`), icon: FileText, permKey: 'reports' })
    }
    return links
}

function createRoleDropdown(ctx, roleKey, roleLabel, roleIcon, hasReports = true, hasHistory = false, hasFlags = false, hasLab = false, filterFn = null) {
    const { route, roleDropdowns } = ctx
    const links = getRoleLinks(route, roleKey, roleLabel, roleIcon, hasReports, hasHistory, hasFlags, hasLab)
    return {
        label: roleLabel,
        icon: roleIcon,
        isDropdown: true,
        isOpen: roleDropdowns.getState(roleKey, false),
        toggle: () => roleDropdowns.toggle(roleKey),
        children: filterFn ? links.filter(filterFn) : links,
    }
}

function departmentStaffDropdowns(ctx) {
    const dept = ctx.supervisedDepartment
    const roleKeys = DEPARTMENT_ROLES[dept] || []
    return roleKeys.map(roleKey => {
        const config = STAFF_ROLE_CONFIG[roleKey]
        const dropdown = createRoleDropdown(ctx, roleKey, config.label, config.icon, config.hasReports, config.hasHistory, config.hasFlags, config.hasLab)
        dropdown.children = dropdown.children.filter(link => ctx.hasManufacturingSupervisorPagePermission(roleKey, link.permKey))
        return dropdown
    }).filter(dropdown => dropdown.children.length > 0)
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
        // Manufacturing supervisors receive role-scoped page grants for the
        // staff workspaces in their department.

        const qualityCheckerChildren = [
            { label: 'Dashboard', href: '/dashboard/man/checker-quality', icon: LayoutDashboard },
            { label: 'Quality Inspection', href: '/dashboard/man/checker-quality/production', icon: ClipboardCheck },
        ]

        const managerChildren = [
            { label: 'Dashboard', href: route('man.manager.dashboard'), icon: Factory, permKey: 'dashboard' },
            { label: 'Staff', href: route('man.manager.staff'), icon: UserCheck, permKey: 'staff' },
            { label: 'Production Orders', href: route('man.manager.production'), icon: ClipboardList, permKey: 'production' },
            { label: 'JO Assignments', href: route('man.assignments.index'), icon: ClipboardCheck, permKey: 'assignments' },
            { label: 'Recipes', href: route('man.recipes'), icon: Layers, permKey: 'recipes' },
            { label: 'Rejected Items', href: route('man.manager.rejected'), icon: XCircle, permKey: 'reject' },
            { label: 'Production Inventory', href: route('man.inventory.index'), icon: Boxes, permKey: 'inventory' },
        ]
        if (isManufacturingSupervisor && ['knitting', 'dyeing', 'finishing'].includes(user?.supervisor_department)) {
            managerChildren.splice(4, 0, { label: 'Department Calendar', href: `/dashboard/man/assignments/calendar?department=${user.supervisor_department}`, icon: CalendarDays, permKey: 'assignments' })
        }
        if (isManufacturingSupervisor && user?.supervisor_department === 'knitting') {
            managerChildren.splice(3, 0, { label: 'Knitting Machines', href: route('man.knitting-machines.index'), icon: Cog, permKey: 'knitting_machines' })
        }
        if (isManufacturingSupervisor && user?.supervisor_department === 'dyeing') {
            managerChildren.splice(3, 0, { label: 'Dyeing Workflow', href: route('man.dyeing-workflow.index'), icon: ClipboardCheck, permKey: 'dyeing_workflow' })
            managerChildren.splice(3, 0, { label: 'Production Recipes', href: route('man.dyeing-production-recipes.index'), icon: Layers, permKey: 'dyeing_workflow' })
            managerChildren.splice(3, 0, { label: 'Dyeing Machines', href: route('man.dyeing-machines.index'), icon: Cog, permKey: 'dyeing_machines' })
        }
        if (isManufacturingSupervisor && user?.supervisor_department === 'finishing') {
            managerChildren.splice(3, 0, { label: 'Finishing Workflow', href: route('man.finishing-workflow.index'), icon: ClipboardCheck, permKey: 'finishing_workflow' })
            managerChildren.splice(3, 0, { label: 'Finishing Machines', href: route('man.finishing-machines.index'), icon: Cog, permKey: 'finishing_machines' })
        }
        if (isManufacturingSupervisor && user?.supervisor_department === 'boiler') {
            managerChildren.splice(3, 0, { label: 'Boiler Machines', href: route('man.boiler-machines.index'), icon: Cog, permKey: 'boiler_machines' })
        }

        // Finishing Quality Checker is its own Finishing department role now.
        // supervisor reaches it via their Department Staff dropdown below,
        // so the overview entry is shown only to CEO / root-MAN secretary / GM.
        // Its routes require production,view, so non-CEO viewers still need
        // that usable grant (locked roots always hold it).
        if (isSecretaryOrGM && ctx.rootModule === 'MAN') {
            if (hasStaffEntry) {
                managerChildren.push({
                    label: 'Finishing Quality Checker',
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

            if (ctx.supervisedDepartment) {
                const staffDropdowns = departmentStaffDropdowns(ctx)
                if (staffDropdowns.length) {
                    children.push({ isDivider: true, label: '── Department Staff ──' })
                    children.push(...staffDropdowns)
                }
            }
        } else {
            // Case 2: regular staff with a single manufacturing_role.
            // Every page is granted individually, so filter the role menu by
            // the pages IT enabled. With no usable grant the menu stays empty
            // and the sidebar hides this module.
            const manufacturingRole = user?.manufacturing_role
            const config = STAFF_ROLE_CONFIG[manufacturingRole]
            if (config) {
                const dropdown = createRoleDropdown(
                    ctx,
                    manufacturingRole,
                    config.label,
                    config.icon,
                    config.hasReports,
                    config.hasHistory,
                    config.hasFlags,
                    config.hasLab,
                    (link) => !link.permKey || can(link.permKey),
                )
                children = dropdown.children.length ? [dropdown] : []
            }
        }

        return children
    },
}
</script>
