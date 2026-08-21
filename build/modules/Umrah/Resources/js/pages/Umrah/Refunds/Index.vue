<script setup lang="ts">
import DateTimeText from '@/components/DateTimeText.vue';
import LedgerRegister from '@/components/LedgerRegister.vue';
import MetaChip from '@/components/MetaChip.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import RecordPagination from '@/components/RecordPagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Undo2 } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    refunds: any;
    statuses: Record<string, string>;
    filters: { status?: string };
    canCreate: boolean;
    canApprove: boolean;
}>();

const status = ref(props.filters.status || 'all');
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Refunds', href: `/${props.company.slug}/umrah/refunds` },
];

const applyFilters = () =>
    router.get(
        `/${props.company.slug}/umrah/refunds`,
        { status: status.value === 'all' ? undefined : status.value },
        { preserveState: true, replace: true },
    );

/**
 * A refund is either owed to an agent or owed by a vendor, but that is one
 * row's fact -- party_type -- not a colour. Direction stays a plain chip,
 * the same law the payment register follows.
 */
const columns = [
    { key: 'refund_number', label: 'Refund', kind: 'ref' as const },
    { key: 'requested_at', label: 'Requested', kind: 'date' as const },
    { key: 'party', label: 'Party', kind: 'text' as const },
    { key: 'service', label: 'Service', kind: 'text' as const },
    { key: 'group', label: 'Group', kind: 'text' as const },
    { key: 'amount', label: 'Amount', kind: 'amount' as const },
    { key: 'actions', label: '', kind: 'text' as const, class: 'text-right', headerClass: 'text-right' },
];
</script>

<template>
    <Head title="Refunds" />
    <PageShell
        title="Refunds"
        description="Money owed back to an agent or a vendor."
        :breadcrumbs="breadcrumbs"
        :icon="Undo2"
    >
        <template #actions>
            <Button
                v-if="canCreate"
                @click="router.get(`/${company.slug}/umrah/refunds/create`)"
                ><Undo2 class="mr-2 h-4 w-4" />Request Refund</Button
            >
        </template>

        <div class="flex flex-col gap-3 md:flex-row">
            <Select v-model="status" @update:model-value="applyFilters">
                <SelectTrigger class="w-full md:w-56"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent
                    ><SelectItem value="all">All statuses</SelectItem
                    ><SelectItem
                        v-for="(label, value) in statuses"
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
                <LedgerRegister :data="refunds.data" :columns="columns">
                    <template #empty>No refunds found.</template>

                    <template #cell-refund_number="{ row }">
                        {{ row.refund_number }}
                        <StatusBadge :status="row.status" />
                    </template>

                    <template #cell-requested_at="{ row }">
                        <DateTimeText :value="row.requested_at" mode="date" />
                    </template>

                    <template #cell-party="{ row }">
                        {{ row.party_name || '—' }}
                    </template>

                    <template #cell-service="{ row }">
                        <MetaChip tone="neutral" bare>{{ row.service }}</MetaChip>
                    </template>

                    <template #cell-group="{ row }">
                        {{ row.group ? `${row.group.group_number} · ${row.group.name}` : '—' }}
                    </template>

                    <template #cell-amount="{ row }">
                        <MoneyText :amount="row.amount" :currency="row.currency" />
                    </template>

                    <template #cell-actions="{ row }">
                        <div class="flex justify-end gap-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="router.get(`/${company.slug}/umrah/refunds/${row.id}`)"
                                >Details</Button
                            >
                        </div>
                    </template>
                </LedgerRegister>
                <RecordPagination
                    :current-page="refunds.current_page"
                    :last-page="refunds.last_page"
                    :from="refunds.from"
                    :to="refunds.to"
                    :total="refunds.total"
                    :previous-url="refunds.prev_page_url"
                    :next-url="refunds.next_page_url"
                />
            </CardContent>
        </Card>
    </PageShell>
</template>
