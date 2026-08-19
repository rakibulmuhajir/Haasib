<script setup lang="ts">
import DateTimeText from '@/components/DateTimeText.vue';
import MoneyText from '@/components/MoneyText.vue';
import LedgerRegister from '@/components/LedgerRegister.vue';
import PageShell from '@/components/PageShell.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Download, ReceiptText, RotateCcw } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{ company: { name: string; slug: string; base_currency: string }; payment: any; canReverse: boolean }>();
const reverseOpen = ref(false);
const form = useForm({ reason: '' });
const party = computed(() => props.payment.agent?.name || props.payment.visa_vendor?.name || props.payment.transport_vendor?.name || props.payment.hotel_vendor?.name || '—');
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Payments', href: `/${props.company.slug}/umrah/payments` },
    { title: props.payment.payment_number, href: `/${props.company.slug}/umrah/payments/${props.payment.id}` },
];
const reversePayment = () => form.post(`/${props.company.slug}/umrah/payments/${props.payment.id}/reverse`, {
    preserveScroll: true,
    onSuccess: () => { reverseOpen.value = false; form.reset(); },
    onError: () => toast.error('Failed to reverse payment'),
});
/**
 * An allocation is either standing or it has been reversed, and reversed is the
 * same state here as it is on the payment itself -- so it goes through the one
 * vocabulary and comes out struck, the way a cancelled line is struck on paper.
 */
const allocationColumns = [
    { key: 'group', label: 'Group', kind: 'text' as const },
    { key: 'base_amount', label: 'Amount', kind: 'amount' as const },
    { key: 'status', label: 'Status', kind: 'status' as const },
    { key: 'reason', label: 'Reason', kind: 'text' as const },
];

const downloadReceipt = () => window.location.assign(`/${props.company.slug}/umrah/payments/${props.payment.id}/pdf`);
</script>

<template>
    <Head :title="payment.payment_number" />
    <PageShell :title="payment.payment_number" description="Payment record and allocation history." :breadcrumbs="breadcrumbs" :icon="ReceiptText">
        <template #actions>
            <Button variant="outline" @click="downloadReceipt"><Download class="mr-2 h-4 w-4" />Receipt PDF</Button>
            <Button v-if="canReverse" variant="destructive" @click="reverseOpen = true"><RotateCcw class="mr-2 h-4 w-4" />Reverse</Button>
        </template>

        <div class="grid gap-4 md:grid-cols-4">
            <Card><CardHeader><CardTitle class="text-sm">Date</CardTitle></CardHeader><CardContent><DateTimeText :value="payment.payment_date" mode="date" /></CardContent></Card>
            <Card><CardHeader><CardTitle class="text-sm">Party</CardTitle></CardHeader><CardContent class="font-medium">{{ party }}</CardContent></Card>
            <Card><CardHeader><CardTitle class="text-sm">Direction</CardTitle></CardHeader><CardContent>{{ payment.direction === 'received' ? 'Received' : 'Paid' }}</CardContent></Card>
            <Card><CardHeader><CardTitle class="text-sm">Status</CardTitle></CardHeader><CardContent><StatusBadge :status="payment.status" /></CardContent></Card>
        </div>

        <Card>
            <CardHeader><CardTitle>Amount</CardTitle></CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-3">
                <div><div class="text-sm text-muted-foreground">Recorded amount</div><MoneyText :amount="payment.amount" :currency="payment.currency" /></div>
                <div><div class="text-sm text-muted-foreground">Exchange rate</div><div>{{ payment.exchange_rate || 1 }}</div></div>
                <div><div class="text-sm text-muted-foreground">Base amount</div><MoneyText :amount="payment.base_amount" :currency="payment.base_currency" /></div>
                <div><div class="text-sm text-muted-foreground">Account</div><div>{{ payment.account?.code }} {{ payment.account?.name || 'Default account' }}</div></div>
                <div><div class="text-sm text-muted-foreground">Method</div><div>{{ payment.method }}</div></div>
                <div><div class="text-sm text-muted-foreground">Reference</div><div>{{ payment.reference || '—' }}</div></div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Allocations</CardTitle></CardHeader>
            <CardContent class="p-0">
                <LedgerRegister :data="payment.all_allocations" :columns="allocationColumns">
                    <template #empty>Nothing allocated yet.</template>

                    <template #cell-group="{ row }">{{ row.group?.group_number }} · {{ row.group?.name }}</template>

                    <template #cell-base_amount="{ row }">
                        <MoneyText :amount="row.base_amount" :currency="payment.base_currency" />
                    </template>

                    <template #cell-status="{ row }">
                        <StatusBadge :status="row.reversed_at ? 'reversed' : 'posted'" />
                    </template>

                    <template #cell-reason="{ row }">{{ row.reversal_reason || '—' }}</template>
                </LedgerRegister>
            </CardContent>
        </Card>

        <Card v-if="payment.reversed_at"><CardHeader><CardTitle>Reversal</CardTitle></CardHeader><CardContent><DateTimeText :value="payment.reversed_at" /> · {{ payment.reversal_reason }}</CardContent></Card>

        <Dialog v-model:open="reverseOpen"><DialogContent><DialogHeader><DialogTitle>Reverse Payment</DialogTitle><DialogDescription>This creates an opposite accounting entry. The original payment remains in the audit trail.</DialogDescription></DialogHeader>
            <div class="space-y-2"><Label for="reason">Reason</Label><Textarea id="reason" v-model="form.reason" required /><p v-if="form.errors.reason" class="text-sm text-destructive">{{ form.errors.reason }}</p></div>
            <DialogFooter><Button variant="outline" @click="reverseOpen = false">Keep Payment</Button><Button variant="destructive" :disabled="form.processing || form.reason.trim().length < 5" @click="reversePayment">Reverse Payment</Button></DialogFooter>
        </DialogContent></Dialog>
    </PageShell>
</template>
