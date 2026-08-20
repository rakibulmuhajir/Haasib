/*
 * Split out from index.ts so Card.vue can import the injection key without a
 * circular module reference (index.ts re-exports Card.vue as a named export,
 * so Card.vue importing back from index.ts would create a cycle that bundlers
 * tolerate at runtime but that is fragile and confusing to trace).
 */
import type { InjectionKey } from 'vue'

export type CardVariant = 'default' | 'figure' | 'register' | 'form' | 'detail'

export const CARD_VARIANT: InjectionKey<CardVariant> = Symbol('card-variant')
