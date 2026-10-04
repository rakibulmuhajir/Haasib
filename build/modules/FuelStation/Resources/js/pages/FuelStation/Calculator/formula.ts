// The Calculator's formula: a row of chips in the builder, a JSON tree on the server.
// The server only ever reads the tree (never a string to eval); these turn one into the other.

export type Op = '+' | '-' | '*' | '/'

export interface Collection {
  type: string
  id?: string
}

export interface When {
  preset?: string
  n?: number
  from?: string
  to?: string
  on?: string
}

export interface ValueNode {
  type: 'value'
  metric: string
  collection: Collection
  when: When
}

export type FormulaNode =
  | ValueNode
  | { type: 'number'; value: number }
  | { type: 'op'; op: Op; left: FormulaNode; right: FormulaNode }
  | { type: 'group'; inner: FormulaNode }

export type Token =
  | { key: number; kind: 'value'; node: ValueNode }
  | { key: number; kind: 'number'; value: number }
  | { key: number; kind: 'op'; op: Op }
  | { key: number; kind: 'paren'; paren: '(' | ')' }

let seq = 0
export const nextKey = (): number => ++seq

/** The tree as chips, left to right. Brackets come back from group nodes. */
export function tokensFromAst(node: FormulaNode): Token[] {
  switch (node.type) {
    case 'value':
      return [{ key: nextKey(), kind: 'value', node }]
    case 'number':
      return [{ key: nextKey(), kind: 'number', value: node.value }]
    case 'group':
      return [{ key: nextKey(), kind: 'paren', paren: '(' }, ...tokensFromAst(node.inner), { key: nextKey(), kind: 'paren', paren: ')' }]
    case 'op':
      return [...tokensFromAst(node.left), { key: nextKey(), kind: 'op', op: node.op }, ...tokensFromAst(node.right)]
  }
}

/** The chips as a tree: x and ÷ before + and −, brackets first, left to right among equals. */
export function astFromTokens(tokens: Token[]): { ast?: FormulaNode; error?: string } {
  if (tokens.length === 0) return { error: 'Add a value first.' }
  let i = 0

  const factor = (): FormulaNode => {
    const t = tokens[i]
    if (!t) throw new Error('The formula ends too early.')
    if (t.kind === 'value') {
      i++
      return t.node
    }
    if (t.kind === 'number') {
      i++
      return { type: 'number', value: t.value }
    }
    if (t.kind === 'paren' && t.paren === '(') {
      i++
      const inner = expr()
      const close = tokens[i]
      if (!close || close.kind !== 'paren' || close.paren !== ')') throw new Error('Brackets do not match.')
      i++
      return { type: 'group', inner }
    }
    throw new Error('Put a value or number here.')
  }

  const chain = (next: () => FormulaNode, ops: Op[]): FormulaNode => {
    let left = next()
    for (;;) {
      const t = tokens[i]
      if (!t || t.kind !== 'op' || !ops.includes(t.op)) return left
      i++
      left = { type: 'op', op: t.op, left, right: next() }
    }
  }

  const term = (): FormulaNode => chain(factor, ['*', '/'])
  const expr = (): FormulaNode => chain(term, ['+', '-'])

  try {
    const ast = expr()
    if (i < tokens.length) {
      const t = tokens[i]
      throw new Error(t.kind === 'paren' && t.paren === ')' ? 'Brackets do not match.' : 'Put an operator between values.')
    }
    return { ast }
  } catch (e) {
    return { error: e instanceof Error ? e.message : 'Check the formula.' }
  }
}

export const isoDate = (date: Date): string => {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

const presetNames: Record<string, string> = {
  today: 'today',
  yesterday: 'yesterday',
  this_month: 'this month',
  last_month: 'last month',
  this_year: 'this year',
  month_end_last: 'last month end',
}

export function whenLabel(when: When): string {
  if (when.preset === 'last_n_days') return `last ${when.n ?? 7} days`
  if (when.preset) return presetNames[when.preset] ?? when.preset
  if (when.on) return `on ${when.on}`
  return `${when.from ?? '?'} to ${when.to ?? '?'}`
}
