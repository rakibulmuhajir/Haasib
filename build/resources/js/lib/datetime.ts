export type DateTimeMode = 'date' | 'datetime' | 'time'

type DateInput = string | Date | null | undefined

type FormatOptions = {
  mode?: DateTimeMode
  locale?: string
  fallback?: string
}

const dateOnlyPattern = /^(\d{4})-(\d{2})-(\d{2})/
const dateOnlyExactPattern = /^\d{4}-\d{2}-\d{2}$/
const dateTimePattern = /^\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}/
const isoFractionPattern = /(\.\d{3})\d+(Z|[+-]\d{2}:?\d{2})?$/

export function formatDateTime(value: DateInput, options: FormatOptions = {}): string {
  const fallback = options.fallback ?? '-'
  const mode = options.mode ?? 'datetime'

  if (!value) {
    return fallback
  }

  const date = parseDateTime(value, mode)
  if (!date) {
    return String(value)
  }

  return formatter(mode, options.locale).format(date)
}

export function formatDateTimeForDisplay(value: unknown, key?: string, options: FormatOptions = {}): unknown {
  if (!isDateLikeValue(value, key)) {
    return value
  }

  const mode = options.mode ?? inferDateTimeMode(value, key)
  return formatDateTime(value as DateInput, {
    ...options,
    mode,
  })
}

export function isDateLikeValue(value: unknown, key?: string): value is string | Date {
  if (value instanceof Date) {
    return !Number.isNaN(value.getTime())
  }

  if (typeof value !== 'string' || value.trim() === '') {
    return false
  }

  const trimmed = value.trim()
  if (dateTimePattern.test(trimmed) || dateOnlyExactPattern.test(trimmed)) {
    return parseDateTime(trimmed, inferDateTimeMode(trimmed, key)) !== null
  }

  if (!key) {
    return false
  }

  const normalizedKey = key.toLowerCase()
  if (!/(^|_)(date|time)$|_date$|_at$|date$|time$/.test(normalizedKey)) {
    return false
  }

  return parseDateTime(trimmed, inferDateTimeMode(trimmed, key)) !== null
}

export function inferDateTimeMode(value: unknown, key?: string): DateTimeMode {
  const normalizedKey = key?.toLowerCase() ?? ''
  const stringValue = value instanceof Date ? value.toISOString() : String(value ?? '')

  if (normalizedKey.endsWith('_at') || dateTimePattern.test(stringValue)) {
    return 'datetime'
  }

  if (normalizedKey.includes('time') && !normalizedKey.includes('date')) {
    return 'time'
  }

  return 'date'
}

export function parseDateTime(value: DateInput, mode: DateTimeMode = 'datetime'): Date | null {
  if (!value) {
    return null
  }

  if (value instanceof Date) {
    return Number.isNaN(value.getTime()) ? null : value
  }

  if (mode === 'date') {
    const match = value.match(dateOnlyPattern)
    if (match) {
      return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
    }
  }

  const normalized = value.replace(isoFractionPattern, '$1$2')
  const date = new Date(normalized)

  return Number.isNaN(date.getTime()) ? null : date
}

export function dateTimeTitle(value: DateInput, mode: DateTimeMode = 'datetime', fallback = '-'): string {
  const date = parseDateTime(value, mode)
  if (!date) {
    return value ? String(value) : fallback
  }

  if (mode === 'date') {
    return new Intl.DateTimeFormat('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    }).format(date)
  }

  return new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    second: '2-digit',
    timeZoneName: 'short',
  }).format(date)
}

function formatter(mode: DateTimeMode, locale = 'en-US'): Intl.DateTimeFormat {
  if (mode === 'time') {
    return new Intl.DateTimeFormat(locale, {
      hour: 'numeric',
      minute: '2-digit',
    })
  }

  if (mode === 'date') {
    return new Intl.DateTimeFormat(locale, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    })
  }

  return new Intl.DateTimeFormat(locale, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}
