<script setup lang="ts">
/**
 * A payment is a receipt, and a receipt is a document.
 *
 * This page used to build one by hand: a logo block, a heading, a coloured
 * badge, a grid of labelled paragraphs, and a list of allocations as bordered
 * rows. It was the fourth different way this application drew the same sheet.
 * It now goes through LedgerDocument like the invoice, the bill and the credit
 * note, so the letterhead, the party blocks, the figures and the total are all
 * decided in one place.
 *
 * The allocations are the line items. That is what they are: this much of the
 * money went against that invoice. Whatever is left over is a line too --
 * unapplied credit is a real position a customer can be in, and a receipt that
 * silently omits it does not add up to the amount printed at the bottom.
 */
import DefinitionList from '@/components/DefinitionList.vue';
import type {
    DocumentIssuer,
    DocumentLine,
} from '@/components/LedgerDocument.vue';
import LedgerDocument from '@/components/LedgerDocument.vue';
import MetaChip from '@/components/MetaChip.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import RelatedActions from '@/components/RelatedActions.vue';
import CorrectRecordDialog from '../../components/CorrectRecordDialog.vue';
import CorrectionHistory from '../../components/CorrectionHistory.vue';
import type { Correction } from '../../components/CorrectionHistory.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDateTime as formatSharedDateTime } from '@/lib/datetime';
import { formatMoneyText } from '@/lib/money';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Input } from '@/components/ui/input';
import { ArrowLeft, Edit, MoreHorizontal, PencilLine } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import {
    allocationDisplayAmount as computeAllocationDisplayAmount,
    appliedAllocations as computeAppliedAllocations,
    unappliedAmount,
} from '../../lib/paymentAllocations';

interface Invoice {
    id: string;
    invoice_number: string;
    currency: string;
}

interface PaymentAllocation {
    id: string;
    invoice_id: string;
    invoice?: Invoice;
    amount_allocated: number;
    base_amount_allocated: number;
}

interface Customer {
    id: string;
    name: string;
    email?: string;
}

interface Payment {
    id: string;
    payment_number: string;
    customer: Customer;
    amount: number;
    transaction_charge?: number;
    base_transaction_charge?: number;
    currency: string;
    payment_method: string;
    reference_number?: string;
    payment_date: string;
    notes?: string;
    payment_allocations: PaymentAllocation[];
    created_at: string;
}

interface CompanyRef {
    id: string;
    name: string;
    slug: string;
    /** Assembled server-side by CompanyLetterhead — see the invoice page. */
    letterhead: DocumentIssuer;
}

const props = defineProps<{
    company: CompanyRef;
    payment: Payment;
    openInvoices?: Array<{ id: string; invoice_number: string; invoice_date: string; balance: number | string }>;
    canApply?: boolean;
    canCorrect?: boolean;
    correctionCustomers?: { id: string; name: string }[];
    corrections?: Correction[];
}>();

const correcting = ref(false);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: props.company.name, href: `/${props.company.slug}` },
    { title: 'Payments', href: `/${props.company.slug}/payments` },
    { title: props.payment.payment_number },
]);

/**
 * Written as 'cheque' by the form, stored as 'check' by the column's check
 * constraint. Both spellings arrive here.
 */
const paymentMethodLabels: Record<string, string> = {
    cash: 'Cash',
    bank_transfer: 'Bank transfer',
    card: 'Card',
    cheque: 'Cheque',
    check: 'Cheque',
};

const methodLabel = computed(
    () => paymentMethodLabels[props.payment.payment_method] ?? 'Other',
);

const formatDate = (dateString: string) =>
    formatSharedDateTime(dateString, { mode: 'date' });

const formatMoney = (amount: number, currency: string) =>
    formatMoneyText(amount, currency || 'USD');

const issuer = computed(() => props.company.letterhead);

const receivedFrom = computed(() => ({
    name: props.payment.customer.name,
    email: props.payment.customer.email,
}));

const documentDates = computed(() =>
    [
        { label: 'Received', value: formatDate(props.payment.payment_date) },
        { label: 'Method', value: methodLabel.value },
        { label: 'Reference', value: props.payment.reference_number ?? null },
    ].filter((date): date is { label: string; value: string } =>
        Boolean(date.value),
    ),
);

// Allocation splitting (applied vs. on-account/unapplied) lives in lib/paymentAllocations
// so it can be unit tested without mounting this whole page -- see
// tests/js/paymentAllocations.spec.ts and commit f4811b93.
const allocationDisplayAmount = (allocation: PaymentAllocation) =>
    computeAllocationDisplayAmount(allocation, props.payment.currency);

const appliedAllocations = computed(() =>
    computeAppliedAllocations(props.payment.payment_allocations),
);

const unapplied = computed(() =>
    unappliedAmount(
        props.payment.payment_allocations,
        props.payment.amount,
        props.payment.currency,
    ),
);

/**
 * One line per invoice the money went against, then the remainder if the
 * customer paid more than they owed on those invoices. The lines add up to the
 * total or the receipt is wrong, so the remainder is never left off.
 */
const documentLines = computed<DocumentLine[]>(() => {
    const lines: DocumentLine[] = appliedAllocations.value.map((allocation) => ({
        description: `Applied to ${allocation.invoice?.invoice_number}`,
        amount: allocationDisplayAmount(allocation),
    }));

    if (unapplied.value > 0.005) {
        lines.push({
            description: lines.length
                ? 'Unapplied credit'
                : 'Payment on account',
            detail: lines.length ? 'Held against future invoices' : undefined,
            amount: unapplied.value,
        });
    }

    return lines;
});

// What this payment still has on account, applied to the customer's unpaid invoices: amounts
// start oldest-first up to what is left and can be changed. Nothing new is posted.
const applyForm = useForm({ amounts: {} as Record<string, number> });
watch([() => props.openInvoices, unapplied], () => {
    let left = unapplied.value;
    applyForm.amounts = Object.fromEntries((props.openInvoices ?? []).map((invoice) => {
        const take = Math.max(0, Math.min(left, Number(invoice.balance)));
        left = Math.round((left - take) * 100) / 100;
        return [invoice.id, Math.round(take * 100) / 100];
    }));
}, { immediate: true });
const applyTotal = computed(() => Object.values(applyForm.amounts).reduce((sum, a) => sum + Number(a || 0), 0));
const applyToInvoices = () => {
    applyForm
        .transform((data) => ({
            lines: Object.entries(data.amounts)
                .filter(([, amount]) => Number(amount) > 0)
                .map(([invoice_id, amount]) => ({ invoice_id, amount: Number(amount) })),
        }))
        .post(`/${props.company.slug}/payments/${props.payment.id}/apply`, { preserveScroll: true });
};

const summaryItems = computed(() => [
    { term: 'Method', value: methodLabel.value },
    {
        term: 'Transaction charges',
        value: formatMoney(
            Number(props.payment.transaction_charge ?? 0),
            props.payment.currency,
        ),
    },
    {
        term: 'Net bank/cash movement',
        value: formatMoney(
            Math.max(
                0,
                Number(props.payment.amount) -
                    Number(props.payment.transaction_charge ?? 0),
            ),
            props.payment.currency,
        ),
    },
    { term: 'Reference', value: props.payment.reference_number ?? null },
    { term: 'Recorded', value: formatDate(props.payment.created_at) },
]);
</script>

<template>
    <Head :title="`Payment ${payment.payment_number}`" />

    <PageShell
        :title="`Payment ${payment.payment_number}`"
        :breadcrumbs="breadcrumbs"
    >
        <template #actions>
            <Button
                variant="outline"
                @click="router.get(`/${company.slug}/payments`)"
            >
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back
            </Button>

            <Button v-if="canCorrect" variant="outline" @click="correcting = true">
                <PencilLine class="mr-2 h-4 w-4" />
                Correct
            </Button>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline">
                        <MoreHorizontal class="mr-2 h-4 w-4" />
                        More
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem
                        @click="
                            router.get(
                                `/${company.slug}/payments/${payment.id}/edit`,
                            )
                        "
                    >
                        <Edit class="mr-2 h-4 w-4" />
                        Edit
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </template>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <LedgerDocument
                    doc-type="Receipt"
                    :doc-number="payment.payment_number"
                    :issuer="issuer"
                    :bill-to="receivedFrom"
                    bill-to-label="Received from"
                    :dates="documentDates"
                    :lines="documentLines"
                    grand-total-label="Amount received"
                    :grand-total-amount="payment.amount"
                    :currency="payment.currency"
                    locale="en-PK"
                    :show-quantity="false"
                />

                <Card v-if="payment.notes" variant="detail">
                    <CardHeader>
                        <CardTitle>Internal notes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm" dir="auto">{{ payment.notes }}</p>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card variant="detail">
                    <CardHeader>
                        <CardTitle>How it was paid</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <DefinitionList :items="summaryItems" />
                    </CardContent>
                </Card>

                <!-- What the money did. A receipt whose allocations are hidden in a
             sidebar total is a receipt nobody can reconcile against. -->
                <Card variant="detail">
                    <CardHeader>
                        <CardTitle>Where it went</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="allocation in appliedAllocations"
                            :key="allocation.id"
                            class="flex items-center justify-between gap-3 text-sm"
                        >
                            <button
                                type="button"
                                class="text-left underline-offset-2 hover:underline focus-visible:underline focus-visible:outline-none"
                                @click="
                                    router.get(
                                        `/${company.slug}/invoices/${allocation.invoice_id}`,
                                    )
                                "
                            >
                                {{
                                    allocation.invoice?.invoice_number ||
                                    'Invoice'
                                }}
                            </button>
                            <MoneyText
                                :amount="allocationDisplayAmount(allocation)"
                                :currency="payment.currency"
                                locale="en-PK"
                            />
                        </div>

                        <div
                            v-if="unapplied > 0.005"
                            class="flex items-center justify-between gap-3 text-sm"
                        >
                            <MetaChip>On account</MetaChip>
                            <MoneyText
                                :amount="unapplied"
                                :currency="payment.currency"
                                locale="en-PK"
                            />
                        </div>

                        <p
                            v-if="
                                !appliedAllocations.length &&
                                unapplied <= 0.005
                            "
                            class="text-sm text-muted-foreground"
                        >
                            Not applied to any invoice yet.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Card v-if="canApply && unapplied > 0.005 && openInvoices?.length" class="mt-6 print:hidden">
            <CardHeader>
                <CardTitle class="text-base">Apply to invoices</CardTitle>
                <p class="text-sm text-muted-foreground">
                    On account: {{ formatMoneyText(unapplied, payment.currency) }}
                </p>
            </CardHeader>
            <CardContent class="space-y-3">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="py-1">Invoice</th>
                            <th class="py-1">Date</th>
                            <th class="py-1 text-right">Owed</th>
                            <th class="py-1 text-right">Apply</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="invoice in openInvoices" :key="invoice.id" class="border-t">
                            <td class="py-1.5">{{ invoice.invoice_number }}</td>
                            <td class="py-1.5 tabular-nums">{{ String(invoice.invoice_date).slice(0, 10) }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ formatMoneyText(Number(invoice.balance), payment.currency) }}</td>
                            <td class="py-1 text-right">
                                <Input
                                    v-model.number="applyForm.amounts[invoice.id]"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="ml-auto h-8 w-36 text-right"
                                    :aria-label="`Apply to ${invoice.invoice_number}`"
                                    @focus="(e: FocusEvent) => (e.target as HTMLInputElement).select()"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="applyForm.errors.lines" class="text-sm text-destructive">{{ applyForm.errors.lines }}</p>
                <div class="flex items-center justify-between">
                    <span class="text-sm" :class="applyTotal - unapplied > 0.005 ? 'text-destructive' : 'text-muted-foreground'">
                        Applying {{ formatMoneyText(applyTotal, payment.currency) }} of {{ formatMoneyText(unapplied, payment.currency) }}
                    </span>
                    <Button :disabled="applyForm.processing || applyTotal <= 0 || applyTotal - unapplied > 0.005" @click="applyToInvoices">Apply</Button>
                </div>
            </CardContent>
        </Card>

        <div class="mt-6">
            <CorrectionHistory :corrections="corrections ?? []" :slug="company.slug" />
        </div>

        <CorrectRecordDialog
            v-if="canCorrect"
            v-model:open="correcting"
            kind="payment"
            :url="`/${company.slug}/payments/${payment.id}/correct`"
            :number="payment.payment_number"
            :total="Number(payment.amount)"
            :customer-id="payment.customer?.id ?? null"
            :parties="correctionCustomers ?? []"
        />

        <RelatedActions
            screen="payment.show"
            :slug="company.slug"
            :subject="payment"
        />
    </PageShell>
</template>
