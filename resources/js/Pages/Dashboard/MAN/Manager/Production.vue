<script setup>
import { ref, computed, watchEffect } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditProduction = computed(() => canEdit('MAN', 'production'));
const humanize = (value) => String(value ?? '').replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
import {
    Package, Shirt, Droplet, Factory,
    ClipboardList, Info,
    Wind, Flame, Truck,
    AlertTriangle, Sparkles,
} from 'lucide-vue-next';

const props = defineProps({
    department:   { type: String, default: null },
    fabrics:      Array,
    dyeingStages: { type: Array, default: () => [] },
    squeezerJobs: Array,
    ironJobs:     Array,
    packages:     Array, // Now includes status, product_name, product_sku, quantity, fabric_code, yarn_type, weight, operator
    finishingReadyOrders: { type: Array, default: () => [] },
    knittingHandoffs: { type: Array, default: () => [] },
    knittingReplacementHandoffs: { type: Array, default: () => [] },
});

const declineReasons = ref({});
const selectedHandoffFabrics = ref({});
const pendingKnittingRejection = ref(null);
const knittingRejectionProcessing = ref(false);
const pendingKnittingReplacementReview = ref(null);
const knittingReplacementReason = ref('');
const knittingReplacementError = ref('');
const knittingReplacementProcessing = ref(false);
const knittingReplacementResult = ref(null);
const releaseKnittingHandoff = (handoff) => router.post(route('man.production.knitting.release', handoff.id), {}, { preserveScroll: true });
const isHandoffFabricSelected = (handoffId, fabricId) => (selectedHandoffFabrics.value[handoffId] || []).includes(fabricId);
const toggleHandoffFabric = (handoffId, fabricId, checked) => {
    const selected = new Set(selectedHandoffFabrics.value[handoffId] || []);
    if (checked) selected.add(fabricId);
    else selected.delete(fabricId);
    selectedHandoffFabrics.value[handoffId] = [...selected];
};
const declineKnittingHandoff = (handoff) => {
    if (!(selectedHandoffFabrics.value[handoff.id] || []).length || !declineReasons.value[handoff.id]?.trim()) return;
    pendingKnittingRejection.value = handoff;
};
const askKnittingReplacementReview = (handoff, decision) => {
    pendingKnittingReplacementReview.value = { ...handoff, decision };
    knittingReplacementReason.value = '';
    knittingReplacementError.value = '';
};
const submitKnittingReplacementReview = () => {
    const handoff = pendingKnittingReplacementReview.value;
    if (!handoff || knittingReplacementProcessing.value) return;
    if (handoff.decision === 'remake' && !knittingReplacementReason.value.trim()) {
        knittingReplacementError.value = 'Enter a reason before returning this fabric for another make.';
        return;
    }
    knittingReplacementProcessing.value = true;
    router.post(route('man.manager.production.knitting.replacements.review', handoff.id), {
        decision: handoff.decision,
        reason: knittingReplacementReason.value,
    }, {
        preserveScroll: true,
        onSuccess: (page) => {
            const error = page.props.flash?.error;
            knittingReplacementResult.value = error
                ? { success: false, title: 'Replacement review failed', message: error }
                : { success: true, title: 'Replacement review saved', message: page.props.flash?.success || page.props.flash?.message || 'The replacement review was saved.' };
            pendingKnittingReplacementReview.value = null;
        },
        onError: (errors) => {
            knittingReplacementResult.value = { success: false, title: 'Replacement review failed', message: Object.values(errors || {}).flat().find(Boolean) || 'The replacement review could not be saved.' };
        },
        onFinish: () => { knittingReplacementProcessing.value = false; },
    });
};
const confirmKnittingRejection = () => {
    const handoff = pendingKnittingRejection.value;
    if (!handoff || knittingRejectionProcessing.value) return;
    knittingRejectionProcessing.value = true;
    router.post(route('man.production.knitting.decline', handoff.id), {
    reason: declineReasons.value[handoff.id] || '',
    fabric_ids: selectedHandoffFabrics.value[handoff.id] || [],
}, {
    preserveScroll: true,
    onSuccess: () => {
        declineReasons.value[handoff.id] = '';
        selectedHandoffFabrics.value[handoff.id] = [];
        pendingKnittingRejection.value = null;
    },
    onFinish: () => { knittingRejectionProcessing.value = false; },
});
};

// ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Tabs (department-scoped for supervisors) ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
const DEPT_TABS = {
    knitting:  ['fabrics'],
    dyeing:    ['bleaching', 'pre_treatment', 'color', 'post_treatment'],
    finishing: ['packed'],
};

const allTabs = computed(() => [
    { key: 'fabrics',  label: 'Fabrics',   icon: Shirt,    count: props.fabrics?.length      ?? 0 },
    { key: 'bleaching', label: 'Bleaching', icon: Wind, count: props.dyeingStages.filter((item) => item.current_stage === 'bleaching').length },
    { key: 'pre_treatment', label: 'Pre Treatment', icon: Sparkles, count: props.dyeingStages.filter((item) => item.current_stage === 'pre_treatment').length },
    { key: 'color', label: 'Color', icon: Droplet, count: props.dyeingStages.filter((item) => item.current_stage === 'color').length },
    { key: 'post_treatment', label: 'Post Treatment', icon: Flame, count: props.dyeingStages.filter((item) => item.current_stage === 'post_treatment').length },
    { key: 'squeezer', label: 'Finishing Squeezer',  icon: Factory,  count: props.squeezerJobs?.length ?? 0 },
    { key: 'iron',     label: 'Finishing Heatsetting', icon: Flame,    count: props.ironJobs?.length     ?? 0 },
    { key: 'packed',   label: 'Packed / Logistics', icon: Package, count: (props.packages?.length ?? 0) + (props.finishingReadyOrders?.length ?? 0) },
]);

const tabs = computed(() => {
    const allowed = DEPT_TABS[props.department];
    return allowed ? allTabs.value.filter((t) => allowed.includes(t.key)) : allTabs.value;
});

// ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Active Tab ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
const activeTab = ref(props.finishingReadyOrders?.length || props.department === 'finishing' ? 'packed' : 'fabrics');
watchEffect(() => {
    if (!tabs.value.some((t) => t.key === activeTab.value)) {
        activeTab.value = tabs.value[0]?.key ?? 'fabrics';
    }
});

// ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Confirm Modal ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
const confirmModal   = ref(false);
const confirmLabel   = ref('');
const confirmSub     = ref('');
const confirmAction  = ref(null);
const confirmAccept  = ref('Yes, continue');
const confirmTone    = ref('danger');

const askConfirm = (label, sub, action, acceptLabel = 'Yes, continue', tone = 'danger') => {
    confirmLabel.value   = label;
    confirmSub.value     = sub;
    confirmAction.value  = action;
    confirmAccept.value  = acceptLabel;
    confirmTone.value    = tone;
    confirmModal.value   = true;
};
const runConfirm = () => {
    if (confirmAction.value) confirmAction.value();
    confirmModal.value   = false;
    confirmAction.value  = null;
};
const cancelConfirm = () => {
    confirmModal.value   = false;
    confirmAction.value  = null;
};

// ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Actions (department supervisor gates) ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
const dyeingStagesForActiveTab = computed(() => props.dyeingStages.filter((item) => item.current_stage === activeTab.value));

const reviewSubmitting = ref(false);
const reviewResult = ref(null);
const reprocessEvaluation = ref(null);
const reprocessLevel = ref('redo');
const reprocessReasons = ref([]);
const reprocessDetails = ref('');
const reprocessEvaluationError = ref('');
const reprocessConfirmation = ref(null);
const reprocessReasonOptions = [
    { key: 'shade_mismatch', label: 'Shade or color mismatch' },
    { key: 'uneven_process', label: 'Uneven processing' },
    { key: 'stain_or_mark', label: 'Stain or visible mark' },
    { key: 'fabric_damage', label: 'Fabric damage' },
    { key: 'incorrect_process', label: 'Incorrect process result' },
    { key: 'other', label: 'Other issue' },
];

const reviewDyeingStage = (item, decision, reason = '') => {
    if (reviewSubmitting.value) return;
    reviewSubmitting.value = true;
    router.post(route('man.manager.production.dyeing.review', item.record_id), { decision, reason }, {
        preserveScroll: true,
        onSuccess: (page) => {
            const error = page.props.flash?.error;
            reviewResult.value = error
                ? { success: false, title: 'Dyeing review failed', message: error }
                : { success: true, title: 'Dyeing review saved', message: page.props.flash?.success || page.props.flash?.message || 'The fabric review was saved.' };
        },
        onError: (errors) => {
            reviewResult.value = { success: false, title: 'Dyeing review failed', message: Object.values(errors || {}).flat().find(Boolean) || 'The review could not be saved. Please try again.' };
        },
        onHttpException: () => {
            reviewResult.value = { success: false, title: 'Dyeing review failed', message: 'The server returned an error while saving this review. Please try again.' };
        },
        onFinish: () => { reviewSubmitting.value = false; },
    });
};

const requestDyeingPass = (item) => askConfirm(
    'Pass this fabric?',
    `Approve fabric ${item.fabric_code} and move it to the next stage?`,
    () => reviewDyeingStage(item, 'pass'),
    'Pass this fabric',
    'success',
);

const requestDyeingReprocess = (item) => {
    reprocessLevel.value = 'redo';
    reprocessReasons.value = [];
    reprocessDetails.value = '';
    reprocessEvaluationError.value = '';
    reprocessEvaluation.value = item;
};

const continueDyeingReprocess = () => {
    if (!reprocessEvaluation.value || !reprocessReasons.value.length) {
        reprocessEvaluationError.value = 'Select at least one problem reason to continue.';
        return;
    }
    const labels = reprocessReasons.value.map((key) => reprocessReasonOptions.find((reason) => reason.key === key)?.label).filter(Boolean);
    const reason = [labels.join(', '), reprocessDetails.value.trim()].filter(Boolean).join(': ');
    const item = reprocessEvaluation.value;
    const level = reprocessLevel.value;
    reprocessEvaluation.value = null;
    reprocessConfirmation.value = { item, level, reason };
};

const confirmDyeingReprocess = () => {
    const confirmation = reprocessConfirmation.value;
    if (!confirmation || reviewSubmitting.value) return;
    reprocessConfirmation.value = null;
    reviewDyeingStage(
        confirmation.item,
        confirmation.level === 'replacement' ? 'replacement' : 'redo',
        confirmation.reason,
    );
};

const pushToLogistics = (pkg) => {
    askConfirm(
        'Push to Logistics?',
        `Send package ${pkg.code} to the logistics module? This action cannot be undone.`,
        () => router.post(route('man.gates.finishing.push-to-logistics', pkg.id), {}, {
            preserveScroll: true,
        }),
        'Yes, Push',
        'success',
    );
};

const pushFinishingOrderToLogistics = (workOrder) => {
    askConfirm(
        'Push JO to Logistics?',
        `Send ${workOrder.jo_number} and its quality-approved fabric rolls to Logistics?`,
        () => router.post(route('man.manager.production.finishing.push-to-logistics', workOrder.id), {}, { preserveScroll: true }),
        'Push JO',
        'success',
    );
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <Factory class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> MAN Ãƒâ€šÃ‚Â· Manager
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Production Pipeline</h1>
                            <span v-if="!canEditProduction" class="mt-2 inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-amber-800">View only</span>
                            <p class="text-sm text-blue-100/90">
                                <span v-if="department" class="font-bold capitalize">{{ department }} department</span>
                                <span v-else>Track and advance items through each stage</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ (fabrics?.length ?? 0) + (dyeingStages?.length ?? 0) + (packages?.length ?? 0) + (finishingReadyOrders?.length ?? 0) }} items
                            </span>
                            <span class="hidden sm:flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 ring-1 ring-white/25 backdrop-blur">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse"></span>
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-300 animate-pulse"></span>
                                <span class="h-1.5 w-1.5 rounded-full bg-white animate-pulse"></span>
                            </span>
                        </div>
                    </div>

                    <!-- Tab Navigation inside hero -->
                    <div class="relative mt-6 overflow-x-auto pb-1 -mx-1 px-1">
                        <div class="flex gap-2 w-max sm:w-full min-w-full">
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                @click="activeTab = tab.key"
                                type="button"
                                class="flex items-center gap-1.5 px-4 py-2.5 rounded-2xl text-xs font-black uppercase tracking-wide whitespace-nowrap transition-all duration-200 flex-1 justify-center active:scale-95"
                                :class="activeTab === tab.key
                                    ? 'bg-white text-indigo-700 shadow-lg scale-105'
                                    : 'bg-white/15 text-white hover:bg-white/25 backdrop-blur'"
                            >
                                <component :is="tab.icon" class="w-3.5 h-3.5 flex-shrink-0" />
                                <span class="hidden sm:inline">{{ tab.label }}</span>
                                <span
                                    class="rounded-full px-1.5 py-0.5 text-[10px] font-black leading-none"
                                    :class="activeTab === tab.key
                                        ? 'bg-indigo-100 text-indigo-700'
                                        : 'bg-white/20 text-white'"
                                >
                                    {{ tab.count }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- FABRICS TAB -->
                <div v-if="activeTab === 'fabrics'">
                    <section v-if="department === 'knitting'" class="knitting-supervisor-review mb-6 rounded-3xl border border-slate-700 bg-slate-900 p-5 text-white shadow-sm dark:border-indigo-900 dark:bg-slate-900 dark:text-white">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div><h2 class="text-lg font-black !text-white">Knitting supervisor review</h2><p class="mt-1 text-sm font-semibold !text-slate-100">Completed Job Orders submitted by Knitting Yarn staff appear here for release to Dyeing or return for remake.</p></div>
                            <span class="rounded-full bg-slate-700 px-3 py-1 text-xs font-black !text-white">{{ knittingHandoffs.length }} awaiting review</span>
                        </div>
                        <p v-if="!knittingHandoffs.length" class="mt-4 rounded-xl bg-slate-800 px-4 py-3 text-sm font-semibold !text-white">No completed Job Orders are waiting for review.</p>
                        <article v-for="handoff in knittingHandoffs" :key="handoff.id" class="mt-4 rounded-2xl border border-slate-700 bg-slate-800 p-4 dark:border-zinc-700 dark:bg-slate-800">
                            <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="font-mono text-base font-black !text-white">{{ handoff.jo_number }}</p><p class="mt-1 text-sm font-semibold !text-slate-100">{{ handoff.client }} Â· Submitted {{ handoff.submitted_at }}</p><p class="mt-1 text-sm font-bold !text-white">{{ handoff.approved_weight }} / {{ handoff.total_quantity }} kg inspector approved</p><p class="mt-1 text-sm font-semibold !text-slate-100">Knitting Yarn staff: {{ handoff.staff.join(', ') || 'Assigned staff' }}</p></div><div class="flex flex-wrap gap-2"><button type="button" :disabled="!canEditProduction || Number(handoff.approved_weight) + 0.001 < Number(handoff.total_quantity)" @click="releaseKnittingHandoff(handoff)" class="rounded-xl bg-emerald-700 px-4 py-2 text-xs font-black text-white disabled:opacity-40">Push to Dyeing</button></div></div>
                            <p class="mt-4 text-xs font-semibold !text-slate-200">Check only the damaged fabric rolls to return. Unchecked rolls stay approved.</p>
                            <div class="mt-2 flex flex-wrap gap-2"><label v-for="fabric in handoff.fabrics" :key="fabric.id" class="flex cursor-pointer items-center gap-2 rounded-lg bg-slate-700 px-2.5 py-2 text-xs font-bold !text-white"><input type="checkbox" :checked="isHandoffFabricSelected(handoff.id, fabric.id)" @change="toggleHandoffFabric(handoff.id, fabric.id, $event.target.checked)" class="rounded border-slate-400 text-rose-600 focus:ring-rose-500"/><span class="!text-white">{{ fabric.code }} · {{ fabric.weight }} kg · {{ fabric.operator || 'Operator unknown' }}</span></label></div>
                            <div v-if="canEditProduction" class="mt-4 flex flex-col gap-2 sm:flex-row"><textarea v-model="declineReasons[handoff.id]" rows="2" maxlength="500" placeholder="Reason for return (required)" class="min-w-0 flex-1 rounded-xl border-slate-300 bg-white text-xs text-slate-900 placeholder:text-slate-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder:text-zinc-400"/><button type="button" :disabled="!declineReasons[handoff.id]?.trim() || !(selectedHandoffFabrics[handoff.id]?.length)" @click="declineKnittingHandoff(handoff)" class="rounded-xl bg-rose-700 px-4 py-2 text-xs font-black text-white disabled:opacity-40">Decline selected fabric(s) and request remake</button></div>
                        </article>
                    </section>
                    <section v-if="department === 'knitting' && knittingReplacementHandoffs.length" class="mb-6 rounded-3xl border border-amber-300 bg-amber-50 p-5 shadow-sm dark:border-amber-500/30 dark:bg-amber-500/5">
                        <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-lg font-black text-amber-950 dark:text-amber-100">Dyeing replacement fabrics awaiting knitting supervisor review</h2><p class="mt-1 text-sm text-amber-800 dark:text-amber-200">Inspector-approved replacement rolls sent by Knitting Yarn staff.</p></div><span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-black text-amber-900 dark:bg-amber-500/15 dark:text-amber-100">{{ knittingReplacementHandoffs.length }} fabric(s)</span></div>
                        <article v-for="handoff in knittingReplacementHandoffs" :key="handoff.id" class="mt-4 rounded-2xl border border-amber-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="font-mono text-base font-black text-slate-900 dark:text-white">{{ handoff.jo_number }} · replacement {{ handoff.replacement_fabric?.code }}</p><p class="mt-1 text-sm text-slate-600 dark:text-zinc-300">Original fabric {{ handoff.original_fabric?.code }} · {{ handoff.replacement_fabric?.yarn_type }} · {{ handoff.replacement_fabric?.weight }} kg</p><p class="mt-1 text-xs text-slate-500">Knitting Yarn staff: {{ handoff.replacement_fabric?.operator || handoff.staff || 'Assigned staff' }} · {{ handoff.code }}</p><p class="mt-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-900 dark:bg-amber-500/10 dark:text-amber-100"><b>Original Dyeing issue:</b> {{ handoff.reason }}</p></div><div class="flex gap-2"><button type="button" :disabled="!canEditProduction" @click="askKnittingReplacementReview(handoff, 'remake')" class="rounded-xl bg-rose-700 px-4 py-2 text-xs font-black text-white disabled:opacity-50">Return for remake</button><button type="button" :disabled="!canEditProduction" @click="askKnittingReplacementReview(handoff, 'pass')" class="rounded-xl bg-emerald-700 px-4 py-2 text-xs font-black text-white disabled:opacity-50">Pass to Dyeing</button></div></div>
                        </article>
                    </section>
                    <div v-if="!fabrics?.length"
                         class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                            <Shirt class="h-9 w-9 text-indigo-400" />
                        </div>
                        <p class="text-sm font-black text-gray-700 dark:text-gray-200">No fabrics awaiting quality check</p>
                        <p class="text-xs text-gray-400 mt-1">New fabrics will appear here.</p>
                    </div>

                    <TransitionGroup v-else name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div
                            v-for="(fabric, i) in fabrics"
                            :key="fabric.id"
                            :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                            class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden"
                        >
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-black text-gray-900 dark:text-white font-mono tracking-wide">{{ fabric.code }}</span>
                                <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30 ring-1 text-[10px] font-black uppercase px-2.5 py-1 rounded-full">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Fabric
                                </span>
                            </div>
                            <div class="mb-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Weight: <span class="font-black text-gray-900 dark:text-white">{{ fabric.weight }} kg</span>
                                </p>
                            </div>
                            <p class="mt-auto rounded-xl bg-amber-50 px-3 py-2 text-center text-xs font-bold text-amber-800 dark:bg-amber-900/20 dark:text-amber-200">Awaiting Knitting Inspector</p>
                        </div>
                    </TransitionGroup>
                </div>

                <!-- DYEING FLOW TABS -->
                <div v-if="['bleaching', 'pre_treatment', 'color', 'post_treatment'].includes(activeTab)">
                    <div v-if="!dyeingStagesForActiveTab.length" class="animate-fade-up flex flex-col items-center justify-center rounded-3xl border border-dashed bg-white py-20 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <Droplet class="mb-4 h-9 w-9 text-indigo-400" />
                        <p class="text-sm font-black text-gray-700 dark:text-gray-200">No {{ humanize(activeTab) }} work in progress</p>
                        <p class="mt-1 text-xs text-gray-400">Staff submissions will appear here when they are ready for supervisor review.</p>
                    </div>
                    <TransitionGroup v-else name="card" tag="div" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <article v-for="(item, i) in dyeingStagesForActiveTab" :key="item.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }" class="flex flex-col rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="flex items-start justify-between gap-3"><div><p class="font-mono text-sm font-black text-slate-900 dark:text-white">{{ item.jo_number || 'Job Order' }}</p><p class="mt-1 text-xs text-slate-500">Fabric {{ item.fabric_code }} Â· {{ item.weight }} kg</p></div><span class="rounded-full bg-indigo-100 px-2.5 py-1 text-[9px] font-black uppercase text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">{{ humanize(item.current_stage) }}</span></div>
                            <div class="mt-4 space-y-1.5 text-xs text-slate-600 dark:text-zinc-300"><p><b>Selected route:</b> {{ item.requires_bleaching ? 'Bleaching â†’ Pre Treatment â†’ Color â†’ Post Treatment' : 'Pre Treatment â†’ Color â†’ Post Treatment' }}</p><p v-if="item.color"><b>Color:</b> {{ item.color }}</p><p v-if="item.yarn_type"><b>Yarn:</b> {{ item.yarn_type }}</p><p><b>Batch / machine:</b> {{ item.batch_code }} Â· {{ item.machine_no }}</p><p><b>Submitted by:</b> {{ item.operator || 'Unknown staff member' }}</p><p v-if="item.attempt"><b>Inspection:</b> Attempt {{ item.attempt }} Â· Awaiting supervisor review</p></div>
                            <div class="mt-3 rounded-xl bg-indigo-50 px-3 py-2 text-xs font-bold text-indigo-800 dark:bg-indigo-500/10 dark:text-indigo-200">
                                {{ item.stage_progress?.passed ?? 0 }} of {{ item.stage_progress?.total ?? 0 }} fabric codes passed {{ humanize(item.current_stage) }}
                            </div>
                            <div class="mt-3 rounded-xl border border-slate-200 p-3 dark:border-zinc-700">
                                <div class="flex items-center justify-between gap-2 text-xs font-black text-slate-800 dark:text-zinc-100">
                                    <span>Fabric codes in this batch</span>
                                    <span>{{ item.batch_total_weight_kg ?? 0 }} kg total</span>
                                </div>
                                <div class="mt-2 divide-y divide-slate-100 dark:divide-zinc-800">
                                    <div v-for="fabric in item.batch_fabrics || []" :key="fabric.code" class="flex items-center justify-between gap-3 py-2 text-xs">
                                        <span class="font-mono font-bold text-slate-800 dark:text-zinc-100">{{ fabric.code }}</span>
                                        <span class="text-right text-slate-500 dark:text-zinc-400">{{ fabric.weight }} kg · {{ humanize(fabric.current_stage) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 rounded-xl border border-slate-200 p-3 dark:border-zinc-700">
                                <p class="text-xs font-black text-slate-800 dark:text-zinc-100">{{ item.recipe_source || 'No production recipe linked to this Job Order' }}</p>
                                <template v-if="item.recipe_source">
                                    <div v-for="(rows, group) in item.recipe_groups" :key="group">
                                        <div v-if="rows?.length" class="mt-2">
                                            <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">{{ humanize(group) }}</p>
                                            <p v-for="(row, rowIndex) in rows" :key="`${group}-${rowIndex}`" class="mt-1 text-xs text-slate-700 dark:text-zinc-300">
                                                {{ row.name }} · {{ row.dose }} {{ row.dose_unit }} → <b>{{ row.amount_kg }} kg</b>
                                            </p>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <div v-if="canEditProduction && item.record_id" class="mt-5 flex gap-2 border-t border-slate-100 pt-4 dark:border-zinc-800">
                                <button type="button" @click="requestDyeingPass(item)" class="flex-1 rounded-xl bg-emerald-700 px-4 py-2.5 text-xs font-black text-white transition hover:bg-emerald-800">Pass this fabric</button>
                                <button type="button" @click="requestDyeingReprocess(item)" class="flex-1 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-xs font-black text-rose-700 transition hover:bg-rose-100 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">Reprocess</button>
                            </div>
                        </article>
                    </TransitionGroup>
                </div>

                <!-- FINISHING SQUEEZER TAB -->
                <div v-if="activeTab === 'squeezer'">
                    <div v-if="!squeezerJobs?.length" class="animate-fade-up flex flex-col items-center justify-center rounded-3xl border border-dashed bg-white py-20 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <Factory class="mb-4 h-9 w-9 text-indigo-400"/><p class="text-sm font-black text-gray-700 dark:text-gray-200">No Finishing Squeezer jobs in progress</p>
                    </div>
                    <TransitionGroup v-else name="card" tag="div" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <article v-for="job in squeezerJobs" :key="job.id" class="flex flex-col rounded-3xl border bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <p class="font-mono text-sm font-black">{{ job.code }}</p><p class="mt-2 text-xs text-gray-500">Fabric: <span class="font-mono font-bold">{{ job.fabric?.code }}</span></p>
                            <p class="mt-auto rounded-xl bg-amber-50 px-3 py-2 text-center text-xs font-bold text-amber-800 dark:bg-amber-900/20 dark:text-amber-200">Awaiting Finishing Shrinkage Inspector</p>
                        </article>
                    </TransitionGroup>
                </div>

                <!-- FINISHING IRONING TAB -->
                <div v-if="activeTab === 'iron'">
                    <div v-if="!ironJobs?.length"
                         class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                            <Flame class="h-9 w-9 text-indigo-400" />
                        </div>
                        <p class="text-sm font-black text-gray-700 dark:text-gray-200">No Finishing Heatsetting jobs in progress</p>
                        <p class="text-xs text-gray-400 mt-1">New iron jobs will appear here.</p>
                    </div>

                    <TransitionGroup v-else name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div
                            v-for="(iron, i) in ironJobs"
                            :key="iron.id"
                            :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                            class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden"
                        >
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-black text-gray-900 dark:text-white font-mono tracking-wide">{{ iron.code }}</span>
                                <span class="inline-flex items-center gap-1 bg-orange-100 text-orange-700 ring-orange-200 dark:bg-orange-500/15 dark:text-orange-300 dark:ring-orange-500/30 ring-1 text-[10px] font-black uppercase px-2.5 py-1 rounded-full">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Ironing
                                </span>
                            </div>
                            <div class="mb-4">
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Fabric: <span class="font-bold text-gray-800 dark:text-gray-200 font-mono">{{ iron.squeezerJob?.fabric?.code }}</span>
                                </p>
                            </div>
                            <p class="mt-auto rounded-xl bg-amber-50 px-3 py-2 text-center text-xs font-bold text-amber-800 dark:bg-amber-900/20 dark:text-amber-200">Awaiting Finishing Shrinkage Inspector</p>
                        </div>
                    </TransitionGroup>
                </div>

                <!-- PACKED TAB -->
                <div v-if="activeTab === 'packed'">
                    <div v-if="!packages?.length && !finishingReadyOrders?.length"
                         class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                            <Package class="h-9 w-9 text-indigo-400" />
                        </div>
                        <p class="text-sm font-black text-gray-700 dark:text-gray-200">No packages or quality-approved JOs ready</p>
                        <p class="text-xs text-gray-400 mt-1">Packed packages and JOs that pass final Quality Check will appear here.</p>
                    </div>

                    <section v-if="finishingReadyOrders?.length" class="mb-6 space-y-3">
                        <h2 class="text-sm font-black uppercase tracking-wide text-gray-700 dark:text-gray-200">Final Quality Check Passed · Ready for Logistics</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                            <article v-for="order in finishingReadyOrders" :key="`finishing-${order.id}`" class="rounded-3xl border border-emerald-200 bg-white p-5 shadow-sm dark:border-emerald-900 dark:bg-zinc-900">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="font-mono text-sm font-black text-gray-900 dark:text-white">{{ order.jo_number || `JO #${order.id}` }}</h3>
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-black uppercase text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">QC Passed</span>
                                </div>
                                <ul class="my-4 space-y-2 text-xs text-gray-600 dark:text-gray-300">
                                    <li v-for="item in order.items" :key="item.code" class="flex justify-between gap-3">
                                        <span class="font-mono font-bold">{{ item.code }}</span><span>{{ Number(item.weight).toLocaleString() }} kg</span>
                                    </li>
                                </ul>
                                <div class="mb-4 border-t border-gray-100 pt-3 text-xs font-black text-gray-900 dark:border-zinc-800 dark:text-white">Total: {{ Number(order.total_weight_kg).toLocaleString() }} kg</div>
                                <button v-if="canEditProduction" @click="pushFinishingOrderToLogistics(order)" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-3 py-2.5 text-xs font-black text-white transition hover:bg-emerald-700">
                                    <Truck class="h-3.5 w-3.5" /> Push JO to Logistics
                                </button>
                            </article>
                        </div>
                    </section>

                    <TransitionGroup v-if="packages?.length" name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div
                            v-for="(pkg, i) in packages"
                            :key="pkg.id"
                            :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                            class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden"
                        >
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-black text-gray-900 dark:text-white font-mono tracking-wide">{{ pkg.code }}</span>
                                <span
                                    class="inline-flex items-center gap-1 ring-1 text-[10px] font-black uppercase px-2.5 py-1 rounded-full"
                                    :class="pkg.status === 'checked'
                                        ? 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30'
                                        : 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30'"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />
                                    {{ pkg.status === 'checked' ? 'Checked Ãƒâ€šÃ‚Â· ready' : 'Awaiting QC' }}
                                </span>
                            </div>
                            <div class="mb-4 space-y-1">
                                <p class="text-sm font-black text-gray-900 dark:text-white tracking-tight">{{ pkg.product_name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">SKU: {{ pkg.product_sku }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Fabric: {{ pkg.fabric_code }} ({{ pkg.yarn_type }}, {{ pkg.weight }} kg)</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Quantity: <span class="font-black text-gray-900 dark:text-white">{{ pkg.quantity }} rolls/pcs</span></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Operator: {{ pkg.operator }}</p>
                            </div>
                            <div class="mt-auto flex flex-col sm:flex-row gap-2">
                                <button v-if="canEditProduction && pkg.status === 'checked'"
                                    @click="pushToLogistics(pkg)"
                                    class="flex-1 flex items-center justify-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black px-3 py-2.5 rounded-2xl shadow-lg shadow-emerald-500/25 transition hover:-translate-y-0.5 active:scale-95"
                                >
                                    <Truck class="w-3.5 h-3.5" /> Push to Logistics
                                </button>
                                <Link
                                    v-else
                                    :href="route('man.assignments.index')"
                                    class="flex-1 flex items-center justify-center gap-1.5 bg-gray-900 hover:bg-gray-700 text-white text-xs font-black px-3 py-2.5 rounded-2xl transition hover:-translate-y-0.5 active:scale-95"
                                >
                                    <ClipboardList class="w-3.5 h-3.5" /> Assignments
                                </Link>
                            </div>
                        </div>
                    </TransitionGroup>
                </div>

            </div>
        </div>

        <div v-if="reprocessEvaluation" class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="reprocess-evaluation-title">
            <section class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                <p class="text-xs font-black uppercase tracking-widest text-rose-600">Dyeing Department · Fabric {{ reprocessEvaluation.fabric_code }}</p>
                <h2 id="reprocess-evaluation-title" class="mt-1 text-xl font-black text-slate-900 dark:text-white">Evaluate the problem</h2>
                <p class="mt-1 text-sm text-slate-500">Choose what should happen to this fabric and record why it needs reprocessing.</p>

                <fieldset class="mt-5 space-y-2">
                    <legend class="mb-2 text-xs font-black uppercase tracking-wide text-slate-500">Reprocess level</legend>
                    <label class="flex cursor-pointer gap-3 rounded-xl border p-3" :class="reprocessLevel === 'replacement' ? 'border-rose-400 bg-rose-50 dark:border-rose-500/50 dark:bg-rose-500/10' : 'border-slate-200 dark:border-zinc-700'">
                        <input v-model="reprocessLevel" type="radio" value="replacement" class="mt-1 text-rose-600">
                        <span><b class="block text-sm text-slate-900 dark:text-white">Reject and issue a replacement fabric</b><span class="mt-0.5 block text-xs text-slate-500">Send only this fabric code to the Knitting Supervisor for staff assignment.</span></span>
                    </label>
                    <label class="flex cursor-pointer gap-3 rounded-xl border p-3" :class="reprocessLevel === 'redo' ? 'border-amber-400 bg-amber-50 dark:border-amber-500/50 dark:bg-amber-500/10' : 'border-slate-200 dark:border-zinc-700'">
                        <input v-model="reprocessLevel" type="radio" value="redo" class="mt-1 text-amber-600">
                        <span><b class="block text-sm text-slate-900 dark:text-white">Remake and process again</b><span class="mt-0.5 block text-xs text-slate-500">Return this fabric to the same stage and staff assignment for another attempt.</span></span>
                    </label>
                </fieldset>

                <fieldset class="mt-5">
                    <legend class="mb-2 text-xs font-black uppercase tracking-wide text-slate-500">Problem reasons *</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label v-for="reason in reprocessReasonOptions" :key="reason.key" class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 dark:border-zinc-700 dark:text-zinc-200"><input v-model="reprocessReasons" type="checkbox" :value="reason.key" class="rounded text-indigo-600">{{ reason.label }}</label>
                    </div>
                    <textarea v-model="reprocessDetails" rows="3" class="mt-3 w-full rounded-xl border border-slate-200 bg-white p-3 text-sm dark:border-zinc-700 dark:bg-zinc-800" placeholder="Additional details (optional)"></textarea>
                    <p v-if="reprocessEvaluationError" class="mt-2 text-xs font-bold text-rose-600">{{ reprocessEvaluationError }}</p>
                </fieldset>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" @click="reprocessEvaluation = null" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold dark:border-zinc-700">Cancel</button>
                    <button type="button" @click="continueDyeingReprocess" class="rounded-xl bg-rose-700 px-4 py-2 text-sm font-black text-white">Continue to confirmation</button>
                </div>
            </section>
        </div>

        <div v-if="reprocessConfirmation" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-4" role="alertdialog" aria-modal="true" aria-labelledby="dyeing-reprocess-confirm-title">
            <section class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                <h2 id="dyeing-reprocess-confirm-title" class="text-xl font-black text-slate-900 dark:text-white">{{ reprocessConfirmation.level === 'replacement' ? 'Confirm replacement request' : 'Confirm staff reprocess' }}</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-zinc-300">{{ reprocessConfirmation.level === 'replacement' ? `Reject fabric ${reprocessConfirmation.item.fabric_code} and send only this fabric to the Knitting Supervisor for a replacement?` : `Return fabric ${reprocessConfirmation.item.fabric_code} to the same ${humanize(reprocessConfirmation.item.current_stage)} staff member for another attempt?` }}</p>
                <p class="mt-3 rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-zinc-800 dark:text-zinc-300"><b>Recorded reason:</b> {{ reprocessConfirmation.reason }}</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" :disabled="reviewSubmitting" @click="reprocessConfirmation = null" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold dark:border-zinc-700">Go back</button>
                    <button type="button" :disabled="reviewSubmitting" @click="confirmDyeingReprocess" class="rounded-xl bg-rose-700 px-4 py-2 text-sm font-black text-white disabled:opacity-50">{{ reviewSubmitting ? 'Saving…' : (reprocessConfirmation.level === 'replacement' ? 'Send to Knitting' : 'Return for remake') }}</button>
                </div>
            </section>
        </div>

        <!-- CONFIRM MODAL -->
        <Transition name="modal">
            <div
                v-if="confirmModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="cancelConfirm"
            >
                <div class="relative w-full max-w-sm bg-white/95 dark:bg-zinc-900/95 backdrop-blur rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-zinc-800">
                    <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 pt-5 pb-4 text-center">
                        <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                        <div class="relative w-12 h-12 mx-auto mb-3 rounded-2xl bg-white/15 ring-1 ring-white/30 flex items-center justify-center">
                            <AlertTriangle class="w-6 h-6 text-white" />
                        </div>
                        <h3 class="relative text-base font-black text-white tracking-tight">{{ confirmLabel }}</h3>
                        <p class="relative text-sm text-blue-100/90 mt-1.5 leading-relaxed">{{ confirmSub }}</p>
                    </div>

                    <div class="flex gap-2 px-6 py-5">
                        <button
                            @click="cancelConfirm"
                            type="button"
                            class="flex-1 py-2.5 rounded-2xl border border-gray-200 dark:border-zinc-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-zinc-800 text-xs font-black uppercase tracking-wide transition active:scale-95"
                        >
                            Cancel
                        </button>
                        <button
                            @click="runConfirm"
                            type="button"
                            :disabled="reviewSubmitting"
                            class="flex-1 py-2.5 rounded-2xl text-white text-xs font-black uppercase tracking-wide shadow-lg transition active:scale-95"
                            :class="confirmTone === 'success'
                                ? 'bg-emerald-600 hover:bg-emerald-700 shadow-emerald-500/25'
                                : 'bg-rose-600 hover:bg-rose-700 shadow-rose-500/25'"
                        >
                            {{ confirmAccept }}
                        </button>
                    </div>
                </div>

            </div>
        </Transition>

        <div v-if="reviewResult" class="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/60 p-4" role="alertdialog" aria-modal="true" aria-labelledby="dyeing-review-result-title">
            <section class="w-full max-w-md rounded-3xl bg-white p-6 text-center shadow-2xl dark:bg-zinc-900">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl text-2xl font-black" :class="reviewResult.success ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300'">{{ reviewResult.success ? '✓' : '!' }}</div>
                <h2 id="dyeing-review-result-title" class="mt-4 text-xl font-black text-slate-900 dark:text-white">{{ reviewResult.title }}</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-zinc-300">{{ reviewResult.message }}</p>
                <button type="button" @click="reviewResult = null" class="mt-5 rounded-xl bg-indigo-700 px-5 py-2.5 text-sm font-black text-white">Close</button>
            </section>
        </div>

        <div v-if="pendingKnittingRejection" class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-950/60 p-4" @click.self="!knittingRejectionProcessing && (pendingKnittingRejection = null)">
            <section role="dialog" aria-modal="true" aria-labelledby="knitting-rejection-info-title" class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300"><Info class="h-7 w-7" /></div>
                <h2 id="knitting-rejection-info-title" class="mt-4 text-center text-xl font-black text-slate-900 dark:text-white">Information before fabric rejection</h2>
                <p class="mt-3 text-center text-sm leading-relaxed text-slate-700 dark:text-zinc-300">Rejecting even one selected fabric returns the entire JO <strong>{{ pendingKnittingRejection.jo_number }}</strong> to the Knitting Yarn staff work page for reprocessing. Only the selected fabric(s) will be marked for remake; the other fabrics remain approved. The assigned machine will require a new knitting mechanic setup before the remake.</p>
                <div class="mt-6 flex justify-center gap-3"><button type="button" :disabled="knittingRejectionProcessing" @click="pendingKnittingRejection = null" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 disabled:opacity-50 dark:border-zinc-700 dark:text-zinc-200">Go back</button><button type="button" :disabled="knittingRejectionProcessing" @click="confirmKnittingRejection" class="rounded-xl bg-rose-700 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50">{{ knittingRejectionProcessing ? 'Rejecting…' : 'Continue with rejection' }}</button></div>
            </section>
        </div>

        <div v-if="pendingKnittingReplacementReview" class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-950/60 p-4" @click.self="!knittingReplacementProcessing && (pendingKnittingReplacementReview = null)">
            <section role="alertdialog" aria-modal="true" aria-labelledby="knitting-replacement-review-title" class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                <h2 id="knitting-replacement-review-title" class="text-xl font-black text-slate-900 dark:text-white">{{ pendingKnittingReplacementReview.decision === 'pass' ? 'Pass replacement to Dyeing?' : 'Return replacement for another make?' }}</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-zinc-300">{{ pendingKnittingReplacementReview.decision === 'pass' ? `Approve ${pendingKnittingReplacementReview.replacement_fabric?.code} and send this specific replacement through the original JO's Dyeing route?` : `Reject ${pendingKnittingReplacementReview.replacement_fabric?.code} and send this specific fabric back to its Knitting Yarn staff member?` }}</p>
                <textarea v-if="pendingKnittingReplacementReview.decision === 'remake'" v-model="knittingReplacementReason" rows="3" maxlength="500" class="mt-4 w-full rounded-xl border border-slate-200 p-3 text-sm dark:border-zinc-700 dark:bg-zinc-800" placeholder="Reason for return (required)"></textarea>
                <p v-if="knittingReplacementError" class="mt-2 text-sm font-bold text-rose-600">{{ knittingReplacementError }}</p>
                <div class="mt-5 flex justify-end gap-2"><button type="button" :disabled="knittingReplacementProcessing" @click="pendingKnittingReplacementReview = null" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold dark:border-zinc-700">Cancel</button><button type="button" :disabled="knittingReplacementProcessing" @click="submitKnittingReplacementReview" class="rounded-xl px-4 py-2 text-sm font-black text-white disabled:opacity-50" :class="pendingKnittingReplacementReview.decision === 'pass' ? 'bg-emerald-700' : 'bg-rose-700'">{{ knittingReplacementProcessing ? 'Saving…' : 'Confirm' }}</button></div>
            </section>
        </div>
        <div v-if="knittingReplacementResult" class="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/60 p-4"><section class="w-full max-w-md rounded-3xl bg-white p-6 text-center shadow-2xl dark:bg-zinc-900"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl text-2xl font-black" :class="knittingReplacementResult.success ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">{{ knittingReplacementResult.success ? '✓' : '!' }}</div><h2 class="mt-4 text-xl font-black text-slate-900 dark:text-white">{{ knittingReplacementResult.title }}</h2><p class="mt-2 text-sm text-slate-600 dark:text-zinc-300">{{ knittingReplacementResult.message }}</p><button type="button" @click="knittingReplacementResult = null" class="mt-5 rounded-xl bg-indigo-700 px-5 py-2.5 text-sm font-black text-white">Close</button></section></div>

    </AuthenticatedLayout>
</template>

<style scoped>
.knitting-supervisor-review h2,
.knitting-supervisor-review p,
.knitting-supervisor-review span:not(.h-1\.5) {
    color: #ffffff !important;
}

:global(.dark) .knitting-supervisor-review h2,
:global(.dark) .knitting-supervisor-review p,
:global(.dark) .knitting-supervisor-review span:not(.h-1\.5) {
    color: #ffffff !important;
}
</style>

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
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: translateY(16px) scale(0.98); }
</style>
