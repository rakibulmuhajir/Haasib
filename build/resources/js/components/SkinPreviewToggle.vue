<script setup lang="ts">
/**
 * SkinPreviewToggle — local-only switch for judging the ledger skin on real
 * screens rather than on a playground.
 *
 * Deliberately plain and deliberately in the corner. It is scaffolding for the
 * migration, not a product feature, and it is removed when the skin ships.
 */
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import type { AppPageProps } from '@/types'
import { useSkin } from '@/composables/useSkin'

const page = usePage<AppPageProps>()
const enabled = computed(() => page.props.skinPreview === true)

const { skin, toggleSkin } = useSkin()
</script>

<template>
    <button
        v-if="enabled"
        type="button"
        class="skin-toggle"
        :aria-pressed="skin === 'ledger'"
        @click="toggleSkin"
    >
        Skin: {{ skin === 'ledger' ? 'ledger' : 'default' }}
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
