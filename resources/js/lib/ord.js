export const formatStatus = (status) =>
    String(status || 'unknown')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());

export const formatMoney = (value) =>
    `₱${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export const formatQuantity = (value, unit = 'kg') =>
    `${Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })} ${unit}`;

export const isOverdue = (row) => {
    if (!row?.expected_ship_date) return false;
    if (['completed', 'cancelled', 'rejected', 'delivered'].includes(row.status)) return false;

    return row.expected_ship_date < new Date().toISOString().slice(0, 10);
};

export const paymentBadge = (status) => ({
    paid: 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800',
    partially_paid: 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800',
    unpaid: 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:border-rose-800',
}[status] || 'bg-gray-100 text-gray-600 border-gray-200 dark:bg-zinc-800 dark:text-zinc-400 dark:border-zinc-700');
