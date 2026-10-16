<template>
  <AuthenticatedLayout>
    <div class="relative">
      <!-- Subtle Background Pattern -->
      <div class="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,transparent,black,transparent)] pointer-events-none"></div>

      <!-- Professional Header -->
      <header class="mb-8 relative">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
          <div>
            <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3">
              <span class="hover:text-slate-700 cursor-pointer transition-colors">Dashboard</span>
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
              <span class="text-slate-800 font-semibold">HRM Operations</span>
            </nav>
            <h1 class="text-4xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">HRM Operations Command Center</h1>
            <p class="text-sm text-slate-500 mt-1.5">Comprehensive workforce analytics, real-time metrics, and operational controls</p>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <div class="relative group">
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
              <input
                type="date"
                class="pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm hover:shadow-md transition-all duration-200"
                v-model="selectedDate"
              />
            </div>

            <button
              @click="showConfigModal = true"
              class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-medium transition-all duration-200 shadow-sm hover:shadow-md hover:-translate-y-0.5"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
              Customize Workspace
            </button>
          </div>
        </div>
      </header>

      <!-- Priority Alerts - Floating Card -->
      <div v-if="hasAccess('dashboard.hr.alerts') && visibleWidgets.alerts" class="mb-8 relative">
        <div class="bg-gradient-to-br from-amber-50 via-orange-50 to-yellow-50 border border-amber-200/60 rounded-2xl p-5 shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-1 backdrop-blur-sm">
          <div class="flex items-start gap-4">
            <div class="flex-shrink-0 mt-0.5">
              <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center shadow-inner">
                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
              </div>
            </div>
            <div class="flex-1">
              <h3 class="text-sm font-bold text-amber-900 mb-3">Attention Required</h3>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                <Link
                  v-for="alert in attentionAlerts"
                  :key="alert.label"
                  :href="route(alert.route)"
                  class="flex items-center gap-2 bg-white/80 backdrop-blur-sm rounded-xl px-4 py-3 border border-amber-100 shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5"
                >
                  <span class="w-2.5 h-2.5 rounded-full" :class="alert.dot"></span>
                  <span class="text-amber-900"><strong>{{ alert.count }}</strong> {{ alert.label }}</span>
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- KPI Dashboard Cards - Floating Effect -->
      <section v-if="visibleWidgets.kpis" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
        <Link
          v-for="kpi in filteredKPIs"
          :key="kpi.label"
          :href="route(kpi.route)"
          class="group bg-white rounded-2xl border border-slate-200/60 p-5 transition-all duration-300 hover:shadow-2xl hover:-translate-y-1 hover:border-blue-200/60 shadow-md backdrop-blur-sm"
        >
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ kpi.label }}</span>
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center group-hover:from-blue-100 group-hover:to-blue-200 transition-all duration-300 shadow-inner">
              <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
              </svg>
            </div>
          </div>
          <div class="flex items-baseline gap-2">
            <span class="text-3xl font-bold bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">{{ kpi.value }}</span>
            <span v-if="kpi.trend"
                  class="inline-flex items-center gap-0.5 text-xs font-semibold px-2.5 py-1 rounded-full shadow-sm"
                  :class="kpi.trendUp ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'">
              <svg v-if="kpi.trendUp" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
              </svg>
              <svg v-else class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
              </svg>
              {{ kpi.trend }}
            </span>
          </div>
        </Link>
      </section>

      <!-- Main Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8 relative">

        <!-- Left Column -->
        <div class="lg:col-span-8 space-y-6">

          <!-- Workforce Overview - Floating Card -->
          <div v-if="hasAccess('dashboard.hr.workforce') && visibleWidgets.workforce"
               class="bg-white rounded-2xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 backdrop-blur-sm">
            <div class="p-6 border-b border-slate-100">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="text-xl font-bold text-slate-900">Workforce Overview</h3>
                  <p class="text-sm text-slate-500 mt-0.5">Department and employment-type distribution</p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-4 py-2 bg-gradient-to-r from-blue-50 to-blue-100 text-blue-700 text-xs font-semibold rounded-xl shadow-sm">
                  <span class="w-2 h-2 rounded-full bg-blue-500 shadow-lg shadow-blue-500/30 animate-pulse"></span>
                  Real-time Data
                </span>
              </div>
            </div>
            <div class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="space-y-4 p-4 bg-slate-50/80 rounded-xl border border-slate-100/60 backdrop-blur-sm hover:shadow-md transition-all duration-200">
                  <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">By Department</h4>
                  <div class="space-y-3">
                    <div v-for="(dept, idx) in (workforce.departments ?? [])" :key="dept.name" class="flex items-center justify-between text-sm">
                      <span class="text-slate-600 font-medium truncate">{{ dept.name }}</span>
                      <div class="flex items-center gap-3">
                        <div class="w-24 h-2.5 bg-slate-200 rounded-full overflow-hidden shadow-inner">
                          <div class="h-full rounded-full shadow-lg" :class="deptBarColor[idx]" :style="{ width: Math.min(dept.pct, 100) + '%' }"></div>
                        </div>
                        <span class="font-bold text-slate-800 w-8 text-right">{{ dept.count }}</span>
                      </div>
                    </div>
                    <div v-if="!(workforce.departments ?? []).length" class="text-sm text-slate-400 py-4 text-center">No employees yet</div>
                  </div>
                </div>

                <div class="space-y-4 p-4 bg-slate-50/80 rounded-xl border border-slate-100/60 backdrop-blur-sm hover:shadow-md transition-all duration-200">
                  <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Employment Type</h4>
                  <div class="space-y-3">
                    <div v-for="type in (workforce.employmentTypes ?? [])" :key="type.name" class="flex justify-between text-sm p-2 hover:bg-white rounded-lg transition-colors">
                      <span class="text-slate-600 font-medium">{{ type.name }}</span>
                      <span class="font-bold text-slate-800">{{ type.pct }}%</span>
                    </div>
                    <div v-if="!(workforce.employmentTypes ?? []).length" class="text-sm text-slate-400 py-4 text-center">No employment types</div>
                  </div>
                </div>

                <div class="p-4 bg-slate-50/80 rounded-xl border border-slate-100/60 backdrop-blur-sm hover:shadow-md transition-all duration-200">
                  <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">New Hires</h4>
                  <div class="space-y-3">
                    <div v-for="hire in (workforce.newHires ?? [])" :key="hire.name" class="flex flex-col gap-0.5">
                      <span class="text-sm font-bold text-slate-800">{{ hire.name }}</span>
                      <span class="text-xs text-slate-500">{{ hire.position }}<template v-if="hire.department"> · {{ hire.department }}</template></span>
                      <span class="text-[10px] text-slate-400 font-medium">{{ hire.date }}</span>
                    </div>
                    <div v-if="!(workforce.newHires ?? []).length" class="text-sm text-slate-400 py-4 text-center">No hires recorded</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Recruitment Funnel - Floating Card -->
          <div v-if="hasAccess('dashboard.hr.recruitment') && visibleWidgets.recruitment"
               class="bg-white rounded-2xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 backdrop-blur-sm">
            <div class="p-6 border-b border-slate-100">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="text-xl font-bold text-slate-900">Recruitment Pipeline</h3>
                  <p class="text-sm text-slate-500 mt-0.5">{{ recruitment.activeOpenings }} Active job openings · {{ recruitment.total }} total applications</p>
                </div>
                <Link :href="route('hrm.recruitment.job-postings.index')"
                      class="text-sm font-semibold text-blue-600 hover:text-blue-700 transition-colors bg-blue-50 hover:bg-blue-100 px-4 py-2 rounded-xl">
                  View All Positions →
                </Link>
              </div>
            </div>
            <div class="p-6">
              <div class="grid grid-cols-5 gap-4">
                <div v-for="(stage, idx) in (recruitment.pipeline ?? [])" :key="stage.label" class="text-center group cursor-pointer">
                  <div class="rounded-2xl p-4 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300" :class="pipelineCardClass[idx]">
                    <div class="text-3xl font-bold" :class="pipelineValueClass[idx]">{{ stage.count }}</div>
                    <div class="text-xs font-semibold mt-2" :class="pipelineLabelClass[idx]">{{ stage.label }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Workforce Status + Onboarding In Progress Grid -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div v-if="hasAccess('dashboard.hr.attendance') && visibleWidgets.attendance"
                 class="bg-white rounded-2xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 p-6 backdrop-blur-sm">
              <h3 class="text-lg font-bold text-slate-900 mb-4">Workforce Status</h3>
              <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-emerald-50/80 rounded-xl border border-emerald-100">
                  <span class="text-sm font-medium text-slate-700">Active Employees</span>
                  <span class="text-sm font-bold text-slate-800">{{ workforce.active }} / {{ workforce.total }}</span>
                </div>
                <div class="w-full bg-slate-200 h-3 rounded-full overflow-hidden shadow-inner">
                  <div class="bg-gradient-to-r from-emerald-500 to-emerald-400 h-full rounded-full shadow-lg shadow-emerald-500/20 transition-all duration-500"
                       :style="{ width: workforce.total > 0 ? Math.round((workforce.active / workforce.total) * 100) + '%' : '0%' }"></div>
                </div>
                <div class="flex justify-between text-xs">
                  <Link :href="route('hrm.workforce.employees.index')"
                        class="text-emerald-700 font-semibold bg-emerald-50 px-3 py-1.5 rounded-lg border border-emerald-100 hover:bg-emerald-100 transition-colors">
                    View All Employees →
                  </Link>
                </div>
              </div>
            </div>

            <div v-if="hasAccess('dashboard.hr.leaves') && visibleWidgets.leaves"
                 class="bg-white rounded-2xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 p-6 backdrop-blur-sm">
              <h3 class="text-lg font-bold text-slate-900 mb-4">Onboarding In Progress</h3>
              <div class="space-y-3">
                <div v-for="record in (onboarding.records ?? [])"
                     :key="record.name"
                     class="p-4 bg-slate-50/80 rounded-xl border border-slate-100 hover:shadow-md transition-all duration-200 hover:-translate-y-0.5">
                  <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-semibold text-slate-800">{{ record.name }}</span>
                    <span class="text-xs font-semibold px-3 py-1 rounded-full border shadow-sm" :class="onboardingBadgeClass(record.status)">{{ record.status }}</span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500">{{ record.template }}</span>
                  </div>
                </div>
                <div v-if="!(onboarding.records ?? []).length" class="text-sm text-slate-400 text-center py-4">No active onboarding records</div>
              </div>
            </div>
          </div>

          <!-- Onboarding Overview - Floating Dark Card -->
          <div v-if="hasAccess('dashboard.hr.payroll') && visibleWidgets.payroll"
               class="relative overflow-hidden rounded-2xl shadow-2xl hover:shadow-3xl transition-all duration-300 hover:-translate-y-1 group">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-800 via-slate-900 to-blue-900"></div>
            <div class="absolute top-0 right-0 w-96 h-96 bg-blue-500/20 rounded-full -translate-y-1/2 translate-x-1/2 blur-3xl group-hover:bg-blue-500/30 transition-all duration-500"></div>
            <div class="absolute bottom-0 left-0 w-64 h-64 bg-emerald-500/10 rounded-full translate-y-1/2 -translate-x-1/2 blur-3xl"></div>
            <div class="relative p-6 text-white">
              <div class="flex items-center justify-between mb-6">
                <div>
                  <h3 class="text-xl font-bold">Onboarding Overview</h3>
                  <p class="text-sm text-slate-300 mt-0.5">Live onboarding pipeline</p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-500/20 text-emerald-300 text-xs font-semibold rounded-xl border border-emerald-500/30 backdrop-blur-sm shadow-lg">
                  <span class="w-2 h-2 rounded-full bg-emerald-400 shadow-lg shadow-emerald-400/50 animate-pulse"></span>
                  {{ onboarding.inProgress + onboarding.completed }} Total
                </span>
              </div>
              <div class="grid grid-cols-2 gap-6 mb-6">
                <div class="bg-white/5 backdrop-blur-sm rounded-xl p-4 border border-white/10">
                  <span class="text-xs text-slate-300 uppercase tracking-wider font-semibold">In Progress</span>
                  <div class="text-3xl font-bold mt-2 bg-gradient-to-r from-white to-slate-200 bg-clip-text text-transparent">{{ onboarding.inProgress }}</div>
                </div>
                <div class="bg-white/5 backdrop-blur-sm rounded-xl p-4 border border-white/10">
                  <span class="text-xs text-slate-300 uppercase tracking-wider font-semibold">Completed</span>
                  <div class="text-3xl font-bold mt-2 bg-gradient-to-r from-white to-slate-200 bg-clip-text text-transparent">{{ onboarding.completed }}</div>
                </div>
              </div>
              <div class="flex flex-wrap items-center justify-between gap-3">
                <span class="text-xs text-slate-300 font-medium">{{ onboarding.activeTemplates }} active templates</span>
                <Link :href="route('hrm.onboarding.status.index')"
                      class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white text-sm font-bold rounded-xl transition-all duration-200 shadow-xl hover:shadow-2xl hover:shadow-blue-500/25 transform hover:-translate-y-0.5">
                  Open Onboarding →
                </Link>
              </div>
            </div>
          </div>

          <!-- Recruitment Actions + Active Postings - Floating Cards -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div v-if="hasAccess('dashboard.hr.performance') && visibleWidgets.performance"
                 class="bg-white rounded-2xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 p-6 backdrop-blur-sm">
              <h3 class="text-lg font-bold text-slate-900 mb-4">Recruitment Actions</h3>
              <div class="space-y-3">
                <Link :href="route('hrm.recruitment.applications.index')"
                      class="flex items-center justify-between p-4 bg-gradient-to-r from-blue-50 to-blue-100/50 rounded-xl border border-blue-100 hover:shadow-md transition-all duration-200 hover:-translate-x-1">
                  <div>
                    <p class="text-sm font-semibold text-slate-800">{{ recruitment.pendingScreening }} Applications Pending Review</p>
                    <p class="text-xs text-slate-500 mt-1">Waiting for screening</p>
                  </div>
                  <span class="text-sm font-semibold text-blue-600 hover:text-blue-700 bg-white px-3 py-1.5 rounded-lg shadow-sm hover:shadow-md transition-all duration-200">View →</span>
                </Link>
                <Link :href="route('hrm.recruitment.interviews.list')"
                      class="flex items-center justify-between p-4 bg-gradient-to-r from-amber-50 to-amber-100/50 rounded-xl border border-amber-100 hover:shadow-md transition-all duration-200 hover:-translate-x-1">
                  <div>
                    <p class="text-sm font-semibold text-slate-800">{{ interviews.pendingCount }} Scheduled Interviews</p>
                    <p class="text-xs text-slate-500 mt-1">Awaiting conduction</p>
                  </div>
                  <span class="text-sm font-semibold text-amber-600 hover:text-amber-700 bg-white px-3 py-1.5 rounded-lg shadow-sm hover:shadow-md transition-all duration-200">Review →</span>
                </Link>
              </div>
            </div>

            <div v-if="hasAccess('dashboard.hr.training') && visibleWidgets.training"
                 class="bg-white rounded-2xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 p-6 backdrop-blur-sm">
              <h3 class="text-lg font-bold text-slate-900 mb-4">Active Job Postings</h3>
              <div class="space-y-3">
                <div v-for="posting in (recruitment.publishedPostings ?? [])"
                     :key="posting.id"
                     class="flex items-center justify-between text-sm p-3 bg-slate-50 rounded-xl hover:bg-white hover:shadow-md transition-all duration-200">
                  <span class="text-slate-600 font-medium truncate">{{ posting.title }}</span>
                  <span class="font-semibold text-emerald-600 bg-emerald-50 px-3 py-1 rounded-lg border border-emerald-200">{{ posting.department }}</span>
                </div>
                <div v-if="!(recruitment.publishedPostings ?? []).length" class="text-sm text-slate-400 text-center py-4">No published job postings</div>
              </div>
            </div>
          </div>

          <!-- Recent Hires - Floating Table -->
          <div v-if="hasAccess('dashboard.hr.movement') && visibleWidgets.movement"
               class="bg-white rounded-2xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 backdrop-blur-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex items-center justify-between">
              <h3 class="text-xl font-bold text-slate-900">Recent Hires</h3>
              <Link :href="route('hrm.workforce.employees.index')" class="text-sm font-semibold text-blue-600 hover:text-blue-700 transition-colors">View All Employees →</Link>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead>
                  <tr class="border-b border-slate-100 bg-slate-50/50">
                    <th class="text-left py-4 px-6 text-xs font-bold text-slate-500 uppercase tracking-wider">Employee</th>
                    <th class="text-left py-4 px-6 text-xs font-bold text-slate-500 uppercase tracking-wider">Action</th>
                    <th class="text-left py-4 px-6 text-xs font-bold text-slate-500 uppercase tracking-wider">Details</th>
                    <th class="text-left py-4 px-6 text-xs font-bold text-slate-500 uppercase tracking-wider">Date</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="hire in (workforce.newHires ?? [])"
                      :key="hire.name"
                      class="hover:bg-blue-50/50 transition-all duration-200 hover:shadow-md group cursor-pointer">
                    <td class="py-4 px-6 text-sm font-bold text-slate-900">{{ hire.name }}</td>
                    <td class="py-4 px-6">
                      <span class="inline-flex px-3 py-1 text-xs font-bold bg-blue-50 text-blue-700 rounded-full border border-blue-200 shadow-sm group-hover:shadow-md transition-all duration-200">
                        New Hire
                      </span>
                    </td>
                    <td class="py-4 px-6 text-sm text-slate-600">{{ hire.position }}</td>
                    <td class="py-4 px-6 text-sm text-slate-500 font-medium">{{ hire.date }}</td>
                  </tr>
                  <tr v-if="!(workforce.newHires ?? []).length">
                    <td colspan="4" class="py-8 px-6 text-sm text-slate-400 text-center">No hires recorded yet</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Right Column -->
        <div class="lg:col-span-4 space-y-6">

          <!-- Quick Actions - Floating Card -->
          <div class="bg-white rounded-2xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 p-6 backdrop-blur-sm">
            <h3 class="text-lg font-bold text-slate-900 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-2 gap-3">
              <Link :href="route(quickActions.employees ?? 'hrm.dashboard')"
                    class="p-4 bg-slate-50 hover:bg-gradient-to-br hover:from-blue-50 hover:to-blue-100 text-left rounded-xl text-sm font-semibold text-slate-700 hover:text-blue-700 transition-all duration-300 border border-slate-200 hover:border-blue-200 shadow-sm hover:shadow-lg hover:-translate-y-0.5 group">
                <svg class="w-6 h-6 mb-2 text-slate-400 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Manage Employees
              </Link>
              <Link :href="route(quickActions.job_posting ?? 'hrm.dashboard')"
                    class="p-4 bg-slate-50 hover:bg-gradient-to-br hover:from-blue-50 hover:to-blue-100 text-left rounded-xl text-sm font-semibold text-slate-700 hover:text-blue-700 transition-all duration-300 border border-slate-200 hover:border-blue-200 shadow-sm hover:shadow-lg hover:-translate-y-0.5 group">
                <svg class="w-6 h-6 mb-2 text-slate-400 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                New Job Opening
              </Link>
              <Link :href="route(quickActions.interview ?? 'hrm.dashboard')"
                    class="p-4 bg-slate-50 hover:bg-gradient-to-br hover:from-blue-50 hover:to-blue-100 text-left rounded-xl text-sm font-semibold text-slate-700 hover:text-blue-700 transition-all duration-300 border border-slate-200 hover:border-blue-200 shadow-sm hover:shadow-lg hover:-translate-y-0.5 group">
                <svg class="w-6 h-6 mb-2 text-slate-400 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Schedule Interview
              </Link>
              <Link :href="route(quickActions.onboarding ?? 'hrm.dashboard')"
                    class="p-4 bg-slate-50 hover:bg-gradient-to-br hover:from-blue-50 hover:to-blue-100 text-left rounded-xl text-sm font-semibold text-slate-700 hover:text-blue-700 transition-all duration-300 border border-slate-200 hover:border-blue-200 shadow-sm hover:shadow-lg hover:-translate-y-0.5 group">
                <svg class="w-6 h-6 mb-2 text-slate-400 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Onboarding
              </Link>
              <a :href="route('hrm.recruitment.applications.export')"
                    class="col-span-2 p-4 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-sm font-bold transition-all duration-300 shadow-lg hover:shadow-2xl hover:shadow-blue-500/25 hover:-translate-y-0.5 group">
                <svg class="w-6 h-6 mb-2 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Export Applications
              </a>
            </div>
          </div>

          <!-- Priority Actions - Floating Card -->
          <div v-if="visibleWidgets.tasks"
               class="bg-white rounded-2xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 p-6 backdrop-blur-sm">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-bold text-slate-900">Priority Actions</h3>
              <span class="inline-flex items-center px-3 py-1.5 bg-gradient-to-r from-amber-50 to-amber-100 text-amber-700 text-xs font-bold rounded-full border border-amber-200 shadow-sm">
                {{ pendingActionCount }} Pending
              </span>
            </div>
            <div class="space-y-3">
              <Link v-for="task in priorityTasks" :key="task.title" :href="route(task.route)"
                    class="flex items-start gap-3 p-4 hover:bg-gradient-to-r hover:from-blue-50 hover:to-transparent rounded-xl transition-all duration-200 group cursor-pointer hover:shadow-md hover:-translate-x-1">
                <span class="mt-0.5 w-5 h-5 rounded-lg bg-amber-100 border border-amber-200 flex items-center justify-center text-[10px] font-bold text-amber-700">{{ task.count }}</span>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-semibold text-slate-800 group-hover:text-blue-700 transition-colors">{{ task.title }}</p>
                  <p class="text-xs text-slate-500 mt-1 font-medium">{{ task.due }}</p>
                </div>
              </Link>
            </div>
          </div>

          <!-- Upcoming Interviews - Floating Card -->
          <div v-if="visibleWidgets.calendar"
               class="bg-white rounded-2xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 p-6 backdrop-blur-sm">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-bold text-slate-900">Upcoming Interviews</h3>
              <Link :href="route('hrm.recruitment.interviews.list')" class="text-xs font-semibold text-blue-600 hover:text-blue-700 transition-colors">View All →</Link>
            </div>
            <div class="space-y-3">
              <div v-for="event in (interviews.upcoming ?? [])"
                   :key="event.name"
                   class="flex items-center gap-4 p-4 bg-gradient-to-r from-blue-50 to-blue-100/50 rounded-xl border border-blue-100 hover:shadow-lg transition-all duration-200 hover:-translate-x-1 group cursor-pointer">
                <div class="w-3 h-3 rounded-full bg-blue-500 shadow-lg shadow-blue-500/30 flex-shrink-0 group-hover:scale-125 transition-transform"></div>
                <div class="flex-1">
                  <p class="text-sm font-semibold text-slate-800">{{ event.name }}<template v-if="event.position"> · {{ event.position }}</template></p>
                  <p class="text-xs text-slate-500 mt-1 font-medium">{{ event.details }}</p>
                </div>
              </div>
              <div v-if="!(interviews.upcoming ?? []).length" class="text-sm text-slate-400 text-center py-4">No upcoming interviews</div>
            </div>
          </div>

          <!-- Activity Feed - Floating Card -->
          <div v-if="visibleWidgets.activities"
               class="bg-white rounded-2xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 p-6 backdrop-blur-sm">
            <h3 class="text-lg font-bold text-slate-900 mb-4">Recent Applications</h3>
            <div class="space-y-4">
              <div v-for="(act, index) in (recruitment.recentApplications ?? [])" :key="act.name" class="flex gap-3 group cursor-pointer hover:bg-slate-50/80 p-2 rounded-xl transition-all duration-200">
                <div class="flex flex-col items-center">
                  <div class="w-3 h-3 rounded-full bg-gradient-to-r from-blue-400 to-blue-500 shadow-lg shadow-blue-400/30 mt-1.5 group-hover:scale-125 transition-transform"></div>
                  <div v-if="index < (recruitment.recentApplications ?? []).length - 1" class="w-0.5 h-full bg-gradient-to-b from-blue-200 to-transparent"></div>
                </div>
                <div class="flex-1 pb-4">
                  <p class="text-xs font-medium text-slate-400 mb-1">{{ act.status }}</p>
                  <p class="text-sm text-slate-700">
                    <span class="font-bold text-slate-900">{{ act.name }}</span> application recorded
                  </p>
                </div>
              </div>
              <div v-if="!(recruitment.recentApplications ?? []).length" class="text-sm text-slate-400 text-center py-4">No applications yet</div>
            </div>
          </div>

          <!-- Quick Reports - Floating Card -->
          <div class="bg-white rounded-2xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 p-6 backdrop-blur-sm">
            <h3 class="text-lg font-bold text-slate-900 mb-3">Export Reports</h3>
            <div class="space-y-2">
              <Link :href="route('hrm.recruitment.applications.export')" class="flex items-center justify-between p-3.5 hover:bg-gradient-to-r hover:from-blue-50 hover:to-transparent rounded-xl transition-all duration-200 group cursor-pointer hover:shadow-md hover:-translate-x-1">
                <span class="text-sm font-medium text-slate-600 group-hover:text-blue-700 transition-colors">Applications Report (.xlsx)</span>
                <svg class="w-5 h-5 text-slate-400 group-hover:text-blue-500 group-hover:translate-x-1 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
              </Link>
              <Link :href="route('hrm.recruitment.job-postings.export')" class="flex items-center justify-between p-3.5 hover:bg-gradient-to-r hover:from-blue-50 hover:to-transparent rounded-xl transition-all duration-200 group cursor-pointer hover:shadow-md hover:-translate-x-1">
                <span class="text-sm font-medium text-slate-600 group-hover:text-blue-700 transition-colors">Job Postings Ledger</span>
                <svg class="w-5 h-5 text-slate-400 group-hover:text-blue-500 group-hover:translate-x-1 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
              </Link>
              <Link :href="route('hrm.workforce.departments.export')" class="flex items-center justify-between p-3.5 hover:bg-gradient-to-r hover:from-blue-50 hover:to-transparent rounded-xl transition-all duration-200 group cursor-pointer hover:shadow-md hover:-translate-x-1">
                <span class="text-sm font-medium text-slate-600 group-hover:text-blue-700 transition-colors">Department Breakdown</span>
                <svg class="w-5 h-5 text-slate-400 group-hover:text-blue-500 group-hover:translate-x-1 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
              </Link>
            </div>
          </div>
        </div>
      </div>

      <!-- Configuration Modal -->
      <Teleport to="body">
        <Transition name="modal">
          <div v-if="showConfigModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-md" @click="showConfigModal = false"></div>
            <div class="relative bg-white rounded-3xl shadow-2xl max-w-md w-full p-8 border border-slate-200/60 transform transition-all duration-300">
              <div class="flex items-center justify-between mb-6">
                <div>
                  <h3 class="text-xl font-bold text-slate-900">Customize Workspace</h3>
                  <p class="text-sm text-slate-500 mt-1">Toggle widgets to personalize your dashboard</p>
                </div>
                <button @click="showConfigModal = false"
                        class="p-2 hover:bg-slate-100 rounded-xl transition-all duration-200 hover:rotate-90 transform">
                  <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>

              <div class="space-y-3 max-h-64 overflow-y-auto custom-scrollbar">
                <label v-for="(val, key) in visibleWidgets" :key="key"
                      class="flex items-center gap-4 p-4 hover:bg-gradient-to-r hover:from-blue-50 hover:to-transparent rounded-xl cursor-pointer transition-all duration-200 group border border-transparent hover:border-blue-100">
                  <input type="checkbox" v-model="visibleWidgets[key]"
                         class="w-5 h-5 rounded-lg border-2 border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer" />
                  <span class="text-sm font-semibold text-slate-700 capitalize group-hover:text-blue-700 transition-colors">{{ key.replace(/([A-Z])/g, ' $1') }}</span>
                </label>
              </div>

              <div class="mt-6 flex justify-end gap-3">
                <button @click="showConfigModal = false"
                        class="px-5 py-2.5 text-sm font-semibold text-slate-700 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all duration-200">
                  Cancel
                </button>
                <button @click="showConfigModal = false"
                        class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white text-sm font-bold rounded-xl transition-all duration-200 shadow-lg hover:shadow-xl hover:shadow-blue-500/25">
                  Apply Changes
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </Teleport>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from "vue";
import { Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    kpis: { type: Array, default: () => [] },
    workforce: { type: Object, default: () => ({ total: 0, active: 0, departments: [], employmentTypes: [], newHires: [] }) },
    recruitment: { type: Object, default: () => ({ pendingScreening: 0, activeOpenings: 0, total: 0, pipeline: [], publishedPostings: [], recentApplications: [] }) },
    interviews: { type: Object, default: () => ({ pendingCount: 0, upcoming: [] }) },
    onboarding: { type: Object, default: () => ({ inProgress: 0, completed: 0, activeTemplates: 0, records: [] }) },
    quickActions: { type: Object, default: () => ({}) },
});

const userRole = ref("hr");
const selectedDate = ref(new Date().toISOString().slice(0, 10));
const showConfigModal = ref(false);

const rolePermissions = {
    hr: [
        "dashboard.hr.kpis",
        "dashboard.hr.alerts",
        "dashboard.hr.workforce",
        "dashboard.hr.recruitment",
        "dashboard.hr.attendance",
        "dashboard.hr.leaves",
        "dashboard.hr.payroll",
        "dashboard.hr.performance",
        "dashboard.hr.training",
        "dashboard.hr.movement",
    ],
};

const hasAccess = (permission) => {
    const userPerms = rolePermissions[userRole.value] || [];
    return userPerms.includes(permission);
};

const visibleWidgets = ref({
    kpis: true,
    alerts: true,
    workforce: true,
    recruitment: true,
    attendance: true,
    leaves: true,
    payroll: true,
    performance: true,
    training: true,
    movement: true,
    calendar: true,
    tasks: true,
    activities: true,
});

const filteredKPIs = computed(() => {
    return (props.kpis ?? []).filter((kpi) => hasAccess(kpi.perm));
});

const deptBarColor = [
    'bg-gradient-to-r from-blue-500 to-blue-400 shadow-blue-500/20',
    'bg-gradient-to-r from-emerald-500 to-emerald-400 shadow-emerald-500/20',
    'bg-gradient-to-r from-purple-500 to-purple-400 shadow-purple-500/20',
    'bg-gradient-to-r from-amber-500 to-orange-400 shadow-amber-500/20',
    'bg-gradient-to-r from-rose-500 to-pink-400 shadow-rose-500/20',
    'bg-gradient-to-r from-teal-500 to-cyan-400 shadow-teal-500/20',
];

const pipelineCardClass = [
    'bg-slate-50/80 border border-slate-100',
    'bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-100',
    'bg-gradient-to-br from-blue-100 to-blue-200 border border-blue-200',
    'bg-gradient-to-br from-indigo-100 to-indigo-200 border border-indigo-200',
    'bg-gradient-to-br from-emerald-50 to-emerald-100 border border-emerald-200',
];
const pipelineValueClass = [
    'bg-gradient-to-r from-slate-800 to-slate-600 bg-clip-text text-transparent',
    'text-blue-700',
    'text-blue-800',
    'text-indigo-800',
    'text-emerald-700',
];
const pipelineLabelClass = [
    'text-slate-500',
    'text-blue-600',
    'text-blue-700',
    'text-indigo-700',
    'text-emerald-600',
];

const attentionAlerts = computed(() => [
    {
        count: props.interviews.pendingCount || 0,
        label: "Interviews scheduled",
        route: 'hrm.recruitment.interviews.list',
        dot: 'bg-blue-500 shadow-lg shadow-blue-500/30',
    },
    {
        count: props.recruitment.pendingScreening || 0,
        label: "Applications pending review",
        route: 'hrm.recruitment.applications.index',
        dot: 'bg-orange-500 shadow-lg shadow-orange-500/30',
    },
    {
        count: props.onboarding.inProgress || 0,
        label: "Onboarding in progress",
        route: 'hrm.onboarding.status.index',
        dot: 'bg-amber-500 shadow-lg shadow-amber-500/30',
    },
]);

const priorityTasks = computed(() => [
    {
        title: "Applications pending review",
        due: "Review screening queue",
        count: props.recruitment.pendingScreening || 0,
        route: 'hrm.recruitment.applications.index',
    },
    {
        title: "Upcoming scheduled interviews",
        due: "Confirm availability",
        count: props.interviews.pendingCount || 0,
        route: 'hrm.recruitment.interviews.list',
    },
    {
        title: "Active onboarding records",
        due: "Monitor completion",
        count: props.onboarding.inProgress || 0,
        route: 'hrm.onboarding.status.index',
    },
]);

const pendingActionCount = computed(() =>
    (props.recruitment.pendingScreening || 0) +
    (props.interviews.pendingCount || 0) +
    (props.onboarding.inProgress || 0),
);

const onboardingBadgeClass = (status) => {
    return {
        'In Progress': 'bg-blue-50 text-blue-700 border-blue-200',
        'Pending Requirements': 'bg-amber-50 text-amber-700 border-amber-200',
        'Ready to Hire': 'bg-violet-50 text-violet-700 border-violet-200',
        'Completed': 'bg-emerald-50 text-emerald-700 border-emerald-200',
    }[status] || 'bg-slate-50 text-slate-500 border-slate-200';
};
</script>

<style scoped>
/* Floating Animation Effects */
@keyframes float {
  0%, 100% {
    transform: translateY(0px);
  }
  50% {
    transform: translateY(-10px);
  }
}

/* Background Grid Pattern */
.bg-grid-slate-100 {
  background-image: radial-gradient(circle, #cbd5e1 1px, transparent 1px);
  background-size: 24px 24px;
}

/* Custom Scrollbar */
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #94a3b8;
  border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #64748b;
}

/* Modal Transitions */
.modal-enter-active,
.modal-leave-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.modal-enter-from {
  opacity: 0;
  transform: scale(0.95);
}

.modal-leave-to {
  opacity: 0;
  transform: scale(0.95);
}

/* Glass Morphism Effect */
.backdrop-blur-sm {
  backdrop-filter: blur(8px);
}

/* Shadow Elevation System */
.shadow-3xl {
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

/* Hover Float Effect */
.hover\:shadow-2xl:hover {
  transform: translateY(-4px);
}

/* Gradient Text */
.bg-clip-text {
  -webkit-background-clip: text;
  background-clip: text;
}
</style>