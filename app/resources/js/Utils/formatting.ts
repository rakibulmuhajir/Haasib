type MoneyCurrency = { code?: string; symbol?: string } | null | undefined

export const formatMoney = (amount?: number | string | null, currency?: MoneyCurrency): string => {
  let numericAmount: number | null = null
  if (typeof amount === 'number') {
    numericAmount = amount
  } else if (typeof amount === 'string') {
    const parsed = Number(amount)
    numericAmount = Number.isFinite(parsed) ? parsed : null
  }
  const safeAmount = typeof numericAmount === 'number' && isFinite(numericAmount) ? numericAmount : 0

  // Prefer a provided ISO currency code when available
  const code = currency && typeof currency === 'object' && currency.code ? currency.code : undefined
  if (code) {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: code,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(safeAmount)
  }

  // Fallback to USD formatting and replace symbol if provided
  const symbol = currency && typeof currency === 'object' && currency.symbol ? currency.symbol : '$'
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
    .format(safeAmount)
    .replace(/\$/g, symbol)
}

export const formatDate = (dateString: string): string => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

export const formatDateTime = (dateString: string): string => {
  return new Date(dateString).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
