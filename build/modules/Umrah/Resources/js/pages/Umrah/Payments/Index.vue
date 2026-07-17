<script setup lang="ts">
import DateTimeText from '@/components/DateTimeText.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import RecordPagination from '@/components/RecordPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
        </template>

        <div class="grid gap-4 md:grid-cols-2">
            <Card
                ><CardHeader
                    ><CardTitle class="flex items-center gap-2 text-base"
                        ><ArrowDownLeft
                            class="h-4 w-4 text-emerald-700"
                        />Received</CardTitle
                    ></CardHeader
                ><CardContent class="text-2xl font-semibold"
                    ><MoneyText
                        :amount="summary.received"
                        :currency="company.base_currency" /></CardContent
            ></Card>
            <Card v-if="directions.sent"
                ><CardHeader
                    ><CardTitle class="flex items-center gap-2 text-base"
                        ><ArrowUpRight
                            class="h-4 w-4 text-destructive"
                        />Sent</CardTitle
                    ></CardHeader
                ><CardContent class="text-2xl font-semibold"
                    ><MoneyText
                        :amount="summary.sent"
                        :currency="company.base_currency" /></CardContent
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

        <Card>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Payment</TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead>Party</TableHead>
                            <TableHead>Allocation</TableHead>
                            <TableHead>Direction</TableHead>
                            <TableHead>Account #</TableHead>
                            <TableHead>Account</TableHead>
                            <TableHead class="text-right">Amount</TableHead>
                            <TableHead class="text-right">Available</TableHead>
                            <TableHead class="w-24 text-right"
                                >Action</TableHead
                            >
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty v-if="!payments.data.length" :colspan="10"
                            >No payments found.</TableEmpty
                        >
                        <TableRow
                            v-for="payment in payments.data"
                            :key="payment.id"
                        >
                            <TableCell class="font-medium">{{
                                payment.payment_number
                            }}<Badge v-if="payment.status === 'reversed'" variant="destructive" class="ml-2">Reversed</Badge></TableCell>
                            <TableCell
                                ><DateTimeText
                                    :value="payment.payment_date"
                                    mode="date"
                            /></TableCell>
                            <TableCell>
                                {{
                                    payment.visa_vendor?.name ||
                                    payment.transport_vendor?.name ||
                                    payment.hotel_vendor?.name ||
                                    payment.agent?.name ||
                                    '-'
                                }}
                            </TableCell>
                            <TableCell>
                                <div
                                    v-if="payment.allocations?.length"
                                    class="flex max-w-56 flex-wrap gap-x-2 gap-y-1"
                                >
                                    <Button
                                        v-for="allocation in payment.allocations"
                                        :key="allocation.id"
                                        variant="link"
                                        class="h-auto p-0 text-sm"
                                        @click="
                                            router.get(
                                                `/${company.slug}/umrah/groups/${allocation.visa_group_id}`,
                                            )
                                        "
                                        >{{
                                            allocation.group?.group_number
                                        }}</Button
                                    >
                                </div>
                                <div v-else class="text-sm text-amber-700">
                                    Unallocated advance
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge
                                    :variant="
                                        payment.direction === 'sent'
                                            ? 'outline'
                                            : 'secondary'
                                    "
                                    >{{
                                        directions[payment.direction] ||
                                        payment.direction
                                    }}</Badge
                                >
                            </TableCell>
                            <TableCell>
                                {{ payment.account?.code || '-' }}
                            </TableCell>
                            <TableCell>
                                {{ payment.account?.name || 'Default account' }}
                            </TableCell>
                            <TableCell class="text-right">
                                <div
                                    class="font-semibold"
                                    :class="
                                        payment.direction === 'sent'
                                            ? 'text-destructive'
                                            : 'text-emerald-700'
                                    "
                                >
                                    <MoneyText
                                        :amount="payment.amount"
                                        :currency="payment.currency"
                                    />
                                </div>
                            </TableCell>
                            <TableCell class="text-right font-medium">
                                <MoneyText
                                    :amount="availableAmount(payment)"
                                    :currency="payment.base_currency"
                                />
                            </TableCell>
                            <TableCell class="flex justify-end gap-2 text-right">
                                <Button variant="ghost" size="sm" @click="router.get(`/${company.slug}/umrah/payments/${payment.id}`)">Details</Button>
                                <Button
                                    v-if="
                                        canRecordPayments &&
                                        payment.status !== 'reversed' &&
                                        availableAmount(payment) > 0.01
                                    "
                                    variant="outline"
                                    size="sm"
                                    @click="openAllocation(payment)"
                                    >Allocate</Button
                                >
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
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
