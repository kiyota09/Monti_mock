<script setup>
import { usePage, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { computed, ref, watch, nextTick } from 'vue'
import {
    ShieldCheck, X, LayoutDashboard, ShoppingCart, ShoppingBag, MessageSquare,
    Receipt, HelpCircle, User, Truck, Navigation, Clock, CalendarDays,
    History, HandCoins, FileText, Bell, UserCog2, Wallet,
} from 'lucide-vue-next'

import { MODULES } from './navItems/navConfig'
import { resolveNav } from './navItems/resolveNav'
import { useSidebarToggle } from './composables/useSidebarToggle'
import BottomNav from './MobileNav/BottomNav.vue'
import MoreSheet from './MobileNav/MoreSheet.vue'

const page = usePage()
const { mobileOpen, closeMobile } = useSidebarToggle()

const user = computed(() => page.props.auth.user)
const unreadCount = computed(() => page.props.notifications_unread || 0)
const client = computed(() => page.props.auth.client)
const supplier = computed(() => page.props.auth.supplier || (page.props.auth.user?.business_name ? page.props.auth.user : null))
const applicantUser = computed(() => page.props.auth.applicant)
const agencyUser = computed(() => page.props.auth.agency)
const currentUrl = computed(() => page.url)

const isEmployeePortal = computed(() => currentUrl.value.startsWith('/dashboard/employee-ui'))
const isClient = computed(() => !!client.value)
const isSupplier = computed(() => !!supplier.value || currentUrl.value.startsWith('/supplier'))
const isApplicant = computed(() => !!applicantUser.value || currentUrl.value.startsWith('/applicant'))
const isAgency = computed(() => !!agencyUser.value || currentUrl.value.startsWith('/agency'))
// Role workspaces that bypass module nav entirely (mirrors Sidebar.vue
// early-returns: LOG driver/conductor portals, trainee portal).
const userPosition = computed(() => String(user.value?.position || '').toLowerCase())
const isLogStaff = computed(() => String(user.value?.role || '').toUpperCase() === 'LOG' && userPosition.value === 'staff')
const isLogDriver = computed(() => isLogStaff.value && (user.value?.driver != null || user.value?.log_role === 'driver'))
const isLogConductor = computed(() => isLogStaff.value && !isLogDriver.value && (user.value?.conductor != null || user.value?.log_role === 'conductor'))
const isTrainee = computed(() => userPosition.value === 'trainee')
const isPortal = computed(() => isSupplier.value || isClient.value || isApplicant.value || isAgency.value || isEmployeePortal.value || isLogStaff.value || isTrainee.value)

const showMoreSheet = ref(false)
const drawerCloseRef = ref(null)

// Safe named-route resolution — falls back to a plain path when the
// named route is not registered (e.g. applicant portal routes).
function safeRoute(name, fallback) {
    try {
        return route(name)
    } catch {
        return fallback
    }
}

function resolveRoute(item) {
    if (!item.route) return item.href || '#'
    if (item.route.startsWith('/')) return item.route
    try {
        return route(item.route)
    } catch {
        return item.fallback || '#'
    }
}

// ─── PORTAL NAVS (mirror of Sidebar.vue so every portal works on mobile) ──
const portalItems = computed(() => {
    if (isSupplier.value) {
        return [
            { id: 'supplier_hub', label: 'Vendor Hub', icon: LayoutDashboard, href: safeRoute('supplier.dashboard', '/supplier/dashboard') },
            { id: 'supplier_messages', label: 'Messages', icon: MessageSquare, href: safeRoute('supplier.messages', '/supplier/messages') },
            { id: 'supplier_orders', label: 'Purchase Orders', icon: ShoppingCart, href: safeRoute('supplier.orders', '/supplier/orders') },
            { id: 'supplier_payments', label: 'Payments', icon: Wallet, href: safeRoute('supplier.payments', '/supplier/payments') },
            { id: 'supplier_products', label: 'My Products', icon: ShoppingBag, href: safeRoute('supplier.products', '/supplier/products') },
        ]
    }
    if (isClient.value) {
        return [
            { id: 'client_dashboard', label: 'Dashboard', icon: LayoutDashboard, href: safeRoute('client.dashboard', '/client/dashboard') },
            { id: 'client_products', label: 'Products', icon: ShoppingBag, href: safeRoute('client.products', '/client/products') },
            { id: 'client_conversations', label: 'Conversations', icon: MessageSquare, href: safeRoute('client.conversations', '/client/conversations') },
            { id: 'client_messages', label: 'Messages', icon: MessageSquare, href: safeRoute('client.messages', '/client/messages') },
            { id: 'client_orders', label: 'Orders', icon: ShoppingCart, href: safeRoute('client.orders', '/client/orders') },
            { id: 'client_tracking', label: 'Order Tracking', icon: Navigation, href: safeRoute('client.tracking', '/client/tracking') },
            { id: 'client_invoices', label: 'Invoices', icon: Receipt, href: safeRoute('client.invoices', '/client/invoices') },
            { id: 'client_payments', label: 'Payments', icon: Wallet, href: safeRoute('client.payments', '/client/payments') },
            { id: 'client_receiving', label: 'Receiving', icon: Truck, href: safeRoute('client.receiving', '/client/receiving') },
            { id: 'client_profile', label: 'Profile', icon: User, href: safeRoute('client.profile.edit', '/client/profile') },
            { id: 'client_support', label: 'Support', icon: HelpCircle, href: safeRoute('client.support', '/client/support') },
        ]
    }
    if (isEmployeePortal.value) {
        return [
            { id: 'employee_dashboard', label: 'Dashboard', icon: Clock, href: safeRoute('employee.ui.dashboard', '/dashboard/employee-ui') },
            { id: 'employee_leave', label: 'Leave Request', icon: History, href: safeRoute('employee.ui.leave', '/dashboard/employee-ui/leave') },
            { id: 'employee_payslip', label: 'Payslip', icon: HandCoins, href: safeRoute('employee.ui.payslip', '/dashboard/employee-ui/payslip') },
        ]
    }
    // LOG plant staff: drivers / conductors get only their portal (mirrors
    // Sidebar.vue). Staff with neither record get no nav, same as desktop.
    if (isLogDriver.value) {
        return [
            { id: 'log_driver_portal', label: 'My Deliveries', icon: Truck, href: safeRoute('logistics.driver.portal', '/dashboard/logistics/driver-portal') },
        ]
    }
    if (isLogConductor.value) {
        return [
            { id: 'log_conductor_portal', label: 'My Trips', icon: Navigation, href: safeRoute('logistics.conductor.portal', '/dashboard/logistics/conductor-portal') },
        ]
    }
    if (isLogStaff.value) {
        return []
    }
    if (isTrainee.value) {
        return [
            { id: 'trainee_timekeeping', label: 'Time In/Out', icon: Clock, href: safeRoute('trainee.timekeeping', '/dashboard/trainee/timekeeping') },
            { id: 'trainee_attendance', label: 'Attendance', icon: CalendarDays, href: safeRoute('trainee.attendance', '/dashboard/trainee/attendance') },
            { id: 'trainee_payslip', label: 'Payslips', icon: HandCoins, href: safeRoute('trainee.payslip', '/dashboard/trainee/payslip') },
        ]
    }
    if (isApplicant.value) {
        return [
            { id: 'applicant_dashboard', label: 'Dashboard', icon: LayoutDashboard, href: safeRoute('applicant.dashboard', '/applicant/dashboard') },
            { id: 'applicant_jobs', label: 'Find Jobs', icon: ShoppingBag, href: safeRoute('applicant.jobs.index', '/applicant/jobs') },
            { id: 'applicant_applications', label: 'My Applications', icon: FileText, href: safeRoute('applicant.applications.index', '/applicant/applications') },
            { id: 'applicant_interviews', label: 'Interviews', icon: CalendarDays, href: safeRoute('applicant.interviews', '/applicant/interviews') },
            { id: 'applicant_onboarding', label: 'Onboarding', icon: Clock, href: safeRoute('applicant.onboarding.index', '/applicant/onboarding') },
            { id: 'applicant_profile', label: 'My Profile', icon: User, href: safeRoute('applicant.profile.index', '/applicant/profile') },
            { id: 'applicant_account', label: 'Account', icon: UserCog2, href: safeRoute('applicant.account.index', '/applicant/account') },
        ]
    }
    if (isAgency.value) {
        return [
            { id: 'agency_dashboard', label: 'Dashboard', icon: LayoutDashboard, href: safeRoute('agency.dashboard', '/agency/dashboard') },
            { id: 'agency_workers', label: 'My Workers', icon: User, href: safeRoute('agency.workers.index', '/agency/workers') },
            { id: 'agency_requests', label: 'Requests', icon: FileText, href: safeRoute('agency.requests.index', '/agency/requests') },
            { id: 'agency_attendance', label: 'Attendance', icon: CalendarDays, href: safeRoute('agency.attendance.index', '/agency/attendance') },
            { id: 'agency_billing', label: 'Billing', icon: Receipt, href: safeRoute('agency.billing.index', '/agency/billing') },
        ]
    }
    return []
})

// Portal bottom-bar split: first 4 in the bar, the rest behind "More"
// (mirrors the internal MAX_BAR_ITEMS behaviour of 4 + More).
const MAX_PORTAL_BAR = 4
const portalBarItems = computed(() => portalItems.value.slice(0, MAX_PORTAL_BAR))
const portalMoreGroups = computed(() => {
    const rest = portalItems.value.slice(MAX_PORTAL_BAR)
    if (rest.length === 0) return []
    return [{ moduleId: 'portal', moduleLabel: sidebarLabel.value, pages: rest }]
})

const userPermissions = computed(() => {
    const perms = []
    if (user.value?.page_permissions) {
        for (const p of user.value.page_permissions) {
            perms.push(`${p.module}.${p.page}`)
        }
    }
    if (user.value?.permissions) {
        for (const [mod, pages] of Object.entries(user.value.permissions)) {
            for (const p of pages) {
                perms.push(`${mod}.${p}`)
            }
        }
    }
    return perms
})

const userModules = computed(() => {
    const mods = []
    if (user.value?.role && user.value?.role !== 'CEO' && user.value?.role !== 'COO') {
        mods.push(user.value.role)
    }
    if (user.value?.is_manufacturing_supervisor && user.value?.manufacturing_role) {
        mods.push('MAN')
    }
    if (user.value?.granted_modules) {
        mods.push(...user.value.granted_modules)
    }
    return [...new Set(mods)]
})

const navResult = computed(() => {
    const fallback = { barItems: [], moreGroups: [], totalCount: 0, showBar: false, showEmptyState: false }
    if (isPortal.value) {
        return fallback
    }
    try {
        return resolveNav(MODULES, user.value, {
            permissions: userPermissions.value,
            modules: userModules.value,
            // Access-control parity (mirrors usePermissions + IT grants):
            // explicit IT page rows, home (root) module, workforce keys and
            // the warehouse/inventory/ord/logistics access flags.
            explicitModules: user.value?.explicit_modules || [],
            rootModule: user.value?.root_module || null,
            workforce: user.value?.workforce_permissions || [],
            warehouse: flagAccess('WAR', 'has_warehouse_access'),
            inventory: flagAccess('INV', 'has_inventory_access'),
            ord: flagAccess('ORD', 'has_ord_access'),
            logistics: flagAccess('LOG', 'logistics_access'),
        }) || fallback
    } catch {
        return fallback
    }
})

// Mirrors usePermissions flag helpers: secretary/GM use their module access,
// manufacturing supervisors use granted modules, LOG managers always pass,
// everyone else uses the backend access flag.
function flagAccess(moduleKey, prop) {
    const pos = userPosition.value
    if (pos === 'secretary' || pos === 'special_officer') return userModules.value.includes(moduleKey)
    if (user.value?.is_manufacturing_supervisor) return (user.value?.granted_modules || []).includes(moduleKey)
    if (moduleKey === 'LOG' && String(user.value?.role || '').toUpperCase() === 'LOG' && pos === 'manager') return true
    return user.value?.[prop] === true
}

// Unified bottom-bar data for internal users AND portals.
const barItems = computed(() => {
    if (isPortal.value) return portalBarItems.value
    return navResult.value.barItems.map(item => ({
        ...item,
        href: resolveRoute(item),
        showUnread: item.showUnread || item.id === 'ceo_inbox',
    }))
})

const moreGroups = computed(() => {
    if (isPortal.value) return portalMoreGroups.value
    return navResult.value.moreGroups.map(g => ({
        ...g,
        pages: g.pages.map(p => ({
            ...p,
            href: resolveRoute(p),
        })),
    }))
})

const showBar = computed(() => {
    if (isPortal.value) return portalItems.value.length > 0
    return navResult.value.showBar
})

const showEmptyState = computed(() => isPortal.value
    ? portalItems.value.length === 0
    : navResult.value.showEmptyState)

const hasMore = computed(() => moreGroups.value.length > 0)

const moreActive = computed(() => {
    if (showMoreSheet.value) return true
    if (moreGroups.value.length === 0) return false
    const allMorePages = moreGroups.value.flatMap(g => g.pages)
    return allMorePages.some(p => {
        if (!p.href || p.href === '#') return false
        return currentUrl.value === p.href || currentUrl.value.startsWith(p.href + '/')
    })
})

// Full-page list for the slide-in drawer (Quick access + every group).
const drawerGroups = computed(() => {
    if (isPortal.value) {
        if (portalItems.value.length === 0) return []
        return [{ moduleId: 'portal', moduleLabel: sidebarLabel.value, pages: portalItems.value }]
    }
    const groups = []
    if (barItems.value.length > 0) {
        groups.push({ moduleId: 'quick', moduleLabel: 'Quick access', pages: barItems.value })
    }
    groups.push(...moreGroups.value)
    return groups
})

const isActive = (item) => {
    if (!item.href || item.href === '#') return false
    return currentUrl.value === item.href || currentUrl.value.startsWith(item.href + '/')
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
    if (isSupplier.value) return 'Supplier'
    if (isClient.value) return client.value?.business_type
    return user.value?.role
})
const displayPosition = computed(() => {
    if (isApplicant.value) return applicantUser.value?.status || 'Applicant'
    if (isAgency.value) return agencyUser.value?.email ?? 'Partner Agency'
    if (isSupplier.value) return supplier.value?.business_name ?? 'Vendor'
    if (isEmployeePortal.value) return user.value?.employee_id ?? 'Staff'
    if (isClient.value) return 'Partner'
    if (user.value?.is_manufacturing_supervisor) return 'Supervisor'
    return user.value?.position
})
const sidebarLabel = computed(() => {
    if (isApplicant.value) return 'Applicant'
    if (isAgency.value) return 'Agency'
    if (isSupplier.value) return 'Vendor'
    if (isClient.value) return 'Partner'
    if (isEmployeePortal.value) return 'Employee'
    return 'System'
})

// Close drawer + sheet on navigation; lock body scroll while either is open.
watch(() => page.url, () => {
    showMoreSheet.value = false
    closeMobile()
})

watch([mobileOpen, showMoreSheet], async ([drawerOpen]) => {
    document.body.style.overflow = (drawerOpen || showMoreSheet.value) ? 'hidden' : ''
    if (drawerOpen) {
        await nextTick()
        drawerCloseRef.value?.focus()
    }
})

const handleDrawerEsc = (e) => {
    if (e.key === 'Escape') closeMobile()
}

watch(mobileOpen, (open) => {
    if (open) window.addEventListener('keydown', handleDrawerEsc)
    else window.removeEventListener('keydown', handleDrawerEsc)
})
</script>

<template>
    <div class="md:hidden">
        <nav class="fixed top-0 left-0 right-0 z-[60] bg-white/80 dark:bg-gray-900/80 backdrop-blur-xl border-b border-gray-200/50 dark:border-gray-800/50 shadow-sm h-16 flex items-center justify-between px-4">
            <div class="flex items-center gap-3">
                <div class="animate-logo h-8 w-8 rounded-xl flex items-center justify-center shadow-lg bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 shadow-indigo-500/30">
                    <img src="/images/applogo.png" alt="Logo" class="h-5 w-5 object-contain brightness-0 invert" />
                </div>
                <div class="flex flex-col">
                    <span class="text-[14px] font-black tracking-tight text-gray-900 dark:text-white uppercase leading-none">
                        Monti <span class="text-indigo-700">Textile</span>
                    </span>
                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mt-0.5">
                        {{ sidebarLabel }}
                    </span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <div v-if="userPhotoUrl" class="h-8 w-8 rounded-full overflow-hidden border-2 border-indigo-200 dark:border-indigo-800">
                    <img :src="userPhotoUrl" alt="Profile" @error="photoBroken = true" class="h-full w-full object-cover" />
                </div>
                <div v-else class="h-8 w-8 rounded-full bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 flex items-center justify-center text-white text-xs font-bold">
                    {{ displayInitial }}
                </div>
            </div>
        </nav>

        <!-- Slide-in drawer (opened by the TopBar hamburger on mobile) -->
        <Teleport to="body">
            <Transition name="drawer">
                <div v-if="mobileOpen" class="fixed inset-0 z-[90] md:hidden" role="dialog" aria-modal="true" aria-label="Menu">
                    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeMobile" aria-hidden="true"></div>
                    <aside class="absolute left-0 top-0 bottom-0 w-[85%] max-w-xs bg-white dark:bg-gray-900 shadow-2xl flex flex-col overflow-hidden">
                        <div class="flex items-center gap-3 px-4 h-16 border-b border-gray-200/70 dark:border-gray-800 flex-shrink-0">
                            <div class="h-8 w-8 rounded-xl flex items-center justify-center bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800">
                                <img src="/images/applogo.png" alt="Logo" class="h-5 w-5 object-contain brightness-0 invert" />
                            </div>
                            <div class="flex flex-col flex-1 min-w-0">
                                <span class="text-[13px] font-black tracking-tight text-gray-900 dark:text-white uppercase leading-none truncate">
                                    Monti <span class="text-indigo-700">Textile</span>
                                </span>
                                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mt-0.5">{{ sidebarLabel }}</span>
                            </div>
                            <button
                                ref="drawerCloseRef"
                                @click="closeMobile"
                                class="p-2 rounded-xl text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                                aria-label="Close menu"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto px-2 py-3">
                            <div v-if="drawerGroups.length === 0" class="py-10 text-center px-4">
                                <div class="w-12 h-12 mx-auto rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center mb-3">
                                    <ShieldCheck class="w-6 h-6 text-indigo-500" />
                                </div>
                                <p class="text-sm font-bold text-gray-900 dark:text-white mb-1">No pages assigned yet</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                    All of your page permissions are still disabled.<br />
                                    Please wait for the admins to grant you access.
                                </p>
                            </div>
                            <div v-for="group in drawerGroups" :key="group.moduleId" class="mb-2">
                                <div class="px-3 py-2">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ group.moduleLabel }}</span>
                                </div>
                                <Link
                                    v-for="item in group.pages"
                                    :key="item.id"
                                    :href="item.href"
                                    @click="closeMobile"
                                    :class="[
                                        'flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors no-underline',
                                        isActive(item)
                                            ? 'bg-indigo-50 dark:bg-indigo-900/30'
                                            : 'hover:bg-gray-50 dark:hover:bg-gray-800/50'
                                    ]"
                                >
                                    <div :class="[
                                        'p-2 rounded-xl flex-shrink-0',
                                        isActive(item)
                                            ? 'bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white'
                                            : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400'
                                    ]">
                                        <component :is="item.icon" class="h-4 w-4" />
                                    </div>
                                    <span :class="[
                                        'text-sm truncate',
                                        isActive(item) ? 'text-indigo-700 dark:text-indigo-300 font-semibold' : 'text-gray-700 dark:text-gray-300 font-medium'
                                    ]">{{ item.label }}</span>
                                    <span
                                        v-if="item.badge || (item.showUnread && unreadCount > 0)"
                                        class="ml-auto min-w-[20px] h-5 px-1.5 rounded-full bg-rose-500 text-white text-[10px] font-bold inline-flex items-center justify-center"
                                    >{{ (item.badge || unreadCount) > 99 ? '99+' : (item.badge || unreadCount) }}</span>
                                </Link>
                            </div>
                        </div>

                        <div class="p-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/50 flex-shrink-0">
                            <div class="flex items-center gap-3">
                                <div v-if="userPhotoUrl" class="h-9 w-9 rounded-full overflow-hidden border-2 border-indigo-200 dark:border-indigo-800 flex-shrink-0">
                                    <img :src="userPhotoUrl" alt="Profile" @error="photoBroken = true" class="h-full w-full object-cover" />
                                </div>
                                <div v-else class="h-9 w-9 rounded-full bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                    {{ displayInitial }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-bold text-gray-900 dark:text-white truncate uppercase">{{ displayName }}</p>
                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate">{{ displayDepartment }}<span v-if="displayPosition"> · {{ displayPosition }}</span></p>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </Transition>
        </Teleport>

        <div v-if="showEmptyState" class="fixed bottom-0 left-0 right-0 z-50 bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl border-t border-gray-200/60 dark:border-gray-700/60 p-6 text-center">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center mb-3">
                <ShieldCheck class="w-6 h-6 text-indigo-500" />
            </div>
            <p class="text-sm font-bold text-gray-900 dark:text-white mb-1">No pages assigned yet</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                All of your page permissions are still disabled.<br />
                Please wait for the admins to grant you access.
            </p>
        </div>

        <BottomNav
            v-if="showBar"
            :items="barItems"
            :active-route="currentUrl"
            :more-active="moreActive"
            :has-more="hasMore"
            :unread-count="unreadCount"
            @open-more="showMoreSheet = true"
        />

        <MoreSheet
            :show="showMoreSheet"
            :groups="moreGroups"
            :active-route="currentUrl"
            @close="showMoreSheet = false"
        />
    </div>
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
.drawer-enter-active,
.drawer-leave-active {
    transition: opacity 0.25s ease;
}
.drawer-enter-active aside,
.drawer-leave-active aside {
    transition: transform 0.25s ease-out;
}
.drawer-enter-from,
.drawer-leave-to {
    opacity: 0;
}
.drawer-enter-from aside,
.drawer-leave-to aside {
    transform: translateX(-100%);
}
a,
a:link,
a:visited,
a:hover,
a:active {
    text-decoration: none;
}
</style>
