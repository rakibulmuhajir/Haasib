<script setup lang="ts">
import DateTimeText from '@/components/DateTimeText.vue';
import LedgerRegister from '@/components/LedgerRegister.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import RecordPagination from '@/components/RecordPagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    Calculator,
    LoaderCircle,
    Plane,
    Plus,
    Search,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    company: { slug: string; base_currency: string };
    groups: {
        data: any[];
        total: number;
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { search?: string };
    canViewAccounting: boolean;
    canViewReadiness: boolean;
}>();

const search = ref(props.filters.search || '');
const searching = ref(false);

/**
 * The payment state used to be spelled out here, one page's private idea of
 * what 'partially_paid' should read as. It is the same state an invoice is in,
 * so it goes through the shared vocabulary and comes out looking the same.
 */
const columns = computed(() => [
    { key: 'group_number', label: 'Group', kind: 'ref' as const },
    { key: 'agent', label: 'Agent', kind: 'text' as const },
    { key: 'travel_date', label: 'Travel', kind: 'date' as const },
    { key: 'passenger_count', label: 'Pax', kind: 'amount' as const },
    { key: 'total_receivable', label: 'Receivable', kind: 'amount' as const },
    { key: 'balance', label: 'Balance', kind: 'amount' as const },
    { key: 'payment_status', label: 'Payment', kind: 'status' as const },
    ...(props.canViewReadiness
        ? [
              {
                  key: 'booking_readiness',
                  label: 'Booking readiness',
                  kind: 'text' as const,
              },
          ]
        : []),
    ...(props.canViewAccounting
        ? [
              {
                  key: 'actions',
                  label: '',
                  kind: 'text' as const,
                  class: 'text-right',
                  headerClass: 'text-right',
              },
          ]
        : []),
]);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Visa Groups', href: `/${props.company.slug}/umrah/groups` },
];

const applyFilters = () => {
    search.value = search.value.trim();
    router.get(
        `/${props.company.slug}/umrah/groups`,
        {
            search: search.value,
        },
        {
            preserveState: true,
            onStart: () => {
                searching.value = true;
            },
            onFinish: () => {
                searching.value = false;
            },
        },
    );
};

const clearFilters = () => {
    search.value = '';
    applyFilters();
};
</script>

<template>
    <Head title="Visa Groups" />
    <PageShell
        title="Visa Groups"
        description="One group keeps agent, passports, visa cost, payment, travel, hotel, and transport together."
        :breadcrumbs="breadcrumbs"
        :icon="Plane"
    >
        <template #actions>
            <Button @click="router.get(`/${company.slug}/umrah/groups/create`)">
                <Plus class="mr-2 h-4 w-4" />
                New Visa Group
            </Button>
        </template>

        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto_auto]">
            <div class="relative">
                <Search
                    class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="pl-10"
                    placeholder="Group number, name, passenger, or passport"
                    @keyup.enter="applyFilters"
                />
            </div>
            <Button
                variant="secondary"
                :disabled="searching"
                @click="applyFilters"
            >
                <LoaderCircle
                    v-if="searching"
                    class="mr-2 h-4 w-4 animate-spin"
                />
                <Search v-else class="mr-2 h-4 w-4" />Search
            </Button>
            <Button
                v-if="search"
                variant="ghost"
                :disabled="searching"
                @click="clearFilters"
            >
                <X class="mr-2 h-4 w-4" />Clear
            </Button>
        </div>

        <Card variant="register">
            <CardContent>
                <LedgerRegister
                    :data="groups.data"
                    :columns="columns"
                    clickable
                    @row-click="
                        (row) =>
                            router.get(
                                `/${company.slug}/umrah/groups/${row.id}`,
                            )
                    "
                >
                    <template #empty>{{
                        search
                            ? 'No visa groups match your search.'
                            : 'No visa groups yet.'
                    }}</template>

                    <template #cell-agent="{ row }">{{
                        row.agent?.name || '—'
                    }}</template>

                    <template #cell-travel_date="{ row }">
                        <DateTimeText :value="row.travel_date" mode="date" />
                    </template>

                    <template #cell-total_receivable="{ row }">
                        <MoneyText
                            :amount="row.total_receivable"
                            :currency="company.base_currency"
                            class="font-medium"
                        />
                    </template>
                    <template #cell-balance="{ row }">
                        <MoneyText
                            :amount="row.balance"
                            :currency="company.base_currency"
                            class="font-medium"
                        />
                    </template>

                    <template #cell-payment_status="{ row }">
                        <StatusBadge :status="row.payment_status || 'unpaid'" />
                    </template>

                    <template #cell-booking_readiness="{ row }">
                        <div class="flex items-start gap-2">
                            <span
                                class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full"
                                :class="
                                    {
                                        green: 'bg-emerald-500',
                                        orange: 'bg-orange-500',
                                        red: 'bg-red-500',
                                        grey: 'bg-slate-400',
                                    }[row.booking_readiness?.colour || 'grey']
                                "
                                aria-hidden="true"
                            />
                            <div>
                                <p class="text-sm">
                                    {{ row.booking_readiness?.label }}
                                </p>
                                <p
                                    v-if="row.booking_readiness?.detail"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ row.booking_readiness.detail }}
                                </p>
                            </div>
                        </div>
                    </template>

                    <template #cell-actions="{ row }">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            title="Open group accounting"
                            @click.stop="
                                router.get(
                                    `/${company.slug}/umrah/groups/${row.id}/accounting`,
                                )
                            "
                        >
                            <Calculator class="h-4 w-4" />
                        </Button>
                    </template>
                </LedgerRegister>
                <RecordPagination
                    :current-page="groups.current_page"
                    :last-page="groups.last_page"
                    :from="groups.from"
                    :to="groups.to"
                    :total="groups.total"
                    :previous-url="groups.prev_page_url"
                    :next-url="groups.next_page_url"
                />
            </CardContent>
        </Card>
    </PageShell>
</template>
