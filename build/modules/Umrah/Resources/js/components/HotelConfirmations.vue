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
    brn?: string | null;
    confirmation_number?: string | null;
    internal_note?: string | null;
    updated_at?: string | null;
    updated_by_name?: string | null;
    cancellation_reason?: string | null;
    supplier_acknowledgement?: string | null;
};
export type HotelConfirmationRow = Entry & {
    stay_id: string;
    revision: string;
    version: number;
    hotel_name: string;
    city: string;
    check_in_date?: string | null;
    check_out_date?: string | null;
    room_type?: string | null;
    room_count?: number | null;
    status_label: string;
    history?: Entry[];
};
const props = defineProps<{
    company: string;
    voucher: string;
    rows: HotelConfirmationRow[];
    canManage: boolean;
    canReviewRefunds?: boolean;
}>();
const editing = ref<HotelConfirmationRow | null>(null);
const form = useForm({
    stay_id: '',
    revision: '',
    version: 0,
    status: 'pending',
    brn: '',
    confirmation_number: '',
    internal_note: '',
    cancellation_reason: '',
    supplier_acknowledgement: '',
});
function edit(row: HotelConfirmationRow) {
    form.clearErrors();
    Object.assign(form, {
        stay_id: row.stay_id,
        revision: row.revision,
        version: row.version,
        status: row.status === 'confirmed' ? 'confirmed' : 'pending',
        brn: row.status === 'cancelled' ? '' : row.brn || '',
        confirmation_number:
            row.status === 'cancelled' ? '' : row.confirmation_number || '',
        internal_note: row.internal_note || '',
        cancellation_reason: '',
        supplier_acknowledgement: '',
    });
    editing.value = row;
}
function cancelBooking(row: HotelConfirmationRow) {
    edit(row);
    form.status = 'cancelled';
}
function save() {
    form.post(
        `/${props.company}/umrah/vouchers/${props.voucher}/hotel-confirmations`,
        {
            preserveScroll: true,
            onSuccess: (page) => {
                if (
                    !(page.props.flash as { error?: string } | undefined)?.error
                )
                    editing.value = null;
            },
            onError: () =>
                toast.error(
                    'Check the highlighted fields. The hotel confirmation was not saved.',
                ),
        },
    );
}
</script>

<template>
    <section
        v-if="rows.length"
        id="hotel-confirmations"
        class="border bg-background"
    >
        <div class="border-b px-4 py-3">
            <h2 class="font-semibold">Hotel confirmations</h2>
            <p class="text-sm text-muted-foreground">
                Booking status for each stay. These updates do not change
                charges or the printed voucher.
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead
                    class="border-b bg-muted/40 text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="px-3 py-2">Hotel / city</th>
                        <th class="px-3 py-2">Check-in → checkout</th>
                        <th class="px-3 py-2">Rooms</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">BRN</th>
                        <th class="px-3 py-2">Confirmation no.</th>
                        <th class="px-3 py-2">Updated</th>
                        <th v-if="canManage" class="px-3 py-2">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="row in rows" :key="row.stay_id">
                        <tr class="border-b">
                            <td class="px-3 py-2">
                                <div class="font-medium">
                                    {{ row.hotel_name || 'Hotel not selected' }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ row.city }}
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                {{ row.check_in_date || '—' }} →
                                {{ row.check_out_date || '—' }}
                            </td>
                            <td class="px-3 py-2">
                                {{ row.room_count || '—' }} {{ row.room_type }}
                            </td>
                            <td
                                class="px-3 py-2"
                                :class="
                                    ['pending', 'reconfirm'].includes(
                                        row.status,
                                    )
                                        ? 'text-amber-700 dark:text-amber-400'
                                        : ''
                                "
                            >
                                {{ row.status_label }}
                            </td>
                            <td class="px-3 py-2">{{ row.brn || '—' }}</td>
                            <td class="px-3 py-2">
                                {{ row.confirmation_number || '—' }}
                            </td>
                            <td class="px-3 py-2 text-xs">
                                {{ row.updated_by_name }}
                                <div>
                                    {{
                                        row.updated_at
                                            ? new Date(
                                                  row.updated_at,
                                              ).toLocaleString()
                                            : '—'
                                    }}
                                </div>
                            </td>
                            <td v-if="canManage" class="px-3 py-2">
                                <Button
                                    v-if="row.status !== 'agent_arranged'"
                                    variant="outline"
                                    size="sm"
                                    :aria-label="`Update confirmation for ${row.hotel_name}`"
                                    @click="edit(row)"
                                    >{{
                                        row.status === 'cancelled'
                                            ? 'Record replacement'
                                            : 'Update'
                                    }}</Button
                                >
                                <Button
                                    v-if="
                                        ![
                                            'agent_arranged',
                                            'cancelled',
                                        ].includes(row.status)
                                    "
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="`Cancel booking for ${row.hotel_name}`"
                                    @click="cancelBooking(row)"
                                    >Cancel booking</Button
                                >
                            </td>
                        </tr>
                        <tr
                            v-if="row.internal_note || row.history?.length"
                            class="border-b"
                        >
                            <td
                                :colspan="canManage ? 8 : 7"
                                class="px-3 py-2 text-xs"
                            >
                                <p
                                    v-if="row.status === 'cancelled'"
                                    class="mb-2 text-destructive"
                                >
                                    Financial review needed: assess supplier
                                    charges and any refund separately. No money
                                    has been returned by this cancellation.
                                </p>
                                <Button
                                    v-if="
                                        row.status === 'cancelled' &&
                                        canReviewRefunds
                                    "
                                    size="sm"
                                    variant="outline"
                                    @click="
                                        router.get(`/${company}/umrah/refunds`)
                                    "
                                    >Review refunds</Button
                                >
                                <p
                                    v-if="row.internal_note"
                                    class="mb-1 whitespace-pre-wrap"
                                >
                                    Internal note: {{ row.internal_note }}
                                </p>
                                <details v-if="row.history?.length">
                                    <summary
                                        class="cursor-pointer text-muted-foreground"
                                    >
                                        Confirmation history ({{
                                            row.history.length
                                        }})
                                    </summary>
                                    <div
                                        v-for="(entry, index) in row.history"
                                        :key="index"
                                        class="mt-2 border-l-2 pl-2"
                                    >
                                        <div>
                                            {{ entry.status }} ·
                                            {{ entry.updated_by_name }} ·
                                            {{
                                                entry.updated_at
                                                    ? new Date(
                                                          entry.updated_at,
                                                      ).toLocaleString()
                                                    : ''
                                            }}
                                        </div>
                                        <p v-if="entry.cancellation_reason">
                                            Cancellation:
                                            {{ entry.cancellation_reason }} ·
                                            Supplier:
                                            {{ entry.supplier_acknowledgement }}
                                        </p>
                                        <div>
                                            BRN: {{ entry.brn || '—' }} ·
                                            Confirmation:
                                            {{
                                                entry.confirmation_number || '—'
                                            }}
                                        </div>
                                        <p class="whitespace-pre-wrap">
                                            {{ entry.internal_note }}
                                        </p>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <Dialog
            :open="!!editing"
            @update:open="
                (open) => {
                    if (!open && !form.processing) editing = null;
                }
            "
        >
            <DialogContent
                ><DialogHeader
                    ><DialogTitle>{{
                        form.status === 'cancelled'
                            ? 'Cancel hotel booking'
                            : 'Update hotel confirmation'
                    }}</DialogTitle
                    ><DialogDescription
                        >{{ editing?.hotel_name }} ·
                        {{ editing?.check_in_date }} →
                        {{ editing?.check_out_date }}</DialogDescription
                    ></DialogHeader
                >
                <form class="space-y-4" @submit.prevent="save">
                    <div v-if="form.status !== 'cancelled'" class="space-y-1">
                        <Label for="hotel-confirmation-status">Status</Label
                        ><Select v-model="form.status"
                            ><SelectTrigger id="hotel-confirmation-status"
                                ><SelectValue /></SelectTrigger
                            ><SelectContent
                                ><SelectItem value="pending">Pending</SelectItem
                                ><SelectItem value="confirmed"
                                    >Confirmed</SelectItem
                                ></SelectContent
                            ></Select
                        >
                    </div>
                    <div v-if="form.status === 'cancelled'" class="space-y-3">
                        <p class="text-sm text-destructive">
                            This records a supplier booking cancellation, not a
                            refund. The journey still needs a replacement hotel.
                            Charges, payments and the passenger copy remain
                            unchanged until separately reviewed.
                        </p>
                        <div>
                            <Label for="hotel-cancellation-reason"
                                >Cancellation reason</Label
                            ><Textarea
                                id="hotel-cancellation-reason"
                                v-model="form.cancellation_reason"
                                maxlength="500"
                            />
                        </div>
                        <div>
                            <Label for="hotel-supplier-acknowledgement"
                                >Supplier acknowledgement / cancellation
                                reference</Label
                            ><Input
                                id="hotel-supplier-acknowledgement"
                                v-model="form.supplier_acknowledgement"
                                maxlength="500"
                            />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label for="hotel-brn">BRN (optional)</Label
                            ><Input
                                id="hotel-brn"
                                v-model="form.brn"
                                maxlength="100"
                            />
                        </div>
                        <div class="space-y-1">
                            <Label for="hotel-confirmation-number"
                                >Confirmation no. (optional)</Label
                            ><Input
                                id="hotel-confirmation-number"
                                v-model="form.confirmation_number"
                                maxlength="100"
                            />
                        </div>
                    </div>
                    <div class="space-y-1">
                        <Label for="hotel-internal-note">Internal note</Label
                        ><Textarea
                            id="hotel-internal-note"
                            v-model="form.internal_note"
                            maxlength="1000"
                        />
                        <p class="text-xs text-muted-foreground">
                            Company staff only. Never printed or shared with
                            agents.
                        </p>
                    </div>
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
                            @click="editing = null"
                            >Cancel</Button
                        ><Button type="submit" :disabled="form.processing">{{
                            form.processing ? 'Saving…' : 'Save confirmation'
                        }}</Button></DialogFooter
                    >
                </form>
            </DialogContent>
        </Dialog>
    </section>
</template>
