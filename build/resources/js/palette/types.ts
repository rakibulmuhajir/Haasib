/** Mirrors the palette catalog and resolve JSON from the server. */

export interface Option { value: string; label: string; meta?: string }

export interface SlotSpec {
  name: string
  kind: 'amount' | 'date' | 'text' | 'search' | 'account' | 'choice'
  optional: boolean
  entity?: string
  options?: string[]
}

export interface CatalogCommand {
  name: string
  aliases: string[]
  label: string
  sentence: string
  key: string
  slots: SlotSpec[]
  generic: boolean
  form_url: string | null
  query: boolean
  opens: string | null
  undo: string | null
  previewable: boolean
}

export type SlotState =
  | { state: 'resolved'; value: string; label: string }
  | { state: 'choices'; prompt: string; choices: Option[] }
  | { state: 'not_found'; message: string }
  | { state: 'missing' }

export interface Card {
  who?: string
  what?: string
  amount?: number
  currency: string
  from?: string
  to?: string
  date?: string
  note?: string
}

export type ResolveResponse =
  | { status: 'ready'; key: string; params: Record<string, unknown>; card: Card; summary: string; notes: string[] }
  | { status: 'pending'; slots: Record<string, SlotState> }
  | { status: 'error'; message: string }
