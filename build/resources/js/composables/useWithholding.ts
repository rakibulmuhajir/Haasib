export type FilerStatus = 'filer' | 'non_filer' | 'unknown'

export interface WhtRateOption {
    id: string
    name: string
    section: string | null
    rate: number | string
    rate_non_filer: number | string | null
    applies_to: 'payments_made' | 'payments_received' | 'both'
}

export interface WhtPreview {
    /** The % that applies, or null when the rate has no value for this filer status. */
    pct: number | null
    wht: number
    /** True when the party is a non-filer and the rate has no non-filer value. */
    missingNonFiler: boolean
    /** True when the non-filer rate is the one being applied. */
    nonFiler: boolean
}

/** Same rule the server applies: non-filer -> non-filer rate (never silently the filer rate). */
export function withholdingFor(rate: WhtRateOption | undefined | null, status: FilerStatus | string | null | undefined, amount: number): WhtPreview {
    if (!rate) return { pct: null, wht: 0, missingNonFiler: false, nonFiler: false }
    const nonFiler = status === 'non_filer'
    if (nonFiler && (rate.rate_non_filer === null || rate.rate_non_filer === undefined || rate.rate_non_filer === '')) {
        return { pct: null, wht: 0, missingNonFiler: true, nonFiler: true }
    }
    const pct = Number(nonFiler ? rate.rate_non_filer : rate.rate)
    return { pct, wht: Math.round((Number(amount) || 0) * pct) / 100, missingNonFiler: false, nonFiler }
}

export function pctText(value: number | string | null | undefined): string {
    if (value === null || value === undefined || value === '') return ''
    return `${Number(value)}%`
}
