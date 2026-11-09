<template>
    <Head :title="`Conversation: ${inquiry.product?.name || 'Bulk Inquiry'}`" />
    <AuthenticatedLayout>
        <div class="flex h-full w-full flex-col overflow-hidden gap-2 sm:gap-3">

            <!-- ── Header ──────────────────────────────────────────── -->
            <header class="flex-shrink-0 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-sm px-4 sm:px-5 py-3.5">
                <div class="flex items-center gap-3">
                    <Link :href="route('client.conversations')" aria-label="Back to conversations"
                        class="p-2 rounded-xl border border-slate-200 dark:border-zinc-700 text-slate-500 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 hover:text-slate-800 dark:hover:text-white transition flex-shrink-0">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white font-semibold text-base flex-shrink-0" aria-hidden="true">
                        {{ (inquiry.product?.name ?? 'M').charAt(0) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 min-w-0">
                            <h1 class="text-base font-semibold text-slate-900 dark:text-white truncate leading-tight">
                                {{ inquiry.product?.name || 'Multiple Products' }}
                            </h1>
                            <span :class="statusPill(inquiry.status).pill"
                                class="flex-shrink-0 hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium border">
                                <span class="h-1.5 w-1.5 rounded-full" :class="statusPill(inquiry.status).dot" />
                                {{ formatStatus(inquiry.status) }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5 truncate">
                            <span v-if="inquiry.product?.sku" class="font-medium text-slate-700 dark:text-zinc-300">SKU {{ inquiry.product.sku }}</span>
                            <span v-if="inquiry.product?.sku"> · </span>
                            <span>Monti Textile team</span>
                        </p>
                    </div>
                </div>
            </header>

            <!-- ── Mobile tab bar ──────────────────────────────────── -->
            <div class="flex lg:hidden rounded-xl bg-slate-200/60 dark:bg-zinc-900 p-1 flex-shrink-0" role="tablist">
                <button @click="mobilePanel = 'chat'" role="tab" :aria-selected="mobilePanel === 'chat'" type="button"
                    :class="mobilePanel === 'chat' ? 'bg-white dark:bg-zinc-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-zinc-400'"
                    class="flex-1 py-2 rounded-lg text-sm font-medium transition">
                    Conversation
                </button>
                <button @click="mobilePanel = 'proposals'" role="tab" :aria-selected="mobilePanel === 'proposals'" type="button"
                    :class="mobilePanel === 'proposals' ? 'bg-white dark:bg-zinc-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-zinc-400'"
                    class="flex-1 py-2 rounded-lg text-sm font-medium transition">
                    Proposals
                    <span v-if="activeQuotations.length"
                        class="ml-1 inline-flex min-w-[18px] justify-center rounded-full bg-indigo-600 text-white text-[11px] px-1.5">{{ activeQuotations.length }}</span>
                </button>
            </div>

            <!-- ── Request fabric sample ───────────────────────────── -->
            <details v-if="fabricOptions.length" ref="sampleRequestDetails"
                class="group hidden flex-shrink-0 overflow-hidden rounded-2xl border border-emerald-200/70 dark:border-emerald-500/20 bg-white dark:bg-zinc-900 shadow-sm transition-shadow open:shadow-md">

                <!-- Collapsed bar -->
                <summary class="relative cursor-pointer select-none list-none [&::-webkit-details-marker]:hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-inset">
                    <div class="weave absolute inset-0 opacity-70 dark:opacity-30" aria-hidden="true" />
                    <div class="relative flex items-center gap-3.5 px-4 py-3.5">
                        <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-600/30">
                            <FlaskConical class="h-5 w-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-slate-900 dark:text-white">Request a fabric sample</span>
                            <span class="block truncate text-xs text-slate-600 dark:text-zinc-400">Pick a design, then show us the color you want matched</span>
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white transition group-open:bg-slate-100 group-open:text-slate-700 dark:group-open:bg-zinc-800 dark:group-open:text-zinc-200">
                            <span class="group-open:hidden">Start</span>
                            <span class="hidden group-open:inline">Close</span>
                            <ChevronDown class="h-3.5 w-3.5 transition-transform duration-200 group-open:rotate-180" />
                        </span>
                    </div>
                </summary>

                <form @submit.prevent="submitFabricSample" class="border-t border-slate-100 dark:border-zinc-800 p-4 sm:p-5 space-y-5">

                    <!-- Each quoted fabric design has its own Pantone color list. -->
                    <div class="space-y-3">
                        <div v-for="(fabricSet, fabricIndex) in sampleForm.fabric_designs" :key="fabricSet.key"
                            class="space-y-3 rounded-xl border border-slate-200 dark:border-zinc-700 p-3.5 sm:p-4">
                            <div class="flex items-center justify-between gap-3">
                                <label :for="`sample-fabric-${fabricSet.key}`" class="text-xs font-semibold text-slate-700 dark:text-zinc-300">
                                    Fabric design {{ fabricIndex + 1 }}
                                </label>
                                <button v-if="sampleForm.fabric_designs.length > 1" type="button" @click="removeSampleFabric(fabricIndex)"
                                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10">
                                    <X class="h-3.5 w-3.5" /> Remove
                                </button>
                            </div>
                            <div class="relative">
                                <Layers class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-emerald-600 dark:text-emerald-400" />
                                <select :id="`sample-fabric-${fabricSet.key}`" v-model="fabricSet.quotation_item_id" required
                                    class="w-full appearance-none rounded-xl border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 py-2.5 pl-10 pr-10 text-sm text-slate-800 dark:text-zinc-100 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/15 transition">
                                    <option value="" disabled>Select an approved fabric design</option>
                                    <option v-for="fabric in fabricOptions" :key="fabric.quotation_item_id" :value="fabric.quotation_item_id"
                                        :disabled="sampleForm.fabric_designs.some((set, index) => index !== fabricIndex && String(set.quotation_item_id) === String(fabric.quotation_item_id))">
                                        {{ fabric.fabric_label || [fabric.yarn_type ? `Yarn: ${fabric.yarn_type}` : '', fabric.fabric, fabric.design, fabric.color].filter(Boolean).join(' · ') }}
                                    </option>
                                </select>
                                <ChevronDown class="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            </div>
                            <p v-if="sampleForm.errors[`fabric_designs.${fabricIndex}.quotation_item_id`]" class="text-xs text-rose-600">
                                {{ sampleForm.errors[`fabric_designs.${fabricIndex}.quotation_item_id`] }}
                            </p>

                            <div v-if="sampleForm.request_method === 'pantone'" class="space-y-2 border-t border-slate-100 pt-3 dark:border-zinc-700">
                                <p class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Colors for this design</p>
                                <div v-for="(color, colorIndex) in fabricSet.colors" :key="colorIndex" class="space-y-2 rounded-lg bg-white/70 p-2.5 dark:bg-zinc-900/60">
                                    <div class="grid grid-cols-[1fr_1.3fr_auto] items-center gap-2">
                                        <input v-model="color.pantone_code" required placeholder="Code, e.g. 19-4052"
                                            class="min-w-0 rounded-xl border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-2.5 text-sm font-mono focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/15 transition" />
                                        <input v-model="color.pantone_color" required placeholder="Name, e.g. Classic Blue"
                                            class="min-w-0 rounded-xl border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-2.5 text-sm focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/15 transition" />
                                        <button type="button" @click="removeSampleColor(fabricIndex, colorIndex)"
                                            :disabled="fabricSet.colors.length === 1" aria-label="Remove color"
                                            class="rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 disabled:opacity-30 dark:hover:bg-rose-500/10">
                                            <X class="h-4 w-4" />
                                        </button>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <label class="space-y-1 text-[11px] font-semibold text-slate-600 dark:text-zinc-400">
                                            Texture
                                            <select v-model="color.texture" required class="block w-full rounded-xl border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-2 text-sm font-normal text-slate-800 dark:text-zinc-100 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/15">
                                                <option value="" disabled>Select texture</option>
                                                <option value="Hard">Hard</option>
                                                <option value="Medium">Medium</option>
                                                <option value="Soft">Soft</option>
                                            </select>
                                        </label>
                                        <label class="space-y-1 text-[11px] font-semibold text-slate-600 dark:text-zinc-400">
                                            GSM
                                            <input v-model.number="color.gsm" type="number" inputmode="numeric" min="1" max="10000" step="1" required placeholder="e.g. 180"
                                                class="block w-full rounded-xl border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-2 text-sm font-normal text-slate-800 dark:text-zinc-100 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/15" />
                                        </label>
                                    </div>
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)] sm:items-end">
                                        <label class="space-y-1 text-[11px] font-semibold text-slate-600 dark:text-zinc-400">
                                            Color reference
                                            <select v-model="color.reference_type" required class="block w-full rounded-xl border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-2 text-sm font-normal text-slate-800 dark:text-zinc-100 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/15">
                                                <option value="hex">Color hex</option>
                                                <option value="image">Upload image</option>
                                            </select>
                                        </label>
                                        <label v-if="color.reference_type === 'hex'" class="space-y-1 text-[11px] font-semibold text-slate-600 dark:text-zinc-400">
                                            Color
                                            <span class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white p-1.5 dark:border-zinc-700 dark:bg-zinc-800">
                                                <input v-model="color.color_hex" type="color" required aria-label="Choose color hex"
                                                    class="h-8 w-10 cursor-pointer rounded border-0 bg-transparent p-0" />
                                                <span class="font-mono text-xs uppercase">{{ color.color_hex }}</span>
                                            </span>
                                        </label>
                                        <label v-else class="space-y-1 text-[11px] font-semibold text-slate-600 dark:text-zinc-400">
                                            Color image
                                            <input type="file" accept="image/*" required @change="onSampleColorImage($event, color)"
                                                class="block w-full rounded-xl border border-slate-200 bg-white text-xs text-slate-700 file:mr-2 file:border-0 file:bg-emerald-50 file:px-3 file:py-2 file:font-semibold file:text-emerald-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:file:bg-emerald-500/10 dark:file:text-emerald-300" />
                                            <span v-if="color.color_image" class="block truncate text-[10px] font-normal">{{ color.color_image.name }}</span>
                                        </label>
                                    </div>
                                </div>
                                <button type="button" @click="addSampleColor(fabricIndex)"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-emerald-300 dark:border-emerald-500/40 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-300 transition hover:bg-emerald-50 dark:hover:bg-emerald-500/10">
                                    <Plus class="h-3.5 w-3.5" /> Add color to this design
                                </button>
                            </div>
                        </div>
                        <p v-if="sampleForm.errors.fabric_designs" class="text-xs text-rose-600">{{ sampleForm.errors.fabric_designs }}</p>
                        <button type="button" @click="addSampleFabric"
                            :disabled="sampleForm.fabric_designs.length >= fabricOptions.length"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-emerald-300 dark:border-emerald-500/40 px-3 py-2 text-xs font-semibold text-emerald-700 dark:text-emerald-300 transition hover:bg-emerald-50 dark:hover:bg-emerald-500/10 disabled:cursor-not-allowed disabled:opacity-40">
                            <Plus class="h-3.5 w-3.5" /> Add another fabric design
                        </button>
                    </div>

                    <!-- Method picker -->
                    <fieldset>
                        <legend class="mb-1.5 text-xs font-semibold text-slate-700 dark:text-zinc-300">How will you share the color?</legend>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3" role="radiogroup">
                            <button v-for="m in sampleMethods" :key="m.value" type="button" role="radio"
                                :aria-checked="sampleForm.request_method === m.value"
                                @click="sampleForm.request_method = m.value"
                                class="relative flex items-center gap-3 rounded-xl border p-3 text-left transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-emerald-500/20 sm:flex-col sm:items-start sm:gap-2"
                                :class="sampleForm.request_method === m.value
                                    ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-500/10 ring-1 ring-emerald-500'
                                    : 'border-slate-200 dark:border-zinc-700 hover:border-emerald-300 dark:hover:border-emerald-500/40 hover:bg-slate-50 dark:hover:bg-zinc-800/60'">
                                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg transition"
                                    :class="sampleForm.request_method === m.value
                                        ? 'bg-emerald-600 text-white'
                                        : 'bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400'">
                                    <component :is="m.icon" class="h-5 w-5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-slate-900 dark:text-white">{{ m.label }}</span>
                                    <span class="block text-xs text-slate-500 dark:text-zinc-400">{{ m.hint }}</span>
                                </span>
                                <Check v-if="sampleForm.request_method === m.value"
                                    class="absolute right-2.5 top-2.5 h-4 w-4 rounded-full bg-emerald-600 p-0.5 text-white" />
                            </button>
                        </div>
                    </fieldset>

                    <!-- Method details -->
                    <div class="rounded-xl border border-slate-200 dark:border-zinc-700 bg-slate-50/70 dark:bg-zinc-800/40 p-3.5 sm:p-4">

                        <!-- Carrier -->
                        <div v-if="sampleForm.request_method === 'carrier'">
                            <label for="sample-carrier" class="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-zinc-300">Carrier or transporter</label>
                            <div class="relative">
                                <Truck class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input id="sample-carrier" v-model="sampleForm.carrier_name" required
                                    placeholder="e.g. LBC, J&T, your own driver"
                                    class="w-full rounded-xl border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-2.5 pl-10 text-sm focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/15 transition" />
                            </div>
                            <p class="mt-2 text-xs text-slate-500 dark:text-zinc-400">Send your physical swatch to the Imus plant. We will match it in the lab.</p>
                            <p v-if="sampleForm.errors.carrier_name" class="mt-1 text-xs text-rose-600">{{ sampleForm.errors.carrier_name }}</p>
                        </div>

                        <!-- Image / PDF -->
                        <div v-else-if="sampleForm.request_method === 'image'">
                            <input ref="sampleFileInput" type="file" accept="image/*,.pdf" class="hidden"
                                @change="onSampleFile($event.target.files[0])" />

                            <button v-if="!sampleForm.reference_file" type="button"
                                @click="sampleFileInput.click()"
                                @dragover.prevent="sampleDragging = true"
                                @dragleave.prevent="sampleDragging = false"
                                @drop.prevent="onSampleDrop"
                                class="flex w-full flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed px-4 py-7 text-center transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-emerald-500/20"
                                :class="sampleDragging
                                    ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-500/10'
                                    : 'border-slate-300 dark:border-zinc-600 hover:border-emerald-400 hover:bg-white dark:hover:bg-zinc-800'">
                                <UploadCloud class="h-7 w-7 text-emerald-600 dark:text-emerald-400" />
                                <span class="text-sm font-semibold text-slate-800 dark:text-zinc-100">Drop your color reference here</span>
                                <span class="text-xs text-slate-500 dark:text-zinc-400">or click to browse · image or PDF</span>
                            </button>

                            <div v-else class="flex items-center gap-3 rounded-xl border border-emerald-200 dark:border-emerald-500/30 bg-white dark:bg-zinc-800 p-2.5">
                                <img v-if="sampleFilePreview" :src="sampleFilePreview" alt="Color reference preview"
                                    class="h-14 w-14 flex-shrink-0 rounded-lg object-cover border border-slate-200 dark:border-zinc-700" />
                                <span v-else class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-500/10">
                                    <FileText class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-slate-900 dark:text-white">{{ sampleForm.reference_file.name }}</span>
                                    <span class="block text-xs text-slate-500 dark:text-zinc-400">{{ formatBytes(sampleForm.reference_file.size) }}</span>
                                </span>
                                <button type="button" @click="clearSampleFile" aria-label="Remove file"
                                    class="rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10">
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                            <p v-if="sampleForm.errors.reference_file" class="mt-1.5 text-xs text-rose-600">{{ sampleForm.errors.reference_file }}</p>
                        </div>

                        <div v-else class="text-xs text-slate-600 dark:text-zinc-400">
                            Add Pantone colors under each fabric design above.
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label for="sample-notes" class="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-zinc-300">
                            Notes <span class="font-normal text-slate-400">(optional)</span>
                        </label>
                        <textarea id="sample-notes" v-model="sampleForm.notes" rows="2"
                            placeholder="Lighting, finish, or anything we should match closely"
                            class="w-full resize-none rounded-xl border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-sm focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/15 transition" />
                    </div>

                    <!-- Footer -->
                    <div class="flex flex-col-reverse items-stretch gap-3 border-t border-slate-100 dark:border-zinc-800 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-slate-500 dark:text-zinc-400">
                            <template v-if="selectedSampleFabrics.length">{{ selectedSampleFabrics.length }} fabric design{{ selectedSampleFabrics.length === 1 ? '' : 's' }} selected</template>
                            <template v-else>Choose a fabric design to continue</template>
                        </p>
                        <button type="submit" :disabled="!canSubmitSample"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-emerald-600/30 transition hover:bg-emerald-700 active:scale-95 disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none">
                            <Loader2 v-if="sampleForm.processing" class="h-4 w-4 animate-spin" />
                            <Send v-else class="h-4 w-4" />
                            {{ sampleForm.processing ? 'Sending…' : 'Send sample request' }}
                        </button>
                    </div>
                </form>
            </details>

            <!-- ── Main layout ─────────────────────────────────────── -->
            <div class="flex flex-1 overflow-hidden gap-4 min-h-0">

                <!-- Left: conversation -->
                <section :class="mobilePanel !== 'chat' ? 'hidden lg:flex' : 'flex'"
                    class="flex-1 flex-col overflow-hidden rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-sm min-w-0"
                    aria-label="Conversation">

                    <!-- Messages -->
                    <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 sm:p-6 bg-slate-50/70 dark:bg-zinc-950/40">
                        <div v-if="!clientChatMessages.length" class="h-full flex flex-col items-center justify-center text-center text-slate-400 dark:text-zinc-500 gap-2">
                            <FileText class="h-8 w-8" />
                            <p class="text-sm font-medium">No messages yet</p>
                            <p class="text-xs">Replies, quotations and sample updates will appear here.</p>
                        </div>

                        <TransitionGroup name="card" tag="div" class="space-y-3">
                            <div v-for="(msg, i) in clientChatMessages" :key="msg.id" class="flex flex-col"
                                :class="msg.sender_type === 'client' ? 'items-end' : 'items-start'">

                                <!-- Day divider -->
                                <div v-if="isNewDay(i)" class="w-full flex items-center gap-3 my-3">
                                    <span class="flex-1 h-px bg-slate-200 dark:bg-zinc-800" />
                                    <span class="text-[11px] font-medium text-slate-400 dark:text-zinc-500">{{ dayLabel(msg.created_at) }}</span>
                                    <span class="flex-1 h-px bg-slate-200 dark:bg-zinc-800" />
                                </div>

                                <!-- System event -->
                                <div v-if="msg.is_system_event" class="w-full flex justify-center my-1">
                                    <div class="bg-white dark:bg-zinc-900 border border-indigo-100 dark:border-indigo-500/20 border-l-4 border-l-indigo-500 px-4 py-3 rounded-xl max-w-[92%] sm:max-w-[80%] shadow-sm">
                                        <template v-if="msg.sample_request && (msg.message.startsWith('Fabric sample request submitted') || msg.message.includes('received the fabric sample request'))">
                                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ msg.message.startsWith('Fabric sample request submitted') ? 'Fabric sample request submitted' : 'Fabric sample received' }}</h3>
                                            <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">{{ msg.sample_request.code }}</p>
                                            <div v-if="msg.message.startsWith('Fabric sample request submitted')" class="mt-3 space-y-2">
                                                <article v-for="design in (msg.sample_request.submitted_designs || [msg.sample_request])" :key="design.id" class="rounded-lg border border-slate-200 p-3 dark:border-zinc-700">
                                                    <p class="text-xs font-semibold text-slate-800 dark:text-zinc-100">{{ design.fabric_name }}</p>
                                                    <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">Method: {{ design.request_method || 'client request' }}<span v-if="design.carrier_name"> · Carrier: {{ design.carrier_name }}</span></p>
                                                    <p v-if="design.color_description" class="mt-1 text-xs text-slate-600 dark:text-zinc-300">{{ design.color_description }}</p>
                                                    <p v-if="design.texture || design.gsm" class="mt-1 text-xs text-slate-600 dark:text-zinc-300">{{ design.texture || 'Texture not specified' }}<span v-if="design.gsm"> · {{ design.gsm }} GSM</span></p>
                                                    <div v-if="design.color_hex" class="mt-2 flex items-center gap-2 text-xs text-slate-600 dark:text-zinc-300"><span class="h-5 w-5 rounded border border-black/10" :style="{ backgroundColor: design.color_hex }" />{{ design.color_hex }}</div>
                                                    <img v-if="design.client_reference_path" :src="getFullUrl(design.client_reference_path)" alt="Uploaded fabric sample reference" class="mt-2 max-h-56 w-full rounded-lg border border-slate-200 bg-slate-50 object-contain dark:border-zinc-700 dark:bg-zinc-950" />
                                                    <img v-if="design.color_image_path" :src="getFullUrl(design.color_image_path)" alt="Uploaded color reference" class="mt-2 max-h-40 rounded-lg border border-slate-200 object-contain dark:border-zinc-700" />
                                                </article>
                                            </div>
                                            <div v-else class="mt-3 space-y-2">
                                                <p class="text-xs text-slate-600 dark:text-zinc-300">Monti Textile recorded {{ msg.sample_request.rounds?.length || 0 }} Pantone color(s).</p>
                                                <article v-for="color in msg.sample_request.rounds" :key="color.id" class="flex items-center gap-3 rounded-lg border border-slate-200 p-2.5 dark:border-zinc-700">
                                                    <span v-if="color.color_hex" class="h-8 w-8 shrink-0 rounded border border-black/10" :style="{ backgroundColor: color.color_hex }" />
                                                    <img v-else-if="color.color_image_path" :src="getFullUrl(color.color_image_path)" alt="Recorded color reference" class="h-9 w-9 shrink-0 rounded border border-slate-200 object-cover dark:border-zinc-700" />
                                                    <span class="min-w-0"><span class="block text-xs font-semibold text-slate-800 dark:text-zinc-100">{{ color.fabric_name }} · {{ color.color_description }}</span><span class="block text-xs text-slate-500 dark:text-zinc-400">{{ color.texture }} · {{ color.gsm }} GSM<span v-if="color.color_hex"> · {{ color.color_hex }}</span></span></span>
                                                </article>
                                            </div>
                                        </template>
                                        <p v-else class="text-xs font-medium text-slate-700 dark:text-zinc-200 whitespace-pre-wrap leading-relaxed">{{ msg.message }}</p>
                                        <div v-if="msg.attachments?.length" class="mt-2.5 space-y-2">
                                            <div v-for="file in msg.attachments" :key="file.id">
                                                <img v-if="file.file_type?.startsWith('image/')"
                                                    :src="getFullUrl(file.file_path)" :alt="file.file_name || 'Attachment'"
                                                    class="max-w-full rounded-xl cursor-zoom-in border border-slate-200 dark:border-zinc-700 hover:opacity-90 transition"
                                                    @click="openPreview(file)" />
                                                <a v-else :href="getFullUrl(file.file_path)" target="_blank" rel="noopener"
                                                    class="flex items-center gap-2 p-2.5 bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-xs font-medium hover:bg-slate-100 dark:hover:bg-zinc-700 transition">
                                                    <FileText class="h-4 w-4 text-indigo-500 flex-shrink-0" />
                                                    <span class="truncate text-slate-700 dark:text-zinc-200">{{ file.file_name }}</span>
                                                </a>
                                                <div v-if="!file.approved_by_client && !msg.sample_request" class="mt-1.5 flex justify-end">
                                                    <button type="button" @click.stop="approveAttachment(file)"
                                                        class="px-2.5 py-1.5 bg-amber-500 text-white rounded-lg text-xs font-medium hover:bg-amber-600 transition active:scale-95">
                                                        Approve
                                                    </button>
                                                </div>
                                                <div v-else-if="file.approved_by_client" class="mt-1.5 flex justify-end">
                                                    <span class="px-2.5 py-1.5 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 rounded-lg text-xs font-medium">
                                                        Approved
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Regular message -->
                                <div v-else
                                    :class="msg.sender_type === 'client'
                                        ? 'bg-indigo-600 text-white rounded-2xl rounded-br-md shadow-sm'
                                        : 'bg-white dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 border border-slate-200 dark:border-zinc-700 rounded-2xl rounded-bl-md shadow-sm'"
                                    class="max-w-[85%] sm:max-w-[70%] px-4 py-3">
                                    <p class="text-xs font-medium opacity-70 mb-1">
                                        {{ msg.sender_type === 'client' ? 'You' : 'Monti Textile team' }}
                                    </p>
                                    <p class="text-sm whitespace-pre-wrap leading-relaxed">{{ msg.message }}</p>

                                    <div v-if="msg.attachments?.length" class="mt-2.5 space-y-2">
                                        <div v-for="file in msg.attachments" :key="file.id">
                                            <img v-if="file.file_type?.startsWith('image/')"
                                                :src="getFullUrl(file.file_path)" :alt="file.file_name || 'Attachment'"
                                                class="max-w-full rounded-xl cursor-zoom-in border border-black/10 dark:border-white/10 hover:opacity-90 transition"
                                                @click="openPreview(file)" />
                                            <a v-else :href="getFullUrl(file.file_path)" target="_blank" rel="noopener"
                                                class="flex items-center gap-2 p-2.5 rounded-xl text-xs font-medium transition"
                                                :class="msg.sender_type === 'client' ? 'bg-white/15 hover:bg-white/25' : 'bg-slate-50 dark:bg-zinc-900 hover:bg-slate-100 dark:hover:bg-zinc-700 border border-slate-200 dark:border-zinc-700'">
                                                <FileText class="h-4 w-4 flex-shrink-0" />
                                                <span class="truncate">{{ file.file_name }}</span>
                                            </a>
                                            <div v-if="!file.approved_by_client && !msg.sample_request" class="mt-1.5 flex justify-end">
                                                <button type="button" @click.stop="approveAttachment(file)"
                                                    class="px-2.5 py-1.5 rounded-lg text-xs font-medium transition active:scale-95"
                                                    :class="msg.sender_type === 'client' ? 'bg-white text-indigo-700 hover:bg-indigo-50' : 'bg-amber-500 text-white hover:bg-amber-600'">
                                                    Approve
                                                </button>
                                            </div>
                                            <div v-else-if="file.approved_by_client" class="mt-1.5 flex justify-end">
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium"
                                                    :class="msg.sender_type === 'client' ? 'bg-white/15' : 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'">
                                                    Approved
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Fabric swatch delivery and client decision -->
                                    <div v-if="msg.sample_request" class="mt-2.5">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium"
                                            :class="msg.sender_type === 'client' ? 'bg-white/15' : 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'">
                                            <FlaskConical class="h-3 w-3" />
                                            {{ msg.sample_request.sample_fabric_design || msg.sample_request.fabric_name }} · {{ msg.sample_request.code }} · {{ sampleStatusLabel(msg.sample_request.status) }}
                                        </span>
                                        <section v-if="msg.sample_request.status === 'forwarded' && Number(msg.id) === Number(msg.sample_request.delivery_message_id)" class="mt-3 space-y-3 rounded-xl border border-indigo-100 bg-indigo-50/70 p-3 dark:border-indigo-900/50 dark:bg-indigo-950/30">
                                            <div class="grid gap-1 text-xs text-slate-700 dark:text-zinc-200">
                                                <p><span class="font-semibold">Fabric design:</span> {{ msg.sample_request.sample_fabric_design || msg.sample_request.fabric_name }}</p>
                                                <p v-if="msg.sample_request.sample_yarn_type"><span class="font-semibold">Yarn type:</span> {{ msg.sample_request.sample_yarn_type }}</p>
                                                <p v-if="msg.sample_request.gsm"><span class="font-semibold">GSM:</span> {{ msg.sample_request.gsm }}</p>
                                                <p><span class="font-semibold">Courier:</span> {{ msg.sample_request.delivery_courier }} · <span class="font-semibold">Tracking:</span> {{ msg.sample_request.delivery_tracking_reference }}</p>
                                            </div>
                                            <p v-if="msg.sample_request.delivery_notes" class="rounded-lg bg-white/80 p-2 text-xs text-slate-600 dark:bg-zinc-900/80 dark:text-zinc-300">{{ msg.sample_request.delivery_notes }}</p>
                                            <a v-if="msg.sample_request.sample_image_path" :href="`/storage/${msg.sample_request.sample_image_path}`" target="_blank" class="inline-flex items-center gap-2 text-xs font-semibold text-indigo-700 underline dark:text-indigo-300">
                                                <img v-if="!String(msg.sample_request.sample_image_path).toLowerCase().endsWith('.pdf')" :src="`/storage/${msg.sample_request.sample_image_path}`" alt="Fabric swatch sample" class="h-12 w-12 rounded-lg border border-slate-200 object-cover dark:border-zinc-700" />
                                                {{ String(msg.sample_request.sample_image_path).toLowerCase().endsWith('.pdf') ? 'Open sample PDF' : 'View sample image' }}
                                            </a>
                                            <div v-if="!msg.sample_request.client_noted_at" class="flex flex-wrap gap-1.5">
                                                <button type="button" @click="noteSampleDelivery(msg.sample_request.id)" class="rounded-lg border border-indigo-200 bg-white px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 dark:border-indigo-800 dark:bg-zinc-900 dark:text-indigo-300">Noted</button>
                                            </div>
                                            <div v-else-if="!msg.sample_request.client_received_at" class="space-y-2">
                                                <p class="text-xs font-medium text-indigo-700 dark:text-indigo-300">Delivery details noted. Confirm once the physical swatch has arrived.</p>
                                                <button type="button" @click="receiveSampleDelivery(msg.sample_request.id)" class="rounded-lg bg-indigo-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">Received</button>
                                            </div>
                                            <div v-else class="flex flex-wrap gap-1.5">
                                            <button type="button" @click="approveSample(msg.sample_request.id)"
                                                class="px-2.5 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-medium hover:bg-emerald-700 transition active:scale-95">
                                                Approved
                                            </button>
                                            <button type="button" @click="openAdjustModal(msg.sample_request)"
                                                class="px-2.5 py-1.5 bg-amber-400 text-amber-950 rounded-lg text-xs font-medium hover:bg-amber-500 transition active:scale-95">
                                                Request for Adjustment
                                            </button>
                                            </div>
                                        </section>
                                        <p v-else-if="msg.sample_request.status === 'approved' && Number(msg.id) === Number(msg.sample_request.delivery_message_id)" class="mt-2 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                            Sample approved — you may now send your P.O.
                                        </p>
                                        <p v-else-if="msg.sample_request.status === 'adjustment_requested' && Number(msg.id) === Number(msg.sample_request.delivery_message_id)" class="mt-2 text-xs font-medium text-amber-600 dark:text-amber-400">
                                            Adjustment requested — the Dyeing Lab is preparing a revised swatch.
                                        </p>
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
                                <span class="text-xs text-slate-500 dark:text-zinc-400">Monti Textile team is typing…</span>
                            </div>
                        </div>
                    </div>

                    <!-- Reply box -->
                    <div class="flex-shrink-0 border-t border-slate-200 dark:border-zinc-800 p-3 sm:p-4 bg-white dark:bg-zinc-900">
                        <div v-if="selectedFiles.length" class="flex gap-2 mb-3 overflow-x-auto pb-1 scrollbar-hide">
                            <div v-for="(file, i) in selectedFiles" :key="i"
                                class="relative h-14 w-14 flex-shrink-0 bg-slate-100 dark:bg-zinc-800 rounded-xl border border-slate-200 dark:border-zinc-700 overflow-hidden">
                                <img v-if="file.type.startsWith('image/')" :src="getFilePreview(file)" :alt="file.name" class="h-full w-full object-cover" />
                                <div v-else class="h-full w-full flex flex-col items-center justify-center px-1">
                                    <FileText class="h-5 w-5 text-slate-400" />
                                    <span class="text-[9px] text-slate-500 truncate w-full text-center">{{ file.name }}</span>
                                </div>
                                <button @click="confirmRemoveFile(i)" type="button" :aria-label="`Remove ${file.name}`"
                                    class="absolute top-0.5 right-0.5 bg-slate-900/80 text-white rounded-full p-0.5 hover:bg-red-500 transition">
                                    <X class="h-3 w-3" />
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 bg-slate-50 dark:bg-zinc-800 rounded-2xl pl-4 pr-1.5 py-1.5 border border-slate-200 dark:border-zinc-700 focus-within:border-indigo-400 focus-within:ring-4 focus-within:ring-indigo-500/10 transition">
                            <input v-model="newMessage" type="text" aria-label="Reply to Monti Textile"
                                placeholder="Write a message…"
                                class="flex-1 bg-transparent border-none focus:ring-0 focus:outline-none text-sm text-slate-800 dark:text-zinc-100 placeholder:text-slate-400 py-2"
                                @input="notifyTyping"
                                @keydown.enter.prevent="handleSend" />
                            <button type="button" @click="triggerFileUpload" aria-label="Attach files"
                                class="p-2 hover:bg-slate-200 dark:hover:bg-zinc-700 rounded-xl transition text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200">
                                <Paperclip class="h-4 w-4" />
                            </button>
                            <input ref="fileInput" type="file" class="hidden" multiple @change="onFilesSelected" />
                            <button type="button" @click="handleSend" aria-label="Send message"
                                :disabled="sending || (!newMessage.trim() && !selectedFiles.length)"
                                class="p-2.5 bg-indigo-600 text-white rounded-xl disabled:opacity-40 disabled:cursor-not-allowed hover:bg-indigo-700 transition active:scale-95">
                                <Send v-if="!sending" class="h-4 w-4" />
                                <Loader2 v-else class="h-4 w-4 animate-spin" />
                            </button>
                        </div>
                    </div>
                </section>

                <!-- Right: actions + proposals -->
                <aside :class="mobilePanel !== 'proposals' ? 'hidden lg:block' : 'block'"
                    class="w-full lg:w-[360px] xl:w-[420px] flex-shrink-0 overflow-y-auto space-y-5 pr-0.5 pb-2"
                    aria-label="Actions and proposals">

                    <!-- Actions -->
                    <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="flex items-center gap-2 px-5 py-3.5 border-b border-slate-100 dark:border-zinc-800">
                            <ClipboardList class="h-4 w-4 text-indigo-500" />
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Actions</h2>
                        </div>
                        <div class="p-3 space-y-1.5">
                            <button v-if="colorProductOptions.length" type="button" @click="openAddColor"
                                class="group flex w-full items-center gap-2.5 rounded-xl p-2.5 text-left transition hover:bg-violet-50 active:scale-[0.99] dark:hover:bg-violet-500/10">
                                <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-violet-600 text-white">
                                    <Plus class="h-4 w-4" />
                                </span>
                                <span class="min-w-0 flex-1 text-sm font-semibold text-slate-900 dark:text-white">Add another Color</span>
                                <ChevronDown class="h-4 w-4 -rotate-90 text-slate-400 transition group-hover:translate-x-0.5" />
                            </button>
                            <button @click="openPoModal" type="button" :disabled="!canSendPurchaseOrder"
                                class="group w-full flex items-center gap-3 p-3 rounded-xl text-left transition active:scale-[0.99]"
                                :class="canSendPurchaseOrder ? 'hover:bg-amber-50 dark:hover:bg-amber-500/10' : 'opacity-50 cursor-not-allowed'">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl flex-shrink-0"
                                    :class="canSendPurchaseOrder ? 'bg-amber-500 text-white' : 'bg-slate-100 dark:bg-zinc-800 text-slate-400 dark:text-zinc-500'">
                                    <FileText class="h-5 w-5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-slate-900 dark:text-white">Send purchase order</span>
                                    <span class="block text-xs text-slate-500 dark:text-zinc-400">
                                         {{ canSendPurchaseOrder ? 'Enter kg for an accepted quotation' : (hasRequestedColorways ? 'Accept an eligible quotation after its swatches are approved' : 'Accept a quotation before ordering') }}
                                    </span>
                                </span>
                            </button>
                            <p v-if="!canSendPurchaseOrder" class="text-xs text-slate-500 dark:text-zinc-400 bg-slate-50 dark:bg-zinc-800/60 border border-slate-200 dark:border-zinc-700 px-3 py-2.5 rounded-xl text-center">
                                {{ hasRequestedColorways ? 'A purchase order is available after you accept a quotation whose color and yarn swatches are approved.' : 'Accept a quotation first to enable purchase orders.' }}
                            </p>
                        </div>
                    </div>

                    <section v-if="clientSampleRequests.length" aria-label="Color swatches and deliveries" class="rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="flex flex-wrap items-center gap-2 px-5 py-3.5 border-b border-slate-100 dark:border-zinc-800">
                            <FlaskConical class="h-4 w-4 text-emerald-600"/>
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Color swatches</h2>
                            <div v-if="selectableSamples.length" class="ml-auto flex flex-wrap items-center gap-2">
                                <label class="inline-flex cursor-pointer items-center gap-1.5 text-[11px] font-semibold text-slate-500 dark:text-zinc-400">
                                    <input type="checkbox" :checked="allSelectableSelected" @change="toggleSelectAllSamples" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                                    Select all
                                </label>
                                <button type="button" :disabled="!selectedSampleIds.length || bulkProcessing" @click="bulkNoteSamples" class="rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-indigo-800 dark:text-indigo-300 dark:hover:bg-indigo-950/40">Noted</button>
                                <button type="button" :disabled="!selectedSampleIds.length || bulkProcessing" @click="bulkReceiveSamples" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-40">Received</button>
                            </div>
                        </div>
                        <div class="p-3 space-y-2">
                            <article v-for="sample in clientSampleRequests" :key="sample.id" class="rounded-xl border border-slate-200 dark:border-zinc-700 p-3 space-y-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex min-w-0 items-start gap-2">
                                        <input v-if="isSampleSelectable(sample)" type="checkbox" :checked="selectedSampleIds.includes(sample.id)" @change="toggleSampleSelection(sample.id)" :aria-label="`Select swatch ${sample.code}`" class="mt-1 h-4 w-4 shrink-0 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                                        <div class="min-w-0"><p class="text-sm font-semibold text-slate-900 dark:text-white">{{ sample.sample_fabric_design || sample.fabric_name }}</p><p class="text-xs text-slate-500 dark:text-zinc-400">{{ sample.sample_yarn_type }} · {{ sample.color_description }} · {{ sample.code }}</p></div>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-slate-100 dark:bg-zinc-800 px-2 py-1 text-[10px] font-semibold">{{ sampleStatusLabel(sample.status) }}</span>
                                </div>
                                <p v-if="sample.delivery_courier" class="text-xs text-slate-600 dark:text-zinc-300">{{ sample.delivery_courier }} · Tracking {{ sample.delivery_tracking_reference }}</p>
                                <p v-if="sample.delivery_notes" class="rounded-lg bg-slate-50 dark:bg-zinc-800 p-2 text-xs text-slate-600 dark:text-zinc-300">{{ sample.delivery_notes }}</p>
                                <button v-if="sample.sample_image_path" type="button" @click="openSwatchModal(sample)" class="inline-flex items-center gap-2 text-xs font-semibold text-indigo-600 underline dark:text-indigo-300"><img v-if="!String(sample.sample_image_path).toLowerCase().endsWith('.pdf')" :src="getFullUrl(sample.sample_image_path)" alt="Swatch" class="h-10 w-10 rounded border object-cover"/>{{ String(sample.sample_image_path).toLowerCase().endsWith('.pdf') ? 'View swatch PDF' : 'View swatch' }}</button>
                                <div v-if="sample.status === 'forwarded' && !sample.client_noted_at" class="flex gap-2"><button type="button" @click="noteSampleDelivery(sample.id)" class="rounded-lg border px-3 py-1.5 text-xs font-semibold">Noted</button></div>
                                <div v-else-if="sample.status === 'forwarded' && !sample.client_received_at" class="flex items-center justify-between gap-2"><span class="text-xs text-slate-500">Confirm the physical swatch arrived.</span><button type="button" @click="receiveSampleDelivery(sample.id)" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white">Received</button></div>
                                <div v-else-if="sample.status === 'forwarded' && sample.client_received_at" class="flex flex-wrap gap-2"><button type="button" @click="approveSample(sample.id)" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white">Approve swatch</button><button type="button" @click="openAdjustModal(sample)" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-700 dark:text-amber-300">Request new formulation</button></div>
                                <p v-else-if="sample.status === 'adjustment_requested'" class="text-xs text-amber-700 dark:text-amber-300">Reformulation requested: {{ sample.adjustment_notes }}</p>
                            </article>
                        </div>
                    </section>

                    <!-- Proposals -->
                    <section v-if="quotations.length > 0" aria-label="Proposals">
                        <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white px-1 mb-2">
                            Proposals
                            <span class="text-xs font-medium text-slate-500 dark:text-zinc-400 bg-slate-200/70 dark:bg-zinc-800 rounded-full px-2 py-0.5">{{ quotations.length }}</span>
                        </h2>

                        <div class="flex rounded-xl bg-slate-200/60 dark:bg-zinc-900 p-1 mb-3" role="tablist">
                            <button @click="quotationTab = 'active'" type="button" role="tab" :aria-selected="quotationTab === 'active'"
                                :class="quotationTab === 'active' ? 'bg-white dark:bg-zinc-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-zinc-400 hover:text-slate-700'"
                                class="flex-1 py-1.5 rounded-lg text-xs font-medium transition">
                                Active ({{ activeQuotations.length }})
                            </button>
                            <button @click="quotationTab = 'trash'" type="button" role="tab" :aria-selected="quotationTab === 'trash'"
                                :class="quotationTab === 'trash' ? 'bg-white dark:bg-zinc-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-zinc-400 hover:text-slate-700'"
                                class="flex-1 py-1.5 rounded-lg text-xs font-medium transition">
                                Trash ({{ trashedQuotations.length }})
                            </button>
                        </div>

                        <p v-if="!visibleQuotations.length"
                            class="text-xs text-slate-500 dark:text-zinc-400 text-center py-6 rounded-xl border border-dashed border-slate-300 dark:border-zinc-700">
                            {{ quotationTab === 'trash' ? 'Nothing in the trash.' : 'No active proposals yet.' }}
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
                                                    ₱{{ formatPrice(item.unit_price) }}<span class="text-xs font-normal text-slate-500">/kg</span>
                                                    <span v-if="q.vat_type === 'inclusive'" class="text-[11px] font-normal text-slate-400 ml-1">incl. VAT</span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <p v-if="q.notes" class="text-xs text-slate-500 dark:text-zinc-400 italic border-t border-slate-100 dark:border-zinc-800 pt-2.5">
                                        {{ q.notes }}
                                    </p>

                                    <!-- Sent: view, then accept / reject -->
                                    <div v-if="q.status === 'sent'" class="space-y-2 border-t border-slate-100 dark:border-zinc-800 pt-3">
                                        <button @click="openQuotationPreview(q)" type="button"
                                            class="w-full py-2 border border-slate-200 dark:border-zinc-700 text-slate-700 dark:text-zinc-200 rounded-lg text-xs font-medium flex items-center justify-center gap-2 hover:bg-slate-50 dark:hover:bg-zinc-800 transition">
                                            <Eye class="h-3.5 w-3.5" /> View quotation
                                        </button>
                                        <div class="flex gap-2">
                                            <button @click="openRejectModal(q)" type="button"
                                                class="flex-1 py-2 border border-red-200 dark:border-red-900 text-red-600 dark:text-red-400 rounded-lg text-xs font-medium hover:bg-red-50 dark:hover:bg-red-500/10 transition active:scale-95">
                                                Reject
                                            </button>
                                            <button @click="openAcceptModal(q)" type="button"
                                                class="flex-1 py-2 bg-indigo-600 text-white rounded-lg text-xs font-medium hover:bg-indigo-700 transition active:scale-95">
                                                Accept
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Accepted: preview / download + trash -->
                                    <div v-if="q.status === 'accepted'" class="space-y-2 border-t border-slate-100 dark:border-zinc-800 pt-3">
                                        <button @click="openQuotationPreview(q)" type="button"
                                            class="w-full py-2 bg-indigo-600 text-white rounded-lg text-xs font-medium flex items-center justify-center gap-2 hover:bg-indigo-700 transition active:scale-95">
                                            <Download class="h-3.5 w-3.5" /> Preview & download
                                        </button>
                                        <button @click="openTrashModal(q)" type="button"
                                            class="w-full py-2 border border-slate-200 dark:border-zinc-700 text-slate-500 dark:text-zinc-400 rounded-lg text-xs font-medium flex items-center justify-center gap-2 hover:bg-red-50 dark:hover:bg-red-500/10 hover:text-red-600 hover:border-red-200 transition active:scale-95">
                                            <Trash2 class="h-3.5 w-3.5" /> Trash & request new
                                        </button>
                                    </div>

                                    <!-- Trashed: terminal -->
                                    <p v-if="q.status === 'trashed'" class="text-xs text-slate-500 dark:text-zinc-400 text-center border-t border-slate-100 dark:border-zinc-800 pt-3">
                                        Trashed — CRM will issue a new quotation.
                                    </p>
                                    <p v-if="q.status === 'rejected'" class="text-xs text-slate-500 dark:text-zinc-400 text-center border-t border-slate-100 dark:border-zinc-800 pt-3">
                                        Rejected — CRM may send an updated quotation.
                                    </p>
                                </div>
                            </article>
                        </TransitionGroup>
                    </section>
                </aside>
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
                <div class="bg-white dark:bg-zinc-900 w-full sm:max-w-3xl rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden max-h-[95vh] flex flex-col">
                    <!-- Header -->
                    <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 px-5 py-4 flex items-center justify-between relative overflow-hidden">
                        <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl animate-float" />
                        <div class="relative">
                            <h3 class="text-sm font-black text-white tracking-tight">Send Purchase Order</h3>
                            <p class="text-xs text-blue-100/90 mt-0.5">Choose quantities from your quotation lines</p>
                        </div>
                        <button @click="poModal.show = false"
                            class="relative flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 text-white hover:bg-white/35 hover:rotate-90 transition-all">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <!-- Body -->
                    <form @submit.prevent="submitPo" class="flex min-h-0 flex-1 flex-col">
                        <div class="space-y-4 overflow-y-auto p-5">
                            <p class="rounded-xl border border-indigo-100 bg-indigo-50/70 p-3 text-xs text-indigo-800 dark:border-indigo-900/50 dark:bg-indigo-950/30 dark:text-indigo-200">Enter the PO number issued by your company. Add an order group for each separate PO, then check the quotation lines to include. Lines from multiple quotations can share one PO.</p>
                            <div v-for="(group, groupIndex) in poModal.orderGroups" :key="group.key" class="space-y-3 rounded-2xl border border-slate-200 p-4 dark:border-zinc-700">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="text-xs font-bold uppercase tracking-wide text-slate-500">Client PO Number
                                        <input v-model.trim="group.client_po_number" required maxlength="100" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-3 text-sm font-semibold normal-case text-slate-800 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" placeholder="e.g. PO-2026-0003" />
                                    </label>
                                    <label class="text-xs font-bold uppercase tracking-wide text-slate-500">PO image (optional)
                                        <input type="file" accept="image/jpeg,image/png,image/webp" @change="setPoImage(groupIndex, $event)" class="mt-1 block w-full rounded-xl border border-slate-200 bg-white p-2 text-xs font-normal normal-case text-slate-700 file:mr-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:font-semibold file:text-indigo-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" />
                                        <span v-if="group.image" class="mt-1 block truncate text-[10px] font-normal normal-case text-slate-500">{{ group.image.name }}</span>
                                    </label>
                                    <button v-if="poModal.orderGroups.length > 1" type="button" @click="removePoGroup(groupIndex)" class="mt-5 rounded-lg px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50">Remove</button>
                                </div>
                                <section v-for="quote in eligiblePoQuotations" :key="quote.id" class="space-y-2">
                                    <h4 class="text-xs font-black text-indigo-700 dark:text-indigo-300">{{ quote.quotation_number }}</h4>
                                    <label v-for="line in quote.items" :key="line.id" class="flex items-start gap-3 rounded-xl border border-slate-100 p-3 dark:border-zinc-800" :class="isPoLineInOtherGroup(groupIndex, quote.id, line.id) ? 'opacity-50' : ''">
                                        <input type="checkbox" :checked="isPoLineSelected(groupIndex, quote.id, line.id)" :disabled="isPoLineInOtherGroup(groupIndex, quote.id, line.id)" @change="togglePoLine(groupIndex, quote, line, $event.target.checked)" class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                                        <span class="min-w-0 flex-1"><span class="block text-sm font-bold text-slate-900 dark:text-white">{{ line.product?.name || line.fabric || 'Product' }}</span><span class="mt-1 block text-xs text-slate-600 dark:text-zinc-300">{{ line.fabric_type || 'Yarn not specified' }} · {{ line.color || 'Color not specified' }}<span v-if="line.design"> · {{ line.design }}</span></span><span class="mt-1 block text-[11px] text-slate-500">{{ formatPrice(line.unit_price) }}/kg<span v-if="poMinimum(line) > 0"> · Min {{ poMinimum(line).toLocaleString() }} kg</span></span></span>
                                        <input v-if="isPoLineSelected(groupIndex, quote.id, line.id)" v-model.number="poGroupLine(groupIndex, quote.id, line.id).kilos" type="number" :min="poMinimum(line) || 0.01" step="0.01" inputmode="decimal" class="w-28 rounded-xl border border-slate-200 bg-white p-2 text-right text-sm font-bold dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" placeholder="kg" aria-label="Order quantity in kilograms" />
                                    </label>
                                </section>
                                <p v-if="!group.items.length" class="text-xs text-slate-500">Select at least one product/color line for this PO.</p>
                            </div>
                            <button type="button" @click="addPoGroup" class="w-full rounded-xl border border-dashed border-indigo-300 px-4 py-3 text-xs font-bold text-indigo-700 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-300">+ Add another PO (separate order)</button>
                            <div class="rounded-2xl bg-slate-50 p-4 dark:bg-zinc-800/70">
                                <div class="flex justify-between text-xs text-slate-600 dark:text-zinc-300"><span>Selected order lines</span><strong>{{ poSelectedLineCount }}</strong></div>
                                <div class="mt-2 flex justify-between text-xs text-slate-600 dark:text-zinc-300"><span>Total quantity</span><strong>{{ Number(poTotalKilos).toLocaleString() }} kg</strong></div>
                                <div class="mt-3 flex justify-between text-sm font-bold text-slate-800 dark:text-zinc-100"><span>Estimated total</span><span>₱{{ Number(poEstimatedTotal).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span></div>
                                <div class="mt-1 flex justify-between text-xs text-slate-600 dark:text-zinc-300"><span>VAT (+12%)</span><span>₱{{ Number(poVatAmount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span></div>
                                <div class="mt-2 flex justify-between border-t border-slate-200 pt-2 text-base font-black text-slate-900 dark:border-zinc-700 dark:text-white"><span>Total amount with +12% VAT</span><span>₱{{ Number(poTotalWithVat).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span></div>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-gray-500">Notes (optional)</label>
                                <textarea v-model="poModal.notes" rows="2" class="w-full resize-none rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm transition focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" placeholder="Add any order remarks"></textarea>
                            </div>
                        </div>
                        <div class="flex gap-3 border-t border-slate-100 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                            <button type="button" @click="poModal.show = false" class="rounded-xl border border-slate-200 px-4 py-3 text-xs font-bold text-slate-600 dark:border-zinc-700 dark:text-zinc-300">Cancel</button>
                            <button type="submit" :disabled="!canSubmitPo" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-amber-400 to-orange-500 py-3 text-xs font-black uppercase tracking-wide text-amber-950 shadow-lg transition-all hover:shadow-xl disabled:opacity-40">
                                <Loader2 v-if="poModal.submitting" class="h-4 w-4 animate-spin" /><Send v-else class="h-4 w-4" />
                                Place Purchase Order
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            </Transition>
        </Teleport>

        <!-- ════════════════════════════════════════════
             SWATCH PREVIEW MODAL
             ════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
            <div v-if="swatchModal.show"
                class="fixed inset-0 z-[90] flex items-center justify-center p-4 bg-black/90 backdrop-blur-md"
                @click.self="swatchModal.show = false">
                <button @click="swatchModal.show = false"
                    class="absolute top-5 right-5 p-2 bg-white/10 hover:bg-white/20 hover:rotate-90 rounded-2xl transition-all z-[95]">
                    <X class="h-5 w-5 text-white" />
                </button>
                <div class="w-full max-w-4xl max-h-[88vh] flex flex-col gap-3">
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-white/60 px-1">{{ swatchModal.title }}</p>
                    <div class="flex-1 min-h-0 bg-black/40 rounded-3xl overflow-hidden border border-white/10 flex items-center justify-center">
                        <img v-if="!swatchModal.isPdf" :src="swatchModal.url" :alt="swatchModal.title"
                            class="max-w-full max-h-[80vh] object-contain rounded-xl shadow-2xl" />
                        <iframe v-else :src="swatchModal.url" title="Swatch PDF" class="h-[80vh] w-full bg-white"></iframe>
                    </div>
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
                                        <span class="text-xs text-gray-700 uppercase font-medium">{{ item.fabric_type || item.fabric }}</span>
                                        <span class="text-[10px] text-gray-500">{{ item.fabric }}<span v-if="item.design"> · {{ item.design }}</span></span>
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
                            <section v-for="(group, fabricType) in groupItemsByFabricType(quotationPreviewModal.quotation.items)" :key="fabricType" style="margin-bottom:14px;">
                                <div style="font-size:9px;font-weight:700;font-family:Arial,sans-serif;margin:0 0 5px;color:#1a1a2e;">{{ fabricType }}</div>
                                <table style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:9px;">
                                    <thead>
                                        <tr style="background:#f5f5f5;">
                                            <th style="border:1px solid #ccc;padding:6px 8px;text-align:left;font-weight:700;width:34%;">Product / Design</th>
                                            <th style="border:1px solid #ccc;padding:6px 8px;text-align:center;font-weight:700;width:14%;">Color</th>
                                            <th style="border:1px solid #ccc;padding:6px 8px;text-align:center;font-weight:700;width:12%;">MOQ (kg)</th>
                                            <th style="border:1px solid #ccc;padding:6px 8px;text-align:right;font-weight:700;">Original Price/kg</th>
                                            <th v-if="quotationPreviewModal.quotation.vat_type === 'inclusive'" style="border:1px solid #ccc;padding:6px 8px;text-align:right;font-weight:700;">Price + 12% VAT/kg</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(item, idx) in group" :key="item.id" :style="idx % 2 === 0 ? 'background:#fff;' : 'background:#fafafa;'">
                                            <td style="border:1px solid #ddd;padding:5px 10px;">{{ item.fabric }}<span v-if="item.design"> · {{ item.design }}</span></td>
                                            <td style="border:1px solid #ddd;padding:5px 10px;text-align:center;">{{ item.color }}</td>
                                            <td style="border:1px solid #ddd;padding:5px 10px;text-align:center;">{{ item.kilos }}</td>
                                            <td style="border:1px solid #ddd;padding:5px 8px;text-align:right;">&#8369;{{ formatPrice(item.unit_price) }}</td>
                                            <td v-if="quotationPreviewModal.quotation.vat_type === 'inclusive'" style="border:1px solid #ddd;padding:5px 8px;text-align:right;">&#8369;{{ formatPrice(Number(item.unit_price) * 1.12) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </section>

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

        <FabricInquiryNotice
            v-model="showQuotationAccepted"
            title="Quotation accepted!"
            message="The quotation has been accepted successfully. You can continue with your purchase order."
        />

        <div v-if="addColorModal" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/50 p-4" @click.self="addColorModal = false">
            <section class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl dark:bg-zinc-900" role="dialog" aria-modal="true" aria-labelledby="add-color-title">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h2 id="add-color-title" class="text-lg font-bold text-slate-900 dark:text-white">Add another Color</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Choose a Pantone and the yarns it applies to. CRM will include these combinations when requesting recipes.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800" aria-label="Close" @click="addColorModal = false"><X class="h-5 w-5" /></button>
                </div>
                <form class="space-y-4" @submit.prevent="submitAddColor">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-zinc-200">Product / fabric design
                        <select v-model="addColorForm.product_id" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                            <option v-for="product in colorProductOptions" :key="product.product_id" :value="String(product.product_id)">{{ product.name }}</option>
                        </select>
                    </label>
                    <div class="relative">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-zinc-200">Pantone color</label>
                        <button type="button" class="mt-1.5 flex w-full items-center gap-3 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-left text-sm shadow-sm transition hover:border-violet-400 focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500/20 dark:border-zinc-700 dark:bg-zinc-800" :aria-expanded="pantoneDropdownOpen" aria-haspopup="listbox" @click="pantoneDropdownOpen = !pantoneDropdownOpen; pantoneSearch = ''">
                            <span v-if="selectedPantone" class="h-7 w-7 shrink-0 rounded-lg border border-black/10 shadow-inner" :style="{ backgroundColor: selectedPantone.hex || '#e5e7eb' }" />
                            <span v-else class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-dashed border-slate-300 text-xs text-slate-400 dark:border-zinc-600">?</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold text-slate-800 dark:text-zinc-100">{{ selectedPantone?.name || 'Select a Pantone' }}</span>
                                <span v-if="selectedPantone?.color_category" class="block truncate text-xs font-normal text-slate-500 dark:text-zinc-400">{{ selectedPantone.color_category }}</span>
                            </span>
                            <ChevronDown class="h-4 w-4 shrink-0 text-slate-400 transition" :class="pantoneDropdownOpen ? 'rotate-180' : ''" />
                        </button>
                        <div v-if="pantoneDropdownOpen" class="mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="border-b border-slate-100 p-2.5 dark:border-zinc-800">
                                <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 dark:border-zinc-700 dark:bg-zinc-800">
                                    <Search class="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
                                    <input v-model="pantoneSearch" type="search" autocomplete="off" placeholder="Search Pantone name or category…" class="w-full border-0 bg-transparent py-2 text-sm outline-none ring-0 placeholder:text-slate-400 focus:ring-0 dark:text-white" aria-label="Search Pantone colors" />
                                </div>
                            </div>
                            <div class="max-h-60 overflow-y-auto p-1.5" role="listbox" aria-label="Pantone colors">
                                <button v-for="pantone in filteredPantones" :key="pantone.id" type="button" role="option" :aria-selected="String(pantone.id) === String(addColorForm.pantone_id)" class="flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left transition hover:bg-violet-50 focus:bg-violet-50 focus:outline-none dark:hover:bg-violet-500/10 dark:focus:bg-violet-500/10" @click="choosePantone(pantone)">
                                    <span class="h-9 w-9 shrink-0 rounded-lg border border-black/10 shadow-inner" :style="{ backgroundColor: pantone.hex || '#e5e7eb' }" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold text-slate-800 dark:text-zinc-100">{{ pantone.name }}</span>
                                        <span class="block truncate text-xs text-slate-500 dark:text-zinc-400">{{ pantone.color_category || 'Pantone reference' }}<span v-if="pantone.hex"> · {{ pantone.hex.toUpperCase() }}</span></span>
                                    </span>
                                    <Check v-if="String(pantone.id) === String(addColorForm.pantone_id)" class="h-4 w-4 shrink-0 text-violet-600" />
                                </button>
                                <p v-if="!filteredPantones.length" class="px-3 py-5 text-center text-sm text-slate-500 dark:text-zinc-400">No Pantone colors match your search.</p>
                            </div>
                        </div>
                    </div>
                    <fieldset class="space-y-2">
                        <legend class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Apply to yarn types</legend>
                        <label v-for="yarn in selectedColorProduct?.yarns || []" :key="yarn.id" class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-zinc-700">
                            <input v-model="addColorForm.yarn_ids" type="checkbox" :value="String(yarn.id)" class="rounded border-slate-300 text-violet-600 focus:ring-violet-500" />
                            <span>{{ yarn.name }}<span v-if="yarn.yarn_tier" class="text-slate-400"> · {{ yarn.yarn_tier }}</span></span>
                        </label>
                        <p v-if="!selectedColorProduct?.yarns?.length" class="text-sm text-amber-600">No yarn types are recorded for this product inquiry.</p>
                    </fieldset>
                    <p v-if="addColorError" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-950/30 dark:text-rose-300">{{ addColorError }}</p>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold dark:border-zinc-700" @click="addColorModal = false">Cancel</button>
                        <button type="submit" :disabled="addingColor || !addColorForm.pantone_id || !addColorForm.yarn_ids.length" class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ addingColor ? 'Sending…' : 'Send to CRM' }}</button>
                    </div>
                </form>
            </section>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FabricInquiryNotice from '@/Components/FabricInquiryNotice.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { ref, computed, nextTick, onMounted, watch } from 'vue';
import { useRealtimeConversation } from '@/composables/useRealtimeConversation';
import { ArrowLeft, Paperclip, Send, Loader2, FileText, X, Eye, Trash2, Package, Download, FlaskConical, ClipboardList, ChevronDown, Layers, Truck, ImagePlus, Palette, UploadCloud, Plus, Check, Search } from 'lucide-vue-next';

const props = defineProps({ inquiry: Object, quotations: Array, fabricOptions: { type: Array, default: () => [] }, sampleRequests: { type: Array, default: () => [] }, pantoneReferences: { type: Array, default: () => [] } });

const colorProductOptions = computed(() => {
    const selections = props.inquiry?.product_selections ?? {};
    const rows = Array.isArray(selections.products) && selections.products.length
        ? selections.products
        : selections.product_id || props.inquiry?.product_id ? [{ ...selections, product_id: selections.product_id ?? props.inquiry.product_id, name: props.inquiry?.product?.name }] : [];
    return rows.filter((row) => row.product_id && row.yarns?.length).map((row) => ({ ...row, name: row.name || props.inquiry?.product?.name || `Product ${row.product_id}` }));
});
const addColorModal = ref(false);
const addingColor = ref(false);
const addColorError = ref('');
const addColorForm = ref({ product_id: '', pantone_id: '', yarn_ids: [] });
const pantoneDropdownOpen = ref(false);
const pantoneSearch = ref('');
const selectedColorProduct = computed(() => colorProductOptions.value.find((product) => String(product.product_id) === String(addColorForm.value.product_id)) ?? colorProductOptions.value[0] ?? null);
const availablePantones = computed(() => {
    const requestedYarnIds = addColorForm.value.yarn_ids.map(Number);
    return props.pantoneReferences.filter((pantone) => {
        if ((selectedColorProduct.value?.pantones ?? []).some((color) => Number(color.id) === Number(pantone.id))) return false;
        return !(selectedColorProduct.value?.colorways ?? []).some((colorway) =>
            Number(colorway.pantone?.id ?? colorway.pantone_id) === Number(pantone.id)
            && requestedYarnIds.some((id) => (colorway.yarn_ids ?? []).map(Number).includes(id))
        );
    });
});
const filteredPantones = computed(() => {
    const query = pantoneSearch.value.trim().toLocaleLowerCase();
    if (!query) return availablePantones.value;
    return availablePantones.value.filter((pantone) => `${pantone.name ?? ''} ${pantone.color_category ?? ''} ${pantone.hex ?? ''}`.toLocaleLowerCase().includes(query));
});
const selectedPantone = computed(() => props.pantoneReferences.find((pantone) => String(pantone.id) === String(addColorForm.value.pantone_id)) ?? null);
const choosePantone = (pantone) => {
    addColorForm.value.pantone_id = String(pantone.id);
    pantoneDropdownOpen.value = false;
    pantoneSearch.value = '';
};
const openAddColor = () => {
    const first = colorProductOptions.value[0];
    addColorForm.value = { product_id: first ? String(first.product_id) : '', pantone_id: '', yarn_ids: [] };
    addColorError.value = '';
    pantoneDropdownOpen.value = false;
    pantoneSearch.value = '';
    addColorModal.value = true;
};
watch(() => addColorForm.value.product_id, () => { addColorForm.value.yarn_ids = []; addColorForm.value.pantone_id = ''; pantoneDropdownOpen.value = false; pantoneSearch.value = ''; });
watch(() => [...addColorForm.value.yarn_ids], () => {
    if (addColorForm.value.pantone_id && !availablePantones.value.some((pantone) => String(pantone.id) === String(addColorForm.value.pantone_id))) {
        addColorForm.value.pantone_id = '';
    }
}, { deep: true });
const submitAddColor = () => {
    if (addingColor.value) return;
    addingColor.value = true;
    addColorError.value = '';
    router.post(route('client.conversation.colors.store', props.inquiry.hash_key), addColorForm.value, {
        preserveScroll: true,
        onSuccess: () => { addColorModal.value = false; },
        onError: (errors) => { addColorError.value = Object.values(errors)[0] || 'Could not send this Pantone request.'; },
        onFinish: () => { addingColor.value = false; },
    });
};

// ── Fabric sample request ───────────────────────────────────────
let sampleFabricSetId = 0;
const makeSampleFabricSet = () => ({
    key: ++sampleFabricSetId,
    quotation_item_id: '',
    colors: [{ pantone_code: '', pantone_color: '', texture: '', gsm: '', reference_type: 'hex', color_hex: '#808080', color_image: null }],
});
const sampleForm = useForm({ fabric_designs: [makeSampleFabricSet()], request_method: 'carrier', carrier_name: '', reference_file: null, notes: '' });
const sampleRequestDetails = ref(null);
const openSampleRequest = () => {
    if (sampleRequestDetails.value) sampleRequestDetails.value.open = true;
};

const sampleMethods = [
    { value: 'carrier', label: 'Send by carrier', hint: 'Ship a physical swatch', icon: Truck },
    { value: 'image',   label: 'Upload a file',   hint: 'Image or PDF reference', icon: ImagePlus },
    { value: 'pantone', label: 'Pantone codes',   hint: 'Type the exact colors',  icon: Palette },
];

const sampleFileInput = ref(null);
const sampleDragging = ref(false);

const selectedSampleFabrics = computed(() => sampleForm.fabric_designs
    .map((set) => props.fabricOptions.find((fabric) => String(fabric.quotation_item_id) === String(set.quotation_item_id)))
    .filter(Boolean));

const sampleFilePreview = computed(() => {
    const f = sampleForm.reference_file;
    return f && f.type?.startsWith('image/') ? URL.createObjectURL(f) : null;
});

const canSubmitSample = computed(() => {
    if (sampleForm.processing || !sampleForm.fabric_designs.length) return false;
    const selectedIds = sampleForm.fabric_designs.map((set) => String(set.quotation_item_id));
    if (selectedIds.some((id) => !id) || new Set(selectedIds).size !== selectedIds.length) return false;
    if (sampleForm.request_method === 'carrier') return !!sampleForm.carrier_name.trim();
    if (sampleForm.request_method === 'image') return !!sampleForm.reference_file;
    return sampleForm.fabric_designs.every((set) => set.colors.length > 0
        && set.colors.every((color) => color.pantone_code.trim()
            && color.pantone_color.trim()
            && color.texture
            && Number.isInteger(Number(color.gsm))
            && Number(color.gsm) > 0
            && Number(color.gsm) <= 10000
            && (color.reference_type === 'hex'
                ? /^#[0-9a-f]{6}$/i.test(color.color_hex)
                : color.reference_type === 'image' && !!color.color_image)));
});

const onSampleFile = (file) => { if (file) sampleForm.reference_file = file; };
const onSampleColorImage = (event, color) => { color.color_image = event.target.files?.[0] ?? null; };
const onSampleDrop = (e) => { sampleDragging.value = false; onSampleFile(e.dataTransfer.files?.[0]); };
const clearSampleFile = () => { sampleForm.reference_file = null; if (sampleFileInput.value) sampleFileInput.value.value = ''; };
const formatBytes = (n) => (n < 1048576 ? `${Math.max(1, Math.round(n / 1024))} KB` : `${(n / 1048576).toFixed(1)} MB`);

const addSampleFabric = () => {
    if (sampleForm.fabric_designs.length < props.fabricOptions.length) sampleForm.fabric_designs.push(makeSampleFabricSet());
};
const removeSampleFabric = (index) => {
    if (sampleForm.fabric_designs.length > 1) sampleForm.fabric_designs.splice(index, 1);
};
const addSampleColor = (fabricIndex) => sampleForm.fabric_designs[fabricIndex].colors.push({ pantone_code: '', pantone_color: '', texture: '', gsm: '', reference_type: 'hex', color_hex: '#808080', color_image: null });
const removeSampleColor = (fabricIndex, colorIndex) => {
    const colors = sampleForm.fabric_designs[fabricIndex].colors;
    if (colors.length > 1) colors.splice(colorIndex, 1);
};
const submitFabricSample = () => sampleForm.post(route('client.conversation.sample-request', props.inquiry.hash_key), {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
        sampleForm.reset();
        clearSampleFile();
        if (sampleRequestDetails.value) sampleRequestDetails.value.open = false;
    },
});

const messagesContainer = ref(null);
const newMessage = ref('');
const fileInput = ref(null);
const selectedFiles = ref([]);

// ── Real-time conversation (no reload) ──────────────────────────
const liveMessages = ref([...(props.inquiry?.messages ?? [])]);
const clientSampleRequests = ref([...(props.sampleRequests ?? [])]);
watch(() => props.sampleRequests, (samples) => { clientSampleRequests.value = [...(samples ?? [])]; }, { deep: true });
const clientChatMessages = computed(() => liveMessages.value.filter((message) => !message.sample_request));

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
    suppressSuccessFeedback: true,
    onNewMessages: () => scrollToBottom(),
});

// Mobile panel toggle
const mobilePanel = ref('chat');

// Structured order submission based on quotation lines.
let poGroupKey = 0;
const makePoGroup = () => ({ key: ++poGroupKey, client_po_number: '', image: null, items: [] });
const poModal = ref({ show: false, orderGroups: [], notes: '', submitting: false });

const rejectModal = ref({ show: false, quotation: null, reason: '', requestNew: false, submitting: false });
const acceptModal = ref({ show: false, quotation: null, notes: '', submitting: false });
const showQuotationAccepted = ref(false);
const trashModal = ref({ show: false, quotation: null, notes: '', submitting: false });
const previewModal = ref({ show: false, title: '', files: [], activeFile: null });

// Quotation preview/download modal
const quotationPreviewModal = ref({ show: false, quotation: null, downloading: false });

// Dialog Modal State
const dialog = ref({ show: false, type: 'info', title: '', message: '', onConfirm: null });
const showDialog = (type, title, message, onConfirm = null) => { dialog.value = { show: true, type, title, message, onConfirm }; };
const handleDialogAction = () => { if (dialog.value.onConfirm) dialog.value.onConfirm(); dialog.value.show = false; };

// A quotation must be accepted by the client before it can be ordered.
const requestedColorwayPairs = computed(() => {
    const selections = props.inquiry?.product_selections ?? {};
    const rows = Array.isArray(selections.products) && selections.products.length ? selections.products : [selections];
    const pairs = new Map();
    rows.forEach((row) => {
        const productId = row.product_id ?? props.inquiry?.product_id;
        (row.pantones ?? []).forEach((pantone) => (row.yarns ?? []).forEach((yarn) => {
            const pair = { product_id: Number(productId), pantone_id: Number(pantone.id), pantone_name: pantone.name, yarn_id: Number(yarn.id), yarn_name: yarn.name || yarn.display_name || '' };
            pairs.set(`${pair.product_id}:${pair.pantone_id}:${pair.yarn_id}`, pair);
        }));
        (row.colorways ?? []).forEach((colorway) => (colorway.yarn_ids ?? []).forEach((yarnId) => {
            const pantoneId = Number(colorway.pantone?.id ?? colorway.pantone_id);
            const pantone = props.pantoneReferences.find((reference) => Number(reference.id) === pantoneId);
            const yarn = (row.yarns ?? []).find((entry) => Number(entry.id) === Number(yarnId));
            const pair = { product_id: Number(productId), pantone_id: pantoneId, pantone_name: colorway.pantone?.name || pantone?.name || '', yarn_id: Number(yarnId), yarn_name: yarn?.name || yarn?.display_name || '' };
            pairs.set(`${pair.product_id}:${pair.pantone_id}:${pair.yarn_id}`, pair);
        }));
    });
    return [...pairs.values()];
});
const requestedColorwayKeys = computed(() => requestedColorwayPairs.value.map((pair) => `${pair.product_id}:${pair.pantone_id}:${pair.yarn_id}`));
const hasRequestedColorways = computed(() => requestedColorwayKeys.value.length > 0);
const availablePoQuotations = computed(() =>
    (props.quotations ?? []).filter(q => q.status === 'accepted' && q.items?.length)
);
const approvedColorwayKeys = computed(() => new Set(clientSampleRequests.value
    .filter((sample) => sample.status === 'approved')
    .flatMap((sample) => (sample.pantone_colors ?? []).map((color) => `${Number(sample.product_id)}:${Number(color.pantone_reference_id)}:${Number(color.yarn_material_id)}`))));
const isPoQuotationEligible = (quotation) => !hasRequestedColorways.value
    || (quotation?.items ?? []).length > 0 && quotation.items.every((item) => {
        const pair = requestedColorwayPairs.value.find((candidate) =>
            candidate.product_id === Number(item.product_id)
            && candidate.yarn_id === Number(item.fabric_type_id)
            && String(candidate.pantone_name || '').trim().toLocaleLowerCase() === String(item.color || '').trim().toLocaleLowerCase()
        );
        return pair && approvedColorwayKeys.value.has(`${pair.product_id}:${pair.pantone_id}:${pair.yarn_id}`);
    });
const eligiblePoQuotations = computed(() => availablePoQuotations.value.filter(isPoQuotationEligible));
const canSendPurchaseOrder = computed(() => eligiblePoQuotations.value.length > 0);
const poMinimum = (line) => Number(line.product?.minimum_order_quantity ?? props.inquiry.product?.minimum_order_quantity ?? 0);
const poGroupLine = (groupIndex, quotationId, itemId) => poModal.value.orderGroups[groupIndex]?.items.find((item) => item.quotation_id === Number(quotationId) && item.quotation_item_id === Number(itemId));
const isPoLineSelected = (groupIndex, quotationId, itemId) => Boolean(poGroupLine(groupIndex, quotationId, itemId));
const isPoLineInOtherGroup = (groupIndex, quotationId, itemId) => poModal.value.orderGroups.some((group, index) => index !== groupIndex && group.items.some((item) => item.quotation_id === Number(quotationId) && item.quotation_item_id === Number(itemId)));
const togglePoLine = (groupIndex, quote, line, checked) => {
    const group = poModal.value.orderGroups[groupIndex];
    if (!group) return;
    const existingIndex = group.items.findIndex((item) => item.quotation_id === Number(quote.id) && item.quotation_item_id === Number(line.id));
    if (checked && existingIndex < 0) group.items.push({ quotation_id: Number(quote.id), quotation_item_id: Number(line.id), kilos: poMinimum(line) || 1 });
    if (!checked && existingIndex >= 0) group.items.splice(existingIndex, 1);
};
const addPoGroup = () => poModal.value.orderGroups.push(makePoGroup());
const removePoGroup = (index) => { if (poModal.value.orderGroups.length > 1) poModal.value.orderGroups.splice(index, 1); };
const setPoImage = (groupIndex, event) => { poModal.value.orderGroups[groupIndex].image = event.target.files?.[0] || null; };
const poSelectedLineCount = computed(() => poModal.value.orderGroups.reduce((sum, group) => sum + group.items.length, 0));
const poTotalKilos = computed(() => poModal.value.orderGroups.flatMap((group) => group.items).reduce((sum, item) => sum + (Number(item.kilos) || 0), 0));
const poEstimatedTotal = computed(() => poModal.value.orderGroups.reduce((sum, group) => sum + group.items.reduce((groupSum, selected) => {
    const quote = eligiblePoQuotations.value.find((item) => Number(item.id) === selected.quotation_id);
    const line = quote?.items.find((item) => Number(item.id) === selected.quotation_item_id);
    return groupSum + Math.round((Number(selected.kilos) || 0) * (Number(line?.unit_price) || 0) * 100) / 100;
}, 0), 0));
const poVatAmount = computed(() => Math.round(poModal.value.orderGroups.reduce((sum, group) => sum + group.items.reduce((groupSum, selected) => {
    const quote = eligiblePoQuotations.value.find((item) => Number(item.id) === selected.quotation_id);
    const line = quote?.items.find((item) => Number(item.id) === selected.quotation_item_id);
    const lineSubtotal = Math.round((Number(selected.kilos) || 0) * (Number(line?.unit_price) || 0) * 100) / 100;
    return groupSum + Math.round(lineSubtotal * 0.12 * 100) / 100;
}, 0), 0) * 100) / 100);
const poTotalWithVat = computed(() => poEstimatedTotal.value + poVatAmount.value);
const canSubmitPo = computed(() => !poModal.value.submitting && poModal.value.orderGroups.length > 0 && poModal.value.orderGroups.every((group) =>
    group.client_po_number.trim() && group.items.length > 0 && group.items.every((selected) => {
        const quote = eligiblePoQuotations.value.find((item) => Number(item.id) === selected.quotation_id);
        const line = quote?.items.find((item) => Number(item.id) === selected.quotation_item_id);
        return line && Number(selected.kilos) >= (poMinimum(line) || 0.01);
    })
));

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

const sampleStatusLabel = (s) => ({
    requested: 'Waiting for the Dyeing Lab',
    in_progress: 'Sample preparation in progress',
    awaiting_crm_review: 'Sample preparation in progress',
    forwarded: 'Delivery update sent',
    adjustment_requested: 'Reformulation in progress',
    crm_adjustment_review: 'Reformulation in progress',
    approved: 'Approved',
}[s] || (s ? String(s).replace(/_/g, ' ') : ''));

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

const noteSampleDelivery = (sampleId) => router.post(route('client.sample.noted', sampleId), {}, { preserveScroll: true });
const receiveSampleDelivery = (sampleId) => router.post(route('client.sample.received', sampleId), {}, { preserveScroll: true });

// ── Bulk delivery acknowledgement + swatch preview ──
const selectedSampleIds = ref([]);
const bulkProcessing = ref(false);
const swatchModal = ref({ show: false, url: '', title: '', isPdf: false });

const isSampleSelectable = (sample) => sample.status === 'forwarded' && !sample.client_received_at;
const selectableSamples = computed(() => clientSampleRequests.value.filter(isSampleSelectable));
const allSelectableSelected = computed(() => selectableSamples.value.length > 0
    && selectableSamples.value.every((sample) => selectedSampleIds.value.includes(sample.id)));

const toggleSampleSelection = (sampleId) => {
    const index = selectedSampleIds.value.indexOf(sampleId);
    if (index === -1) selectedSampleIds.value.push(sampleId);
    else selectedSampleIds.value.splice(index, 1);
};

const toggleSelectAllSamples = () => {
    selectedSampleIds.value = allSelectableSelected.value ? [] : selectableSamples.value.map((sample) => sample.id);
};

const openSwatchModal = (sample) => {
    if (!sample.sample_image_path) return;
    swatchModal.value = {
        show: true,
        url: getFullUrl(sample.sample_image_path),
        title: sample.code || sample.fabric_name || 'Swatch',
        isPdf: String(sample.sample_image_path).toLowerCase().endsWith('.pdf'),
    };
};

const bulkAcknowledgeSamples = (routeName) => {
    if (!selectedSampleIds.value.length || bulkProcessing.value) return;
    bulkProcessing.value = true;
    router.post(route(routeName), { sample_ids: [...selectedSampleIds.value] }, {
        preserveScroll: true,
        onSuccess: () => { selectedSampleIds.value = []; },
        onFinish: () => { bulkProcessing.value = false; },
    });
};
const bulkNoteSamples = () => bulkAcknowledgeSamples('client.sample.bulk-noted');
const bulkReceiveSamples = () => bulkAcknowledgeSamples('client.sample.bulk-received');

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
            showDialog('success', 'Sent', 'Adjustment requested. The Dyeing Lab will prepare a revised swatch.');
        },
        onError: (errors) => {
            adjustModal.value.submitting = false;
            showDialog('error', 'Error', errors.message || 'Failed to send adjustment request.');
        }
    });
};

// PO Modal functions
const openPoModal = () => {
    if (!eligiblePoQuotations.value.length) return;
    poModal.value = {
        show: true,
        orderGroups: [makePoGroup()],
        notes: '',
        submitting: false,
    };
};

const submitPo = () => {
    if (!canSubmitPo.value) return;
    poModal.value.submitting = true;
    router.post(route('client.conversation.send-po', props.inquiry.hash_key), {
        order_groups: poModal.value.orderGroups.map((group) => ({
            client_po_number: group.client_po_number.trim(),
            image: group.image,
            items: group.items,
        })),
        notes: poModal.value.notes || '',
    }, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            poModal.value = { show: false, orderGroups: [], notes: '', submitting: false };
            showDialog('success', 'Order submitted', 'Your purchase order has been sent to the team for review.');
            scrollToBottom();
        },
        onError: (errors) => {
            showDialog('error', 'Order could not be submitted', errors.message || Object.values(errors)[0] || 'Please check the quantities and try again.');
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
            onSuccess: () => {
                acceptModal.value.show = false;
                showQuotationAccepted.value = true;
            },
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

        const fabricGroups = groupItemsByFabricType(quotation.items);
        Object.entries(fabricGroups).forEach(([fabricType, items]) => {
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(8.5);
            doc.setTextColor(26, 26, 46);
            doc.text(fabricType, marginL, y);
            y += 2;

            const tableBody = items.map((item) => [
                `${item.fabric || ''}${item.design ? ` — ${item.design}` : ''}`,
                item.color,
                `${parseFloat(item.kilos).toFixed(2)}`,
                `PHP ${formatPrice(item.unit_price)}`,
                ...(quotation.vat_type === 'inclusive' ? [`PHP ${formatPrice(Number(item.unit_price) * 1.12)}`] : []),
            ]);

            doc.autoTable({
                startY: y,
                head: [quotation.vat_type === 'inclusive'
                    ? ['Product / Design', 'Color', 'MOQ (kg)', 'Original Price/kg', 'Price + 12% VAT/kg']
                    : ['Product / Design', 'Color', 'MOQ (kg)', 'Price/kg']],
                body: tableBody,
                theme: 'grid',
                headStyles: { fillColor: [240, 240, 240], textColor: [0, 0, 0], fontStyle: 'bold', fontSize: 8, halign: 'center' },
                bodyStyles: { fontSize: 8, textColor: [30, 30, 30] },
                alternateRowStyles: { fillColor: [250, 250, 250] },
                margin: { left: marginL, right: marginR },
                columnStyles: quotation.vat_type === 'inclusive'
                    ? { 0: { cellWidth: 52, halign: 'left' }, 1: { cellWidth: 30, halign: 'center' }, 2: { cellWidth: 18, halign: 'center' }, 3: { cellWidth: 35, halign: 'right' }, 4: { cellWidth: 35, halign: 'right' } }
                    : { 0: { cellWidth: 74, halign: 'left' }, 1: { cellWidth: 42, halign: 'center' }, 2: { cellWidth: 22, halign: 'center' }, 3: { cellWidth: 32, halign: 'right' } },
            });

            y = doc.lastAutoTable.finalY + 6;
        });

        if (y > pageH - 65) {
            doc.addPage();
            y = 24;
        }

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
const formatStatus = (s) => {
    if (!s) return 'Open';
    const t = String(s).replace(/_/g, ' ').toLowerCase();
    return t.charAt(0).toUpperCase() + t.slice(1);
};
const openUrl      = (url) => window.open(url, '_blank');

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

const groupItemsByFabric = (items) =>
    (items || []).reduce((acc, item) => {
        const key = item.fabric_type || item.fabric;
        (acc[key] = acc[key] || []).push(item);
        return acc;
    }, {});

const groupItemsByFabricType = (items) =>
    (items || []).reduce((acc, item) => {
        const type = item.fabric_type || item.fabric;
        (acc[type] = acc[type] || []).push(item);
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
.card-enter-active { transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.22, 1, 0.36, 1); }
.card-enter-from { opacity: 0; transform: translateY(8px); }
.card-leave-active { transition: opacity 0.2s ease; }
.card-leave-to { opacity: 0; }
.card-move { transition: transform 0.3s ease; }
.modal-enter-active { transition: opacity 0.25s ease; }
.modal-enter-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from { opacity: 0; }
.modal-enter-from > div { transform: scale(0.92) translateY(14px); opacity: 0; }
.modal-leave-active { transition: opacity 0.2s ease; }
.modal-leave-to { opacity: 0; }
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

/* Render the sample request as a viewport-level dialog so page overflow cannot clip it. */
details[open].group {
    position: fixed;
    top: 50%;
    left: 50%;
    z-index: 60;
    width: min(48rem, calc(100vw - 2rem));
    height: min(90dvh, 52rem);
    max-height: calc(100dvh - 2rem);
    min-height: 0;
    transform: translate(-50%, -50%);
    display: block;
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
    scrollbar-gutter: stable;
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28), 0 0 0 100vmax rgba(15, 23, 42, 0.48);
}
details[open].group > form {
    overflow: visible;
}

/* Woven-thread texture behind the fabric sample bar */
.weave {
    background-image:
        repeating-linear-gradient(0deg,  rgba(16, 185, 129, 0.10) 0 1px, transparent 1px 6px),
        repeating-linear-gradient(90deg, rgba(16, 185, 129, 0.10) 0 1px, transparent 1px 6px),
        linear-gradient(100deg, rgba(209, 250, 229, 0.9), rgba(255, 255, 255, 0) 70%);
}
</style>
