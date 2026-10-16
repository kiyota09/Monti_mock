<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Search, Save, ShieldCheck } from 'lucide-vue-next';

const props = defineProps({
    users: { type: Array, default: () => [] },
    pages: { type: Object, default: () => ({}) },
    permissions: { type: Object, default: () => ({}) },
});

const searchQuery = ref('');
const savingUserId = ref(null);

const userPerms = ref({});
const initPerms = () => {
    const perms = {};
    props.users.forEach((user) => {
        perms[user.id] = {};
        Object.keys(props.pages).forEach((page) => {
            perms[user.id][page] = props.permissions[user.id]?.[page] || false;
        });
    });
    userPerms.value = perms;
};
initPerms();

const eligibleUsers = computed(() => {
    let users = props.users.filter((u) =>
        ['secretary', 'special_officer', 'manager', 'supervisor', 'staff'].includes(u.position)
    );
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        users = users.filter((u) => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q));
    }
    return users;
});

const pageKeys = computed(() => Object.keys(props.pages));

const saveUser = (userId) => {
    savingUserId.value = userId;
    router.post(route('it.access.update'), {
        user_id: userId,
        permissions: userPerms.value[userId],
    }, { preserveScroll: true, onFinish: () => { savingUserId.value = null; } });
};
</script>

<template>
    <Head title="IT Access Control | Monti Textile" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    IT Access <span class="text-blue-600">Control</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">
                    Native IT managers/staff have automatic access; these grants cover everyone else.
                </p>
            </div>
            <div class="relative w-full sm:w-72">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                <input v-model="searchQuery" type="text" placeholder="Search users..."
                    class="w-full pl-9 pr-4 py-2.5 text-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200 placeholder-slate-400" />
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left min-w-[720px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900/40 border-b border-slate-100 dark:border-slate-700">
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">User</th>
                            <th v-for="page in pageKeys" :key="page" class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">
                                {{ pages[page] }}
                            </th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Save</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="user in eligibleUsers" :key="user.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/60">
                            <td class="px-4 sm:px-6 py-3 sm:py-3.5">
                                <p class="text-sm font-bold text-slate-900 dark:text-white">{{ user.name }}</p>
                                <p class="text-[11px] text-slate-400">{{ user.role }} · {{ user.position }}</p>
                            </td>
                            <td v-for="page in pageKeys" :key="page" class="px-4 py-3.5 text-center">
                                <button @click="userPerms[user.id][page] = !userPerms[user.id][page]"
                                    :class="['w-9 h-9 sm:w-6 sm:h-6 rounded-md border inline-flex items-center justify-center transition-all',
                                        userPerms[user.id][page]
                                            ? 'bg-blue-600 border-blue-600 text-white'
                                            : 'bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-600 hover:border-blue-400']">
                                    <ShieldCheck v-if="userPerms[user.id][page]" class="w-4 h-4" />
                                </button>
                            </td>
                            <td class="px-4 sm:px-6 py-3 sm:py-3.5 text-right">
                                <button @click="saveUser(user.id)" :disabled="savingUserId === user.id"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1.5 text-xs font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                    <Save class="w-3.5 h-3.5" /> {{ savingUserId === user.id ? 'Saving…' : 'Save' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="eligibleUsers.length === 0" class="py-12 text-center text-sm text-slate-400 font-bold">No users found.</div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
