/**
 * useSkin — which palette the app is wearing.
 *
 * A skin is a `data-skin` attribute on <html>, and it has to be on <html>
 * rather than a wrapper because reka-ui portals its overlays — dialog, popover,
 * dropdown, toast — straight to document.body. A skin scoped to the page
 * content would leave every overlay in the app rendering unskinned.
 *
 * A skin is a PALETTE, not a second design system: the grammar rules live in
 * app.css under the bare `[data-skin]` selector and apply to all of them. See
 * docs/theming.md.
 *
 * The list of skins is not held here. It comes from config/skins.php, shared
 * through Inertia, so adding one never means editing this file. 'default' is
 * the stock theme and is represented by the absence of the attribute.
 */
import { ref } from 'vue'

export const DEFAULT_SKIN = 'default'

const STORAGE_KEY = 'skin'

/** Module-level so every mounted picker reflects the same state. */
const skin = ref<string>(DEFAULT_SKIN)

function applySkin(value: string) {
    if (typeof document === 'undefined') return

    const root = document.documentElement

    if (value && value !== DEFAULT_SKIN) root.setAttribute('data-skin', value)
    else root.removeAttribute('data-skin')
}

/**
 * Called once at boot. The blade template applies the attribute before first
 * paint; this only syncs the ref so the picker starts in the right position.
 * It reads the attribute rather than localStorage so the two can never
 * disagree — blade has already validated the stored value against the registry.
 */
export function initializeSkin() {
    if (typeof document === 'undefined') return

    skin.value = document.documentElement.getAttribute('data-skin') || DEFAULT_SKIN
}

export function useSkin() {
    function setSkin(value: string) {
        skin.value = value
        applySkin(value)

        if (typeof window === 'undefined') return

        if (value && value !== DEFAULT_SKIN) localStorage.setItem(STORAGE_KEY, value)
        else localStorage.removeItem(STORAGE_KEY)
    }

    /**
     * Step to the next skin in the registry, wrapping at the end. A cycle
     * rather than a two-state toggle, so a third skin needs no new control.
     */
    function cycleSkin(ids: string[]) {
        if (!ids.length) return

        const next = ids[(ids.indexOf(skin.value) + 1) % ids.length]
        setSkin(next)
    }

    return { skin, setSkin, cycleSkin }
}
