<template>
  <AuthenticatedLayout>
    <div class="h-full flex flex-col bg-gradient-to-br from-slate-100 via-slate-50 to-blue-50 dark:from-zinc-950 dark:via-zinc-900 dark:to-indigo-950/40 overflow-hidden -mx-4 -mt-4 px-4 pt-4">

      <div class="flex-1 text-slate-900 dark:text-zinc-100 font-sans relative flex flex-col overflow-hidden">

        <!-- Subtle Background Pattern -->
        <div class="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,transparent,black,transparent)] pointer-events-none"></div>

        <!-- Professional Header -->
        <header class="mb-6 relative shrink-0">
          <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
              <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-zinc-400 mb-2" aria-label="Breadcrumb">
                <span class="hover:text-slate-700 dark:hover:text-zinc-200 cursor-pointer transition-colors">Dashboard</span>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="text-slate-800 dark:text-zinc-100 font-semibold">Organization Structure</span>
              </nav>
              <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 dark:from-white dark:to-zinc-400 bg-clip-text text-transparent">Department Architecture</h1>
              <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">Design and manage organizational units, reporting lines, and workforce distribution.</p>
            </div>

            <!-- Quick Actions - Right Aligned -->
            <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
              <button
                @click="openModal('create')"
                class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 dark:from-indigo-600 dark:to-indigo-700 dark:hover:from-indigo-500 dark:hover:to-indigo-600 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-lg hover:shadow-xl hover:shadow-blue-500/25 hover:-translate-y-0.5"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Create Department
              </button>

              <button
                @click="toggleArchived()"
                class="flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 text-slate-700 dark:text-zinc-200 rounded-xl text-sm font-medium transition-all duration-200 shadow-sm hover:shadow-md hover:-translate-y-0.5"
              >
                <span v-if="showArchived"><ClipboardList class="w-4 h-4" /></span>
                <span v-else><Archive class="w-4 h-4" /></span>
                {{ showArchived ? 'Show Active (' + activeCount + ')' : 'Archived (' + archivedCount + ')' }}
              </button>

              <button
                @click="exportCsv"
                title="Download departments as CSV"
                aria-label="Export departments as CSV"
                class="flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 text-slate-700 dark:text-zinc-200 rounded-xl text-sm font-medium transition-all duration-200 shadow-sm hover:shadow-md hover:-translate-y-0.5"
              >
                <Download class="w-4 h-4" />
                Export
              </button>
            </div>
          </div>
        </header>

        <!-- KPI METRICS ROW -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4 shrink-0">
          <div v-for="metric in generalMetrics" :key="metric.label"
               class="group bg-white dark:bg-zinc-900/80 rounded-xl border border-slate-200/60 dark:border-zinc-800 p-4 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-blue-200/60 dark:hover:border-indigo-700/60 shadow-md backdrop-blur-sm">
            <div class="flex items-center justify-between mb-2">
              <span class="text-[10px] font-semibold text-slate-500 dark:text-zinc-400 uppercase tracking-wider">{{ metric.label }}</span>
              <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 dark:from-indigo-900/50 dark:to-indigo-800/40 flex items-center justify-center group-hover:from-blue-100 group-hover:to-blue-200 dark:group-hover:from-indigo-800/60 dark:group-hover:to-indigo-700/50 transition-all duration-300 shadow-inner">
                <component :is="metric.icon" class="w-5 h-5" :class="metric.color" />
              </div>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white">{{ metric.value }}</div>
            <div v-if="metric.subtext" class="text-xs text-slate-400 dark:text-zinc-500 mt-0.5">{{ metric.subtext }}</div>
          </div>
        </div>

        <!-- SEARCH + STATUS FILTER BAR -->
        <div class="bg-white dark:bg-zinc-900/80 rounded-xl border border-slate-200/60 dark:border-zinc-800 shadow-md backdrop-blur-sm p-3 mb-4 shrink-0">
          <div class="flex flex-col md:flex-row md:items-center gap-3">
            <div class="flex-1 relative">
              <span class="absolute inset-y-0 left-0 flex items-center pl-3"><Search class="w-4 h-4 text-slate-400 dark:text-zinc-500" /></span>
              <input
                v-model="filters.search"
                type="text"
                placeholder="Search departments..."
                aria-label="Search departments"
                @input="debouncedSearch"
                class="w-full pl-9 pr-9 py-2.5 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-sm text-slate-900 dark:text-zinc-100 placeholder:text-slate-400 dark:placeholder:text-zinc-500 focus:ring-2 focus:ring-blue-500 dark:focus:ring-indigo-500 focus:border-transparent shadow-sm transition-all duration-200"
              />
              <button v-if="filters.search" @click="clearSearch" aria-label="Clear search"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 dark:hover:text-zinc-200 transition-colors">
                <X class="w-4 h-4" />
              </button>
            </div>
            <!-- Status segmented control (client-side) -->
            <div class="flex items-center gap-1 p-1 bg-slate-100 dark:bg-zinc-800 rounded-xl" role="tablist" aria-label="Filter by status">
              <button v-for="opt in statusOptions" :key="opt.value" @click="statusFilter = opt.value" role="tab" :aria-selected="statusFilter === opt.value"
                :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition-all',
                  statusFilter === opt.value
                    ? 'bg-white dark:bg-zinc-700 text-slate-900 dark:text-white shadow-sm'
                    : 'text-slate-500 dark:text-zinc-400 hover:text-slate-800 dark:hover:text-zinc-200']">
                {{ opt.label }}
              </button>
            </div>
          </div>
        </div>

        <!-- TABLE VIEW SECTION - FULL HEIGHT -->
        <div class="bg-white dark:bg-zinc-900/80 rounded-xl border border-slate-200/60 dark:border-zinc-800 shadow-lg hover:shadow-2xl transition-all duration-300 backdrop-blur-sm overflow-hidden flex-1 flex flex-col min-h-0">

          <!-- Department Table -->
          <div class="flex-1 overflow-auto">
            <table class="w-full text-left border-collapse min-w-[760px]">
              <thead class="sticky top-0 z-10">
                <tr class="bg-gradient-to-r from-slate-50 to-transparent dark:from-zinc-800 dark:to-zinc-900 border-b border-slate-200 dark:border-zinc-700 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-zinc-400">
                  <th class="py-3 px-4">Department</th>
                  <th class="py-3 px-4">Leadership</th>
                  <th class="py-3 px-4 text-center">Stats</th>
                  <th class="py-3 px-4 text-center">Status</th>
                  <th class="py-3 px-4">Created By</th>
                  <th class="py-3 px-4">Created At</th>
                  <th class="py-3 px-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                <tr
                  v-for="dept in filteredDepartments"
                  :key="dept.id"
                  class="hover:bg-gradient-to-r hover:from-blue-50/50 hover:to-transparent dark:hover:from-indigo-900/20 dark:hover:to-transparent transition-all duration-200 group"
                >
                  <td class="py-3 px-4">
                    <div class="flex items-center gap-3">
                      <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-50 to-blue-100 dark:from-indigo-900/50 dark:to-indigo-800/40 flex items-center justify-center shadow-inner shrink-0">
                        <Building2 class="w-4 h-4 text-blue-600 dark:text-indigo-300" />
                      </div>
                      <div class="min-w-0">
                        <button @click="openModal('edit', dept)" :disabled="!!dept.archived_at"
                          class="text-sm font-bold text-slate-900 dark:text-zinc-100 flex items-center gap-2 hover:text-blue-700 dark:hover:text-indigo-300 transition-colors text-left disabled:cursor-default">
                          <span class="truncate">{{ dept.name }}</span>
                          <span v-if="dept.category" class="px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-700 text-[10px] font-semibold whitespace-nowrap">
                            {{ dept.category }}
                          </span>
                        </button>
                        <div class="text-[10px] text-slate-400 dark:text-zinc-500 font-mono">{{ dept.code }}</div>
                      </div>
                    </div>
                  </td>
                  <td class="py-3 px-4">
                    <div class="text-sm font-semibold text-slate-800 dark:text-zinc-200">{{ dept.head_name || '—' }}</div>
                  </td>
                  <td class="py-3 px-4 text-center">
                    <div class="text-sm font-bold text-slate-800 dark:text-zinc-100">{{ dept.employee_count || 0 }} emp.</div>
                    <div class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold">{{ dept.open_positions || 0 }} open</div>
                  </td>
                  <td class="py-3 px-4 text-center">
                    <span :class="[
                      'inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold shadow-sm whitespace-nowrap border',
                      dept.archived_at
                        ? 'bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-700'
                        : dept.status === 'active'
                          ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-700'
                          : 'bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400 border-slate-200 dark:border-zinc-700'
                    ]">
                      <span :class="['w-1.5 h-1.5 rounded-full', dept.archived_at ? 'bg-amber-500' : dept.status === 'active' ? 'bg-emerald-500' : 'bg-slate-400']"></span>
                      {{ dept.archived_at ? 'Archived' : dept.status === 'active' ? 'Active' : 'Inactive' }}
                    </span>
                  </td>
                  <td class="py-3 px-4">
                    <div class="text-sm font-semibold text-slate-700 dark:text-zinc-300">{{ dept.created_by_name || 'System' }}</div>
                  </td>
                  <td class="py-3 px-4">
                    <div class="text-sm text-slate-600 dark:text-zinc-400">{{ dept.created_at || '—' }}</div>
                  </td>
                  <td class="py-3 px-4">
                    <div class="flex items-center justify-end gap-1">
                      <button
                        v-if="!dept.archived_at"
                        @click="openModal('edit', dept)"
                        class="p-1.5 hover:bg-blue-50 dark:hover:bg-indigo-900/40 rounded-lg text-slate-400 dark:text-zinc-500 hover:text-blue-600 dark:hover:text-indigo-300 transition-all"
                        title="Edit"
                        :aria-label="`Edit ${dept.name}`"
                      >
                        <Pencil class="w-4 h-4" />
                      </button>
                      <button
                        v-else
                        class="p-1.5 text-slate-300 dark:text-zinc-600 cursor-not-allowed"
                        title="Cannot edit archived"
                        :aria-label="`${dept.name} is archived`"
                      >
                        <Lock class="w-4 h-4" />
                      </button>
                      <button v-if="!dept.archived_at" @click="confirmArchive(dept.id, dept.name)" class="p-1.5 hover:bg-amber-50 dark:hover:bg-amber-900/30 rounded-lg text-slate-400 dark:text-zinc-500 hover:text-amber-600 dark:hover:text-amber-300 transition-all" title="Archive" :aria-label="`Archive ${dept.name}`"><Archive class="w-4 h-4" /></button>
                      <button v-else @click="confirmRestore(dept.id, dept.name)" class="p-1.5 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 rounded-lg text-slate-400 dark:text-zinc-500 hover:text-emerald-600 dark:hover:text-emerald-300 transition-all" title="Restore" :aria-label="`Restore ${dept.name}`"><RotateCcw class="w-4 h-4" /></button>
                    </div>
                  </td>
                </tr>
                <!-- Empty State -->
                <tr v-if="filteredDepartments.length === 0">
                  <td colspan="7" class="py-12 text-center">
                    <div class="flex flex-col items-center justify-center">
                      <Building2 class="w-6 h-6 mb-3 text-slate-300 dark:text-zinc-600" />
                      <p class="text-sm text-slate-500 dark:text-zinc-400">No departments found</p>
                      <p class="text-xs text-slate-400 dark:text-zinc-500 mt-1">Try adjusting your search or create a new department</p>
                      <button v-if="filters.search" @click="clearSearch" class="mt-3 px-4 py-2 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-xs font-semibold text-slate-600 dark:text-zinc-300 hover:bg-slate-50 dark:hover:bg-zinc-700 transition-all">
                        Clear Search
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Footer with record count + pagination -->
        <div class="mt-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 shrink-0">
          <div class="text-sm text-slate-400 dark:text-zinc-500">
            Showing {{ filteredDepartments.length }} of {{ pagination.total || filteredDepartments.length }} {{ (pagination.total || filteredDepartments.length) === 1 ? 'department' : 'departments' }}
          </div>
          <div v-if="pagination.last_page > 1" class="flex items-center gap-1">
            <button @click="goToPage(pagination.current_page - 1)" :disabled="pagination.current_page <= 1" aria-label="Previous page"
              class="px-3 py-1.5 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-lg text-xs font-semibold text-slate-600 dark:text-zinc-300 disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-all">
              ← Prev
            </button>
            <span class="px-3 py-1.5 text-xs font-semibold text-slate-500 dark:text-zinc-400">
              Page {{ pagination.current_page }} of {{ pagination.last_page }}
            </span>
            <button @click="goToPage(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page" aria-label="Next page"
              class="px-3 py-1.5 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-lg text-xs font-semibold text-slate-600 dark:text-zinc-300 disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-all">
              Next →
            </button>
          </div>
        </div>

        <!-- VALIDATION ERROR MODAL -->
        <div v-if="showValidationModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" role="alertdialog" aria-modal="true" aria-label="Validation error">
          <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200/60 dark:border-zinc-700 w-full max-w-md overflow-hidden shadow-2xl">
            <div class="p-6 border-b border-slate-100 dark:border-zinc-800 flex items-center justify-between bg-gradient-to-r from-rose-50 to-transparent dark:from-rose-900/30 dark:to-transparent">
              <div>
                <h3 class="text-sm font-bold text-rose-600 dark:text-rose-400">Validation Error</h3>
                <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">Please check the following:</p>
              </div>
              <button @click="closeValidationModal" aria-label="Dismiss" class="p-2 hover:bg-slate-100 dark:hover:bg-zinc-800 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-zinc-200 transition-all duration-200 hover:rotate-90 transform">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
            <div class="p-6">
              <div class="flex items-start gap-4 mb-4">
                <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-900/30 flex items-center justify-center flex-shrink-0">
                  <AlertTriangle class="w-6 h-6 text-rose-500 dark:text-rose-400" />
                </div>
                <div>
                  <p class="text-sm text-slate-700 dark:text-zinc-200">{{ validationMessage }}</p>
                </div>
              </div>
              <div class="flex justify-end">
                <button @click="closeValidationModal" class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 dark:from-indigo-600 dark:to-indigo-700 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-lg hover:shadow-xl hover:-translate-y-0.5">
                  Got it
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- CUSTOM CONFIRMATION MODAL -->
        <div v-if="showConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" role="alertdialog" aria-modal="true" :aria-label="confirmModal.title">
          <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200/60 dark:border-zinc-700 w-full max-w-md overflow-hidden shadow-2xl">
            <div class="p-6 border-b border-slate-100 dark:border-zinc-800 flex items-center justify-between bg-gradient-to-r from-slate-50 to-transparent dark:from-zinc-800 dark:to-transparent">
              <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ confirmModal.title }}</h3>
                <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">{{ confirmModal.subtitle }}</p>
              </div>
              <button @click="closeConfirmModal" aria-label="Cancel" class="p-2 hover:bg-slate-100 dark:hover:bg-zinc-800 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-zinc-200 transition-all duration-200 hover:rotate-90 transform">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
            <div class="p-6">
              <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center" :class="confirmModal.type === 'danger' ? 'bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400' : 'bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400'">
                  <AlertTriangle v-if="confirmModal.type === 'danger'" class="w-6 h-6" />
                  <Archive v-else class="w-6 h-6" />
                </div>
                <div>
                  <p class="text-sm text-slate-700 dark:text-zinc-200">{{ confirmModal.message }}</p>
                </div>
              </div>
              <div class="flex justify-end gap-3">
                <button @click="closeConfirmModal" class="px-5 py-2.5 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-200 rounded-xl text-sm font-semibold transition-all duration-200 shadow-sm hover:shadow-md">
                  Cancel
                </button>
                <button
                  @click="executeConfirmAction"
                  :class="confirmModal.type === 'danger' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-amber-600 hover:bg-amber-700'"
                  class="px-5 py-2.5 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-lg hover:shadow-xl hover:-translate-y-0.5"
                >
                  {{ confirmModal.confirmText }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- CREATE/EDIT MODAL -->
        <div v-if="activeModal" class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" role="dialog" aria-modal="true" :aria-label="modalDetails.title">
          <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200/60 dark:border-zinc-700 w-full max-w-2xl overflow-hidden shadow-2xl max-h-[90vh] flex flex-col">

            <div class="p-6 border-b border-slate-100 dark:border-zinc-800 flex items-center justify-between bg-gradient-to-r from-slate-50 to-transparent dark:from-zinc-800 dark:to-transparent shrink-0">
              <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ modalDetails.title }}</h3>
                <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">{{ modalDetails.subtitle }}</p>
              </div>
              <button @click="closeModal" aria-label="Close dialog" class="p-2 hover:bg-slate-100 dark:hover:bg-zinc-800 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-zinc-200 transition-all duration-200 hover:rotate-90 transform">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <div class="flex-1 overflow-y-auto p-6 space-y-5">

              <!-- FORM: Create / Edit Department -->
              <div v-if="activeModal === 'create' || activeModal === 'edit'" class="space-y-5">

                <div>
                  <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-semibold text-slate-600 dark:text-zinc-300">Department Name <span class="text-rose-500">*</span></label>
                    <span class="text-[10px] font-medium" :class="(form.name?.length || 0) > 255 ? 'text-rose-500' : 'text-slate-400 dark:text-zinc-500'">{{ form.name?.length || 0 }}/255</span>
                  </div>
                  <input
                    v-model="form.name"
                    type="text"
                    placeholder="e.g., Platform Engineering"
                    maxlength="255"
                    class="w-full px-3 py-2.5 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-xs text-slate-900 dark:text-zinc-100 placeholder:text-slate-400 dark:placeholder:text-zinc-500 focus:ring-2 focus:ring-blue-500 dark:focus:ring-indigo-500 focus:border-transparent shadow-sm transition-all duration-200"
                    :class="{ 'border-rose-500 ring-2 ring-rose-200 dark:ring-rose-900': validationErrors.name }"
                  />
                  <p v-if="validationErrors.name" class="text-[10px] text-rose-500 mt-1">{{ validationErrors.name }}</p>
                </div>

                <div>
                  <label class="block text-xs font-semibold text-slate-600 dark:text-zinc-300 mb-1.5">Module <span class="text-rose-500">*</span></label>
                  <select v-model="form.category" required class="w-full px-3 py-2.5 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-xs text-slate-600 dark:text-zinc-200 focus:ring-2 focus:ring-blue-500 dark:focus:ring-indigo-500 focus:border-transparent shadow-sm transition-all duration-200 cursor-pointer">
                    <option value="">Select module</option>
                    <option v-for="module in categoryOptions" :key="module" :value="module">{{ module }}</option>
                  </select>
                </div>

                <div>
                  <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-semibold text-slate-600 dark:text-zinc-300">Description</label>
                    <span class="text-[10px] font-medium text-slate-400 dark:text-zinc-500">{{ form.description?.length || 0 }}/500</span>
                  </div>
                  <textarea v-model="form.description" rows="3" maxlength="500" placeholder="Define the operational scope and primary responsibilities..." class="w-full px-3 py-2.5 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-xs text-slate-900 dark:text-zinc-100 placeholder:text-slate-400 dark:placeholder:text-zinc-500 focus:ring-2 focus:ring-blue-500 dark:focus:ring-indigo-500 focus:border-transparent shadow-sm transition-all duration-200 resize-none"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-zinc-300 mb-1.5">Department Head <span class="text-slate-400 dark:text-zinc-500 text-[10px] font-normal">(Optional)</span></label>
                    <select v-model="form.head_id" class="w-full px-3 py-2.5 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-xs text-slate-600 dark:text-zinc-200 focus:ring-2 focus:ring-blue-500 dark:focus:ring-indigo-500 focus:border-transparent shadow-sm transition-all duration-200 cursor-pointer">
                      <option value="">Select Department Head</option>
                      <option v-for="person in filteredHeadCandidates" :key="person.id" :value="person.id">
                        {{ person.name }} — {{ person.job_position }}{{ person.department ? ' · ' + person.department : '' }}
                      </option>
                    </select>
                    <p class="text-[10px] text-slate-400 dark:text-zinc-500 mt-1">{{ headCandidatesHint }}</p>
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-zinc-300 mb-1.5">Status</label>
                    <select v-model="form.status" class="w-full px-3 py-2.5 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-xs text-slate-600 dark:text-zinc-200 focus:ring-2 focus:ring-blue-500 dark:focus:ring-indigo-500 shadow-sm transition-all duration-200 cursor-pointer">
                      <option value="active">Active</option>
                      <option value="inactive">Inactive</option>
                    </select>
                  </div>
                </div>

              </div>

            </div>

            <!-- Modal Footer -->
            <div class="p-4 border-t border-slate-100 dark:border-zinc-800 bg-gradient-to-r from-slate-50 to-transparent dark:from-zinc-800 dark:to-transparent flex items-center justify-end shrink-0">
              <div class="flex items-center gap-3">
                <button @click="closeModal" class="px-5 py-2.5 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-200 rounded-xl text-sm font-semibold transition-all duration-200 shadow-sm hover:shadow-md">
                  Cancel
                </button>
                <button
                  @click="validateAndConfirm"
                  :disabled="saving"
                  class="px-5 py-2.5 bg-gradient-to-r from-slate-800 to-slate-900 hover:from-slate-700 hover:to-slate-800 dark:from-indigo-600 dark:to-indigo-700 dark:hover:from-indigo-500 dark:hover:to-indigo-600 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-lg hover:shadow-xl hover:shadow-slate-500/25 hover:-translate-y-0.5 disabled:opacity-50 disabled:hover:translate-y-0"
                >
                  {{ saving ? 'Saving...' : getButtonText }}
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
import { ref, computed, reactive, watch, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Building2, Users, Target, BarChart3, ClipboardList, Archive, Search, Pencil, Lock, AlertTriangle, RotateCcw, Download, X } from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const props = defineProps({
    departmentsData: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    metrics: { type: Object, default: () => ({ active: 0, totalEmployees: 0, totalOpenPositions: 0, totalProjects: 0, archived: 0 }) },
    positions: { type: Array, default: () => [] },
    headCandidates: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
});

const page = usePage();

// State
const showArchived = ref(false);
const activeModal = ref(null);
const saving = ref(false);
const showConfirmModal = ref(false);
const showValidationModal = ref(false);
const validationMessage = ref('');
const validationErrors = reactive({
    name: '',
});
const confirmAction = ref(null);
const statusFilter = ref('all');
const currentPage = ref(1);

const statusOptions = [
    { label: 'All', value: 'all' },
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
];

// Module options use active role codes, matching users.role.
const categoryOptions = computed(() => (props.categories || []).map((c) =>
    typeof c === 'string' ? c : (c?.value ?? String(c ?? ''))
).map((c) => String(c)).filter(Boolean));

// Confirm Modal State
const confirmModal = ref({
    title: '',
    subtitle: '',
    message: '',
    confirmText: 'Confirm',
    type: 'warning',
});

// Departments from server
const allDepartments = ref([]);

// Pagination meta from the server paginator (supports plain arrays too)
const pagination = computed(() => {
    const d = props.departmentsData || {};
    return {
        current_page: d.current_page || currentPage.value || 1,
        last_page: d.last_page || 1,
        per_page: d.per_page || 12,
        total: d.total ?? allDepartments.value.length,
    };
});

// Computed filtered departments based on archived toggle + search + status pills
const filteredDepartments = computed(() => {
    return allDepartments.value.filter(dept => {
        const matchesArchived = showArchived.value ? !!dept.archived_at : !dept.archived_at;
        const q = (filters.search || '').toLowerCase();
        const matchesSearch = !q ||
            (dept.name || '').toLowerCase().includes(q) ||
            (dept.code || '').toLowerCase().includes(q);
        const matchesStatus = statusFilter.value === 'all'
            || (dept.archived_at ? false : (dept.status || 'active') === statusFilter.value);
        return matchesArchived && matchesSearch && matchesStatus;
    });
});

// Filters
const filters = reactive({
    search: props.filters?.search || '',
});

// Form
const form = reactive({
    id: null,
    code: '',
    name: '',
    category: '',
    description: '',
    head_id: '',
    status: 'active',
});

// Computed
const archivedCount = computed(() => {
    if (props.metrics?.archived !== undefined) {
        return props.metrics.archived;
    }
    return allDepartments.value.filter(d => d.archived_at).length;
});

const activeCount = computed(() => {
    if (props.metrics?.active !== undefined) {
        return props.metrics.active;
    }
    return allDepartments.value.filter(d => !d.archived_at).length;
});

const generalMetrics = computed(() => {
    const totalEmployees = props.metrics?.totalEmployees || 0;
    const totalOpenPositions = props.metrics?.totalOpenPositions || 0;
    const totalProjects = props.metrics?.totalProjects || 0;
    const active = props.metrics?.active || 0;

    return [
        { label: 'Active Departments', value: active, icon: Building2, color: 'text-blue-600 dark:text-indigo-300', subtext: 'Operational units' },
        { label: 'Total Workforce', value: totalEmployees, icon: Users, color: 'text-emerald-600 dark:text-emerald-300', subtext: 'Active employees' },
        { label: 'Open Positions', value: totalOpenPositions, icon: Target, color: 'text-amber-600 dark:text-amber-300', subtext: 'Active recruitment' },
        { label: 'Active Projects', value: totalProjects, icon: BarChart3, color: 'text-violet-600 dark:text-violet-300', subtext: 'Ongoing initiatives' }
    ];
});

const modalDetails = computed(() => {
    switch (activeModal.value) {
        case 'create': return { title: 'Create New Department', subtitle: 'Define a new organizational unit' };
        case 'edit': return { title: 'Edit Department', subtitle: 'Modify department properties and assignments' };
        default: return { title: '', subtitle: '' };
    }
});

const getButtonText = computed(() => {
    switch (activeModal.value) {
        case 'create': return 'Create Department';
        case 'edit': return 'Update Department';
        default: return 'Save';
    }
});

// Department Head picker: managers / supervisors of this department.
// Create mode has no department yet → show all; edit mode narrows to the
// department's own managers plus company-level (no department) people.
// The currently saved head always stays visible.
const filteredHeadCandidates = computed(() => {
    const all = props.headCandidates || [];
    if (!form.id) return all;
    return all.filter(p => {
        if (String(form.head_id) === String(p.id)) return true;
        if (p.hrm_department_id == null) return true;
        return String(p.hrm_department_id) === String(form.id);
    });
});

const headCandidatesHint = computed(() => {
    const all = props.headCandidates || [];
    const shown = filteredHeadCandidates.value || [];
    if (!form.id) return `${all.length} managers / supervisors available`;
    if (!shown.length) return 'No manager found for this department — you may leave this blank';
    return `${shown.length} manager${shown.length === 1 ? '' : 's'} available for this department`;
});

// Initialize departments from props
onMounted(() => {
    if (props.departmentsData?.data) {
        allDepartments.value = props.departmentsData.data;
        currentPage.value = props.departmentsData.current_page || 1;
        if (activeModal.value === 'create' && !form.code) {
            generateCode();
        }
    }
    window.addEventListener('keydown', onEscape);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onEscape);
    clearTimeout(searchTimeout);
});

const onEscape = (e) => {
    if (e.key !== 'Escape') return;
    if (showValidationModal.value) closeValidationModal();
    else if (showConfirmModal.value) closeConfirmModal();
    else if (activeModal.value) closeModal();
};

// Server flash → toast (controller redirects with success/error/message)
watch(() => page.props.flash, (flash) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
    if (flash?.message) toast.info(flash.message);
}, { immediate: true });

// Watch for prop changes
watch(() => props.departmentsData, (newVal) => {
    if (newVal?.data) {
        allDepartments.value = newVal.data;
        currentPage.value = newVal.current_page || currentPage.value;
    }
}, { deep: true });

// Debounced search (single fetch path — no duplicate watcher)
let searchTimeout;
const debouncedSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => loadDepartments(1), 300);
};

const clearSearch = () => {
    filters.search = '';
    loadDepartments(1);
};

// Toggle archived view
const toggleArchived = () => {
    showArchived.value = !showArchived.value;
    loadDepartments(1);
};

// Pagination
const goToPage = (pageNum) => {
    if (pageNum < 1 || pageNum > pagination.value.last_page || pageNum === pagination.value.current_page) return;
    loadDepartments(pageNum);
};

// Load departments
const loadDepartments = (pageNum = null) => {
    if (pageNum) currentPage.value = pageNum;
    router.get(route('hrm.workforce.departments.index'), {
        search: filters.search,
        status: showArchived.value ? 'archived' : 'active',
        page: currentPage.value,
    }, { preserveState: true, replace: true });
};

// CSV export of the current filter view
const exportCsv = () => {
    window.location.href = route('hrm.workforce.departments.export', {
        search: filters.search,
        status: showArchived.value ? 'archived' : 'active',
    });
    toast.info('Preparing departments export…');
};

// ============================================
// VALIDATION FUNCTIONS
// ============================================

const validateForm = () => {
    let isValid = true;
    validationErrors.name = '';

    if (!form.name || form.name.trim() === '') {
        validationErrors.name = 'Department Name is required.';
        isValid = false;
    } else if (form.name.length < 3) {
        validationErrors.name = 'Department Name must be at least 3 characters.';
        isValid = false;
    } else if (form.name.length > 255) {
        validationErrors.name = 'Department Name cannot exceed 255 characters.';
        isValid = false;
    }

    return isValid;
};

const showValidationError = (message) => {
    validationMessage.value = message;
    showValidationModal.value = true;
};

const closeValidationModal = () => {
    showValidationModal.value = false;
    validationMessage.value = '';
};

// ============================================
// CONFIRMATION MODAL FUNCTIONS
// ============================================

const showConfirmation = (config) => {
    confirmModal.value = {
        title: config.title || 'Confirm Action',
        subtitle: config.subtitle || '',
        message: config.message || 'Are you sure you want to proceed?',
        confirmText: config.confirmText || 'Confirm',
        type: config.type || 'warning',
    };
    confirmAction.value = config.action;
    showConfirmModal.value = true;
};

const closeConfirmModal = () => {
    showConfirmModal.value = false;
    confirmAction.value = null;
};

const executeConfirmAction = () => {
    if (confirmAction.value) {
        confirmAction.value();
    }
    closeConfirmModal();
};

// ============================================
// VALIDATE AND CONFIRM
// ============================================

const validateAndConfirm = () => {
    validationErrors.name = '';

    if (!validateForm()) {
        showValidationError('Please fix the highlighted fields before proceeding.');
        return;
    }

    const isEdit = activeModal.value === 'edit';
    const titleText = isEdit ? 'Update Department' : 'Create Department';
    const subtitleText = isEdit ? 'Confirm changes to this department' : 'Confirm new department creation';
    const messageText = isEdit
        ? `Are you sure you want to update "${form.name}"?`
        : `Are you sure you want to create a new department "${form.name}"?`;
    const confirmText = isEdit ? 'Update' : 'Create';
    const type = isEdit ? 'warning' : 'success';

    showConfirmation({
        title: titleText,
        subtitle: subtitleText,
        message: messageText,
        confirmText: confirmText,
        type: type,
        action: () => submitForm()
    });
};

// ============================================
// CONFIRM WRAPPERS FOR ACTIONS
// ============================================

const confirmArchive = (id, name) => {
    showConfirmation({
        title: 'Archive Department',
        subtitle: 'This action can be reversed',
        message: `Are you sure you want to archive "${name}"? This department will be hidden from active views but can be restored later.`,
        confirmText: 'Archive',
        type: 'warning',
        action: () => archiveDepartment(id)
    });
};

const confirmRestore = (id, name) => {
    showConfirmation({
        title: 'Restore Department',
        subtitle: 'Reactivate archived department',
        message: `Are you sure you want to restore "${name}"? This department will become active again.`,
        confirmText: 'Restore',
        type: 'warning',
        action: () => restoreDepartment(id)
    });
};

// ============================================
// CRUD FUNCTIONS
// ============================================

const openModal = (type, data = null) => {
    activeModal.value = type;
    validationErrors.name = '';
    validationMessage.value = '';

    if (type === 'edit' && data) {
        Object.assign(form, {
            id: data.id,
            code: data.code,
            name: data.name,
            category: data.category || '',
            description: data.description || '',
            head_id: data.head_id || '',
            status: data.status || 'active',
        });
    } else if (type === 'create') {
        Object.assign(form, {
            id: null,
            code: '',
            name: '',
            category: '',
            description: '',
            head_id: '',
            status: 'active',
        });
        generateCode();
    }
};

const generateCode = () => {
    const codes = (allDepartments.value || []).map(d => d.code).filter(Boolean);
    const maxCode = codes.reduce((max, code) => {
        const num = parseInt(String(code).replace('DEP-', ''));
        return Number.isFinite(num) && num > max ? num : max;
    }, 0);

    const newNumber = maxCode + 1;
    form.code = 'DEP-' + String(newNumber).padStart(3, '0');
};

const closeModal = () => {
    activeModal.value = null;
    validationErrors.name = '';
    validationMessage.value = '';
};

const submitForm = async () => {
    saving.value = true;
    try {
        if (activeModal.value === 'edit') {
            await router.put(route('hrm.workforce.departments.update', { department: form.id }), form, {
                onSuccess: () => {
                    toast.success('Department updated.');
                    closeModal();
                    loadDepartments();
                },
                onError: (errors) => toast.error(String(Object.values(errors || {})[0] || 'Failed to update department.')),
                onFinish: () => { saving.value = false; }
            });
        } else if (activeModal.value === 'create') {
            const submitData = { ...form };
            await router.post(route('hrm.workforce.departments.store'), submitData, {
                onSuccess: () => {
                    toast.success('Department created.');
                    closeModal();
                    loadDepartments();
                },
                onError: (errors) => toast.error(String(Object.values(errors || {})[0] || 'Failed to create department.')),
                onFinish: () => { saving.value = false; }
            });
        }
    } catch (e) {
        saving.value = false;
        toast.error('Something went wrong. Please try again.');
        console.error('Error submitting form:', e);
    }
};

const archiveDepartment = async (id) => {
    try {
        await router.post(route('hrm.workforce.departments.archive', { department: id }), {}, {
            onSuccess: () => {
                toast.success('Department archived.');
                loadDepartments();
            },
            onError: () => toast.error('Failed to archive department.')
        });
    } catch (e) {
        toast.error('Failed to archive department.');
        console.error('Error archiving department:', e);
    }
};

const restoreDepartment = async (id) => {
    try {
        await router.post(route('hrm.workforce.departments.reactivate', { department: id }), {}, {
            onSuccess: () => {
                toast.success('Department restored.');
                loadDepartments();
            },
            onError: () => toast.error('Failed to restore department.')
        });
    } catch (e) {
        toast.error('Failed to restore department.');
        console.error('Error restoring department:', e);
    }
};
</script>

<style scoped>
.bg-grid-slate-100 {
  background-image: radial-gradient(circle, #cbd5e1 1px, transparent 1px);
  background-size: 24px 24px;
}
.dark .bg-grid-slate-100 {
  background-image: radial-gradient(circle, #3f3f46 1px, transparent 1px);
}
.bg-clip-text {
  -webkit-background-clip: text;
  background-clip: text;
}
.backdrop-blur-sm {
  backdrop-filter: blur(8px);
}
</style>
