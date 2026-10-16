<template>
    <Head title="Route Management" />
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
                            <Navigation class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <MapPin class="h-3.5 w-3.5" /> Logistics · Navigation
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Delivery Routes <span v-if="!canEditRoutes" class="ml-2 inline-block rounded-full bg-amber-400/90 px-3 py-1 align-middle text-xs font-black uppercase tracking-widest text-amber-950">View only</span></h1>
                            <p class="text-sm text-blue-100/90">Map-driven delivery routes from MontiTextiles HQ to all business clients.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse" /> {{ routes.length }} route{{ routes.length !== 1 ? 's' : '' }}
                            </span>
                            <button v-if="canEditRoutes" @click="openCreateModal"
                                class="flex items-center gap-2 rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg transition-all duration-200 hover:scale-105 active:scale-95">
                                <Plus class="h-4 w-4" /> Add Route
                            </button>
                        </div>
                    </div>

                    <!-- Search inside hero -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchTerm" type="text" placeholder="Search routes, origin, destination, client..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <div class="flex items-center gap-2 text-[11px] font-bold text-blue-100">
                            <span class="rounded-2xl bg-white/15 px-4 py-3 ring-1 ring-white/25 backdrop-blur">{{ clients.length }} client{{ clients.length !== 1 ? 's' : '' }} pinned</span>
                        </div>
                    </div>
                </div>

                <!-- Live Route Map -->
                <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden" style="animation-delay:80ms">
                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex flex-wrap items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 via-blue-600 to-cyan-600 text-white shadow-lg"><Globe class="h-5 w-5" /></span>
                        <div class="min-w-0">
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Live Network Map</p>
                            <p class="text-sm font-black text-gray-900 dark:text-white">All Routes &amp; Client Locations</p>
                        </div>
                        <div class="ml-auto flex flex-wrap items-center gap-4 text-[10px] font-black uppercase tracking-widest text-gray-400">
                            <div class="flex items-center gap-1.5">
                                <span class="w-3 h-3 rounded-full bg-blue-500 inline-block"></span> MontiTextiles HQ
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-3 h-3 rounded-full bg-emerald-500 inline-block"></span> Client Locations
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-6 h-1 rounded-full bg-indigo-500 inline-block"></span> Delivery Routes
                            </div>
                        </div>
                    </div>

                    <div class="relative p-3">
                        <div v-if="!montiLocation"
                            class="absolute inset-3 z-10 flex items-center justify-center bg-amber-50/90 dark:bg-amber-900/30 backdrop-blur-sm rounded-3xl border-2 border-dashed border-amber-300">
                            <div class="text-center">
                                <AlertTriangle class="h-8 w-8 text-amber-500 mx-auto mb-2 animate-bounce-soft" />
                                <p class="text-sm font-black text-amber-700 dark:text-amber-400">MontiTextiles HQ location not set.</p>
                                <p class="text-xs text-amber-600 mt-1">Please set a location in the IT Geolocation module first.</p>
                            </div>
                        </div>
                        <div id="main-map" class="w-full rounded-3xl z-0" style="height:520px;"></div>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-100 dark:border-zinc-800 flex flex-wrap items-center gap-6 text-[10px] font-black uppercase tracking-widest">
                        <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                            <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                            Live Map Active
                        </div>
                        <div class="text-gray-400">{{ routes.length }} Route{{ routes.length !== 1 ? 's' : '' }} Mapped</div>
                        <div class="text-gray-400">{{ clients.length }} Client{{ clients.length !== 1 ? 's' : '' }} Pinned</div>
                        <div v-if="montiLocation" class="text-gray-400">
                            HQ: {{ Number(montiLocation.latitude).toFixed(4) }}, {{ Number(montiLocation.longitude).toFixed(4) }}
                        </div>
                    </div>
                </div>

                <!-- Stats Summary -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:80ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 via-blue-600 to-cyan-600 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Navigation class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Total Routes</p>
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ routes.length }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:160ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-cyan-400/20 to-blue-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-cyan-500 to-blue-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-500 via-sky-600 to-blue-700 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <MapPin class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Avg Distance</p>
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ avgDistance }} <span class="text-sm font-bold text-gray-400">km</span></h3>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative flex flex-col rounded-3xl bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 p-5 text-white shadow-lg shadow-orange-500/20 hover:shadow-2xl hover:-translate-y-1.5 transition-all duration-300 overflow-hidden" style="animation-delay:240ms">
                        <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10 blur-2xl group-hover:scale-125 transition-transform duration-500" />
                        <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                        <div class="relative flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Flag class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-amber-100">Avg Est. Time</p>
                                <h3 class="text-3xl font-black leading-none">{{ avgDuration }} <span class="text-sm font-bold text-amber-100">min</span></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Routes Table -->
                <div class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden hover:shadow-xl transition-shadow duration-300" style="animation-delay:120ms">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                        <Navigation class="h-5 w-5 text-indigo-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">Routes</h2>
                        <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-1">{{ filteredRoutes.length }}</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 dark:border-zinc-800">
                                <tr>
                                    <th class="px-8 py-4">Route Name</th>
                                    <th class="px-8 py-4">Client</th>
                                    <th class="px-8 py-4">Origin</th>
                                    <th class="px-8 py-4">Destination</th>
                                    <th class="px-8 py-4 text-right">Distance</th>
                                    <th class="px-8 py-4 text-right">Est. Time</th>
                                    <th class="px-8 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <TransitionGroup name="card" tag="tbody" class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="(r, idx) in filteredRoutes" :key="r.id"
                                    :style="{ transitionDelay: `${Math.min(idx * 40, 400)}ms` }"
                                    class="group hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-all cursor-pointer"
                                    @click="flyToRoute(r)">
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 rounded-2xl flex items-center justify-center text-white text-xs font-black shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300"
                                                :style="{ background: routeColors[idx % routeColors.length] }">
                                                {{ idx + 1 }}
                                            </div>
                                            <div>
                                                <p class="font-black text-gray-900 dark:text-white group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors">{{ r.name }}</p>
                                                <span v-if="parseGeometry(r.route_geometry)"
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-200 dark:ring-emerald-500/30 text-[9px] font-black uppercase tracking-widest">
                                                    <span class="w-1 h-1 bg-emerald-500 rounded-full animate-pulse"></span> Road Route
                                                </span>
                                                <span v-else
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 ring-1 ring-amber-200 dark:ring-amber-500/30 text-[9px] font-black uppercase tracking-widest">
                                                    ⚠ No road data — edit to fix
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <span v-if="r.client" class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ r.client.company_name }}</span>
                                        <span v-else class="text-xs text-gray-400 italic">—</span>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-1">
                                            <MapPin class="h-3 w-3 text-blue-400 flex-shrink-0" />
                                            <span class="text-sm text-gray-600 dark:text-gray-400 truncate max-w-[160px]">{{ r.origin }}</span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-1">
                                            <Flag class="h-3 w-3 text-emerald-400 flex-shrink-0" />
                                            <span class="text-sm text-gray-600 dark:text-gray-400 truncate max-w-[160px]">{{ r.destination }}</span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6 text-right">
                                        <span class="font-black text-gray-900 dark:text-white">{{ Number(r.distance_km).toFixed(1) }}</span>
                                        <span class="text-xs text-gray-400 ml-1">km</span>
                                    </td>
                                    <td class="px-8 py-6 text-right">
                                        <span class="font-black text-amber-600">{{ r.estimated_minutes }}</span>
                                        <span class="text-xs text-gray-400 ml-1">min</span>
                                    </td>
                                    <td class="px-8 py-6 text-right" @click.stop>
                                        <div class="flex items-center justify-end gap-2">
                                            <button v-if="canEditRoutes" @click="openEditModal(r)"
                                                class="p-2 rounded-xl hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition hover:-translate-y-0.5">
                                                <Edit class="h-4 w-4 text-gray-500" />
                                            </button>
                                            <button v-if="canEditRoutes" @click="confirmDelete(r)"
                                                class="p-2 rounded-xl hover:bg-red-50 dark:hover:bg-red-900/20 transition hover:-translate-y-0.5">
                                                <Trash2 class="h-4 w-4 text-red-500" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </TransitionGroup>
                        </table>
                        <div v-if="filteredRoutes.length === 0" class="px-8 py-20 text-center">
                            <Navigation class="h-12 w-12 mx-auto mb-3 text-indigo-200 dark:text-zinc-700 animate-bounce-soft" />
                            <p class="text-sm font-black text-gray-500">No routes found. Click "Add Route" to create your first delivery route.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create / Edit Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                    @click.self="closeModal">
                    <div class="bg-white dark:bg-zinc-900 w-full max-w-2xl rounded-3xl shadow-2xl overflow-hidden max-h-[95vh] flex flex-col border border-gray-100 dark:border-zinc-800">

                        <div class="px-6 py-5 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white flex justify-between items-center flex-shrink-0 relative overflow-hidden">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <div class="relative flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur"><Navigation class="h-5 w-5" /></span>
                                <h3 class="font-black text-lg tracking-tight">{{ isEditing ? 'Edit Route' : 'Add New Route' }}</h3>
                            </div>
                            <button @click="closeModal" class="relative p-1.5 hover:bg-white/20 rounded-xl transition">
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="overflow-y-auto flex-1">
                            <!-- Mini preview map -->
                            <div class="relative" style="height: 260px;">
                                <div id="modal-map" class="w-full h-full z-0"></div>
                                <div class="absolute top-3 left-3 z-[1000] pointer-events-none">
                                    <div class="bg-slate-900/80 backdrop-blur-md text-white px-3 py-1.5 rounded-xl text-[9px] tracking-widest font-black uppercase flex items-center gap-2">
                                        <span class="h-1.5 w-1.5 bg-emerald-400 rounded-full animate-pulse"></span>
                                        Route Preview
                                    </div>
                                </div>
                                <div v-if="isCalculating"
                                    class="absolute inset-0 z-[1000] flex items-center justify-center bg-black/40 backdrop-blur-sm">
                                    <div class="bg-white dark:bg-zinc-900 px-5 py-3 rounded-2xl flex items-center gap-3 shadow-xl">
                                        <Loader2 class="h-5 w-5 animate-spin text-indigo-600" />
                                        <span class="text-[11px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-300">
                                            Calculating Road Route…
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="p-6 space-y-5">

                                <div>
                                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">Route Name *</label>
                                    <input v-model="form.name" type="text" required
                                        class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition"
                                        placeholder="e.g., MontiTextiles → OmniTech Express">
                                </div>

                                <div>
                                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">
                                        Origin <span class="text-indigo-400">(MontiTextiles HQ — Auto)</span>
                                    </label>
                                    <div class="w-full rounded-2xl border border-indigo-100 dark:border-indigo-800 bg-indigo-50/50 dark:bg-indigo-900/10 px-4 py-3 text-sm flex items-center gap-2">
                                        <MapPin class="h-4 w-4 text-blue-500 flex-shrink-0" />
                                        <span v-if="montiLocation" class="text-gray-700 dark:text-gray-300 font-medium">
                                            MontiTextiles HQ —
                                            <span class="font-mono text-xs text-gray-500">
                                                {{ Number(montiLocation.latitude).toFixed(6) }}, {{ Number(montiLocation.longitude).toFixed(6) }}
                                            </span>
                                        </span>
                                        <span v-else class="text-amber-600 italic text-xs">
                                            ⚠ HQ location not set.
                                        </span>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">
                                        Destination — Client *
                                    </label>
                                    <div v-if="clients.length === 0" class="w-full rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700 font-medium">
                                        ⚠ No clients have pinned their location yet.
                                    </div>
                                    <select v-else v-model="form.client_id" required
                                        class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                                        <option value="">— Select a business client —</option>
                                        <option v-for="c in clients" :key="c.id" :value="c.id">
                                            {{ c.company_name }} — {{ [c.company_address, c.city, c.province].filter(Boolean).join(', ') }}
                                        </option>
                                    </select>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">
                                            Distance (km) <span class="text-indigo-400 ml-1">Auto</span>
                                        </label>
                                        <div class="relative">
                                            <input v-model.number="form.distance_km" type="number" step="0.01" min="0" required
                                                class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition"
                                                :class="{ 'opacity-60': isCalculating }">
                                            <Loader2 v-if="isCalculating" class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 animate-spin text-indigo-400" />
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">
                                            Est. Time (min) <span class="text-indigo-400 ml-1">Auto</span>
                                        </label>
                                        <div class="relative">
                                            <input v-model.number="form.estimated_minutes" type="number" min="1" required
                                                class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition"
                                                :class="{ 'opacity-60': isCalculating }">
                                            <Loader2 v-if="isCalculating" class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 animate-spin text-indigo-400" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Route source label / recalculate button -->
                                <div class="flex items-center justify-between gap-4">
                                    <p v-if="routeSourceLabel" class="text-[10px] text-gray-400 font-medium flex items-center gap-1">
                                        <CheckCircle2 class="h-3 w-3 text-emerald-500 flex-shrink-0" />
                                        {{ routeSourceLabel }}
                                    </p>
                                    <!-- Recalculate — useful for old routes that were saved without road geometry -->
                                    <button v-if="canEditRoutes && form.client_id" type="button" @click="recalculate"
                                        :disabled="isCalculating"
                                        class="ml-auto flex-shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-300 text-[10px] font-black uppercase tracking-widest hover:bg-indigo-100 transition disabled:opacity-50">
                                        <RefreshCw class="h-3 w-3" :class="{ 'animate-spin': isCalculating }" />
                                        Recalculate Road Route
                                    </button>
                                </div>

                                <div class="flex gap-3 pt-4 border-t border-gray-100 dark:border-zinc-800">
                                    <button type="button" @click="closeModal"
                                        class="flex-1 px-4 py-3 border border-gray-200 dark:border-zinc-700 rounded-2xl text-[10px] font-black uppercase hover:bg-gray-50 dark:hover:bg-zinc-800 transition active:scale-95">
                                        Cancel
                                    </button>
                                    <button v-if="canEditRoutes" type="button" @click="submitForm"
                                        :disabled="form.processing || isCalculating || !form.client_id || !form.distance_km"
                                        class="flex-1 px-4 py-3 bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-2xl text-[10px] font-black uppercase hover:shadow-lg hover:-translate-y-0.5 transition disabled:opacity-50 flex items-center justify-center gap-2 active:scale-95">
                                        <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                                        {{ form.processing ? 'Saving…' : (isEditing ? 'Update Route' : 'Create Route') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Delete Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showDeleteModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                    @click.self="showDeleteModal = false">
                    <div class="bg-white dark:bg-zinc-900 w-full max-w-md rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-zinc-800">
                        <div class="px-6 py-5 bg-gradient-to-br from-rose-600 via-red-600 to-orange-600 text-white flex justify-between items-center relative overflow-hidden">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <h3 class="relative font-black text-lg tracking-tight">Delete Route</h3>
                            <button @click="showDeleteModal = false" class="relative p-1 hover:bg-white/20 rounded-xl transition">
                                <X class="h-5 w-5" />
                            </button>
                        </div>
                        <div class="p-6 space-y-4">
                            <p class="text-gray-700 dark:text-gray-300">
                                Are you sure you want to delete
                                <span class="font-bold">{{ routeToDelete?.name }}</span>?
                            </p>
                            <p class="text-xs text-red-500">This removes the route line from the map and cannot be undone.</p>
                            <div class="flex gap-3">
                                <button @click="showDeleteModal = false"
                                    class="flex-1 px-4 py-3 border border-gray-200 dark:border-zinc-700 rounded-2xl text-[10px] font-black uppercase hover:bg-gray-50 dark:hover:bg-zinc-800 transition active:scale-95">
                                    Cancel
                                </button>
                                <button v-if="canEditRoutes" @click="deleteRoute" :disabled="deleting"
                                    class="flex-1 px-4 py-3 bg-gradient-to-r from-rose-600 to-red-600 text-white rounded-2xl text-[10px] font-black uppercase hover:shadow-lg transition disabled:opacity-50 flex items-center justify-center gap-2 active:scale-95">
                                    <Loader2 v-if="deleting" class="h-4 w-4 animate-spin" />
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Toast -->
        <Transition name="toast">
            <div v-if="toast.show"
                class="fixed bottom-8 right-8 z-[9999] px-6 py-3 rounded-2xl shadow-lg text-white font-bold text-sm flex items-center gap-2"
                :class="toast.type === 'success' ? 'bg-emerald-600' : toast.type === 'warning' ? 'bg-amber-500' : 'bg-red-600'">
                <CheckCircle2 v-if="toast.type === 'success'" class="h-4 w-4" />
                <AlertTriangle v-else-if="toast.type === 'warning'" class="h-4 w-4" />
                <X v-else class="h-4 w-4" />
                {{ toast.message }}
            </div>
        </Transition>

    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import {
    MapPin, Navigation, Plus, Search, Edit, Trash2, X,
    Loader2, Flag, Globe, AlertTriangle, CheckCircle2, RefreshCw
} from 'lucide-vue-next';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import axios from 'axios';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditRoutes = computed(() => canEdit('LOG', 'routes'));

// ── Props ─────────────────────────────────────────────────────────────────
const props = defineProps({
    routes:        { type: Array,  default: () => [] },
    clients:       { type: Array,  default: () => [] },
    montiLocation: { type: Object, default: null },
});

const routeColors = [
    '#6366f1', '#f59e0b', '#10b981', '#ef4444',
    '#8b5cf6', '#06b6d4', '#f97316', '#ec4899',
];

// ── Map state ─────────────────────────────────────────────────────────────
let mainMap           = null;
let mainMarkers       = [];
let mainPolylines     = [];
let modalMap          = null;
let modalOriginMarker = null;
let modalDestMarker   = null;
let modalRoutePolyline = null;

// ── UI state ───────────────────────────────────────────────────────────────
const searchTerm       = ref('');
const showModal        = ref(false);
const isEditing        = ref(false);
const editingId        = ref(null);
const showDeleteModal  = ref(false);
const routeToDelete    = ref(null);
const deleting         = ref(false);
const isCalculating    = ref(false);
const routeSourceLabel = ref('');
const toast            = ref({ show: false, type: 'success', message: '' });

// ── Form ───────────────────────────────────────────────────────────────────
const form = useForm({
    name:              '',
    client_id:         '',
    origin:            '',
    origin_lat:        null,
    origin_lng:        null,
    destination:       '',
    destination_lat:   null,
    destination_lng:   null,
    distance_km:       0,
    estimated_minutes: 0,
    route_geometry:    null,
});

const montiLat = computed(() =>
    props.montiLocation ? Number(props.montiLocation.latitude)  : 14.4167
);
const montiLng = computed(() =>
    props.montiLocation ? Number(props.montiLocation.longitude) : 121.0000
);

// ─────────────────────────────────────────────────────────────────────────
// GEOMETRY HELPER
// Safely handles route_geometry whether it's a JS array (fresh from OSRM)
// or a JSON string (retrieved from DB when the Route model lacks an array cast).
// ─────────────────────────────────────────────────────────────────────────
const parseGeometry = (geom) => {
    if (!geom) return null;
    if (Array.isArray(geom))   return geom.length > 1 ? geom : null;
    if (typeof geom === 'string') {
        try {
            const p = JSON.parse(geom);
            return Array.isArray(p) && p.length > 1 ? p : null;
        } catch { return null; }
    }
    return null;
};

// ─────────────────────────────────────────────────────────────────────────
// MAIN MAP
// ─────────────────────────────────────────────────────────────────────────
const buildMontiIcon = (size = 34) => L.divIcon({
    className: '',
    html: `<div style="background:#3b82f6;color:white;border-radius:50%;width:${size}px;height:${size}px;display:flex;align-items:center;justify-content:center;border:3px solid white;box-shadow:0 3px 10px rgba(59,130,246,0.5);font-size:${size * 0.45}px;">🏭</div>`,
    iconSize: [size, size], iconAnchor: [size / 2, size / 2],
});

const buildClientIcon = (size = 30) => L.divIcon({
    className: '',
    html: `<div style="background:#10b981;color:white;border-radius:50%;width:${size}px;height:${size}px;display:flex;align-items:center;justify-content:center;border:3px solid white;box-shadow:0 3px 10px rgba(16,185,129,0.5);font-size:${size * 0.45}px;">📦</div>`,
    iconSize: [size, size], iconAnchor: [size / 2, size / 2],
});

const initMainMap = () => {
    if (mainMap) return;
    const street    = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 });
    const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 20 });
    mainMap = L.map('main-map', { center: [montiLat.value, montiLng.value], zoom: 11, layers: [street] });
    L.control.layers({ 'Street': street, 'Satellite': satellite }).addTo(mainMap);
    L.marker([montiLat.value, montiLng.value], { icon: buildMontiIcon(34) })
        .bindPopup('<strong style="font-size:13px;">🏭 MontiTextiles HQ</strong><br><span style="font-size:11px;color:#6366f1;">Origin of all delivery routes</span>')
        .addTo(mainMap);
    refreshMainMapClients();
    refreshMainMapRoutes();
};

const refreshMainMapClients = () => {
    mainMarkers.forEach(m => m.remove());
    mainMarkers = [];
    props.clients.forEach(c => {
        if (!c.latitude || !c.longitude) return;
        const marker = L.marker([Number(c.latitude), Number(c.longitude)], { icon: buildClientIcon(30) })
            .bindPopup(`<strong style="font-size:13px;">📦 ${c.company_name}</strong><br><span style="font-size:11px;color:#555;">${[c.company_address, c.city, c.province].filter(Boolean).join(', ')}</span><br><span style="font-size:10px;color:#aaa;">${Number(c.latitude).toFixed(5)}, ${Number(c.longitude).toFixed(5)}</span>`)
            .addTo(mainMap);
        mainMarkers.push(marker);
    });
};

const refreshMainMapRoutes = () => {
    mainPolylines.forEach(p => p.remove());
    mainPolylines = [];

    props.routes.forEach((r, idx) => {
        const color = routeColors[idx % routeColors.length];
        const geom  = parseGeometry(r.route_geometry);
        let polyline;

        if (geom) {
            // ✅ Real road path stored in DB
            polyline = L.polyline(geom, { color, weight: 5, opacity: 0.85 });
        } else if (r.origin_lat && r.origin_lng && r.destination_lat && r.destination_lng) {
            // Fallback: dashed straight line (no geometry saved yet)
            polyline = L.polyline([
                [Number(r.origin_lat),      Number(r.origin_lng)],
                [Number(r.destination_lat), Number(r.destination_lng)],
            ], { color, weight: 4, opacity: 0.6, dashArray: '10, 6' });
        } else {
            return;
        }

        polyline.bindPopup(`
            <strong style="font-size:13px;">${r.name}</strong><br>
            <span style="font-size:11px;color:#555;">📍 ${r.origin}<br>🏁 ${r.destination}</span><br>
            <span style="font-size:11px;color:${color};font-weight:900;">${Number(r.distance_km).toFixed(1)} km · ${r.estimated_minutes} min</span>
            ${geom ? '<br><span style="font-size:10px;color:#10b981;font-weight:900;">✓ Real road path</span>' : '<br><span style="font-size:10px;color:#f59e0b;font-weight:900;">⚠ Straight-line estimate — edit route to fix</span>'}
        `);
        polyline.addTo(mainMap);
        mainPolylines.push(polyline);
    });
};

const flyToRoute = (r) => {
    if (!mainMap) return;
    const geom = parseGeometry(r.route_geometry);
    if (geom) {
        mainMap.fitBounds(L.polyline(geom).getBounds(), { padding: [60, 60], maxZoom: 14 });
    } else if (r.origin_lat && r.destination_lat) {
        mainMap.fitBounds([
            [Number(r.origin_lat), Number(r.origin_lng)],
            [Number(r.destination_lat), Number(r.destination_lng)],
        ], { padding: [60, 60], maxZoom: 13 });
    }
};

// ─────────────────────────────────────────────────────────────────────────
// MODAL PREVIEW MAP
// ─────────────────────────────────────────────────────────────────────────
const initModalMap = () => {
    const el = document.getElementById('modal-map');
    if (!el) return;
    if (modalMap) { modalMap.remove(); modalMap = null; }
    modalMap = L.map('modal-map', { center: [montiLat.value, montiLng.value], zoom: 11 });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(modalMap);
    modalOriginMarker = L.marker([montiLat.value, montiLng.value], { icon: buildMontiIcon(28) })
        .bindPopup('MontiTextiles HQ').addTo(modalMap);

    const savedGeom = parseGeometry(form.route_geometry);
    if (isEditing.value && savedGeom) {
        modalRoutePolyline = L.polyline(savedGeom, { color: '#6366f1', weight: 5 }).addTo(modalMap);
        modalMap.fitBounds(modalRoutePolyline.getBounds(), { padding: [30, 30] });
    }
    if (isEditing.value && form.destination_lat && form.destination_lng) {
        modalDestMarker = L.marker([form.destination_lat, form.destination_lng], { icon: buildClientIcon(28) }).addTo(modalMap);
    }
};

const updateModalMapRoute = (geometry, oLat, oLng, dLat, dLng) => {
    if (!modalMap) return;
    if (modalDestMarker)    { modalDestMarker.remove();    modalDestMarker    = null; }
    if (modalRoutePolyline) { modalRoutePolyline.remove(); modalRoutePolyline = null; }
    modalDestMarker = L.marker([dLat, dLng], { icon: buildClientIcon(28) }).addTo(modalMap);
    const geom = parseGeometry(geometry);
    if (geom) {
        modalRoutePolyline = L.polyline(geom, { color: '#6366f1', weight: 5, opacity: 0.85 }).addTo(modalMap);
        modalMap.fitBounds(modalRoutePolyline.getBounds(), { padding: [30, 30] });
    } else {
        modalRoutePolyline = L.polyline([[oLat, oLng], [dLat, dLng]], { color: '#6366f1', weight: 4, dashArray: '10, 6', opacity: 0.7 }).addTo(modalMap);
        modalMap.fitBounds(modalRoutePolyline.getBounds(), { padding: [30, 30] });
    }
};

// ─────────────────────────────────────────────────────────────────────────
// OSRM ROUTE CALCULATION (browser-side — for live modal preview only)
// Even if this fails, the controller will retry OSRM server-side on save,
// so there are no CORS/network issues blocking real road geometry.
// ─────────────────────────────────────────────────────────────────────────
const haversineKm = (lat1, lng1, lat2, lng2) => {
    const R    = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a    = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
};

const computeRoute = async (client) => {
    if (!client?.latitude || !client?.longitude) return;
    isCalculating.value    = true;
    routeSourceLabel.value = '';

    const oLat = montiLat.value;
    const oLng = montiLng.value;
    const dLat = Number(client.latitude);
    const dLng = Number(client.longitude);

    try {
        const url = `https://router.project-osrm.org/route/v1/driving/${oLng},${oLat};${dLng},${dLat}?overview=full&geometries=geojson`;
        const res = await axios.get(url, { timeout: 10000 });

        if (res.data?.routes?.length) {
            const r                = res.data.routes[0];
            form.distance_km       = parseFloat((r.distance / 1000).toFixed(2));
            form.estimated_minutes = Math.ceil(r.duration / 60);
            // OSRM returns [lng, lat] — flip to [lat, lng] for Leaflet
            form.route_geometry    = r.geometry.coordinates.map(c => [c[1], c[0]]);
            routeSourceLabel.value = `✓ Road route — ${form.distance_km} km, ~${form.estimated_minutes} min`;
        } else {
            throw new Error('No routes');
        }
    } catch {
        // Browser OSRM failed — controller will retry server-side on save
        const dist             = haversineKm(oLat, oLng, dLat, dLng);
        form.distance_km       = parseFloat(dist.toFixed(2));
        form.estimated_minutes = Math.ceil((dist / 40) * 60);
        form.route_geometry    = null;
        routeSourceLabel.value = `Preview: straight-line estimate — server will compute road route on save`;
    } finally {
        isCalculating.value = false;
        updateModalMapRoute(form.route_geometry, oLat, oLng, dLat, dLng);
    }
};

// Recalculate button — lets users fix existing straight-line routes
const recalculate = async () => {
    const client = props.clients.find(c => c.id == form.client_id);
    if (client) await computeRoute(client);
};

// ─────────────────────────────────────────────────────────────────────────
// WATCHERS
// ─────────────────────────────────────────────────────────────────────────
watch(() => form.client_id, async (newId) => {
    if (!newId) return;
    const client = props.clients.find(c => c.id == newId);
    if (!client) return;
    form.origin          = 'MontiTextiles HQ';
    form.origin_lat      = montiLat.value;
    form.origin_lng      = montiLng.value;
    form.destination     = [client.company_name, client.company_address, client.city, client.province].filter(Boolean).join(', ');
    form.destination_lat = Number(client.latitude);
    form.destination_lng = Number(client.longitude);
    await computeRoute(client);
});

watch(showModal, (val) => {
    if (val) {
        nextTick(() => setTimeout(() => initModalMap(), 80));
    } else {
        if (modalMap) {
            modalMap.remove();
            modalMap = modalOriginMarker = modalDestMarker = modalRoutePolyline = null;
        }
        routeSourceLabel.value = '';
    }
});

watch(() => props.routes,  () => { if (mainMap) refreshMainMapRoutes();  }, { deep: true });
watch(() => props.clients, () => { if (mainMap) refreshMainMapClients(); }, { deep: true });

onMounted(() => nextTick(() => initMainMap()));

// ─────────────────────────────────────────────────────────────────────────
// COMPUTED
// ─────────────────────────────────────────────────────────────────────────
const filteredRoutes = computed(() => {
    if (!searchTerm.value) return props.routes;
    const t = searchTerm.value.toLowerCase();
    return props.routes.filter(r =>
        r.name.toLowerCase().includes(t) ||
        r.origin.toLowerCase().includes(t) ||
        r.destination.toLowerCase().includes(t) ||
        (r.client?.company_name || '').toLowerCase().includes(t)
    );
});

const avgDistance = computed(() => {
    if (!props.routes.length) return 0;
    return (props.routes.reduce((s, r) => s + Number(r.distance_km || 0), 0) / props.routes.length).toFixed(1);
});

const avgDuration = computed(() => {
    if (!props.routes.length) return 0;
    return Math.round(props.routes.reduce((s, r) => s + (r.estimated_minutes || 0), 0) / props.routes.length);
});

// ─────────────────────────────────────────────────────────────────────────
// MODAL ACTIONS
// ─────────────────────────────────────────────────────────────────────────
const showToast  = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 3500);
};
const refreshData = () => router.reload({ only: ['routes', 'clients', 'montiLocation'] });

const openCreateModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    showModal.value = true;
};

const openEditModal = (r) => {
    isEditing.value         = true;
    editingId.value         = r.id;
    form.name               = r.name;
    form.client_id          = r.client_id || '';
    form.origin             = r.origin;
    form.origin_lat         = r.origin_lat;
    form.origin_lng         = r.origin_lng;
    form.destination        = r.destination;
    form.destination_lat    = r.destination_lat;
    form.destination_lng    = r.destination_lng;
    form.distance_km        = r.distance_km;
    form.estimated_minutes  = r.estimated_minutes;
    form.route_geometry     = parseGeometry(r.route_geometry); // normalize to array
    showModal.value         = true;
    const geom = parseGeometry(r.route_geometry);
    routeSourceLabel.value  = geom
        ? `✓ Saved road route — ${r.distance_km} km, ~${r.estimated_minutes} min`
        : `⚠ No road data saved — click Recalculate to fix`;
};

const closeModal = () => { showModal.value = false; form.reset(); };

const submitForm = () => {
    const payload = {
        name:              form.name,
        client_id:         form.client_id || null,
        origin:            form.origin || 'MontiTextiles HQ',
        origin_lat:        form.origin_lat  || montiLat.value,
        origin_lng:        form.origin_lng  || montiLng.value,
        destination:       form.destination,
        destination_lat:   form.destination_lat,
        destination_lng:   form.destination_lng,
        distance_km:       form.distance_km,
        estimated_minutes: form.estimated_minutes,
        route_geometry:    form.route_geometry,
    };

    const onSuccess = (msg) => { showToast('success', msg); closeModal(); refreshData(); };
    const onError   = (errors) => showToast('error', Object.values(errors)[0] || 'Save failed.');

    if (isEditing.value) {
        router.put(route('logistics.routes.update', editingId.value), payload, {
            preserveScroll: true,
            onSuccess: () => onSuccess('Route updated successfully.'),
            onError,
        });
    } else {
        router.post(route('logistics.routes.store'), payload, {
            preserveScroll: true,
            onSuccess: () => onSuccess('Route created successfully.'),
            onError,
        });
    }
};

const confirmDelete = (r) => { routeToDelete.value = r; showDeleteModal.value = true; };

const deleteRoute = () => {
    deleting.value = true;
    router.delete(route('logistics.routes.destroy', routeToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showToast('success', `Route "${routeToDelete.value.name}" deleted.`);
            showDeleteModal.value = false;
            routeToDelete.value   = null;
            refreshData();
        },
        onError:  () => showToast('error', 'Failed to delete route.'),
        onFinish: () => { deleting.value = false; },
    });
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
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active, .modal-leave-active { transition: all 0.3s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: scale(0.95) translateY(12px); }
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }
:deep(.leaflet-control-layers) {
    border-radius: 1rem !important; border: none !important;
    box-shadow: 0 4px 15px rgba(0,0,0,0.12); padding: 6px 10px;
    font-weight: 900; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em;
}
:deep(.leaflet-popup-content-wrapper) { border-radius: 1rem !important; box-shadow: 0 8px 24px rgba(0,0,0,0.15); }
:deep(.leaflet-popup-tip) { display: none; }
</style>
