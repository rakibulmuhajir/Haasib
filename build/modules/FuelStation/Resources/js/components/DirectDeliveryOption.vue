<script setup lang="ts">
import InputError from '@/components/InputError.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'

// `form` is the invoice page's shared Inertia form; the slot contract is that this card sets its field.
const props = defineProps<{
    form: { is_direct_delivery: boolean; errors: Record<string, string | undefined> }
    disabled?: boolean
}>()
</script>

<template>
    <Card variant="form">
        <CardContent class="space-y-2 pt-6">
            <div class="flex items-start gap-2">
                <!-- eslint-disable-next-line vue/no-mutating-props -- the slot's `form` is the page's shared form object -->
                <Checkbox id="is_direct_delivery" v-model:checked="props.form.is_direct_delivery" :disabled="props.disabled" />
                <div>
                    <Label for="is_direct_delivery" class="font-normal">Direct delivery — not from the pumps</Label>
                    <p class="text-xs text-muted-foreground">Fuel that went straight to the customer. The daily close leaves it alone.</p>
                </div>
            </div>
            <InputError :message="props.form.errors.is_direct_delivery" />
        </CardContent>
    </Card>
</template>
