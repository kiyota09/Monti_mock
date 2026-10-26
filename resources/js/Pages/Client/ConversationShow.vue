<template>
    <Head :title="`Conversation: ${inquiry.product?.name || 'Bulk Inquiry'}`" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

            <!-- ── Hero Header ─────────────────────────────────────── -->
            <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                <div class="relative flex flex-wrap items-center gap-3">
                    <Link :href="route('client.conversations')"
                        class="group flex h-10 w-10 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25 backdrop-blur hover:bg-white/30 hover:-translate-x-0.5 transition-all active:scale-95 flex-shrink-0">
                        <ArrowLeft class="h-4 w-4 group-hover:-translate-x-0.5 transition-transform" />
                    </Link>
                    <div class="flex-1 min-w-0">
                        <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                            <Sparkles class="h-3.5 w-3.5" /> Client conversation
                        </p>
                        <h1 class="text-xl sm:text-2xl font-black tracking-tight leading-tight truncate">
                            {{ inquiry.product?.name || 'Multiple Products' }}
                        </h1>
                        <p class="text-xs text-blue-100/90 font-medium uppercase tracking-wider mt-0.5 flex items-center gap-1.5">
                            {{ inquiry.product?.sku ? `SKU: ${inquiry.product.sku} · ` : '' }}
                            <span class="inline-flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ formatStatus(inquiry.status) }}</span>
                        </p>
                    </div>
                    <button
                        @click="openPoModal"
                        :disabled="!hasAcceptedQuotation"
                        :class="hasAcceptedQuotation
                            ? 'bg-amber-400 hover:bg-amber-300 text-amber-950 cursor-pointer shadow-lg hover:scale-105'
                            : 'bg-white/15 text-white/50 cursor-not-allowed ring-1 ring-white/20'"
                        class="flex items-center gap-1.5 px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-wider transition-all active:scale-95 flex-shrink-0 backdrop-blur">
                        <FileText class="h-3.5 w-3.5" />
                        <span class="hidden sm:inline">Send P.O.</span>
                        <span class="sm:hidden">P.O.</span>
                    </button>
                </div>
            </div>

            <!-- ── Mobile Tab Bar ───────────────────────────────────── -->
            <div class="flex lg:hidden gap-2">
                <button @click="mobilePanel = 'chat'"
                    :class="mobilePanel === 'chat' ? 'bg-white dark:bg-zinc-900 text-indigo-700 dark:text-indigo-300 shadow-lg scale-[1.02]' : 'bg-white/60 dark:bg-zinc-900/60 text-gray-400 hover:text-gray-600'"
                    class="flex-1 py-2.5 rounded-2xl border border-gray-100 dark:border-zinc-800 text-[11px] font-black uppercase tracking-wider transition-all active:scale-95">
                    Chat
                </button>
                <button @click="mobilePanel = 'proposals'"
                    :class="mobilePanel === 'proposals' ? 'bg-white dark:bg-zinc-900 text-indigo-700 dark:text-indigo-300 shadow-lg scale-[1.02]' : 'bg-white/60 dark:bg-zinc-900/60 text-gray-400 hover:text-gray-600'"
                    class="flex-1 py-2.5 rounded-2xl border border-gray-100 dark:border-zinc-800 text-[11px] font-black uppercase tracking-wider transition-all active:scale-95">
                    Proposals
                </button>
            </div>

            <!-- ── Main Layout ──────────────────────────────────────── -->
            <div class="animate-fade-up flex flex-col lg:flex-row gap-4 lg:h-[calc(100vh-340px)] lg:min-h-[560px]" style="animation-delay:120ms">

                <!-- Left: Messages ──────────────────────────────────── -->
                <div :class="mobilePanel !== 'chat' ? 'hidden lg:flex' : 'flex'"
                    class="flex-1 flex-col overflow-hidden rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white/80 dark:bg-zinc-900/80 backdrop-blur shadow-sm hover:shadow-xl transition-shadow duration-300 min-w-0 min-h-[540px] lg:min-h-0">

                    <!-- Messages area -->
                    <div ref="messagesContainer"
                        class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-3 bg-gradient-to-b from-indigo-50/40 via-transparent to-transparent dark:from-indigo-950/20">
                        <div v-for="msg in liveMessages" :key="msg.id"
                            class="animate-fade-up flex flex-col"
                            :class="msg.sender_type === 'client' ? 'items-end' : 'items-start'">

                            <!-- System event pill -->
                            <div v-if="msg.is_system_event" class="w-full flex justify-center my-3">
                                <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 px-4 py-1.5 rounded-full max-w-[85%] shadow-sm">
                                    <p class="text-[9px] font-black uppercase tracking-wide text-indigo-500 dark:text-indigo-300 text-center flex items-center gap-1.5 justify-center">
                                        <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ msg.message }}
                                    </p>
                                </div>
                            </div>

                            <!-- Regular message bubble -->
                            <div v-else
                                :class="msg.sender_type === 'client'
                                    ? 'bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-600 text-white rounded-3xl rounded-tr-xl shadow-lg shadow-indigo-500/25'
                                    : 'bg-white dark:bg-zinc-800 text-gray-800 dark:text-gray-100 border border-gray-100 dark:border-zinc-700 rounded-3xl rounded-tl-xl shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all'"
                                class="max-w-[80%] sm:max-w-[65%] px-4 py-3">

                                <p class="text-xs leading-relaxed whitespace-pre-wrap">{{ msg.message }}</p>

                                <!-- Lab sample under review: photo + decision (formula stays internal) -->
                                <div v-if="msg.sample_request" class="mt-2 rounded-xl bg-black/10 dark:bg-white/10 p-2.5">
                                    <p class="text-[10px] font-black uppercase tracking-widest opacity-70">
                                        🧬 Lab sample · {{ msg.sample_request.fabric_name }}
                                    </p>
                                    <p class="text-[9px] font-bold opacity-50 mt-0.5">{{ msg.sample_request.code }}</p>
                                </div>

                                <!-- Attachments -->
                                <div v-if="msg.attachments && msg.attachments.length" class="mt-2 space-y-2">
                                    <div v-for="file in msg.attachments" :key="file.id">
                                        <!-- Image -->
                                        <div v-if="file.file_type?.startsWith('image/')"
                                            class="rounded-xl overflow-hidden border border-black/10 relative">
                                            <img :src="getFullUrl(file.file_path)"
                                                class="max-w-full h-auto cursor-pointer"
                                                @click="openPreview(file)" />
                                            <div class="absolute bottom-2 right-2">
                                                <button v-if="!file.approved_by_client && !msg.sample_request"
                                                    @click.stop="approveAttachment(file)"
                                                    class="px-2.5 py-1 bg-amber-400 text-amber-900 rounded-lg text-[9px] font-bold uppercase shadow-sm hover:bg-amber-500 transition">
                                                    Approve
                                                </button>
                                                <span v-else-if="file.approved_by_client"
                                                    class="px-2.5 py-1 bg-emerald-100 text-emerald-700 rounded-lg text-[9px] font-bold uppercase">
                                                    Approved ✓
                                                </span>
                                            </div>
                                        </div>
                                        <!-- Non-image -->
                                        <div v-else
                                            class="flex items-center gap-2 px-3 py-2 bg-black/10 rounded-xl text-[10px] font-medium">
                                            <FileText class="h-3.5 w-3.5 flex-shrink-0" />
                                            <a :href="getFullUrl(file.file_path)" target="_blank"
                                                class="truncate hover:underline flex-1">{{ file.file_name }}</a>
                                            <button v-if="!file.approved_by_client && !msg.sample_request"
                                                @click.stop="approveAttachment(file)"
                                                class="ml-1 px-2 py-1 bg-amber-400 text-amber-900 rounded-lg text-[8px] font-bold uppercase hover:bg-amber-500 transition flex-shrink-0">
                                                Approve
                                            </button>
                                            <span v-else-if="file.approved_by_client"
                                                class="ml-1 px-2 py-1 bg-emerald-100 text-emerald-700 rounded-lg text-[8px] font-bold uppercase flex-shrink-0">
                                                Approved ✓
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <p class="text-[9px] opacity-50 mt-1.5 text-right">{{ formatTime(msg.created_at) }}</p>

                                <!-- Fabric sample decision (forwarded lab result) -->
                                <div v-if="msg.sample_request" class="mt-2">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-widest bg-black/10 dark:bg-white/10">
                                            {{ msg.sample_request.code }} · {{ sampleStatusLabel(msg.sample_request.status) }}
                                        </span>
                                    </div>
                                    <div v-if="msg.sample_request.status === 'forwarded'" class="mt-1.5 flex flex-wrap gap-1.5">
                                        <button @click="approveSample(msg.sample_request.id)"
                                            class="px-3 py-1.5 bg-emerald-500 text-white rounded-lg text-[9px] font-black uppercase shadow-sm hover:bg-emerald-600 hover:scale-105 active:scale-95 transition">
                                            Approve Sample
                                        </button>
                                        <button @click="openAdjustModal(msg.sample_request)"
                                            class="px-3 py-1.5 bg-amber-400 text-amber-900 rounded-lg text-[9px] font-black uppercase shadow-sm hover:bg-amber-500 hover:scale-105 active:scale-95 transition">
                                            Request Adjustment
                                        </button>
                                    </div>
                                    <p v-else-if="msg.sample_request.status === 'approved'" class="mt-1.5 text-[9px] font-black uppercase text-emerald-600 dark:text-emerald-400">
                                        ✓ Sample approved — you may now send your P.O.
                                    </p>
                                    <p v-else-if="msg.sample_request.status === 'adjustment_requested'" class="mt-1.5 text-[9px] font-black uppercase text-amber-600 dark:text-amber-400">
                                        Adjustment requested — CRM is preparing a new round.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <!-- Typing indicator (real-time, no reload) -->
                        <div v-if="otherTyping" class="animate-fade-up flex flex-col items-start mt-1">
                            <div class="bg-white dark:bg-zinc-800 text-gray-800 dark:text-gray-100 border border-gray-100 dark:border-zinc-700 rounded-3xl rounded-tl-xl shadow-sm px-4 py-3 flex items-center gap-2">
                                <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">ECO Team is typing</span>
                                <span class="flex gap-1">
                                    <span class="h-1.5 w-1.5 rounded-full bg-indigo-500 animate-bounce" style="animation-delay:0ms" />
                                    <span class="h-1.5 w-1.5 rounded-full bg-indigo-500 animate-bounce" style="animation-delay:150ms" />
                                    <span class="h-1.5 w-1.5 rounded-full bg-indigo-500 animate-bounce" style="animation-delay:300ms" />
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Reply box -->
                    <div class="flex-shrink-0 border-t border-gray-100 dark:border-zinc-800 p-3 sm:p-4 bg-white/70 dark:bg-zinc-900/70 backdrop-blur rounded-b-3xl">
                        <!-- File previews -->
                        <div v-if="selectedFiles.length" class="flex gap-2 mb-3 overflow-x-auto pb-1">
                            <div v-for="(file, i) in selectedFiles" :key="i"
                                class="relative h-12 w-12 flex-shrink-0 bg-gray-100 rounded-xl border border-gray-200 overflow-hidden">
                                <img v-if="file.type.startsWith('image/')" :src="getFilePreview(file)"
                                    class="h-full w-full object-cover" />
                                <div v-else class="h-full w-full flex items-center justify-center">
                                    <FileText class="h-5 w-5 text-gray-400" />
                                </div>
                                <button @click="confirmRemoveFile(i)"
                                    class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full p-0.5 shadow">
                                    <X class="h-2.5 w-2.5" />
                                </button>
                            </div>
                        </div>
                        <!-- Input row -->
                        <div class="flex items-center gap-2 bg-gray-50 dark:bg-zinc-800 rounded-2xl px-3 py-2 border border-transparent focus-within:border-indigo-400 focus-within:ring-2 focus-within:ring-indigo-200 dark:focus-within:ring-indigo-900 transition">
                            <input v-model="newMessage" type="text"
                                placeholder="Type a message…"
                                class="flex-1 bg-transparent border-none focus:ring-0 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400"
                                @input="notifyTyping"
                                @keydown.enter.prevent="handleSend" />
                            <button type="button" @click="triggerFileUpload"
                                class="p-1.5 hover:bg-indigo-100 dark:hover:bg-indigo-900/40 rounded-xl transition text-gray-400 hover:text-indigo-600">
                                <Paperclip class="h-4 w-4" />
                            </button>
                            <input ref="fileInput" type="file" class="hidden" multiple @change="onFilesSelected" />
                            <button type="button" @click="handleSend"
                                :disabled="sending || (!newMessage.trim() && !selectedFiles.length)"
                                class="p-2 bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-xl shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:scale-105 active:scale-95 disabled:opacity-40 disabled:hover:scale-100 transition-all">
                                <Send v-if="!sending" class="h-4 w-4" />
                                <Loader2 v-else class="h-4 w-4 animate-spin" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right: Proposals ────────────────────────────────── -->
                <div :class="mobilePanel !== 'proposals' ? 'hidden lg:block' : 'block'"
                    class="w-full lg:w-80 xl:w-96 flex-shrink-0 overflow-y-auto rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white/80 dark:bg-zinc-900/80 backdrop-blur shadow-sm p-4 space-y-3 min-h-[300px]">

                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 px-1 pt-1 flex items-center gap-1.5">
                        <Sparkles class="h-3.5 w-3.5 text-indigo-400" /> Proposals
                        <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-0.5">{{ quotations.length }}</span>
                    </p>

                    <!-- Active / Trash tabs -->
                    <div class="flex gap-1.5 px-1">
                        <button @click="quotationTab = 'active'"
                            :class="quotationTab === 'active' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/25' : 'bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-gray-400 hover:text-indigo-600'"
                            class="flex-1 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition active:scale-95">
                            Active ({{ activeQuotations.length }})
                        </button>
                        <button @click="quotationTab = 'trash'"
                            :class="quotationTab === 'trash' ? 'bg-slate-600 text-white shadow-md' : 'bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-gray-400 hover:text-slate-600'"
                            class="flex-1 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition active:scale-95">
                            Trash ({{ trashedQuotations.length }})
                        </button>
                    </div>

                    <div v-for="(quotation, qi) in visibleQuotations" :key="quotation.id"
                        :style="{ animationDelay: `${Math.min(qi * 70, 350)}ms` }"
                        class="group animate-fade-up relative bg-white dark:bg-zinc-900 rounded-3xl border border-gray-100 dark:border-zinc-800 overflow-hidden shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300">
                        <div class="pointer-events-none absolute -top-12 -right-12 h-32 w-32 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />

                        <!-- Status bar -->
                        <div class="relative flex items-center justify-between px-4 py-2.5"
                            :class="quotation.status === 'accepted' ? 'bg-gradient-to-r from-emerald-500 to-teal-600'
                                : quotation.status === 'rejected' ? 'bg-gradient-to-r from-rose-500 to-red-600'
                                : quotation.status === 'trashed' ? 'bg-gradient-to-r from-slate-500 to-gray-600'
                                : 'bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-700'">
                            <span class="font-mono text-[10px] font-bold text-white">
                                {{ quotation.quotation_number }}
                            </span>
                            <span class="text-[9px] font-black uppercase text-white/90 bg-black/15 px-2 py-0.5 rounded-full ring-1 ring-white/25 flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ quotation.status }}
                            </span>
                        </div>

                        <div class="p-3 space-y-2.5">
                            <!-- VAT badge -->
                            <div class="relative flex items-center gap-2 flex-wrap">
                                <span class="text-[9px] font-black rounded-full px-2 py-0.5 ring-1"
                                    :class="quotation.vat_type === 'inclusive'
                                        ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-800'
                                        : 'bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-gray-400'">
                                    {{ quotation.vat_type === 'inclusive' ? 'VAT Incl. +12%' : 'VAT Excl.' }}
                                </span>
                            </div>

                            <!-- Fabric groups -->
                            <div v-for="(group, fabric) in groupItemsByFabric(quotation.items)" :key="fabric"
                                class="relative border border-gray-100 dark:border-zinc-800 rounded-2xl overflow-hidden">
                                <div class="bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 px-3 py-2 flex items-center gap-1.5">
                                    <Package class="h-3 w-3 text-indigo-500 flex-shrink-0" />
                                    <span class="text-[10px] font-black text-indigo-700 dark:text-indigo-300 uppercase truncate flex-1">
                                        {{ fabric }}
                                    </span>
                                    <span class="text-[9px] text-gray-400 font-medium">{{ group[0]?.kilos }}kg</span>
                                </div>
                                <div class="divide-y divide-gray-50">
                                    <div v-for="item in group" :key="item.id"
                                        class="px-3 py-2 flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2.5 h-2.5 rounded-full flex-shrink-0 border"
                                                :style="colorDotStyle(item.color)"></div>
                                            <span class="text-[9px] font-semibold text-gray-600 uppercase">
                                                {{ item.color }}
                                            </span>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-[10px] font-bold text-blue-600">
                                                ₱{{ formatPrice(item.unit_price) }}/kg
                                                <span v-if="quotation.vat_type === 'inclusive'"
                                                    class="text-[8px] text-gray-400 ml-1">inc. VAT</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sent: View document first, then Accept / Reject -->
                            <div v-if="quotation.status === 'sent'" class="relative pt-1 space-y-2">
                                <button @click="openQuotationPreview(quotation)"
                                    class="w-full py-2 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-800 rounded-xl text-[9px] font-black uppercase flex items-center justify-center gap-2 hover:bg-indigo-100 dark:hover:bg-indigo-900/40 hover:scale-[1.01] active:scale-95 transition-all">
                                    <Eye class="h-3 w-3" /> View Quotation
                                </button>
                                <div class="flex gap-2">
                                    <button @click="openRejectModal(quotation)"
                                        class="flex-1 py-2 border border-red-200 dark:border-red-900 text-red-500 rounded-xl text-[9px] font-black uppercase hover:bg-red-50 dark:hover:bg-red-900/20 active:scale-95 transition">
                                        Reject
                                    </button>
                                    <button @click="openAcceptModal(quotation)"
                                        class="flex-1 py-2 bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-xl text-[9px] font-black uppercase shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:scale-[1.02] active:scale-95 transition-all">
                                        Accept
                                    </button>
                                </div>
                            </div>

                            <!-- Accepted: Preview / Download + Trash -->
                            <div v-if="quotation.status === 'accepted'" class="relative pt-1 space-y-2">
                                <button @click="openQuotationPreview(quotation)"
                                    class="w-full py-2 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-800 rounded-xl text-[9px] font-black uppercase flex items-center justify-center gap-2 hover:bg-indigo-100 dark:hover:bg-indigo-900/40 hover:scale-[1.01] active:scale-95 transition-all">
                                    <Download class="h-3 w-3" /> Preview & Download
                                </button>
                                <button @click="openTrashModal(quotation)"
                                    class="w-full py-2 bg-gray-50 dark:bg-zinc-800 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-zinc-700 rounded-xl text-[9px] font-black uppercase flex items-center justify-center gap-2 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-500 hover:border-red-200 active:scale-95 transition-all">
                                    <Trash2 class="h-3 w-3" /> Trash & Request New
                                </button>
                            </div>
                            <!-- Trashed: terminal, waiting on a fresh quotation -->
                            <div v-if="quotation.status === 'trashed'" class="relative pt-1">
                                <p class="text-center text-[9px] font-bold uppercase text-gray-400">
                                    Trashed — CRM will issue a new quotation.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════════
             SEND P.O. MODAL
             ════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
            <div v-if="poModal.show"
                class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center sm:p-4 bg-black/60 backdrop-blur-sm"
                @click.self="poModal.show = false">
                <div class="bg-white dark:bg-zinc-900 w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden">
                    <!-- Header -->
                    <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 px-5 py-4 flex items-center justify-between relative overflow-hidden">
                        <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl animate-float" />
                        <div class="relative">
                            <h3 class="text-sm font-black text-white tracking-tight">Send Purchase Order</h3>
                            <p class="text-xs text-blue-100/90 mt-0.5">Attach your PO files below</p>
                        </div>
                        <button @click="poModal.show = false"
                            class="relative flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 text-white hover:bg-white/35 hover:rotate-90 transition-all">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <!-- Body -->
                    <form @submit.prevent="submitPo" class="p-5 space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1.5">
                                PO Files (PDF / Images) *
                            </label>
                            <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-dashed border-gray-200 rounded-xl cursor-pointer hover:border-blue-400 hover:bg-blue-50/30 transition">
                                <Paperclip class="h-5 w-5 text-gray-400 mb-1" />
                                <span class="text-xs text-gray-400">Click to upload files</span>
                                <span class="text-[10px] text-gray-300 mt-0.5">PDF, JPG, PNG · Max 10MB each</span>
                                <input type="file" ref="poFileInput" @change="onPoFilesSelected" accept=".pdf,image/*" multiple class="hidden" />
                            </label>
                        </div>
                        <!-- File list -->
                        <div v-if="poModal.files.length > 0" class="flex gap-2 flex-wrap">
                            <div v-for="(file, idx) in poModal.files" :key="idx"
                                class="flex items-center gap-1.5 bg-gray-100 rounded-lg px-2.5 py-1.5 text-xs">
                                <FileText class="h-3 w-3 text-gray-500" />
                                <span class="truncate max-w-[120px] text-gray-700">{{ file.name }}</span>
                                <button type="button" @click="removePoFile(idx)"
                                    class="text-gray-400 hover:text-red-500 transition ml-0.5">
                                    <X class="h-3 w-3" />
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1.5">Notes (Optional)</label>
                            <textarea v-model="poModal.notes" rows="2"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition resize-none"
                                placeholder="Add any remarks…"></textarea>
                        </div>
                        <button type="submit"
                            :disabled="poModal.submitting || poModal.files.length === 0"
                            class="w-full py-3 bg-gradient-to-r from-amber-400 to-orange-500 text-amber-950 rounded-xl font-black text-xs uppercase tracking-wide flex justify-center items-center gap-2 hover:shadow-xl hover:scale-[1.01] active:scale-95 disabled:opacity-40 transition-all shadow-lg">
                            <Loader2 v-if="poModal.submitting" class="h-4 w-4 animate-spin" />
                            <Send v-else class="h-4 w-4" />
                            Send Purchase Order
                        </button>
                    </form>
                </div>
            </div>
            </Transition>
        </Teleport>

        <!-- ════════════════════════════════════════════
             DIALOG MODAL (info / confirm / error)
             ════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
            <div v-if="dialog.show"
                class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                <div class="bg-white dark:bg-zinc-900 w-full max-w-xs rounded-3xl shadow-2xl overflow-hidden">
                    <div class="p-6 text-center">
                        <div class="h-14 w-14 rounded-2xl flex items-center justify-center mx-auto mb-4"
                            :class="dialog.type === 'error' ? 'bg-red-100'
                                : dialog.type === 'confirm' ? 'bg-blue-100'
                                : 'bg-emerald-100'">
                            <X v-if="dialog.type === 'error'" class="h-6 w-6 text-red-500" />
                            <Send v-else-if="dialog.type === 'confirm'" class="h-6 w-6 text-blue-600" />
                            <Loader2 v-else class="h-6 w-6 text-emerald-600 animate-spin" />
                        </div>
                        <h3 class="text-sm font-bold text-gray-900 mb-1">{{ dialog.title }}</h3>
                        <p class="text-xs text-gray-500 leading-relaxed">{{ dialog.message }}</p>
                    </div>
                    <div class="flex border-t border-gray-100 dark:border-zinc-800">
                        <button v-if="dialog.type === 'confirm'"
                            @click="dialog.show = false"
                            class="flex-1 py-3.5 text-xs font-semibold text-gray-400 hover:bg-gray-50 dark:hover:bg-zinc-800 transition border-r border-gray-100 dark:border-zinc-800">
                            Cancel
                        </button>
                        <button @click="handleDialogAction"
                            class="flex-1 py-3.5 text-xs font-bold uppercase"
                            :class="dialog.type === 'error' ? 'text-red-500 hover:bg-red-50' : 'text-indigo-600 hover:bg-indigo-50'">
                            {{ dialog.type === 'confirm' ? 'Confirm' : 'Got it' }}
                        </button>
                    </div>
                </div>
            </div>
            </Transition>
        </Teleport>

        <!-- ════════════════════════════════════════════
             FILE PREVIEW MODAL
             ════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
            <div v-if="previewModal.show"
                class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/95 backdrop-blur-md"
                @click.self="previewModal.show = false">
                <button @click="previewModal.show = false"
                    class="absolute top-5 right-5 p-2 bg-white/10 hover:bg-white/20 hover:rotate-90 rounded-2xl transition-all z-[80]">
                    <X class="h-5 w-5 text-white" />
                </button>
                <div class="w-full max-w-5xl h-[88vh] flex flex-col gap-3">
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-white/60 px-1">
                        {{ previewModal.title }}
                    </p>
                    <div class="flex-1 bg-black/40 rounded-3xl overflow-hidden border border-white/10 flex items-center justify-center">
                        <div v-if="previewModal.activeFile" class="w-full h-full flex items-center justify-center p-4">
                            <img v-if="previewModal.activeFile.file_type?.startsWith('image/') || previewModal.activeFile.type?.startsWith('image/')"
                                :src="previewModal.activeFile.url || getFilePreview(previewModal.activeFile)"
                                class="max-w-full max-h-full object-contain rounded-xl shadow-2xl" />
                            <div v-else class="bg-white/10 p-10 rounded-2xl flex flex-col items-center gap-4">
                                <FileText class="h-16 w-16 text-white/40" />
                                <p class="text-sm font-semibold text-white/70 uppercase tracking-wide">
                                    {{ previewModal.activeFile.file_name || previewModal.activeFile.name }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <!-- Thumbnails -->
                    <div v-if="previewModal.files.length > 1"
                        class="h-20 flex gap-2 overflow-x-auto py-1 px-1 scrollbar-hide">
                        <div v-for="(file, idx) in previewModal.files" :key="idx"
                            @click="previewModal.activeFile = file"
                            :class="previewModal.activeFile === file
                                ? 'ring-2 ring-blue-400 ring-offset-1 ring-offset-black/80'
                                : 'opacity-50 hover:opacity-70'"
                            class="h-full aspect-square flex-shrink-0 rounded-xl overflow-hidden cursor-pointer transition">
                            <img v-if="file.file_type?.startsWith('image/') || file.type?.startsWith('image/')"
                                :src="file.url || getFilePreview(file)"
                                class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full bg-white/10 flex items-center justify-center">
                                <FileText class="h-6 w-6 text-white/50" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </Transition>
        </Teleport>

        <!-- ════════════════════════════════════════════
             ACCEPT QUOTATION MODAL
             ════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
            <div v-if="acceptModal.show"
                class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4 bg-black/60 backdrop-blur-sm"
                @click.self="acceptModal.show = false">
                <div class="bg-white dark:bg-zinc-900 w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                    <!-- Header -->
                    <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 px-5 py-4 flex items-center justify-between relative overflow-hidden flex-shrink-0">
                        <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl animate-float" />
                        <div class="relative">
                            <h3 class="text-sm font-black text-white tracking-tight">Accept Quotation</h3>
                            <p class="text-xs text-blue-100/90 mt-0.5">Review and confirm your acceptance</p>
                        </div>
                        <button @click="acceptModal.show = false"
                            class="relative flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 text-white hover:bg-white/35 hover:rotate-90 transition-all">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <!-- Body -->
                    <form @submit.prevent="submitAccept" class="p-5 space-y-4 overflow-y-auto flex-1">
                        <!-- Summary -->
                        <div v-if="acceptModal.quotation"
                            class="bg-gray-50 rounded-xl border border-gray-100 p-4 max-h-60 overflow-y-auto">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 mb-3">Summary</p>
                            <div class="space-y-2">
                                <div v-for="item in acceptModal.quotation.items" :key="item.id"
                                    class="flex justify-between items-center">
                                    <div class="flex items-center gap-2">
                                        <div class="w-2.5 h-2.5 rounded-full border flex-shrink-0"
                                            :style="colorDotStyle(item.color)"></div>
                                        <span class="text-xs text-gray-700 uppercase font-medium">{{ item.fabric }}</span>
                                        <span class="text-[9px] text-gray-400">({{ item.color }})</span>
                                    </div>
                                    <span class="text-xs font-bold text-blue-600">₱{{ formatPrice(item.unit_price) }}/kg</span>
                                </div>
                            </div>
                        </div>
                        <!-- Notes -->
                        <textarea v-model="acceptModal.notes" rows="2"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition resize-none"
                            placeholder="Notes (optional)"></textarea>
                        <!-- Submit -->
                        <button type="submit" :disabled="acceptModal.submitting"
                            class="w-full py-3 bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-xl font-black text-xs uppercase tracking-wide flex justify-center items-center gap-2 shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:scale-[1.01] active:scale-95 disabled:opacity-50 transition-all">
                            <Loader2 v-if="acceptModal.submitting" class="h-4 w-4 animate-spin" />
                            Confirm Acceptance
                        </button>
                    </form>
                </div>
            </div>
            </Transition>
        </Teleport>

        <!-- ════════════════════════════════════════════
             REJECT QUOTATION MODAL
             ════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
            <div v-if="rejectModal.show"
                class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4 bg-black/60 backdrop-blur-sm"
                @click.self="rejectModal.show = false">
                <div class="bg-white dark:bg-zinc-900 w-full sm:max-w-sm rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden">
                    <!-- Header -->
                    <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 px-5 py-4 flex items-center justify-between relative overflow-hidden">
                        <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl animate-float" />
                        <div class="relative">
                            <h3 class="text-sm font-black text-white tracking-tight">Reject Quotation</h3>
                            <p class="text-xs text-blue-100/90 mt-0.5">Please provide a reason</p>
                        </div>
                        <button @click="rejectModal.show = false"
                            class="relative flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 text-white hover:bg-white/35 hover:rotate-90 transition-all">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <form @submit.prevent="submitReject" class="p-5 space-y-4">
                        <textarea v-model="rejectModal.reason" rows="3" required
                            class="w-full rounded-xl border border-transparent bg-gray-50 dark:bg-zinc-800 p-3 text-sm text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-red-200 focus:border-red-400 transition resize-none"
                            placeholder="Explain why you're rejecting this quotation…"></textarea>
                        <div class="flex gap-3">
                            <button type="button" @click="rejectModal.show = false"
                                class="flex-1 py-2.5 bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-gray-300 rounded-xl text-xs font-bold uppercase hover:bg-gray-200 dark:hover:bg-zinc-700 active:scale-95 transition">
                                Cancel
                            </button>
                            <button type="submit" :disabled="rejectModal.submitting"
                                class="flex-1 py-2.5 bg-gradient-to-r from-rose-500 to-red-600 text-white rounded-xl text-xs font-black uppercase shadow-lg shadow-rose-500/30 hover:shadow-xl hover:scale-[1.02] active:scale-95 disabled:opacity-50 transition-all">
                                Confirm Reject
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            </Transition>
        </Teleport>

        <!-- ════════════════════════════════════════════
             SAMPLE COLOR ADJUSTMENT MODAL
             ════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
            <div v-if="adjustModal.show"
                class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4 bg-black/60 backdrop-blur-sm"
                @click.self="adjustModal.show = false">
                <div class="bg-white dark:bg-zinc-900 w-full sm:max-w-sm rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden">
                    <!-- Header -->
                    <div class="bg-gradient-to-r from-amber-500 via-orange-500 to-rose-500 px-5 py-4 flex items-center justify-between relative overflow-hidden">
                        <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl animate-float" />
                        <div class="relative">
                            <h3 class="text-sm font-black text-white tracking-tight">Request Color Adjustment</h3>
                            <p class="text-xs text-amber-100/90 mt-0.5">{{ adjustModal.sample?.fabric_name }} · describe the change</p>
                        </div>
                        <button @click="adjustModal.show = false"
                            class="relative flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 text-white hover:bg-white/35 hover:rotate-90 transition-all">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <form @submit.prevent="submitAdjust" class="p-5 space-y-4">
                        <textarea v-model="adjustModal.notes" rows="3" required
                            class="w-full rounded-xl border border-transparent bg-gray-50 dark:bg-zinc-800 p-3 text-sm text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-amber-200 focus:border-amber-400 transition resize-none"
                            placeholder="e.g. Make the blue deeper with less red…"></textarea>
                        <div class="flex gap-3">
                            <button type="button" @click="adjustModal.show = false"
                                class="flex-1 py-2.5 bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-gray-300 rounded-xl text-xs font-bold uppercase hover:bg-gray-200 dark:hover:bg-zinc-700 active:scale-95 transition">
                                Cancel
                            </button>
                            <button type="submit" :disabled="adjustModal.submitting"
                                class="flex-1 py-2.5 bg-gradient-to-r from-amber-500 to-orange-600 text-white rounded-xl text-xs font-black uppercase shadow-lg shadow-orange-500/30 hover:shadow-xl hover:scale-[1.02] active:scale-95 disabled:opacity-50 transition-all">
                                Send Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            </Transition>
        </Teleport>

        <!-- ════════════════════════════════════════════
             TRASH ACCEPTED QUOTATION MODAL
             ════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
            <div v-if="trashModal.show"
                class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4 bg-black/60 backdrop-blur-sm"
                @click.self="trashModal.show = false">
                <div class="bg-white dark:bg-zinc-900 w-full sm:max-w-sm rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden">
                    <!-- Header -->
                    <div class="bg-gradient-to-r from-slate-600 via-gray-700 to-zinc-800 px-5 py-4 flex items-center justify-between relative overflow-hidden">
                        <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl animate-float" />
                        <div class="relative">
                            <h3 class="text-sm font-black text-white tracking-tight">Trash Quotation</h3>
                            <p class="text-xs text-gray-200/90 mt-0.5">{{ trashModal.quotation?.quotation_number }} · CRM will issue a new one</p>
                        </div>
                        <button @click="trashModal.show = false"
                            class="relative flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 text-white hover:bg-white/35 hover:rotate-90 transition-all">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <form @submit.prevent="submitTrash" class="p-5 space-y-4">
                        <textarea v-model="trashModal.notes" rows="3"
                            class="w-full rounded-xl border border-transparent bg-gray-50 dark:bg-zinc-800 p-3 text-sm text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:border-gray-400 transition resize-none"
                            placeholder="Optional note for CRM (e.g. price changed my mind)…"></textarea>
                        <div class="flex gap-3">
                            <button type="button" @click="trashModal.show = false"
                                class="flex-1 py-2.5 bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-gray-300 rounded-xl text-xs font-bold uppercase hover:bg-gray-200 dark:hover:bg-zinc-700 active:scale-95 transition">
                                Cancel
                            </button>
                            <button type="submit" :disabled="trashModal.submitting"
                                class="flex-1 py-2.5 bg-gradient-to-r from-slate-600 to-gray-700 text-white rounded-xl text-xs font-black uppercase shadow-lg hover:shadow-xl hover:scale-[1.02] active:scale-95 disabled:opacity-50 transition-all">
                                Confirm Trash
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            </Transition>
        </Teleport>

        <!-- ════════════════════════════════════════════
             QUOTATION PREVIEW & DOWNLOAD MODAL
             ════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
            <div v-if="quotationPreviewModal.show"
                class="fixed inset-0 z-[120] flex items-center justify-center p-2 sm:p-4 bg-black/70 backdrop-blur-sm"
                @click.self="quotationPreviewModal.show = false">
                <div class="bg-white dark:bg-zinc-900 w-full max-w-3xl max-h-[95vh] rounded-3xl shadow-2xl overflow-hidden flex flex-col">

                    <!-- Header -->
                    <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 px-5 sm:px-6 py-4 flex items-center justify-between relative overflow-hidden flex-shrink-0">
                        <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl animate-float" />
                        <div class="relative">
                            <h3 class="text-sm font-black text-white tracking-tight">Quotation Preview</h3>
                            <p class="text-xs text-blue-100/90 mt-0.5 font-mono">
                                {{ quotationPreviewModal.quotation?.quotation_number }}
                            </p>
                        </div>
                        <div class="relative flex items-center gap-2">
                            <button
                                v-if="quotationPreviewModal.quotation?.status === 'accepted'"
                                @click="downloadQuotationPDF(quotationPreviewModal.quotation)"
                                :disabled="quotationPreviewModal.downloading"
                                class="flex items-center gap-2 px-4 py-2 bg-white text-indigo-700 rounded-xl text-xs font-black uppercase hover:bg-indigo-50 hover:scale-105 active:scale-95 transition-all shadow-lg disabled:opacity-60">
                                <Loader2 v-if="quotationPreviewModal.downloading" class="h-3.5 w-3.5 animate-spin" />
                                <Download v-else class="h-3.5 w-3.5" />
                                <span class="hidden sm:inline">Download PDF</span>
                            </button>
                            <span v-else class="hidden sm:inline text-[10px] font-bold uppercase tracking-widest text-blue-100/70">Accept to enable PDF download</span>
                            <button @click="quotationPreviewModal.show = false"
                                class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 text-white hover:bg-white/35 hover:rotate-90 transition-all">
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Document preview body -->
                    <div class="flex-1 overflow-y-auto bg-gray-100 p-4 sm:p-6">
                        <div v-if="quotationPreviewModal.quotation" id="quotation-doc"
                            class="bg-white shadow-lg mx-auto overflow-y-auto"
                            style="width:100%;max-width:620px;aspect-ratio:210/297;max-height:100%;padding:40px 48px;font-family:'Times New Roman',Times,serif;color:#111;">

                            <!-- Company Header -->
                            <div style="display:flex;align-items:center;justify-content:center;gap:14px;margin-bottom:10px;">
                                <img src="/images/applogo.png" alt="Monti Textile Logo"
                                    style="width:52px;height:52px;flex-shrink:0;object-fit:contain;" />
                                <div style="text-align:center;">
                                    <div style="font-size:22px;font-style:italic;font-weight:700;color:#1a1a2e;letter-spacing:0.5px;line-height:1.1;">
                                        Monti Textile Manufacturing Corp.
                                    </div>
                                    <div style="font-size:9px;font-weight:700;font-family:Arial,sans-serif;letter-spacing:2px;color:#333;margin-top:3px;">
                                        MANUFACTURER OF QUALITY FABRICS
                                    </div>
                                    <div style="font-size:8.5px;font-family:Arial,sans-serif;color:#555;margin-top:1px;">
                                        KM. 22, ANABU I, IMUS CAVITE
                                    </div>
                                </div>
                            </div>

                            <hr style="border:none;border-top:1.5px solid #ccc;margin:14px 0;" />

                            <!-- Title block -->
                            <div style="margin-bottom:10px;">
                                <div style="display:flex;align-items:baseline;justify-content:space-between;flex-wrap:wrap;gap:4px;">
                                    <div style="font-size:10px;font-weight:700;font-family:Arial,sans-serif;color:#111;">
                                        Quotation &mdash; {{ quotationPreviewModal.quotation.quotation_number }}
                                    </div>
                                    <div style="font-size:9px;font-family:Arial,sans-serif;color:#555;">
                                        Prepared: {{ formatDocDate(quotationPreviewModal.quotation.created_at) }}
                                    </div>
                                </div>
                                <div style="font-size:8px;font-family:Arial,sans-serif;color:#444;margin-top:5px;line-height:1.5;">
                                    All prices are quoted <strong>per kilogram (kg)</strong>.
                                    <span v-if="quotationPreviewModal.quotation.vat_type === 'inclusive'">The table shows the original base price and the price after adding 12% VAT.</span>
                                    <span v-else>Prices shown are VAT-exclusive.</span>
                                    Please note that prices are subject to change in accordance with fluctuations in yarn market costs.
                                </div>
                            </div>

                            <!-- Fabric Pricelist Table -->
                            <div style="font-size:10px;font-style:italic;font-weight:700;font-family:Arial,sans-serif;margin-bottom:6px;color:#1a1a2e;">
                                Fabric Pricelist
                            </div>
                            <table style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:9px;margin-bottom:16px;">
                                <thead>
                                    <tr style="background:#f5f5f5;">
                                        <th style="border:1px solid #ccc;padding:6px 8px;text-align:left;font-weight:700;width:32%;">Product Name</th>
                                        <th style="border:1px solid #ccc;padding:6px 8px;text-align:center;font-weight:700;width:18%;">Color</th>
                                        <th style="border:1px solid #ccc;padding:6px 8px;text-align:center;font-weight:700;width:12%;">MOQ (kg)</th>
                                        <th style="border:1px solid #ccc;padding:6px 8px;text-align:right;font-weight:700;width:19%;">Original Price/kg</th>
                                        <th v-if="quotationPreviewModal.quotation.vat_type === 'inclusive'" style="border:1px solid #ccc;padding:6px 8px;text-align:right;font-weight:700;width:19%;">Price + 12% VAT/kg</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="(group, fabric) in groupItemsByFabric(quotationPreviewModal.quotation.items)" :key="fabric">
                                        <tr v-for="(item, idx) in group" :key="item.id"
                                            :style="idx % 2 === 0 ? 'background:#fff;' : 'background:#fafafa;'">
                                            <td style="border:1px solid #ddd;padding:5px 10px;">{{ idx === 0 ? fabric : '' }}</td>
                                            <td style="border:1px solid #ddd;padding:5px 10px;text-align:center;">{{ item.color }}</td>
                                            <td style="border:1px solid #ddd;padding:5px 10px;text-align:center;">{{ item.kilos }}</td>
                                            <td style="border:1px solid #ddd;padding:5px 8px;text-align:right;">&#8369;{{ formatPrice(item.unit_price) }}</td>
                                            <td v-if="quotationPreviewModal.quotation.vat_type === 'inclusive'" style="border:1px solid #ddd;padding:5px 8px;text-align:right;">&#8369;{{ formatPrice(Number(item.unit_price) * 1.12) }}</td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>

                            <!-- VAT note -->
                            <div v-if="quotationPreviewModal.quotation.vat_type === 'inclusive'"
                                style="font-size:7.5px;font-family:Arial,sans-serif;color:#888;margin-bottom:40px;font-style:italic;">
                                * Price + 12% VAT is calculated from the original base price. The order total includes VAT.
                            </div>
                            <div v-else style="margin-bottom:40px;"></div>

                            <!-- Signature lines -->
                            <div style="display:flex;justify-content:space-between;margin-top:20px;">
                                <div style="width:45%;">
                                    <div style="border-top:1.5px solid #333;padding-top:6px;">
                                        <div style="font-size:9px;font-family:Arial,sans-serif;font-weight:700;color:#111;">Thomas Dominick C. Ong</div>
                                        <div style="font-size:8px;font-family:Arial,sans-serif;color:#555;margin-top:1px;">QA Lead</div>
                                    </div>
                                </div>
                                <div style="width:40%;">
                                    <div style="border-top:1.5px solid #333;padding-top:6px;">
                                        <div style="font-size:9px;font-family:Arial,sans-serif;color:#555;">Received By</div>
                                    </div>
                                </div>
                            </div>
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
import { useRealtimeConversation } from '@/composables/useRealtimeConversation';
import { ArrowLeft, Paperclip, Send, Loader2, FileText, X, Eye, Trash2, Layers, Package, Download, Sparkles } from 'lucide-vue-next';

const props = defineProps({ inquiry: Object, quotations: Array });
const messagesContainer = ref(null);
const newMessage = ref('');
const fileInput = ref(null);
const selectedFiles = ref([]);

// ── Real-time conversation (no reload) ──────────────────────────
const liveMessages = ref([...(props.inquiry?.messages ?? [])]);

const scrollToBottom = () => {
    // Force to latest chat on open + after images/files load (layout shift).
    const el = messagesContainer.value;
    if (!el) return;
    nextTick(() => {
        el.scrollTop = el.scrollHeight;
        requestAnimationFrame(() => { el.scrollTop = el.scrollHeight; });
        setTimeout(() => { el.scrollTop = el.scrollHeight; }, 300);
    });
};

const mergeMessages = (incoming) => {
    const list = Array.isArray(incoming) ? incoming : [incoming];
    const known = new Set(liveMessages.value.map((m) => Number(m.id)));
    let added = false;
    for (const m of list) {
        if (m && !known.has(Number(m.id))) {
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

watch(() => props.inquiry?.messages, (msgs) => {
    if (Array.isArray(msgs)) mergeMessages(msgs);
}, { deep: true });

const {
    otherTyping,
    sending,
    notifyTyping,
    sendMessage: sendRealtime,
} = useRealtimeConversation({
    getMessages: () => liveMessages.value,
    appendMessages: mergeMessages,
    feedUrl: route('client.conversation.feed', props.inquiry.hash_key),
    typingUrl: route('client.conversation.typing', props.inquiry.hash_key),
    sendUrl: route('client.conversation.message', props.inquiry.hash_key),
    onNewMessages: () => scrollToBottom(),
});

// Mobile panel toggle
const mobilePanel = ref('chat');

// PO Modal state (multiple files)
const poModal = ref({ show: false, files: [], notes: '', submitting: false });
const poFileInput = ref(null);

const rejectModal = ref({ show: false, quotation: null, reason: '', requestNew: false, submitting: false });
const acceptModal = ref({ show: false, quotation: null, notes: '', submitting: false });
const trashModal = ref({ show: false, quotation: null, notes: '', submitting: false });
const previewModal = ref({ show: false, title: '', files: [], activeFile: null });

// Quotation preview/download modal
const quotationPreviewModal = ref({ show: false, quotation: null, downloading: false });

// Dialog Modal State
const dialog = ref({ show: false, type: 'info', title: '', message: '', onConfirm: null });
const showDialog = (type, title, message, onConfirm = null) => { dialog.value = { show: true, type, title, message, onConfirm }; };
const handleDialogAction = () => { if (dialog.value.onConfirm) dialog.value.onConfirm(); dialog.value.show = false; };

// Computed: check if any quotation is accepted
const hasAcceptedQuotation = computed(() => {
    return props.quotations.some(q => q.status === 'accepted');
});

// Active vs trash tabs (trash = rejected + trashed)
const quotationTab = ref('active');
const activeQuotations = computed(() =>
    (props.quotations ?? []).filter(q => !['rejected', 'trashed'].includes(q.status))
);
const trashedQuotations = computed(() =>
    (props.quotations ?? []).filter(q => ['rejected', 'trashed'].includes(q.status))
);
const visibleQuotations = computed(() =>
    quotationTab.value === 'trash' ? trashedQuotations.value : activeQuotations.value
);

// Trash an accepted quotation the client changed their mind on (asks CRM for a new one)
const openTrashModal = (quotation) => {
    trashModal.value = { show: true, quotation, notes: '', submitting: false };
};
const submitTrash = () => {
    trashModal.value.submitting = true;
    router.post(route('client.quotation.trash', trashModal.value.quotation.id), { notes: trashModal.value.notes }, {
        preserveScroll: true,
        onSuccess: () => {
            trashModal.value = { show: false, quotation: null, notes: '', submitting: false };
            router.reload({ only: ['inquiry', 'quotations'] });
            showDialog('success', 'Trashed', 'Quotation trashed. The CRM team will issue a new one.');
        },
        onError: (errors) => {
            trashModal.value.submitting = false;
            showDialog('error', 'Error', errors.message || 'Failed to trash quotation.');
        }
    });
};

const viewQuotationAttachments = (q) => {
    const acceptedMsg = liveMessages.value.find(m => m.is_system_event && m.message.includes(q.quotation_number) && m.message.includes('ACCEPTED') && m.attachments?.length > 0);
    if (acceptedMsg) { previewModal.value = { show: true, title: q.quotation_number, files: acceptedMsg.attachments, activeFile: acceptedMsg.attachments[0] }; }
    else { showDialog('error', 'No Files', 'Could not find attachments for this quotation.'); }
};

const triggerFileUpload = () => fileInput.value.click();
const onFilesSelected = (e) => { selectedFiles.value.push(...Array.from(e.target.files)); e.target.value = ''; };
const getFilePreview = (file) => URL.createObjectURL(file);

// Helper to get full storage URL
const getFullUrl = (path) => {
    if (!path) return '#';
    if (path.startsWith('http')) return path;
    const cleanPath = path.replace(/^\/?storage\//, '');
    return `/storage/${cleanPath}`;
};

const openPreview = (file) => {
    previewModal.value = {
        show: true,
        title: file.file_name,
        files: [file],
        activeFile: file
    };
};

// Approve an attachment (client side)
const approveAttachment = (file) => {
    router.post(route('client.conversation.attachment.approve', file.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['inquiry'] });
            showDialog('success', 'Approved', 'Attachment has been marked as approved.');
        },
        onError: (errors) => {
            showDialog('error', 'Error', errors.message || 'Failed to approve attachment.');
        }
    });
};

// Fabric sample decisions (lab result forwarded by CRM)
const adjustModal = ref({ show: false, sample: null, notes: '', submitting: false });

const sampleStatusLabel = (s) => (s ? String(s).replace(/_/g, ' ') : '');

const approveSample = (sampleId) => {
    router.post(route('client.sample.approve', sampleId), {}, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['inquiry'] });
            showDialog('success', 'Sample Approved', 'You may now send your P.O.');
        },
        onError: (errors) => {
            showDialog('error', 'Error', errors.message || 'Failed to approve sample.');
        }
    });
};

const openAdjustModal = (sample) => {
    adjustModal.value = { show: true, sample, notes: '', submitting: false };
};

const submitAdjust = () => {
    if (!adjustModal.value.notes.trim()) return;
    adjustModal.value.submitting = true;
    router.post(route('client.sample.adjust', adjustModal.value.sample.id), { notes: adjustModal.value.notes }, {
        preserveScroll: true,
        onSuccess: () => {
            adjustModal.value = { show: false, sample: null, notes: '', submitting: false };
            router.reload({ only: ['inquiry'] });
            showDialog('success', 'Sent', 'Adjustment requested. CRM will prepare a new sample round.');
        },
        onError: (errors) => {
            adjustModal.value.submitting = false;
            showDialog('error', 'Error', errors.message || 'Failed to send adjustment request.');
        }
    });
};

// PO Modal functions (multiple files)
const openPoModal = () => {
    if (!hasAcceptedQuotation.value) return;
    poModal.value = { show: true, files: [], notes: '', submitting: false };
    if (poFileInput.value) poFileInput.value.value = '';
};

const onPoFilesSelected = (e) => {
    const files = Array.from(e.target.files);
    if (files.length === 0) return;
    poModal.value.files.push(...files);
    e.target.value = '';
};

const removePoFile = (index) => {
    poModal.value.files.splice(index, 1);
};

const submitPo = async () => {
    if (poModal.value.files.length === 0) {
        showDialog('error', 'No Files', 'Please select at least one file.');
        return;
    }
    poModal.value.submitting = true;
    const formData = new FormData();
    poModal.value.files.forEach((file) => {
        formData.append('po_files[]', file);
    });
    formData.append('notes', poModal.value.notes || '');

    router.post(route('client.conversation.send-po', props.inquiry.hash_key), formData, {
        forceFormData: true,
        onSuccess: () => {
            poModal.value.show = false;
            showDialog('success', 'PO Sent', 'Your Purchase Order(s) have been sent to our team.');
            scrollToBottom();
        },
        onError: (errors) => {
            showDialog('error', 'Upload Failed', errors.message || 'Failed to send Purchase Order.');
        },
        onFinish: () => { poModal.value.submitting = false; }
    });
};

// Confirmed delete for Main Input Selection
const confirmRemoveFile = (index) => {
    showDialog('confirm', 'Remove Attachment', 'Are you sure you want to remove this file from your message?', () => {
        selectedFiles.value.splice(index, 1);
    });
};

const handleSend = async () => {
    if (!newMessage.value.trim() && !selectedFiles.value.length) return;
    const text = newMessage.value;
    const files = [...selectedFiles.value];
    newMessage.value = '';
    selectedFiles.value = [];
    const created = await sendRealtime({ text, files });
    if (!created) {
        newMessage.value = text;
        selectedFiles.value = files;
    }
};

const openAcceptModal = (quotation) => { acceptModal.value = { show: true, quotation, notes: '', submitting: false }; };

const submitAccept = () => {
    showDialog('confirm', 'Accept Quotation', 'Do you confirm that you accept this quotation? You will be able to send your Purchase Order afterwards.', () => {
        acceptModal.value.submitting = true;
        router.post(route('client.quotation.accept', acceptModal.value.quotation.id), { notes: acceptModal.value.notes }, {
            onSuccess: () => { acceptModal.value.show = false; },
            onFinish: () => { acceptModal.value.submitting = false; }
        });
    });
};

const openRejectModal = (quotation) => { rejectModal.value = { show: true, quotation, reason: '', requestNew: false, submitting: false }; };
const submitReject = () => {
    rejectModal.value.submitting = true;
    router.post(route('client.quotation.reject', rejectModal.value.quotation.id), { reason: rejectModal.value.reason }, {
        onSuccess: () => { rejectModal.value.show = false; },
        onFinish: () => { rejectModal.value.submitting = false; }
    });
};

// =====================================================
// QUOTATION PREVIEW & PDF DOWNLOAD
// =====================================================

const openQuotationPreview = (quotation) => {
    quotationPreviewModal.value = { show: true, quotation, downloading: false };
};

const formatDocDate = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
};

const loadJsPDF = () => new Promise((resolve, reject) => {
    if (window.jspdf) {
        resolve(window.jspdf);
        return;
    }
    const s1 = document.createElement('script');
    s1.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
    s1.onload = () => {
        const s2 = document.createElement('script');
        s2.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js';
        s2.onload = () => resolve(window.jspdf);
        s2.onerror = () => reject(new Error('Failed to load jspdf-autotable'));
        document.head.appendChild(s2);
    };
    s1.onerror = () => reject(new Error('Failed to load jsPDF'));
    document.head.appendChild(s1);
});

const fetchLogoAsBase64 = () => new Promise((resolve) => {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
        try {
            const canvas = document.createElement('canvas');
            canvas.width = img.naturalWidth;
            canvas.height = img.naturalHeight;
            canvas.getContext('2d').drawImage(img, 0, 0);
            resolve(canvas.toDataURL('image/png'));
        } catch {
            resolve(null);
        }
    };
    img.onerror = () => resolve(null);
    img.src = '/images/applogo.png?' + Date.now();
});

const downloadQuotationPDF = async (quotation) => {
    if (!quotation) return;
    quotationPreviewModal.value.downloading = true;

    try {
        const [{ jsPDF }, logoDataUrl] = await Promise.all([
            loadJsPDF(),
            fetchLogoAsBase64(),
        ]);

        const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });

        const pageW  = doc.internal.pageSize.getWidth();
        const pageH  = doc.internal.pageSize.getHeight();
        const marginL  = 20;
        const marginR  = 20;
        const contentW = pageW - marginL - marginR;
        let y = 16;

        const logoSize   = 18;
        const logoGap    = 5;
        const textBlockW = 100;
        const groupW     = logoSize + logoGap + textBlockW;
        const groupX     = (pageW - groupW) / 2;
        const logoX      = groupX;
        const textCX     = groupX + logoSize + logoGap + textBlockW / 2;

        if (logoDataUrl) {
            doc.addImage(logoDataUrl, 'PNG', logoX, y, logoSize, logoSize);
        } else {
            const cx = logoX + logoSize / 2;
            const cy = y + logoSize / 2;
            const r  = logoSize / 2;
            doc.setFillColor(224, 123, 154);
            doc.circle(cx, cy, r, 'F');
            doc.setFillColor(255, 255, 255);
            const outerR = r * 0.55;
            const innerR = r * 0.22;
            const pts    = 5;
            const coords = [];
            for (let i = 0; i < pts * 2; i++) {
                const angle = (Math.PI / pts) * i - Math.PI / 2;
                const rd    = i % 2 === 0 ? outerR : innerR;
                coords.push([cx + rd * Math.cos(angle), cy + rd * Math.sin(angle)]);
            }
            const relLines = coords.slice(1).map((p, i) => [
                p[0] - coords[i][0],
                p[1] - coords[i][1],
            ]);
            doc.lines(relLines, coords[0][0], coords[0][1], [1, 1], 'F', true);
        }

        doc.setFont('times', 'bolditalic');
        doc.setFontSize(17);
        doc.setTextColor(26, 26, 46);
        doc.text('Monti Textile Manufacturing Corp.', textCX, y + 6.5, { align: 'center' });

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(7.5);
        doc.setTextColor(51, 51, 51);
        doc.text('MANUFACTURER OF QUALITY FABRICS', textCX, y + 11.5, { align: 'center' });

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(7);
        doc.setTextColor(85, 85, 85);
        doc.text('KM. 22, ANABU I, IMUS CAVITE', textCX, y + 15.5, { align: 'center' });

        y += logoSize + 5;

        doc.setDrawColor(180, 180, 180);
        doc.setLineWidth(0.5);
        doc.line(marginL, y, pageW - marginR, y);
        y += 6;

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(9);
        doc.setTextColor(17, 17, 17);
        doc.text(`Quotation \u2014 ${quotation.quotation_number}`, marginL, y);

        const preparedDate = formatDocDate(quotation.created_at);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8.5);
        doc.setTextColor(85, 85, 85);
        doc.text(`Prepared: ${preparedDate}`, pageW - marginR, y, { align: 'right' });
        y += 5;

        const vatText = quotation.vat_type === 'inclusive'
            ? 'Prices are quoted per kilogram (kg). The original base price and the price after adding 12% VAT are shown below.'
            : 'Prices are quoted per kilogram (kg) and shown VAT-exclusive. The order computation includes 12% VAT.';
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(7.5);
        doc.setTextColor(68, 68, 68);
        const vatLines = doc.splitTextToSize(vatText, contentW);
        doc.text(vatLines, marginL, y);
        y += vatLines.length * 3.5;

        const changeNote = 'Please note that prices are subject to change in accordance with fluctuations in yarn market costs.';
        const changeLines = doc.splitTextToSize(changeNote, contentW);
        doc.text(changeLines, marginL, y);
        y += changeLines.length * 3.5 + 5;

        doc.setFont('helvetica', 'bolditalic');
        doc.setFontSize(9.5);
        doc.setTextColor(26, 26, 46);
        doc.text('Fabric Pricelist', marginL, y);
        y += 4;

        const groups    = groupItemsByFabric(quotation.items);
        const tableBody = [];

        Object.entries(groups).forEach(([fabric, items]) => {
            items.forEach((item, idx) => {
                tableBody.push([
                    idx === 0 ? fabric : '',
                    item.color,
                    `${parseFloat(item.kilos).toFixed(2)}`,
                    `PHP ${formatPrice(item.unit_price)}`,
                    ...(quotation.vat_type === 'inclusive' ? [`PHP ${formatPrice(Number(item.unit_price) * 1.12)}`] : []),
                ]);
            });
        });

        doc.autoTable({
            startY: y,
            head: [quotation.vat_type === 'inclusive'
                ? ['Product Name', 'Color', 'MOQ (kg)', 'Original Price/kg', 'Price + 12% VAT/kg']
                : ['Product Name', 'Color', 'MOQ (kg)', 'Price/kg']],
            body: tableBody,
            theme: 'grid',
            headStyles: {
                fillColor: [240, 240, 240],
                textColor: [0, 0, 0],
                fontStyle: 'bold',
                fontSize: 8,
                halign: 'center',
            },
            bodyStyles: {
                fontSize: 8,
                textColor: [30, 30, 30],
            },
            alternateRowStyles: { fillColor: [250, 250, 250] },
            margin: { left: marginL, right: marginR },
            columnStyles: {
                0: { cellWidth: quotation.vat_type === 'inclusive' ? 58 : 78, halign: 'left' },
                1: { cellWidth: quotation.vat_type === 'inclusive' ? 35 : 46, halign: 'center' },
                2: { cellWidth: quotation.vat_type === 'inclusive' ? 18 : 22, halign: 'center' },
                3: { cellWidth: quotation.vat_type === 'inclusive' ? 34 : 24, halign: 'right' },
                ...(quotation.vat_type === 'inclusive' ? { 4: { cellWidth: 34, halign: 'right' } } : {}),
            },
        });

        y = doc.lastAutoTable.finalY + 6;

        doc.setFont('helvetica', 'italic');
        doc.setFontSize(7);
        doc.setTextColor(100, 100, 100);
        doc.text('* Order computations include 12% Value Added Tax (VAT), even when the quotation is viewed with VAT excluded.', marginL, y);
        y += 5;

        const sigY = Math.max(y + 30, Math.min(265, pageH - 25));

        doc.setDrawColor(50, 50, 50);
        doc.setLineWidth(0.6);

        const leftSigX = marginL;
        doc.line(leftSigX, sigY, leftSigX + 72, sigY);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8);
        doc.setTextColor(17, 17, 17);
        doc.text('Thomas Dominick C. Ong', leftSigX, sigY + 4.5);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(7.5);
        doc.setTextColor(85, 85, 85);
        doc.text('QA Lead', leftSigX, sigY + 8.5);

        const rightSigX = pageW - marginR - 68;
        doc.line(rightSigX, sigY, rightSigX + 68, sigY);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8);
        doc.setTextColor(85, 85, 85);
        doc.text('Received By', rightSigX, sigY + 4.5);

        doc.save(`Quotation-${quotation.quotation_number}.pdf`);

    } catch (err) {
        console.error('PDF generation failed:', err);
        showDialog('error', 'Download Failed', 'Could not generate the PDF. Please try again.');
    } finally {
        quotationPreviewModal.value.downloading = false;
    }
};

// =====================================================
// HELPERS
// =====================================================

const formatPrice  = (v) => Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2 });
const formatTime   = (d) => new Date(d).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
const formatStatus = (s) => s ? s.replace(/_/g, ' ').toUpperCase() : 'OPEN';
const openUrl      = (url) => window.open(url, '_blank');

const groupItemsByFabric = (items) =>
    (items || []).reduce((acc, item) => {
        (acc[item.fabric] = acc[item.fabric] || []).push(item);
        return acc;
    }, {});

const colorDotStyle = (color) => {
    if (color === 'White')        return 'background-color:#f5f5f5; border-color:#d1d5db';
    if (color === 'Light Colors') return 'background-color:#fde68a; border-color:#fbbf24';
    if (color === 'Dark Colors')  return 'background-color:#374151; border-color:#1f2937';
    return 'background-color:#9ca3af; border-color:#6b7280';
};

watch(() => props.inquiry.messages, () => scrollToBottom(), { deep: true });
onMounted(() => {
    scrollToBottom();
    // Images shift layout after load — re-pin to bottom.
    setTimeout(() => scrollToBottom(), 500);
});
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
.modal-enter-active { transition: opacity 0.25s ease; }
.modal-enter-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from { opacity: 0; }
.modal-enter-from > div { transform: scale(0.92) translateY(14px); opacity: 0; }
.modal-leave-active { transition: opacity 0.2s ease; }
.modal-leave-to { opacity: 0; }
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
