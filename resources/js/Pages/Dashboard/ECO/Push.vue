<template>

    <Head title="Push Center · ECO" />

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
                            <Send class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> ECO · Dispatch Unit
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Push Center</h1>
                            <p class="text-sm text-blue-100/90">Forward approved job orders to SCM in one click.</p>
                            <span v-if="!canEditPush" class="mt-2 inline-block text-xs font-bold text-amber-700 bg-amber-50 px-3 py-1.5 rounded-full">View only</span>
                        </div>
                        <button @click="refreshData" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/25 backdrop-blur hover:bg-white/25 transition active:scale-95" title="Refresh">
                            <RefreshCw class="h-4 w-4" />
                        </button>
                        <button @click="scheduleModal.show = true" class="flex items-center gap-1.5 rounded-full bg-white px-4 py-2 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:scale-105 active:scale-95 transition" title="View delivery schedule calendar">
                            <CalendarDays class="h-4 w-4" /> Schedule
                        </button>
                    </div>

                    <!-- Search + tabs -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchQuery" type="text" placeholder="Search JO#, PO#, Client Name..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <div class="flex gap-2">
                            <button @click="activeTab = 'pending'"
                                :class="activeTab === 'pending' ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                                class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                                Pending · {{ filteredPendingOrders.length }}
                            </button>
                            <button @click="activeTab = 'pushed'"
                                :class="activeTab === 'pushed' ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                                class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                                Pushed · {{ filteredPushedOrders.length }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════════
                     PENDING TAB
                     ══════════════════════════════════════════════════════════ -->
                <div v-if="activeTab === 'pending'" class="animate-fade-up">

                    <!-- Mobile cards -->
                    <TransitionGroup name="card" tag="div" class="md:hidden space-y-4">
                        <div v-for="(jo, i) in filteredPendingOrders" :key="jo.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                            class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur p-5 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 overflow-hidden flex flex-col gap-4">
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-amber-500 to-orange-500 rounded-r-full" />

                            <div class="relative pl-2 flex justify-between items-start">
                                <div>
                                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-0.5">
                                        Client Name</p>
                                    <h3 class="text-base font-black text-gray-900 dark:text-white leading-tight">
                                        {{ jo.client?.company_name }}
                                    </h3>
                                </div>
                                <div class="text-right flex-shrink-0 ml-4">
                                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-0.5">
                                        Volume</p>
                                    <span
                                        class="text-sm font-black text-gray-900 dark:text-white bg-gray-100 dark:bg-zinc-800 px-3 py-1.5 rounded-xl border border-gray-100 dark:border-zinc-700">{{
                                            jo.quantity }} kg</span>
                                </div>
                            </div>

                            <div class="relative grid grid-cols-2 gap-3 pl-2">
                                <div class="bg-blue-50/70 dark:bg-blue-900/10 p-3 rounded-2xl border border-blue-100 dark:border-blue-800/40">
                                    <p class="text-[9px] font-black uppercase tracking-widest text-blue-400 mb-1">JO
                                        Number</p>
                                    <p class="text-xs font-bold text-blue-600 dark:text-blue-300 uppercase">{{ jo.jo_number }}</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-zinc-800/60 p-3 rounded-2xl border border-gray-100 dark:border-zinc-700">
                                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">PO
                                        Number</p>
                                    <p class="text-[10px] font-mono font-bold text-gray-700 dark:text-gray-300">{{ jo.purchase_order_id }}
                                    </p>
                                </div>
                            </div>

                            <div class="relative grid grid-cols-1 sm:grid-cols-2 gap-3 pl-2">
                                <div>
                                    <p class="text-[9px] font-black uppercase text-gray-400 mb-0.5">Color</p>
                                    <p
                                        class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase truncate flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span> {{ jo.color }}
                                    </p>
                                </div>
                                <!-- ── RECIPE (mobile) ── -->
                                <div>
                                    <p class="text-[9px] font-black uppercase text-gray-400 mb-1">Recipe Number(s)</p>
                                    <div class="flex flex-wrap gap-1">
                                        <template v-if="jo.extracted_recipes && jo.extracted_recipes.length > 0">
                                            <div v-for="(rec, idx) in jo.extracted_recipes" :key="idx"
                                                class="flex flex-col gap-0.5">
                                                <span
                                                    class="text-[10px] font-mono font-bold text-gray-700 dark:text-gray-300 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 px-1.5 py-0.5 rounded-lg">
                                                    REC-{{ String(rec).padStart(4, '0') }}
                                                </span>
                                                <span v-if="idx === 0 && jo.recipe && jo.recipe.yarn_type"
                                                    class="text-[8px] text-gray-400 font-medium leading-none truncate max-w-[110px]">
                                                    {{ jo.recipe.yarn_type }}
                                                </span>
                                            </div>
                                        </template>
                                        <span v-else
                                            class="text-[10px] font-bold text-gray-500 font-mono bg-gray-50 dark:bg-zinc-800 px-1.5 py-0.5 rounded-lg border border-gray-100 dark:border-zinc-700">
                                            N/A
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Total Amount (mobile) ── -->
                            <div class="relative pl-2">
                                <p class="text-[9px] font-black uppercase text-gray-400 mb-0.5">Total Amount</p>
                                <p class="text-sm font-black text-indigo-600">₱{{ formatPrice(jo.total_amount) }}</p>
                            </div>

                            <div class="relative flex items-center gap-2 pl-2 mt-1">
                                <button @click="openSummary(jo)"
                                    class="flex-1 py-3 bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-gray-300 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-100 hover:text-indigo-700 transition-colors flex justify-center items-center gap-2 active:scale-95">
                                    <Eye class="h-4 w-4" /> Summary
                                </button>
                                <button @click="openDss(jo)" :disabled="dssChecking[jo.id]"
                                    class="flex-1 py-3 bg-teal-50 dark:bg-teal-900/20 text-teal-700 dark:text-teal-300 border border-teal-200 dark:border-teal-800 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-teal-100 transition-colors flex justify-center items-center gap-2 active:scale-95 disabled:opacity-50">
                                    <Loader2 v-if="dssChecking[jo.id]" class="h-4 w-4 animate-spin" />
                                    <Activity v-else class="h-4 w-4" /> Check Inv
                                </button>
                                <button v-if="canEditPush" @click="openConfirm(jo, 'scm')" :disabled="pushing[jo.id]"
                                    class="flex-1 py-3 bg-gradient-to-br from-indigo-600 to-violet-700 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all disabled:opacity-50 flex justify-center items-center gap-2 shadow-lg shadow-indigo-500/25 hover:scale-[1.02] active:scale-95">
                                    <Loader2 v-if="pushing[jo.id]" class="h-4 w-4 animate-spin" />
                                    Push SCM
                                </button>
                            </div>
                        </div>
                    </TransitionGroup>

                    <!-- Desktop table -->
                    <div
                        class="hidden md:block bg-white dark:bg-zinc-900 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden hover:shadow-xl transition-shadow duration-300">
                        <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                            <Send class="h-5 w-5 text-indigo-600" />
                            <h2 class="text-sm font-black uppercase tracking-widest">Pending Push</h2>
                            <span class="ml-auto rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 text-[11px] font-black px-2.5 py-1">{{ filteredPendingOrders.length }} orders</span>
                        </div>
                        <table class="w-full text-left">
                            <thead
                                class="text-[10px] font-black uppercase text-gray-400 tracking-widest border-b border-gray-100 dark:border-zinc-800">
                                <tr>
                                    <th class="px-6 py-4">Order Identifiers</th>
                                    <th class="px-6 py-4">Product Specifications</th>
                                    <th class="px-6 py-4 text-center">Volume / Value</th>
                                    <th class="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="jo in filteredPendingOrders" :key="jo.id"
                                    class="hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors">

                                    <!-- Order identifiers -->
                                    <td class="px-6 py-5">
                                        <div class="flex items-start gap-3">
                                            <div
                                                class="h-10 w-10 mt-1 rounded-2xl bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 text-white flex items-center justify-center font-black text-xs shadow-lg flex-shrink-0">
                                                JO
                                            </div>
                                            <div class="space-y-2.5">
                                                <div>
                                                    <span
                                                        class="text-[9px] font-black uppercase tracking-widest text-gray-400 block mb-0.5">Client
                                                        Name</span>
                                                    <p class="text-sm font-black text-gray-900 dark:text-white">{{ jo.client?.company_name
                                                    }}</p>
                                                </div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <div
                                                        class="flex items-center gap-1.5 bg-blue-50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-800/40 px-2 py-1 rounded-lg">
                                                        <span class="text-[9px] font-black uppercase text-blue-400">JO
                                                            Number:</span>
                                                        <span class="text-xs font-bold text-blue-600 dark:text-blue-300 uppercase">{{
                                                            jo.jo_number }}</span>
                                                    </div>
                                                    <div
                                                        class="flex items-center gap-1.5 bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 px-2 py-1 rounded-lg">
                                                        <span class="text-[9px] font-black uppercase text-gray-400">PO
                                                            Number:</span>
                                                        <span class="text-[10px] font-mono font-bold text-gray-600 dark:text-gray-300">{{
                                                            jo.purchase_order_id
                                                        }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Product specs + Recipe -->
                                    <td class="px-6 py-5">
                                        <div class="space-y-2.5">
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="text-[9px] font-black uppercase text-gray-400 w-14">Color:</span>
                                                <span
                                                    class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase flex items-center gap-1.5">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span> {{ jo.color
                                                    }}
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="text-[9px] font-black uppercase text-gray-400 w-14">Type:</span>
                                                <span class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase">{{
                                                    jo.yarn_type }}</span>
                                            </div>

                                            <!-- ── RECIPE DISPLAY (desktop) ── -->
                                            <div
                                                class="flex items-start gap-2 bg-gray-50 dark:bg-zinc-800/60 px-2 py-1.5 rounded-xl w-fit border border-gray-100 dark:border-zinc-700">
                                                <span
                                                    class="text-[9px] font-black uppercase text-gray-400 mt-0.5 flex-shrink-0">Recipe(s):</span>
                                                <div class="flex flex-wrap gap-1 max-w-[220px]">
                                                    <template
                                                        v-if="jo.extracted_recipes && jo.extracted_recipes.length > 0">
                                                        <div v-for="(rec, idx) in jo.extracted_recipes" :key="idx"
                                                            class="flex flex-col gap-0.5">
                                                            <span
                                                                class="text-[9px] font-mono text-gray-700 dark:text-gray-300 font-bold bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 px-1.5 py-0.5 rounded-lg">
                                                                REC-{{ String(rec).padStart(4, '0') }}
                                                            </span>
                                                            <span v-if="idx === 0 && jo.recipe && jo.recipe.yarn_type"
                                                                class="text-[8px] text-gray-400 font-medium leading-none truncate max-w-[110px]">
                                                                {{ jo.recipe.yarn_type }}
                                                            </span>
                                                            <span v-if="idx === 0 && jo.recipe && jo.recipe.dye_color"
                                                                class="text-[8px] text-blue-400 font-medium leading-none truncate max-w-[110px]">
                                                                {{ jo.recipe.dye_color }}
                                                            </span>
                                                        </div>
                                                    </template>
                                                    <span v-else
                                                        class="text-[9px] font-mono text-gray-500 font-bold">N/A</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Volume / Value -->
                                    <td class="px-6 py-5 text-center">
                                        <p class="text-sm font-black text-gray-900 dark:text-white">{{ jo.quantity }} kg</p>
                                        <p class="text-[10px] font-bold text-gray-400 mt-1">₱{{
                                            formatPrice(jo.total_amount) }}</p>
                                        <span v-if="jo.credit && !jo.credit.is_good_payer"
                                            class="mt-1.5 inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300" title="Client has pending payments — advisory only, you may still accept">
                                            ⚠ At Risk · ₱{{ formatPrice(jo.credit.outstanding_balance) }}
                                        </span>
                                        <span v-else-if="jo.credit && jo.credit.pending_count > 0"
                                            class="mt-1.5 inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-[9px] font-black uppercase text-blue-700 ring-1 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300" :title="`${jo.credit.pending_count} pending payment(s)`">
                                            {{ jo.credit.pending_count }} pending
                                        </span>
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-6 py-5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button @click="openSummary(jo)" title="Summary"
                                                class="p-3 rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 hover:bg-indigo-600 hover:text-white transition-all active:scale-95">
                                                <Eye class="h-4 w-4" />
                                            </button>
                                            <button @click="openDss(jo)" :disabled="dssChecking[jo.id]" title="Inventory check"
                                                class="p-3 rounded-xl bg-teal-50 dark:bg-teal-900/20 text-teal-600 dark:text-teal-300 border border-teal-200 dark:border-teal-800 hover:bg-teal-100 transition-all active:scale-95 disabled:opacity-50">
                                                <Loader2 v-if="dssChecking[jo.id]" class="h-4 w-4 animate-spin" />
                                                <Activity v-else class="h-4 w-4" />
                                            </button>
                                            <button v-if="canEditPush" @click="openConfirm(jo, 'scm')" :disabled="pushing[jo.id]"
                                                class="px-5 py-3 bg-gradient-to-br from-indigo-600 to-violet-700 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all disabled:opacity-50 flex items-center gap-2 shadow-lg shadow-indigo-500/25 hover:scale-[1.02] active:scale-95">
                                                <Loader2 v-if="pushing[jo.id]" class="h-3 w-3 animate-spin" />
                                                Push SCM
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                            </tbody>
                        </table>
                    </div>

                    <div v-if="filteredPendingOrders.length === 0"
                        class="mt-4 flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                            <Search class="h-9 w-9 text-indigo-400" />
                        </div>
                        <p class="text-sm font-black text-gray-700 dark:text-gray-200">No pending orders found</p>
                        <p class="text-xs text-gray-400 mt-1">Try a different search.</p>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════════
                     PUSHED TAB
                     ══════════════════════════════════════════════════════════ -->
                <div v-if="activeTab === 'pushed'" class="animate-fade-up">

                    <!-- Mobile cards -->
                    <TransitionGroup name="card" tag="div" class="md:hidden space-y-4">
                        <div v-for="(order, i) in filteredPushedOrders" :key="order.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                            class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur p-5 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 transition-all space-y-4 overflow-hidden">
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-emerald-400/20 to-teal-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />

                            <div class="relative flex justify-between items-start">
                                <div>
                                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-0.5">
                                        Client Name</p>
                                    <h3 class="text-sm font-black text-gray-900 dark:text-white">{{ order.client?.company_name }}</h3>
                                </div>
                                <span
                                    class="px-3 py-1.5 bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 rounded-full ring-1 ring-blue-200 dark:ring-blue-500/30 text-[9px] font-black uppercase tracking-widest flex items-center gap-1">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ order.pushed_to }}
                                </span>
                            </div>

                            <div class="relative bg-gray-50 dark:bg-zinc-800/60 p-3 rounded-2xl border border-gray-100 dark:border-zinc-700">
                                <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-0.5">JO
                                    Number</p>
                                <p class="text-xs font-mono font-bold text-gray-700 dark:text-gray-300">{{ order.jo_number }}</p>
                            </div>

                            <div class="relative bg-gray-50 dark:bg-zinc-800/60 p-3 rounded-2xl border border-gray-100 dark:border-zinc-700">
                                <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-0.5">PO
                                    Number</p>
                                <p class="text-xs font-mono font-bold text-gray-700 dark:text-gray-300">{{ order.purchase_order_id }}</p>
                            </div>
                        </div>
                    </TransitionGroup>

                    <!-- Desktop table -->
                    <div
                        class="hidden md:block bg-white dark:bg-zinc-900 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden hover:shadow-xl transition-shadow duration-300">
                        <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-emerald-50 to-transparent dark:from-emerald-900/10 flex items-center gap-2">
                            <Check class="h-5 w-5 text-emerald-600" />
                            <h2 class="text-sm font-black uppercase tracking-widest">Already Pushed</h2>
                            <span class="ml-auto rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 text-[11px] font-black px-2.5 py-1">{{ filteredPushedOrders.length }} orders</span>
                        </div>
                        <table class="w-full text-left">
                            <thead
                                class="text-[10px] font-black uppercase text-gray-400 tracking-widest border-b border-gray-100 dark:border-zinc-800">
                                <tr>
                                    <th class="px-6 py-4">Client Information</th>
                                    <th class="px-6 py-4">Reference Details</th>
                                    <th class="px-6 py-4 text-right">Status / Location</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="order in filteredPushedOrders" :key="order.id"
                                    class="hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors">
                                    <td class="px-6 py-5">
                                        <span class="text-[9px] font-black uppercase text-gray-400 block mb-0.5">Client
                                            Name</span>
                                        <span class="font-black text-gray-900 dark:text-white text-sm">{{ order.client?.company_name
                                        }}</span>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="flex flex-wrap gap-2">
                                            <div>
                                                <span
                                                    class="text-[9px] font-black uppercase text-gray-400 block mb-0.5">JO
                                                    Number</span>
                                                <span
                                                    class="font-mono text-sm font-bold text-blue-600 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/10 px-2 py-1 rounded-lg border border-blue-100 dark:border-blue-800/40">{{
                                                        order.jo_number }}</span>
                                            </div>
                                            <div>
                                                <span
                                                    class="text-[9px] font-black uppercase text-gray-400 block mb-0.5">PO
                                                    Number</span>
                                                <span
                                                    class="font-mono text-sm font-bold text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-zinc-800 px-2 py-1 rounded-lg border border-gray-100 dark:border-zinc-700">{{
                                                        order.purchase_order_id }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-right">
                                        <span
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 rounded-full ring-1 ring-blue-200 dark:ring-blue-500/30 text-[10px] font-black uppercase tracking-widest">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />
                                            <Check class="h-3 w-3" />
                                            {{ order.pushed_to }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="filteredPushedOrders.length === 0"
                        class="mt-4 flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                            <Check class="h-9 w-9 text-indigo-400" />
                        </div>
                        <p class="text-sm font-black text-gray-700 dark:text-gray-200">No pushed orders yet</p>
                        <p class="text-xs text-gray-400 mt-1">Pushed orders will appear here.</p>
                    </div>
                </div>

            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════
             INVENTORY CHECK MODAL (single stock check for ECO + SCM — the old
             SCM check-inv buttons now live here)
             ══════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="dssModal.show"
                    class="fixed inset-0 z-[150] flex items-center justify-center p-4 sm:p-6 bg-black/60 backdrop-blur-sm"
                    @click.self="dssModal.show = false">
                    <div class="bg-white dark:bg-zinc-900 w-full max-w-3xl rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                        <div class="relative overflow-hidden px-6 py-5 bg-gradient-to-br from-teal-700 via-cyan-700 to-blue-800 text-white flex justify-between items-start">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <div class="relative">
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-teal-100">Inventory Check · ECO + SCM</p>
                                <h3 class="font-black text-xl tracking-tight">Can we sustain {{ dssModal.order?.jo_number }}?</h3>
                            </div>
                            <button @click="dssModal.show = false" class="relative p-2 bg-white/15 hover:bg-white/25 rounded-xl transition-colors">
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="p-6 space-y-4 overflow-y-auto">
                            <div v-if="dssModal.loading" class="flex flex-col items-center py-10 gap-3">
                                <Loader2 class="h-8 w-8 animate-spin text-teal-600" />
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">Checking stock vs accepted orders…</p>
                            </div>

                            <template v-else-if="dssModal.result">
                                <div v-if="dssModal.result.verdict === 'sufficient'"
                                    class="rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 p-4 flex items-start gap-3">
                                    <CheckCircle2 class="h-5 w-5 text-emerald-600 shrink-0 mt-0.5" />
                                    <p class="text-sm font-bold text-emerald-800 dark:text-emerald-200">Sustainable — stock covers this job after {{ dssModal.result.committed_orders }} accepted order(s). Safe to push.</p>
                                </div>
                                <div v-else-if="dssModal.result.verdict === 'insufficient'"
                                    class="rounded-2xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 p-4 flex items-start gap-3">
                                    <TriangleAlert class="h-5 w-5 text-rose-600 shrink-0 mt-0.5" />
                                    <p class="text-sm font-bold text-rose-800 dark:text-rose-200">Not sustainable — pushing is blocked. Use Request on a short row to file procurement now (PRO is notified), or suggestions are filed automatically when you attempt the push.</p>
                                </div>
                                <div v-else
                                    class="rounded-2xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-4 flex items-start gap-3">
                                    <TriangleAlert class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
                                    <p class="text-sm font-bold text-amber-800 dark:text-amber-200">{{ dssModal.result.warning }}</p>
                                </div>

                                <p v-if="dssModal.result.materials?.length" class="text-[10px] font-black uppercase tracking-widest text-gray-400">
                                    Demand vs on-hand · click the accepted-orders figure for the per-order breakdown
                                </p>
                                <div v-if="dssModal.result.materials?.length" class="rounded-2xl border border-gray-100 dark:border-zinc-700 overflow-x-auto">
                                    <table class="w-full min-w-[760px] text-xs">
                                        <thead class="bg-gray-50 dark:bg-zinc-800 text-[9px] font-black uppercase tracking-widest text-gray-400">
                                            <tr>
                                                <th class="px-3 py-2 text-left">Material</th>
                                                <th class="px-3 py-2 text-right">Need · this order</th>
                                                <th class="px-3 py-2 text-right">Committed · accepted</th>
                                                <th class="px-3 py-2 text-right">Total needed</th>
                                                <th class="px-3 py-2 text-right">On-hand</th>
                                                <th class="px-3 py-2 text-right">ATP</th>
                                                <th class="px-3 py-2 text-center">OK?</th>
                                                <th class="px-3 py-2 text-center">Procure</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50 dark:divide-zinc-800">
                                            <template v-for="m in dssModal.result.materials" :key="m.material_id">
                                            <tr class="hover:bg-indigo-50/40 dark:hover:bg-indigo-950/10">
                                                <td class="px-3 py-2 font-black whitespace-nowrap">{{ m.material_name }}</td>
                                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                                    <p class="font-bold">{{ m.required }}{{ m.unit }}</p>
                                                    <p class="text-[9px] font-medium text-gray-400">{{ m.recipe_rate ?? 0 }}{{ m.unit }}/unit × {{ m.order_qty ?? 0 }}</p>
                                                </td>
                                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                                    <button @click="toggleDssRow(m.material_id)" title="Show the accepted orders behind this total"
                                                        class="inline-flex items-center gap-1.5 group/why text-right">
                                                        <span class="font-bold group-hover/why:text-indigo-600 group-hover/why:underline underline-offset-2">{{ m.committed }}{{ m.unit }}</span>
                                                        <span class="inline-flex items-center gap-0.5 rounded-full bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-300 px-1.5 py-0.5 text-[9px] font-black">
                                                            <ChevronDown :class="['h-3 w-3 transition-transform', dssExpanded[m.material_id] && 'rotate-180']" />
                                                            {{ m.committed_orders_count ?? 0 }}
                                                        </span>
                                                    </button>
                                                </td>
                                                <td class="px-3 py-2 text-right font-black whitespace-nowrap">{{ m.total_needed }}{{ m.unit }}</td>
                                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                                    <p class="font-bold">{{ m.available }}{{ m.unit }}</p>
                                                    <p class="text-[9px] font-medium text-gray-400">unopened {{ m.unopened ?? m.available }}{{ m.unit }} + opened {{ m.opened ?? 0 }}{{ m.unit }}</p>
                                                </td>
                                                <td class="px-3 py-2 text-right font-black whitespace-nowrap" :class="m.sufficient ? 'text-emerald-600' : 'text-rose-600'">{{ m.atp }}{{ m.unit }}</td>
                                                <td class="px-3 py-2 text-center">
                                                    <CheckCircle2 v-if="m.sufficient" class="h-4 w-4 text-emerald-500 inline" />
                                                    <TriangleAlert v-else class="h-4 w-4 text-rose-500 inline" />
                                                </td>
                                                <td class="px-3 py-2 text-center whitespace-nowrap">
                                                    <span v-if="requestedReqs[m.material_id]" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 text-[10px] font-black">
                                                        <Check class="h-3 w-3" /> {{ requestedReqs[m.material_id] }}
                                                    </span>
                                                    <button v-else-if="canEditPush && !m.sufficient" @click="requestProcurement(m)" :disabled="!!requesting[m.material_id]"
                                                        title="File a procurement request for this material and notify the PRO module"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-white text-[10px] font-black uppercase tracking-wide transition-colors active:scale-95">
                                                        <Loader2 v-if="requesting[m.material_id]" class="h-3 w-3 animate-spin" />
                                                        <ClipboardList v-else class="h-3 w-3" />
                                                        {{ requesting[m.material_id] ? 'Filing…' : 'Request' }}
                                                    </button>
                                                    <span v-else class="text-gray-300 dark:text-zinc-600">—</span>
                                                </td>
                                            </tr>
                                            <tr v-if="dssExpanded[m.material_id]">
                                                <td colspan="8" class="px-3 py-3 bg-indigo-50/60 dark:bg-indigo-950/20">
                                                    <p class="text-[9px] font-black uppercase tracking-widest text-indigo-500 dark:text-indigo-300 mb-2">Accepted orders holding {{ m.material_name }} — total {{ m.committed }}{{ m.unit }}</p>
                                                    <div v-if="m.committed_lines?.length" class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                                                        <p v-for="line in m.committed_lines" :key="line.order_id"
                                                            class="rounded-xl bg-white dark:bg-zinc-900 border border-gray-100 dark:border-zinc-700 px-3 py-2 text-[11px] font-mono font-bold text-gray-700 dark:text-gray-200">
                                                            {{ line.jo_number }} <span class="text-indigo-600 dark:text-indigo-300">holds {{ line.qty }}{{ m.unit }}</span>
                                                        </p>
                                                    </div>
                                                    <p v-else class="text-[11px] font-bold text-gray-400">No accepted order holds this material — the total need is this order alone.</p>
                                                    <p v-if="m.committed_lines_extra > 0" class="mt-1.5 text-[10px] font-bold italic text-gray-400">+ {{ m.committed_lines_extra }} more order(s) included in the total</p>
                                                    <p class="mt-2 text-[11px] font-bold" :class="m.sufficient ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'">
                                                        Total {{ m.total_needed }}{{ m.unit }} (need {{ m.required }} + committed {{ m.committed }}) vs on-hand {{ m.available }}{{ m.unit }} (unopened {{ m.unopened ?? m.available }} + opened {{ m.opened ?? 0 }})
                                                        {{ m.sufficient ? '— covered.' : `— short by ${m.shortage}${m.unit}, file procurement above.` }}
                                                    </p>
                                                </td>
                                            </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </template>
                        </div>

                        <div class="p-6 border-t border-gray-100 dark:border-zinc-800 flex gap-3">
                            <button @click="dssModal.show = false"
                                class="flex-1 py-3.5 rounded-2xl bg-gray-100 dark:bg-zinc-800 text-gray-700 dark:text-gray-300 text-xs font-black uppercase tracking-widest hover:bg-gray-200 transition-colors active:scale-95">
                                Close
                            </button>
                            <button v-if="canEditPush && dssModal.result && dssModal.result.verdict !== 'insufficient'" @click="pushFromDss"
                                class="flex-1 py-3.5 rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-700 text-white text-xs font-black uppercase tracking-widest shadow-lg flex items-center justify-center gap-2 hover:scale-[1.02] active:scale-95">
                                <Send class="h-4 w-4" /> Push to SCM
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ══════════════════════════════════════════════════════════════════
             SUCCESS TOAST NOTIFICATION
             ══════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="toast">
                <div v-if="toast.show"
                    class="fixed bottom-6 right-6 z-[200] flex items-center gap-3 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 px-6 py-4 rounded-2xl shadow-2xl max-w-sm">
                    <div class="h-8 w-8 rounded-xl flex items-center justify-center flex-shrink-0"
                        :class="toast.type === 'error' ? 'bg-red-500 text-white' : 'bg-emerald-500 text-white'">
                        <Check v-if="toast.type !== 'error'" class="h-4 w-4" />
                        <X v-else class="h-4 w-4" />
                    </div>
                    <p class="text-sm font-bold leading-snug">{{ toast.message }}</p>
                </div>
            </Transition>
        </Teleport>

        <!-- ══════════════════════════════════════════════════════════════════
             PUSH CONFIRMATION MODAL
             ══════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="confirmModal.show"
                    class="fixed inset-0 z-[150] flex items-center justify-center p-4 sm:p-6 bg-black/60 backdrop-blur-sm"
                    @click.self="confirmModal.show = false">
                    <div
                        class="bg-white dark:bg-zinc-900 w-full max-w-md rounded-3xl shadow-2xl overflow-hidden animate-pop flex flex-col">

                        <!-- Modal Header -->
                        <div class="relative overflow-hidden px-6 py-5 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white flex justify-between items-start">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                            <div class="relative">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse"></span>
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-100">
                                        Confirm Action
                                    </p>
                                </div>
                                <h3 class="font-black text-xl tracking-tight">
                                    Push to {{ confirmModal.module === 'scm' ? 'SCM' : 'Order Management' }}?
                                </h3>
                            </div>
                            <button @click="confirmModal.show = false"
                                class="relative p-2 bg-white/15 hover:bg-white/25 rounded-xl transition-colors">
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <!-- Modal Body -->
                        <div class="p-6 space-y-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium leading-relaxed">
                                You are about to forward this Job Order to
                                <strong class="text-gray-900 dark:text-white">{{ confirmModal.module === 'scm' ? 'Supply Chain Management (SCM)' : 'Order Management' }}</strong>.
                                This action will move the order out of the pending queue.
                            </p>

                            <!-- Credit + inventory pre-acceptance checklist (advisory only) -->
                            <div v-if="confirmModal.order?.credit && !confirmModal.order.credit.is_good_payer"
                                class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs font-bold text-amber-800 dark:border-amber-800/50 dark:bg-amber-900/20 dark:text-amber-200">
                                ⚠ Credit notice: {{ confirmModal.order.client?.company_name }} is flagged
                                <strong>At Risk</strong> — outstanding ₱{{ formatPrice(confirmModal.order.credit.outstanding_balance) }}<span v-if="confirmModal.order.credit.pending_count"> · {{ confirmModal.order.credit.pending_count }} pending payment(s)</span>.
                                This is for notification only — you may still accept at your judgement.
                            </div>
                            <div v-else-if="confirmModal.order?.credit?.pending_count > 0"
                                class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-xs font-bold text-blue-800 dark:border-blue-800/50 dark:bg-blue-900/20 dark:text-blue-200">
                                ℹ {{ confirmModal.order.client?.company_name }} has {{ confirmModal.order.credit.pending_count }} pending payment(s) to settle (outstanding ₱{{ formatPrice(confirmModal.order.credit.outstanding_balance) }}). Advisory only — acceptance is at your judgement.
                            </div>
                            <p class="rounded-2xl bg-teal-50 border border-teal-200 p-3 text-[11px] font-bold text-teal-800 dark:bg-teal-900/20 dark:text-teal-200 dark:border-teal-800">
                                ✓ Inventory: run “Check Inv” before accepting. Blocked pushes auto-suggest procurement. Pick a delivery date below — it shows in tracking (Monti + client) as the scheduled date.
                            </p>

                            <!-- Scheduled delivery date (calendar picker) -->
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-gray-400">Scheduled delivery date</label>
                                <input v-model="deliveryDate" type="date" :min="todayStr"
                                    class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm font-bold text-gray-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                            </div>

                            <!-- Order Summary Card -->
                            <div v-if="confirmModal.order" class="bg-gray-50 dark:bg-zinc-800/60 border border-gray-100 dark:border-zinc-700 rounded-2xl p-5 space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-[9px] font-black uppercase text-gray-400 tracking-widest">Client</span>
                                    <span class="text-sm font-black text-gray-900 dark:text-white">{{ confirmModal.order.client?.company_name }}</span>
                                </div>
                                <div class="flex justify-between items-center border-t border-gray-100 dark:border-zinc-700 pt-3">
                                    <span class="text-[9px] font-black uppercase text-gray-400 tracking-widest">JO Number</span>
                                    <span class="text-xs font-bold text-blue-600 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/10 px-2 py-1 rounded-lg border border-blue-100 dark:border-blue-800/40">
                                        {{ confirmModal.order.jo_number }}
                                    </span>
                                </div>
                                <div class="flex justify-between items-center border-t border-gray-100 dark:border-zinc-700 pt-3">
                                    <span class="text-[9px] font-black uppercase text-gray-400 tracking-widest">PO Number</span>
                                    <span class="text-xs font-mono font-bold text-gray-600 dark:text-gray-300 bg-white dark:bg-zinc-800 px-2 py-1 rounded-lg border border-gray-100 dark:border-zinc-700">
                                        {{ confirmModal.order.purchase_order_id ?? 'N/A' }}
                                    </span>
                                </div>
                                <div class="flex justify-between items-center border-t border-gray-100 dark:border-zinc-700 pt-3">
                                    <span class="text-[9px] font-black uppercase text-gray-400 tracking-widest">Volume</span>
                                    <span class="text-sm font-black text-gray-900 dark:text-white">{{ confirmModal.order.quantity }} kg</span>
                                </div>
                                <div class="flex justify-between items-center border-t border-gray-100 dark:border-zinc-700 pt-3">
                                    <span class="text-[9px] font-black uppercase text-gray-400 tracking-widest">Amount</span>
                                    <span class="text-sm font-black text-indigo-600">₱{{ formatPrice(confirmModal.order.total_amount) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="p-6 border-t border-gray-100 dark:border-zinc-800 flex gap-3">
                            <button @click="confirmModal.show = false"
                                class="flex-1 py-3.5 rounded-2xl bg-gray-100 dark:bg-zinc-800 text-gray-700 dark:text-gray-300 text-xs font-black uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-zinc-700 transition-colors active:scale-95">
                                Cancel
                            </button>
                            <button @click="executePush"
                                :disabled="pushing[confirmModal.order?.id]"
                                class="flex-1 py-3.5 rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-700 text-white text-xs font-black uppercase tracking-widest transition-all shadow-lg shadow-indigo-500/25 hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-2 disabled:opacity-50">
                                <Loader2 v-if="pushing[confirmModal.order?.id]" class="h-4 w-4 animate-spin" />
                                <Send v-else class="h-4 w-4" />
                                Confirm Push
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ══════════════════════════════════════════════════════════════════
             DELIVERY SCHEDULE CALENDAR — all JO loads, Monti-wide
             ══════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="scheduleModal.show"
                    class="fixed inset-0 z-[150] flex items-center justify-center p-4 sm:p-6 bg-black/60 backdrop-blur-sm"
                    @click.self="scheduleModal.show = false">
                    <div class="bg-white dark:bg-zinc-900 w-full max-w-3xl rounded-3xl shadow-2xl overflow-hidden animate-pop flex flex-col max-h-[90vh]">
                        <div class="relative overflow-hidden px-6 py-5 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white flex justify-between items-center">
                            <div class="relative">
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-100">JO Delivery Schedule</p>
                                <h3 class="font-black text-xl tracking-tight">{{ calendarTitle }}</h3>
                                <p class="text-[11px] text-blue-100/90">{{ scheduledList.length }} scheduled · {{ unscheduledCount }} unscheduled</p>
                            </div>
                            <div class="relative flex items-center gap-2">
                                <button @click="calOffset--" class="p-2 bg-white/15 hover:bg-white/25 rounded-xl transition"><ChevronLeft class="h-4 w-4" /></button>
                                <button @click="calOffset = 0" class="px-3 py-1.5 bg-white/15 hover:bg-white/25 rounded-xl text-[10px] font-black uppercase">Today</button>
                                <button @click="calOffset++" class="p-2 bg-white/15 hover:bg-white/25 rounded-xl transition"><ChevronRight class="h-4 w-4" /></button>
                                <button @click="scheduleModal.show = false" class="p-2 bg-white/15 hover:bg-white/25 rounded-xl transition"><X class="h-4 w-4" /></button>
                            </div>
                        </div>
                        <div class="p-6 overflow-y-auto">
                            <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">
                                <span v-for="d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="d">{{ d }}</span>
                            </div>
                            <div class="grid grid-cols-7 gap-1">
                                <div v-for="cell in calendarCells" :key="cell.key"
                                    :class="['min-h-20 rounded-2xl border p-1.5 text-left transition', cell.isToday ? 'border-indigo-400 ring-2 ring-indigo-200 bg-indigo-50/60 dark:bg-indigo-900/20' : 'border-gray-100 dark:border-zinc-800 bg-gray-50/50 dark:bg-zinc-800/40', !cell.inMonth && 'opacity-40']">
                                    <p class="text-[11px] font-black" :class="cell.isToday ? 'text-indigo-700 dark:text-indigo-300' : 'text-gray-500'">{{ cell.day }}</p>
                                    <div class="mt-1 space-y-1">
                                        <span v-for="o in cell.orders.slice(0, 3)" :key="o.id" :title="`${o.jo_number} · ${o.client?.company_name}`"
                                            class="block truncate rounded-lg bg-indigo-600 px-1.5 py-0.5 text-[9px] font-black text-white">{{ o.jo_number }}</span>
                                        <span v-if="cell.orders.length > 3" class="block text-[9px] font-black text-indigo-600">+{{ cell.orders.length - 3 }} more</span>
                                    </div>
                                </div>
                            </div>
                            <div v-if="unscheduledCount > 0" class="mt-4 rounded-2xl bg-amber-50 border border-amber-200 p-3 text-[11px] font-bold text-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                                {{ unscheduledCount }} pushed JO(s) have no delivery date yet — open them and set one via Push SCM.
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ══════════════════════════════════════════════════════════════════
             SUMMARY MODAL
             ══════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="summaryModal.show"
                    class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 bg-black/60 backdrop-blur-sm"
                    @click.self="summaryModal.show = false">
                    <div
                        class="bg-white dark:bg-zinc-900 w-full max-w-xl rounded-3xl shadow-2xl overflow-hidden animate-pop flex flex-col max-h-[90vh]">

                        <!-- Modal header -->
                        <div class="relative overflow-hidden px-6 py-5 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white flex justify-between items-start">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                            <div class="relative">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-300 animate-pulse"></span>
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-100">JO Number
                                    </p>
                                </div>
                                <h3 class="font-black text-2xl tracking-tight uppercase">{{
                                    summaryModal.data.jo_number
                                }}</h3>
                            </div>
                            <button @click="summaryModal.show = false"
                                class="relative p-2 bg-white/15 hover:bg-white/25 rounded-xl transition-colors">
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <!-- Modal body -->
                        <div class="p-6 space-y-5 overflow-y-auto">

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="p-5 bg-gray-50 dark:bg-zinc-800/60 border border-gray-100 dark:border-zinc-700 rounded-3xl">
                                    <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Client
                                        Name</p>
                                    <p class="font-black text-gray-900 dark:text-white text-sm">
                                        {{ summaryModal.data.client?.company_name }}
                                    </p>
                                </div>
                                <div class="p-5 bg-gray-50 dark:bg-zinc-800/60 border border-gray-100 dark:border-zinc-700 rounded-3xl">
                                    <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">PO Number
                                    </p>
                                    <p
                                        class="font-bold font-mono text-gray-700 dark:text-gray-300 text-sm bg-white dark:bg-zinc-800 px-2 py-1 rounded-lg w-fit border border-gray-100 dark:border-zinc-700">
                                        {{ summaryModal.data.purchase_order_id }}</p>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <h4
                                    class="text-[10px] font-black uppercase text-gray-400 tracking-[0.2em] flex items-center gap-2">
                                    <span class="h-px bg-gray-200 dark:bg-zinc-700 flex-1"></span> Item Details <span
                                        class="h-px bg-gray-200 dark:bg-zinc-700 flex-1"></span>
                                </h4>

                                <div class="bg-gray-50 dark:bg-zinc-800/60 border border-gray-100 dark:border-zinc-700 rounded-3xl p-6">
                                    <div class="flex justify-between items-start mb-6">
                                        <div class="space-y-4">
                                            <div>
                                                <p
                                                    class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-0.5">
                                                    Color</p>
                                                <p class="text-lg font-black text-gray-900 dark:text-white uppercase leading-none">{{
                                                    summaryModal.data.color }}</p>
                                            </div>
                                            <div class="flex flex-col gap-3">
                                                <div>
                                                    <p
                                                        class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-0.5">
                                                        Type</p>
                                                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">{{
                                                        summaryModal.data.yarn_type }}</p>
                                                </div>

                                                <!-- ── RECIPE in Summary Modal ── -->
                                                <div>
                                                    <p
                                                        class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">
                                                        Recipe Number(s)</p>
                                                    <div class="flex flex-wrap gap-1.5">
                                                        <template
                                                            v-if="summaryModal.data.extracted_recipes && summaryModal.data.extracted_recipes.length > 0">
                                                            <div v-for="(rec, idx) in summaryModal.data.extracted_recipes"
                                                                :key="idx" class="flex flex-col gap-0.5">
                                                                <span
                                                                    class="text-[10px] font-mono font-bold text-gray-700 dark:text-gray-300 bg-white dark:bg-zinc-800 px-2 py-0.5 rounded-lg border border-gray-100 dark:border-zinc-700">
                                                                    REC-{{ String(rec).padStart(4, '0') }}
                                                                </span>
                                                                <span
                                                                    v-if="idx === 0 && summaryModal.data.recipe?.yarn_type"
                                                                    class="text-[9px] text-gray-400 font-medium truncate max-w-[160px]">
                                                                    {{ summaryModal.data.recipe.yarn_type }}
                                                                </span>
                                                                <span
                                                                    v-if="idx === 0 && summaryModal.data.recipe?.dye_color"
                                                                    class="text-[9px] text-blue-400 font-medium truncate max-w-[160px]">
                                                                    {{ summaryModal.data.recipe.dye_color }}
                                                                </span>
                                                            </div>
                                                        </template>
                                                        <span v-else
                                                            class="text-[10px] font-mono font-bold text-gray-500">N/A</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">
                                                Volume
                                            </p>
                                            <span
                                                class="block text-2xl font-black text-indigo-600 tracking-tight leading-none">{{
                                                    summaryModal.data.quantity }}<span
                                                    class="text-sm text-indigo-400 ml-1">kg</span></span>
                                        </div>
                                    </div>

                                    <div class="pt-4 border-t border-gray-100 dark:border-zinc-700 flex justify-between items-center">
                                        <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Est.
                                            Amount</span>
                                        <span class="text-base font-black text-gray-900 dark:text-white">₱{{
                                            formatPrice(summaryModal.data.total_amount) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 border-t border-gray-100 dark:border-zinc-800 flex gap-3">
                            <button @click="summaryModal.show = false"
                                class="flex-1 py-3.5 rounded-2xl bg-gray-100 dark:bg-zinc-800 text-gray-700 dark:text-gray-300 text-xs font-black uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-zinc-700 transition-colors active:scale-95">
                                Close Summary
                            </button>
                            <button v-if="canEditPush" @click="openConfirmFromSummary"
                                class="flex-1 py-3.5 rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-700 text-white text-xs font-black uppercase tracking-widest transition-all shadow-lg shadow-indigo-500/25 hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-2">
                                <Send class="h-4 w-4" />
                                Push to SCM
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { usePageAccess } from '@/composables/usePageAccess';
import { Send, RefreshCw, Search, Loader2, X, Eye, Check, Sparkles, Activity, TriangleAlert, CheckCircle2, ClipboardList, ChevronDown, ChevronLeft, ChevronRight, CalendarDays } from 'lucide-vue-next';
import axios from 'axios';

const { canEdit } = usePageAccess();
const canEditPush = computed(() => canEdit('ECO', 'push'));

// ── Props ──────────────────────────────────────────────────────────────────
const props = defineProps({
    salesOrders: { type: Array, default: () => [] },
    pushedOrders: { type: Array, default: () => [] },
});

// ── State ──────────────────────────────────────────────────────────────────
const activeTab = ref('pending');
const searchQuery = ref('');
const pushing = ref({});
const summaryModal = ref({ show: false, data: {} });

// Confirmation modal state
const confirmModal = ref({ show: false, order: null, module: null });
const deliveryDate = ref('');
const todayStr = new Date().toISOString().slice(0, 10);

// Delivery schedule calendar (all JO loads, Monti-wide)
const scheduleModal = ref({ show: false });
const calOffset = ref(0);
const calBase = computed(() => {
    const now = new Date();
    return new Date(now.getFullYear(), now.getMonth() + calOffset.value, 1);
});
const calendarTitle = computed(() => calBase.value.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' }));
const scheduledList = computed(() => [...(props.pushedOrders || []), ...(props.salesOrders || [])].filter((o) => o.expected_ship_date));
const unscheduledCount = computed(() => (props.pushedOrders || []).filter((o) => !o.expected_ship_date).length);
const calendarCells = computed(() => {
    const base = calBase.value;
    const y = base.getFullYear(), m = base.getMonth();
    const first = new Date(y, m, 1).getDay();
    const days = new Date(y, m + 1, 0).getDate();
    const todayKey = new Date().toISOString().slice(0, 10);
    const byDate = {};
    scheduledList.value.forEach((o) => {
        const k = String(o.expected_ship_date).slice(0, 10);
        (byDate[k] = byDate[k] || []).push(o);
    });
    const cells = [];
    for (let i = 0; i < first; i++) {
        const d = new Date(y, m, -first + i + 1);
        const k = d.toISOString().slice(0, 10);
        cells.push({ key: 'pad-' + i, day: d.getDate(), inMonth: false, isToday: k === todayKey, orders: byDate[k] || [] });
    }
    for (let d = 1; d <= days; d++) {
        const k = `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        cells.push({ key: k, day: d, inMonth: true, isToday: k === todayKey, orders: byDate[k] || [] });
    }
    return cells;
});

// Inventory-check state (THE single stock check for ECO + SCM, run before acceptance)
const dssModal = ref({ show: false, loading: false, order: null, result: null });
const dssChecking = ref({});
const dssExpanded = ref({});

const toggleDssRow = (materialId) => {
    dssExpanded.value[materialId] = !dssExpanded.value[materialId];
};

const openDss = async (order) => {
    dssModal.value = { show: true, loading: true, order, result: null };
    dssExpanded.value = {};
    requesting.value = {};
    requestedReqs.value = {};
    dssChecking.value[order.id] = true;
    try {
        const { data } = await axios.get(route('eco.push.dss', order.id));
        dssModal.value.result = data;
    } catch {
        dssModal.value.result = null;
        showToast('Could not run sustainability check. Pushing will still verify stock.', 'error');
    } finally {
        dssModal.value.loading = false;
        delete dssChecking.value[order.id];
    }
};

const pushFromDss = () => {
    const order = dssModal.value.order;
    dssModal.value.show = false;
    deliveryDate.value = order?.expected_ship_date ? String(order.expected_ship_date).slice(0, 10) : '';
    confirmModal.value = { show: true, order, module: 'scm' };
};

// Per-material procurement request state (shortfall rows only).
const requesting = ref({});
const requestedReqs = ref({});

const requestProcurement = async (m) => {
    const order = dssModal.value.order;
    if (!order || requesting.value[m.material_id]) return;
    requesting.value[m.material_id] = true;
    try {
        const { data } = await axios.post(route('eco.push.dss.request', order.id), { material_id: m.material_id });
        requestedReqs.value[m.material_id] = data.req_number;
        showToast(data.created
            ? `Procurement requested (${data.req_number}) — PRO module notified.`
            : `Already requested (${data.req_number}).`, 'success');
    } catch (e) {
        const msg = e?.response?.data?.message || 'Could not file procurement request.';
        const rn = e?.response?.data?.req_number;
        if (rn) requestedReqs.value[m.material_id] = rn;
        showToast(msg, 'error');
    } finally {
        delete requesting.value[m.material_id];
    }
};

// Toast notification state
const toast = ref({ show: false, message: '', type: 'success' });

// ── Computed lists ─────────────────────────────────────────────────────────
const localPending = computed(() => props.salesOrders || []);
const localPushed = computed(() => props.pushedOrders || []);

const filteredPendingOrders = computed(() => {
    if (!searchQuery.value) return localPending.value;
    const s = searchQuery.value.toLowerCase();
    return localPending.value.filter(
        (jo) =>
            jo.jo_number?.toLowerCase().includes(s) ||
            jo.purchase_order_id?.toLowerCase().includes(s) ||
            jo.client?.company_name?.toLowerCase().includes(s) ||
            jo.color?.toLowerCase().includes(s) ||
            jo.design?.toLowerCase().includes(s) ||
            (jo.extracted_recipes ?? []).some(r =>
                String(r).includes(s) ||
                ('rec-' + String(r).padStart(4, '0')).includes(s)
            )
    );
});

const filteredPushedOrders = computed(() => {
    if (!searchQuery.value) return localPushed.value;
    const s = searchQuery.value.toLowerCase();
    return localPushed.value.filter(
        (po) =>
            po.jo_number?.toLowerCase().includes(s) ||
            po.purchase_order_id?.toLowerCase().includes(s) ||
            po.client?.company_name?.toLowerCase().includes(s) ||
            po.pushed_to?.toLowerCase().includes(s)
    );
});

// ── Toast helper ───────────────────────────────────────────────────────────
const showToast = (message, type = 'success') => {
    toast.value = { show: true, message, type };
    setTimeout(() => { toast.value.show = false; }, 3500);
};

// ── Helpers ────────────────────────────────────────────────────────────────
const openSummary = (jo) => {
    summaryModal.value = { show: true, data: jo };
};

// Open confirm modal from the Summary modal's push button
const openConfirmFromSummary = () => {
    const order = summaryModal.value.data;
    summaryModal.value.show = false;
    deliveryDate.value = order?.expected_ship_date ? String(order.expected_ship_date).slice(0, 10) : '';
    confirmModal.value = { show: true, order, module: 'scm' };
};

// Open confirmation modal (from table / card buttons)
const openConfirm = (order, module) => {
    deliveryDate.value = order?.expected_ship_date ? String(order.expected_ship_date).slice(0, 10) : '';
    confirmModal.value = { show: true, order, module };
};

// Execute the actual push after user confirms
const executePush = () => {
    const { order, module } = confirmModal.value;
    if (!order) return;

    confirmModal.value.show = false;
    pushing.value[order.id] = true;

    router.post(
        route(module === 'scm' ? 'eco.push.scm' : 'eco.push.ordermgmt', order.id),
        deliveryDate.value ? { delivery_date: deliveryDate.value } : {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showToast(
                    `Order ${order.jo_number} successfully pushed to ${module === 'scm' ? 'SCM' : 'Order Management'}!`
                );
                deliveryDate.value = '';
                // Auto-switch to the "Already Pushed" tab
                activeTab.value = 'pushed';
            },
            onError: (errors) => {
                const msg = errors?.error ?? 'Failed to push order. Please try again.';
                showToast(msg, 'error');
            },
            onFinish: () => {
                delete pushing.value[order.id];
            },
        }
    );
};

const formatPrice = (v) =>
    Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });

const refreshData = () => router.reload();
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
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-active > div, .modal-leave-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from > div, .modal-leave-to > div { opacity: 0; transform: scale(0.95) translateY(10px); }
.toast-enter-active, .toast-leave-active { transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(1rem) scale(0.95); }
</style>
