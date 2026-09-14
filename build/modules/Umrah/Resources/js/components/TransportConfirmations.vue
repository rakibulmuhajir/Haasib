<script setup lang="ts">
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
type Entry = {
    status: string;
    reference?: string;
    internal_note?: string;
    cancellation_reason?: string;
    supplier_acknowledgement?: string;
    updated_by_name: string;
    updated_at: string;
};
export type TransportConfirmationRow = {
    id: string;
    label: string;
    scheduled_at?: string | null;
    status: string;
    reference?: string | null;
    internal_note?: string | null;
    revision: string;
    version: number;
    history?: Entry[];
};
const props = defineProps<{
    company: string;
    group: string;
    rows: TransportConfirmationRow[];
    canManage: boolean;
    canReviewRefunds?: boolean;
}>();
const active = ref<TransportConfirmationRow | null>(null);
const labels: Record<string, string> = {
    pending: 'Pending',
    confirmed: 'Confirmed',
    cancelled: 'Cancelled — replacement needed',
    reconfirm: 'Needs reconfirmation',
    not_recorded: 'Not recorded',
};
const form = useForm({
    booking_id: '',
    revision: '',
    version: 0,
    status: 'pending',
    reference: '',
    internal_note: '',
    cancellation_reason: '',
    supplier_acknowledgement: '',
});
function edit(row: TransportConfirmationRow, cancel = false) {
    form.clearErrors();
    Object.assign(form, {
        booking_id: row.id,
        revision: row.revision,
        version: row.version,
        status: cancel
            ? 'cancelled'
            : row.status === 'confirmed'
              ? 'confirmed'
              : 'pending',
        reference: row.status === 'cancelled' ? '' : row.reference || '',
        internal_note: row.internal_note || '',
        cancellation_reason: '',
        supplier_acknowledgement: '',
    });
    active.value = row;
}
function save() {
    form.post(
        `/${props.company}/umrah/groups/${props.group}/transport-confirmations`,
        {
            preserveScroll: true,
            onSuccess: (page) => {
                if (
                    !(page.props.flash as { error?: string } | undefined)?.error
                )
                    active.value = null;
            },
            onError: () =>
                toast.error(
                    'Transport booking was not saved. Check the highlighted errors.',
                ),
        },
    );
}
</script>
<template>
    <section
        v-if="rows.length"
        id="transport-confirmations"
        class="my-4 border bg-background"
    >
        <div class="border-b px-4 py-3">
            <h2 class="font-semibold">Transport booking confirmations</h2>
            <p class="text-sm text-muted-foreground">
                Provider acceptance is separate from vehicle dispatch and
                payment. No charges change here.
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th class="p-3">Booking / route</th>
                        <th class="p-3">Scheduled locally</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Reference</th>
                        <th v-if="canManage" class="p-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="row in rows" :key="row.id"
                        ><tr class="border-b">
                            <td class="p-3">{{ row.label }}</td>
                            <td class="p-3">
                                {{ row.scheduled_at || 'Not scheduled' }}
                            </td>
                            <td class="p-3">{{ labels[row.status] }}</td>
                            <td class="p-3">{{ row.reference || '—' }}</td>
                            <td v-if="canManage" class="p-3">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="edit(row)"
                                    >{{
                                        row.status === 'cancelled'
                                            ? 'Record replacement'
                                            : 'Update'
                                    }}</Button
                                ><Button
                                    v-if="row.status !== 'cancelled'"
                                    size="sm"
                                    variant="ghost"
                                    @click="edit(row, true)"
                                    >Cancel booking</Button
                                >
                            </td>
                        </tr>
                        <tr v-if="row.history?.length">
                            <td
                                :colspan="canManage ? 5 : 4"
                                class="p-3 text-xs"
                            >
                                <div
                                    v-if="row.status === 'cancelled'"
                                    class="mb-2 flex flex-wrap items-center gap-2 text-destructive"
                                >
                                    <span>
                                        Booking cancelled; replacement and any
                                        refund are separate follow-up work.
                                    </span>
                                    <Button
                                        v-if="canReviewRefunds"
                                        size="sm"
                                        variant="outline"
                                        @click="
                                            router.get(
                                                `/${company}/umrah/refunds`,
                                            )
                                        "
                                    >Review refunds</Button>
                                </div>
                                <p v-if="row.internal_note">
                                    {{ row.internal_note }}
                                </p>
                                <details>
                                    <summary class="cursor-pointer">
                                        History ({{ row.history.length }})
                                    </summary>
                                    <p
                                        v-for="(entry, i) in row.history"
                                        :key="i"
                                        class="mt-2"
                                    >
                                        {{ labels[entry.status] }} ·
                                        {{ entry.updated_by_name }} ·
                                        {{ entry.updated_at }} ·
                                        {{ entry.reference }}<br />{{
                                            entry.internal_note
                                        }}<br
                                            v-if="entry.cancellation_reason"
                                        />{{ entry.cancellation_reason }}
                                        {{ entry.supplier_acknowledgement }}
                                    </p>
                                </details>
                            </td>
                        </tr></template
                    >
                </tbody>
            </table>
        </div>
        <Dialog
            :open="!!active"
            @update:open="
                (open) => {
                    if (!open && !form.processing) active = null;
                }
            "
            ><DialogContent
                ><DialogHeader
                    ><DialogTitle>{{
                        form.status === 'cancelled'
                            ? 'Cancel transport booking'
                            : 'Update transport confirmation'
                    }}</DialogTitle
                    ><DialogDescription>{{
                        active?.label
                    }}</DialogDescription></DialogHeader
                >
                <form class="space-y-4" @submit.prevent="save">
                    <div v-if="form.status !== 'cancelled'">
                        <Label for="transport-confirm-status">Status</Label
                        ><Select v-model="form.status"
                            ><SelectTrigger id="transport-confirm-status"
                                ><SelectValue /></SelectTrigger
                            ><SelectContent
                                ><SelectItem value="pending">Pending</SelectItem
                                ><SelectItem value="confirmed"
                                    >Confirmed</SelectItem
                                ></SelectContent
                            ></Select
                        >
                    </div>
                    <div>
                        <Label for="transport-confirm-reference"
                            >Booking reference (optional)</Label
                        ><Input
                            id="transport-confirm-reference"
                            v-model="form.reference"
                            maxlength="100"
                        />
                    </div>
                    <div>
                        <Label for="transport-confirm-note"
                            >Internal note — staff only</Label
                        ><Textarea
                            id="transport-confirm-note"
                            v-model="form.internal_note"
                            maxlength="1000"
                        />
                    </div>
                    <template v-if="form.status === 'cancelled'"
                        ><p class="text-sm text-destructive">
                            This does not refund money or remove the need for
                            transport. Review replacement transport and any
                            fees/refund separately.
                        </p>
                        <div>
                            <Label for="transport-cancel-reason"
                                >Cancellation reason</Label
                            ><Textarea
                                id="transport-cancel-reason"
                                v-model="form.cancellation_reason"
                                maxlength="500"
                            />
                        </div>
                        <div>
                            <Label for="transport-cancel-ack"
                                >Supplier acknowledgement / cancellation
                                reference</Label
                            ><Input
                                id="transport-cancel-ack"
                                v-model="form.supplier_acknowledgement"
                                maxlength="500"
                            /></div
                    ></template>
                    <p
                        v-for="(error, key) in form.errors"
                        :key="key"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ error }}
                    </p>
                    <DialogFooter
                        ><Button
                            type="button"
                            variant="outline"
                            :disabled="form.processing"
                            @click="active = null"
                            >Close</Button
                        ><Button type="submit" :disabled="form.processing">{{
                            form.processing ? 'Saving…' : 'Save booking status'
                        }}</Button></DialogFooter
                    >
                </form>
            </DialogContent></Dialog
        >
    </section>
</template>
