<script setup lang="ts">
import DateTimeText from '@/components/DateTimeText.vue';
import MoneyText from '@/components/MoneyText.vue';
import LedgerRegister from '@/components/LedgerRegister.vue';
import PageShell from '@/components/PageShell.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { BreadcrumbItem } from '@/types';
import AllocatePaymentDialog from './components/AllocatePaymentDialog.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { CheckCircle2, Download, ReceiptText, RotateCcw, Split, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    payment: any;
    allocationGroups: Array<{
        id: string;
        party_key: string;
        group_number: string;
        name: string;
        outstanding_amount: number;
    }>;
    canReverse: boolean;
    canReview: boolean;
    canAllocate: boolean;
}>();
const reverseOpen = ref(false);
const allocateOpen = ref(false);

/**
 * What is left of this payment to put against a group. Reversed
 * allocations handed their money back, so they are not spent.
 */
const unallocated = computed(() =>
    Math.max(
        Number(props.payment.base_amount) -
            (props.payment.all_allocations || [])
                .filter((allocation: any) => !allocation.reversed_at)
                .reduce((sum: number, allocation: any) => sum + Number(allocation.base_amount), 0),
        0,
    ),
);
const form = useForm({ reason: '' });
const reviewOpen = ref(false);
const reviewDecision = ref<'approve' | 'reject'>('approve');
const reviewForm = useForm({
    decision: 'approve',
    review_remarks: '',
    payment_date: props.payment.payment_date ? String(props.payment.payment_date).slice(0, 10) : '',
    amount: String(props.payment.amount ?? ''),
    currency: props.payment.currency,
    exchange_rate: props.payment.exchange_rate ? String(props.payment.exchange_rate) : '',
    method: props.payment.method,
    reference: props.payment.reference || '',
});
const openReview = (decision: 'approve' | 'reject') => {
    reviewDecision.value = decision;
    reviewForm.decision = decision;
    reviewForm.review_remarks = '';
    reviewOpen.value = true;
};
const submitReview = () =>
    reviewForm
        .transform((data) => ({
            ...data,
            amount: Number(data.amount || 0),
            exchange_rate:
                data.currency === props.company.base_currency
                    ? null
                    : Number(data.exchange_rate || 0),
        }))
        .post(`/${props.company.slug}/umrah/payments/${props.payment.id}/review`, {
            preserveScroll: true,
            onSuccess: () => {
                reviewOpen.value = false;
            },
            onError: () => toast.error('Failed to record review'),
        });
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
            <Button v-if="canAllocate && unallocated > 0.01" variant="outline" @click="allocateOpen = true"><Split class="mr-2 h-4 w-4" />Allocate</Button>
            <Button v-if="canReverse" variant="destructive" @click="reverseOpen = true"><RotateCcw class="mr-2 h-4 w-4" />Reverse</Button>
            <Button v-if="canReview" variant="outline" @click="openReview('reject')"><XCircle class="mr-2 h-4 w-4" />Reject</Button>
            <Button v-if="canReview" @click="openReview('approve')"><CheckCircle2 class="mr-2 h-4 w-4" />Approve</Button>
        </template>

        <div class="grid gap-4 md:grid-cols-4">
            <Card variant="detail"><CardHeader><CardTitle>Date</CardTitle></CardHeader><CardContent><DateTimeText :value="payment.payment_date" mode="date" /></CardContent></Card>
            <Card variant="detail"><CardHeader><CardTitle>Party</CardTitle></CardHeader><CardContent class="font-medium">{{ party }}</CardContent></Card>
            <Card variant="detail"><CardHeader><CardTitle>Direction</CardTitle></CardHeader><CardContent>{{ payment.direction === 'received' ? 'Received' : 'Paid' }}</CardContent></Card>
            <Card variant="detail"><CardHeader><CardTitle>Status</CardTitle></CardHeader><CardContent><StatusBadge :status="payment.status" /></CardContent></Card>
        </div>

        <Card variant="detail">
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

        <Card variant="register">
            <CardHeader><CardTitle>Allocations</CardTitle></CardHeader>
            <CardContent>
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

        <Card v-if="payment.reversed_at" variant="detail"><CardHeader><CardTitle>Reversal</CardTitle></CardHeader><CardContent><DateTimeText :value="payment.reversed_at" /> · {{ payment.reversal_reason }}</CardContent></Card>

        <Card v-if="payment.submitted_at" variant="detail">
            <CardHeader><CardTitle>Submission</CardTitle></CardHeader>
            <CardContent class="space-y-1 text-sm">
                <div>Submitted by {{ payment.submitted_by?.name || '—' }} · <DateTimeText :value="payment.submitted_at" /></div>
                <div v-if="payment.reviewed_at">
                    Reviewed by {{ payment.reviewed_by?.name || '—' }} · <DateTimeText :value="payment.reviewed_at" />
                </div>
            </CardContent>
        </Card>

        <Card
            v-if="payment.review_remarks"
            variant="detail"
            :class="payment.status === 'rejected' ? 'border-l-4 border-l-status-critical' : ''"
        >
            <CardHeader><CardTitle>Reviewer remarks</CardTitle></CardHeader>
            <CardContent class="space-y-1">
                <p class="text-sm whitespace-pre-wrap">{{ payment.review_remarks }}</p>
                <p class="text-xs text-muted-foreground">
                    {{ payment.reviewed_by?.name || 'Reviewer' }} ·
                    <DateTimeText :value="payment.reviewed_at" />
                </p>
            </CardContent>
        </Card>

        <Dialog v-model:open="reviewOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ reviewDecision === 'approve' ? 'Approve Payment' : 'Reject Payment' }}</DialogTitle>
                    <DialogDescription>
                        {{
                            reviewDecision === 'approve'
                                ? 'Approving books this payment to the ledger. Correct any details before approving if needed.'
                                : 'Rejecting posts nothing. The agent will see your remarks.'
                        }}
                    </DialogDescription>
                </DialogHeader>
                <form novalidate class="space-y-4" @submit.prevent="submitReview">
                    <template v-if="reviewDecision === 'approve'">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-2">
                                <Label>Date</Label>
                                <Input v-model="reviewForm.payment_date" type="date" />
                            </div>
                            <div class="space-y-2">
                                <Label>Method</Label>
                                <Input v-model="reviewForm.method" type="text" />
                            </div>
                            <div class="space-y-2">
                                <Label>Amount</Label>
                                <Input v-model="reviewForm.amount" type="number" min="0.01" step="0.000001" />
                            </div>
                            <div class="space-y-2">
                                <Label>Currency</Label>
                                <Input v-model="reviewForm.currency" type="text" maxlength="3" class="uppercase" />
                            </div>
                            <div v-if="reviewForm.currency !== company.base_currency" class="col-span-2 space-y-2">
                                <Label>Exchange rate to {{ company.base_currency }}</Label>
                                <Input v-model="reviewForm.exchange_rate" type="number" min="0.00000001" step="0.00000001" />
                                <p v-if="reviewForm.errors.exchange_rate" class="text-xs text-destructive">{{ reviewForm.errors.exchange_rate }}</p>
                            </div>
                            <div class="col-span-2 space-y-2">
                                <Label>Reference</Label>
                                <Input v-model="reviewForm.reference" type="text" />
                            </div>
                        </div>
                        <p v-if="reviewForm.errors.amount" class="text-xs text-destructive">{{ reviewForm.errors.amount }}</p>
                        <p v-if="reviewForm.errors.currency" class="text-xs text-destructive">{{ reviewForm.errors.currency }}</p>
                    </template>
                    <div class="space-y-2">
                        <Label for="review_remarks">Remarks{{ reviewDecision === 'reject' ? ' (required)' : '' }}</Label>
                        <Textarea id="review_remarks" v-model="reviewForm.review_remarks" :required="reviewDecision === 'reject'" />
                        <p v-if="reviewForm.errors.review_remarks" class="text-xs text-destructive">{{ reviewForm.errors.review_remarks }}</p>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="reviewOpen = false">Cancel</Button>
                        <Button type="submit" :variant="reviewDecision === 'reject' ? 'destructive' : 'default'" :disabled="reviewForm.processing">
                            {{ reviewDecision === 'approve' ? 'Approve & Post' : 'Reject' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <AllocatePaymentDialog
            v-model:open="allocateOpen"
            :company="company"
            :payment="payment"
            :allocation-groups="allocationGroups"
        />

        <Dialog v-model:open="reverseOpen"><DialogContent><DialogHeader><DialogTitle>Reverse Payment</DialogTitle><DialogDescription>This creates an opposite accounting entry. The original payment remains in the audit trail.</DialogDescription></DialogHeader>
            <div class="space-y-2"><Label for="reason">Reason</Label><Textarea id="reason" v-model="form.reason" required /><p v-if="form.errors.reason" class="text-sm text-destructive">{{ form.errors.reason }}</p></div>
            <DialogFooter><Button variant="outline" @click="reverseOpen = false">Keep Payment</Button><Button variant="destructive" :disabled="form.processing || form.reason.trim().length < 5" @click="reversePayment">Reverse Payment</Button></DialogFooter>
        </DialogContent></Dialog>
    </PageShell>
</template>
