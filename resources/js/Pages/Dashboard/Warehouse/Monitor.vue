<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Warehouse, Plus, Trash2, X, Edit2, Save, Package, Box,
    MapPin, LayoutList, AlertCircle, ChevronUp, ChevronDown,
    Eye, ClipboardList, Info, CheckCircle, Building2, Layers,
    Search, Pencil, Grid3X3, Archive, ArrowRightLeft
} from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditMonitor = computed(() => canEdit('WAR', 'monitor'));

const props = defineProps({
    warehouse: Object,
    floors: { type: Array, default: () => [] },
    sections: Array,
    unassignedStock: { type: Array, default: () => [] },
    moveTargets: { type: Array, default: () => [] },
    auth: Object,
});

// ─── Floors state ────────────────────────────────────────────────
const floorsList = ref([]);
const activeFloorId = ref(null);

const activeFloor = computed(() =>
    floorsList.value.find(f => String(f.id) === String(activeFloorId.value)) || floorsList.value[0] || null
);

const buildFloorsFromProps = () => {
    if (props.floors && props.floors.length > 0) {
        floorsList.value = JSON.parse(JSON.stringify(props.floors));
        // normalise nested shape
        floorsList.value.forEach(f => {
            f.sections = (f.sections || []).map(s => ({
                ...s,
                shelves: s.shelves || [],
                stock_items_no_shelf: s.stock_items_no_shelf || [],
            }));
            f.grid_rows = Number(f.grid_rows || 3);
            f.grid_cols = Number(f.grid_cols || 3);
        });
    } else {
        // legacy fallback: single floor from warehouse grid + flat sections
        floorsList.value = [{
            id: 'temp-floor-1',
            name: 'Ground Floor',
            level: 1,
            grid_rows: Number(props.warehouse?.grid_rows || 3),
            grid_cols: Number(props.warehouse?.grid_cols || 3),
            sections: JSON.parse(JSON.stringify(props.sections || [])),
        }];
    }
    if (!activeFloorId.value || !floorsList.value.some(f => String(f.id) === String(activeFloorId.value))) {
        activeFloorId.value = floorsList.value[0]?.id ?? null;
    }
};

onMounted(() => buildFloorsFromProps());
watch(() => props.floors, () => {
    const keep = activeFloorId.value;
    buildFloorsFromProps();
    if (keep && floorsList.value.some(f => String(f.id) === String(keep))) activeFloorId.value = keep;
}, { deep: true });

// ─── Edit / search / modals ──────────────────────────────────────
const editMode = ref(false);
const searchQuery = ref('');
const showQueue = ref(true);

const showSectionModal = ref(false);
const showShelfModal = ref(false);
const showDetailsModal = ref(false);
const showUseModal = ref(false);
const showConfirmModal = ref(false);
const showFloorModal = ref(false);

const editingSection = ref(null);
const editingShelfSection = ref(null);
const editingFloor = ref(null);
const newSectionName = ref('');
const newShelfNumber = ref('');
const floorForm = ref({ name: '', grid_rows: 3, grid_cols: 3 });
const selectedCell = ref(null);
const detailItem = ref(null);
const selectedStockItem = ref(null);
const useFormErrors = ref({});

const confirmConfig = ref({ title: '', message: '', confirmText: '', type: 'blue', action: null });
const materialForm = useForm({ quantity: 0, manufacturing_department: '' });

// ─── Derived stats ───────────────────────────────────────────────
const allSections = computed(() => floorsList.value.flatMap(f => (f.sections || []).map(s => ({ ...s, _floor: f }))));
const totalShelves = computed(() => allSections.value.reduce((n, s) => n + (s.shelves?.length || 0), 0));
const totalUnits = computed(() => {
    let n = 0;
    allSections.value.forEach(s => {
        (s.stock_items_no_shelf || []).forEach(i => n += Number(i.quantity || 0));
        (s.shelves || []).forEach(sh => (sh.stock_items || []).forEach(i => n += Number(i.quantity || 0)));
    });
    (props.unassignedStock || []).forEach(i => n += Number(i.quantity || 0));
    return n;
});
const isLiveItem = (i) => Number(i?.quantity || 0) > 0 && i?.status !== 'used';
const floorCounts = (floor) => {
    const secs = floor?.sections || [];
    let items = 0, shelves = 0;
    secs.forEach(s => {
        shelves += s.shelves?.length || 0;
        items += (s.stock_items_no_shelf || []).filter(isLiveItem).length;
        (s.shelves || []).forEach(sh => items += (sh.stock_items || []).filter(isLiveItem).length);
    });
    return { sectors: secs.length, shelves, items };
};
const visibleQueue = computed(() => (props.unassignedStock || []).filter(isLiveItem));
const visibleNoShelf = (section) => (section?.stock_items_no_shelf || []).filter(isLiveItem);
const visibleShelfItems = (shelf) => (shelf?.stock_items || []).filter(isLiveItem);

const filteredQueue = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    const base = visibleQueue.value;
    if (!q) return base;
    return base.filter(i =>
        (i.material?.name || '').toLowerCase().includes(q) ||
        (i.control_number || '').toLowerCase().includes(q)
    );
});

// ─── Grid for active floor ───────────────────────────────────────
const gridTemplate = computed(() => ({
    gridTemplateRows: `repeat(${activeFloor.value?.grid_rows || 3}, minmax(190px, auto))`,
    gridTemplateColumns: `repeat(${activeFloor.value?.grid_cols || 3}, minmax(0, 1fr))`,
}));

const gridCells = computed(() => {
    const f = activeFloor.value;
    if (!f) return [];
    const cells = [];
    for (let r = 0; r < f.grid_rows; r++) {
        for (let c = 0; c < f.grid_cols; c++) {
            const section = (f.sections || []).find(s => Number(s.grid_row) === r && Number(s.grid_col) === c);
            cells.push({ row: r, col: c, section: section || null });
        }
    }
    return cells;
});

const sectionQty = (section) => {
    let n = 0;
    (section.stock_items_no_shelf || []).forEach(i => n += Number(i.quantity || 0));
    (section.shelves || []).forEach(sh => (sh.stock_items || []).forEach(i => n += Number(i.quantity || 0)));
    return n;
};

// ─── Floor actions ───────────────────────────────────────────────
const isSavingFloor = ref(false);
const floorFormError = ref('');
const openFloorModal = (floor = null) => {
    editingFloor.value = floor;
    floorForm.value = floor
        ? { name: floor.name, grid_rows: floor.grid_rows, grid_cols: floor.grid_cols }
        : { name: '', grid_rows: 3, grid_cols: 3 };
    floorFormError.value = '';
    isSavingFloor.value = false;
    showFloorModal.value = true;
};

const saveFloor = () => {
    if (isSavingFloor.value) return; // ← spam-click guard
    const name = floorForm.value.name.trim();
    if (!name) {
        floorFormError.value = 'Please enter a floor name.';
        return;
    }
    // client-side duplicate guard (case-insensitive, ignores the floor being edited)
    const dupe = floorsList.value.some(f =>
        String(f.id) !== String(editingFloor.value?.id) &&
        String(f.name || '').trim().toLowerCase() === name.toLowerCase()
    );
    if (dupe) {
        floorFormError.value = 'A floor with this name already exists.';
        return;
    }
    floorFormError.value = '';
    isSavingFloor.value = true;
    const payload = { name, grid_rows: Number(floorForm.value.grid_rows), grid_cols: Number(floorForm.value.grid_cols) };
    const done = () => { isSavingFloor.value = false; };
    if (editingFloor.value && !String(editingFloor.value.id).startsWith('temp-floor')) {
        // persisted → immediate PUT
        router.put(route('warehouse.monitor.floors.update', editingFloor.value.id), payload, {
            preserveScroll: true,
            onSuccess: () => { showFloorModal.value = false; },
            onError: (errors) => { floorFormError.value = errors.name || errors.error || 'Could not save floor.'; },
            onFinish: done,
        });
    } else if (editingFloor.value) {
        // temp → local rename/resize
        editingFloor.value.name = name;
        editingFloor.value.grid_rows = payload.grid_rows;
        editingFloor.value.grid_cols = payload.grid_cols;
        isSavingFloor.value = false;
        showFloorModal.value = false;
    } else {
        // quick-create persisted floor immediately (better UX than waiting for Save)
        router.post(route('warehouse.monitor.floors.store', props.warehouse.id), payload, {
            preserveScroll: true,
            onSuccess: () => { showFloorModal.value = false; },
            onError: (errors) => { floorFormError.value = errors.name || errors.error || 'Could not save floor.'; },
            onFinish: done,
        });
    }
};

const deleteFloor = (floor) => {
    if (floorsList.value.length <= 1) {
        triggerConfirm('Action Denied', 'A warehouse must keep at least one floor.', 'Understood', 'amber', () => showConfirmModal.value = false);
        return;
    }
    // Rule: a floor holding ANY live raw material cannot be deleted.
    let liveCount = 0;
    (floor.sections || []).forEach(s => {
        liveCount += visibleNoShelf(s).length;
        (s.shelves || []).forEach(sh => liveCount += visibleShelfItems(sh).length);
    });
    if (liveCount > 0) {
        triggerConfirm('Cannot Delete Floor', `“${floor.name}” still holds ${liveCount} raw material(s). Move them to another shelf, floor, or warehouse first — only empty floors can be deleted.`, 'Understood', 'amber', () => showConfirmModal.value = false);
        return;
    }
    const emptySectors = (floor.sections || []).length;
    triggerConfirm('Delete Floor', emptySectors > 0
        ? `“${floor.name}” is empty of materials and has ${emptySectors} empty sector(s) that will be removed with it. Continue?`
        : `Remove empty floor “${floor.name}” permanently?`, 'Delete', 'red', () => {
        showConfirmModal.value = false;
        if (String(floor.id).startsWith('temp-floor')) {
            floorsList.value = floorsList.value.filter(f => f.id !== floor.id);
            activeFloorId.value = floorsList.value[0]?.id ?? null;
        } else {
            router.delete(route('warehouse.monitor.floors.destroy', floor.id), {
                preserveScroll: true,
                onSuccess: () => { if (String(activeFloorId.value) === String(floor.id)) activeFloorId.value = floorsList.value[0]?.id ?? null; }
            });
        }
    });
};

// ─── Section / shelf actions (staged locally, persisted via Save Layout) ──
const deleteSection = (sectionId) => {
    const floor = activeFloor.value;
    const section = (floor?.sections || []).find(s => String(s.id) === String(sectionId));
    if (!section) return;
    const hasItems = visibleNoShelf(section).length > 0 || (section.shelves?.some(sh => visibleShelfItems(sh).length > 0));
    if (hasItems) {
        triggerConfirm('Action Denied', 'Sector contains items. Move them to the queue first.', 'Understood', 'amber', () => showConfirmModal.value = false);
        return;
    }
    triggerConfirm('Delete Sector', `Remove sector “${section.name}”?`, 'Delete', 'red', () => {
        floor.sections = floor.sections.filter(s => String(s.id) !== String(sectionId));
        showConfirmModal.value = false;
    });
};

const saveSection = () => {
    if (!newSectionName.value.trim() || !activeFloor.value) return;
    if (editingSection.value) {
        editingSection.value.name = newSectionName.value.trim();
    } else {
        activeFloor.value.sections.push({
            id: 'temp-' + Date.now(),
            name: newSectionName.value.trim().toUpperCase(),
            grid_row: selectedCell.value.row,
            grid_col: selectedCell.value.col,
            shelves: [],
            stock_items_no_shelf: []
        });
    }
    editingSection.value = null;
    newSectionName.value = '';
    showSectionModal.value = false;
};

const addShelf = () => {
    if (!newShelfNumber.value.trim() || !editingShelfSection.value) return;
    const sec = editingShelfSection.value;
    sec.shelves = sec.shelves || [];
    sec.shelves.push({ id: 'ts-' + Date.now(), shelf_number: newShelfNumber.value.trim().toUpperCase(), stock_items: [] });
    newShelfNumber.value = '';
};

const removeShelf = (section, shid) => {
    const shelf = (section.shelves || []).find(s => String(s.id) === String(shid));
    if (visibleShelfItems(shelf).length > 0) {
        triggerConfirm('Denied', 'Shelf contains items. Move them first.', 'OK', 'amber', () => showConfirmModal.value = false);
        return;
    }
    section.shelves = (section.shelves || []).filter(s => String(s.id) !== String(shid));
};

const saveLayout = () => {
    showConfirmModal.value = false;
    const floors = floorsList.value.map((f, i) => ({
        id: String(f.id).startsWith('temp-floor') ? null : f.id,
        name: f.name,
        level: i + 1,
        grid_rows: f.grid_rows,
        grid_cols: f.grid_cols,
        sections: (f.sections || []).map(s => ({
            id: String(s.id).startsWith('temp-') ? null : s.id,
            name: s.name,
            row: s.grid_row,
            col: s.grid_col,
            shelves: (s.shelves || []).map(sh => ({
                id: (String(sh.id).startsWith('ts-') || String(sh.id).startsWith('temp-')) ? null : sh.id,
                shelf_number: sh.shelf_number
            }))
        }))
    }));
    router.post(route('warehouse.monitor.layout', props.warehouse.id), { floors }, {
        onSuccess: () => { editMode.value = false; router.reload(); },
        preserveScroll: true
    });
};

// ─── Stock moves ─────────────────────────────────────────────────
const onDrop = (event, shelfId, section = null) => {
    event.preventDefault();
    try {
        const item = JSON.parse(event.dataTransfer.getData('text/plain'));
        router.post(route('warehouse.monitor.assign'), { stock_item_id: item.id, shelf_id: shelfId, section_id: section?.id }, { preserveScroll: true });
    } catch { /* ignore bad drops */ }
};

const triggerConfirm = (title, message, confirmText, type, action) => {
    confirmConfig.value = { title, message, confirmText, type, action };
    showConfirmModal.value = true;
};

const openDetailsModal = (item) => { detailItem.value = item; showDetailsModal.value = true; };

const openUseModal = (item) => {
    selectedStockItem.value = item;
    useFormErrors.value = {};
    materialForm.quantity = item.quantity;
    materialForm.manufacturing_department = '';
    showUseModal.value = true;
};

const submitUse = () => {
    if (!materialForm.manufacturing_department) {
        useFormErrors.value = { manufacturing_department: 'Please select a destination department.' };
        return;
    }
    useFormErrors.value = {};
    triggerConfirm(
        'Release Material',
        `Send ${materialForm.quantity} ${selectedStockItem.value?.unit} of "${selectedStockItem.value?.material?.name}" to ${materialForm.manufacturing_department}?`,
        'Confirm', 'green',
        () => {
            showConfirmModal.value = false;
            materialForm.post(route('warehouse.monitor.use', selectedStockItem.value.id), {
                onSuccess: () => { showUseModal.value = false; router.reload(); },
                onError: (errors) => { useFormErrors.value = errors; showUseModal.value = true; },
            });
        }
    );
};

const adjustGrid = (floor, key, delta) => {
    const next = Number(floor[key]) + delta;
    if (next < 1 || next > 20) return;
    if (delta < 0) {
        const edge = key === 'grid_rows' ? next : floor.grid_cols;
        const blocked = (floor.sections || []).some(s => key === 'grid_rows' ? Number(s.grid_row) === next : Number(s.grid_col) === next);
        if (blocked) {
            triggerConfirm('Restriction', `A sector occupies that ${key === 'grid_rows' ? 'row' : 'column'}.`, 'OK', 'amber', () => showConfirmModal.value = false);
            return;
        }
        void edge;
    }
    floor[key] = next;
};

// ─── Move stock: shelf → shelf, floor → floor, warehouse → warehouse ──
const showMoveModal = ref(false);
const moveItem = ref(null);
const moveForm = ref({ warehouse_id: null, floor_id: null, section_id: null, shelf_id: null });
const moveError = ref('');
const isMoving = ref(false);

const moveWarehouseOptions = computed(() => [
    { id: props.warehouse?.id, name: `${props.warehouse?.name} (current)` },
    ...(props.moveTargets || []).map(w => ({ id: w.id, name: w.name })),
]);

// floors available for the selected destination warehouse:
// current warehouse → live floorsList; other warehouse → moveTargets tree
const moveFloorOptions = computed(() => {
    if (!moveForm.value.warehouse_id) return [];
    if (String(moveForm.value.warehouse_id) === String(props.warehouse?.id)) {
        return (floorsList.value || []).map(f => ({ id: f.id, name: f.name }));
    }
    const w = (props.moveTargets || []).find(x => String(x.id) === String(moveForm.value.warehouse_id));
    return (w?.floors || []).map(f => ({ id: f.id, name: f.name }));
});

const moveSectionOptions = computed(() => {
    if (!moveForm.value.floor_id) return [];
    if (String(moveForm.value.warehouse_id) === String(props.warehouse?.id)) {
        const f = (floorsList.value || []).find(x => String(x.id) === String(moveForm.value.floor_id));
        return (f?.sections || []).map(s => ({ id: s.id, name: s.name }));
    }
    const w = (props.moveTargets || []).find(x => String(x.id) === String(moveForm.value.warehouse_id));
    const f = (w?.floors || []).find(x => String(x.id) === String(moveForm.value.floor_id));
    return (f?.sections || []).map(s => ({ id: s.id, name: s.name }));
});

const moveShelfOptions = computed(() => {
    if (!moveForm.value.section_id) return [];
    if (String(moveForm.value.warehouse_id) === String(props.warehouse?.id)) {
        for (const f of (floorsList.value || [])) {
            const s = (f.sections || []).find(x => String(x.id) === String(moveForm.value.section_id));
            if (s) return (s.shelves || []).map(sh => ({ id: sh.id, shelf_number: sh.shelf_number }));
        }
        return [];
    }
    for (const w of (props.moveTargets || [])) {
        for (const f of (w.floors || [])) {
            const s = (f.sections || []).find(x => String(x.id) === String(moveForm.value.section_id));
            if (s) return (s.shelves || []).map(sh => ({ id: sh.id, shelf_number: sh.shelf_number }));
        }
    }
    return [];
});

const openMoveModal = (item) => {
    moveItem.value = item;
    moveError.value = '';
    isMoving.value = false;
    // default: current warehouse, keep floor context when known
    moveForm.value = { warehouse_id: props.warehouse?.id, floor_id: null, section_id: null, shelf_id: null };
    showMoveModal.value = true;
};

const submitMove = () => {
    if (isMoving.value) return;
    if (!moveForm.value.warehouse_id) { moveError.value = 'Select a destination warehouse.'; return; }
    if (!moveForm.value.section_id && !moveForm.value.shelf_id) { moveError.value = 'Select a destination sector (and shelf, optional).'; return; }
    moveError.value = '';
    isMoving.value = true;
    router.post(route('warehouse.monitor.move', moveItem.value.id), {
        warehouse_id: moveForm.value.warehouse_id,
        section_id: moveForm.value.section_id,
        shelf_id: moveForm.value.shelf_id,
    }, {
        preserveScroll: true,
        onSuccess: () => { showMoveModal.value = false; router.reload(); },
        onError: (errors) => { moveError.value = errors.error || errors.section_id || errors.shelf_id || errors.warehouse_id || 'Move failed.'; },
        onFinish: () => { isMoving.value = false; },
    });
};

const dragStart = (e, item) => { e.dataTransfer.setData('text/plain', JSON.stringify(item)); e.dataTransfer.effectAllowed = 'move'; };
const allowDrop = (e) => e.preventDefault();
</script>

<template>
    <Head :title="`Monitor: ${warehouse.name}`" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-slate-100/70 dark:bg-zinc-950">
            <div class="mx-auto max-w-[1600px] space-y-5 p-4 sm:p-6 pb-16">

                <!-- ═══ Header ═══ -->
                <div class="relative overflow-hidden rounded-2xl bg-slate-900 text-white shadow-xl">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800" />
                    <div class="absolute -top-24 -right-16 h-72 w-72 rounded-full bg-white/10 blur-3xl" />
                    <div class="absolute -bottom-28 -left-10 h-72 w-72 rounded-full bg-fuchsia-400/20 blur-3xl" />
                    <div class="relative p-6 sm:p-7">
                        <div class="flex flex-wrap items-start gap-4">
                            <div class="flex h-13 w-13 items-center justify-center rounded-2xl bg-white/15 p-3 ring-1 ring-white/30">
                                <Warehouse class="h-7 w-7" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                    <LayoutList class="h-3.5 w-3.5" /> Warehouse · Monitor
                                </p>
                                <h1 class="mt-1 text-2xl sm:text-3xl font-extrabold tracking-tight">
                                    {{ warehouse.name }}
                                    <span v-if="!canEditMonitor" class="ml-2 inline-block rounded-full bg-amber-400 px-3 py-1 align-middle text-[11px] font-black uppercase tracking-widest text-amber-950">View only</span>
                                </h1>
                                <p class="mt-1 text-sm text-blue-100/90">{{ warehouse.location }} · {{ floorsList.length }} floor{{ floorsList.length > 1 ? 's' : '' }} · drag received stock onto shelves</p>
                                <!-- stat chips -->
                                <div class="mt-4 flex flex-wrap gap-2 text-xs font-bold">
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 ring-1 ring-white/25"><Building2 class="h-3.5 w-3.5" /> {{ floorsList.length }} floors</span>
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 ring-1 ring-white/25"><Grid3X3 class="h-3.5 w-3.5" /> {{ allSections.length }} sectors</span>
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 ring-1 ring-white/25"><Layers class="h-3.5 w-3.5" /> {{ totalShelves }} shelves</span>
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 ring-1 ring-white/25"><Archive class="h-3.5 w-3.5" /> {{ totalUnits.toLocaleString() }} units</span>
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-300/90 px-3 py-1.5 text-amber-950"><Package class="h-3.5 w-3.5" /> {{ visibleQueue.length }} unassigned</span>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button v-if="!editMode && canEditMonitor" @click="editMode = true" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-xs font-extrabold uppercase tracking-wide text-indigo-700 shadow hover:bg-indigo-50 active:scale-95"><Edit2 class="h-3.5 w-3.5" /> Edit layout</button>
                                <button v-if="editMode && canEditMonitor" @click="triggerConfirm('Apply Layout', 'Save all floor grids, sectors and shelves?', 'Save', 'blue', saveLayout)" class="inline-flex items-center gap-2 rounded-xl bg-emerald-400 px-4 py-2.5 text-xs font-extrabold uppercase tracking-wide text-emerald-950 shadow hover:bg-emerald-300 active:scale-95"><Save class="h-3.5 w-3.5" /> Save layout</button>
                                <button v-if="editMode" @click="editMode = false" class="rounded-xl bg-white/15 px-4 py-2.5 text-xs font-extrabold uppercase tracking-wide ring-1 ring-white/25 hover:bg-white/25 active:scale-95">Cancel</button>
                            </div>
                        </div>

                        <!-- ═══ Floor tabs ═══ -->
                        <div class="relative mt-5 flex flex-wrap items-center gap-2">
                            <button v-for="(f, i) in floorsList" :key="f.id"
                                @click="activeFloorId = f.id"
                                class="group inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-extrabold uppercase tracking-wide transition active:scale-95"
                                :class="String(activeFloorId) === String(f.id) ? 'bg-white text-indigo-700 shadow-lg' : 'bg-white/12 text-white ring-1 ring-white/25 hover:bg-white/25'">
                                <span class="flex h-6 w-6 items-center justify-center rounded-lg text-[11px] font-black"
                                    :class="String(activeFloorId) === String(f.id) ? 'bg-indigo-600 text-white' : 'bg-white/20'">{{ i + 1 }}</span>
                                {{ f.name }}
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-black"
                                    :class="String(activeFloorId) === String(f.id) ? 'bg-indigo-100 text-indigo-700' : 'bg-white/15'">{{ floorCounts(f).sectors }} sectors</span>
                                <span v-if="editMode && canEditMonitor" class="hidden group-hover:inline-flex items-center gap-1">
                                    <span @click.stop="openFloorModal(f)" class="rounded-md p-1 hover:bg-indigo-100" title="Rename / resize"><Pencil class="h-3 w-3" /></span>
                                    <span @click.stop="deleteFloor(f)" class="rounded-md p-1 hover:bg-red-100 hover:text-red-600" title="Delete floor"><Trash2 class="h-3 w-3" /></span>
                                </span>
                            </button>
                            <button v-if="canEditMonitor" @click="openFloorModal(null)"
                                class="inline-flex items-center gap-1.5 rounded-xl border-2 border-dashed border-white/40 px-4 py-2.5 text-xs font-extrabold uppercase tracking-wide text-white/90 hover:border-white hover:bg-white/10 active:scale-95">
                                <Plus class="h-4 w-4" /> Add floor
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ═══ Toolbar: search + active floor meta + grid sizing ═══ -->
                <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:flex-row lg:items-center">
                    <div class="relative flex-1">
                        <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input v-model="searchQuery" placeholder="Search unassigned stock by material or control no…"
                            class="w-full rounded-xl border-0 bg-slate-100 py-3 pl-10 pr-4 text-sm font-semibold outline-none focus:ring-2 focus:ring-indigo-500/40 dark:bg-zinc-800 dark:text-slate-100" />
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1.5 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">
                            <MapPin class="h-3.5 w-3.5" /> {{ activeFloor?.name }} · {{ activeFloor?.grid_rows }} × {{ activeFloor?.grid_cols }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 dark:bg-zinc-800">
                            {{ floorCounts(activeFloor).items }} stored · {{ floorCounts(activeFloor).shelves }} shelves
                        </span>
                        <button @click="showQueue = !showQueue" class="rounded-full bg-slate-900 px-3 py-1.5 text-white dark:bg-white dark:text-slate-900">
                            {{ showQueue ? 'Hide queue' : 'Show queue' }} ({{ filteredQueue.length }})
                        </button>
                    </div>
                </div>

                <div v-if="editMode && canEditMonitor && activeFloor" class="rounded-2xl border-2 border-dashed border-indigo-300 bg-indigo-50/60 p-4 sm:p-5 dark:border-indigo-800 dark:bg-indigo-950/20">
                    <div class="flex flex-wrap items-center gap-6">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-indigo-500">“{{ activeFloor.name }}” rows · {{ activeFloor.grid_rows }}</p>
                            <div class="mt-1.5 flex items-center gap-2 rounded-xl border bg-white p-1.5 shadow-sm dark:bg-zinc-900">
                                <button @click="adjustGrid(activeFloor, 'grid_rows', -1)" class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-zinc-800"><ChevronDown class="h-4 w-4" /></button>
                                <span class="w-8 text-center text-lg font-black">{{ activeFloor.grid_rows }}</span>
                                <button @click="adjustGrid(activeFloor, 'grid_rows', 1)" class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-zinc-800"><ChevronUp class="h-4 w-4" /></button>
                            </div>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-indigo-500">Columns · {{ activeFloor.grid_cols }}</p>
                            <div class="mt-1.5 flex items-center gap-2 rounded-xl border bg-white p-1.5 shadow-sm dark:bg-zinc-900">
                                <button @click="adjustGrid(activeFloor, 'grid_cols', -1)" class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-zinc-800"><ChevronDown class="h-4 w-4" /></button>
                                <span class="w-8 text-center text-lg font-black">{{ activeFloor.grid_cols }}</span>
                                <button @click="adjustGrid(activeFloor, 'grid_cols', 1)" class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-zinc-800"><ChevronUp class="h-4 w-4" /></button>
                            </div>
                        </div>
                        <p class="text-[11px] font-bold uppercase tracking-widest text-indigo-400">Click an empty cell to add a sector · remember to Save layout</p>
                    </div>
                </div>

                <!-- ═══ Incoming queue ═══ -->
                <div v-if="showQueue" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <h3 class="mb-4 flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
                        <Package class="h-4 w-4 text-indigo-500" /> Incoming queue · drag onto a shelf or sector
                        <span class="ml-auto rounded-full bg-indigo-100 px-2.5 py-0.5 text-[10px] text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">{{ filteredQueue.length }}</span>
                    </h3>
                    <div v-if="filteredQueue.length" class="flex flex-wrap gap-2.5">
                        <div v-for="item in filteredQueue" :key="item.id" :draggable="canEditMonitor" @dragstart="dragStart($event, item)"
                            class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-400 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800"
                            :class="canEditMonitor ? 'cursor-move' : 'cursor-default'">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-violet-700 text-white"><Box class="h-4 w-4" /></span>
                            <div class="leading-tight">
                                <p class="text-[13px] font-bold text-slate-700 dark:text-slate-200">{{ item.material?.name }}</p>
                                <p class="text-[10px] font-bold text-slate-400">{{ item.control_number }} · {{ item.quantity }}{{ item.unit }}</p>
                            </div>
                            <button @click="openDetailsModal(item)" class="text-slate-300 hover:text-indigo-600" title="Details"><Eye class="h-4 w-4" /></button>
                            <button v-if="canEditMonitor" @click="openMoveModal(item)" class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-2.5 py-1.5 text-[9px] font-black uppercase text-white hover:bg-indigo-500" title="Move to shelf / floor / warehouse"><ArrowRightLeft class="h-3 w-3" /> Move</button>
                        </div>
                    </div>
                    <p v-else class="rounded-xl border border-dashed border-slate-200 py-6 text-center text-xs font-bold uppercase tracking-widest text-slate-400 dark:border-zinc-700">
                        {{ visibleQueue.length ? 'No match for search' : 'Queue empty — all received stock is stored' }}
                    </p>
                </div>

                <!-- ═══ Floor grid ═══ -->
                <div class="rounded-2xl border border-slate-200 bg-white/70 p-4 shadow-inner dark:border-zinc-800 dark:bg-zinc-900/60 sm:p-6">
                    <div class="grid gap-4" :style="gridTemplate">
                        <div v-for="cell in gridCells" :key="`${cell.row}-${cell.col}`"
                            @click="!cell.section && editMode && canEditMonitor ? (selectedCell = cell, editingSection = null, newSectionName = '', showSectionModal = true) : null"
                            @drop="cell.section ? onDrop($event, null, cell.section) : null" @dragover="allowDrop"
                            class="relative min-h-[210px] overflow-hidden rounded-2xl border transition"
                            :class="[
                                cell.section ? 'border-slate-200 bg-white shadow-md hover:border-indigo-300 hover:shadow-lg dark:border-zinc-700 dark:bg-zinc-900' : 'border-dashed border-slate-200 bg-slate-50/60 dark:border-zinc-700 dark:bg-zinc-900/40',
                                !cell.section && editMode ? 'cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/50' : ''
                            ]">
                            <!-- Sector card -->
                            <div v-if="cell.section" class="flex h-full flex-col p-4">
                                <div class="mb-3 flex items-center gap-2">
                                    <span class="truncate rounded-lg bg-slate-900 px-3 py-1.5 text-[11px] font-black uppercase tracking-widest text-white dark:bg-indigo-600">{{ cell.section.name }}</span>
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black text-slate-500 dark:bg-zinc-800 dark:text-slate-300">{{ sectionQty(cell.section).toLocaleString() }} units</span>
                                    <div v-if="editMode && canEditMonitor" class="ml-auto flex gap-1">
                                        <button @click.stop="editingSection = cell.section; newSectionName = cell.section.name; selectedCell = null; showSectionModal = true" class="rounded-lg bg-slate-100 p-1.5 text-slate-500 hover:bg-indigo-600 hover:text-white dark:bg-zinc-800" title="Rename sector"><Edit2 class="h-3.5 w-3.5" /></button>
                                        <button @click.stop="editingShelfSection = cell.section; newShelfNumber = ''; showShelfModal = true" class="rounded-lg bg-slate-100 p-1.5 text-slate-500 hover:bg-emerald-600 hover:text-white dark:bg-zinc-800" title="Manage shelves"><Plus class="h-3.5 w-3.5" /></button>
                                        <button @click.stop="deleteSection(cell.section.id)" class="rounded-lg bg-slate-100 p-1.5 text-slate-500 hover:bg-rose-600 hover:text-white dark:bg-zinc-800" title="Delete sector"><Trash2 class="h-3.5 w-3.5" /></button>
                                    </div>
                                </div>

                                <div class="custom-scroll max-h-[330px] flex-1 space-y-2.5 overflow-y-auto pr-0.5">
                                    <!-- common area -->
                                    <div v-if="visibleNoShelf(cell.section).length" class="space-y-1.5">
                                        <p class="px-1 text-[9px] font-black uppercase tracking-[0.2em] text-amber-500">Common area · no shelf</p>
                                        <div v-for="s in visibleNoShelf(cell.section)" :key="s.id" :draggable="canEditMonitor" @dragstart="dragStart($event, s)"
                                            class="flex items-center justify-between gap-2 rounded-xl border border-amber-200 bg-amber-50 p-2.5 dark:border-amber-900/40 dark:bg-amber-900/10">
                                            <button @click="openDetailsModal(s)" class="flex min-w-0 items-center gap-1.5 text-left">
                                                <Eye class="h-3.5 w-3.5 shrink-0 text-amber-400" />
                                                <span class="truncate text-xs font-bold text-slate-700 dark:text-slate-200">{{ s.material?.name }}</span>
                                            </button>
                                            <span class="flex shrink-0 items-center gap-1.5">
                                                <span class="text-[11px] font-black text-amber-600">{{ s.quantity }}{{ s.unit }}</span>
                                                <button v-if="canEditMonitor" @click.stop="openMoveModal(s)" class="rounded-lg bg-white px-2.5 py-1 text-[9px] font-black uppercase text-indigo-600 shadow-sm hover:bg-indigo-600 hover:text-white dark:bg-zinc-800" title="Move">Move</button>
                                                <button v-if="canEditMonitor" @click.stop="openUseModal(s)" class="rounded-lg bg-white px-2.5 py-1 text-[9px] font-black uppercase text-amber-700 shadow-sm hover:bg-amber-500 hover:text-white dark:bg-zinc-800">Use</button>
                                            </span>
                                        </div>
                                    </div>
                                    <!-- shelves -->
                                    <div v-for="shelf in (cell.section.shelves || [])" :key="shelf.id" @drop.stop="onDrop($event, shelf.id)" @dragover="allowDrop"
                                        class="rounded-xl border border-slate-150 bg-slate-50 p-2.5 dark:border-zinc-700 dark:bg-zinc-800/60">
                                        <div class="mb-2 flex items-center justify-between">
                                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Shelf {{ shelf.shelf_number }}</span>
                                            <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-black text-slate-500 shadow-sm dark:bg-zinc-900">{{ visibleShelfItems(shelf).length }}</span>
                                        </div>
                                        <div v-if="visibleShelfItems(shelf).length" class="space-y-1.5">
                                            <div v-for="st in visibleShelfItems(shelf)" :key="st.id" :draggable="canEditMonitor" @dragstart="dragStart($event, st)"
                                                class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white p-2.5 shadow-sm hover:border-indigo-300 dark:border-zinc-700 dark:bg-zinc-900">
                                                <button @click="openDetailsModal(st)" class="flex min-w-0 items-center gap-1.5 text-left">
                                                    <span class="truncate text-xs font-bold text-slate-700 dark:text-slate-200">{{ st.material?.name }}</span>
                                                </button>
                                                <span class="flex shrink-0 items-center gap-1.5">
                                                    <span class="text-[11px] font-black text-indigo-600">{{ st.quantity }}{{ st.unit }}</span>
                                                    <button v-if="canEditMonitor" @click.stop="openMoveModal(st)" class="text-[9px] font-black uppercase text-indigo-600 hover:underline" title="Move">Move</button>
                                                    <button v-if="canEditMonitor" @click.stop="openUseModal(st)" class="text-[9px] font-black uppercase text-emerald-600 hover:underline">Use</button>
                                                </span>
                                            </div>
                                        </div>
                                        <div v-else class="rounded-lg border border-dashed border-slate-300 py-3 text-center text-[9px] font-bold uppercase tracking-widest text-slate-400 dark:border-zinc-600">Drop stock here</div>
                                    </div>
                                    <div v-if="!(cell.section.shelves?.length) && !visibleNoShelf(cell.section).length" class="rounded-xl border border-dashed border-slate-200 py-5 text-center text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                        Empty sector — drag stock here
                                    </div>
                                </div>
                            </div>
                            <!-- Empty cell -->
                            <div v-else class="flex h-full flex-col items-center justify-center gap-2 py-10 text-slate-300">
                                <Box class="h-9 w-9" />
                                <span class="text-[10px] font-black uppercase tracking-[0.25em]">{{ editMode ? '+ Add sector' : 'Available zone' }}</span>
                                <span v-if="editMode" class="text-[10px] font-bold text-slate-400">R{{ cell.row + 1 }} · C{{ cell.col + 1 }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <Teleport to="body">
                <!-- Confirm -->
                <Transition name="modal">
                    <div v-if="showConfirmModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
                        <div class="w-full max-w-[340px] overflow-hidden rounded-2xl border bg-white text-center shadow-2xl dark:bg-zinc-900">
                            <div class="bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 pb-5 pt-6 text-white">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30">
                                    <AlertCircle v-if="confirmConfig.type !== 'green' && confirmConfig.type !== 'blue'" class="h-7 w-7" />
                                    <Info v-if="confirmConfig.type === 'blue'" class="h-7 w-7" />
                                    <CheckCircle v-if="confirmConfig.type === 'green'" class="h-7 w-7" />
                                </div>
                            </div>
                            <div class="p-6">
                                <h3 class="text-base font-black uppercase tracking-tight">{{ confirmConfig.title }}</h3>
                                <p class="mb-6 mt-2 text-xs leading-relaxed text-slate-400">{{ confirmConfig.message }}</p>
                                <div class="flex gap-2">
                                    <button @click="showConfirmModal = false" class="flex-1 rounded-xl bg-slate-100 py-3 text-[10px] font-black uppercase text-slate-500 hover:bg-slate-200 dark:bg-zinc-800">Cancel</button>
                                    <button @click="confirmConfig.action" class="flex-1 rounded-xl py-3 text-[10px] font-black uppercase text-white shadow-lg hover:opacity-90"
                                        :class="{ 'bg-blue-600': confirmConfig.type === 'blue', 'bg-amber-600': confirmConfig.type === 'amber', 'bg-red-600': confirmConfig.type === 'red', 'bg-emerald-600': confirmConfig.type === 'green' }">{{ confirmConfig.confirmText }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>

                <!-- Details -->
                <Transition name="modal">
                    <div v-if="showDetailsModal && detailItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
                        <div class="w-full max-w-[360px] overflow-hidden rounded-2xl border bg-white shadow-2xl dark:bg-zinc-900">
                            <div class="bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 py-5 text-white">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/30"><ClipboardList class="h-5 w-5" /></span>
                                    <h3 class="text-base font-black">Stock Details</h3>
                                </div>
                            </div>
                            <div class="space-y-3 p-6">
                                <div class="rounded-2xl border bg-slate-50 p-5 text-center dark:bg-zinc-800">
                                    <p class="mb-1 text-[9px] font-black uppercase tracking-[0.3em] text-indigo-500">Material</p>
                                    <p class="text-sm font-black uppercase">{{ detailItem.material?.name }}</p>
                                    <p class="mt-1 text-[11px] font-bold text-slate-400">{{ detailItem.quantity }}{{ detailItem.unit }}</p>
                                </div>
                                <div class="flex items-center justify-between rounded-xl border p-4 text-xs">
                                    <span class="text-[10px] font-bold uppercase text-slate-400">Control ID</span>
                                    <span class="font-black tracking-widest text-indigo-600">{{ detailItem.control_number }}</span>
                                </div>
                                <div class="flex items-center justify-between rounded-xl border p-4 text-xs">
                                    <span class="text-[10px] font-bold uppercase text-slate-400">Stock in</span>
                                    <span class="font-black">{{ detailItem.received_at ? new Date(detailItem.received_at).toLocaleDateString() : '—' }}</span>
                                </div>
                            </div>
                            <div class="px-6 pb-6">
                                <button @click="showDetailsModal = false" class="w-full rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 py-3.5 text-[10px] font-black uppercase tracking-widest text-white shadow-lg hover:opacity-90">Dismiss</button>
                            </div>
                        </div>
                    </div>
                </Transition>

                <!-- Use / release -->
                <Transition name="modal">
                    <div v-if="showUseModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
                        <div class="w-full max-w-[380px] overflow-hidden rounded-2xl border bg-white shadow-2xl dark:bg-zinc-900">
                            <div class="bg-gradient-to-br from-emerald-600 via-teal-600 to-cyan-700 px-6 py-5 text-white">
                                <h3 class="text-lg font-black">Floor Release</h3>
                                <p class="mt-1 text-[11px] text-emerald-50">Select the department that will receive this material.</p>
                            </div>
                            <div class="p-6">
                                <div class="mb-5 rounded-2xl border border-emerald-100 bg-emerald-50 p-4 text-center dark:border-emerald-800 dark:bg-emerald-900/20">
                                    <p class="text-sm font-black uppercase text-emerald-900 dark:text-emerald-200">{{ selectedStockItem?.material?.name }}</p>
                                    <p class="mt-1 text-[10px] font-bold text-emerald-600">{{ selectedStockItem?.control_number }}</p>
                                </div>
                                <label class="mb-1 block text-[9px] font-black uppercase tracking-widest text-slate-400">Quantity to transfer</label>
                                <input v-model="materialForm.quantity" type="number" step="0.01"
                                    class="w-full rounded-xl border bg-slate-50 p-4 text-center text-lg font-black outline-none focus:ring-2 focus:ring-indigo-500/30 dark:bg-zinc-800" />
                                <p v-if="useFormErrors.quantity" class="mt-1 text-[10px] font-bold text-red-500">{{ useFormErrors.quantity }}</p>
                                <label class="mb-1 mt-4 block text-[9px] font-black uppercase tracking-widest text-slate-400">Destination dept <span class="text-red-400">*</span></label>
                                <select v-model="materialForm.manufacturing_department"
                                    class="w-full appearance-none rounded-xl border bg-slate-50 p-4 text-center text-xs font-black uppercase outline-none focus:ring-2 focus:ring-indigo-500/30 dark:bg-zinc-800"
                                    :class="useFormErrors.manufacturing_department ? 'ring-2 ring-red-400' : ''">
                                    <option value="">— Select Department —</option>
                                    <option value="knitting">Knitting</option>
                                    <option value="dyeing">Dyeing</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="packaging">Packaging</option>
                                </select>
                                <p v-if="useFormErrors.manufacturing_department" class="mt-1 text-[10px] font-bold text-red-500">{{ useFormErrors.manufacturing_department }}</p>
                                <p v-if="useFormErrors.error" class="mt-3 rounded-xl bg-red-50 p-3 text-[10px] font-bold text-red-500">{{ useFormErrors.error }}</p>
                                <div class="mt-6 flex gap-2">
                                    <button @click="showUseModal = false" class="flex-1 rounded-xl bg-slate-100 py-3.5 text-[10px] font-black uppercase text-slate-500 hover:bg-slate-200 dark:bg-zinc-800">Cancel</button>
                                    <button v-if="canEditMonitor" @click="submitUse" :disabled="materialForm.processing"
                                        class="flex-[2] rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 py-3.5 text-[10px] font-black uppercase text-white shadow-lg disabled:opacity-50 hover:opacity-90">
                                        {{ materialForm.processing ? 'Releasing…' : 'Release to production' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>

                <!-- Shelf manager -->
                <Transition name="modal">
                    <div v-if="showShelfModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
                        <div class="w-full max-w-[400px] overflow-hidden rounded-2xl border bg-white shadow-2xl dark:bg-zinc-900">
                            <div class="flex items-center justify-between bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 py-5 text-white">
                                <div>
                                    <h3 class="text-base font-black">Shelves · {{ editingShelfSection?.name }}</h3>
                                    <p class="text-[11px] text-blue-100">Received materials go on these shelves</p>
                                </div>
                                <button @click="showShelfModal = false" class="rounded-xl bg-white/15 p-1.5 ring-1 ring-white/25 hover:bg-white/25"><X class="h-4 w-4" /></button>
                            </div>
                            <div class="p-6">
                                <div class="custom-scroll mb-4 max-h-[240px] space-y-2 overflow-y-auto pr-1">
                                    <div v-for="shelf in (editingShelfSection?.shelves || [])" :key="shelf.id"
                                        class="flex items-center justify-between rounded-xl border bg-slate-50 p-3.5 dark:bg-zinc-800">
                                        <span class="text-[11px] font-black uppercase tracking-widest">Shelf {{ shelf.shelf_number }}</span>
                                        <span class="flex items-center gap-2">
                                            <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-black text-slate-500 shadow-sm dark:bg-zinc-900">{{ visibleShelfItems(shelf).length }} items</span>
                                            <button @click="removeShelf(editingShelfSection, shelf.id)" class="p-1.5 text-slate-300 hover:text-rose-500"><Trash2 class="h-4 w-4" /></button>
                                        </span>
                                    </div>
                                    <p v-if="!(editingShelfSection?.shelves?.length)" class="rounded-xl border border-dashed py-6 text-center text-[10px] font-bold uppercase tracking-widest text-slate-400">No shelves yet — add the first one below</p>
                                </div>
                                <div class="mb-4 flex gap-2 rounded-2xl bg-slate-100 p-2 dark:bg-zinc-800">
                                    <input v-model="newShelfNumber" @keyup.enter="addShelf" placeholder="e.g. A-01" class="flex-1 rounded-xl border-0 bg-white px-4 py-3 text-xs font-black uppercase outline-none focus:ring-2 focus:ring-indigo-500/30 dark:bg-zinc-900" />
                                    <button @click="addShelf" class="rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-3 text-[10px] font-black uppercase text-white shadow-lg hover:opacity-90">Add</button>
                                </div>
                                <button @click="showShelfModal = false" class="w-full rounded-xl bg-slate-900 py-3.5 text-[10px] font-black uppercase text-white hover:opacity-90 dark:bg-white dark:text-slate-900">Done — remember to Save layout</button>
                            </div>
                        </div>
                    </div>
                </Transition>

                <!-- Sector modal -->
                <Transition name="modal">
                    <div v-if="showSectionModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
                        <div class="w-full max-w-[340px] overflow-hidden rounded-2xl border bg-white text-center shadow-2xl dark:bg-zinc-900">
                            <div class="bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 pb-5 pt-6 text-white">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30"><MapPin class="h-6 w-6" /></div>
                            </div>
                            <div class="p-6">
                                <h3 class="text-lg font-black uppercase">{{ editingSection ? 'Rename' : 'New' }} sector</h3>
                                <p class="mt-1 text-[11px] font-bold text-slate-400">{{ activeFloor?.name }} · Row {{ (selectedCell?.row ?? editingSection?.grid_row ?? 0) + 1 }} · Col {{ (selectedCell?.col ?? editingSection?.grid_col ?? 0) + 1 }}</p>
                                <input v-model="newSectionName" @keyup.enter="saveSection" placeholder="e.g. A-01" class="mt-4 w-full rounded-2xl border bg-slate-50 p-4 text-center text-sm font-black uppercase outline-none focus:ring-2 focus:ring-indigo-500/30 dark:bg-zinc-800" />
                                <div class="mt-5 flex gap-2">
                                    <button @click="showSectionModal = false" class="flex-1 rounded-xl bg-slate-100 py-3.5 text-[10px] font-black uppercase text-slate-400 hover:bg-slate-200 dark:bg-zinc-800">Cancel</button>
                                    <button @click="saveSection" class="flex-[2] rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 py-3.5 text-[10px] font-black uppercase text-white shadow-lg hover:opacity-90">Confirm</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>

                <!-- Floor modal -->
                <Transition name="modal">
                    <div v-if="showFloorModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
                        <div class="w-full max-w-[360px] overflow-hidden rounded-2xl border bg-white shadow-2xl dark:bg-zinc-900">
                            <div class="bg-gradient-to-br from-slate-900 via-indigo-900 to-violet-900 px-6 py-5 text-white">
                                <h3 class="text-base font-black">{{ editingFloor ? 'Edit floor' : 'Add floor' }}</h3>
                                <p class="mt-0.5 text-[11px] text-indigo-200">Multi-storey support — each floor has its own grid.</p>
                            </div>
                            <div class="space-y-4 p-6">
                                <div>
                                    <label class="mb-1 block text-[9px] font-black uppercase tracking-widest text-slate-400">Floor name</label>
                                    <input v-model="floorForm.name" @keyup.enter="saveFloor" :disabled="isSavingFloor" placeholder="e.g. 2nd Floor"
                                        class="w-full rounded-xl border bg-slate-50 p-3.5 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500/30 disabled:opacity-50 dark:bg-zinc-800"
                                        :class="floorFormError ? 'border-red-400 ring-2 ring-red-400/30' : ''" />
                                    <p v-if="floorFormError" class="mt-1.5 text-[11px] font-bold text-red-500">{{ floorFormError }}</p>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-1 block text-[9px] font-black uppercase tracking-widest text-slate-400">Rows</label>
                                        <input v-model.number="floorForm.grid_rows" type="number" min="1" max="20" class="w-full rounded-xl border bg-slate-50 p-3.5 text-center font-black outline-none focus:ring-2 focus:ring-indigo-500/30 dark:bg-zinc-800" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-[9px] font-black uppercase tracking-widest text-slate-400">Columns</label>
                                        <input v-model.number="floorForm.grid_cols" type="number" min="1" max="20" class="w-full rounded-xl border bg-slate-50 p-3.5 text-center font-black outline-none focus:ring-2 focus:ring-indigo-500/30 dark:bg-zinc-800" />
                                    </div>
                                </div>
                                <div class="flex gap-2 pt-1">
                                    <button @click="showFloorModal = false" :disabled="isSavingFloor" class="flex-1 rounded-xl bg-slate-100 py-3.5 text-[10px] font-black uppercase text-slate-500 hover:bg-slate-200 disabled:opacity-50 dark:bg-zinc-800">Cancel</button>
                                    <button @click="saveFloor" :disabled="isSavingFloor || !floorForm.name.trim()"
                                        class="flex-[2] rounded-xl bg-slate-900 py-3.5 text-[10px] font-black uppercase text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-slate-900">
                                        {{ isSavingFloor ? 'Saving…' : 'Confirm floor' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>

                <!-- Move stock modal: shelf → shelf / floor → floor / warehouse → warehouse -->
                <Transition name="modal">
                    <div v-if="showMoveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
                        <div class="w-full max-w-[400px] overflow-hidden rounded-2xl border bg-white shadow-2xl dark:bg-zinc-900">
                            <div class="bg-gradient-to-br from-indigo-700 via-blue-700 to-cyan-700 px-6 py-5 text-white">
                                <h3 class="flex items-center gap-2 text-base font-black"><ArrowRightLeft class="h-4 w-4" /> Move material</h3>
                                <p class="mt-0.5 text-[11px] text-blue-100">{{ moveItem?.material?.name }} · {{ moveItem?.control_number }} · {{ moveItem?.quantity }}{{ moveItem?.unit }}</p>
                            </div>
                            <div class="space-y-3.5 p-6">
                                <div>
                                    <label class="mb-1 block text-[9px] font-black uppercase tracking-widest text-slate-400">Destination warehouse</label>
                                    <select v-model="moveForm.warehouse_id" @change="moveForm.floor_id = null; moveForm.section_id = null; moveForm.shelf_id = null"
                                        class="w-full rounded-xl border bg-slate-50 p-3.5 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500/30 dark:bg-zinc-800">
                                        <option :value="null">— Select warehouse —</option>
                                        <option v-for="w in moveWarehouseOptions" :key="w.id" :value="w.id">{{ w.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[9px] font-black uppercase tracking-widest text-slate-400">Floor</label>
                                    <select v-model="moveForm.floor_id" @change="moveForm.section_id = null; moveForm.shelf_id = null" :disabled="!moveForm.warehouse_id"
                                        class="w-full rounded-xl border bg-slate-50 p-3.5 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500/30 disabled:opacity-50 dark:bg-zinc-800">
                                        <option :value="null">— Select floor —</option>
                                        <option v-for="f in moveFloorOptions" :key="f.id" :value="f.id">{{ f.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[9px] font-black uppercase tracking-widest text-slate-400">Sector</label>
                                    <select v-model="moveForm.section_id" @change="moveForm.shelf_id = null" :disabled="!moveForm.floor_id"
                                        class="w-full rounded-xl border bg-slate-50 p-3.5 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500/30 disabled:opacity-50 dark:bg-zinc-800">
                                        <option :value="null">— Select sector (required) —</option>
                                        <option v-for="s in moveSectionOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[9px] font-black uppercase tracking-widest text-slate-400">Shelf (optional — empty = common area)</label>
                                    <select v-model="moveForm.shelf_id" :disabled="!moveForm.section_id"
                                        class="w-full rounded-xl border bg-slate-50 p-3.5 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500/30 disabled:opacity-50 dark:bg-zinc-800">
                                        <option :value="null">Common area (no shelf)</option>
                                        <option v-for="sh in moveShelfOptions" :key="sh.id" :value="sh.id">Shelf {{ sh.shelf_number }}</option>
                                    </select>
                                </div>
                                <p v-if="moveError" class="rounded-xl bg-red-50 p-3 text-[11px] font-bold text-red-500 dark:bg-red-900/20">{{ moveError }}</p>
                                <div class="flex gap-2 pt-1">
                                    <button @click="showMoveModal = false" :disabled="isMoving" class="flex-1 rounded-xl bg-slate-100 py-3.5 text-[10px] font-black uppercase text-slate-500 hover:bg-slate-200 disabled:opacity-50 dark:bg-zinc-800">Cancel</button>
                                    <button @click="submitMove" :disabled="isMoving"
                                        class="flex-[2] rounded-xl bg-indigo-600 py-3.5 text-[10px] font-black uppercase text-white hover:bg-indigo-500 disabled:opacity-50">
                                        {{ isMoving ? 'Moving…' : 'Confirm move' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>
            </Teleport>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-track { background: transparent; }
.custom-scroll::-webkit-scrollbar-thumb { background: #c7d2fe; border-radius: 20px; }
.modal-enter-active { transition: opacity 0.25s ease; }
.modal-enter-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.25s ease; }
.modal-enter-from { opacity: 0; }
.modal-enter-from > div { opacity: 0; transform: translateY(16px) scale(0.97); }
.modal-leave-active { transition: opacity 0.18s ease; }
.modal-leave-to { opacity: 0; }
</style>
