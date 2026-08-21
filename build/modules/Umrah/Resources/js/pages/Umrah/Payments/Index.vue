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
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDownLeft,
    ArrowUpRight,
    Plane,
    Search,
    WalletCards,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
const allocationForm = useForm({ visa_group_id: 'none', base_amount: '' });
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
const selectedPartyKey = computed(() => {
    const payment = selectedPayment.value;
    if (!payment) return '';
    if (payment.agent_id) return `agent:${payment.agent_id}`;
    if (payment.visa_vendor_id) return `visa:${payment.visa_vendor_id}`;
    if (payment.transport_vendor_id)
        return `transport:${payment.transport_vendor_id}`;
    return payment.hotel_vendor_id ? `hotel:${payment.hotel_vendor_id}` : '';
});
const availableAllocationGroups = computed(() =>
    props.allocationGroups.filter(
        (group) =>
            group.party_key === selectedPartyKey.value &&
            !(selectedPayment.value?.allocations || []).some(
                (allocation: any) => allocation.visa_group_id === group.id,
            ),
    ),
);
const selectedAllocationGroup = computed(() =>
    availableAllocationGroups.value.find(
        (group) => group.id === allocationForm.visa_group_id,
    ),
);
const openAllocation = (payment: any) => {
    selectedPayment.value = payment;
    allocationForm.reset();
    allocationForm.visa_group_id = 'none';
    allocationForm.base_amount = String(availableAmount(payment));
    allocationOpen.value = true;
};
watch(
    () => allocationForm.visa_group_id,
    (groupId) => {
        if (groupId === 'none' || !selectedPayment.value) return;
        const group = availableAllocationGroups.value.find(
            (option) => option.id === groupId,
        );
        if (group) {
            allocationForm.base_amount = String(
                Math.min(
                    availableAmount(selectedPayment.value),
                    Number(group.outstanding_amount),
                ),
            );
        }
    },
);
const submitAllocation = () => {
    if (!selectedPayment.value) return;
    allocationForm
        .transform((data) => ({
            ...data,
            visa_group_id:
                data.visa_group_id === 'none' ? null : data.visa_group_id,
            base_amount: Number(data.base_amount || 0),
        }))
        .post(
            `/${props.company.slug}/umrah/payments/${selectedPayment.value.id}/allocations`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    allocationOpen.value = false;
                },
            },
        );
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
                <LedgerRegister :data="payments.data" :columns="columns">
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
                            <Button
                                v-for="allocation in row.allocations"
                                :key="allocation.id"
                                variant="link"
                                class="h-auto p-0 text-sm"
                                @click="
                                    router.get(
                                        `/${company.slug}/umrah/groups/${allocation.visa_group_id}`,
                                    )
                                "
                                >{{ allocation.group?.group_number }}</Button
                            >
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

        <Dialog v-model:open="allocationOpen">
            <DialogContent>
                <DialogHeader
                    ><DialogTitle>Allocate Payment</DialogTitle></DialogHeader
                >
                <form class="space-y-4" @submit.prevent="submitAllocation">
                    <div class="space-y-2">
                        <Label>Group</Label>
                        <Select v-model="allocationForm.visa_group_id">
                            <SelectTrigger
                                ><SelectValue placeholder="Select group"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none"
                                    >Select group</SelectItem
                                >
                                <SelectItem
                                    v-for="group in availableAllocationGroups"
                                    :key="group.id"
                                    :value="group.id"
                                >
                                    {{ group.group_number }} · {{ group.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="allocationForm.errors.visa_group_id"
                            class="text-xs text-destructive"
                        >
                            {{ allocationForm.errors.visa_group_id }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label>Amount in {{ company.base_currency }}</Label>
                        <Input
                            v-model="allocationForm.base_amount"
                            type="number"
                            min="0.01"
                            :max="selectedAllocationGroup?.outstanding_amount"
                            step="0.01"
                            required
                        />
                        <p class="text-xs text-muted-foreground">
                            Available
                            <MoneyText
                                :amount="
                                    selectedPayment
                                        ? availableAmount(selectedPayment)
                                        : 0
                                "
                                :currency="company.base_currency"
                            />
                            <template v-if="selectedAllocationGroup">
                                · Group outstanding
                                <MoneyText
                                    :amount="
                                        selectedAllocationGroup.outstanding_amount
                                    "
                                    :currency="company.base_currency"
                                />
                            </template>
                        </p>
                        <p
                            v-if="allocationForm.errors.base_amount"
                            class="text-xs text-destructive"
                        >
                            {{ allocationForm.errors.base_amount }}
                        </p>
                    </div>
                    <DialogFooter
                        ><Button
                            type="submit"
                            :disabled="allocationForm.processing"
                            >Allocate</Button
                        ></DialogFooter
                    >
                </form>
            </DialogContent>
        </Dialog>
    </PageShell>
</template>
