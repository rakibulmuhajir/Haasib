/**
 * useLexicon — the product's words, looked up by key.
 *
 * There is one vocabulary. This composable used to take a mode and hand back
 * either the owner or the accountant spelling of a term; the accountant half
 * was never reachable, and the toggle that would have selected it was dropped
 * deliberately. A mode switch asks someone to declare an identity before they
 * know what the words mean — the person who does not know what "Particulars"
 * means cannot tell which mode they belong in. Plain language plus <Explain>
 * answers the question the toggle only relabelled.
 *
 * @see docs/frontend-experience-contract.md Section 14: Language & Terminology
 * @see lib/lexicon.ts for the dictionary itself
 *
 * Usage:
 *   const { t, tpl } = useLexicon()
 *   t('moneyIn')                              // "Money In"
 *   tpl('transactionsToReviewCount', { count: 5 })  // "5 transactions to review"
 */

import {
    lexicon,
    getTerm,
    interpolate,
    type LexiconKey,
    type TermDictionary,

    // Category exports for selective imports
    coreTerms,
    receivablesTerms,
    payablesTerms,
    bankingTerms,
    reportTerms,
    navigationTerms,
    statusTerms,
    dashboardTerms,
    emptyStateTerms,
    helpTerms,
    templateTerms,
} from '@/lib/lexicon'

export interface UseLexiconReturn {
    /** Look up a term. Unknown keys warn and return the key. */
    t: (key: LexiconKey | string) => string

    /** Look up a term and interpolate `{name}` placeholders into it. */
    tpl: (key: LexiconKey | string, params: Record<string, string | number>) => string

    /** Whether a key exists, for callers that fall back to their own copy. */
    has: (key: string) => boolean
}

export function useLexicon(): UseLexiconReturn {
    return {
        t: (key) => getTerm(key),
        tpl: (key, params) => getTerm(key, params),
        has: (key) => key in lexicon,
    }
}

export {
    lexicon,
    getTerm,
    interpolate,
    type LexiconKey,
    type TermDictionary,

    // Category exports
    coreTerms,
    receivablesTerms,
    payablesTerms,
    bankingTerms,
    reportTerms,
    navigationTerms,
    statusTerms,
    dashboardTerms,
    emptyStateTerms,
    helpTerms,
    templateTerms,
}
