export function formatDate(value?: string | number | Date | null, locale = 'en-US', options?: Intl.DateTimeFormatOptions): string {
    if (!value) return ''
    const date = value instanceof Date ? value : new Date(value)
    if (Number.isNaN(date.getTime())) {
        return ''
    }
    return date.toLocaleDateString(locale, options ?? { year: 'numeric', month: 'short', day: 'numeric' })
}

export function formatDateTime(value?: string | number | Date | null, locale = 'en-US', options?: Intl.DateTimeFormatOptions): string {
    if (!value) return ''
    const date = value instanceof Date ? value : new Date(value)
    if (Number.isNaN(date.getTime())) {
        return ''
    }
    return date.toLocaleString(locale, options ?? { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

export function formatCurrency(value?: string | number | null, currency = 'USD', locale = 'en-US'): string {
    const amount = typeof value === 'string' ? Number(value) : value
    const safeAmount = Number.isFinite(amount as number) ? (amount as number) : 0
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(safeAmount)
}
