<script setup lang="ts">
import AmountDelta from '@/components/AmountDelta.vue';
import LedgerRegister from '@/components/LedgerRegister.vue';
import MetaChip from '@/components/MetaChip.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Calculator, Save, Undo2 } from 'lucide-vue-next';
import RateBasis from '@/components/RateBasis.vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

type Vendor = {
    id: string;
    name: string;
    is_default?: boolean;
    is_company_owned?: boolean;
    provides_mandatory_transport?: boolean;
    mandatory_transport_vendor_id?: string | null;
};

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    group: any;
    adjustmentReasons: Record<string, string>;
    passengerSummary: { total: number; adults: number; children: number; infants: number; visa: number; transport_only: number };
    services: Array<{ stage: 'group' | 'voucher'; service: string; quantity: number; charge: number }>;
    voucherBreakdown: Array<{
        id: string;
        voucher_number: string;
        status: string;
        passengers: number;
        company_stays: number;
        self_stays: number;
        hotel_sale_amount: number;
        hotel_cost_amount: number;
        accounting_state: string;
        billing_voucher_number?: string | null;
    }>;
    vendors: Vendor[];
    transportVendors: Vendor[];
    canUpdate: boolean;
    canCreateRefund: boolean;
}>();

/**
 * Both sides of a trip's money can go wrong, so both are offered here.
 *
 * The button used to assume the agent, which is only half the story: the
 * suppliers who billed this group are refunded from the same page, and
 * picking one here saves choosing it again on a form that already knows
 * the trip.
 */
const refundParties = computed(() => {
    // The parties this page already names: the trip's agent, and the
    // suppliers who billed it. group.agent_id is not in the payload -- only
    // the nested party objects are -- which is why the old button silently
    // prefilled nothing.
    const parties: Array<{ label: string; party_type: string; party_id: string }> = [];
    const add = (label: string, party_type: string, party?: { id: string; name: string } | null) => {
        if (party?.id) parties.push({ label: `${label} · ${party.name}`, party_type, party_id: party.id });
    };

    add('To the agent', 'agent', props.group.agent);
    add('From the visa vendor', 'visa_vendor', props.group.vendor);
    add('From the transport vendor', 'transport_vendor', props.group.mandatory_transport_vendor);

    return parties;
});

const requestRefund = (party?: { party_type: string; party_id: string }) => {
    const query: Record<string, string> = { visa_group_id: props.group.id };
    const chosen = party ?? refundParties.value[0];
    if (chosen) {
        query.party_type = chosen.party_type;
        query.party_id = chosen.party_id;
    }
    router.get(`/${props.company.slug}/umrah/refunds/create`, query);
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Visa Groups', href: `/${props.company.slug}/umrah/groups` },
    { title: props.group.group_number, href: `/${props.company.slug}/umrah/groups/${props.group.id}` },
    { title: 'Accounting', href: `/${props.company.slug}/umrah/groups/${props.group.id}/accounting` },
];

/*
 * Every figure here is a rate times a count. Visa is charged per head, and
 * a standard bus per billable seat, so the screen can show the sum behind
 * each total and let someone re-do it at a new rate rather than working it
 * out elsewhere and typing the answer.
 *
 * The visa rate is derived by division: a group stores its total, not its
 * rate. That is exact while every passenger pays the same, which is how
 * visas are priced -- one fee whatever the age.
 *
 * Transport keeps whatever the total holds beyond rate times seats, which
 * is the charge of any passenger who bought a seat on their own.
 */
const paxCount = computed(() => Number(props.group.passenger_count || 0));

const busSeats = computed(() =>
    Number(props.group.standard_bus_billable_passenger_count || 0),
);

const isStandardBus = computed(() => props.group.transport_mode === 'standard_bus');

const transportExtra = computed(() =>
    Math.round(
        (Number(props.group.transport_amount || 0) -
            Number(props.group.standard_bus_retail_amount || 0) * busSeats.value) *
            100,
    ) / 100,
);

const transportCostExtra = computed(() =>
    Math.round(
        (Number(props.group.transport_cost_amount || 0) -
            Number(props.group.standard_bus_cost_amount || 0) * busSeats.value) *
            100,
    ) / 100,
);

/*
 * The reasons an adjustment actually has, and the difference between the
 * first two is the one that matters later: correcting a mistake says the
 * original figure was never right, while a renegotiation says it was right
 * when it was made and the terms changed afterwards. An accountant closing
 * a period needs to tell those apart, and free text does not let them.
 *
 * Other stays, because a list that cannot say everything must not force
 * someone to pick the nearest wrong thing.
 */
const reasonChoice = ref('');
const reasonTyped = ref('');

const isOtherReason = computed(() => reasonChoice.value === 'other');

watch([reasonChoice, reasonTyped], () => {
    form.reason_category = reasonChoice.value;
    form.reason = isOtherReason.value
        ? reasonTyped.value
        : (props.adjustmentReasons[reasonChoice.value] ?? '');
});

const form = useForm({
    vendor_id: props.group.vendor_id || 'none',
    mandatory_transport_vendor_id: props.group.mandatory_transport_vendor_id || 'none',
    visa_sale_amount: String(props.group.visa_sale_amount ?? 0),
    transport_amount: String(props.group.transport_amount ?? 0),
    discount_amount: String(props.group.discount_amount ?? 0),
    visa_cost_amount: String(props.group.visa_cost_amount ?? 0),
    transport_cost_amount: String(props.group.transport_cost_amount ?? 0),
    reason: '',
    reason_category: '',
});

watch(
    () => form.vendor_id,
    (vendorId, previous) => {
        if (!previous) return;
        const vendor = props.vendors.find((item) => item.id === vendorId);
        if (!vendor) return;
        form.mandatory_transport_vendor_id = vendor.provides_mandatory_transport
            ? vendor.id
            : vendor.mandatory_transport_vendor_id || 'none';
    },
);

const grossCharges = computed(() =>
    Number(form.visa_sale_amount || 0) + Number(form.transport_amount || 0) + Number(props.group.hotel_amount || 0),
);
const receivable = computed(() => Math.max(grossCharges.value - Number(form.discount_amount || 0), 0));
/*
 * The costs on this page are editable, so the summary has to read the
 * form like the charges do. Reading the saved group meant lowering a
 * supplier's price moved nothing on the right-hand side, and the profit
 * shown was this month's charge against last month's cost.
 */
const visaCost = computed(() => Number(form.visa_cost_amount || 0));
const transportCost = computed(() => Number(form.transport_cost_amount || 0));
const totalCost = computed(() => visaCost.value + transportCost.value + Number(props.group.hotel_cost_amount || 0));

/** The same figures as saved, for showing what a change is changing. */
const savedGross = computed(() =>
    Number(props.group.visa_sale_amount || 0) + Number(props.group.transport_amount || 0) + Number(props.group.hotel_amount || 0),
);
const savedTotalCost = computed(() =>
    Number(props.group.visa_cost_amount || 0) + Number(props.group.transport_cost_amount || 0) + Number(props.group.hotel_cost_amount || 0),
);
const savedReceivable = computed(() => Math.max(savedGross.value - Number(props.group.discount_amount || 0), 0));
const savedProfit = computed(() => savedReceivable.value - savedTotalCost.value);
const savedBalance = computed(() => Math.max(savedReceivable.value - Number(props.group.total_paid || 0), 0));
const profit = computed(() => receivable.value - totalCost.value);
const balance = computed(() => Math.max(receivable.value - Number(props.group.total_paid || 0), 0));
const paymentStatus = computed(() => {
    if (Number(props.group.total_paid || 0) <= 0) return 'unpaid';
    return balance.value <= 0 ? 'paid' : 'partially_paid';
});

/**
 * The stage a service was added at (group vs voucher) is a category, not a
 * state, so it is a plain annotation chip rather than a badge borrowed from
 * the status vocabulary. Quantity is a count, so it reads as a figure like
 * any other amount column.
 */
const servicesColumns = [
    { key: 'stage', label: 'Stage', kind: 'text' as const },
    { key: 'service', label: 'Service', kind: 'text' as const },
    { key: 'quantity', label: 'Quantity', kind: 'amount' as const },
    { key: 'charge', label: 'Charge', kind: 'amount' as const },
];

/**
 * `accounting_state` is one of the seven posting states, all of which
 * already live in the shared vocabulary -- no page-local label map needed.
 * Pax and company-stay counts are figures, and the voucher number is this
 * row's reference.
 */
const voucherColumns = [
    { key: 'voucher_number', label: 'Voucher', kind: 'ref' as const },
    { key: 'passengers', label: 'Pax', kind: 'amount' as const },
    { key: 'company_stays', label: 'Company stays', kind: 'amount' as const },
    { key: 'accounting_state', label: 'Status', kind: 'status' as const },
    { key: 'hotel_sale_amount', label: 'Hotel charge', kind: 'amount' as const },
    { key: 'actions', label: '', kind: 'text' as const, class: 'text-right', headerClass: 'text-right' },
];

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            mandatory_transport_vendor_id:
                props.group.transport_mode === 'standard_bus' && data.mandatory_transport_vendor_id !== 'none'
                    ? data.mandatory_transport_vendor_id
                    : null,
            visa_sale_amount: Number(data.visa_sale_amount || 0),
            transport_amount: Number(data.transport_amount || 0),
            discount_amount: Number(data.discount_amount || 0),
            reason_category: data.reason_category || null,
            visa_cost_amount: Number(data.visa_cost_amount || 0),
            transport_cost_amount: Number(data.transport_cost_amount || 0),
        }))
        .put(`/${props.company.slug}/umrah/groups/${props.group.id}/accounting`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reason = '';
                reasonChoice.value = '';
                form.reason_category = '';
                reasonTyped.value = '';
            },
            onError: () => toast.error('Group accounting could not be updated'),
        });
};
</script>

<template>
    <Head :title="`${group.group_number} Accounting`" />
    <PageShell
        title="Group Accounting"
        :description="`${group.group_number} · ${group.agent?.name || 'No agent'}`"
        :breadcrumbs="breadcrumbs"
        :icon="Calculator"
    >
        <template #actions>
            <Button variant="outline" @click="router.get(`/${company.slug}/umrah/groups/${group.id}`)">
                <ArrowLeft class="mr-2 h-4 w-4" />Operational View
            </Button>
            <DropdownMenu v-if="canCreateRefund && refundParties.length > 1">
                <DropdownMenuTrigger as-child>
                    <Button variant="outline"><Undo2 class="mr-2 h-4 w-4" />Request a refund</Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem
                        v-for="party in refundParties"
                        :key="`${party.party_type}:${party.party_id}`"
                        @select="requestRefund(party)"
                    >
                        {{ party.label }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <Button v-else-if="canCreateRefund" variant="outline" @click="requestRefund()">
                <Undo2 class="mr-2 h-4 w-4" />Request a refund
            </Button>
        </template>

        <div class="grid grid-cols-2 gap-px overflow-hidden rounded-md border bg-border sm:grid-cols-3 xl:grid-cols-6">
            <div v-for="item in [
                ['Total pax', passengerSummary.total],
                ['Adults', passengerSummary.adults],
                ['Children', passengerSummary.children],
                ['Infants', passengerSummary.infants],
                ['Visa', passengerSummary.visa],
                ['Transport only', passengerSummary.transport_only],
            ]" :key="String(item[0])" class="bg-background p-4">
                <div class="text-sm text-muted-foreground">{{ item[0] }}</div>
                <div class="mt-1 text-2xl font-semibold">{{ item[1] }}</div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="space-y-6">
                <Card variant="register">
                    <CardHeader><CardTitle>Used Services</CardTitle></CardHeader>
                    <CardContent>
                        <LedgerRegister
                            :data="services"
                            :columns="servicesColumns"
                            :key-field="(row: any, i: number) => `${row.service}-${row.quantity}-${i}`"
                        >
                            <template #empty>No services recorded.</template>

                            <template #cell-stage="{ row }">
                                <MetaChip tone="neutral" bare>{{ row.stage === 'group' ? 'Group' : 'Voucher' }}</MetaChip>
                            </template>

                            <template #cell-charge="{ row }">
                                <MoneyText :amount="row.charge" :currency="company.base_currency" />
                            </template>
                        </LedgerRegister>
                    </CardContent>
                </Card>

                <Card variant="register">
                    <CardHeader><CardTitle>Voucher Accounting</CardTitle></CardHeader>
                    <CardContent>
                        <LedgerRegister :data="voucherBreakdown" :columns="voucherColumns">
                            <template #empty>No vouchers created yet. Group visa and transport accounting remains posted.</template>

                            <template #cell-accounting_state="{ row }">
                                <StatusBadge :status="row.accounting_state" />
                            </template>

                            <template #cell-hotel_sale_amount="{ row }">
                                <MoneyText :amount="row.hotel_sale_amount" :currency="company.base_currency" />
                            </template>

                            <template #cell-actions="{ row }">
                                <div class="flex justify-end gap-2">
                                    <Button size="icon" variant="ghost" title="Open voucher accounting" @click="router.get(`/${company.slug}/umrah/vouchers/${row.id}/accounting`)"><Calculator class="h-4 w-4" /></Button>
                                </div>
                            </template>
                        </LedgerRegister>
                    </CardContent>
                </Card>

                <form novalidate id="group-accounting-form" class="space-y-6" @submit.prevent="submit">
                    <Card variant="form">
                        <CardHeader><CardTitle>Supplier Assignment</CardTitle></CardHeader>
                        <CardContent class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label>Visa vendor</Label>
                                <Select v-model="form.vendor_id" :disabled="!canUpdate">
                                    <SelectTrigger><SelectValue placeholder="Select visa vendor" /></SelectTrigger>
                                    <SelectContent><SelectItem v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.name }}{{ vendor.is_default ? ' · Default' : '' }}</SelectItem></SelectContent>
                                </Select>
                                <p v-if="form.errors.vendor_id" class="text-xs text-destructive">{{ form.errors.vendor_id }}</p>
                            </div>
                            <div v-if="group.transport_mode === 'standard_bus'" class="space-y-2">
                                <Label>Mandatory transport vendor</Label>
                                <Select v-model="form.mandatory_transport_vendor_id" :disabled="!canUpdate">
                                    <SelectTrigger><SelectValue placeholder="Select transport vendor" /></SelectTrigger>
                                    <SelectContent><SelectItem v-for="vendor in transportVendors" :key="vendor.id" :value="vendor.id">{{ vendor.name }}{{ vendor.is_company_owned ? ' · Company-owned' : '' }}</SelectItem></SelectContent>
                                </Select>
                                <p v-if="form.errors.mandatory_transport_vendor_id" class="text-xs text-destructive">{{ form.errors.mandatory_transport_vendor_id }}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="form">
                        <CardHeader><CardTitle>Charges</CardTitle></CardHeader>
                        <CardContent class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label>Visa charge</Label>
                                <Input v-model="form.visa_sale_amount" type="number" min="0" step="0.01" :disabled="!canUpdate" />
                                <p v-if="Number(form.visa_sale_amount || 0) !== Number(group.visa_sale_amount || 0)" class="text-xs">
                                    <AmountDelta :before="group.visa_sale_amount" :after="Number(form.visa_sale_amount || 0)" :currency="company.base_currency" align="start" />
                                </p>
                                <RateBasis
                                    :count="paxCount"
                                    :total="Number(form.visa_sale_amount || 0)"
                                    :currency="company.base_currency"
                                    derived
                                    :disabled="!canUpdate"
                                    @apply="(total) => (form.visa_sale_amount = String(total))"
                                />
                                <p v-if="form.errors.visa_sale_amount" class="text-xs text-destructive">{{ form.errors.visa_sale_amount }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label>Transport charge</Label>
                                <Input v-model="form.transport_amount" type="number" min="0" step="0.01" :disabled="!canUpdate" />
                                <p v-if="Number(form.transport_amount || 0) !== Number(group.transport_amount || 0)" class="text-xs">
                                    <AmountDelta :before="group.transport_amount" :after="Number(form.transport_amount || 0)" :currency="company.base_currency" align="start" />
                                </p>
                                <RateBasis
                                    v-if="isStandardBus"
                                    :count="busSeats"
                                    :total="Number(form.transport_amount || 0)"
                                    :extra="transportExtra"
                                    :currency="company.base_currency"
                                    :disabled="!canUpdate"
                                    @apply="(total) => (form.transport_amount = String(total))"
                                />
                                <p v-if="form.errors.transport_amount" class="text-xs text-destructive">{{ form.errors.transport_amount }}</p>
                            </div>
                            <div class="space-y-2"><Label>Hotel charge</Label><Input :model-value="String(group.hotel_amount || 0)" type="number" disabled /><p class="text-xs text-muted-foreground">Controlled by approved hotel vouchers.</p></div>
                            <div class="space-y-2">
                                <Label>Discount</Label>
                                <Input v-model="form.discount_amount" type="number" min="0" step="0.01" :disabled="!canUpdate" />
                                <p v-if="form.errors.discount_amount" class="text-xs text-destructive">{{ form.errors.discount_amount }}</p>
                                <p v-if="Number(form.discount_amount || 0) !== Number(group.discount_amount || 0)" class="text-xs">
                                    <AmountDelta :before="group.discount_amount" :after="Number(form.discount_amount || 0)" :currency="company.base_currency" align="start" />
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>Supplier costs</CardTitle></CardHeader>
                        <CardContent class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label>Visa cost</Label>
                                <Input v-model="form.visa_cost_amount" type="number" min="0" step="0.01" :disabled="!canUpdate" />
                                <p v-if="Number(form.visa_cost_amount || 0) !== Number(group.visa_cost_amount || 0)" class="text-xs">
                                    <AmountDelta :before="group.visa_cost_amount" :after="Number(form.visa_cost_amount || 0)" :currency="company.base_currency" align="start" />
                                </p>
                                <RateBasis
                                    :count="paxCount"
                                    :total="Number(form.visa_cost_amount || 0)"
                                    :currency="company.base_currency"
                                    derived
                                    :disabled="!canUpdate"
                                    @apply="(total) => (form.visa_cost_amount = String(total))"
                                />
                                <p v-if="form.errors.visa_cost_amount" class="text-xs text-destructive">{{ form.errors.visa_cost_amount }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label>Transport cost</Label>
                                <Input v-model="form.transport_cost_amount" type="number" min="0" step="0.01" :disabled="!canUpdate" />
                                <p v-if="Number(form.transport_cost_amount || 0) !== Number(group.transport_cost_amount || 0)" class="text-xs">
                                    <AmountDelta :before="group.transport_cost_amount" :after="Number(form.transport_cost_amount || 0)" :currency="company.base_currency" align="start" />
                                </p>
                                <RateBasis
                                    v-if="isStandardBus"
                                    :count="busSeats"
                                    :total="Number(form.transport_cost_amount || 0)"
                                    :extra="transportCostExtra"
                                    :currency="company.base_currency"
                                    :disabled="!canUpdate"
                                    @apply="(total) => (form.transport_cost_amount = String(total))"
                                />
                                <p v-if="form.errors.transport_cost_amount" class="text-xs text-destructive">{{ form.errors.transport_cost_amount }}</p>
                            </div>
                            <p class="text-xs text-muted-foreground sm:col-span-2">
                                What the suppliers charge you, for when one lowers their
                                price after the group was built. The difference is posted
                                against their payable; the agent's charge is not affected.
                            </p>
                            <div v-if="canUpdate" class="space-y-2 sm:col-span-2">
                                <Label>Adjustment reason</Label>
                                <Select v-model="reasonChoice">
                                    <SelectTrigger><SelectValue placeholder="Why is this changing?" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="(label, value) in adjustmentReasons"
                                            :key="value"
                                            :value="value"
                                        >
                                            {{ value === 'other' ? 'Other — write it below' : label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Textarea
                                    v-if="isOtherReason"
                                    v-model="reasonTyped"
                                    placeholder="Say what changed and why"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Kept with the adjustment and shown on the agent's statement.
                                </p>
                                <p v-if="form.errors.reason" class="text-xs text-destructive">{{ form.errors.reason }}</p>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>

            <div class="space-y-6">
                <Card variant="detail">
                    <CardHeader><CardTitle>Financial Position</CardTitle></CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-2"><span>Gross charges</span><AmountDelta :before="savedGross" :after="grossCharges" :currency="company.base_currency" /></div>
                        <div class="flex items-center justify-between gap-2"><span>Discount</span><AmountDelta :before="group.discount_amount" :after="Number(form.discount_amount || 0)" :currency="company.base_currency" /></div>
                        <div class="flex items-center justify-between gap-2 border-t pt-3 font-medium"><span>Total receivable</span><AmountDelta :before="savedReceivable" :after="receivable" :currency="company.base_currency" /></div>
                        <div class="flex justify-between"><span>Received</span><MoneyText :amount="group.total_paid" :currency="company.base_currency" /></div>
                        <div class="flex items-center justify-between gap-2 border-t pt-3 text-base font-semibold"><span>Balance</span><AmountDelta :before="savedBalance" :after="balance" :currency="company.base_currency" /></div>
                        <StatusBadge :status="paymentStatus" />
                    </CardContent>
                </Card>

                <Card variant="detail">
                    <CardHeader><CardTitle>Cost & Margin</CardTitle></CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-2"><span>Visa cost</span><AmountDelta :before="group.visa_cost_amount" :after="visaCost" :currency="company.base_currency" /></div>
                        <div class="flex items-center justify-between gap-2"><span>Mandatory transport</span><AmountDelta :before="group.mandatory_transport_cost_amount" :after="isStandardBus ? transportCost : Number(group.mandatory_transport_cost_amount || 0)" :currency="company.base_currency" /></div>
                        <div class="flex items-center justify-between gap-2"><span>Total transport cost</span><AmountDelta :before="group.transport_cost_amount" :after="transportCost" :currency="company.base_currency" /></div>
                        <div class="flex justify-between"><span>Hotel cost</span><MoneyText :amount="group.hotel_cost_amount" :currency="company.base_currency" /></div>
                        <div class="flex items-center justify-between gap-2 border-t pt-3"><span>Total cost</span><AmountDelta :before="savedTotalCost" :after="totalCost" :currency="company.base_currency" /></div>
                        <div class="flex items-center justify-between gap-2 border-t pt-3 text-base font-semibold"><span>Profit</span><AmountDelta :before="savedProfit" :after="profit" :currency="company.base_currency" /></div>
                    </CardContent>
                </Card>

                <Button v-if="canUpdate" type="submit" form="group-accounting-form" class="w-full" :disabled="form.processing || form.reason.trim().length < 5">
                    <Save class="mr-2 h-4 w-4" />Save Accounting
                </Button>
            </div>
        </div>
    </PageShell>
</template>
