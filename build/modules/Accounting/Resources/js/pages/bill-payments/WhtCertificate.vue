<script setup lang="ts">
import LedgerDocument from '@/components/LedgerDocument.vue';
import type { DocumentIssuer, DocumentLine, DocumentTotal } from '@/components/LedgerDocument.vue';
import PageShell from '@/components/PageShell.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/datetime';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed } from 'vue';

interface Certificate {
    payment_id: string;
    number: string;
    date: string | null;
    reference: string | null;
    section: string | null;
    rate: number | null;
    filer_status: string | null;
    gross: number;
    wht: number;
    net: number;
    currency: string;
    voided: boolean;
    vendor: { name: string | null; tax_id: string | null; email: string | null; lines: string[] };
}

const props = defineProps<{
    company: { id: string; name: string; slug: string; base_currency: string; letterhead: DocumentIssuer };
    certificate: Certificate;
}>();

const printPage = () => window.print();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Bill Payments', href: `/${props.company.slug}/bill-payments` },
    { title: props.certificate.number, href: `/${props.company.slug}/bill-payments/${props.certificate.payment_id}` },
    { title: 'Withholding tax certificate' },
]);

const rateText = computed(() => {
    const c = props.certificate;
    if (c.rate === null) return null;
    return `Rate ${c.rate}%${c.filer_status === 'non_filer' ? ' (non-filer)' : ''}`;
});

const lines = computed<DocumentLine[]>(() => [
    {
        description: props.certificate.section ? `Payment, section ${props.certificate.section}` : 'Payment',
        detail: rateText.value ?? undefined,
        amount: props.certificate.gross,
    },
]);

const totals = computed<DocumentTotal[]>(() => [
    { label: 'Paid to vendor', amount: props.certificate.net, muted: true },
]);

const dates = computed(() =>
    [
        { label: 'Payment date', value: props.certificate.date ? formatDateTime(props.certificate.date, { mode: 'date' }) : null },
        { label: 'Reference', value: props.certificate.reference },
    ].filter((d): d is { label: string; value: string } => Boolean(d.value)),
);

const vendor = computed(() => ({
    name: props.certificate.vendor.name ?? '',
    lines: props.certificate.vendor.lines,
    email: props.certificate.vendor.email ?? undefined,
    taxId: props.certificate.vendor.tax_id ?? undefined,
    taxIdLabel: 'NTN / CNIC',
}));
</script>

<template>
    <Head :title="`Withholding tax certificate ${certificate.number}`" />
    <PageShell title="Withholding tax certificate" :breadcrumbs="breadcrumbs">
        <template #actions>
            <Button variant="outline" @click="router.get(`/${company.slug}/bill-payments/${certificate.payment_id}`)">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back
            </Button>
            <Button @click="printPage">
                <Printer class="mr-2 h-4 w-4" />
                Print
            </Button>
        </template>

        <LedgerDocument
            doc-type="Withholding tax deduction certificate"
            :doc-number="certificate.number"
            :issuer="company.letterhead"
            :bill-to="vendor"
            bill-to-label="Tax deducted from"
            :dates="dates"
            :lines="lines"
            :totals="totals"
            grand-total-label="Tax withheld"
            :grand-total-amount="certificate.wht"
            :currency="certificate.currency"
            locale="en-PK"
            :show-quantity="false"
            :overprint="certificate.voided ? 'VOID' : null"
        />
    </PageShell>
</template>
