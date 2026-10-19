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
                <span class="hover:text-slate-700 cursor-pointer transition-colors">Dashboard</span>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="text-slate-800 font-semibold">Organization Structure</span>
              </nav>
              <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">Position Architecture</h1>
              <p class="text-sm text-slate-500 mt-0.5">Define job titles, reporting structures, compensation frameworks, and workforce planning parameters.</p>
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
                Create Position
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

        <!-- SEARCH BAR -->
        <div class="bg-white rounded-xl border border-slate-200/60 shadow-md backdrop-blur-sm p-3 mb-4 shrink-0">
          <div class="flex flex-col md:flex-row md:items-center gap-3">
            <div class="flex-1 relative">
              <span class="absolute inset-y-0 left-0 flex items-center pl-3"><Search class="w-4 h-4" /></span>
              <input 
                v-model="filters.search"
                type="text" 
                placeholder="Search positions..." 
                @input="debouncedSearch"
                class="w-full pl-9 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200"
              />
            </div>
            
            <div class="flex flex-wrap items-center gap-2">
              <select v-model="filters.department" class="bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-600 focus:ring-2 focus:ring-blue-500 shadow-sm transition-all duration-200 cursor-pointer">
                <option value="">All Departments</option>
                <option v-for="dept in filterOptions.departments" :key="dept.id" :value="String(dept.id)">{{ dept.name }}</option>
              </select>
            </div>
          </div>
        </div>

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

        <!-- TABLE VIEW SECTION - FULL HEIGHT -->
        <div class="bg-white rounded-xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 backdrop-blur-sm overflow-hidden flex-1 flex flex-col min-h-0">
          
          <!-- Position Table -->
          <div class="flex-1 overflow-auto">
            <table class="w-full text-left border-collapse">
              <thead class="sticky top-0 z-10">
                <tr class="bg-gradient-to-r from-slate-50 to-transparent border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                  <th class="py-3 px-4">Position</th>
                  <th class="py-3 px-4">Department</th>
                  <th class="py-3 px-4 text-center">Headcount</th>
                  <th class="py-3 px-4">Compensation</th>
                  <th class="py-3 px-4 text-center">Status</th>
                  <th class="py-3 px-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr 
                  v-for="position in filteredPositions" 
                  :key="position.id" 
                  class="hover:bg-gradient-to-r hover:from-blue-50/50 hover:to-transparent transition-all duration-200 group"
                >
                  <td class="py-3 px-4">
                    <div class="flex items-center gap-3">
                      <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center shadow-inner shrink-0">
                        <Briefcase class="w-4 h-4" />
                      </div>
                      <div>
                        <div class="text-sm font-bold text-slate-900">{{ position.name }}</div>
                        <div class="text-[10px] text-slate-400 font-mono">{{ position.code }}</div>
                      </div>
                    </div>
                  </td>
                  <td class="py-3 px-4">
                    <div class="text-sm text-slate-700">{{ position.department_name }}</div>
                  </td>
                  <td class="py-3 px-4 text-center">
                    <div class="flex items-center justify-center gap-3">
                      <div class="text-center">
                        <div class="text-sm font-bold text-emerald-600">{{ position.filled_positions }}</div>
                        <div class="text-[9px] text-slate-400">Filled</div>
                      </div>
                      <div class="text-slate-300">/</div>
                      <div class="text-center">
                        <div class="text-sm font-bold text-slate-800">{{ position.approved_headcount }}</div>
                        <div class="text-[9px] text-slate-400">Approved</div>
                      </div>
                    </div>
                  </td>
                  <td class="py-3 px-4">
                    <div class="text-sm text-emerald-700 font-semibold">
                      {{ formatCurrency(position.salary_min) }} – {{ formatCurrency(position.salary_max) }}
                    </div>
                  </td>
                  <td class="py-3 px-4 text-center">
                    <span :class="[
                      'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold shadow-sm whitespace-nowrap',
                      position.archived_at 
                        ? 'bg-amber-50 text-amber-700 border-amber-200' 
                        : position.status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'
                    ]">
                      {{ position.archived_at ? 'Archived' : position.status === 'active' ? 'Active' : 'Inactive' }}
                    </span>
                  </td>
                  <td class="py-3 px-4">
                    <div class="flex items-center justify-end gap-1">
                      <button 
                        v-if="!position.archived_at"
                        @click="openModal('edit', position)" 
                        class="p-1.5 hover:bg-blue-50 rounded-lg text-slate-400 hover:text-blue-600 transition-all" 
                        title="Edit"
                      >
                        <Pencil class="w-4 h-4" />
                      </button>
                      <button 
                        v-else
                        class="p-1.5 text-slate-300 cursor-not-allowed" 
                        title="Cannot edit archived"
                      >
                        <Lock class="w-4 h-4" />
                      </button>
                      <button @click="viewEmployees(position)" class="p-1.5 hover:bg-emerald-50 rounded-lg text-slate-400 hover:text-emerald-600 transition-all" title="View Employees"><Users class="w-4 h-4" /></button>
                      <button v-if="!position.archived_at" @click="confirmArchive(position.id, position.name)" class="p-1.5 hover:bg-amber-50 rounded-lg text-slate-400 hover:text-amber-600 transition-all" title="Archive"><Archive class="w-4 h-4" /></button>
                      <button v-else @click="confirmRestore(position.id, position.name)" class="p-1.5 hover:bg-emerald-50 rounded-lg text-slate-400 hover:text-emerald-600 transition-all" title="Restore"><RotateCcw class="w-4 h-4" /></button>
                    </div>
                  </td>
                </tr>
                <!-- Empty State -->
                <tr v-if="filteredPositions.length === 0">
                  <td colspan="6" class="py-12 text-center">
                    <div class="flex flex-col items-center justify-center">
                      <Briefcase class="w-6 h-6 mb-3" />
                      <p class="text-sm text-slate-500">No positions found</p>
                      <p class="text-xs text-slate-400 mt-1">Try adjusting your search or create a new position</p>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Footer with record count -->
        <div class="mt-3 text-sm text-slate-400 shrink-0">
          Showing {{ filteredPositions.length }} {{ filteredPositions.length === 1 ? 'position' : 'positions' }}
        </div>

        <!-- VALIDATION ERROR MODAL -->

        <!-- VALIDATION ERROR MODAL -->
        <div v-if="showValidationModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div class="bg-white rounded-2xl border border-slate-200/60 w-full max-w-md overflow-hidden shadow-2xl">
            <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-rose-50 to-transparent">
              <div>
                <h3 class="text-sm font-bold text-rose-600">Validation Error</h3>
                <p class="text-xs text-slate-500 mt-0.5">Please check the following:</p>
              </div>
              <button @click="closeValidationModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all duration-200 hover:rotate-90 transform">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
            <div class="p-6">
              <div class="flex items-start gap-4 mb-4">
                <div class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center flex-shrink-0">
                  <AlertTriangle class="w-6 h-6" />
                </div>
                <div>
                  <p class="text-sm text-slate-700">{{ validationMessage }}</p>
                </div>
              </div>
              <div class="flex justify-end">
                <button @click="closeValidationModal" class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-lg hover:shadow-xl hover:-translate-y-0.5">
                  Got it
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- CUSTOM CONFIRMATION MODAL -->
        <div v-if="showConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div class="bg-white rounded-2xl border border-slate-200/60 w-full max-w-md overflow-hidden shadow-2xl">
            <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-transparent">
              <div>
                <h3 class="text-sm font-bold text-slate-900">{{ confirmModal.title }}</h3>
                <p class="text-xs text-slate-500 mt-0.5">{{ confirmModal.subtitle }}</p>
              </div>
              <button @click="closeConfirmModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all duration-200 hover:rotate-90 transform">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
            <div class="p-6">
              <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center" :class="confirmModal.type === 'danger' ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600'">
                  <AlertTriangle v-if="confirmModal.type === 'danger'" class="w-6 h-6" />
                  <Archive v-else class="w-6 h-6" />
                </div>
                <div>
                  <p class="text-sm text-slate-700">{{ confirmModal.message }}</p>
                </div>
              </div>
              <div class="flex justify-end gap-3">
                <button @click="closeConfirmModal" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-all duration-200 shadow-sm hover:shadow-md">
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

        <!-- CREATE/EDIT POSITION MODAL -->
        <div v-if="activeModal" class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div class="bg-white rounded-2xl border border-slate-200/60 w-full max-w-3xl overflow-hidden shadow-2xl max-h-[90vh] flex flex-col">
            
            <!-- Modal Header -->
            <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-transparent shrink-0">
              <div>
                <h3 class="text-sm font-bold text-slate-900">{{ modalDetails.title }}</h3>
                <p class="text-xs text-slate-500 mt-0.5">{{ modalDetails.subtitle }}</p>
              </div>
              <button @click="closeModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all duration-200 hover:rotate-90 transform">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>

            <!-- Modal Body -->
            <div class="flex-1 overflow-y-auto p-6 space-y-5">
              
              <!-- SECTION 1: Position Information -->
              <div class="bg-slate-50/50 border border-slate-200 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-4">
                  <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  </div>
                  <h4 class="text-sm font-bold text-slate-800">Position Information</h4>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Position Name <span class="text-rose-500">*</span></label>
                    <input v-model="form.name" type="text" placeholder="e.g., Senior Software Engineer" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" :class="{ 'border-rose-500 ring-2 ring-rose-200': validationErrors.name }" />
                    <p v-if="validationErrors.name" class="text-[10px] text-rose-500 mt-1">{{ validationErrors.name }}</p>
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Department <span class="text-rose-500">*</span></label>
                    <select v-model="form.department_id" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200 cursor-pointer" :class="{ 'border-rose-500 ring-2 ring-rose-200': validationErrors.department_id }">
                      <option value="">Select Department</option>
                      <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                    </select>
                    <p v-if="validationErrors.department_id" class="text-[10px] text-rose-500 mt-1">{{ validationErrors.department_id }}</p>
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Reports To <span class="text-slate-400 text-[10px] font-normal">(Optional)</span></label>
                    <select v-model="form.reports_to_id" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200 cursor-pointer">
                      <option :value="null">None (Top Level)</option>
                      <option v-for="pos in allPositions.filter(p => p.id !== form.id)" :key="pos.id" :value="pos.id">{{ pos.name }} ({{ pos.code }})</option>
                    </select>
                  </div>
                </div>
              </div>

              <!-- SECTION 2: Status -->
              <div class="bg-slate-50/50 border border-slate-200 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-4">
                  <div class="w-8 h-8 rounded-lg bg-indigo-500/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  </div>
                  <h4 class="text-sm font-bold text-slate-800">Status</h4>
                </div>
                <div class="grid grid-cols-1 gap-4">
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Status <span class="text-rose-500">*</span></label>
                    <select v-model="form.status" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200 cursor-pointer">
                      <option value="active">Active</option>
                      <option value="inactive">Inactive</option>
                    </select>
                  </div>
                </div>
              </div>

              <!-- SECTION 3: Job Information -->
              <div class="bg-slate-50/50 border border-slate-200 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-4">
                  <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                  </div>
                  <h4 class="text-sm font-bold text-slate-800">Job Information</h4>
                </div>
                <div class="space-y-4">
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Job Description</label>
                    <textarea v-model="form.description" rows="2" placeholder="Brief overview of the position and its purpose..." class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200 resize-none"></textarea>
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Key Responsibilities</label>
                    <textarea v-model="form.responsibilities" rows="3" placeholder="• List primary duties and tasks&#10;• Include day-to-day activities&#10;• Specify expected outcomes..." class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200 resize-none"></textarea>
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Qualifications</label>
                    <textarea v-model="form.qualifications" rows="2" placeholder="• Education requirements&#10;• Years of experience&#10;• Specific certifications..." class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200 resize-none"></textarea>
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Required Skills</label>
                    <input v-model="form.required_skills" type="text" placeholder="e.g., Python, AWS, Leadership, Agile, Docker" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                    <p class="text-[10px] text-slate-400 mt-1">Separate skills with commas</p>
                  </div>
                </div>
              </div>

              <!-- SECTION 4: Compensation -->
              <div class="bg-slate-50/50 border border-slate-200 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-4">
                  <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  </div>
                  <h4 class="text-sm font-bold text-slate-800">Compensation</h4>
                </div>
                <div class="space-y-4">
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Minimum Salary</label>
                    <div class="relative">
                      <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 text-xs">₱</span>
                      <input v-model="salaryMinDisplay" type="text" inputmode="numeric" placeholder="e.g., 50,000" class="w-full pl-8 pr-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                    </div>
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Maximum Salary</label>
                    <div class="relative">
                      <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 text-xs">₱</span>
                      <input v-model="salaryMaxDisplay" type="text" inputmode="numeric" placeholder="e.g., 120,000" class="w-full pl-8 pr-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                    </div>
                  </div>
                </div>
              </div>

              <!-- SECTION 5: Workforce Planning -->
              <div class="bg-slate-50/50 border border-slate-200 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-4">
                  <div class="w-8 h-8 rounded-lg bg-indigo-500/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                  </div>
                  <h4 class="text-sm font-bold text-slate-800">Workforce Planning</h4>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1.5">Approved Headcount</label>
                  <input v-model="form.approved_headcount" type="number" min="1" placeholder="Number of approved positions" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                  <p class="text-[10px] text-slate-400 mt-1">Total number of employees allowed for this position</p>
                </div>
              </div>

            </div>

            <!-- Modal Footer -->
            <div class="p-4 border-t border-slate-100 bg-gradient-to-r from-slate-50 to-transparent flex items-center justify-between shrink-0">
              <div v-if="activeModal === 'edit'" class="flex items-center gap-2">
                <button @click="confirmDelete" class="px-4 py-2.5 bg-white border border-rose-200 hover:bg-rose-50 text-rose-600 rounded-xl text-sm font-semibold transition-all duration-200 shadow-sm hover:shadow-md">
                  <Trash2 class="w-4 h-4 inline" /> Delete Position
                </button>
              </div>
              <div class="flex items-center gap-3 ml-auto">
                <button @click="closeModal" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-all duration-200 shadow-sm hover:shadow-md">
                  Cancel
                </button>
                <button 
                  @click="validateAndConfirm"
                  :disabled="saving"
                  class="px-5 py-2.5 bg-gradient-to-r from-slate-800 to-slate-900 hover:from-slate-700 hover:to-slate-800 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-lg hover:shadow-xl hover:shadow-slate-500/25 hover:-translate-y-0.5 disabled:opacity-50 disabled:hover:translate-y-0"
                >
                  {{ saving ? 'Saving...' : (activeModal === 'edit' ? 'Update Position' : 'Create Position') }}
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
import { ref, computed, reactive, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Briefcase, Users, CheckCircle2, Target, ClipboardList, Archive, Search, Pencil, Lock, RotateCcw, BarChart3, Clock, AlertTriangle, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    positionsData: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    filterOptions: { type: Object, default: () => ({ departments: [], managementLevels: [] }) },
    metrics: { type: Object, default: () => ({ active: 0, totalApproved: 0, totalFilled: 0, totalVacant: 0, archived: 0 }) },
    departments: { type: Array, default: () => [] },
    allPositions: { type: Array, default: () => [] },
});

// State
const showArchived = ref(false);
const activeModal = ref(null);
const saving = ref(false);
const showConfirmModal = ref(false);
const showValidationModal = ref(false);
const validationMessage = ref('');
const confirmAction = ref(null);

const validationErrors = reactive({
    name: '',
    department_id: '',
});

// Confirm Modal State
const confirmModal = ref({
    title: '',
    subtitle: '',
    message: '',
    confirmText: 'Confirm',
    type: 'warning',
});

// Positions from server
const allPositionsData = ref([]);

// Filters
const filters = reactive({
    search: props.filters?.search || '',
    department: props.filters?.department || '',
});

// Computed filtered positions based on showArchived toggle
// Note: server already filters by department_id, this is a client-side
// guard that matches by id (backend expects department id, not name).
const filteredPositions = computed(() => {
    return allPositionsData.value.filter(position => {
        const matchesArchived = showArchived.value ? !!position.archived_at : !position.archived_at;
        const matchesSearch = !filters.search || 
            position.name.toLowerCase().includes(filters.search.toLowerCase()) || 
            position.code.toLowerCase().includes(filters.search.toLowerCase());
        const matchesDept = !filters.department
            || String(position.department_id) === String(filters.department)
            || position.department_name === filters.department;
        return matchesArchived && matchesSearch && matchesDept;
    });
});

// Form
const form = reactive({
    id: null, code: '', name: '', description: '', department_id: '',
    reports_to_id: null,
    management_level: 'Individual Contributor', organization_level: 3,
    responsibilities: '', qualifications: '', required_skills: '',
    salary_min: null, salary_max: null, overtime_eligible: false,
    approved_headcount: 1, required_training: '', required_certifications: '',
    required_equipment: '', status: 'active'
});

// Computed
const archivedCount = computed(() => {
    if (props.metrics?.archived !== undefined) {
        return props.metrics.archived;
    }
    return allPositionsData.value.filter(p => p.archived_at).length;
});

const activeCount = computed(() => {
    if (props.metrics?.active !== undefined) {
        return props.metrics.active;
    }
    return allPositionsData.value.filter(p => !p.archived_at).length;
});

const generalMetrics = computed(() => [
    { label: 'Active Positions', value: props.metrics?.active || 0, icon: Briefcase, subtext: 'Defined job titles' },
    { label: 'Total Headcount', value: props.metrics?.totalApproved || 0, icon: Users, subtext: 'Approved positions' },
    { label: 'Filled Positions', value: props.metrics?.totalFilled || 0, icon: CheckCircle2, subtext: `${props.metrics?.totalApproved ? Math.round((props.metrics.totalFilled/props.metrics.totalApproved)*100) : 0}% fulfillment` },
    { label: 'Vacant Positions', value: props.metrics?.totalVacant || 0, icon: Target, subtext: 'Open for recruitment' },
]);

const modalDetails = computed(() => ({
    create: { title: 'Create New Position', subtitle: 'Define a new job position within the company.' },
    edit: { title: 'Edit Position', subtitle: 'Modify position details and requirements' },
    duplicate: { title: 'Duplicate Position', subtitle: 'Create a copy of an existing position' },
}[activeModal.value] || { title: '', subtitle: '' }));

// Initialize positions from props
onMounted(() => {
    if (props.positionsData?.data) {
        allPositionsData.value = props.positionsData.data;
    }
});

// Watch for prop changes
watch(() => props.positionsData, (newVal) => {
    if (newVal?.data) allPositionsData.value = newVal.data;
}, { deep: true });

// Format currency
const formatCurrency = (val) => {
    if (val === null || val === undefined || val === '') return '—';
    return '₱' + Number(val).toLocaleString('en-PH');
};

// Salary inputs show thousand commas while typing (342345 → 342,345).
// The form model always keeps the raw number for validation + submit.
const digitsOnly = (v) => String(v ?? '').replace(/[^0-9]/g, '');
const formatSalaryInput = (v) => {
    const d = digitsOnly(v);
    if (d === '') return '';
    return Number(d).toLocaleString('en-US');
};
const parseSalaryInput = (v) => {
    const d = digitsOnly(v);
    return d === '' ? null : Number(d);
};
const salaryMinDisplay = computed({
    get: () => formatSalaryInput(form.salary_min),
    set: (v) => { form.salary_min = parseSalaryInput(v); },
});
const salaryMaxDisplay = computed({
    get: () => formatSalaryInput(form.salary_max),
    set: (v) => { form.salary_max = parseSalaryInput(v); },
});

// Debounced search
let searchTimeout;
const debouncedSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => loadPositions(), 300);
};

// Toggle archived view
const toggleArchived = () => {
    showArchived.value = !showArchived.value;
    loadPositions();
};

// Load positions
const loadPositions = () => {
    const params = {
        search: filters.search,
        department: filters.department,
        status: showArchived.value ? 'archived' : 'active',
    };
    router.get(route('hrm.workforce.positions.index'), params, { preserveState: true, replace: true });
};

// Watch filters
watch(() => filters.department, () => loadPositions());

// ============================================
// VALIDATION FUNCTIONS
// ============================================

const validateForm = () => {
    let isValid = true;
    validationErrors.name = '';
    validationErrors.department_id = '';

    if (!form.name || form.name.trim() === '') {
        validationErrors.name = 'Position Name is required.';
        isValid = false;
    } else if (form.name.length < 3) {
        validationErrors.name = 'Position Name must be at least 3 characters.';
        isValid = false;
    }

    if (!form.department_id) {
        validationErrors.department_id = 'Please select a department.';
        isValid = false;
    }

    // Validate salary range
    if (form.salary_min && form.salary_max && form.salary_min > form.salary_max) {
        showValidationError('Minimum salary cannot be greater than maximum salary.');
        return false;
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
    validationErrors.department_id = '';
    
    if (!validateForm()) {
        showValidationError('Please fix the highlighted fields before proceeding.');
        return;
    }
    
    const isEdit = activeModal.value === 'edit';
    const titleText = isEdit ? 'Update Position' : 'Create Position';
    const subtitleText = isEdit ? 'Confirm changes to this position' : 'Confirm new position creation';
    const messageText = isEdit 
        ? `Are you sure you want to update "${form.name}"?` 
        : `Are you sure you want to create a new position "${form.name}"?`;
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
        title: 'Archive Position',
        subtitle: 'This action can be reversed',
        message: `Are you sure you want to archive "${name}"? This position will be hidden from active views but can be restored later.`,
        confirmText: 'Archive',
        type: 'warning',
        action: () => archivePosition(id)
    });
};

const confirmRestore = (id, name) => {
    showConfirmation({
        title: 'Restore Position',
        subtitle: 'Reactivate archived position',
        message: `Are you sure you want to restore "${name}"? This position will become active again.`,
        confirmText: 'Restore',
        type: 'warning',
        action: () => reactivatePosition(id)
    });
};

const confirmDelete = () => {
    showConfirmation({
        title: 'Delete Position',
        subtitle: 'This action cannot be undone',
        message: `Are you sure you want to delete "${form.name}"? This will permanently remove the position and all associated data.`,
        confirmText: 'Delete Permanently',
        type: 'danger',
        action: () => deletePosition()
    });
};

const confirmReport = (type) => {
    const reportNames = {
        employees: 'Employees Assigned Report',
        vacancy: 'Vacancy Analysis Report',
        history: 'Position History Log'
    };
    showConfirmation({
        title: 'Generate Report',
        subtitle: 'Position Report',
        message: `Are you sure you want to generate the "${reportNames[type]}"?`,
        confirmText: 'Generate',
        type: 'warning',
        action: () => generateReport(type)
    });
};

// ============================================
// CRUD FUNCTIONS
// ============================================

const openModal = (type, data = null) => {
    activeModal.value = type;
    validationErrors.name = '';
    validationErrors.department_id = '';
    validationMessage.value = '';
    
    if (data) {
        Object.assign(form, {
            id: data.id, code: data.code, name: data.name, description: data.description || '',
            department_id: data.department_id || '',
            reports_to_id: data.reports_to_id || null,
            management_level: data.management_level || 'Individual Contributor',
            organization_level: data.organization_level || 3,
            responsibilities: data.responsibilities || '', qualifications: data.qualifications || '',
            required_skills: data.required_skills || '',
            salary_min: data.salary_min || null, salary_max: data.salary_max || null,
            overtime_eligible: data.overtime_eligible || false,
            approved_headcount: data.approved_headcount || 1,
            required_training: data.required_training || '', required_certifications: data.required_certifications || '',
            required_equipment: data.required_equipment || '', status: data.status || 'active'
        });
    } else {
        Object.assign(form, {
            id: null, code: '', name: '', description: '', department_id: '',
            reports_to_id: null,
            management_level: 'Individual Contributor', organization_level: 3,
            responsibilities: '', qualifications: '', required_skills: '',
            salary_min: null, salary_max: null, overtime_eligible: false,
            approved_headcount: 1, required_training: '', required_certifications: '',
            required_equipment: '', status: 'active'
        });
    }
};

const closeModal = () => {
    activeModal.value = null;
    validationErrors.name = '';
    validationErrors.department_id = '';
    validationMessage.value = '';
};

const submitForm = async () => {
    saving.value = true;
    // Surface server-side (422) messages instead of failing silently.
    const showServerErrors = (errors) => {
        const msg = Object.values(errors || {}).flat().join(' ');
        showValidationError(msg || 'Please fix the highlighted fields before proceeding.');
    };
    try {
        if (activeModal.value === 'edit') {
            await router.put(route('hrm.workforce.positions.update', { position: form.id }), form, {
                onSuccess: () => { closeModal(); loadPositions(); },
                onError: showServerErrors,
                onFinish: () => { saving.value = false; }
            });
        } else {
            const submitData = { ...form };
            await router.post(route('hrm.workforce.positions.store'), submitData, {
                onSuccess: () => { closeModal(); loadPositions(); },
                onError: showServerErrors,
                onFinish: () => { saving.value = false; }
            });
        }
    } catch (e) {
        saving.value = false;
    }
};

const deletePosition = async () => {
    try {
        await router.delete(route('hrm.workforce.positions.destroy', { position: form.id }), {
            onSuccess: () => closeModal()
        });
    } catch (e) {
        console.error('Error deleting position:', e);
    }
};

const archivePosition = async (id) => {
    try {
        await router.post(route('hrm.workforce.positions.archive', { position: id }), {}, {
            onSuccess: () => loadPositions()
        });
    } catch (e) {
        console.error('Error archiving position:', e);
    }
};

const reactivatePosition = async (id) => {
    try {
        await router.post(route('hrm.workforce.positions.reactivate', { position: id }), {}, {
            onSuccess: () => loadPositions()
        });
    } catch (e) {
        console.error('Error reactivating position:', e);
    }
};

const viewEmployees = async (position) => {
    try {
        const res = await fetch(route('hrm.workforce.positions.employees', { position: position.id }));
        const data = await res.json();
        showConfirmation({
            title: 'Employees Assigned',
            subtitle: position.name,
            message: `${data.employees?.total || 0} employees are currently assigned to this position.`,
            confirmText: 'Close',
            type: 'warning',
            action: () => {}
        });
    } catch (e) {
        console.error('Failed to load employees:', e);
        showValidationError('Failed to load employees. Please try again.');
    }
};

const generateReport = (type) => {
    const reportNames = {
        employees: 'Employees Assigned Report',
        vacancy: 'Vacancy Analysis Report',
        history: 'Position History Log'
    };
    showConfirmation({
        title: 'Report Generated',
        subtitle: 'Success',
        message: `The "${reportNames[type]}" has been generated successfully.`,
        confirmText: 'OK',
        type: 'success',
        action: () => {}
    });
};
</script>

<style scoped>
.bg-grid-slate-100 {
  background-image: radial-gradient(circle, #cbd5e1 1px, transparent 1px);
  background-size: 24px 24px;
}
.bg-clip-text {
  -webkit-background-clip: text;
  background-clip: text;
}
.backdrop-blur-sm {
  backdrop-filter: blur(8px);
}
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>