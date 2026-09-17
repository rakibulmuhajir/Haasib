<script setup lang="ts">
import DateTimeText from '@/components/DateTimeText.vue';
import LedgerRegister from '@/components/LedgerRegister.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatMoneyText } from '@/lib/money';
import { paymentMethodLabel } from '@/lib/payment-method';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { CreditCard } from 'lucide-vue-next';
import { computed } from 'vue';

interface CompanyRef {
    id: string;
    name: string;
    slug: string;
    base_currency: string;
}

interface Allocation {
    bill_id: string;
    amount_allocated: number;
    base_amount_allocated: number;
    applied_at: string;
    bill?: {
        bill_number: string;
    };
}

interface PaymentRef {
    id: string;
    payment_group_id?: string | null;
    payment_group_number?: string | null;
    payment_number: string;
    vendor?: { id: string; name: string };
    payment_date: string;
    amount: number;
    transaction_charge?: number;
    base_transaction_charge?: number;
    currency: string;
    base_currency?: string;
    payment_method: string;
    reference_number: string | null;
    notes: string | null;
    payment_account?: { id: string; code: string; name: string } | null;
    allocations?: Allocation[];
}

const props = defineProps<{
    company: CompanyRef;
    payment: PaymentRef;
    groupPayments?: PaymentRef[];
    journalTransactionId?: string | null;
}>();

const paymentTitle = computed(
    () => props.payment.payment_group_number || props.payment.payment_number,
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: `/${props.company.slug}` },
    { title: 'Bill Payments', href: `/${props.company.slug}/bill-payments` },
    {
        title: paymentTitle.value,
        href: `/${props.company.slug}/bill-payments/${props.payment.id}`,
    },
];

const columns = [
    { key: 'bill_number', label: 'Bill #', kind: 'ref' as const },
    { key: 'amount_allocated', label: 'Allocated', kind: 'amount' as const },
    {
        key: 'base_amount_allocated',
        label: 'Base Allocated',
        kind: 'amount' as const,
    },
    { key: 'applied_at', label: 'Applied At', kind: 'date' as const },
];

const sourceColumns = [
    { key: 'payment_number', label: 'Source #', kind: 'ref' as const },
    { key: 'account', label: 'Account', kind: 'text' as const },
    { key: 'method', label: 'Method', kind: 'text' as const },
    { key: 'amount', label: 'Amount', kind: 'amount' as const },
    { key: 'reference', label: 'Reference', kind: 'ref' as const },
];

const formatMoney = (val: number, currency: string) =>
    formatMoneyText(val, currency || 'USD');

const allocationRows = computed(() =>
    (props.groupPayments || [props.payment])
        .flatMap((payment) => payment.allocations || [])
        .map((a) => ({
            bill_number: a.bill?.bill_number || a.bill_id,
            amount_allocated: formatMoney(
                a.amount_allocated,
                props.payment.currency,
            ),
            base_amount_allocated: formatMoney(
                a.base_amount_allocated,
                props.payment.base_currency || props.company.base_currency,
            ),
            applied_at: a.applied_at,
        })),
);

const sourceRows = computed(() =>
    (props.groupPayments || [props.payment]).map((payment) => ({
        payment_number: payment.payment_number,
        account: payment.payment_account
            ? `${payment.payment_account.code} — ${payment.payment_account.name}`
            : '—',
        method: paymentMethodLabel(payment.payment_method),
        amount: formatMoney(payment.amount, payment.currency),
        reference: payment.reference_number || '—',
    })),
);

const groupedAmount = computed(() =>
    (props.groupPayments || [props.payment]).reduce(
        (sum, payment) => sum + Number(payment.amount || 0),
        0,
    ),
);

const groupedCharge = computed(() =>
    (props.groupPayments || [props.payment]).reduce(
        (sum, payment) => sum + Number(payment.transaction_charge || 0),
        0,
    ),
);

const groupedCashMovement = computed(
    () => groupedAmount.value + groupedCharge.value,
);
</script>

<template>
    <Head :title="`Payment ${paymentTitle}`" />
    <PageShell
        :title="`Payment ${paymentTitle}`"
        :breadcrumbs="breadcrumbs"
        :icon="CreditCard"
    >
        <template #actions>
            <Button
                v-if="journalTransactionId"
                variant="outline"
                @click="
                    router.get(
                        `/${company.slug}/journals/${journalTransactionId}`,
                    )
                "
            >
                View Journal
            </Button>
        </template>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="space-y-1">
                <div class="text-sm text-muted-foreground">Vendor</div>
                <div class="font-semibold">
                    {{ payment.vendor?.name ?? '—' }}
                </div>
            </div>
            <div class="space-y-1">
                <div class="text-sm text-muted-foreground">Date</div>
                <div class="font-semibold">
                    <DateTimeText :value="payment.payment_date" mode="date" />
                </div>
            </div>
            <div class="space-y-1">
                <div class="text-sm text-muted-foreground">Amount</div>
                <div class="font-semibold">
                    <MoneyText
                        :amount="groupedAmount"
                        :currency="payment.currency"
                    />
                </div>
            </div>
            <div class="space-y-1">
                <div class="text-sm text-muted-foreground">
                    Transaction charges
                </div>
                <div class="font-semibold">
                    <MoneyText
                        :amount="groupedCharge"
                        :currency="payment.currency"
                    />
                </div>
            </div>
            <div class="space-y-1">
                <div class="text-sm text-muted-foreground">
                    Total cash/bank movement
                </div>
                <div class="font-semibold">
                    <MoneyText
                        :amount="groupedCashMovement"
                        :currency="payment.currency"
                    />
                </div>
            </div>
            <div class="space-y-1">
                <div class="text-sm text-muted-foreground">Sources</div>
                <Badge variant="outline">{{ sourceRows.length }}</Badge>
            </div>
            <div class="space-y-1">
                <div class="text-sm text-muted-foreground">Reference</div>
                <div class="font-semibold">
                    {{ payment.reference_number || '—' }}
                </div>
            </div>
            <div class="space-y-1">
                <div class="text-sm text-muted-foreground">Notes</div>
                <div class="font-semibold">{{ payment.notes || '—' }}</div>
            </div>
        </div>

        <div class="mt-6">
            <div class="mb-2 text-lg font-semibold">Payment Sources</div>
            <LedgerRegister :columns="sourceColumns" :data="sourceRows" />
        </div>

        <div class="mt-6">
            <div class="mb-2 text-lg font-semibold">Allocations</div>
            <LedgerRegister :columns="columns" :data="allocationRows">
                <template #cell-applied_at="{ value }">
                    <DateTimeText :value="value" mode="date" />
                </template>
            </LedgerRegister>
        </div>
    </PageShell>
</template>
