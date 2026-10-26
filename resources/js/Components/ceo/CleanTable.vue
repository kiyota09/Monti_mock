<script setup>
const props = defineProps({
    columns: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    empty: { type: String, default: 'No records found.' },
    getRowKey: { type: [String, Function], default: null },
});

const rowKey = (row, index) => {
    if (typeof props.getRowKey === 'function') return props.getRowKey(row);
    if (typeof props.getRowKey === 'string') return row[props.getRowKey];
    return index;
};

const cellValue = (value) => (value === null || value === undefined || value === '' ? '—' : value);
</script>

<template>
    <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-zinc-800">
        <table class="min-w-full text-left text-[11px]">
            <thead class="bg-slate-50 dark:bg-zinc-800/60">
                <tr>
                    <th
                        v-for="col in columns"
                        :key="col.key"
                        class="whitespace-nowrap px-3 py-2 text-[9px] font-black uppercase tracking-wide text-slate-500 dark:text-zinc-400"
                        :class="col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'"
                    >
                        {{ col.label }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                <tr
                    v-for="(row, index) in rows"
                    :key="rowKey(row, index)"
                    class="align-top transition hover:bg-slate-50/70 dark:hover:bg-zinc-800/40"
                >
                    <td
                        v-for="col in columns"
                        :key="col.key"
                        class="px-3 py-2 font-medium text-slate-600 dark:text-zinc-300"
                        :class="[
                            col.wrap ? 'min-w-56 whitespace-normal' : 'whitespace-nowrap',
                            col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : '',
                            col.class,
                        ]"
                    >
                        <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]" :index="index">
                            {{ cellValue(row[col.key]) }}
                        </slot>
                    </td>
                </tr>
                <tr v-if="!rows.length">
                    <td :colspan="columns.length || 1" class="px-3 py-10 text-center text-[11px] text-slate-400 dark:text-zinc-500">
                        {{ empty }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
