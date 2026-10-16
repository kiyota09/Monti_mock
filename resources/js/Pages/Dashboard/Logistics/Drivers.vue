<template>
    <Head title="Drivers Management" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <Users class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> Logistics · Personnel Registry
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Drivers &amp; Conductors <span v-if="!canEditDrivers" class="ml-2 inline-block rounded-full bg-amber-400/90 px-3 py-1 align-middle text-xs font-black uppercase tracking-widest text-amber-950">View only</span></h1>
                            <p class="text-sm text-blue-100/90">{{ filteredDrivers.length }} of {{ drivers.length }} driver{{ drivers.length !== 1 ? 's' : '' }} showing</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse" /> {{ availableCount }} available
                            </span>
                            <button v-if="canEditDrivers" @click="openCreateModal"
                                class="rounded-full bg-white px-4 py-2 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:bg-indigo-50 hover:scale-105 active:scale-95 transition-all flex items-center gap-1.5">
                                <Plus class="h-4 w-4" /> Add Driver
                            </button>
                        </div>
                    </div>

                    <!-- Search + filters -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchTerm" type="text" placeholder="Search by name, license, or email..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <div class="flex gap-2">
                            <button v-for="key in ['all','available','unavailable']" :key="key" @click="availabilityFilter = key"
                                :class="availabilityFilter === key ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                                class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                                {{ key === 'unavailable' ? 'On Trip' : key }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats Summary -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:80ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 via-blue-600 to-cyan-600 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Users class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Total Drivers</p>
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ drivers.length }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:160ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-emerald-400/20 to-teal-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-emerald-500 to-teal-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <CheckCircle2 class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-1.5">Available <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" /></p>
                                <h3 class="text-3xl font-black text-emerald-600 tracking-tight">{{ availableCount }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative overflow-hidden rounded-3xl bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 p-5 text-white shadow-lg shadow-orange-500/20 hover:shadow-2xl hover:-translate-y-1.5 transition-all duration-300" style="animation-delay:240ms">
                        <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10 blur-2xl group-hover:scale-125 transition-transform duration-500" />
                        <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                        <div class="relative flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Star class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-amber-100">Average Rating</p>
                                <h3 class="text-3xl font-black leading-none">{{ avgRating }} ★</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Drivers Table -->
                <div class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden hover:shadow-xl transition-shadow duration-300" style="animation-delay:120ms">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                        <Users class="h-5 w-5 text-indigo-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">Driver Roster</h2>
                        <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-1">{{ filteredDrivers.length }}</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 dark:border-zinc-800">
                                <tr>
                                    <th class="px-6 py-4">Driver</th>
                                    <th class="px-6 py-4">License #</th>
                                    <th class="px-6 py-4">Contact</th>
                                    <th class="px-6 py-4 text-center">Rating</th>
                                    <th class="px-6 py-4 text-center">Status</th>
                                    <th class="px-6 py-4 text-center">Total Trips</th>
                                    <th class="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <TransitionGroup name="card" tag="tbody" class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="(driver, i) in filteredDrivers" :key="driver.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }" class="group text-sm hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 rounded-2xl bg-gradient-to-br from-indigo-500 via-purple-600 to-fuchsia-600 flex items-center justify-center text-white text-sm font-black shadow-lg uppercase group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                                {{ driver.user?.name?.charAt(0) || '?' }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-black text-gray-900 dark:text-white truncate">{{ driver.user?.name }}</p>
                                                <p class="text-[10px] text-gray-400 truncate">{{ driver.user?.email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 font-mono text-sm text-gray-500 dark:text-gray-400">{{ driver.license_number }}</td>
                                    <td class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400">{{ driver.user?.phone || '—' }}</td>
                                    <td class="px-6 py-3 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <Star class="h-3.5 w-3.5 fill-amber-400 text-amber-400" />
                                            <span class="font-bold text-gray-900 dark:text-white">{{ driver.rating }}</span>
                                            <span class="text-[10px] text-gray-400">/5</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        <span :class="driver.is_available ? 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30' : 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30'"
                                            class="px-3 py-1 rounded-full ring-1 text-[9px] font-black uppercase inline-flex items-center gap-1">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />
                                            {{ driver.is_available ? 'Available' : 'On Trip' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-center font-bold text-gray-900 dark:text-white">{{ driver.deliveries_count || 0 }}</td>
                                    <td class="px-6 py-3 text-right">
                                        <button @click="viewDetails(driver)" class="flex h-8 w-8 ml-auto items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 hover:bg-indigo-600 hover:text-white transition-all">
                                            <Eye class="h-4 w-4" />
                                        </button>
                                    </td>
                                 </tr>
                            </TransitionGroup>
                        </table>
                        <div v-if="filteredDrivers.length === 0" class="px-6 py-12 text-center">
                            <Users class="mx-auto h-10 w-10 text-gray-200 dark:text-zinc-700 animate-bounce-soft" />
                            <p class="mt-2 text-sm font-bold text-gray-400">No drivers found. Click "Add Driver" to register.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Driver Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeCreateModal">
                    <div class="modal-panel bg-white dark:bg-zinc-900 w-full max-w-2xl rounded-3xl shadow-2xl overflow-hidden max-h-[90vh] overflow-y-auto">
                        <div class="sticky top-0 z-10 relative overflow-hidden px-6 py-5 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white flex justify-between items-center">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                            <h3 class="relative font-black text-lg">Register New Driver</h3>
                            <button @click="closeCreateModal" class="relative p-1.5 hover:bg-white/20 rounded-xl transition"><X class="h-5 w-5" /></button>
                        </div>

                        <form @submit.prevent="submitDriver" class="p-6 space-y-5">
                            <!-- Personal Info -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">Full Name *</label>
                                    <input v-model="driverForm.name" type="text" required
                                        class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">Email *</label>
                                    <input v-model="driverForm.email" type="email" required
                                        class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">Phone *</label>
                                    <input v-model="driverForm.phone" type="text" required
                                        class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">License Number *</label>
                                    <input v-model="driverForm.license_number" type="text" required
                                        class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                                </div>
                            </div>

                            <!-- File Uploads -->
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">License Image</label>
                                <input type="file" @change="handleLicenseUpload" accept="image/*" class="text-sm text-gray-500 dark:text-gray-400">
                                <p class="text-[9px] text-gray-400 mt-1">Upload scanned license (JPG, PNG)</p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">Medical Certificate</label>
                                <input type="file" @change="handleMedicalUpload" accept="image/*,application/pdf" class="text-sm text-gray-500 dark:text-gray-400">
                            </div>

                            <div class="bg-amber-50 dark:bg-amber-900/20 p-4 rounded-2xl border border-amber-200 dark:border-amber-800">
                                <p class="text-xs font-medium text-amber-800 dark:text-amber-200 flex items-start gap-2">
                                    <Info class="h-4 w-4 mt-0.5 flex-shrink-0" />
                                    The driver will receive login credentials via email. Default password is "password123" – please instruct them to change upon first login.
                                </p>
                            </div>

                            <div class="flex gap-3 pt-4 border-t border-gray-100 dark:border-zinc-800">
                                <button type="button" @click="closeCreateModal"
                                    class="flex-1 px-4 py-3 border border-gray-200 dark:border-zinc-700 rounded-xl text-[10px] font-black uppercase hover:bg-gray-50 dark:hover:bg-zinc-800 transition active:scale-95">
                                    Cancel
                                </button>
                                <button v-if="canEditDrivers" type="submit" :disabled="driverForm.processing"
                                    class="flex-1 px-4 py-3 bg-indigo-600 text-white rounded-xl text-[10px] font-black uppercase hover:bg-indigo-700 transition disabled:opacity-50 flex items-center justify-center gap-2 active:scale-95">
                                    <Loader2 v-if="driverForm.processing" class="h-4 w-4 animate-spin" />
                                    Register Driver
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Driver Details Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showDetailModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeDetailModal">
                    <div class="modal-panel bg-white dark:bg-zinc-900 w-full max-w-3xl rounded-3xl shadow-2xl overflow-hidden max-h-[90vh] overflow-y-auto">
                        <div class="sticky top-0 z-10 relative overflow-hidden px-6 py-5 bg-gradient-to-br from-indigo-600 via-violet-700 to-fuchsia-700 text-white flex justify-between items-center">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                            <h3 class="relative font-black text-lg">Driver Details</h3>
                            <button @click="closeDetailModal" class="relative p-1.5 hover:bg-white/20 rounded-xl transition"><X class="h-5 w-5" /></button>
                        </div>

                        <div v-if="selectedDriver" class="p-6 space-y-6">
                            <!-- Profile Header -->
                            <div class="flex items-center gap-4 pb-4 border-b border-gray-100 dark:border-zinc-800">
                                <div class="h-16 w-16 rounded-2xl bg-gradient-to-br from-indigo-500 via-purple-600 to-fuchsia-600 flex items-center justify-center text-white text-2xl font-black shadow-lg animate-pop">
                                    {{ selectedDriver.user?.name?.charAt(0) }}
                                </div>
                                <div class="min-w-0">
                                    <h2 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">{{ selectedDriver.user?.name }}</h2>
                                    <p class="text-xs text-gray-500 truncate">{{ selectedDriver.user?.email }} | {{ selectedDriver.user?.phone }}</p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span :class="selectedDriver.is_available ? 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30' : 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30'"
                                            class="px-2 py-0.5 rounded-full ring-1 text-[9px] font-black uppercase inline-flex items-center gap-1">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />
                                            {{ selectedDriver.is_available ? 'Available' : 'On Trip' }}
                                        </span>
                                        <div class="flex items-center gap-1">
                                            <Star class="h-4 w-4 fill-amber-400 text-amber-400" />
                                            <span class="font-bold text-sm text-gray-900 dark:text-white">{{ selectedDriver.rating }} / 5</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- License & Medical -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-2xl border border-gray-100 dark:border-zinc-800">
                                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-wider">License Information</p>
                                    <p class="font-mono font-bold mt-1 text-gray-900 dark:text-white">{{ selectedDriver.license_number }}</p>
                                    <a v-if="selectedDriver.license_image" :href="'/storage/' + selectedDriver.license_image" target="_blank"
                                        class="text-xs text-indigo-600 hover:underline mt-2 inline-block">View License Image →</a>
                                </div>
                                <div class="bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-2xl border border-gray-100 dark:border-zinc-800">
                                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-wider">Medical Certificate</p>
                                    <a v-if="selectedDriver.medical_certificate" :href="'/storage/' + selectedDriver.medical_certificate" target="_blank"
                                        class="text-xs text-indigo-600 hover:underline mt-2 inline-block">View Certificate →</a>
                                    <p v-else class="text-sm text-gray-400 mt-1">Not uploaded</p>
                                </div>
                            </div>

                            <!-- Delivery History -->
                            <div>
                                <h4 class="font-black text-gray-900 dark:text-white mb-3 flex items-center gap-2 text-sm uppercase tracking-widest">
                                    <Truck class="h-4 w-4 text-indigo-500" /> Delivery History
                                </h4>
                                <div class="overflow-x-auto rounded-2xl border border-gray-100 dark:border-zinc-800">
                                    <table class="w-full text-left text-sm">
                                        <thead class="bg-gray-50 dark:bg-zinc-800/50 text-[9px] font-black uppercase text-gray-400">
                                            <tr><th class="p-3">Delivery #</th><th class="p-3">Date</th><th class="p-3">Route</th><th class="p-3">Status</th></tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50 dark:divide-zinc-800">
                                            <tr v-for="delivery in selectedDriver.deliveries" :key="delivery.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors">
                                                <td class="p-3 font-mono font-bold text-gray-900 dark:text-white">{{ delivery.delivery_number }}</td>
                                                <td class="p-3 text-gray-500 dark:text-gray-400">{{ formatDate(delivery.scheduled_departure) }}</td>
                                                <td class="p-3 text-gray-500 dark:text-gray-400">{{ delivery.route?.name || '—' }}</td>
                                                <td class="p-3">
                                                    <span :class="statusBadge(delivery.status)" class="px-2 py-0.5 rounded-full ring-1 text-[9px] font-black uppercase inline-flex items-center gap-1">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />
                                                        {{ formatStatus(delivery.status) }}
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr v-if="!selectedDriver.deliveries?.length">
                                                <td colspan="4" class="p-6 text-center text-gray-400 text-sm">No deliveries yet.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Toast Notification -->
        <Transition name="toast">
            <div v-if="toast.show" class="fixed bottom-8 right-8 z-50 px-6 py-3 rounded-2xl shadow-lg text-white font-bold text-sm"
                :class="toast.type === 'success' ? 'bg-emerald-600' : 'bg-red-600'">
                {{ toast.message }}
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Users, Plus, Search, Eye, X, Loader2, Star, Truck, Info, Sparkles, CheckCircle2 } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditDrivers = computed(() => canEdit('LOG', 'drivers'));

const props = defineProps({
    drivers: {
        type: Array,
        default: () => []
    }
});

// Filters
const searchTerm = ref('');
const availabilityFilter = ref('all');

// Modals
const showCreateModal = ref(false);
const showDetailModal = ref(false);
const selectedDriver = ref(null);
const toast = ref({ show: false, type: 'success', message: '' });

// Driver form
const driverForm = useForm({
    name: '',
    email: '',
    phone: '',
    license_number: '',
    license_image: null,
    medical_certificate: null
});

const filteredDrivers = computed(() => {
    let list = props.drivers;
    if (searchTerm.value) {
        const term = searchTerm.value.toLowerCase();
        list = list.filter(d =>
            d.user?.name?.toLowerCase().includes(term) ||
            d.license_number?.toLowerCase().includes(term) ||
            d.user?.email?.toLowerCase().includes(term)
        );
    }
    if (availabilityFilter.value === 'available') {
        list = list.filter(d => d.is_available === true);
    } else if (availabilityFilter.value === 'unavailable') {
        list = list.filter(d => d.is_available === false);
    }
    return list;
});

const availableCount = computed(() => props.drivers.filter(d => d.is_available).length);
const avgRating = computed(() => {
    if (!props.drivers.length) return 0;
    const sum = props.drivers.reduce((acc, d) => acc + (d.rating || 0), 0);
    return (sum / props.drivers.length).toFixed(1);
});

const statusBadge = (status) => {
    const map = {
        pending: 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700',
        dispatched: 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
        in_transit: 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30',
        delivered: 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30'
    };
    return map[status] || 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
};

const formatStatus = (status) => {
    const map = { pending: 'Pending', dispatched: 'Dispatched', in_transit: 'In Transit', delivered: 'Delivered' };
    return map[status] || status;
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-PH');
};

const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

const refreshData = () => {
    router.reload({ only: ['drivers'] });
};

const handleLicenseUpload = (e) => {
    driverForm.license_image = e.target.files[0];
};
const handleMedicalUpload = (e) => {
    driverForm.medical_certificate = e.target.files[0];
};

const openCreateModal = () => {
    driverForm.reset();
    showCreateModal.value = true;
};
const closeCreateModal = () => {
    showCreateModal.value = false;
};

const submitDriver = () => {
    driverForm.post(route('logistics.drivers.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showToast('success', 'Driver registered successfully.');
            closeCreateModal();
            refreshData();
        },
        onError: (errors) => {
            const errorMsg = Object.values(errors)[0] || 'Registration failed.';
            showToast('error', errorMsg);
        }
    });
};

const viewDetails = async (driver) => {
    // Fetch full details including deliveries
    try {
        const response = await fetch(route('logistics.drivers.show', driver.id));
        const data = await response.json();
        selectedDriver.value = data.driver;
        showDetailModal.value = true;
    } catch (error) {
        showToast('error', 'Failed to load driver details.');
    }
};

const closeDetailModal = () => {
    showDetailModal.value = false;
    selectedDriver.value = null;
};
</script>

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
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease; }
.modal-enter-active .modal-panel, .modal-leave-active .modal-panel { transition: transform 0.35s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-panel { opacity: 0; transform: translateY(24px) scale(0.97); }
.modal-leave-to .modal-panel { opacity: 0; transform: translateY(12px) scale(0.98); }
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }
</style>
