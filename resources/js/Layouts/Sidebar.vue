<script setup>
import { usePage, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { computed, ref, watch } from 'vue'
import {
    LayoutDashboard, ShoppingCart, ShoppingBag, MessageSquare,
    Receipt, HelpCircle, User, Truck, Navigation, Clock, CalendarDays,
    History, HandCoins, Building2, UserCog2, ShieldCheck,
    FileText, Megaphone, Stamp, Bell, Printer, Target, Wrench, Repeat, ClipboardList, Zap, ScanSearch, Users, Wallet,
    Route, ClipboardCheck, AlertTriangle, PackageX, FileWarning,
} from 'lucide-vue-next'

import { useDropdown, useDynamicDropdowns, useSidebarScroll } from './composables/useSidebarPersistence'
import { useSidebarToggle } from './composables/useSidebarToggle'
import { usePermissions } from './composables/usePermissions'
import { useManufacturingSupervisor } from './composables/useManufacturingSupervisor'
import { buildNavItems } from './navItems/buildNavItems'

const page = usePage()

    const user = computed(() => page.props.auth.user)
    const unreadCount = computed(() => page.props.notifications_unread || 0)
const client = computed(() => page.props.auth.client)
const representative = computed(() => page.props.auth.representative || null)
const supplier = computed(() => page.props.auth.supplier || (page.props.auth.user?.business_name ? page.props.auth.user : null))
const applicantUser = computed(() => page.props.auth.applicant)
const agencyUser = computed(() => page.props.auth.agency)
const currentUrl = computed(() => page.url)

// ─── PERSISTENCE ──────────────────────────────────────────────────────────────
const { scrollRef: sidebarScrollRef } = useSidebarScroll()

// ─── COLLAPSE (driven by the TopBar sidebar toggle) ─────────────────────────
const { sidebarCollapsed } = useSidebarToggle()

// One persisted dropdown per top-level module (keys match module.key in navItems/buildNavItems.js)
const moduleDropdowns = {
    HRM: useDropdown('hrm'),
    CRM: useDropdown('crm'),
    MAN: useDropdown('man'),
    LOG: useDropdown('logistics'),
    ECO: useDropdown('eco'),
    ORD: useDropdown('ord'),
    SCM: useDropdown('scm'),
    WAR: useDropdown('warehouse'),
    INV: useDropdown('inventory'),
    PRO: useDropdown('pro'),
    FIN: useDropdown('fin'),
    IT: useDropdown('it'),
    WRF: useDropdown('workforce'),
}
const qualityCheckerDropdown = useDropdown('quality_checker')
const roleDropdowns = useDynamicDropdowns('role_')

// ─── PERMISSIONS ──────────────────────────────────────────────────────────────
const {
    grantedModules, rootModule, canAccessModule, canAccessWorkforce, hasWorkforcePermission,
    hasHrmPermission, hasCrmPermission, hasModulePermission, hasExplicitModuleGrants, hasAnyModuleGrant,
    hasWarehouseAccess, hasInventoryAccess, hasOrdAccess, hasLogisticsAccess,
} = usePermissions(user, page)

// ─── MANUFACTURING SUPERVISOR (also used in the footer "Switch Role" block) ──
const {
    isManufacturingSupervisor, supervisorRoles, activeManufacturingRole,
    supervisedDepartment, switchManufacturingRole, formatRoleLabel,
} = useManufacturingSupervisor(user)

// ─── AUTH-STATE FLAGS ─────────────────────────────────────────────────────────
const isDriver = computed(() => user.value?.log_role === 'driver' || (user.value?.driver !== null && user.value?.driver !== undefined))
const isConductor = computed(() => user.value?.log_role === 'conductor' || (user.value?.conductor !== null && user.value?.conductor !== undefined))
const isEmployeePortal = computed(() => currentUrl.value.startsWith('/dashboard/employee-ui'))
    const isClient = computed(() => !!client.value)
    const isSupplier = computed(() => !!supplier.value || currentUrl.value.startsWith('/supplier'))
    const isApplicant = computed(() => !!applicantUser.value || currentUrl.value.startsWith('/applicant'))
    const isAgency = computed(() => !!agencyUser.value || currentUrl.value.startsWith('/agency'))

// ─── NAV ITEMS ────────────────────────────────────────────────────────────────
const navItems = computed(() => {
    if (isSupplier.value) {
        return [
            { label: 'Vendor Hub', href: route('supplier.dashboard'), icon: LayoutDashboard },
            { label: 'Messages', href: route('supplier.messages'), icon: MessageSquare },
            { label: 'Purchase Orders', href: route('supplier.orders'), icon: ShoppingCart },
            { label: 'Payments', href: route('supplier.payments'), icon: Wallet },
            { label: 'My Products', href: route('supplier.products'), icon: ShoppingBag },
        ]
    }

    if (isClient.value) {
        const portalPages = [
            { key: 'dashboard', label: 'Dashboard', href: route('client.dashboard'), icon: LayoutDashboard },
            { key: 'products', label: 'Products', href: route('client.products'), icon: ShoppingBag },
            { key: 'conversations', label: 'Inquiries', href: route('client.conversations'), icon: MessageSquare },
            { key: 'messages', label: 'Messages', href: route('client.messages'), icon: MessageSquare },
            { key: 'orders', label: 'Orders', href: route('client.orders'), icon: ShoppingCart },
            { key: 'tracking', label: 'Order Tracking', href: route('client.tracking'), icon: Navigation },
            { key: 'invoices', label: 'Invoices', href: route('client.invoices'), icon: Receipt },
            { key: 'payments', label: 'Payments', href: route('client.payments'), icon: Wallet },
            { key: 'receiving', label: 'Receiving', href: route('client.receiving'), icon: Truck },
            { key: 'profile', label: 'Profile', href: route('client.profile.edit'), icon: User },
            { key: 'support', label: 'Support', href: route('client.support'), icon: HelpCircle },
        ]

        const portal = page.props.auth.client_portal || null
        const allowed = portal ? portal.access_pages : null
        // A non-primary login — delegated representative or the owner account
        // itself — only sees the pages granted to it; null means "every page".
        const visible = allowed ? portalPages.filter((p) => allowed.includes(p.key)) : portalPages

        // The current primary — and only the primary — manages representatives
        // and their page access.
        if (portal && portal.is_primary) {
            return [
                ...visible,
                { label: 'Representatives', href: route('client.representatives'), icon: Users },
                { label: 'Access Control', href: route('client.access-control'), icon: ShieldCheck },
            ]
        }

        return visible
    }

    if (isAgency.value) {
        const agencyRoute = (name, fallback) => {
            try { return route(name) } catch { return fallback }
        }
        return [
            { label: 'Dashboard', href: agencyRoute('agency.dashboard', '/agency/dashboard'), icon: LayoutDashboard },
            { label: 'My Workers', href: agencyRoute('agency.workers.index', '/agency/workers'), icon: UserCog2 },
            { label: 'Manpower Requests', href: agencyRoute('agency.requests.index', '/agency/requests'), icon: ClipboardList },
            { label: 'Attendance', href: agencyRoute('agency.attendance.index', '/agency/attendance'), icon: Clock },
            { label: 'Billing', href: agencyRoute('agency.billing.index', '/agency/billing'), icon: Receipt },
        ]
    }

    if (isEmployeePortal.value) {
        return [
            { label: 'Employee Dashboard', href: route('employee.ui.dashboard'), icon: Clock },
            { label: 'Leave Request', href: route('employee.ui.leave'), icon: History },
            { label: 'Payslip', href: route('employee.ui.payslip'), icon: HandCoins },
        ]
    }

    // ─── INTERNAL USER ─────────────────────────────────────────────────────────
    const items = []
    const userPosition = user.value?.position?.toLowerCase()
    const isCEO = user.value?.role === 'CEO'
    const isCOO = user.value?.role === 'COO'
    const isSecretaryOrGM = user.value?.position === 'secretary' || user.value?.position === 'special_officer'

    if (isCEO || isCOO) {
        const isVP = isCOO || user.value?.position === 'vice_president'
        if (isVP) {
            // Vice Presidency: execution arm (operations, directives, workforce).
            // The full CRM module is appended below via buildNavItems — the
            // early return was removed so executives keep CRM access.
            items.push({ label: 'VP Dashboard', href: route('vp.operations'), icon: LayoutDashboard })
            items.push({ label: 'Downtime Board', href: route('vp.downtime'), icon: Wrench })
            items.push({ label: 'Shift Handovers', href: route('vp.handovers'), icon: Repeat })
            items.push({ label: 'Plan vs Actual', href: route('vp.plan'), icon: ClipboardList })
            items.push({ label: 'Utilities Brief', href: route('vp.utilities'), icon: Zap })
            items.push({ label: 'Directives', href: route('vp.directives'), icon: Megaphone })
            items.push({ label: 'Bulletins', href: route('vp.bulletins'), icon: Bell })
            items.push({ label: 'Workforce Overview', href: route('vp.workforce'), icon: User })
            items.push({ label: 'Joint Approvals', href: route('ceo.approvals'), icon: Stamp })
        } else {
            items.push({ label: 'CEO Dashboard', href: route('dashboard'), icon: LayoutDashboard })
            items.push({ label: 'Inbox', href: route('ceo.inbox'), icon: Bell, badge: unreadCount.value || undefined })
            items.push({ label: 'Approvals Center', href: route('ceo.approvals'), icon: Stamp })
            items.push({ label: 'Executive Reports', href: route('ceo.reports'), icon: FileText })
            items.push({ label: 'Board Pack', href: route('ceo.board-pack'), icon: Printer })
            items.push({ label: 'Deliveries', href: route('ceo.deliveries'), icon: Truck })
            items.push({ label: 'Traceability', href: route('ceo.traceability'), icon: ScanSearch })
            items.push({ label: 'Goals & Targets', href: route('ceo.goals'), icon: Target })
            items.push({ label: 'Audit Trail', href: route('ceo.audit'), icon: History })
            items.push({ label: 'Organization Chart', href: route('ceo.access'), icon: ShieldCheck })
        }
    }

    // ─── SECRETARY WORKSPACE (secretary-exclusive pages + granted modules below) ──
    if (user.value?.position === 'secretary') {
        items.push({ label: 'Secretary Dashboard', href: route('secretary.dashboard'), icon: LayoutDashboard })
        items.push({ label: 'Documents', href: route('secretary.documents'), icon: FileText })
        items.push({ label: 'Meetings', href: route('secretary.meetings'), icon: CalendarDays })
        items.push({ label: 'Memos', href: route('secretary.memos'), icon: Megaphone })
    }

    if (user.value?.role?.toUpperCase() === 'LOG' && userPosition === 'staff') {
        if (isDriver.value) {
            const portal = route('logistics.driver.portal', {}, false)
            items.push(
                { isHeading: true, label: 'Driver Portal' },
                { label: 'Dashboard', href: portal, icon: LayoutDashboard },
                { label: 'My Trips', href: `${portal}?tab=trips`, icon: Route },
                { label: 'Proof of Delivery', href: `${portal}?tab=pod`, icon: ClipboardCheck },
                { label: 'Inspection', href: `${portal}?tab=inspection`, icon: Wrench },
                { label: 'Incidents', href: `${portal}?tab=incidents`, icon: AlertTriangle },
                { label: 'Hours of Service', href: `${portal}?tab=hos`, icon: Clock },
                { label: 'Earnings', href: `${portal}?tab=earnings`, icon: Wallet },
                { label: 'Documents', href: `${portal}?tab=documents`, icon: FileText },
            )
        } else if (isConductor.value) {
            const portal = route('logistics.conductor.portal', {}, false)
            items.push(
                { isHeading: true, label: 'Conductor Portal' },
                { label: 'Dashboard', href: portal, icon: LayoutDashboard },
                { label: 'My Trips', href: `${portal}?tab=trips`, icon: Route },
                { label: 'Load Check', href: `${portal}?tab=load`, icon: ClipboardCheck },
                { label: 'Collections', href: `${portal}?tab=collections`, icon: HandCoins },
                { label: 'Returns', href: `${portal}?tab=returns`, icon: PackageX },
                { label: 'Reports', href: `${portal}?tab=reports`, icon: FileWarning },
                { label: 'History', href: `${portal}?tab=history`, icon: History },
            )
        }
        return items
    }

    if (userPosition === 'trainee') {
        items.push(
            { label: 'Time In/Out', href: route('trainee.timekeeping'), icon: Clock },
            { label: 'Attendance', href: route('trainee.attendance'), icon: CalendarDays },
            { label: 'Payslips', href: route('trainee.payslip'), icon: HandCoins }
        )
        return items
    }

    // ─── APPLICANT PORTAL (official APPLICANTS module) ──
    if (isApplicant.value) {
        const applicantRoute = (name, fallback) => {
            try { return route(name) } catch { return fallback }
        }
        items.push(
            { label: 'Dashboard', href: applicantRoute('applicant.dashboard', '/applicant/dashboard'), icon: LayoutDashboard },
            { label: 'Find Jobs', href: applicantRoute('applicant.jobs.index', '/applicant/jobs'), icon: ShoppingBag },
            { label: 'My Applications', href: applicantRoute('applicant.applications.index', '/applicant/applications'), icon: FileText },
            { label: 'Interviews', href: applicantRoute('applicant.interviews', '/applicant/interviews'), icon: CalendarDays },
            { label: 'Onboarding', href: applicantRoute('applicant.onboarding.index', '/applicant/onboarding'), icon: ClipboardList },
            { label: 'My Profile', href: applicantRoute('applicant.profile.index', '/applicant/profile'), icon: User },
            { label: 'Account', href: applicantRoute('applicant.account.index', '/applicant/account'), icon: UserCog2 },
        )
        return items
    }

    // ─── MODULE ITEMS (HRM, CRM, MAN, LOG, ECO, ORD, SCM, WAR, INV, PRO, WRF) ──
    const ctx = {
        route,
        user: user.value,
        isCEO,
        isSecretaryOrGM,
        userPosition,
        grantedModules: grantedModules.value,
        canAccessModule,
        canAccessWorkforce,
        rootModule: rootModule.value,
        hasExplicitModuleGrants,
        hasAnyModuleGrant,
        hasModulePermission,
        hasHrmPermission,
        hasCrmPermission,
        hasWorkforcePermission,
        hasWarehouseAccess: hasWarehouseAccess.value,
        hasInventoryAccess: hasInventoryAccess.value,
        hasOrdAccess: hasOrdAccess.value,
        hasLogisticsAccess: hasLogisticsAccess.value,
        isManufacturingSupervisor: isManufacturingSupervisor.value,
        supervisedDepartment: supervisedDepartment.value,
        qualityChecker: { isOpen: qualityCheckerDropdown.isOpen.value, toggle: qualityCheckerDropdown.toggle },
        roleDropdowns,
        dropdowns: moduleDropdowns,
    }

    items.push(...buildNavItems(ctx))
    return items
})

// ─── DISPLAY HELPERS ──────────────────────────────────────────────────────────
const isActive = (href) => {
    if (href === '#') return false
    return currentUrl.value === href || currentUrl.value.startsWith(href + '/')
}

const displayName = computed(() => {
    if (isSupplier.value) return supplier.value?.representative_name
    if (isClient.value) return client.value?.company_name
    if (isApplicant.value) return applicantUser.value?.first_name ? `${applicantUser.value.first_name} ${applicantUser.value.last_name || ''}`.trim() : applicantUser.value?.email
    if (isAgency.value) return agencyUser.value?.name || agencyUser.value?.email
    return user.value?.name
})
const displayInitial = computed(() => displayName.value?.charAt(0) ?? '?')
const userPhotoUrl = computed(() => {
    if (photoBroken.value) return null
    if (user.value?.profile_photo_path) return `/storage/${user.value.profile_photo_path}`
    if (supplier.value?.profile_photo_path) return `/storage/${supplier.value.profile_photo_path}`
    if (client.value?.profile_photo_path) return `/storage/${client.value.profile_photo_path}`
    return null
})
// If the stored photo is missing on disk, fall back to initials instead of a broken image
const photoBroken = ref(false)
watch(userPhotoUrl, () => { photoBroken.value = false })
const displayDepartment = computed(() => {
    if (isApplicant.value) return 'Applicant'
    if (isAgency.value) return 'Agency'
    return isSupplier.value ? 'Supplier' : (isClient.value ? client.value?.business_type : user.value?.role)
})
const displayPosition = computed(() => {
    if (isApplicant.value) return applicantUser.value?.status || 'Applicant'
    if (isAgency.value) return agencyUser.value?.email ?? 'Partner Agency'
    if (isSupplier.value) return supplier.value?.business_name ?? 'Vendor'
    if (isClient.value) return 'Partner'
    if (user.value?.is_manufacturing_supervisor) return 'Supervisor'
    return user.value?.position
})
const sidebarLabel = computed(() => {
    if (isApplicant.value) return 'Applicant'
    if (isAgency.value) return 'Agency'
    return isSupplier.value ? 'Vendor' : (isClient.value ? 'Partner' : (isEmployeePortal.value ? 'Employee' : 'System'))
})
</script>

<template>
    <aside :class="sidebarCollapsed ? 'md:-ml-64' : ''"
        class="hidden md:flex md:w-64 md:flex-col md:fixed md:inset-y-0 z-40 transition-all duration-300 h-screen">
        <div
            class="relative flex flex-col h-full bg-white dark:bg-zinc-900 border-r border-gray-200/70 dark:border-zinc-800 text-gray-600 dark:text-gray-300 font-sans antialiased shadow-xl select-none overflow-hidden">
            <!-- slim gradient strip tying the sidebar to the module heroes -->
            <div class="h-1 w-full bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 flex-shrink-0" />

            <!-- BRAND / SYSTEM HEADER -->
            <div class="relative p-5 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-3 flex-shrink-0">
                <div class="animate-logo w-10 h-10 rounded-xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-1.5 flex items-center justify-center overflow-hidden shadow-lg shadow-indigo-500/30 flex-shrink-0">
                    <img src="/images/applogo.png" alt="Logo" class="h-full w-full object-contain brightness-0 invert" />
                </div>
                <div class="flex flex-col overflow-hidden">
                    <h2 class="text-sm font-bold tracking-wide text-gray-900 dark:text-white leading-tight">
                        MONTI <span class="font-normal text-indigo-600 dark:text-indigo-400">TEXTILE</span>
                    </h2>
                    <p class="text-[10px] font-bold uppercase tracking-widest truncate text-indigo-500 dark:text-indigo-400">{{ sidebarLabel }}</p>
                </div>
            </div>

            <!-- MAIN NAVIGATION -->
            <div ref="sidebarScrollRef" class="relative flex-1 overflow-y-auto px-3 py-4 space-y-1 scrollbar-thin">
                <template v-for="item in navItems" :key="item.label || item.isHeading">
                    <!-- Heading -->
                    <div v-if="item.isHeading" class="mb-2 px-3 pt-5 first:pt-1">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.15em]">{{ item.label }}</p>
                    </div>

                    <!-- Dropdown (level 1) -->
                    <div v-else-if="item.isDropdown">
                        <button @click="item.toggle" type="button"
                            :class="[item.isOpen ? 'text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/30 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-indigo-700 dark:hover:text-indigo-300 hover:bg-indigo-50/60 dark:hover:bg-indigo-900/20']"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-semibold tracking-wide transition-all duration-200 group text-left">
                            <div class="flex items-center gap-3">
                                <component :is="item.icon" class="w-4 h-4 flex-shrink-0 transition-colors duration-200"
                                    :class="item.isOpen ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-400 group-hover:text-indigo-500'" />
                                <span class="truncate">{{ item.label }}</span>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg"
                                class="w-3.5 h-3.5 transition-transform duration-200 flex-shrink-0"
                                :class="[item.isOpen ? 'rotate-90 text-indigo-500' : 'text-gray-400 group-hover:text-indigo-400']"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9 18 15 12 9 6" />
                            </svg>
                        </button>

                        <!-- Children (may contain further dropdowns, dividers, or links) -->
                        <div v-show="item.isOpen" class="mt-1 ml-4 pl-3 border-l border-gray-200 dark:border-zinc-700 space-y-1 overflow-hidden">
                            <template v-for="subItem in item.children" :key="subItem.label">
                                <!-- Sub-dropdown (level 2) -->
                                <div v-if="subItem.isDropdown">
                                    <button @click="subItem.toggle" type="button"
                                        :class="[subItem.isOpen ? 'text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/30 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-indigo-700 dark:hover:text-indigo-300 hover:bg-indigo-50/60 dark:hover:bg-indigo-900/20']"
                                        class="w-full flex items-center justify-between py-2 px-3 rounded-md text-[11px] font-semibold transition-all duration-200 text-left group">
                                        <div class="flex items-center gap-2.5">
                                            <component :is="subItem.icon" class="w-3.5 h-3.5 flex-shrink-0"
                                                :class="subItem.isOpen ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-400 group-hover:text-indigo-500'" />
                                            <span class="truncate">{{ subItem.label }}</span>
                                        </div>
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                            class="w-3 h-3 transition-transform duration-200 flex-shrink-0"
                                            :class="[subItem.isOpen ? 'rotate-90 text-indigo-500' : 'text-gray-400 group-hover:text-indigo-400']"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                            stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="9 18 15 12 9 6" />
                                        </svg>
                                    </button>
                                    <div v-show="subItem.isOpen" class="mt-1 ml-3 pl-3 border-l border-gray-200 dark:border-zinc-700 space-y-1">
                                        <Link v-for="link in subItem.children" :key="link.label" :href="link.href"
                                            preserve-scroll preserve-state
                                            :class="[isActive(link.href) ? 'bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 text-white shadow-md shadow-indigo-500/25 font-bold' : 'text-gray-500 dark:text-gray-400 hover:text-indigo-700 dark:hover:text-indigo-300 hover:bg-indigo-50/60 dark:hover:bg-indigo-900/20']"
                                            class="w-full flex items-center py-1.5 px-2 rounded-md text-[10.5px] font-medium transition-all duration-200 text-left no-underline">
                                            <span class="w-1.5 h-1.5 rounded-full mr-2 flex-shrink-0"
                                                :class="isActive(link.href) ? 'bg-white shadow-[0_0_6px_rgba(255,255,255,0.9)]' : 'bg-gray-300 dark:bg-zinc-600'"></span>
                                            <component :is="link.icon" class="h-3 w-3 mr-1.5 flex-shrink-0" />
                                            <span class="truncate">{{ link.label }}</span>
                                        </Link>
                                    </div>
                                </div>
                                <!-- Divider -->
                                <div v-else-if="subItem.isDivider"
                                    class="text-[9px] text-gray-400 py-2 px-3 uppercase tracking-widest font-bold">{{ subItem.label }}</div>
                                <!-- Direct link (level 2) -->
                                <Link v-else :href="subItem.href" preserve-scroll preserve-state
                                    :class="[isActive(subItem.href) ? 'bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 text-white shadow-md shadow-indigo-500/25 font-bold' : 'text-gray-500 dark:text-gray-400 hover:text-indigo-700 dark:hover:text-indigo-300 hover:bg-indigo-50/60 dark:hover:bg-indigo-900/20']"
                                    class="w-full flex items-center py-2 px-3 rounded-md text-[11px] font-semibold transition-all duration-200 text-left no-underline">
                                    <span class="w-1.5 h-1.5 rounded-full mr-2.5 flex-shrink-0"
                                        :class="isActive(subItem.href) ? 'bg-white shadow-[0_0_6px_rgba(255,255,255,0.9)]' : 'bg-gray-300 dark:bg-zinc-600'"></span>
                                    <component :is="subItem.icon" class="h-3.5 w-3.5 mr-2 flex-shrink-0" />
                                    <span class="truncate">{{ subItem.label }}</span>
                                </Link>
                            </template>
                        </div>
                    </div>

                    <!-- Direct link (top level, non-dropdown) -->
                    <Link v-else :href="item.href" preserve-scroll preserve-state
                        :class="[isActive(item.href) ? 'bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 text-white shadow-lg shadow-indigo-500/25' : 'text-gray-500 dark:text-gray-400 hover:text-indigo-700 dark:hover:text-indigo-300 hover:bg-indigo-50/60 dark:hover:bg-indigo-900/20']"
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-semibold tracking-wide transition-all duration-200 group text-left no-underline">
                        <div class="flex items-center gap-3">
                            <component :is="item.icon" class="w-4 h-4 flex-shrink-0 transition-colors duration-200"
                                :class="isActive(item.href) ? 'text-white' : 'text-gray-400 group-hover:text-indigo-500'" />
                            <span class="truncate">{{ item.label }}</span>
                        </div>
                        <span v-if="item.badge" class="min-w-[20px] h-5 px-1.5 rounded-full bg-rose-500 text-white text-[10px] font-black flex items-center justify-center flex-shrink-0">{{ item.badge > 99 ? '99+' : item.badge }}</span>
                        <span v-if="isActive(item.href)" class="w-1.5 h-1.5 rounded-full flex-shrink-0 bg-white"></span>
                    </Link>
                </template>

                <!-- Empty state: every page is still 'disabled' (IT default) -->
                <div v-if="navItems.length === 0" class="px-3 py-8 text-center">
                    <div class="w-12 h-12 mx-auto rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center mb-3">
                        <ShieldCheck class="w-6 h-6 text-indigo-500" />
                    </div>
                    <p class="text-xs font-bold text-gray-900 dark:text-white mb-1">No pages assigned yet</p>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                        All of your page permissions are still disabled.<br />
                        Please wait for the admins to grant you access.
                    </p>
                </div>
            </div>

            <!-- FOOTER PROFILE -->
            <div class="relative p-4 border-t border-gray-100 dark:border-zinc-800 bg-gray-50/80 dark:bg-zinc-800/50 backdrop-blur flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="relative flex-shrink-0">
                        <div class="w-9 h-9 rounded-full p-0.5 bg-gradient-to-tr from-blue-700 via-indigo-700 to-violet-800 shadow-md shadow-indigo-500/25">
                            <img v-if="userPhotoUrl" :src="userPhotoUrl" @error="photoBroken = true"
                                class="w-full h-full rounded-full object-cover" />
                            <div v-else
                                class="w-full h-full bg-gradient-to-br from-blue-700 to-violet-800 rounded-full flex items-center justify-center font-bold text-xs text-white uppercase">
                                {{ displayInitial }}</div>
                        </div>
                        <span
                            class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-500 border-2 border-white dark:border-zinc-800 rounded-full"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="text-xs font-bold text-gray-900 dark:text-white truncate uppercase tracking-tight">{{ displayName }}</h4>
                        <div class="flex items-center gap-1 mt-0.5 min-w-0">
                            <Building2 class="h-2.5 w-2.5 text-gray-400 flex-shrink-0" />
                            <span class="text-[10px] font-semibold truncate text-indigo-600 dark:text-indigo-400">{{ displayDepartment }}</span>
                            <span class="text-gray-300 dark:text-zinc-600 text-[9px]">•</span>
                            <span class="text-[10px] text-gray-500 dark:text-gray-400 truncate">{{ displayPosition }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="isManufacturingSupervisor && supervisorRoles.length > 0"
                    class="mt-3 pt-3 border-t border-gray-100 dark:border-zinc-800">
                    <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-1">
                        <UserCog2 class="w-3 h-3" /> Switch Role
                    </p>
                    <div class="space-y-1">
                        <button v-for="role in supervisorRoles" :key="role.manufacturing_role"
                            @click="switchManufacturingRole(role.manufacturing_role)"
                            :class="activeManufacturingRole === role.manufacturing_role
                                ? 'bg-gradient-to-r from-blue-700 to-violet-800 text-white shadow-md shadow-indigo-500/25'
                                : 'bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-gray-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-700'"
                            class="w-full text-left text-[11px] font-medium px-2.5 py-1.5 rounded-md transition-all">
                            {{ formatRoleLabel(role.manufacturing_role) }}
                            <span v-if="activeManufacturingRole === role.manufacturing_role"
                                class="float-right text-white">✓</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</template>

<style scoped>
.animate-logo {
    animation: logoFloat 4.5s ease-in-out infinite, logoGlow 3.2s ease-in-out infinite;
    transition: transform 0.3s ease;
}
.animate-logo:hover {
    animation-play-state: paused;
    transform: scale(1.12) rotate(-8deg);
}
@keyframes logoFloat {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    25% { transform: translateY(-2px) rotate(-4deg); }
    50% { transform: translateY(1px) rotate(0deg); }
    75% { transform: translateY(-1px) rotate(4deg); }
}
@keyframes logoGlow {
    0%, 100% { box-shadow: 0 8px 20px -6px rgba(99, 102, 241, 0.45); }
    50% { box-shadow: 0 8px 30px -4px rgba(139, 92, 246, 0.7); }
}
.scrollbar-thin::-webkit-scrollbar {
    width: 4px;
}
.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
    background: rgba(99, 102, 241, 0.25);
    border-radius: 10px;
}
.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: #818cf8;
}
a,
a:link,
a:visited,
a:hover,
a:active {
    text-decoration: none;
}
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.2s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}
.modal-fade-enter-active > div,
.modal-fade-leave-active > div {
    transition: transform 0.2s ease;
}
.modal-fade-enter-from > div,
.modal-fade-leave-to > div {
    transform: scale(0.95) translateY(10px);
}
</style>
