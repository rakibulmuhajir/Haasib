/**
 * Document and record states.
 *
 * These 20 states are not invented — they are the ones actually present in the
 * codebase, gathered from invoices, bills, journals, reconciliations, payroll
 * runs and partners. Defining them once means a status looks the same wherever
 * it appears, which is the whole point of a status.
 *
 * Two rules govern every entry:
 *
 *   1. Every state carries a NON-COLOUR indicator. The label is the primary
 *      one; `struck` adds a second for records that no longer count. Colour
 *      only ever reinforces something already legible without it.
 *
 *   2. Labels are plain language. Real accounting terms survive only where they
 *      name a thing the user navigates to. "Posted" is a bookkeeping verb, so
 *      the chip reads `Recorded` and the glossary explains that it is called
 *      posting — see lib/glossary.ts.
 */

export type StatusTone = 'neutral' | 'info' | 'success' | 'attention' | 'critical' | 'muted'

export interface StatusMeta {
    /** What the chip reads. Plain language, sentence case. */
    label: string
    tone: StatusTone
    /**
     * Strike the label through. The second non-colour indicator, reserved for
     * records that exist but no longer count toward any balance.
     */
    struck?: boolean
    /** Glossary key, when the state is worth explaining on demand. */
    explain?: string
}

export const statusMeta = {
    // ── In progress ──────────────────────────────────────────────────────
    draft: { label: 'Draft', tone: 'neutral' },
    pending: { label: 'Pending', tone: 'attention' },
    submitted: { label: 'Submitted', tone: 'info' },
    sent: { label: 'Sent', tone: 'info' },

    // ── Decided ──────────────────────────────────────────────────────────
    approved: { label: 'Approved', tone: 'success' },
    rejected: { label: 'Rejected', tone: 'critical' },
    confirmed: { label: 'Confirmed', tone: 'success' },

    // ── Money ────────────────────────────────────────────────────────────
    paid: { label: 'Paid', tone: 'success' },
    partially_paid: { label: 'Partly paid', tone: 'attention' },
    overdue: { label: 'Overdue', tone: 'critical', explain: 'overdue' },
    received: { label: 'Received', tone: 'success' },

    // ── Books ────────────────────────────────────────────────────────────
    // Recorded is a fact, not a success. Nothing improved when it posted; the
    // books simply now contain it, so it reads as settled rather than green.
    posted: { label: 'Recorded', tone: 'neutral', explain: 'posted' },
    reconciled: { label: 'Matched', tone: 'success', explain: 'reconciled' },
    reversed: { label: 'Reversed', tone: 'muted', struck: true, explain: 'reversed' },

    // ── No longer counts ─────────────────────────────────────────────────
    void: { label: 'Voided', tone: 'muted', struck: true, explain: 'void' },
    cancelled: { label: 'Cancelled', tone: 'muted', struck: true },
    archived: { label: 'Archived', tone: 'muted' },

    // ── Availability ─────────────────────────────────────────────────────
    active: { label: 'Active', tone: 'success' },
    locked: { label: 'Locked', tone: 'neutral', explain: 'locked' },
    closed: { label: 'Closed', tone: 'neutral', explain: 'closed' },
} as const satisfies Record<string, StatusMeta>

export type StatusKey = keyof typeof statusMeta

/**
 * Resolve a status coming off the wire.
 *
 * Backends are inconsistent about casing and separators — `PartiallyPaid`,
 * `partially-paid` and `PARTIALLY_PAID` all appear. Normalising here beats 20
 * pages each doing it slightly differently.
 *
 * An unrecognised value is never dropped: it is titlecased and shown neutral,
 * because hiding a state the server considers real is worse than showing one
 * this file has not caught up with yet.
 */
export function resolveStatus(status: string | null | undefined): StatusMeta | null {
    if (!status) return null

    const key = String(status)
        .trim()
        .replace(/([a-z0-9])([A-Z])/g, '$1_$2')
        .replace(/[\s-]+/g, '_')
        .toLowerCase() as StatusKey

    if (key in statusMeta) return statusMeta[key]

    return {
        label: key.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase()),
        tone: 'neutral',
    }
}

/** Every key, in declaration order. Used by the design playground. */
export const statusKeys = Object.keys(statusMeta) as StatusKey[]
