<template>
  <AuthenticatedLayout>
    <div class="h-full flex flex-col bg-gradient-to-br from-slate-100 via-slate-50 to-blue-50 overflow-hidden -mx-4 -mt-4 px-4 pt-4">

      <div class="flex-1 text-slate-900 font-sans relative flex flex-col overflow-hidden">

        <!-- Subtle Background Pattern -->
        <div class="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,transparent,black,transparent)] pointer-events-none"></div>

        <!-- Professional Header -->
        <header class="mb-6 relative shrink-0">
          <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
              <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
                <span>Dashboard</span>
                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="text-slate-800 font-semibold">Organization Configuration</span>
              </nav>
              <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">Employment Types</h1>
              <p class="text-sm text-slate-500 mt-0.5">Define and manage employment classifications used across job postings, payroll, and HR configuration.</p>
            </div>

            <!-- Quick Actions - Right Aligned -->
            <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
              <button
                @click="openModal('create')"
                class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-lg hover:shadow-xl hover:shadow-blue-500/25 hover:-translate-y-0.5"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Create Employment Type
              </button>

              <button
                @click="toggleArchived()"
                class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-medium transition-all duration-200 shadow-sm hover:shadow-md hover:-translate-y-0.5"
              >
                <span v-if="showArchived"><ClipboardList class="w-4 h-4" /></span>
                <span v-else><Archive class="w-4 h-4" /></span>
                {{ showArchived ? 'Show Active (' + activeCount + ')' : 'Archived (' + archivedCount + ')' }}
              </button>
            </div>
          </div>
        </header>

        <!-- KPI METRICS ROW -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4 shrink-0">
          <div v-for="metric in generalMetrics" :key="metric.label"
               class="group bg-white rounded-xl border border-slate-200/60 p-4 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-blue-200/60 shadow-md backdrop-blur-sm">
            <div class="flex items-center justify-between mb-2">
              <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">{{ metric.label }}</span>
              <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center group-hover:from-blue-100 group-hover:to-blue-200 transition-all duration-300 shadow-inner">
                <component :is="metric.icon" class="w-5 h-5" />
              </div>
            </div>
            <div class="text-2xl font-bold text-slate-900">{{ metric.value }}</div>
            <div v-if="metric.subtext" class="text-xs text-slate-400 mt-0.5">{{ metric.subtext }}</div>
          </div>
        </div>

        <!-- SEARCH BAR -->
        <div class="bg-white rounded-xl border border-slate-200/60 shadow-md backdrop-blur-sm p-3 mb-4 shrink-0">
          <div class="flex flex-col md:flex-row md:items-center gap-3">
            <div class="flex-1 relative">
              <span class="absolute inset-y-0 left-0 flex items-center pl-3"><Search class="w-4 h-4 text-slate-400" /></span>
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search employment types..."
                class="w-full pl-9 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200"
              />
            </div>
          </div>
        </div>

        <!-- TABLE VIEW SECTION - FULL HEIGHT -->
        <div class="bg-white rounded-xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 backdrop-blur-sm overflow-hidden flex-1 flex flex-col min-h-0">

          <!-- Employment Types Table - Scrollable -->
          <div class="flex-1 overflow-auto">
            <table class="w-full text-left border-collapse">
              <thead class="sticky top-0 z-10">
                <tr class="bg-gradient-to-r from-slate-50 to-transparent border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                  <th class="py-3 px-4">Employment Type</th>
                  <th class="py-3 px-4">Description</th>
                  <th class="py-3 px-4 text-center">Status</th>
                  <th class="py-3 px-4">Slug</th>
                  <th class="py-3 px-4">Created</th>
                  <th class="py-3 px-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr
                  v-for="type in filteredEmploymentTypes"
                  :key="type.id"
                  class="hover:bg-gradient-to-r hover:from-blue-50/50 hover:to-transparent transition-all duration-200 group"
                >
                  <td class="py-3 px-4">
                    <div class="flex items-center gap-3">
                      <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center shadow-inner shrink-0">
                        <Briefcase class="w-4 h-4 text-indigo-600" />
                      </div>
                      <div class="text-sm font-bold text-slate-900">{{ type.name }}</div>
                    </div>
                  </td>
                  <td class="py-3 px-4">
                    <div class="text-sm text-slate-600 line-clamp-1 max-w-xs">{{ type.description || '—' }}</div>
                  </td>
                  <td class="py-3 px-4 text-center">
                    <span :class="[
                      'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold shadow-sm whitespace-nowrap',
                      type.archived_at
                        ? 'bg-amber-50 text-amber-700 border-amber-200'
                        : type.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'
                    ]">
                      {{ type.archived_at ? 'Archived' : type.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </td>
                  <td class="py-3 px-4">
                    <span class="text-[10px] font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded">{{ type.slug }}</span>
                  </td>
                  <td class="py-3 px-4">
                    <span class="text-sm text-slate-500">{{ type.created_at || '—' }}</span>
                  </td>
                  <td class="py-3 px-4">
                    <div class="flex items-center justify-end gap-1">
                      <button
                        v-if="!type.archived_at"
                        @click="openModal('edit', type)"
                        class="p-1.5 hover:bg-blue-50 rounded-lg text-slate-400 hover:text-blue-600 transition-all"
                        title="Edit"
                      ><Pencil class="w-4 h-4" /></button>
                      <button
                        v-else
                        class="p-1.5 text-slate-300 cursor-not-allowed"
                        title="Cannot edit archived"
                      ><Lock class="w-4 h-4" /></button>
                      <button v-if="!type.archived_at" @click="archiveType(type.id)" class="p-1.5 hover:bg-amber-50 rounded-lg text-slate-400 hover:text-amber-600 transition-all" title="Archive"><Archive class="w-4 h-4" /></button>
                      <button v-else @click="restoreType(type.id)" class="p-1.5 hover:bg-emerald-50 rounded-lg text-slate-400 hover:text-emerald-600 transition-all" title="Restore"><Undo2 class="w-4 h-4" /></button>
                    </div>
                  </td>
                </tr>
                <!-- Empty State -->
                <tr v-if="filteredEmploymentTypes.length === 0">
                  <td colspan="6" class="py-12 text-center">
                    <div class="flex flex-col items-center justify-center">
                      <span class="text-5xl mb-3"><Briefcase class="w-6 h-6" /></span>
                      <p class="text-sm text-slate-500">No employment types found</p>
                      <p class="text-xs text-slate-400 mt-1">Try adjusting your search or create a new employment type</p>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Footer with record count -->
        <div class="mt-3 text-sm text-slate-400 shrink-0">
          Showing {{ filteredEmploymentTypes.length }} {{ filteredEmploymentTypes.length === 1 ? 'record' : 'records' }}
        </div>

        <!-- CREATE/EDIT MODAL -->
        <div v-if="activeModal" class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div class="bg-white rounded-2xl border border-slate-200/60 w-full max-w-2xl overflow-hidden shadow-2xl max-h-[90vh] flex flex-col">

            <!-- Modal Header -->
            <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-transparent shrink-0">
              <div>
                <h3 class="text-sm font-bold text-slate-900">{{ activeModal === 'create' ? 'Create Employment Type' : 'Edit Employment Type' }}</h3>
                <p class="text-xs text-slate-500 mt-0.5">Employment classifications are reused across job postings.</p>
              </div>
              <button @click="closeModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all duration-200 hover:rotate-90 transform">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <!-- Modal Body -->
            <div class="flex-1 overflow-y-auto p-6 space-y-5">
              <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Employment Name <span class="text-rose-500">*</span></label>
                <input
                  v-model="form.name"
                  type="text"
                  placeholder="e.g., Full-time, Part-time, Contract"
                  :class="['w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200', error && !form.name ? 'border-rose-500 ring-2 ring-rose-200' : '']"
                />
                <p v-if="error && !form.name" class="text-[10px] text-rose-500 mt-1">Employment name is required.</p>
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Slug</label>
                <p class="w-full px-3 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-xs text-slate-600">{{ previewSlug || 'Auto-generated from name' }}</p>
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Description</label>
                <textarea
                  v-model="form.description"
                  rows="3"
                  placeholder="Describe this employment classification..."
                  class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200 resize-none"
                ></textarea>
              </div>

              <div class="flex items-center justify-between p-3 bg-slate-50/50 border border-slate-200 rounded-xl">
                <div>
                  <div class="text-xs font-semibold text-slate-700">Active</div>
                  <div class="text-[10px] text-slate-400 mt-0.5">Only active types appear in job posting forms.</div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.is_active" class="sr-only peer" />
                  <div class="w-10 h-5 bg-slate-200 rounded-full peer peer-checked:bg-emerald-500 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-5"></div>
                </label>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="p-4 border-t border-slate-100 bg-gradient-to-r from-slate-50 to-transparent flex items-center justify-end shrink-0">
              <div class="flex items-center gap-3">
                <button @click="closeModal" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-all duration-200 shadow-sm hover:shadow-md">
                  Cancel
                </button>
                <button
                  @click="submitForm"
                  :disabled="saving"
                  class="px-5 py-2.5 bg-gradient-to-r from-slate-800 to-slate-900 hover:from-slate-700 hover:to-slate-800 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-lg hover:shadow-xl hover:shadow-slate-500/25 hover:-translate-y-0.5 disabled:opacity-50 disabled:hover:translate-y-0"
                >
                  {{ saving ? 'Saving...' : (activeModal === 'edit' ? 'Update Type' : 'Create Type') }}
                </button>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";
import axios from "axios";
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { ClipboardList, Archive, Search, Briefcase, CheckCircle2, Pencil, Lock, Undo2 } from 'lucide-vue-next';

const props = defineProps({
    employmentTypes: { type: Array, default: () => [] },
    archivedCount: { type: Number, default: 0 },
    activeCount: { type: Number, default: 0 },
    totalCount: { type: Number, default: 0 },
});

// State
const showArchived = ref(false);
const activeModal = ref(null);
const searchQuery = ref("");
const saving = ref(false);
const error = ref(false);

const form = ref({
    id: null,
    name: "",
    slug: "",
    description: "",
    is_active: true,
});

// Computed
const generalMetrics = computed(() => {
    const active = props.totalCount - props.archivedCount;

    return [
        {
            label: "Total Types",
            value: props.totalCount,
            icon: ClipboardList,
            subtext: "All classifications",
        },
        {
            label: "Active Types",
            value: active,
            icon: Briefcase,
            subtext: "Not archived",
        },
        {
            label: "Enabled",
            value: props.activeCount,
            icon: CheckCircle2,
            subtext: "Available for job postings",
        },
        {
            label: "Archived",
            value: props.archivedCount,
            icon: Archive,
            subtext: "Soft-deleted types",
        },
    ];
});

const filteredEmploymentTypes = computed(() => {
    return props.employmentTypes.filter((t) => {
        const matchesArchived = showArchived.value ? !!t.archived_at : !t.archived_at;
        const q = searchQuery.value.toLowerCase();
        const matchesSearch =
            !q ||
            t.name.toLowerCase().includes(q) ||
            (t.slug || "").toLowerCase().includes(q) ||
            (t.description || "").toLowerCase().includes(q);
        return matchesArchived && matchesSearch;
    });
});

// Toggle archived view
const toggleArchived = () => {
    showArchived.value = !showArchived.value;
};

// Modal functions
const resetForm = () => {
    form.value = {
        id: null,
        name: "",
        slug: "",
        description: "",
        is_active: true,
    };
    error.value = false;
};

const openModal = (type, data = null) => {
    activeModal.value = type;
    resetForm();
    if (type === "edit" && data) {
        form.value = {
            id: data.id,
            name: data.name || "",
            slug: data.slug || "",
            description: data.description || "",
            is_active: data.is_active !== undefined ? data.is_active : true,
        };
    }
};

const closeModal = () => {
    activeModal.value = null;
    resetForm();
};

const reloadPage = () => {
    router.reload({
        only: ["employmentTypes", "archivedCount", "activeCount", "totalCount"],
        preserveScroll: true,
    });
};

const submitForm = async () => {
    error.value = false;

    if (!form.value.name || !form.value.name.trim()) {
        error.value = true;
        toast.error("Employment name is required.");
        return;
    }

    saving.value = true;

    const payload = {
        name: form.value.name.trim(),
        description: form.value.description || null,
        is_active: form.value.is_active,
    };

    try {
        if (form.value.id) {
            const res = await axios.patch(
                route("hrm.workforce.employment-types.update", { employmentType: form.value.id }),
                payload
            );
            toast.success(res.data?.message || "Employment type updated successfully.");
        } else {
            const res = await axios.post(route("hrm.workforce.employment-types.store"), payload);
            toast.success(res.data?.message || "Employment type created successfully.");
        }
        closeModal();
        reloadPage();
    } catch (e) {
        const msg = e.response?.data?.errors
            ? Object.values(e.response.data.errors).flat()[0]
            : e.response?.data?.message || "Something went wrong. Please try again.";
        toast.error(msg);
    } finally {
        saving.value = false;
    }
};

const archiveType = async (id) => {
    try {
        const res = await axios.delete(route("hrm.workforce.employment-types.destroy", { employmentType: id }));
        toast.success(res.data?.message || "Employment type archived successfully.");
        reloadPage();
    } catch (e) {
        toast.error(e.response?.data?.message || "Failed to archive employment type.");
    }
};

const restoreType = async (id) => {
    try {
        const res = await axios.post(route("hrm.workforce.employment-types.restore", { employmentType: id }));
        toast.success(res.data?.message || "Employment type restored successfully.");
        reloadPage();
    } catch (e) {
        toast.error(e.response?.data?.message || "Failed to restore employment type.");
    }
};

// Live slug preview
const previewSlug = computed(() => {
    if (form.value.slug) return form.value.slug;
    if (!form.value.name) return "";
    return form.value.name
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/(^-|-$)/g, "");
});
</script>

<style scoped>
/* Background Grid Pattern */
.bg-grid-slate-100 {
  background-image: radial-gradient(circle, #cbd5e1 1px, transparent 1px);
  background-size: 24px 24px;
}

/* Gradient Text */
.bg-clip-text {
  -webkit-background-clip: text;
  background-clip: text;
}

/* Glass Morphism Effect */
.backdrop-blur-sm {
  backdrop-filter: blur(8px);
}

.line-clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>