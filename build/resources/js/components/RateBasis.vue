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

const newRate = ref('')

const newTotal = computed(() => {
    const rate = Number(newRate.value)
    if (!Number.isFinite(rate) || newRate.value === '' || rate < 0) return null

    return Math.round((rate * props.count + props.extra) * 100) / 100
})

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
            <template v-if="extra">
                · plus <MoneyText :amount="extra" :currency="currency" /> charged separately
            </template>
        </p>
        <div v-if="!disabled" class="flex items-end gap-2">
            <div class="space-y-1">
                <Label class="text-xs text-muted-foreground">New rate each</Label>
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
        </div>
    </div>
</template>
