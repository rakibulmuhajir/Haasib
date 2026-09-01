<script setup lang="ts">
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Undo2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    partyTypes: Record<string, string>;
    services: Record<string, string>;
    servicesByParty: Record<string, Record<string, string>>;
    reasonsByParty: Record<string, Record<string, string>>;
    agents: Array<{ id: string; name: string }>;
    visaVendors: Array<{ id: string; name: string }>;
    transportVendors: Array<{ id: string; name: string }>;
    hotelVendors: Array<{ id: string; name: string }>;
    refundGroups: Array<{
        id: string;
        agent_id: string;
        group_number: string;
        name: string;
        vendor_ids: string[];
        has_visa: boolean;
        has_transport: boolean;
        has_hotel: boolean;
        charged: {
            sale: Record<string, number>;
            cost: Record<string, number>;
        };
        passenger_count: number;
        per_passenger: { sale: number; cost: number; count: number } | null;
    }>;
    currencies: Array<{
        currency_code: string;
        exchange_rate: string | number;
    }>;
    initial?: {
        party_type?: string;
        party_id?: string;
        visa_group_id?: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Refunds', href: `/${props.company.slug}/umrah/refunds` },
    { title: 'Request Refund' },
];

const firstPartyType = Object.keys(props.partyTypes)[0] || 'agent';

const form = useForm({
    party_type: props.initial?.party_type || firstPartyType,
    party_id: props.initial?.party_id || 'none',
    visa_group_id: props.initial?.visa_group_id || 'none',
    service: Object.keys(props.services)[0] || 'other',
    reason_category: '',
    amount: '',
    currency: props.company.base_currency,
    exchange_rate: '',
    reason: '',
});

const partyOptions = computed(() => {
    switch (form.party_type) {
        case 'visa_vendor':
            return props.visaVendors;
        case 'transport_vendor':
            return props.transportVendors;
        case 'hotel_vendor':
            return props.hotelVendors;
        default:
            return props.agents;
    }
});

const isAgentRefund = computed(() => form.party_type === 'agent');

/*
 * The agent's groups, narrowed to the ones that bought the service being
 * refunded. This used to read the payment allocation options, which carry
 * a group only while it still owes money -- so the group you were
 * refunding, which the agent had usually just paid, was the one missing.
 *
 * A ticket is not attached to a group at all, so that service offers
 * none; the note under the field says so rather than leaving an empty box.
 */
/*
 * Choose the group first; the services narrow to it, not the other way
 * round. Filtering both against each other left whichever was picked
 * second unable to change the first.
 *
 * An agent sees the trips they sold. A supplier sees the trips they
 * billed, because a credit from them lowers what one of those trips cost.
 */
const partyGroups = computed(() => {
    if (form.party_id === 'none') return [];

    return isAgentRefund.value
        ? props.refundGroups.filter((group) => group.agent_id === form.party_id)
        : props.refundGroups.filter((group) =>
              group.vendor_ids.includes(form.party_id),
          );
});

/*
 * What the group was charged for the service being refunded. It comes off
 * the group's own stored figures, so it is the sum the refund is being
 * taken out of rather than a number someone has to remember.
 */
const chargedForService = computed(() => {
    const group = selectedGroup.value;
    if (!group) return null;

    // The two sides look at different money and always did: an agent is
    // refunded out of what they were charged, a supplier credits us out of
    // what they charged us. Reading one figure for both is how the vendor
    // side ended up showing nothing at all.
    const side = isAgentRefund.value ? group.charged.sale : group.charged.cost;

    return side[form.service] ?? null;
});

/*
 * A standard bus is the only transport priced per head, so it is the only
 * one that can offer "refund this many passengers". A specialized group is
 * priced per vehicle or per journey and has no per-person rate to work
 * back from.
 */
const perPassenger = computed(() => {
    if (form.service !== 'transport') return null;

    const rates = selectedGroup.value?.per_passenger;
    if (!rates) return null;

    return {
        rate: isAgentRefund.value ? rates.sale : rates.cost,
        count: rates.count,
    };
});

/*
 * Why the money went back, in a form something can count. The sentence
 * still says what happened; this says what kind of thing it was, so the
 * same question can be asked of a thousand refunds at once.
 *
 * Choosing one writes it into the reason as a starting sentence, which
 * stays editable -- the category is the shape of the answer, not the
 * answer.
 */
const partyReasons = computed(
    () => props.reasonsByParty[form.party_type] ?? {},
);

watch(
    () => form.reason_category,
    (category) => {
        const label = partyReasons.value[category];
        if (label && category !== 'other' && !form.reason.trim()) {
            form.reason = label;
        }
    },
);

watch(
    () => form.party_type,
    () => {
        if (!(form.reason_category in partyReasons.value)) {
            form.reason_category = '';
        }
    },
);

const serviceLabel = computed(() =>
    (availableServices.value[form.service] ?? form.service).toLowerCase(),
);

const refundPassengers = ref('');
const refundPercent = ref('');

const setAmount = (value: number) => {
    if (value > 0) form.amount = String(Math.round(value * 100) / 100);
};

const passengerRefundAmount = computed(() => {
    const rate = perPassenger.value?.rate ?? 0;
    const count = Number(refundPassengers.value || 0);

    return Number.isFinite(count) && count > 0
        ? Math.round(rate * count * 100) / 100
        : 0;
});

const percentRefundAmount = computed(() => {
    const charged = chargedForService.value ?? 0;
    const percent = Number(refundPercent.value || 0);

    return Number.isFinite(percent) && percent > 0 && charged > 0
        ? Math.round(charged * (percent / 100) * 100) / 100
        : 0;
});

const overCharged = computed(
    () =>
        chargedForService.value !== null &&
        Number(form.amount || 0) > chargedForService.value + 0.001,
);

const groupHint = computed(() => {
    if (!partyGroups.value.length) {
        return isAgentRefund.value
            ? 'This agent has no groups yet.'
            : 'This supplier has not billed any group yet.';
    }
    if (!form.visa_group_id || form.visa_group_id === 'none') {
        return isAgentRefund.value
            ? 'Choosing a group narrows the services below to what it bought, and takes the refund off that group\u2019s payments.'
            : 'Naming the group lowers what that trip cost by this amount. Leave it unset for money that is not about one trip\u2019s price \u2014 damages, or a rebate across a season.';
    }
    if (!isAgentRefund.value) {
        return 'This trip\u2019s cost falls by the credit. The money itself is settled separately.';
    }

    return 'The refund comes off this group\u2019s payments, so its balance goes back up.';
});

const selectedCurrency = computed(() =>
    props.currencies.find(
        (currency) => currency.currency_code === form.currency,
    ),
);
const baseAmount = computed(
    () =>
        Math.round(
            Math.max(Number(form.amount || 0), 0) *
                Number(form.exchange_rate || 1) *
                100,
        ) / 100,
);

/*
 * What this party can be refunding, narrowed again by what the chosen
 * group actually bought.
 *
 * A visa desk only ever sold visas, so offering it hotel and transport is
 * three wrong answers and one right one. An agent's group that bought no
 * hotel has no hotel to give back. Without a group there is nothing to
 * narrow by, so the party's own list stands.
 */
const selectedGroup = computed(
    () =>
        props.refundGroups.find((group) => group.id === form.visa_group_id) ??
        null,
);

const partyServices = computed(
    () => props.servicesByParty[form.party_type] ?? props.services,
);

const availableServices = computed(() => {
    const group = selectedGroup.value;
    if (!group) return partyServices.value;

    const bought: Record<string, boolean> = {
        visa: group.has_visa,
        transport: group.has_transport,
        hotel: group.has_hotel,
    };

    const narrowed = Object.fromEntries(
        Object.entries(partyServices.value).filter(([key]) => bought[key]),
    );

    // A group whose services are all unrefundable leaves the party's list
    // rather than an empty box.
    return Object.keys(narrowed).length ? narrowed : partyServices.value;
});

watch(
    () => form.party_type,
    () => {
        form.party_id = 'none';
        form.visa_group_id = 'none';
        // Switching to an agent while 'visa' is selected would submit a
        // service the agent list does not contain.
        if (!(form.service in availableServices.value)) {
            form.service = Object.keys(availableServices.value)[0];
        }
    },
);
watch(
    () => form.party_id,
    () => {
        form.visa_group_id = 'none';
    },
);
watch(
    () => form.visa_group_id,
    () => {
        // The group decides which services are on offer, so one chosen
        // before it must still be among them.
        if (!(form.service in availableServices.value)) {
            form.service = Object.keys(availableServices.value)[0];
        }
    },
);
watch(
    () => form.currency,
    (currency) => {
        form.exchange_rate =
            currency === props.company.base_currency
                ? ''
                : String(selectedCurrency.value?.exchange_rate || '');
    },
);

const submit = () =>
    form
        .transform((data) => ({
            party_type: data.party_type,
            party_id: data.party_id === 'none' ? null : data.party_id,
            visa_group_id:
                data.visa_group_id === 'none' ? null : data.visa_group_id,
            service: data.service,
            amount: Number(data.amount || 0),
            currency: data.currency,
            exchange_rate:
                data.currency === props.company.base_currency
                    ? null
                    : Number(data.exchange_rate || 0),
            reason: data.reason,
        }))
        .post(`/${props.company.slug}/umrah/refunds`, {
            onError: () => toast.error('Failed to request refund'),
        });
</script>

<template>
    <Head title="Request Refund" />
    <PageShell
        title="Request Refund"
        description="Record what is owed back. Nothing moves and nothing posts until this is accepted and settled."
        :breadcrumbs="breadcrumbs"
        :icon="Undo2"
    >
        <template #actions>
            <Button
                variant="outline"
                @click="router.get(`/${company.slug}/umrah/refunds`)"
            >
                <ArrowLeft class="mr-2 h-4 w-4" />Back
            </Button>
        </template>

        <form novalidate class="mx-auto max-w-xl" @submit.prevent="submit">
            <Card variant="form">
                <CardHeader><CardTitle>Refund</CardTitle></CardHeader>
                <CardContent class="space-y-5">
                    <div
                        v-if="Object.keys(partyTypes).length > 1"
                        class="space-y-2"
                    >
                        <Label>Owed to</Label>
                        <Select v-model="form.party_type">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(label, value) in partyTypes"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="form.errors.party_type"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.party_type }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label>Party</Label>
                        <Select v-model="form.party_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Select party" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none"
                                    >Select party</SelectItem
                                >
                                <SelectItem
                                    v-for="party in partyOptions"
                                    :key="party.id"
                                    :value="party.id"
                                >
                                    {{ party.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="form.errors.party_id"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.party_id }}
                        </p>
                    </div>

                    <div v-if="form.party_id !== 'none'" class="space-y-2">
                        <Label>Group (optional)</Label>
                        <Select v-model="form.visa_group_id">
                            <SelectTrigger>
                                <SelectValue
                                    placeholder="Not tied to a group"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none"
                                    >Not tied to a group</SelectItem
                                >
                                <SelectItem
                                    v-for="group in partyGroups"
                                    :key="group.id"
                                    :value="group.id"
                                >
                                    {{ group.group_number }} · {{ group.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-muted-foreground">
                            {{ groupHint }}
                        </p>
                        <p
                            v-if="form.errors.visa_group_id"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.visa_group_id }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label>Service</Label>
                        <Select v-model="form.service">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(label, value) in availableServices"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="form.errors.service"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.service }}
                        </p>
                    </div>

                    <div
                        v-if="chargedForService !== null"
                        class="space-y-3 rounded-md border bg-muted/40 p-3 text-sm"
                    >
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">
                                {{
                                    isAgentRefund
                                        ? 'Charged to this agent'
                                        : 'Charged by this supplier'
                                }}
                                for {{ serviceLabel }}
                            </span>
                            <MoneyText
                                :amount="chargedForService"
                                :currency="company.base_currency"
                                class="font-medium"
                            />
                        </div>

                        <div class="flex flex-wrap items-end gap-3">
                            <div class="space-y-1">
                                <Label class="text-xs text-muted-foreground"
                                    >Part of it</Label
                                >
                                <div class="flex items-center gap-1">
                                    <Input
                                        v-model="refundPercent"
                                        type="number"
                                        min="0"
                                        max="100"
                                        class="h-9 w-24"
                                    />
                                    <span class="text-xs text-muted-foreground"
                                        >%</span
                                    >
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="percentRefundAmount <= 0"
                                @click="setAmount(percentRefundAmount)"
                            >
                                Use
                                <MoneyText
                                    :amount="percentRefundAmount"
                                    :currency="company.base_currency"
                                />
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="setAmount(chargedForService ?? 0)"
                            >
                                All of it
                            </Button>
                        </div>

                        <div
                            v-if="perPassenger"
                            class="space-y-1 border-t pt-2"
                        >
                            <p class="text-xs text-muted-foreground">
                                {{ perPassenger.count }} passengers &times;
                                <MoneyText
                                    :amount="perPassenger.rate"
                                    :currency="company.base_currency"
                                />
                                each
                            </p>
                            <div class="flex items-end gap-2">
                                <div class="space-y-1">
                                    <Label class="text-xs text-muted-foreground"
                                        >Or this many passengers</Label
                                    >
                                    <Input
                                        v-model="refundPassengers"
                                        type="number"
                                        min="1"
                                        :max="perPassenger.count"
                                        class="h-9 w-40"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="passengerRefundAmount <= 0"
                                    @click="setAmount(passengerRefundAmount)"
                                >
                                    Use
                                    <MoneyText
                                        :amount="passengerRefundAmount"
                                        :currency="company.base_currency"
                                    />
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-[1fr_9rem]">
                        <div class="space-y-2">
                            <Label>Amount</Label>
                            <Input
                                v-model="form.amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                autofocus
                                required
                            />
                            <p
                                v-if="form.errors.amount"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.amount }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label>Currency</Label>
                            <Select v-model="form.currency">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="currency in currencies"
                                        :key="currency.currency_code"
                                        :value="currency.currency_code"
                                    >
                                        {{ currency.currency_code }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p
                                v-if="form.errors.currency"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.currency }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="form.currency !== company.base_currency"
                        class="space-y-2"
                    >
                        <Label>Conversion rate</Label>
                        <Input
                            v-model="form.exchange_rate"
                            type="number"
                            min="0.00000001"
                            step="0.00000001"
                            required
                        />
                        <p class="text-xs text-muted-foreground">
                            1 {{ form.currency }} =
                            {{ form.exchange_rate || 0 }}
                            {{ company.base_currency }} ·
                            <MoneyText
                                :amount="baseAmount"
                                :currency="company.base_currency"
                            />
                        </p>
                        <p
                            v-if="form.errors.exchange_rate"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.exchange_rate }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label>Reason</Label>
                        <Select v-model="form.reason_category">
                            <SelectTrigger
                                ><SelectValue
                                    placeholder="Why is this going back?"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(label, value) in partyReasons"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Textarea
                            v-model="form.reason"
                            required
                            placeholder="Say what happened"
                        />
                        <p
                            v-if="form.errors.reason"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.reason }}
                        </p>
                    </div>

                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="form.processing"
                    >
                        <Undo2 class="mr-2 h-4 w-4" />
                        {{ form.processing ? 'Requesting…' : 'Request Refund' }}
                    </Button>
                </CardContent>
            </Card>
        </form>
    </PageShell>
</template>
