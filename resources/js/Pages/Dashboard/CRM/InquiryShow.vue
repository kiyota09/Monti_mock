<template>
    <Head :title="`Inquiry ${inquiry.hash_key} — ${inquiry.client?.company_name}`" />
    <AuthenticatedLayout>
        <div :class="sidebarCollapsed ? 'md:left-0' : 'md:left-64'"
            class="fixed inset-x-0 bottom-0 top-32 md:top-16 z-20 overflow-hidden bg-slate-50 dark:bg-zinc-950">
            <div ref="pageRef" :style="pageStyle"
                class="flex h-full w-full flex-col overflow-hidden p-2 pt-9 sm:p-3 sm:pt-9 lg:p-4 lg:pt-9 gap-2 sm:gap-3">

                <!-- ── Header ──────────────────────────────────────────── -->
                <header class="flex-shrink-0 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-sm px-4 sm:px-5 py-3.5">
                    <div class="flex items-center gap-3">
                        <Link :href="route('crm.inquiries')" aria-label="Back to inquiries"
                            class="p-2 rounded-xl border border-slate-200 dark:border-zinc-700 text-slate-500 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 hover:text-slate-800 dark:hover:text-white transition flex-shrink-0">
                            <ArrowLeft class="h-4 w-4" />
                        </Link>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white font-semibold text-base flex-shrink-0" aria-hidden="true">
                            {{ (inquiry.client?.company_name ?? '?').charAt(0) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 min-w-0">
                                <h1 class="text-base font-semibold text-slate-900 dark:text-white truncate leading-tight">
                                    {{ inquiry.client?.company_name }}
                                </h1>
                                <span v-if="!canEditInquiry"
                                    class="flex-shrink-0 text-[11px] font-medium text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 px-2 py-0.5 rounded-full">
                                    View only
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5 truncate">
                                <span class="font-medium text-slate-700 dark:text-zinc-300">
                                    {{ isBulkInquiry ? `Bulk inquiry · ${parsedProducts.length} products` : inquiry.product?.name }}
                                </span>
                                <span v-if="!isBulkInquiry && inquiry.product?.sku"> · SKU {{ inquiry.product.sku }}</span>
                                <span v-if="inquiry.client?.email"> · {{ inquiry.client.email }}</span>
                            </p>
                        </div>
                        <span :class="statusPill(inquiry.status).pill"
                            class="flex-shrink-0 hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium border">
                            <span class="h-1.5 w-1.5 rounded-full" :class="statusPill(inquiry.status).dot" />
                            {{ formatStatus(inquiry.status) }}
                        </span>
                        <button v-if="canEditInquiry && inquiry.status !== 'rejected'" @click="openRejectModal" type="button"
                            class="flex items-center gap-1.5 px-3 py-2 rounded-xl border border-red-200 dark:border-red-500/30 text-red-600 dark:text-red-400 text-sm font-medium hover:bg-red-50 dark:hover:bg-red-500/10 transition flex-shrink-0 active:scale-95">
                            <XCircle class="h-4 w-4" />
                            <span class="hidden sm:inline">Reject</span>
                        </button>
                    </div>
                </header>

                <!-- ── Mobile tab bar ──────────────────────────────────── -->
                <div class="flex lg:hidden rounded-xl bg-slate-200/60 dark:bg-zinc-900 p-1 flex-shrink-0" role="tablist">
                    <button @click="mobilePanel = 'chat'" role="tab" :aria-selected="mobilePanel === 'chat'" type="button"
                        :class="mobilePanel === 'chat' ? 'bg-white dark:bg-zinc-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-zinc-400'"
                        class="flex-1 py-2 rounded-lg text-sm font-medium transition">
                        Conversation
                    </button>
                    <button @click="mobilePanel = 'actions'" role="tab" :aria-selected="mobilePanel === 'actions'" type="button"
                        :class="mobilePanel === 'actions' ? 'bg-white dark:bg-zinc-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-zinc-400'"
                        class="flex-1 py-2 rounded-lg text-sm font-medium transition">
                        Actions
                        <span v-if="activeQuotations.length"
                            class="ml-1 inline-flex min-w-[18px] justify-center rounded-full bg-indigo-600 text-white text-[11px] px-1.5">{{ activeQuotations.length }}</span>
                    </button>
                </div>

                <!-- ── Main layout ─────────────────────────────────────── -->
                <div class="flex flex-1 overflow-hidden gap-4 min-h-0">

                    <!-- Left: conversation -->
                    <section :class="mobilePanel !== 'chat' ? 'hidden lg:flex' : 'flex'"
                        class="flex-1 flex-col overflow-hidden rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-sm min-w-0"
                        aria-label="Conversation">

                        <!-- Messages -->
                        <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 sm:p-6 bg-slate-50/70 dark:bg-zinc-950/40">
                            <div v-if="!liveMessages.length" class="h-full flex flex-col items-center justify-center text-center text-slate-400 dark:text-zinc-500 gap-2">
                                <FileText class="h-8 w-8" />
                                <p class="text-sm font-medium">No messages yet</p>
                                <p class="text-xs">Replies, quotations and sample updates will appear here.</p>
                            </div>

                            <TransitionGroup name="card" tag="div" class="space-y-3">
                                <div v-for="(msg, i) in liveMessages" :key="msg.id" class="flex flex-col"
                                    :class="msg.sender_type === 'eco' ? 'items-end' : 'items-start'">

                                    <!-- Day divider -->
                                    <div v-if="isNewDay(i)" class="w-full flex items-center gap-3 my-3">
                                        <span class="flex-1 h-px bg-slate-200 dark:bg-zinc-800" />
                                        <span class="text-[11px] font-medium text-slate-400 dark:text-zinc-500">{{ dayLabel(msg.created_at) }}</span>
                                        <span class="flex-1 h-px bg-slate-200 dark:bg-zinc-800" />
                                    </div>

                                    <!-- System event -->
                                    <div v-if="msg.is_system_event" class="w-full flex justify-center my-1">
                                        <div class="bg-white dark:bg-zinc-900 border border-indigo-100 dark:border-indigo-500/20 border-l-4 border-l-indigo-500 px-4 py-3 rounded-xl max-w-[92%] sm:max-w-[80%] shadow-sm">
                                            <p class="text-xs font-medium text-slate-700 dark:text-zinc-200 whitespace-pre-wrap leading-relaxed">{{ msg.message }}</p>
                                            <div v-if="msg.attachments?.length" class="mt-2.5 space-y-2">
                                                <div v-for="file in msg.attachments" :key="file.id">
                                                    <img v-if="file.file_type?.startsWith('image/')"
                                                        :src="getFullUrl(file.file_path)" :alt="file.file_name || 'Attachment'"
                                                        class="max-w-full rounded-xl cursor-zoom-in border border-slate-200 dark:border-zinc-700 hover:opacity-90 transition"
                                                        @click="openImagePreview(file)" />
                                                    <a v-else :href="getFullUrl(file.file_path)" target="_blank" rel="noopener"
                                                        class="flex items-center gap-2 p-2.5 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-xs font-medium hover:bg-slate-100 dark:hover:bg-zinc-700 transition">
                                                        <FileText class="h-4 w-4 text-indigo-500 flex-shrink-0" />
                                                        <span class="truncate text-slate-700 dark:text-zinc-200">{{ file.file_name }}</span>
                                                    </a>
                                                    <div v-if="(file.approved_by_client && !file.is_po && canEditInquiry) || (file.is_po && canEditInquiry)" class="mt-1.5 flex justify-end gap-1.5">
                                                        <button v-if="file.approved_by_client && !file.is_po && canEditInquiry" type="button"
                                                            @click="openRecipeModal(file)"
                                                            class="px-2.5 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-medium hover:bg-indigo-700 transition active:scale-95">
                                                            Create recipe
                                                        </button>
                                                        <button v-if="file.is_po && canEditInquiry" type="button"
                                                            @click="openJobOrderModal(file)"
                                                            class="px-2.5 py-1.5 bg-amber-500 text-white rounded-lg text-xs font-medium hover:bg-amber-600 transition active:scale-95">
                                                            Create job order
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Regular message -->
                                    <div v-else
                                        :class="msg.sender_type === 'eco'
                                            ? 'bg-indigo-600 text-white rounded-2xl rounded-br-md shadow-sm'
                                            : 'bg-white dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 border border-slate-200 dark:border-zinc-700 rounded-2xl rounded-bl-md shadow-sm'"
                                        class="max-w-[85%] sm:max-w-[70%] px-4 py-3">
                                        <p class="text-xs font-medium opacity-70 mb-1">
                                            {{ msg.sender_type === 'client' ? inquiry.client?.company_name : 'CRM team' }}
                                        </p>
                                        <p class="text-sm whitespace-pre-wrap leading-relaxed">{{ msg.message }}</p>

                                        <div v-if="msg.attachments?.length" class="mt-2.5 space-y-2">
                                            <div v-for="file in msg.attachments" :key="file.id">
                                                <img v-if="file.file_type?.startsWith('image/')"
                                                    :src="getFullUrl(file.file_path)" :alt="file.file_name || 'Attachment'"
                                                    class="max-w-full rounded-xl cursor-zoom-in border border-black/10 dark:border-white/10 hover:opacity-90 transition"
                                                    @click="openImagePreview(file)" />
                                                <a v-else :href="getFullUrl(file.file_path)" target="_blank" rel="noopener"
                                                    class="flex items-center gap-2 p-2.5 rounded-xl text-xs font-medium transition"
                                                    :class="msg.sender_type === 'eco' ? 'bg-white/15 hover:bg-white/25' : 'bg-slate-50 dark:bg-zinc-900 hover:bg-slate-100 dark:hover:bg-zinc-700 border border-slate-200 dark:border-zinc-700'">
                                                    <FileText class="h-4 w-4 flex-shrink-0" />
                                                    <span class="truncate">{{ file.file_name }}</span>
                                                </a>
                                                <div v-if="canEditInquiry && ((file.approved_by_client && !file.is_po) || file.is_po)" class="mt-1.5 flex justify-end gap-1.5">
                                                    <button v-if="file.approved_by_client && !file.is_po" type="button"
                                                        @click="openRecipeModal(file)"
                                                        class="px-2.5 py-1.5 rounded-lg text-xs font-medium transition active:scale-95"
                                                        :class="msg.sender_type === 'eco' ? 'bg-white text-indigo-700 hover:bg-indigo-50' : 'bg-indigo-600 text-white hover:bg-indigo-700'">
                                                        Create recipe
                                                    </button>
                                                    <button v-if="file.is_po" type="button"
                                                        @click="openJobOrderModal(file)"
                                                        class="px-2.5 py-1.5 bg-amber-400 text-amber-950 rounded-lg text-xs font-medium hover:bg-amber-500 transition active:scale-95">
                                                        Create job order
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Sample round status -->
                                        <div v-if="msg.sample_request" class="mt-2.5">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium"
                                                :class="msg.sender_type === 'eco' ? 'bg-white/15' : 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'">
                                                <FlaskConical class="h-3 w-3" />
                                                {{ msg.sample_request.code }} · {{ sampleStatusLabel(msg.sample_request.status) }}
                                            </span>
                                        </div>

                                        <p class="text-[11px] opacity-60 mt-1.5 text-right">{{ formatTime(msg.created_at) }}</p>
                                    </div>
                                </div>
                            </TransitionGroup>

                            <!-- Typing indicator -->
                            <div v-if="otherTyping" class="flex items-start mt-3" aria-live="polite">
                                <div class="bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-2xl rounded-bl-md shadow-sm px-4 py-2.5 flex items-center gap-2.5">
                                    <span class="flex gap-1">
                                        <span class="h-1.5 w-1.5 rounded-full bg-indigo-500 animate-bounce" style="animation-delay:0ms" />
                                        <span class="h-1.5 w-1.5 rounded-full bg-indigo-500 animate-bounce" style="animation-delay:150ms" />
                                        <span class="h-1.5 w-1.5 rounded-full bg-indigo-500 animate-bounce" style="animation-delay:300ms" />
                                    </span>
                                    <span class="text-xs text-slate-500 dark:text-zinc-400">{{ inquiry.client?.company_name ?? 'Client' }} is typing…</span>
                                </div>
                            </div>
                        </div>

                        <!-- Reply box -->
                        <div class="flex-shrink-0 border-t border-slate-200 dark:border-zinc-800 p-3 sm:p-4 bg-white dark:bg-zinc-900">
                            <div v-if="canEditInquiry && selectedFiles.length" class="flex gap-2 mb-3 overflow-x-auto pb-1 scrollbar-hide">
                                <div v-for="(file, i) in selectedFiles" :key="i"
                                    class="relative h-14 w-14 flex-shrink-0 bg-slate-100 dark:bg-zinc-800 rounded-xl border border-slate-200 dark:border-zinc-700 overflow-hidden">
                                    <img v-if="file.type.startsWith('image/')" :src="objectUrl(file)" :alt="file.name" class="h-full w-full object-cover" />
                                    <div v-else class="h-full w-full flex flex-col items-center justify-center px-1">
                                        <FileText class="h-5 w-5 text-slate-400" />
                                        <span class="text-[9px] text-slate-500 truncate w-full text-center">{{ file.name }}</span>
                                    </div>
                                    <button @click="selectedFiles.splice(i, 1)" type="button" :aria-label="`Remove ${file.name}`"
                                        class="absolute top-0.5 right-0.5 bg-slate-900/80 text-white rounded-full p-0.5 hover:bg-red-500 transition">
                                        <X class="h-3 w-3" />
                                    </button>
                                </div>
                            </div>

                            <div v-if="canEditInquiry"
                                class="flex items-center gap-1.5 bg-slate-50 dark:bg-zinc-800 rounded-2xl pl-4 pr-1.5 py-1.5 border border-slate-200 dark:border-zinc-700 focus-within:border-indigo-400 focus-within:ring-4 focus-within:ring-indigo-500/10 transition">
                                <input v-model="newMessage" type="text" aria-label="Reply to client"
                                    placeholder="Write a reply to the client…"
                                    class="flex-1 bg-transparent border-none focus:ring-0 focus:outline-none text-sm text-slate-800 dark:text-zinc-100 placeholder:text-slate-400 py-2"
                                    @input="notifyTyping"
                                    @keydown.enter.prevent="sendMessage" />
                                <button type="button" @click="$refs.fileInput.click()" aria-label="Attach files"
                                    class="p-2 hover:bg-slate-200 dark:hover:bg-zinc-700 rounded-xl transition text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200">
                                    <Paperclip class="h-4 w-4" />
                                </button>
                                <input ref="fileInput" type="file" class="hidden" multiple
                                    @change="e => { selectedFiles.push(...Array.from(e.target.files)); e.target.value = ''; }" />
                                <button type="button" @click="sendMessage" aria-label="Send message"
                                    :disabled="sending || (!newMessage.trim() && !selectedFiles.length)"
                                    class="p-2.5 bg-indigo-600 text-white rounded-xl disabled:opacity-40 disabled:cursor-not-allowed hover:bg-indigo-700 transition active:scale-95">
                                    <Send v-if="!sending" class="h-4 w-4" />
                                    <Loader2 v-else class="h-4 w-4 animate-spin" />
                                </button>
                            </div>
                            <p v-else class="text-sm font-medium text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 px-3 py-2.5 rounded-xl text-center">
                                View only — replies are disabled.
                            </p>
                        </div>
                    </section>

                    <!-- Right: actions panel -->
                    <aside :class="mobilePanel !== 'actions' ? 'hidden lg:block' : 'block'"
                        class="w-full lg:w-[400px] xl:w-[460px] flex-shrink-0 overflow-y-auto space-y-5 pr-0.5 pb-2">

                        <!-- Client-selected product colorways and yarns -->
                        <section v-if="inquiry.product_selections?.pantones?.length || inquiry.product_selections?.yarns?.length" aria-label="Client product selections"
                            class="overflow-hidden rounded-2xl border border-violet-200 bg-white shadow-sm dark:border-violet-900/50 dark:bg-zinc-900">
                            <div class="border-b border-violet-100 px-5 py-3 dark:border-violet-900/50">
                                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Client product selections</h2>
                                <p class="text-xs text-slate-500 dark:text-zinc-400">Pantone colors and yarns requested for {{ inquiry.product?.name }}</p>
                            </div>
                            <div class="space-y-4 p-4">
                                <div v-if="inquiry.product_selections?.pantones?.length">
                                    <h3 class="mb-2 text-[10px] font-black uppercase tracking-wider text-violet-600 dark:text-violet-300">Pantone colors ({{ inquiry.product_selections.pantones.length }})</h3>
                                    <div class="flex flex-wrap gap-1.5">
                                        <span v-for="pantone in inquiry.product_selections.pantones" :key="pantone.id" class="inline-flex max-w-full items-center gap-1.5 rounded-lg border border-slate-100 bg-slate-50 px-2 py-1.5 text-[10px] dark:border-zinc-800 dark:bg-zinc-800">
                                            <span class="h-4 w-4 shrink-0 rounded border border-black/10" :style="{ backgroundColor: pantone.hex }" />
                                            <span class="truncate font-semibold text-slate-700 dark:text-zinc-200">{{ pantone.name }}</span>
                                            <span class="shrink-0 text-slate-400">{{ colorCategoryLabel(pantone.color_category) }}</span>
                                        </span>
                                    </div>
                                </div>
                                <div v-if="inquiry.product_selections?.yarns?.length">
                                    <h3 class="mb-2 text-[10px] font-black uppercase tracking-wider text-blue-600 dark:text-blue-300">Yarn types ({{ inquiry.product_selections.yarns.length }})</h3>
                                    <div class="flex flex-wrap gap-1.5">
                                        <span v-for="yarn in inquiry.product_selections.yarns" :key="yarn.id" class="rounded-lg border border-blue-100 bg-blue-50 px-2 py-1.5 text-[10px] font-semibold text-blue-800 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-200">{{ yarn.name }}<span v-if="yarn.yarn_tier"> · {{ yarn.yarn_tier }}</span></span>
                                    </div>
                                </div>
                                <div v-if="inquiry.product_selections?.gsm || inquiry.product_selections?.texture" class="flex flex-wrap gap-2 border-t border-slate-100 pt-3 dark:border-zinc-800">
                                    <span v-if="inquiry.product_selections?.gsm" class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 dark:bg-zinc-800 dark:text-zinc-200">{{ inquiry.product_selections.gsm }} GSM</span>
                                    <span v-if="inquiry.product_selections?.texture" class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 dark:bg-zinc-800 dark:text-zinc-200">{{ inquiry.product_selections.texture }} texture</span>
                                </div>
                            </div>
                        </section>

                        <!-- Product images -->
                        <section v-if="isBulkInquiry" aria-label="Inquired product images"
                            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="border-b border-slate-100 px-5 py-3 dark:border-zinc-800">
                                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Inquired products</h2>
                                <p class="text-xs text-slate-500 dark:text-zinc-400">{{ parsedProducts.length }} products in this request</p>
                            </div>
                            <div v-if="parsedProducts.length" class="grid grid-cols-2 gap-3 p-3">
                                <article v-for="product in parsedProducts" :key="product.id || product.sku || product.name" class="min-w-0 overflow-hidden rounded-xl border border-slate-100 dark:border-zinc-800">
                                    <div class="flex h-28 items-center justify-center bg-slate-50 p-2 dark:bg-zinc-950/40">
                                        <img v-if="product.images?.length" :src="getFullUrl(product.images[0].url)" :alt="`${product.name} image`" class="h-full w-full object-contain" />
                                        <Package v-else class="h-8 w-8 text-slate-300 dark:text-zinc-600" />
                                    </div>
                                    <div class="p-2.5">
                                        <p class="truncate text-xs font-semibold text-slate-800 dark:text-zinc-100" :title="product.name">{{ product.name }}</p>
                                        <p v-if="product.sku" class="mt-0.5 truncate text-[10px] text-slate-500 dark:text-zinc-400">SKU {{ product.sku }}</p>
                                        <p v-if="product.images?.length > 1" class="mt-1 text-[10px] text-slate-400">{{ product.images.length }} images</p>
                                    </div>
                                </article>
                            </div>
                            <p v-else class="p-4 text-xs text-slate-500 dark:text-zinc-400">No products were parsed from this inquiry.</p>
                        </section>
                        <section v-else aria-label="Inquired product images"
                            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3 dark:border-zinc-800">
                                <div class="min-w-0">
                                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Inquired product</h2>
                                    <p class="truncate text-xs text-slate-500 dark:text-zinc-400">{{ inquiry.product?.name }}</p>
                                </div>
                                <span v-if="productImages.length > 1" class="shrink-0 text-xs text-slate-500 dark:text-zinc-400">
                                    {{ productImageIndex + 1 }} / {{ productImages.length }}
                                </span>
                            </div>
                            <div class="relative flex h-52 items-center justify-center bg-slate-50 p-3 dark:bg-zinc-950/40">
                                <img v-if="currentProductImage" :src="getFullUrl(currentProductImage.url)"
                                    :alt="`${inquiry.product?.name || 'Product'} image ${productImageIndex + 1}`"
                                    class="h-full w-full object-contain" />
                                <div v-else class="flex flex-col items-center gap-2 text-slate-400 dark:text-zinc-500">
                                    <Package class="h-8 w-8" />
                                    <span class="text-xs">No product images available</span>
                                </div>
                                <template v-if="productImages.length > 1">
                                    <button type="button" @click="showPreviousProductImage" aria-label="Previous product image"
                                        class="absolute left-3 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow hover:bg-white dark:bg-zinc-800/90 dark:text-white dark:hover:bg-zinc-700">
                                        <ChevronLeft class="h-4 w-4" />
                                    </button>
                                    <button type="button" @click="showNextProductImage" aria-label="Next product image"
                                        class="absolute right-3 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow hover:bg-white dark:bg-zinc-800/90 dark:text-white dark:hover:bg-zinc-700">
                                        <ChevronRight class="h-4 w-4" />
                                    </button>
                                </template>
                            </div>
                            <div v-if="productImages.length > 1" class="flex justify-center gap-1.5 py-3">
                                <button v-for="(image, index) in productImages" :key="image.id ?? index" type="button"
                                    :aria-label="`Show product image ${index + 1}`" :aria-current="productImageIndex === index ? 'true' : undefined"
                                    @click="productImageIndex = index"
                                    class="h-1.5 rounded-full transition-all"
                                    :class="productImageIndex === index ? 'w-5 bg-indigo-600' : 'w-1.5 bg-slate-300 hover:bg-slate-400 dark:bg-zinc-600'" />
                            </div>
                        </section>

                        <!-- Actions -->
                        <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-sm overflow-hidden">
                            <div class="flex items-center gap-2 px-5 py-3.5 border-b border-slate-100 dark:border-zinc-800">
                                <ClipboardList class="h-4 w-4 text-indigo-500" />
                                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Actions</h2>
                            </div>
                            <div class="p-3 space-y-1.5">
                                <button v-if="canEditInquiry && hasClientProductSelections" type="button" @click="createSelectedRecipes"
                                    :disabled="creatingRecipes"
                                    class="group w-full flex items-center gap-3 p-3 rounded-xl text-left hover:bg-emerald-50 disabled:opacity-50 dark:hover:bg-emerald-500/10 transition active:scale-[0.99]">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 text-white flex-shrink-0"><FlaskConical class="h-5 w-5" /></span>
                                    <span class="min-w-0"><span class="block text-sm font-semibold text-slate-900 dark:text-white">{{ creatingRecipes ? 'Sending to lab…' : 'Create Recipe' }}</span><span class="block text-xs text-slate-500 dark:text-zinc-400">Send {{ productSelections.pantones.length }} colors × {{ productSelections.yarns.length }} yarns to Dyeing Lab</span></span>
                                </button>
                                <button v-if="canEditInquiry" @click="openQuotationModal" type="button"
                                    class="group w-full flex items-center gap-3 p-3 rounded-xl text-left hover:bg-indigo-50 dark:hover:bg-indigo-500/10 transition active:scale-[0.99]">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white flex-shrink-0"><FileText class="h-5 w-5" /></span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-slate-900 dark:text-white">Issue quotation</span>
                                        <span class="block text-xs text-slate-500 dark:text-zinc-400">Send prices per kg to the client</span>
                                    </span>
                                </button>
                                <button v-if="canEditInquiry" @click="openMeetingModal" type="button"
                                    class="group w-full flex items-center gap-3 p-3 rounded-xl text-left hover:bg-amber-50 dark:hover:bg-amber-500/10 transition active:scale-[0.99]">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-300 flex-shrink-0"><Calendar class="h-5 w-5" /></span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-slate-900 dark:text-white">Set meeting</span>
                                        <span class="block text-xs text-slate-500 dark:text-zinc-400">Invite the client to a call or visit</span>
                                    </span>
                                </button>
                                    <div v-if="labDeliverySamples.length" class="mt-3 space-y-2 border-t border-slate-100 pt-3 dark:border-zinc-800">
                                    <div class="flex items-center justify-between gap-2 px-2"><div><h3 class="text-xs font-bold text-slate-800 dark:text-zinc-100">Sample fabric deliveries</h3><p class="mt-0.5 text-[11px] text-slate-500">Track lab preparation and send completed swatches to the client.</p></div><button v-if="canEditInquiry && readyToDeliverSamples.length >= 2" type="button" @click="openBulkDeliverSamples" class="shrink-0 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold text-white hover:bg-indigo-700">Send samples together</button></div>
                                    <article v-for="sample in labDeliverySamples" :key="`delivery-${sample.id}`" class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-3 dark:border-indigo-900/50 dark:bg-indigo-950/20">
                                        <p class="font-mono text-[11px] font-bold text-slate-700 dark:text-zinc-200">{{ sample.code }}</p>
                                        <div class="mt-1 space-y-1 text-xs text-slate-700 dark:text-zinc-200">
                                            <p><span class="font-semibold">Fabric design:</span> {{ sample.sample_fabric_design || sample.fabric_name }}</p>
                                            <p v-if="sample.sample_yarn_type"><span class="font-semibold">Yarn type:</span> {{ sample.sample_yarn_type }}</p>
                                            <p v-if="sample.gsm"><span class="font-semibold">GSM:</span> {{ sample.gsm }}</p>
                                        </div>
                                        <img v-if="sample.sample_image_path && !String(sample.sample_image_path).toLowerCase().endsWith('.pdf')" :src="`/storage/${sample.sample_image_path}`" alt="Fabric swatch sample" class="mt-2 max-h-28 w-full rounded-lg border border-slate-200 bg-white object-contain dark:border-zinc-700 dark:bg-zinc-900" />
                                        <a v-else-if="sample.sample_image_path" :href="`/storage/${sample.sample_image_path}`" target="_blank" class="mt-2 inline-flex text-xs font-semibold text-indigo-700 underline dark:text-indigo-300">Open sample PDF</a>
                                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                                            <span class="text-[11px] font-semibold text-slate-500 dark:text-zinc-400">{{ sample.status === 'requested' ? 'Queued for Dyeing Lab' : sample.status === 'in_progress' ? 'Lab preparing sample' : sample.status === 'awaiting_crm_review' ? 'Ready to send' : sample.status === 'forwarded' ? (sample.client_received_at ? 'Client received swatch' : sample.client_noted_at ? 'Client noted delivery' : 'Sent to client') : 'Client requested an adjustment' }}</span>
                                            <button v-if="canEditInquiry && sample.status === 'awaiting_crm_review'" type="button" @click="openDeliverSample(sample)" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700">Received Sample Fabrics</button>
                                        </div>
                                    </article>
                                </div>
                                <p v-if="!canEditInquiry" class="text-sm font-medium text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-500/10 px-3 py-2.5 rounded-xl text-center">
                                    View only — actions are disabled.
                                </p>
                            </div>
                            <div v-if="displaySampleRequests.length" class="mt-2 space-y-2 border-t border-slate-100 dark:border-zinc-800 pt-3">
                                <p class="px-2 text-xs font-semibold text-slate-500 dark:text-zinc-400">Client fabric sample requests</p>
                                <article v-for="sample in displaySampleRequests" :key="sample.id" class="rounded-xl border border-slate-200 dark:border-zinc-700 p-3">
                                    <p class="text-xs font-mono font-semibold text-slate-800 dark:text-zinc-100">{{ sample.code }} · {{ sample.grouped_fabric_names?.join(', ') || sample.fabric_name }}</p>
                                    <p v-if="sample.grouped_samples?.length > 1" class="mt-1 text-[11px] font-medium text-indigo-600 dark:text-indigo-300">{{ sample.grouped_samples.length }} selected fabric designs · one request</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">{{ sample.color_description }} · {{ sample.request_method || 'client request' }}<span v-if="sample.carrier_name"> · {{ sample.carrier_name }}</span></p>
                                    <div v-if="['awaiting_crm_review', 'forwarded', 'adjustment_requested'].includes(sample.status)" class="mt-2 space-y-1 rounded-lg bg-slate-50 p-2.5 text-xs text-slate-700 dark:bg-zinc-800/70 dark:text-zinc-200">
                                        <p><span class="font-semibold">Fabric design:</span> {{ sample.fabric_name }}</p>
                                        <p v-if="sample.sample_yarn_type"><span class="font-semibold">Yarn type:</span> {{ sample.sample_yarn_type }}</p>
                                        <p v-if="sample.gsm"><span class="font-semibold">GSM:</span> {{ sample.gsm }}</p>
                                        <p v-if="sample.delivery_courier"><span class="font-semibold">Courier:</span> {{ sample.delivery_courier }} · {{ sample.delivery_tracking_reference }}</p>
                                        <p v-if="sample.delivery_group_code"><span class="font-semibold">Shared shipment:</span> {{ sample.delivery_group_code }}</p>
                                        <p v-if="sample.client_noted_at || sample.client_received_at" class="font-semibold text-emerald-700 dark:text-emerald-300">Client {{ sample.client_received_at ? 'received the swatch' : 'noted the delivery update' }}</p>
                                        <a v-if="sample.sample_image_path" :href="`/storage/${sample.sample_image_path}`" target="_blank" class="mt-1 inline-flex items-center gap-2 font-semibold text-indigo-600 underline dark:text-indigo-300"><img v-if="!String(sample.sample_image_path).toLowerCase().endsWith('.pdf')" :src="`/storage/${sample.sample_image_path}`" alt="Fabric swatch sample" class="h-10 w-10 rounded border border-slate-200 object-cover dark:border-zinc-700" />{{ String(sample.sample_image_path).toLowerCase().endsWith('.pdf') ? 'Open sample PDF' : 'View sample image' }}</a>
                                    </div>
                                    <div v-if="sample.texture || sample.gsm || sample.color_hex" class="mt-1.5 flex items-center gap-2 text-xs text-slate-600 dark:text-zinc-300">
                                        <span v-if="sample.color_hex" class="h-5 w-5 rounded border border-black/10" :style="{ backgroundColor: sample.color_hex }" />
                                        <span v-if="sample.color_hex" class="font-mono">{{ sample.color_hex }}</span>
                                        <span v-if="sample.texture">Texture: {{ sample.texture }}</span>
                                        <span v-if="sample.gsm">{{ sample.gsm }} GSM</span>
                                    </div>
                                    <a v-if="sample.color_image_path" :href="`/storage/${sample.color_image_path}`" target="_blank" class="mt-1 inline-flex items-center gap-2 text-xs text-indigo-600 underline">
                                        <img :src="`/storage/${sample.color_image_path}`" alt="Client color reference" class="h-10 w-10 rounded border border-slate-200 object-cover dark:border-zinc-700" />
                                        Open color image
                                    </a>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <button v-if="canEditInquiry && sample.status === 'awaiting_receipt'" type="button" @click="openReceiveSample(sample)" class="rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white">Receive sample</button>
                                        <button v-if="canEditInquiry && ['client_submitted','crm_review'].includes(sample.status)" type="button" @click="passSampleToLab(sample)" class="rounded-lg bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white">Pass to Dyeing Lab Chemist</button>
                                        <span v-if="sample.status === 'forwarded' && sample.client_noted_at" class="self-center text-[11px] text-slate-500">Client noted delivery</span>
                                        <span v-if="sample.status === 'forwarded' && sample.client_received_at" class="self-center text-[11px] font-semibold text-emerald-700">Client received swatch</span>
                                        <span class="self-center text-[11px] capitalize text-slate-500">{{ String(sample.status).replaceAll('_', ' ') }}</span>
                                    </div>
                                </article>
                            </div>
                        </div>

                        <section v-if="clientPoReviewState.length" aria-label="Client purchase orders" class="space-y-2">
                            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white px-1">Client purchase orders <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs dark:bg-zinc-800">{{ clientPoReviewState.length }}</span></h2>
                            <article v-for="po in clientPoReviewState" :key="po.id" class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                <div class="flex items-start justify-between gap-2"><div><p class="font-mono text-xs font-bold text-slate-800 dark:text-zinc-100">{{ po.po_number }}</p><p class="mt-1 text-[11px] capitalize text-slate-500">{{ String(po.status).replaceAll('_', ' ') }} · PHP {{ Number(po.total_amount).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</p></div><span v-if="po.pushed" class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-bold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">In ECO Push Center</span></div>
                                <div class="mt-2 space-y-1 text-xs text-slate-600 dark:text-zinc-300"><p v-for="(item, index) in po.items" :key="index">{{ item.product_name }} · {{ item.color }} · {{ item.fabric_type }} · {{ item.quantity }} kg</p></div>
                                <div class="mt-3 space-y-3">
                                    <div v-for="item in po.items" :key="item.id" class="rounded-lg border border-slate-100 p-2.5 dark:border-zinc-800">
                                        <p class="text-xs font-semibold text-slate-700 dark:text-zinc-200">{{ item.product_name }} · {{ item.color }} · {{ item.fabric_type }} · {{ item.quantity }} kg</p>
                                        <label class="mt-2 block text-[11px] font-semibold text-slate-500 dark:text-zinc-400">Approved recipe for this PO line
                                            <select v-model.number="item.recipe_id" :disabled="po.pushed || !canEditInquiry" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs text-slate-800 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                                                <option :value="null">Choose matching approved recipe</option>
                                                <option v-for="recipe in item.recipe_options" :key="recipe.id" :value="recipe.id">{{ recipe.label }}</option>
                                            </select>
                                        </label>
                                        <p v-if="!item.recipe_options.length" class="mt-1 text-[10px] text-amber-700 dark:text-amber-300">No approved recipe matches this product, color, and yarn yet.</p>
                                    </div>
                                </div>
                                <button v-if="canEditInquiry && !po.pushed" type="button" :disabled="savingRecipePoId === po.id || po.items.some(item => !item.recipe_id)" @click="saveClientPoRecipes(po)" class="mt-3 w-full rounded-lg border border-indigo-200 px-3 py-2 text-xs font-bold text-indigo-700 disabled:opacity-50 dark:border-indigo-800 dark:text-indigo-300">{{ savingRecipePoId === po.id ? 'Saving recipe links…' : 'Save recipes to this PO' }}</button>
                                <button v-if="canEditInquiry && !po.pushed" type="button" :disabled="pushingPurchaseOrderId === po.id || !poRecipesPersisted(po)" @click="pushClientPurchaseOrder(po)" class="mt-3 w-full rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-50">{{ pushingPurchaseOrderId === po.id ? 'Passing to ECO…' : 'Pass PO to ECO Push Center' }}</button>
                            </article>
                        </section>

                        <!-- Recipes -->
                        <section v-if="recipes.length > 0" aria-label="Recipes">
                            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white px-1 mb-2">
                                Recipes
                                <span class="text-xs font-medium text-slate-500 dark:text-zinc-400 bg-slate-200/70 dark:bg-zinc-800 rounded-full px-2 py-0.5">{{ recipes.length }}</span>
                            </h2>
                            <TransitionGroup name="card" tag="div" class="space-y-2">
                                <div v-for="recipe in recipes" :key="recipe.id"
                                    class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-zinc-800 px-4 py-3 shadow-sm">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ recipe.product?.name }}</p>
                                    <p class="text-xs text-slate-600 dark:text-zinc-300 mt-0.5">{{ recipe.yarn_type }} · {{ recipe.dye_color }}</p>
                                    <p v-if="recipe.weave_design" class="text-xs text-slate-400 dark:text-zinc-500 mt-0.5">{{ recipe.weave_design }}</p>
                                </div>
                            </TransitionGroup>
                        </section>

                        <!-- Quotations -->
                        <section v-if="quotations.length > 0" aria-label="Quotations">
                            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white px-1 mb-2">
                                Quotations
                                <span class="text-xs font-medium text-slate-500 dark:text-zinc-400 bg-slate-200/70 dark:bg-zinc-800 rounded-full px-2 py-0.5">{{ quotations.length }}</span>
                            </h2>

                            <div class="flex rounded-xl bg-slate-200/60 dark:bg-zinc-900 p-1 mb-3" role="tablist">
                                <button @click="quoteTab = 'active'" type="button" role="tab" :aria-selected="quoteTab === 'active'"
                                    :class="quoteTab === 'active' ? 'bg-white dark:bg-zinc-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-zinc-400 hover:text-slate-700'"
                                    class="flex-1 py-1.5 rounded-lg text-xs font-medium transition">
                                    Active ({{ activeQuotations.length }})
                                </button>
                                <button @click="quoteTab = 'trash'" type="button" role="tab" :aria-selected="quoteTab === 'trash'"
                                    :class="quoteTab === 'trash' ? 'bg-white dark:bg-zinc-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-zinc-400 hover:text-slate-700'"
                                    class="flex-1 py-1.5 rounded-lg text-xs font-medium transition">
                                    Trash ({{ trashedQuotations.length }})
                                </button>
                            </div>

                            <p v-if="!visibleQuotations.length"
                                class="text-xs text-slate-500 dark:text-zinc-400 text-center py-6 rounded-xl border border-dashed border-slate-300 dark:border-zinc-700">
                                {{ quoteTab === 'trash' ? 'Nothing in the trash.' : 'No active quotations. Issue one from the actions above.' }}
                            </p>

                            <TransitionGroup name="card" tag="div" class="space-y-3">
                                <article v-for="q in visibleQuotations" :key="q.id"
                                    class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-zinc-800 border-l-4 overflow-hidden shadow-sm"
                                    :class="quoteTone(q.status).border">
                                    <div class="flex items-center justify-between gap-2 px-4 py-2.5 border-b border-slate-100 dark:border-zinc-800">
                                        <span class="font-mono text-xs font-semibold text-slate-800 dark:text-zinc-100">{{ q.quotation_number }}</span>
                                        <span class="text-xs font-medium px-2 py-0.5 rounded-full capitalize" :class="quoteTone(q.status).badge">{{ q.status }}</span>
                                    </div>
                                    <div class="p-4 space-y-3">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-xs font-medium rounded-full px-2 py-0.5"
                                                :class="q.vat_type === 'inclusive'
                                                    ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300'
                                                    : 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300'">
                                                {{ q.vat_type === 'inclusive' ? 'VAT inclusive (+12%)' : 'VAT exclusive' }}
                                            </span>
                                            <span v-if="q.payment_terms" class="text-xs text-slate-500 dark:text-zinc-400">{{ q.payment_terms }}</span>
                                        </div>

                                        <div v-for="(group, fabric) in groupItemsByFabric(q.items)" :key="fabric"
                                            class="border border-slate-100 dark:border-zinc-800 rounded-xl overflow-hidden">
                                            <div class="bg-slate-50 dark:bg-zinc-800/60 px-3 py-2 flex items-center gap-2">
                                                <Package class="h-3.5 w-3.5 text-indigo-500 flex-shrink-0" />
                                                <p class="text-xs font-semibold text-slate-800 dark:text-zinc-100 truncate flex-1">{{ fabric }}</p>
                                                <span class="text-xs text-slate-500 dark:text-zinc-400">MOQ {{ group[0]?.kilos }} kg</span>
                                            </div>
                                            <div class="divide-y divide-slate-100 dark:divide-zinc-800">
                                                <div v-for="item in group" :key="item.id" class="px-3 py-2 flex items-center justify-between">
                                                    <div class="flex items-center gap-2">
                                                        <span class="w-3 h-3 rounded-full border flex-shrink-0" :style="colorDotStyle(item.color)" />
                                                        <span class="text-xs text-slate-700 dark:text-zinc-300">{{ item.color }}</span>
                                                        <span class="truncate text-[10px] text-slate-500 dark:text-zinc-400">{{ item.fabric }}<span v-if="item.design"> · {{ item.design }}</span></span>
                                                    </div>
                                                    <p class="text-sm font-semibold text-slate-900 dark:text-white tabular-nums">
                                                        ₱{{ formatPeso(item.unit_price) }}<span class="text-xs font-normal text-slate-500">/kg</span>
                                                        <span v-if="q.vat_type === 'inclusive'" class="text-[11px] font-normal text-slate-400 ml-1">incl. VAT</span>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <p v-if="q.notes" class="text-xs text-slate-500 dark:text-zinc-400 italic border-t border-slate-100 dark:border-zinc-800 pt-2.5">
                                            {{ q.notes }}
                                        </p>
                                    </div>
                                </article>
                            </TransitionGroup>
                        </section>
                    </aside>
                </div>
            </div>
        </div>

        <!-- ════════ IMAGE PREVIEW ════════ -->
        <Teleport to="body">
            <div v-if="bulkDeliverModal.show" class="fixed inset-0 z-[131] flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="bulk-deliver-title">
                <form @submit.prevent="submitBulkDeliverSamples" class="flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-zinc-700 dark:bg-zinc-900">
                    <header class="border-b border-slate-100 p-5 dark:border-zinc-800"><h2 id="bulk-deliver-title" class="text-lg font-bold text-slate-900 dark:text-white">Send samples in one shipment</h2><p class="mt-1 text-sm text-slate-500">Choose at least two ready samples. They will share one courier and tracking reference.</p></header>
                    <div class="space-y-3 overflow-y-auto p-5">
                        <label v-for="sample in readyToDeliverSamples" :key="`bulk-${sample.id}`" class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-zinc-700"><input v-model="bulkDeliverModal.sample_request_ids" type="checkbox" :value="sample.id" class="mt-1 rounded border-slate-300 text-indigo-600" /><span><span class="block font-mono text-xs font-bold">{{ sample.code }}</span><span class="mt-1 block text-sm">{{ sample.sample_fabric_design || sample.fabric_name }}</span></span></label>
                        <label class="block space-y-1 text-sm font-semibold">Courier<input v-model="bulkDeliverModal.delivery_courier" required maxlength="120" placeholder="Courier or delivery service" class="block w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800" /></label>
                        <label class="block space-y-1 text-sm font-semibold">Shared tracking reference<input v-model="bulkDeliverModal.delivery_tracking_reference" required maxlength="160" placeholder="Tracking reference" class="block w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800" /></label>
                        <label class="block space-y-1 text-sm font-semibold">Notes for the client <span class="font-normal text-slate-400">(optional)</span><textarea v-model="bulkDeliverModal.delivery_notes" maxlength="1000" rows="3" class="block w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800"></textarea></label>
                    </div>
                    <footer class="flex justify-end gap-2 border-t border-slate-100 p-4"><button type="button" @click="bulkDeliverModal.show = false" class="rounded-xl border px-4 py-2 text-sm">Cancel</button><button type="submit" :disabled="bulkDeliverModal.submitting || bulkDeliverModal.sample_request_ids.length < 2" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Send {{ bulkDeliverModal.sample_request_ids.length }} samples together</button></footer>
                </form>
            </div>
            <Transition name="modal">
                <div v-if="imagePreviewModal.show" role="dialog" aria-modal="true" aria-label="Image preview"
                    class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/85 backdrop-blur-sm"
                    @click.self="imagePreviewModal.show = false">
                    <button @click="imagePreviewModal.show = false" type="button" aria-label="Close preview"
                        class="absolute top-4 right-4 p-2.5 bg-white/10 hover:bg-white/20 rounded-full transition z-[210]">
                        <X class="h-5 w-5 text-white" />
                    </button>
                    <div class="w-full max-w-5xl h-[88vh] flex items-center justify-center">
                        <img v-if="imagePreviewModal.file" :src="getFullUrl(imagePreviewModal.file.file_path)"
                            :alt="imagePreviewModal.file.file_name || 'Attachment'"
                            class="max-w-full max-h-full object-contain rounded-xl shadow-2xl" />
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ════════ ISSUE QUOTATION ════════ -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showQuotationModal" role="dialog" aria-modal="true" aria-labelledby="quotation-title"
                    class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center sm:p-4 bg-slate-900/50 backdrop-blur-sm"
                    @click.self="showQuotationModal = false">
                    <div class="bg-white dark:bg-zinc-900 w-full sm:max-w-2xl rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[95vh] border border-slate-200 dark:border-zinc-800">
                        <div class="flex items-start justify-between gap-3 px-5 sm:px-6 py-4 border-b border-slate-100 dark:border-zinc-800 flex-shrink-0">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white"><FileText class="h-5 w-5" /></span>
                                <div>
                                    <h3 id="quotation-title" class="text-base font-semibold text-slate-900 dark:text-white">Issue quotation</h3>
                                    <p class="text-sm text-slate-500 dark:text-zinc-400">{{ inquiry.client?.company_name }}</p>
                                </div>
                            </div>
                            <button @click="showQuotationModal = false" type="button" aria-label="Close" class="modal-close"><X class="h-4 w-4" /></button>
                        </div>

                        <form @submit.prevent="submitQuotation" class="flex flex-col flex-1 min-h-0">
                            <div class="p-5 sm:p-6 overflow-y-auto flex-1 space-y-5">

                                <!-- VAT type -->
                                <div>
                                    <span class="field-label">VAT type</span>
                                    <div class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 dark:bg-zinc-800 p-1">
                                        <button type="button" @click="form.vat_type = 'exclusive'"
                                            :class="form.vat_type === 'exclusive' ? 'bg-white dark:bg-zinc-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-zinc-400'"
                                            class="py-2 rounded-lg text-sm font-medium transition">
                                            VAT exclusive
                                        </button>
                                        <button type="button" @click="form.vat_type = 'inclusive'"
                                            :class="form.vat_type === 'inclusive' ? 'bg-white dark:bg-zinc-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-zinc-400'"
                                            class="py-2 rounded-lg text-sm font-medium flex items-center justify-center gap-1.5 transition">
                                            VAT inclusive
                                            <span v-if="form.vat_type === 'inclusive'" class="bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 px-1.5 py-0.5 rounded text-[11px]">+12%</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Products -->
                                <div class="space-y-3">
                                    <div v-for="(item, idx) in form.items" :key="idx"
                                        class="border border-slate-200 dark:border-zinc-800 rounded-2xl overflow-hidden">
                                        <div class="flex items-center gap-2 bg-slate-50 dark:bg-zinc-800/60 px-3 py-2.5 border-b border-slate-200 dark:border-zinc-800">
                                            <Package class="h-4 w-4 text-indigo-500 flex-shrink-0" />
                                            <input v-model="item.fabric" aria-label="Fabric or product name" :readonly="hasClientProductSelections"
                                                class="flex-1 bg-transparent border-none p-0 text-sm font-semibold text-slate-900 dark:text-white focus:ring-0 focus:outline-none min-w-0 placeholder:text-slate-400 placeholder:font-normal"
                                                placeholder="Fabric / product name" required />
                                            <button v-if="!item.isDefault" type="button" @click="removeItem(idx)" aria-label="Remove product"
                                                class="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg flex-shrink-0 transition">
                                                <Trash2 class="h-4 w-4" />
                                            </button>
                                        </div>

                                        <div class="p-3 space-y-3">
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label class="field-label">MOQ (kg)</label>
                                                    <input v-model.number="item.kilos" type="number" :min="minimumOrderFor(item)" step="0.01" inputmode="decimal" class="field-input" placeholder="0.00" />
                                                </div>
                                                <div>
                                                    <label class="field-label">Design <span class="font-normal text-slate-400">(optional)</span></label>
                                                    <input v-model="item.design" type="text" class="field-input" placeholder="e.g. Standard" />
                                                </div>
                                            </div>

                                            <div v-if="!hasClientProductSelections">
                                                <span class="field-label">Fabric types <span class="font-normal text-slate-400">(select all that apply)</span></span>
                                                <div class="grid max-h-44 grid-cols-1 gap-1 overflow-y-auto rounded-xl border border-slate-200 p-2 dark:border-zinc-700 sm:grid-cols-2">
                                                    <label v-for="fabricType in quotationFabrics" :key="fabricType.id" class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-xs text-slate-700 hover:bg-indigo-50 dark:text-zinc-200 dark:hover:bg-indigo-500/10">
                                                        <input type="checkbox" :checked="item.fabric_type_ids.includes(Number(fabricType.id))" @change="toggleQuotationFabricType(item, fabricType, $event.target.checked)" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                                                        <span class="min-w-0 truncate">{{ fabricType.name }}</span>
                                                    </label>
                                                    <p v-if="!quotationFabrics.length" class="col-span-full p-2 text-xs text-slate-500">No yarn materials are available for fabric pricing.</p>
                                                </div>
                                            </div>

                                            <div v-if="hasClientProductSelections" class="rounded-xl border border-violet-100 bg-violet-50/60 p-3 dark:border-violet-900/40 dark:bg-violet-950/20">
                                                <div class="flex items-center gap-3">
                                                    <span class="h-9 w-9 shrink-0 rounded-lg border border-black/10" :style="{ backgroundColor: item.pantone_hex }" />
                                                    <div class="min-w-0 flex-1"><p class="truncate text-xs font-bold text-slate-800 dark:text-white">{{ item.pantone_name }}</p><p class="text-[10px] text-slate-500">{{ colorCategoryLabel(item.color_category) }} · Yarn: {{ item.fabric_type_name }}</p></div>
                                                    <span class="text-right text-[10px] text-slate-500">Price page<br /><strong class="text-slate-800 dark:text-zinc-100">{{ item.default_unit_price === null ? 'Not set' : `₱${Number(item.default_unit_price).toFixed(2)}/kg` }}</strong></span>
                                                </div>
                                                <label class="mt-3 block text-xs font-bold text-slate-700 dark:text-zinc-200">Client-specific price per kg
                                                    <span class="relative mt-1 block"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">₱</span><input v-model.number="item.unit_price" type="number" min="0" step="0.01" inputmode="decimal" required class="field-input !pl-8" /></span>
                                                </label>
                                            </div>

                                            <div v-for="fabricTypeId in item.fabric_type_ids" :key="fabricTypeId" class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-3 dark:border-indigo-500/20 dark:bg-indigo-500/5">
                                                <p class="mb-2 text-xs font-semibold text-indigo-800 dark:text-indigo-200">{{ quotationFabricName(fabricTypeId) }} · Price per kg <span v-if="form.vat_type === 'inclusive'" class="font-normal text-indigo-600 dark:text-indigo-300">(VAT inclusive)</span></p>
                                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                                    <label v-for="tier in quotationPriceTiers" :key="tier.key" class="tier-box">
                                                        <span class="flex items-center gap-1.5 text-xs font-medium text-slate-600 dark:text-zinc-300"><span class="h-3 w-3 rounded-full border" :class="tier.dot" /> {{ tier.label }}</span>
                                                        <span class="relative mt-1.5 block"><span class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-sm text-slate-400">₱</span><input v-model.number="item.fabric_prices[fabricTypeId][tier.key]" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00" required class="field-input !pl-7" /></span>
                                                    </label>
                                                </div>
                                            </div>


                                        </div>
                                    </div>
                                </div>

                                <button v-if="!hasClientProductSelections" type="button" @click="addItem"
                                    class="w-full py-2.5 border-2 border-dashed border-slate-300 dark:border-zinc-700 text-indigo-600 dark:text-indigo-400 rounded-xl text-sm font-medium hover:bg-indigo-50 dark:hover:bg-indigo-500/10 hover:border-indigo-300 transition flex items-center justify-center gap-2">
                                    <Plus class="h-4 w-4" /> Add another product
                                </button>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="field-label" for="payment-terms">Payment terms <span class="text-red-500">*</span></label>
                                        <select id="payment-terms" v-model="form.payment_terms" required class="field-input">
                                            <option value="">Select payment terms…</option>
                                            <option value="Cash on Delivery">Cash on Delivery</option>
                                            <option value="30 Days">30 Days</option>
                                            <option value="60 Days">60 Days</option>
                                            <option value="90 Days">90 Days</option>
                                            <option value="120 Days">120 Days</option>
                                            <option value="150 Days">150 Days</option>
                                        </select>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="field-label" for="quote-notes">Notes <span class="font-normal text-slate-400">(optional)</span></label>
                                        <textarea id="quote-notes" v-model="form.notes" rows="2" class="field-input resize-none" placeholder="Additional terms, delivery requirements…"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-3 px-5 sm:px-6 py-4 border-t border-slate-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 flex-shrink-0">
                                <button type="button" @click="showQuotationModal = false" class="btn-secondary">Cancel</button>
                                <button type="submit" :disabled="submitting || !form.payment_terms" class="btn-primary flex-1">
                                    <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
                                    <Send v-else class="h-4 w-4" />
                                    Send quotation
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ════════ SET MEETING ════════ -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showMeetingModal" role="dialog" aria-modal="true" aria-labelledby="meeting-title"
                    class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center sm:p-4 bg-slate-900/50 backdrop-blur-sm"
                    @click.self="showMeetingModal = false">
                    <div class="bg-white dark:bg-zinc-900 w-full sm:max-w-md rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden border border-slate-200 dark:border-zinc-800">
                        <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-slate-100 dark:border-zinc-800">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-300"><Calendar class="h-5 w-5" /></span>
                                <div>
                                    <h3 id="meeting-title" class="text-base font-semibold text-slate-900 dark:text-white">Schedule meeting</h3>
                                    <p class="text-sm text-slate-500 dark:text-zinc-400">{{ inquiry.client?.company_name }}</p>
                                </div>
                            </div>
                            <button @click="showMeetingModal = false" type="button" aria-label="Close" class="modal-close"><X class="h-4 w-4" /></button>
                        </div>
                        <form @submit.prevent="submitMeeting" class="p-5 space-y-4">
                            <div>
                                <label class="field-label" for="meeting-when">Date and time <span class="text-red-500">*</span></label>
                                <input id="meeting-when" v-model="meetingData.scheduled_at" type="datetime-local" required :min="meetingMinDateTime" class="field-input" />
                                <p v-if="meetingDateError" class="text-sm font-medium text-rose-600" role="alert">{{ meetingDateError }}</p>
                                <p class="hint">Today or later — past dates can't be scheduled.</p>
                            </div>
                            <div>
                                <label class="field-label" for="meeting-type">Meeting type <span class="text-red-500">*</span></label>
                                <select id="meeting-type" v-model="meetingData.type" required class="field-input">
                                    <option value="onsite">On‑site</option>
                                    <option value="video">Video call</option>
                                    <option value="phone">Phone call</option>
                                </select>
                            </div>
                            <div>
                                <label class="field-label" for="meeting-location">Location or link <span class="text-red-500">*</span></label>
                                <input id="meeting-location" v-model="meetingData.location" type="text" placeholder="e.g. Zoom, Office, Google Meet" required class="field-input" />
                            </div>
                            <div class="flex gap-3 pt-1">
                                <button type="button" @click="showMeetingModal = false" class="btn-secondary">Cancel</button>
                                <button type="submit" :disabled="scheduling" class="btn-primary flex-1 !bg-amber-500 hover:!bg-amber-600">
                                    <Loader2 v-if="scheduling" class="h-4 w-4 animate-spin" />
                                    <Calendar v-else class="h-4 w-4" />
                                    Send invite
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ════════ REQUEST FABRIC SAMPLE ════════ -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="false" role="dialog" aria-modal="true" aria-labelledby="sample-title"
                    class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center sm:p-4 bg-slate-900/50 backdrop-blur-sm"
                    @click.self="showSampleModal = false">
                    <div class="bg-white dark:bg-zinc-900 w-full sm:max-w-md rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden border border-slate-200 dark:border-zinc-800">
                        <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-slate-100 dark:border-zinc-800">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-300"><FlaskConical class="h-5 w-5" /></span>
                                <div>
                                    <h3 id="sample-title" class="text-base font-semibold text-slate-900 dark:text-white">Request fabric sample</h3>
                                    <p class="text-sm text-slate-500 dark:text-zinc-400">{{ inquiry.client?.company_name }} · goes to the dyeing lab</p>
                                </div>
                            </div>
                            <button @click="showSampleModal = false" type="button" aria-label="Close" class="modal-close"><X class="h-4 w-4" /></button>
                        </div>
                        <form @submit.prevent="submitSampleRequest" class="p-5 space-y-4">
                            <div>
                                <label class="field-label" for="sample-fabric">Fabric design <span class="text-red-500">*</span></label>
                                <select id="sample-fabric" v-model="sampleForm.product_id" required class="field-input">
                                    <option value="" disabled>Select fabric design</option>
                                    <option v-for="p in availableProducts" :key="p.id" :value="p.id">{{ p.name }}{{ p.sku ? ` · ${p.sku}` : '' }}</option>
                                </select>
                                <p v-if="isBulkInquiry" class="hint">Bulk inquiry — choose the specific fabric for this sample round.</p>
                            </div>
                            <div>
                                <label class="field-label" for="sample-color">Color description <span class="text-red-500">*</span></label>
                                <textarea id="sample-color" v-model="sampleForm.color_description" rows="3" required
                                    placeholder="e.g. Deep royal blue, slightly reddish cast — see attached reference"
                                    class="field-input resize-none"></textarea>
                            </div>
                            <div>
                                <span class="field-label">Urgency</span>
                                <div class="grid grid-cols-4 gap-1 rounded-xl bg-slate-100 dark:bg-zinc-800 p-1">
                                    <button v-for="u in urgencyOptions" :key="u.value" type="button" @click="sampleForm.urgency = u.value"
                                        :aria-pressed="sampleForm.urgency === u.value"
                                        :class="sampleForm.urgency === u.value ? 'bg-white dark:bg-zinc-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-zinc-400'"
                                        class="py-1.5 rounded-lg text-xs font-medium transition">
                                        {{ u.label }}
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="field-label" for="sample-notes">Notes for the lab <span class="font-normal text-slate-400">(optional)</span></label>
                                <textarea id="sample-notes" v-model="sampleForm.notes" rows="2" placeholder="Guidance for the chemist" class="field-input resize-none"></textarea>
                            </div>
                            <div class="flex gap-3 pt-1">
                                <button type="button" @click="showSampleModal = false" class="btn-secondary">Cancel</button>
                                <button type="submit" :disabled="sampleSubmitting" class="btn-primary flex-1 !bg-emerald-600 hover:!bg-emerald-700">
                                    <Loader2 v-if="sampleSubmitting" class="h-4 w-4 animate-spin" />
                                    <FlaskConical v-else class="h-4 w-4" />
                                    Send to dyeing lab
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <div v-if="receiveSampleModal.show" class="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/50 p-4" role="dialog" aria-modal="true" aria-labelledby="receive-sample-title">
                <section class="flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl border border-slate-200 dark:border-zinc-700">
                    <header class="shrink-0 border-b border-slate-100 dark:border-zinc-800 p-5"><h2 id="receive-sample-title" class="font-semibold text-slate-900 dark:text-white">Receive fabric sample</h2><p class="mt-1 text-sm text-slate-500">Record each color from the swatch or image for {{ receiveSampleModal.sample?.fabric_name }}.</p></header>
                    <div class="flex min-h-0 flex-1 flex-col overflow-hidden lg:flex-row">
                        <aside v-if="receiveSampleModal.sample?.client_reference_path" class="flex min-h-[240px] shrink-0 items-center justify-center overflow-auto border-b border-slate-200 bg-slate-50 dark:border-zinc-700 dark:bg-zinc-950 lg:min-h-0 lg:w-1/2 lg:border-b-0 lg:border-r">
                            <img v-if="receiveSampleReferenceKind === 'image'" :src="`/storage/${receiveSampleModal.sample.client_reference_path}`" alt="Client fabric sample reference" class="h-full max-h-[42vh] w-full object-contain lg:max-h-none" />
                            <iframe v-else-if="receiveSampleReferenceKind === 'pdf'" :src="`/storage/${receiveSampleModal.sample.client_reference_path}`" title="Client fabric sample reference" class="h-full min-h-[280px] w-full border-0 lg:min-h-0" />
                            <p v-else class="p-5 text-sm text-slate-500">This client reference cannot be previewed here.</p>
                        </aside>
                        <div class="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain p-5">
                        <p class="text-xs font-medium text-slate-500 dark:text-zinc-400">Add a design entry for each fabric type shown in the client reference. You can add more designs as needed.</p>
                        <div v-for="(color, index) in receiveSampleModal.colors" :key="index" class="space-y-3 rounded-xl border border-slate-200 p-3 dark:border-zinc-700">
                            <label class="block space-y-1 text-xs font-semibold text-slate-600 dark:text-zinc-400">Fabric design
                                <select v-model="color.fabric_name" required class="block w-full rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 text-sm font-normal">
                                    <option v-if="color.fabric_name && !acceptedFabricDesignNames.includes(color.fabric_name)" :value="color.fabric_name">{{ color.fabric_name }} (submitted)</option>
                                    <option value="" disabled>Select an approved fabric design</option>
                                    <option v-for="fabricName in acceptedFabricDesignNames" :key="fabricName" :value="fabricName">{{ fabricName }}</option>
                                </select>
                            </label>
                            <input v-model="color.pantone_code" required placeholder="Pantone code" class="rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 text-sm" />
                            <input v-model="color.pantone_color" required placeholder="Pantone color name" class="rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 text-sm" />
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <label class="space-y-1 text-xs font-semibold text-slate-600 dark:text-zinc-400">Texture
                                    <select v-model="color.texture" required class="block w-full rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 text-sm font-normal"><option value="" disabled>Select texture</option><option value="Hard">Hard</option><option value="Medium">Medium</option><option value="Soft">Soft</option></select>
                                </label>
                                <label class="space-y-1 text-xs font-semibold text-slate-600 dark:text-zinc-400">GSM
                                    <input v-model.number="color.gsm" type="number" inputmode="numeric" min="1" max="10000" step="1" required placeholder="e.g. 180" class="block w-full rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 text-sm font-normal" />
                                </label>
                                <label class="space-y-1 text-xs font-semibold text-slate-600 dark:text-zinc-400">Color reference
                                    <select v-model="color.reference_type" required class="block w-full rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 text-sm font-normal"><option value="hex">Color hex</option><option value="image">Upload image</option></select>
                                </label>
                                <label v-if="color.reference_type === 'hex'" class="space-y-1 text-xs font-semibold text-slate-600 dark:text-zinc-400">Color
                                    <span class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white p-1.5 dark:border-zinc-700 dark:bg-zinc-800"><input v-model="color.color_hex" type="color" required aria-label="Choose color hex" class="h-8 w-10 cursor-pointer rounded border-0 bg-transparent p-0" /><span class="font-mono text-xs">{{ color.color_hex }}</span></span>
                                </label>
                                <label v-else class="space-y-1 text-xs font-semibold text-slate-600 dark:text-zinc-400">Color image
                                    <input type="file" accept="image/*" required @change="onReceivedColorImage($event, color)" class="block w-full rounded-xl border border-slate-200 bg-white text-xs text-slate-700 file:mr-2 file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:font-semibold file:text-indigo-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" />
                                    <span v-if="color.color_image" class="block truncate text-[10px] font-normal">{{ color.color_image.name }}</span>
                                </label>
                            </div>
                            <button type="button" @click="removeReceivedColor(index)" class="px-2 text-rose-600" aria-label="Remove color">×</button>
                        </div>
                        <button type="button" @click="addReceivedColor" class="text-sm font-medium text-indigo-600 hover:underline">+ Add another Pantone color</button>
                        <button type="button" @click="addReceivedDesign" class="ml-4 rounded-lg border border-indigo-200 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-300 dark:hover:bg-indigo-950">+ Add fabric design</button>
                        </div>
                    </div>
                    <footer class="flex shrink-0 justify-end gap-2 border-t border-slate-100 dark:border-zinc-800 p-4"><button type="button" @click="receiveSampleModal.show = false" class="rounded-xl border px-4 py-2 text-sm">Cancel</button><button type="button" @click="submitReceiveSample" :disabled="receiveSampleModal.submitting || !canSubmitReceivedColors" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Receive &amp; record colors</button></footer>
                </section>
            </div>
        </Teleport>

        <!-- ════════ REJECT INQUIRY ════════ -->
        <Teleport to="body">
                    <div v-if="deliverSampleModal.show" class="fixed inset-0 z-[130] flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="deliver-sample-title">
                        <form @submit.prevent="submitDeliverSample" class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-zinc-700 dark:bg-zinc-900">
                            <header class="border-b border-slate-100 p-5 dark:border-zinc-800"><h2 id="deliver-sample-title" class="text-lg font-bold text-slate-900 dark:text-white">Send fabric sample to the client</h2><p class="mt-1 text-sm text-slate-500">Record the courier and tracking reference for the physical swatch.</p></header>
                            <div class="space-y-4 overflow-y-auto p-5">
                                <div class="grid gap-3 rounded-xl border border-slate-200 p-4 text-sm dark:border-zinc-700">
                                    <p><span class="font-semibold">Fabric design:</span> {{ deliverSampleModal.sample?.sample_fabric_design || deliverSampleModal.sample?.fabric_name }}</p>
                                    <p v-if="deliverSampleModal.sample?.sample_yarn_type"><span class="font-semibold">Yarn type:</span> {{ deliverSampleModal.sample.sample_yarn_type }}</p>
                                    <p v-if="deliverSampleModal.sample?.gsm"><span class="font-semibold">GSM:</span> {{ deliverSampleModal.sample.gsm }}</p>
                            <img v-if="deliverSampleModal.sample?.sample_image_path && !String(deliverSampleModal.sample.sample_image_path).toLowerCase().endsWith('.pdf')" :src="`/storage/${deliverSampleModal.sample.sample_image_path}`" alt="Fabric swatch sample" class="max-h-52 w-full rounded-lg border border-slate-200 object-contain dark:border-zinc-700" />
                            <a v-else-if="deliverSampleModal.sample?.sample_image_path" :href="`/storage/${deliverSampleModal.sample.sample_image_path}`" target="_blank" class="font-semibold text-indigo-600 underline dark:text-indigo-300">Open sample PDF</a>
                                </div>
                                <label class="block space-y-1 text-sm font-semibold text-slate-700 dark:text-zinc-200">Courier<input v-model="deliverSampleModal.delivery_courier" required maxlength="120" placeholder="Courier or delivery service" class="block w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800" /></label>
                                <label class="block space-y-1 text-sm font-semibold text-slate-700 dark:text-zinc-200">Delivery tracking reference number<input v-model="deliverSampleModal.delivery_tracking_reference" required maxlength="160" placeholder="Tracking reference" class="block w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800" /></label>
                                <label class="block space-y-1 text-sm font-semibold text-slate-700 dark:text-zinc-200">Notes for the client <span class="font-normal text-slate-400">(optional)</span><textarea v-model="deliverSampleModal.delivery_notes" maxlength="1000" rows="3" placeholder="Delivery or swatch notes" class="block w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800"></textarea></label>
                            </div>
                            <footer class="flex justify-end gap-2 border-t border-slate-100 p-4 dark:border-zinc-800"><button type="button" @click="deliverSampleModal.show = false" class="rounded-xl border px-4 py-2 text-sm">Cancel</button><button type="submit" :disabled="deliverSampleModal.submitting" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Send to the Client</button></footer>
                        </form>
                    </div>
            <Transition name="modal">
                <div v-if="rejectModal.show" role="alertdialog" aria-modal="true" aria-labelledby="reject-title"
                    class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4 bg-slate-900/50 backdrop-blur-sm"
                    @click.self="rejectModal.show = false">
                    <div class="bg-white dark:bg-zinc-900 w-full sm:max-w-sm rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden border border-slate-200 dark:border-zinc-800">
                        <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-slate-100 dark:border-zinc-800">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-100 dark:bg-red-500/15 text-red-600 dark:text-red-300"><XCircle class="h-5 w-5" /></span>
                                <div>
                                    <h3 id="reject-title" class="text-base font-semibold text-slate-900 dark:text-white">Reject inquiry</h3>
                                    <p class="text-sm text-slate-500 dark:text-zinc-400">This conversation will be marked rejected and appear in the Rejected tab.</p>
                                </div>
                            </div>
                            <button @click="rejectModal.show = false" type="button" aria-label="Close" class="modal-close"><X class="h-4 w-4" /></button>
                        </div>
                        <form @submit.prevent="submitReject" class="p-5 space-y-4">
                            <div>
                                <label class="field-label" for="reject-reason">Reason <span class="text-red-500">*</span></label>
                                <textarea id="reject-reason" v-model="rejectModal.reason" rows="3" required class="field-input resize-none"
                                    placeholder="Tell the client why this inquiry is being rejected…"></textarea>
                            </div>
                            <div class="flex gap-3">
                                <button type="button" @click="rejectModal.show = false" :disabled="rejectSubmitting" class="btn-secondary flex-1">Cancel</button>
                                <button type="submit" :disabled="rejectSubmitting" class="btn-primary flex-1 !bg-red-600 hover:!bg-red-700 disabled:cursor-not-allowed disabled:opacity-60">
                                    <Loader2 v-if="rejectSubmitting" class="h-4 w-4 animate-spin" />
                                    <XCircle v-else class="h-4 w-4" />
                                    {{ rejectSubmitting ? 'Rejecting…' : 'Confirm rejection' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ════════ CREATE RECIPE ════════ -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="recipeModal.show" role="dialog" aria-modal="true" aria-labelledby="recipe-title"
                    class="fixed inset-0 z-[110] flex items-end sm:items-center justify-center sm:p-4 bg-slate-900/50 backdrop-blur-sm"
                    @click.self="recipeModal.show = false">
                    <div class="bg-white dark:bg-zinc-900 w-full sm:max-w-xl rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh] border border-slate-200 dark:border-zinc-800">
                        <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-slate-100 dark:border-zinc-800 flex-shrink-0">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white"><FlaskConical class="h-5 w-5" /></span>
                                <div>
                                    <h3 id="recipe-title" class="text-base font-semibold text-slate-900 dark:text-white">Create recipe</h3>
                                    <p class="text-sm text-slate-500 dark:text-zinc-400">{{ inquiry.client?.company_name }}</p>
                                </div>
                            </div>
                            <button @click="recipeModal.show = false" type="button" aria-label="Close" class="modal-close"><X class="h-4 w-4" /></button>
                        </div>

                        <form @submit.prevent="submitRecipe" class="flex flex-col flex-1 min-h-0">
                            <div class="p-5 overflow-y-auto flex-1 space-y-4">
                                <div>
                                    <label class="field-label">Business client</label>
                                    <input type="text" :value="inquiry.client?.company_name" disabled class="field-input !bg-slate-100 dark:!bg-zinc-800 cursor-not-allowed !text-slate-500" />
                                </div>
                                <div>
                                    <label class="field-label" for="recipe-product">Product <span class="text-red-500">*</span></label>
                                    <select id="recipe-product" v-model="recipeForm.product_id" required class="field-input">
                                        <option value="" disabled>Select product</option>
                                        <option v-for="p in availableProducts" :key="p.id" :value="p.id">{{ p.name }}</option>
                                    </select>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="field-label" for="recipe-yarn">Yarn type <span class="text-red-500">*</span></label>
                                        <input id="recipe-yarn" v-model="recipeForm.yarn_type" type="text" required placeholder="e.g. 50% Cotton 50% Silk" class="field-input" />
                                    </div>
                                    <div>
                                        <label class="field-label" for="recipe-dye">Dye color <span class="text-red-500">*</span></label>
                                        <input id="recipe-dye" v-model="recipeForm.dye_color" type="text" required placeholder="e.g. 30% White 70% Blue" class="field-input" />
                                    </div>
                                </div>
                                <div>
                                    <label class="field-label" for="recipe-weave">Weave design <span class="text-red-500">*</span></label>
                                    <input id="recipe-weave" v-model="recipeForm.weave_design" type="text" required placeholder="e.g. Twill 2/1" class="field-input" />
                                </div>
                                <div>
                                    <span class="field-label">Raw materials <span class="text-red-500">*</span></span>
                                    <div class="relative">
                                        <button type="button" @click="showMaterialsDropdown = !showMaterialsDropdown"
                                            :aria-expanded="showMaterialsDropdown"
                                            class="field-input text-left flex justify-between items-center">
                                            <span :class="selectedMaterialsCount ? 'text-slate-900 dark:text-white' : 'text-slate-400'">
                                                {{ selectedMaterialsCount ? `${selectedMaterialsCount} material(s) selected` : 'Select materials' }}
                                            </span>
                                            <ChevronDown class="h-4 w-4 text-slate-400 transition" :class="showMaterialsDropdown ? 'rotate-180' : ''" />
                                        </button>
                                        <div v-if="showMaterialsDropdown"
                                            class="absolute z-50 mt-1 w-full bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl shadow-2xl max-h-52 overflow-y-auto">
                                            <div class="p-1.5">
                                                <div v-for="mat in materials" :key="mat.id"
                                                    class="flex items-center gap-2.5 py-2 px-2.5 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 rounded-lg cursor-pointer transition"
                                                    @click="toggleMaterial(mat.id)">
                                                    <input type="checkbox" :checked="recipeForm.materials.includes(mat.id)"
                                                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 pointer-events-none" tabindex="-1" />
                                                    <span class="text-sm text-slate-700 dark:text-zinc-200">{{ mat.mat_id }} – {{ mat.display_name || mat.name }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="hint">Click a material to select or deselect it.</p>
                                </div>

                                <div v-if="recipeModal.error" role="alert"
                                    class="p-3 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-300 rounded-xl text-sm">
                                    {{ recipeModal.error }}
                                </div>
                            </div>

                            <div class="flex gap-3 px-5 py-4 border-t border-slate-100 dark:border-zinc-800 flex-shrink-0">
                                <button type="button" @click="recipeModal.show = false" class="btn-secondary">Cancel</button>
                                <button type="submit" :disabled="recipeModal.submitting" class="btn-primary flex-1">
                                    <Loader2 v-if="recipeModal.submitting" class="h-4 w-4 animate-spin" />
                                    {{ recipeModal.submitting ? 'Saving…' : 'Create recipe' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ════════ CREATE JOB ORDER ════════ -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="jobOrderModal.show" role="dialog" aria-modal="true" aria-labelledby="job-title"
                    class="fixed inset-0 z-[110] flex items-end sm:items-center justify-center sm:p-4 bg-slate-900/50 backdrop-blur-sm"
                    @click.self="jobOrderModal.show = false">
                    <div class="bg-white dark:bg-zinc-900 w-full sm:max-w-5xl rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh] border border-slate-200 dark:border-zinc-800">
                        <div class="flex items-start justify-between gap-3 px-5 sm:px-6 py-4 border-b border-slate-100 dark:border-zinc-800 flex-shrink-0">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-300"><ClipboardList class="h-5 w-5" /></span>
                                <div>
                                    <h3 id="job-title" class="text-base font-semibold text-slate-900 dark:text-white">Create job order</h3>
                                    <p class="text-sm text-slate-500 dark:text-zinc-400">
                                        {{ inquiry.client?.company_name }} · {{ totalRecipesCount }} recipe(s) available
                                    </p>
                                </div>
                            </div>
                            <button @click="jobOrderModal.show = false" type="button" aria-label="Close" class="modal-close"><X class="h-4 w-4" /></button>
                        </div>

                        <div class="flex flex-col lg:flex-row flex-1 overflow-hidden min-h-0">
                            <!-- Left: PO preview -->
                            <div class="w-full lg:w-5/12 border-b lg:border-b-0 lg:border-r border-slate-100 dark:border-zinc-800 p-4 overflow-auto bg-slate-50 dark:bg-zinc-950/40 max-h-56 lg:max-h-none">
                                <p class="text-sm font-semibold text-slate-700 dark:text-zinc-200 mb-3">Client purchase order</p>
                                <div v-if="jobOrderModal.attachment">
                                    <img v-if="jobOrderModal.attachment.file_type?.startsWith('image/')"
                                        :src="getFullUrl(jobOrderModal.attachment.file_path)" alt="Purchase order"
                                        class="max-w-full border border-slate-200 dark:border-zinc-700 rounded-xl shadow-sm" />
                                    <div v-else class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-zinc-700 flex flex-col items-center gap-3">
                                        <FileText class="h-10 w-10 text-slate-300 dark:text-zinc-600" />
                                        <a :href="getFullUrl(jobOrderModal.attachment.file_path)" target="_blank" rel="noopener"
                                            class="text-indigo-600 dark:text-indigo-400 text-sm font-medium hover:underline">Open PDF in a new tab</a>
                                    </div>
                                </div>
                                <div v-else class="p-8 rounded-xl border border-dashed border-slate-300 dark:border-zinc-700 text-center">
                                    <FileText class="h-8 w-8 text-slate-300 dark:text-zinc-600 mx-auto mb-2" />
                                    <p class="text-sm text-slate-500">No PO file attached — verify the details with the client in chat first.</p>
                                </div>
                            </div>

                            <!-- Right: form -->
                            <form @submit.prevent="submitJobOrder" class="w-full lg:w-7/12 flex flex-col min-h-0">
                                <div class="p-4 sm:p-5 overflow-y-auto flex-1 space-y-4">
                                    <div>
                                        <label class="field-label" for="po-number">PO number <span class="text-red-500">*</span></label>
                                        <input id="po-number" v-model="jobOrderForm.po_number" type="text" required placeholder="e.g. PO-2026-0001" class="field-input font-mono" />
                                        <p class="hint">Must match the purchase order shown on the left.</p>
                                    </div>

                                    <div v-if="(acceptedQuotations ?? []).length > 1">
                                        <label class="field-label" for="job-quote">Quotation for pricing <span class="text-red-500">*</span></label>
                                        <select id="job-quote" v-model="jobOrderQuotationId" required aria-label="Quotation" @change="onJobQuotationChange" class="field-input">
                                            <option v-for="q in acceptedQuotations" :key="q.id" :value="q.id">
                                                {{ q.quotation_number }} · {{ (q.items ?? []).length }} tier(s)
                                            </option>
                                        </select>
                                        <p class="hint">This conversation has {{ acceptedQuotations.length }} accepted quotations — prices follow the one you choose.</p>
                                    </div>
                                    <p v-else-if="(acceptedQuotations ?? []).length === 1"
                                        class="text-sm text-slate-600 dark:text-zinc-300 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl px-3 py-2.5">
                                        Pricing from <span class="font-mono font-medium">{{ acceptedQuotations[0].quotation_number }}</span> (accepted)
                                    </p>
                                    <p v-else class="text-sm text-amber-800 dark:text-amber-300 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 rounded-xl px-3 py-2.5">
                                        No accepted quotation on this conversation — enter prices manually.
                                    </p>

                                    <!-- Items -->
                                    <div v-for="(item, idx) in jobOrderForm.items" :key="idx"
                                        class="border border-slate-200 dark:border-zinc-800 rounded-2xl overflow-hidden">
                                        <div class="flex justify-between items-center px-4 py-2.5 bg-slate-50 dark:bg-zinc-800/60 border-b border-slate-200 dark:border-zinc-800">
                                            <span class="text-sm font-semibold text-slate-800 dark:text-zinc-100">Product {{ idx + 1 }}</span>
                                            <button v-if="jobOrderForm.items.length > 1" type="button" @click="removeJobOrderItem(idx)"
                                                class="text-xs font-medium text-red-500 hover:text-red-700 hover:underline">
                                                Remove
                                            </button>
                                        </div>

                                        <div class="p-4 space-y-3">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="field-label">Product <span class="text-red-500">*</span></label>
                                                    <select v-model="item.product_id" required aria-label="Product" @change="onJobProductChange(item)" class="field-input">
                                                        <option value="">Select product</option>
                                                        <option v-for="p in availableProducts" :key="p.id" :value="p.id">{{ p.name }}</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="field-label">Color tier <span class="text-red-500">*</span></label>
                                                    <select v-model="item.color" required aria-label="Color" @change="onJobColorChange(item)" class="field-input">
                                                        <option value="" disabled>Select color tier</option>
                                                        <option v-for="c in JOB_COLORS" :key="c" :value="c">{{ c }}</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div>
                                                <label class="field-label">Recipe <span class="font-normal text-slate-400">(optional)</span></label>
                                                <select v-model="item.recipe_id" class="field-input">
                                                    <option :value="null">
                                                        {{ !item.product_id
                                                            ? 'Select a product first'
                                                            : recipesForProduct(item.product_id).length === 0
                                                                ? 'No recipes found'
                                                                : 'Select recipe (optional)'
                                                        }}
                                                    </option>
                                                    <option v-for="r in recipesForProduct(item.product_id)" :key="r.id" :value="r.id">
                                                        <template v-if="r.is_lab_sample">🧬 {{ r.sample_code }} · </template>{{ r.yarn_type }} — {{ r.dye_color }}
                                                        <template v-if="r.weave_design"> ({{ r.weave_design }})</template>
                                                    </option>
                                                </select>
                                                <p v-if="item.product_id && recipesForProduct(item.product_id).some(r => r.is_lab_sample)"
                                                    class="hint !text-teal-600 dark:!text-teal-400 font-medium">
                                                    Lab recipe from this conversation was selected — change it if needed.
                                                </p>
                                                <p v-if="item.product_id && recipesForProduct(item.product_id).length > 0" class="hint !text-emerald-600 dark:!text-emerald-400">
                                                    {{ recipesForProduct(item.product_id).length }} recipe(s) available
                                                </p>
                                                <p v-else-if="item.product_id && recipesForProduct(item.product_id).length === 0" class="hint !text-amber-600">
                                                    No recipe yet — create one with “Create recipe” first.
                                                </p>
                                            </div>

                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label class="field-label">Quantity (kg) <span class="text-red-500">*</span></label>
                                                    <input v-model.number="item.kilos" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00" required class="field-input" />
                                                </div>
                                                <div>
                                                    <label class="field-label">Price per kg (₱) <span class="text-red-500">*</span></label>
                                                    <input v-model.number="item.unit_price" type="number" min="0" step="0.01" inputmode="decimal"
                                                        placeholder="0.00" required :readonly="item.auto_price"
                                                        :class="item.auto_price ? '!bg-indigo-50 dark:!bg-indigo-500/10 cursor-not-allowed' : ''"
                                                        class="field-input" />
                                                </div>
                                            </div>
                                            <p v-if="item.auto_price" class="hint !text-emerald-600 dark:!text-emerald-400 font-medium">
                                                Priced from {{ selectedJobQuotation?.quotation_number ?? 'the accepted quotation' }} ({{ item.color }}).
                                            </p>
                                            <p v-else-if="!selectedJobQuotation" class="hint !text-amber-600">
                                                No accepted quotation on this conversation — enter the price manually.
                                            </p>
                                            <p v-else-if="item.product_id && item.color" class="hint !text-amber-600">
                                                No approved price for this product and color — enter it manually or check the quotation tiers.
                                            </p>

                                            <div>
                                                <label class="field-label">Description <span class="font-normal text-slate-400">(optional)</span></label>
                                                <textarea v-model="item.description" placeholder="e.g. Special finishing, packaging notes…" rows="2" class="field-input resize-none"></textarea>
                                            </div>

                                            <div class="flex justify-end">
                                                <span class="text-sm text-slate-500 dark:text-zinc-400">
                                                    Line total
                                                    <span class="ml-1 text-sm font-semibold text-slate-900 dark:text-white tabular-nums">₱{{ ((item.kilos || 0) * (item.unit_price || 0)).toFixed(2) }}</span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="button" @click="addJobOrderItem"
                                        class="w-full py-2.5 border-2 border-dashed border-slate-300 dark:border-zinc-700 text-indigo-600 dark:text-indigo-400 rounded-xl text-sm font-medium hover:bg-indigo-50 dark:hover:bg-indigo-500/10 hover:border-indigo-300 transition flex items-center justify-center gap-2">
                                        <Plus class="h-4 w-4" /> Add product
                                    </button>

                                    <div v-if="jobOrderModal.error" role="alert"
                                        class="p-3 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-300 rounded-xl text-sm">
                                        {{ jobOrderModal.error }}
                                    </div>
                                </div>

                                <!-- Sticky footer with live total -->
                                <div class="flex-shrink-0 border-t border-slate-100 dark:border-zinc-800 px-4 sm:px-5 py-3.5 bg-white dark:bg-zinc-900 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-slate-500 dark:text-zinc-400">Order total · {{ jobOrderForm.items.length }} item(s)</span>
                                        <span class="text-lg font-semibold text-slate-900 dark:text-white tabular-nums">₱{{ jobOrderGrandTotal }}</span>
                                    </div>
                                    <div class="flex gap-3">
                                        <button type="button" @click="jobOrderModal.show = false" class="btn-secondary">Cancel</button>
                                        <button type="submit" :disabled="jobOrderModal.submitting" class="btn-primary flex-1 !bg-amber-500 hover:!bg-amber-600">
                                            <Loader2 v-if="jobOrderModal.submitting" class="h-4 w-4 animate-spin" />
                                            {{ jobOrderModal.submitting ? 'Creating…' : 'Create job order' }}
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
        <FabricInquiryNotice
            v-model="showQuotationSuccess"
            title="Quotation sent successfully!"
            message="The quotation has been sent to the business client."
        />
        <FabricInquiryNotice
            v-model="showMeetingSuccess"
            title="Invite sent successfully!"
            message="The meeting invitation has been sent to the business client."
        />
        <FabricInquiryNotice
            v-model="showRecipesCreated"
            title="Recipe request sent successfully!"
            message="Your selected color and yarn combinations have been sent to the Dyeing Lab for processing."
        />
        <TemplateWidget page-key="inquiries" />
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TemplateWidget from '@/Components/crm/TemplateWidget.vue';
import FabricInquiryNotice from '@/Components/FabricInquiryNotice.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, nextTick, onMounted, onBeforeUnmount, watch } from 'vue';
import { usePageAccess } from '@/composables/usePageAccess';
import { useRealtimeConversation } from '@/composables/useRealtimeConversation';
import { useSidebarToggle } from '@/Layouts/composables/useSidebarToggle';
import { confirmModal, holdSuccessFeedback } from '@/lib/feedback';
import {
    ArrowLeft, Send, Loader2, FileText, X, Package, Trash2,
    Plus, Paperclip, ClipboardList, XCircle, Calendar, ChevronDown, ChevronLeft, ChevronRight, FlaskConical
} from 'lucide-vue-next';

const { canEdit } = usePageAccess();
const canEditInquiry = computed(() => canEdit('CRM', 'inquiry'));

const props = defineProps({
    inquiry:        { type: Object,  required: true },
    quotations:     { type: Array,   default: () => [] },
    approvedQuotation: { type: Object, default: null },
    acceptedQuotations: { type: Array, default: () => [] },
    parsedProducts: { type: Array,   default: () => [] },
    acceptedFabricOptions: { type: Array, default: () => [] },
    allProducts:    { type: Array,   default: () => [] },
    recipes:        { type: Array,   default: () => [] },
    sampleRecipes:  { type: Array,   default: () => [] },
    materials:      { type: Array,   default: () => [] },
    quotationFabrics: { type: Array, default: () => [] },
    selectionPricing: { type: Object, default: () => ({}) },
    selectedProductMinimumOrderQuantity: { type: [Number, String], default: null },
    clientPurchaseOrders: { type: Array, default: () => [] },
});

// Mobile panel toggle
const mobilePanel = ref('chat');

// Keep the whole page locked to the viewport so the reply box is always
// visible. The page itself is fixed below the top nav; only its height is
// tracked so the mobile keyboard never hides the composer.
const { sidebarCollapsed } = useSidebarToggle();
const pageRef = ref(null);
const pageHeight = ref(0);
const pageStyle = computed(() => ({
    height: pageHeight.value > 0 ? `${pageHeight.value}px` : 'calc(100dvh - 4rem)',
}));
const measurePage = () => {
    const el = pageRef.value;
    if (!el) return;
    const top = el.getBoundingClientRect().top;
    const viewport = window.visualViewport?.height ?? window.innerHeight;
    pageHeight.value = Math.max(360, Math.floor(viewport - top));
};
let previousBodyOverflow = '';
const lockPageScroll = () => {
    previousBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
};
const unlockPageScroll = () => {
    document.body.style.overflow = previousBodyOverflow;
};

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

const productImages = computed(() => (props.inquiry.product?.images ?? []).filter(image => image?.url));
const productImageIndex = ref(0);
const currentProductImage = computed(() => productImages.value[productImageIndex.value] ?? null);
const showPreviousProductImage = () => {
    productImageIndex.value = (productImageIndex.value - 1 + productImages.value.length) % productImages.value.length;
};
const showNextProductImage = () => {
    productImageIndex.value = (productImageIndex.value + 1) % productImages.value.length;
};
watch(productImages, () => { productImageIndex.value = 0; });

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

const formatStatus = (s) => {
    if (!s) return 'Open';
    const t = String(s).replace(/_/g, ' ').toLowerCase();
    return t.charAt(0).toUpperCase() + t.slice(1);
};
const formatTime   = (d) => new Date(d).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
const formatPeso   = (v) => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const objectUrl    = (f) => URL.createObjectURL(f);

const getFullUrl = (path) => {
    if (!path) return '#';
    if (path.startsWith('http')) return path;
    const cleanPath = path.replace(/^\/?storage\//, '');
    return `/storage/${cleanPath}`;
};

// Header status pill (light + dark)
const statusPill = (s) => {
    const map = {
        open:           { pill: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:border-blue-500/20', dot: 'bg-blue-500' },
        quotation_sent: { pill: 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-300 dark:border-indigo-500/20', dot: 'bg-indigo-500' },
        converted:      { pill: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/20', dot: 'bg-emerald-500' },
        rejected:       { pill: 'bg-red-50 text-red-700 border-red-200 dark:bg-red-500/10 dark:text-red-300 dark:border-red-500/20', dot: 'bg-red-500' },
    };
    return map[s] || { pill: 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-700', dot: 'bg-slate-400' };
};

// Quotation card accent by status
const quoteTone = (s) => {
    const map = {
        accepted: { border: 'border-l-emerald-500', badge: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' },
        rejected: { border: 'border-l-red-500',     badge: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' },
        trashed:  { border: 'border-l-slate-400',   badge: 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300' },
    };
    return map[s] || { border: 'border-l-indigo-500', badge: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' };
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
        const key = item.fabric_type || item.fabric;
        (acc[key] = acc[key] || []).push(item);
        return acc;
    }, {});

const quotationPriceTiers = [
    { key: 'price_white', label: 'White', dot: 'border-slate-300 bg-white' },
    { key: 'price_light', label: 'Light colors', dot: 'border-amber-300 bg-amber-200' },
    { key: 'price_dark', label: 'Dark colors', dot: 'border-gray-600 bg-gray-700' },
];
const quotationFabricName = (id) => props.quotationFabrics.find((fabric) => Number(fabric.id) === Number(id))?.name || 'Fabric type';
const productSelections = computed(() => props.inquiry.product_selections || {});
const hasClientProductSelections = computed(() => Boolean(
    productSelections.value.pantones?.length && productSelections.value.yarns?.length
));
const creatingRecipes = ref(false);
const showRecipesCreated = ref(false);
const createSelectedRecipes = async () => {
    if (creatingRecipes.value) return;
    const confirmed = await confirmModal(
        'Create recipes for all selected Pantone colors and yarn combinations and send them to the Dyeing Lab?',
        { title: 'Create recipes?', confirmButtonText: 'Create recipes' },
    );
    if (!confirmed) return;

    creatingRecipes.value = true;
    router.post(route('crm.inquiry.create-recipes', props.inquiry.hash_key), {}, {
        preserveScroll: true,
        onSuccess: () => { showRecipesCreated.value = true; },
        onFinish: () => { creatingRecipes.value = false; },
    });
};
const pushingPurchaseOrderId = ref(null);
const savingRecipePoId = ref(null);
const copyClientPurchaseOrders = (orders) => (orders ?? []).map((po) => ({ ...po, items: (po.items ?? []).map((item) => ({ ...item })) }));
const clientPoReviewState = ref(copyClientPurchaseOrders(props.clientPurchaseOrders));
watch(() => props.clientPurchaseOrders, (orders) => { clientPoReviewState.value = copyClientPurchaseOrders(orders); }, { deep: true });
const poRecipesPersisted = (purchaseOrder) => purchaseOrder.items.length > 0 && purchaseOrder.items.every((item) =>
    item.recipe_id && Number(item.recipe_id) === Number(item.saved_recipe_id)
);
const saveClientPoRecipes = (purchaseOrder) => {
    if (savingRecipePoId.value || purchaseOrder.items.some((item) => !item.recipe_id)) return;
    savingRecipePoId.value = purchaseOrder.id;
    router.post(route('crm.inquiry.client-po-recipes', { inquiry: props.inquiry.hash_key, purchaseOrder: purchaseOrder.id }), {
        items: purchaseOrder.items.map((item) => ({ purchase_order_item_id: item.id, recipe_id: Number(item.recipe_id) })),
    }, {
        preserveScroll: true,
        onFinish: () => { savingRecipePoId.value = null; },
    });
};
const pushClientPurchaseOrder = (purchaseOrder) => {
    if (pushingPurchaseOrderId.value || !poRecipesPersisted(purchaseOrder)) return;
    pushingPurchaseOrderId.value = purchaseOrder.id;
    router.post(route('crm.inquiry.push-client-po', { inquiry: props.inquiry.hash_key, purchaseOrder: purchaseOrder.id }), {}, {
        preserveScroll: true,
        onFinish: () => { pushingPurchaseOrderId.value = null; },
    });
};
const colorCategoryLabel = (category) => ({ white: 'White color', light: 'Light color', dark: 'Dark color' }[category] || 'Color');
const toggleQuotationFabricType = (item, fabricType, checked) => {
    const id = Number(fabricType.id);
    const index = item.fabric_type_ids.indexOf(id);
    if (checked && index < 0) {
        item.fabric_type_ids.push(id);
        item.fabric_prices[id] = { price_white: '', price_light: '', price_dark: '' };
    } else if (!checked && index >= 0) {
        item.fabric_type_ids.splice(index, 1);
        delete item.fabric_prices[id];
    }
};

const makeItem = (name = '', isDefault = false, productId = null, kilos = 0) => ({
    product_id:  productId,
    fabric:      name,
    design:      '',
    kilos,
    fabric_type_ids: [],
    fabric_prices: {},
    isDefault,
});

const minimumForProduct = (product) => Number(
    product?.minimum_order_quantity
    ?? props.selectedProductMinimumOrderQuantity
    ?? props.inquiry.selected_product_minimum_order_quantity
    ?? props.inquiry.product?.minimum_order_quantity
    ?? 0
);
const defaultProductItems = () => {
    if (props.parsedProducts?.length) {
        return props.parsedProducts.map((product) => makeItem(product.name, true, product.id, minimumForProduct(product)));
    }

    const product = props.inquiry.product;
    if (product?.id || props.inquiry.product_id) {
        return [makeItem(product?.name || '', true, product?.id || props.inquiry.product_id, minimumForProduct(product))];
    }

    return [makeItem('', true)];
};

const form = ref({
    vat_type:      'exclusive',
    payment_terms: '',
    notes:         '',
    items: defaultProductItems(),
});

const submitting = ref(false);
const showQuotationModal = ref(false);
const showQuotationSuccess = ref(false);
const showMeetingSuccess = ref(false);
const selectedFabricTypeCount = computed(() => form.value.items.reduce((total, item) => total + item.fabric_type_ids.length, 0));
const minimumOrderFor = (item) => {
    const parsedProduct = (props.parsedProducts ?? []).find((product) => Number(product.id) === Number(item.product_id));
    return minimumForProduct(parsedProduct || props.inquiry.product);
};

const selectedProductQuoteItems = () => {
    const selections = productSelections.value;
    const productId = Number(selections.product_id || props.inquiry.product_id);
    const productName = props.inquiry.product?.name || '';

    return (selections.pantones || []).flatMap((pantone) => (selections.yarns || []).map((yarn) => {
        const priceKey = `${yarn.id}:${pantone.color_category}`;
        const presetPrice = Object.prototype.hasOwnProperty.call(props.selectionPricing, priceKey)
            ? props.selectionPricing[priceKey]
            : null;
        return {
            product_id: productId,
            fabric: productName,
            design: '',
            kilos: Number(props.selectedProductMinimumOrderQuantity ?? props.inquiry.selected_product_minimum_order_quantity ?? props.inquiry.product?.minimum_order_quantity ?? 0),
            pantone_id: Number(pantone.id),
            pantone_name: pantone.name,
            pantone_hex: pantone.hex,
            color_category: pantone.color_category,
            color: pantone.name,
            fabric_type_id: Number(yarn.id),
            fabric_type_name: yarn.name,
            unit_price: presetPrice,
            default_unit_price: presetPrice,
            fabric_type_ids: [],
            fabric_prices: {},
            isDefault: true,
        };
    }));
};

const openQuotationModal = () => {
    form.value.items = hasClientProductSelections.value
        ? selectedProductQuoteItems()
        : defaultProductItems();
    form.value.payment_terms = '';
    form.value.notes = '';
    form.value.vat_type = 'exclusive';
    showQuotationModal.value = true;
};

const addItem = () => {
    form.value.items.push(makeItem('', false, null, minimumForProduct(null)));
};

const removeItem = (idx) => {
    if (!form.value.items[idx].isDefault) {
        form.value.items.splice(idx, 1);
    }
};

const submitQuotation = () => {
    submitting.value = true;
    const items = hasClientProductSelections.value
        ? form.value.items.map((item) => ({
            product_id: item.product_id,
            fabric: item.fabric,
            design: item.design,
            kilos: item.kilos,
            fabric_type_id: item.fabric_type_id,
            pantone_id: item.pantone_id,
            color: item.pantone_name,
            unit_price: item.unit_price,
        }))
        : form.value.items.flatMap((item) => item.fabric_type_ids.map((fabricTypeId) => ({
            product_id: item.product_id,
            fabric: item.fabric,
            design: item.design,
            kilos: item.kilos,
            fabric_type_id: fabricTypeId,
            ...item.fabric_prices[fabricTypeId],
        })));

    router.post(route('crm.inquiry.quotation', props.inquiry.hash_key), { ...form.value, items }, {
        onSuccess: () => {
            showQuotationModal.value = false;
            showQuotationSuccess.value = true;
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
const liveSampleRequests = ref([...(props.inquiry?.sample_requests ?? [])]);
const labDeliveryStatuses = ['requested', 'in_progress', 'awaiting_crm_review', 'crm_adjustment_review', 'forwarded', 'adjustment_requested'];
const labDeliverySamples = computed(() => liveSampleRequests.value
    .filter((sample) => labDeliveryStatuses.includes(sample.status))
    .sort((a, b) => Number(a.id) - Number(b.id)));
const readyToDeliverSamples = computed(() => labDeliverySamples.value.filter((sample) => sample.status === 'awaiting_crm_review'));
const displaySampleRequests = computed(() => {
    const samples = liveSampleRequests.value.filter((sample) => ![...labDeliveryStatuses, 'approved', 'cancelled'].includes(sample.status));
    const groupedByReference = new Map();
    samples.forEach((sample) => {
        if (sample.status === 'awaiting_receipt' && sample.request_method === 'image' && sample.client_reference_path && !sample.parent_id) {
            const key = sample.client_reference_path;
            if (!groupedByReference.has(key)) groupedByReference.set(key, []);
            groupedByReference.get(key).push(sample);
        }
    });
    const groupedIds = new Set();
    const groupedSamples = [];
    groupedByReference.forEach((group) => {
        if (group.length < 2) return;
        group.forEach((sample) => groupedIds.add(Number(sample.id)));
        groupedSamples.push({ ...group[0], grouped_samples: group, grouped_fabric_names: group.map((sample) => sample.fabric_name) });
    });
    return [
        ...groupedSamples,
        ...samples.filter((sample) => !groupedIds.has(Number(sample.id))),
    ].sort((a, b) => Number(a.id) - Number(b.id));
});

watch(() => props.inquiry?.sample_requests, (samples) => {
    if (Array.isArray(samples)) liveSampleRequests.value = [...samples];
}, { deep: true, immediate: true });

const scrollToBottom = () =>
    nextTick(() => {
        if (messagesContainer.value)
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    });

const mergeMessages = (incoming) => {
    const known = new Set(liveMessages.value.map((m) => Number(m.id)));
    let added = false;
    let sampleRequestAdded = false;
    for (const m of incoming) {
        if (!known.has(Number(m.id))) {
            liveMessages.value.push(m);
            known.add(Number(m.id));
            added = true;
            if (m.sample_request) {
                const existingIndex = liveSampleRequests.value.findIndex((sample) => Number(sample.id) === Number(m.sample_request.id));
                if (existingIndex === -1) {
                    liveSampleRequests.value.unshift(m.sample_request);
                } else {
                    liveSampleRequests.value[existingIndex] = { ...liveSampleRequests.value[existingIndex], ...m.sample_request };
                }
                sampleRequestAdded = true;
            }
        }
    }
    if (added) {
        liveMessages.value.sort((a, b) => Number(a.id) - Number(b.id));
        scrollToBottom();
    }
    if (sampleRequestAdded) {
        // Refresh the relationship list too; a single system message can link
        // only the first row when the client requested several fabric designs.
        router.reload({ only: ['inquiry'], preserveScroll: true, preserveState: true });
    }
};

// Day dividers in the conversation
const dayKey = (d) => new Date(d).toDateString();
const isNewDay = (i) =>
    i === 0 || dayKey(liveMessages.value[i]?.created_at) !== dayKey(liveMessages.value[i - 1]?.created_at);
const dayLabel = (d) => {
    const date = new Date(d);
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);
    if (date.toDateString() === today.toDateString()) return 'Today';
    if (date.toDateString() === yesterday.toDateString()) return 'Yesterday';
    return date.toLocaleDateString([], {
        month: 'short',
        day: 'numeric',
        year: date.getFullYear() !== today.getFullYear() ? 'numeric' : undefined,
    });
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
    suppressSuccessFeedback: true,
    onNewMessages: () => scrollToBottom(),
});
const sending = rtSending;

const sendMessage = async () => {
    if (!newMessage.value.trim() && !selectedFiles.value.length) return;
    const text = newMessage.value;
    const files = [...selectedFiles.value];
    newMessage.value = '';
    selectedFiles.value = [];
    const releaseSuccessFeedback = isBulkInquiry.value ? holdSuccessFeedback() : null;
    try {
        const created = await sendRealtime({ text, files });
        if (!created) {
            // Restore on failure so the user doesn't lose their draft.
            newMessage.value = text;
            selectedFiles.value = files;
        }
    } finally {
        // The app's generic mutation feedback is delayed briefly after the POST.
        if (releaseSuccessFeedback) window.setTimeout(releaseSuccessFeedback, 500);
    }
};

const showMeetingModal = ref(false);
const scheduling = ref(false);
const meetingDateError = ref('');
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
    meetingDateError.value = '';
    showMeetingModal.value = true;
};

const submitMeeting = () => {
    if (!meetingData.value.scheduled_at || new Date(meetingData.value.scheduled_at) <= new Date()) {
        meetingDateError.value = 'Choose a meeting date and time in the future.';
        return;
    }
    meetingDateError.value = '';
    scheduling.value = true;
    router.post(route('crm.inquiry.meeting', props.inquiry.hash_key), meetingData.value, {
        onSuccess: () => {
            showMeetingModal.value = false;
            showMeetingSuccess.value = true;
            scrollToBottom();
        },
        onFinish: () => { scheduling.value = false; },
    });
};

const rejectModal   = ref({ show: false, reason: '' });
const rejectSubmitting = ref(false);
const openRejectModal = () => { rejectModal.value = { show: true, reason: '' }; };
const submitReject  = () => {
    if (rejectSubmitting.value) return;
    rejectSubmitting.value = true;
    router.post(route('crm.inquiry.reject', props.inquiry.hash_key), { reason: rejectModal.value.reason }, {
        onSuccess: () => { rejectModal.value.show = false; },
        onFinish: () => { rejectSubmitting.value = false; },
    });
};

// ── Fabric sample loop (CRM → dyeing lab → forward to client) ──
const sampleStatusLabel = (s) => ({
    requested: 'Queued for Dyeing Lab',
    in_progress: 'Lab preparing sample',
    awaiting_crm_review: 'Ready for client delivery',
    forwarded: 'Sent to client',
    adjustment_requested: 'Awaiting lab reformulation',
    crm_adjustment_review: 'Awaiting lab reformulation',
}[s] || (s ? String(s).replace(/_/g, ' ') : ''));
const deliverSampleModal = ref({ show: false, sample: null, delivery_courier: '', delivery_tracking_reference: '', delivery_notes: '', submitting: false });
const openDeliverSample = (sample) => {
    deliverSampleModal.value = { show: true, sample, delivery_courier: '', delivery_tracking_reference: '', delivery_notes: '', submitting: false };
};
const submitDeliverSample = () => {
    const modal = deliverSampleModal.value;
    if (!modal.sample || modal.submitting || !modal.delivery_courier.trim() || !modal.delivery_tracking_reference.trim()) return;
    modal.submitting = true;
    router.post(route('crm.inquiry.sample.deliver', { inquiry: props.inquiry.hash_key, sampleRequest: modal.sample.id }), {
        delivery_courier: modal.delivery_courier.trim(),
        delivery_tracking_reference: modal.delivery_tracking_reference.trim(),
        delivery_notes: modal.delivery_notes.trim() || null,
    }, {
        preserveScroll: true,
        onSuccess: () => { deliverSampleModal.value.show = false; },
        onFinish: () => { deliverSampleModal.value.submitting = false; },
    });
};
const bulkDeliverModal = ref({ show: false, sample_request_ids: [], delivery_courier: '', delivery_tracking_reference: '', delivery_notes: '', submitting: false });
const openBulkDeliverSamples = () => {
    bulkDeliverModal.value = { show: true, sample_request_ids: [], delivery_courier: '', delivery_tracking_reference: '', delivery_notes: '', submitting: false };
};
const submitBulkDeliverSamples = () => {
    const modal = bulkDeliverModal.value;
    if (modal.submitting || modal.sample_request_ids.length < 2 || !modal.delivery_courier.trim() || !modal.delivery_tracking_reference.trim()) return;
    modal.submitting = true;
    router.post(route('crm.inquiry.samples.deliver-bulk', { inquiry: props.inquiry.hash_key }), {
        sample_request_ids: modal.sample_request_ids,
        delivery_courier: modal.delivery_courier.trim(),
        delivery_tracking_reference: modal.delivery_tracking_reference.trim(),
        delivery_notes: modal.delivery_notes.trim() || null,
    }, {
        preserveScroll: true,
        onSuccess: () => { bulkDeliverModal.value.show = false; },
        onFinish: () => { bulkDeliverModal.value.submitting = false; },
    });
};

const makeReceivedColor = (fabricName = '') => ({ fabric_name: fabricName, pantone_code: '', pantone_color: '', texture: '', gsm: '', reference_type: 'hex', color_hex: '#808080', color_image: null });
const receiveSampleModal = ref({ show: false, sample: null, colors: [makeReceivedColor()], submitting: false });
const acceptedFabricDesignNames = computed(() => props.acceptedFabricOptions.map((option) =>
    option.fabric_label || [option.yarn_type ? `Yarn: ${option.yarn_type}` : '', option.fabric, option.design, option.color]
        .filter((part) => String(part ?? '').trim())
        .map((part) => String(part).trim())
        .filter((part, index, parts) => parts.findIndex((candidate) => candidate.toLowerCase() === part.toLowerCase()) === index)
        .join(' · ')
).filter((name, index, names) => name && names.indexOf(name) === index));
const receiveSampleReferenceKind = computed(() => {
    const path = String(receiveSampleModal.value.sample?.client_reference_path ?? '').toLowerCase();
    if (path.endsWith('.pdf')) return 'pdf';
    if (/\.(jpe?g|png|webp|gif|bmp|avif)$/.test(path)) return 'image';
    return null;
});
const openReceiveSample = (sample) => {
    const designs = sample.grouped_samples?.length ? sample.grouped_samples : [sample];
    receiveSampleModal.value = { show: true, sample, colors: designs.map((design) => makeReceivedColor(design.fabric_name)), submitting: false };
};
const addReceivedColor = () => receiveSampleModal.value.colors.push(makeReceivedColor(receiveSampleModal.value.sample?.fabric_name ?? ''));
const addReceivedDesign = () => receiveSampleModal.value.colors.push(makeReceivedColor(acceptedFabricDesignNames.value[0] ?? ''));
const removeReceivedColor = (index) => { if (receiveSampleModal.value.colors.length > 1) receiveSampleModal.value.colors.splice(index, 1); };
const onReceivedColorImage = (event, color) => { color.color_image = event.target.files?.[0] ?? null; };
const canSubmitReceivedColors = computed(() => receiveSampleModal.value.colors.length > 0 && receiveSampleModal.value.colors.every((color) =>
    color.fabric_name.trim()
    && color.pantone_code.trim()
    && color.pantone_color.trim()
    && color.texture
    && Number.isInteger(Number(color.gsm))
    && Number(color.gsm) >= 1
    && Number(color.gsm) <= 10000
    && (color.reference_type === 'hex'
        ? /^#[0-9a-f]{6}$/i.test(color.color_hex)
        : color.reference_type === 'image' && !!color.color_image)
));
const submitReceiveSample = () => {
    if (!receiveSampleModal.value.sample || receiveSampleModal.value.submitting || !canSubmitReceivedColors.value) return;
    receiveSampleModal.value.submitting = true;
    router.post(route('crm.inquiry.sample.receive', [props.inquiry.hash_key, receiveSampleModal.value.sample.id]), { colors: receiveSampleModal.value.colors }, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { receiveSampleModal.value.show = false; },
        onFinish: () => { receiveSampleModal.value.submitting = false; },
    });
};
const passSampleToLab = (sample) => router.post(route('crm.inquiry.sample.pass-to-lab', [props.inquiry.hash_key, sample.id]), {}, { preserveScroll: true });

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

// Escape closes the top-most open modal
const closeTopModal = (e) => {
    if (e.key !== 'Escape') return;
    if (imagePreviewModal.value.show) imagePreviewModal.value.show = false;
    else if (recipeModal.value.show) recipeModal.value.show = false;
    else if (jobOrderModal.value.show) jobOrderModal.value.show = false;
    else if (showQuotationModal.value) showQuotationModal.value = false;
    else if (showMeetingModal.value) showMeetingModal.value = false;
    else if (rejectModal.value.show) rejectModal.value.show = false;
};

onMounted(() => {
    scrollToBottom();
    window.addEventListener('keydown', closeTopModal);
    lockPageScroll();
    window.scrollTo(0, 0);
    nextTick(measurePage);
    window.addEventListener('resize', measurePage);
    window.visualViewport?.addEventListener('resize', measurePage);
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', closeTopModal);
    window.removeEventListener('resize', measurePage);
    window.visualViewport?.removeEventListener('resize', measurePage);
    unlockPageScroll();
});
</script>

<style scoped>
/* ── Shared form + button primitives (plain CSS so no Tailwind @apply is needed) ── */
.field-label {
    display: block;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 0.375rem;
}
.field-input {
    width: 100%;
    border-radius: 0.75rem;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    padding: 0.625rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1.25rem;
    color: #0f172a;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.field-input::placeholder { color: #94a3b8; }
.field-input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18);
}
.field-input:disabled { opacity: 0.7; }
.hint {
    font-size: 0.75rem;
    color: #64748b;
    margin-top: 0.375rem;
}
.tier-box {
    display: block;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 0.75rem;
    padding: 0.625rem;
}

.btn-primary,
.btn-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border-radius: 0.75rem;
    padding: 0.625rem 1rem;
    font-size: 0.875rem;
    font-weight: 600;
    transition: background-color 0.15s ease, transform 0.1s ease, opacity 0.15s ease;
}
.btn-primary { background: #4f46e5; color: #fff; }
.btn-primary:hover:not(:disabled) { background: #4338ca; }
.btn-primary:disabled { opacity: 0.45; cursor: not-allowed; }
.btn-primary:active:not(:disabled), .btn-secondary:active { transform: scale(0.98); }
.btn-secondary { background: #fff; color: #475569; border: 1px solid #e2e8f0; }
.btn-secondary:hover { background: #f8fafc; }

.modal-close {
    padding: 0.5rem;
    border-radius: 0.625rem;
    color: #94a3b8;
    transition: background-color 0.15s ease, color 0.15s ease;
    flex-shrink: 0;
}
.modal-close:hover { background: #f1f5f9; color: #334155; }

/* ── Dark mode (class strategy) ── */
.dark .field-label { color: #cbd5e1; }
.dark .field-input { background: #27272a; border-color: #3f3f46; color: #f4f4f5; }
.dark .field-input::placeholder { color: #71717a; }
.dark .field-input:focus { border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.25); }
.dark .hint { color: #a1a1aa; }
.dark .tier-box { background: rgba(39, 39, 42, 0.6); border-color: #3f3f46; }
.dark .btn-secondary { background: #27272a; color: #d4d4d8; border-color: #3f3f46; }
.dark .btn-secondary:hover { background: #3f3f46; }
.dark .modal-close:hover { background: #27272a; color: #e4e4e7; }

/* ── Accessibility ── */
button:focus-visible,
a:focus-visible,
[role="tab"]:focus-visible {
    outline: 2px solid #6366f1;
    outline-offset: 2px;
}

/* ── Motion: only where it answers an action ── */
.card-enter-active { transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.22, 1, 0.36, 1); }
.card-enter-from { opacity: 0; transform: translateY(8px); }
.card-leave-active { transition: opacity 0.2s ease; }
.card-leave-to { opacity: 0; }
.card-move { transition: transform 0.3s ease; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-active > div, .modal-leave-active > div { transition: transform 0.25s cubic-bezier(0.22, 1, 0.36, 1); }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from > div, .modal-leave-to > div { transform: translateY(12px) scale(0.98); }
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { scrollbar-width: none; }

@media (prefers-reduced-motion: reduce) {
    .card-enter-active, .card-leave-active, .card-move,
    .modal-enter-active, .modal-leave-active,
    .modal-enter-active > div, .modal-leave-active > div { transition: none; }
    .animate-bounce, .animate-spin { animation: none; }
}
</style>
