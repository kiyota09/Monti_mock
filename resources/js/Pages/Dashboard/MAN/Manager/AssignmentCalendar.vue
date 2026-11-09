<script setup>
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import { CalendarDays, ChevronLeft, ChevronRight, Clock3, Factory, UserRound, Weight } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    department: { type: String, required: true },
    departmentLabel: { type: String, default: 'Manufacturing' },
    assignments: { type: Array, default: () => [] },
})

const today = new Date()
const dateKey = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
const monthCursor = ref(new Date(today.getFullYear(), today.getMonth(), 1))
const selectedDate = ref(dateKey(today))
const title = computed(() => monthCursor.value.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' }))
const assignmentsByDate = computed(() => {
    const map = new Map()
    for (const assignment of props.assignments) {
        if (!assignment.deadline) continue
        if (!map.has(assignment.deadline)) map.set(assignment.deadline, [])
        map.get(assignment.deadline).push(assignment)
    }
    return map
})
const cells = computed(() => {
    const first = new Date(monthCursor.value.getFullYear(), monthCursor.value.getMonth(), 1)
    const start = new Date(first)
    start.setDate(first.getDate() - first.getDay())
    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(start)
        date.setDate(start.getDate() + index)
        const key = dateKey(date)
        const events = assignmentsByDate.value.get(key) || []
        return {
            key,
            day: date.getDate(),
            inMonth: date.getMonth() === monthCursor.value.getMonth(),
            isToday: key === dateKey(today),
            events,
            load: events.reduce((total, item) => total + Number(item.weight_kg || 0), 0),
        }
    })
})
const selectedAssignments = computed(() => assignmentsByDate.value.get(selectedDate.value) || [])
const unscheduled = computed(() => props.assignments.filter((item) => !item.deadline))
const totalLoad = computed(() => props.assignments.reduce((sum, item) => sum + Number(item.weight_kg || 0), 0))
const overdueCount = computed(() => props.assignments.filter((item) => item.deadline && item.deadline < dateKey(today)).length)
const upcoming = computed(() => props.assignments.filter((item) => item.deadline >= dateKey(today)).sort((a, b) => a.deadline.localeCompare(b.deadline)).slice(0, 8))
const monthMove = (delta) => {
    monthCursor.value = new Date(monthCursor.value.getFullYear(), monthCursor.value.getMonth() + delta, 1)
}
const stageLabel = (value) => String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
const formatDate = (value) => value ? new Date(`${value}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : 'No deadline'
const isPastDue = (assignment) => assignment.deadline < dateKey(today)
</script>

<template>
    <Head :title="`${departmentLabel} Assignment Calendar`" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-[1800px] space-y-6 p-4 sm:p-6">
            <header class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-indigo-800 via-blue-800 to-cyan-800 p-6 text-white shadow-xl sm:p-8">
                <div class="absolute -right-20 -top-24 h-80 w-80 rounded-full bg-white/10 blur-3xl" />
                <div class="relative flex flex-wrap items-center gap-4">
                    <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25"><CalendarDays class="h-8 w-8" /></span>
                    <div class="min-w-0 flex-1"><p class="text-xs font-black uppercase tracking-[0.22em] text-blue-100">Manufacturing · Supervisor view</p><h1 class="mt-1 text-3xl font-black sm:text-4xl">{{ departmentLabel }} production calendar</h1><p class="mt-2 max-w-2xl text-sm text-blue-100">Monitor assigned Job Orders, staff deadlines, and planned kilograms across the department.</p></div>
                    <a href="/dashboard/man/assignments" class="rounded-xl bg-white px-4 py-2.5 text-xs font-black text-indigo-800 shadow hover:bg-blue-50">JO assignment board</a>
                </div>
            </header>

            <section class="grid gap-3 sm:grid-cols-3">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Active assignments</p><p class="mt-1 text-3xl font-black text-slate-900 dark:text-white">{{ assignments.length }}</p></article>
                <article class="rounded-2xl border border-indigo-100 bg-indigo-50 p-4 shadow-sm dark:border-indigo-900/50 dark:bg-indigo-950/30"><p class="text-xs font-bold uppercase tracking-wide text-indigo-600 dark:text-indigo-300">Active workload</p><p class="mt-1 text-3xl font-black text-indigo-900 dark:text-indigo-100">{{ totalLoad.toLocaleString('en-PH', { maximumFractionDigits: 1 }) }} <span class="text-base">kg</span></p></article>
                <article class="rounded-2xl border border-rose-100 bg-rose-50 p-4 shadow-sm dark:border-rose-900/50 dark:bg-rose-950/30"><p class="text-xs font-bold uppercase tracking-wide text-rose-600 dark:text-rose-300">Past deadline</p><p class="mt-1 text-3xl font-black text-rose-900 dark:text-rose-100">{{ overdueCount }}</p></article>
            </section>

            <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
                        <div><p class="text-xs font-black uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-300">Department workload</p><h2 class="mt-1 text-2xl font-black text-slate-900 dark:text-white">{{ title }}</h2></div>
                        <div class="flex items-center gap-2"><button type="button" @click="monthMove(-1)" aria-label="Previous month" class="rounded-xl border border-slate-200 p-2.5 hover:bg-slate-50 dark:border-zinc-700 dark:hover:bg-zinc-800"><ChevronLeft class="h-5 w-5" /></button><button type="button" @click="monthCursor = new Date(today.getFullYear(), today.getMonth(), 1); selectedDate = dateKey(today)" class="rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-black dark:border-zinc-700">Today</button><button type="button" @click="monthMove(1)" aria-label="Next month" class="rounded-xl border border-slate-200 p-2.5 hover:bg-slate-50 dark:border-zinc-700 dark:hover:bg-zinc-800"><ChevronRight class="h-5 w-5" /></button></div>
                    </header>
                    <div class="grid grid-cols-7 border-b border-slate-100 bg-slate-50/80 dark:border-zinc-800 dark:bg-zinc-800/40"><div v-for="day in ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']" :key="day" class="px-2 py-3 text-center text-[10px] font-black uppercase tracking-wider text-slate-500 sm:text-xs">{{ day }}</div></div>
                    <div class="grid grid-cols-7">
                        <button v-for="cell in cells" :key="cell.key" type="button" @click="selectedDate = cell.key" :class="['group min-h-28 border-b border-r border-slate-100 p-1.5 text-left transition sm:min-h-36 sm:p-2.5 dark:border-zinc-800', cell.inMonth ? 'bg-white dark:bg-zinc-900' : 'bg-slate-50/70 dark:bg-zinc-950/50', selectedDate === cell.key ? 'relative z-10 ring-2 ring-inset ring-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/30' : 'hover:bg-blue-50/60 dark:hover:bg-blue-950/20']">
                            <div class="flex items-center justify-between gap-1"><span :class="['flex h-7 w-7 items-center justify-center rounded-full text-xs font-black sm:h-8 sm:w-8', cell.isToday ? 'bg-indigo-700 text-white' : cell.inMonth ? 'text-slate-800 dark:text-zinc-100' : 'text-slate-300 dark:text-zinc-700']">{{ cell.day }}</span><span v-if="cell.load" class="rounded-full bg-indigo-100 px-1.5 py-1 text-[9px] font-black text-indigo-800 dark:bg-indigo-500/20 dark:text-indigo-200">{{ cell.load.toLocaleString('en-PH', { maximumFractionDigits: 0 }) }} kg</span></div>
                            <div class="mt-2 space-y-1"><div v-for="item in cell.events.slice(0, 3)" :key="item.id" :class="['rounded-md border-l-2 px-1.5 py-1 text-[9px] leading-tight sm:text-[10px]', isPastDue(item) ? 'border-rose-500 bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-200' : 'border-indigo-500 bg-indigo-50 text-indigo-800 dark:bg-indigo-950/50 dark:text-indigo-200']"><span class="block truncate font-black">{{ item.jo_number }}</span><span class="hidden truncate sm:block">{{ stageLabel(item.stage) }} · {{ item.staff }}</span></div><p v-if="cell.events.length > 3" class="px-1 text-[9px] font-bold text-slate-500">+{{ cell.events.length - 3 }} more</p></div>
                        </button>
                    </div>
                </article>

                <aside class="space-y-5">
                    <article v-if="unscheduled.length" class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/20"><h3 class="text-sm font-black text-amber-900 dark:text-amber-200">No deadline set · {{ unscheduled.length }}</h3><div class="mt-3 space-y-2"><div v-for="item in unscheduled" :key="item.id" class="flex justify-between gap-3 rounded-xl bg-white/70 px-3 py-2 text-xs dark:bg-zinc-900/50"><span class="min-w-0"><span class="block truncate font-mono font-black">{{ item.jo_number }}</span><span class="text-amber-800 dark:text-amber-200">{{ item.staff }} · {{ stageLabel(item.stage) }}</span></span><span class="shrink-0 font-bold">{{ Number(item.weight_kg || 0).toLocaleString() }} kg</span></div></div></article>
                    <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-300">Selected day</p><h2 class="mt-1 text-xl font-black text-slate-900 dark:text-white">{{ formatDate(selectedDate) }}</h2>
                        <p class="mt-1 text-xs text-slate-500">{{ selectedAssignments.length }} assignment(s) · {{ selectedAssignments.reduce((sum, item) => sum + Number(item.weight_kg || 0), 0).toLocaleString('en-PH', { maximumFractionDigits: 1 }) }} kg</p>
                        <div v-if="selectedAssignments.length" class="mt-4 space-y-3"><article v-for="item in selectedAssignments" :key="item.id" class="rounded-2xl border p-3" :class="isPastDue(item) ? 'border-rose-200 bg-rose-50/70 dark:border-rose-900/50 dark:bg-rose-950/20' : 'border-slate-200 bg-slate-50 dark:border-zinc-700 dark:bg-zinc-800/50'"><div class="flex items-start justify-between gap-2"><p class="font-mono text-sm font-black text-slate-900 dark:text-white">{{ item.jo_number }}</p><span class="rounded-full bg-white px-2 py-1 text-[9px] font-black uppercase text-slate-600 dark:bg-zinc-900 dark:text-zinc-300">{{ item.status.replace('_', ' ') }}</span></div><p class="mt-1 text-xs font-bold text-indigo-700 dark:text-indigo-300">{{ stageLabel(item.stage) }}</p><p v-if="item.fabric_code" class="mt-1 text-xs text-slate-500">Fabric {{ item.fabric_code }}</p><div class="mt-3 grid grid-cols-2 gap-2 text-xs"><p class="flex items-center gap-1.5 text-slate-600 dark:text-zinc-300"><UserRound class="h-3.5 w-3.5" />{{ item.staff }}</p><p class="flex items-center gap-1.5 text-slate-600 dark:text-zinc-300"><Weight class="h-3.5 w-3.5" />{{ Number(item.weight_kg || 0).toLocaleString() }} kg</p></div></article></div><p v-else class="mt-4 rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500 dark:border-zinc-700">No deadlines on this date.</p>
                    </article>
                    <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"><h3 class="flex items-center gap-2 text-sm font-black text-slate-900 dark:text-white"><Clock3 class="h-4 w-4 text-indigo-600" /> Upcoming deadlines</h3><div v-if="upcoming.length" class="mt-3 space-y-2"><button v-for="item in upcoming" :key="item.id" type="button" @click="selectedDate = item.deadline; monthCursor = new Date(`${item.deadline}T00:00:00`)" class="flex w-full items-center justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2.5 text-left hover:bg-indigo-50 dark:bg-zinc-800/60 dark:hover:bg-indigo-950/30"><span class="min-w-0"><span class="block truncate font-mono text-xs font-black">{{ item.jo_number }}</span><span class="block truncate text-[10px] text-slate-500">{{ item.staff }} · {{ stageLabel(item.stage) }}</span></span><span class="shrink-0 text-[10px] font-black text-indigo-700 dark:text-indigo-300">{{ new Date(`${item.deadline}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }) }}</span></button></div><p v-else class="mt-3 text-sm text-slate-500">No upcoming deadlines.</p></article>
                </aside>
            </section>
        </main>
    </AuthenticatedLayout>
</template>
