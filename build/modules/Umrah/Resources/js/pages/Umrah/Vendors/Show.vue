<script setup lang="ts">
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, FileDown, Truck } from 'lucide-vue-next';
import { reactive } from 'vue';

const props = defineProps<{
    company: { slug: string; base_currency: string };
    vendor: any;
    statement: {
        opening_balance: number;
        charges: number;
        payments: number;
        closing_balance: number;
        rows: any[];
    };
    filters: { date_from?: string; date_to?: string };
}>();

const filter = reactive({
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
});
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Vendors', href: `/${props.company.slug}/umrah/vendors` },
    { title: props.vendor.name },
];
const query = () =>
    Object.fromEntries(Object.entries(filter).filter(([, value]) => value));
const applyFilters = () =>
    router.get(
        `/${props.company.slug}/umrah/vendors/${props.vendor.id}`,
        query(),
        { preserveState: true, replace: true },
    );
const exportPdf = () => {
    const params = new URLSearchParams(query()).toString();
    window.location.href = `/${props.company.slug}/umrah/vendors/${props.vendor.id}/statement.pdf${params ? `?${params}` : ''}`;
};
</script>

<template>
    <Head :title="`${vendor.name} Statement`" />
    <PageShell
        :title="vendor.name"
        description="Supplier costs, payments, advances, and outstanding payable."
        :breadcrumbs="breadcrumbs"
        :icon="Truck"
    >
        <template #actions>
            <Button variant="outline" @click="router.get(`/${company.slug}/umrah/vendors`)">
                <ArrowLeft class="mr-2 h-4 w-4" />Back
            </Button>
            <Button @click="exportPdf"><FileDown class="mr-2 h-4 w-4" />PDF</Button>
        </template>

        <div class="mb-5 flex flex-wrap items-end gap-3">
            <div class="space-y-1"><Label>From</Label><Input v-model="filter.date_from" type="date" /></div>
            <div class="space-y-1"><Label>To</Label><Input v-model="filter.date_to" type="date" /></div>
            <Button variant="outline" @click="applyFilters">Apply</Button>
            <Badge v-if="vendor.is_company_owned" variant="secondary">Company-owned provider</Badge>
        </div>

        <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card><CardHeader><CardTitle class="text-sm">Opening</CardTitle></CardHeader><CardContent><MoneyText :amount="statement.opening_balance" :currency="company.base_currency" /></CardContent></Card>
            <Card><CardHeader><CardTitle class="text-sm">Supplier Costs</CardTitle></CardHeader><CardContent><MoneyText :amount="statement.charges" :currency="company.base_currency" /></CardContent></Card>
            <Card><CardHeader><CardTitle class="text-sm">Payments</CardTitle></CardHeader><CardContent><MoneyText :amount="statement.payments" :currency="company.base_currency" /></CardContent></Card>
            <Card><CardHeader><CardTitle class="text-sm">Closing Payable</CardTitle></CardHeader><CardContent class="font-semibold"><MoneyText :amount="statement.closing_balance" :currency="company.base_currency" /></CardContent></Card>
        </div>

        <Card>
            <CardContent class="p-0">
                <Table>
                    <TableHeader><TableRow><TableHead>Date</TableHead><TableHead>Reference</TableHead><TableHead>Description</TableHead><TableHead class="text-right">Cost</TableHead><TableHead class="text-right">Paid</TableHead><TableHead class="text-right">Allocated</TableHead><TableHead class="text-right">Advance</TableHead><TableHead class="text-right">Balance</TableHead></TableRow></TableHeader>
                    <TableBody>
                        <TableEmpty v-if="!statement.rows.length" :colspan="8">No supplier activity in this period.</TableEmpty>
                        <TableRow v-for="row in statement.rows" :key="`${row.date}-${row.type}-${row.reference}-${row.description}`">
                            <TableCell>{{ row.date }}</TableCell><TableCell class="font-medium">{{ row.reference }}</TableCell><TableCell>{{ row.description }}</TableCell>
                            <TableCell class="text-right"><MoneyText :amount="row.charge" :currency="company.base_currency" /></TableCell>
                            <TableCell class="text-right"><MoneyText :amount="row.payment" :currency="company.base_currency" /></TableCell>
                            <TableCell class="text-right"><MoneyText :amount="row.allocated" :currency="company.base_currency" /></TableCell>
                            <TableCell class="text-right"><MoneyText :amount="row.advance" :currency="company.base_currency" /></TableCell>
                            <TableCell class="text-right font-medium"><MoneyText :amount="row.balance" :currency="company.base_currency" /></TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </PageShell>
</template>
