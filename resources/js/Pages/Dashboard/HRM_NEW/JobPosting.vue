<template>
  <AuthenticatedLayout>
    <div class="flex min-h-screen bg-gradient-to-br from-slate-100 via-slate-50 to-blue-50">

      <div class="flex-1 p-8 text-slate-900 font-sans min-w-0 relative flex flex-col">
        
        <!-- Subtle Background Pattern -->
        <div class="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,transparent,black,transparent)] pointer-events-none"></div>
        
        <!-- Professional Header -->
        <header class="mb-6 relative shrink-0">
          <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
              <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
                <span class="hover:text-slate-700 cursor-pointer transition-colors">Dashboard</span>
                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="text-slate-800 font-semibold">Recruitment</span>
              </nav>
              <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">Job Postings</h1>
              <p class="text-sm text-slate-500 mt-0.5">Create, publish, and manage job advertisements. Connect positions with recruitment to attract qualified applicants.</p>
            </div>

            <!-- Quick Actions - Right Aligned -->
            <div class="flex flex-wrap items-center justify-end gap-2">
              <button 
                @click="openModal('create')"
                class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-lg hover:shadow-xl hover:shadow-blue-500/25 hover:-translate-y-0.5"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Create Job Posting
              </button>
              
              <button 
                @click="toggleArchived()"
                class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-medium transition-all duration-200 shadow-sm hover:shadow-md hover:-translate-y-0.5"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                </svg>
                {{ showArchived ? 'Show Active' : 'Archived (' + archivedCount + ')' }}
              </button>
              
              <button 
                @click="exportReport"
                class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-medium transition-all duration-200 shadow-sm hover:shadow-md hover:-translate-y-0.5"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                </svg>
                Export Report
              </button>
            </div>
          </div>
        </header>

        <!-- KPI METRICS ROW - All 7 Metrics -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-2 mb-4 shrink-0">
          <div v-for="metric in metrics" :key="metric.label" 
               class="group bg-white rounded-xl border border-slate-200/60 p-3 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-blue-200/60 shadow-md backdrop-blur-sm cursor-pointer"
               @click="quickFilter(metric.filter)">
            <div class="flex items-center justify-between mb-1">
              <span class="text-[9px] font-semibold text-slate-500 uppercase tracking-wider">{{ metric.label }}</span>
              <div :class="['w-7 h-7 rounded-lg flex items-center justify-center shadow-inner', metric.bg]">
                <svg class="w-3.5 h-3.5" :class="metric.color" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path v-if="metric.icon === 'clipboard'" stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                  <path v-else-if="metric.icon === 'pencil'" stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                  <path v-else-if="metric.icon === 'users'" stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                  <path v-else-if="metric.icon === 'check'" stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  <path v-else-if="metric.icon === 'alert'" stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                  <path v-else-if="metric.icon === 'target'" stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                  <path v-else-if="metric.icon === 'lock'" stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                  <path v-else stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
              </div>
            </div>
            <div class="text-lg font-bold" :class="metric.color">{{ metric.value }}</div>
          </div>
        </div>

        <!-- SEARCH BAR -->
        <div class="bg-white rounded-xl border border-slate-200/60 shadow-md backdrop-blur-sm p-3 mb-4 shrink-0">
          <div class="flex flex-col md:flex-row md:items-center gap-3">
            <div class="flex-1 relative">
              <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
              </span>
              <input 
                v-model="filters.search"
                type="text" 
                placeholder="Search job postings..." 
                @input="debouncedSearch"
                class="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200"
              />
            </div>
            
            <div class="flex flex-wrap items-center gap-2">
              <select v-model="filters.status" @change="loadPostings" class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-600 focus:ring-2 focus:ring-blue-500 shadow-sm transition-all duration-200 cursor-pointer">
                <option value="">All Statuses</option>
                <option v-for="status in filterOptions.statuses" :key="status" :value="status">{{ status }}</option>
              </select>
              <select v-model="filters.department" @change="loadPostings" class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-600 focus:ring-2 focus:ring-blue-500 shadow-sm transition-all duration-200 cursor-pointer">
                <option value="">All Departments</option>
                <option v-for="dept in filterOptions.departments" :key="dept.id || dept" :value="dept.name || dept">{{ dept.name || dept }}</option>
              </select>
            </div>
          </div>
        </div>

        <!-- TABLE VIEW SECTION - FULL HEIGHT -->
        <div class="bg-white rounded-xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 backdrop-blur-sm overflow-hidden flex-1 flex flex-col min-h-0">
          
          <!-- Job Postings Table - Scrollable -->
          <div class="flex-1 overflow-auto">
            <table class="w-full min-w-[1100px] text-left border-collapse">
              <thead class="sticky top-0 z-10">
                <tr class="bg-gradient-to-r from-slate-50 to-transparent border-b border-slate-200 text-xs font-bold uppercase tracking-wider text-slate-500">
                  <th class="py-2.5 px-4">Posting</th>
                  <th class="py-2.5 px-4">Position</th>
                  <th class="py-2.5 px-4">Department</th>
                  <th class="py-2.5 px-4 text-center">Employment Type</th>
                  <th class="py-2.5 px-4 text-center">Vacancies</th>
                  <th class="py-2.5 px-4 text-center">Applications</th>
                  <th class="py-2.5 px-4 text-center">Status</th>
                  <th class="py-2.5 px-4">Published</th>
                  <th class="py-2.5 px-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr 
                  v-for="posting in filteredPostings" 
                  :key="posting.id" 
                  class="hover:bg-gradient-to-r hover:from-blue-50/50 hover:to-transparent transition-all duration-200 group"
                >
                  <td class="py-2.5 px-4">
                    <div class="flex items-center gap-2">
                      <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center shadow-inner shrink-0">
                        <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                      </div>
                      <div class="min-w-0">
                    <div class="text-sm font-bold text-slate-900 truncate">{{ posting.position || posting.title }}</div>
                    <div class="text-xs text-slate-500 font-mono">{{ posting.posting_id }}</div>
                      </div>
                    </div>
                  </td>
                  <td class="py-2.5 px-4">
                    <div class="text-sm text-slate-700 truncate max-w-[160px]">{{ posting.position }}</div>
                  </td>
                  <td class="py-2.5 px-4">
                    <div class="text-sm text-slate-700 truncate max-w-[140px]">{{ posting.department }}</div>
                  </td>
                  <td class="py-2.5 px-4 text-center">
                    <span :class="getEmploymentBadgeClass(posting.employment_type)" class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold whitespace-nowrap">
                      {{ posting.employment_type || 'Full-time' }}
                    </span>
                  </td>
                  <td class="py-2.5 px-4 text-center">
                    <div class="text-sm font-bold text-slate-800">{{ posting.filled_vacancies }}/{{ posting.vacancies }}</div>
                  </td>
                  <td class="py-2.5 px-4 text-center">
                    <div class="text-sm font-bold text-blue-600">{{ posting.total_applications }}</div>
                  </td>
                  <td class="py-2.5 px-4 text-center">
                    <span :class="[
                      'inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold shadow-sm whitespace-nowrap',
                      getStatusBadgeClass(posting.status)
                    ]">
                      <svg v-if="posting.status === 'Draft'" class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 8 8">
                        <circle cx="4" cy="4" r="3"/>
                      </svg>
                      <svg v-else-if="posting.status === 'Published'" class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 8 8">
                        <circle cx="4" cy="4" r="3"/>
                      </svg>
                      <svg v-else-if="posting.status === 'Closed'" class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 8 8">
                        <circle cx="4" cy="4" r="3"/>
                      </svg>
                      {{ posting.status }}
                    </span>
                  </td>
                  <td class="py-2.5 px-4">
                    <div class="text-sm text-slate-600">{{ posting.published_date || '—' }}</div>
                  </td>
                  <td class="py-2.5 px-4">
                    <div class="flex items-center justify-end gap-1">
                      <button 
                        v-if="!posting.archived_at"
                        @click="openModal('edit', posting)" 
                        class="p-1.5 hover:bg-blue-50 rounded-lg text-slate-400 hover:text-blue-600 transition-all" 
                        title="Edit"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                        </svg>
                      </button>
                      <button 
                        v-else
                        class="p-1.5 text-slate-300 cursor-not-allowed" 
                        title="Cannot edit archived"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                      </button>
                      <button @click="viewPosting(posting)" class="p-1.5 hover:bg-emerald-50 rounded-lg text-slate-400 hover:text-emerald-600 transition-all" title="View">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                      </button>
                      <!-- Dropdown Menu -->
                      <div class="relative">
                        <button 
                          @click.stop="togglePostingMenu(posting.id,$event)"
                          class="p-1.5 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-slate-600 transition-all"
                          title="More"
                        >
                          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                          </svg>
                        </button>
                        <!-- Dropdown (teleported to <body> so the table's
                             scroll container can never clip it) -->
                        <Teleport to="body">
                        <div 
                          v-if="activeMenu === posting.id" 
                          :ref="setMenuRef"
                          :style="menuStyle"
                          class="fixed w-52 bg-white border border-slate-200 rounded-xl shadow-2xl z-[60] py-1 min-w-[180px]"
                          @click.stop
                        >
                          <button 
                            v-if="posting.status === 'Draft' && !posting.archived_at" 
                            @click="publishPosting(posting.id)" 
                            class="w-full text-left px-4 py-2 text-xs text-emerald-600 hover:bg-emerald-50 flex items-center gap-2 transition-colors"
                          >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Publish
                          </button>
                          <button 
                            v-if="posting.status === 'Published' && !posting.archived_at" 
                            @click="closePosting(posting.id)" 
                            class="w-full text-left px-4 py-2 text-xs text-amber-600 hover:bg-amber-50 flex items-center gap-2 transition-colors"
                          >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                            </svg>
                            Close
                          </button>
                          <button 
                            v-if="posting.status === 'Closed' && !posting.archived_at" 
                            @click="reopenPosting(posting.id)" 
                            class="w-full text-left px-4 py-2 text-xs text-blue-600 hover:bg-blue-50 flex items-center gap-2 transition-colors"
                          >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 119 0v3.75M3.75 21.75h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                            </svg>
                            Reopen
                          </button>
                          <hr class="my-1 border-slate-100" />
                          <button 
                            v-if="!posting.archived_at" 
                            @click="archivePosting(posting.id)" 
                            class="w-full text-left px-4 py-2 text-xs text-slate-500 hover:bg-slate-50 flex items-center gap-2 transition-colors"
                          >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                            </svg>
                            Archive
                          </button>
                          <button 
                            v-else 
                            @click="reactivatePosting(posting.id)" 
                            class="w-full text-left px-4 py-2 text-xs text-emerald-600 hover:bg-emerald-50 flex items-center gap-2 transition-colors"
                          >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
                            </svg>
                            Restore
                          </button>
                          <hr class="my-1 border-slate-100" />
                          <button 
                            @click="deletePosting(posting.id)" 
                            class="w-full text-left px-4 py-2 text-xs text-rose-600 hover:bg-rose-50 flex items-center gap-2 transition-colors"
                          >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                            </svg>
                            Delete
                          </button>
                        </div>
                        </Teleport>
                      </div>
                    </div>
                  </td>
                </tr>
                <!-- Empty State -->
                <tr v-if="filteredPostings.length === 0">
                  <td colspan="9" class="py-12 text-center">
                    <div class="flex flex-col items-center justify-center">
                      <svg class="w-16 h-16 text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                      </svg>
                      <p class="text-sm text-slate-500">No job postings found</p>
                      <p class="text-xs text-slate-400 mt-1">Try adjusting your search or filters</p>
                      <button v-if="hasActiveFilters" @click="clearFilters" class="mt-3 px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-all">
                        Clear Filters
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Footer with record count -->
        <div class="mt-3 text-sm text-slate-500 shrink-0">
          Showing {{ filteredPostings.length }} {{ filteredPostings.length === 1 ? 'record' : 'records' }}
        </div>

        <!-- VALIDATION ERROR MODAL -->
        <div v-if="showValidationModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div class="bg-white rounded-2xl border border-slate-200/60 w-full max-w-md overflow-hidden shadow-2xl">
            <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-rose-50 to-transparent">
              <div>
                <h3 class="text-sm font-bold text-rose-600">Validation Error</h3>
                <p class="text-xs text-slate-500 mt-0.5">Please check the following:</p>
              </div>
              <button @click="closeValidationModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all duration-200 hover:rotate-90 transform">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
            <div class="p-6">
              <div class="flex items-start gap-4 mb-4">
                <div class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center flex-shrink-0">
                  <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                  </svg>
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
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
            <div class="p-6">
              <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center" :class="confirmModal.type === 'danger' ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600'">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path v-if="confirmModal.type === 'danger'" stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    <path v-else stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                  </svg>
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

        <!-- CREATE/EDIT MODAL -->
        <div v-if="activeModal" class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div class="bg-white rounded-2xl border border-slate-200/60 w-full max-w-3xl overflow-hidden shadow-2xl max-h-[90vh] flex flex-col">
            
            <!-- Modal Header -->
            <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-transparent shrink-0">
              <div>
                <h3 class="text-sm font-bold text-slate-900">
                  {{ modalType === 'create' ? 'Create Job Posting' : modalType === 'view' ? 'View Job Posting' : 'Edit Job Posting' }}
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Advertise open positions and manage recruitment campaigns.</p>
              </div>
              <button @click="closeModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all duration-200 hover:rotate-90 transform">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <!-- Modal Body -->
            <div class="flex-1 overflow-y-auto p-6 space-y-5">
              
              <!-- Section 1: Position Selection -->
              <div class="bg-slate-50/50 border border-slate-200 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-4">
                  <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                  </div>
                  <h4 class="text-sm font-bold text-slate-800">Position Selection</h4>
                </div>
                <div class="mb-4">
                  <label class="block text-xs font-semibold text-slate-600 mb-1.5">Department <span class="text-rose-500">*</span></label>
                  <select v-model="form.department_id" :disabled="modalType === 'view'" @change="onDepartmentChange" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-600 focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer disabled:bg-slate-50 disabled:text-slate-400">
                    <option value="">Select Department</option>
                    <option v-for="dept in filterOptions.departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1.5">Position <span class="text-rose-500">*</span></label>
                  <select v-model="form.position_id" :disabled="modalType === 'view' || !form.department_id" @change="onPositionChange" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-600 focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer disabled:bg-slate-50 disabled:text-slate-400" :class="{ 'border-rose-500 ring-2 ring-rose-200': validationErrors.position_id }">
                    <option value="">{{ form.department_id ? 'Select Position' : 'Select Department First' }}</option>
                    <option v-for="pos in filteredPositions" :key="pos.id" :value="pos.id">{{ pos.name }}</option>
                  </select>
                  <p v-if="validationErrors.position_id" class="text-[10px] text-rose-500 mt-1">{{ validationErrors.position_id }}</p>
                </div>
                <!-- Auto-filled Position Details -->
                <div v-if="form.position_id" class="mt-4 p-4 bg-white border border-slate-200 rounded-xl">
                  <div class="flex items-center justify-between mb-2">
                    <h5 class="text-xs font-bold text-slate-700">Position Details (Auto-filled)</h5>
                    <Link :href="route('hrm.workforce.positions.index')" class="text-[10px] font-bold text-blue-600 hover:text-blue-700 hover:underline">Open Positions page →</Link>
                  </div>
                  <div class="grid grid-cols-2 gap-2 text-xs">
                    <div><span class="text-slate-500">Department:</span> <span class="font-semibold">{{ form.department || '—' }}</span></div>
                    <div><span class="text-slate-500">Reports To:</span> <span class="font-semibold">{{ form.reports_to || '—' }}</span></div>
                    <div><span class="text-slate-500">Min Salary:</span> <span class="font-semibold">{{ formatCurrency(form.salary_min) }}</span></div>
                    <div><span class="text-slate-500">Max Salary:</span> <span class="font-semibold">{{ formatCurrency(form.salary_max) }}</span></div>
                    <div><span class="text-slate-500">Available Vacancies:</span> <span class="font-semibold">{{ getAvailableVacancies() }}</span></div>
                  </div>
                </div>
              </div>

              <!-- Section 2: Posting Information -->
              <div class="bg-slate-50/50 border border-slate-200 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-4">
                  <div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                  </div>
                  <h4 class="text-sm font-bold text-slate-800">Posting Information</h4>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div class="col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Posting Title</label>
                    <div class="w-full px-3 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-xs text-slate-700 font-semibold flex items-center gap-2 cursor-not-allowed opacity-90">
                      <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                      </svg>
                      <span>{{ selectedPositionName || 'Select a position above — its name becomes the posting title' }}</span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1">Auto-filled from the selected position. No manual title needed.</p>
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Employment Type <span class="text-rose-500">*</span></label>
                    <select v-model="form.employment_type_id" :disabled="modalType === 'view'" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-600 focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer disabled:bg-slate-50 disabled:text-slate-400">
                      <option value="">Select Employment Type</option>
                      <option v-for="type in employmentTypeOptions" :key="type.id" :value="type.id">
                        {{ type.name }}
                      </option>
                      <option v-if="selectedEmploymentType && !employmentTypeOptions.some(t => String(t.id) === String(selectedEmploymentType.id))" :value="selectedEmploymentType.id">
                        {{ selectedEmploymentType.name }}
                      </option>
                    </select>
                  </div>
                  <div v-if="isContractType" class="col-span-2 p-4 bg-amber-50/60 border border-amber-200 rounded-xl">
                    <div class="flex items-center gap-2 mb-3">
                      <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                      </svg>
                      <h5 class="text-xs font-bold text-amber-700">Contract Duration</h5>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                      <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Duration</label>
                        <input
                          :value="form.contract_duration"
                          @input="onContractDurationInput"
                          type="text"
                          inputmode="numeric"
                          placeholder="e.g., 6"
                          :disabled="modalType === 'view'"
                          class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm disabled:bg-slate-50 disabled:text-slate-400"
                        />
                      </div>
                      <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Period</label>
                        <select v-model="form.contract_unit" :disabled="modalType === 'view'" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-600 focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer disabled:bg-slate-50 disabled:text-slate-400">
                          <option value="">Select Period</option>
                          <option value="Month">Month</option>
                          <option value="Year">Year</option>
                        </select>
                      </div>
                    </div>
                    <p class="text-[10px] text-amber-600 mt-2">Shown because the selected employment type is contractual.</p>
                  </div>
                </div>
              </div>

              <!-- Section 3: Recruitment Details -->
              <div class="bg-slate-50/50 border border-slate-200 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-4">
                  <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                  </div>
                  <h4 class="text-sm font-bold text-slate-800">Recruitment Details</h4>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Number of Vacancies <span class="text-rose-500">*</span></label>
                    <input v-model.number="form.vacancies" type="number" min="1" :max="maxVacancies ?? undefined" @input="clampVacancies" :disabled="modalType === 'view'" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm disabled:bg-slate-50 disabled:text-slate-400" />
                    <p v-if="form.position_id" class="text-[10px] text-slate-400 mt-1">
                      {{ getPositionVacancyInfo() }}
                    </p>
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Hiring Priority</label>
                    <select v-model="form.hiring_priority" :disabled="modalType === 'view'" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-600 shadow-sm cursor-pointer disabled:bg-slate-50 disabled:text-slate-400">
                      <option>Normal</option>
                      <option>High</option>
                      <option>Urgent</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Expected Start Date</label>
                    <input v-model="form.expected_start_date" type="date" :min="todayDate" :disabled="modalType === 'view'" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm disabled:bg-slate-50 disabled:text-slate-400" />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Application Deadline</label>
                    <input v-model="form.closing_date" type="date" :disabled="modalType === 'view'" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm disabled:bg-slate-50 disabled:text-slate-400" />
                  </div>
                </div>
              </div>

              <!-- Section 4: Job Information -->
              <div class="bg-slate-50/50 border border-slate-200 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-4">
                  <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                  </div>
                  <h4 class="text-sm font-bold text-slate-800">Job Information</h4>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Work Location</label>
                    <div class="w-full px-3 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-xs text-slate-600 cursor-not-allowed opacity-75 flex items-center gap-2">
                      <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                      </svg>
                      <span>{{ workLocation || 'Km 22 Emilio Aguinaldo Hwy, Anabu 1B, Imus, 4103 Cavite' }}</span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1">Fixed company location</p>
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Work Mode</label>
                    <div class="w-full px-3 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-xs text-slate-600 cursor-not-allowed opacity-75 flex items-center gap-2">
                      <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                      </svg>
                      <span>On-site</span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1">Fixed to on-site work</p>
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Work Schedule</label>
                    <input v-model="form.work_schedule" type="text" placeholder="e.g., Mon-Fri, 9AM-6PM" :disabled="modalType === 'view'" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs shadow-sm disabled:bg-slate-50 disabled:text-slate-400" />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Shift</label>
                    <select v-model="form.shift" :disabled="modalType === 'view'" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-600 focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer disabled:bg-slate-50 disabled:text-slate-400">
                      <template v-if="(shifts || []).length">
                        <option v-for="sh in shifts" :key="sh.id" :value="sh.name">{{ sh.name }}</option>
                      </template>
                      <template v-else>
                        <option>Morning Shift</option>
                        <option>Afternoon Shift</option>
                        <option>Night Shift</option>
                        <option>Rotating Shift</option>
                        <option>Flexible Shift</option>
                      </template>
                      <option v-if="form.shift && !(shifts || []).some((sh) => sh.name === form.shift)" :value="form.shift">{{ form.shift }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Salary Visibility</label>
                    <select v-model="form.salary_visibility" :disabled="modalType === 'view'" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-600 shadow-sm cursor-pointer disabled:bg-slate-50 disabled:text-slate-400">
                      <option>Show Range</option>
                      <option>Hide</option>
                      <option>Show on Application</option>
                    </select>
                  </div>
                  <div class="col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Training Required?</label>
                    <label
                      :class="[
                        form.training_required ? 'border-amber-300 bg-amber-50 text-amber-800' : 'border-slate-200 bg-white text-slate-600',
                        modalType === 'view' ? 'cursor-not-allowed opacity-70' : 'cursor-pointer hover:border-amber-300'
                      ]"
                      class="w-full flex items-center justify-between rounded-xl border px-3 py-2.5 text-xs font-semibold shadow-sm transition-colors duration-200">
                      <span>{{ form.training_required ? 'Yes, training is needed' : 'No training needed' }}</span>
                      <input v-model="form.training_required" type="checkbox" :disabled="modalType === 'view'"
                        class="peer sr-only" />
                      <span :class="form.training_required ? 'border-amber-500 bg-amber-500' : 'border-slate-300 bg-white'"
                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border transition-all duration-200 ease-out peer-focus-visible:ring-2 peer-focus-visible:ring-amber-400 peer-focus-visible:ring-offset-2">
                        <svg class="h-3.5 w-3.5 text-white transition-all duration-200 ease-out"
                          :class="form.training_required ? 'scale-100 opacity-100' : 'scale-50 opacity-0'"
                          viewBox="0 0 20 20" fill="none" aria-hidden="true">
                          <path d="m4 10 4 4 8-8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                      </span>
                    </label>
                  </div>
                </div>
              </div>

              <!-- Section 5: Salary Range -->
              <div class="bg-slate-50/50 border border-slate-200 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-4">
                  <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                  </div>
                  <h4 class="text-sm font-bold text-slate-800">Salary Range</h4>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Minimum Salary</label>
                    <div class="w-full px-3 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-xs text-slate-600 cursor-not-allowed opacity-75 flex items-center gap-2">
                      <span v-if="form.salary_min !== null && form.salary_min !== ''" class="font-semibold">{{ formatCurrency(form.salary_min) }}</span>
                      <span v-else class="text-slate-400">Auto from position</span>
                    </div>
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Maximum Salary</label>
                    <div class="w-full px-3 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-xs text-slate-600 cursor-not-allowed opacity-75 flex items-center gap-2">
                      <span v-if="form.salary_max !== null && form.salary_max !== ''" class="font-semibold">{{ formatCurrency(form.salary_max) }}</span>
                      <span v-else class="text-slate-400">Auto from position</span>
                  </div>
                  <div v-show="showHiringManager" class="col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Hiring Manager <span class="text-slate-400 font-normal">(Optional)</span></label>
                    <select v-model="form.hiring_manager" :disabled="modalType === 'view'" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-600 focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer disabled:bg-slate-50 disabled:text-slate-400">
                      <option value="">Select Hiring Manager (optional)</option>
                      <option v-for="emp in filteredHiringManagers || []" :key="emp.id" :value="emp.name">
                        {{ emp.name }} — {{ emp.job_position }}{{ emp.department ? ' (' + emp.department + ')' : '' }}
                      </option>
                    </select>
                    <p class="text-[10px] text-slate-400 mt-1">
                      {{ hiringManagerHint }}
                    </p>
                  </div>
                </div>
              </div>
                <p class="text-[10px] text-slate-400 mt-2">Read-only — taken from the selected position. Update it on the Positions page.</p>
              </div>

              <!-- Applicant Statistics (View Mode Only) -->
              <div v-if="modalType === 'view' && form.applicant_stats" class="bg-slate-50/50 border border-slate-200 rounded-xl p-5">
                <h4 class="text-sm font-bold text-slate-800 mb-3">Applicant Statistics</h4>
                <div class="grid grid-cols-3 gap-3">
                  <div class="p-3 bg-blue-50 border border-blue-100 rounded-xl text-center">
                    <span class="text-lg font-bold text-blue-700">{{ form.applicant_stats?.total || 0 }}</span>
                    <p class="text-[10px] text-blue-500">Total</p>
                  </div>
                  <div class="p-3 bg-amber-50 border border-amber-100 rounded-xl text-center">
                    <span class="text-lg font-bold text-amber-700">{{ form.applicant_stats?.shortlisted || 0 }}</span>
                    <p class="text-[10px] text-amber-500">Shortlisted</p>
                  </div>
                  <div class="p-3 bg-purple-50 border border-purple-100 rounded-xl text-center">
                    <span class="text-lg font-bold text-purple-700">{{ form.applicant_stats?.interviewed || 0 }}</span>
                    <p class="text-[10px] text-purple-500">Interviewed</p>
                  </div>
                  <div class="p-3 bg-indigo-50 border border-indigo-100 rounded-xl text-center">
                    <span class="text-lg font-bold text-indigo-700">{{ form.applicant_stats?.offered || 0 }}</span>
                    <p class="text-[10px] text-indigo-500">Offered</p>
                  </div>
                  <div class="p-3 bg-emerald-50 border border-emerald-100 rounded-xl text-center">
                    <span class="text-lg font-bold text-emerald-700">{{ form.applicant_stats?.hired || 0 }}</span>
                    <p class="text-[10px] text-emerald-500">Hired</p>
                  </div>
                  <div class="p-3 bg-rose-50 border border-rose-100 rounded-xl text-center">
                    <span class="text-lg font-bold text-rose-700">{{ form.applicant_stats?.rejected || 0 }}</span>
                    <p class="text-[10px] text-rose-500">Rejected</p>
                  </div>
                </div>
              </div>

            </div>

            <!-- Modal Footer -->
            <div class="p-4 border-t border-slate-100 bg-gradient-to-r from-slate-50 to-transparent flex items-center justify-between shrink-0">
              <div v-if="modalType === 'edit'" class="flex items-center gap-2">
                <button @click="confirmDelete" class="px-4 py-2.5 bg-white border border-rose-200 hover:bg-rose-50 text-rose-600 rounded-xl text-sm font-semibold transition-all duration-200 shadow-sm hover:shadow-md">
                  <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                  </svg>
                  Delete
                </button>
              </div>
              <div class="flex items-center gap-3 ml-auto">
                <button @click="closeModal" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-all duration-200 shadow-sm hover:shadow-md">
                  Cancel
                </button>
                <button 
                  v-if="modalType !== 'view'"
                  @click="validateAndConfirm"
                  :disabled="saving"
                  class="px-5 py-2.5 bg-gradient-to-r from-slate-800 to-slate-900 hover:from-slate-700 hover:to-slate-800 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-lg hover:shadow-xl hover:shadow-slate-500/25 hover:-translate-y-0.5 disabled:opacity-50 disabled:hover:translate-y-0"
                >
                  {{ saving ? 'Saving...' : (modalType === 'edit' ? 'Update Posting' : 'Create Posting') }}
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
import { ref, computed, reactive, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const props = defineProps({
    postingsData: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    filterOptions: { type: Object, default: () => ({ departments: [], statuses: [] }) },
    metrics: { type: Object, default: () => ({ active: 0, drafts: 0, total_applicants: 0, hired: 0, urgent: 0, vacancies: 0, closed: 0, archived: 0 }) },
    positions: { type: Array, default: () => [] },
    hiringManagers: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
    workLocation: { type: String, default: 'Km 22 Emilio Aguinaldo Hwy, Anabu 1B, Imus, 4103 Cavite' },
    employmentTypes: { type: Array, default: () => ['Full-time', 'Part-time', 'Contract', 'Temporary', 'Internship', 'Freelance'] },
});

// State
const page = usePage();
const showArchived = ref(false);
const activeModal = ref(null);
const modalType = ref('create');
const showHiringManager = ref(false);
const activeMenu = ref(null);
const menuRef = ref(null);
const menuAnchor = ref(null);
const menuStyle = reactive({ top: '0px', left: '0px' });
const saving = ref(false);
const showConfirmModal = ref(false);
const showValidationModal = ref(false);
const validationMessage = ref('');
const confirmAction = ref(null);

const validationErrors = reactive({
    position_id: '',
});

// Confirm Modal State
const confirmModal = ref({
    title: '',
    subtitle: '',
    message: '',
    confirmText: 'Confirm',
    type: 'warning',
});

// Postings from server - ALL ACTIVE POSTINGS
const allPostings = ref([]);

// Filters
const filters = reactive({
    search: props.filters?.search || '',
    status: props.filters?.status || '',
    department: props.filters?.department || '',
});

// Form
const form = reactive({
    id: null,
    title: '',
    position_id: '',
    department: '',
    department_id: null,
    reports_to: '',
    salary_min: null,
    salary_max: null,
    vacancies: 1,
    hiring_priority: 'Normal',
    status: 'Draft',
    recruiter: '',
    hiring_manager: '',
    work_location: props.workLocation || 'Km 22 Emilio Aguinaldo Hwy, Anabu 1B, Imus, 4103 Cavite',
    employment_type_id: null,
    contract_duration: '',
    contract_unit: '',
    work_mode: 'On-site',
    work_schedule: '',
    shift: 'Morning Shift',
    salary_visibility: 'Show Range',
    training_required: false,
    recruitment_notes: '',
    internal_notes: '',
    require_initial_interview: true,
    require_final_interview: false,
    closing_date: '',
    expected_start_date: '',
    published_date: '',
    applicant_stats: null,
});

// Computed
const archivedCount = computed(() => props.metrics?.archived || 0);

const metrics = computed(() => {
    const m = props.metrics || {};
    return [
        { label: 'Active Postings', value: m.active || 0, icon: 'clipboard', bg: 'bg-blue-50', color: 'text-blue-600', filter: 'Published' },
        { label: 'Drafts', value: m.drafts || 0, icon: 'pencil', bg: 'bg-amber-50', color: 'text-amber-600', filter: 'Draft' },
        { label: 'Total Applicants', value: m.total_applicants || 0, icon: 'users', bg: 'bg-indigo-50', color: 'text-indigo-600', filter: null },
        { label: 'Hired', value: m.hired || 0, icon: 'check', bg: 'bg-emerald-50', color: 'text-emerald-600', filter: null },
        { label: 'Urgent', value: m.urgent || 0, icon: 'alert', bg: 'bg-rose-50', color: 'text-rose-600', filter: null },
        { label: 'Vacancies', value: m.vacancies || 0, icon: 'target', bg: 'bg-purple-50', color: 'text-purple-600', filter: null },
        { label: 'Closed', value: m.closed || 0, icon: 'lock', bg: 'bg-slate-100', color: 'text-slate-500', filter: null },
    ];
});

// Show ALL active postings (not archived)
const filteredPostings = computed(() => {
    return allPostings.value.filter(p => {
        const matchesArchived = showArchived.value ? !!p.archived_at : !p.archived_at;
        const haystack = `${p.position || ''} ${p.title || ''} ${p.posting_id || ''}`.toLowerCase();
        const matchesSearch = !filters.search || haystack.includes(filters.search.toLowerCase());
        const matchesStatus = !filters.status || p.status === filters.status;
        const matchesDept = !filters.department || p.department === filters.department;
        return matchesArchived && matchesSearch && matchesStatus && matchesDept;
    });
});

// Display name for a posting — position name wins since title is derived.
const postingDisplayName = (p) => p?.position || p?.title || 'this posting';

// Contract / contractual employment types reveal contract-specific fields.
const hiddenEmploymentTypes = ['internship', 'temporary'];
const employmentTypeOptions = computed(() => {
    const list = props.filterOptions?.employmentTypes || [];
    return list.filter(t => !hiddenEmploymentTypes.includes(String(t.name || '').trim().toLowerCase()));
});
const selectedEmploymentType = computed(() => {
    const list = props.filterOptions?.employmentTypes || [];
    return list.find(t => String(t.id) === String(form.employment_type_id)) || null;
});
const isContractType = computed(() => /contract/i.test(selectedEmploymentType.value?.name || ''));

// Contract duration input accepts numbers only (no decimals, signs or text).
const onContractDurationInput = (e) => {
    const cleaned = String(e.target.value).replace(/[^0-9]/g, '');
    form.contract_duration = cleaned;
    e.target.value = cleaned;
};

const filteredPositions = computed(() => {
    if (!form.department_id) return [];
    return (props.positions || []).filter((position) =>
        String(position.department_id) === String(form.department_id)
    );
});

const selectedPositionName = computed(() => {
    if (form.position_id) {
        const found = props.positions.find(p => String(p.id) === String(form.position_id));
        if (found) return found.name;
    }
    return form.title || '';
});

// Hiring managers: manager level or higher, restricted to the selected
// position's department (matched by department id, falling back to the
// department name). Managers from other departments are never shown.
const filteredHiringManagers = computed(() => {
    const all = props.hiringManagers || [];
    const deptId = form.department_id;
    const deptName = String(form.department || '').trim().toLowerCase();
    if (!deptId && !deptName) return all;
    return all.filter(m => {
        const idMatch = deptId != null && m.hrm_department_id != null
            && String(m.hrm_department_id) === String(deptId);
        const nameMatch = deptName && String(m.department || '').trim().toLowerCase() === deptName;
        return idMatch || nameMatch;
    });
});

const hiringManagerHint = computed(() => {
    const all = props.hiringManagers || [];
    const shown = filteredHiringManagers.value || [];
    if (!form.position_id) return `${all.length} managers available — pick a position to filter by department`;
    if (!shown.length) return 'No manager found for this department — you may leave this blank';
    const deptLabel = form.department ? ` for ${form.department}` : '';
    return `${shown.length} manager${shown.length === 1 ? '' : 's'} available${deptLabel}`;
});

const formatCurrency = (val) => {
    if (val === null || val === undefined || val === '') return '—';
    return '₱' + Number(val).toLocaleString('en-PH');
};

// Get employment badge class
const getEmploymentBadgeClass = (type) => {
    const map = {
        'Full-time': 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'Part-time': 'bg-blue-50 text-blue-700 border-blue-200',
        'Contract': 'bg-amber-50 text-amber-700 border-amber-200',
        'Temporary': 'bg-purple-50 text-purple-700 border-purple-200',
        'Internship': 'bg-rose-50 text-rose-700 border-rose-200',
        'Freelance': 'bg-indigo-50 text-indigo-700 border-indigo-200',
    };
    return map[type] || 'bg-slate-50 text-slate-700 border-slate-200';
};

// Get available vacancies from selected position
const getAvailableVacancies = () => {
    if (!form.position_id) return '—';
    const selectedPosition = props.positions.find(p => String(p.id) === String(form.position_id));
    if (!selectedPosition) return '—';
    
    const approved = selectedPosition.approved_headcount || 0;
    const filled = selectedPosition.filled_positions || 0;
    const available = approved - filled;
    return available > 0 ? available : 0;
};

// Get position vacancy info for display
const getPositionVacancyInfo = () => {
    if (!form.position_id) return '';
    const selectedPosition = props.positions.find(p => String(p.id) === String(form.position_id));
    if (!selectedPosition) return '';
    
    const approved = selectedPosition.approved_headcount || 0;
    const filled = selectedPosition.filled_positions || 0;
    const available = approved - filled;
    
    return `Approved: ${approved}, Filled: ${filled}, Available: ${available}`;
};

// Number of vacancies may never exceed the position's available headcount
// (approved − filled). Falls back to 1 when the position is already full so
// the required min=1 input stays valid.
const maxVacancies = computed(() => {
    if (!form.position_id) return null;
    const p = props.positions.find(x => String(x.id) === String(form.position_id));
    if (!p) return null;
    const available = (p.approved_headcount || 0) - (p.filled_positions || 0);
    return available > 0 ? available : 1;
});

const clampVacancies = () => {
    const max = maxVacancies.value;
    if (max == null) return;
    let v = Math.floor(Number(form.vacancies) || 0);
    if (v > max) v = max;
    if (v < 1) v = 1;
    form.vacancies = v;
};

// Today (local) as YYYY-MM-DD — the earliest selectable date for the
// expected start date (past dates are not allowed).
const todayDate = (() => {
    const d = new Date();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${m}-${day}`;
})();

const getStatusBadgeClass = (s) => ({ 
  'Draft':'bg-amber-50 text-amber-700 border-amber-200',
  'Published':'bg-emerald-50 text-emerald-700 border-emerald-200',
  'Closed':'bg-slate-50 text-slate-500 border-slate-200',
  'Filled':'bg-blue-50 text-blue-700 border-blue-200',
  'Cancelled':'bg-rose-50 text-rose-700 border-rose-200',
  'Archived':'bg-slate-50 text-slate-400 border-slate-200' 
}[s]||'');

// Initialize postings from props
onMounted(() => {
    if (props.postingsData?.data) {
        allPostings.value = props.postingsData.data;
    }
    document.addEventListener('click', onDocumentClick);
    window.addEventListener('scroll', onViewportChange, true);
    window.addEventListener('resize', onViewportChange);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    window.removeEventListener('scroll', onViewportChange, true);
    window.removeEventListener('resize', onViewportChange);
});

// Watch for prop changes
watch(() => props.postingsData, (newVal) => {
    if (newVal?.data) allPostings.value = newVal.data;
}, { deep: true });

// Debounced search
let searchTimeout;
const debouncedSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => loadPostings(), 300);
};

// Toggle archived view
const toggleArchived = () => {
    showArchived.value = !showArchived.value;
    loadPostings();
};

// Load postings
const loadPostings = () => {
    router.get(route('hrm.recruitment.job-postings.index'), {
        search: filters.search,
        status: filters.status,
        department: filters.department,
        showArchived: showArchived.value ? 'true' : 'false',
    }, { preserveState: true, replace: true });
};

// Watch filters
watch([() => filters.status, () => filters.department], () => loadPostings());

// Server flash → toast (controller redirects with success/error/message)
watch(() => page.props.flash, (flash) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
    if (flash?.message) toast.info(flash.message);
}, { immediate: true });

// Clear all filters so freshly saved rows are always visible
const resetFilters = () => {
    filters.search = '';
    filters.status = '';
    filters.department = '';
    showArchived.value = false;
};
const hasActiveFilters = computed(() =>
    !!filters.search || !!filters.status || !!filters.department || showArchived.value
);
const clearFilters = () => {
    resetFilters();
    loadPostings();
};

// Quick filter
const quickFilter = (status) => { 
    if (status) {
        filters.status = status;
        loadPostings();
    }
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
// VALIDATION FUNCTIONS
// ============================================

const validateForm = () => {
    let isValid = true;
    validationErrors.position_id = '';

    if (!form.position_id) {
        validationErrors.position_id = 'Please select a position.';
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
// VALIDATE AND CONFIRM
// ============================================

const validateAndConfirm = () => {
    validationErrors.position_id = '';
    
    if (!validateForm()) {
        showValidationError('Please select a position before proceeding.');
        return;
    }
    
    const isEdit = modalType.value === 'edit';
    const displayName = selectedPositionName.value || postingDisplayName(form);
    const titleText = isEdit ? 'Update Job Posting' : 'Create Job Posting';
    const subtitleText = isEdit ? 'Confirm changes to this posting' : 'Confirm new posting creation';
    const messageText = isEdit 
        ? `Are you sure you want to update "${displayName}"?` 
        : `Are you sure you want to create a new job posting for "${displayName}"?`;
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

const confirmDelete = () => {
    showConfirmation({
        title: 'Delete Job Posting',
        subtitle: 'This action cannot be undone',
        message: `Are you sure you want to delete "${postingDisplayName(form)}"? This will permanently remove the posting.`,
        confirmText: 'Delete Permanently',
        type: 'danger',
        action: () => deletePosting(form.id)
    });
};

// ============================================
// POSITION CHANGE - AUTO-FILL FROM DATABASE
// ============================================

const onDepartmentChange = () => {
    const department = (props.filterOptions?.departments || []).find(
        (item) => String(item.id) === String(form.department_id)
    );
    form.department = department?.name || '';
    form.position_id = '';
    form.title = '';
    form.reports_to = '';
    form.salary_min = null;
    form.salary_max = null;
    form.vacancies = 1;
    form.hiring_manager = '';
};

const onPositionChange = () => {
    const selectedPosition = props.positions.find(p => String(p.id) === String(form.position_id));
    
    if (selectedPosition) {
        form.title = selectedPosition.name || '';
        form.department = selectedPosition.department_name || '';
        form.department_id = selectedPosition.department_id ?? null;
        form.reports_to = selectedPosition.reports_to_name || '';
        form.salary_min = selectedPosition.salary_min || null;
        form.salary_max = selectedPosition.salary_max || null;
        
        // Auto-fill vacancies from position's available vacancies, capped at
        // the available headcount (approved − filled).
        const approved = selectedPosition.approved_headcount || 0;
        const filled = selectedPosition.filled_positions || 0;
        const available = approved - filled;
        form.vacancies = available > 0 ? available : 1;
        
        form.hiring_manager = '';
    } else {
        const department = (props.filterOptions?.departments || []).find(
            (item) => String(item.id) === String(form.department_id)
        );
        form.department = department?.name || '';
        form.reports_to = '';
        form.salary_min = null;
        form.salary_max = null;
        form.vacancies = 1;
        form.hiring_manager = '';
    }
};

// ============================================
// TOGGLE MENU
// ============================================

const togglePostingMenu = async (id, event) => {
    if (activeMenu.value === id) {
        activeMenu.value = null;
        menuAnchor.value = null;
        return;
    }
    activeMenu.value = id;
    menuAnchor.value = event?.currentTarget?.getBoundingClientRect?.() || null;
    await nextTick();
    placePostingMenu();
};

// Fixed-position dropdown so it is never clipped by the table's scroll
// container; it flips up when there isn't room below.
const placePostingMenu = () => {
    const rect = menuAnchor.value;
    if (!rect) return;
    const menuW = menuRef.value?.offsetWidth || 208;
    const menuH = menuRef.value?.offsetHeight || 160;
    let left = rect.right - menuW;
    if (left + menuW > window.innerWidth - 8) left = window.innerWidth - menuW - 8;
    if (left < 8) left = 8;
    let top = rect.bottom + 4;
    if (top + menuH > window.innerHeight - 8) top = rect.top - menuH - 4;
    if (top < 8) top = 8;
    menuStyle.top = top + 'px';
    menuStyle.left = left + 'px';
};

const closePostingMenu = () => {
    activeMenu.value = null;
    menuAnchor.value = null;
};

const setMenuRef = (el) => {
    menuRef.value = el;
};

const onDocumentClick = (e) => {
    if (activeMenu.value == null) return;
    if (menuRef.value && menuRef.value.contains(e.target)) return;
    closePostingMenu();
};

const onViewportChange = () => {
    if (activeMenu.value != null) closePostingMenu();
};

// ============================================
// CRUD FUNCTIONS
// ============================================

const openModal = (type, data = null) => {
    activeModal.value = true;
    modalType.value = type;
    activeMenu.value = null;
    validationErrors.position_id = '';
    validationMessage.value = '';
    
    if (data) {
        Object.assign(form, {
            id: data.id,
            title: data.position || data.title || '',
            position_id: data.position_id || '',
            department: data.department || '',
            department_id: null,
            reports_to: data.reports_to || '',
            salary_min: data.salary_min || null,
            salary_max: data.salary_max || null,
            vacancies: data.vacancies || 1,
            hiring_priority: data.hiring_priority || 'Normal',
            status: data.status || 'Draft',
            recruiter: data.recruiter || '',
            hiring_manager: data.hiring_manager || '',
            work_location: data.work_location || props.workLocation || 'Km 22 Emilio Aguinaldo Hwy, Anabu 1B, Imus, 4103 Cavite',
            employment_type_id: data.employment_type_id || null,
            contract_duration: data.contract_duration || '',
            contract_unit: data.contract_unit || '',
            work_mode: 'On-site',
            work_schedule: data.work_schedule || '',
            shift: data.shift || 'Morning Shift',
            salary_visibility: data.salary_visibility || 'Show Range',
            training_required: Boolean(data.training_required),
            recruitment_notes: data.recruitment_notes || '',
            internal_notes: data.internal_notes || '',
            require_initial_interview: data.require_initial_interview !== undefined ? data.require_initial_interview : true,
            require_final_interview: data.require_final_interview !== undefined ? data.require_final_interview : false,
            closing_date: data.closing_date || '',
            expected_start_date: data.expected_start_date || '',
            published_date: data.published_date || '',
            applicant_stats: {
                total: data.total_applications || 0,
                shortlisted: data.shortlisted || 0,
                interviewed: data.interviewed || 0,
                offered: data.offered || 0,
                hired: data.hired_count || 0,
                rejected: data.rejected || 0,
            }
        });
    } else {
        Object.assign(form, {
            id: null,
            title: '',
            position_id: '',
            department: '',
            department_id: null,
            reports_to: '',
            salary_min: null,
            salary_max: null,
            vacancies: 1,
            hiring_priority: 'Normal',
            status: 'Draft',
            recruiter: '',
            hiring_manager: '',
            work_location: props.workLocation || 'Km 22 Emilio Aguinaldo Hwy, Anabu 1B, Imus, 4103 Cavite',
            employment_type_id: null,
            contract_duration: '',
            contract_unit: '',
            work_mode: 'On-site',
            work_schedule: '',
            shift: (props.shifts && props.shifts.length) ? props.shifts[0].name : 'Morning Shift',
            salary_visibility: 'Show Range',
            training_required: false,
            recruitment_notes: '',
            internal_notes: '',
            require_initial_interview: true,
            require_final_interview: false,
            closing_date: '',
            expected_start_date: '',
            published_date: '',
            applicant_stats: null,
        });
    }
    // Resolve department id from the selected position so the manager list
    // filters correctly in edit/view mode (table rows carry names only).
    if (form.position_id) {
        const found = props.positions.find(p => String(p.id) === String(form.position_id));
        form.department_id = found?.department_id ?? null;
        if (found && !form.department) form.department = found.department_name || '';
    } else {
        form.department_id = null;
    }
};

const closeModal = () => {
    activeModal.value = null;
    validationErrors.position_id = '';
    validationMessage.value = '';
};

const submitForm = async () => {
    saving.value = true;
    // Expected start date cannot be in the past (min attribute blocks the
    // picker; this guards typed input too).
    if (form.expected_start_date && form.expected_start_date < todayDate) {
        toast.error('Expected start date cannot be in the past.');
        saving.value = false;
        return;
    }
    // Title + salary are derived — sync from the selected position before send.
    const selected = props.positions.find(p => String(p.id) === String(form.position_id));
    if (selected) {
        form.title = selected.name || form.title;
        form.salary_min = selected.salary_min ?? form.salary_min;
        form.salary_max = selected.salary_max ?? form.salary_max;
    }
    try {
        if (modalType.value === 'edit') {
            await router.put(route('hrm.recruitment.job-postings.update', { jobPosting: form.id }), form, {
                onSuccess: () => {
                    closeModal();
                    resetFilters();
                    loadPostings();
                },
                onError: (errors) => toast.error(String(Object.values(errors || {})[0] || 'Failed to update posting.')),
                onFinish: () => { saving.value = false; }
            });
        } else {
            await router.post(route('hrm.recruitment.job-postings.store'), form, {
                onSuccess: () => {
                    closeModal();
                    resetFilters();
                    loadPostings();
                },
                onError: (errors) => toast.error(String(Object.values(errors || {})[0] || 'Failed to create posting.')),
                onFinish: () => { saving.value = false; }
            });
        }
    } catch (e) {
        saving.value = false;
        toast.error('Something went wrong. Please try again.');
        console.error('Error submitting form:', e);
    }
};

const deletePosting = async (id) => {
    try {
            await router.delete(route('hrm.recruitment.job-postings.destroy', { jobPosting: id }), {
                onSuccess: () => {
                    toast.success('Posting deleted.');
                    closeModal();
                    loadPostings();
                },
                onError: () => toast.error('Failed to delete posting.')
            });
    } catch (e) {
        console.error('Error deleting posting:', e);
    }
};

const publishPosting = async (id) => {
    activeMenu.value = null;
    showConfirmation({
        title: 'Publish Job Posting',
        subtitle: 'Make this posting live',
        message: 'Are you sure you want to publish this job posting? It will become visible to applicants.',
        confirmText: 'Publish',
        type: 'success',
        action: async () => {
            try {
                await router.post(route('hrm.recruitment.job-postings.publish', { jobPosting: id }), {}, {
                    onSuccess: () => { toast.success('Posting published.'); loadPostings(); },
                    onError: () => toast.error('Failed to publish posting.')
                });
            } catch (e) {
                toast.error('Failed to publish posting.');
                console.error('Error publishing posting:', e);
            }
        }
    });
};

const closePosting = async (id) => {
    activeMenu.value = null;
    showConfirmation({
        title: 'Close Job Posting',
        subtitle: 'Stop accepting applications',
        message: 'Are you sure you want to close this job posting? It will no longer accept applications.',
        confirmText: 'Close',
        type: 'warning',
        action: async () => {
            try {
                await router.post(route('hrm.recruitment.job-postings.close', { jobPosting: id }), {}, {
                    onSuccess: () => { toast.success('Posting closed.'); loadPostings(); },
                    onError: () => toast.error('Failed to close posting.')
                });
            } catch (e) {
                console.error('Error closing posting:', e);
            }
        }
    });
};

const reopenPosting = async (id) => {
    activeMenu.value = null;
    showConfirmation({
        title: 'Reopen Job Posting',
        subtitle: 'Reactivate closed posting',
        message: 'Are you sure you want to reopen this job posting? It will start accepting applications again.',
        confirmText: 'Reopen',
        type: 'warning',
        action: async () => {
            try {
                await router.post(route('hrm.recruitment.job-postings.reopen', { jobPosting: id }), {}, {
                    onSuccess: () => { toast.success('Posting reopened.'); loadPostings(); },
                    onError: () => toast.error('Failed to reopen posting.')
                });
            } catch (e) {
                console.error('Error reopening posting:', e);
            }
        }
    });
};

const duplicatePosting = async (posting) => {
    activeMenu.value = null;
    showConfirmation({
        title: 'Duplicate Job Posting',
        subtitle: 'Create a copy',
        message: `Are you sure you want to duplicate "${postingDisplayName(posting)}"? A new draft posting will be created.`,
        confirmText: 'Duplicate',
        type: 'warning',
        action: async () => {
            try {
                await router.post(route('hrm.recruitment.job-postings.duplicate', { jobPosting: posting.id }), {}, {
                    onSuccess: () => { toast.success('Posting duplicated as draft.'); resetFilters(); loadPostings(); },
                    onError: () => toast.error('Failed to duplicate posting.')
                });
            } catch (e) {
                console.error('Error duplicating posting:', e);
            }
        }
    });
};

const archivePosting = async (id) => {
    activeMenu.value = null;
    showConfirmation({
        title: 'Archive Job Posting',
        subtitle: 'Hide from active list',
        message: 'Are you sure you want to archive this job posting? It will be hidden from active views but can be restored later.',
        confirmText: 'Archive',
        type: 'warning',
        action: async () => {
            try {
                await router.post(route('hrm.recruitment.job-postings.archive', { jobPosting: id }), {}, {
                    onSuccess: () => { toast.success('Posting archived.'); loadPostings(); },
                    onError: () => toast.error('Failed to archive posting.')
                });
            } catch (e) {
                console.error('Error archiving posting:', e);
            }
        }
    });
};

const reactivatePosting = async (id) => {
    activeMenu.value = null;
    showConfirmation({
        title: 'Restore Job Posting',
        subtitle: 'Reactivate archived posting',
        message: 'Are you sure you want to restore this job posting? It will become active again.',
        confirmText: 'Restore',
        type: 'warning',
        action: async () => {
            try {
                await router.post(route('hrm.recruitment.job-postings.reactivate', { jobPosting: id }), {}, {
                    onSuccess: () => { toast.success('Posting reactivated.'); loadPostings(); },
                    onError: () => toast.error('Failed to reactivate posting.')
                });
            } catch (e) {
                console.error('Error reactivating posting:', e);
            }
        }
    });
};

const viewPosting = (posting) => {
    activeMenu.value = null;
    openModal('view', posting);
};

const exportReport = () => {
    showConfirmation({
        title: 'Export Report',
        subtitle: 'Job Postings Report',
        message: 'Are you sure you want to export the job postings report?',
        confirmText: 'Export',
        type: 'warning',
        action: () => {
            window.location.href = route('hrm.recruitment.job-postings.export', {
                status: filters.status,
                department: filters.department,
                showArchived: showArchived.value ? 'true' : 'false',
            });
        }
    });
};
</script>

<style scoped>
.bg-grid-slate-100 { background-image: radial-gradient(circle, #cbd5e1 1px, transparent 1px); background-size: 24px 24px; }
.bg-clip-text { -webkit-background-clip: text; background-clip: text; }
.backdrop-blur-sm { backdrop-filter: blur(8px); }
</style>
