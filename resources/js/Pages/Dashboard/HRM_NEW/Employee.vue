<template>
  <AuthenticatedLayout>
    <div class="flex min-h-screen bg-gradient-to-br from-slate-100 via-slate-50 to-blue-50">

      <div class="flex-1 p-8 text-slate-900 font-sans min-w-0 relative">
        
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
                <span class="text-slate-800 font-semibold">Central Registry</span>
              </nav>
              <h1 class="text-4xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">Employee Master Records</h1>
              <p class="text-sm text-slate-500 mt-1.5">Manage directory profiles, organization context, payroll variables, and documents.</p>
            </div>

            <!-- Quick Directory Actions -->
            <div class="flex flex-wrap items-center gap-3">
              <button 
                @click="createNewEmployee"
                class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-lg hover:shadow-xl hover:shadow-blue-500/25 hover:-translate-y-0.5"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Create New Profile
              </button>
              <button @click="triggerAction('export')" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-medium transition-all duration-200 shadow-sm hover:shadow-md hover:-translate-y-0.5">
                <Upload class="w-4 h-4" /> Export CSV
              </button>
              <button @click="triggerAction('print')" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-medium transition-all duration-200 shadow-sm hover:shadow-md hover:-translate-y-0.5">
                <Printer class="w-4 h-4" /> Print Profile
              </button>
            </div>
          </div>
        </header>

        <!-- Generated login pair (one-time, after creation or resend) -->
        <div v-if="hireCreds" class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm relative">
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2">
              <KeyRound class="w-4 h-4 text-emerald-600" />
              <h3 class="text-sm font-black text-emerald-800">Employee credentials — copy now, shown only once</h3>
            </div>
            <button @click="hireCreds = null" title="Dismiss" class="p-1 rounded-lg text-emerald-500 hover:bg-emerald-100 transition">
              <X class="w-4 h-4" />
            </button>
          </div>
          <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
            <button @click="copyCred('email', hireCreds.email)" title="Click to copy" class="flex items-center justify-between gap-2 px-3 py-2 bg-white border border-emerald-200 rounded-xl font-mono hover:border-emerald-400 transition text-left">
              <span class="truncate">{{ hireCreds.email }} <span class="font-sans text-[10px] text-slate-400">({{ hireCreds.employee_id }})</span></span>
              <Copy class="w-3.5 h-3.5 shrink-0 text-slate-400" />
            </button>
            <button v-if="hireCreds.password" @click="copyCred('password', hireCreds.password)" title="Click to copy" class="flex items-center justify-between gap-2 px-3 py-2 bg-white border border-emerald-200 rounded-xl font-mono hover:border-emerald-400 transition text-left">
              <span class="truncate">{{ hireCreds.password }}</span>
              <Copy class="w-3.5 h-3.5 shrink-0 text-slate-400" />
            </button>
            <span v-else class="px-3 py-2 text-[11px] text-slate-500">Existing account — password unchanged.</span>
          </div>
          <p class="mt-2 text-[11px] leading-relaxed" :class="hireCreds.mailed ? 'text-emerald-700' : 'text-amber-700'">
            <template v-if="hireCreds.mailed">Emailed to {{ hireCreds.mailed_to }} — use the copy above if the mail hasn't arrived.</template>
            <template v-else>Email NOT sent{{ hireCreds.mail_error ? (': ' + hireCreds.mail_error) : '' }} — hand these credentials over manually.</template>
            <template v-if="copiedField"> · Copied {{ copiedField }}!</template>
          </p>
        </div>

        <!-- COMPANY DIRECTORY BROWSER (live DB, all MontiTextile employees) -->
        <div v-if="!profileOnly" class="bg-white rounded-2xl border border-slate-200/60 shadow-lg backdrop-blur-sm p-5 mb-6 relative">
          <div class="flex flex-col xl:flex-row xl:items-center gap-4">
            <div class="flex items-center gap-3 text-xs font-bold text-slate-700 whitespace-nowrap">
              <span class="inline-flex items-center px-3 py-1.5 bg-blue-50 text-blue-700 rounded-xl border border-blue-200">{{ stats?.total ?? employeeList.length }} Total</span>
              <span class="inline-flex items-center px-3 py-1.5 bg-emerald-50 text-emerald-700 rounded-xl border border-emerald-200">{{ stats?.active ?? 0 }} Active</span>
              <span class="inline-flex items-center px-3 py-1.5 bg-slate-100 text-slate-600 rounded-xl border border-slate-200">{{ stats?.inactive ?? 0 }} Inactive</span>
            </div>
            <div class="flex-1 relative">
              <input v-model="directorySearch" type="text" placeholder="Search name, email, or employee ID across the whole company..." class="w-full pl-4 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm" />
            </div>
            <select v-model="directoryDepartment" @change="applyDirectoryFilter" class="px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs cursor-pointer">
              <option value="">All Departments</option>
              <option v-for="d in departmentOptions" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
            <select v-model="directoryStatus" @change="applyDirectoryFilter" class="px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs cursor-pointer">
              <option value="">Any Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div class="mt-4 max-h-44 overflow-y-auto divide-y divide-slate-100 border border-slate-100 rounded-xl">
            <button v-for="rec in employeeList" :key="rec.id" @click="selectEmployee(rec.id)"
              :class="['w-full flex items-center gap-3 px-4 py-2.5 text-left text-xs transition-all', selectedId === rec.id ? 'bg-gradient-to-r from-blue-50 to-blue-100/50' : 'hover:bg-slate-50']">
              <span class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center text-[10px] font-bold text-blue-700 shrink-0">{{ initials(rec.firstName + ' ' + rec.lastName) }}</span>
              <span class="flex-1 min-w-0"><span class="block font-bold text-slate-800 truncate">{{ rec.firstName }} {{ rec.lastName }}</span>
              <span class="block text-[10px] text-slate-400 truncate">{{ rec.employeeId }} • {{ rec.department }} • {{ rec.role }}</span></span>
              <span :class="['px-2 py-0.5 rounded-full text-[10px] font-semibold shrink-0', rec.employeeStatus === 'Active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200']">{{ rec.employeeStatus }}</span>
            </button>
            <div v-if="employeeList.length === 0" class="py-8 text-center text-xs text-slate-400">No employees found in the database.</div>
          </div>
          <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
            <span>Showing {{ pageInfo.from }}–{{ pageInfo.to }} of {{ pageInfo.total }} employees</span>
            <div class="flex items-center gap-2">
              <button @click="goDirectoryPage(pageInfo.current - 1)" :disabled="pageInfo.current <= 1" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-bold hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Previous</button>
              <span class="font-bold text-slate-700">Page {{ pageInfo.current }} of {{ pageInfo.last }}</span>
              <button @click="goDirectoryPage(pageInfo.current + 1)" :disabled="pageInfo.current >= pageInfo.last" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-bold hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
            </div>
          </div>
        </div>

        <!-- Back to directory (profile page only) -->
        <div v-if="profileOnly" class="mb-4 relative">
          <button @click="router.get(route('hrm.workforce.employees.index'))" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold transition-all shadow-sm">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back to Directory
          </button>
        </div>

        <!-- CORE LAYOUT SPLIT (profile shows only after a row is clicked) -->
        <div v-if="selectedId !== null" id="employee-profile" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
          
          <!-- LEFT SIDE PANEL (col-span-4) -->
          <aside class="lg:col-span-4 space-y-6">
            
            <!-- Employee Selection & Status Card - Floating -->
            <div class="bg-white rounded-2xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 backdrop-blur-sm p-6 text-center">
              <div class="relative w-24 h-24 mx-auto mb-4">
                <div class="w-24 h-24 rounded-full bg-gradient-to-tr from-indigo-500 to-emerald-400 p-0.5 shadow-lg shadow-indigo-500/20">
                  <img 
                    :src="employee.profilePicture || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=200'" 
                    class="rounded-full w-full h-full object-cover" 
                    alt="Profile"
                  />
                </div>
                <span class="absolute bottom-1 right-1 w-4 h-4 bg-emerald-500 border-2 border-white rounded-full shadow-lg shadow-emerald-500/30" title="Active"></span>
              </div>
              
              <h2 class="text-lg font-bold text-slate-900">{{ employee.firstName }} {{ employee.lastName }}</h2>
              <p class="text-xs text-slate-400 font-mono mb-2">{{ employee.employeeId }}</p>
              
              <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-sm mb-4">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse shadow-lg shadow-emerald-500/30"></span>
                {{ employee.employeeStatus }} • {{ employee.employmentType }}
              </div>

              <div class="border-t border-slate-100 pt-4 text-left space-y-2 text-xs">
                <div class="flex justify-between"><span class="text-slate-400">Position:</span><span class="font-semibold text-slate-800">{{ employee.position }}</span></div>
                <div class="flex justify-between"><span class="text-slate-400">Department:</span><span class="font-semibold text-slate-800">{{ employee.department }}</span></div>
                <div class="flex justify-between"><span class="text-slate-400">Reports To:</span><span class="font-semibold text-slate-800">{{ employee.reportsTo }}</span></div>
              </div>
            </div>

            <!-- Profile Navigation Tabs - Floating -->
            <div class="bg-white rounded-2xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 backdrop-blur-sm overflow-hidden">
              <div class="p-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-transparent">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Record Chapters</h3>
              </div>
              <nav class="flex flex-col divide-y divide-slate-100">
                <button 
                  v-for="tab in tabs" 
                  :key="tab.id"
                  @click="activeTab = tab.id"
                  :class="[
                    activeTab === tab.id ? 'bg-gradient-to-r from-blue-50 to-blue-100/50 text-blue-700 font-semibold border-l-2 border-blue-500' : 'text-slate-600 hover:bg-slate-50',
                    'px-4 py-3 text-left text-xs transition-all duration-200 flex items-center justify-between'
                  ]"
                >
                  <span class="flex items-center gap-2"><component :is="tab.icon" class="w-4 h-4" />{{ tab.label }}</span>
                  <span v-if="activeTab === tab.id" class="w-1.5 h-1.5 rounded-full bg-blue-500 shadow-lg shadow-blue-500/30"></span>
                </button>
              </nav>
            </div>

            <!-- Operational Controls - Floating -->
            <div class="bg-white rounded-2xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 backdrop-blur-sm p-5">
              <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">Operational Controls</h3>
              <div class="grid grid-cols-2 gap-2">
                <button @click="triggerAction('promote')" class="p-3 bg-gradient-to-r from-slate-50 to-slate-100/50 hover:from-blue-50 hover:to-blue-100 border border-slate-200 hover:border-blue-200 rounded-xl text-left text-xs font-semibold text-slate-700 hover:text-blue-700 transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex items-center gap-1.5">
                  <TrendingUp class="w-4 h-4" /> Promote
                </button>
                <button @click="triggerAction('demote')" class="p-3 bg-gradient-to-r from-slate-50 to-slate-100/50 hover:from-slate-50 hover:to-slate-100 border border-slate-200 rounded-xl text-left text-xs font-semibold text-slate-700 transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex items-center gap-1.5">
                  <TrendingDown class="w-4 h-4" /> Demote
                </button>
                <button @click="triggerAction('transfer')" class="p-3 bg-gradient-to-r from-slate-50 to-slate-100/50 hover:from-slate-50 hover:to-slate-100 border border-slate-200 rounded-xl text-left text-xs font-semibold text-slate-700 transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex items-center gap-1.5">
                  <RefreshCw class="w-4 h-4" /> Transfer
                </button>
                <button @click="triggerAction('duplicate')" class="p-3 bg-gradient-to-r from-slate-50 to-slate-100/50 hover:from-slate-50 hover:to-slate-100 border border-slate-200 rounded-xl text-left text-xs font-semibold text-slate-700 transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex items-center gap-1.5">
                  <ClipboardList class="w-4 h-4" /> Duplicate
                </button>
                <button @click="triggerAction('archive')" class="p-3 bg-gradient-to-r from-amber-50 to-amber-100/50 hover:from-amber-100 hover:to-amber-200 border border-amber-200 text-amber-800 rounded-xl text-left text-xs font-semibold transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex items-center gap-1.5">
                  <Archive class="w-4 h-4" /> Archive
                </button>
                <button @click="triggerAction('terminate')" class="p-3 bg-gradient-to-r from-rose-50 to-rose-100/50 hover:from-rose-100 hover:to-rose-200 border border-rose-200 text-rose-800 rounded-xl text-left text-xs font-semibold transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex items-center gap-1.5">
                  <Ban class="w-4 h-4" /> Terminate
                </button>
                <button @click="triggerAction('resend')" title="Generate a new temporary password and re-send the welcome email" class="p-3 bg-gradient-to-r from-emerald-50 to-emerald-100/50 hover:from-emerald-100 hover:to-emerald-200 border border-emerald-200 text-emerald-800 rounded-xl text-left text-xs font-semibold transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex items-center gap-1.5">
                  <KeyRound class="w-4 h-4" /> Resend Login
                </button>
              </div>

              <div class="mt-4 pt-4 border-t border-slate-100 space-y-2">
                <button @click="triggerAction('assets')" class="w-full text-left text-xs text-blue-600 hover:text-blue-700 font-semibold flex items-center justify-between p-2 hover:bg-blue-50 rounded-lg transition-colors">
                  <span class="flex items-center gap-2"><Monitor class="w-4 h-4" /> View Assigned Assets</span>
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
                <button @click="triggerAction('audit')" class="w-full text-left text-xs text-blue-600 hover:text-blue-700 font-semibold flex items-center justify-between p-2 hover:bg-blue-50 rounded-lg transition-colors">
                  <span class="flex items-center gap-2"><Clock class="w-4 h-4" /> View Audit History</span>
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
              </div>
            </div>

          </aside>

          <!-- RIGHT MAIN PANEL (col-span-8) -->
          <main class="lg:col-span-8 bg-white rounded-2xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 backdrop-blur-sm p-6">
            
            <!-- SECTION A: Personal & Contact Info -->
            <div v-show="activeTab === 'personal'" class="space-y-6">
              <div>
                <h3 class="text-xl font-bold text-slate-900">Personal Details</h3>
                <p class="text-sm text-slate-500 mt-0.5">Primary legal profile and identity declarations.</p>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">First Name</label>
                  <input type="text" v-model="employee.firstName" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Middle Name</label>
                  <input type="text" v-model="employee.middleName" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Last Name</label>
                  <input type="text" v-model="employee.lastName" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Suffix</label>
                  <input type="text" v-model="employee.suffix" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Nickname</label>
                  <input type="text" v-model="employee.nickname" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Preferred Name</label>
                  <input type="text" v-model="employee.preferredName" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Gender</label>
                  <select v-model="employee.gender" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200 cursor-pointer">
                    <option>Male</option>
                    <option>Female</option>
                    <option>Other</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Date of Birth</label>
                  <input type="date" v-model="employee.dateOfBirth" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Civil Status</label>
                  <select v-model="employee.civilStatus" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200 cursor-pointer">
                    <option>Single</option>
                    <option>Married</option>
                    <option>Divorced</option>
                    <option>Widowed</option>
                  </select>
                </div>
              </div>

              <div class="border-t border-slate-100 pt-6">
                <h3 class="text-lg font-bold text-slate-900 mb-4">Contact & Emergency Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Personal Email</label>
                    <input type="email" v-model="employee.personalEmail" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Company Email</label>
                    <input type="email" v-model="employee.companyEmail" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Mobile Number</label>
                    <input type="text" v-model="employee.mobileNumber" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Emergency Contact Person</label>
                    <input type="text" v-model="employee.emergencyContact" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Emergency Contact Number</label>
                    <input type="text" v-model="employee.emergencyContactNumber" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                  </div>
                </div>
              </div>
            </div>

            <!-- SECTION B: Address & Location -->
            <div v-show="activeTab === 'address'" class="space-y-6">
              <div>
                <h3 class="text-xl font-bold text-slate-900">Address Registration</h3>
                <p class="text-sm text-slate-500 mt-0.5">Current residential and permanent tracking coordinates.</p>
              </div>
              <div class="space-y-4">
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Current Residential Address</label>
                  <textarea v-model="employee.currentAddress" rows="2" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200 resize-none"></textarea>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Permanent Legal Address</label>
                  <textarea v-model="employee.permanentAddress" rows="2" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200 resize-none"></textarea>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Country</label>
                    <input type="text" v-model="employee.country" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Province/State</label>
                    <input type="text" v-model="employee.provinceState" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">City</label>
                    <input type="text" v-model="employee.city" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Postal Code</label>
                    <input type="text" v-model="employee.postalCode" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                  </div>
                </div>
              </div>
            </div>

            <!-- SECTION C: Employment & Org Assignment -->
            <div v-show="activeTab === 'employment'" class="space-y-6">
              <div>
                <h3 class="text-xl font-bold text-slate-900">Employment & Organizational Structure</h3>
                <p class="text-sm text-slate-500 mt-0.5">Corporate assignments, operational hubs, and chronological milestones.</p>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Company</label>
                  <input type="text" v-model="employee.company" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Branch Location</label>
                  <input type="text" v-model="employee.branch" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Business Unit</label>
                  <input type="text" v-model="employee.businessUnit" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Department</label>
                  <input type="text" v-model="employee.department" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Position</label>
                  <input type="text" v-model="employee.position" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Employment Type</label>
                  <input type="text" v-model="employee.employmentType" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                </div>
              </div>

              <div class="border-t border-slate-100 pt-6">
                <h3 class="text-lg font-bold text-slate-900 mb-4">Milestone Dates</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Date Hired</label>
                    <input type="date" v-model="employee.dateHired" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Probation End</label>
                    <input type="date" v-model="employee.probationEndDate" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Regularization</label>
                    <input type="date" v-model="employee.regularizationDate" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Resignation Date</label>
                    <input type="date" v-model="employee.resignationDate" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                  </div>
                </div>
              </div>

              <div class="border-t border-slate-100 pt-6">
                <h3 class="text-lg font-bold text-slate-900 mb-4">Organizational Hierarchy</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Reports To (Direct)</label>
                    <input type="text" v-model="employee.reportsTo" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Immediate Supervisor</label>
                    <input type="text" v-model="employee.immediateSupervisor" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Cost Center Code</label>
                    <input type="text" v-model="employee.costCenter" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                  </div>
                </div>
              </div>

              <div class="border-t border-slate-100 pt-6">
                <h3 class="text-lg font-bold text-slate-900 mb-1">Hire Source</h3>
                <p class="text-[11px] text-slate-400 mb-3">Applicant + onboarding linkage for this employee.</p>
                <div class="overflow-x-auto border border-slate-100 rounded-xl">
                  <table class="w-full text-xs">
                    <thead><tr class="bg-slate-50 text-slate-500 uppercase text-[10px] tracking-wider">
                      <th class="px-3 py-2 text-left">Source</th><th class="px-3 py-2 text-left">Detail</th><th class="px-3 py-2 text-center">Status / Progress</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                      <tr class="hover:bg-slate-50">
                        <td class="px-3 py-2 font-semibold">Applicant</td>
                        <td class="px-3 py-2">{{ employee.applicantProfile ? `#${employee.applicantProfile.id} • ${employee.applicantProfile.position_applied || '—'} • ${employee.applicantProfile.city || ''}` : 'No linked applicant (manual / walk-in record)' }}</td>
                        <td class="px-3 py-2 text-center">{{ employee.applicantProfile?.status || '—' }}</td>
                      </tr>
                      <tr class="hover:bg-slate-50">
                        <td class="px-3 py-2 font-semibold">Onboarding</td>
                        <td class="px-3 py-2">{{ employee.onboardingRecord ? `#${employee.onboardingRecord.id} • ${employee.onboardingRecord.position || ''} • ${employee.onboardingRecord.department || ''}` : 'No onboarding record found' }}</td>
                        <td class="px-3 py-2 text-center">{{ employee.onboardingRecord ? `${employee.onboardingRecord.status} (${employee.onboardingRecord.progress ?? 0}%)` : '—' }}</td>
                      </tr>
                      <tr class="hover:bg-slate-50">
                        <td class="px-3 py-2 font-semibold">System IDs</td>
                        <td class="px-3 py-2" colspan="2">Role: {{ employee.role || '—' }} • Rank: {{ employee.positionRank || '—' }} • Dept ID: {{ employee.departmentId || '—' }} • Position ID: {{ employee.orgPositionId || '—' }} • Type ID: {{ employee.employmentTypeId || '—' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- SECTION D: Payroll & Attendance Variables -->
            <div v-show="activeTab === 'payroll'" class="space-y-6">
              <div>
                <h3 class="text-xl font-bold text-slate-900">Payroll & Attendance Architecture</h3>
                <p class="text-sm text-slate-500 mt-0.5">Compensation frameworks, financial mappings, tax codes, and schedule matrices.</p>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Salary Grade</label>
                  <input type="text" v-model="employee.salaryGrade" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Basic Base Salary</label>
                  <input type="text" v-model="employee.basicSalary" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Bank Account / Swift</label>
                  <input type="text" v-model="employee.bankAccount" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Tax Identification (TIN)</label>
                  <input type="text" v-model="employee.taxID" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">SSN / Social Security</label>
                  <input type="text" v-model="employee.ssn" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Payroll Grouping</label>
                  <input type="text" v-model="employee.payrollGroup" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                </div>
              </div>

              <div class="border-t border-slate-100 pt-6">
                <h3 class="text-lg font-bold text-slate-900 mb-4">Attendance Reference</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Work Schedule Profile</label>
                    <input type="text" v-model="employee.workSchedule" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Standard Shift</label>
                    <input type="text" v-model="employee.shift" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Biometrics Device ID</label>
                    <input type="text" v-model="employee.biometricsID" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                  </div>
                </div>
              </div>

              <div class="border-t border-slate-100 pt-6">
                <h3 class="text-lg font-bold text-slate-900 mb-4">Allocated Leave Balances</h3>
                <div class="grid grid-cols-3 gap-4">
                  <div class="p-4 bg-gradient-to-br from-blue-50 to-blue-100/50 rounded-xl border border-blue-100 text-center shadow-sm hover:shadow-md transition-all duration-200">
                    <span class="text-[10px] text-blue-500 font-semibold block uppercase tracking-wider">Vacation Leave</span>
                    <span class="text-2xl font-bold text-blue-700 mt-1">{{ employee.vacationLeaveBalance }}</span>
                    <span class="text-[10px] text-blue-400">Days</span>
                  </div>
                  <div class="p-4 bg-gradient-to-br from-emerald-50 to-emerald-100/50 rounded-xl border border-emerald-100 text-center shadow-sm hover:shadow-md transition-all duration-200">
                    <span class="text-[10px] text-emerald-500 font-semibold block uppercase tracking-wider">Sick Leave</span>
                    <span class="text-2xl font-bold text-emerald-700 mt-1">{{ employee.sickLeaveBalance }}</span>
                    <span class="text-[10px] text-emerald-400">Days</span>
                  </div>
                  <div class="p-4 bg-gradient-to-br from-purple-50 to-purple-100/50 rounded-xl border border-purple-100 text-center shadow-sm hover:shadow-md transition-all duration-200">
                    <span class="text-[10px] text-purple-500 font-semibold block uppercase tracking-wider">Other Leaves</span>
                    <span class="text-2xl font-bold text-purple-700 mt-1">{{ employee.otherLeaveBalance || '5' }}</span>
                    <span class="text-[10px] text-purple-400">Days</span>
                  </div>
                </div>
                <div v-if="(employee.leaveBalancesDetailed ?? []).length" class="mt-4 overflow-x-auto border border-slate-100 rounded-xl">
                  <table class="w-full text-xs">
                    <thead><tr class="bg-slate-50 text-slate-500 uppercase text-[10px] tracking-wider">
                      <th class="px-3 py-2 text-left">Leave Type</th><th class="px-3 py-2 text-center">Entitled</th>
                      <th class="px-3 py-2 text-center">Used</th><th class="px-3 py-2 text-center">Remaining</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                      <tr v-for="b in (employee.leaveBalancesDetailed ?? [])" :key="b.code" class="hover:bg-slate-50">
                        <td class="px-3 py-2 font-semibold text-slate-700">{{ b.name }} <span class="text-slate-400 font-normal">({{ b.code }})</span></td>
                        <td class="px-3 py-2 text-center">{{ b.entitled }}</td>
                        <td class="px-3 py-2 text-center">{{ b.used }}</td>
                        <td class="px-3 py-2 text-center font-bold text-emerald-700">{{ b.remaining }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="border-t border-slate-100 pt-6">
                <h3 class="text-lg font-bold text-slate-900 mb-1">Payroll History</h3>
                <p class="text-[11px] text-slate-400 mb-3">Latest computed payrolls for this employee (payrolls table).</p>
                <div v-if="(employee.payrollHistory ?? []).length" class="overflow-x-auto border border-slate-100 rounded-xl">
                  <table class="w-full text-xs">
                    <thead><tr class="bg-slate-50 text-slate-500 uppercase text-[10px] tracking-wider">
                      <th class="px-3 py-2 text-left">Period</th><th class="px-3 py-2 text-center">Days</th>
                      <th class="px-3 py-2 text-right">Base</th><th class="px-3 py-2 text-right">Gross</th>
                      <th class="px-3 py-2 text-right">Net</th><th class="px-3 py-2 text-center">Status</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                      <tr v-for="p in (employee.payrollHistory ?? [])" :key="p.id" class="hover:bg-slate-50">
                        <td class="px-3 py-2 font-semibold">{{ p.period }} <span class="block text-[10px] text-slate-400 font-normal">{{ p.date }}</span></td>
                        <td class="px-3 py-2 text-center">{{ p.days_worked }}</td>
                        <td class="px-3 py-2 text-right">₱{{ Number(p.base_salary || 0).toLocaleString() }}</td>
                        <td class="px-3 py-2 text-right">₱{{ Number(p.gross_pay || 0).toLocaleString() }}</td>
                        <td class="px-3 py-2 text-right font-bold text-emerald-700">₱{{ Number(p.net_pay || 0).toLocaleString() }}</td>
                        <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded-full bg-slate-100 border border-slate-200 text-[10px]">{{ p.status }}</span></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-else class="text-xs text-slate-400 py-3 text-center border border-dashed border-slate-200 rounded-xl">No payroll rows yet for this employee.</div>
              </div>

              <div class="border-t border-slate-100 pt-6">
                <h3 class="text-lg font-bold text-slate-900 mb-1">Leave Requests</h3>
                <p class="text-[11px] text-slate-400 mb-3">Filing history (leave_requests table).</p>
                <div v-if="(employee.leaveRequests ?? []).length" class="overflow-x-auto border border-slate-100 rounded-xl">
                  <table class="w-full text-xs">
                    <thead><tr class="bg-slate-50 text-slate-500 uppercase text-[10px] tracking-wider">
                      <th class="px-3 py-2 text-left">Type</th><th class="px-3 py-2 text-center">Start</th>
                      <th class="px-3 py-2 text-center">End</th><th class="px-3 py-2 text-left">Reason</th>
                      <th class="px-3 py-2 text-center">Status</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                      <tr v-for="l in (employee.leaveRequests ?? [])" :key="l.id" class="hover:bg-slate-50">
                        <td class="px-3 py-2 font-semibold">{{ l.type }}</td>
                        <td class="px-3 py-2 text-center">{{ l.start }}</td>
                        <td class="px-3 py-2 text-center">{{ l.end }}</td>
                        <td class="px-3 py-2 text-slate-500 max-w-[220px] truncate" :title="l.reason">{{ l.reason }}</td>
                        <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded-full bg-slate-100 border border-slate-200 text-[10px]">{{ l.status }}</span></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-else class="text-xs text-slate-400 py-3 text-center border border-dashed border-slate-200 rounded-xl">No leave requests filed.</div>
              </div>

              <div class="border-t border-slate-100 pt-6">
                <h3 class="text-lg font-bold text-slate-900 mb-1">Attendance — This Month ({{ employee.attendanceMonth.present }} present / {{ employee.attendanceMonth.late }} late / {{ employee.attendanceMonth.absent }} absent)</h3>
                <p class="text-[11px] text-slate-400 mb-3">Recent punches (attendance_logs table).</p>
                <div v-if="(employee.attendanceRecent ?? []).length" class="overflow-x-auto border border-slate-100 rounded-xl">
                  <table class="w-full text-xs">
                    <thead><tr class="bg-slate-50 text-slate-500 uppercase text-[10px] tracking-wider">
                      <th class="px-3 py-2 text-left">Date</th><th class="px-3 py-2 text-center">In</th>
                      <th class="px-3 py-2 text-center">Out</th><th class="px-3 py-2 text-center">Status</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                      <tr v-for="a in (employee.attendanceRecent ?? [])" :key="a.date" class="hover:bg-slate-50">
                        <td class="px-3 py-2 font-semibold">{{ a.date }}</td>
                        <td class="px-3 py-2 text-center">{{ a.clock_in || '—' }}</td>
                        <td class="px-3 py-2 text-center">{{ a.clock_out || '—' }}</td>
                        <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded-full bg-slate-100 border border-slate-200 text-[10px]">{{ a.status }}</span></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-else class="text-xs text-slate-400 py-3 text-center border border-dashed border-slate-200 rounded-xl">No attendance logs this period.</div>
              </div>

              <div class="border-t border-slate-100 pt-6">
                <h3 class="text-lg font-bold text-slate-900 mb-1">Shift Assignments</h3>
                <p class="text-[11px] text-slate-400 mb-3">Workforce scheduling (employee_shifts table).</p>
                <div v-if="(employee.shifts ?? []).length" class="overflow-x-auto border border-slate-100 rounded-xl">
                  <table class="w-full text-xs">
                    <thead><tr class="bg-slate-50 text-slate-500 uppercase text-[10px] tracking-wider">
                      <th class="px-3 py-2 text-left">Effective</th><th class="px-3 py-2 text-left">Dept</th>
                      <th class="px-3 py-2 text-left">Shift</th><th class="px-3 py-2 text-left">Schedule</th>
                      <th class="px-3 py-2 text-center">Status</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                      <tr v-for="s in (employee.shifts ?? [])" :key="s.id" class="hover:bg-slate-50">
                        <td class="px-3 py-2">{{ s.effective_date }}</td>
                        <td class="px-3 py-2">{{ s.dept_code }}</td>
                        <td class="px-3 py-2 font-semibold">{{ s.shift_type }}</td>
                        <td class="px-3 py-2">{{ s.schedule }}</td>
                        <td class="px-3 py-2 text-center">{{ s.status }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-else class="text-xs text-slate-400 py-3 text-center border border-dashed border-slate-200 rounded-xl">No shift assignments.</div>
              </div>
            </div>

            <!-- SECTION E: Document Locker -->
            <div v-show="activeTab === 'documents'" class="space-y-6">
              <div class="flex justify-between items-center">
                <div>
                  <h3 class="text-xl font-bold text-slate-900">Employee Documents</h3>
                  <p class="text-sm text-slate-500 mt-0.5">Encrypted personal vault for verified files and credentials.</p>
                </div>
                <button class="px-4 py-2.5 bg-gradient-to-r from-slate-800 to-slate-900 hover:from-slate-700 hover:to-slate-800 text-white rounded-xl text-xs font-semibold transition-all duration-200 shadow-lg hover:shadow-xl hover:shadow-slate-500/25 hover:-translate-y-0.5 flex items-center gap-2">
                  <Upload class="w-4 h-4" /> Upload File
                </button>
              </div>

              <div class="space-y-3">
                <div v-for="doc in (employee.documents ?? [])" :key="doc.name" class="flex items-center justify-between p-4 bg-slate-50/80 border border-slate-100 rounded-xl hover:shadow-md transition-all duration-200 hover:bg-white group">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center shadow-inner">
                      <FileText class="w-5 h-5 text-blue-600" />
                    </div>
                    <div>
                      <p class="text-xs font-bold text-slate-800 group-hover:text-blue-700 transition-colors">{{ doc.name }}</p>
                      <p class="text-[10px] text-slate-400">Type: {{ doc.type }} • {{ doc.verified ? 'Verified' : 'Unverified' }} • {{ doc.uploaded_at || '' }}</p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <span :class="['px-2 py-0.5 rounded-full text-[10px] font-semibold border', doc.verified ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200]']">{{ doc.verified ? 'Verified' : 'Pending' }}</span>
                    <a v-if="doc.url" :href="doc.url" target="_blank" class="text-xs text-blue-600 hover:text-blue-700 font-semibold hover:underline transition-all">View</a>
                    <span v-else class="text-[10px] text-slate-400">No file</span>
                  </div>
                </div>
                <div v-if="!(employee.documents ?? []).length" class="text-center text-xs text-slate-400 py-6">No documents on file for this employee.</div>
              </div>

              <div v-if="(employee.documents ?? []).length" class="overflow-x-auto border border-slate-100 rounded-xl">
                <table class="w-full text-xs">
                  <thead><tr class="bg-slate-50 text-slate-500 uppercase text-[10px] tracking-wider">
                    <th class="px-3 py-2 text-left">File</th><th class="px-3 py-2 text-left">Type</th>
                    <th class="px-3 py-2 text-center">Verified</th><th class="px-3 py-2 text-center">Uploaded</th><th class="px-3 py-2 text-center">Link</th>
                  </tr></thead>
                  <tbody class="divide-y divide-slate-100">
                    <tr v-for="doc in (employee.documents ?? [])" :key="'tbl-' + doc.name" class="hover:bg-slate-50">
                      <td class="px-3 py-2 font-semibold">{{ doc.name }}</td>
                      <td class="px-3 py-2">{{ doc.type }}</td>
                      <td class="px-3 py-2 text-center">{{ doc.verified ? 'Yes' : 'No' }}</td>
                      <td class="px-3 py-2 text-center">{{ doc.uploaded_at || '—' }}</td>
                      <td class="px-3 py-2 text-center"><a v-if="doc.url" :href="doc.url" target="_blank" class="text-blue-600 hover:underline font-semibold">Open</a><span v-else class="text-slate-400">—</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- SECTION F: Skills & Qualifications -->
            <div v-show="activeTab === 'qualifications'" class="space-y-6">
              <div>
                <h3 class="text-xl font-bold text-slate-900">Skills & Educational Accreditations</h3>
                <p class="text-sm text-slate-500 mt-0.5">Key performance metrics, academic context, and domain expertise.</p>
              </div>
              
              <div class="space-y-4">
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Education History</label>
                  <textarea v-model="employee.education" rows="2" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200 resize-none"></textarea>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Certifications & Accreditations</label>
                  <input type="text" v-model="employee.certifications" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 mb-1.5">Core Tech Skills (Comma Separated)</label>
                  <input type="text" v-model="employee.skills" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200" />
                </div>
              </div>

              <div class="border-t border-slate-100 pt-6">
                <h3 class="text-lg font-bold text-slate-900 mb-1">Certification Registry</h3>
                <p class="text-[11px] text-slate-400 mb-3">System-issued records (hrm_certifications table).</p>
                <div v-if="(employee.certificationsDetailed ?? []).length" class="overflow-x-auto border border-slate-100 rounded-xl">
                  <table class="w-full text-xs">
                    <thead><tr class="bg-slate-50 text-slate-500 uppercase text-[10px] tracking-wider">
                      <th class="px-3 py-2 text-left">Name</th><th class="px-3 py-2 text-center">Training #</th>
                      <th class="px-3 py-2 text-center">Issued</th><th class="px-3 py-2 text-center">Expiry</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                      <tr v-for="c in (employee.certificationsDetailed ?? [])" :key="c.id" class="hover:bg-slate-50">
                        <td class="px-3 py-2 font-semibold">{{ c.name }}</td>
                        <td class="px-3 py-2 text-center">{{ c.training_id || '—' }}</td>
                        <td class="px-3 py-2 text-center">{{ c.issued || '—' }}</td>
                        <td class="px-3 py-2 text-center">{{ c.expiry || '—' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-else class="text-xs text-slate-400 py-3 text-center border border-dashed border-slate-200 rounded-xl">No system certifications — free-text field above still applies.</div>
              </div>

              <div class="border-t border-slate-100 pt-6">
                <h3 class="text-lg font-bold text-slate-900 mb-1">Training History</h3>
                <p class="text-[11px] text-slate-400 mb-3">Enrollments (hrm_training_enrollments table).</p>
                <div v-if="(employee.trainingHistory ?? []).length" class="overflow-x-auto border border-slate-100 rounded-xl">
                  <table class="w-full text-xs">
                    <thead><tr class="bg-slate-50 text-slate-500 uppercase text-[10px] tracking-wider">
                      <th class="px-3 py-2 text-left">Training</th><th class="px-3 py-2 text-center">Status</th>
                      <th class="px-3 py-2 text-center">Progress</th><th class="px-3 py-2 text-center">Completed</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                      <tr v-for="t in (employee.trainingHistory ?? [])" :key="t.id" class="hover:bg-slate-50">
                        <td class="px-3 py-2 font-semibold">{{ t.training }}</td>
                        <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded-full bg-slate-100 border border-slate-200 text-[10px]">{{ t.status }}</span></td>
                        <td class="px-3 py-2 text-center">{{ t.progress ?? 0 }}%</td>
                        <td class="px-3 py-2 text-center">{{ t.completed || '—' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-else class="text-xs text-slate-400 py-3 text-center border border-dashed border-slate-200 rounded-xl">No training enrollments.</div>
              </div>
            </div>

            <!-- SECTION G: Performance Reviews & Disciplinary Records -->
            <div v-show="activeTab === 'performance'" class="space-y-6">
              <div>
                <h3 class="text-xl font-bold text-slate-900">Performance & Compliance Registry</h3>
                <p class="text-sm text-slate-500 mt-0.5">Live roll-up from trainee grades, trainings, attendance, and certifications — no mock scores.</p>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div class="p-5 bg-gradient-to-br from-emerald-50 to-emerald-100/50 border border-emerald-100 rounded-xl shadow-sm hover:shadow-md transition-all duration-200">
                  <span class="text-xs text-emerald-700 font-semibold uppercase tracking-wider">Trainee / Review Score</span>
                  <div class="text-3xl font-bold text-emerald-800 mt-2">{{ employee.performanceSummary?.has_grade ? (employee.performanceSummary.total_percentage + '%') : 'No grade yet' }}</div>
                  <p class="text-[10px] text-emerald-600 mt-1">{{ employee.performanceSummary?.has_grade ? (employee.performanceSummary.passed_to_hr ? 'Passed to HR' : 'Pending HR review') : 'No trainee_grade row for this user' }}</p>
                </div>
                <div class="p-5 bg-gradient-to-br from-slate-50 to-slate-100/50 border border-slate-200 rounded-xl shadow-sm hover:shadow-md transition-all duration-200">
                  <span class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Training Completion</span>
                  <div class="text-3xl font-bold text-slate-800 mt-2">{{ employee.performanceSummary?.trainings_completed ?? 0 }} / {{ employee.performanceSummary?.trainings_total ?? 0 }}</div>
                  <p class="text-[10px] text-slate-400 mt-1">{{ employee.performanceSummary?.training_completion_rate ?? 0 }}% complete • Attendance {{ employee.performanceSummary?.attendance_rate ?? 0 }}% this month</p>
                </div>
              </div>

              <div v-if="employee.performanceSummary?.has_grade" class="overflow-x-auto border border-slate-100 rounded-xl">
                <table class="w-full text-xs">
                  <thead><tr class="bg-slate-50 text-slate-500 uppercase text-[10px] tracking-wider">
                    <th class="px-3 py-2 text-left">Dimension</th><th class="px-3 py-2 text-center">Score (1-5)</th>
                  </tr></thead>
                  <tbody class="divide-y divide-slate-100">
                    <tr class="hover:bg-slate-50"><td class="px-3 py-2">Skills Performance</td><td class="px-3 py-2 text-center font-bold">{{ employee.performanceSummary.skills_performance }}</td></tr>
                    <tr class="hover:bg-slate-50"><td class="px-3 py-2">Behaviour</td><td class="px-3 py-2 text-center font-bold">{{ employee.performanceSummary.behaviour }}</td></tr>
                    <tr class="hover:bg-slate-50"><td class="px-3 py-2">Technicals</td><td class="px-3 py-2 text-center font-bold">{{ employee.performanceSummary.technicals }}</td></tr>
                    <tr class="hover:bg-slate-50"><td class="px-3 py-2">Safety Awareness</td><td class="px-3 py-2 text-center font-bold">{{ employee.performanceSummary.safety_awareness }}</td></tr>
                    <tr class="hover:bg-slate-50"><td class="px-3 py-2">Productivity</td><td class="px-3 py-2 text-center font-bold">{{ employee.performanceSummary.productivity }}</td></tr>
                  </tbody>
                </table>
              </div>

              <div class="bg-gradient-to-r from-slate-50 to-slate-100/50 border border-slate-200 rounded-xl p-4 flex items-start gap-3 shadow-sm">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center shadow-inner">
                  <Shield class="w-5 h-5 text-blue-600" />
                </div>
                <div>
                  <p class="text-xs font-bold text-slate-800">{{ (employee.performanceSummary?.certifications_count ?? 0) }} certification(s) • {{ employee.performanceSummary?.attendance_present ?? 0 }}/{{ employee.performanceSummary?.attendance_total ?? 0 }} present this month</p>
                  <p class="text-[10px] text-slate-500">Disciplinary tracking lives in audit logs — see System Change Log for any incident entries.</p>
                </div>
              </div>
            </div>

            <!-- SECTION H: Audit & Chronological Records -->
            <div v-show="activeTab === 'history'" class="space-y-6">
              <div>
                <h3 class="text-xl font-bold text-slate-900">System Logs & Chronology</h3>
                <p class="text-sm text-slate-500 mt-0.5">Audit footprints across positions, departments, and payroll shifts.</p>
              </div>

              <div class="relative border-l-2 border-slate-200 pl-6 space-y-6">
                <div v-for="(log, idx) in (employee.auditTrail ?? []).slice(0, 5)" :key="idx" class="relative text-xs">
                  <div class="absolute -left-[29px] top-1.5 w-3 h-3 rounded-full bg-gradient-to-r from-blue-500 to-blue-600 ring-4 ring-white shadow-lg shadow-blue-500/20"></div>
                  <span class="text-[10px] text-slate-400 block mb-0.5 font-medium">{{ log.date }}</span>
                  <p class="text-slate-800 font-bold">{{ log.title }}</p>
                  <p class="text-[10px] text-slate-500 mt-0.5">{{ log.desc }}</p>
                </div>
                <div v-if="!(employee.auditTrail ?? []).length" class="text-xs text-slate-400">No system events recorded for this employee yet.</div>
              </div>

              <div>
                <h3 class="text-lg font-bold text-slate-900 mb-1">Full Change Log Table</h3>
                <p class="text-[11px] text-slate-400 mb-3">Every audit_logs row targeting this employee (latest 20).</p>
                <div v-if="(employee.auditTrail ?? []).length" class="overflow-x-auto border border-slate-100 rounded-xl">
                  <table class="w-full text-xs">
                    <thead><tr class="bg-slate-50 text-slate-500 uppercase text-[10px] tracking-wider">
                      <th class="px-3 py-2 text-left">Date</th><th class="px-3 py-2 text-left">Action</th>
                      <th class="px-3 py-2 text-left">By</th><th class="px-3 py-2 text-left">Target</th><th class="px-3 py-2 text-left">Reason</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                      <tr v-for="(log, idx) in (employee.auditTrail ?? [])" :key="'full-' + idx" class="hover:bg-slate-50">
                        <td class="px-3 py-2 whitespace-nowrap">{{ log.raw_date || log.date }}</td>
                        <td class="px-3 py-2 font-semibold">{{ log.action }}</td>
                        <td class="px-3 py-2">{{ log.admin }}</td>
                        <td class="px-3 py-2">{{ log.target }}</td>
                        <td class="px-3 py-2 text-slate-500 max-w-[240px] truncate" :title="log.desc">{{ log.desc || '—' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- Global Action Bottom Bar (Save Changes) -->
            <div class="mt-8 pt-6 border-t border-slate-100 flex justify-end gap-3">
              <button @click="resetForm" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-all duration-200 shadow-sm hover:shadow-md">
                Discard Changes
              </button>
              <button @click="saveEmployee" class="px-5 py-2.5 bg-gradient-to-r from-slate-800 to-slate-900 hover:from-slate-700 hover:to-slate-800 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-lg hover:shadow-xl hover:shadow-slate-500/25 hover:-translate-y-0.5">
                Save Record Changes
              </button>
            </div>

          </main>

        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Upload, Printer, TrendingUp, TrendingDown, RefreshCw, ClipboardList, Archive, Ban,
    Monitor, Clock, FileText, Shield, User, MapPin, Building2, CreditCard, Folder, GraduationCap,
    KeyRound, Copy, X
} from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

// Dynamic Record Tab State
const activeTab = ref("personal");

const tabs = [
  { id: "personal", icon: User, label: "Personal & Contact Info" },
  { id: "address", icon: MapPin, label: "Registered Addresses" },
  { id: "employment", icon: Building2, label: "Employment & Structure" },
  { id: "payroll", icon: CreditCard, label: "Payroll & Attendance Reference" },
  { id: "documents", icon: Folder, label: "Digital Document Locker" },
  { id: "qualifications", icon: GraduationCap, label: "Qualifications & Skills" },
  { id: "performance", icon: TrendingUp, label: "Performance Reviews" },
  { id: "history", icon: Clock, label: "System Change Log" }
];

const props = defineProps({
  employees: { type: [Array, Object], default: () => [] },
  profileOnly: { type: Boolean, default: false },
  focusId: { default: null },
  pagination: { type: Object, default: () => ({}) },
  stats: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
  departments: { type: [Array, Object], default: () => [] },
  positions: { type: [Array, Object], default: () => [] },
  employmentTypes: { type: [Array, Object], default: () => [] },
  roles: { type: [Array, Object], default: () => [] },
});

const page = usePage();
const asArray = (v) => (Array.isArray(v) ? v : (v?.data ?? [])) ?? [];
const employeeList = computed(() => asArray(props.employees).filter((e) => e && typeof e === 'object' && e.id != null));
const departmentOptions = computed(() => asArray(props.departments));

const directorySearch = ref(props.filters?.search || '');
const directoryDepartment = ref(props.filters?.department || '');
const directoryStatus = ref(props.filters?.status || '');

// Blank master record so every v-model binding stays defined.
const blankRecord = () => ({
  id: null, employeeId: '', firstName: '', middleName: '', lastName: '', suffix: '',
  nickname: '', preferredName: '', gender: '', dateOfBirth: '', civilStatus: '',
  nationality: 'Filipino', profilePicture: '', personalEmail: '', companyEmail: '',
  mobileNumber: '', telephone: '', emergencyContact: '', emergencyContactNumber: '',
  currentAddress: '', permanentAddress: '', country: 'Philippines', provinceState: '',
  city: '', postalCode: '', company: 'MontiTextile', branch: '', businessUnit: '',
  department: '', departmentId: null, position: '', orgPositionId: null,
  employmentType: 'Regular', employmentTypeId: null, employeeStatus: 'Active',
  role: '', positionRank: '', dateHired: '', probationEndDate: '', regularizationDate: '',
  resignationDate: '', lastWorkingDay: '', reportsTo: '', immediateSupervisor: '',
  departmentHead: '', costCenter: '', workLocation: '', payrollGroup: '', salaryGrade: '',
  basicSalary: '', basicSalaryRaw: null, bankAccount: '', taxID: '', ssn: '',
  workSchedule: '', shift: '', timeZone: 'GMT+8', biometricsID: '',
  vacationLeaveBalance: '12', sickLeaveBalance: '15', otherLeaveBalance: '5',
  attendanceMonth: { present: 0, late: 0, absent: 0 },
  education: '', certifications: '', skills: '', languages: '', trainings: '',
  documents: [], auditTrail: [],
  leaveBalancesDetailed: [], leaveRequests: [], attendanceRecent: [],
  payrollHistory: [], shifts: [], certificationsDetailed: [], trainingHistory: [],
  performanceSummary: null, applicantProfile: null, onboardingRecord: null,
});

const selectedId = ref(props.focusId ?? null);
const initialRec = props.focusId != null
  ? employeeList.value.find((e) => String(e.id) === String(props.focusId))
  : null;
const employee = ref(initialRec ? JSON.parse(JSON.stringify(initialRec)) : blankRecord());
let pristine = JSON.stringify(employee.value);

const selectEmployee = (id) => {
  // List page: open the standalone profile page. Profile page: switch record locally.
  if (!props.profileOnly) {
    router.get(route('hrm.workforce.employees.profile', id));
    return;
  }
  const rec = employeeList.value.find((e) => e.id === id);
  if (!rec) return;
  selectedId.value = id;
  employee.value = JSON.parse(JSON.stringify(rec));
  pristine = JSON.stringify(employee.value);
};

const initials = (name) => String(name || '?').split(' ').map((n) => n[0]).join('').slice(0, 2).toUpperCase();

watch(() => page.props.flash, (flash) => {
  if (flash?.success) toast.success(flash.success);
  if (flash?.error) toast.error(flash.error);
  if (flash?.message) toast.info(flash.message);
  if (flash?.hire_credentials) hireCreds.value = flash.hire_credentials;
}, { immediate: true });

// One-time generated login pair (employee creation or credential resend).
// Dismissible; never stored — copy it now.
const hireCreds = ref(page.props.flash?.hire_credentials ?? null);
const copiedField = ref('');
const copyCred = async (field, value) => {
  try {
    await navigator.clipboard.writeText(String(value ?? ''));
    copiedField.value = field;
    setTimeout(() => { copiedField.value = ''; }, 1500);
  } catch (e) {
    toast.error('Copy failed — select the text manually.');
  }
};

let searchTimer = null;
watch(directorySearch, () => { clearTimeout(searchTimer); searchTimer = setTimeout(applyDirectoryFilter, 400); });

const applyDirectoryFilter = () => {
  router.get(route('hrm.workforce.employees.index'), {
    search: directorySearch.value.trim(), department: directoryDepartment.value, status: directoryStatus.value,
  }, { preserveState: true, preserveScroll: true, replace: true });
};

// Pagination: the header total counts every user row, but the browser list
// only holds one page (20). Without these controls pages 2+ are unreachable.
const pageInfo = computed(() => {
  const p = props.pagination || {};
  const total = Number(p.total || 0), per = Number(p.per_page || 20);
  const current = Number(p.current_page || 1), last = Math.max(1, Number(p.last_page || 1));
  const from = total === 0 ? 0 : (current - 1) * per + 1;
  const to = Math.min(total, current * per);
  return { total, per, current, last, from, to };
});
const goDirectoryPage = (pageNum) => {
  const target = Math.max(1, Math.min(Number(props.pagination?.last_page || 1), Number(pageNum) || 1));
  router.get(route('hrm.workforce.employees.index'), {
    search: directorySearch.value.trim(), department: directoryDepartment.value, status: directoryStatus.value, page: target,
  }, { preserveState: true, preserveScroll: true, replace: true });
};

// Handlers for Lifecycle and System Actions (all live, no mocks)
const createNewEmployee = () => {
  toast.info('New hires enter through Onboarding — convert a hired applicant there.');
  router.visit(route('hrm.onboarding.status.index'));
};

const toPayload = () => {
  // Basic salary displays formatted (₱68,500.00) — parse back to numeric.
  let basic = employee.value.basicSalaryRaw;
  if (basic === null || basic === undefined || basic === '') {
    const parsed = parseFloat(String(employee.value.basicSalary || '').replace(/[^0-9.]/g, ''));
    basic = isNaN(parsed) ? null : parsed;
  }
  return {
  first_name: employee.value.firstName, middle_name: employee.value.middleName,
  last_name: employee.value.lastName, suffix: employee.value.suffix, nickname: employee.value.nickname,
  preferred_name: employee.value.preferredName, gender: employee.value.gender,
  date_of_birth: employee.value.dateOfBirth || null, civil_status: employee.value.civilStatus,
  nationality: employee.value.nationality, personal_email: employee.value.personalEmail || null,
  mobile_number: employee.value.mobileNumber, telephone: employee.value.telephone,
  emergency_contact_name: employee.value.emergencyContact,
  emergency_contact_number: employee.value.emergencyContactNumber,
  current_address: employee.value.currentAddress, permanent_address: employee.value.permanentAddress,
  country: employee.value.country, province: employee.value.provinceState, city: employee.value.city,
  postal_code: employee.value.postalCode, company: employee.value.company, branch: employee.value.branch,
  business_unit: employee.value.businessUnit, department: employee.value.department,
  cost_center: employee.value.costCenter, work_location: employee.value.workLocation,
  department_head: employee.value.departmentHead, immediate_supervisor: employee.value.immediateSupervisor,
  payroll_group: employee.value.payrollGroup, salary_grade: employee.value.salaryGrade,
  basic_salary: basic, bank_account: employee.value.bankAccount,
  tax_id: employee.value.taxID, sss_id: employee.value.ssn, work_schedule: employee.value.workSchedule,
  shift: employee.value.shift, timezone: employee.value.timeZone, biometrics_id: employee.value.biometricsID,
  join_date: employee.value.dateHired || null, probation_end_date: employee.value.probationEndDate || null,
  regularization_date: employee.value.regularizationDate || null,
  resignation_date: employee.value.resignationDate || null,
  last_working_day: employee.value.lastWorkingDay || null,
  hrm_department_id: employee.value.departmentId || null,
  hrm_position_id: employee.value.orgPositionId || null,
  hrm_employment_type_id: employee.value.employmentTypeId || null,
  education: employee.value.education, certifications: employee.value.certifications,
  skills: employee.value.skills, languages: employee.value.languages, trainings: employee.value.trainings,
  };
};

const saveEmployee = () => {
  if (!employee.value?.id) { toast.error('Select an employee first.'); return; }
  router.patch(route('hrm.workforce.employees.update', employee.value.id), toPayload(), {
    preserveScroll: true,
    onSuccess: () => { toast.success('Master record saved.'); pristine = JSON.stringify(employee.value); },
    onError: (e) => toast.error(String(Object.values(e || {})[0] || 'Failed to save record.')),
  });
};

const resetForm = () => {
  employee.value = JSON.parse(pristine);
  toast.info('Changes discarded.');
};

const triggerAction = (action) => {
  const id = employee.value?.id;
  if (!id && !['export', 'print'].includes(action)) { toast.error('Select an employee first.'); return; }
  if (action === 'export') {
    window.location.href = route('hrm.workforce.employees.export');
    return;
  }
  if (action === 'print') { window.print(); return; }
  if (action === 'assets') { toast.info('Asset assignment lives in IT → Assets.'); return; }
  if (action === 'audit') { activeTab.value = 'history'; return; }
  if (action === 'promote') {
    router.post(route('hrm.employees.promote-to-manager', id), {}, {
      onSuccess: () => toast.success('Promotion requested.'), onError: () => toast.error('Promotion denied.'),
    });
    return;
  }
  if (action === 'demote') {
    router.post(route('hrm.employees.demote-to-staff', id), {}, {
      onSuccess: () => toast.success('Demotion recorded.'), onError: () => toast.error('Demotion denied.'),
    });
    return;
  }
  if (action === 'transfer') {
    const dept = prompt('Transfer to which department (name or code)?', employee.value.department || '');
    if (dept === null) return;
    router.patch(route('hrm.workforce.employees.update', id), { department: dept }, {
      onSuccess: () => toast.success('Employee transferred.'),
    });
    return;
  }
  if (action === 'duplicate') { toast.info('Duplicates are created via Onboarding → Create Employee.'); return; }
  if (action === 'resend') {
    router.post(route('hrm.workforce.employees.resend-credentials', id), {}, {
      preserveScroll: true,
      onError: () => toast.error('Failed to resend credentials.'),
    });
    return;
  }
  if (action === 'archive' || action === 'terminate') {
    const reason = prompt(`Reason for ${action} (${employee.value.firstName} ${employee.value.lastName}) — required:`);
    if (!reason) return;
    router.delete(route('hrm.employees.toggle-status', id), { data: { reason }, preserveScroll: true });
  }
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

/* Gradient Text */
.bg-clip-text {
  -webkit-background-clip: text;
  background-clip: text;
}

/* Glass Morphism Effect */
.backdrop-blur-sm {
  backdrop-filter: blur(8px);
}
</style>