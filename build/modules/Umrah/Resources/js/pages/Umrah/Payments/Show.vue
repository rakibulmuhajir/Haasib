<script setup lang="ts">
import DateTimeText from '@/components/DateTimeText.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Download, ReceiptText, RotateCcw } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{ company: { name: string; slug: string; base_currency: string }; payment: any; canReverse: boolean }>();
const reverseOpen = ref(false);
const form = useForm({ reason: '' });
const party = computed(() => props.payment.agent?.name || props.payment.visa_vendor?.name || props.payment.transport_vendor?.name || props.payment.hotel_vendor?.name || '-');
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Payments', href: `/${props.company.slug}/umrah/payments` },
    { title: props.payment.payment_number, href: `/${props.company.slug}/umrah/payments/${props.payment.id}` },
];
const reversePayment = () => form.post(`/${props.company.slug}/umrah/payments/${props.payment.id}/reverse`, {
    preserveScroll: true,
    onSuccess: () => { reverseOpen.value = false; form.reset(); toast.success('Payment reversed'); },
    onError: () => toast.error('Failed to reverse payment'),
});
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
            <Card><CardHeader><CardTitle class="text-sm">Status</CardTitle></CardHeader><CardContent><Badge :variant="payment.status === 'reversed' ? 'destructive' : 'secondary'">{{ payment.status }}</Badge></CardContent></Card>
        </div>

        <Card>
            <CardHeader><CardTitle>Amount</CardTitle></CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-3">
                <div><div class="text-sm text-muted-foreground">Recorded amount</div><MoneyText :amount="payment.amount" :currency="payment.currency" /></div>
                <div><div class="text-sm text-muted-foreground">Exchange rate</div><div>{{ payment.exchange_rate || 1 }}</div></div>
                <div><div class="text-sm text-muted-foreground">Base amount</div><MoneyText :amount="payment.base_amount" :currency="payment.base_currency" /></div>
                <div><div class="text-sm text-muted-foreground">Account</div><div>{{ payment.account?.code }} {{ payment.account?.name || 'Default account' }}</div></div>
                <div><div class="text-sm text-muted-foreground">Method</div><div>{{ payment.method }}</div></div>
                <div><div class="text-sm text-muted-foreground">Reference</div><div>{{ payment.reference || '-' }}</div></div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Allocations</CardTitle></CardHeader>
            <CardContent class="p-0"><Table><TableHeader><TableRow><TableHead>Group</TableHead><TableHead>Amount</TableHead><TableHead>Status</TableHead><TableHead>Reason</TableHead></TableRow></TableHeader><TableBody>
                <TableRow v-for="allocation in payment.all_allocations" :key="allocation.id">
                    <TableCell>{{ allocation.group?.group_number }} · {{ allocation.group?.name }}</TableCell>
                    <TableCell><MoneyText :amount="allocation.base_amount" :currency="payment.base_currency" /></TableCell>
                    <TableCell><Badge :variant="allocation.reversed_at ? 'destructive' : 'secondary'">{{ allocation.reversed_at ? 'Reversed' : 'Posted' }}</Badge></TableCell>
                    <TableCell>{{ allocation.reversal_reason || '-' }}</TableCell>
                </TableRow>
            </TableBody></Table></CardContent>
        </Card>

        <Card v-if="payment.reversed_at"><CardHeader><CardTitle>Reversal</CardTitle></CardHeader><CardContent><DateTimeText :value="payment.reversed_at" /> · {{ payment.reversal_reason }}</CardContent></Card>

        <Dialog v-model:open="reverseOpen"><DialogContent><DialogHeader><DialogTitle>Reverse Payment</DialogTitle></DialogHeader>
            <div class="space-y-2"><Label for="reason">Reason</Label><Textarea id="reason" v-model="form.reason" required /><p v-if="form.errors.reason" class="text-sm text-destructive">{{ form.errors.reason }}</p></div>
            <DialogFooter><Button variant="outline" @click="reverseOpen = false">Keep Payment</Button><Button variant="destructive" :disabled="form.processing || form.reason.trim().length < 5" @click="reversePayment">Reverse Payment</Button></DialogFooter>
        </DialogContent></Dialog>
    </PageShell>
</template>
