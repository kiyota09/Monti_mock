<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { usePageAccess } from '@/composables/usePageAccess';
import { Building2, CheckCircle, XCircle, Eye, X, Plus, Trash2, AlertTriangle, ChevronRight, Sparkles, Clock } from 'lucide-vue-next';

const { canEdit } = usePageAccess();
const canEditVendor = computed(() => canEdit('SCM', 'vendor'));

const props = defineProps({ registrations: Array });

const selectedVendor = ref(null);
const showModal = ref(false);
const modalMode = ref('view');
const rejectionReason = ref('');
const requirementLines = ref([{ requirement_name: '', description: '', value: '' }]);

const openModal = (vendor, mode) => {
    selectedVendor.value = vendor;
    modalMode.value = mode;
    rejectionReason.value = '';
    requirementLines.value = vendor.requirements?.length
        ? vendor.requirements.map(r => ({ ...r }))
        : [{ requirement_name: '', description: '', value: '' }];
    showModal.value = true;
};

const submitApprove = () => {
    const validReqs = requirementLines.value.filter(r => r.requirement_name.trim());
    router.post(route('scm.vendors.approve', selectedVendor.value.id), { requirements: validReqs }, {
        preserveScroll: true,
        onSuccess: () => showModal.value = false,
    });
};

const submitReject = () => {
    router.post(route('scm.vendors.reject', selectedVendor.value.id), { rejection_reason: rejectionReason.value }, {
        preserveScroll: true,
        onSuccess: () => showModal.value = false,
    });
};

const addReqLine = () => requirementLines.value.push({ requirement_name: '', description: '', value: '' });
const removeReqLine = (i) => requirementLines.value.splice(i, 1);

const statusConfig = (s) => ({
    pending:  { classes: 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',  dot: 'bg-amber-500' },
    approved: { classes: 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30',    dot: 'bg-blue-500' },
    rejected: { classes: 'bg-gray-100 text-gray-500 ring-gray-200 dark:bg-zinc-800 dark:text-gray-400 dark:ring-zinc-700', dot: 'bg-gray-400' },
}[s] || { classes: 'bg-gray-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400', dot: 'bg-gray-400' });
</script>

<template>
    <AuthenticatedLayout>
        <Head title="SCM Vendors" />

        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <Building2 class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> SCM · Suppliers
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Vendor Registrations</h1>
                            <p class="text-sm text-blue-100/90">Review and manage vendor applications</p>
                            <span v-if="!canEditVendor" class="mt-2 inline-block text-xs font-bold text-amber-700 bg-amber-50 px-3 py-1.5 rounded-full">View only</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-300 animate-pulse" /> {{ registrations?.filter(v => v.status === 'pending').length ?? 0 }} pending
                            </span>
                        </div>
                    </div>

                    <!-- Stat strip -->
                    <div class="relative mt-6 grid grid-cols-3 gap-3">
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:80ms">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Total</p>
                            <p class="mt-1 text-lg font-black">{{ registrations?.length ?? 0 }}</p>
                        </div>
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:160ms">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Pending</p>
                            <p class="mt-1 text-lg font-black">{{ registrations?.filter(v => v.status === 'pending').length ?? 0 }}</p>
                        </div>
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:240ms">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Approved</p>
                            <p class="mt-1 text-lg font-black">{{ registrations?.filter(v => v.status === 'approved').length ?? 0 }}</p>
                        </div>
                    </div>
                </div>

                <!-- Desktop Table -->
                <div class="animate-fade-up hidden md:block bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden" style="animation-delay:120ms">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                        <Clock class="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                        <h2 class="text-sm font-black uppercase tracking-widest text-gray-900 dark:text-white">Applications</h2>
                        <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-1">{{ registrations?.length ?? 0 }}</span>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 dark:border-zinc-800">
                                <th class="px-5 py-4 text-left">Business</th>
                                <th class="px-5 py-4 text-left">Contact</th>
                                <th class="px-5 py-4 text-center">Status</th>
                                <th class="px-5 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-zinc-800">
                            <tr
                                v-for="vendor in registrations"
                                :key="vendor.id"
                                class="group hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors duration-150"
                            >
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 via-blue-600 to-cyan-600 flex items-center justify-center flex-shrink-0 shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                            <span class="text-xs font-black text-white uppercase">{{ vendor.business_name?.charAt(0)?.toUpperCase() }}</span>
                                        </div>
                                        <span class="font-bold text-gray-900 dark:text-white">{{ vendor.business_name }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="text-gray-700 dark:text-gray-200 text-sm font-medium">{{ vendor.representative_name }}</p>
                                    <p class="text-gray-400 text-xs mt-0.5">{{ vendor.email }}</p>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <span :class="statusConfig(vendor.status).classes" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1">
                                        <span :class="statusConfig(vendor.status).dot" class="w-1.5 h-1.5 rounded-full animate-pulse"></span>
                                        {{ vendor.status }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            @click="openModal(vendor, 'view')"
                                            class="flex h-8 w-8 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 hover:bg-indigo-600 hover:text-white transition-all"
                                            title="View Details"
                                        >
                                            <Eye class="w-4 h-4" />
                                        </button>
                                        <template v-if="vendor.status === 'pending' && canEditVendor">
                                            <button
                                                @click="openModal(vendor, 'approve')"
                                                class="inline-flex items-center gap-1.5 px-3 py-2 bg-gradient-to-br from-indigo-600 to-violet-700 hover:from-indigo-700 hover:to-violet-800 text-white rounded-xl text-[10px] font-black uppercase tracking-wider transition-all active:scale-95 shadow-md"
                                            >
                                                <CheckCircle class="w-3.5 h-3.5" />
                                                Approve
                                            </button>
                                            <button
                                                @click="openModal(vendor, 'reject')"
                                                class="inline-flex items-center gap-1.5 px-3 py-2 bg-white dark:bg-zinc-900 hover:bg-gray-50 dark:hover:bg-zinc-800 text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white border border-gray-200 dark:border-zinc-700 rounded-xl text-[10px] font-black uppercase tracking-wider transition-all active:scale-95"
                                            >
                                                <XCircle class="w-3.5 h-3.5" />
                                                Reject
                                            </button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!registrations?.length">
                                <td colspan="4" class="px-5 py-16 text-center">
                                    <Building2 class="w-10 h-10 text-gray-200 dark:text-zinc-700 mx-auto mb-3 animate-bounce-soft" />
                                    <p class="text-gray-400 text-sm font-bold">No vendor registrations yet</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </div>

                <!-- Mobile Cards -->
                <TransitionGroup name="card" tag="div" class="md:hidden space-y-3">
                    <div
                        v-for="(vendor, i) in registrations"
                        :key="vendor.id"
                        :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                        class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur border border-gray-100 dark:border-zinc-800 rounded-3xl p-4 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 overflow-hidden"
                    >
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="relative flex items-start justify-between gap-3 mb-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 via-blue-600 to-cyan-600 flex items-center justify-center flex-shrink-0 shadow-lg">
                                    <span class="text-sm font-black text-white uppercase">{{ vendor.business_name?.charAt(0)?.toUpperCase() }}</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-gray-900 dark:text-white text-sm truncate">{{ vendor.business_name }}</p>
                                    <p class="text-xs text-gray-400 truncate">{{ vendor.email }}</p>
                                </div>
                            </div>
                            <span :class="statusConfig(vendor.status).classes" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1 flex-shrink-0">
                                <span :class="statusConfig(vendor.status).dot" class="w-1.5 h-1.5 rounded-full animate-pulse"></span>
                                {{ vendor.status }}
                            </span>
                        </div>
                        <p class="relative text-xs text-gray-500 dark:text-gray-400 mb-4">{{ vendor.representative_name }}</p>
                        <div class="relative flex items-center gap-2 pt-3 border-t border-gray-100 dark:border-zinc-800">
                            <button
                                @click="openModal(vendor, 'view')"
                                class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2.5 border border-gray-200 dark:border-zinc-700 rounded-xl text-xs font-bold text-gray-600 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors active:scale-95"
                            >
                                <Eye class="w-3.5 h-3.5" /> View
                            </button>
                            <template v-if="vendor.status === 'pending'">
                                <button
                                    @click="openModal(vendor, 'approve')"
                                    class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2.5 bg-gradient-to-br from-indigo-600 to-violet-700 text-white rounded-xl text-xs font-bold transition-all active:scale-95"
                                >
                                    <CheckCircle class="w-3.5 h-3.5" /> Approve
                                </button>
                                <button
                                    @click="openModal(vendor, 'reject')"
                                    class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2.5 border border-gray-200 dark:border-zinc-700 rounded-xl text-xs font-bold text-gray-600 dark:text-gray-300 transition-colors active:scale-95"
                                >
                                    <XCircle class="w-3.5 h-3.5" /> Reject
                                </button>
                            </template>
                        </div>
                    </div>

                    <div v-if="!registrations?.length" key="empty" class="bg-white dark:bg-zinc-900 border border-dashed border-gray-200 dark:border-zinc-800 rounded-3xl py-16 text-center">
                        <Building2 class="w-10 h-10 text-gray-200 dark:text-zinc-700 mx-auto mb-3 animate-bounce-soft" />
                        <p class="text-gray-400 text-sm font-bold">No vendor registrations yet</p>
                    </div>
                </TransitionGroup>

            </div>
        </div>

        <!-- ─── MODALS ─── -->
        <Teleport to="body">
            <Transition name="modal">
                <div
                    v-if="showModal"
                    class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-zinc-950/60 backdrop-blur-sm"
                    @click.self="showModal = false"
                >
                    <div
                        class="relative bg-white dark:bg-zinc-900 w-full sm:max-w-lg sm:rounded-3xl rounded-t-3xl shadow-2xl max-h-[92vh] sm:max-h-[85vh] flex flex-col overflow-hidden border border-gray-100 dark:border-zinc-800"
                    >
                        <!-- Drag handle (mobile) -->
                        <div class="flex justify-center pt-3 pb-1 sm:hidden">
                            <div class="w-10 h-1 bg-gray-200 dark:bg-zinc-700 rounded-full"></div>
                        </div>

                        <!-- Modal Header -->
                        <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-5 sm:px-6 py-5 text-white">
                            <div class="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/10 blur-3xl animate-float" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                            <div class="relative flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur flex items-center justify-center flex-shrink-0 animate-pop">
                                        <Eye v-if="modalMode === 'view'" class="w-4 h-4" />
                                        <CheckCircle v-if="modalMode === 'approve'" class="w-4 h-4" />
                                        <XCircle v-if="modalMode === 'reject'" class="w-4 h-4" />
                                    </div>
                                    <div>
                                        <h3 class="text-sm sm:text-base font-black tracking-tight">
                                            {{ modalMode === 'approve' ? 'Approve Vendor' : modalMode === 'reject' ? 'Reject Vendor' : 'Vendor Details' }}
                                        </h3>
                                        <p class="text-xs text-blue-100/90 leading-tight">{{ selectedVendor?.business_name }}</p>
                                    </div>
                                </div>
                                <button
                                    @click="showModal = false"
                                    class="w-8 h-8 rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 flex items-center justify-center transition-all backdrop-blur"
                                >
                                    <X class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Modal Body -->
                        <div class="flex-1 overflow-y-auto px-5 sm:px-6 py-5">

                            <!-- VIEW MODE -->
                            <div v-if="modalMode === 'view'" class="space-y-4">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="bg-gray-50 dark:bg-zinc-800/60 rounded-2xl p-3.5 border border-gray-100 dark:border-zinc-800">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Business Name</p>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedVendor?.business_name }}</p>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-zinc-800/60 rounded-2xl p-3.5 border border-gray-100 dark:border-zinc-800">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Representative</p>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedVendor?.representative_name }}</p>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-zinc-800/60 rounded-2xl p-3.5 border border-gray-100 dark:border-zinc-800">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Email Address</p>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white break-all">{{ selectedVendor?.email }}</p>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-zinc-800/60 rounded-2xl p-3.5 border border-gray-100 dark:border-zinc-800">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Phone Number</p>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedVendor?.phone_number }}</p>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-zinc-800/60 rounded-2xl p-3.5 sm:col-span-2 border border-gray-100 dark:border-zinc-800">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Address</p>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedVendor?.address }}</p>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-zinc-800/60 rounded-2xl p-3.5 border border-gray-100 dark:border-zinc-800">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Status</p>
                                        <span :class="statusConfig(selectedVendor?.status).classes" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1">
                                            <span :class="statusConfig(selectedVendor?.status).dot" class="w-1.5 h-1.5 rounded-full animate-pulse"></span>
                                            {{ selectedVendor?.status }}
                                        </span>
                                    </div>
                                </div>
                                <!-- Rejection reason alert -->
                                <div v-if="selectedVendor?.rejection_reason" class="flex gap-3 bg-gray-50 dark:bg-zinc-800/60 border border-gray-200 dark:border-zinc-700 rounded-2xl p-4">
                                    <AlertTriangle class="w-4 h-4 text-gray-400 flex-shrink-0 mt-0.5" />
                                    <div>
                                        <p class="text-xs font-bold text-gray-600 dark:text-gray-300 mb-1">Rejection Reason</p>
                                        <p class="text-sm text-gray-700 dark:text-gray-200">{{ selectedVendor?.rejection_reason }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- REJECT MODE -->
                            <div v-if="modalMode === 'reject'" class="space-y-4">
                                <div class="flex gap-3 bg-gray-50 dark:bg-zinc-800/60 border border-gray-200 dark:border-zinc-700 rounded-2xl p-4">
                                    <AlertTriangle class="w-4 h-4 text-gray-500 flex-shrink-0 mt-0.5" />
                                    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                                        This will permanently reject <span class="font-bold text-gray-900 dark:text-white">{{ selectedVendor?.business_name }}</span>. Please provide a clear reason.
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-200 mb-2">
                                        Reason for Rejection <span class="text-rose-400">*</span>
                                    </label>
                                    <textarea
                                        v-model="rejectionReason"
                                        rows="4"
                                        placeholder="Describe why this vendor is being rejected..."
                                        class="w-full border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 rounded-2xl px-4 py-3 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-300 dark:placeholder:text-zinc-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition resize-none"
                                    ></textarea>
                                </div>
                            </div>

                            <!-- APPROVE MODE -->
                            <div v-if="modalMode === 'approve'" class="space-y-4">
                                <div class="flex gap-3 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 rounded-2xl p-4">
                                    <CheckCircle class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                                    <p class="text-sm text-amber-800 dark:text-amber-200 leading-relaxed">
                                        Approving <span class="font-bold">{{ selectedVendor?.business_name }}</span>. Set compliance requirements below (optional).
                                    </p>
                                </div>

                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <label class="text-xs font-bold text-gray-700 dark:text-gray-200">Compliance Requirements</label>
                                        <button
                                            v-if="canEditVendor"
                                            @click="addReqLine"
                                            class="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 dark:text-indigo-300 hover:underline transition"
                                        >
                                            <Plus class="w-3.5 h-3.5" />
                                            Add row
                                        </button>
                                    </div>

                                    <div class="space-y-2.5">
                                        <div v-for="(req, i) in requirementLines" :key="i" class="flex gap-2 items-start">
                                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 flex-1">
                                                <input
                                                    v-model="req.requirement_name"
                                                    placeholder="Requirement name"
                                                    class="border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 rounded-xl px-3 py-2.5 text-xs text-gray-800 dark:text-gray-100 placeholder:text-gray-300 dark:placeholder:text-zinc-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                                                />
                                                <input
                                                    v-model="req.description"
                                                    placeholder="Description"
                                                    class="border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 rounded-xl px-3 py-2.5 text-xs text-gray-800 dark:text-gray-100 placeholder:text-gray-300 dark:placeholder:text-zinc-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                                                />
                                                <input
                                                    v-model="req.value"
                                                    placeholder="Value"
                                                    class="border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 rounded-xl px-3 py-2.5 text-xs text-gray-800 dark:text-gray-100 placeholder:text-gray-300 dark:placeholder:text-zinc-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                                                />
                                            </div>
                                            <button
                                                v-if="canEditVendor"
                                                @click="removeReqLine(i)"
                                                class="w-8 h-8 flex-shrink-0 mt-0.5 flex items-center justify-center rounded-lg text-gray-300 hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-all"
                                                :disabled="requirementLines.length === 1"
                                            >
                                                <Trash2 class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- Modal Footer -->
                        <div class="px-5 sm:px-6 py-4 border-t border-gray-100 dark:border-zinc-800 flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                            <button
                                @click="showModal = false"
                                class="w-full sm:w-auto px-5 py-2.5 text-sm font-bold text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white border border-gray-200 dark:border-zinc-700 hover:border-indigo-200 dark:hover:border-indigo-800 rounded-xl transition-all duration-150 active:scale-95"
                            >
                                {{ modalMode === 'view' ? 'Close' : 'Cancel' }}
                            </button>

                            <!-- Reject confirm -->
                            <button
                                v-if="modalMode === 'reject' && canEditVendor"
                                @click="submitReject"
                                :disabled="!rejectionReason.trim()"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-zinc-900 dark:bg-white disabled:bg-gray-200 dark:disabled:bg-zinc-800 disabled:text-gray-400 disabled:cursor-not-allowed text-white dark:text-zinc-900 rounded-xl text-sm font-bold transition-all duration-150 active:scale-95"
                            >
                                <XCircle class="w-4 h-4" />
                                Confirm Rejection
                            </button>

                            <!-- Approve confirm -->
                            <button
                                v-if="modalMode === 'approve' && canEditVendor"
                                @click="submitApprove"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-gradient-to-br from-indigo-600 to-violet-700 hover:from-indigo-700 hover:to-violet-800 text-white rounded-xl text-sm font-bold transition-all duration-150 active:scale-95 shadow-lg"
                            >
                                <CheckCircle class="w-4 h-4" />
                                Approve Vendor
                            </button>
                        </div>

                    </div>
                </div>
            </Transition>
        </Teleport>

    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
@keyframes bounceSoft { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-float-delayed { animation: float 8s ease-in-out 1.2s infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.animate-bounce-soft { animation: bounceSoft 2.4s ease-in-out infinite; }
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-active > div, .modal-leave-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from > div, .modal-leave-to > div { opacity: 0; transform: scale(0.95) translateY(10px); }
</style>
