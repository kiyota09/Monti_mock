<template>
    <Head title="Fleet Management" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gray-100 dark:bg-zinc-950">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-4 pb-16">

                <!-- Theme-aware fleet banner -->
                <div data-erp-page-banner="fallback" class="relative flex flex-wrap items-center justify-between gap-5">
                    <div class="relative flex min-w-0 flex-1 items-center gap-4">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/20">
                            <Truck class="h-7 w-7" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/75">Logistics · Asset registry</p>
                            <h1 class="mt-1 text-2xl font-black tracking-tight sm:text-3xl">
                                Fleet Management
                                <span v-if="!canEditFleet" class="ml-2 inline-flex rounded-full bg-white/15 px-2.5 py-1 align-middle text-[10px] font-bold uppercase tracking-wider ring-1 ring-white/20">View only</span>
                            </h1>
                            <p class="mt-1 text-sm text-white/80">Manage fleet vehicles, capacity, and availability.</p>
                        </div>
                    </div>
                    <div class="relative flex flex-wrap items-center gap-3">
                        <div class="rounded-2xl bg-white/10 px-4 py-2.5 text-center ring-1 ring-white/20">
                            <p class="text-xl font-black leading-none">{{ trucks.length }}</p>
                            <p class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-white/75">vehicles</p>
                        </div>
                        <div class="rounded-2xl bg-white/10 px-4 py-2.5 text-center ring-1 ring-white/20">
                            <p class="text-xl font-black leading-none">{{ availableCount }}</p>
                            <p class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-white/75">available</p>
                        </div>
                        <button v-if="canEditFleet" @click="openCreateModal"
                            class="flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-xs font-bold text-slate-900 shadow-lg transition hover:bg-slate-100 active:scale-95 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                            <Plus class="h-4 w-4" /> Add Truck
                        </button>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="flex gap-6 overflow-x-auto border-b border-gray-200 dark:border-zinc-800">
                    <button v-for="tab in tabs" :key="tab.key" @click="statusFilter = tab.key"
                        :class="statusFilter === tab.key
                            ? 'border-indigo-700 text-indigo-700 dark:text-indigo-400 dark:border-indigo-400'
                            : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                        class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-1 pb-3 text-xs font-semibold transition-colors">
                        {{ tab.label }}
                        <span :class="statusFilter === tab.key ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' : 'bg-gray-200 text-gray-600 dark:bg-zinc-800 dark:text-gray-300'"
                            class="rounded-full px-2 py-0.5 text-[10px] font-bold">{{ tab.count }}</span>
                    </button>
                </div>

                <!-- Main panel -->
                <div class="rounded-2xl bg-white p-5 shadow-sm dark:bg-zinc-900">
                    <div class="mb-4 flex items-center gap-2">
                        <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ currentTabLabel }}</h2>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold text-gray-500 dark:bg-zinc-800 dark:text-gray-300">{{ filteredTrucks.length }}</span>
                    </div>

                    <!-- Filters -->
                    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="flex-1 sm:max-w-xs">
                            <label class="mb-1 block text-[10px] font-semibold text-gray-500">Search vehicle</label>
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                <input v-model="searchTerm" type="text" placeholder="Search by number, plate, or model..."
                                    class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-3 text-xs text-gray-900 placeholder:text-gray-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                            </div>
                        </div>
                        <div class="sm:w-52">
                            <label class="mb-1 block text-[10px] font-semibold text-gray-500">Vehicle model</label>
                            <select v-model="modelFilter"
                                class="w-full rounded-lg border border-gray-200 bg-white py-2 px-3 text-xs text-gray-700 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                <option value="all">Any</option>
                                <option v-for="m in modelOptions" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-1 sm:ml-auto">
                            <button @click="viewMode = 'grid'" :class="viewMode === 'grid' ? 'bg-gray-200 text-gray-900 dark:bg-zinc-700 dark:text-white' : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-zinc-800'" class="flex h-8 w-8 items-center justify-center rounded-md transition">
                                <LayoutGrid class="h-4 w-4" />
                            </button>
                            <button @click="viewMode = 'list'" :class="viewMode === 'list' ? 'bg-gray-200 text-gray-900 dark:bg-zinc-700 dark:text-white' : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-zinc-800'" class="flex h-8 w-8 items-center justify-center rounded-md transition">
                                <List class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- GRID VIEW -->
                    <TransitionGroup v-if="viewMode === 'grid' && filteredTrucks.length" name="card" tag="div" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="(truck, i) in filteredTrucks" :key="truck.id" :style="{ transitionDelay: `${Math.min(i * 30, 300)}ms` }"
                            :class="cardTheme(truck.status).bg"
                            class="group relative flex min-h-[26rem] flex-col overflow-hidden rounded-3xl text-white shadow-lg transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl">
                            <!-- soft light rays -->
                            <div class="pointer-events-none absolute inset-0 opacity-40" style="background: radial-gradient(ellipse at 15% 0%, rgba(255,255,255,0.35), transparent 55%), radial-gradient(ellipse at 85% 30%, rgba(255,255,255,0.12), transparent 50%);" />
                            <!-- vehicle image (falls back to icon) -->
                            <img v-if="truck.image_url" :src="truck.image_url" :alt="truck.model" class="absolute inset-0 h-full w-full object-cover" />
                            <Truck v-else class="absolute left-1/2 top-[40%] h-44 w-44 -translate-x-1/2 -translate-y-1/2 text-white/25 transition-transform duration-500 group-hover:-rotate-3 group-hover:scale-110" stroke-width="1" />
                            <div class="absolute inset-0 bg-gradient-to-b from-black/55 via-black/30 to-black/70" />

                            <div class="relative flex flex-1 flex-col p-5">
                                <!-- decorative dots -->
                                <div class="absolute left-1/2 top-6 flex -translate-x-1/2 gap-1.5">
                                    <span class="h-2 w-2 rounded-full bg-white" />
                                    <span class="h-2 w-2 rounded-full bg-white/40" />
                                    <span class="h-2 w-2 rounded-full bg-white/40" />
                                </div>

                                <!-- top row: chips + capacity -->
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex flex-col items-start gap-2">
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-white/40 bg-white/10 px-3 py-1 text-xs font-medium backdrop-blur-sm">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current" /> {{ formatStatus(truck.status) }}
                                        </span>
                                        <span class="rounded-full border border-white/40 bg-white/10 px-3 py-1 text-xs font-medium backdrop-blur-sm">{{ truck.model }}</span>
                                        <span class="rounded-full border border-white/40 bg-white/10 px-3 py-1 font-mono text-xs font-medium backdrop-blur-sm">{{ truck.plate_number }}</span>
                                    </div>
                                    <p class="whitespace-nowrap text-xl font-bold drop-shadow">{{ truck.max_load_kg ? `${Number(truck.max_load_kg).toLocaleString()} kg` : 'No cap.' }}</p>
                                </div>

                                <!-- bottom: title, description, button -->
                                <div class="mt-auto pt-24">
                                    <h3 class="text-xl font-bold leading-tight drop-shadow">{{ truck.truck_number }} · {{ truck.model }}</h3>
                                    <p class="mt-1.5 text-sm leading-snug text-white/90 drop-shadow">
                                        {{ truck.year }} model with {{ (truck.mileage ?? 0).toLocaleString() }} km on the clock. Ready to roll for your next delivery run.
                                    </p>
                                    <button @click="openDetailsModal(truck)"
                                        :class="cardTheme(truck.status).btn"
                                        class="mt-4 w-full rounded-full py-3 text-sm font-semibold text-white shadow-lg transition active:scale-[0.98]">
                                        View Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    </TransitionGroup>

                    <!-- LIST VIEW -->
                    <div v-else-if="viewMode === 'list' && filteredTrucks.length" class="overflow-x-auto rounded-xl border border-gray-100 dark:border-zinc-800">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:bg-zinc-800/50">
                                <tr>
                                    <th class="px-5 py-3">Truck #</th>
                                    <th class="px-5 py-3">Plate #</th>
                                    <th class="px-5 py-3">Model</th>
                                    <th class="px-5 py-3">Year</th>
                                    <th class="px-5 py-3 text-right">Mileage (km)</th>
                                    <th class="px-5 py-3 text-center">Capacity</th>
                                    <th class="px-5 py-3 text-center">Status</th>
                                    <th class="px-5 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <TransitionGroup name="card" tag="tbody" class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr v-for="truck in filteredTrucks" :key="truck.id" class="text-xs transition-colors hover:bg-gray-50 dark:hover:bg-zinc-800/40">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-500 dark:bg-zinc-800">
                                                <Truck class="h-4 w-4" />
                                            </div>
                                            <span class="font-mono font-bold text-gray-900 dark:text-white">{{ truck.truck_number }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 font-mono text-gray-500 dark:text-gray-400">{{ truck.plate_number }}</td>
                                    <td class="px-5 py-3 font-medium text-gray-700 dark:text-gray-200">{{ truck.model }}</td>
                                    <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ truck.year }}</td>
                                    <td class="px-5 py-3 text-right font-bold text-gray-900 dark:text-white">{{ truck.mileage?.toLocaleString() || 0 }}</td>
                                    <td class="px-5 py-3 text-center">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-700 dark:bg-zinc-800 dark:text-gray-300">
                                            <Package class="h-3 w-3" /> {{ truck.max_load_kg ? `${truck.max_load_kg} kg` : 'Not set' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <span :class="statusBadge(truck.status)" class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-semibold">{{ formatStatus(truck.status) }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button @click="openDetailsModal(truck)" class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-indigo-700 dark:hover:bg-zinc-800" title="View details"><Eye class="h-4 w-4" /></button>
                                            <button v-if="canEditFleet" @click="openEditModal(truck)" class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-indigo-50 hover:text-indigo-700 dark:hover:bg-zinc-800" title="Edit"><Edit class="h-4 w-4" /></button>
                                            <button v-if="canEditFleet" @click.stop="confirmDelete(truck)" class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-zinc-800" :aria-label="`Delete truck ${truck.truck_number}`" title="Delete"><Trash2 class="h-4 w-4" /></button>
                                        </div>
                                    </td>
                                </tr>
                            </TransitionGroup>
                        </table>
                    </div>

                    <!-- Empty state -->
                    <div v-if="filteredTrucks.length === 0" class="py-14 text-center">
                        <Truck class="mx-auto h-10 w-10 text-gray-200 dark:text-zinc-700 animate-bounce-soft" />
                        <p class="mt-2 text-sm font-semibold text-gray-400">No trucks found.</p>
                        <p v-if="canEditFleet" class="text-xs text-gray-400">Click "Add Truck" to register a vehicle.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Details Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showDetailsModal && selectedTruck" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4 bg-black/50 backdrop-blur-sm" @click.self="showDetailsModal = false">
                    <div :class="cardTheme(selectedTruck.status).bg" class="modal-panel relative w-full max-w-md overflow-hidden rounded-3xl text-white shadow-2xl">
                        <div class="pointer-events-none absolute inset-0 opacity-40" style="background: radial-gradient(ellipse at 15% 0%, rgba(255,255,255,0.35), transparent 55%);" />
                        <img v-if="selectedTruck.image_url" :src="selectedTruck.image_url" :alt="selectedTruck.model" class="absolute inset-0 h-full w-full object-cover" />
                        <Truck v-else class="absolute left-1/2 top-24 h-40 w-40 -translate-x-1/2 -translate-y-1/2 text-white/25" stroke-width="1" />
                        <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/35 to-black/75" />

                        <div class="relative p-6">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex flex-col items-start gap-2">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-white/40 bg-white/10 px-3 py-1 text-xs font-medium backdrop-blur-sm">
                                        <span class="h-1.5 w-1.5 rounded-full bg-current" /> {{ formatStatus(selectedTruck.status) }}
                                    </span>
                                    <span class="rounded-full border border-white/40 bg-white/10 px-3 py-1 font-mono text-xs font-medium backdrop-blur-sm">{{ selectedTruck.plate_number }}</span>
                                </div>
                                <div class="flex items-start gap-3">
                                    <p class="whitespace-nowrap text-xl font-bold drop-shadow">{{ selectedTruck.max_load_kg ? `${Number(selectedTruck.max_load_kg).toLocaleString()} kg` : 'No cap.' }}</p>
                                    <button @click="showDetailsModal = false" class="rounded-full border border-white/40 bg-white/10 p-1.5 backdrop-blur-sm transition hover:bg-white/25"><X class="h-4 w-4" /></button>
                                </div>
                            </div>

                            <div class="pt-28">
                                <h3 class="text-2xl font-bold leading-tight drop-shadow">{{ selectedTruck.truck_number }} · {{ selectedTruck.model }}</h3>

                                <dl class="mt-4 space-y-2 rounded-2xl border border-white/25 bg-white/10 p-4 text-sm backdrop-blur-md">
                                    <div class="flex justify-between"><dt class="text-white/70">Year</dt><dd class="font-semibold">{{ selectedTruck.year }}</dd></div>
                                    <div class="flex justify-between"><dt class="text-white/70">Mileage</dt><dd class="font-semibold">{{ (selectedTruck.mileage ?? 0).toLocaleString() }} km</dd></div>
                                    <div class="flex justify-between"><dt class="text-white/70">Max. load</dt><dd class="font-semibold">{{ selectedTruck.max_load_kg ? `${selectedTruck.max_load_kg} kg` : 'Not set' }}</dd></div>
                                    <div class="border-t border-white/20 pt-2"><dt class="mb-1 text-white/70">Remarks</dt><dd class="text-white/95">{{ selectedTruck.remarks || 'No remarks.' }}</dd></div>
                                </dl>

                                <div class="mt-4 flex gap-3">
                                    <button v-if="canEditFleet" @click="editFromDetails" :class="cardTheme(selectedTruck.status).btn" class="flex flex-1 items-center justify-center gap-2 rounded-full py-3 text-sm font-semibold text-white shadow-lg transition active:scale-[0.98]"><Edit class="h-4 w-4" /> Edit Truck</button>
                                    <button v-else @click="showDetailsModal = false" :class="cardTheme(selectedTruck.status).btn" class="flex-1 rounded-full py-3 text-sm font-semibold text-white shadow-lg transition active:scale-[0.98]">Close</button>
                                    <button v-if="canEditFleet" @click.stop="deleteFromDetails" class="flex h-12 w-12 items-center justify-center rounded-full border border-white/40 bg-white/10 backdrop-blur-sm transition hover:bg-red-600 active:scale-95" :aria-label="`Delete truck ${selectedTruck.truck_number}`" title="Delete truck"><Trash2 class="h-4 w-4" /></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Create/Edit Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overscroll-contain p-4 bg-black/50 backdrop-blur-sm" @click.self="closeModal">
                    <div class="modal-panel flex max-h-[calc(100dvh-2rem)] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                        <div class="flex shrink-0 items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-zinc-800">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ isEditing ? 'Edit Truck' : 'Add New Truck' }}</h3>
                            <button @click="closeModal" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 dark:hover:bg-zinc-800"><X class="h-4 w-4" /></button>
                        </div>

                        <form @submit.prevent="submitForm" class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain p-6">
                            <div>
                                <label class="mb-1 block text-[10px] font-semibold text-gray-500">Truck Number *</label>
                                <input v-model="form.truck_number" type="text" required
                                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                    placeholder="e.g., TRK-001">
                            </div>

                            <div>
                                <label class="mb-1 block text-[10px] font-semibold text-gray-500">Plate Number *</label>
                                <input v-model="form.plate_number" type="text" required
                                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                    placeholder="e.g., ABC-1234">
                            </div>

                            <div>
                                <label class="mb-1 block text-[10px] font-semibold text-gray-500">Model *</label>
                                <input v-model="form.model" type="text" required
                                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                    placeholder="e.g., Isuzu Elf">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="mb-1 block text-[10px] font-semibold text-gray-500">Year *</label>
                                    <input v-model.number="form.year" type="number" required min="1990" :max="new Date().getFullYear()"
                                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] font-semibold text-gray-500">Mileage (km)</label>
                                    <input v-model.number="form.mileage" type="number" step="0.01" min="0"
                                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-[10px] font-semibold text-gray-500">Maximum Load Capacity (kg) *</label>
                                <input v-model.number="form.max_load_kg" type="number" required min="0.01" step="0.01" max="99999999.99"
                                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                    placeholder="Maximum combined package weight in kilograms">
                            </div>

                            <div>
                                <label class="mb-1 block text-[10px] font-semibold text-gray-500">Status</label>
                                <select v-model="form.status"
                                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                    <option value="available">Available</option>
                                    <option value="in_use">In Use</option>
                                    <option value="under_maintenance">Under Maintenance</option>
                                    <option value="retired">Retired</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-1 block text-[10px] font-semibold text-gray-500">Remarks</label>
                                <textarea v-model="form.remarks" rows="2"
                                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                    placeholder="Additional notes..."></textarea>
                            </div>

                            <div>
                                <span class="mb-1 block text-[10px] font-semibold text-gray-500">Truck image</span>
                                <div class="flex flex-wrap items-center gap-3">
                                    <label for="truck-image-upload" class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3.5 py-2.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-900 dark:bg-indigo-950/40 dark:text-indigo-200 dark:hover:bg-indigo-900/50">
                                        <Upload class="h-4 w-4" />
                                        {{ form.image ? 'Choose another image' : (isEditing ? 'Replace truck image' : 'Upload truck image') }}
                                        <input id="truck-image-upload" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="selectTruckImage">
                                    </label>
                                    <span class="text-[10px] text-gray-400">JPG, PNG, or WebP · max 5 MB</span>
                                </div>
                                <img v-if="imagePreview" :src="imagePreview" alt="Truck image preview" class="mt-3 h-36 w-full rounded-xl border border-gray-200 object-cover dark:border-zinc-700">
                                <p v-else-if="isEditing" class="mt-2 text-[10px] text-gray-400">No truck image uploaded yet.</p>
                            </div>

                            <div class="flex gap-3 border-t border-gray-100 pt-4 dark:border-zinc-800">
                                <button type="button" @click="closeModal"
                                    class="flex-1 rounded-lg border border-gray-200 px-4 py-2.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 active:scale-95 dark:border-zinc-700 dark:text-gray-300 dark:hover:bg-zinc-800">
                                    Cancel
                                </button>
                                <button v-if="canEditFleet" type="submit" :disabled="form.processing"
                                    class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-indigo-700 px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-indigo-800 active:scale-95 disabled:opacity-50">
                                    <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                                    {{ form.processing ? 'Saving...' : (isEditing ? 'Update' : 'Create') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Delete Confirmation Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showDeleteModal && truckToDelete" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" role="alertdialog" aria-modal="true" aria-labelledby="delete-truck-title" aria-describedby="delete-truck-description" @click.self="closeDeleteModal">
                    <div class="modal-panel w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900" @click.stop>
                        <div class="space-y-4 p-6">
                            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-red-50 text-red-600 dark:bg-red-900/20">
                                <Trash2 class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 id="delete-truck-title" class="text-sm font-bold text-gray-900 dark:text-white">Delete this truck?</h3>
                                <p id="delete-truck-description" class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                    Delete <span class="font-bold">{{ truckToDelete.truck_number }}</span><span v-if="truckToDelete.plate_number"> ({{ truckToDelete.plate_number }})</span> from the fleet?
                                </p>
                                <p class="mt-1 text-[11px] font-medium text-red-600 dark:text-red-400">This action cannot be undone.</p>
                            </div>
                            <div class="flex gap-3">
                                <button @click="closeDeleteModal" :disabled="deleting" class="flex-1 rounded-lg border border-gray-200 px-4 py-2.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 active:scale-95 disabled:opacity-50 dark:border-zinc-700 dark:text-gray-300 dark:hover:bg-zinc-800">Cancel</button>
                                <button v-if="canEditFleet" @click="deleteTruck" :disabled="deleting || !truckToDelete" class="flex flex-1 items-center justify-center gap-1 rounded-lg bg-red-600 px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-red-700 active:scale-95 disabled:opacity-50">
                                    <Loader2 v-if="deleting" class="mr-1 inline h-4 w-4 animate-spin" />
                                    {{ deleting ? 'Deleting…' : 'Yes, delete truck' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Toast Notification -->
        <Transition name="toast">
            <div v-if="toast.show" class="fixed bottom-8 right-8 z-[70] rounded-xl px-5 py-3 text-xs font-semibold text-white shadow-lg"
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
import { Truck, Plus, Search, Edit, Trash2, X, Loader2, Package, LayoutGrid, List, Eye, Upload } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditFleet = computed(() => canEdit('LOG', 'fleet'));

const props = defineProps({
    trucks: {
        type: Array,
        default: () => []
    }
});

// Filters & view
const searchTerm = ref('');
const statusFilter = ref('all');
const modelFilter = ref('all');
const viewMode = ref('grid');

// Modal states
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const showDeleteModal = ref(false);
const truckToDelete = ref(null);
const deleting = ref(false);
const showDetailsModal = ref(false);
const selectedTruck = ref(null);
const toast = ref({ show: false, type: 'success', message: '' });
const imagePreview = ref(null);

const form = useForm({
    truck_number: '',
    plate_number: '',
    model: '',
    year: new Date().getFullYear(),
    mileage: 0,
    max_load_kg: '',
    status: 'available',
    remarks: '',
    image: null
});

const modelOptions = computed(() => [...new Set(props.trucks.map(t => t.model).filter(Boolean))].sort());

const filteredTrucks = computed(() => {
    let list = props.trucks;
    if (searchTerm.value) {
        const term = searchTerm.value.toLowerCase();
        list = list.filter(t =>
            t.truck_number.toLowerCase().includes(term) ||
            t.plate_number.toLowerCase().includes(term) ||
            t.model.toLowerCase().includes(term)
        );
    }
    if (statusFilter.value !== 'all') {
        list = list.filter(t => t.status === statusFilter.value);
    }
    if (modelFilter.value !== 'all') {
        list = list.filter(t => t.model === modelFilter.value);
    }
    return list;
});

const availableCount = computed(() => props.trucks.filter(t => t.status === 'available').length);
const inUseCount = computed(() => props.trucks.filter(t => t.status === 'in_use').length);
const maintenanceCount = computed(() => props.trucks.filter(t => t.status === 'under_maintenance').length);
const retiredCount = computed(() => props.trucks.filter(t => t.status === 'retired').length);

const tabs = computed(() => [
    { key: 'all', label: 'All vehicles', count: props.trucks.length },
    { key: 'available', label: 'Available', count: availableCount.value },
    { key: 'in_use', label: 'In use', count: inUseCount.value },
    { key: 'under_maintenance', label: 'Service needed', count: maintenanceCount.value },
    { key: 'retired', label: 'Retired', count: retiredCount.value },
]);

const currentTabLabel = computed(() => tabs.value.find(t => t.key === statusFilter.value)?.label || 'All vehicles');

const statusBadge = (status) => {
    const map = {
        available: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 dark:text-emerald-300',
        in_use: 'text-indigo-700 bg-indigo-50 dark:bg-indigo-500/10 dark:text-indigo-300',
        under_maintenance: 'text-red-600 bg-red-50 dark:bg-red-500/10 dark:text-red-300',
        retired: 'text-gray-600 bg-gray-100 dark:bg-zinc-800 dark:text-gray-300'
    };
    return map[status] || 'text-gray-600 bg-gray-100 dark:bg-zinc-800 dark:text-gray-300';
};

const cardTheme = (status) => {
    const map = {
        available: { bg: 'bg-gradient-to-br from-[#9a8a5c] via-[#6e6440] to-[#453f2c]', btn: 'bg-[#6b6b2f] hover:bg-[#7a7a36]' },
        in_use: { bg: 'bg-gradient-to-br from-[#6b7b9a] via-[#47556f] to-[#2b3548]', btn: 'bg-[#3f4f73] hover:bg-[#4a5b82]' },
        under_maintenance: { bg: 'bg-gradient-to-br from-[#b0765a] via-[#7d4e3a] to-[#46291d]', btn: 'bg-[#8a3f2a] hover:bg-[#9a4a33]' },
        retired: { bg: 'bg-gradient-to-br from-[#8a8a8a] via-[#5e5e5e] to-[#363636]', btn: 'bg-[#4b4b4b] hover:bg-[#5a5a5a]' },
    };
    return map[status] || map.retired;
};

const formatStatus = (status) => {
    const map = {
        available: 'Available',
        in_use: 'In Use',
        under_maintenance: 'Under Maintenance',
        retired: 'Retired'
    };
    return map[status] || status;
};

const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

const refreshData = () => {
    router.reload({ only: ['trucks'] });
};

const openDetailsModal = (truck) => {
    selectedTruck.value = truck;
    showDetailsModal.value = true;
};

const editFromDetails = () => {
    const truck = selectedTruck.value;
    showDetailsModal.value = false;
    openEditModal(truck);
};

const deleteFromDetails = () => {
    const truck = selectedTruck.value;
    showDetailsModal.value = false;
    confirmDelete(truck);
};

const openCreateModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    form.year = new Date().getFullYear();
    form.status = 'available';
    form.max_load_kg = '';
    form.image = null;
    imagePreview.value = null;
    showModal.value = true;
};

const openEditModal = (truck) => {
    isEditing.value = true;
    editingId.value = truck.id;
    form.truck_number = truck.truck_number;
    form.plate_number = truck.plate_number;
    form.model = truck.model;
    form.year = truck.year;
    form.mileage = truck.mileage || 0;
    form.max_load_kg = truck.max_load_kg ?? '';
    form.status = truck.status;
    form.remarks = truck.remarks || '';
    form.image = null;
    imagePreview.value = truck.image_url || null;
    showModal.value = true;
};

const selectTruckImage = (event) => {
    const file = event.target.files?.[0] || null;
    form.image = file;
    if (imagePreview.value?.startsWith('blob:')) URL.revokeObjectURL(imagePreview.value);
    imagePreview.value = file ? URL.createObjectURL(file) : (isEditing.value ? props.trucks.find((truck) => truck.id === editingId.value)?.image_url || null : null);
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
    if (imagePreview.value?.startsWith('blob:')) URL.revokeObjectURL(imagePreview.value);
    imagePreview.value = null;
};

const submitForm = () => {
    if (isEditing.value) {
        form.transform((data) => ({ ...data, _method: 'patch' })).post(route('logistics.fleet.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => {
                showToast('success', 'Truck updated successfully.');
                closeModal();
                refreshData();
            },
            onError: (errors) => {
                const errorMsg = Object.values(errors)[0] || 'Update failed.';
                showToast('error', errorMsg);
            }
        });
    } else {
        form.transform((data) => {
            const { _method, ...createData } = data;
            return createData;
        }).post(route('logistics.fleet.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showToast('success', 'Truck added successfully.');
                closeModal();
                refreshData();
            },
            onError: (errors) => {
                const errorMsg = Object.values(errors)[0] || 'Creation failed.';
                showToast('error', errorMsg);
            }
        });
    }
};

const confirmDelete = (truck) => {
    truckToDelete.value = truck;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    if (deleting.value) return;
    showDeleteModal.value = false;
    truckToDelete.value = null;
};

const deleteTruck = () => {
    if (!truckToDelete.value || deleting.value) return;
    deleting.value = true;
    router.delete(route('logistics.fleet.destroy', truckToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showToast('success', `Truck ${truckToDelete.value.truck_number} deleted.`);
            showDeleteModal.value = false;
            truckToDelete.value = null;
            refreshData();
        },
        onError: () => {
            showToast('error', 'Failed to delete truck.');
        },
        onFinish: () => {
            deleting.value = false;
        }
    });
};
</script>

<style scoped>
@keyframes bounceSoft { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
.animate-bounce-soft { animation: bounceSoft 2.4s ease-in-out infinite; }
.card-enter-active { transition: opacity 0.35s ease, transform 0.35s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(14px) scale(0.98); }
.card-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.35s ease; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-active .modal-panel, .modal-leave-active .modal-panel { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-panel { opacity: 0; transform: translateY(20px) scale(0.97); }
.modal-leave-to .modal-panel { opacity: 0; transform: translateY(10px) scale(0.98); }
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }
</style>
