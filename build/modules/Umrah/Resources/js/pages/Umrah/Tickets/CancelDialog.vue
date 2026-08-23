<script setup lang="ts">
import MoneyText from '@/components/MoneyText.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AmountInput from '@/components/forms/AmountInput.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    companySlug: string;
    currency: string;
    ticket: { id: string; passenger_name: string } | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const nextIdempotencyKey = () =>
    typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID()
        : `cancel-${Math.random().toString(36).slice(2)}`;

const form = useForm({
    cancellation_date: new Date().toISOString().slice(0, 10),
    buyer_returns_amount: null as number | null,
    supplier_returns_amount: null as number | null,
    reason: '',
    idempotency_key: nextIdempotencyKey(),
});

// A fresh key each time the dialog opens for a (possibly different)
// ticket -- reopening after a cancel must never replay a stale key.
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            form.reset();
            form.idempotency_key = nextIdempotencyKey();
            form.cancellation_date = new Date().toISOString().slice(0, 10);
        }
    },
);

// buyer back minus supplier back, arithmetic visible as the two numbers
// are typed. A negative result -- the supplier returned more than the
// buyer got -- is a gain, shown as ink with a minus sign, never red:
// direction is not severity (MoneyText's default tone already does this).
const cancellationCost = computed(
    () => Number(form.buyer_returns_amount || 0) - Number(form.supplier_returns_amount || 0),
);

const isOpen = computed({
    get: () => props.open,
    set: (value: boolean) => emit('update:open', value),
});

const close = () => emit('update:open', false);

const submit = () => {
    if (!props.ticket) {
        return;
    }

    form.post(`/${props.companySlug}/umrah/tickets/${props.ticket.id}/cancel`, {
        preserveScroll: true,
        onSuccess: () => close(),
    });
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Cancel Ticket</DialogTitle>
                <DialogDescription>
                    <template v-if="ticket">
                        Cancelling {{ ticket.passenger_name }}'s ticket raises a credit note
                        to the buyer, a vendor credit against the supplier, or both. This
                        cannot be undone.
                    </template>
                </DialogDescription>
            </DialogHeader>

            <form novalidate class="space-y-4" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label>Buyer gets back</Label>
                    <AmountInput
                        v-model="form.buyer_returns_amount"
                        :currency="currency"
                        :error="form.errors.buyer_returns_amount"
                    />
                    <p v-if="form.errors.buyer_returns_amount" class="text-xs text-destructive">
                        {{ form.errors.buyer_returns_amount }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label>Supplier returns</Label>
                    <AmountInput
                        v-model="form.supplier_returns_amount"
                        :currency="currency"
                        :error="form.errors.supplier_returns_amount"
                    />
                    <p v-if="form.errors.supplier_returns_amount" class="text-xs text-destructive">
                        {{ form.errors.supplier_returns_amount }}
                    </p>
                </div>

                <div class="flex items-center justify-between rounded-md border bg-muted/30 p-3">
                    <span class="text-sm text-muted-foreground">Buyer back &minus; supplier back</span>
                    <MoneyText :amount="cancellationCost" :currency="currency" class="font-semibold" />
                </div>

                <div class="space-y-2">
                    <Label for="cancel-date">Cancellation date</Label>
                    <Input
                        id="cancel-date"
                        v-model="form.cancellation_date"
                        type="date"
                        :aria-invalid="Boolean(form.errors.cancellation_date)"
                    />
                    <p v-if="form.errors.cancellation_date" class="text-xs text-destructive">
                        {{ form.errors.cancellation_date }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="cancel-reason">Reason</Label>
                    <Textarea id="cancel-reason" v-model="form.reason" placeholder="Optional" />
                    <p v-if="form.errors.reason" class="text-xs text-destructive">
                        {{ form.errors.reason }}
                    </p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" :disabled="form.processing" @click="close">
                        Keep Ticket
                    </Button>
                    <Button type="submit" variant="destructive" :disabled="form.processing">
                        {{ form.processing ? 'Cancelling...' : 'Cancel Ticket' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
