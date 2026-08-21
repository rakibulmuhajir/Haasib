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
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Banknote, CheckCircle2, PiggyBank, Undo2, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string };
    refund: any;
    canApprove: boolean;
    canCancel: boolean;
    canSettle: boolean;
    settlementAccounts: Array<{ id: string; code: string; name: string }>;
}>();

const isVendorRefund = computed(() => props.refund.party_type !== 'agent');

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

const settleOpen = ref(false);
const settleForm = useForm({
    settlement_method: 'cash',
    account_id: '',
    date: new Date().toISOString().slice(0, 10),
});
const submitSettle = () =>
    settleForm
        .transform((data) => ({
            ...data,
            account_id: data.settlement_method === 'cash' ? data.account_id : null,
            date: data.settlement_method === 'cash' ? data.date : null,
        }))
        .post(`/${props.company.slug}/umrah/refunds/${props.refund.id}/settle`, {
            preserveScroll: true,
            onSuccess: () => {
                settleOpen.value = false;
            },
            onError: () => toast.error('Failed to settle refund'),
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
            <Button v-if="canSettle" @click="settleOpen = true">
                <PiggyBank class="mr-2 h-4 w-4" />Settle
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

        <Card v-if="refund.settled_at" variant="detail">
            <CardHeader><CardTitle>Settlement</CardTitle></CardHeader>
            <CardContent class="text-sm">
                {{ refund.settlement_method === 'credit' ? 'Kept as credit' : 'Paid back' }}
                by {{ refund.settled_by?.name || '—' }} · <DateTimeText :value="refund.settled_at" />
            </CardContent>
        </Card>

        <Dialog v-model:open="approveOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Approve Refund</DialogTitle>
                    <DialogDescription>
                        This confirms the amount owed and moves the refund to Approved. It does not pay anything or post to the books — that happens when the refund is settled.
                    </DialogDescription>
                </DialogHeader>
                <form novalidate class="space-y-4" @submit.prevent="submitApprove">
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
                <form novalidate class="space-y-4" @submit.prevent="submitReject">
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
                <form novalidate class="space-y-4" @submit.prevent="submitCancel">
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

        <Dialog v-model:open="settleOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Settle Refund</DialogTitle>
                    <DialogDescription>
                        <template v-if="isVendorRefund">This posts the cash received from the vendor.</template>
                        <template v-else>Pay it back, or keep it as credit the agent can spend on a future group.</template>
                    </DialogDescription>
                </DialogHeader>
                <form novalidate class="space-y-4" @submit.prevent="submitSettle">
                    <div v-if="!isVendorRefund" class="space-y-2">
                        <Label>How is this being settled?</Label>
                        <Select v-model="settleForm.settlement_method">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cash"><Banknote class="mr-2 inline h-4 w-4" />Pay it back</SelectItem>
                                <SelectItem value="credit"><PiggyBank class="mr-2 inline h-4 w-4" />Keep as credit</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="settleForm.errors.settlement_method" class="text-xs text-destructive">{{ settleForm.errors.settlement_method }}</p>
                    </div>

                    <template v-if="settleForm.settlement_method === 'cash'">
                        <div class="space-y-2">
                            <Label>Account</Label>
                            <Select v-model="settleForm.account_id">
                                <SelectTrigger><SelectValue placeholder="Select account" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="account in settlementAccounts" :key="account.id" :value="account.id">
                                        {{ account.code }} · {{ account.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="settleForm.errors.account_id" class="text-xs text-destructive">{{ settleForm.errors.account_id }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label>Date</Label>
                            <Input v-model="settleForm.date" type="date" required />
                            <p v-if="settleForm.errors.date" class="text-xs text-destructive">{{ settleForm.errors.date }}</p>
                        </div>
                    </template>

                    <p class="text-xs text-muted-foreground">
                        This posts to the books and cannot be edited afterwards.
                    </p>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="settleOpen = false">Not Yet</Button>
                        <Button
                            type="submit"
                            :disabled="settleForm.processing || (settleForm.settlement_method === 'cash' && !settleForm.account_id)"
                        >
                            Settle Refund
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </PageShell>
</template>
