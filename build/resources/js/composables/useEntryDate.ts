import { watch, type WatchSource } from 'vue'

/**
 * The date an entry belongs to decides which daily close it lands in, so the date a form
 * starts with matters more than it looks.
 *
 * Two problems with how forms used to start:
 *
 *  - They defaulted to `new Date().toISOString().slice(0, 10)`, which is the UTC date. Pakistan
 *    is UTC+5, so anything entered between midnight and 5am local time defaulted to yesterday -
 *    including a reading taken at midnight on a rate-change night.
 *
 *  - Every form reset to today on every open. Entering a past month from a register means
 *    changing the date on every single entry, and the one that gets missed lands on today and
 *    leaves its real day short.
 *
 * So a form starts on the last date used for this company, in this browser tab. Deliberately
 * the tab, not the browser: remembering yesterday's date into tomorrow's normal work would
 * reproduce the mistake this exists to prevent. A new tab, or the next morning, starts at today.
 * EntryDateNote says so whenever the date on screen is not today.
 */

const pad = (n: number) => String(n).padStart(2, '0')

/** Today in the browser's own time zone, as YYYY-MM-DD. */
export const localToday = (): string => {
    const d = new Date()
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

const storageKey = (companySlug: string) => `haasib:entry-date:${companySlug}`

const isIsoDate = (value: unknown): value is string =>
    typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)

/** The date a new entry should start on: the last one used in this tab, else today. */
export const entryDateDefault = (companySlug?: string | null): string => {
    if (companySlug) {
        try {
            const stored = sessionStorage.getItem(storageKey(companySlug))
            if (isIsoDate(stored)) return stored
        } catch {
            // Storage can be unavailable (private windows, blocked site data). Today is the
            // honest fallback.
        }
    }

    return localToday()
}

/** Remember the date field as the user changes it, so the next entry starts there. */
export const rememberEntryDate = (
    companySlug: string | null | undefined,
    source: WatchSource<string | null | undefined>,
): void => {
    watch(source, (value) => {
        if (!companySlug || !isIsoDate(value)) return

        try {
            sessionStorage.setItem(storageKey(companySlug), value)
        } catch {
            // Not remembering is harmless; the form still works.
        }
    })
}
