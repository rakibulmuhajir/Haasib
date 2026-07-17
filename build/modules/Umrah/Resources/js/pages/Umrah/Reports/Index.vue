<script setup lang="ts">
import { computed, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import DateTimeText from '@/components/DateTimeText.vue'
import MoneyText from '@/components/MoneyText.vue'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import type { BreadcrumbItem } from '@/types'
import { BarChart3, ChevronLeft, ChevronRight, Download, Search } from 'lucide-vue-next'

type Column = { key: string; label: string; type: 'text' | 'money' | 'number' | 'date' | 'datetime' | 'status' }
type FilterDefinition = { key: string; label: string; type: 'select' | 'text'; options?: { value: string; label: string }[] }
type Summary = { label: string; value: number; type: 'money' | 'number' }

const props = defineProps<{
    company: { slug: string; name: string; base_currency: string }
    report: {
        key: string
        title: string
        description: string
        date_basis: string
        filters: Record<string, string | number | null>
        filter_definitions: FilterDefinition[]
        summary: Summary[]
        columns: Column[]
        rows: Record<string, unknown>[]
        pagination: { page: number; per_page: number; total: number; last_page: number }
    }
    reportLinks: { key: string; title: string }[]
}>()

const filters = reactive<Record<string, string>>({
    start: String(props.report.filters.start || ''),
    end: String(props.report.filters.end || ''),
    per_page: String(props.report.filters.per_page || 25),
})
for (const definition of props.report.filter_definitions) {
    filters[definition.key] = String(props.report.filters[definition.key] || 'all')
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Reports', href: `/${props.company.slug}/umrah/reports/${props.report.key}` },
    { title: props.report.title, href: `/${props.company.slug}/umrah/reports/${props.report.key}` },
]

const query = (page = 1) => {
    const values: Record<string, string | number> = { start: filters.start, end: filters.end, page, per_page: filters.per_page }
    for (const definition of props.report.filter_definitions) {
        const value = filters[definition.key]
        if (value && value !== 'all') values[definition.key] = value
    }
    return values
}

const applyFilters = () => router.get(`/${props.company.slug}/umrah/reports/${props.report.key}`, query(), { preserveState: true, replace: true })
const changeReport = (key: string) => router.get(`/${props.company.slug}/umrah/reports/${key}`)
const exportPdf = () => {
    const params = new URLSearchParams(query() as Record<string, string>).toString()
    window.location.href = `/${props.company.slug}/umrah/reports/${props.report.key}/pdf?${params}`
}
const goToPage = (page: number) => router.get(`/${props.company.slug}/umrah/reports/${props.report.key}`, query(page), { preserveState: true, preserveScroll: true })
const openRow = (row: Record<string, unknown>) => {
    if (row.href) router.get(`/${props.company.slug}${String(row.href)}`)
}
const numeric = (value: unknown) => Number(value || 0)
const statusLabel = (value: unknown) => String(value || '-').replaceAll('_', ' ')
const showing = computed(() => {
    if (!props.report.pagination.total) return '0 records'
    const start = (props.report.pagination.page - 1) * props.report.pagination.per_page + 1
    const end = Math.min(props.report.pagination.page * props.report.pagination.per_page, props.report.pagination.total)
    return `${start}-${end} of ${props.report.pagination.total}`
})
</script>

<template>
    <Head :title="report.title" />
    <PageShell :title="report.title" :description="report.description" :breadcrumbs="breadcrumbs" :icon="BarChart3">
        <div class="flex flex-col gap-3 border-b pb-4 lg:flex-row lg:items-end">
            <div class="w-full space-y-1.5 lg:w-64">
                <Label>Report</Label>
                <Select :model-value="report.key" @update:model-value="changeReport(String($event))">
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent><SelectItem v-for="link in reportLinks" :key="link.key" :value="link.key">{{ link.title }}</SelectItem></SelectContent>
                </Select>
            </div>
            <div class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
                <div class="space-y-1.5"><Label for="report-start">From</Label><Input id="report-start" v-model="filters.start" type="date" /></div>
                <div class="space-y-1.5"><Label for="report-end">To</Label><Input id="report-end" v-model="filters.end" type="date" /></div>
                <div v-for="definition in report.filter_definitions" :key="definition.key" class="space-y-1.5">
                    <Label :for="`filter-${definition.key}`">{{ definition.label }}</Label>
                    <Select v-if="definition.type === 'select'" v-model="filters[definition.key]">
                        <SelectTrigger :id="`filter-${definition.key}`"><SelectValue :placeholder="`All ${definition.label.toLowerCase()}`" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All</SelectItem>
                            <SelectItem v-for="option in definition.options" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Input v-else :id="`filter-${definition.key}`" v-model="filters[definition.key]" :placeholder="definition.label" @keyup.enter="applyFilters" />
                </div>
            </div>
            <div class="flex gap-2">
                <Button @click="applyFilters"><Search class="size-4" />Apply</Button>
                <Button variant="outline" @click="exportPdf"><Download class="size-4" />PDF</Button>
            </div>
        </div>

        <div class="flex items-center justify-between text-sm text-muted-foreground">
            <span>Date basis: {{ report.date_basis }}</span>
            <span>{{ report.filters.start }} to {{ report.filters.end }}</span>
        </div>

        <div v-if="report.summary.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <Card v-for="item in report.summary" :key="item.label" class="rounded-md">
                <CardHeader class="pb-1"><CardTitle class="text-sm font-medium text-muted-foreground">{{ item.label }}</CardTitle></CardHeader>
                <CardContent class="text-xl font-semibold tabular-nums">
                    <MoneyText v-if="item.type === 'money'" :amount="numeric(item.value)" :currency="company.base_currency" />
                    <span v-else>{{ numeric(item.value).toLocaleString() }}</span>
                </CardContent>
            </Card>
        </div>

        <Card class="overflow-hidden rounded-md">
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <Table class="min-w-max">
                        <TableHeader><TableRow><TableHead v-for="column in report.columns" :key="column.key" :class="{ 'text-right': ['money', 'number'].includes(column.type) }">{{ column.label }}</TableHead></TableRow></TableHeader>
                        <TableBody>
                            <TableEmpty v-if="!report.rows.length" :colspan="report.columns.length">No records found for this period.</TableEmpty>
                            <TableRow v-for="(row, index) in report.rows" :key="String(row.id || `${index}-${row.reference || row.group || row.voucher || ''}`)" :class="{ 'cursor-pointer hover:bg-muted/50': row.href }" @click="openRow(row)">
                                <TableCell v-for="column in report.columns" :key="column.key" :class="{ 'text-right tabular-nums': ['money', 'number'].includes(column.type), 'max-w-80 whitespace-normal': !['money', 'number', 'date', 'datetime', 'status'].includes(column.type) }">
                                    <MoneyText v-if="column.type === 'money'" :amount="numeric(row[column.key])" :currency="company.base_currency" />
                                    <DateTimeText v-else-if="column.type === 'date'" :value="String(row[column.key] || '')" mode="date" />
                                    <DateTimeText v-else-if="column.type === 'datetime'" :value="String(row[column.key] || '')" />
                                    <Badge v-else-if="column.type === 'status'" variant="secondary" class="capitalize">{{ statusLabel(row[column.key]) }}</Badge>
                                    <span v-else-if="column.type === 'number'">{{ numeric(row[column.key]).toLocaleString(undefined, { maximumFractionDigits: 2 }) }}</span>
                                    <span v-else>{{ row[column.key] || '-' }}</span>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
                <div class="flex items-center justify-between border-t px-4 py-3">
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <span>{{ showing }}</span>
                        <Select v-model="filters.per_page" @update:model-value="applyFilters">
                            <SelectTrigger class="h-8 w-24"><SelectValue /></SelectTrigger>
                            <SelectContent><SelectItem value="25">25 rows</SelectItem><SelectItem value="50">50 rows</SelectItem><SelectItem value="100">100 rows</SelectItem></SelectContent>
                        </Select>
                    </div>
                    <div class="flex gap-1">
                        <Button size="icon" variant="outline" :disabled="report.pagination.page <= 1" title="Previous page" @click="goToPage(report.pagination.page - 1)"><ChevronLeft class="size-4" /></Button>
                        <Button size="icon" variant="outline" :disabled="report.pagination.page >= report.pagination.last_page" title="Next page" @click="goToPage(report.pagination.page + 1)"><ChevronRight class="size-4" /></Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </PageShell>
</template>
