<template>
    <Head title="Drivers Management" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gray-100 dark:bg-zinc-950">
            <div class="mx-auto max-w-7xl space-y-4 p-4 pb-16 sm:p-6">

                <!-- Theme-aware banner styled like Fleet -->
                <div data-erp-page-banner="fallback" class="relative flex flex-wrap items-center justify-between gap-5">
                    <div class="relative flex min-w-0 flex-1 items-center gap-4">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/20">
                            <Users class="h-7 w-7" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/75">Logistics · Personnel registry</p>
                            <h1 class="mt-1 text-2xl font-black tracking-tight sm:text-3xl">
                                Drivers &amp; Conductors
                                <span v-if="!canEditDrivers" class="ml-2 inline-flex rounded-full bg-white/15 px-2.5 py-1 align-middle text-[10px] font-bold uppercase tracking-wider ring-1 ring-white/20">View only</span>
                            </h1>
                            <p class="mt-1 text-sm text-white/80">{{ filteredDrivers.length }} of {{ drivers.length }} drivers shown.</p>
                        </div>
                    </div>
                    <div class="relative flex flex-wrap items-center gap-3">
                        <div class="rounded-2xl bg-white/10 px-4 py-2.5 text-center ring-1 ring-white/20">
                            <p class="text-xl font-black leading-none">{{ drivers.length }}</p>
                            <p class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-white/75">drivers</p>
                        </div>
                        <div class="rounded-2xl bg-white/10 px-4 py-2.5 text-center ring-1 ring-white/20">
                            <p class="text-xl font-black leading-none">{{ availableCount }}</p>
                            <p class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-white/75">available</p>
                        </div>
                        <button v-if="canEditDrivers" @click="openCreateModal"
                            class="flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-xs font-bold text-slate-900 shadow-lg transition hover:bg-slate-100 active:scale-95 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                            <Plus class="h-4 w-4" /> Add Driver
                        </button>
                    </div>
                </div>

                <!-- Search and availability filters -->
                <div class="flex flex-col gap-3 rounded-2xl bg-white p-4 shadow-sm dark:bg-zinc-900 sm:flex-row sm:items-center">
                    <div class="relative min-w-0 flex-1">
                        <Search class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input v-model="searchTerm" type="text" placeholder="Search by name, license, or email..."
                            class="w-full rounded-xl border border-gray-200 bg-white py-2.5 pl-10 pr-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="key in ['all','available','unavailable']" :key="key" @click="availabilityFilter = key"
                            :class="availabilityFilter === key ? 'bg-slate-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700'"
                            class="rounded-full px-4 py-2 text-xs font-semibold capitalize transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500">
                            {{ key === 'unavailable' ? 'On Trip' : key }}
                        </button>
                    </div>
                </div>

                <!-- Stats Summary -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="animate-fade-up group relative flex flex-col rounded-2xl border border-gray-100 bg-white p-5 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-indigo-800" style="animation-delay:80ms">
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
                    <div class="animate-fade-up group relative flex flex-col rounded-2xl border border-gray-100 bg-white p-5 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-emerald-800" style="animation-delay:160ms">
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
                    <div class="animate-fade-up group relative overflow-hidden rounded-2xl border border-gray-100 bg-white p-5 text-gray-900 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:border-amber-200 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:text-white" style="animation-delay:240ms">
                        <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10 blur-2xl group-hover:scale-125 transition-transform duration-500" />
                        <div class="relative flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 ring-1 ring-amber-100 transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20">
                                <Star class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Average Rating</p>
                                <h3 class="text-3xl font-black leading-none text-gray-900 dark:text-white">{{ avgRating }} ★</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Driver cards styled to match Fleet -->
                <div class="animate-fade-up overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition-shadow duration-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900" style="animation-delay:120ms">
                    <div class="flex items-center gap-2 border-b border-gray-100 bg-gray-50 p-5 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <Users class="h-5 w-5 text-indigo-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">Driver Roster</h2>
                        <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-1">{{ filteredDrivers.length }}</span>
                    </div>

                    <div v-if="filteredDrivers.length" class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 xl:grid-cols-3 sm:p-5">
                        <TransitionGroup name="card" tag="div" class="contents">
                            <article v-for="(driver, i) in filteredDrivers" :key="driver.id" :style="{ transitionDelay: `${Math.min(i * 35, 350)}ms` }"
                                :class="driverCardTheme(driver).background"
                                class="group relative flex min-h-[22rem] flex-col overflow-hidden rounded-3xl p-5 text-white shadow-lg transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl">
                                <div class="pointer-events-none absolute -right-12 -top-14 h-44 w-44 rounded-full bg-white/10 blur-2xl transition-transform duration-500 group-hover:scale-125" />
                                <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/5 via-transparent to-black/25" />

                                <div class="relative flex items-start justify-between gap-3">
                                    <span class="inline-flex items-center gap-2 rounded-full border border-white/25 bg-black/15 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider backdrop-blur-sm">
                                        <span class="h-1.5 w-1.5 rounded-full bg-white" />{{ driver.is_available ? 'Available' : 'On Trip' }}
                                    </span>
                                    <span class="rounded-xl border border-white/20 bg-black/15 px-3 py-1.5 text-center backdrop-blur-sm">
                                        <span class="block text-lg font-black leading-none">{{ driver.deliveries_count || 0 }}</span>
                                        <span class="mt-1 block text-[9px] font-semibold uppercase tracking-wider text-white/75">trips</span>
                                    </span>
                                </div>

                                <div class="relative flex flex-1 flex-col items-center justify-center py-5 text-center">
                                    <div class="relative flex h-20 w-20 items-center justify-center overflow-hidden rounded-3xl border border-white/30 bg-white/15 text-3xl font-black uppercase shadow-lg backdrop-blur-sm transition-transform duration-300 group-hover:rotate-3 group-hover:scale-105">
                                        <span>{{ driver.user?.name?.charAt(0) || '?' }}</span>
                                        <img v-if="driver.photo_url" :src="driver.photo_url" :alt="`${driver.user?.name || 'Driver'} profile photo`" class="absolute inset-0 h-full w-full object-cover" @error="$event.target.style.display = 'none'" />
                                    </div>
                                    <h3 class="mt-4 max-w-full truncate text-xl font-black tracking-tight drop-shadow">{{ driver.user?.name || 'Unnamed driver' }}</h3>
                                    <p class="mt-1 max-w-full truncate text-xs text-white/75">{{ driver.user?.email || 'No email on file' }}</p>
                                    <div class="mt-3 flex items-center gap-1.5 rounded-full border border-white/20 bg-black/15 px-3 py-1.5 backdrop-blur-sm">
                                        <Star class="h-3.5 w-3.5 fill-amber-300 text-amber-300" />
                                        <span class="text-xs font-bold">{{ Number(driver.rating || 0).toFixed(1) }}</span>
                                        <span class="text-[10px] text-white/65">/ 5</span>
                                    </div>
                                </div>

                                <div class="relative space-y-2 rounded-2xl border border-white/20 bg-black/15 p-3.5 text-xs backdrop-blur-sm">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-white/70">License</span>
                                        <span class="truncate font-mono font-semibold">{{ driver.license_number || 'Not set' }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-white/70">Phone</span>
                                        <span class="truncate font-semibold">{{ driver.user?.phone || 'Not set' }}</span>
                                    </div>
                                </div>

                                <button @click="viewDetails(driver)" :class="driverCardTheme(driver).button"
                                    class="relative mt-3 flex w-full items-center justify-center gap-2 rounded-xl py-2.5 text-xs font-bold text-white shadow-md transition active:scale-[0.98]">
                                    <Eye class="h-4 w-4" /> View driver details
                                </button>
                            </article>
                        </TransitionGroup>
                    </div>
                    <div v-else class="px-6 py-12 text-center">
                            <Users class="mx-auto h-10 w-10 text-gray-200 dark:text-zinc-700 animate-bounce-soft" />
                            <p class="mt-2 text-sm font-bold text-gray-400">No drivers found. Click "Add Driver" to register.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Driver Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeCreateModal">
                    <div class="modal-panel bg-white dark:bg-zinc-900 w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden max-h-[90vh] overflow-y-auto">
                        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-gray-100 bg-white px-6 py-4 dark:border-zinc-800 dark:bg-zinc-900">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white">Register New Driver</h3>
                            <button @click="closeCreateModal" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 dark:hover:bg-zinc-800"><X class="h-4 w-4" /></button>
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
                    <div class="modal-panel bg-white dark:bg-zinc-900 w-full max-w-3xl rounded-2xl shadow-2xl overflow-hidden max-h-[90vh] overflow-y-auto">
                        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-gray-100 bg-white px-6 py-4 dark:border-zinc-800 dark:bg-zinc-900">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white">Driver Details</h3>
                            <button @click="closeDetailModal" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 dark:hover:bg-zinc-800"><X class="h-4 w-4" /></button>
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
import { Users, Plus, Search, Eye, X, Loader2, Star, Truck, Info, CheckCircle2 } from 'lucide-vue-next';
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

const driverCardTheme = (driver) => driver.is_available
    ? {
        background: 'bg-gradient-to-br from-[#386641] via-[#2d6a4f] to-[#173b2c]',
        button: 'bg-[#24563f] hover:bg-[#1b4332]',
    }
    : {
        background: 'bg-gradient-to-br from-[#52647f] via-[#394b66] to-[#202d42]',
        button: 'bg-[#34445f] hover:bg-[#26364f]',
    };

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
    selectedDriver.value = driver;
    showDetailModal.value = true;
    try {
        const response = await fetch(route('logistics.drivers.show', driver.id), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error(`Driver details request failed (${response.status}).`);
        const data = await response.json();
        if (!data.driver) throw new Error('Driver details were not returned.');
        selectedDriver.value = {
            ...data.driver,
            deliveries: Array.isArray(data.deliveries)
                ? data.deliveries
                : (Array.isArray(data.driver.deliveries) ? data.driver.deliveries : []),
        };
    } catch (error) {
        showDetailModal.value = false;
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
