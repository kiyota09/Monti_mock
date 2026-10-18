<template>
    <AuthenticatedLayout>
        <!-- START SCREEN LOADING OVERLAY: lazy loading shown on page load -->
        <Transition name="fade">
            <div
                v-if="pageLoading"
                class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-100/50 backdrop-blur-md"
                aria-live="polite"
            >
                <div class="flex flex-col items-center gap-3">
                    <div class="w-10 h-10 rounded-full border-4 border-slate-300 border-t-blue-600 animate-spin"></div>
                    <p class="text-sm font-semibold text-slate-600">Loading applications...</p>
                </div>
            </div>
        </Transition>

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
                                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="text-blue-600 font-semibold">Applications</span>
                            </nav>
                            <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">Applications</h1>
                            <p class="text-sm text-slate-500 mt-0.5">Manage all candidates who apply for open job vacancies. Track progress and build your talent database.</p>
                        </div>

                        <!-- Quick Actions -->
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <button 
                                @click="openModal('create')"
                                class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-lg hover:shadow-xl hover:shadow-blue-500/25 hover:-translate-y-0.5"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                </svg>
                                Add Application
                            </button>
                            <button 
                                @click="exportCSV"
                                class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-medium transition-all duration-200 shadow-sm hover:shadow-md hover:-translate-y-0.5"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                                </svg>
                                Export CSV
                            </button>
                        </div>
                    </div>
                </header>

                <!-- KPI METRICS ROW -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4 shrink-0">
                    <div 
                        v-for="metric in metricsData" 
                        :key="metric.label"
                        class="group bg-white rounded-xl border border-slate-200/60 p-3 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-blue-200/60 shadow-md backdrop-blur-sm cursor-pointer"
                        @click="quickFilter(metric.filter)"
                    >
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-[9px] font-semibold text-slate-500 uppercase tracking-wider">{{ metric.label }}</span>
                            <div :class="['w-7 h-7 rounded-lg flex items-center justify-center shadow-inner', metric.bg]">
                                <svg class="w-3.5 h-3.5" :class="metric.color" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path v-if="metric.icon === 'users'" stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                                    <path v-else-if="metric.icon === 'clipboard'" stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    <path v-else-if="metric.icon === 'search'" stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                                    <path v-else-if="metric.icon === 'star'" stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
                                    <path v-else-if="metric.icon === 'check'" stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    <path v-else-if="metric.icon === 'x'" stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    <path v-else stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="text-lg font-bold" :class="metric.color">{{ metric.value }}</div>
                    </div>
                </div>

                <!-- HIRING FUNNEL -->
                <div class="bg-white rounded-xl border border-slate-200/60 shadow-md backdrop-blur-sm p-4 mb-4 shrink-0">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
                            </svg>
                            Hiring Funnel
                        </h3>
                        <span class="text-[10px] text-slate-500 bg-slate-50/80 px-3 py-1.5 rounded-lg border border-slate-200/60 shadow-sm">
                            Total: {{ applications ? applications.length : 0 }} applications
                        </span>
                    </div>
                    <div class="grid grid-cols-7 gap-1.5">
                        <div 
                            v-for="stage in funnelStages" 
                            :key="stage.name" 
                            class="text-center group cursor-pointer transition-all duration-200 hover:-translate-y-0.5"
                        >
                            <div 
                                :class="[
                                    'rounded-xl p-2.5 transition-all duration-200 group-hover:shadow-lg',
                                    stage.color
                                ]"
                            >
                                <div class="text-base font-bold">{{ stage.count }}</div>
                                <div class="text-[8px] font-semibold uppercase mt-0.5 truncate">{{ stage.name }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SEARCH & FILTERS -->
                <div class="bg-white rounded-xl border border-slate-200/60 shadow-md backdrop-blur-sm p-3 mb-4 shrink-0">
                    <div class="flex flex-col md:flex-row md:items-center gap-3">
                        <div class="flex-1 relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                                </svg>
                            </span>
                            <input 
                                v-model="searchQuery"
                                type="text" 
                                placeholder="Search applicants..." 
                                @input="debouncedSearch"
                                class="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200"
                            />
                        </div>
                        
                        <div class="flex flex-wrap items-center gap-2">
                            <select v-model="filterStatus" @change="applyFilters" class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-600 focus:ring-2 focus:ring-blue-500 shadow-sm transition-all duration-200 cursor-pointer">
                                <option value="">All Statuses</option>
                                <option v-for="status in statusOptions" :key="status" :value="status">{{ status }}</option>
                            </select>
                            <select v-model="filterPosition" @change="applyFilters" class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-600 focus:ring-2 focus:ring-blue-500 shadow-sm transition-all duration-200 cursor-pointer">
                                <option value="">All Positions</option>
                                <option v-for="pos in positionOptions" :key="pos" :value="pos">{{ pos }}</option>
                            </select>
                            <button @click="openHistory" class="inline-flex items-center gap-1.5 bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 focus:ring-2 focus:ring-blue-500 shadow-sm transition-all duration-200 cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                History
                            </button>
                        </div>
                    </div>
                </div>

                <!-- APPLICATION CARDS GRID -->
                <div class="bg-white rounded-xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 backdrop-blur-sm overflow-hidden flex-1 flex flex-col min-h-0">
                    <div class="flex-1 overflow-auto p-4">
                        <div v-if="applications && applications.length > 0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                            <div
                                v-for="app in filteredApplications"
                                :key="cardKey(app)"
                                class="bg-white rounded-xl border border-slate-200/60 hover:shadow-xl transition-all duration-300 hover:-translate-y-1 overflow-visible group"
                            >
                                <!-- Card Header -->
                                <div class="p-4 border-b border-slate-100 bg-gradient-to-r from-slate-50/50 to-transparent rounded-t-xl">
                                    <div class="flex items-start justify-between mb-2">
                                        <div class="flex items-center gap-3">
                                            <img
                                                v-if="getApplicantPhoto(app)"
                                                :src="getApplicantPhoto(app)"
                                                :alt="`${app.first_name || ''} ${app.last_name || ''}`"
                                                class="w-10 h-10 rounded-xl object-cover shadow-inner transition-all duration-300 group-hover:scale-105"
                                                @error="handleCardImageError($event, app)"
                                            />
                                            <div
                                                v-else
                                                :class="[
                                                    'w-10 h-10 rounded-xl flex items-center justify-center text-white font-bold text-sm shadow-inner transition-all duration-300 group-hover:scale-105',
                                                    getAvatarColor(app.status)
                                                ]"
                                            >
                                                {{ getInitials(app.first_name, app.last_name) }}
                                            </div>
                                            <div>
                                                <h3 class="text-sm font-bold text-slate-800">{{ app.first_name }} {{ app.last_name }}</h3>
                                                <span class="text-[10px] text-slate-400">{{ app.email || 'No email' }}</span>
                                            </div>
                                        </div>
                                        <span
                                            :class="[
                                                'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-semibold border shadow-sm',
                                                getStatusBadgeClass(app.status)
                                            ]"
                                        >
                                            <span class="w-1.5 h-1.5 rounded-full" :class="getStatusDotClass(app.status)"></span>
                                            {{ app.status || 'Submitted' }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500">
                                        Applied for: <strong class="text-slate-700">{{ app.job_posting?.title || 'N/A' }}</strong>
                                    </p>
                                </div>

                                <!-- Card Body -->
                                <div class="p-4 space-y-2.5">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="p-2 bg-slate-50/80 border border-slate-100 rounded-lg text-xs">
                                            <span class="text-slate-400">Email</span>
                                            <p class="font-semibold text-slate-700 text-sm truncate">{{ app.email || 'N/A' }}</p>
                                        </div>
                                        <div class="p-2 bg-slate-50/80 border border-slate-100 rounded-lg text-xs">
                                            <span class="text-slate-400">Phone</span>
                                            <p class="font-semibold text-slate-700 text-sm">{{ app.phone || 'N/A' }}</p>
                                        </div>
                                    </div>
                                    <div class="space-y-1 text-xs">
                                        <div class="flex justify-between">
                                            <span class="text-slate-400">Applied</span>
                                            <span class="text-slate-600">{{ getRelativeTime(app.application_date || app.created_at) }}</span>
                                        </div>
                                        <div v-if="app.notes" class="flex justify-between">
                                            <span class="text-slate-400">Notes</span>
                                            <span class="text-slate-600 truncate max-w-[120px]">{{ app.notes }}</span>
                                        </div>
                                    </div>
                                    <div v-if="app.cover_letter" class="flex items-center gap-1 text-slate-400 text-[10px]">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                        </svg>
                                        <span class="truncate">Cover letter attached</span>
                                    </div>
                                </div>

                                <!-- Card Footer Actions -->
                                <div class="px-4 py-2.5 border-t border-slate-100 bg-slate-50/50 flex items-center gap-2">
                                    <button
                                        @click="viewApplicantProfile(app)"
                                        class="flex-1 px-3 py-1.5 bg-white border border-slate-200 hover:border-blue-300 hover:bg-blue-50 text-slate-600 hover:text-blue-700 rounded-lg text-[10px] font-semibold transition-all duration-200 text-center shadow-sm hover:shadow"
                                        :disabled="isLoading"
                                    >
                                        <span v-if="isLoading && loadingId === cardKey(app)" class="inline-flex items-center gap-1">
                                            <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Loading...
                                        </span>
                                        <span v-else class="inline-flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                            View
                                        </span>
                                    </button>

                                    <!-- Status-specific actions -->
                                    <div v-if="app.status === 'Submitted' || app.status === 'Screening'" class="flex-1">
                                        <button
                                            @click="openScreeningModal(app)"
                                            class="w-full px-3 py-1.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-lg text-[10px] font-semibold transition-all duration-200 shadow-sm hover:shadow"
                                        >
                                            <span class="inline-flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                                                </svg>
                                                {{ app.status === 'Screening' ? 'Continue' : 'Screen' }}
                                            </span>
                                        </button>
                                    </div>
                                    <div v-else-if="app.status === 'Shortlisted'" class="flex-1 text-center">
                                        <span class="text-xs text-amber-600 font-medium inline-flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
                                            </svg>
                                            Shortlisted
                                        </span>
                                    </div>
                                    <div v-else-if="app.status === 'Hired'" class="flex-1 text-center">
                                        <span class="text-xs text-emerald-600 font-medium inline-flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Hired
                                        </span>
                                    </div>
                                    <div v-else-if="app.status === 'Rejected'" class="flex-1 text-center">
                                        <span class="text-xs text-rose-600 font-medium inline-flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Rejected
                                        </span>
                                    </div>

                                    <!-- More Actions Dropdown (hidden at the interview stage, once shortlisted, and once rejected) -->
                                    <div v-if="!isInterviewStatus(app.status) && app.status !== 'Rejected' && app.status !== 'Shortlisted'" class="relative">
                                        <button 
                                            @click.stop="toggleMenu(cardKey(app))" 
                                            class="p-1.5 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-slate-600 transition-all"
                                            title="More"
                                        >
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                                            </svg>
                                        </button>
                                        <div 
                                            v-if="activeMenu === cardKey(app)" 
                                            class="absolute right-0 top-full mt-1 w-48 bg-white border border-slate-200 rounded-xl shadow-2xl z-50 py-1"
                                            @click.stop
                                        >
                                            <button 
                                                @click="editApplication(app)" 
                                                class="w-full text-left px-4 py-2 text-xs text-slate-600 hover:bg-slate-50 flex items-center gap-2 transition-colors"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                </svg>
                                                Edit
                                            </button>
                                            <button
                                                v-if="app.status === 'Screening'"
                                                @click="quickShortlist(app)"
                                                class="w-full text-left px-4 py-2 text-xs text-purple-600 hover:bg-purple-50 flex items-center gap-2 transition-colors"
                                            >
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
                                                </svg>
                                                Shortlist
                                            </button>
                                            <button 
                                                v-if="app.status !== 'Rejected' && app.status !== 'Hired'"
                                                @click="openRejectModal(app)" 
                                                class="w-full text-left px-4 py-2 text-xs text-rose-600 hover:bg-rose-50 flex items-center gap-2 transition-colors"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                Reject
                                            </button>
                                            <button
                                                @click="openScreeningModal(app)"
                                                class="w-full text-left px-4 py-2 text-xs text-blue-600 hover:bg-blue-50 flex items-center gap-2 transition-colors"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                                                </svg>
                                                {{ app.status === 'Screening' ? 'Continue Screening' : 'Start Screening' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="text-center py-16">
                            <div class="w-20 h-20 rounded-2xl bg-slate-50/80 border border-slate-200 flex items-center justify-center mx-auto mb-4">
                                <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-800 mb-2">No Applications Found</h3>
                            <p class="text-sm text-slate-500 mb-6">Start adding applicants to build your talent pipeline.</p>
                            <button
                                @click="openModal('create')"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-lg hover:shadow-xl hover:shadow-blue-500/25 hover:-translate-y-0.5"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                </svg>
                                Add Application
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-3 text-xs text-slate-400 shrink-0">
                    Showing {{ filteredApplications ? filteredApplications.length : 0 }} {{ filteredApplications && filteredApplications.length === 1 ? 'application' : 'applications' }}
                </div>

                <!-- ============================================ -->
                <!-- ✅ HISTORY PANEL - STATUS TRAIL             -->
                <!-- ============================================ -->
                <div v-if="showHistory" class="fixed inset-0 z-50 flex justify-end bg-black/60 backdrop-blur-sm" @click.self="closeHistory">
                    <div class="bg-white w-full max-w-md h-full overflow-hidden shadow-2xl flex flex-col">
                        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between shrink-0">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">Application History</h3>
                                <p class="text-[11px] text-slate-400">Hired applicants (moved out of the main table)</p>
                            </div>
                            <button @click="closeHistory" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all">✕</button>
                        </div>
                        <div class="flex-1 overflow-y-auto p-4">
                            <div v-if="historyLoading" class="text-center py-8 text-sm text-slate-400">Loading history...</div>
                            <div v-else-if="historyRows.length" class="space-y-3">
                                <div v-for="h in historyRows" :key="cardKey(h)" class="bg-white rounded-xl border border-slate-200/60 overflow-hidden">
                                    <div class="p-3 border-b border-slate-100 bg-gradient-to-r from-slate-50/50 to-transparent">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <img
                                                    v-if="getApplicantPhoto(h)"
                                                    :src="getApplicantPhoto(h)"
                                                    :alt="`${h.first_name || ''} ${h.last_name || ''}`"
                                                    class="w-9 h-9 rounded-xl object-cover shrink-0"
                                                    @error="handleCardImageError($event, h)"
                                                />
                                                <div
                                                    v-else
                                                    :class="['w-9 h-9 rounded-xl flex items-center justify-center text-white font-bold text-xs shrink-0', getAvatarColor(h.status)]"
                                                >
                                                    {{ getInitials(h.first_name, h.last_name) }}
                                                </div>
                                                <div class="min-w-0">
                                                    <h3 class="text-xs font-bold text-slate-800 truncate">{{ h.first_name }} {{ h.last_name }}</h3>
                                                    <span class="text-[10px] text-slate-400 truncate block">{{ h.email || 'No email' }}</span>
                                                </div>
                                            </div>
                                            <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-semibold border shadow-sm shrink-0', getStatusBadgeClass(h.status)]">
                                                <span class="w-1.5 h-1.5 rounded-full" :class="getStatusDotClass(h.status)"></span>
                                                {{ h.status || 'Hired' }}
                                            </span>
                                        </div>
                                        <p class="mt-2 text-[11px] text-slate-500">
                                            Applied for: <strong class="text-slate-700">{{ h.job_posting?.title || 'N/A' }}</strong>
                                        </p>
                                    </div>
                                    <div class="p-3 space-y-1.5 text-[11px]">
                                        <div class="flex justify-between gap-2">
                                            <span class="text-slate-400">Email</span>
                                            <span class="font-semibold text-slate-700 truncate">{{ h.email || 'N/A' }}</span>
                                        </div>
                                        <div class="flex justify-between gap-2">
                                            <span class="text-slate-400">Phone</span>
                                            <span class="font-semibold text-slate-700">{{ h.phone || 'N/A' }}</span>
                                        </div>
                                        <div class="flex justify-between gap-2">
                                            <span class="text-slate-400">Applied</span>
                                            <span class="text-slate-600">{{ getRelativeTime(h.application_date || h.created_at) }}</span>
                                        </div>
                                    </div>
                                    <div class="px-3 py-2.5 border-t border-slate-100 bg-slate-50/50">
                                        <button
                                            @click="viewHiredProfile(h)"
                                            class="w-full px-3 py-1.5 bg-white border border-slate-200 hover:border-blue-300 hover:bg-blue-50 text-slate-600 hover:text-blue-700 rounded-lg text-[10px] font-semibold transition-all text-center shadow-sm"
                                            :disabled="isLoading"
                                        >
                                            View
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-center py-8 text-sm text-slate-400">No hired applicants yet.</p>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- ✅ VIEW MODAL - WIDE                          -->
                <!-- ============================================ -->
                <div v-if="showViewModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeViewModal">
                    <div class="bg-white rounded-2xl border border-slate-200/60 w-full max-w-7xl max-h-[95vh] overflow-hidden shadow-2xl animate-modalIn flex flex-col">
                        <!-- Modal Header -->
                        <div class="px-6 py-5 border-b border-slate-200 bg-white flex items-start justify-between shrink-0">
                            <div class="flex items-center gap-4">
                                <div class="relative">
                                    <img
                                        v-if="viewData?.personal?.profile_photo_path"
                                        :src="viewData.personal.profile_photo_path"
                                        :alt="viewData?.personal?.first_name"
                                        class="w-16 h-16 rounded-xl object-cover ring-4 ring-slate-100 border border-slate-200"
                                        @error="handleImageError"
                                    />
                                    <div
                                        v-else
                                        class="w-16 h-16 rounded-xl bg-slate-800 border border-slate-800 flex items-center justify-center text-white font-bold text-2xl"
                                    >
                                        {{ getViewInitials() }}
                                    </div>
                                    <span class="absolute -bottom-1 -right-1 px-2 py-0.5 bg-amber-500 text-slate-900 text-[10px] font-semibold rounded-full shadow">
                                        #{{ viewData?.application?.id }}
                                    </span>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Applicant Profile</p>
                                    <h2 class="text-2xl font-bold text-slate-900 tracking-tight">{{ getViewFullName() }}</h2>
                                    <p class="text-slate-500 text-sm mt-0.5">{{ viewData?.personal?.email || 'No email' }}<span v-if="viewData?.job_posting?.title"> · {{ viewData.job_posting.title }}</span></p>
                                    <div class="flex flex-wrap items-center gap-3 mt-2">
                                        <span
                                            :class="[
                                                'inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold border shadow-sm',
                                                getStatusBadgeClass(viewData?.application?.status)
                                            ]"
                                        >
                                            <span class="w-1.5 h-1.5 rounded-full" :class="getStatusDotClass(viewData?.application?.status)"></span>
                                            {{ viewData?.application?.status || 'Submitted' }}
                                        </span>
                                        <span class="text-xs text-slate-400">
                                            Applied: {{ formatDate(viewData?.application?.application_date) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <button @click="closeViewModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all duration-200">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Modal Body -->
                        <div class="flex-1 overflow-y-auto p-6">
                            <div v-if="isLoadingProfile" class="flex items-center justify-center py-12">
                                <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="ml-3 text-sm text-slate-500">Loading applicant profile...</span>
                            </div>

                            <div v-else>
                                <!-- Quick Info Cards -->
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-6">
                                    <div class="p-3 bg-slate-50/80 rounded-xl border border-slate-100">
                                        <span class="text-[10px] text-slate-400 uppercase tracking-wider">Position</span>
                                        <p class="text-sm font-semibold text-slate-800">{{ viewData?.job_posting?.title || 'N/A' }}</p>
                                    </div>
                                    <div class="p-3 bg-slate-50/80 rounded-xl border border-slate-100">
                                        <span class="text-[10px] text-slate-400 uppercase tracking-wider">Department</span>
                                        <p class="text-sm font-semibold text-slate-800">{{ viewData?.job_posting?.department || 'N/A' }}</p>
                                    </div>
                                    <div class="p-3 bg-slate-50/80 rounded-xl border border-slate-100">
                                        <span class="text-[10px] text-slate-400 uppercase tracking-wider">Employment Type</span>
                                        <p class="text-sm font-semibold text-slate-800">{{ viewData?.job_posting?.employment_type || 'N/A' }}</p>
                                    </div>
                                </div>

                                <!-- Tabs -->
                                <div class="flex flex-wrap gap-1 mb-4 bg-slate-50/80 rounded-xl border border-slate-200/60 p-1">
                                    <button
                                        v-for="tab in viewTabs"
                                        :key="tab.id"
                                        @click="viewActiveTab = tab.id"
                                        class="px-4 py-2 rounded-lg text-xs font-semibold transition-all duration-200"
                                        :class="viewActiveTab === tab.id 
                                            ? 'bg-gradient-to-r from-blue-600 to-blue-700 text-white shadow-lg shadow-blue-500/25' 
                                            : 'text-slate-600 hover:bg-white'"
                                    >
                                        {{ tab.label }}
                                    </button>
                                </div>

                                <!-- Tab Content -->
                                <div class="bg-slate-50/80 rounded-xl border border-slate-200/60 p-4">
                                    <!-- Personal Information -->
                                    <div v-if="viewActiveTab === 'personal'">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">First Name</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.personal?.first_name || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Middle Name</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.personal?.middle_name || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Last Name</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.personal?.last_name || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Suffix</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.personal?.suffix || 'None' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Email</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.personal?.email || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Phone</span><p class="text-sm font-semibold text-slate-800">{{ formatPhone(viewData?.personal?.phone) }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Date of Birth</span><p class="text-sm font-semibold text-slate-800">{{ formatDate(viewData?.personal?.birth_date) }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Place of Birth</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.personal?.place_of_birth || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Gender</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.personal?.gender || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Civil Status</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.personal?.civil_status || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Citizenship</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.personal?.citizenship || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Religion</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.personal?.religion || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Weight (kg)</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.personal?.weight || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Height (cm)</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.personal?.height || 'N/A' }}</p></div>
                                        </div>
                                    </div>

                                    <!-- Address -->
                                    <div v-if="viewActiveTab === 'address'">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Street</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.address?.street || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Barangay</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.address?.barangay || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">City</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.address?.city || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Province</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.address?.province || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">ZIP Code</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.address?.zip_code || 'N/A' }}</p></div>
                                        </div>
                                    </div>

                                    <!-- Education -->
                                    <div v-if="viewActiveTab === 'education'">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Highest Education</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.education?.highest_education || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">School / University</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.education?.school || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Course / Program</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.education?.course || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Graduation Year</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.education?.graduation_year || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Elementary School</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.education?.elementary_school || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Elementary Year</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.education?.elementary_year || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">High School</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.education?.high_school || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">High School Year</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.education?.high_year || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">College</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.education?.college || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">College Year</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.education?.college_year || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Vocational</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.education?.vocational || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Vocational Year</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.education?.vocational_year || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Special Skills</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.education?.special_skills || 'N/A' }}</p></div>
                                        </div>
                                    </div>

                                    <!-- Work Experience -->
                                    <div v-if="viewActiveTab === 'experience'">
                                        <div v-if="viewData?.employment?.work_experience && viewData.employment.work_experience.length > 0">
                                            <div v-for="(exp, idx) in viewData.employment.work_experience" :key="idx"
                                                class="border-b border-slate-100 pb-3 mb-3 last:border-0 last:pb-0">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                                    <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Company</span><p class="text-sm font-semibold text-slate-800">{{ exp.company || 'N/A' }}</p></div>
                                                    <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Position</span><p class="text-sm font-semibold text-slate-800">{{ exp.position || 'N/A' }}</p></div>
                                                    <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Start Date</span><p class="text-sm font-semibold text-slate-800">{{ exp.start_date ? formatDate(exp.start_date) : 'N/A' }}</p></div>
                                                    <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">End Date</span><p class="text-sm font-semibold text-slate-800">{{ exp.end_date ? formatDate(exp.end_date) : 'N/A' }}</p></div>
                                                </div>
                                                <div v-if="exp.responsibilities" class="mt-2">
                                                    <span class="text-[10px] text-slate-400 uppercase tracking-wider">Responsibilities</span>
                                                    <p class="text-sm text-slate-600 mt-1">{{ exp.responsibilities }}</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="text-center py-8 text-sm text-slate-400">No work experience records found.</div>
                                    </div>

                                    <!-- Notes -->
                                    <div v-if="viewActiveTab === 'notes'">
                                        <div class="space-y-3">
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Cover Letter</span><p class="text-sm text-slate-600 mt-1">{{ viewData?.application?.cover_letter || 'No cover letter provided.' }}</p></div>
                                            <div>
                                                <span class="text-[10px] text-slate-400 uppercase tracking-wider">Resume</span>
                                                <div v-if="viewData?.application?.resume_path" class="mt-1">
                                                    <a :href="getResumeUrl(viewData.application.resume_path)" target="_blank" class="inline-flex items-center gap-2 px-3 py-2 bg-blue-50 hover:bg-blue-100 rounded-lg text-sm font-semibold text-blue-700 transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                                        </svg>
                                                        {{ viewData.application.resume_name || getResumeFileName(viewData.application.resume_path) }}
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                                        </svg>
                                                    </a>
                                                </div>
                                                <p v-else class="text-sm text-slate-400 mt-1">No resume uploaded</p>
                                            </div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Availability Date</span><p class="text-sm font-semibold text-slate-800">{{ formatDate(viewData?.application?.availability_date) }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Notice Period</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.application?.notice_period?.replace(/_/g, ' ') || 'N/A' }}</p></div>
                                            <div v-if="viewData?.application?.rejection">
                                                <span class="text-[10px] text-slate-400 uppercase tracking-wider">Rejection Details</span>
                                                <div class="mt-1 p-3 bg-rose-50 rounded-xl border border-rose-200">
                                                    <p class="text-sm font-semibold text-rose-700">{{ viewData.application.rejection.reason || 'No reason provided' }}</p>
                                                    <p v-if="viewData.application.rejection.feedback" class="text-xs text-slate-600 mt-1">{{ viewData.application.rejection.feedback }}</p>
                                                    <p class="text-[10px] text-slate-400 mt-1">Rejected on {{ formatDate(viewData.application.rejection.rejected_at) }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Emergency Contact -->
                                    <div v-if="viewActiveTab === 'emergency'">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Emergency Name</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.emergency?.name || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Relationship</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.emergency?.relationship || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Phone</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.emergency?.phone || 'N/A' }}</p></div>
                                            <div><span class="text-[10px] text-slate-400 uppercase tracking-wider">Address</span><p class="text-sm font-semibold text-slate-800">{{ viewData?.emergency?.address || 'N/A' }}</p></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="p-4 border-t border-slate-100 bg-slate-50/80 flex items-center justify-between shrink-0">
                            <div>
                                <span class="text-xs text-slate-400">Last updated: {{ formatDate(viewData?.application?.updated_at) }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button @click="closeViewModal" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-all duration-200 shadow-sm hover:shadow-md">Close</button>
                                <button
                                    v-if="viewData?.application?.status === 'Submitted' || viewData?.application?.status === 'Screening'"
                                    @click="openScreeningModal({ id: viewData?.application?.id, status: viewData?.application?.status, job_application_id: viewData?.application?.job_application_id ?? null })"
                                    class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-lg hover:shadow-xl hover:shadow-blue-500/25 hover:-translate-y-0.5"
                                >
                                    <span class="inline-flex items-center gap-1.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                                        </svg>
                                        {{ viewData?.application?.status === 'Screening' ? 'Continue Screening' : 'Start Screening' }}
                                    </span>
                                </button>
                                <span v-if="viewData?.application?.status === 'Shortlisted'" class="px-4 py-2.5 bg-amber-500 text-white rounded-xl text-sm font-bold inline-flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
                                    </svg>
                                    Shortlisted
                                </span>
                                <span v-if="viewData?.application?.status === 'Rejected'" class="px-4 py-2.5 bg-rose-600 text-white rounded-xl text-sm font-bold inline-flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Rejected
                                </span>
                                <span v-if="viewData?.application?.status === 'Hired'" class="px-4 py-2.5 bg-emerald-600 text-white rounded-xl text-sm font-bold inline-flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Hired
                                </span>
                                <span v-if="viewData?.application?.status === 'Screening'" class="px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold inline-flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                                    </svg>
                                    Screening
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- ✅ SCREENING MODAL                           -->
                <!-- ============================================ -->
                <div v-if="showScreeningModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeScreeningModal">
                    <div class="bg-white rounded-2xl border border-slate-200/60 w-full max-w-7xl max-h-[95vh] overflow-hidden shadow-2xl animate-modalIn flex flex-col">
                        <!-- Modal Header -->
                        <div class="px-6 py-5 border-b border-slate-200 bg-white flex items-start justify-between shrink-0">
                            <div class="flex items-center gap-4">
                                <div class="relative">
                                    <img
                                        v-if="screeningData?.personal?.profile_photo_path"
                                        :src="screeningData.personal.profile_photo_path"
                                        :alt="screeningData?.personal?.first_name"
                                        class="w-14 h-14 rounded-xl object-cover ring-4 ring-slate-100 border border-slate-200"
                                        @error="handleImageError"
                                    />
                                    <div v-else class="w-14 h-14 rounded-xl bg-slate-800 border border-slate-800 flex items-center justify-center text-white font-bold text-xl">
                                        {{ screeningData?.personal?.first_name?.charAt(0) || '' }}{{ screeningData?.personal?.last_name?.charAt(0) || '' }}
                                    </div>
                                    <span class="absolute -bottom-1 -right-1 px-2 py-0.5 bg-amber-500 text-slate-900 text-[10px] font-semibold rounded-full shadow">
                                        #{{ screeningData?.application?.id }}
                                    </span>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Screening Review</p>
                                    <h2 class="text-xl font-bold text-slate-900 tracking-tight">{{ screeningData?.personal?.first_name || '' }} {{ screeningData?.personal?.middle_name || '' }} {{ screeningData?.personal?.last_name || '' }}</h2>
                                    <p class="text-slate-500 text-sm mt-0.5">{{ screeningData?.personal?.email || 'No email' }}<span v-if="screeningData?.job_posting?.title"> · {{ screeningData.job_posting.title }}</span></p>
                                    <div class="flex flex-wrap items-center gap-3 mt-2">
                                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold border shadow-sm bg-blue-50 text-blue-700 border-blue-200">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                                            </svg>
                                            Screening
                                        </span>
                                        <span class="text-xs text-slate-400">
                                            Applied: {{ formatDate(screeningData?.application?.application_date) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <button @click="closeScreeningModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all duration-200">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Modal Body - Three Columns -->
                        <div class="flex-1 overflow-hidden flex flex-col md:flex-row">
                            <!-- LEFT COLUMN: Applicant Information -->
                            <div class="w-full md:w-2/5 overflow-y-auto p-4 border-r border-slate-100">
                                <div class="space-y-4">
                                    <!-- Resume (viewable) -->
                                    <div>
                                        <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                            </svg>
                                            Resume
                                        </h3>
                                        <div class="bg-slate-50/80 rounded-xl border border-slate-100 p-3">
                                            <div v-if="screeningData?.application?.resume_path">
                                                <a :href="getResumeUrl(screeningData.application.resume_path)" target="_blank" class="inline-flex items-center gap-2 px-3 py-2 bg-blue-50 hover:bg-blue-100 rounded-lg text-sm font-semibold text-blue-700 transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                                    </svg>
                                                    {{ screeningData.application.resume_name || getResumeFileName(screeningData.application.resume_path) }}
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                                                    </svg>
                                                </a>
                                            </div>
                                            <p v-else class="text-sm text-slate-400 text-center py-2">No resume uploaded</p>
                                        </div>
                                    </div>
                                    <!-- Personal Information -->
                                    <div>
                                        <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                            </svg>
                                            Personal Information
                                        </h3>
                                        <div class="bg-slate-50/80 rounded-xl border border-slate-100 p-3 space-y-2">
                                            <div class="grid grid-cols-2 gap-2 text-sm">
                                                <div><span class="text-slate-400">Full Name</span></div>
                                                <div class="font-semibold">{{ screeningData?.personal?.first_name || '' }} {{ screeningData?.personal?.middle_name || '' }} {{ screeningData?.personal?.last_name || '' }}</div>
                                                <div><span class="text-slate-400">Email</span></div>
                                                <div class="font-semibold">{{ screeningData?.personal?.email || 'N/A' }}</div>
                                                <div><span class="text-slate-400">Phone</span></div>
                                                <div class="font-semibold">{{ screeningData?.personal?.phone || 'N/A' }}</div>
                                                <div><span class="text-slate-400">Date of Birth</span></div>
                                                <div class="font-semibold">{{ formatDate(screeningData?.personal?.birth_date) }}</div>
                                                <div><span class="text-slate-400">Place of Birth</span></div>
                                                <div class="font-semibold">{{ screeningData?.personal?.place_of_birth || 'N/A' }}</div>
                                                <div><span class="text-slate-400">Gender</span></div>
                                                <div class="font-semibold">{{ screeningData?.personal?.gender || 'N/A' }}</div>
                                                <div><span class="text-slate-400">Civil Status</span></div>
                                                <div class="font-semibold">{{ screeningData?.personal?.civil_status || 'N/A' }}</div>
                                                <div><span class="text-slate-400">Citizenship</span></div>
                                                <div class="font-semibold">{{ screeningData?.personal?.citizenship || 'N/A' }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Address -->
                                    <div>
                                        <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                                            </svg>
                                            Address
                                        </h3>
                                        <div class="bg-slate-50/80 rounded-xl border border-slate-100 p-3 space-y-2">
                                            <div class="grid grid-cols-2 gap-2 text-sm">
                                                <div><span class="text-slate-400">Street</span></div>
                                                <div class="font-semibold">{{ screeningData?.address?.street || 'N/A' }}</div>
                                                <div><span class="text-slate-400">Barangay</span></div>
                                                <div class="font-semibold">{{ screeningData?.address?.barangay || 'N/A' }}</div>
                                                <div><span class="text-slate-400">City</span></div>
                                                <div class="font-semibold">{{ screeningData?.address?.city || 'N/A' }}</div>
                                                <div><span class="text-slate-400">Province</span></div>
                                                <div class="font-semibold">{{ screeningData?.address?.province || 'N/A' }}</div>
                                                <div><span class="text-slate-400">ZIP Code</span></div>
                                                <div class="font-semibold">{{ screeningData?.address?.zip_code || 'N/A' }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Education -->
                                    <div>
                                        <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.968 50.968 0 0112 17.606a50.968 50.968 0 01-7.74-7.459z"/>
                                            </svg>
                                            Education
                                        </h3>
                                        <div class="bg-slate-50/80 rounded-xl border border-slate-100 p-3 space-y-2">
                                            <div class="grid grid-cols-2 gap-2 text-sm">
                                                <div><span class="text-slate-400">Highest Education</span></div>
                                                <div class="font-semibold">{{ screeningData?.education?.highest_education || 'N/A' }}</div>
                                                <div><span class="text-slate-400">School/University</span></div>
                                                <div class="font-semibold">{{ screeningData?.education?.school || 'N/A' }}</div>
                                                <div><span class="text-slate-400">Course/Program</span></div>
                                                <div class="font-semibold">{{ screeningData?.education?.course || 'N/A' }}</div>
                                                <div><span class="text-slate-400">Graduation Year</span></div>
                                                <div class="font-semibold">{{ screeningData?.education?.graduation_year || 'N/A' }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Work Experience -->
                                    <div>
                                        <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.114 48.114 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z"/>
                                            </svg>
                                            Work Experience
                                        </h3>
                                        <div class="bg-slate-50/80 rounded-xl border border-slate-100 p-3">
                                            <div v-if="screeningData?.employment?.work_experience && screeningData.employment.work_experience.length > 0">
                                                <div v-for="(exp, idx) in screeningData.employment.work_experience" :key="idx"
                                                    class="border-b border-slate-100 pb-3 mb-3 last:border-0 last:pb-0 last:mb-0">
                                                    <div class="grid grid-cols-2 gap-2 text-sm">
                                                        <div><span class="text-slate-400">Company</span></div>
                                                        <div class="font-semibold">{{ exp.company || 'N/A' }}</div>
                                                        <div><span class="text-slate-400">Position</span></div>
                                                        <div class="font-semibold">{{ exp.position || 'N/A' }}</div>
                                                        <div><span class="text-slate-400">Start Date</span></div>
                                                        <div class="font-semibold">{{ exp.start_date ? formatDate(exp.start_date) : 'N/A' }}</div>
                                                        <div><span class="text-slate-400">End Date</span></div>
                                                        <div class="font-semibold">{{ exp.end_date ? formatDate(exp.end_date) : 'Present' }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <p v-else class="text-sm text-slate-400 text-center py-2">No work experience records.</p>
                                        </div>
                                    </div>

                                    <!-- Emergency Contact -->
                                    <div>
                                        <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                                            </svg>
                                            Emergency Contact
                                        </h3>
                                        <div class="bg-slate-50/80 rounded-xl border border-slate-100 p-3 space-y-2">
                                            <div class="grid grid-cols-2 gap-2 text-sm">
                                                <div><span class="text-slate-400">Name</span></div>
                                                <div class="font-semibold">{{ screeningData?.emergency?.name || 'N/A' }}</div>
                                                <div><span class="text-slate-400">Relationship</span></div>
                                                <div class="font-semibold">{{ screeningData?.emergency?.relationship || 'N/A' }}</div>
                                                <div><span class="text-slate-400">Phone</span></div>
                                                <div class="font-semibold">{{ screeningData?.emergency?.phone || 'N/A' }}</div>
                                                <div><span class="text-slate-400">Address</span></div>
                                                <div class="font-semibold">{{ screeningData?.emergency?.address || 'N/A' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- CENTER COLUMN: Position Requirements -->
                            <div class="w-full md:w-1/3 overflow-y-auto p-4 border-r border-slate-100">
                                <h3 class="text-sm font-bold text-slate-800 mb-3 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Position Qualifications
                                </h3>
                                
                                <div class="space-y-3 text-sm">
                                    <div>
                                        <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Job Description</h4>
                                        <p class="text-slate-600 mt-1 whitespace-pre-wrap">{{ screeningData?.position?.description || 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Key Responsibilities</h4>
                                        <p class="text-slate-600 mt-1 whitespace-pre-wrap">{{ screeningData?.position?.responsibilities || 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Qualifications</h4>
                                        <p class="text-slate-600 mt-1 whitespace-pre-wrap">{{ screeningData?.position?.qualifications || 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Required Skills</h4>
                                        <p class="text-slate-600 mt-1 whitespace-pre-wrap">{{ screeningData?.position?.required_skills || 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- RIGHT COLUMN: Screening Checklist -->
                            <div class="w-full md:w-1/3 overflow-y-auto p-4">
                                <h3 class="text-sm font-bold text-slate-800 mb-3 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Screening Checklist
                                </h3>
                                
                                <div class="space-y-3">
                                    <div class="flex items-start gap-3 p-2 hover:bg-slate-50/80 rounded-xl transition-colors">
                                        <input type="checkbox" v-model="screeningChecks.resume_reviewed" class="mt-0.5 w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                                        <div>
                                            <label class="text-sm font-medium text-slate-700">Resume reviewed</label>
                                            <p class="text-xs text-slate-400">Verify the applicant's resume is complete and relevant</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3 p-2 hover:bg-slate-50/80 rounded-xl transition-colors">
                                        <input type="checkbox" v-model="screeningChecks.education_verified" class="mt-0.5 w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                                        <div>
                                            <label class="text-sm font-medium text-slate-700">Education verified</label>
                                            <p class="text-xs text-slate-400">Confirm the applicant meets the education requirements</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3 p-2 hover:bg-slate-50/80 rounded-xl transition-colors">
                                        <input type="checkbox" v-model="screeningChecks.work_experience_reviewed" class="mt-0.5 w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                                        <div>
                                            <label class="text-sm font-medium text-slate-700">Work experience reviewed</label>
                                            <p class="text-xs text-slate-400">Verify relevant work experience</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3 p-2 hover:bg-slate-50/80 rounded-xl transition-colors">
                                        <input type="checkbox" v-model="screeningChecks.skills_reviewed" class="mt-0.5 w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                                        <div>
                                            <label class="text-sm font-medium text-slate-700">Skills reviewed</label>
                                            <p class="text-xs text-slate-400">Verify the applicant has required skills</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3 p-2 hover:bg-slate-50/80 rounded-xl transition-colors">
                                        <input type="checkbox" v-model="screeningChecks.qualifications_met" class="mt-0.5 w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                                        <div>
                                            <label class="text-sm font-medium text-slate-700">Qualifications meet job requirements</label>
                                            <p class="text-xs text-slate-400">Overall qualifications match the position requirements</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3 p-2 hover:bg-slate-50/80 rounded-xl transition-colors">
                                        <input type="checkbox" v-model="screeningChecks.applicant_info_reviewed" class="mt-0.5 w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                                        <div>
                                            <label class="text-sm font-medium text-slate-700">Applicant information reviewed</label>
                                            <p class="text-xs text-slate-400">All applicant details have been verified</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Screening Notes -->
                                <div class="mt-4">
                                    <label class="text-xs font-semibold text-slate-600 block mb-1.5">Screening Notes</label>
                                    <textarea 
                                        v-model="screeningNotes"
                                        rows="3"
                                        class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm resize-none"
                                        placeholder="Enter internal screening notes..."
                                    ></textarea>
                                </div>

                                <!-- Decision -->
                                <div class="mt-4">
                                    <label class="text-xs font-semibold text-slate-600 block mb-1.5">Decision</label>
                                    <div class="flex gap-4 mt-2">
                                        <label class="flex items-center gap-2 cursor-pointer p-2 rounded-xl border border-transparent hover:bg-emerald-50/50 transition-colors">
                                            <input type="radio" value="passed" v-model="screeningDecision" class="w-4 h-4 text-emerald-600 border-slate-300 focus:ring-emerald-500">
                                            <span class="text-sm font-medium text-emerald-700">Pass</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer p-2 rounded-xl border border-transparent hover:bg-rose-50/50 transition-colors">
                                            <input type="radio" value="rejected" v-model="screeningDecision" class="w-4 h-4 text-rose-600 border-slate-300 focus:ring-rose-500">
                                            <span class="text-sm font-medium text-rose-700">Reject</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="mt-6">
                                    <button
                                        @click="submitScreening"
                                        :disabled="screeningSubmitting"
                                        class="w-full px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-lg hover:shadow-xl hover:shadow-blue-500/25 hover:-translate-y-0.5 disabled:opacity-50 disabled:hover:translate-y-0"
                                    >
                                        <span v-if="screeningSubmitting" class="flex items-center justify-center gap-2">
                                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Submitting...
                                        </span>
                                        <span v-else>Submit Screening</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- ✅ REJECTION MODAL                           -->
                <!-- ============================================ -->
                <div v-if="showRejectModal" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeRejectModal">
                    <div class="bg-white rounded-2xl border border-slate-200/60 w-full max-w-md max-h-[90vh] overflow-hidden shadow-2xl animate-modalIn flex flex-col">
                        <div class="p-6 border-b border-slate-100 bg-gradient-to-r from-rose-50 to-transparent flex items-center justify-between shrink-0">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-rose-100 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-rose-700">Reject Application</h3>
                                    <p class="text-xs text-slate-500 mt-0.5">Provide feedback for the applicant</p>
                                </div>
                            </div>
                            <button @click="closeRejectModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all duration-200 hover:rotate-90 transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto p-6 space-y-4">
                            <div class="flex items-center gap-3 p-3 bg-slate-50/80 rounded-xl border border-slate-100">
                                <img
                                    v-if="rejectApplicantData && getApplicantPhoto(rejectApplicantData)"
                                    :src="getApplicantPhoto(rejectApplicantData)"
                                    :alt="`${rejectApplicantData?.first_name || ''} ${rejectApplicantData?.last_name || ''}`"
                                    class="w-10 h-10 rounded-xl object-cover"
                                />
                                <div v-else class="w-10 h-10 rounded-xl bg-gradient-to-br from-slate-700 to-slate-800 flex items-center justify-center text-white font-bold text-sm">
                                    {{ getInitials(rejectApplicantData?.first_name, rejectApplicantData?.last_name) }}
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-800">{{ rejectApplicantData?.first_name }} {{ rejectApplicantData?.last_name }}</p>
                                    <p class="text-xs text-slate-400">{{ rejectApplicantData?.job_posting?.title || 'Position not specified' }}</p>
                                </div>
                            </div>

                            <div>
                                <label class="text-xs font-semibold text-slate-600 block mb-1.5">Reason for Rejection <span class="text-rose-500">*</span></label>
                                <select v-model="rejectForm.reason" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm" :class="{ 'border-rose-500 ring-2 ring-rose-200': rejectErrors.reason }">
                                    <option value="">Select a reason...</option>
                                    <option value="Not qualified">Not qualified for the position</option>
                                    <option value="Position filled">Position has been filled</option>
                                    <option value="Budget constraints">Budget constraints</option>
                                    <option value="Better candidate found">Better candidate found</option>
                                    <option value="Incomplete application">Incomplete application</option>
                                    <option value="Withdrawn by applicant">Withdrawn by applicant</option>
                                    <option value="Other">Other</option>
                                </select>
                                <p v-if="rejectErrors.reason" class="text-[10px] text-rose-500 mt-1">{{ rejectErrors.reason }}</p>
                            </div>

                            <div>
                                <label class="text-xs font-semibold text-slate-600 block mb-1.5">Feedback <span class="text-slate-400 font-normal">(Optional)</span></label>
                                <textarea v-model="rejectForm.feedback" rows="4" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm resize-none" placeholder="Provide detailed feedback to help the applicant improve..."></textarea>
                            </div>
                        </div>

                        <div class="p-4 border-t border-slate-100 bg-slate-50/80 flex items-center justify-end gap-3 shrink-0">
                            <button @click="closeRejectModal" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-all duration-200 shadow-sm hover:shadow-md">Cancel</button>
                            <button @click="submitRejection" :disabled="rejectSubmitting" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-lg hover:shadow-xl hover:shadow-rose-500/25 hover:-translate-y-0.5 disabled:opacity-50 disabled:hover:translate-y-0">
                                <span v-if="rejectSubmitting" class="flex items-center gap-2">
                                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Submitting...
                                </span>
                                <span v-else>Reject Application</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- ✅ CREATE/EDIT MODAL                         -->
                <!-- ============================================ -->
                <div v-if="activeModal === 'create' || activeModal === 'edit'" class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                    <div class="bg-white rounded-2xl border border-slate-200/60 w-full max-w-2xl overflow-hidden shadow-2xl max-h-[90vh] flex flex-col animate-modalIn">
                        <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-transparent shrink-0">
                            <div>
                                <h3 class="text-sm font-bold text-slate-800">{{ activeModal === 'create' ? 'Add Application' : 'Edit Application' }}</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Manage applicant information and documents.</p>
                            </div>
                            <button @click="closeModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all duration-200 hover:rotate-90 transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto p-6 space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs font-semibold text-slate-600 block mb-1.5">First Name</label>
                                    <input v-model="form.first_name" type="text" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-slate-600 block mb-1.5">Last Name</label>
                                    <input v-model="form.last_name" type="text" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-slate-600 block mb-1.5">Email</label>
                                    <input v-model="form.email" type="email" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-slate-600 block mb-1.5">Phone</label>
                                    <input v-model="form.phone" type="text" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-slate-600 block mb-1.5">Position</label>
                                    <select v-model="form.job_posting_id" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-600 focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer">
                                        <option value="">Select Position</option>
                                        <option v-for="job in jobOptions" :key="job.id" :value="job.id">{{ job.title }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-slate-600 block mb-1.5">Status</label>
                                    <select v-model="form.status" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-600 focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer">
                                        <option value="Submitted">Submitted</option>
                                        <option value="Screening">Screening</option>
                                        <option value="Shortlisted">Shortlisted</option>
                                        <option value="Interview Scheduled">Interview Scheduled</option>
                                        <option value="Initial Interview">Initial Interview</option>
                                        <option value="Final Interview">Final Interview</option>
                                        <option value="Interviewed">Interviewed</option>
                                        <option value="Selected">Selected</option>
                                        <option value="Job Offer">Job Offer</option>
                                        <option value="Offer Accepted">Offer Accepted</option>
                                        <option value="Pre-employment">Pre-employment</option>
                                        <option value="Onboard">Onboard</option>
                                        <option value="Onboarding">Onboarding</option>
                                        <option value="Hired">Hired</option>
                                        <option value="Rejected">Rejected</option>
                                        <option value="Withdrawn">Withdrawn</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-600 block mb-1.5">Notes</label>
                                <textarea v-model="form.notes" rows="3" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm resize-none" placeholder="Add notes about this applicant..."></textarea>
                            </div>
                        </div>

                        <div class="p-4 border-t border-slate-100 bg-slate-50/80 flex items-center justify-end gap-3 shrink-0">
                            <button @click="closeModal" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-all duration-200 shadow-sm hover:shadow-md">Cancel</button>
                            <button @click="submitForm" :disabled="formSaving" class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-lg hover:shadow-xl hover:shadow-blue-500/25 hover:-translate-y-0.5 disabled:opacity-50">{{ formSaving ? 'Saving...' : 'Save' }}</button>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- ✅ SUCCESS TOAST                             -->
                <!-- ============================================ -->
                <div v-if="showSuccessToast" class="fixed bottom-6 right-6 z-[100] max-w-sm w-full bg-white rounded-xl border border-slate-200/60 shadow-2xl animate-slideUp overflow-hidden">
                    <div class="flex items-start gap-4 p-4">
                        <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-bold text-slate-800">{{ successToastTitle }}</h4>
                            <p class="text-xs text-slate-500 mt-0.5">{{ successToastMessage }}</p>
                        </div>
                        <button @click="showSuccessToast = false" class="flex-shrink-0 p-1 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-slate-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- ✅ ERROR TOAST                              -->
                <!-- ============================================ -->
                <div v-if="showErrorToast" class="fixed bottom-6 right-6 z-[100] max-w-sm w-full bg-white rounded-xl border border-rose-200/60 shadow-2xl animate-slideUp overflow-hidden">
                    <div class="flex items-start gap-4 p-4">
                        <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-rose-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-bold text-rose-700">{{ errorToastTitle }}</h4>
                            <p class="text-xs text-slate-500 mt-0.5">{{ errorToastMessage }}</p>
                        </div>
                        <button @click="showErrorToast = false" class="flex-shrink-0 p-1 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-slate-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from "vue";
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

// ============================================
// TYPES
// ============================================
interface Application {
    id: number;
    // Portal job-application row id. When an applicant applied for 2+ jobs,
    // the backend emits one card per job application sharing the same
    // applicant `id` but with a distinct `job_application_id`.
    job_application_id?: number | null;
    status: string;
    created_at?: string;
    updated_at?: string;
    first_name?: string;
    middle_name?: string;
    last_name?: string;
    email?: string;
    phone?: string;
    notes?: string;
    cover_letter?: string;
    resume_path?: string;
    job_posting_id?: number | string | null;
    application_date?: string;
    job_posting?: {
        id?: number;
        title?: string;
        department?: string;
        employment_type?: string;
        description?: string;
    } | null;
    rejection?: {
        reason?: string;
        feedback?: string;
        rejected_at?: string;
    } | null;
    [key: string]: any;
}

interface ProfilePayload {
    application?: Record<string, any>;
    personal?: Record<string, any>;
    address?: Record<string, any>;
    education?: Record<string, any>;
    employment?: Record<string, any>;
    emergency?: Record<string, any>;
    position?: Record<string, any>;
    job_posting?: Record<string, any>;
    screening?: Record<string, any>;
}

interface JobOption {
    id: number;
    title: string;
}

// ============================================
// PROPS
// ============================================
const props = withDefaults(defineProps<{
    applications: Application[] | Record<string, any>;
    pagination?: Record<string, any>;
    metrics: Record<string, any>;
    filters: Record<string, any>;
    filterOptions: Record<string, any>;
    jobPostings?: JobOption[];
}>(), {
    applications: () => [],
    pagination: () => ({}),
    metrics: () => ({}),
    filters: () => ({}),
    filterOptions: () => ({}),
    jobPostings: () => [],
});

// Accept both plain arrays (new controller) and legacy Laravel paginators
// ({data:[...]}). All downstream computed use this normalized list.
const applicationList = computed<Application[]>(() => {
    const raw: any = props.applications as any;
    const arr = Array.isArray(raw) ? raw : (raw?.data ?? []);
    return (arr ?? []).filter((a: any) => a && typeof a === 'object');
});

// ============================================
// STATE
// ============================================
const pageLoading = ref(true);
const activeModal = ref<'create' | 'edit' | null>(null);
const searchQuery = ref(props.filters?.search || "");
const filterStatus = ref(props.filters?.status || "");
const filterPosition = ref("");
const viewActiveTab = ref('personal');
const showViewModal = ref(false);
const showScreeningModal = ref(false);
// History panel (status-change trail, newest first).
const showHistory = ref(false);
const historyRows = ref<any[]>([]);
const historyLoading = ref(false);
const openHistory = async () => {
    showHistory.value = true;
    historyLoading.value = true;
    try {
        const response = await axios.get('/hrm/recruitment/applications/hired');
        historyRows.value = response.data?.data ?? [];
    } catch (e) {
        historyRows.value = [];
    } finally {
        historyLoading.value = false;
    }
};
const closeHistory = () => { showHistory.value = false; };
const viewHiredProfile = (app: any) => {
    closeHistory();
    viewApplicantProfile(app);
};
const isLoading = ref(false);
const isLoadingProfile = ref(false);
const loadingId = ref<string | null>(null);
const activeMenu = ref<string | null>(null);

// Unique key per card: one card per job applied, so two cards of the same
// applicant must not share a key (job_application ids live in their own
// sequence and could numerically collide with applicant ids).
const cardKey = (app: Application): string => {
    return app.job_application_id ? `ja-${app.job_application_id}` : `a-${app.id}`;
};

// Toast State
const showSuccessToast = ref(false);
const showErrorToast = ref(false);
const successToastTitle = ref('');
const successToastMessage = ref('');
const errorToastTitle = ref('');
const errorToastMessage = ref('');

// Screening State
const screeningData = ref<ProfilePayload>({
    application: {},
    personal: {},
    address: {},
    education: {},
    employment: {},
    emergency: {},
    position: {},
    job_posting: {},
});
const screeningChecks = ref({
    resume_reviewed: false,
    education_verified: false,
    work_experience_reviewed: false,
    skills_reviewed: false,
    qualifications_met: false,
    applicant_info_reviewed: false,
});
const screeningNotes = ref('');
const screeningDecision = ref('');
const screeningSubmitting = ref(false);
const screeningApplicationId = ref<number | null>(null);
// The specific job application card being screened (null = legacy applicant-wide).
const screeningJobApplicationId = ref<number | null>(null);

// Rejection Modal State
const showRejectModal = ref(false);
const rejectApplicantData = ref<Application | null>(null);
const rejectSubmitting = ref(false);
const rejectForm = ref({
    reason: '',
    feedback: '',
});
const rejectErrors = ref({
    reason: '',
});

// View Data
const viewData = ref<ProfilePayload>({
    application: {},
    personal: {},
    address: {},
    education: {},
    employment: {},
    job_posting: {},
    emergency: {},
});

// Form
const form = ref<{
    id: number | null;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    job_posting_id: string | number | null;
    status: string;
    notes: string;
}>({
    id: null,
    first_name: "",
    last_name: "",
    email: "",
    phone: "",
    job_posting_id: "",
    status: "Submitted",
    notes: "",
});

// Tabs
const viewTabs = [
    { id: 'personal', label: 'Personal Info' },
    { id: 'address', label: 'Address' },
    { id: 'education', label: 'Education' },
    { id: 'experience', label: 'Work Experience' },
    { id: 'emergency', label: 'Emergency Contact' },
    { id: 'notes', label: 'Notes' },
];

// ============================================
// DATA
// ============================================
const statusOptions = ['Submitted', 'Screening', 'Shortlisted', 'Interview Scheduled', 'Initial Interview', 'Final Interview', 'Interviewed', 'Selected', 'Job Offer', 'Offer Accepted', 'Pre-employment', 'Onboard', 'Onboarding', 'Hired', 'Rejected', 'Withdrawn'];
// Applications already at the interview stage show only the View button
// (the "More" actions menu is hidden).
const interviewStatuses = ['Interview', 'Interview Scheduled', 'Initial Interview', 'Final Interview', 'Interviewed'];
const isInterviewStatus = (s) => interviewStatuses.includes(s);
// Position options for the Add/Edit modal, fed by the backend's
// published job postings (kept in sync if the prop updates).
const jobOptions = ref<JobOption[]>([...(props.jobPostings ?? [])]);
watch(() => props.jobPostings, (list) => { jobOptions.value = [...(list ?? [])]; });

const positionOptions = computed(() => {
    const positions = new Set<string>();
    applicationList.value.forEach(app => {
            if (app.job_posting?.title) {
                positions.add(app.job_posting.title);
            }
        });
    return Array.from(positions);
});

// ============================================
// COMPUTED
// ============================================
const metricsData = computed(() => {
    const apps = applicationList.value || [];
    return [
        { label: "Total Applications", value: apps.length, icon: "users", bg: "bg-blue-50", color: "text-blue-600", filter: null },
        { label: "Submitted", value: apps.filter(a => a.status === 'Submitted').length || 0, icon: "clipboard", bg: "bg-amber-50", color: "text-amber-600", filter: "Submitted" },
        { label: "Screening", value: apps.filter(a => a.status === 'Screening').length || 0, icon: "search", bg: "bg-indigo-50", color: "text-indigo-600", filter: "Screening" },
        { label: "Shortlisted", value: apps.filter(a => a.status === 'Shortlisted').length || 0, icon: "star", bg: "bg-purple-50", color: "text-purple-600", filter: "Shortlisted" },
    ];
});

const funnelStages = computed(() => {
    const apps = applicationList.value || [];
    return [
        { name: "Submitted", count: apps.filter(a => a.status === 'Submitted').length || 0, color: "bg-amber-50/80 text-amber-700 border border-amber-100" },
        { name: "Screening", count: apps.filter(a => a.status === 'Screening').length || 0, color: "bg-indigo-50/80 text-indigo-700 border border-indigo-100" },
        { name: "Shortlisted", count: apps.filter(a => a.status === 'Shortlisted').length || 0, color: "bg-purple-50/80 text-purple-700 border border-purple-100" },
        { name: "Interview", count: apps.filter(a => ['Interview Scheduled', 'Initial Interview', 'Final Interview', 'Interviewed'].includes(a.status)).length || 0, color: "bg-blue-50/80 text-blue-700 border border-blue-100" },
        { name: "Selected", count: apps.filter(a => a.status === 'Selected').length || 0, color: "bg-emerald-50/80 text-emerald-700 border border-emerald-100" },
        { name: "Hired", count: apps.filter(a => a.status === 'Hired').length || 0, color: "bg-emerald-100/80 text-emerald-800 border border-emerald-200" },
        { name: "Rejected", count: apps.filter(a => a.status === 'Rejected').length || 0, color: "bg-rose-50/80 text-rose-700 border border-rose-100" },
    ];
});

const filteredApplications = computed(() => {
    let apps = [...(applicationList.value || [])];
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        apps = apps.filter(a =>
            (a.first_name?.toLowerCase().includes(query) || false) ||
            (a.last_name?.toLowerCase().includes(query) || false) ||
            (a.email?.toLowerCase().includes(query) || false) ||
            (a.job_posting?.title?.toLowerCase().includes(query) || false)
        );
    }
    if (filterStatus.value) apps = apps.filter(a => a.status === filterStatus.value);
    if (filterPosition.value) apps = apps.filter(a => a.job_posting?.title === filterPosition.value);
    return apps;
});

// ============================================
// TOAST HELPERS
// ============================================
const showToast = (type: 'success' | 'error', title: string, message: string, duration: number = 5000) => {
    if (type === 'success') {
        successToastTitle.value = title;
        successToastMessage.value = message;
        showSuccessToast.value = true;
        setTimeout(() => { showSuccessToast.value = false; }, duration);
    } else if (type === 'error') {
        errorToastTitle.value = title;
        errorToastMessage.value = message;
        showErrorToast.value = true;
        setTimeout(() => { showErrorToast.value = false; }, duration);
    }
};

// ============================================
// HELPERS
// ============================================
const getInitials = (first?: string, last?: string) => {
    const f = first?.charAt(0) || '';
    const l = last?.charAt(0) || '';
    return (f + l).toUpperCase() || '?';
};

const getViewInitials = () => {
    const personal = viewData.value?.personal || {};
    const first = personal.first_name?.charAt(0) || '';
    const last = personal.last_name?.charAt(0) || '';
    return (first + last).toUpperCase() || '?';
};

const getViewFullName = () => {
    const personal = viewData.value?.personal || {};
    // Same shaping as the applicant portal profile (empty suffix is dropped).
    return [personal.first_name, personal.middle_name, personal.last_name, personal.suffix]
        .filter(Boolean).join(' ').trim() || 'N/A';
};

const getAvatarColor = (s?: string) => {
    const colors: Record<string, string> = {
        'Submitted': 'bg-gradient-to-br from-amber-500 to-amber-600',
        'Screening': 'bg-gradient-to-br from-indigo-500 to-indigo-600',
        'Shortlisted': 'bg-gradient-to-br from-purple-500 to-purple-600',
        'Interview': 'bg-gradient-to-br from-blue-500 to-blue-600',
        'Interview Scheduled': 'bg-gradient-to-br from-blue-500 to-blue-600',
        'Initial Interview': 'bg-gradient-to-br from-blue-500 to-blue-600',
        'Final Interview': 'bg-gradient-to-br from-blue-500 to-blue-600',
        'Interviewed': 'bg-gradient-to-br from-blue-500 to-blue-600',
        'Selected': 'bg-gradient-to-br from-emerald-500 to-emerald-600',
        'Hired': 'bg-gradient-to-br from-emerald-600 to-emerald-700',
        'Onboard': 'bg-gradient-to-br from-emerald-500 to-emerald-600',
        'Onboarding': 'bg-gradient-to-br from-emerald-500 to-emerald-600',
        'Rejected': 'bg-gradient-to-br from-rose-500 to-rose-600',
        'Withdrawn': 'bg-gradient-to-br from-slate-400 to-slate-500',
    };
    return colors[s ?? ''] || 'bg-gradient-to-br from-slate-500 to-slate-600';
};

const getStatusDotClass = (s?: string) => {
    const colors: Record<string, string> = {
        'Submitted': 'bg-amber-500',
        'Screening': 'bg-indigo-500',
        'Shortlisted': 'bg-purple-500',
        'Interview': 'bg-blue-500',
        'Interview Scheduled': 'bg-blue-500',
        'Initial Interview': 'bg-blue-500',
        'Final Interview': 'bg-blue-500',
        'Interviewed': 'bg-blue-500',
        'Selected': 'bg-emerald-500',
        'Hired': 'bg-emerald-600',
        'Onboard': 'bg-emerald-500',
        'Onboarding': 'bg-emerald-500',
        'Rejected': 'bg-rose-500',
        'Withdrawn': 'bg-slate-400',
    };
    return colors[s ?? ''] || 'bg-slate-400';
};

const getStatusBadgeClass = (s?: string) => {
    const colors: Record<string, string> = {
        'Submitted': 'bg-amber-50 text-amber-700 border-amber-200',
        'Screening': 'bg-indigo-50 text-indigo-700 border-indigo-200',
        'Shortlisted': 'bg-purple-50 text-purple-700 border-purple-200',
        'Interview': 'bg-blue-50 text-blue-700 border-blue-200',
        'Interview Scheduled': 'bg-blue-50 text-blue-700 border-blue-200',
        'Initial Interview': 'bg-blue-50 text-blue-700 border-blue-200',
        'Final Interview': 'bg-blue-50 text-blue-700 border-blue-200',
        'Interviewed': 'bg-blue-50 text-blue-700 border-blue-200',
        'Selected': 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'Hired': 'bg-emerald-100 text-emerald-800 border-emerald-300',
        'Onboard': 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'Onboarding': 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'Rejected': 'bg-rose-50 text-rose-700 border-rose-200',
        'Withdrawn': 'bg-slate-50 text-slate-500 border-slate-200',
    };
    return colors[s ?? ''] || 'bg-slate-50 text-slate-600 border-slate-200';
};

const formatDate = (date?: string | number | null) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

const formatPhone = (phone?: string) => {
    if (!phone) return 'N/A';
    return String(phone).replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
};

const getRelativeTime = (dateString?: string | number | null) => {
    if (!dateString) return 'Recently';
    try {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        if (diffMs < 0) return 'Just now';
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return diffMins + 'm ago';
        if (diffHours < 24) return diffHours + 'h ago';
        if (diffDays < 7) return diffDays + 'd ago';
        if (diffDays < 30) return Math.floor(diffDays / 7) + 'w ago';
        if (diffDays < 365) return Math.floor(diffDays / 30) + 'mo ago';
        return Math.floor(diffDays / 365) + 'y ago';
    } catch (e) { return 'Recently'; }
};

const getResumeFileName = (path?: string) => {
    if (!path) return '';
    return path.split('/').pop() || '';
};

const getResumeUrl = (path?: string) => {
    if (!path) return '';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    if (path.startsWith('/storage/')) return path;
    return `/storage/${path}`;
};

const handleImageError = (event: Event) => {
    const target = event.target as HTMLImageElement | null;
    if (target) target.src = '';
};

// Resolve applicant list photo: prefers backend-provided URL,
// falls back to raw storage path fields.
const getApplicantPhoto = (app: Application): string => {
    const url = (app as any)?.profile_photo_url;
    if (url) return url;
    const raw = (app as any)?.profile_photo_path || (app as any)?.image;
    if (!raw) return '';
    if (raw.startsWith('http://') || raw.startsWith('https://')) return raw;
    if (raw.startsWith('/storage/')) return raw;
    return `/storage/${raw}`;
};

// If card photo fails to load, clear it so initials avatar shows instead.
const handleCardImageError = (event: Event, app: Application) => {
    const target = event.target as HTMLImageElement | null;
    if (target) target.style.display = 'none';
    (app as any).profile_photo_url = null;
    (app as any).profile_photo_path = null;
    (app as any).image = null;
};

// ============================================
// SEARCH & FILTERS
// ============================================
let searchTimeout: ReturnType<typeof setTimeout> | undefined;
const debouncedSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 300);
};

const applyFilters = () => {
    router.get(route('hrm.recruitment.applications.index'), {
        search: searchQuery.value,
        status: filterStatus.value,
        position: filterPosition.value,
    }, { preserveState: true, replace: true });
};

const quickFilter = (status?: string | null) => {
    if (status) {
        filterStatus.value = status;
        applyFilters();
    }
};

// ============================================
// MENU FUNCTIONS
// ============================================
const toggleMenu = (key: string) => {
    if (activeMenu.value === key) {
        activeMenu.value = null;
    } else {
        activeMenu.value = key;
    }
};

// ============================================
// VIEW APPLICANT PROFILE
// ============================================
// Takes the whole card: the profile API is applicant-scoped (uses app.id),
// while the loading spinner tracks the unique card key so only the clicked
// card spins when an applicant has 2+ application cards.
const viewApplicantProfile = async (app: Application) => {
    const applicantId = app.id;
    isLoading.value = true;
    loadingId.value = cardKey(app);

    try {
        // Pass the card's job application so the modal shows that specific
        // application's status / cover letter / dates / job posting.
        const params = app.job_application_id ? { job_application_id: app.job_application_id } : {};
        const response = await axios.get(`/hrm/recruitment/applications/${applicantId}/profile`, { params });
        viewData.value = response.data;
        // Show which job this card is for when the applicant has 2+ applications.
        if (app.job_posting && !viewData.value.job_posting) {
            viewData.value.job_posting = app.job_posting;
        }
        showViewModal.value = true;
        viewActiveTab.value = 'personal';
        document.body.style.overflow = 'hidden';
    } catch (error: any) {
        console.error('Failed to load profile:', error);
        showToast('error', 'Profile Load Error', 'Failed to load applicant profile. Please try again.');
    } finally {
        isLoading.value = false;
        loadingId.value = null;
    }
};

const closeViewModal = () => {
    showViewModal.value = false;
    viewData.value = {
        application: {},
        personal: {},
        address: {},
        education: {},
        employment: {},
        job_posting: {},
        emergency: {},
    };
    document.body.style.overflow = '';
};

// ============================================
// SCREENING MODAL FUNCTIONS
// ============================================
const openScreeningModal = async (app: Application) => {
    const applicationId = app.id;
    screeningApplicationId.value = applicationId;
    screeningJobApplicationId.value = app.job_application_id ?? null;
    screeningSubmitting.value = false;
    screeningChecks.value = {
        resume_reviewed: false,
        education_verified: false,
        work_experience_reviewed: false,
        skills_reviewed: false,
        qualifications_met: false,
        applicant_info_reviewed: false,
    };
    screeningNotes.value = '';
    screeningDecision.value = '';

    try {
        // Only this card's job application moves — sibling applications of
        // the same applicant keep their own statuses.
        const matchesCard = (a: Application) =>
            screeningJobApplicationId.value
                ? a.job_application_id === screeningJobApplicationId.value
                : a.id === applicationId;
        const targetCard = applicationList.value.find(matchesCard);
        const alreadyScreening = targetCard?.status === 'Screening';
        const jaParam = screeningJobApplicationId.value
            ? { job_application_id: screeningJobApplicationId.value }
            : {};
        let profile;

        if (alreadyScreening) {
            const response = await axios.get(`/hrm/recruitment/applications/${applicationId}/profile`, { params: jaParam });
            profile = response.data;
        } else {
            await axios.post(`/hrm/recruitment/applications/${applicationId}/start-screening`, jaParam);
            const response = await axios.get(`/hrm/recruitment/applications/${applicationId}/profile`, { params: jaParam });
            profile = response.data;
            if (targetCard) targetCard.status = 'Screening';
        }

        screeningData.value = profile;

        if (profile?.screening) {
            screeningChecks.value = {
                resume_reviewed: !!profile.screening.resume_reviewed,
                education_verified: !!profile.screening.education_verified,
                work_experience_reviewed: !!profile.screening.work_experience_reviewed,
                skills_reviewed: !!profile.screening.skills_reviewed,
                qualifications_met: !!profile.screening.qualifications_met,
                applicant_info_reviewed: !!profile.screening.applicant_info_reviewed,
            };
            if (profile.screening.notes) screeningNotes.value = profile.screening.notes;
            if (profile.screening.result === 'passed' || profile.screening.result === 'rejected') {
                screeningDecision.value = profile.screening.result;
            }
        }
        
        showScreeningModal.value = true;
        document.body.style.overflow = 'hidden';
        activeMenu.value = null;
    } catch (error: any) {
        console.error('Failed to start screening:', error);
        if (error.response) {
            showToast('error', 'Screening Error', error.response.data?.message || 'Failed to start screening. Please try again.');
        } else {
            showToast('error', 'Screening Error', 'Failed to start screening. Please check your connection.');
        }
    }
};

const closeScreeningModal = () => {
    showScreeningModal.value = false;
    screeningData.value = { application: {}, personal: {}, address: {}, education: {}, employment: {}, emergency: {}, position: {}, job_posting: {} };
    screeningApplicationId.value = null;
    screeningJobApplicationId.value = null;
    document.body.style.overflow = '';
};

const submitScreening = async () => {
    if (!screeningDecision.value) {
        showToast('error', 'Validation Error', 'Please select a decision (Pass or Reject) before submitting.');
        return;
    }

    const anyChecked = Object.values(screeningChecks.value).some(v => v === true);
    if (!anyChecked) {
        showToast('error', 'Validation Error', 'Please review at least one checklist item before submitting.');
        return;
    }

    screeningSubmitting.value = true;

    try {
        const payload = {
            resume_reviewed: screeningChecks.value.resume_reviewed,
            education_verified: screeningChecks.value.education_verified,
            work_experience_reviewed: screeningChecks.value.work_experience_reviewed,
            skills_reviewed: screeningChecks.value.skills_reviewed,
            qualifications_met: screeningChecks.value.qualifications_met,
            applicant_info_reviewed: screeningChecks.value.applicant_info_reviewed,
            result: screeningDecision.value,
            notes: screeningNotes.value,
            job_application_id: screeningJobApplicationId.value,
        };

        const response = await axios.post(
            `/hrm/recruitment/applications/${screeningApplicationId.value}/screening`,
            payload
        );

        // Backend moves ONLY the acted job application — sibling applications
        // of the same applicant keep their statuses. Update just that card.
        const nextStatus = screeningDecision.value === 'passed' ? 'Shortlisted' : 'Rejected';
        const screenedCard = screeningJobApplicationId.value
            ? applicationList.value.find(a => a.job_application_id === screeningJobApplicationId.value)
            : applicationList.value.find(a => a.id === screeningApplicationId.value);
        if (screenedCard) screenedCard.status = response.data.status || nextStatus;

        closeScreeningModal();
        closeViewModal();
        
        const message = response.data.message || (screeningDecision.value === 'passed' 
            ? 'Applicant passed screening and has been shortlisted!' 
            : 'Applicant has been rejected.');
        
        showToast('success', 'Screening Submitted', message);

    } catch (error: any) {
        console.error('Failed to submit screening:', error);
        if (error.response) {
            showToast('error', 'Screening Failed', error.response.data?.message || 'Please try again.');
        } else {
            showToast('error', 'Screening Failed', 'No response from server. Please check your connection.');
        }
    } finally {
        screeningSubmitting.value = false;
    }
};

// ============================================
// REJECTION FUNCTIONS
// ============================================
const openRejectModal = (app: Application) => {
    rejectApplicantData.value = app;
    rejectForm.value = { reason: '', feedback: '' };
    rejectErrors.value = { reason: '' };
    showRejectModal.value = true;
    document.body.style.overflow = 'hidden';
    activeMenu.value = null;
};

const closeRejectModal = () => {
    showRejectModal.value = false;
    rejectApplicantData.value = null;
    rejectForm.value = { reason: '', feedback: '' };
    rejectErrors.value = { reason: '' };
    document.body.style.overflow = '';
};

const submitRejection = () => {
    if (!rejectForm.value.reason) {
        rejectErrors.value.reason = 'Please select a reason for rejection.';
        return;
    }
    rejectErrors.value.reason = '';
    rejectSubmitting.value = true;
    const rejectTarget = rejectApplicantData.value;
    if (!rejectTarget) return;
    const applicationId = rejectTarget.id;

    axios.post(`/hrm/recruitment/applications/${applicationId}/reject`, {
        reason: rejectForm.value.reason,
        feedback: rejectForm.value.feedback,
        stage: 'screening',
        job_application_id: rejectTarget.job_application_id ?? null,
    })
    .then((response) => {
        rejectSubmitting.value = false;
        closeRejectModal();
        // Backend rejects ONLY the acted job application — the applicant's
        // other applications keep their statuses. Update just that card.
        const rejectedCard = rejectTarget.job_application_id
            ? applicationList.value.find(a => a.job_application_id === rejectTarget.job_application_id)
            : applicationList.value.find(a => a.id === applicationId);
        if (rejectedCard) {
            rejectedCard.status = 'Rejected';
            rejectedCard.rejection = {
                reason: rejectForm.value.reason,
                feedback: rejectForm.value.feedback,
                rejected_at: new Date().toISOString(),
            };
        }
        if (viewData.value?.application?.id === applicationId) {
            viewData.value.application.status = 'Rejected';
            viewData.value.application.rejection = {
                reason: rejectForm.value.reason,
                feedback: rejectForm.value.feedback,
                rejected_at: new Date().toISOString(),
            };
            closeViewModal();
        }
        showToast('success', 'Rejection Submitted', 'Application rejected successfully.');
    })
    .catch((error: any) => {
        rejectSubmitting.value = false;
        console.error('Failed to reject application:', error);
        const errorMessage = error.response?.data?.message || 'Please try again.';
        showToast('error', 'Rejection Failed', errorMessage);
    });
};

// ============================================
// QUICK SHORTLIST (Screening -> Shortlisted, this card only)
// ============================================
const quickShortlist = async (app: Application) => {
    activeMenu.value = null;
    try {
        const payload = app.job_application_id ? { job_application_id: app.job_application_id } : {};
        const response = await axios.post(`/hrm/recruitment/applications/${app.id}/shortlist`, payload);
        const target = app.job_application_id
            ? applicationList.value.find(a => a.job_application_id === app.job_application_id)
            : applicationList.value.find(a => a.id === app.id);
        if (target) target.status = response.data.status || 'Shortlisted';
        showToast('success', 'Shortlisted', 'Applicant shortlisted successfully.');
    } catch (error: any) {
        console.error('Failed to shortlist:', error);
        showToast('error', 'Shortlist Failed', error.response?.data?.message || 'Please try again.');
    }
};

// ============================================
// EDIT APPLICATION
// ============================================
const editApplication = (app: Application) => {
    activeMenu.value = null;
    openModal('edit', app);
};

// ============================================
// MODAL FUNCTIONS
// ============================================
const openModal = (type: 'create' | 'edit', data: Application | null = null) => {
    viewActiveTab.value = 'personal';
    if (type === 'create') {
        activeModal.value = 'create';
        form.value = {
            id: null,
            first_name: "",
            last_name: "",
            email: "",
            phone: "",
            job_posting_id: "",
            status: "Submitted",
            notes: "",
        };
    } else if (type === 'edit' && data) {
        activeModal.value = 'edit';
        form.value = {
            id: data.id,
            first_name: data.first_name || "",
            last_name: data.last_name || "",
            email: data.email || "",
            phone: data.phone || "",
            job_posting_id: data.job_posting_id || "",
            status: data.status || "Submitted",
            notes: data.notes || "",
        };
    }
    document.body.style.overflow = 'hidden';
};

const closeModal = () => {
    activeModal.value = null;
    document.body.style.overflow = '';
};

const formSaving = ref(false);

const submitForm = () => {
    if (!form.value.first_name || !form.value.last_name || !form.value.email) {
        showToast('error', 'Validation Error', 'First name, last name and email are required.');
        return;
    }
    formSaving.value = true;
    const payload: Record<string, any> = {
        first_name: form.value.first_name,
        last_name: form.value.last_name,
        email: form.value.email,
        phone: form.value.phone || null,
        notes: form.value.notes || null,
        status: form.value.status || 'Submitted',
    };
    if (form.value.job_posting_id) payload.job_posting_id = form.value.job_posting_id;
    const done = {
        onSuccess: () => {
            formSaving.value = false;
            closeModal();
            showToast('success', 'Application Saved', 'The application has been saved successfully.');
        },
        onError: (errors: Record<string, string>) => {
            formSaving.value = false;
            showToast('error', 'Save Failed', Object.values(errors)[0] || 'Please check your inputs and try again.');
        },
    };
    if (activeModal.value === 'edit' && form.value.id) {
        router.put(`/hrm/recruitment/applications/${form.value.id}`, payload, { preserveScroll: true, ...done });
    } else {
        router.post('/hrm/recruitment/applications', payload, { preserveScroll: true, ...done });
    }
};

// ============================================
// EXPORT CSV
// ============================================
const exportCSV = () => {
    console.log("Exporting applications to CSV...");
    showToast('success', 'Export Started', 'Your CSV export is being prepared.');
};

// ============================================
// LIFECYCLE
// ============================================
onMounted(() => {
    console.log('Applications mounted:', applicationList.value);
    pageLoading.value = true;
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
        pageLoading.value = false;
        document.body.style.overflow = '';
    }, 500);
});
</script>

<style scoped>
.bg-grid-slate-100 { background-image: radial-gradient(circle, #cbd5e1 1px, transparent 1px); background-size: 24px 24px; }
.bg-clip-text { -webkit-background-clip: text; background-clip: text; }
.backdrop-blur-sm { backdrop-filter: blur(8px); }

.animate-modalIn { animation: modalIn 0.25s ease-out; }
@keyframes modalIn {
    from { opacity: 0; transform: scale(0.95) translateY(15px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.animate-slideUp { animation: slideUp 0.3s ease-out; }
@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.fade-leave-active { transition: opacity 0.35s ease-out; }
.fade-leave-to { opacity: 0; }

.animate-spin { animation: spin 1s linear infinite; }
@keyframes spin {
    to { transform: rotate(360deg); }
}

.animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.whitespace-pre-wrap {
    white-space: pre-wrap;
    word-wrap: break-word;
}

/* Custom scrollbar */
.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}
.overflow-y-auto::-webkit-scrollbar-track {
    background: transparent;
}
.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 9999px;
}
.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>