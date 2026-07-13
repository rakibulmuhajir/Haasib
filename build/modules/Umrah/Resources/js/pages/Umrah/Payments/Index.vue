<script setup lang="ts">
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
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
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDownLeft,
    ArrowUpRight,
    Plane,
    Search,
    WalletCards,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    payments: any;
    summary: { received: number; sent: number };
    directions: Record<string, string>;
    filters: { search?: string; direction?: string };
    groups: Array<{
        id: string;
        agent_id: string;
        group_number: string;
        name: string;
        balance: number;
    }>;
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

const openPage = (url?: string | null) => {
    if (url) router.get(url);
};
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
const allocationGroups = computed(() =>
    selectedPayment.value?.direction === 'received'
        ? props.groups.filter(
              (group) => group.agent_id === selectedPayment.value.agent_id,
          )
        : props.groups,
);
const openAllocation = (payment: any) => {
    selectedPayment.value = payment;
    allocationForm.reset();
    allocationForm.visa_group_id = 'none';
    allocationForm.base_amount = String(availableAmount(payment));
    allocationOpen.value = true;
};
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

        <div
            v-if="!payments.data.length"
            class="py-12 text-center text-sm text-muted-foreground"
        >
            No payments found.
        </div>
        <div v-else class="space-y-2">
            <div
                v-for="payment in payments.data"
                :key="payment.id"
                class="grid gap-3 border-b py-4 md:grid-cols-[150px_1fr_180px_180px] md:items-center"
            >
                <div>
                    <div class="font-medium">{{ payment.payment_number }}</div>
                    <div class="text-xs text-muted-foreground">
                        {{ payment.payment_date }}
                    </div>
                </div>
                <div>
                    <div class="font-medium">
                        {{
                            payment.visa_vendor?.name ||
                            payment.hotel_vendor?.name ||
                            payment.agent?.name
                        }}
                    </div>
                    <div
                        v-if="payment.allocations?.length"
                        class="flex flex-wrap gap-2"
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
                            >{{ allocation.group?.group_number }}</Button
                        >
                    </div>
                    <div v-else class="text-sm text-amber-700">
                        Unallocated advance
                    </div>
                    <div class="text-xs text-muted-foreground">
                        {{ payment.reference || 'No reference' }}
                    </div>
                </div>
                <div>
                    <Badge
                        :variant="
                            payment.direction === 'sent'
                                ? 'outline'
                                : 'secondary'
                        "
                        >{{
                            directions[payment.direction] || payment.direction
                        }}</Badge
                    >
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{
                            payment.account
                                ? `${payment.account.code} · ${payment.account.name}`
                                : 'Default account'
                        }}
                    </div>
                </div>
                <div class="text-right">
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
                    <div
                        v-if="payment.currency !== payment.base_currency"
                        class="text-xs text-muted-foreground"
                    >
                        Rate {{ payment.exchange_rate }} ·
                        <MoneyText
                            :amount="payment.base_amount"
                            :currency="payment.base_currency"
                        />
                    </div>
                    <div class="text-xs text-muted-foreground">
                        Available
                        <MoneyText
                            :amount="availableAmount(payment)"
                            :currency="payment.base_currency"
                        />
                    </div>
                    <Button
                        v-if="availableAmount(payment) > 0.01"
                        variant="outline"
                        size="sm"
                        class="mt-2"
                        @click="openAllocation(payment)"
                        >Allocate</Button
                    >
                </div>
            </div>
        </div>

        <div
            v-if="payments.last_page > 1"
            class="flex items-center justify-between"
        >
            <Button
                variant="outline"
                :disabled="!payments.prev_page_url"
                @click="openPage(payments.prev_page_url)"
                >Previous</Button
            >
            <span class="text-sm text-muted-foreground"
                >Page {{ payments.current_page }} of
                {{ payments.last_page }}</span
            >
            <Button
                variant="outline"
                :disabled="!payments.next_page_url"
                @click="openPage(payments.next_page_url)"
                >Next</Button
            >
        </div>

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
                                    v-for="group in allocationGroups"
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
