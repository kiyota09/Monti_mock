<script>
import {
    LayoutDashboard, FileUser, Eye, Award, ClipboardCheck, Users,
    AlertCircle, UserPen, KanbanSquare, FileText, Activity,
    Briefcase, Megaphone, ShieldCheck, MessageSquare,
} from 'lucide-vue-next'

export const crmModule = {
    key: 'CRM',
    label: 'Customer Relationship',
    icon: UserPen,
    group: 'core',

    // Extra-module grants also display the section (filtered per page below).
    condition: (ctx) => ctx.canAccessModule('CRM') || ctx.hasAnyModuleGrant('CRM'),

    getChildren(ctx) {
        const { route, isCEO, isSecretaryOrGM, userPosition, user, canAccessModule, hasCrmPermission, grantedModules } = ctx
        const all = [
            { label: 'Dashboard', href: route('crm.dashboard'), icon: LayoutDashboard, permKey: 'dashboard' },
            { label: 'Leads', href: route('crm.lead'), icon: FileUser, permKey: 'leads' },
            { label: 'Accounts', href: route('crm.customerprofile.index'), icon: Users, permKey: 'customer_profiles' },
            { label: 'Opportunities', href: route('crm.opportunities'), icon: KanbanSquare, permKey: 'opportunities' },
            { label: 'Inquiries', href: route('crm.inquiries'), icon: MessageSquare, permKey: 'inquiry' },
            { label: 'Approvals', href: route('crm.approval.index'), icon: ClipboardCheck, permKey: 'approvals' },
            { label: 'Quotations', href: route('crm.quotations'), icon: FileText, permKey: 'quotations' },
            { label: 'Activities', href: route('crm.activities'), icon: Activity, permKey: 'activities' },
            { label: 'Cases', href: route('crm.cases'), icon: Briefcase, permKey: 'cases' },
            { label: 'Campaigns', href: route('crm.campaigns'), icon: Megaphone, permKey: 'campaigns' },
            { label: 'Due Diligence', href: route('crm.investigation.index'), icon: AlertCircle, permKey: 'investigation' },
        ]
        if (isCEO) return all
        // President & Vice President (COO / vice_president): full CRM access.
        if (user?.role === 'COO' || user?.position === 'vice_president') return all
        // Explicit rows (even all-'disabled') are the exact access set and
        // override this shortcut — mirrors backend CheckPagePermission.
        if (userPosition === 'manager' && user?.role === 'CRM' && !ctx.hasExplicitModuleGrants('CRM')) return all
        // Auto-full on the root module only (mirrors backend CheckPagePermission);
        // extra granted modules fall through to the per-page filter below.
        if (isSecretaryOrGM && ctx.rootModule === 'CRM') return all
        // No supervisor shortcut: the backend grants supervisors the module shell
        // but every page still needs an explicit row — fall through to filter.
        // No fallback: when every page is 'disabled' (the IT default), the
        // filtered list is empty and buildNavItems skips this module entirely,
        // leaving the sidebar empty → AwaitingAccess landing page.
        return all.filter(child => hasCrmPermission(child.permKey))
    },
}
</script>
