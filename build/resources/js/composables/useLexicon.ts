/**
 * Mode-Aware Lexicon Composable
 *
 * Provides mode-aware terminology throughout the application.
 * Automatically uses the current user mode from useUserMode().
 *
 * @see docs/frontend-experience-contract.md Section 14: Language & Terminology
 * @see lib/lexicon.ts for the terminology dictionary
 *
 * Usage:
 *   const { t, tpl } = useLexicon()
 *
 *   // Simple term lookup
 *   t('moneyIn')  // "Money In" (owner) or "Revenue" (accountant)
 *
 *   // Template with interpolation
 *   tpl('transactionsToReviewCount', { count: 5 })  // "5 transactions to review"
 *
 *   // Override mode temporarily
 *   t('moneyIn', 'accountant')  // Always returns "Revenue"
 */

import { computed } from 'vue'
import { useUserMode, type UserMode } from './useUserMode'
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
  /**
   * Get a term in the current mode
   * @param key - The lexicon key
   * @param overrideMode - Optional mode override
   */
  t: (key: LexiconKey | string, overrideMode?: UserMode) => string

  /**
   * Get a templated term with interpolation
   * @param key - The lexicon key
   * @param params - Parameters to interpolate
   * @param overrideMode - Optional mode override
   */
  tpl: (
    key: LexiconKey | string,
    params: Record<string, string | number>,
    overrideMode?: UserMode
  ) => string

  /**
   * Get both mode variants for a key (useful for debugging or showing toggle)
   * @param key - The lexicon key
   */
  both: (key: LexiconKey | string) => { owner: string; accountant: string } | null

  /**
   * Check if a key exists in the lexicon
   * @param key - The key to check
   */
  has: (key: string) => boolean

  /**
   * Current mode (reactive)
   */
  currentMode: ReturnType<typeof useUserMode>['mode']

  /**
   * Is in accountant mode (reactive)
   */
  isAccountantMode: ReturnType<typeof useUserMode>['isAccountantMode']
}

export function useLexicon(): UseLexiconReturn {
  const { mode, isAccountantMode } = useUserMode()

  /**
   * Get a term in the current (or overridden) mode
   */
  function t(key: LexiconKey | string, overrideMode?: UserMode): string {
    const effectiveMode = overrideMode ?? mode.value
    return getTerm(key, effectiveMode)
  }

  /**
   * Get a templated term with interpolation
   */
  function tpl(
    key: LexiconKey | string,
    params: Record<string, string | number>,
    overrideMode?: UserMode
  ): string {
    const effectiveMode = overrideMode ?? mode.value
    return getTerm(key, effectiveMode, params)
  }

  /**
   * Get both mode variants for a key
   */
  function both(key: LexiconKey | string): { owner: string; accountant: string } | null {
    const entry = lexicon[key]
    return entry ?? null
  }

  /**
   * Check if a key exists in the lexicon
   */
  function has(key: string): boolean {
    return key in lexicon
  }

  return {
    t,
    tpl,
    both,
    has,
    currentMode: mode,
    isAccountantMode,
  }
}

// -----------------------------------------------------------------------------
// Standalone helper for non-reactive contexts (e.g., route definitions)
// -----------------------------------------------------------------------------

/**
 * Get a term for a specific mode (non-reactive)
 * Use this in route definitions or other non-component contexts
 */
export function getTermForMode(
  key: LexiconKey | string,
  mode: UserMode,
  params?: Record<string, string | number>
): string {
  return getTerm(key, mode, params)
}

// -----------------------------------------------------------------------------
// Re-exports for convenience
// -----------------------------------------------------------------------------

export {
  lexicon,
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
