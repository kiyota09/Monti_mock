import { ref } from 'vue'
import { getStored, setStored } from './useSidebarPersistence'

// Shared sidebar visibility — one source of truth for the TopBar toggle,
// the desktop Sidebar (collapse) and the MobileSidebar (drawer).
// Desktop collapse persists per session; the mobile drawer is ephemeral.
const sidebarCollapsed = ref(getStored('collapsed', false))
const mobileOpen = ref(false)

export function useSidebarToggle() {
    const toggleDesktop = () => {
        sidebarCollapsed.value = !sidebarCollapsed.value
        setStored('collapsed', sidebarCollapsed.value)
    }
    const toggleMobile = () => {
        mobileOpen.value = !mobileOpen.value
    }
    const closeMobile = () => {
        mobileOpen.value = false
    }
    // Single TopBar toggle: drawer on mobile (<md), collapse on desktop.
    const toggleSidebar = () => {
        try {
            if (typeof window !== 'undefined' && window.matchMedia('(min-width: 768px)').matches) {
                toggleDesktop()
            } else {
                toggleMobile()
            }
        } catch {
            toggleDesktop()
        }
    }
    return { sidebarCollapsed, mobileOpen, toggleDesktop, toggleMobile, closeMobile, toggleSidebar }
}
