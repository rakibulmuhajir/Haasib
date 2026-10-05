export type OverallDiscountType = 'amount' | 'percent'

interface TotalsLine {
  line_total?: number | string | null
  tax_rate?: number | string | null
  discount_rate?: number | string | null
}

/**
 * Mirrors the server's BillLineTotals::computeAll: the overall discount comes off the
 * subtotal left after line discounts, is spread over the lines pro rata (last line takes
 * the rounding), and tax is charged on each line less its share. A preview only -- the
 * server's figures are the ones stored.
 */
export function billTotals(lines: TotalsLine[], type: OverallDiscountType, value: number | string | null) {
  const round2 = (n: number) => Math.round((n + Number.EPSILON) * 100) / 100
  const gross = lines.map((l) => Number(l.line_total) || 0)
  const lineDisc = lines.map((l, i) => gross[i] * ((Number(l.discount_rate) || 0) / 100))

  const subtotal = gross.reduce((a, b) => a + b, 0)
  const lineDiscounts = lineDisc.reduce((a, b) => a + b, 0)
  const netBase = subtotal - lineDiscounts

  const v = Number(value) || 0
  let overall = type === 'percent' ? round2((netBase * v) / 100) : round2(v)
  overall = netBase > 0 ? Math.min(overall, round2(netBase)) : 0

  let allocated = 0
  let tax = 0
  lines.forEach((l, i) => {
    let share = 0
    if (overall > 0 && netBase > 0) {
      share = i === lines.length - 1 ? round2(overall - allocated) : round2(overall * ((gross[i] - lineDisc[i]) / netBase))
    }
    allocated += share
    tax += (gross[i] - share) * ((Number(l.tax_rate) || 0) / 100)
  })

  return {
    subtotal,
    lineDiscounts,
    overall,
    tax,
    total: subtotal - lineDiscounts - overall + tax,
  }
}
