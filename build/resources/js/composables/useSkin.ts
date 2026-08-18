/**
 * useSkin — turns the ledger skin on for real screens, not just the playground.
 *
 * The skin is a `data-skin="ledger"` attribute on <html>, and it has to be on
 * <html> rather than a wrapper because reka-ui portals its overlays — dialog,
 * popover, dropdown, toast — straight to document.body. A skin scoped to the
 * page content would leave every overlay in the app rendering unskinned.
 *
 * This exists so the pilot slice can be judged against real data with the skin
 * both on and off, without flipping anything for anyone else. It is a preview
 * switch, not the rollout: `skinPreview` is shared from the server and is true
 * only in local. When the skin eventually ships it will be set server-side in
 * the blade template and this file goes away.
 */
import { ref } from 'vue'

export type Skin = 'ledger' | 'default'

const STORAGE_KEY = 'skin'

/** Module-level so every mounted toggle reflects the same state. */
const skin = ref<Skin>('default')

function applySkin(value: Skin) {
    if (typeof document === 'undefined') return

    const root = document.documentElement

    if (value === 'ledger') root.setAttribute('data-skin', 'ledger')
    else root.removeAttribute('data-skin')
}

function storedSkin(): Skin | null {
    if (typeof window === 'undefined') return null

    return localStorage.getItem(STORAGE_KEY) === 'ledger' ? 'ledger' : null
}

/**
 * Called once at boot. The blade template applies the attribute before first
 * paint; this only syncs the ref so the toggle starts in the right position.
 */
export function initializeSkin() {
    const stored = storedSkin()

    if (stored) {
        skin.value = stored
        applySkin(stored)
    }
}

export function useSkin() {
    function setSkin(value: Skin) {
        skin.value = value
        applySkin(value)

        if (typeof window !== 'undefined') {
            if (value === 'ledger') localStorage.setItem(STORAGE_KEY, 'ledger')
            else localStorage.removeItem(STORAGE_KEY)
        }
    }

    function toggleSkin() {
        setSkin(skin.value === 'ledger' ? 'default' : 'ledger')
    }

    return { skin, setSkin, toggleSkin }
}
