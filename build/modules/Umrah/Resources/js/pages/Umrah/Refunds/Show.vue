<script setup lang="ts">
import DateTimeText from '@/components/DateTimeText.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { CheckCircle2, Undo2, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    refund: any;
    canApprove: boolean;
    canCancel: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Refunds', href: `/${props.company.slug}/umrah/refunds` },
    { title: props.refund.refund_number, href: `/${props.company.slug}/umrah/refunds/${props.refund.id}` },
];

const reviewedByLabel = computed(() =>
    props.refund.status === 'rejected' ? 'Rejected by' : 'Approved by',
);
const remarksCardTitle = computed(() =>
    props.refund.status === 'rejected' ? 'Rejection reason' : 'Review remarks',
);

const approveOpen = ref(false);
const approveForm = useForm({ review_remarks: '' });
const submitApprove = () =>
    approveForm.post(`/${props.company.slug}/umrah/refunds/${props.refund.id}/approve`, {
        preserveScroll: true,
        onSuccess: () => {
            approveOpen.value = false;
        },
        onError: () => toast.error('Failed to approve refund'),
    });

const rejectOpen = ref(false);
const rejectForm = useForm({ review_remarks: '' });
const submitReject = () =>
    rejectForm.post(`/${props.company.slug}/umrah/refunds/${props.refund.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => {
            rejectOpen.value = false;
        },
        onError: () => toast.error('Failed to reject refund'),
    });

const cancelOpen = ref(false);
const cancelForm = useForm({ cancellation_reason: '' });
const submitCancel = () =>
    cancelForm.post(`/${props.company.slug}/umrah/refunds/${props.refund.id}/cancel`, {
        preserveScroll: true,
        onSuccess: () => {
            cancelOpen.value = false;
        },
        onError: () => toast.error('Failed to cancel refund'),
    });
</script>

<template>
    <Head :title="refund.refund_number" />
    <PageShell
        :title="refund.refund_number"
        description="Refund record. Nothing moves and nothing posts until this is settled."
        :breadcrumbs="breadcrumbs"
        :icon="Undo2"
    >
        <template #actions>
            <Button v-if="canApprove" variant="outline" @click="rejectOpen = true">
                <XCircle class="mr-2 h-4 w-4" />Reject
            </Button>
            <Button v-if="canApprove" @click="approveOpen = true">
                <CheckCircle2 class="mr-2 h-4 w-4" />Approve
            </Button>
            <Button v-if="canCancel" variant="destructive" @click="cancelOpen = true">
                <XCircle class="mr-2 h-4 w-4" />Cancel
            </Button>
        </template>

        <div class="grid gap-4 md:grid-cols-4">
            <Card variant="detail"><CardHeader><CardTitle>Requested</CardTitle></CardHeader><CardContent><DateTimeText :value="refund.requested_at" mode="date" /></CardContent></Card>
            <Card variant="detail"><CardHeader><CardTitle>Party</CardTitle></CardHeader><CardContent class="font-medium">{{ refund.party_name || '—' }}</CardContent></Card>
            <Card variant="detail"><CardHeader><CardTitle>Service</CardTitle></CardHeader><CardContent>{{ refund.service }}</CardContent></Card>
            <Card variant="detail"><CardHeader><CardTitle>Status</CardTitle></CardHeader><CardContent><StatusBadge :status="refund.status" /></CardContent></Card>
        </div>

        <Card variant="detail">
            <CardHeader><CardTitle>Amount</CardTitle></CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-3">
                <div><div class="text-sm text-muted-foreground">Amount owed</div><MoneyText :amount="refund.amount" :currency="refund.currency" /></div>
                <div><div class="text-sm text-muted-foreground">Exchange rate</div><div>{{ refund.exchange_rate || 1 }}</div></div>
                <div><div class="text-sm text-muted-foreground">Base amount</div><MoneyText :amount="refund.base_amount" :currency="refund.base_currency" /></div>
                <div v-if="refund.group"><div class="text-sm text-muted-foreground">Group</div><div>{{ refund.group.group_number }} · {{ refund.group.name }}</div></div>
                <div class="md:col-span-2"><div class="text-sm text-muted-foreground">Reason</div><div class="whitespace-pre-wrap">{{ refund.reason }}</div></div>
            </CardContent>
        </Card>

        <Card variant="detail">
            <CardHeader><CardTitle>Request</CardTitle></CardHeader>
            <CardContent class="space-y-1 text-sm">
                <div>Requested by {{ refund.requested_by?.name || '—' }} · <DateTimeText :value="refund.requested_at" /></div>
                <div v-if="refund.reviewed_at">
                    {{ reviewedByLabel }} {{ refund.reviewed_by?.name || '—' }} · <DateTimeText :value="refund.reviewed_at" />
                </div>
                <div v-if="refund.cancelled_at">
                    Cancelled by {{ refund.cancelled_by?.name || '—' }} · <DateTimeText :value="refund.cancelled_at" />
                </div>
            </CardContent>
        </Card>

        <Card
            v-if="refund.review_remarks"
            variant="detail"
            :class="refund.status === 'rejected' ? 'border-l-4 border-l-status-critical' : ''"
        >
            <CardHeader><CardTitle>{{ remarksCardTitle }}</CardTitle></CardHeader>
            <CardContent>
                <p class="text-sm whitespace-pre-wrap">{{ refund.review_remarks }}</p>
            </CardContent>
        </Card>

        <Card v-if="refund.cancellation_reason" variant="detail">
            <CardHeader><CardTitle>Cancellation reason</CardTitle></CardHeader>
            <CardContent><p class="text-sm whitespace-pre-wrap">{{ refund.cancellation_reason }}</p></CardContent>
        </Card>

        <Card v-if="refund.settled_payment" variant="detail">
            <CardHeader><CardTitle>Settlement</CardTitle></CardHeader>
            <CardContent class="text-sm">Paid via {{ refund.settled_payment.payment_number }}</CardContent>
        </Card>

        <Dialog v-model:open="approveOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Approve Refund</DialogTitle>
                    <DialogDescription>
                        This confirms the amount owed and moves the refund to Approved. It does not pay anything or post to the books — that happens when the refund is settled.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitApprove">
                    <div class="space-y-2">
                        <Textarea v-model="approveForm.review_remarks" placeholder="Remarks (optional)" />
                        <p v-if="approveForm.errors.review_remarks" class="text-xs text-destructive">{{ approveForm.errors.review_remarks }}</p>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="approveOpen = false">Keep as Requested</Button>
                        <Button type="submit" :disabled="approveForm.processing">Approve</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="rejectOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reject Refund</DialogTitle>
                    <DialogDescription>The requester will see your remarks.</DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitReject">
                    <div class="space-y-2">
                        <Textarea v-model="rejectForm.review_remarks" required placeholder="Reason (required)" />
                        <p v-if="rejectForm.errors.review_remarks" class="text-xs text-destructive">{{ rejectForm.errors.review_remarks }}</p>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="rejectOpen = false">Keep as Requested</Button>
                        <Button type="submit" variant="destructive" :disabled="rejectForm.processing || rejectForm.review_remarks.trim().length < 5">Reject</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="cancelOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel Refund</DialogTitle>
                    <DialogDescription>This withdraws an accepted refund before it is settled.</DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitCancel">
                    <div class="space-y-2">
                        <Textarea v-model="cancelForm.cancellation_reason" required placeholder="Reason (required)" />
                        <p v-if="cancelForm.errors.cancellation_reason" class="text-xs text-destructive">{{ cancelForm.errors.cancellation_reason }}</p>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="cancelOpen = false">Keep Refund</Button>
                        <Button type="submit" variant="destructive" :disabled="cancelForm.processing || cancelForm.cancellation_reason.trim().length < 5">Cancel Refund</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </PageShell>
</template>
