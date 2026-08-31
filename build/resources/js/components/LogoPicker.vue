<script setup lang="ts">
/**
 * Choose a logo from a file rather than pasting a link to one.
 *
 * These are drawn on vouchers, receipts and every report PDF, so the file
 * is re-encoded to PNG and brought down to a predictable size server-side.
 *
 * The upload happens as soon as an image is chosen, and what this emits is
 * the stored URL. That is deliberate: the party forms save with PUT, and a
 * PUT carrying a file arrives with no file at all, because PHP only fills
 * in uploads for POST. Sending the image separately leaves every one of
 * those forms submitting a plain string, exactly as it did when someone
 * typed the address in by hand.
 */
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { computed, ref } from 'vue'

const props = withDefaults(
    defineProps<{
        /** The stored logo URL; this is what the form submits. */
        modelValue?: string | null
        companySlug: string
        label?: string
        maxKilobytes?: number
        error?: string
        disabled?: boolean
    }>(),
    { modelValue: null, label: 'Logo', maxKilobytes: 300, disabled: false },
)

const emit = defineEmits<{ 'update:modelValue': [url: string | null] }>()

const input = ref<HTMLInputElement | null>(null)
const uploading = ref(false)
const failure = ref<string | null>(null)

const shown = computed(() => props.modelValue)

const reset = () => {
    if (input.value) input.value.value = ''
}

const onChange = async (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null
    failure.value = null

    if (!file) return

    // Checked here as well as on the server, so the answer is immediate
    // rather than arriving after sending an image that was never going to
    // be accepted.
    if (file.size > props.maxKilobytes * 1024) {
        failure.value = `That image is over ${props.maxKilobytes} KB. Choose a smaller one.`
        reset()

        return
    }

    const body = new FormData()
    body.append('logo', file)
    if (props.modelValue) body.append('replacing', props.modelValue)

    uploading.value = true

    try {
        const response = await fetch(`/${props.companySlug}/umrah/logos`, {
            method: 'POST',
            body,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
        })

        if (!response.ok) {
            const problem = await response.json().catch(() => null)
            failure.value = problem?.errors?.logo?.[0] ?? 'That image could not be uploaded.'
            reset()

            return
        }

        emit('update:modelValue', (await response.json()).url)
    } catch {
        failure.value = 'That image could not be uploaded.'
    } finally {
        uploading.value = false
        reset()
    }
}

const clear = () => {
    failure.value = null
    reset()
    emit('update:modelValue', null)
}
</script>

<template>
    <div class="space-y-2">
        <Label>{{ label }}</Label>
        <div class="flex items-center gap-3">
            <div
                class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted/40"
            >
                <img v-if="shown" :src="shown" alt="" class="h-full w-full object-contain" />
                <span v-else class="text-[10px] text-muted-foreground">None</span>
            </div>
            <div class="min-w-0 space-y-1">
                <input
                    ref="input"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    :disabled="disabled || uploading"
                    class="block max-w-full text-sm file:mr-3 file:rounded-md file:border file:border-input file:bg-transparent file:px-3 file:py-1.5 file:text-sm"
                    @change="onChange"
                />
                <p class="text-xs text-muted-foreground">
                    <template v-if="uploading">Uploading…</template>
                    <template v-else>
                        PNG, JPG or WebP, up to {{ maxKilobytes }} KB. Shown on vouchers and reports.
                    </template>
                </p>
            </div>
            <Button v-if="shown" type="button" variant="ghost" size="sm" :disabled="disabled" @click="clear">
                Remove
            </Button>
        </div>
        <p v-if="failure" class="text-xs text-destructive">{{ failure }}</p>
        <p v-else-if="error" class="text-xs text-destructive">{{ error }}</p>
    </div>
</template>
