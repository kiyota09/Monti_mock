<template>
    <AuthenticatedLayout>
        <!-- START SCREEN LOADING OVERLAY -->
        <Transition name="fade">
            <div
                v-if="pageLoading"
                class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-100/50 backdrop-blur-md"
                aria-live="polite"
            >
                <div class="flex flex-col items-center gap-3">
                    <div class="w-10 h-10 rounded-full border-4 border-slate-300 border-t-blue-600 animate-spin"></div>
                    <p class="text-sm font-semibold text-slate-600">Loading template builder...</p>
                </div>
            </div>
        </Transition>

        <div class="flex min-h-screen bg-gradient-to-br from-slate-100 via-slate-50 to-blue-50">
            <div class="flex-1 p-6 lg:p-8 text-slate-900 font-sans min-w-0 relative">
                <div class="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,transparent,black,transparent)] pointer-events-none"></div>

                <!-- HEADER -->
                <header class="mb-6 relative">
                    <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3">
                        <Link :href="route('hrm.onboarding.templates.index')" class="hover:text-slate-700 transition-colors inline-flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Onboarding Templates
                        </Link>
                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span class="text-slate-800 font-semibold">{{ template.name }}</span>
                    </nav>

                    <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center text-white shadow-lg">
                                <LayoutTemplate class="w-5 h-5" />
                            </div>
                            <div>
                                <h1 class="text-2xl lg:text-3xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent flex items-center gap-2">
                                    {{ template.name }}
                                    <span v-if="template.is_default" class="bg-amber-50 text-amber-600 border-amber-200 px-2 py-0.5 rounded-full text-[10px] font-bold border">DEFAULT</span>
                                </h1>
                                <div class="flex flex-wrap items-center gap-2 mt-1">
                                    <span class="text-[11px] text-slate-400">{{ template.code }} · v{{ template.version }}</span>
                                    <span :class="['inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-semibold border shadow-sm', template.status_badge]">
                                        {{ template.status }}
                                    </span>
                                    <span class="text-[11px] text-slate-400">{{ template.applicability }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                @click="openEditModal"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-semibold transition-all duration-200 shadow-sm hover:shadow-md"
                            >
                                <Pencil class="w-3.5 h-3.5" /> Edit Template
                            </button>
                            <button
                                @click="duplicateTemplate"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-semibold transition-all duration-200 shadow-sm hover:shadow-md"
                            >
                                <Copy class="w-3.5 h-3.5" /> Duplicate
                            </button>
                            <button
                                v-if="template.status === 'Active'"
                                @click="setStatus('Inactive')"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-amber-200 hover:bg-amber-50 text-amber-700 rounded-xl text-xs font-semibold transition-all duration-200 shadow-sm"
                            >
                                <PauseCircle class="w-3.5 h-3.5" /> Deactivate
                            </button>
                            <button
                                v-else
                                @click="setStatus('Active')"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-500 hover:to-emerald-600 text-white rounded-xl text-xs font-bold transition-all duration-200 shadow-lg"
                            >
                                <PlayCircle class="w-3.5 h-3.5" /> Activate
                            </button>
                            <button
                                @click="openAddItemModal"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-xs font-bold transition-all duration-200 shadow-lg hover:shadow-xl"
                            >
                                <Plus class="w-3.5 h-3.5" /> Add Item
                            </button>
                        </div>
                    </div>
                </header>

                <!-- THREAT FIELDS OVERVIEW -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5 relative">
                    <div class="bg-white rounded-xl border border-slate-200/60 shadow-md backdrop-blur-sm p-3.5">
                        <div class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-1">Department</div>
                        <div class="text-xs font-semibold text-slate-700">{{ template.department || 'Any' }}</div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200/60 shadow-md backdrop-blur-sm p-3.5">
                        <div class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-1">Position</div>
                        <div class="text-xs font-semibold text-slate-700">{{ template.position || 'Any' }}</div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200/60 shadow-md backdrop-blur-sm p-3.5">
                        <div class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-1">Employment Type</div>
                        <div class="text-xs font-semibold text-slate-700">{{ template.employment_type || 'Any' }}</div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200/60 shadow-md backdrop-blur-sm p-3.5">
                        <div class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-1">Work Mode</div>
                        <div class="text-xs font-semibold text-slate-700">{{ template.work_mode || 'Any' }}</div>
                    </div>
                </div>

                <!-- ITEMS BY CATEGORY -->
                <div class="space-y-5 relative">
                    <div
                        v-for="(items, category) in itemGroups"
                        :key="category"
                        class="bg-white rounded-xl border border-slate-200/60 shadow-md backdrop-blur-sm overflow-hidden"
                    >
                        <div class="px-4 py-3 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-transparent flex items-center justify-between">
                            <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                <span class="w-1 h-4 rounded-full" :class="sectionColor(category)"></span>{{ category }}
                                <span class="text-[9px] font-semibold text-slate-400 bg-slate-50 px-1.5 py-0.5 rounded border border-slate-100">{{ items.length }}</span>
                            </h3>
                            <button
                                @click="openAddItemModal(category)"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 rounded-lg text-[10px] font-semibold transition-all duration-200"
                            >
                                <Plus class="w-3 h-3" /> Add
                            </button>
                        </div>
                        <div class="p-4 space-y-2">
                            <div
                                v-for="item in items"
                                :key="item.id"
                                class="group flex flex-col md:flex-row md:items-center gap-3 p-3 rounded-xl border border-slate-100 hover:border-blue-100 hover:bg-blue-50/20 transition-all"
                            >
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs font-medium text-slate-700 flex flex-wrap items-center gap-2">
                                        <span class="text-[10px] text-slate-300 font-bold">{{ item.sort_order + 1 }}</span>
                                        {{ item.name }}
                                        <span :class="item.is_required ? 'bg-blue-50 text-blue-600 border-blue-200' : 'bg-slate-50 text-slate-400 border-slate-200'" class="px-1.5 py-0.5 rounded text-[9px] font-bold border">
                                            {{ item.is_required ? 'REQUIRED' : 'OPTIONAL' }}
                                        </span>
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold border bg-indigo-50 text-indigo-600 border-indigo-200">{{ inputTypeOptions.find(t => t.value === item.input_type)?.label || item.input_type }}</span>
                                        <span v-if="!item.applicant_visible" class="px-1.5 py-0.5 rounded text-[9px] font-bold border bg-slate-50 text-slate-500 border-slate-200">INTERNAL</span>
                                        <span v-if="!item.is_active" class="px-1.5 py-0.5 rounded text-[9px] font-bold border bg-slate-100 text-slate-400 border-slate-200">DISABLED</span>
                                    </div>
                                    <p v-if="item.description && item.category !== 'HR Processing'" class="text-[10px] text-slate-400 mt-0.5">{{ item.description }}</p>
                                    <!-- HR task: parent name stays on top, contents render as an organized list below -->
                                    <div v-else-if="item.description" class="mt-1.5 rounded-lg bg-slate-50/80 border border-slate-100 px-3 py-2">
                                        <p v-if="descHead(item.description)" class="text-[10px] text-slate-400">{{ descHead(item.description) }}</p>
                                        <ul v-if="descBullets(item.description).length" class="mt-1 space-y-1">
                                            <li v-for="(line, li) in descBullets(item.description)" :key="li" class="flex items-start gap-1.5 text-[10px] text-slate-500">
                                                <span class="mt-1 w-1 h-1 rounded-full bg-violet-400 shrink-0"></span>
                                                <span>{{ line }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                    <div v-if="item.due_rule_type !== 'none'" class="flex flex-wrap items-center gap-2 mt-1 text-[9px] text-slate-400">
                                        <span>Due: {{ dueRuleLabel(item) }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button
                                        @click="openEditItemModal(item)"
                                        class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                        title="Edit item"
                                    >
                                        <Pencil class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        @click="toggleItemActive(item)"
                                        class="p-1.5 rounded-lg transition-colors"
                                        :class="item.is_active ? 'text-slate-400 hover:text-amber-600 hover:bg-amber-50' : 'text-amber-500 hover:text-slate-500 hover:bg-slate-50'"
                                        :title="item.is_active ? 'Disable item' : 'Enable item'"
                                    >
                                        <Power class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        @click="deleteItem(item)"
                                        class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                                        title="Remove item"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                            <div v-if="items.length === 0" class="text-center py-4">
                                <p class="text-xs text-slate-400">No items in this section yet.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ITEM EDITOR NOTE -->
                <div class="mt-5 text-[10px] text-slate-400 relative bg-white/60 border border-slate-100 rounded-xl px-4 py-3">
                    <Info class="w-3.5 h-3.5 inline-block mr-1 text-blue-500" />
                    Changing a template never alters existing onboarding snapshots. New hires created after the change will use the updated definition.
                </div>
            </div>
        </div>

        <!-- EDIT TEMPLATE MODAL -->
        <div v-if="activeModal === 'edit'" class="fixed inset-0 z-[80] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeModal">
            <div class="bg-white rounded-2xl border border-slate-200/60 w-full max-w-xl overflow-hidden shadow-2xl">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-transparent">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Edit Template</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Update header and applicability rules.</p>
                    </div>
                    <button @click="closeModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all"><X class="w-4 h-4" /></button>
                </div>
                <div class="p-5 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Code</label>
                            <input v-model="editForm.code" type="text" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Name <span class="text-rose-500">*</span></label>
                            <input v-model="editForm.name" type="text" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Description</label>
                        <textarea v-model="editForm.description" rows="2" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Department</label>
                            <select v-model="editForm.department_id" :disabled="saving" @change="onDepartmentChange" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm">
                                <option value="">Any</option>
                                <option v-for="d in optionDepartments" :key="d.id" :value="d.id">{{ d.name }} ({{ positionCount(d.id) }})</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Employment Type</label>
                            <select v-model="editForm.employment_type_id" :disabled="saving" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm">
                                <option value="">Any</option>
                                <option v-for="t in optionEmploymentTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Position</label>
                            <select v-model="editForm.position_id" :disabled="saving" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm">
                                <option value="">Any</option>
                                <option v-for="p in filteredPositions" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                            <p v-if="optionPositions.length && filteredPositions.length === 0" class="text-[10px] text-amber-600 font-medium mt-1">No positions under {{ selectedDepartmentName }} — add one under HRM → Positions first, or keep Any.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Work Mode</label>
                            <select v-model="editForm.work_mode" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm">
                                <option value="">Any</option>
                                <option v-for="m in workModes" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Duration (days)</label>
                        <input v-model.number="editForm.duration_days" type="number" min="1" max="3650" placeholder="e.g. 7" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm" />
                        <p class="text-[10px] text-slate-400 mt-1">Onboarding countdown starts when the applicant opens their onboarding page. They are reminded 3 days before it ends. Leave blank for no deadline.</p>
                    </div>
                    <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                        <input v-model="editForm.is_default" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                        Make this the default template
                    </label>
                </div>
                <div class="p-4 border-t border-slate-100 bg-gradient-to-r from-slate-50 to-transparent flex items-center justify-end gap-3">
                    <button @click="closeModal" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-all">Cancel</button>
                    <button @click="submitEdit" :disabled="saving" class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-sm font-bold transition-all shadow-lg">
                        {{ saving ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ADD / EDIT ITEM MODAL -->
        <div v-if="activeModal === 'add-item' || activeModal === 'edit-item'" class="fixed inset-0 z-[80] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeModal">
            <div class="bg-white rounded-2xl border border-slate-200/60 w-full max-w-2xl overflow-hidden shadow-2xl max-h-[92vh] flex flex-col">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-transparent shrink-0">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">{{ activeModal === 'edit-item' ? (isHrTask ? 'Edit HR Task' : 'Edit Item') : (isHrTask ? 'HR Task' : 'Add Onboarding Item') }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Define the task or requirement that new hires must complete.</p>
                    </div>
                    <button @click="closeModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition-all"><X class="w-4 h-4" /></button>
                </div>
                <div class="p-5 space-y-4 overflow-y-auto">
                    <div class="grid grid-cols-2 gap-4">
                        <div :class="isHrTask ? 'col-span-2' : ''">
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">{{ isHrTask ? 'Task name' : 'Name' }} <span class="text-rose-500">*</span></label>
                            <input v-model="itemForm.name" type="text" :placeholder="isHrTask ? 'e.g. Verify NBI clearance' : 'e.g. NBI Clearance'" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm" />
                        </div>
                        <!-- HR add mode: task contents (children of the task name above) -->
                        <div v-if="isHrTask && activeModal === 'add-item'" class="col-span-2 space-y-2">
                            <label class="block text-xs font-semibold text-slate-600">Task content</label>
                            <div v-for="(_, i) in hrTaskContents" :key="i" class="flex items-center gap-2">
                                <input v-model="hrTaskContents[i]" type="text" :placeholder="`Content ${i + 1} — e.g. Collect the ID copy`" class="flex-1 px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm" />
                                <button type="button" @click="hrTaskContents.splice(i, 1)" title="Remove this content" class="p-2.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 border border-slate-200 rounded-xl transition-colors shrink-0">
                                    <X class="w-3.5 h-3.5" />
                                </button>
                            </div>
                            <button type="button" @click="hrTaskContents.push('')" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-dashed border-blue-300 hover:bg-blue-50 text-blue-700 rounded-xl text-xs font-bold transition-all">
                                <Plus class="w-3.5 h-3.5" /> Add Task
                            </button>
                        </div>
                        <div v-if="!isHrTask">
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Category</label>
                            <select v-model="itemForm.category" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm">
                                <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
<div v-if="!isHrTask" class="grid grid-cols-2 gap-4">
                             <div>
                                 <label class="block text-xs font-semibold text-slate-600 mb-1.5">Input Type</label>
                                 <select v-model="itemForm.input_type" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm">
                                     <option v-for="t in inputTypeOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
                                 </select>
                                  <p class="text-[10px] text-slate-400 mt-1">{{ inputTypeHint }}</p>
                              </div>
                             <div>
                                 <label class="block text-xs font-semibold text-slate-600 mb-1.5">Reference File <span class="text-slate-400 font-normal">(optional)</span></label>
                                 <input
                                     ref="itemFileInput"
                                     type="file"
                                     @change="onItemFileChange"
                                     accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                                     class="block w-full text-[11px] text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                 />
                                 <p v-if="itemFileName" class="text-[10px] text-slate-400 mt-1 truncate">Selected: {{ itemFileName }}</p>
                                 <p v-else-if="itemForm.attachment_name && !removeAttachment" class="text-[10px] text-slate-400 mt-1 truncate">
                                     Current:
                                     <a :href="itemForm.attachment_url" target="_blank" class="text-blue-600 hover:underline">{{ itemForm.attachment_name }}</a>
                                     <button type="button" @click="removeAttachment = true" class="text-rose-500 hover:underline ml-1">Remove</button>
                                 </p>
                                 <p v-else class="text-[10px] text-slate-400 mt-1">Attach a file HR can download (PDF/DOC/XLS/image, max 10MB).</p>
                             </div>
                          </div>
                    </div>
                    <div v-if="itemForm.input_type === 'select'">
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Dropdown Options <span class="text-rose-500">*</span></label>
                        <textarea v-model="itemOptionsText" rows="3" placeholder="One option per line, e.g.&#10;Single&#10;Married&#10;Widowed" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm"></textarea>
                        <p class="text-[10px] text-slate-400 mt-1">Applicants will pick one of these in the portal. Separate with new lines or commas.</p>
                        <div v-if="parsedItemOptions.length" class="flex flex-wrap gap-1.5 mt-2">
                            <span v-for="opt in parsedItemOptions" :key="opt" class="px-2 py-0.5 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200 text-[10px] font-semibold">{{ opt }}</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Description</label>
                        <textarea v-model="itemForm.description" rows="2" placeholder="What does the new hire need to do?" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Due Rule</label>
                            <select v-model="itemForm.due_rule_type" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm">
                                <option value="none">No deadline</option>
                                <option value="specific_date">Specific date</option>
                                <option value="days_after_start">Days after start</option>
                                <option value="days_before_start">Days before start</option>
                            </select>
                        </div>
                        <div v-if="itemForm.due_rule_type !== 'none'">
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">
                                {{ itemForm.due_rule_type === 'specific_date' ? 'Date' : 'Days' }}
                            </label>
                            <input
                                v-model="itemForm.due_rule_value"
                                :type="itemForm.due_rule_type === 'specific_date' ? 'date' : 'number'"
                                :placeholder="itemForm.due_rule_type === 'specific_date' ? 'YYYY-MM-DD' : 'e.g. 7'"
                                class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm"
                            />
                        </div>
                        <div v-else>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Sort Order</label>
                            <input v-model="itemForm.sort_order" type="number" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm" />
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                            <input v-model="itemForm.is_required" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                            Required
                        </label>
                        <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                            <input v-model="itemForm.applicant_visible" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                            Visible to applicant
                        </label>
                        <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                            <input v-model="itemForm.is_active" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                            Active
                        </label>
                    </div>
                    <div class="bg-blue-50/50 border border-blue-100 rounded-xl px-3 py-2 text-[10px] text-slate-500">
                        <Info class="w-3 h-3 inline-block mr-1 text-blue-500" />
                        Task/Verification items are completed directly by HR. All other input types are submitted by the applicant and reviewed / verified by HR.
                    </div>
                </div>
                <div class="p-4 border-t border-slate-100 bg-gradient-to-r from-slate-50 to-transparent flex items-center justify-end gap-3 shrink-0">
                    <button @click="closeModal" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-all">Cancel</button>
                    <button @click="submitItem" :disabled="saving" class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-sm font-bold transition-all shadow-lg">
                        {{ saving ? 'Saving...' : (isHrTask ? 'Save Task' : (activeModal === 'edit-item' ? 'Save Item' : 'Add Item')) }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Swal from 'sweetalert2';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import {
    LayoutTemplate, Pencil, Copy, Plus, Trash2, PauseCircle, PlayCircle,
    ChevronUp, ChevronDown, Power, X, Info,
} from 'lucide-vue-next';

const props = defineProps({
    template: { type: Object, required: true },
    inputTypes: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    responsibleParties: { type: Array, default: () => [] },
    activityTypes: { type: Array, default: () => [] },
    activityMethods: { type: Array, default: () => ['On-site', 'Online', 'Hybrid'] },
    options: { type: Object, default: () => ({ departments: [], positions: [], employment_types: [], work_modes: [] }) },
});

const activity_definitions = computed(() =>
    (props.template.activities || []).slice().sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
);

// Backend sends a flat `items` array (no `item_groups`), so group them here.
// Seeded from the category list so empty sections still render. Reactive to
// Inertia prop refreshes, so newly added items appear immediately.
const itemGroups = computed(() => {
    const groups = {};
    (props.categories || []).forEach((c) => { groups[c] = []; });
    (props.template.items || []).forEach((i) => {
        const cat = i.category || 'Applicant Requirements';
        if (!groups[cat]) groups[cat] = [];
        groups[cat].push(i);
    });
    Object.values(groups).forEach((arr) => arr.sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)));
    return groups;
});

const page = usePage();
const pageLoading = ref(true);
const saving = ref(false);
const activeModal = ref(null);

const optionDepartments = computed(() => props.options.departments || []);
const optionPositions = computed(() => props.options.positions || []);
const optionEmploymentTypes = computed(() => props.options.employment_types || []);
const workModes = computed(() => props.options.work_modes || ['On-site', 'Remote', 'Hybrid']);

const filteredPositions = computed(() => {
    if (!editForm.value.department_id) return optionPositions.value;
    return optionPositions.value.filter(p => p.department_id != null && String(p.department_id) === String(editForm.value.department_id));
});

const positionCount = (deptId) => optionPositions.value.filter(p => p.department_id != null && String(p.department_id) === String(deptId)).length;

const selectedDepartmentName = computed(() => {
    const found = optionDepartments.value.find(d => String(d.id) === String(editForm.value.department_id));
    return found ? found.name : 'this department';
});

const onDepartmentChange = () => {
    if (!filteredPositions.value.some(p => String(p.id) === String(editForm.value.position_id))) {
        editForm.value.position_id = '';
    }
};

onMounted(() => {
    setTimeout(() => { pageLoading.value = false; }, 150);
});

watch(() => page.props.flash, (flash) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
}, { immediate: true });

watch(() => page.props.errors, (errors) => {
    if (Object.keys(errors).length > 0) {
        toast.error(String(Object.values(errors)[0]));
    }
}, { deep: true });

const sectionColor = (section) =>
        ({
            'Applicant Requirements': 'bg-blue-500',
            'HR Processing': 'bg-violet-500',
            'Company Preparation': 'bg-emerald-500',
        })[section] || 'bg-slate-400';

    const inputTypeOptions = computed(() => {
        return [
            { value: 'text', label: 'Text — applicant types an answer' },
            { value: 'file', label: 'Document Upload — applicant uploads PDF/JPG/PNG' },
            { value: 'date', label: 'Date — applicant picks a date' },
            { value: 'checkbox', label: 'Checkbox — applicant ticks to confirm' },
            { value: 'select', label: 'Select Dropdown — applicant picks one option' },
        ];
    });

    const inputTypeHint = computed(() => {
        switch (itemForm.value.input_type) {
            case 'text': return 'Applicant sees a text box and a Submit button.';
            case 'file': return 'Applicant sees an Upload Document button (PDF/JPG/PNG, max 5MB).';
            case 'date': return 'Applicant sees a date picker and a Submit button.';
            case 'checkbox': return 'Applicant sees a confirmation checkbox and a Submit button.';
            case 'select': return 'Applicant sees a dropdown with the options below.';
            default: return '';
        }
    });

    const parseOptionsText = (text) =>
        String(text || '').split(/[\r\n,]+/).map((o) => o.trim()).filter((o, i, arr) => o !== '' && arr.indexOf(o) === i);

    const itemOptionsText = ref('');
    const parsedItemOptions = computed(() => parseOptionsText(itemOptionsText.value));

    const dueRuleLabel = (item) => {
    if (item.due_rule_type === 'specific_date') return item.due_rule_value;
    if (item.due_rule_type === 'days_after_start') return item.due_rule_value + ' days after start';
    if (item.due_rule_type === 'days_before_start') return item.due_rule_value + ' days before start';
    return '';
};

// HR task display: split a description into its intro text (parent context)
// and "- " bullet lines (task contents) so each renders on its own line.
const descLines = (text) => String(text ?? '').split('\n').map((l) => l.trim()).filter((l) => l !== '');
const descBullets = (text) => descLines(text).filter((l) => l.startsWith('- ')).map((l) => l.slice(2).trim()).filter((l) => l !== '');
const descHead = (text) => descLines(text).filter((l) => !l.startsWith('- ')).join(' ');

// ============ TEMPLATE EDIT ============
const editForm = ref({ code: '', name: '', description: '', department_id: '', position_id: '', employment_type_id: '', work_mode: '', is_default: false });

const openEditModal = () => {
    editForm.value = {
        code: props.template.code || '',
        name: props.template.name || '',
        description: props.template.description || '',
        department_id: props.template.department_id || '',
        position_id: props.template.position_id || '',
        employment_type_id: props.template.employment_type_id || '',
        work_mode: props.template.work_mode || '',
        is_default: props.template.is_default,
    };
    activeModal.value = 'edit';
};

const submitEdit = () => {
    if (!editForm.value.name.trim()) {
        toast.error('Template name is required.');
        return;
    }
    saving.value = true;
    router.put(route('hrm.onboarding.templates.update', props.template.id), editForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            toast.success('Template updated.');
        },
        onError: () => toast.error('Failed to update template.'),
        onFinish: () => { saving.value = false; },
    });
};

const setStatus = (status) => {
    const apply = () => {
        router.post(route('hrm.onboarding.templates.set-status', props.template.id), { status }, {
            preserveScroll: true,
            onSuccess: () => toast.success('Template marked as ' + status + '.'),
            onError: () => toast.error('Failed to update template status.'),
        });
    };

    if (status !== 'Inactive') {
        apply();
        return;
    }

    Swal.fire({
        title: 'Deactivate template?',
        text: props.template.name + ' will no longer be used for new hires until it is activated again.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Deactivate',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#d97706',
        customClass: { popup: 'rounded-[2rem]' },
    }).then((result) => {
        if (result.isConfirmed) apply();
    });
};

const duplicateTemplate = () => {
    Swal.fire({
        title: 'Duplicate template?',
        text: props.template.name + ' will be copied with all its active items.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Duplicate',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#2563eb',
        customClass: { popup: 'rounded-[2rem]' },
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('hrm.onboarding.templates.duplicate', props.template.id), {}, {
                onSuccess: () => toast.success('Template duplicated.'),
                onError: () => toast.error('Failed to duplicate template.'),
            });
        }
    });
};

// ============ ITEM FORMS ============
const normaliseItemOptions = (raw) => {
    if (Array.isArray(raw)) return raw.map((o) => String(o ?? '').trim()).filter((o, i, arr) => o !== '' && arr.indexOf(o) === i);
    if (typeof raw === 'string') return parseOptionsText(raw);
    return [];
};

const emptyItemForm = () => ({
        id: null,
        category: 'Applicant Requirements',
        name: '',
        description: '',
        input_type: 'file',
        responsible_party: 'Applicant',
        is_required: true,
        applicant_visible: true,
        is_active: true,
        due_rule_type: 'none',
        due_rule_value: '',
        sort_order: null,
        attachment_name: null,
        attachment_url: null,
    });

const itemForm = ref(emptyItemForm());

// HR reference file attached beside the Input Type field.
const itemFile = ref(null);
const itemFileName = ref('');
const removeAttachment = ref(false);
const itemFileInput = ref(null);

const onItemFileChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    itemFile.value = file;
    itemFileName.value = file ? file.name : '';
    if (file) removeAttachment.value = false;
};

const resetItemAttachment = () => {
    itemFile.value = null;
    itemFileName.value = '';
    removeAttachment.value = false;
    if (itemFileInput.value) itemFileInput.value.value = '';
};

// HR add mode: task content inputs (children of the Task name above).
// Each "Add Task" click appends one content input; all filled contents
// are saved as bullet lines inside the item description.
const hrTaskContents = ref([]);

// HR Processing items are HR-owned tasks: the modal shows "Task name",
// hides the Category + Input Type dropdowns (category preset by the
// section's Add button, type keeps its default), the "Add Task" button
// appends content inputs saved as description bullets, and submit reads
// "Save Task".
const isHrTask = computed(() => itemForm.value.category === 'HR Processing');

const openAddItemModal = (category = 'Applicant Requirements') => {
    itemForm.value = { ...emptyItemForm(), category };
    itemOptionsText.value = '';
    hrTaskContents.value = [];
    resetItemAttachment();
    activeModal.value = 'add-item';
};

const openEditItemModal = (item) => {
    itemForm.value = {
        id: item.id,
        category: item.category,
        name: item.name,
        description: item.description,
        input_type: item.input_type,
        responsible_party: item.responsible_party,
        is_required: item.is_required,
        applicant_visible: item.applicant_visible,
        is_active: item.is_active,
        due_rule_type: item.due_rule_type,
        due_rule_value: item.due_rule_value,
        sort_order: item.sort_order,
        attachment_name: item.attachment_name,
        attachment_url: item.attachment_url,
    };
    itemOptionsText.value = normaliseItemOptions(item.options).join('\n');
    resetItemAttachment();
    activeModal.value = 'edit-item';
};

const submitItem = () => {
    if (!itemForm.value.name.trim()) {
        toast.error('Item name is required.');
        return;
    }
    if (itemForm.value.input_type === 'select' && parsedItemOptions.value.length === 0) {
        toast.error('Add at least one dropdown option for select items.');
        return;
    }
    const fields = { ...itemForm.value };
    delete fields.id;
    delete fields.attachment_name;
    delete fields.attachment_url;
    if (fields.sort_order === null) delete fields.sort_order;
    fields.options = fields.input_type === 'select' ? parsedItemOptions.value : null;
    // HR add mode: filled task contents are saved as bullet lines
    // inside the item description (items have no child rows).
    if (isHrTask.value && activeModal.value === 'add-item') {
        const contents = hrTaskContents.value.map((c) => String(c ?? '').trim()).filter((c) => c !== '');
        if (contents.length) {
            const block = contents.map((c) => `- ${c}`).join('\n');
            const base = String(fields.description ?? '').trim();
            fields.description = base ? `${base}\n${block}` : block;
        }
    }

    let payload = fields;
    let forceFormData = false;
    if (itemFile.value) {
        payload = new FormData();
        Object.entries(fields).forEach(([key, value]) => {
            if (value === null || value === undefined) return;
            if (Array.isArray(value)) {
                value.forEach((entry) => payload.append(`${key}[]`, entry));
                return;
            }
            payload.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : value);
        });
        payload.append('attachment', itemFile.value);
        forceFormData = true;
    } else if (removeAttachment.value) {
        payload.remove_attachment = true;
    }

    saving.value = true;
    const requestOptions = {
        preserveScroll: true,
        forceFormData,
        onSuccess: () => {
            closeModal();
            toast.success(activeModal.value === 'edit-item' ? 'Item updated.' : 'Item added to template.');
        },
        onError: () => toast.error(activeModal.value === 'edit-item' ? 'Failed to update item.' : 'Failed to add item.'),
        onFinish: () => { saving.value = false; },
    };
    if (activeModal.value === 'edit-item') {
        router.put(route('hrm.onboarding.templates.items.update', { template: props.template.id, item: itemForm.value.id }), payload, requestOptions);
    } else {
        router.post(route('hrm.onboarding.templates.items.store', props.template.id), payload, requestOptions);
    }
};

const moveItem = (item, delta) => {
    const ids = (itemGroups.value[item.category] || []).map((i) => i.id);
    const idx = ids.indexOf(item.id);
    const target = idx + delta;
    if (target < 0 || target >= ids.length) return;
    const moved = ids.splice(idx, 1)[0];
    ids.splice(target, 0, moved);
    router.patch(route('hrm.onboarding.templates.reorder', props.template.id), { ordered_ids: ids }, {
        preserveScroll: true,
        onSuccess: () => toast.success('Order updated.'),
        onError: () => toast.error('Failed to reorder.'),
    });
};

const toggleItemActive = (item) => {
    router.put(route('hrm.onboarding.templates.items.update', { template: props.template.id, item: item.id }), {
        category: item.category,
        name: item.name,
        description: item.description,
        input_type: item.input_type,
        options: normaliseItemOptions(item.options),
        responsible_party: item.responsible_party,
        is_required: item.is_required,
        applicant_visible: item.applicant_visible,
        is_active: !item.is_active,
        due_rule_type: item.due_rule_type,
        due_rule_value: item.due_rule_value,
        sort_order: item.sort_order,
    }, {
        preserveScroll: true,
        onSuccess: () => toast.success(item.is_active ? 'Item disabled.' : 'Item enabled.'),
        onError: () => toast.error('Failed to toggle item.'),
    });
};

const deleteItem = (item) => {
    Swal.fire({
        title: 'Remove item?',
        text: item.name + ' will be removed from this template definition.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Remove',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#e11d48',
        customClass: { popup: 'rounded-[2rem]' },
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('hrm.onboarding.templates.items.destroy', { template: props.template.id, item: item.id }), {
                preserveScroll: true,
                onSuccess: () => toast.success('Item removed.'),
                onError: () => toast.error('Failed to remove item.'),
            });
        }
    });
};

const closeModal = () => {
    activeModal.value = null;
};

// ============ ACTIVITY DEFINITION FORMS ============
const emptyActivityForm = () => ({
    id: null,
    type: 'Orientation',
    title: '',
    description: '',
    default_method: '',
    default_duration_minutes: null,
    is_required: true,
    applicant_visible: true,
    attendance_required: false,
    must_complete_before_onboarding_completion: false,
    is_active: true,
});

const activityForm = ref(emptyActivityForm());

const openAddActivityModal = () => {
    activityForm.value = emptyActivityForm();
    activeModal.value = 'add-activity';
};

const openEditActivityModal = (activity) => {
    activityForm.value = {
        id: activity.id,
        type: activity.type,
        title: activity.title,
        description: activity.description,
        default_method: activity.default_method || '',
        default_duration_minutes: activity.default_duration_minutes,
        is_required: activity.is_required,
        applicant_visible: activity.applicant_visible,
        attendance_required: activity.attendance_required,
        must_complete_before_onboarding_completion: activity.must_complete_before_onboarding_completion,
        is_active: activity.is_active,
    };
    activeModal.value = 'edit-activity';
};

const submitActivity = () => {
    if (!activityForm.value.title.trim()) {
        toast.error('Activity title is required.');
        return;
    }
    const payload = { ...activityForm.value };
    delete payload.id;
    if (payload.default_duration_minutes === null || payload.default_duration_minutes === '') delete payload.default_duration_minutes;

    saving.value = true;
    if (activeModal.value === 'edit-activity') {
        router.put(route('hrm.onboarding.templates.activities.update', { template: props.template.id, activity: activityForm.value.id }), payload, {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                toast.success('Activity definition updated.');
            },
            onError: () => toast.error('Failed to update activity.'),
            onFinish: () => { saving.value = false; },
        });
    } else {
        router.post(route('hrm.onboarding.templates.activities.store', props.template.id), payload, {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                toast.success('Activity definition added.');
            },
            onError: () => toast.error('Failed to add activity.'),
            onFinish: () => { saving.value = false; },
        });
    }
};

const moveActivity = (activity, delta) => {
    const ids = activity_definitions.value.slice().sort((a, b) => a.sort_order - b.sort_order).map((a) => a.id);
    const idx = ids.indexOf(activity.id);
    const target = idx + delta;
    if (target < 0 || target >= ids.length) return;
    const moved = ids.splice(idx, 1)[0];
    ids.splice(target, 0, moved);
    router.patch(route('hrm.onboarding.templates.activities.reorder', props.template.id), { ordered_ids: ids }, {
        preserveScroll: true,
        onSuccess: () => toast.success('Activity order updated.'),
        onError: () => toast.error('Failed to reorder activities.'),
    });
};

const toggleActivityActive = (activity) => {
    router.put(route('hrm.onboarding.templates.activities.update', { template: props.template.id, activity: activity.id }), {
        type: activity.type,
        title: activity.title,
        description: activity.description,
        default_method: activity.default_method,
        default_duration_minutes: activity.default_duration_minutes,
        is_required: activity.is_required,
        applicant_visible: activity.applicant_visible,
        attendance_required: activity.attendance_required,
        must_complete_before_onboarding_completion: activity.must_complete_before_onboarding_completion,
        is_active: !activity.is_active,
    }, {
        preserveScroll: true,
        onSuccess: () => toast.success(activity.is_active ? 'Activity disabled.' : 'Activity enabled.'),
        onError: () => toast.error('Failed to toggle activity.'),
    });
};

const deleteActivity = (activity) => {
    Swal.fire({
        title: 'Remove activity?',
        text: activity.title + ' will be removed from this template definition.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Remove',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#e11d48',
        customClass: { popup: 'rounded-[2rem]' },
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('hrm.onboarding.templates.activities.destroy', { template: props.template.id, activity: activity.id }), {
                preserveScroll: true,
                onSuccess: () => toast.success('Activity definition removed.'),
                onError: () => toast.error('Failed to remove activity.'),
            });
        }
    });
};
</script>

<style scoped>
.bg-grid-slate-100 {
    background-image: radial-gradient(circle, #cbd5e1 1px, transparent 1px);
    background-size: 24px 24px;
}

/* This page uses fixed light styling (bg-white cards/inputs). The app sets
   `.dark { color-scheme: dark }` globally, which makes native <select>
   dropdown popups render dark while the option text stays dark -> invisible
   options in dark mode. Force native controls to render light so
   Department / Employment Type / Position / Work Mode (and all other
   dropdowns here) stay readable in both themes. */
select {
    color: #334155;
    color-scheme: light;
}
select option {
    color: #334155;
    background-color: #ffffff;
}
</style>