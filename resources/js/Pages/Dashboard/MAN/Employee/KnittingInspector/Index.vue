<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { requestInput, showNotice } from '@/lib/feedback';
import { CheckCircle, Factory, XCircle } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const props = defineProps({ fabrics: Array });
const { canEdit } = usePageAccess();
const canInspect = computed(() => canEdit('MAN', 'knitting_inspector'));
const approve = (fabric) => router.post(route('man.staff.knitting-inspector.pass-fabric', fabric.id), { action: 'approve', destination: 'dyeing' }, { preserveScroll: true });
const reject = async (fabric) => {
    const reason = await requestInput(`Why is fabric ${fabric.code} not approved?`, { input: 'textarea', title: 'Return for remake' });
    if (reason === null) return;
    if (!reason.trim()) return showNotice('A reason is required so Knitting staff know what to fix.');
    router.post(route('man.staff.knitting-inspector.pass-fabric', fabric.id), { action: 'reject', rejection_reason: reason.trim() }, { preserveScroll: true });
};
</script>

<template>
    <AuthenticatedLayout title="Knitting Inspector">
        <main class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <header class="rounded-3xl bg-gradient-to-br from-blue-700 to-indigo-800 p-7 text-white shadow-xl"><div class="flex items-center gap-4"><Factory class="h-10 w-10"/><div><p class="text-xs font-bold uppercase tracking-widest text-blue-100">Knitting Department</p><h1 class="text-3xl font-black">Knitting Inspector</h1><p class="mt-1 text-blue-100">Inspect completed fabric before it moves to Dyeing.</p></div></div></header>
            <section><div class="mb-4 flex items-center justify-between"><h2 class="text-xl font-black">Fabric awaiting inspection</h2><span class="rounded-full bg-blue-100 px-3 py-1 text-sm font-bold text-blue-800">{{ fabrics?.length ?? 0 }} waiting</span></div>
                <div v-if="!fabrics?.length" class="rounded-2xl border border-dashed p-12 text-center text-gray-500">No knitting output is waiting for inspection.</div>
                <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"><article v-for="fabric in fabrics" :key="fabric.id" class="rounded-2xl border bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"><div class="flex items-start justify-between"><div><p class="font-mono text-lg font-black">{{ fabric.code }}</p><p class="text-sm text-gray-500">{{ fabric.sales_order?.jo_number ?? fabric.sales_order?.client?.company_name ?? fabric.manufacturing_order?.code ?? 'Job Order' }}</p></div><span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">Pending</span></div><dl class="mt-4 space-y-1 text-sm"><div class="flex justify-between"><dt class="text-gray-500">Yarn</dt><dd class="font-semibold">{{ fabric.yarn_type }}</dd></div><div class="flex justify-between"><dt class="text-gray-500">Weight</dt><dd class="font-semibold">{{ fabric.weight }} kg</dd></div><div class="flex justify-between"><dt class="text-gray-500">Machine</dt><dd class="font-semibold">{{ fabric.machine?.machine_no ?? '—' }}</dd></div><div v-if="fabric.sales_order" class="flex justify-between"><dt class="text-gray-500">JO client</dt><dd class="font-semibold">{{ fabric.sales_order.client?.company_name }}</dd></div></dl><div v-if="canInspect" class="mt-5 flex gap-2"><button @click="approve(fabric)" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-3 py-2.5 text-sm font-bold text-white hover:bg-emerald-700"><CheckCircle class="h-4 w-4"/> Approve for supervisor handoff</button><button @click="reject(fabric)" class="flex items-center justify-center gap-2 rounded-xl border border-rose-200 px-3 py-2.5 text-sm font-bold text-rose-700 hover:bg-rose-50"><XCircle class="h-4 w-4"/> Return</button></div></article></div>
            </section>
        </main>
    </AuthenticatedLayout>
</template>
