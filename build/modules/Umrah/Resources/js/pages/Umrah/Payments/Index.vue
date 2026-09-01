<script setup lang="ts">
import DateTimeText from '@/components/DateTimeText.vue';
import LedgerRegister from '@/components/LedgerRegister.vue';
import MetaChip from '@/components/MetaChip.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import RecordPagination from '@/components/RecordPagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFigure, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { BreadcrumbItem } from '@/types';
import AllocatePaymentDialog from './components/AllocatePaymentDialog.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowDownLeft,
    ArrowUpRight,
    Plane,
    Search,
    WalletCards,
} from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    payments: any;
    summary: { received: number; sent: number };
    directions: Record<string, string>;
    filters: { search?: string; direction?: string };
    allocationGroups: Array<{
        id: string;
        party_key: string;
        group_number: string;
        name: string;
        outstanding_amount: number;
    }>;
    canRecordPayments: boolean;
    canSubmitPayments: boolean;
    canApprovePayments: boolean;
}>();

const search = ref(props.filters.search || '');
const direction = ref(props.filters.direction || 'all');
const selectedPayment = ref<any>(null);
const allocationOpen = ref(false);
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Payments', href: `/${props.company.slug}/umrah/payments` },
];

const applyFilters = () =>
    router.get(
        `/${props.company.slug}/umrah/payments`,
        {
            search: search.value || undefined,
            direction: direction.value === 'all' ? undefined : direction.value,
        },
        { preserveState: true, replace: true },
    );

/**
 * A payment is either sent or received, but that is one row's fact, not two
 * columns' worth -- so amount stays a single neutral figure and direction is
 * a plain annotation chip rather than a colour repeating what the heading
 * already says. Reversed is a book state, so it goes through the shared
 * vocabulary instead of a destructive-red badge; an ordinary reversal is not
 * an adverse one.
 */
const columns = [
    { key: 'payment_number', label: 'Payment', kind: 'ref' as const },
    { key: 'payment_date', label: 'Date', kind: 'date' as const },
    { key: 'party', label: 'Party', kind: 'text' as const },
    { key: 'allocation', label: 'Allocation', kind: 'text' as const },
    { key: 'direction', label: 'Direction', kind: 'text' as const },
    { key: 'account_code', label: 'Account #', kind: 'ref' as const },
    { key: 'account_name', label: 'Account', kind: 'text' as const },
    { key: 'amount', label: 'Amount', kind: 'amount' as const },
    { key: 'available', label: 'Available', kind: 'amount' as const },
    { key: 'actions', label: '', kind: 'text' as const, class: 'text-right', headerClass: 'text-right' },
];

const availableAmount = (payment: any) =>
    Math.max(
        Number(payment.base_amount) -
            (payment.allocations || []).reduce(
                (sum: number, allocation: any) =>
                    sum + Number(allocation.base_amount),
                0,
            ),
        0,
    );
const openAllocation = (payment: any) => {
    selectedPayment.value = payment;
    allocationOpen.value = true;
};
</script>

<template>
    <Head title="Travel Payments" />
    <PageShell
        title="Payments"
        description="Money received from agents and sent to travel vendors."
        :breadcrumbs="breadcrumbs"
        :icon="WalletCards"
    >
        <template #actions>
            <Button
                v-if="canRecordPayments"
                @click="router.get(`/${company.slug}/umrah/payments/create`)"
                ><Plane class="mr-2 h-4 w-4" />Record Payment</Button
            >
            <Button
                v-if="canSubmitPayments"
                variant="outline"
                @click="router.get(`/${company.slug}/umrah/payments/submit`)"
                ><Plane class="mr-2 h-4 w-4" />Submit Payment</Button
            >
        </template>

        <div class="grid gap-4 md:grid-cols-2">
            <Card variant="figure"
                ><CardHeader
                    ><CardTitle class="flex items-center gap-2"
                        ><ArrowDownLeft
                            class="h-4 w-4 text-status-success"
                        />Received</CardTitle
                    ></CardHeader
                ><CardContent
                    ><CardFigure
                        ><MoneyText
                            :amount="summary.received"
                            :currency="company.base_currency"
                        /></CardFigure
                    ></CardContent
            ></Card>
            <Card v-if="directions.sent" variant="figure"
                ><CardHeader
                    ><CardTitle class="flex items-center gap-2"
                        ><ArrowUpRight
                            class="h-4 w-4 text-destructive"
                        />Sent</CardTitle
                    ></CardHeader
                ><CardContent
                    ><CardFigure
                        ><MoneyText
                            :amount="summary.sent"
                            :currency="company.base_currency"
                        /></CardFigure
                    ></CardContent
            ></Card>
        </div>

        <div class="flex flex-col gap-3 md:flex-row">
            <div class="relative flex-1">
                <Search
                    class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground"
                /><Input
                    v-model="search"
                    class="pl-9"
                    placeholder="Payment, reference, or group"
                    @keyup.enter="applyFilters"
                />
            </div>
            <Select
                v-if="directions.sent"
                v-model="direction"
                @update:model-value="applyFilters"
            >
                <SelectTrigger class="w-full md:w-48"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent
                    ><SelectItem value="all">All directions</SelectItem
                    ><SelectItem
                        v-for="(label, value) in directions"
                        :key="value"
                        :value="value"
                        >{{ label }}</SelectItem
                    ></SelectContent
                >
            </Select>
            <Button variant="outline" @click="applyFilters">Apply</Button>
        </div>

        <Card variant="register">
            <CardContent>
                <LedgerRegister
                    :data="payments.data"
                    :columns="columns"
                    @row-click="(row: any) => router.get(`/${company.slug}/umrah/payments/${row.id}`)"
                >
                    <template #empty>No payments found.</template>

                    <template #cell-payment_number="{ row }">
                        {{ row.payment_number }}
                        <StatusBadge v-if="row.status !== 'posted'" :status="row.status" />
                    </template>

                    <template #cell-payment_date="{ row }">
                        <DateTimeText :value="row.payment_date" mode="date" />
                    </template>

                    <template #cell-party="{ row }">
                        {{
                            row.visa_vendor?.name ||
                            row.transport_vendor?.name ||
                            row.hotel_vendor?.name ||
                            row.agent?.name ||
                            '—'
                        }}
                    </template>

                    <template #cell-allocation="{ row }">
                        <div
                            v-if="row.allocations?.length"
                            class="flex max-w-56 flex-wrap gap-x-2 gap-y-1"
                        >
                            <template
                                v-for="allocation in row.allocations"
                                :key="allocation.id"
                            >
                                <!-- Two things land in this column and they
                                     go to different places: money applied to
                                     a group, and money a refund drew back
                                     out. Both consumed the advance, so both
                                     have to be named here. -->
                                <Button
                                    v-if="allocation.visa_group_id"
                                    variant="link"
                                    class="h-auto p-0 text-sm"
                                    @click="
                                        router.get(
                                            `/${company.slug}/umrah/groups/${allocation.visa_group_id}`,
                                        )
                                    "
                                    >{{ allocation.group?.group_number }}</Button
                                >
                                <Button
                                    v-else-if="allocation.refund_id"
                                    variant="link"
                                    class="h-auto p-0 text-sm"
                                    @click="
                                        router.get(
                                            `/${company.slug}/umrah/refunds/${allocation.refund_id}`,
                                        )
                                    "
                                    >{{
                                        allocation.refund?.refund_number ??
                                        'Refund'
                                    }}</Button
                                >
                            </template>
                        </div>
                        <div v-else class="text-sm text-status-attention">
                            Credit held — not applied to a group
                        </div>
                    </template>

                    <template #cell-direction="{ row }">
                        <MetaChip tone="neutral" bare>{{
                            directions[row.direction] || row.direction
                        }}</MetaChip>
                    </template>

                    <template #cell-account_code="{ row }">
                        {{ row.account?.code || '—' }}
                    </template>

                    <template #cell-account_name="{ row }">
                        {{ row.account?.name || 'Default account' }}
                    </template>

                    <template #cell-amount="{ row }">
                        <MoneyText :amount="row.amount" :currency="row.currency" />
                    </template>

                    <template #cell-available="{ row }">
                        <MoneyText :amount="availableAmount(row)" :currency="row.base_currency" />
                    </template>

                    <template #cell-actions="{ row }">
                        <div class="flex justify-end gap-2">
                            <Button variant="ghost" size="sm" @click="router.get(`/${company.slug}/umrah/payments/${row.id}`)">Details</Button>
                            <Button
                                v-if="
                                    canRecordPayments &&
                                    row.status !== 'reversed' &&
                                    availableAmount(row) > 0.01
                                "
                                variant="outline"
                                size="sm"
                                @click="openAllocation(row)"
                                >Allocate</Button
                            >
                        </div>
                    </template>
                </LedgerRegister>
                <RecordPagination
                    :current-page="payments.current_page"
                    :last-page="payments.last_page"
                    :from="payments.from"
                    :to="payments.to"
                    :total="payments.total"
                    :previous-url="payments.prev_page_url"
                    :next-url="payments.next_page_url"
                />
            </CardContent>
        </Card>

        <AllocatePaymentDialog
            v-model:open="allocationOpen"
            :company="company"
            :payment="selectedPayment"
            :allocation-groups="allocationGroups"
        />
    </PageShell>
</template>
