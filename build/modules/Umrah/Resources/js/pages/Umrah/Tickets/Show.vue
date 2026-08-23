<script setup lang="ts">
import DateTimeText from '@/components/DateTimeText.vue';
import LedgerRegister from '@/components/LedgerRegister.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Ticket as TicketIcon, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CancelDialog from './CancelDialog.vue';

type TicketRow = {
    id: string;
    passenger_name: string;
    airline: string | null;
    route: string | null;
    travel_date: string | null;
    amount_base: number;
    currency: string;
    status: string;
    supplier_cost_base?: number;
    commission_base?: number;
};

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    booking: {
        id: string;
        booking_reference: string;
        booking_date: string | null;
        pnr: string | null;
        status: string;
        buyer: string | null;
        agent: string | null;
        currency: string;
        invoice: { id: string; invoice_number: string } | null;
        supplier?: string | null;
        bill?: { id: string; bill_number: string } | null;
        tickets: TicketRow[];
    };
    canCancel: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Tickets', href: `/${props.company.slug}/umrah/tickets` },
    { title: props.booking.booking_reference, href: '#' },
];

// Cost/commission columns only exist in the payload for a viewer holding
// the full ticket.view permission -- absent entirely for own-view, not
// merely hidden, so their presence on the first row is the only signal
// this page needs.
const showCosts = computed(() => props.booking.tickets[0]?.supplier_cost_base !== undefined);

const columns = computed(() => [
    { key: 'passenger_name', label: 'Passenger', kind: 'text' as const },
    { key: 'airline', label: 'Airline', kind: 'text' as const },
    { key: 'route', label: 'Route', kind: 'text' as const },
    { key: 'travel_date', label: 'Travel Date', kind: 'date' as const },
    { key: 'amount_base', label: 'Amount', kind: 'amount' as const },
    ...(showCosts.value
        ? [
              { key: 'supplier_cost_base', label: 'Supplier Cost', kind: 'amount' as const },
              { key: 'commission_base', label: 'Commission', kind: 'amount' as const },
          ]
        : []),
    { key: 'status', label: 'Status', kind: 'status' as const },
    ...(props.canCancel ? [{ key: 'actions', label: '', kind: 'text' as const }] : []),
]);

const selectedTicket = ref<{ id: string; passenger_name: string } | null>(null);
const cancelOpen = ref(false);

const openCancel = (row: TicketRow) => {
    selectedTicket.value = { id: row.id, passenger_name: row.passenger_name };
    cancelOpen.value = true;
};
</script>

<template>
    <Head :title="`Booking ${booking.booking_reference}`" />
    <PageShell
        :title="`Booking ${booking.booking_reference}`"
        description="A ticket booking, the passengers it carries, and where its money is standing."
        :breadcrumbs="breadcrumbs"
        :icon="TicketIcon"
    >
        <template #actions>
            <Button variant="outline" @click="router.get(`/${company.slug}/umrah/tickets`)">Back to Tickets</Button>
        </template>

        <div class="grid gap-4 md:grid-cols-4">
            <Card variant="detail">
                <CardContent class="space-y-1 pt-6">
                    <p class="text-xs text-muted-foreground uppercase">Booking Date</p>
                    <DateTimeText :value="booking.booking_date" mode="date" />
                </CardContent>
            </Card>
            <Card variant="detail">
                <CardContent class="space-y-1 pt-6">
                    <p class="text-xs text-muted-foreground uppercase">Buyer</p>
                    <p class="font-medium">{{ booking.buyer || '—' }}</p>
                </CardContent>
            </Card>
            <Card variant="detail">
                <CardContent class="space-y-1 pt-6">
                    <p class="text-xs text-muted-foreground uppercase">Agent</p>
                    <p class="font-medium">{{ booking.agent || '—' }}</p>
                </CardContent>
            </Card>
            <Card variant="detail">
                <CardContent class="space-y-1 pt-6">
                    <p class="text-xs text-muted-foreground uppercase">Status</p>
                    <StatusBadge :status="booking.status" />
                </CardContent>
            </Card>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <Card variant="detail">
                <CardContent class="space-y-1 pt-6">
                    <p class="text-xs text-muted-foreground uppercase">PNR</p>
                    <p class="font-mono">{{ booking.pnr || '—' }}</p>
                </CardContent>
            </Card>
            <Card variant="detail">
                <CardContent class="space-y-1 pt-6">
                    <p class="text-xs text-muted-foreground uppercase">Invoice</p>
                    <Link
                        v-if="booking.invoice"
                        :href="`/${company.slug}/acct/invoices/${booking.invoice.id}`"
                        class="font-mono text-primary underline-offset-4 hover:underline"
                    >
                        {{ booking.invoice.invoice_number }}
                    </Link>
                    <p v-else>—</p>
                </CardContent>
            </Card>
            <Card v-if="booking.bill !== undefined" variant="detail">
                <CardContent class="space-y-1 pt-6">
                    <p class="text-xs text-muted-foreground uppercase">Supplier Bill</p>
                    <Link
                        v-if="booking.bill"
                        :href="`/${company.slug}/acct/bills/${booking.bill.id}`"
                        class="font-mono text-primary underline-offset-4 hover:underline"
                    >
                        {{ booking.bill.bill_number }}
                    </Link>
                    <p v-else>—</p>
                </CardContent>
            </Card>
        </div>

        <Card variant="register" class="mt-6">
            <CardContent>
                <LedgerRegister :data="booking.tickets" :columns="columns">
                    <template #empty>No tickets on this booking.</template>

                    <template #cell-passenger_name="{ row }">
                        {{ row.passenger_name }}
                    </template>

                    <template #cell-airline="{ row }">
                        {{ row.airline || '—' }}
                    </template>

                    <template #cell-route="{ row }">
                        {{ row.route || '—' }}
                    </template>

                    <template #cell-travel_date="{ row }">
                        <DateTimeText :value="row.travel_date" mode="date" />
                    </template>

                    <template #cell-amount_base="{ row }">
                        <MoneyText :amount="row.amount_base" :currency="row.currency" />
                    </template>

                    <template #cell-supplier_cost_base="{ row }">
                        <MoneyText :amount="row.supplier_cost_base" :currency="row.currency" />
                    </template>

                    <template #cell-commission_base="{ row }">
                        <MoneyText :amount="row.commission_base" :currency="row.currency" />
                    </template>

                    <template #cell-status="{ row }">
                        <StatusBadge :status="row.status" />
                    </template>

                    <template #cell-actions="{ row }">
                        <Button
                            v-if="row.status !== 'cancelled'"
                            variant="ghost"
                            size="sm"
                            @click="openCancel(row)"
                        >
                            <XCircle class="mr-1 h-4 w-4" />Cancel
                        </Button>
                    </template>
                </LedgerRegister>
            </CardContent>
        </Card>

        <CancelDialog
            v-model:open="cancelOpen"
            :company-slug="company.slug"
            :currency="booking.currency"
            :ticket="selectedTicket"
        />
    </PageShell>
</template>
