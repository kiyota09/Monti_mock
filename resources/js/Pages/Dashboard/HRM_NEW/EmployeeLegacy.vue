<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Users, UserCheck, Eye, Edit, ShieldOff, ShieldCheck, History, X, Search, Building2,
    CheckCircle, XCircle, UserMinus, UserPlus, Calendar, Mail, Briefcase, MoreHorizontal,
    ArrowUpCircle, RotateCcw, Crown, UserCog, FileText, Info, MapPin, Phone, Mail as MailIcon,
    Calendar as CalendarIcon, Users as UsersIcon, BookOpen, Briefcase as BriefcaseIcon,
    Award, Heart, Shield, AlertCircle, BadgeCheck
} from 'lucide-vue-next';

const props = defineProps({
    employees: {
        type: Array,
        default: () => []
    },
    permissions: {
        type: Object,
        default: () => ({
            can_promote: false,
            can_demote_manager: false,
            current_user_rank: 0
        })
    },
    page_permissions: {
        type: Object,
        default: () => ({})
    }
});

// Check if user can edit employees (from RBAC) — key must match the
// canonical page identifier enforced by routes ('employee').
const canEditEmployees = computed(() => props.page_permissions?.employee === 'edit');

// Toast notification
const showToast = ref(false);
const toastMessage = ref('');
const triggerToast = (msg) => {
    toastMessage.value = msg;
    showToast.value = true;
    setTimeout(() => { showToast.value = false; }, 4000);
};

// Flash messages from server
const page = usePage();
if (page.props.flash?.message) {
    triggerToast(page.props.flash.message);
}
if (page.props.flash?.error) {
    triggerToast(page.props.flash.error);
}

// RANK HIERARCHY LOGIC (for display only, permissions come from backend)
const getRank = (role, position) => {
    if (role === 'CEO') return 60;
    const pos = (position || '').toLowerCase();
    if (pos === 'secretary') return 50;
    if (pos === 'special_officer') return 40;
    if (pos === 'manager') return 30;
    if (pos === 'supervisor') return 20;
    if (pos === 'staff') return 10;
    return 0;
};

const currentUserRank = computed(() => props.permissions.current_user_rank);
const canPromote = computed(() => props.permissions.can_promote);
const canDemoteManager = computed(() => props.permissions.can_demote_manager);

const canManage = (emp) => {
    return currentUserRank.value > getRank(emp.role, emp.position);
};

const canShowPromote = (emp) => {
    return emp.position === 'staff' && canPromote.value;
};

const canShowDemote = (emp) => {
    return emp.position === 'manager' && canDemoteManager.value;
};

// Filters
const searchQuery = ref('');
const activeDept = ref('ALL');
const showDeactivated = ref(false);

const departments = ['ALL', 'HRM', 'CRM', 'MAN', 'LOG'];

const isActive = (emp) => {
    return emp.is_active === 1 || emp.is_active === true;
};

const filteredEmployees = computed(() => {
    let list = props.employees;
    list = list.filter(emp => isActive(emp) !== showDeactivated.value);
    if (activeDept.value !== 'ALL') {
        list = list.filter(emp => emp.role === activeDept.value);
    }
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(emp =>
            emp.name.toLowerCase().includes(q) ||
            emp.email.toLowerCase().includes(q) ||
            emp.role.toLowerCase().includes(q) ||
            (emp.employee_id && emp.employee_id.toLowerCase().includes(q))
        );
    }
    return list;
});

// Split into three groups: executives (secretary, special_officer), managers, staff (including supervisors)
const executives = computed(() => filteredEmployees.value.filter(emp => ['secretary', 'special_officer'].includes(emp.position)));
const managers = computed(() => filteredEmployees.value.filter(emp => emp.position === 'manager'));
const staff = computed(() => filteredEmployees.value.filter(emp => emp.position === 'staff'));

// Modals
const isViewModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isManageModalOpen = ref(false);
const isHistoryModalOpen = ref(false);
const isDeactivateModalOpen = ref(false);
const isActivateModalOpen = ref(false);
const isPromoteModalOpen = ref(false);
const isDemoteModalOpen = ref(false);
const selectedEmployee = ref(null);
const applicantDetails = ref(null);
const viewModalTab = ref('employee');
const deactivationReason = ref('');

// Forms
const editForm = ref({
    id: null,
    name: '',
    email: '',
    role: '',
    position: '',
    is_active: true
});

const manageForm = ref({
    role: '',
    position: ''
});

const roleOptions = [
    { value: 'HRM', label: 'Human Resource' },
    { value: 'CRM', label: 'Customer Relationship' },
    { value: 'MAN', label: 'Manufacturing' },
    { value: 'LOG', label: 'Logistics' }
];

const positionOptions = [
    { value: 'staff', label: 'Staff' },
    { value: 'manager', label: 'Manager' }
];

// Helper functions
const getInitials = (name) => name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
const getAvatarColor = (id) => {
    const colors = [
        'bg-blue-100 text-blue-600',
        'bg-violet-100 text-violet-600',
        'bg-emerald-100 text-emerald-600',
        'bg-orange-100 text-orange-600',
        'bg-pink-100 text-pink-600'
    ];
    return colors[id % colors.length];
};
const formatDate = (date) => date ? new Date(date).toLocaleDateString() : 'N/A';
const formatDateTime = (date) => date ? new Date(date).toLocaleString() : 'N/A';

// Fetch applicant details when opening view modal
const openViewModal = async (emp) => {
    selectedEmployee.value = emp;
    viewModalTab.value = 'employee';
    applicantDetails.value = null;
    try {
        const response = await axios.get(route('hrm.employees.show', emp.id));
        applicantDetails.value = response.data.applicant;
    } catch (error) {
        console.error('Failed to load applicant details', error);
    }
    isViewModalOpen.value = true;
};
const closeViewModal = () => {
    isViewModalOpen.value = false;
    selectedEmployee.value = null;
    applicantDetails.value = null;
};

const openEditModal = (emp) => {
    if (!canEditEmployees.value) {
        triggerToast('You do not have permission to edit employees.', 'error');
        return;
    }
    editForm.value = {
        id: emp.id,
        name: emp.name,
        email: emp.email,
        role: emp.role,
        position: emp.position,
        is_active: emp.is_active
    };
    isEditModalOpen.value = true;
};
const closeEditModal = () => {
    isEditModalOpen.value = false;
    editForm.value = { id: null, name: '', email: '', role: '', position: '', is_active: true };
};

const updateEmployee = () => {
    router.patch(route('hrm.employees.update', editForm.value.id), {
        name: editForm.value.name,
        email: editForm.value.email,
        role: editForm.value.role,
        position: editForm.value.position,
        is_active: editForm.value.is_active
    }, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast('Employee updated successfully.');
            closeEditModal();
        },
        onError: (errors) => {
            triggerToast(Object.values(errors)[0] || 'Update failed.');
        }
    });
};

const openManageModal = (emp) => {
    if (!canEditEmployees.value) {
        triggerToast('You do not have permission to manage role/position.', 'error');
        return;
    }
    selectedEmployee.value = emp;
    manageForm.value = {
        role: emp.role,
        position: emp.position
    };
    isManageModalOpen.value = true;
};
const closeManageModal = () => {
    isManageModalOpen.value = false;
    selectedEmployee.value = null;
};

const updateRolePosition = () => {
    if (!selectedEmployee.value) return;
    router.patch(route('hrm.employees.update-role-position', selectedEmployee.value.id), {
        role: manageForm.value.role,
        position: manageForm.value.position
    }, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast('Role/position updated successfully.');
            closeManageModal();
        },
        onError: (errors) => {
            triggerToast(Object.values(errors)[0] || 'Update failed.');
        }
    });
};

const openHistoryModal = (emp) => {
    selectedEmployee.value = emp;
    isHistoryModalOpen.value = true;
};
const closeHistoryModal = () => {
    isHistoryModalOpen.value = false;
    selectedEmployee.value = null;
};

// Promote modal
const openPromoteModal = (emp) => {
    if (!canEditEmployees.value) {
        triggerToast('You do not have permission to promote employees.', 'error');
        return;
    }
    selectedEmployee.value = emp;
    isPromoteModalOpen.value = true;
};
const closePromoteModal = () => {
    isPromoteModalOpen.value = false;
    selectedEmployee.value = null;
};
const confirmPromote = () => {
    router.post(route('hrm.employees.promote-to-manager', selectedEmployee.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast(`${selectedEmployee.value.name} promoted to Manager.`);
            closePromoteModal();
        },
        onError: (errors) => {
            triggerToast(Object.values(errors)[0] || 'Promotion failed.');
        }
    });
};

// Demote modal
const openDemoteModal = (emp) => {
    if (!canEditEmployees.value) {
        triggerToast('You do not have permission to demote employees.', 'error');
        return;
    }
    selectedEmployee.value = emp;
    isDemoteModalOpen.value = true;
};
const closeDemoteModal = () => {
    isDemoteModalOpen.value = false;
    selectedEmployee.value = null;
};
const confirmDemote = () => {
    router.post(route('hrm.employees.demote-to-staff', selectedEmployee.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast(`${selectedEmployee.value.name} demoted to Staff.`);
            closeDemoteModal();
        },
        onError: (errors) => {
            triggerToast(Object.values(errors)[0] || 'Demotion failed.');
        }
    });
};

// Deactivate / Reactivate modals
const openDeactivateModal = (emp) => {
    if (!canEditEmployees.value) {
        triggerToast('You do not have permission to deactivate employees.', 'error');
        return;
    }
    selectedEmployee.value = emp;
    deactivationReason.value = '';
    isDeactivateModalOpen.value = true;
};
const closeDeactivateModal = () => {
    isDeactivateModalOpen.value = false;
    selectedEmployee.value = null;
    deactivationReason.value = '';
};
const confirmDeactivate = () => {
    if (!deactivationReason.value.trim()) {
        triggerToast('Please provide a reason for deactivation.');
        return;
    }
    router.delete(route('hrm.employees.toggle-status', selectedEmployee.value.id), {
        data: { reason: deactivationReason.value },
        preserveScroll: true,
        onSuccess: () => {
            triggerToast(`Employee ${selectedEmployee.value.name} deactivated.`);
            closeDeactivateModal();
        },
        onError: (errors) => {
            triggerToast(Object.values(errors)[0] || 'Deactivation failed.');
        }
    });
};

const openActivateModal = (emp) => {
    if (!canEditEmployees.value) {
        triggerToast('You do not have permission to reactivate employees.', 'error');
        return;
    }
    selectedEmployee.value = emp;
    deactivationReason.value = '';
    isActivateModalOpen.value = true;
};
const closeActivateModal = () => {
    isActivateModalOpen.value = false;
    selectedEmployee.value = null;
    deactivationReason.value = '';
};
const confirmActivate = () => {
    if (!deactivationReason.value.trim()) {
        triggerToast('Please provide a reason for reactivation.');
        return;
    }
    router.delete(route('hrm.employees.toggle-status', selectedEmployee.value.id), {
        data: { reason: deactivationReason.value },
        preserveScroll: true,
        onSuccess: () => {
            triggerToast(`Employee ${selectedEmployee.value.name} reactivated.`);
            closeActivateModal();
        },
        onError: (errors) => {
            triggerToast(Object.values(errors)[0] || 'Reactivation failed.');
        }
    });
};
</script>

<template>
    <Head title="Employee Management" />
    <AuthenticatedLayout>

        <!-- Toast Notification -->
        <Transition name="toast">
            <div v-if="showToast"
                class="fixed top-5 right-5 z-[100] flex items-center gap-3 px-5 py-3.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-xl shadow-xl border border-white/10 dark:border-gray-200">
                <CheckCircle class="h-4 w-4 text-emerald-400 dark:text-emerald-600 flex-shrink-0" />
                <p class="text-sm font-medium">{{ toastMessage }}</p>
            </div>
        </Transition>

        <div class="px-4 sm:px-8 py-8 max-w-7xl mx-auto space-y-8">

            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-5">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white tracking-tight">
                        Employee Directory
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ showDeactivated ? 'Showing deactivated accounts' : 'Showing active personnel' }}
                        <span v-if="!canEditEmployees" class="ml-2 text-xs text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">View only</span>
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Search -->
                    <div class="relative">
                        <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search by name, ID, email…"
                            class="pl-10 pr-4 py-2.5 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 text-sm text-gray-800 dark:text-gray-200 placeholder-gray-400 w-60 transition outline-none"
                        />
                    </div>
                    <!-- Toggle deactivated -->
                    <button
                        @click="showDeactivated = !showDeactivated"
                        :class="[
                            'inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition-all border',
                            showDeactivated
                                ? 'bg-red-50 text-red-600 border-red-200 dark:bg-red-900/20 dark:border-red-800 dark:text-red-400'
                                : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700'
                        ]">
                        <UserMinus v-if="!showDeactivated" class="h-4 w-4" />
                        <UserCheck v-else class="h-4 w-4" />
                        {{ showDeactivated ? 'View Active' : 'Deactivated' }}
                    </button>
                </div>
            </div>

            <!-- Department Filter -->
            <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1">
                <button
                    v-for="dept in departments"
                    :key="dept"
                    @click="activeDept = dept"
                    :class="[
                        'px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all border',
                        activeDept === dept
                            ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                            : 'bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-700 dark:hover:text-gray-200'
                    ]">
                    {{ dept === 'ALL' ? 'All Departments' : dept }}
                </button>
            </div>

            <!-- Executives Section (Secretary & Special Officer) -->
            <div class="space-y-4" v-if="executives.length > 0">
                <div class="flex items-center gap-3">
                    <h2 class="text-base font-semibold text-gray-800 dark:text-gray-200">Executive Leadership</h2>
                    <span class="px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 text-xs font-semibold">
                        {{ executives.length }}
                    </span>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div
                        v-for="emp in executives"
                        :key="emp.id"
                        class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-100 dark:border-gray-700 flex items-center justify-between hover:border-amber-300 dark:hover:border-amber-600 hover:shadow-md transition-all duration-200 group">
                        <div class="flex items-center gap-4 min-w-0">
                            <div class="relative flex-shrink-0">
                                <img
                                    v-if="emp.profile_photo_url"
                                    :src="emp.profile_photo_url"
                                    class="h-12 w-12 rounded-xl object-cover border border-gray-100 dark:border-gray-600" />
                                <div
                                    v-else
                                    :class="['h-12 w-12 rounded-xl flex items-center justify-center font-semibold text-base', getAvatarColor(emp.id)]">
                                    {{ getInitials(emp.name) }}
                                </div>
                                <span
                                    v-if="!isActive(emp)"
                                    class="absolute -top-1 -right-1 h-3.5 w-3.5 bg-red-500 border-2 border-white dark:border-gray-800 rounded-full">
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="font-semibold text-gray-900 dark:text-white text-sm leading-tight truncate group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors">
                                    {{ emp.name }}
                                </h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                    ID: {{ emp.employee_id || 'N/A' }}
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 truncate">
                                    {{ emp.email }}
                                </p>
                                <div class="flex items-center gap-2 mt-1.5">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400">
                                        {{ emp.role }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400">
                                        {{ emp.position.replace('_', ' ') }}
                                    </span>
                                    <span :class="[
                                        'px-2 py-0.5 rounded-full text-[10px] font-semibold',
                                        isActive(emp)
                                            ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400'
                                            : 'bg-red-50 dark:bg-red-900/30 text-red-500 dark:text-red-400'
                                    ]">
                                        {{ isActive(emp) ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 flex-shrink-0 ml-3">
                            <button
                                @click="openViewModal(emp)"
                                class="p-2 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors"
                                title="View Details">
                                <Eye class="h-4 w-4" />
                            </button>
                            <template v-if="canManage(emp) && canEditEmployees">
                                <button
                                    @click="openHistoryModal(emp)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors"
                                    title="Audit Logs">
                                    <History class="h-4 w-4" />
                                </button>
                                <button
                                    v-if="isActive(emp)"
                                    @click="openDeactivateModal(emp)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                                    title="Deactivate">
                                    <ShieldOff class="h-4 w-4" />
                                </button>
                                <button
                                    v-else
                                    @click="openActivateModal(emp)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors"
                                    title="Reactivate">
                                    <ShieldCheck class="h-4 w-4" />
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Managers Section -->
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <h2 class="text-base font-semibold text-gray-800 dark:text-gray-200">Managers</h2>
                    <span class="px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 text-xs font-semibold">
                        {{ managers.length }}
                    </span>
                </div>

                <div v-if="managers.length === 0"
                    class="flex flex-col items-center justify-center py-16 rounded-xl border-2 border-dashed border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/20">
                    <Users class="h-10 w-10 text-gray-300 dark:text-gray-600 mb-3" />
                    <p class="text-sm text-gray-400 dark:text-gray-500">No managers found</p>
                </div>

                <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div
                        v-for="emp in managers"
                        :key="emp.id"
                        class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-100 dark:border-gray-700 flex items-center justify-between hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-md transition-all duration-200 group">
                        <div class="flex items-center gap-4 min-w-0">
                            <div class="relative flex-shrink-0">
                                <img
                                    v-if="emp.profile_photo_url"
                                    :src="emp.profile_photo_url"
                                    class="h-12 w-12 rounded-xl object-cover border border-gray-100 dark:border-gray-600" />
                                <div
                                    v-else
                                    :class="['h-12 w-12 rounded-xl flex items-center justify-center font-semibold text-base', getAvatarColor(emp.id)]">
                                    {{ getInitials(emp.name) }}
                                </div>
                                <span
                                    v-if="!isActive(emp)"
                                    class="absolute -top-1 -right-1 h-3.5 w-3.5 bg-red-500 border-2 border-white dark:border-gray-800 rounded-full">
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="font-semibold text-gray-900 dark:text-white text-sm leading-tight truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                    {{ emp.name }}
                                </h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                    ID: {{ emp.employee_id || 'N/A' }}
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 truncate">
                                    {{ emp.email }}
                                </p>
                                <div class="flex items-center gap-2 mt-1.5">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400">
                                        {{ emp.role }}
                                    </span>
                                    <span :class="[
                                        'px-2 py-0.5 rounded-full text-[10px] font-semibold',
                                        isActive(emp)
                                            ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400'
                                            : 'bg-red-50 dark:bg-red-900/30 text-red-500 dark:text-red-400'
                                    ]">
                                        {{ isActive(emp) ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 flex-shrink-0 ml-3">
                            <button
                                @click="openViewModal(emp)"
                                class="p-2 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors"
                                title="View Details">
                                <Eye class="h-4 w-4" />
                            </button>
                            <template v-if="canManage(emp) && canEditEmployees">
                                <button
                                    @click="openHistoryModal(emp)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors"
                                    title="Audit Logs">
                                    <History class="h-4 w-4" />
                                </button>
                                <button
                                    @click="openEditModal(emp)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors"
                                    title="Edit Details">
                                    <Edit class="h-4 w-4" />
                                </button>
                                <button
                                    v-if="canShowDemote(emp)"
                                    @click="openDemoteModal(emp)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                                    title="Demote to Staff">
                                    <RotateCcw class="h-4 w-4" />
                                </button>
                                <button
                                    v-if="isActive(emp)"
                                    @click="openDeactivateModal(emp)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                                    title="Deactivate">
                                    <ShieldOff class="h-4 w-4" />
                                </button>
                                <button
                                    v-else
                                    @click="openActivateModal(emp)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors"
                                    title="Reactivate">
                                    <ShieldCheck class="h-4 w-4" />
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Staff Section -->
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <h2 class="text-base font-semibold text-gray-800 dark:text-gray-200">Staff</h2>
                    <span class="px-2 py-0.5 rounded-full bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-xs font-semibold">
                        {{ staff.length }}
                    </span>
                </div>

                <div v-if="staff.length === 0"
                    class="flex flex-col items-center justify-center py-16 rounded-xl border-2 border-dashed border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/20">
                    <Users class="h-10 w-10 text-gray-300 dark:text-gray-600 mb-3" />
                    <p class="text-sm text-gray-400 dark:text-gray-500">No staff found</p>
                </div>

                <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div
                        v-for="emp in staff"
                        :key="emp.id"
                        class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-100 dark:border-gray-700 flex items-center justify-between hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-md transition-all duration-200 group">
                        <div class="flex items-center gap-4 min-w-0">
                            <div class="relative flex-shrink-0">
                                <img
                                    v-if="emp.profile_photo_url"
                                    :src="emp.profile_photo_url"
                                    class="h-12 w-12 rounded-xl object-cover border border-gray-100 dark:border-gray-600" />
                                <div
                                    v-else
                                    :class="['h-12 w-12 rounded-xl flex items-center justify-center font-semibold text-base', getAvatarColor(emp.id)]">
                                    {{ getInitials(emp.name) }}
                                </div>
                                <span
                                    v-if="!isActive(emp)"
                                    class="absolute -top-1 -right-1 h-3.5 w-3.5 bg-red-500 border-2 border-white dark:border-gray-800 rounded-full">
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="font-semibold text-gray-900 dark:text-white text-sm leading-tight truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                    {{ emp.name }}
                                    <span v-if="emp.is_manufacturing_supervisor" class="ml-1.5 inline-flex items-center gap-0.5 text-[9px] font-semibold bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 px-1.5 py-0.5 rounded-full">
                                        <BadgeCheck class="w-2.5 h-2.5" /> Supervisor
                                    </span>
                                </h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                    ID: {{ emp.employee_id || 'N/A' }}
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 truncate">
                                    {{ emp.email }}
                                </p>
                                <div class="flex items-center gap-2 mt-1.5">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">
                                        {{ emp.role }}
                                    </span>
                                    <span :class="[
                                        'px-2 py-0.5 rounded-full text-[10px] font-semibold',
                                        isActive(emp)
                                            ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400'
                                            : 'bg-red-50 dark:bg-red-900/30 text-red-500 dark:text-red-400'
                                    ]">
                                        {{ isActive(emp) ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 flex-shrink-0 ml-3">
                            <button
                                @click="openViewModal(emp)"
                                class="p-2 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors"
                                title="View Details">
                                <Eye class="h-4 w-4" />
                            </button>
                            <template v-if="canManage(emp) && canEditEmployees">
                                <button
                                    @click="openHistoryModal(emp)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors"
                                    title="Audit Logs">
                                    <History class="h-4 w-4" />
                                </button>
                                <button
                                    @click="openEditModal(emp)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors"
                                    title="Edit Details">
                                    <Edit class="h-4 w-4" />
                                </button>
                                <button
                                    v-if="canShowPromote(emp)"
                                    @click="openPromoteModal(emp)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors"
                                    title="Promote to Manager">
                                    <ArrowUpCircle class="h-4 w-4" />
                                </button>
                                <button
                                    v-if="isActive(emp)"
                                    @click="openDeactivateModal(emp)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                                    title="Deactivate">
                                    <ShieldOff class="h-4 w-4" />
                                </button>
                                <button
                                    v-else
                                    @click="openActivateModal(emp)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors"
                                    title="Reactivate">
                                    <ShieldCheck class="h-4 w-4" />
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== VIEW MODAL ===== -->
        <div v-if="isViewModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
            @click.self="closeViewModal">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col border border-gray-100 dark:border-gray-800">

                <!-- Modal Header -->
                <div class="bg-blue-600 px-6 py-5 flex-shrink-0">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-4">
                            <div class="h-14 w-14 flex-shrink-0 rounded-xl overflow-hidden border-2 border-white/30">
                                <img v-if="selectedEmployee?.profile_photo_url" :src="selectedEmployee.profile_photo_url" class="h-full w-full object-cover" />
                                <div v-else class="h-full w-full bg-white/20 flex items-center justify-center text-xl font-semibold text-white">
                                    {{ getInitials(selectedEmployee?.name) }}
                                </div>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-white">{{ selectedEmployee?.name }}</h2>
                                <p class="text-blue-200 text-xs mt-0.5">{{ selectedEmployee?.role }} · {{ selectedEmployee?.position }}</p>
                                <p class="text-blue-300 text-xs">ID: {{ selectedEmployee?.employee_id || 'N/A' }} · {{ selectedEmployee?.email }}</p>
                            </div>
                        </div>
                        <button @click="closeViewModal" class="p-1.5 rounded-lg text-white/60 hover:text-white hover:bg-white/10 transition-colors">
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <!-- Tabs -->
                    <div class="flex gap-1 mt-4">
                        <button
                            @click="viewModalTab = 'employee'"
                            :class="[
                                'px-4 py-1.5 rounded-lg text-sm font-medium transition-all',
                                viewModalTab === 'employee' ? 'bg-white/20 text-white' : 'text-blue-200 hover:text-white hover:bg-white/10'
                            ]">
                            Employee Info
                        </button>
                        <button
                            v-if="applicantDetails"
                            @click="viewModalTab = 'applicant'"
                            :class="[
                                'px-4 py-1.5 rounded-lg text-sm font-medium transition-all',
                                viewModalTab === 'applicant' ? 'bg-white/20 text-white' : 'text-blue-200 hover:text-white hover:bg-white/10'
                            ]">
                            Application Details
                        </button>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="overflow-y-auto p-6 flex-1 space-y-5">

                    <!-- Employee Info Tab -->
                    <div v-if="viewModalTab === 'employee'" class="space-y-5">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-xl">
                                <p class="text-xs text-gray-400 font-medium mb-1">Employee ID</p>
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ selectedEmployee?.employee_id || 'N/A' }}</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-xl">
                                <p class="text-xs text-gray-400 font-medium mb-1">Join Date</p>
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ formatDate(selectedEmployee?.join_date) }}</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-xl">
                                <p class="text-xs text-gray-400 font-medium mb-1">Status</p>
                                <span :class="[
                                    'inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold',
                                    isActive(selectedEmployee) ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400'
                                ]">
                                    <span :class="['w-1.5 h-1.5 rounded-full', isActive(selectedEmployee) ? 'bg-emerald-500' : 'bg-red-500']"></span>
                                    {{ isActive(selectedEmployee) ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-xl">
                                <p class="text-xs text-gray-400 font-medium mb-1">Department</p>
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ selectedEmployee?.role }}</p>
                            </div>
                        </div>
                        <!-- Audit Logs inline -->
                        <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-xl">
                            <p class="text-xs text-gray-400 font-medium mb-3">Recent Audit Logs</p>
                            <div class="max-h-48 overflow-y-auto space-y-2">
                                <div v-for="log in selectedEmployee?.audit_logs" :key="log.id"
                                    class="flex items-start gap-3 pb-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                                    <span :class="[
                                        'px-2 py-0.5 rounded-md text-[10px] font-semibold flex-shrink-0 mt-0.5',
                                        log.action === 'deactivate' ? 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' :
                                        log.action === 'reactivate' ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400'
                                    ]">{{ log.action }}</span>
                                    <div>
                                        <p class="text-xs text-gray-700 dark:text-gray-300">{{ log.reason }}</p>
                                        <p class="text-[10px] text-gray-400 mt-0.5">{{ formatDateTime(log.created_at) }}</p>
                                    </div>
                                </div>
                                <div v-if="!selectedEmployee?.audit_logs?.length" class="text-center text-gray-400 text-xs py-4">No audit logs found.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Applicant Details Tab (unchanged) -->
                    <div v-if="viewModalTab === 'applicant' && applicantDetails" class="space-y-6">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white flex items-center gap-2 mb-3">
                                <span class="w-1 h-4 bg-blue-500 rounded-full inline-block"></span>
                                Personal Information
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Full Name</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.first_name }} {{ applicantDetails.middle_name }} {{ applicantDetails.last_name }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Date of Birth</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ formatDate(applicantDetails.date_of_birth) }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Place of Birth</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.place_of_birth || 'N/A' }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Citizenship</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.citizenship || 'N/A' }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Civil Status</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.civil_status || 'N/A' }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Sex</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.sex || 'N/A' }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Religion</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.religion || 'N/A' }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Weight / Height</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.weight }} kg / {{ applicantDetails.height }} cm</p></div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white flex items-center gap-2 mb-3">
                                <span class="w-1 h-4 bg-indigo-500 rounded-full inline-block"></span>
                                Contact Information
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Phone</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.phone_number || 'N/A' }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Email</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ selectedEmployee?.email }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg md:col-span-2"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Address</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.street_address }}, {{ applicantDetails.city }}, {{ applicantDetails.state_province }} {{ applicantDetails.postal_zip_code }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">SSS</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.sss_number || 'N/A' }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">PhilHealth</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.philhealth_number || 'N/A' }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Pag-IBIG</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.pagibig_number || 'N/A' }}</p></div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white flex items-center gap-2 mb-3">
                                <span class="w-1 h-4 bg-pink-500 rounded-full inline-block"></span>
                                Family Background
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Spouse</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.spouse_name || 'N/A' }} {{ applicantDetails.spouse_occupation ? `(${applicantDetails.spouse_occupation})` : '' }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Children</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.number_of_children || 0 }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Mother</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.mother_name || 'N/A' }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Father</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.father_name || 'N/A' }}</p></div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white flex items-center gap-2 mb-3">
                                <span class="w-1 h-4 bg-amber-500 rounded-full inline-block"></span>
                                Education
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Elementary</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.elementary_school || 'N/A' }} {{ applicantDetails.elementary_year ? `(${applicantDetails.elementary_year})` : '' }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">High School</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.high_school || 'N/A' }} {{ applicantDetails.high_year ? `(${applicantDetails.high_year})` : '' }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">College</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.college || 'N/A' }} {{ applicantDetails.college_year ? `(${applicantDetails.college_year})` : '' }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Vocational</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.vocational || 'N/A' }} {{ applicantDetails.vocational_year ? `(${applicantDetails.vocational_year})` : '' }}</p></div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white flex items-center gap-2 mb-3">
                                <span class="w-1 h-4 bg-teal-500 rounded-full inline-block"></span>
                                Employment History
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Previous Company</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.previous_employment_company || 'N/A' }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Position</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.previous_employment_position || 'N/A' }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Department</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.previous_employment_department || 'N/A' }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Year</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.previous_employment_when || 'N/A' }}</p></div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white flex items-center gap-2 mb-3">
                                <span class="w-1 h-4 bg-purple-500 rounded-full inline-block"></span>
                                Other Information
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Position Applied</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.position_applied || 'N/A' }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Notice Period</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.notice_period || 'N/A' }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Application Status</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.status || 'N/A' }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Assigned Module</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.assigned_module || 'N/A' }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Rejection Reason</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.rejection_reason || 'N/A' }}</p></div>
                                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-1">Interview Feedback</p><p class="text-sm text-gray-800 dark:text-gray-200">{{ applicantDetails.interview_feedback || 'N/A' }}</p></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-800 flex justify-end">
                    <button @click="closeViewModal"
                        class="px-5 py-2 rounded-lg text-sm font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- ===== EDIT MODAL ===== -->
        <div v-if="isEditModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
            @click.self="closeEditModal">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-gray-100 dark:border-gray-800">
                <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100 dark:border-gray-800">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Edit Employee</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Update employee information</p>
                    </div>
                    <button @click="closeEditModal" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Full Name</label>
                        <input v-model="editForm.name" type="text"
                            class="w-full px-3.5 py-2.5 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm text-gray-800 dark:text-gray-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none transition" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Email</label>
                        <input v-model="editForm.email" type="email"
                            class="w-full px-3.5 py-2.5 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm text-gray-800 dark:text-gray-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none transition" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Department</label>
                        <select v-model="editForm.role"
                            class="w-full px-3.5 py-2.5 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm text-gray-800 dark:text-gray-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none transition">
                            <option v-for="opt in roleOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Position</label>
                        <select v-model="editForm.position"
                            class="w-full px-3.5 py-2.5 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm text-gray-800 dark:text-gray-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none transition">
                            <option value="staff">Staff</option>
                            <option value="manager">Manager</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" v-model="editForm.is_active"
                            class="w-4 h-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500/20" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Mark as active</span>
                    </label>
                </div>
                <div class="flex gap-3 px-6 py-4 border-t border-gray-100 dark:border-gray-800">
                    <button @click="closeEditModal"
                        class="flex-1 py-2.5 rounded-lg text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors border border-gray-200 dark:border-gray-700">
                        Cancel
                    </button>
                    <button @click="updateEmployee"
                        class="flex-1 py-2.5 rounded-lg text-sm font-semibold bg-amber-500 hover:bg-amber-600 text-white transition-colors">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>

        <!-- ===== PROMOTE MODAL ===== -->
        <div v-if="isPromoteModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
            @click.self="closePromoteModal">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl max-w-sm w-full overflow-hidden border border-gray-100 dark:border-gray-800">
                <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-800">
                    <div class="w-10 h-10 rounded-xl bg-green-100 dark:bg-green-900/30 flex items-center justify-center mb-3">
                        <ArrowUpCircle class="h-5 w-5 text-green-600 dark:text-green-400" />
                    </div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Confirm Promotion</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Promote <span class="font-semibold text-gray-700 dark:text-gray-200">{{ selectedEmployee?.name }}</span> from Staff to Manager?
                    </p>
                </div>
                <div class="flex gap-3 px-6 py-4">
                    <button @click="closePromoteModal"
                        class="flex-1 py-2.5 rounded-lg text-sm font-medium text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        Cancel
                    </button>
                    <button @click="confirmPromote"
                        class="flex-1 py-2.5 rounded-lg text-sm font-semibold bg-green-600 hover:bg-green-700 text-white transition-colors">
                        Confirm Promotion
                    </button>
                </div>
            </div>
        </div>

        <!-- ===== DEMOTE MODAL ===== -->
        <div v-if="isDemoteModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
            @click.self="closeDemoteModal">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl max-w-sm w-full overflow-hidden border border-gray-100 dark:border-gray-800">
                <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-800">
                    <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center mb-3">
                        <RotateCcw class="h-5 w-5 text-red-600 dark:text-red-400" />
                    </div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Confirm Demotion</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Demote <span class="font-semibold text-gray-700 dark:text-gray-200">{{ selectedEmployee?.name }}</span> from Manager to Staff?
                    </p>
                </div>
                <div class="flex gap-3 px-6 py-4">
                    <button @click="closeDemoteModal"
                        class="flex-1 py-2.5 rounded-lg text-sm font-medium text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        Cancel
                    </button>
                    <button @click="confirmDemote"
                        class="flex-1 py-2.5 rounded-lg text-sm font-semibold bg-red-600 hover:bg-red-700 text-white transition-colors">
                        Confirm Demotion
                    </button>
                </div>
            </div>
        </div>

        <!-- ===== MANAGE ROLE/POSITION MODAL ===== -->
        <div v-if="isManageModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
            @click.self="closeManageModal">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-gray-100 dark:border-gray-800">
                <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100 dark:border-gray-800">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Manage Role & Position</h2>
                        <p class="text-xs text-gray-400 mt-0.5">{{ selectedEmployee?.name }}</p>
                    </div>
                    <button @click="closeManageModal" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Department</label>
                        <select v-model="manageForm.role"
                            class="w-full px-3.5 py-2.5 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm text-gray-800 dark:text-gray-200 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 outline-none transition">
                            <option v-for="opt in roleOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Position</label>
                        <select v-model="manageForm.position"
                            class="w-full px-3.5 py-2.5 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm text-gray-800 dark:text-gray-200 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 outline-none transition">
                            <option value="staff">Staff</option>
                            <option value="manager">Manager</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-3 px-6 py-4 border-t border-gray-100 dark:border-gray-800">
                    <button @click="closeManageModal"
                        class="flex-1 py-2.5 rounded-lg text-sm font-medium text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        Cancel
                    </button>
                    <button @click="updateRolePosition"
                        class="flex-1 py-2.5 rounded-lg text-sm font-semibold bg-purple-600 hover:bg-purple-700 text-white transition-colors">
                        Update
                    </button>
                </div>
            </div>
        </div>

        <!-- ===== HISTORY MODAL ===== -->
        <div v-if="isHistoryModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
            @click.self="closeHistoryModal">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl max-w-2xl w-full max-h-[80vh] overflow-hidden flex flex-col border border-gray-100 dark:border-gray-800">
                <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100 dark:border-gray-800">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Audit Logs</h2>
                        <p class="text-xs text-gray-400 mt-0.5">{{ selectedEmployee?.name }}</p>
                    </div>
                    <button @click="closeHistoryModal" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <div class="overflow-y-auto p-6 flex-1">
                    <div v-if="!selectedEmployee?.audit_logs || selectedEmployee.audit_logs.length === 0"
                        class="flex flex-col items-center justify-center py-12">
                        <History class="h-10 w-10 text-gray-300 dark:text-gray-600 mb-3" />
                        <p class="text-sm text-gray-400">No audit logs found.</p>
                    </div>
                    <div v-else class="space-y-3">
                        <div v-for="log in selectedEmployee.audit_logs" :key="log.id"
                            class="p-4 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-3">
                                    <span :class="[
                                        'px-2 py-0.5 rounded-md text-[10px] font-semibold flex-shrink-0 mt-0.5',
                                        log.action === 'deactivate' ? 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' :
                                        log.action === 'reactivate' ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400'
                                    ]">{{ log.action }}</span>
                                    <div>
                                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ log.reason }}</p>
                                        <p class="text-xs text-gray-400 mt-1">{{ new Date(log.created_at).toLocaleString() }}</p>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-400 flex-shrink-0">Admin #{{ log.admin_id }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== DEACTIVATE MODAL ===== -->
        <div v-if="isDeactivateModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
            @click.self="closeDeactivateModal">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-gray-100 dark:border-gray-800">
                <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-800">
                    <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center mb-3">
                        <ShieldOff class="h-5 w-5 text-red-600 dark:text-red-400" />
                    </div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Deactivate Account</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Suspend access for <span class="font-semibold text-gray-700 dark:text-gray-200">{{ selectedEmployee?.name }}</span></p>
                </div>
                <div class="p-6">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Reason for deactivation</label>
                    <textarea
                        v-model="deactivationReason"
                        rows="3"
                        placeholder="e.g., End of contract, policy violation…"
                        class="w-full px-3.5 py-2.5 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:border-red-400 focus:ring-2 focus:ring-red-400/20 outline-none transition resize-none">
                    </textarea>
                </div>
                <div class="flex gap-3 px-6 py-4 border-t border-gray-100 dark:border-gray-800">
                    <button @click="closeDeactivateModal"
                        class="flex-1 py-2.5 rounded-lg text-sm font-medium text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        Cancel
                    </button>
                    <button
                        @click="confirmDeactivate"
                        :disabled="!deactivationReason.trim()"
                        class="flex-1 py-2.5 rounded-lg text-sm font-semibold bg-red-600 hover:bg-red-700 text-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                        Confirm Deactivation
                    </button>
                </div>
            </div>
        </div>

        <!-- ===== ACTIVATE MODAL ===== -->
        <div v-if="isActivateModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
            @click.self="closeActivateModal">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-gray-100 dark:border-gray-800">
                <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-800">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mb-3">
                        <ShieldCheck class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Reactivate Account</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Restore access for <span class="font-semibold text-gray-700 dark:text-gray-200">{{ selectedEmployee?.name }}</span></p>
                </div>
                <div class="p-6">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Reason for reactivation</label>
                    <textarea
                        v-model="deactivationReason"
                        rows="3"
                        placeholder="e.g., Cleared for return, contract renewed…"
                        class="w-full px-3.5 py-2.5 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20 outline-none transition resize-none">
                    </textarea>
                </div>
                <div class="flex gap-3 px-6 py-4 border-t border-gray-100 dark:border-gray-800">
                    <button @click="closeActivateModal"
                        class="flex-1 py-2.5 rounded-lg text-sm font-medium text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        Cancel
                    </button>
                    <button
                        @click="confirmActivate"
                        :disabled="!deactivationReason.trim()"
                        class="flex-1 py-2.5 rounded-lg text-sm font-semibold bg-emerald-600 hover:bg-emerald-700 text-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                        Confirm Reactivation
                    </button>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
.toast-enter-active, .toast-leave-active { transition: all 0.25s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(-8px) scale(0.98); }
</style>