<template>
    <AuthenticatedLayout>
        <!-- START SCREEN LOADING OVERLAY -->
        <Transition name="fade">
            <div
                v-if="pageLoading"
                class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-100/50 backdrop-blur-md"
                aria-live="polite"
            >
                <div class="flex flex-col items-center gap-3">
                    <div class="w-10 h-10 rounded-full border-4 border-slate-300 border-t-blue-600 animate-spin"></div>
                    <p class="text-sm font-semibold text-slate-600">Loading templates...</p>
                </div>
            </div>
        </Transition>

        <div class="flex min-h-screen bg-gradient-to-br from-slate-100 via-slate-50 to-blue-50">
            <div class="flex-1 p-8 text-slate-900 font-sans min-w-0 relative flex flex-col">
                <div class="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,transparent,black,transparent)] pointer-events-none"></div>

                <!-- Header -->
                <header class="mb-6 relative shrink-0">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div>
                            <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
                                <span class="hover:text-slate-700 cursor-pointer transition-colors">Dashboard</span>
                                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                                <Link :href="route('hrm.onboarding.status.index')" class="text-slate-500 hover:text-slate-700 transition-colors">Onboarding</Link>
                                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="text-blue-600 font-semibold">Templates</span>
                            </nav>
                            <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">Onboarding Templates</h1>
                            <p class="text-sm text-slate-500 mt-0.5">Reusable onboarding flows with applicability rules. Each new hire snapshots the active template at creation.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                @click="openCreateModal"
                                class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-xs font-bold transition-all duration-200 shadow-lg hover:shadow-xl"
                            >
                                <Plus class="w-3.5 h-3.5" /> New Template
                            </button>
                        </div>
                    </div>
                </header>

                <!-- Filter bar -->
                <div class="bg-white rounded-xl border border-slate-200/60 shadow-md backdrop-blur-sm p-4 mb-4 shrink-0">
                    <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                        <div class="flex-1">
                            <div class="relative">
                                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                                </svg>
                                <input
                                    v-model="search"
                                    type="text"
                                    placeholder="Search by name or code..."
                                    class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200"
                                />
                            </div>
                        </div>
                        <select
                            v-model="filterStatus"
                            class="px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200"
                        >
                            <option value="">All Statuses</option>
                            <option v-for="s in statusFilterOptions" :key="s" :value="s">{{ s }}</option>
                        </select>
                        <button
                            @click="applyFilter"
                            class="inline-flex items-center gap-1 px-3.5 py-2.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-[10px] font-bold transition-all duration-200 shadow-sm"
                        >
                            <Search class="w-3 h-3" /> Apply
                        </button>
                    </div>
                </div>

                <!-- Template cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 relative">
                    <div
                        v-for="template in templateList"
                        :key="template.id"
                        class="group bg-white rounded-xl border border-slate-200/60 shadow-md backdrop-blur-sm p-5 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-blue-200/60 cursor-pointer relative"
                        @click="openTemplate(template)"
                    >
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-50 to-indigo-50 flex items-center justify-center">
                                    <LayoutTemplate class="w-4 h-4 text-blue-600" />
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-800 group-hover:text-blue-700 transition-colors flex items-center gap-1.5">
                                        {{ template.name }}
                                        <span v-if="template.is_default" class="bg-amber-50 text-amber-600 border-amber-200 px-1.5 py-0.5 rounded text-[9px] font-bold border">DEFAULT</span>
                                    </div>
                                    <div class="text-[10px] text-slate-400">{{ template.code }} · v{{ template.version }}</div>
                                </div>
                            </div>
                            <span :class="['inline-flex px-2.5 py-1 rounded-full text-[10px] font-semibold border shadow-sm', template.status_badge]">
                                {{ template.status }}
                            </span>
                        </div>

                        <p v-if="template.description" class="text-[10px] text-slate-500 leading-relaxed line-clamp-2 mb-3">{{ template.description }}</p>

                        <div class="text-[10px] font-medium text-slate-500 bg-slate-50 border border-slate-100 rounded-lg px-2.5 py-1.5 mb-3">
                            {{ template.applicability }}
                        </div>

                        <div class="flex items-center justify-between text-[10px] text-slate-400">
                            <span class="inline-flex items-center gap-1"><ClipboardList class="w-3 h-3" /> {{ template.item_count }} items</span>
                            <span class="inline-flex items-center gap-1"><UserCheck class="w-3 h-3" /> {{ template.active_uses }} active uses</span>
                            <span v-if="template.created_by">by {{ template.created_by }}</span>
                        </div>

                        <div class="flex items-center gap-2 mt-4 pt-3 border-t border-slate-100">
                            <button
                                @click.stop="openTemplate(template)"
                                class="flex-1 inline-flex justify-center items-center gap-1 px-3 py-1.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-lg text-[10px] font-bold transition-all duration-200 shadow-sm"
                            >
                                Builder <ArrowRight class="w-3 h-3" />
                            </button>
                            <button
                                @click.stop="duplicateTemplate(template)"
                                title="Duplicate"
                                class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                            >
                                <Copy class="w-3.5 h-3.5" />
                            </button>
                            <button
                                v-if="template.status === 'Active'"
                                @click.stop="setStatus(template, 'Inactive')"
                                title="Deactivate"
                                class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors"
                            >
                                <PauseCircle class="w-3.5 h-3.5" />
                            </button>
                            <button
                                v-else-if="template.status === 'Inactive' || template.status === 'Draft'"
                                @click.stop="setStatus(template, 'Active')"
                                title="Activate"
                                class="p-1.5 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"
                            >
                                <PlayCircle class="w-3.5 h-3.5" />
                            </button>
                            <button
                                v-if="template.active_uses === 0 && template.status !== 'Archived'"
                                @click.stop="deleteTemplate(template)"
                                title="Delete"
                                class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-if="templateList.length === 0" class="md:col-span-2 xl:col-span-3 text-center py-16">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center mx-auto mb-4 shadow-inner">
                            <LayoutTemplate class="w-6 h-6 text-slate-400" />
                        </div>
                        <h3 class="text-base font-bold text-slate-900 mb-1">No Templates Found</h3>
                        <p class="text-xs text-slate-500">Create a template to define the onboarding flow for new hires.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Template Modal -->
        <div v-if="activeModal === 'create'" class="fixed inset-0 z-[80] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeModal">
            <div class="bg-white rounded-2xl border border-slate-200/60 w-full max-w-xl overflow-hidden shadow-2xl">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-transparent">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">New Onboarding Template</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Define the template header first; items are added in the builder.</p>
                    </div>
                    <button @click="closeModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    <input v-model="createForm.code" type="hidden" />
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Name <span class="text-rose-500">*</span></label>
                        <input v-model="createForm.name" type="text" placeholder="e.g. Store Front Onboarding" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Description</label>
                        <textarea v-model="createForm.description" rows="2" placeholder="What is this template for?" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Department</label>
                            <select v-model="createForm.department_id" :disabled="saving" @change="onDepartmentChange" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm">
                                <option value="">Any</option>
                                <option v-for="d in optionDepartments" :key="d.id" :value="d.id">{{ d.name }} ({{ positionCount(d.id) }})</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Employment Type</label>
                            <select v-model="createForm.employment_type_id" :disabled="saving" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm">
                                <option value="">Any</option>
                                <option v-for="t in optionEmploymentTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Position</label>
                            <select v-model="createForm.position_id" :disabled="saving" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm">
                                <option value="">Any</option>
                                <option v-for="p in filteredPositions" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                            <p v-if="optionPositions.length && filteredPositions.length === 0" class="text-[10px] text-amber-600 font-medium mt-1">No positions under {{ selectedDepartmentName }} — add one under HRM → Positions first, or keep Any.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Work Mode</label>
                            <select v-model="createForm.work_mode" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm">
                                <option value="">Any</option>
                                <option v-for="m in workModes" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Duration (days)</label>
                        <input v-model.number="createForm.duration_days" type="number" min="1" max="3650" placeholder="e.g. 7" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm" />
                        <p class="text-[10px] text-slate-400 mt-1">Onboarding countdown starts when the applicant opens their onboarding page. They are reminded 3 days before it ends. Leave blank for no deadline.</p>
                    </div>
                    <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                        <input v-model="createForm.is_default" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                        Make this the default template
                    </label>
                </div>
                <div class="p-4 border-t border-slate-100 bg-gradient-to-r from-slate-50 to-transparent flex items-center justify-end gap-3">
                    <button @click="closeModal" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-all">Cancel</button>
                    <button @click="submitCreate" :disabled="saving" class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-sm font-bold transition-all shadow-lg">
                        {{ saving ? 'Creating...' : 'Create Template' }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Swal from 'sweetalert2';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { Plus, Search, Copy, Trash2, PauseCircle, PlayCircle, LayoutTemplate, ClipboardList, UserCheck, ArrowRight, X } from 'lucide-vue-next';

const props = defineProps({
    templates: { type: [Array, Object], default: () => [] },
    pagination: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({ search: '', status: '' }) },
    options: { type: Object, default: () => ({ departments: [], positions: [], employment_types: [], work_modes: [] }) },
});

// Accept both plain arrays (new controller) and legacy Laravel paginators
// ({data:[...]}). Filter out nulls so :key="template.id" never crashes.
const templateList = computed(() => {
    const raw = Array.isArray(props.templates)
        ? props.templates
        : (props.templates?.data ?? []);
    return (raw ?? []).filter((t) => t && typeof t === 'object' && t.id != null);
});

const page = usePage();
const pageLoading = ref(true);
const saving = ref(false);
const activeModal = ref(null);
const search = ref(props.filters.search || '');
const filterStatus = ref(props.filters.status || '');

const statusFilterOptions = ['Draft', 'Active', 'Inactive', 'Archived'];

const optionDepartments = computed(() => props.options.departments || []);
const optionPositions = computed(() => props.options.positions || []);
const optionEmploymentTypes = computed(() => props.options.employment_types || []);
const workModes = computed(() => props.options.work_modes || ['On-site', 'Remote', 'Hybrid']);

const filteredPositions = computed(() => {
    if (!createForm.value.department_id) return optionPositions.value;
    return optionPositions.value.filter(p => p.department_id != null && String(p.department_id) === String(createForm.value.department_id));
});

const positionCount = (deptId) => optionPositions.value.filter(p => p.department_id != null && String(p.department_id) === String(deptId)).length;

const selectedDepartmentName = computed(() => {
    const found = optionDepartments.value.find(d => String(d.id) === String(createForm.value.department_id));
    return found ? found.name : 'this department';
});

const onDepartmentChange = () => {
    if (!filteredPositions.value.some(p => String(p.id) === String(createForm.value.position_id))) {
        createForm.value.position_id = '';
    }
};

onMounted(() => {
    setTimeout(() => { pageLoading.value = false; }, 200);
});

watch(() => page.props.flash, (flash) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
}, { immediate: true });

watch(() => page.props.errors, (errors) => {
    if (Object.keys(errors).length > 0) {
        toast.error(String(Object.values(errors)[0]));
    }
}, { deep: true });

const applyFilter = () => {
    router.get(route('hrm.onboarding.templates.index'), {
        search: search.value.trim(),
        status: filterStatus.value,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const openTemplate = (template) => {
    if (!template?.id) return;
    router.get(route('hrm.onboarding.templates.show', template.id));
};

const createForm = ref({
    code: '', name: '', description: '', department_id: '', position_id: '',
    employment_type_id: '', work_mode: '', duration_days: '', is_default: false,
});

const openCreateModal = () => {
    createForm.value = { code: '', name: '', description: '', department_id: '', position_id: '', employment_type_id: '', work_mode: '', duration_days: '', is_default: false };
    activeModal.value = 'create';
};

const submitCreate = () => {
    const name = (createForm.value.name || '').trim();
    if (!name) {
        toast.error('Template name is required.');
        return;
    }
    // Confirm before creating (same Swal pattern as duplicate/deactivate).
    Swal.fire({
        title: 'Create template?',
        text: `"${name}" will be created as a Draft. Checklist items are added next in the builder.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Create',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#2563eb',
        customClass: { popup: 'rounded-[2rem]' },
    }).then((result) => {
        if (!result.isConfirmed) return;
        saving.value = true;
        router.post(route('hrm.onboarding.templates.store'), createForm.value, {
            onSuccess: () => {
                // Close the modal (it previously stayed open) + confirm.
                activeModal.value = null;
                Swal.fire({
                    title: 'Template created!',
                    text: `"${name}" was created as a Draft. Open its Builder to add checklist items.`,
                    icon: 'success',
                    confirmButtonText: 'Got it',
                    confirmButtonColor: '#2563eb',
                    customClass: { popup: 'rounded-[2rem]' },
                });
            },
            onError: () => toast.error('Failed to create template.'),
            onFinish: () => { saving.value = false; },
        });
    });
};

const duplicateTemplate = (template) => {
    if (!template?.id) return;
    Swal.fire({
        title: 'Duplicate template?',
        text: template.name + ' will be copied with all its items.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Duplicate',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#2563eb',
        customClass: { popup: 'rounded-[2rem]' },
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('hrm.onboarding.templates.duplicate', template.id), {}, {
                onSuccess: () => toast.success('Template duplicated.'),
                onError: () => toast.error('Failed to duplicate template.'),
            });
        }
    });
};

const setStatus = (template, status) => {
    if (!template?.id) return;
    const isDeactivating = status === 'Inactive';
    const action = (confirmed) => {
        if (confirmed === false) return;
        router.post(route('hrm.onboarding.templates.set-status', template.id), { status }, {
            preserveScroll: true,
            onSuccess: () => toast.success('Template marked as ' + status + '.'),
            onError: () => toast.error('Failed to update template status.'),
        });
    };

    if (!isDeactivating) {
        action(true);
        return;
    }

    Swal.fire({
        title: 'Deactivate template?',
        text: template.name + ' will no longer be used for new hires until it is activated again.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Deactivate',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#d97706',
        customClass: { popup: 'rounded-[2rem]' },
    }).then((result) => action(result.isConfirmed));
};

const deleteTemplate = (template) => {
    if (!template?.id) return;
    Swal.fire({
        title: 'Delete template?',
        text: template.name + ' will be permanently removed.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#e11d48',
        customClass: { popup: 'rounded-[2rem]' },
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('hrm.onboarding.templates.destroy', template.id), {
                onSuccess: () => toast.success('Template deleted.'),
                onError: () => toast.error('Failed to delete template.'),
            });
        }
    });
};

const closeModal = () => {
    activeModal.value = null;
};
</script>

<style scoped>
.bg-grid-slate-100 {
    background-image: radial-gradient(circle, #cbd5e1 1px, transparent 1px);
    background-size: 24px 24px;
}

/* This page uses fixed light styling (bg-white cards/inputs). The app sets
   `.dark { color-scheme: dark }` globally, which makes native <select>
   dropdown popups render dark while the option text stays dark -> invisible
   options in dark mode. Force native controls to render light so
   Department / Employment Type / Position / Work Mode stay readable. */
select {
    color: #334155;
    color-scheme: light;
}
select option {
    color: #334155;
    background-color: #ffffff;
}
</style>