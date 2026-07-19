<script setup lang="ts">
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Calculator, FilePenLine } from 'lucide-vue-next';

type AccountingState = 'pending' | 'posted' | 'shared' | 'reversed' | 'superseded' | 'no_charge' | 'unposted';

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    voucher: any;
    groupPosting: any;
    voucherPosting: any;
    groupConsolidated: any;
    passengerSummary: { total: number; adults: number; children: number; infants: number; visa: number; transport_only: number };
    canManageGroupAccounting: boolean;
    canEditVoucher: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Vouchers', href: `/${props.company.slug}/umrah/vouchers` },
    { title: props.voucher.voucher_number, href: `/${props.company.slug}/umrah/vouchers/${props.voucher.id}` },
    { title: 'Accounting', href: `/${props.company.slug}/umrah/vouchers/${props.voucher.id}/accounting` },
];

const stateLabels: Record<AccountingState, string> = {
    pending: 'Pending approval',
    posted: 'Posted',
    shared: 'Shared billing',
    reversed: 'Reversed',
    superseded: 'Superseded',
    no_charge: 'No charge',
    unposted: 'Needs review',
};

const stateVariant = (state: AccountingState) => {
    if (state === 'posted') return 'default';
    if (['reversed', 'unposted'].includes(state)) return 'destructive';
    return 'secondary';
};
</script>

<template>
    <Head :title="`${voucher.voucher_number} Accounting`" />
    <PageShell
        title="Voucher Accounting"
        :description="`${voucher.voucher_number} · ${voucher.agent?.name || 'No agent'}`"
        :breadcrumbs="breadcrumbs"
        :icon="Calculator"
    >
        <template #actions>
            <Button variant="outline" @click="router.get(`/${company.slug}/umrah/vouchers/${voucher.id}`)">
                <ArrowLeft class="mr-2 h-4 w-4" />Voucher
            </Button>
            <Button
                v-if="canManageGroupAccounting"
                variant="outline"
                @click="router.get(`/${company.slug}/umrah/groups/${groupPosting.id}/accounting`)"
            >
                <Calculator class="mr-2 h-4 w-4" />Group Accounting
            </Button>
            <Button
                v-if="canEditVoucher && voucher.status === 'draft'"
                variant="outline"
                @click="router.get(`/${company.slug}/umrah/vouchers/${voucher.id}/edit`)"
            >
                <FilePenLine class="mr-2 h-4 w-4" />Edit Voucher
            </Button>
        </template>

        <div class="grid grid-cols-2 gap-px overflow-hidden rounded-md border bg-border sm:grid-cols-3 xl:grid-cols-6">
            <div
                v-for="item in [
                    ['Total pax', passengerSummary.total],
                    ['Adults', passengerSummary.adults],
                    ['Children', passengerSummary.children],
                    ['Infants', passengerSummary.infants],
                    ['Visa', passengerSummary.visa],
                    ['Transport only', passengerSummary.transport_only],
                ]"
                :key="String(item[0])"
                class="bg-background p-4"
            >
                <div class="text-sm text-muted-foreground">{{ item[0] }}</div>
                <div class="mt-1 text-2xl font-semibold">{{ item[1] }}</div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <CardTitle>Inherited from Group</CardTitle>
                            <CardDescription>{{ groupPosting.group_number }} · Posted when visa and transport were approved</CardDescription>
                        </div>
                        <Badge :variant="stateVariant(groupPosting.accounting_state)">
                            {{ stateLabels[groupPosting.accounting_state as AccountingState] }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader><TableRow><TableHead>Group service</TableHead><TableHead>Supplier</TableHead><TableHead class="text-right">Revenue</TableHead><TableHead class="text-right">Cost</TableHead></TableRow></TableHeader>
                        <TableBody>
                            <TableRow>
                                <TableCell class="font-medium">Visa</TableCell>
                                <TableCell>{{ groupPosting.vendor?.name || 'Not assigned' }}</TableCell>
                                <TableCell class="text-right"><MoneyText :amount="groupPosting.visa_sale_amount" :currency="company.base_currency" /></TableCell>
                                <TableCell class="text-right"><MoneyText :amount="groupPosting.visa_cost_amount" :currency="company.base_currency" /></TableCell>
                            </TableRow>
                            <TableRow>
                                <TableCell class="font-medium">Transport</TableCell>
                                <TableCell>{{ groupPosting.mandatory_transport_vendor?.name || 'Fare suppliers' }}</TableCell>
                                <TableCell class="text-right"><MoneyText :amount="groupPosting.transport_amount" :currency="company.base_currency" /></TableCell>
                                <TableCell class="text-right"><MoneyText :amount="groupPosting.transport_cost_amount" :currency="company.base_currency" /></TableCell>
                            </TableRow>
                            <TableRow v-if="Number(groupPosting.discount_amount) > 0">
                                <TableCell class="font-medium">Group discount</TableCell>
                                <TableCell>Group adjustment</TableCell>
                                <TableCell class="text-right">-<MoneyText :amount="groupPosting.discount_amount" :currency="company.base_currency" /></TableCell>
                                <TableCell />
                            </TableRow>
                            <TableRow class="font-semibold">
                                <TableCell>Group stage total</TableCell><TableCell />
                                <TableCell class="text-right"><MoneyText :amount="groupPosting.revenue" :currency="company.base_currency" /></TableCell>
                                <TableCell class="text-right"><MoneyText :amount="groupPosting.cost" :currency="company.base_currency" /></TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div class="border-t px-6 py-3 text-xs text-muted-foreground">Displayed for context. These amounts remain owned and posted by the parent group.</div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <CardTitle>Added by Voucher</CardTitle>
                            <CardDescription>Company-booked hotels post when this voucher is approved</CardDescription>
                        </div>
                        <Badge :variant="stateVariant(voucherPosting.accounting_state)">
                            {{ stateLabels[voucherPosting.accounting_state as AccountingState] }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="voucher.billing_voucher" class="rounded-md border px-4 py-3 text-sm">
                        Hotel accounting is owned by {{ voucher.billing_voucher.voucher_number }}. This separated voucher does not duplicate it.
                    </div>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div><div class="text-muted-foreground">Company stays</div><div class="mt-1 text-xl font-semibold">{{ voucherPosting.company_stays }}</div></div>
                        <div><div class="text-muted-foreground">Self-arranged stays</div><div class="mt-1 text-xl font-semibold">{{ voucherPosting.self_stays }}</div></div>
                    </div>
                    <div class="space-y-3 border-t pt-4 text-sm">
                        <div class="flex justify-between"><span>Hotel charge</span><MoneyText :amount="voucherPosting.hotel_sale_amount" :currency="company.base_currency" /></div>
                        <div class="flex justify-between"><span>Hotel cost</span><MoneyText :amount="voucherPosting.hotel_cost_amount" :currency="company.base_currency" /></div>
                        <div class="flex justify-between border-t pt-3 font-semibold"><span>Voucher hotel margin</span><MoneyText :amount="voucherPosting.profit" :currency="company.base_currency" /></div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Group Consolidated Position</CardTitle>
                <CardDescription>One group total after approved voucher hotel postings; inherited services are counted once</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3 xl:grid-cols-6">
                <div><div class="text-sm text-muted-foreground">Approved hotels</div><MoneyText class="mt-1 text-lg font-semibold" :amount="groupConsolidated.hotel_amount" :currency="company.base_currency" /></div>
                <div><div class="text-sm text-muted-foreground">Hotel cost</div><MoneyText class="mt-1 text-lg font-semibold" :amount="groupConsolidated.hotel_cost_amount" :currency="company.base_currency" /></div>
                <div><div class="text-sm text-muted-foreground">Receivable</div><MoneyText class="mt-1 text-lg font-semibold" :amount="groupConsolidated.total_receivable" :currency="company.base_currency" /></div>
                <div><div class="text-sm text-muted-foreground">Received</div><MoneyText class="mt-1 text-lg font-semibold" :amount="groupConsolidated.total_paid" :currency="company.base_currency" /></div>
                <div><div class="text-sm text-muted-foreground">Balance</div><MoneyText class="mt-1 text-lg font-semibold" :amount="groupConsolidated.balance" :currency="company.base_currency" /></div>
                <div><div class="text-sm text-muted-foreground">Profit</div><MoneyText class="mt-1 text-lg font-semibold" :amount="groupConsolidated.profit" :currency="company.base_currency" /></div>
            </CardContent>
        </Card>
    </PageShell>
</template>
