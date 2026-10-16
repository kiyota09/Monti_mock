import type { Updater } from "@tanstack/vue-table"
import type { ClassValue } from "clsx"
import type { Ref } from "vue"
import { clsx } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

// Role-based utilities
export const ROLES = {
    HRM: 'HRM',
    SCM: 'SCM'
} as const;

export const POSITIONS = {
    MANAGER: 'manager',
    STAFF: 'staff'
} as const;

// Minimal user type – adjust fields as needed
interface User {
    role: string;
    position: string;
}

export function getDashboardPath(user: User): string {
    const paths: Record<string, Record<string, string>> = {
        [ROLES.HRM]: {
            [POSITIONS.STAFF]: '/dashboard/hrm/employee',
            [POSITIONS.MANAGER]: '/dashboard/hrm/manager'
        },
        [ROLES.SCM]: {
            [POSITIONS.STAFF]: '/dashboard/scm/employee',
            [POSITIONS.MANAGER]: '/dashboard/scm/manager'
        }
    };
    
    return paths[user.role]?.[user.position] || '/dashboard';
}

export function hasPermission(
    user: User | null | undefined,
    requiredRole: string,
    requiredPosition: string | null = null
): boolean {
    if (!user) return false;
    if (user.role !== requiredRole) return false;
    if (requiredPosition && user.position !== requiredPosition) return false;
    return true;
}

export function valueUpdater<T extends Updater<any>>(updaterOrValue: T, ref: Ref) {
  ref.value
    = typeof updaterOrValue === "function"
      ? updaterOrValue(ref.value)
      : updaterOrValue
}