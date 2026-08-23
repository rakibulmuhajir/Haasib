<script setup lang="ts">
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import AmountInput from '@/components/forms/AmountInput.vue';
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
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Save, Ticket as TicketIcon, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

type Customer = { id: string; name: string };
type Agent = { id: string; name: string; customer_id: string | null };
type Vendor = { id: string; name: string };
type Currency = { currency_code: string; exchange_rate?: number | string | null };

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    customers: Customer[];
    agents: Agent[];
    vendors: Vendor[];
    currencies: Currency[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Tickets', href: `/${props.company.slug}/umrah/tickets` },
    { title: 'Book Ticket', href: `/${props.company.slug}/umrah/tickets/create` },
];

type TicketRow = {
    row_id: string;
    passenger_name: string;
    airline: string;
    route: string;
    travel_date: string;
    gross_fare: number | null;
    taxes: number | null;
    discount: number | null;
    service_fee: number | null;
    supplier_cost: number | null;
    supplier_currency: string;
    supplier_exchange_rate: number | null;
};

const nextRowId = () =>
    typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID()
        : `row-${Math.random().toString(36).slice(2)}`;

const emptyTicket = (): TicketRow => ({
    row_id: nextRowId(),
    passenger_name: '',
    airline: '',
    route: '',
    travel_date: '',
    gross_fare: null,
    taxes: null,
    discount: null,
    service_fee: null,
    supplier_cost: null,
    supplier_currency: props.company.base_currency,
    supplier_exchange_rate: null,
});

// Generated once, when this page mounts -- a resubmission of the same
// form (double-click, a retry after a slow response) replays this same
// key, and CreateTicketBookingHandler treats a replayed key as "already
// booked" rather than creating a second one. Regenerating it on submit
// would defeat that.
const idempotencyKey = nextRowId();

const form = useForm({
    customer_id: '',
    agent_id: '' as string | '',
    supplier_vendor_id: '',
    booking_date: new Date().toISOString().slice(0, 10),
    pnr: '',
    idempotency_key: idempotencyKey,
    tickets: [emptyTicket()],
});

// The buyer is derived from the agent, not chosen alongside it. That is
// what keeps this form from ever building a booking where
// booking.customer_id and agent.customer_id disagree -- the mismatch
// CreateTicketBookingHandler refuses is simply not constructible here.
const selectedAgent = computed(() =>
    props.agents.find((agent) => agent.id === form.agent_id) ?? null,
);

function onAgentChange(agentId: string) {
    form.agent_id = agentId;
    const agent = props.agents.find((item) => item.id === agentId);
    if (agent?.customer_id) {
        form.customer_id = agent.customer_id;
    }
}

function clearAgent() {
    form.agent_id = '';
}

const addTicket = () => form.tickets.push(emptyTicket());
const removeTicket = (index: number) => {
    if (form.tickets.length > 1) {
        form.tickets.splice(index, 1);
    }
};

const nestedError = (path: string) => form.errors[path as keyof typeof form.errors];

// The buyer's total -- what gets invoiced. The sale leg is always the
// company's base currency (the invoice is only ever raised in base), so
// this sum needs no conversion. Commission is not shown here: it is
// derived at posting time from the supplier cost, and a form-side copy
// of that arithmetic would drift from what the command actually posts.
const buyerTotal = computed(() =>
    form.tickets.reduce((sum, ticket) => {
        const gross = Number(ticket.gross_fare || 0);
        const taxes = Number(ticket.taxes || 0);
        const serviceFee = Number(ticket.service_fee || 0);
        const discount = Number(ticket.discount || 0);
        return sum + gross + taxes + serviceFee - discount;
    }, 0),
);

const submit = () => {
    form.transform((data) => ({
        ...data,
        agent_id: data.agent_id || null,
        pnr: data.pnr || null,
        tickets: data.tickets.map((ticket) => ({
            passenger_name: ticket.passenger_name,
            airline: ticket.airline || null,
            route: ticket.route || null,
            travel_date: ticket.travel_date || null,
            gross_fare: ticket.gross_fare,
            taxes: ticket.taxes,
            discount: ticket.discount,
            service_fee: ticket.service_fee,
            supplier_cost: ticket.supplier_cost,
            // The sale leg never offers a currency other than base --
            // the invoice this posts to cannot honour one, so the form
            // never asks and there is nothing to convert here.
            sale_currency: props.company.base_currency,
            sale_exchange_rate: null,
            supplier_currency: ticket.supplier_currency,
            supplier_exchange_rate:
                ticket.supplier_currency === props.company.base_currency
                    ? null
                    : ticket.supplier_exchange_rate,
        })),
    })).post(`/${props.company.slug}/umrah/tickets`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Book Ticket" />
    <PageShell
        title="Book Ticket"
        description="Sell one or more tickets as a single booking: one buyer invoice, one supplier bill."
        :breadcrumbs="breadcrumbs"
        :icon="TicketIcon"
    >
        <template #actions>
            <Button
                variant="outline"
                @click="router.get(`/${company.slug}/umrah/tickets`)"
            >
                Back
            </Button>
        </template>

        <form novalidate class="max-w-5xl space-y-6" @submit.prevent="submit">
            <Card variant="form">
                <CardHeader><CardTitle>Booking details</CardTitle></CardHeader>
                <CardContent class="grid gap-5 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Sold via agent (optional)</Label>
                        <Select
                            :model-value="form.agent_id"
                            @update:model-value="(v) => onAgentChange(String(v ?? ''))"
                        >
                            <SelectTrigger
                                :class="{ 'border-destructive': form.errors.agent_id }"
                            >
                                <SelectValue placeholder="Walk-in customer" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="agent in agents"
                                    :key="agent.id"
                                    :value="agent.id"
                                >
                                    {{ agent.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <button
                            v-if="form.agent_id"
                            type="button"
                            class="text-xs text-muted-foreground underline"
                            @click="clearAgent"
                        >
                            Clear agent, bill a walk-in customer instead
                        </button>
                        <p v-if="form.errors.agent_id" class="text-sm text-destructive">
                            {{ form.errors.agent_id }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label>Buyer</Label>
                        <Select
                            :model-value="form.customer_id"
                            :disabled="Boolean(selectedAgent)"
                            @update:model-value="(v) => (form.customer_id = String(v ?? ''))"
                        >
                            <SelectTrigger
                                :class="{ 'border-destructive': form.errors.customer_id }"
                            >
                                <SelectValue placeholder="Select buyer" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="customer in customers"
                                    :key="customer.id"
                                    :value="customer.id"
                                >
                                    {{ customer.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="selectedAgent"
                            class="text-xs text-muted-foreground"
                        >
                            Billed to this agent's linked account.
                        </p>
                        <p v-if="form.errors.customer_id" class="text-sm text-destructive">
                            {{ form.errors.customer_id }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label>Supplier</Label>
                        <Select
                            :model-value="form.supplier_vendor_id"
                            @update:model-value="(v) => (form.supplier_vendor_id = String(v ?? ''))"
                        >
                            <SelectTrigger
                                :class="{ 'border-destructive': form.errors.supplier_vendor_id }"
                            >
                                <SelectValue placeholder="Select supplier" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="vendor in vendors"
                                    :key="vendor.id"
                                    :value="vendor.id"
                                >
                                    {{ vendor.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.supplier_vendor_id" class="text-sm text-destructive">
                            {{ form.errors.supplier_vendor_id }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="booking-date">Booking date</Label>
                        <Input
                            id="booking-date"
                            v-model="form.booking_date"
                            type="date"
                            :aria-invalid="Boolean(form.errors.booking_date)"
                        />
                        <p v-if="form.errors.booking_date" class="text-sm text-destructive">
                            {{ form.errors.booking_date }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="pnr">PNR</Label>
                        <Input
                            id="pnr"
                            v-model="form.pnr"
                            placeholder="Booking reference (optional)"
                            :aria-invalid="Boolean(form.errors.pnr)"
                        />
                        <p v-if="form.errors.pnr" class="text-sm text-destructive">
                            {{ form.errors.pnr }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card variant="form">
                <CardHeader><CardTitle>Tickets</CardTitle></CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-for="(ticket, index) in form.tickets"
                        :key="ticket.row_id"
                        class="space-y-4 rounded-md border p-4"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-muted-foreground">
                                Ticket {{ index + 1 }}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :disabled="form.tickets.length <= 1"
                                aria-label="Remove ticket"
                                title="Remove ticket"
                                @click="removeTicket(index)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="space-y-1">
                                <Label>Passenger name</Label>
                                <Input v-model="ticket.passenger_name" />
                                <p
                                    v-if="nestedError(`tickets.${index}.passenger_name`)"
                                    class="text-xs text-destructive"
                                >
                                    {{ nestedError(`tickets.${index}.passenger_name`) }}
                                </p>
                            </div>
                            <div class="space-y-1">
                                <Label>Airline</Label>
                                <Input v-model="ticket.airline" placeholder="Optional" />
                            </div>
                            <div class="space-y-1">
                                <Label>Route</Label>
                                <Input v-model="ticket.route" placeholder="e.g. KHI-JED" />
                            </div>
                            <div class="space-y-1">
                                <Label>Travel date</Label>
                                <Input v-model="ticket.travel_date" type="date" />
                            </div>
                            <div class="space-y-1">
                                <Label>Gross fare</Label>
                                <AmountInput
                                    v-model="ticket.gross_fare"
                                    :currency="company.base_currency"
                                    :error="nestedError(`tickets.${index}.gross_fare`)"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label>Taxes</Label>
                                <AmountInput
                                    v-model="ticket.taxes"
                                    :currency="company.base_currency"
                                    :error="nestedError(`tickets.${index}.taxes`)"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label>Discount</Label>
                                <AmountInput
                                    v-model="ticket.discount"
                                    :currency="company.base_currency"
                                    :error="nestedError(`tickets.${index}.discount`)"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label>Service fee</Label>
                                <AmountInput
                                    v-model="ticket.service_fee"
                                    :currency="company.base_currency"
                                    :error="nestedError(`tickets.${index}.service_fee`)"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label>Supplier cost</Label>
                                <AmountInput
                                    v-model="ticket.supplier_cost"
                                    :currency="ticket.supplier_currency"
                                    :error="nestedError(`tickets.${index}.supplier_cost`)"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label>Supplier currency</Label>
                                <Select v-model="ticket.supplier_currency">
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
                            </div>
                            <div
                                v-if="ticket.supplier_currency !== company.base_currency"
                                class="space-y-1"
                            >
                                <Label>Supplier exchange rate</Label>
                                <Input
                                    v-model.number="ticket.supplier_exchange_rate"
                                    type="number"
                                    min="0.00000001"
                                    step="0.00000001"
                                    :aria-invalid="Boolean(nestedError(`tickets.${index}.supplier_exchange_rate`))"
                                />
                                <p class="text-xs text-muted-foreground">
                                    1 {{ ticket.supplier_currency }} equals this many
                                    {{ company.base_currency }}
                                </p>
                                <p
                                    v-if="nestedError(`tickets.${index}.supplier_exchange_rate`)"
                                    class="text-xs text-destructive"
                                >
                                    {{ nestedError(`tickets.${index}.supplier_exchange_rate`) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <Button type="button" variant="outline" @click="addTicket">
                        <Plus class="mr-2 h-4 w-4" />Add ticket
                    </Button>

                    <p v-if="form.errors.tickets" class="text-sm text-destructive">
                        {{ form.errors.tickets }}
                    </p>

                    <div class="flex items-center justify-end gap-3 rounded-md border bg-muted/30 p-3">
                        <span class="text-sm text-muted-foreground">Buyer will pay:</span>
                        <MoneyText
                            :amount="buyerTotal"
                            :currency="company.base_currency"
                            class="text-lg font-semibold"
                        />
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end gap-3">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="form.processing"
                    @click="router.get(`/${company.slug}/umrah/tickets`)"
                >
                    Cancel
                </Button>
                <Button type="submit" :disabled="form.processing">
                    <Save class="mr-2 h-4 w-4" />
                    {{ form.processing ? 'Booking...' : 'Book ticket' }}
                </Button>
            </div>
        </form>
    </PageShell>
</template>
