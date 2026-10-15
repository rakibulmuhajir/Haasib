<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import { Button } from '@/components/ui/button';
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
import { useFormFeedback } from '@/composables/useFormFeedback';
import { useLexicon } from '@/composables/useLexicon';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    company: { slug: string; name: string; base_currency: string };
    customer: { id: string; name: string };
    filters: {
        from: string;
        to: string;
        credit_date: string;
        method: string;
        value: number;
        notes?: string;
    };
    preview: {
        amount: number;
        invoice_count: number;
        excluded_count: number;
        eligible_amount: number;
        litres: number;
        applied: number;
        remaining_credit: number;
        error: string | null;
        token: string;
        closing_balance: number;
    };
}>();
const { showError } = useFormFeedback();
const { t } = useLexicon();
const form = useForm({ ...props.filters, preview_token: props.preview.token });
const ready = ref(true);
const previewing = ref(false);
watch(
    () => [form.from, form.to, form.credit_date, form.method, form.value],
    () => {
        ready.value = false;
    },
);
watch(
    () => props.preview.token,
    () => {
        form.preview_token = props.preview.token;
        ready.value = true;
    },
);
const endpoint = computed(
    () =>
        `/${props.company.slug}/customers/${props.customer.id}/period-discount`,
);
const previewDiscount = () =>
    router.get(
        endpoint.value,
        {
            from: form.from,
            to: form.to,
            credit_date: form.credit_date,
            method: form.method,
            value: form.value,
            notes: form.notes,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onStart: () => {
                previewing.value = true;
            },
            onSuccess: () => {
                form.preview_token = props.preview.token;
                ready.value = true;
                form.clearErrors();
            },
            onError: showError,
            onFinish: () => {
                previewing.value = false;
            },
        },
    );
const post = () =>
    form.post(endpoint.value, {
        onError: (errors) => {
            ready.value = false;
            showError(errors);
        },
    });
const breadcrumbs = computed(() => [
    { title: 'Customers', href: `/${props.company.slug}/customers` },
    {
        title: props.customer.name,
        href: `/${props.company.slug}/customers/${props.customer.id}`,
    },
    { title: 'Period discount' },
]);
</script>

<template>
    <Head :title="t('customerDiscountPeriodTitle')" />
    <PageShell
        :title="t('customerDiscountPeriodTitle')"
        :description="customer.name"
        :breadcrumbs="breadcrumbs"
    >
        <form
            novalidate
            class="mx-auto max-w-3xl space-y-6"
            @submit.prevent="post"
        >
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="space-y-2">
                    <Label for="discount-from">Period from</Label
                    ><Input
                        id="discount-from"
                        v-model="form.from"
                        type="date"
                    /><InputError :message="form.errors.from" />
                </div>
                <div class="space-y-2">
                    <Label for="discount-to">Period to</Label
                    ><Input
                        id="discount-to"
                        v-model="form.to"
                        type="date"
                    /><InputError :message="form.errors.to" />
                </div>
                <div class="space-y-2">
                    <Label for="discount-date">Posting date</Label
                    ><Input
                        id="discount-date"
                        v-model="form.credit_date"
                        type="date"
                        :min="form.to"
                    /><InputError :message="form.errors.credit_date" />
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label for="discount-method">Calculate discount</Label>
                    <Select v-model="form.method"
                        ><SelectTrigger id="discount-method"
                            ><SelectValue /></SelectTrigger
                        ><SelectContent>
                            <SelectItem value="fixed">Manual amount</SelectItem
                            ><SelectItem value="percent"
                                >Percentage of eligible sales</SelectItem
                            ><SelectItem value="per_litre"
                                >Amount per litre of fuel</SelectItem
                            >
                        </SelectContent></Select
                    ><InputError :message="form.errors.method" />
                </div>
                <div class="space-y-2">
                    <Label for="discount-value">{{
                        form.method === 'percent'
                            ? 'Percentage'
                            : form.method === 'per_litre'
                              ? 'Discount per litre'
                              : 'Discount amount'
                    }}</Label>
                    <Input
                        id="discount-value"
                        v-model.number="form.value"
                        type="number"
                        min="0.01"
                        :max="form.method === 'percent' ? 100 : undefined"
                        step="0.01"
                    /><InputError :message="form.errors.value" />
                </div>
            </div>
            <div class="space-y-2">
                <Label for="discount-notes">Note (optional)</Label
                ><Textarea id="discount-notes" v-model="form.notes" rows="2" />
            </div>
            <Button
                type="button"
                variant="outline"
                :disabled="previewing || form.processing"
                @click="previewDiscount"
                >{{
                    previewing ? 'Calculating…' : t('customerDiscountPreview')
                }}</Button
            >
            <div
                class="space-y-3 border-y border-border py-5"
                :class="{ 'opacity-50': !ready }"
            >
                <p class="text-sm text-muted-foreground">
                    {{ preview.invoice_count }} eligible invoices ·
                    {{ preview.litres.toLocaleString() }} litres ·
                    {{ preview.excluded_count }} excluded
                </p>
                <div class="flex justify-between gap-3">
                    <span>Eligible sales</span
                    ><MoneyText
                        :amount="preview.eligible_amount"
                        :currency="company.base_currency"
                    />
                </div>
                <div class="flex justify-between gap-3 font-medium">
                    <span>Discount credit</span
                    ><MoneyText
                        :amount="preview.amount"
                        :currency="company.base_currency"
                    />
                </div>
                <div class="flex justify-between gap-3 text-sm">
                    <span>Applied to unpaid invoices</span
                    ><MoneyText
                        :amount="preview.applied"
                        :currency="company.base_currency"
                    />
                </div>
                <div
                    v-if="preview.remaining_credit > 0"
                    class="flex justify-between gap-3 text-sm"
                >
                    <span>Kept as customer credit</span
                    ><MoneyText
                        :amount="preview.remaining_credit"
                        :currency="company.base_currency"
                    />
                </div>
                <div class="flex justify-between gap-3 border-t pt-3">
                    <span>Customer balance after discount</span
                    ><MoneyText
                        :amount="preview.closing_balance - preview.amount"
                        :currency="company.base_currency"
                    />
                </div>
            </div>
            <p class="text-sm text-muted-foreground">
                Sales already discounted, credited, taxed, or recorded in
                another currency are excluded. Posting creates one separate
                credit; original invoices and fuel quantities stay unchanged.
            </p>
            <p v-if="!ready" class="text-sm text-muted-foreground">
                Preview again to confirm the current figures.
            </p>
            <p v-if="preview.error" class="text-sm text-destructive">
                {{ preview.error }}
            </p>
            <InputError
                v-for="(error, field) in form.errors"
                :key="field"
                :message="error"
            />
            <div class="flex justify-end gap-2">
                <Button type="button" variant="outline" as-child
                    ><Link :href="`/${company.slug}/customers/${customer.id}`"
                        >Cancel</Link
                    ></Button
                >
                <Button
                    type="submit"
                    :disabled="
                        !ready ||
                        !!preview.error ||
                        preview.amount <= 0 ||
                        form.processing ||
                        previewing
                    "
                    >{{
                        form.processing ? 'Posting…' : t('customerDiscountPost')
                    }}</Button
                >
            </div>
        </form>
    </PageShell>
</template>
