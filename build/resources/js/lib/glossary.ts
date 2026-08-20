/**
 * Glossary.
 *
 * This file replaces the owner/accountant mode toggle.
 *
 * A mode toggle asks people to declare an identity before they know what the
 * words mean — the person who doesn't know what "Particulars" is cannot tell
 * which mode they belong in. It also duplicates every user-facing string across
 * 193 pages and guarantees drift, which gets expensive the moment Urdu
 * localisation arrives. One vocabulary, explained on demand, does the same job
 * without either cost.
 *
 * It also answers questions a toggle structurally cannot. Renaming a heading
 * never explains why a figure differs from the bank balance; `why` does.
 *
 * Writing rules for entries:
 *   - `short` is one sentence, plain language, no jargon used to define jargon.
 *   - `why` answers the question the number actually provokes. Optional, and
 *     worth far more than `short` when it applies.
 *   - `also` names the bookkeeping term, so someone who knows it recognises the
 *     concept and someone who doesn't learns the word without being tested on it.
 */

export interface GlossaryEntry {
    /** The term as it reads in the interface. */
    label: string
    short: string
    why?: string
    /** The formal accounting name, when the interface uses a plainer one. */
    also?: string
    /** Related terms, rendered as further explainers. */
    see?: string[]
}

export const glossary = {
    // ── States ───────────────────────────────────────────────────────────
    posted: {
        label: 'Recorded',
        short: 'The entry is in your books and counts toward your balances.',
        why: 'Recorded entries cannot be edited. Correcting one means recording a reversal, so the original stays visible and the history stays honest.',
        also: 'Posted',
        see: ['reversed'],
    },
    reversed: {
        label: 'Reversed',
        short: 'A later entry cancelled this one out. Both stay in the books.',
        why: 'Deleting an entry would leave a gap nobody could explain later. Reversing leaves the original in place and adds an equal, opposite entry beside it.',
        see: ['posted'],
    },
    void: {
        label: 'Voided',
        short: 'The document was cancelled before it counted toward anything.',
        also: 'Void',
    },
    reconciled: {
        label: 'Matched',
        short: 'This line has been paired with the matching line on your bank statement.',
        why: 'Matching is how you confirm your books and your bank agree. Anything still unmatched is the difference between the two.',
        also: 'Reconciled',
    },
    overdue: {
        label: 'Overdue',
        short: 'The due date has passed and the amount is still outstanding.',
    },
    locked: {
        label: 'Locked',
        short: 'The period is closed to new entries, so nothing dated inside it can change.',
        see: ['closed'],
    },
    closed: {
        label: 'Closed',
        short: 'The period is finished and its figures are final.',
        see: ['locked'],
    },

    // ── Amounts ──────────────────────────────────────────────────────────
    cashPosition: {
        label: 'Cash position',
        short: 'What is actually in your accounts right now.',
        why: 'This is usually lower than your profit. Profit counts a sale the day you invoice it; cash counts it the day the money lands.',
        see: ['profit'],
    },
    profit: {
        label: 'Profit',
        short: 'What you earned minus what it cost you, over a period.',
        why: 'Profit is not money in the bank. An unpaid invoice adds to profit immediately and adds to cash only when the customer pays.',
        see: ['cashPosition'],
    },
    estimated: {
        label: 'Estimated',
        short: 'A projection, not a recorded fact. It will change.',
    },
    baseCurrency: {
        label: 'Base currency',
        short: 'The currency your reports are totalled in.',
        why: 'Foreign amounts are converted at the rate on the transaction date, so the converted figure stays fixed even when the rate moves afterwards.',
    },
    receivables: {
        label: 'Owed to you',
        short: 'Money customers have been invoiced for but have not yet paid.',
        also: 'Accounts receivable',
    },
    payables: {
        label: 'You owe',
        short: 'Bills you have received but not yet paid.',
        also: 'Accounts payable',
    },

    // ── Artifacts the user navigates to ──────────────────────────────────
    // These keep their real names: they are the names of things, not jargon.
    chartOfAccounts: {
        label: 'Chart of accounts',
        short: 'The list of categories every transaction gets filed under.',
        why: 'Every report is built by grouping transactions by account, so the shape of this list decides the shape of your reports.',
    },
    trialBalance: {
        label: 'Trial balance',
        short: 'Every account and its balance on one page, proving the books add up.',
        why: 'The two columns must total the same. If they do not, an entry was recorded incompletely somewhere.',
    },
    journal: {
        label: 'Journal',
        short: 'The dated record of every entry made to your books.',
    },
    fiscalYear: {
        label: 'Financial year',
        short: 'The twelve months your accounts and taxes are reported against.',
        also: 'Fiscal year',
    },
} as const satisfies Record<string, GlossaryEntry>

export type GlossaryKey = keyof typeof glossary

export function lookup(term: string): GlossaryEntry | null {
    return (glossary as Record<string, GlossaryEntry>)[term] ?? null
}

export const glossaryKeys = Object.keys(glossary) as GlossaryKey[]
