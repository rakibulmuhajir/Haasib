<script setup lang="ts">
/**
 * Putting a payment's remaining credit against a group.
 *
 * Lived on the payments register alone, which meant opening a payment to
 * read it was the one place you could not act on it. Extracted so the
 * register and the payment's own page ask the same question the same way
 * rather than growing two answers to it.
 */
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { useForm } from '@inertiajs/vue3'
import { computed, watch } from 'vue'

type AllocationGroup = {
    id: string
    group_number: string
    name: string
    party_key: string
    outstanding_amount: number | string
}

const props = defineProps<{
    open: boolean
    company: { slug: string; base_currency: string }
    payment: any | null
    allocationGroups: AllocationGroup[]
}>()

const emit = defineEmits<{ 'update:open': [boolean]; allocated: [] }>()

const form = useForm({ visa_group_id: 'none', base_amount: '' })

/** Reversed allocations gave the money back, so they are not spent. */
const allocations = computed(() =>
    ((props.payment?.allocations ?? props.payment?.all_allocations ?? []) as any[]).filter(
        (allocation) => !allocation.reversed_at,
    ),
)

const available = computed(() =>
    props.payment
        ? Math.max(
              Number(props.payment.base_amount) -
                  allocations.value.reduce((sum, allocation) => sum + Number(allocation.base_amount), 0),
              0,
          )
        : 0,
)

const partyKey = computed(() => {
    const payment = props.payment
    if (!payment) return ''
    if (payment.agent_id) return `agent:${payment.agent_id}`
    if (payment.visa_vendor_id) return `visa:${payment.visa_vendor_id}`
    if (payment.transport_vendor_id) return `transport:${payment.transport_vendor_id}`
    return payment.hotel_vendor_id ? `hotel:${payment.hotel_vendor_id}` : ''
})

/**
 * A group this payment has already been put against still belongs here.
 *
 * It used to be struck off the list, from when a group's charge could not
 * move after it was built. An adjustment moves it, and the group that
 * then owes more is exactly the one an agent's credit is wanted for.
 * What is outstanding is the question, and the server answers it.
 */
const groups = computed(() =>
    props.allocationGroups.filter((group) => group.party_key === partyKey.value),
)

const selectedGroup = computed(() => groups.value.find((group) => group.id === form.visa_group_id))

watch(
    () => props.open,
    (open) => {
        if (!open) return
        form.reset()
        form.visa_group_id = 'none'
        form.base_amount = String(available.value)
    },
)

watch(
    () => form.visa_group_id,
    (groupId) => {
        if (groupId === 'none') return
        const group = groups.value.find((option) => option.id === groupId)
        if (group) {
            form.base_amount = String(Math.min(available.value, Number(group.outstanding_amount)))
        }
    },
)

const submit = () => {
    if (!props.payment) return

    form.transform((data) => ({
        ...data,
        visa_group_id: data.visa_group_id === 'none' ? null : data.visa_group_id,
        base_amount: Number(data.base_amount || 0),
    })).post(`/${props.company.slug}/umrah/payments/${props.payment.id}/allocations`, {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false)
            emit('allocated')
        },
    })
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent>
            <DialogHeader><DialogTitle>Allocate Payment</DialogTitle></DialogHeader>
            <form novalidate class="space-y-4" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label>Group</Label>
                    <Select v-model="form.visa_group_id">
                        <SelectTrigger><SelectValue placeholder="Select group" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">Select group</SelectItem>
                            <SelectItem v-for="group in groups" :key="group.id" :value="group.id">
                                {{ group.group_number }} · {{ group.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="groups.length === 0" class="text-xs text-muted-foreground">
                        Nothing of this party's is outstanding.
                    </p>
                    <p v-if="form.errors.visa_group_id" class="text-xs text-destructive">
                        {{ form.errors.visa_group_id }}
                    </p>
                </div>
                <div class="space-y-2">
                    <Label>Amount in {{ company.base_currency }}</Label>
                    <Input
                        v-model="form.base_amount"
                        type="number"
                        min="0.01"
                        :max="selectedGroup?.outstanding_amount"
                        step="0.01"
                        required
                    />
                    <p class="text-xs text-muted-foreground">
                        Available <MoneyText :amount="available" :currency="company.base_currency" />
                        <template v-if="selectedGroup">
                            · Group outstanding
                            <MoneyText
                                :amount="selectedGroup.outstanding_amount"
                                :currency="company.base_currency"
                            />
                        </template>
                    </p>
                    <p v-if="form.errors.base_amount" class="text-xs text-destructive">
                        {{ form.errors.base_amount }}
                    </p>
                </div>
                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">Allocate</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
