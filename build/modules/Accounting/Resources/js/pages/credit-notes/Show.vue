<script setup lang="ts">
import { computed, ref } from 'vue';

import { Head, Link, router, useForm } from '@inertiajs/vue3';

import { localToday } from '@/composables/useEntryDate';

import { useFormFeedback } from '@/composables/useFormFeedback';

import { Input } from '@/components/ui/input';

import { Label } from '@/components/ui/label';

import InputError from '@/components/InputError.vue';

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

import PageShell from '@/components/PageShell.vue';

import LedgerDocument from '@/components/LedgerDocument.vue';

import type { DocumentStampData } from '@/components/DocumentStamp.vue';

import type {
    DocumentIssuer,
    DocumentLine,
} from '@/components/LedgerDocument.vue';

import StatusBadge from '@/components/StatusBadge.vue';

import MoneyText from '@/components/MoneyText.vue';

import { Button } from '@/components/ui/button';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

import { Separator } from '@/components/ui/separator';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

import type { BreadcrumbItem } from '@/types';

import { formatDateTime as formatSharedDateTime } from '@/lib/datetime';

import { ArrowLeft, Edit, MoreHorizontal, Send } from 'lucide-vue-next';

interface Customer {
    id: string;

    name: string;

    email?: string;
}

interface Invoice {
    id: string;

    invoice_number: string;
}

interface CreditNote {
    id: string;

    credit_note_number: string;

    customer: Customer;

    invoice?: Invoice;

    amount: number;

    base_currency: string;

    reason: string;

    status: string;

    credit_date: string;

    notes?: string;

    terms?: string;

    sent_at?: string;

    posted_at?: string;

    voided_at?: string;

    created_at: string;

    transaction_id?: string | null;

    currency?: string;

    period_discount?: {
        from: string;
        to: string;
        method: string;
        value: number;
        eligible_amount: number;
        litres: number;
    } | null;
}

interface CompanyRef {
    id: string;

    name: string;

    slug: string;

    letterhead: DocumentIssuer;
}

const props = defineProps<{
    company: CompanyRef;

    credit_note: CreditNote;

    stamp?: DocumentStampData | null;

    periodSources?: {
        data: Array<{
            id: string;
            number: string;
            date: string;
            gross: number;
            litres: number;
        }>;
        next_page_url?: string | null;
        prev_page_url?: string | null;
        total: number;
    };

    availableCredit?: number;

    canApply?: boolean;

    canVoid?: boolean;

    openInvoices?: Array<{
        id: string;
        invoice_number: string;
        balance: number;
    }>;
}>();

const { showError } = useFormFeedback();

const applyForm = useForm({
    invoice_id: '',
    amount: 0,
    applied_at: localToday(),
});

const voidOpen = ref(false);

const voidForm = useForm({ reason: '' });

const apply = () =>
    applyForm.post(
        `/${props.company.slug}/credit-notes/${props.credit_note.id}/apply`,
        {
            preserveScroll: true,
            onError: showError,
            onSuccess: () => applyForm.reset(),
        },
    );

const voidCredit = () =>
    voidForm.post(
        `/${props.company.slug}/credit-notes/${props.credit_note.id}/void`,
        {
            preserveScroll: true,
            onError: showError,
            onSuccess: () => {
                voidOpen.value = false;
            },
        },
    );

const issueForm = useForm({ status: 'issued' });

const issue = () =>
    issueForm.put(
        `/${props.company.slug}/credit-notes/${props.credit_note.id}`,
        { onError: showError },
    );

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },

    { title: props.company.name, href: `/${props.company.slug}` },

    { title: 'Credit Notes', href: `/${props.company.slug}/credit-notes` },

    { title: props.credit_note.credit_note_number },
]);

const formatDate = (dateString: string) => {
    return formatSharedDateTime(dateString, { mode: 'date' });
};

/**

 * A credit note is a document, and until now it was a Card with a big green

 * number in it. Green said "good news"; a credit note is a reduction, and the

 * grammar reserves colour for whether something needs attention, never for

 * which direction money moved. It goes through LedgerDocument for the same

 * reason invoices and bills do -- it is the same sheet with different words.

 *

 * There are no line items to show: a credit note carries one amount and the

 * reason for it, so the reason IS the line. Quantities and unit rates are

 * suppressed rather than padded with a quantity of one.

 */

const issuer = computed(() => props.company.letterhead);

const creditTo = computed(() => ({
    name: props.credit_note.customer.name,

    email: props.credit_note.customer.email,
}));

const documentDates = computed(() =>
    [
        { label: 'Issued', value: formatDate(props.credit_note.credit_date) },

        {
            label: 'Against',

            value: props.credit_note.invoice?.invoice_number ?? null,
        },
    ].filter((date): date is { label: string; value: string } =>
        Boolean(date.value),
    ),
);

const documentLines = computed<DocumentLine[]>(() => [
    {
        description: props.credit_note.reason || 'Credit',

        amount: props.credit_note.amount,
    },
]);

const overprint = computed(() => {
    if (['void', 'cancelled', 'reversed'].includes(props.credit_note.status))
        return 'Void';

    if (props.credit_note.status === 'draft') return 'Draft';

    return null;
});

const isEditable = computed(() => {
    return (
        props.credit_note.status === 'draft' &&
        !props.credit_note.transaction_id &&
        !props.credit_note.period_discount
    );
});
</script>

<template>
    <Head :title="`Credit Note ${credit_note.credit_note_number}`" />

    <PageShell
        :title="`Credit Note ${credit_note.credit_note_number}`"
        :breadcrumbs="breadcrumbs"
    >
        <template #actions>
            <Button
                variant="outline"
                @click="router.get(`/${company.slug}/credit-notes`)"
            >
                <ArrowLeft class="mr-2 h-4 w-4" />

                Back
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
                                `/${company.slug}/credit-notes/${credit_note.id}/edit`,
                            )
                        "
                        :disabled="!isEditable"
                    >
                        <Edit class="mr-2 h-4 w-4" />

                        Edit
                    </DropdownMenuItem>

                    <DropdownMenuItem
                        @click="
                            router.post(
                                `/${company.slug}/credit-notes/${credit_note.id}/send`,
                            )
                        "
                        v-if="credit_note.status === 'draft'"
                    >
                        <Send class="mr-2 h-4 w-4" />

                        Send
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </template>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Main Credit Note Content -->

            <div class="space-y-6 lg:col-span-2">
                <LedgerDocument
                    doc-type="Credit Note"
                    :doc-number="credit_note.credit_note_number"
                    :issuer="issuer"
                    :bill-to="creditTo"
                    bill-to-label="Credit to"
                    :dates="documentDates"
                    :lines="documentLines"
                    grand-total-label="Credit total"
                    :grand-total-amount="credit_note.amount"
                    :currency="
                        credit_note.currency ?? credit_note.base_currency
                    "
                    locale="en-PK"
                    :overprint="overprint"
                    :stamp="stamp"
                    :show-quantity="false"
                >
                    <template v-if="credit_note.terms" #terms>
                        <p dir="auto">{{ credit_note.terms }}</p>
                    </template>
                </LedgerDocument>

                <!-- Notes -->

                <Card v-if="credit_note.notes" variant="detail">
                    <CardHeader>
                        <CardTitle>Internal Notes</CardTitle>
                    </CardHeader>

                    <CardContent>
                        <p class="text-sm">{{ credit_note.notes }}</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Sidebar -->

            <div class="space-y-6">
                <!-- Credit Note Summary -->

                <Card variant="detail">
                    <CardHeader>
                        <CardTitle>Credit Note Summary</CardTitle>
                    </CardHeader>

                    <CardContent class="space-y-3">
                        <div class="flex justify-between">
                            <span>Credit amount</span>

                            <MoneyText
                                :amount="credit_note.amount"
                                :currency="
                                    credit_note.currency ??
                                    credit_note.base_currency
                                "
                                locale="en-PK"
                            />
                        </div>

                        <div class="flex justify-between text-sm">
                            <span>Status</span>

                            <StatusBadge :status="credit_note.status" explain />
                        </div>

                        <div
                            v-if="credit_note.invoice"
                            class="flex justify-between text-sm"
                        >
                            <span>Applied to:</span>

                            <span>{{
                                credit_note.invoice.invoice_number
                            }}</span>
                        </div>

                        <Separator />

                        <div
                            class="flex justify-between text-sm text-muted-foreground"
                        >
                            <span>Credit Date:</span>

                            <span>{{
                                formatDate(credit_note.credit_date)
                            }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Timeline -->

                <Card variant="detail">
                    <CardHeader>
                        <CardTitle>Timeline</CardTitle>
                    </CardHeader>

                    <CardContent class="space-y-3">
                        <div class="flex justify-between text-sm">
                            <span>Created:</span>

                            <span>{{
                                formatDate(credit_note.created_at)
                            }}</span>
                        </div>

                        <div
                            v-if="credit_note.sent_at"
                            class="flex justify-between text-sm"
                        >
                            <span>Sent:</span>

                            <span>{{ formatDate(credit_note.sent_at) }}</span>
                        </div>

                        <div
                            v-if="credit_note.posted_at"
                            class="flex justify-between text-sm"
                        >
                            <span>Posted:</span>

                            <span>{{ formatDate(credit_note.posted_at) }}</span>
                        </div>

                        <div
                            v-if="credit_note.voided_at"
                            class="flex justify-between text-sm"
                        >
                            <span>Voided:</span>

                            <span>{{ formatDate(credit_note.voided_at) }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Actions -->

                <Card variant="detail">
                    <CardHeader>
                        <CardTitle>Actions</CardTitle>
                    </CardHeader>

                    <CardContent class="space-y-2">
                        <Button
                            class="w-full"
                            variant="outline"
                            @click="
                                router.get(
                                    `/${company.slug}/credit-notes/${credit_note.id}/edit`,
                                )
                            "
                            :disabled="!isEditable"
                        >
                            <Edit class="mr-2 h-4 w-4" />

                            Edit Credit Note
                        </Button>

                        <Button
                            v-if="credit_note.status === 'draft'"
                            class="w-full"
                            variant="outline"
                            :disabled="issueForm.processing"
                            @click="issue"
                        >
                            <Send class="mr-2 h-4 w-4" />

                            {{
                                issueForm.processing
                                    ? 'Issuing…'
                                    : 'Issue credit note'
                            }}
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>

        <section
            v-if="credit_note.period_discount"
            class="mt-6 space-y-4 rounded-lg border border-border p-5"
        >
            <h2 class="font-medium">Period discount calculation</h2>

            <p class="text-sm text-muted-foreground">
                {{ credit_note.period_discount.from }} to
                {{ credit_note.period_discount.to }} ·
                {{ periodSources?.total ?? 0 }} invoices
            </p>

            <p class="text-sm">
                <template v-if="credit_note.period_discount.method === 'fixed'"
                    >Manual discount on
                </template>

                <template
                    v-else-if="credit_note.period_discount.method === 'percent'"
                    >{{ credit_note.period_discount.value }}% of
                </template>

                <template v-else
                    >{{ credit_note.period_discount.litres }} litres ×
                    {{ credit_note.period_discount.value }} per litre. Eligible
                    fuel sales:
                </template>

                <MoneyText
                    :amount="credit_note.period_discount.eligible_amount"
                    :currency="
                        credit_note.currency ?? credit_note.base_currency
                    "
                />
            </p>

            <div class="divide-y divide-border">
                <div
                    v-for="source in periodSources?.data ?? []"
                    :key="source.id"
                    class="flex justify-between gap-3 py-2 text-sm"
                >
                    <Link
                        :href="`/${company.slug}/invoices/${source.id}`"
                        class="underline underline-offset-4"
                        >{{ source.number }} · {{ source.date }}</Link
                    >

                    <MoneyText
                        :amount="source.gross"
                        :currency="
                            credit_note.currency ?? credit_note.base_currency
                        "
                    />
                </div>
            </div>

            <div class="flex gap-3 text-sm">
                <Link
                    v-if="periodSources?.prev_page_url"
                    :href="periodSources.prev_page_url"
                    preserve-scroll
                    >Previous invoices</Link
                ><Link
                    v-if="periodSources?.next_page_url"
                    :href="periodSources.next_page_url"
                    preserve-scroll
                    >More invoices</Link
                >
            </div>
        </section>

        <section
            v-if="
                canApply &&
                (availableCredit ?? 0) > 0 &&
                credit_note.status !== 'draft' &&
                credit_note.status !== 'void'
            "
            class="mt-6 space-y-4 rounded-lg border border-border p-5"
        >
            <h2 class="font-medium">
                Available customer credit:
                <MoneyText
                    :amount="availableCredit ?? 0"
                    :currency="
                        credit_note.currency ?? credit_note.base_currency
                    "
                />
            </h2>

            <form
                v-if="openInvoices?.length"
                class="grid items-end gap-3 sm:grid-cols-4"
                @submit.prevent="apply"
            >
                <div class="space-y-2">
                    <Label for="credit-apply-invoice">Invoice</Label
                    ><Select v-model="applyForm.invoice_id"
                        ><SelectTrigger id="credit-apply-invoice"
                            ><SelectValue
                                placeholder="Choose invoice" /></SelectTrigger
                        ><SelectContent
                            ><SelectItem
                                v-for="invoice in openInvoices"
                                :key="invoice.id"
                                :value="invoice.id"
                                >{{ invoice.invoice_number }} ·
                                {{ invoice.balance }}</SelectItem
                            ></SelectContent
                        ></Select
                    ><InputError :message="applyForm.errors.invoice_id" />
                </div>

                <div class="space-y-2">
                    <Label for="credit-apply-amount">Amount</Label
                    ><Input
                        id="credit-apply-amount"
                        v-model.number="applyForm.amount"
                        type="number"
                        min="0.01"
                        :max="availableCredit"
                        step="0.01"
                    /><InputError :message="applyForm.errors.amount" />
                </div>

                <div class="space-y-2">
                    <Label for="credit-apply-date">Apply on</Label
                    ><Input
                        id="credit-apply-date"
                        v-model="applyForm.applied_at"
                        type="date"
                        :min="credit_note.credit_date"
                    /><InputError :message="applyForm.errors.applied_at" />
                </div>

                <Button
                    type="submit"
                    :disabled="applyForm.processing || !applyForm.invoice_id"
                    >{{
                        applyForm.processing ? 'Applying…' : 'Apply credit'
                    }}</Button
                >
            </form>

            <p v-else class="text-sm text-muted-foreground">
                This credit stays available for a future invoice.
            </p>
        </section>

        <section
            v-if="
                canVoid &&
                credit_note.status !== 'draft' &&
                credit_note.status !== 'void'
            "
            class="mt-6"
        >
            <Button v-if="!voidOpen" variant="outline" @click="voidOpen = true"
                >Void
                {{
                    credit_note.period_discount
                        ? 'period discount'
                        : 'credit note'
                }}</Button
            >

            <form
                v-else
                class="max-w-xl space-y-3 rounded-lg border border-border p-4"
                @submit.prevent="voidCredit"
            >
                <p class="text-sm text-muted-foreground">
                    {{
                        credit_note.period_discount
                            ? 'This restores the invoices settled by this discount and reverses its journal.'
                            : 'This reverses the credit note. Applied ordinary credits must be unallocated first.'
                    }}
                </p>

                <Label for="void-credit-reason">Reason for voiding</Label
                ><Input
                    id="void-credit-reason"
                    v-model="voidForm.reason"
                /><InputError :message="voidForm.errors.reason" />

                <div class="flex gap-2">
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="voidForm.processing"
                        >{{
                            voidForm.processing ? 'Voiding…' : 'Confirm void'
                        }}</Button
                    ><Button
                        type="button"
                        variant="outline"
                        :disabled="voidForm.processing"
                        @click="voidOpen = false"
                        >Cancel</Button
                    >
                </div>
            </form>
        </section>
    </PageShell>
</template>
