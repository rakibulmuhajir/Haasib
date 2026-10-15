<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useFormFeedback } from '@/composables/useFormFeedback';
import { useLexicon } from '@/composables/useLexicon';
import { Link, useForm } from '@inertiajs/vue3';

export interface DiscountPolicy {
    override: 'invoice' | 'period' | null;
    effective: 'invoice' | 'period';
    company_default: 'invoice' | 'period';
    can_update: boolean;
    can_create: boolean;
}
const props = defineProps<{
    companySlug: string;
    customerId: string;
    policy: DiscountPolicy;
    from?: string;
    to?: string;
}>();
const form = useForm({ discount_timing: props.policy.override ?? 'inherit' });
const { showError } = useFormFeedback();
const { t } = useLexicon();
const save = () =>
    form
        .transform((data) => ({
            discount_timing:
                data.discount_timing === 'inherit'
                    ? null
                    : data.discount_timing,
        }))
        .patch(`/${props.companySlug}/customers/${props.customerId}`, {
            preserveScroll: true,
            onError: showError,
        });
</script>

<template>
    <div class="space-y-3 rounded-lg border border-border p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <Label for="customer-discount-timing">{{
                    t('customerDiscountTiming')
                }}</Label>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        policy.effective === 'period'
                            ? 'Keep invoices at their original amounts; post one discount at period end.'
                            : 'Apply agreed discounts on each new invoice.'
                    }}
                    Saved invoices stay unchanged.
                </p>
            </div>
            <Button
                v-if="policy.can_create"
                variant="outline"
                size="sm"
                as-child
            >
                <Link
                    :href="`/${companySlug}/customers/${customerId}/period-discount`"
                    :data="{ from, to }"
                    >{{ t('customerDiscountAddPeriod') }}</Link
                >
            </Button>
        </div>
        <form
            v-if="policy.can_update"
            class="flex flex-wrap items-center gap-2"
            @submit.prevent="save"
        >
            <Select v-model="form.discount_timing">
                <SelectTrigger id="customer-discount-timing" class="w-64"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="inherit"
                        >Company default ({{
                            policy.company_default === 'period'
                                ? 'Period end'
                                : 'Per invoice'
                        }})</SelectItem
                    >
                    <SelectItem value="invoice">{{
                        t('customerDiscountPerInvoice')
                    }}</SelectItem>
                    <SelectItem value="period">{{
                        t('customerDiscountPeriodEnd')
                    }}</SelectItem>
                </SelectContent>
            </Select>
            <Button
                type="submit"
                variant="outline"
                size="sm"
                :disabled="form.processing"
                >{{ form.processing ? 'Saving…' : 'Save' }}</Button
            >
            <InputError :message="form.errors.discount_timing" />
        </form>
    </div>
</template>
