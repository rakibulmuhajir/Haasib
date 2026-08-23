<script setup lang="ts">
import DateTimeText from '@/components/DateTimeText.vue';
import LedgerRegister from '@/components/LedgerRegister.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import RecordPagination from '@/components/RecordPagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Ticket } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    bookings: any;
    canCreate: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Tickets', href: `/${props.company.slug}/umrah/tickets` },
];

// Cost and commission are only in the payload at all when the viewer
// holds the full ticket.view permission -- an own.view agent's rows
// never carry these keys, so the columns simply don't appear rather
// than being hidden client-side.
const showCosts = computed(() =>
    (props.bookings.data ?? []).some(
        (row: any) => row.supplier_cost_base !== undefined,
    ),
);

const columns = computed(() => [
    { key: 'booking_reference', label: 'Booking', kind: 'ref' as const },
    { key: 'booking_date', label: 'Date', kind: 'date' as const },
    { key: 'buyer', label: 'Buyer', kind: 'text' as const },
    { key: 'pnr', label: 'PNR', kind: 'ref' as const },
    { key: 'passenger_count', label: 'Passengers', kind: 'text' as const },
    { key: 'amount_base', label: 'Amount', kind: 'amount' as const },
    ...(showCosts.value
        ? [
              { key: 'supplier_cost_base', label: 'Supplier Cost', kind: 'amount' as const },
              { key: 'commission_base', label: 'Commission', kind: 'amount' as const },
          ]
        : []),
    { key: 'status', label: 'Status', kind: 'status' as const },
]);
</script>

<template>
    <Head title="Tickets" />
    <PageShell
        title="Tickets"
        description="Ticket bookings sold to buyers, with the supplier cost that stands behind each one."
        :breadcrumbs="breadcrumbs"
        :icon="Ticket"
    >
        <template #actions>
            <Button
                v-if="canCreate"
                @click="router.get(`/${company.slug}/umrah/tickets/create`)"
                ><Ticket class="mr-2 h-4 w-4" />Book Ticket</Button
            >
        </template>

        <Card variant="register">
            <CardContent>
                <LedgerRegister :data="bookings.data" :columns="columns">
                    <template #empty>No ticket bookings found.</template>

                    <template #cell-booking_reference="{ row }">
                        {{ row.booking_reference }}
                    </template>

                    <template #cell-booking_date="{ row }">
                        <DateTimeText :value="row.booking_date" mode="date" />
                    </template>

                    <template #cell-buyer="{ row }">
                        {{ row.buyer || '—' }}
                    </template>

                    <template #cell-pnr="{ row }">
                        {{ row.pnr || '—' }}
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
                </LedgerRegister>
                <RecordPagination
                    :current-page="bookings.current_page"
                    :last-page="bookings.last_page"
                    :from="bookings.from"
                    :to="bookings.to"
                    :total="bookings.total"
                    :previous-url="bookings.prev_page_url"
                    :next-url="bookings.next_page_url"
                />
            </CardContent>
        </Card>
    </PageShell>
</template>
