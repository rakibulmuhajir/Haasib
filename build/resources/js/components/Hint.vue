<script setup lang="ts">
/** Shared entry point for contextual help and module-supplied value evidence. */
import { inject, ref } from 'vue'
import ExplanationTrigger from '@/components/ExplanationTrigger.vue'
import { useValueTrails } from '@/composables/useValueTrails'
import { valueTrailKey } from '@/composables/useValueTrail'
import { useLexicon } from '@/composables/useLexicon'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'

const props = withDefaults(defineProps<{
    side?: 'top' | 'right' | 'bottom' | 'left'
    preview?: string
    /** A root supplied by the module's useValueTrail provider. True emits explore. */
    trail?: string | boolean
}>(), { side: 'top', trail: false })
const emit = defineEmits<{ explore: [] }>()
const open = ref(false)
const { enabled } = useValueTrails()
const { t } = useLexicon()
const controller = inject(valueTrailKey, null)

function activate() {
    if (props.trail) {
        open.value = false
        if (typeof props.trail === 'string') controller?.explore(props.trail)
        emit('explore')
    } else {
        open.value = true
    }
}
</script>

<template>
    <span v-if="!enabled"><slot /></span>
    <TooltipProvider v-else :delay-duration="150">
        <Tooltip v-model:open="open" disable-closing-trigger>
            <TooltipTrigger as-child>
                <ExplanationTrigger :aria-haspopup="trail ? 'dialog' : undefined" @click="activate">
                    <slot />
                </ExplanationTrigger>
            </TooltipTrigger>
            <TooltipContent :side="side" class="max-w-xs space-y-1 text-left leading-relaxed">
                <slot name="content">
                    <template v-if="preview">{{ preview }}</template>
                    <template v-else-if="trail">{{ t('valueTrailOpen') }}</template>
                    <slot v-else />
                </slot>
                <p v-if="trail" class="text-xs text-muted-foreground">{{ t('valueTrailOpen') }}</p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
