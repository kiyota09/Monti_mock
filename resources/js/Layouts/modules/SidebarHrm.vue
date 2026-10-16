<script>
import {
    LayoutDashboard, Users, FileText, Eye, Award, UserPlus, Archive,
    HandCoins, ChartNoAxesCombined, Building2, Building, Briefcase, ClipboardList,
    UserCog, Layers, GraduationCap, CalendarCheck, CalendarDays, Clock, HardHat, Receipt,
    CalendarClock,
} from 'lucide-vue-next'

/**
 * HRM_NEW aligned module: workforce / recruitment / onboarding IA,
 * permission keys reuse canonical HRM pages so CheckPagePermission,
 * AccessGate and IT grants keep working. Old routes stay for BC
 * (see routes/Hrm.php) but sidebar points at HRM_NEW screens.
 */
export const hrmModule = {
    key: 'HRM',
    label: 'Human Resource',
    icon: Users,
    group: 'core',

    // Extra-module grants also display the section (filtered per page below).
    condition: (ctx) => ctx.canAccessModule('HRM') || ctx.hasAnyModuleGrant('HRM'),

    getChildren(ctx) {
        const { route, isCEO, isSecretaryOrGM, userPosition, user, hasHrmPermission, roleDropdowns } = ctx
        const safe = (name, params) => {
            try { return route(name, params) } catch { return '#' }
        }
        // Agency group — owns HRM_NEW/Agencies, Workers, Requests, Attendance, Billing.
        // permKeys mirror page.permission in routes/Hrm.php.
        const agencyChildren = [
            { label: 'Agencies', href: safe('hrm.agencies.index'), icon: Building2, permKey: 'agencies' },
            { label: 'Workers', href: safe('hrm.workers.index'), icon: HardHat, permKey: 'workers' },
            { label: 'Manpower Requests', href: safe('hrm.manpower-requests.index'), icon: ClipboardList, permKey: 'requests' },
            { label: 'Agency Attendance', href: safe('hrm.attendance.index'), icon: CalendarCheck, permKey: 'agency_attendance' },
            { label: 'Agency Billing', href: safe('hrm.billing.index'), icon: Receipt, permKey: 'agency_billing' },
        ]
        const agencyDropdown = {
            label: 'Agency',
            icon: Building,
            isDropdown: true,
            isOpen: roleDropdowns.getState('hrm_agency', false),
            toggle: () => roleDropdowns.toggle('hrm_agency'),
            children: agencyChildren,
        }
        const all = [
            { label: 'Dashboard', href: safe('hrm.dashboard'), icon: LayoutDashboard, permKey: 'dashboard' },
            // Workforce (DB-rooted departments / positions / employment types).
            // permKeys mirror the page.permission enforced by routes/Hrm.php (employee).
            { label: 'Employees', href: safe('hrm.workforce.employees.index'), icon: Users, permKey: 'employee' },
            { label: 'Departments', href: safe('hrm.workforce.departments.index'), icon: Building2, permKey: 'employee' },
            { label: 'Positions', href: safe('hrm.workforce.positions.index'), icon: Briefcase, permKey: 'employee' },
            { label: 'Employment Types', href: safe('hrm.workforce.employment-types.index'), icon: Layers, permKey: 'employee' },
            agencyDropdown,
            // Recruitment (routes enforce application/interview).
            { label: 'Job Postings', href: safe('hrm.recruitment.job-postings.index'), icon: ClipboardList, permKey: 'application' },
            { label: 'Applications', href: safe('hrm.recruitment.applications.index'), icon: FileText, permKey: 'application' },
            { label: 'Interviews', href: safe('hrm.recruitment.interviews.index'), icon: Eye, permKey: 'interview' },
            // Onboarding (routes enforce onboarding).
            { label: 'Onboarding', href: safe('hrm.onboarding.status.index'), icon: UserPlus, permKey: 'onboarding' },
            { label: 'Onboarding Templates', href: safe('hrm.onboarding.templates.index'), icon: UserCog, permKey: 'onboarding' },
            // Workforce ops (static-phase pages, permission-gated)
            { label: 'Attendance Management', href: safe('hrm.workforce.attendance.index'), icon: CalendarCheck, permKey: 'attendance' },
            { label: 'Holiday Management', href: safe('hrm.workforce.holidays.index'), icon: CalendarDays, permKey: 'attendance' },
            { label: 'My Attendance', href: safe('hrm.workforce.my-attendance.index'), icon: Clock, permKey: 'attendance' },
            { label: 'Shift Management', href: safe('hrm.workforce.shifts.index'), icon: CalendarClock, permKey: 'attendance' },
            { label: 'Leave Management', href: safe('hrm.workforce.leave.index'), icon: CalendarDays, permKey: 'leave' },
            { label: 'Trainees', href: safe('hrm.trainee.index'), icon: Award, permKey: 'trainee' },
            { label: 'My Assigned Trainees', href: safe('hrm.assigned-trainees.index'), icon: GraduationCap, permKey: 'trainee' },
            { label: 'Archive', href: safe('hrm.applications.rejected'), icon: Archive, permKey: 'application' },
            { label: 'Payroll', href: safe('hrm.payroll'), icon: HandCoins, permKey: 'payroll' },
            { label: 'Analytics', href: safe('hrm.analytics'), icon: ChartNoAxesCombined, permKey: 'analytics' },
        ]
        // Fallback when a named route is missing (e.g. route cache): drop '#' links.
        // Agency dropdown has no href — keep it if any child survives.
        const cleanAgency = (dropdown) => ({
            ...dropdown,
            children: (dropdown.children || []).filter(c => c.href && c.href !== '#'),
        })
        const existing = all
            .map(child => (child.isDropdown ? cleanAgency(child) : child))
            .filter(child => child.isDropdown ? (child.children && child.children.length > 0) : (child.href && child.href !== '#'))
        const list = existing.length ? existing : all
        if (isCEO) return list
        // Explicit rows (even all-'disabled') are the exact access set and
        // override this shortcut — mirrors backend CheckPagePermission.
        if (userPosition === 'manager' && user?.role === 'HRM' && !ctx.hasExplicitModuleGrants('HRM')) return list
        // Auto-full on the root module only (mirrors backend CheckPagePermission);
        // extra granted modules fall through to the per-page filter below.
        if (isSecretaryOrGM && ctx.rootModule === 'HRM') return list
        // No fallback: when every page is 'disabled' (the IT default), the
        // filtered list is empty and buildNavItems skips this module entirely,
        // leaving the sidebar empty → AwaitingAccess landing page.
        // Agency parent shows only if at least one agency child is granted.
        return list
            .map(child => {
                if (!child.isDropdown) return hasHrmPermission(child.permKey) ? child : null
                const kept = (child.children || []).filter(c => hasHrmPermission(c.permKey))
                return kept.length ? { ...child, children: kept } : null
            })
            .filter(Boolean)
    },
}
</script>
