<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Stamp, Sparkles, CheckCircle2, XCircle, Users, Truck, CreditCard, UserCog, Clock } from 'lucide-vue-next';

const props = defineProps({
    counts: Object,
    payrolls: Array,
    vendors: Array,
    credits: Array,
    vacancies: Array,
    recentDecisions: Array,
});

const tab = ref('payroll');
const reason = ref('');
const reasonFor = ref(null);

const decide = (routeName, id, decision, needReason, label) => {
    if (needReason && reasonFor.value !== label) {
        reasonFor.value = label;
        reason.value = '';
        return;
    }
    const payload = needReason ? { reason: reason.value, rejection_reason: reason.value } : {};
    if (needReason && !reason.value.trim()) return;
    router.post(route(routeName, id), payload, {
        preserveScroll: true,
        onSuccess: () => { reasonFor.value = null; reason.value = ''; },
    });
};

const peso = (v) => '₱' + new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(v ?? 0);
const tabs = [
    { key: 'payroll', label: `Payroll (${props.counts.payrolls})`, icon: Users },
    { key: 'vendors', label: `Vendors (${props.counts.vendors})`, icon: Truck },
    { key: 'credits', label: `Credit (${props.counts.credits})`, icon: CreditCard },
    { key: 'vacancies', label: `Vacancies (${props.counts.vacancies})`, icon: UserCog },
];
</script>

<template>
    <Head title="Approvals Center" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><Stamp class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> CEO · Decision Queue</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Approvals Center</h1>
                            <p class="text-sm text-blue-100/90">{{ counts.payrolls + counts.vendors + counts.credits }} items awaiting decision · every decision is logged</p>
                        </div>
                    </div>
                    <div class="relative mt-6 flex flex-wrap gap-2">
                        <button v-for="t in tabs" :key="t.key" @click="tab = t.key"
                            :class="['flex items-center gap-1.5 rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide transition-all', tab === t.key ? 'bg-white dark:bg-zinc-900 text-indigo-700 dark:text-indigo-300 shadow-lg' : 'bg-white/15 text-white ring-1 ring-white/25 backdrop-blur hover:bg-white/25']">
                            <component :is="t.icon" class="h-4 w-4" /> {{ t.label }}
                        </button>
                    </div>
                </div>

                <!-- Payroll -->
                <div v-if="tab === 'payroll'" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto"><table class="w-full">
                        <thead class="bg-gray-50/80 dark:bg-zinc-800/50"><tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase">Employee</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase">Net Pay</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase">Decision</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <tr v-for="p in payrolls" :key="p.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20">
                                <td class="px-6 py-4 text-sm font-bold">{{ p.employee_name ?? `#${p.employee_id}` }}<p class="text-[11px] font-medium text-gray-400 dark:text-zinc-500">Payroll #{{ p.id }} · {{ p.days_worked ?? '—' }} days</p></td>
                                <td class="px-6 py-4 font-black">{{ peso(p.net_pay) }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button @click="decide('ceo.approvals.payroll.approve', p.id, 'approved', false)" class="inline-flex items-center gap-1 rounded-xl bg-emerald-600 px-3 py-1.5 text-[11px] font-black uppercase text-white hover:bg-emerald-700 active:scale-95 transition"><CheckCircle2 class="h-3.5 w-3.5" /> Approve</button>
                                        <button @click="decide('ceo.approvals.payroll.reject', p.id, 'rejected', true, `payroll-${p.id}`)" class="inline-flex items-center gap-1 rounded-xl bg-gray-100 dark:bg-zinc-800 px-3 py-1.5 text-[11px] font-black uppercase text-rose-600 hover:bg-rose-50 transition"><XCircle class="h-3.5 w-3.5" /> Reject</button>
                                    </div>
                                    <div v-if="reasonFor === `payroll-${p.id}`" class="mt-2 flex gap-2 justify-end">
                                        <input v-model="reason" placeholder="Rejection reason (required)" class="rounded-xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-1.5 text-xs outline-none focus:ring-2 focus:ring-rose-400 w-56" />
                                        <button @click="decide('ceo.approvals.payroll.reject', p.id, 'rejected', true, `payroll-${p.id}`)" class="rounded-xl bg-rose-600 px-3 py-1.5 text-[11px] font-black uppercase text-white">Confirm</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!payrolls?.length"><td colspan="3" class="px-6 py-10 text-center text-sm text-gray-400 dark:text-zinc-500">No pending payroll. Queue clear.</td></tr>
                        </tbody>
                    </table></div>
                </div>

                <!-- Vendors -->
                <div v-if="tab === 'vendors'" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto"><table class="w-full">
                        <thead class="bg-gray-50/80 dark:bg-zinc-800/50"><tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase">Business</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase">Contact</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase">Decision</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <tr v-for="v in vendors" :key="v.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20">
                                <td class="px-6 py-4 text-sm font-bold">{{ v.business_name }}<p class="text-[11px] font-medium text-gray-400 dark:text-zinc-500">{{ v.email }}</p></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-zinc-400">{{ v.representative_name ?? '—' }}<p class="text-[11px] text-gray-400 dark:text-zinc-500">{{ v.phone_number ?? '' }}</p></td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button @click="decide('ceo.approvals.vendor.approve', v.id, 'approved', false)" class="inline-flex items-center gap-1 rounded-xl bg-emerald-600 px-3 py-1.5 text-[11px] font-black uppercase text-white hover:bg-emerald-700 active:scale-95 transition"><CheckCircle2 class="h-3.5 w-3.5" /> Approve</button>
                                        <button @click="decide('ceo.approvals.vendor.reject', v.id, 'rejected', true, `vendor-${v.id}`)" class="inline-flex items-center gap-1 rounded-xl bg-gray-100 dark:bg-zinc-800 px-3 py-1.5 text-[11px] font-black uppercase text-rose-600 hover:bg-rose-50 transition"><XCircle class="h-3.5 w-3.5" /> Reject</button>
                                    </div>
                                    <div v-if="reasonFor === `vendor-${v.id}`" class="mt-2 flex gap-2 justify-end">
                                        <input v-model="reason" placeholder="Rejection reason (required)" class="rounded-xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-1.5 text-xs outline-none focus:ring-2 focus:ring-rose-400 w-56" />
                                        <button @click="decide('ceo.approvals.vendor.reject', v.id, 'rejected', true, `vendor-${v.id}`)" class="rounded-xl bg-rose-600 px-3 py-1.5 text-[11px] font-black uppercase text-white">Confirm</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!vendors?.length"><td colspan="3" class="px-6 py-10 text-center text-sm text-gray-400 dark:text-zinc-500">No pending vendor registrations.</td></tr>
                        </tbody>
                    </table></div>
                </div>

                <!-- Credit -->
                <div v-if="tab === 'credits'" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto"><table class="w-full">
                        <thead class="bg-gray-50/80 dark:bg-zinc-800/50"><tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase">PO / Client</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase">Amount</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase">Decision</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <tr v-for="o in credits" :key="o.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20">
                                <td class="px-6 py-4 text-sm font-bold font-mono">{{ o.po_number }}<p class="font-sans text-[11px] font-medium text-gray-400 dark:text-zinc-500">{{ o.client }}</p></td>
                                <td class="px-6 py-4 font-black">{{ peso(o.total_amount) }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button @click="decide('ceo.approvals.credit.approve', o.id, 'approved', false)" class="inline-flex items-center gap-1 rounded-xl bg-emerald-600 px-3 py-1.5 text-[11px] font-black uppercase text-white hover:bg-emerald-700 active:scale-95 transition"><CheckCircle2 class="h-3.5 w-3.5" /> Approve</button>
                                        <button @click="decide('ceo.approvals.credit.reject', o.id, 'rejected', true, `credit-${o.id}`)" class="inline-flex items-center gap-1 rounded-xl bg-gray-100 dark:bg-zinc-800 px-3 py-1.5 text-[11px] font-black uppercase text-rose-600 hover:bg-rose-50 transition"><XCircle class="h-3.5 w-3.5" /> Reject</button>
                                    </div>
                                    <div v-if="reasonFor === `credit-${o.id}`" class="mt-2 flex gap-2 justify-end">
                                        <input v-model="reason" placeholder="Rejection reason (required)" class="rounded-xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-1.5 text-xs outline-none focus:ring-2 focus:ring-rose-400 w-56" />
                                        <button @click="decide('ceo.approvals.credit.reject', o.id, 'rejected', true, `credit-${o.id}`)" class="rounded-xl bg-rose-600 px-3 py-1.5 text-[11px] font-black uppercase text-white">Confirm</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!credits?.length"><td colspan="3" class="px-6 py-10 text-center text-sm text-gray-400 dark:text-zinc-500">No orders pending credit review.</td></tr>
                        </tbody>
                    </table></div>
                </div>

                <!-- Vacancies -->
                <div v-if="tab === 'vacancies'" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-6">
                    <h2 class="text-sm font-black mb-1">Supervisor Seats Without a Holder</h2>
                    <p class="text-xs text-gray-500 dark:text-zinc-400 mb-4">Request appointments via CEO Access → Command to IT (fulfilled in IT Access Control).</p>
                    <div v-if="vacancies?.length" class="flex flex-wrap gap-2">
                        <span v-for="d in vacancies" :key="d" class="rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-800 ring-1 ring-amber-200 dark:text-amber-300 dark:ring-amber-500/30 px-4 py-1.5 text-xs font-black uppercase">{{ d }} vacant</span>
                    </div>
                    <p v-else class="text-sm text-emerald-600 dark:text-emerald-400 font-bold">All department supervisor seats are filled.</p>
                </div>

                <!-- Recent decisions -->
                <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2">
                        <Clock class="h-4 w-4 text-indigo-500" />
                        <h2 class="text-sm font-black">Recent Executive Decisions</h2>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-zinc-800">
                        <li v-for="d in recentDecisions" :key="d.id" class="px-6 py-3 text-sm flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span :class="d.decision === 'approved' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 ring-emerald-200' : 'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300 ring-rose-200 dark:ring-rose-500/30'" class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase ring-1">{{ d.decision }}</span>
                            <span class="font-bold">{{ d.subject_label }}</span>
                            <span class="text-xs text-gray-400 dark:text-zinc-500 ml-auto">{{ d.actor?.name }} · {{ new Date(d.created_at).toLocaleString() }}</span>
                            <p v-if="d.reason" class="w-full text-xs text-gray-500 dark:text-zinc-400">Reason: {{ d.reason }}</p>
                        </li>
                        <li v-if="!recentDecisions?.length" class="px-6 py-8 text-center text-xs text-gray-400 dark:text-zinc-500">No decisions recorded yet.</li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
</style>
