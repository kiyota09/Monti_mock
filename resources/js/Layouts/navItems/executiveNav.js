import {
    LayoutDashboard, Wrench, Repeat, ClipboardList, Zap, Megaphone, Bell,
    User, Stamp, FileText, Printer, Truck, ScanSearch, Target, History,
    ShieldCheck, CalendarDays,
} from 'lucide-vue-next'

/**
 * Executive / secretary workspace items for the mobile nav.
 *
 * Mirrors the desktop Sidebar.vue blocks exactly (same order, labels, icons
 * and route names) so mobile shows the same workspace the desktop shows:
 *
 * - CEO (role CEO, non-VP position): President's office — CEO Dashboard,
 *   Inbox, Approvals Center, Executive Reports, Board Pack, Deliveries,
 *   Traceability, Goals & Targets, Audit Trail, Organization Chart.
 * - COO / vice_president: Vice Presidency — VP Dashboard, Downtime Board,
 *   Shift Handovers, Plan vs Actual, Utilities Brief, Directives, Bulletins,
 *   Workforce Overview, Joint Approvals.
 * - Secretary (position secretary): Secretary Dashboard, Documents,
 *   Meetings, Memos.
 *
 * Each item carries `route` (ziggy name) + `fallback` (plain path) so the
 * caller can resolve safely. resolveNav stamps the group's
 * moduleId/moduleLabel onto every item.
 */
const CEO_ITEMS = [
    { id: 'exec_ceo_dashboard', label: 'CEO Dashboard', icon: LayoutDashboard, route: 'dashboard', fallback: '/dashboard' },
    { id: 'ceo_inbox', label: 'Inbox', icon: Bell, route: 'ceo.inbox', fallback: '/dashboard/ceo/inbox', showUnread: true },
    { id: 'exec_ceo_approvals', label: 'Approvals Center', icon: Stamp, route: 'ceo.approvals', fallback: '/dashboard/ceo/approvals' },
    { id: 'exec_ceo_reports', label: 'Executive Reports', icon: FileText, route: 'ceo.reports', fallback: '/dashboard/ceo/reports' },
    { id: 'exec_ceo_board_pack', label: 'Board Pack', icon: Printer, route: 'ceo.board-pack', fallback: '/dashboard/ceo/board-pack' },
    { id: 'exec_ceo_deliveries', label: 'Deliveries', icon: Truck, route: 'ceo.deliveries', fallback: '/dashboard/ceo/deliveries' },
    { id: 'exec_ceo_traceability', label: 'Traceability', icon: ScanSearch, route: 'ceo.traceability', fallback: '/dashboard/ceo/traceability' },
    { id: 'exec_ceo_goals', label: 'Goals & Targets', icon: Target, route: 'ceo.goals', fallback: '/dashboard/ceo/goals' },
    { id: 'exec_ceo_audit', label: 'Audit Trail', icon: History, route: 'ceo.audit', fallback: '/dashboard/ceo/audit' },
    { id: 'exec_ceo_access', label: 'Organization Chart', icon: ShieldCheck, route: 'ceo.access', fallback: '/dashboard/ceo/access' },
]

const VP_ITEMS = [
    { id: 'exec_vp_dashboard', label: 'VP Dashboard', icon: LayoutDashboard, route: 'vp.operations', fallback: '/dashboard/vp/operations' },
    { id: 'exec_vp_downtime', label: 'Downtime Board', icon: Wrench, route: 'vp.downtime', fallback: '/dashboard/vp/downtime' },
    { id: 'exec_vp_handovers', label: 'Shift Handovers', icon: Repeat, route: 'vp.handovers', fallback: '/dashboard/vp/handovers' },
    { id: 'exec_vp_plan', label: 'Plan vs Actual', icon: ClipboardList, route: 'vp.plan', fallback: '/dashboard/vp/plan' },
    { id: 'exec_vp_utilities', label: 'Utilities Brief', icon: Zap, route: 'vp.utilities', fallback: '/dashboard/vp/utilities' },
    { id: 'exec_vp_directives', label: 'Directives', icon: Megaphone, route: 'vp.directives', fallback: '/dashboard/vp/directives' },
    { id: 'exec_vp_bulletins', label: 'Bulletins', icon: Bell, route: 'vp.bulletins', fallback: '/dashboard/vp/bulletins' },
    { id: 'exec_vp_workforce', label: 'Workforce Overview', icon: User, route: 'vp.workforce', fallback: '/dashboard/vp/workforce' },
    { id: 'exec_vp_approvals', label: 'Joint Approvals', icon: Stamp, route: 'ceo.approvals', fallback: '/dashboard/ceo/approvals' },
]

const SECRETARY_ITEMS = [
    { id: 'exec_sec_dashboard', label: 'Secretary Dashboard', icon: LayoutDashboard, route: 'secretary.dashboard', fallback: '/dashboard/secretary' },
    { id: 'exec_sec_documents', label: 'Documents', icon: FileText, route: 'secretary.documents', fallback: '/dashboard/secretary/documents' },
    { id: 'exec_sec_meetings', label: 'Meetings', icon: CalendarDays, route: 'secretary.meetings', fallback: '/dashboard/secretary/meetings' },
    { id: 'exec_sec_memos', label: 'Memos', icon: Megaphone, route: 'secretary.memos', fallback: '/dashboard/secretary/memos' },
]

export function getLeadingNav(user) {
    const role = user?.role
    const position = String(user?.position || '').toLowerCase()

    if (role === 'CEO' || role === 'COO') {
        // Mirrors Sidebar.vue: COO (or a VP-positioned CEO) gets the VP
        // workspace, everyone else with these roles gets the CEO workspace.
        if (role === 'COO' || position === 'vice_president') {
            return {
                group: { moduleId: 'VP', moduleLabel: 'Vice Presidency' },
                items: VP_ITEMS,
            }
        }
        return {
            group: { moduleId: 'EXEC', moduleLabel: 'Executive' },
            items: CEO_ITEMS,
        }
    }

    if (position === 'secretary') {
        return {
            group: { moduleId: 'SEC', moduleLabel: 'Secretary' },
            items: SECRETARY_ITEMS,
        }
    }

    return null
}
