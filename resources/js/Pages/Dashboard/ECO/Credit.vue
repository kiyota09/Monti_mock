<template>
    <Head title="Credit Management - ECO Module" />
    <AuthenticatedLayout>
        <div class="eco-credit-page min-h-screen bg-slate-50 dark:bg-zinc-950">
            <div class="mx-auto max-w-7xl space-y-4 px-3 pb-24 pt-3 sm:space-y-6 sm:px-6 sm:pt-6">

                <!-- Hero header -->
                <header class="animate-fade-up relative overflow-hidden rounded-[28px] bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-5 text-white shadow-xl shadow-indigo-500/20 sm:p-8">
                    <div class="absolute -right-16 -top-20 h-56 w-56 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-56 w-56 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.12]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />

                    <div class="relative flex items-start gap-3 sm:items-center sm:gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/15 shadow-lg ring-1 ring-white/30 backdrop-blur animate-pop sm:h-14 sm:w-14">
                            <CreditCard class="h-6 w-6 sm:h-7 sm:w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-semibold tracking-wide text-blue-100">
                                <Sparkles class="h-3.5 w-3.5 shrink-0" /> <span class="truncate">ECO · Financial Risk Monitoring</span>
                            </p>
                            <h1 class="mt-0.5 text-2xl font-black tracking-tight sm:text-3xl">Credit Ledger</h1>
                            <p class="mt-1 text-xs leading-relaxed text-blue-100/90 sm:text-sm">Client balances and invoices sync from Finance Receivables.</p>
                            <span v-if="!canEditCredit" class="mt-2 inline-block rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">View only</span>
                        </div>
                        <button @click="refreshData" aria-label="Refresh data" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/25 backdrop-blur transition hover:bg-white/25 active:scale-95">
                            <RefreshCw class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Outstanding highlight -->
                    <div class="relative mt-5 flex items-center justify-between gap-3 rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/20 backdrop-blur">
                        <span class="flex items-center gap-2 text-xs font-semibold text-blue-100">
                            <span class="h-2 w-2 rounded-full bg-rose-300 animate-pulse" /> Total outstanding
                        </span>
                        <span class="text-lg font-black tracking-tight sm:text-xl">₱{{ formatCurrency(totalOutstanding) }}</span>
                    </div>

                    <!-- Search + filters -->
                    <div class="relative mt-4 flex flex-col gap-3 sm:flex-row">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchTerm" type="text" placeholder="Search by company name..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3.5 pl-11 pr-4 text-base font-medium text-gray-900 shadow-lg outline-none transition placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 sm:py-3 sm:text-sm" />
                        </div>
                        <div class="grid grid-cols-3 gap-1 rounded-2xl bg-black/20 p-1 sm:flex sm:gap-1">
                            <button v-for="key in ['all','good','risk']" :key="key" @click="filterStatus = key"
                                :class="filterStatus === key ? 'bg-white text-indigo-700 shadow-md' : 'text-white/90 hover:bg-white/15'"
                                class="rounded-xl px-3 py-2.5 text-xs font-bold transition-all duration-200 active:scale-95 sm:px-4">
                                {{ key === 'all' ? 'All' : key === 'good' ? 'Good Payers' : 'At Risk' }}
                            </button>
                        </div>
                    </div>
                </header>

                <!-- Stats Cards -->
                <section class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
                    <div class="animate-fade-up group relative col-span-2 overflow-hidden rounded-3xl border border-gray-100 bg-white p-4 shadow-sm transition-all duration-300 hover:border-rose-200 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-rose-900 sm:p-5 lg:col-span-1" style="animation-delay:80ms">
                        <div class="pointer-events-none absolute -right-14 -top-14 h-36 w-36 rounded-full bg-gradient-to-br from-rose-400/20 to-orange-400/20 blur-2xl" />
                        <div class="relative flex items-center gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-rose-500 via-red-600 to-orange-600 text-white shadow-lg sm:h-12 sm:w-12">
                                <CreditCard class="h-5 w-5 sm:h-6 sm:w-6" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Total Outstanding</p>
                                <h3 class="truncate text-2xl font-black tracking-tight text-rose-600">₱{{ formatCurrency(totalOutstanding) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative overflow-hidden rounded-3xl border border-gray-100 bg-white p-4 shadow-sm transition-all duration-300 hover:border-emerald-200 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-emerald-900 sm:p-5" style="animation-delay:160ms">
                        <div class="pointer-events-none absolute -right-14 -top-14 h-36 w-36 rounded-full bg-gradient-to-br from-emerald-400/20 to-teal-400/20 blur-2xl" />
                        <div class="relative flex flex-col gap-3 sm:flex-row sm:items-center">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 text-white shadow-lg sm:h-12 sm:w-12">
                                <CheckCircle class="h-5 w-5 sm:h-6 sm:w-6" />
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Good Payers</p>
                                <h3 class="text-3xl font-black tracking-tight text-emerald-600">{{ goodPayersCount }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative overflow-hidden rounded-3xl border border-gray-100 bg-white p-4 shadow-sm transition-all duration-300 hover:border-amber-200 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-amber-900 sm:p-5" style="animation-delay:240ms">
                        <div class="pointer-events-none absolute -right-14 -top-14 h-36 w-36 rounded-full bg-gradient-to-br from-amber-400/20 to-orange-400/20 blur-2xl" />
                        <div class="relative flex flex-col gap-3 sm:flex-row sm:items-center">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 text-white shadow-lg sm:h-12 sm:w-12">
                                <AlertCircle class="h-5 w-5 sm:h-6 sm:w-6" />
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">At Risk</p>
                                <h3 class="text-3xl font-black tracking-tight text-amber-600">{{ atRiskCount }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative col-span-2 overflow-hidden rounded-3xl border border-gray-100 bg-white p-4 shadow-sm transition-all duration-300 hover:border-indigo-200 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-indigo-800 sm:p-5 lg:col-span-1" style="animation-delay:320ms">
                        <div class="pointer-events-none absolute -right-14 -top-14 h-36 w-36 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl" />
                        <div class="relative flex items-center gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg sm:h-12 sm:w-12">
                                <Building2 class="h-5 w-5 sm:h-6 sm:w-6" />
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Total Clients</p>
                                <h3 class="text-3xl font-black tracking-tight text-gray-900 dark:text-white">{{ clients.length }}</h3>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Pre-acceptance notice -->
                <div class="animate-fade-up flex gap-3 rounded-3xl border border-indigo-200 bg-indigo-50 p-4 text-xs leading-relaxed text-indigo-800 dark:border-indigo-800/50 dark:bg-indigo-900/20 dark:text-indigo-200 sm:text-sm" style="animation-delay:100ms">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-black text-white">✓</span>
                    <p class="font-medium">Pre-acceptance checklist (aligned with Push Center): before a PO is accepted, verify <strong>inventory</strong> (Push Center → Check Inv) and <strong>credits</strong> below (good payer vs pending payments). Credit flags are advisory only — Monti may still accept at their judgement.</p>
                </div>

                <!-- Pending Credit Reviews Section -->
                <section v-if="pendingCreditReviews.length > 0" class="animate-fade-up overflow-hidden rounded-3xl border border-amber-200 bg-white shadow-sm dark:border-amber-800/50 dark:bg-zinc-900" style="animation-delay:120ms">
                    <div class="flex items-center gap-2 border-b border-amber-200 bg-gradient-to-r from-amber-50 to-transparent p-4 dark:border-amber-800/50 dark:from-amber-900/20 sm:p-6">
                        <AlertCircle class="h-5 w-5 shrink-0 text-amber-600" />
                        <h2 class="text-sm font-black tracking-wide text-amber-800 dark:text-amber-300">Pending Credit Review ({{ pendingCreditReviews.length }})</h2>
                    </div>

                    <!-- Mobile cards -->
                    <ul class="divide-y divide-gray-100 dark:divide-zinc-800 md:hidden">
                        <li v-for="order in pendingCreditReviews" :key="order.id" class="space-y-3 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-mono text-sm font-black text-gray-900 dark:text-white">{{ order.po_number }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ formatDate(order.created_at) }}</p>
                                </div>
                                <span class="shrink-0 text-base font-black text-gray-900 dark:text-white">₱{{ formatCurrency(order.total_amount) }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-900/20"><Building2 class="h-4 w-4 text-indigo-600" /></span>
                                <span class="truncate text-sm font-bold text-gray-900 dark:text-white">{{ order.client?.company_name }}</span>
                            </div>
                            <button v-if="canEditCredit" @click="approveCreditReview(order)" :disabled="approving[order.id]"
                                class="flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-700 active:scale-[0.98] disabled:opacity-50">
                                <CheckCircle v-if="!approving[order.id]" class="h-4 w-4" />
                                <Loader2 v-else class="h-4 w-4 animate-spin" />
                                {{ approving[order.id] ? 'Approving...' : 'Approve' }}
                            </button>
                            <span v-else class="inline-block rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-black uppercase text-amber-600">View only</span>
                        </li>
                    </ul>

                    <!-- Desktop table -->
                    <div class="hidden overflow-x-auto md:block">
                        <table class="w-full text-left">
                            <thead class="border-b border-gray-100 text-[10px] font-black uppercase tracking-widest text-gray-400 dark:border-zinc-800">
                                <tr>
                                    <th class="px-6 py-4">PO Number</th>
                                    <th class="px-6 py-4">Client</th>
                                    <th class="px-6 py-4 text-right">Total Amount</th>
                                    <th class="px-6 py-4 text-center">Order Date</th>
                                    <th class="px-6 py-4 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="order in pendingCreditReviews" :key="order.id" class="transition-colors hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10">
                                    <td class="px-6 py-4">
                                        <span class="font-mono text-sm font-black text-gray-900 dark:text-white">{{ order.po_number }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-900/20"><Building2 class="h-4 w-4 text-indigo-600" /></span>
                                            <span class="text-sm font-bold text-gray-900 dark:text-white">{{ order.client?.company_name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right font-black text-gray-900 dark:text-white">₱{{ formatCurrency(order.total_amount) }}</td>
                                    <td class="px-6 py-4 text-center text-xs text-gray-500 dark:text-gray-400">{{ formatDate(order.created_at) }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <button v-if="canEditCredit" @click="approveCreditReview(order)" :disabled="approving[order.id]"
                                            class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-[10px] font-black uppercase tracking-wide text-white shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-700 active:scale-95 disabled:opacity-50">
                                            <CheckCircle v-if="!approving[order.id]" class="h-3 w-3" />
                                            <Loader2 v-else class="h-3 w-3 animate-spin" />
                                            {{ approving[order.id] ? 'Approving...' : 'Approve' }}
                                        </button>
                                        <span v-else class="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-black uppercase text-amber-600">View only</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Clients -->
                <section class="animate-fade-up overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" style="animation-delay:200ms">
                    <div class="flex items-center gap-2 border-b border-gray-100 bg-gradient-to-r from-indigo-50 to-transparent p-4 dark:border-zinc-800 dark:from-indigo-900/20 sm:p-6">
                        <Building2 class="h-5 w-5 shrink-0 text-indigo-600" />
                        <h2 class="text-sm font-black tracking-wide">Client Balances</h2>
                        <span class="ml-auto rounded-full bg-indigo-100 px-2.5 py-1 text-[11px] font-black text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">{{ filteredClients.length }} showing</span>
                    </div>

                    <!-- Mobile cards -->
                    <ul class="divide-y divide-gray-100 dark:divide-zinc-800 md:hidden">
                        <li v-for="client in filteredClients" :key="client.id" class="space-y-3 p-4">
                            <div class="flex items-start gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 font-black uppercase text-white shadow-lg">
                                    {{ (client.company_name ?? '?').charAt(0) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-black text-gray-900 dark:text-white">{{ client.company_name }}</p>
                                    <p class="truncate text-xs text-gray-500">{{ client.contact_person }}</p>
                                </div>
                                <span :class="client.is_good_payer ? 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30' : 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30'"
                                    class="inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-black ring-1">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ client.is_good_payer ? 'Good Payer' : 'At Risk' }}
                                </span>
                            </div>

                            <div class="rounded-2xl bg-slate-50 p-3 dark:bg-zinc-800/60">
                                <p class="text-[11px] font-semibold text-gray-500">Outstanding balance</p>
                                <p class="text-2xl font-black tracking-tight text-rose-600">₱{{ formatCurrency(client.outstanding_balance || 0) }}</p>
                                <p class="mt-0.5 text-[11px] text-gray-500">{{ client.open_receivables_count }} open Finance invoice(s)</p>
                                <p v-if="client.overdue_balance > 0" class="text-[11px] font-bold text-rose-600">₱{{ formatCurrency(client.overdue_balance) }} overdue</p>
                            </div>

                            <div v-if="client.due_dates?.length" class="flex flex-wrap gap-1.5">
                                <span v-for="due in client.due_dates" :key="due.invoice_no"
                                    class="rounded-lg px-2 py-1 text-[11px] font-semibold ring-1"
                                    :class="due.status === 'overdue' ? 'bg-rose-50 text-rose-600 ring-rose-100 dark:bg-rose-500/10 dark:ring-rose-500/20' : 'bg-gray-50 text-gray-700 ring-gray-100 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700'">
                                    {{ formatDate(due.due_date) }} <span class="text-[10px] text-gray-400">· {{ due.invoice_no }}</span>
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <span class="mr-auto text-xs font-semibold text-gray-500">{{ client.orders_count || 0 }} orders</span>
                                <button @click="openHistoryModal(client)" class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-indigo-700 active:scale-95">
                                    <ReceiptText class="h-4 w-4" /> See Invoices
                                </button>
                                <button @click="openHistoryModal(client)" title="View order history" aria-label="View order history" class="rounded-xl bg-gray-100 p-2.5 text-gray-500 transition hover:bg-indigo-600 hover:text-white active:scale-95 dark:bg-zinc-800">
                                    <History class="h-4 w-4" />
                                </button>
                            </div>
                        </li>
                        <li v-if="filteredClients.length === 0" class="px-6 py-12 text-center">
                            <Search class="mx-auto h-10 w-10 text-gray-200 animate-bounce-soft dark:text-zinc-700" />
                            <p class="mt-2 text-sm font-bold text-gray-400">No clients found.</p>
                        </li>
                    </ul>

                    <!-- Desktop table -->
                    <div class="hidden overflow-x-auto md:block">
                        <table class="w-full text-left">
                            <thead class="border-b border-gray-100 text-[10px] font-black uppercase tracking-widest text-gray-400 dark:border-zinc-800">
                                <tr>
                                    <th class="px-6 py-4">Client</th>
                                    <th class="px-6 py-4 text-right">Outstanding Balance</th>
                                    <th class="px-6 py-4">Due Dates</th>
                                    <th class="px-6 py-4 text-center">Payment Standing</th>
                                    <th class="px-6 py-4 text-center">Total Orders</th>
                                    <th class="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="client in filteredClients" :key="client.id" class="transition-colors hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 font-black uppercase text-white shadow-lg">
                                                {{ (client.company_name ?? '?').charAt(0) }}
                                            </div>
                                            <div>
                                                <p class="text-sm font-black text-gray-900 dark:text-white">{{ client.company_name }}</p>
                                                <p class="text-[10px] font-bold text-gray-400">{{ client.contact_person }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="block text-lg font-black text-rose-600">₱{{ formatCurrency(client.outstanding_balance || 0) }}</span>
                                        <span class="block text-[10px] text-gray-500">{{ client.open_receivables_count }} open Finance invoice(s)</span>
                                        <span v-if="client.overdue_balance > 0" class="block text-[10px] font-bold text-rose-600">₱{{ formatCurrency(client.overdue_balance) }} overdue</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div v-if="client.due_dates?.length" class="space-y-1">
                                            <p v-for="due in client.due_dates" :key="due.invoice_no" class="text-xs font-semibold" :class="due.status === 'overdue' ? 'text-rose-600' : 'text-gray-700 dark:text-gray-300'">
                                                {{ formatDate(due.due_date) }} <span class="text-[10px] text-gray-400">· {{ due.invoice_no }}</span>
                                            </p>
                                        </div>
                                        <span v-else class="text-xs text-gray-400">—</span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span :class="client.is_good_payer ? 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30' : 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30'"
                                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[9px] font-black uppercase ring-1">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ client.is_good_payer ? 'Good Payer' : 'At Risk' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center font-bold text-gray-700 dark:text-gray-300">{{ client.orders_count || 0 }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button @click="openHistoryModal(client)" class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-indigo-700 active:scale-95">
                                                <ReceiptText class="h-4 w-4" /> See Invoices
                                            </button>
                                            <button @click="openHistoryModal(client)" title="View order history" aria-label="View order history" class="rounded-xl bg-gray-100 p-2.5 text-gray-500 transition hover:bg-indigo-600 hover:text-white active:scale-95 dark:bg-zinc-800">
                                                <History class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredClients.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <Search class="mx-auto h-10 w-10 text-gray-200 animate-bounce-soft dark:text-zinc-700" />
                                        <p class="mt-2 text-sm font-bold text-gray-400">No clients found.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Order History Modal (bottom sheet on mobile, centered dialog on larger screens) -->
            <Teleport to="body">
                <Transition name="modal">
                    <div v-if="showHistoryModal && selectedClient" class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 backdrop-blur-sm sm:items-center sm:p-4" @click.self="showHistoryModal = false">
                        <div class="sheet flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-t-[28px] bg-white shadow-2xl dark:bg-zinc-900 sm:max-h-[90vh] sm:rounded-3xl">
                            <div class="relative flex items-center justify-between gap-3 overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-5 pb-4 pt-3 text-white sm:px-6 sm:py-5">
                                <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                                <div class="absolute inset-0 opacity-[0.12]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                                <div class="absolute left-1/2 top-2 h-1 w-10 -translate-x-1/2 rounded-full bg-white/40 sm:hidden" />
                                <div class="relative mt-3 min-w-0 sm:mt-0">
                                    <p class="flex items-center gap-1.5 text-[11px] font-semibold tracking-wide text-blue-100"><History class="h-3.5 w-3.5" /> Client Financial History</p>
                                    <h3 class="truncate text-lg font-black tracking-tight sm:text-xl">{{ selectedClient.company_name }}</h3>
                                </div>
                                <button @click="showHistoryModal = false" aria-label="Close" class="relative mt-3 shrink-0 rounded-xl bg-white/10 p-2 transition hover:bg-white/20 sm:mt-0"><X class="h-5 w-5" /></button>
                            </div>

                            <div class="overflow-y-auto p-4 pb-8 sm:p-6">
                                <div class="mb-5 grid grid-cols-3 gap-2 rounded-2xl bg-gray-50 p-3 dark:bg-zinc-800/60 sm:gap-4 sm:p-4">
                                    <div><span class="block text-[11px] text-gray-500 dark:text-gray-400 sm:text-sm">Outstanding</span> <span class="text-sm font-black text-rose-600 sm:text-base">₱{{ formatCurrency(selectedClient.outstanding_balance) }}</span></div>
                                    <div><span class="block text-[11px] text-gray-500 dark:text-gray-400 sm:text-sm">Overdue</span> <span class="text-sm font-black text-rose-600 sm:text-base">₱{{ formatCurrency(selectedClient.overdue_balance) }}</span></div>
                                    <div><span class="block text-[11px] text-gray-500 dark:text-gray-400 sm:text-sm">Status</span> <span :class="selectedClient.is_good_payer ? 'text-emerald-600' : 'text-amber-600'" class="inline-flex items-center gap-1 text-sm font-black sm:text-base"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ selectedClient.is_good_payer ? 'Good Payer' : 'At Risk' }}</span></div>
                                </div>

                                <section v-if="selectedClient.return_adjustments?.length" class="mb-6">
                                    <h4 class="mb-3 text-sm font-black text-gray-700 dark:text-gray-200">Approved return credits</h4>
                                    <div class="space-y-2">
                                        <article v-for="adjustment in selectedClient.return_adjustments" :key="adjustment.return_number" class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-3 dark:border-emerald-900 dark:bg-emerald-900/10">
                                            <div><p class="text-xs font-black text-gray-900 dark:text-white">{{ adjustment.return_number }} · {{ formatLabel(adjustment.request_type) }}</p><p class="mt-0.5 text-[11px] text-gray-500">{{ adjustment.jo_number }} · {{ adjustment.resolved_at }} · {{ adjustment.reason }}</p></div>
                                            <span class="text-sm font-black text-emerald-700 dark:text-emerald-300">−₱{{ formatCurrency(adjustment.amount) }}</span>
                                        </article>
                                    </div>
                                </section>

                                <section class="mb-6">
                                    <div class="mb-3 flex items-center justify-between gap-2">
                                        <h4 class="text-sm font-black text-gray-700 dark:text-gray-200">Finance Receivables</h4>
                                        <span class="text-[11px] font-semibold text-gray-400">{{ selectedClient.receivables_count }} invoice(s) · live ledger data</span>
                                    </div>
                                    <div v-if="selectedClient.receivables?.length" class="space-y-2">
                                        <article v-for="invoice in selectedClient.receivables" :key="invoice.id" class="rounded-2xl border border-gray-100 p-3 dark:border-zinc-800">
                                            <details class="group">
                                                <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                                    <div><p class="font-mono text-xs font-black">{{ invoice.invoice_no }}</p><p class="mt-0.5 text-[11px] text-gray-600 dark:text-gray-300">Due {{ invoice.due_date ? formatDate(invoice.due_date) : 'date not set' }} · {{ invoice.receipt_count }} receipt(s)</p><p class="mt-1 text-[10px] font-semibold text-indigo-600 dark:text-indigo-300">Click to view linked order details</p></div>
                                                    <div class="flex items-center gap-2"><span class="text-xs font-bold text-gray-700 dark:text-gray-200">Balance ₱{{ formatCurrency(invoice.balance) }}</span><span :class="invoice.status === 'overdue' ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300' : invoice.status === 'paid' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'" class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase">{{ invoice.status }}</span><ChevronDown class="h-4 w-4 text-gray-500 transition-transform group-open:rotate-180 dark:text-gray-400" /></div>
                                                </summary>
                                                <div class="mt-3 space-y-4 border-t border-gray-100 pt-3 dark:border-zinc-800">
                                                    <div class="grid grid-cols-3 gap-2 rounded-xl bg-gray-50 p-2 text-[11px] text-gray-600 dark:bg-zinc-800/60 dark:text-gray-300"><p>Invoice total<strong class="mt-0.5 block text-xs text-gray-900 dark:text-white">₱{{ formatCurrency(invoice.amount) }}</strong></p><p>Paid<strong class="mt-0.5 block text-xs text-gray-900 dark:text-white">₱{{ formatCurrency(invoice.paid) }}</strong></p><p>Balance<strong class="mt-0.5 block text-xs text-gray-900 dark:text-white">₱{{ formatCurrency(invoice.balance) }}</strong></p></div>
                                                    <section v-if="invoice.order_details" class="space-y-3">
                                                        <h5 class="text-xs font-black uppercase tracking-wide text-gray-700 dark:text-gray-200">Linked Order Details</h5>
                                                        <div class="grid grid-cols-2 gap-x-4 gap-y-2 rounded-xl bg-gray-50 p-3 text-xs dark:bg-zinc-800/60 sm:grid-cols-3">
                                                            <p v-if="invoice.order_details.job_order"><span class="block text-[10px] text-gray-500 dark:text-gray-400">Job Order</span><strong>{{ invoice.order_details.job_order }}</strong></p>
                                                            <p v-if="invoice.order_details.control_number"><span class="block text-[10px] text-gray-500 dark:text-gray-400">Control No.</span><strong>{{ invoice.order_details.control_number }}</strong></p>
                                                            <p v-if="invoice.order_details.status"><span class="block text-[10px] text-gray-500 dark:text-gray-400">Order status</span><strong class="capitalize">{{ invoice.order_details.status.replaceAll('_', ' ') }}</strong></p>
                                                            <p v-if="invoice.order_details.production_status"><span class="block text-[10px] text-gray-500 dark:text-gray-400">Production status</span><strong class="capitalize">{{ invoice.order_details.production_status.replaceAll('_', ' ') }}</strong></p>
                                                            <p v-if="invoice.order_details.order_date"><span class="block text-[10px] text-gray-500 dark:text-gray-400">Order date</span><strong>{{ formatDate(invoice.order_details.order_date) }}</strong></p>
                                                            <p v-if="invoice.order_details.expected_ship_date"><span class="block text-[10px] text-gray-500 dark:text-gray-400">Expected ship date</span><strong>{{ formatDate(invoice.order_details.expected_ship_date) }}</strong></p>
                                                            <p v-if="invoice.order_details.quantity"><span class="block text-[10px] text-gray-500 dark:text-gray-400">Quantity</span><strong>{{ invoice.order_details.quantity }}</strong></p>
                                                            <p v-if="invoice.order_details.yarn_type"><span class="block text-[10px] text-gray-500 dark:text-gray-400">Yarn</span><strong>{{ invoice.order_details.yarn_type }}</strong></p>
                                                            <p v-if="invoice.order_details.color"><span class="block text-[10px] text-gray-500 dark:text-gray-400">Color</span><strong>{{ invoice.order_details.color }}</strong></p>
                                                            <p v-if="invoice.order_details.design"><span class="block text-[10px] text-gray-500 dark:text-gray-400">Design</span><strong>{{ invoice.order_details.design }}</strong></p>
                                                            <p v-if="invoice.order_details.priority"><span class="block text-[10px] text-gray-500 dark:text-gray-400">Priority</span><strong class="capitalize">{{ invoice.order_details.priority }}</strong></p>
                                                            <p v-if="invoice.order_details.payment_status"><span class="block text-[10px] text-gray-500 dark:text-gray-400">Order payment status</span><strong class="capitalize">{{ invoice.order_details.payment_status }}</strong></p>
                                                        </div>
                                                        <div v-if="invoice.order_details.purchase_order" class="rounded-xl border border-gray-100 p-3 dark:border-zinc-700">
                                                            <div class="flex flex-wrap justify-between gap-2 text-xs"><p><span class="text-gray-500 dark:text-gray-400">Purchase Order</span><strong class="ml-2 font-mono">{{ invoice.order_details.purchase_order.po_number }}</strong></p><p v-if="invoice.order_details.purchase_order.status"><span class="text-gray-500 dark:text-gray-400">Status</span><strong class="ml-2 capitalize">{{ invoice.order_details.purchase_order.status.replaceAll('_', ' ') }}</strong></p><p v-if="invoice.order_details.purchase_order.order_date"><span class="text-gray-500 dark:text-gray-400">PO date</span><strong class="ml-2">{{ formatDate(invoice.order_details.purchase_order.order_date) }}</strong></p><p v-if="invoice.order_details.purchase_order.delivery_date"><span class="text-gray-500 dark:text-gray-400">Delivery date</span><strong class="ml-2">{{ formatDate(invoice.order_details.purchase_order.delivery_date) }}</strong></p><p v-if="invoice.order_details.purchase_order.discount_amount"><span class="text-gray-500 dark:text-gray-400">Discount</span><strong class="ml-2">₱{{ formatCurrency(invoice.order_details.purchase_order.discount_amount) }}</strong></p></div>
                                                            <div v-if="invoice.order_details.purchase_order.items?.length" class="mt-3 space-y-2">
                                                                <p class="text-[10px] font-black uppercase tracking-wide text-gray-500 dark:text-gray-400">Ordered items</p>
                                                                <div v-for="(item, index) in invoice.order_details.purchase_order.items" :key="`${item.sku || item.product}-${index}`" class="flex flex-wrap justify-between gap-x-4 gap-y-1 border-t border-gray-100 pt-2 text-xs dark:border-zinc-800">
                                                                    <div><strong>{{ item.product }}</strong><span v-if="item.sku" class="ml-2 text-[10px] text-gray-500 dark:text-gray-400">{{ item.sku }}</span><p v-if="item.category" class="text-[10px] text-gray-500 dark:text-gray-400">{{ item.category }}</p></div>
                                                                    <div class="text-right text-gray-600 dark:text-gray-300"><p>{{ item.quantity }} × ₱{{ formatCurrency(item.unit_price) }}</p><strong class="text-gray-900 dark:text-white">₱{{ formatCurrency(item.line_total) }}</strong></div>
                                                                </div>
                                                            </div>
                                                            <p v-else class="mt-2 text-xs text-gray-500 dark:text-gray-400">No line items are recorded on the linked purchase order.</p>
                                                            <p v-if="invoice.order_details.purchase_order.notes" class="mt-3 text-xs text-gray-600 dark:text-gray-300"><strong>Order notes:</strong> {{ invoice.order_details.purchase_order.notes }}</p>
                                                        </div>
                                                        <div v-if="invoice.order_details.recipe" class="rounded-xl bg-indigo-50 p-3 text-xs dark:bg-indigo-900/20"><p class="font-bold">Linked recipe <span v-if="invoice.order_details.recipe.id">#{{ invoice.order_details.recipe.id }}</span></p><p v-if="invoice.order_details.recipe.product">Product: {{ invoice.order_details.recipe.product }}</p><p v-if="invoice.order_details.recipe.yarn_type">Yarn: {{ invoice.order_details.recipe.yarn_type }}</p><p v-if="invoice.order_details.recipe.dye_color">Dye color: {{ invoice.order_details.recipe.dye_color }}</p><p v-if="invoice.order_details.recipe.weave_design">Weave/design: {{ invoice.order_details.recipe.weave_design }}</p></div>
                                                        <p class="text-right text-xs text-gray-600 dark:text-gray-300">Sales order total: <strong class="text-gray-900 dark:text-white">₱{{ formatCurrency(invoice.order_details.total_amount) }}</strong></p>
                                                    </section>
                                                    <p v-else class="rounded-xl bg-gray-50 p-3 text-xs text-gray-500 dark:bg-zinc-800/60 dark:text-gray-400">No Sales Order is linked to this invoice.</p>
                                                </div>
                                            </details>
                                        </article>
                                    </div>
                                    <p v-else class="rounded-2xl bg-gray-50 p-4 text-center text-xs text-gray-500 dark:bg-zinc-800/60">No Finance receivables are linked to this client.</p>
                                </section>

                                <h4 class="mb-3 text-sm font-black text-gray-700 dark:text-gray-200">Purchase Order History</h4>
                                <TransitionGroup name="card" tag="div" class="space-y-3">
                                    <div v-for="order in selectedClient.orders" :key="order.id" class="rounded-2xl border border-gray-100 p-4 transition-all hover:border-indigo-200 hover:shadow-lg dark:border-zinc-800 dark:hover:border-indigo-800">
                                        <div class="flex items-start justify-between gap-2">
                                            <div>
                                                <p class="font-mono text-sm font-bold text-gray-900 dark:text-white">{{ order.po_number }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(order.created_at) }}</p>
                                            </div>
                                            <span :class="order.status === 'approved' ? 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30' : 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30'" class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-black uppercase ring-1">
                                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ order.status }}
                                            </span>
                                        </div>
                                        <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-3 dark:border-zinc-800">
                                            <span class="text-sm text-gray-600 dark:text-gray-300">Total: <span class="font-black text-gray-900 dark:text-white">₱{{ formatCurrency(order.total_amount) }}</span></span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ order.items_count || 0 }} items</span>
                                        </div>
                                    </div>
                                </TransitionGroup>
                                <div v-if="!selectedClient.orders || selectedClient.orders.length === 0" class="py-8 text-center text-gray-400">
                                    <p class="text-sm font-bold">No orders found for this client.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>
            </Teleport>

            <Transition name="toast">
                <div v-if="toast.show" class="fixed inset-x-4 bottom-6 z-50 rounded-2xl px-5 py-3 text-center text-sm font-bold text-white shadow-lg sm:inset-x-auto sm:bottom-8 sm:right-8 sm:text-left"
                    :class="toast.type === 'success' ? 'bg-emerald-600' : 'bg-red-600'">
                    {{ toast.message }}
                </div>
            </Transition>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { confirmModal } from '@/lib/feedback';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { usePageAccess } from '@/composables/usePageAccess';
import { CreditCard, RefreshCw, Search, Building2, History, X, AlertCircle, CheckCircle, Loader2, Sparkles, ChevronDown } from 'lucide-vue-next';

const { canEdit } = usePageAccess();
const canEditCredit = computed(() => canEdit('ECO', 'credit'));

const props = defineProps({
    clients: { type: Array, default: () => [] },
    pendingCreditReviews: { type: Array, default: () => [] }
});

const searchTerm = ref('');
const filterStatus = ref('all');
const showHistoryModal = ref(false);
const selectedClient = ref(null);
const approving = ref({});
const toast = ref({ show: false, type: 'success', message: '' });

const filteredClients = computed(() => {
    let list = props.clients;
    if (searchTerm.value) {
        const term = searchTerm.value.toLowerCase();
        list = list.filter(c => c.company_name.toLowerCase().includes(term));
    }
    if (filterStatus.value === 'good') {
        list = list.filter(c => c.is_good_payer);
    } else if (filterStatus.value === 'risk') {
        list = list.filter(c => !c.is_good_payer);
    }
    return list;
});

const totalOutstanding = computed(() => {
    return props.clients.reduce((sum, c) => sum + (parseFloat(c.outstanding_balance) || 0), 0);
});

const goodPayersCount = computed(() => props.clients.filter(c => c.is_good_payer).length);
const atRiskCount = computed(() => props.clients.filter(c => !c.is_good_payer).length);

const formatCurrency = (val) => {
    return Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
};

const formatLabel = (value) => String(value || '').replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

const openHistoryModal = (client) => {
    selectedClient.value = client;
    showHistoryModal.value = true;
};

const approveCreditReview = async (order) => {
    if (!await confirmModal(`Approve order ${order.po_number}? This will run the credit check (advisory only) and move it toward the Push Center. At-risk clients can still be accepted at Monti's judgement.`)) return;
    approving.value[order.id] = true;
    router.post(route('eco.credit.approve-order', order.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            showToast('success', `Order ${order.po_number} approved.`);
            refreshData();
        },
        onError: (errors) => {
            showToast('error', errors.error || 'Failed to approve order.');
        },
        onFinish: () => {
            approving.value[order.id] = false;
        }
    });
};

const refreshData = () => {
    router.reload({ only: ['clients', 'pendingCreditReviews'] });
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

/* Modal: slides up as a bottom sheet on mobile, scales in on larger screens */
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-active .sheet, .modal-leave-active .sheet { transition: transform 0.35s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .sheet, .modal-leave-to .sheet { opacity: 0; transform: translateY(100%); }
@media (min-width: 640px) {
    .modal-enter-from .sheet, .modal-leave-to .sheet { transform: scale(0.95) translateY(10px); }
}

.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }

@media (prefers-reduced-motion: reduce) {
    .animate-fade-up, .animate-float, .animate-float-delayed, .animate-pop, .animate-bounce-soft { animation: none; }
}
</style>
