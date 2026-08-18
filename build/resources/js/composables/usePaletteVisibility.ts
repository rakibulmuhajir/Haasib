import { ref } from 'vue'

/**
 * Whether the command palette is open.
 *
 * This lived as a local ref inside AppRoot, which meant the only way to open
 * the palette was the ⌘K listener AppRoot itself installed. The header needs a
 * search control that opens the same palette, and a header cannot reach into
 * another component's setup scope — so the flag moves to module scope, where
 * both can hold it. There is exactly one palette on the page, so exactly one
 * ref is correct.
 */
const visible = ref(false)

export function usePaletteVisibility() {
    return {
        visible,
        open: () => (visible.value = true),
        close: () => (visible.value = false),
        toggle: () => (visible.value = !visible.value),
    }
}
