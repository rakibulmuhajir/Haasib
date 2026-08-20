<script setup lang="ts">
import DateTimeText from '@/components/DateTimeText.vue';
import LedgerRegister from '@/components/LedgerRegister.vue';
import MetaChip from '@/components/MetaChip.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFigure, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    backUrl?: string;
    statementUrl?: string;
}>();

const filter = reactive({
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
});
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Vendors', href: props.backUrl || `/${props.company.slug}/umrah/vendors` },
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
/**
 * A statement of account has two directions and they belong in two columns:
 * what the vendor charged, and what we paid against it. Allocated and advance
 * are a breakdown of the payment rather than a third direction, so they stay
 * plain figures. Both direction columns are ink -- the headings say which way
 * the money went and colour would only repeat them.
 */
const columns = [
    { key: 'date', label: 'Date', kind: 'date' as const },
    { key: 'reference', label: 'Reference', kind: 'ref' as const },
    { key: 'description', label: 'Description', kind: 'text' as const },
    { key: 'charge', label: 'Cost', kind: 'in' as const },
    { key: 'payment', label: 'Paid', kind: 'out' as const },
    { key: 'allocated', label: 'Allocated', kind: 'amount' as const },
    { key: 'advance', label: 'Advance', kind: 'amount' as const },
    { key: 'balance', label: 'Balance', kind: 'amount' as const },
];

const exportPdf = () => {
    const params = new URLSearchParams(query()).toString();
    const url = props.statementUrl || `/${props.company.slug}/umrah/vendors/${props.vendor.id}/statement.pdf`;
    window.location.href = `${url}${params ? `?${params}` : ''}`;
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
            <Button variant="outline" @click="router.get(backUrl || `/${company.slug}/umrah/vendors`)">
                <ArrowLeft class="mr-2 h-4 w-4" />Back
            </Button>
            <Button @click="exportPdf"><FileDown class="mr-2 h-4 w-4" />PDF</Button>
        </template>

        <div class="mb-5 flex flex-wrap items-end gap-3">
            <div class="space-y-1"><Label>From</Label><Input v-model="filter.date_from" type="date" /></div>
            <div class="space-y-1"><Label>To</Label><Input v-model="filter.date_to" type="date" /></div>
            <Button variant="outline" @click="applyFilters">Apply</Button>
            <MetaChip v-if="vendor.is_company_owned" tone="neutral">Company-owned provider</MetaChip>
        </div>

        <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card variant="figure"><CardHeader><CardTitle>Opening</CardTitle></CardHeader><CardContent><CardFigure><MoneyText :amount="statement.opening_balance" :currency="company.base_currency" /></CardFigure></CardContent></Card>
            <Card variant="figure"><CardHeader><CardTitle>Supplier Costs</CardTitle></CardHeader><CardContent><CardFigure><MoneyText :amount="statement.charges" :currency="company.base_currency" /></CardFigure></CardContent></Card>
            <Card variant="figure"><CardHeader><CardTitle>Payments</CardTitle></CardHeader><CardContent><CardFigure><MoneyText :amount="statement.payments" :currency="company.base_currency" /></CardFigure></CardContent></Card>
            <Card variant="figure"><CardHeader><CardTitle>Closing Payable</CardTitle></CardHeader><CardContent><CardFigure><MoneyText :amount="statement.closing_balance" :currency="company.base_currency" /></CardFigure></CardContent></Card>
        </div>

        <Card variant="register">
            <CardContent>
                <LedgerRegister
                    :data="statement.rows"
                    :columns="columns"
                    :key-field="(row: any, i: number) => `${row.date}-${row.type}-${row.reference}-${i}`"
                    :totals="{}"
                    totals-label="Period"
                >
                    <template #empty>No supplier activity in this period.</template>

                    <template #cell-date="{ row }">
                        <DateTimeText :value="row.date" mode="date" />
                    </template>

                    <template #cell-charge="{ row }">
                        <MoneyText :amount="row.charge" :currency="company.base_currency" />
                    </template>
                    <template #cell-payment="{ row }">
                        <MoneyText :amount="row.payment" :currency="company.base_currency" />
                    </template>
                    <template #cell-allocated="{ row }">
                        <MoneyText :amount="row.allocated" :currency="company.base_currency" />
                    </template>
                    <template #cell-advance="{ row }">
                        <MoneyText :amount="row.advance" :currency="company.base_currency" />
                    </template>
                    <template #cell-balance="{ row }">
                        <MoneyText :amount="row.balance" :currency="company.base_currency" class="font-medium" />
                    </template>

                    <template #total-charge>
                        <MoneyText :amount="statement.charges" :currency="company.base_currency" />
                    </template>
                    <template #total-payment>
                        <MoneyText :amount="statement.payments" :currency="company.base_currency" />
                    </template>
                    <template #total-balance>
                        <MoneyText :amount="statement.closing_balance" :currency="company.base_currency" />
                    </template>
                </LedgerRegister>
            </CardContent>
        </Card>
    </PageShell>
</template>
