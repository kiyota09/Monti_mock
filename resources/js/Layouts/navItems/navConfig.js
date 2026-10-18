import {
    LayoutDashboard, Users, Building2, Briefcase, Layers, ClipboardList, FileText,
    Eye, UserPlus, UserCog, CalendarCheck, CalendarDays, Award, GraduationCap,
    Archive, HandCoins, ChartNoAxesCombined, UserPen, FileUser, KanbanSquare,
    MessageSquare, ClipboardCheck, Activity, Briefcase as BriefcaseIcon, Megaphone,
    AlertCircle, Factory, XCircle, Boxes, CheckCircle2, Truck, Package, Send,
    Navigation, MapPin, Camera, ShoppingBag, CreditCard, Building2 as BuildingIcon,
    Wallet, Receipt, PieChart, BarChart3, Stamp, ShoppingCart, CalendarClock,
    PackageCheck, ClipboardCheck as ClipboardCheckIcon, Warehouse, XCircle as XCircleIcon,
    Undo2, Spool, AlertCircle as AlertIcon, LifeBuoy, MonitorSmartphone, BookOpen,
    GitPullRequest, ScrollText, UserX, HardHat,
} from 'lucide-vue-next'

export const MODULES = [
    {
        id: 'HRM',
        label: 'Human Resource',
        icon: Users,
        order: 1,
        pages: [
            { id: 'hrm_dashboard', label: 'Dashboard', icon: LayoutDashboard, route: 'hrm.dashboard', permission: 'hrm.dashboard', priority: 100, pinned: true },
            { id: 'hrm_employees', label: 'Employees', icon: Users, route: 'hrm.workforce.employees.index', permission: 'hrm.employee', priority: 90 },
            { id: 'hrm_departments', label: 'Departments', icon: Building2, route: 'hrm.workforce.departments.index', permission: 'hrm.employee', priority: 50 },
            { id: 'hrm_positions', label: 'Positions', icon: Briefcase, route: 'hrm.workforce.positions.index', permission: 'hrm.employee', priority: 50 },
            { id: 'hrm_employment_types', label: 'Employment Types', icon: Layers, route: 'hrm.workforce.employment-types.index', permission: 'hrm.employee', priority: 40 },
            { id: 'hrm_job_postings', label: 'Job Postings', icon: ClipboardList, route: 'hrm.recruitment.job-postings.index', permission: 'hrm.application', priority: 80 },
            { id: 'hrm_applications', label: 'Applications', icon: FileText, route: 'hrm.recruitment.applications.index', permission: 'hrm.application', priority: 85 },
            { id: 'hrm_agencies', label: 'Agencies', icon: Building2, route: 'hrm.agencies.index', permission: 'hrm.agencies', priority: 70 },
            { id: 'hrm_workers', label: 'Workers', icon: Users, route: 'hrm.workers.index', permission: 'hrm.workers', priority: 69 },
            { id: 'hrm_manpower_requests', label: 'Manpower Requests', icon: ClipboardList, route: 'hrm.manpower-requests.index', permission: 'hrm.requests', priority: 68 },
            { id: 'hrm_agency_attendance', label: 'Agency Attendance', icon: CalendarCheck, route: 'hrm.attendance.index', permission: 'hrm.agency_attendance', priority: 67 },
            { id: 'hrm_agency_billing', label: 'Agency Billing', icon: HandCoins, route: 'hrm.billing.index', permission: 'hrm.agency_billing', priority: 66 },
            { id: 'hrm_interviews', label: 'Interviews', icon: Eye, route: 'hrm.recruitment.interviews.index', permission: 'hrm.interview', priority: 75 },
            { id: 'hrm_onboarding', label: 'Onboarding', icon: UserPlus, route: 'hrm.onboarding.status.index', permission: 'hrm.onboarding', priority: 60 },
            { id: 'hrm_onboarding_templates', label: 'Templates', icon: UserCog, route: 'hrm.onboarding.templates.index', permission: 'hrm.onboarding', priority: 30 },
            { id: 'hrm_attendance', label: 'Attendance Management', icon: CalendarCheck, route: 'hrm.workforce.attendance.index', permission: 'hrm.attendance', priority: 85 },
            { id: 'hrm_holidays', label: 'Holiday Management', icon: CalendarDays, route: 'hrm.workforce.holidays.index', permission: 'hrm.attendance', priority: 82 },
            { id: 'hrm_my_attendance', label: 'My Attendance', icon: CalendarClock, route: 'hrm.workforce.my-attendance.index', permission: 'hrm.attendance', priority: 84 },
            { id: 'hrm_shifts', label: 'Shift Management', icon: CalendarClock, route: 'hrm.workforce.shifts.index', permission: 'hrm.attendance', priority: 83 },
            { id: 'hrm_leave', label: 'Leave Management', icon: CalendarDays, route: 'hrm.workforce.leave.index', permission: 'hrm.leave', priority: 80 },
            { id: 'hrm_trainees', label: 'Trainees', icon: Award, route: 'hrm.trainee.index', permission: 'hrm.trainee', priority: 50 },
            { id: 'hrm_assigned_trainees', label: 'My Assigned Trainees', icon: GraduationCap, route: 'hrm.assigned-trainees.index', permission: 'hrm.trainee', priority: 45 },
            { id: 'hrm_archive', label: 'Archive', icon: Archive, route: 'hrm.applications.rejected', permission: 'hrm.application', priority: 20 },
            { id: 'hrm_payroll', label: 'Payroll', icon: HandCoins, route: 'hrm.payroll', permission: 'hrm.payroll', priority: 70 },
            { id: 'hrm_analytics', label: 'Analytics', icon: ChartNoAxesCombined, route: 'hrm.analytics', permission: 'hrm.analytics', priority: 40 },
        ],
    },
    {
        id: 'CRM',
        label: 'Customer Relationship',
        icon: UserPen,
        order: 2,
        pages: [
            { id: 'crm_dashboard', label: 'Dashboard', icon: LayoutDashboard, route: 'crm.dashboard', permission: 'crm.dashboard', priority: 100, pinned: true },
            { id: 'crm_leads', label: 'Leads', icon: FileUser, route: 'crm.lead', permission: 'crm.leads', priority: 90 },
            { id: 'crm_accounts', label: 'Accounts', icon: Users, route: 'crm.customerprofile.index', permission: 'crm.customer_profiles', priority: 85 },
            { id: 'crm_opportunities', label: 'Opportunities', icon: KanbanSquare, route: 'crm.opportunities', permission: 'crm.opportunities', priority: 80 },
            { id: 'crm_inquiries', label: 'Inquiries', icon: MessageSquare, route: 'crm.inquiries', permission: 'crm.inquiry', priority: 75 },
            { id: 'crm_messaging', label: 'Messaging', icon: MessageSquare, route: 'crm.messages', permission: 'crm.messaging', priority: 74 },
            { id: 'crm_approvals', label: 'Approvals', icon: ClipboardCheck, route: 'crm.approval.index', permission: 'crm.approvals', priority: 70 },
            { id: 'crm_quotations', label: 'Quotations', icon: FileText, route: 'crm.quotations', permission: 'crm.quotations', priority: 65 },
            { id: 'crm_activities', label: 'Activities', icon: Activity, route: 'crm.activities', permission: 'crm.activities', priority: 60 },
            { id: 'crm_cases', label: 'Cases', icon: BriefcaseIcon, route: 'crm.cases', permission: 'crm.cases', priority: 55 },
            { id: 'crm_campaigns', label: 'Campaigns', icon: Megaphone, route: 'crm.campaigns', permission: 'crm.campaigns', priority: 50 },
            { id: 'crm_due_diligence', label: 'Due Diligence', icon: AlertCircle, route: 'crm.investigation.index', permission: 'crm.investigation', priority: 40 },
        ],
    },
    {
        id: 'MAN',
        label: 'Manufacturing',
        icon: Factory,
        order: 3,
        pages: [
            { id: 'man_dashboard', label: 'Dashboard', icon: Factory, route: 'man.manager.dashboard', permission: 'man.dashboard', priority: 100, pinned: true },
            { id: 'man_production', label: 'Production Orders', icon: ClipboardList, route: 'man.manager.production', permission: 'man.production', priority: 90 },
            { id: 'man_recipes', label: 'Recipes', icon: Layers, route: 'man.recipes', permission: 'man.production', priority: 80 },
            { id: 'man_rejected', label: 'Rejected Items', icon: XCircle, route: 'man.manager.rejected', permission: 'man.reject', priority: 70 },
            { id: 'man_inventory', label: 'Prod. Inventory', icon: Boxes, route: 'man.inventory.index', permission: 'man.inventory', priority: 65 },
            { id: 'man_quality_checker', label: 'Quality Checker', icon: CheckCircle2, route: '/dashboard/man/checker-quality', permission: 'man.production', priority: 60 },
        ],
    },
    {
        id: 'LOG',
        label: 'Logistics',
        icon: Truck,
        order: 4,
        pages: [
            { id: 'log_dashboard', label: 'Dashboard', icon: LayoutDashboard, route: 'logistics.dashboard', permission: 'log.dashboard', priority: 100, pinned: true },
            { id: 'log_load', label: 'Load', icon: Package, route: 'logistics.load.index', permission: 'log.load', priority: 85 },
            { id: 'log_dispatch', label: 'Dispatch', icon: Send, route: 'logistics.dispatch.index', permission: 'log.dispatch', priority: 90 },
            { id: 'log_fleet', label: 'Fleet', icon: Truck, route: 'logistics.fleet.index', permission: 'log.fleet', priority: 80 },
            { id: 'log_drivers', label: 'Drivers', icon: Users, route: 'logistics.drivers.index', permission: 'log.drivers', priority: 75 },
            { id: 'log_routes', label: 'Routes', icon: Navigation, route: 'logistics.routes', permission: 'log.routes', priority: 70 },
            { id: 'log_tracking', label: 'Tracking', icon: MapPin, route: 'logistics.tracking', permission: 'log.tracking', priority: 85 },
            { id: 'log_proof', label: 'Proof', icon: Camera, route: 'logistics.proof.index', permission: 'log.proof', priority: 50 },
            { id: 'log_reports', label: 'Reports', icon: FileText, route: 'logistics.reports.index', permission: 'log.reports', priority: 60 },
        ],
    },
    {
        id: 'ECO',
        label: 'E-Commerce',
        icon: ShoppingBag,
        order: 5,
        pages: [
            { id: 'eco_dashboard', label: 'Dashboard', icon: LayoutDashboard, route: 'eco.dashboard', permission: 'eco.dashboard', priority: 100, pinned: true },
            { id: 'eco_store', label: 'Store', icon: ShoppingBag, route: 'eco.store', permission: 'eco.store', priority: 90 },
            { id: 'eco_suppliers', label: 'Suppliers', icon: Users, route: 'eco.suppliers', permission: 'eco.supplier', priority: 70 },
            { id: 'eco_clients', label: 'Clients', icon: BuildingIcon, route: 'eco.clients', permission: 'eco.client', priority: 75 },
            { id: 'eco_credit', label: 'Credit', icon: CreditCard, route: 'eco.credit', permission: 'eco.credit', priority: 60 },
            { id: 'eco_push', label: 'Push Center', icon: Send, route: 'eco.push', permission: 'eco.push', priority: 50 },
        ],
    },
    {
        id: 'FIN',
        label: 'Finance',
        icon: Wallet,
        order: 6,
        pages: [
            { id: 'fin_overview', label: 'Overview', icon: LayoutDashboard, route: 'fin.manager.dashboard', permission: 'fin.dashboard', priority: 100, pinned: true },
            { id: 'fin_receivables', label: 'Receivables', icon: HandCoins, route: 'fin.manager.receivables', permission: 'fin.receivables', priority: 90 },
            { id: 'fin_payables', label: 'Payables', icon: Receipt, route: 'fin.manager.payables', permission: 'fin.payables', priority: 85 },
            { id: 'fin_expenses', label: 'Expenses', icon: PieChart, route: 'fin.manager.expenses', permission: 'fin.expenses', priority: 80 },
            { id: 'fin_payroll', label: 'Payroll', icon: Users, route: 'fin.manager.payroll', permission: 'fin.payroll', priority: 75 },
            { id: 'fin_reports', label: 'Reports', icon: BarChart3, route: 'fin.manager.reports', permission: 'fin.reports', priority: 70 },
            { id: 'fin_approvals', label: 'PO Approvals', icon: Stamp, route: 'fin.manager.approvals', permission: 'fin.approvals', priority: 65 },
        ],
    },
    {
        id: 'SCM',
        label: 'Supply Chain',
        icon: Truck,
        order: 7,
        pages: [
            { id: 'scm_dashboard', label: 'Dashboard', icon: LayoutDashboard, route: 'scm.dashboard', permission: 'scm.dashboard', priority: 100, pinned: true },
            { id: 'scm_sales_orders', label: 'Sales Orders', icon: ShoppingCart, route: 'scm.sales-orders', permission: 'scm.sales', priority: 90 },
            { id: 'scm_procurement_orders', label: 'Procurement', icon: ClipboardList, route: 'scm.procurement-orders', permission: 'scm.procurement', priority: 85 },
            { id: 'scm_planning', label: 'Planning', icon: CalendarClock, route: 'scm.planning', permission: 'scm.planning', priority: 80 },
            { id: 'scm_purchase_orders', label: 'Purchase Orders', icon: PackageCheck, route: 'scm.purchase-orders', permission: 'scm.purchase', priority: 75 },
            { id: 'scm_deliveries', label: 'Deliveries', icon: ClipboardCheckIcon, route: 'scm.deliveries', permission: 'scm.deliveries', priority: 70 },
            { id: 'scm_vendors', label: 'Vendors', icon: BuildingIcon, route: 'scm.vendors', permission: 'scm.vendor', priority: 60 },
            { id: 'scm_analytics', label: 'Analytics', icon: BarChart3, route: 'scm.analytics', permission: 'scm.analytics', priority: 55 },
        ],
    },
    {
        id: 'WAR',
        label: 'Warehouse',
        icon: Warehouse,
        order: 8,
        pages: [
            { id: 'war_warehouses', label: 'All Warehouses', icon: Warehouse, route: 'warehouse.index', permission: 'war.warehouse', priority: 100, pinned: true },
            { id: 'war_receiving', label: 'Receiving', icon: Truck, route: 'warehouse.receiving', permission: 'war.receiving', priority: 90 },
            { id: 'war_packages', label: 'Packages', icon: Package, route: 'warehouse.packages', permission: 'war.packages', priority: 85 },
            { id: 'war_rejects', label: 'Rejects', icon: XCircleIcon, route: 'warehouse.rejects', permission: 'war.reject', priority: 70 },
        ],
    },
    {
        id: 'INV',
        label: 'Inventory',
        icon: Boxes,
        order: 9,
        pages: [
            { id: 'inv_dashboard', label: 'Dashboard', icon: LayoutDashboard, route: 'inv.dashboard', permission: 'inv.dashboard', priority: 100, pinned: true },
            { id: 'inv_materials', label: 'Materials', icon: Spool, route: 'inv.materials', permission: 'inv.materials', priority: 90 },
            { id: 'inv_products', label: 'Products', icon: Package, route: 'inv.products', permission: 'inv.products', priority: 85 },
            { id: 'inv_checker', label: 'Stock Checker', icon: AlertIcon, route: 'inv.checker', permission: 'inv.checker', priority: 70 },
        ],
    },
    {
        id: 'PRO',
        label: 'Procurement',
        icon: ShoppingCart,
        order: 10,
        pages: [
            { id: 'pro_dashboard', label: 'Dashboard', icon: LayoutDashboard, route: 'pro.manager.dashboard', permission: 'pro.dashboard', priority: 100, pinned: true },
            { id: 'pro_material_requests', label: 'Material Requests', icon: ClipboardList, route: 'pro.manager.material-requests', permission: 'pro.requests', priority: 90 },
            { id: 'pro_quotations', label: 'Quotations', icon: FileText, route: 'pro.manager.supplier-quotations', permission: 'pro.quotations', priority: 85 },
            { id: 'pro_receipts', label: 'Receipts', icon: Send, route: 'pro.manager.receipt', permission: 'pro.receipt', priority: 80 },
            { id: 'pro_tracking', label: 'Order Tracking', icon: Navigation, route: 'pro.manager.tracking', permission: 'pro.tracking', priority: 75 },
        ],
    },
    {
        id: 'ORD',
        label: 'Order Management',
        icon: ClipboardCheck,
        order: 11,
        pages: [
            { id: 'ord_dashboard', label: 'Dashboard', icon: LayoutDashboard, route: 'ord.dashboard', permission: 'ord.dashboard', priority: 100, pinned: true },
            { id: 'ord_orders', label: 'Orders', icon: ClipboardList, route: 'ord.orders', permission: 'ord.orders', priority: 90 },
            { id: 'ord_productions', label: 'Productions', icon: Factory, route: 'ord.productions', permission: 'ord.productions', priority: 85 },
            { id: 'ord_delivery', label: 'Delivery', icon: Truck, route: 'ord.delivery', permission: 'ord.delivery', priority: 80 },
            { id: 'ord_returns', label: 'Returns', icon: Undo2, route: 'ord.returns', permission: 'ord.returns', priority: 70 },
        ],
    },
    {
        id: 'IT',
        label: 'IT & Systems',
        icon: MonitorSmartphone,
        order: 12,
        pages: [
            { id: 'it_dashboard', label: 'Dashboard', icon: LayoutDashboard, route: 'it.dashboard', permission: 'it.dashboard', priority: 100, pinned: true },
            { id: 'it_service_desk', label: 'Service Desk', icon: LifeBuoy, route: 'it.tickets', permission: 'it.tickets', priority: 90 },
            { id: 'it_assets', label: 'Assets', icon: MonitorSmartphone, route: 'it.assets', permission: 'it.assets', priority: 80 },
            { id: 'it_monitoring', label: 'Monitoring', icon: Activity, route: 'it.monitoring', permission: 'it.monitoring', priority: 75 },
            { id: 'it_knowledge', label: 'Knowledge Base', icon: BookOpen, route: 'it.knowledge', permission: 'it.knowledge', priority: 60 },
            { id: 'it_geolocation', label: 'Geolocation', icon: MapPin, route: 'it.location.index', permission: 'it.location', priority: 55 },
            { id: 'it_changes', label: 'Changes', icon: GitPullRequest, route: 'it.changes', permission: 'it.changes', priority: 50 },
            { id: 'it_access_control', label: 'Access Control', icon: Users, route: 'it.access-control', permission: 'it.access_control', priority: 70 },
            { id: 'it_access_logs', label: 'Access Logs', icon: ScrollText, route: 'it.access-logs', permission: 'it.access_logs', priority: 45 },
        ],
    },
    {
        id: 'WRF',
        label: 'Workforce Management',
        icon: CalendarDays,
        order: 13,
        pages: [
            { id: 'wrf_dashboard', label: 'Dashboard', icon: LayoutDashboard, route: 'workforce.dashboard', permission: 'wrf.dashboard', priority: 100, pinned: true },
            { id: 'wrf_scheduler', label: 'Scheduler', icon: CalendarCheck, route: 'workforce.scheduler', permission: 'wrf.scheduler', priority: 90 },
            { id: 'wrf_leave_requests', label: 'Leave Requests', icon: FileText, route: 'workforce.leave', permission: 'wrf.leave', priority: 85 },
            { id: 'wrf_absence', label: 'Absence Tracking', icon: UserX, route: 'workforce.absent', permission: 'wrf.absent', priority: 70 },
        ],
    },
]

export const SUPER_ADMIN_ROLES = ['CEO', 'COO']

export function isSuperAdmin(user) {
    return SUPER_ADMIN_ROLES.includes(user?.role)
}

export function getVisibleModules(user, permissions) {
    if (isSuperAdmin(user)) return MODULES
    return MODULES.filter(mod => {
        if (user?.modules?.includes(mod.id)) return true
        if (mod.id === 'MAN' && user?.manufacturing_role) return true
        return false
    })
}

export function getVisiblePages(module, user, permissions) {
    return module.pages.filter(page => {
        if (isSuperAdmin(user)) return true
        if (permissions.includes(page.permission)) return true
        if (user?.role === module.id && user?.position === 'manager') return true
        if ((user?.position === 'secretary' || user?.position === 'special_officer') && user?.role === module.id) return true
        if (user?.is_manufacturing_supervisor && module.id === 'MAN') return true
        return false
    })
}
