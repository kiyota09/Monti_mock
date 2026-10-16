import { ref, onMounted, onBeforeUnmount } from 'vue'

const STORAGE_PREFIX = 'sidebar_'

export function getStored(key, fallback = false) {
    try {
        const raw = sessionStorage.getItem(STORAGE_PREFIX + key)
        return raw !== null ? JSON.parse(raw) : fallback
    } catch {
        return fallback
    }
}

export function setStored(key, value) {
    try {
        sessionStorage.setItem(STORAGE_PREFIX + key, JSON.stringify(value))
    } catch { }
}

/**
 * A single persisted open/close dropdown (top-level module dropdowns,
 * e.g. HRM, CRM, MAN...).
 */
export function useDropdown(key) {
    const isOpen = ref(getStored(key))
    const toggle = () => {
        isOpen.value = !isOpen.value
        setStored(key, isOpen.value)
    }
    return { isOpen, toggle }
}

/**
 * Dynamically-keyed dropdowns created at runtime — used for the MAN
 * module's per-role dropdowns (knitting_yarn, dyeing_color, etc.)
 * where the set of keys depends on the logged-in user.
 */
export function useDynamicDropdowns(prefix = 'role_') {
    const states = ref({})

    const toggle = (key) => {
        states.value[key] = !states.value[key]
        setStored(`${prefix}${key}`, states.value[key])
    }

    const getState = (key, defaultState = false) => {
        if (states.value[key] === undefined) {
            states.value[key] = getStored(`${prefix}${key}`, defaultState)
        }
        return states.value[key]
    }

    return { states, toggle, getState }
}

/** Sidebar scroll position persistence. */
export function useSidebarScroll() {
    const scrollRef = ref(null)

    const onScroll = () => {
        if (scrollRef.value) setStored('scrollTop', scrollRef.value.scrollTop)
    }

    onMounted(() => {
        if (scrollRef.value) {
            scrollRef.value.scrollTop = getStored('scrollTop', 0)
            scrollRef.value.addEventListener('scroll', onScroll, { passive: true })
        }
    })

    onBeforeUnmount(() => {
        scrollRef.value?.removeEventListener('scroll', onScroll)
    })

    return { scrollRef }
}
