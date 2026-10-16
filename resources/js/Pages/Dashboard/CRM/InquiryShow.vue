<template>
    <Head :title="`Inquiry ${inquiry.hash_key} — ${inquiry.client?.company_name}`" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="h-[calc(100vh-64px)] flex flex-col overflow-hidden max-w-7xl mx-auto p-4 sm:p-6 gap-4">

                <!-- ── Hero Header ──────────────────────────────────────────── -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-5 sm:px-6 py-4 text-white shadow-xl shadow-indigo-500/20 flex-shrink-0">
                    <div class="absolute -top-16 -right-16 h-48 w-48 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-20 -left-10 h-48 w-48 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex items-center gap-2 sm:gap-3">
                        <Link :href="route('crm.inquiries')"
                            class="p-2 rounded-xl bg-white/15 ring-1 ring-white/25 backdrop-blur hover:bg-white/25 hover:scale-105 active:scale-95 transition text-white flex-shrink-0">
                            <ArrowLeft class="h-4 w-4" />
                        </Link>
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop flex-shrink-0 font-black text-lg">
                            {{ (inquiry.client?.company_name ?? '?').charAt(0) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <h1 class="text-sm sm:text-base font-black tracking-tight truncate leading-tight">
                                {{ inquiry.client?.company_name }}
                                <span class="text-blue-200 mx-1">·</span>
                                <span class="text-blue-100">
                                    {{ isBulkInquiry ? `${parsedProducts.length} Products` : inquiry.product?.name }}
                                </span>
                            </h1>
                            <p class="text-[10px] text-blue-100/80 font-bold uppercase tracking-widest mt-0.5 truncate">
                                {{ isBulkInquiry ? 'Bulk Inquiry' : `SKU: ${inquiry.product?.sku}` }}
                                · {{ inquiry.client?.email }}
                            </p>
                            <span v-if="!canEditInquiry" class="mt-1 inline-block text-[10px] font-bold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-full">View only</span>
                        </div>
                        <span :class="statusBadge(inquiry.status)" class="flex-shrink-0 hidden sm:inline-flex !bg-white/15 !text-white ring-1 ring-white/25 backdrop-blur items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse" /> {{ formatStatus(inquiry.status) }}
                        </span>
                        <button v-if="canEditInquiry" @click="openRejectModal"
                            class="flex items-center gap-1.5 px-3 py-2 bg-white/15 ring-1 ring-white/25 backdrop-blur text-white rounded-xl text-xs font-bold uppercase hover:bg-red-500/80 transition flex-shrink-0 active:scale-95">
                            <XCircle class="h-3.5 w-3.5" />
                            <span class="hidden sm:inline">Reject</span>
                        </button>
                    </div>
                </div>

                <!-- ── Mobile Tab Bar ──────────────────────────────────── -->
                <div class="animate-fade-up flex lg:hidden rounded-2xl bg-white/80 dark:bg-zinc-900/80 backdrop-blur border border-gray-100 dark:border-zinc-800 p-1 flex-shrink-0" style="animation-delay:80ms">
                    <button @click="mobilePanel = 'chat'"
                        :class="mobilePanel === 'chat' ? 'bg-gradient-to-r from-blue-700 to-indigo-700 text-white shadow-lg' : 'text-gray-400 dark:text-gray-500 hover:text-gray-600'"
                        class="flex-1 py-2.5 rounded-xl text-[11px] font-black uppercase tracking-widest transition-all">
                        Conversation
                    </button>
                    <button @click="mobilePanel = 'actions'"
                        :class="mobilePanel === 'actions' ? 'bg-gradient-to-r from-blue-700 to-indigo-700 text-white shadow-lg' : 'text-gray-400 dark:text-gray-500 hover:text-gray-600'"
                        class="flex-1 py-2.5 rounded-xl text-[11px] font-black uppercase tracking-widest transition-all">
                        Actions
                    </button>
                </div>

                <!-- ── Main Layout ─────────────────────────────────────── -->
                <div class="flex flex-1 overflow-hidden gap-4 min-h-0">

                    <!-- Left: Conversation ─────────────────────────────── -->
                    <div :class="mobilePanel !== 'chat' ? 'hidden lg:flex' : 'flex'"
                        class="animate-fade-up flex-1 flex-col overflow-hidden rounded-3xl bg-white/80 dark:bg-zinc-900/80 backdrop-blur border border-gray-100 dark:border-zinc-800 shadow-sm min-w-0" style="animation-delay:120ms">

                        <!-- Messages -->
                        <div ref="messagesContainer"
                            class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-3">
                            <TransitionGroup name="card" tag="div" class="space-y-3">
                                <div v-for="msg in liveMessages" :key="msg.id"
                                    class="flex flex-col"
                                    :class="msg.sender_type === 'eco' ? 'items-end' : 'items-start'">

                                    <!-- System event pill -->
                                    <div v-if="msg.is_system_event" class="w-full flex justify-center my-2">
                                        <div class="bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-100 dark:border-indigo-500/20 px-4 py-2.5 rounded-2xl max-w-[90%] sm:max-w-[80%] shadow-sm">
                                            <p class="text-[9px] font-bold uppercase tracking-wide text-indigo-600 dark:text-indigo-300 text-center whitespace-pre-wrap leading-relaxed">
                                                {{ msg.message }}
                                            </p>
                                            <!-- Attachments in system event -->
                                            <div v-if="msg.attachments?.length" class="mt-2 space-y-1.5">
                                                <div v-for="file in msg.attachments" :key="file.id" class="relative">
                                                    <img v-if="file.file_type?.startsWith('image/')"
                                                        :src="getFullUrl(file.file_path)"
                                                        class="max-w-full rounded-2xl cursor-pointer border border-indigo-100 dark:border-indigo-500/20 hover:scale-[1.01] transition-transform"
                                                        @click="openImagePreview(file)" />
                                                    <a v-else :href="getFullUrl(file.file_path)" target="_blank"
                                                        class="flex items-center gap-2 p-2 bg-white/60 dark:bg-zinc-800/60 rounded-xl text-xs font-medium hover:bg-white dark:hover:bg-zinc-800 transition">
                                                        <FileText class="h-3.5 w-3.5 text-indigo-400 flex-shrink-0" />
                                                        <span class="truncate text-indigo-700 dark:text-indigo-300">{{ file.file_name }}</span>
                                                    </a>
                                                    <div class="absolute bottom-1 right-1 flex gap-1">
                                                        <button v-if="file.approved_by_client && !file.is_po && canEditInquiry"
                                                            @click="openRecipeModal(file)"
                                                            class="px-2 py-1 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg text-[8px] font-black uppercase shadow-sm hover:scale-105 transition">
                                                            Create Recipe
                                                        </button>
                                                        <button v-if="file.is_po && canEditInquiry"
                                                            @click="openJobOrderModal(file)"
                                                            class="px-2 py-1 bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-lg text-[8px] font-black uppercase shadow-sm hover:scale-105 transition">
                                                            Create Job Order
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Regular message -->
                                    <div v-else
                                        :class="msg.sender_type === 'eco'
                                            ? 'bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white rounded-3xl rounded-tr-lg shadow-lg shadow-indigo-500/20'
                                            : 'bg-white dark:bg-zinc-800 text-gray-800 dark:text-gray-200 border border-gray-100 dark:border-zinc-700 rounded-3xl rounded-tl-lg shadow-sm hover:shadow-lg hover:shadow-indigo-500/15 transition-shadow'"
                                        class="max-w-[80%] sm:max-w-[65%] px-4 py-3">
                                        <p class="text-[9px] font-black uppercase tracking-widest opacity-60 mb-1">
                                            {{ msg.sender_type === 'client' ? inquiry.client?.company_name : 'CRM Team' }}
                                        </p>
                                        <p class="text-xs whitespace-pre-wrap leading-relaxed font-medium">{{ msg.message }}</p>
                                        <!-- Attachments -->
                                        <div v-if="msg.attachments?.length" class="mt-2 space-y-1.5">
                                            <div v-for="file in msg.attachments" :key="file.id" class="relative">
                                                <img v-if="file.file_type?.startsWith('image/')"
                                                    :src="getFullUrl(file.file_path)"
                                                    class="max-w-full rounded-2xl cursor-pointer border border-white/20 hover:scale-[1.01] transition-transform"
                                                    @click="openImagePreview(file)" />
                                                <a v-else :href="getFullUrl(file.file_path)" target="_blank"
                                                    class="flex items-center gap-2 p-2 bg-white/10 rounded-xl text-xs font-medium hover:bg-white/20 transition">
                                                    <FileText class="h-3.5 w-3.5 flex-shrink-0" />
                                                    <span class="truncate">{{ file.file_name }}</span>
                                                </a>
                                                <div class="absolute bottom-1 right-1 flex gap-1">
                                                    <button v-if="file.approved_by_client && !file.is_po && canEditInquiry"
                                                        @click="openRecipeModal(file)"
                                                        class="px-2 py-1 bg-blue-600 text-white rounded-lg text-[8px] font-black uppercase shadow-sm hover:bg-blue-700 transition">
                                                        Create Recipe
                                                    </button>
                                                    <button v-if="file.is_po && canEditInquiry"
                                                        @click="openJobOrderModal(file)"
                                                        class="px-2 py-1 bg-amber-400 text-amber-900 rounded-lg text-[8px] font-black uppercase shadow-sm hover:bg-amber-500 transition">
                                                        Create Job Order
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="text-[9px] opacity-50 mt-1.5 text-right font-bold">{{ formatTime(msg.created_at) }}</p>
                                        <!-- Fabric sample round status -->
                                        <div v-if="msg.sample_request" class="mt-2 flex flex-wrap items-center gap-1.5">
                                            <span class="px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-widest bg-black/10 dark:bg-white/10">
                                                {{ msg.sample_request.code }} · {{ sampleStatusLabel(msg.sample_request.status) }}
                                            </span>
                                        </div>
                                        <!-- Lab formulation (CRM + lab eyes only — never sent to the client) -->
                                        <div v-if="msg.sample_request?.formula" class="mt-2 rounded-xl bg-black/10 dark:bg-white/10 p-2.5 text-left">
                                            <p class="text-[8px] font-black uppercase tracking-widest opacity-60 mb-1">Lab formulation · internal</p>
                                        <div v-if="(msg.sample_request?.formula?.yarns ?? []).length" class="mb-1">
                                                <p v-for="(y, i) in msg.sample_request.formula.yarns" :key="i" class="text-[10px] font-bold leading-relaxed">
                                                    • 🧶 {{ y.name }}<span v-if="y.qty !== '' && y.qty != null"> — {{ y.qty }} kg/kg</span>
                                                </p>
                                            </div>
                                            <div v-if="(msg.sample_request.formula.dyestuffs ?? []).length" class="mb-1">
                                                <p v-for="(d, i) in msg.sample_request.formula.dyestuffs" :key="i" class="text-[10px] font-bold leading-relaxed">
                                                    • {{ d.name }}<span v-if="d.pct !== '' && d.pct != null"> — {{ d.pct }}%</span>
                                                </p>
                                            </div>
                                            <div v-if="(msg.sample_request.formula.auxiliaries ?? []).length" class="mb-1">
                                                <p v-for="(a, i) in msg.sample_request.formula.auxiliaries" :key="i" class="text-[10px] font-bold leading-relaxed">
                                                    • {{ a.name }}<span v-if="a.gpl !== '' && a.gpl != null"> — {{ a.gpl }} g/L</span>
                                                </p>
                                            </div>
                                            <p v-if="msg.sample_request.formula.notes" class="text-[10px] italic opacity-70">Notes: {{ msg.sample_request.formula.notes }}</p>
                                        </div>
                                    </div>
                                </div>
                            </TransitionGroup>
                            <!-- Typing indicator (real-time, no reload) -->
                            <div v-if="otherTyping" class="flex items-start mt-2 animate-fade-up">
                                <div class="bg-white dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700 rounded-3xl rounded-tl-lg shadow-sm px-4 py-3 flex items-center gap-2">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">{{ inquiry.client?.company_name ?? 'Client' }} is typing</span>
                                    <span class="flex gap-1">
                                        <span class="h-1.5 w-1.5 rounded-full bg-indigo-500 animate-bounce" style="animation-delay:0ms" />
                                        <span class="h-1.5 w-1.5 rounded-full bg-indigo-500 animate-bounce" style="animation-delay:150ms" />
                                        <span class="h-1.5 w-1.5 rounded-full bg-indigo-500 animate-bounce" style="animation-delay:300ms" />
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Reply box -->
                        <div class="flex-shrink-0 border-t border-gray-100 dark:border-zinc-800 p-3 sm:p-4 bg-white/60 dark:bg-zinc-900/60 backdrop-blur">
                            <!-- File previews -->
                            <div v-if="canEditInquiry && selectedFiles.length" class="flex gap-2 mb-3 overflow-x-auto pb-1 scrollbar-hide">
                                <div v-for="(file, i) in selectedFiles" :key="i"
                                    class="relative h-12 w-12 flex-shrink-0 bg-gray-100 dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 overflow-hidden">
                                    <img v-if="file.type.startsWith('image/')" :src="objectUrl(file)"
                                        class="h-full w-full object-cover" />
                                    <div v-else class="h-full w-full flex items-center justify-center">
                                        <FileText class="h-5 w-5 text-gray-400" />
                                    </div>
                                    <button @click="selectedFiles.splice(i, 1)"
                                        class="absolute top-0.5 right-0.5 bg-red-500 text-white rounded-full p-0.5 shadow hover:scale-110 transition">
                                        <X class="h-2.5 w-2.5" />
                                    </button>
                                </div>
                            </div>
                            <!-- Input row -->
                            <div v-if="canEditInquiry" class="flex items-center gap-2 bg-gray-50 dark:bg-zinc-800/60 rounded-2xl px-3 py-2 border border-gray-200 dark:border-zinc-700 focus-within:border-indigo-400 focus-within:ring-2 focus-within:ring-indigo-500/10 transition">
                                <input v-model="newMessage" type="text"
                                    placeholder="Reply to client…"
                                    class="flex-1 bg-transparent border-none focus:ring-0 text-sm text-gray-800 dark:text-gray-200 placeholder:text-gray-400"
                                    @input="notifyTyping"
                                    @keydown.enter.prevent="sendMessage" />
                                <button type="button" @click="$refs.fileInput.click()"
                                    class="p-1.5 hover:bg-gray-200 dark:hover:bg-zinc-700 rounded-xl transition text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <Paperclip class="h-4 w-4" />
                                </button>
                                <input ref="fileInput" type="file" class="hidden" multiple
                                    @change="e => { selectedFiles.push(...Array.from(e.target.files)); e.target.value = ''; }" />
                                <button type="button" @click="sendMessage"
                                    :disabled="sending || (!newMessage.trim() && !selectedFiles.length)"
                                    class="p-2 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl disabled:opacity-40 transition hover:scale-105 active:scale-95 shadow-lg shadow-indigo-500/25">
                                    <Send v-if="!sending" class="h-4 w-4" />
                                    <Loader2 v-else class="h-4 w-4 animate-spin" />
                                </button>
                            </div>
                            <p v-else class="text-xs font-bold text-amber-600 bg-amber-50 px-3 py-2 rounded-xl">View only — replies disabled.</p>
                        </div>
                    </div>

                    <!-- Right: Actions Panel ──────────────────────────── -->
                    <div :class="mobilePanel !== 'actions' ? 'hidden lg:block' : 'block'"
                        class="animate-fade-up w-full lg:w-[440px] xl:w-[500px] flex-shrink-0 overflow-y-auto space-y-4 pr-0.5" style="animation-delay:200ms">

                        <!-- ── Quick Actions ──────────────────────────── -->
                        <div class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 overflow-hidden shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 transition-all duration-300">
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <div class="flex items-center gap-2 px-5 py-3.5 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20">
                                <ClipboardList class="h-4 w-4 text-indigo-500 flex-shrink-0" />
                                <h3 class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest">Actions</h3>
                                <span class="ml-auto h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
                            </div>
                            <div class="p-4 space-y-2.5">
                                <button v-if="canEditInquiry" @click="openQuotationModal"
                                    class="w-full py-3 bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 text-white rounded-2xl text-xs font-black uppercase tracking-wide hover:scale-[1.01] hover:shadow-xl hover:shadow-indigo-500/25 active:scale-95 transition-all flex items-center justify-center gap-2 shadow-lg shadow-indigo-500/20">
                                    <FileText class="h-4 w-4" /> Issue Quotation
                                </button>
                                <button v-if="canEditInquiry" @click="openMeetingModal"
                                    class="w-full py-3 bg-gradient-to-r from-amber-500 via-orange-500 to-rose-500 text-white rounded-2xl text-xs font-black uppercase tracking-wide hover:scale-[1.01] hover:shadow-xl hover:shadow-orange-500/25 active:scale-95 transition-all flex items-center justify-center gap-2 shadow-lg shadow-orange-500/20">
                                    <Calendar class="h-4 w-4" /> Set Meeting
                                </button>
                                <button v-if="canEditInquiry" @click="openSampleModal"
                                    class="w-full py-3 bg-gradient-to-r from-teal-600 via-emerald-600 to-green-600 text-white rounded-2xl text-xs font-black uppercase tracking-wide hover:scale-[1.01] hover:shadow-xl hover:shadow-emerald-500/25 active:scale-95 transition-all flex items-center justify-center gap-2 shadow-lg shadow-emerald-500/20">
                                    <FlaskConical class="h-4 w-4" /> Request Fabric Sample
                                </button>
                                <p v-if="!canEditInquiry" class="text-xs font-bold text-amber-600 bg-amber-50 px-3 py-2 rounded-xl text-center">View only — actions disabled.</p>
                            </div>
                        </div>

                        <!-- ── Recipes ─────────────────────────────────── -->
                        <template v-if="recipes.length > 0">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 px-1 flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" /> Recipes ({{ recipes.length }})
                            </p>
                            <TransitionGroup name="card" tag="div" class="space-y-3">
                                <div v-for="(recipe, i) in recipes" :key="recipe.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                                    class="group bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 p-4 shadow-sm hover:shadow-xl hover:shadow-indigo-500/15 hover:-translate-y-1 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300">
                                    <p class="text-xs font-black text-gray-900 dark:text-white tracking-tight">{{ recipe.product?.name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 font-medium">{{ recipe.yarn_type }} · {{ recipe.dye_color }}</p>
                                    <p class="text-[10px] text-gray-400 mt-0.5">{{ recipe.weave_design }}</p>
                                </div>
                            </TransitionGroup>
                        </template>

                        <!-- ── Previous Quotations ────────────────────── -->
                        <template v-if="quotations.length > 0">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 px-1 flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500 animate-pulse" /> Quotations ({{ quotations.length }})
                            </p>

                            <!-- Active / Trash tabs -->
                            <div class="flex gap-1.5 px-1">
                                <button @click="quoteTab = 'active'" type="button"
                                    :class="quoteTab === 'active' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/25' : 'bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-gray-400 hover:text-indigo-600'"
                                    class="flex-1 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition active:scale-95">
                                    Active ({{ activeQuotations.length }})
                                </button>
                                <button @click="quoteTab = 'trash'" type="button"
                                    :class="quoteTab === 'trash' ? 'bg-slate-600 text-white shadow-md' : 'bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-gray-400 hover:text-slate-600'"
                                    class="flex-1 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition active:scale-95">
                                    Trash ({{ trashedQuotations.length }})
                                </button>
                            </div>

                            <TransitionGroup name="card" tag="div" class="space-y-3">
                                <div v-for="(q, i) in visibleQuotations" :key="q.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                                    class="group bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 overflow-hidden shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 transition-all duration-300">
                                    <!-- Status bar -->
                                    <div class="relative overflow-hidden flex items-center justify-between px-4 py-2.5"
                                        :class="q.status === 'accepted' ? 'bg-gradient-to-r from-emerald-600 to-teal-600'
                                            : q.status === 'rejected' ? 'bg-gradient-to-r from-red-500 to-rose-600'
                                            : q.status === 'trashed' ? 'bg-gradient-to-r from-slate-500 to-gray-600'
                                            : 'bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800'">
                                        <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 16px 16px;" />
                                        <span class="relative font-mono text-[10px] font-bold text-white">
                                            {{ q.quotation_number }}
                                        </span>
                                        <span class="relative text-[9px] font-black uppercase text-white/90 bg-black/15 ring-1 ring-white/25 px-2 py-0.5 rounded-full flex items-center gap-1">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ q.status }}
                                        </span>
                                    </div>
                                    <div class="p-3.5 space-y-2.5">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-[9px] font-black rounded-full px-2 py-0.5 ring-1"
                                                :class="q.vat_type === 'inclusive'
                                                    ? 'bg-blue-50 text-blue-600 border-blue-100 dark:bg-blue-500/10 dark:text-blue-300 dark:border-blue-500/20'
                                                    : 'bg-gray-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400 dark:border-zinc-700 border'">
                                                {{ q.vat_type === 'inclusive' ? 'VAT Inclusive (+12%)' : 'VAT Exclusive' }}
                                            </span>
                                            <span class="text-[9px] text-gray-400 font-bold uppercase">
                                                {{ q.payment_terms }}
                                            </span>
                                        </div>
                                        <div v-for="(group, fabric) in groupItemsByFabric(q.items)" :key="fabric"
                                            class="border border-gray-100 dark:border-zinc-800 rounded-2xl overflow-hidden">
                                            <div class="bg-gray-50 dark:bg-zinc-800/60 px-3 py-2 flex items-center gap-2">
                                                <Package class="h-3 w-3 text-indigo-500 flex-shrink-0" />
                                                <p class="text-[10px] font-black text-indigo-700 dark:text-indigo-300 uppercase tracking-wider truncate flex-1">
                                                    {{ fabric }}
                                                </p>
                                                <span class="text-[9px] text-gray-400 font-bold">{{ group[0]?.kilos }}kg MOQ</span>
                                            </div>
                                            <div class="divide-y divide-gray-50 dark:divide-zinc-800">
                                                <div v-for="item in group" :key="item.id"
                                                    class="px-3 py-2 flex items-center justify-between">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-2.5 h-2.5 rounded-full border flex-shrink-0"
                                                            :style="colorDotStyle(item.color)"></div>
                                                        <span class="text-[9px] font-bold text-gray-700 dark:text-gray-300 uppercase">
                                                            {{ item.color }}
                                                        </span>
                                                    </div>
                                                    <p class="text-[10px] font-black text-indigo-600 dark:text-indigo-400">
                                                        ₱{{ formatPeso(item.unit_price) }}/kg
                                                        <span v-if="q.vat_type === 'inclusive'"
                                                            class="text-[8px] text-gray-400 ml-1">inc. VAT</span>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <p v-if="q.notes"
                                            class="text-[9px] text-gray-400 italic border-t border-gray-100 dark:border-zinc-800 pt-2">
                                            {{ q.notes }}
                                        </p>
                                    </div>
                                </div>
                            </TransitionGroup>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════════
             IMAGE PREVIEW MODAL
             ════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="imagePreviewModal.show"
                    class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/90 backdrop-blur-md"
                    @click.self="imagePreviewModal.show = false">
                    <button @click="imagePreviewModal.show = false"
                        class="absolute top-5 right-5 p-2 bg-white/10 hover:bg-white/20 rounded-xl transition z-[210] active:scale-95">
                        <X class="h-5 w-5 text-white" />
                    </button>
                    <div class="w-full max-w-5xl h-[88vh] flex items-center justify-center animate-pop">
                        <img v-if="imagePreviewModal.file"
                            :src="getFullUrl(imagePreviewModal.file.file_path)"
                            class="max-w-full max-h-full object-contain rounded-3xl shadow-2xl ring-1 ring-white/20" />
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ════════════════════════════════════════════
             ISSUE QUOTATION MODAL
             ════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showQuotationModal"
                    class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center sm:p-4 bg-black/60 backdrop-blur-sm"
                    @click.self="showQuotationModal = false">
                    <div class="bg-white dark:bg-zinc-900 w-full sm:max-w-2xl rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[95vh] border border-gray-100 dark:border-zinc-800">
                        <!-- Gradient Header -->
                        <div class="relative overflow-hidden px-5 sm:px-6 py-5 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white flex-shrink-0">
                            <div class="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/10 blur-3xl animate-float" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                            <div class="relative flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-black tracking-tight">Issue Quotation</h3>
                                    <p class="text-xs text-blue-100 mt-0.5">{{ inquiry.client?.company_name }}</p>
                                </div>
                                <button @click="showQuotationModal = false"
                                    class="p-2 bg-white/15 ring-1 ring-white/25 hover:bg-white/25 rounded-xl transition active:scale-95">
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Body -->
                        <div class="p-5 sm:p-6 overflow-y-auto flex-1">
                            <form @submit.prevent="submitQuotation" class="space-y-5">

                                <!-- VAT Type Toggle -->
                                <div>
                                    <label class="block text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-2">
                                        VAT Type
                                    </label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="button" @click="form.vat_type = 'exclusive'"
                                            :class="form.vat_type === 'exclusive'
                                                ? 'bg-gray-900 dark:bg-white text-white dark:text-zinc-900 shadow-lg scale-[1.02]'
                                                : 'bg-white dark:bg-zinc-800 text-gray-500 dark:text-gray-400 border-gray-200 dark:border-zinc-700 hover:border-gray-400'"
                                            class="py-2.5 rounded-2xl border-2 text-xs font-black uppercase tracking-wide transition-all active:scale-95">
                                            VAT Exclusive
                                        </button>
                                        <button type="button" @click="form.vat_type = 'inclusive'"
                                            :class="form.vat_type === 'inclusive'
                                                ? 'bg-gradient-to-r from-blue-700 to-indigo-700 text-white shadow-lg shadow-indigo-500/25 scale-[1.02]'
                                                : 'bg-white dark:bg-zinc-800 text-gray-500 dark:text-gray-400 border-gray-200 dark:border-zinc-700 hover:border-indigo-300'"
                                            class="py-2.5 rounded-2xl border-2 text-xs font-black uppercase tracking-wide flex items-center justify-center gap-1.5 transition-all active:scale-95">
                                            VAT Inclusive
                                            <span v-if="form.vat_type === 'inclusive'"
                                                class="bg-white/20 px-1.5 py-0.5 rounded text-[10px]">+12%</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Product rows -->
                                <div class="space-y-3">
                                    <div v-for="(item, idx) in form.items" :key="idx"
                                        class="border border-gray-100 dark:border-zinc-800 bg-white/80 dark:bg-zinc-900/80 rounded-3xl overflow-hidden shadow-sm hover:shadow-lg hover:shadow-indigo-500/15 transition-shadow">
                                        <!-- Fabric name row -->
                                        <div class="flex items-center gap-2 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 px-3 py-2.5 border-b border-gray-100 dark:border-zinc-800">
                                            <Package class="h-3.5 w-3.5 text-indigo-500 flex-shrink-0" />
                                            <input v-model="item.fabric"
                                                class="flex-1 bg-transparent border-none p-0 text-xs font-black text-gray-900 dark:text-white focus:ring-0 uppercase tracking-wide min-w-0 placeholder:text-gray-400"
                                                placeholder="Fabric / Product Name" required />
                                            <button v-if="!item.isDefault" type="button" @click="removeItem(idx)"
                                                class="p-1 text-gray-400 hover:text-red-500 flex-shrink-0 transition hover:scale-110">
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </button>
                                        </div>

                                        <!-- MOQ + Design -->
                                        <div class="grid grid-cols-2 gap-3 px-3 pt-3 pb-2">
                                            <div>
                                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">
                                                    MOQ (kg)
                                                </label>
                                                <input v-model.number="item.kilos" type="number" min="0" step="0.01"
                                                    class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 text-sm font-bold py-2 px-3 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition"
                                                    placeholder="0.00" />
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">
                                                    Design <span class="normal-case font-normal">(opt.)</span>
                                                </label>
                                                <input v-model="item.design" type="text"
                                                    class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 text-sm py-2 px-3 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition"
                                                    placeholder="e.g. Standard" />
                                            </div>
                                        </div>

                                        <!-- Prices per kg -->
                                        <div class="px-3 pb-3 space-y-2">
                                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">
                                                Price per kg
                                                <span v-if="form.vat_type === 'inclusive'" class="text-indigo-500 ml-1">(VAT inclusive)</span>
                                            </p>
                                            <!-- White -->
                                            <div class="flex items-center gap-2 bg-gray-50 dark:bg-zinc-800/60 rounded-2xl p-2.5 border border-gray-100 dark:border-zinc-800">
                                                <div class="w-3.5 h-3.5 rounded-full border-2 border-gray-300 bg-white shadow-sm flex-shrink-0"></div>
                                                <span class="text-xs font-bold text-gray-600 dark:text-gray-300 uppercase w-16 flex-shrink-0">White</span>
                                                <div class="flex-1 relative">
                                                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400 pointer-events-none">₱</span>
                                                    <input v-model.number="item.price_white" type="number" min="0" step="0.01"
                                                        class="w-full pl-6 pr-2 py-1.5 rounded-xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-sm font-bold text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition"
                                                        placeholder="0.00" required />
                                                </div>
                                            </div>
                                            <!-- Light -->
                                            <div class="flex items-center gap-2 bg-amber-50/60 dark:bg-amber-500/5 rounded-2xl p-2.5 border border-amber-100 dark:border-amber-500/20">
                                                <div class="w-3.5 h-3.5 rounded-full border-2 border-amber-300 bg-amber-100 shadow-sm flex-shrink-0"></div>
                                                <span class="text-xs font-bold text-gray-600 dark:text-gray-300 uppercase w-16 flex-shrink-0">Light</span>
                                                <div class="flex-1 relative">
                                                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400 pointer-events-none">₱</span>
                                                    <input v-model.number="item.price_light" type="number" min="0" step="0.01"
                                                        class="w-full pl-6 pr-2 py-1.5 rounded-xl border border-amber-200 dark:border-amber-500/20 bg-white dark:bg-zinc-900 text-sm font-bold text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-400 transition"
                                                        placeholder="0.00" required />
                                                </div>
                                            </div>
                                            <!-- Dark -->
                                            <div class="flex items-center gap-2 bg-gray-800/5 dark:bg-zinc-800/60 rounded-2xl p-2.5 border border-gray-200 dark:border-zinc-700">
                                                <div class="w-3.5 h-3.5 rounded-full border-2 border-gray-500 bg-gray-700 shadow-sm flex-shrink-0"></div>
                                                <span class="text-xs font-bold text-gray-600 dark:text-gray-300 uppercase w-16 flex-shrink-0">Dark</span>
                                                <div class="flex-1 relative">
                                                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400 pointer-events-none">₱</span>
                                                    <input v-model.number="item.price_dark" type="number" min="0" step="0.01"
                                                        class="w-full pl-6 pr-2 py-1.5 rounded-xl border border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-sm font-bold text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-gray-500/20 focus:border-gray-400 transition"
                                                        placeholder="0.00" required />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Add product -->
                                <button type="button" @click="addItem"
                                    class="w-full py-2.5 border-2 border-dashed border-indigo-200 dark:border-indigo-800 text-indigo-600 dark:text-indigo-400 rounded-2xl text-xs font-black uppercase tracking-wide hover:bg-indigo-50 dark:hover:bg-indigo-500/10 hover:-translate-y-0.5 transition-all flex items-center justify-center gap-2">
                                    <Plus class="h-3.5 w-3.5" /> Add Another Product
                                </button>

                                <!-- Payment Terms -->
                                <div>
                                    <label class="block text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1.5">
                                        Payment Terms *
                                    </label>
                                    <select v-model="form.payment_terms" required
                                        class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 text-sm py-2.5 px-3 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition">
                                        <option value="">Select payment terms…</option>
                                        <option value="Cash on Delivery">Cash on Delivery</option>
                                        <option value="30 Days">30 Days</option>
                                        <option value="60 Days">60 Days</option>
                                        <option value="90 Days">90 Days</option>
                                        <option value="120 Days">120 Days</option>
                                        <option value="150 Days">150 Days</option>
                                    </select>
                                </div>

                                <!-- Notes -->
                                <div>
                                    <label class="block text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1.5">
                                        Notes <span class="normal-case font-normal">(optional)</span>
                                    </label>
                                    <textarea v-model="form.notes" rows="2"
                                        class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 text-sm p-3 text-gray-900 dark:text-white resize-none focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition"
                                        placeholder="Additional terms, delivery requirements…"></textarea>
                                </div>

                                <!-- Submit -->
                                <button type="submit" :disabled="submitting || !form.payment_terms"
                                    class="w-full py-3 bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 text-white rounded-2xl font-black text-xs uppercase tracking-wide flex justify-center items-center gap-2 hover:scale-[1.01] hover:shadow-xl hover:shadow-indigo-500/25 disabled:opacity-40 transition-all shadow-lg shadow-indigo-500/20 active:scale-95">
                                    <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
                                    <Send v-else class="h-4 w-4" />
                                    Send Quotation
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ════════════════════════════════════════════
             SET MEETING MODAL
             ════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showMeetingModal"
                    class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center sm:p-4 bg-black/60 backdrop-blur-sm"
                    @click.self="showMeetingModal = false">
                    <div class="bg-white dark:bg-zinc-900 w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-zinc-800">
                        <!-- Gradient Header -->
                        <div class="relative overflow-hidden px-5 py-5 bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 text-white">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                            <div class="relative flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-black tracking-tight">Schedule Meeting</h3>
                                    <p class="text-xs text-amber-100 mt-0.5">{{ inquiry.client?.company_name }}</p>
                                </div>
                                <button @click="showMeetingModal = false"
                                    class="p-2 bg-white/15 ring-1 ring-white/25 hover:bg-white/25 rounded-xl transition active:scale-95">
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <form @submit.prevent="submitMeeting" class="p-5 space-y-4">
                            <div>
                                <label class="block text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1.5">
                                    Date & Time *
                                </label>
                                <input v-model="meetingData.scheduled_at" type="datetime-local" required :min="meetingMinDateTime"
                                    class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500/20 focus:border-orange-400 transition" />
                                <p class="text-[10px] text-gray-400 mt-1 font-medium">Today or later — past dates can't be scheduled.</p>
                            </div>
                            <div>
                                <label class="block text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1.5">
                                    Location *
                                </label>
                                <input v-model="meetingData.location" type="text"
                                    placeholder="e.g., Zoom, Office, Google Meet" required
                                    class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500/20 focus:border-orange-400 transition" />
                            </div>
                            <div>
                                <label class="block text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1.5">
                                    Meeting Type *
                                </label>
                                <select v-model="meetingData.type" required
                                    class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500/20 focus:border-orange-400 transition">
                                    <option value="onsite">On‑site</option>
                                    <option value="video">Video Call</option>
                                    <option value="phone">Phone Call</option>
                                </select>
                            </div>
                            <button type="submit" :disabled="scheduling"
                                class="w-full py-3 bg-gradient-to-r from-amber-500 via-orange-500 to-rose-500 text-white rounded-2xl font-black text-xs uppercase tracking-wide flex justify-center items-center gap-2 hover:scale-[1.01] hover:shadow-xl disabled:opacity-50 transition-all shadow-lg active:scale-95">
                                <Loader2 v-if="scheduling" class="h-4 w-4 animate-spin" />
                                <Calendar v-else class="h-4 w-4" />
                                Send Invite
                            </button>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ════════════════════════════════════════════
             REQUEST FABRIC SAMPLE MODAL
             ════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showSampleModal"
                    class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center sm:p-4 bg-black/60 backdrop-blur-sm"
                    @click.self="showSampleModal = false">
                    <div class="bg-white dark:bg-zinc-900 w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-zinc-800">
                        <!-- Gradient Header -->
                        <div class="relative overflow-hidden px-5 py-5 bg-gradient-to-br from-teal-600 via-emerald-600 to-green-600 text-white">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                            <div class="relative flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-black tracking-tight">Request Fabric Sample</h3>
                                    <p class="text-xs text-emerald-100 mt-0.5">{{ inquiry.client?.company_name }} · sent to dyeing lab</p>
                                </div>
                                <button @click="showSampleModal = false"
                                    class="p-2 bg-white/15 ring-1 ring-white/25 hover:bg-white/25 rounded-xl transition active:scale-95">
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <form @submit.prevent="submitSampleRequest" class="p-5 space-y-4">
                            <div>
                                <label class="block text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1.5">
                                    Fabric *
                                </label>
                                <select v-model="sampleForm.product_id" required
                                    class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-400 transition">
                                    <option value="" disabled>Select fabric</option>
                                    <option v-for="p in availableProducts" :key="p.id" :value="p.id">{{ p.name }}{{ p.sku ? ` · ${p.sku}` : '' }}</option>
                                </select>
                                <p v-if="isBulkInquiry" class="text-[10px] text-gray-400 mt-1">Bulk inquiry — choose the specific fabric for this sample round.</p>
                            </div>
                            <div>
                                <label class="block text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1.5">
                                    Color Description *
                                </label>
                                <textarea v-model="sampleForm.color_description" rows="3" required
                                    placeholder="e.g. Deep royal blue, slightly reddish cast — see attached reference"
                                    class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-400 transition"></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1.5">
                                    Urgency
                                </label>
                                <select v-model="sampleForm.urgency"
                                    class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-400 transition">
                                    <option value="low">Low</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1.5">
                                    Notes for the Lab
                                </label>
                                <textarea v-model="sampleForm.notes" rows="2"
                                    placeholder="Optional guidance for the chemist"
                                    class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-400 transition"></textarea>
                            </div>
                            <button type="submit" :disabled="sampleSubmitting"
                                class="w-full py-3 bg-gradient-to-r from-teal-600 via-emerald-600 to-green-600 text-white rounded-2xl font-black text-xs uppercase tracking-wide flex justify-center items-center gap-2 hover:scale-[1.01] hover:shadow-xl disabled:opacity-50 transition-all shadow-lg active:scale-95">
                                <Loader2 v-if="sampleSubmitting" class="h-4 w-4 animate-spin" />
                                <FlaskConical v-else class="h-4 w-4" />
                                Send to Dyeing Lab
                            </button>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ════════════════════════════════════════════
             REJECT INQUIRY MODAL
             ════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="rejectModal.show"
                    class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4 bg-black/60 backdrop-blur-sm"
                    @click.self="rejectModal.show = false">
                    <div class="bg-white dark:bg-zinc-900 w-full sm:max-w-sm rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-zinc-800">
                        <!-- Gradient Header -->
                        <div class="relative overflow-hidden px-5 py-5 bg-gradient-to-br from-red-600 via-rose-600 to-pink-700 text-white">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                            <div class="relative flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-black tracking-tight">Reject Inquiry</h3>
                                    <p class="text-xs text-red-100 mt-0.5">This action cannot be undone</p>
                                </div>
                                <button @click="rejectModal.show = false"
                                    class="p-2 bg-white/15 ring-1 ring-white/25 hover:bg-white/25 rounded-xl transition active:scale-95">
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <form @submit.prevent="submitReject" class="p-5 space-y-4">
                            <textarea v-model="rejectModal.reason" rows="3" required
                                class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-3 text-sm text-gray-900 dark:text-white resize-none focus:outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-400 transition"
                                placeholder="Reason for rejection…"></textarea>
                            <div class="flex gap-3">
                                <button type="button" @click="rejectModal.show = false"
                                    class="flex-1 py-2.5 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 text-gray-600 dark:text-gray-300 rounded-2xl text-xs font-bold uppercase hover:bg-gray-50 dark:hover:bg-zinc-700 transition active:scale-95">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="flex-1 py-2.5 bg-gradient-to-r from-red-500 to-rose-600 text-white rounded-2xl text-xs font-black uppercase hover:scale-[1.02] transition-all shadow-lg active:scale-95">
                                    Confirm Reject
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ════════════════════════════════════════════
             CREATE RECIPE MODAL
             ════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="recipeModal.show"
                    class="fixed inset-0 z-[110] flex items-end sm:items-center justify-center sm:p-4 bg-black/60 backdrop-blur-sm"
                    @click.self="recipeModal.show = false">
                    <div class="bg-white dark:bg-zinc-900 w-full sm:max-w-xl rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh] border border-gray-100 dark:border-zinc-800">
                        <!-- Gradient Header -->
                        <div class="relative overflow-hidden px-5 py-5 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white flex-shrink-0">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                            <div class="relative flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-black tracking-tight">Create Recipe</h3>
                                    <p class="text-xs text-blue-100 mt-0.5">{{ inquiry.client?.company_name }}</p>
                                </div>
                                <button @click="recipeModal.show = false"
                                    class="p-2 bg-white/15 ring-1 ring-white/25 hover:bg-white/25 rounded-xl transition active:scale-95">
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <div class="p-5 overflow-y-auto flex-1">
                            <form @submit.prevent="submitRecipe" class="space-y-4">
                                <!-- Client (readonly) -->
                                <div>
                                    <label class="block text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1.5">Business Client</label>
                                    <input type="text" :value="inquiry.client?.company_name" disabled
                                        class="w-full rounded-2xl border border-gray-100 dark:border-zinc-800 bg-gray-50 dark:bg-zinc-800 p-2.5 text-sm text-gray-500 dark:text-gray-400 cursor-not-allowed" />
                                </div>
                                <!-- Product -->
                                <div>
                                    <label class="block text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1.5">Product *</label>
                                    <select v-model="recipeForm.product_id" required
                                        class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition">
                                        <option value="" disabled>Select product</option>
                                        <option v-for="p in availableProducts" :key="p.id" :value="p.id">{{ p.name }}</option>
                                    </select>
                                </div>
                                <!-- Yarn Type -->
                                <div>
                                    <label class="block text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1.5">Yarn Type *</label>
                                    <input v-model="recipeForm.yarn_type" type="text" required
                                        placeholder="e.g. 50% Cotton 50% Silk"
                                        class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition" />
                                </div>
                                <!-- Dye Color -->
                                <div>
                                    <label class="block text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1.5">Dye Color *</label>
                                    <input v-model="recipeForm.dye_color" type="text" required
                                        placeholder="e.g. 30% White 70% Blue"
                                        class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition" />
                                </div>
                                <!-- Weave Design -->
                                <div>
                                    <label class="block text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1.5">Weave Design *</label>
                                    <input v-model="recipeForm.weave_design" type="text" required
                                        placeholder="e.g. Twill 2/1"
                                        class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition" />
                                </div>
                                <!-- Raw Materials -->
                                <div>
                                    <label class="block text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1.5">Raw Materials *</label>
                                    <div class="relative">
                                        <button type="button" @click="showMaterialsDropdown = !showMaterialsDropdown"
                                            class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-2.5 text-sm text-left flex justify-between items-center text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition">
                                            <span>{{ selectedMaterialsCount }} material(s) selected</span>
                                            <ChevronDown class="h-4 w-4 text-gray-400" />
                                        </button>
                                        <div v-if="showMaterialsDropdown"
                                            class="absolute z-50 mt-1 w-full bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-2xl shadow-2xl max-h-52 overflow-y-auto">
                                            <div class="p-2">
                                                <div v-for="mat in materials" :key="mat.id"
                                                    class="flex items-center gap-2.5 py-2 px-2.5 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 rounded-xl cursor-pointer transition"
                                                    @click="toggleMaterial(mat.id)">
                                                    <input type="checkbox" :checked="recipeForm.materials.includes(mat.id)"
                                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ mat.mat_id }} – {{ mat.name }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-gray-400 mt-1">Click to select or deselect materials</p>
                                </div>

                                <!-- Error -->
                                <div v-if="recipeModal.error"
                                    class="p-3 bg-red-50 dark:bg-red-500/10 border border-red-100 dark:border-red-500/20 text-red-600 dark:text-red-400 rounded-2xl text-xs font-bold">
                                    {{ recipeModal.error }}
                                </div>

                                <!-- Submit -->
                                <button type="submit" :disabled="recipeModal.submitting"
                                    class="w-full py-3 bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 text-white rounded-2xl font-black text-xs uppercase tracking-wide flex justify-center items-center gap-2 hover:scale-[1.01] hover:shadow-xl disabled:opacity-50 transition-all shadow-lg active:scale-95">
                                    <Loader2 v-if="recipeModal.submitting" class="h-4 w-4 animate-spin" />
                                    {{ recipeModal.submitting ? 'Saving…' : 'Create Recipe' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ════════════════════════════════════════════
             CREATE JOB ORDER MODAL
             ════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="jobOrderModal.show"
                    class="fixed inset-0 z-[110] flex items-end sm:items-center justify-center sm:p-4 bg-black/60 backdrop-blur-sm"
                    @click.self="jobOrderModal.show = false">
                    <div class="bg-white dark:bg-zinc-900 w-full sm:max-w-5xl rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh] border border-gray-100 dark:border-zinc-800">
                        <!-- Gradient Header -->
                        <div class="relative overflow-hidden px-5 sm:px-6 py-5 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white flex-shrink-0">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                            <div class="relative flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-black tracking-tight">Create Job Order</h3>
                                    <p class="text-xs text-blue-100 mt-0.5">
                                        {{ totalRecipesCount }} recipe(s) available · {{ inquiry.client?.company_name }}
                                    </p>
                                    <div class="mt-2 flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-blue-200">
                                        <span class="rounded-full bg-white/20 px-2 py-0.5">1 · Review PO</span>
                                        <span class="opacity-50">→</span>
                                        <span class="rounded-full bg-white/20 px-2 py-0.5">2 · Add products</span>
                                        <span class="opacity-50">→</span>
                                        <span class="rounded-full bg-white/20 px-2 py-0.5">3 · Confirm</span>
                                    </div>
                                </div>
                                <button @click="jobOrderModal.show = false"
                                    class="p-2 bg-white/15 ring-1 ring-white/25 hover:bg-white/25 rounded-xl transition active:scale-95">
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-col lg:flex-row flex-1 overflow-hidden">
                            <!-- Left: PO Preview -->
                            <div class="w-full lg:w-1/2 border-b lg:border-b-0 lg:border-r border-gray-100 dark:border-zinc-800 p-4 overflow-auto bg-gray-50 dark:bg-zinc-800/40 max-h-60 lg:max-h-none">
                                <p class="text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-3">Purchase Order</p>
                                <div v-if="jobOrderModal.attachment">
                                    <img v-if="jobOrderModal.attachment.file_type?.startsWith('image/')"
                                        :src="getFullUrl(jobOrderModal.attachment.file_path)"
                                        class="max-w-full border border-gray-200 dark:border-zinc-700 rounded-2xl shadow-sm" />
                                    <div v-else class="p-6 bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-700 flex flex-col items-center gap-3 shadow-sm">
                                        <FileText class="h-10 w-10 text-gray-300 dark:text-zinc-600" />
                                        <a :href="getFullUrl(jobOrderModal.attachment.file_path)" target="_blank"
                                            class="text-indigo-600 dark:text-indigo-400 text-sm font-bold hover:underline">View PDF</a>
                                    </div>
                                </div>
                                <div v-else class="p-8 rounded-2xl border border-dashed border-gray-200 dark:border-zinc-700 text-center">
                                    <FileText class="h-8 w-8 text-gray-300 dark:text-zinc-600 mx-auto mb-2" />
                                    <p class="text-xs font-bold text-gray-400">No PO file attached — verify details with the client in chat first.</p>
                                </div>
                            </div>

                            <!-- Right: Form -->
                            <div class="w-full lg:w-1/2 p-4 sm:p-5 overflow-auto">
                                <form @submit.prevent="submitJobOrder" class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1.5">
                                            PO Number *
                                        </label>
                                        <input v-model="jobOrderForm.po_number" type="text" required placeholder="e.g. PO-2026-0001"
                                            class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-2.5 text-sm font-mono text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition" />
                                        <p class="text-[10px] text-gray-400 mt-1 font-medium">Must match the client's purchase order shown on the left.</p>
                                    </div>

                                    <!-- Quotation used for pricing -->
                                    <div v-if="(acceptedQuotations ?? []).length > 1">
                                        <label class="block text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1.5">
                                            Quotation for Pricing *
                                        </label>
                                        <select v-model="jobOrderQuotationId" required aria-label="Quotation" @change="onJobQuotationChange"
                                            class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition">
                                            <option v-for="q in acceptedQuotations" :key="q.id" :value="q.id">
                                                {{ q.quotation_number }} · {{ (q.items ?? []).length }} tier(s)
                                            </option>
                                        </select>
                                        <p class="text-[9px] text-gray-400 mt-1 font-medium">This conversation has {{ acceptedQuotations.length }} accepted quotations — prices below follow the chosen one.</p>
                                    </div>
                                    <div v-else-if="(acceptedQuotations ?? []).length === 1">
                                        <p class="text-[10px] font-bold text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700 rounded-2xl px-3 py-2.5">
                                            Pricing from <span class="font-mono">{{ acceptedQuotations[0].quotation_number }}</span> (accepted)
                                        </p>
                                    </div>
                                    <div v-else>
                                        <p class="text-[10px] font-bold text-amber-600 bg-amber-50 border border-amber-200 rounded-2xl px-3 py-2.5">
                                            No accepted quotation on this conversation — prices must be entered manually.
                                        </p>
                                    </div>

                                    <!-- Product items -->
                                    <div v-for="(item, idx) in jobOrderForm.items" :key="idx"
                                        class="border border-gray-100 dark:border-zinc-800 bg-white/80 dark:bg-zinc-900/80 rounded-3xl p-4 space-y-3 shadow-sm">
                                        <div class="flex justify-between items-center">
                                            <span class="text-xs font-black text-gray-700 dark:text-gray-200 uppercase tracking-widest">
                                                Product {{ idx + 1 }}
                                            </span>
                                            <button v-if="jobOrderForm.items.length > 1" type="button"
                                                @click="removeJobOrderItem(idx)"
                                                class="text-xs text-red-400 hover:text-red-600 font-bold transition">
                                                Remove
                                            </button>
                                        </div>

                                        <div>
                                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">
                                                Product *
                                            </label>
                                            <select v-model="item.product_id" required aria-label="Product" @change="onJobProductChange(item)"
                                                class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition">
                                                <option value="">Select Product</option>
                                                <option v-for="p in availableProducts" :key="p.id" :value="p.id">{{ p.name }}</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">
                                                Color *
                                            </label>
                                            <select v-model="item.color" required aria-label="Color" @change="onJobColorChange(item)"
                                                class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition">
                                                <option value="" disabled>Select color tier</option>
                                                <option v-for="c in JOB_COLORS" :key="c" :value="c">{{ c }}</option>
                                            </select>
                                            <p class="text-[9px] text-gray-400 mt-1 font-medium">Priced from the client-approved quotation.</p>
                                        </div>

                                        <div>
                                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">
                                                Recipe <span class="normal-case font-normal">(optional)</span>
                                            </label>
                                            <select v-model="item.recipe_id"
                                                class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition">
                                                <option :value="null">
                                                    {{ !item.product_id
                                                        ? '— Select a product first —'
                                                        : recipesForProduct(item.product_id).length === 0
                                                            ? '— No recipes found —'
                                                            : '— Select Recipe (optional) —'
                                                    }}
                                                </option>
                                                <option v-for="r in recipesForProduct(item.product_id)" :key="r.id" :value="r.id">
                                                    <template v-if="r.is_lab_sample">🧬 {{ r.sample_code }} · </template>{{ r.yarn_type }} — {{ r.dye_color }}
                                                    <template v-if="r.weave_design"> ({{ r.weave_design }})</template>
                                                </option>
                                            </select>
                                            <p v-if="item.product_id && recipesForProduct(item.product_id).some(r => r.is_lab_sample)"
                                                class="text-[9px] text-teal-600 dark:text-teal-400 font-bold mt-1 flex items-center gap-1">
                                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />
                                                Lab recipe from this conversation auto-selected — change if needed
                                            </p>
                                            <p v-if="item.product_id && recipesForProduct(item.product_id).length > 0"
                                                class="text-[9px] text-emerald-600 dark:text-emerald-400 font-bold mt-1 flex items-center gap-1">
                                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />
                                                {{ recipesForProduct(item.product_id).length }} recipe(s) available
                                            </p>
                                            <p v-else-if="item.product_id && recipesForProduct(item.product_id).length === 0"
                                                class="text-[9px] text-amber-500 font-bold mt-1">
                                                No recipe yet — create one via "Create Recipe" first.
                                            </p>
                                        </div>

                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">
                                                    Quantity (kg) *
                                                </label>
                                                <input v-model.number="item.kilos" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00" required
                                                    class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition" />
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">
                                                    Price per kg (₱) *
                                                </label>
                                                <input v-model.number="item.unit_price" type="number" min="0" step="0.01" inputmode="decimal"
                                                    placeholder="0.00" required :readonly="item.auto_price"
                                                    :class="item.auto_price ? 'bg-indigo-50/60 dark:bg-indigo-500/10 cursor-not-allowed' : ''"
                                                    class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition" />
                                                <p v-if="item.auto_price" class="text-[9px] text-emerald-600 dark:text-emerald-400 font-bold mt-1 flex items-center gap-1">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />
                                                    Auto-priced from {{ selectedJobQuotation?.quotation_number ?? 'accepted quotation' }} ({{ item.color }})
                                                </p>
                                                <p v-else-if="!selectedJobQuotation" class="text-[9px] text-amber-500 font-bold mt-1">
                                                    No accepted quotation on this conversation — enter the price manually.
                                                </p>
                                                <p v-else-if="item.product_id && item.color" class="text-[9px] text-amber-500 font-bold mt-1">
                                                    No approved price for this product + color — enter manually or check the quotation tiers.
                                                </p>
                                            </div>
                                        </div>

                                        <div class="flex justify-end">
                                            <span class="text-xs font-black text-indigo-600 dark:text-indigo-400">
                                                Total: ₱{{ ((item.kilos || 0) * (item.unit_price || 0)).toFixed(2) }}
                                            </span>
                                        </div>

                                        <div>
                                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">
                                                Description <span class="normal-case font-normal">(optional)</span>
                                            </label>
                                            <textarea v-model="item.description" placeholder="e.g. Special finishing, packaging notes…" rows="2"
                                                class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-2.5 text-sm text-gray-900 dark:text-white resize-none focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition"></textarea>
                                        </div>
                                    </div>

                                    <button type="button" @click="addJobOrderItem"
                                        class="w-full py-2.5 border-2 border-dashed border-indigo-200 dark:border-indigo-800 text-indigo-600 dark:text-indigo-400 rounded-2xl text-xs font-black uppercase tracking-wide hover:bg-indigo-50 dark:hover:bg-indigo-500/10 transition flex items-center justify-center gap-2">
                                        + Add Product
                                    </button>

                                    <!-- Live order total -->
                                    <div class="flex items-center justify-between rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-100 dark:border-indigo-500/20 px-4 py-3">
                                        <span class="text-[10px] font-black text-indigo-500 uppercase tracking-widest">Order total · {{ jobOrderForm.items.length }} item(s)</span>
                                        <span class="text-base font-black text-indigo-700 dark:text-indigo-300">₱{{ jobOrderGrandTotal }}</span>
                                    </div>

                                    <!-- Error -->
                                    <div v-if="jobOrderModal.error"
                                        class="p-3 bg-red-50 dark:bg-red-500/10 border border-red-100 dark:border-red-500/20 text-red-600 dark:text-red-400 rounded-2xl text-xs font-bold">
                                        {{ jobOrderModal.error }}
                                    </div>

                                    <!-- Submit -->
                                    <button type="submit" :disabled="jobOrderModal.submitting"
                                        class="w-full py-3 bg-gradient-to-r from-amber-500 via-orange-500 to-rose-500 text-white rounded-2xl font-black text-xs uppercase tracking-wide flex justify-center items-center gap-2 hover:scale-[1.01] disabled:opacity-50 transition-all shadow-lg active:scale-95">
                                        <Loader2 v-if="jobOrderModal.submitting" class="h-4 w-4 animate-spin" />
                                        {{ jobOrderModal.submitting ? 'Creating…' : 'Create Job Order' }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, nextTick, onMounted, watch } from 'vue';
import { usePageAccess } from '@/composables/usePageAccess';
import { useRealtimeConversation } from '@/composables/useRealtimeConversation';
import {
    ArrowLeft, Send, Loader2, FileText, X, Package, Trash2,
    Plus, Paperclip, ClipboardList, XCircle, Calendar, ChevronDown, FlaskConical
} from 'lucide-vue-next';

const { canEdit } = usePageAccess();
const canEditInquiry = computed(() => canEdit('CRM', 'inquiry'));

const props = defineProps({
    inquiry:        { type: Object,  required: true },
    quotations:     { type: Array,   default: () => [] },
    approvedQuotation: { type: Object, default: null },
    acceptedQuotations: { type: Array, default: () => [] },
    parsedProducts: { type: Array,   default: () => [] },
    allProducts:    { type: Array,   default: () => [] },
    recipes:        { type: Array,   default: () => [] },
    sampleRecipes:  { type: Array,   default: () => [] },
    materials:      { type: Array,   default: () => [] },
});

// Mobile panel toggle
const mobilePanel = ref('chat');

// Image preview modal
const imagePreviewModal = ref({ show: false, file: null });
const openImagePreview = (file) => {
    imagePreviewModal.value = { show: true, file };
};

// Materials dropdown state
const showMaterialsDropdown = ref(false);
const selectedMaterialsCount = computed(() => recipeForm.value.materials.length);

const toggleMaterial = (id) => {
    const index = recipeForm.value.materials.indexOf(id);
    if (index > -1) {
        recipeForm.value.materials.splice(index, 1);
    } else {
        recipeForm.value.materials.push(id);
    }
};

// Close dropdown when clicking outside
watch(showMaterialsDropdown, (val) => {
    if (val) {
        setTimeout(() => {
            const handler = (e) => {
                if (!e.target.closest('.relative')) {
                    showMaterialsDropdown.value = false;
                    document.removeEventListener('click', handler);
                }
            };
            document.addEventListener('click', handler);
        }, 100);
    }
});

const isBulkInquiry = computed(() =>
    props.inquiry.product?.name === 'Bulk Inquiry' || !props.inquiry.product
);

// Use allProducts for dropdown (they have valid IDs)
const availableProducts = computed(() => props.allProducts);

// Every distinct recipe across client history + this conversation's lab work.
const totalRecipesCount = computed(() => {
    const ids = new Set([
        ...(props.recipes ?? []).map(r => Number(r.id)),
        ...((props.sampleRecipes ?? []).map(s => s.recipe && Number(s.recipe.id)).filter(Boolean)),
    ]);
    return ids.size;
});

const formatStatus = (s) => (s ? s.replace(/_/g, ' ').toUpperCase() : 'OPEN');
const formatTime   = (d) => new Date(d).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
const formatPeso   = (v) => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const objectUrl    = (f) => URL.createObjectURL(f);

const getFullUrl = (path) => {
    if (!path) return '#';
    if (path.startsWith('http')) return path;
    const cleanPath = path.replace(/^\/?storage\//, '');
    return `/storage/${cleanPath}`;
};

const statusBadge = (s) => {
    const map = {
        open:           'bg-blue-100 text-blue-700',
        quotation_sent: 'bg-blue-100 text-blue-700',
        converted:      'bg-emerald-100 text-emerald-700',
        rejected:       'bg-red-100 text-red-700',
    };
    return (map[s] || 'bg-gray-100 text-gray-500') + ' px-3 py-1 rounded-full text-[9px] font-bold uppercase';
};

const colorDotStyle = (color) => {
    if (color === 'White')        return 'background-color:#f5f5f5; border-color:#d1d5db';
    if (color === 'Light Colors') return 'background-color:#fde68a; border-color:#fbbf24';
    if (color === 'Dark Colors')  return 'background-color:#374151; border-color:#1f2937';
    return 'background-color:#9ca3af; border-color:#6b7280';
};

// Quotation tabs: active vs trash (rejected + trashed)
const quoteTab = ref('active');
const activeQuotations = computed(() =>
    (props.quotations ?? []).filter(q => !['rejected', 'trashed'].includes(q.status))
);
const trashedQuotations = computed(() =>
    (props.quotations ?? []).filter(q => ['rejected', 'trashed'].includes(q.status))
);
const visibleQuotations = computed(() =>
    quoteTab.value === 'trash' ? trashedQuotations.value : activeQuotations.value
);

const groupItemsByFabric = (items) =>
    (items || []).reduce((acc, item) => {
        (acc[item.fabric] = acc[item.fabric] || []).push(item);
        return acc;
    }, {});

const makeItem = (name = '', isDefault = false) => ({
    fabric:      name,
    design:      '',
    kilos:       0,
    price_white: 0,
    price_light: 0,
    price_dark:  0,
    isDefault,
});

const form = ref({
    vat_type:      'exclusive',
    payment_terms: '',
    notes:         '',
    items: props.parsedProducts?.length
        ? props.parsedProducts.map(p => makeItem(p.name, true))
        : [makeItem('', true)],
});

const submitting = ref(false);
const showQuotationModal = ref(false);

const openQuotationModal = () => {
    form.value.items = props.parsedProducts?.length
        ? props.parsedProducts.map(p => makeItem(p.name, true))
        : [makeItem('', true)];
    form.value.payment_terms = '';
    form.value.notes = '';
    form.value.vat_type = 'exclusive';
    showQuotationModal.value = true;
};

const addItem = () => {
    form.value.items.push(makeItem('', false));
};

const removeItem = (idx) => {
    if (!form.value.items[idx].isDefault) {
        form.value.items.splice(idx, 1);
    }
};

const submitQuotation = () => {
    submitting.value = true;
    router.post(route('crm.inquiry.quotation', props.inquiry.hash_key), form.value, {
        onSuccess: () => {
            showQuotationModal.value = false;
        },
        onFinish: () => { submitting.value = false; },
    });
};

const messagesContainer = ref(null);
const newMessage        = ref('');
const selectedFiles     = ref([]);

// ── Real-time conversation (no reload) ──────────────────────────
// Local copy so polling can append without mutating the Inertia prop.
const liveMessages = ref([...(props.inquiry?.messages ?? [])]);

const scrollToBottom = () =>
    nextTick(() => {
        if (messagesContainer.value)
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    });

const mergeMessages = (incoming) => {
    const known = new Set(liveMessages.value.map((m) => Number(m.id)));
    let added = false;
    for (const m of incoming) {
        if (!known.has(Number(m.id))) {
            liveMessages.value.push(m);
            known.add(Number(m.id));
            added = true;
        }
    }
    if (added) {
        liveMessages.value.sort((a, b) => Number(a.id) - Number(b.id));
        scrollToBottom();
    }
};

// Keep in sync if Inertia reloads (e.g. after quotation / meeting / recipe).
watch(() => props.inquiry?.messages, (msgs) => {
    if (Array.isArray(msgs)) mergeMessages(msgs);
}, { deep: true });

const {
    otherTyping,
    sending: rtSending,
    notifyTyping,
    sendMessage: sendRealtime,
} = useRealtimeConversation({
    getMessages: () => liveMessages.value,
    appendMessages: mergeMessages,
    feedUrl: route('crm.inquiry.feed', props.inquiry.hash_key),
    typingUrl: route('crm.inquiry.typing', props.inquiry.hash_key),
    sendUrl: route('crm.inquiry.message', props.inquiry.hash_key),
    onNewMessages: () => scrollToBottom(),
});
const sending = rtSending;

const sendMessage = async () => {
    if (!newMessage.value.trim() && !selectedFiles.value.length) return;
    const text = newMessage.value;
    const files = [...selectedFiles.value];
    newMessage.value = '';
    selectedFiles.value = [];
    const created = await sendRealtime({ text, files });
    if (!created) {
        // Restore on failure so the user doesn't lose their draft.
        newMessage.value = text;
        selectedFiles.value = files;
    }
};

const showMeetingModal = ref(false);
const scheduling = ref(false);
const meetingData = ref({
    scheduled_at: '',
    location: '',
    type: 'video',
});

// Earliest pickable slot, formatted for datetime-local (today or later).
const meetingMinDateTime = computed(() => {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
});

const openMeetingModal = () => {
    meetingData.value = { scheduled_at: '', location: '', type: 'video' };
    showMeetingModal.value = true;
};

const submitMeeting = () => {
    scheduling.value = true;
    router.post(route('crm.inquiry.meeting', props.inquiry.hash_key), meetingData.value, {
        onSuccess: () => {
            showMeetingModal.value = false;
            scrollToBottom();
        },
        onFinish: () => { scheduling.value = false; },
    });
};

const rejectModal   = ref({ show: false, reason: '' });
const openRejectModal = () => { rejectModal.value = { show: true, reason: '' }; };
const submitReject  = () => {
    router.post(route('crm.inquiry.reject', props.inquiry.hash_key), { reason: rejectModal.value.reason }, {
        onSuccess: () => { rejectModal.value.show = false; },
    });
};

// ── Fabric sample loop (CRM → dyeing lab → forward to client) ──
const showSampleModal = ref(false);
const sampleSubmitting = ref(false);
const sampleForm = ref({ product_id: '', color_description: '', notes: '', urgency: 'normal' });

const openSampleModal = () => {
    sampleForm.value = {
        product_id: props.inquiry.product?.id ?? '',
        color_description: '',
        notes: '',
        urgency: 'normal',
    };
    showSampleModal.value = true;
};

const submitSampleRequest = () => {
    sampleSubmitting.value = true;
    router.post(route('crm.inquiry.sample-request', props.inquiry.hash_key), sampleForm.value, {
        preserveScroll: true,
        onSuccess: () => { showSampleModal.value = false; },
        onFinish: () => { sampleSubmitting.value = false; },
    });
};

const sampleStatusLabel = (s) => (s ? String(s).replace(/_/g, ' ') : '');

const recipeModal = ref({
    show: false,
    submitting: false,
    attachment: null,
    error: null,
});

const recipeForm = ref({
    client_id: props.inquiry.client_id,
    product_id: '',
    yarn_type: '',
    dye_color: '',
    weave_design: '',
    materials: [],
});

const openRecipeModal = (attachment) => {
    const firstProductId = availableProducts.value[0]?.id || '';
    recipeForm.value = {
        client_id: props.inquiry.client_id,
        product_id: firstProductId,
        yarn_type: '',
        dye_color: '',
        weave_design: '',
        materials: [],
    };
    recipeModal.value = { show: true, submitting: false, attachment, error: null };
    showMaterialsDropdown.value = false;
};

const submitRecipe = () => {
    recipeModal.value.error = null;
    recipeModal.value.submitting = true;
    router.post(route('crm.attachment.create-recipe', recipeModal.value.attachment.id), recipeForm.value, {
        onSuccess: () => {
            recipeModal.value.show = false;
        },
        onError: (errors) => {
            const messages = [];
            for (const key in errors) {
                if (Array.isArray(errors[key])) {
                    messages.push(...errors[key]);
                } else {
                    messages.push(errors[key]);
                }
            }
            recipeModal.value.error = messages.join(' ') || 'Validation failed.';
        },
        onFinish: () => { recipeModal.value.submitting = false; }
    });
};

const jobOrderModal = ref({
    show: false,
    submitting: false,
    attachment: null,
    error: null,
});

const JOB_COLORS = ['White', 'Light Colors', 'Dark Colors'];

const makeJobOrderItem = () => ({ product_id: '', color: '', recipe_id: null, kilos: 0, unit_price: 0, auto_price: false, description: '' });

const jobOrderForm = ref({
    po_number: '',
    items: [makeJobOrderItem()],
});

const openJobOrderModal = (attachment) => {
    jobOrderForm.value = {
        po_number: '',
        items: [makeJobOrderItem()],
    };
    // Default to the latest accepted quotation (first in the list).
    jobOrderQuotationId.value = props.acceptedQuotations?.[0]?.id ?? props.approvedQuotation?.id ?? null;
    jobOrderModal.value = { show: true, submitting: false, attachment, error: null };
};

const addJobOrderItem = () => {
    jobOrderForm.value.items.push(makeJobOrderItem());
};

const removeJobOrderItem = (idx) => {
    jobOrderForm.value.items.splice(idx, 1);
};

const recipesForProduct = (productId) => {
    if (!productId) return [];

    const numId = Number(productId);
    if (isNaN(numId) || numId <= 0) return [];

    const matches = (r) => {
        const byForeignKey   = Number(r.product_id) === numId;
        const byRelationship = r.product && Number(r.product.id) === numId;
        return byForeignKey || byRelationship;
    };

    // Lab recipes formulated for THIS conversation first (tagged with their
    // sample round), then older client recipes — deduped by recipe id.
    const seen = new Set();
    const merged = [];
    for (const s of (props.sampleRecipes ?? [])) {
        const r = s.recipe;
        if (r && !seen.has(Number(r.id)) && matches(r)) {
            seen.add(Number(r.id));
            merged.push({ ...r, sample_code: s.sample_code, sample_status: s.sample_status, is_lab_sample: true });
        }
    }
    for (const r of (props.recipes ?? [])) {
        if (!seen.has(Number(r.id)) && matches(r)) {
            seen.add(Number(r.id));
            merged.push({ ...r, is_lab_sample: false });
        }
    }
    return merged;
};

// Prefer the approved lab recipe for the product, else the latest lab one.
const defaultLabRecipeId = (productId) => {
    const list = recipesForProduct(productId).filter(r => r.is_lab_sample);
    if (!list.length) return null;
    return (list.find(r => r.sample_status === 'approved') ?? list[0]).id;
};

const onJobProductChange = (item) => {
    const labId = defaultLabRecipeId(item.product_id);
    item.recipe_id = labId ?? null;
    applyQuotedPrice(item);
};

// Unit price comes from the job order's chosen accepted quotation: match
// the row for this product + color tier (White / Light Colors / Dark Colors).
const jobOrderQuotationId = ref(null);
const selectedJobQuotation = computed(() =>
    (props.acceptedQuotations ?? []).find(q => Number(q.id) === Number(jobOrderQuotationId.value))
    ?? props.approvedQuotation ?? null
);

const quotedPriceFor = (productId, color) => {
    const items = selectedJobQuotation.value?.items ?? [];
    const name = (availableProducts.value.find(p => Number(p.id) === Number(productId))?.name ?? '').trim().toLowerCase();
    if (!name || !color) return null;
    const hit = items.find(i =>
        String(i.fabric ?? '').trim().toLowerCase() === name &&
        String(i.color ?? '').toLowerCase() === String(color).toLowerCase()
    );
    return hit ? Number(hit.unit_price) : null;
};

const applyQuotedPrice = (item) => {
    const price = quotedPriceFor(item.product_id, item.color);
    if (price !== null && !isNaN(price)) {
        item.unit_price = price;
        item.auto_price = true;
    } else {
        item.auto_price = false;
    }
};

const onJobColorChange = (item) => {
    applyQuotedPrice(item);
};

// Switching quotations re-prices every line from the newly chosen one.
const onJobQuotationChange = () => {
    jobOrderForm.value.items.forEach((item) => {
        if (item.product_id && item.color) applyQuotedPrice(item);
        else item.auto_price = false;
    });
};

// Live grand total across all job-order items
const jobOrderGrandTotal = computed(() =>
    jobOrderForm.value.items
        .reduce((sum, i) => sum + (Number(i.kilos) || 0) * (Number(i.unit_price) || 0), 0)
        .toFixed(2)
);

const submitJobOrder = () => {
    jobOrderModal.value.error = null;
    jobOrderModal.value.submitting = true;
    router.post(route('crm.attachment.create-job-order', jobOrderModal.value.attachment.id), jobOrderForm.value, {
        onSuccess: () => {
            jobOrderModal.value.show = false;
        },
        onError: (errors) => {
            const messages = [];
            for (const key in errors) {
                if (Array.isArray(errors[key])) {
                    messages.push(...errors[key]);
                } else {
                    messages.push(errors[key]);
                }
            }
            jobOrderModal.value.error = messages.join(' ') || 'Failed to create job order.';
        },
        onFinish: () => { jobOrderModal.value.submitting = false; }
    });
};

onMounted(() => scrollToBottom());
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
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease, transform 0.25s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: translateY(16px) scale(0.98); }
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { scrollbar-width: none; }
</style>
