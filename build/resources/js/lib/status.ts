/**
 * Document and record states.
 *
 * These states are not invented — they are the ones actually present in the
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
    requested: { label: 'Requested', tone: 'info' },

    // ── Decided ──────────────────────────────────────────────────────────
    approved: { label: 'Approved', tone: 'success' },
    // A refund is *accepted*, not approved: the company is agreeing to owe
    // something back, not authorising work the way an approved voucher does.
    accepted: { label: 'Accepted', tone: 'success' },
    rejected: { label: 'Rejected', tone: 'critical' },
    confirmed: { label: 'Confirmed', tone: 'success' },

    // ── Money ────────────────────────────────────────────────────────────
    paid: { label: 'Paid', tone: 'success' },
    partially_paid: { label: 'Partly paid', tone: 'attention' },
    // Nothing has been paid yet, which is the ordinary state of a document
    // that was issued this morning. Only a due date passing makes it adverse.
    unpaid: { label: 'Unpaid', tone: 'neutral' },
    // Bills store the same state under a shorter word. One state, one
    // appearance -- the spelling the table happens to use is not a reason
    // for a bill to look different from an invoice.
    partial: { label: 'Partly paid', tone: 'attention' },
    // A refund the company paid back in cash or bank. Distinct from the
    // generic `paid` above -- this is the settlement outcome, not the
    // record's own document status.
    refunded: { label: 'Refunded', tone: 'success' },
    // A vendor credit that has been matched against a bill and consumed --
    // the outcome it exists for, the same standing as a bill being paid.
    applied: { label: 'Applied', tone: 'success' },
    // The other settlement outcome: the money never left, it went back to
    // sitting as credit the party can still spend. Deliberately not worded
    // like a payment -- nothing was paid, something was kept.
    credited: { label: 'Kept as credit', tone: 'info' },
    overdue: { label: 'Overdue', tone: 'critical', explain: 'overdue' },
    received: { label: 'Received', tone: 'success' },
    // A salary advance being paid back. Part-way is the same standing as a
    // part-paid invoice -- someone still has to see it through.
    partially_recovered: { label: 'Partly recovered', tone: 'attention' },
    fully_recovered: { label: 'Recovered', tone: 'success' },

    // ── Books ────────────────────────────────────────────────────────────
    // Recorded is a fact, not a success. Nothing improved when it posted; the
    // books simply now contain it, so it reads as settled rather than green.
    posted: { label: 'Recorded', tone: 'neutral', explain: 'posted' },
    reconciled: { label: 'Matched', tone: 'success', explain: 'reconciled' },
    reversed: { label: 'Reversed', tone: 'muted', struck: true, explain: 'reversed' },

    // Billing for this record sits on a sibling record, so it is neither
    // unposted nor double-counted here. A fact about ownership, not a problem.
    shared: { label: 'Shared billing', tone: 'info' },
    // There was nothing to charge. A real, settled outcome -- not an omission,
    // which is why it is not amber.
    no_charge: { label: 'No charge', tone: 'neutral' },
    // Something should have reached the books and has not. It needs a person,
    // which is what amber means; it is not adverse until someone decides it is.
    unposted: { label: 'Not recorded', tone: 'attention' },

    // A shortage nobody is going to recover. Written off and settled -- the
    // decision has been taken, which is what stops it being amber.
    final_loss: { label: 'Written off', tone: 'neutral' },

    // ── No longer counts ─────────────────────────────────────────────────
    void: { label: 'Voided', tone: 'muted', struck: true, explain: 'void' },
    // Replaced by a later posting. The entry still exists and no longer counts,
    // which is the same standing -- and so the same strike -- as a reversal.
    superseded: { label: 'Superseded', tone: 'muted', struck: true },
    cancelled: { label: 'Cancelled', tone: 'muted', struck: true },
    // Removed, but still on screen because something still points at it. Same
    // standing as a void: it exists and it no longer counts.
    deleted: { label: 'Deleted', tone: 'muted', struck: true },
    archived: { label: 'Archived', tone: 'muted' },

    // ── Availability ─────────────────────────────────────────────────────
    active: { label: 'Active', tone: 'success' },
    // Deactivated, not deleted. The record keeps its history and stops being
    // offered for new work, which is the same standing as an archived one.
    inactive: { label: 'Inactive', tone: 'muted' },
    // The one period new entries land in. Informational, not a success --
    // nothing was achieved by a year being the current one.
    current: { label: 'Current', tone: 'info' },
    open: { label: 'Open', tone: 'neutral' },
    locked: { label: 'Locked', tone: 'neutral', explain: 'locked' },
    closed: { label: 'Closed', tone: 'neutral', explain: 'closed' },
    // A payroll period between opening and closing: payslips are being
    // generated, approved or paid. Same standing as `pending` -- someone
    // still has to see it through before the period can close.
    processing: { label: 'Processing', tone: 'attention' },

    // An employee temporarily away, still on the books. Informational, like
    // `current` -- nothing was achieved or lost by taking leave.
    on_leave: { label: 'On leave', tone: 'info' },
    // A deliberate hold, not yet a termination. Someone has to review it and
    // decide, which is the same standing as an unposted document.
    suspended: { label: 'Suspended', tone: 'attention' },
    // No longer employed. The record keeps its history and stops counting
    // toward active headcount, the same standing as an inactive partner.
    terminated: { label: 'Terminated', tone: 'muted', struck: true },

    // A customer blocked from further credit purchases -- a decision already
    // taken, not a live emergency, so it does not reach for red; it needs a
    // person to review and lift it, same standing as `suspended`.
    blocked: { label: 'Blocked', tone: 'attention' },
    // Balance has crossed the credit limit. Someone needs to collect or
    // adjust the limit -- amber, not red, until it is decided to write off.
    over_limit: { label: 'Over limit', tone: 'attention' },

    // A stock level under its reorder point. Needs a person to reorder, the
    // same standing as `unposted` -- not itself a failure.
    low: { label: 'Low stock', tone: 'attention' },
    // Above reorder point. The ordinary state of a stock line on any given
    // day, so it reads plain rather than as an accomplishment.
    ok: { label: 'In stock', tone: 'neutral' },

    // -- Visa processing -------------------------------------------------
    // A visa group moves through these on its way to a traveller. The middle
    // steps are progress reports, not achievements, so only the two that end
    // the wait are green.
    passports_received: { label: 'Passports in', tone: 'info' },
    visa_approved: { label: 'Visa approved', tone: 'success' },
    delivered: { label: 'Delivered', tone: 'success' },
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
