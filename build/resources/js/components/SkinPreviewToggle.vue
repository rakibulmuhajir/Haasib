<script setup lang="ts">
/**
 * SkinPreviewToggle — local-only switch for judging a skin on real screens
 * rather than on a playground.
 *
 * Deliberately plain and deliberately in the corner. It is scaffolding for the
 * migration, not a product feature. It cycles the registry rather than
 * toggling two states, so adding a third skin needs nothing here.
 */
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import type { AppPageProps } from '@/types'
import { DEFAULT_SKIN, useSkin } from '@/composables/useSkin'

const page = usePage<AppPageProps>()
const enabled = computed(() => page.props.skinPreview === true)
const options = computed(() => page.props.skins ?? [])
const ids = computed(() => options.value.map((option) => option.id))

const { skin, cycleSkin } = useSkin()

const label = computed(
    () => options.value.find((option) => option.id === skin.value)?.label ?? DEFAULT_SKIN,
)
</script>

<template>
    <button
        v-if="enabled && ids.length > 1"
        type="button"
        class="skin-toggle"
        :title="`Skin: ${label} — click to cycle`"
        @click="cycleSkin(ids)"
    >
        Skin: {{ label }}
    </button>
</template>

<style scoped>
.skin-toggle {
    position: fixed;
    right: 12px;
    bottom: 12px;
    /* Above page content, below reka-ui's portalled overlays (z-50), so it can
       never sit on top of a dialog the user is trying to read. */
    z-index: 40;
    padding: 4px 10px;
    border: 1px solid var(--rule-default);
    border-radius: var(--radius);
    background: var(--surface-raised);
    color: var(--text-metadata);
    font-family: var(--mono-family);
    font-size: 11px;
    line-height: 1.6;
    opacity: 0.55;
}

.skin-toggle:hover {
    opacity: 1;
}

.skin-toggle:focus-visible {
    opacity: 1;
    outline: 2px solid var(--focus-ring);
    outline-offset: 2px;
}
</style>
