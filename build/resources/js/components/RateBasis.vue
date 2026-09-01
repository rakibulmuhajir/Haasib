<script setup lang="ts">
/**
 * The arithmetic behind a group's figure, and a way to redo it.
 *
 * Adjusting a group meant knowing the new total: a supplier dropping from
 * 520 a head to 480 left someone multiplying by fourteen in their head and
 * typing 6,720 into a box that showed neither the fourteen nor the 520.
 * This shows the sum and offers to do it again at a different rate.
 *
 * `extra` is whatever the total holds beyond rate times count -- a
 * transport figure also carries the charges of passengers who bought a
 * seat on their own. Recomputing without it would quietly drop them.
 */
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { computed, ref } from 'vue'

const props = withDefaults(
    defineProps<{
        count: number
        total: number
        currency: string
        /** Held constant while the rate changes. */
        extra?: number
        /** Shown when the rate is derived rather than stored. */
        derived?: boolean
        disabled?: boolean
    }>(),
    { extra: 0, derived: false, disabled: false },
)

const emit = defineEmits<{ apply: [total: number] }>()

const currentRate = computed(() => {
    if (props.count <= 0) return 0

    return Math.round(((props.total - props.extra) / props.count) * 100) / 100
})

/**
 * Two ways to say the same thing, because both are how the change arrives.
 *
 * A supplier quotes a rate a head; an agent renegotiates a lump sum for
 * the group. Offering only the per-head box left whoever held the lump
 * sum dividing it by fourteen to type it in, and the division is exactly
 * what this component exists to do.
 */
const basis = ref<'each' | 'total'>('each')
const newRate = ref('')

const typed = computed(() => {
    const value = Number(newRate.value)

    return Number.isFinite(value) && newRate.value !== '' && value >= 0 ? value : null
})

const newTotal = computed(() => {
    if (typed.value === null) return null
    if (basis.value === 'total') return Math.round(typed.value * 100) / 100

    return Math.round((typed.value * props.count + props.extra) * 100) / 100
})

/** What a typed total works out at a head -- the sum, run the other way. */
const impliedRate = computed(() => {
    if (basis.value !== 'total' || newTotal.value === null || props.count <= 0) return null

    return Math.round(((newTotal.value - props.extra) / props.count) * 100) / 100
})

const setBasis = (value: 'each' | 'total') => {
    basis.value = value
    newRate.value = ''
}

/**
 * A rate typed here counts as typed, whether or not Use was pressed.
 *
 * The box sits inside the accounting form, so Enter used to submit that
 * form -- saving the old total, reporting success, and dropping the rate
 * on the floor. Leaving the field without pressing Use did the same thing
 * more quietly. Both now commit what was typed, which is the only reading
 * of typing a rate into a box labelled New rate each.
 */
const apply = () => {
    if (newTotal.value !== null) {
        emit('apply', newTotal.value)
        newRate.value = ''
    }
}
</script>

<template>
    <div v-if="count > 0" class="space-y-1 text-xs text-muted-foreground">
        <p>
            {{ count }} <template v-if="count === 1">passenger</template><template v-else>passengers</template>
            &times; <MoneyText :amount="currentRate" :currency="currency" />
            <template v-if="derived"> each, as charged</template><template v-else> each</template>
            <template v-if="extra > 0">
                · plus <MoneyText :amount="extra" :currency="currency" /> charged separately
            </template>
            <!--
                A negative remainder is a supplier's credit, not a charge.
                Reading "plus -400.00 charged separately" is how a coach
                company knocking 400 off a fare described itself.
            -->
            <template v-else-if="extra < 0">
                · less <MoneyText :amount="Math.abs(extra)" :currency="currency" /> credited back
            </template>
        </p>
        <div v-if="!disabled" class="flex flex-wrap items-end gap-2">
            <div class="space-y-1">
                <div class="flex items-center gap-1">
                    <Button
                        type="button"
                        size="sm"
                        class="h-6 px-2 text-xs"
                        :variant="basis === 'each' ? 'secondary' : 'ghost'"
                        :aria-pressed="basis === 'each'"
                        @click="setBasis('each')"
                    >
                        Per passenger
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        class="h-6 px-2 text-xs"
                        :variant="basis === 'total' ? 'secondary' : 'ghost'"
                        :aria-pressed="basis === 'total'"
                        @click="setBasis('total')"
                    >
                        Total
                    </Button>
                </div>
                <Label class="text-xs text-muted-foreground">{{ basis === 'each' ? 'New rate each' : 'New total' }}</Label>
                <Input
                    v-model="newRate"
                    type="number"
                    min="0"
                    step="0.01"
                    class="h-8 w-32"
                    @keydown.enter.prevent="apply"
                    @blur="apply"
                />
            </div>
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="newTotal === null"
                @click="apply"
            >
                Use
                <MoneyText v-if="newTotal !== null" :amount="newTotal" :currency="currency" />
            </Button>
            <p v-if="impliedRate !== null" class="pb-2 text-xs text-muted-foreground">
                = <MoneyText :amount="impliedRate" :currency="currency" /> each
            </p>
        </div>
    </div>
</template>
