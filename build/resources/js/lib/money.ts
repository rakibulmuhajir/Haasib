/**
 * Money formatting.
 *
 * The one contract every amount in Haasib obeys. Kept as a pure function
 * separate from the component so totals, exports, chart labels and tests can
 * reach the same output without mounting anything.
 *
 * The rule this file exists to enforce is that DIRECTION IS NOT SEVERITY.
 * A payment leaving the account is an ordinary event: it renders in ink with a
 * minus sign. Red is reserved for adverse and destructive, and nothing else.
 * So `direction` decides the SIGN and `tone` decides the COLOUR, and neither
 * one is allowed to reach into the other.
 *
 * @see docs/frontend-experience-contract.md
 */

/** Which way the money moved. `auto` trusts the sign already on the number. */
export type MoneyDirection = 'auto' | 'inflow' | 'outflow'

/**
 * How settled the figure is. Not a synonym for direction — an outflow is
 * `default`; an inflow that is 40 days late is `overdue`.
 */
export type MoneyTone = 'default' | 'estimated' | 'overdue' | 'muted'

/**
 * One negative convention throughout. `minus` is the app default; `parens` is
 * the accounting convention and belongs to printed reports, where it is the
 * form a reader expects. Never mix the two on one screen.
 */
export type NegativeConvention = 'minus' | 'parens'

export interface FormatMoneyOptions {
    currency: string
    locale?: string
    direction?: MoneyDirection
    negative?: NegativeConvention
    /** Override the currency's natural decimal places (0 for whole-rupee views). */
    fractionDigits?: number
    /** Render zero as an em dash. Registers are easier to scan without a row of 0.00. */
    dashZero?: boolean
}

export interface MoneyParts {
    /** The currency symbol on its own, so it can be set smaller than the digits. */
    currency: string
    /** Digits and separators. No sign, no symbol, no parentheses. */
    value: string
    /** Leading glyph under the `minus` convention; empty otherwise. */
    sign: string
    /** Wrapping glyphs under the `parens` convention; empty otherwise. */
    open: string
    close: string
    /** True when there was no figure to show — null, undefined, or unparseable. */
    isNil: boolean
    isNegative: boolean
    isZero: boolean
    /** Everything joined, for aria-labels, exports and tests. */
    plain: string
}

const NIL_DISPLAY = '—'

/**
 * Apply direction to a raw number.
 *
 * A bill line of 5000 is an outflow whether or not the API bothered to send it
 * negative, so `outflow` forces the sign rather than flipping it — passing
 * -5000 with direction `outflow` must not double-negate back to positive.
 */
export function applyDirection(n: number, direction: MoneyDirection = 'auto'): number {
    if (direction === 'outflow') return -Math.abs(n)
    if (direction === 'inflow') return Math.abs(n)
    return n
}

export function parseAmount(amount: number | string | null | undefined): number | null {
    if (amount === null || amount === undefined || amount === '') return null
    const n = typeof amount === 'number' ? amount : Number(amount)
    return Number.isFinite(n) ? n : null
}

export function formatMoney(
    amount: number | string | null | undefined,
    options: FormatMoneyOptions,
): MoneyParts {
    const {
        currency,
        locale = 'en-US',
        direction = 'auto',
        negative = 'minus',
        fractionDigits,
        dashZero = false,
    } = options

    const nil = (): MoneyParts => ({
        currency: '',
        value: NIL_DISPLAY,
        sign: '',
        open: '',
        close: '',
        isNil: true,
        isNegative: false,
        isZero: false,
        plain: NIL_DISPLAY,
    })

    const raw = parseAmount(amount)
    if (raw === null) return nil()

    const signed = applyDirection(raw, direction)

    // -0 is arithmetically zero but formats with a minus sign, which reads as a
    // rounding artefact to anyone looking at a column of figures.
    const isZero = signed === 0
    if (isZero && dashZero) return nil()

    const isNegative = signed < 0

    let formatter: Intl.NumberFormat
    try {
        formatter = new Intl.NumberFormat(locale, {
            style: 'currency',
            currency,
            currencyDisplay: 'narrowSymbol',
            ...(fractionDigits === undefined
                ? {}
                : { minimumFractionDigits: fractionDigits, maximumFractionDigits: fractionDigits }),
        })
    } catch {
        // An unknown or malformed currency code must not blank out a figure.
        // Fall back to plain decimal formatting and show the code as given.
        const value = new Intl.NumberFormat(locale, {
            minimumFractionDigits: fractionDigits ?? 2,
            maximumFractionDigits: fractionDigits ?? 2,
        }).format(Math.abs(signed))
        return assemble(currency, value, isNegative, isZero, negative)
    }

    // Format the magnitude, so the sign is ours to place rather than the
    // locale's — mixing Intl's minus with a parenthesised convention in the
    // same column is exactly the inconsistency this contract exists to prevent.
    const parts = formatter.formatToParts(Math.abs(signed))
    const symbol = parts.find((p) => p.type === 'currency')?.value ?? currency
    const value = parts
        .filter((p) => p.type !== 'currency')
        .map((p) => p.value)
        .join('')
        .trim()

    return assemble(symbol, value, isNegative, isZero, negative)
}

function assemble(
    symbol: string,
    value: string,
    isNegative: boolean,
    isZero: boolean,
    negative: NegativeConvention,
): MoneyParts {
    const useParens = isNegative && negative === 'parens'
    const sign = isNegative && negative === 'minus' ? '−' : '' // true minus, not a hyphen
    const open = useParens ? '(' : ''
    const close = useParens ? ')' : ''

    return {
        currency: symbol,
        value,
        sign,
        open,
        close,
        isNil: false,
        isNegative,
        isZero,
        plain: `${open}${sign}${symbol} ${value}${close}`,
    }
}

/**
 * Screen-reader text. A true-minus glyph and a parenthesis are both silent or
 * misread in most screen readers, so the direction is spelled out in words.
 */
export function moneyLabel(parts: MoneyParts, currencyCode: string): string {
    if (parts.isNil) return 'No amount'
    const prefix = parts.isNegative ? 'negative ' : ''
    return `${prefix}${parts.value} ${currencyCode}`
}

/**
 * The same contract, as a plain string.
 *
 * Some places genuinely cannot mount a component — a chart label, a `title`
 * attribute, a row object built in script before it reaches a register. This
 * is for those, and only those. Anything that renders into the DOM should go
 * through MoneyText, which additionally owns alignment, tone and scale.
 *
 * It exists because fifty-odd pages had each written their own
 * `new Intl.NumberFormat(...)`, which meant fifty-odd chances to disagree about
 * the negative glyph, the symbol style and the decimal count. They now all
 * arrive here, so a change to the convention is one edit rather than fifty.
 */
export function formatMoneyText(
    amount: number | string | null | undefined,
    currency: string,
    options: Omit<FormatMoneyOptions, 'currency'> = {},
): string {
    return formatMoney(amount, { currency: currency || 'USD', ...options }).plain
}
