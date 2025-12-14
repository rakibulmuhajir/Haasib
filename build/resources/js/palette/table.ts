/**
 * Format data as an ASCII table
 */
export function formatTable(
  rows: string[][],
  headers?: string[],
  footer?: string
): string {
  if (rows.length === 0 && !headers) {
    return '(no results)'
  }

  // Calculate column widths
  const allRows = headers ? [headers, ...rows] : rows
  const colCount = Math.max(...allRows.map(r => r.length))
  const colWidths: number[] = []

  for (let col = 0; col < colCount; col++) {
    let maxWidth = 0
    for (const row of allRows) {
      const cell = stripAnsi(row[col] || '')
      maxWidth = Math.max(maxWidth, cell.length)
    }
    // Cap column width at 40 chars
    colWidths.push(Math.min(maxWidth, 40))
  }

  const lines: string[] = []

  // Top border
  lines.push(formatBorder(colWidths, '┌', '┬', '┐'))

  // Header
  if (headers) {
    lines.push(formatRow(headers, colWidths))
    lines.push(formatBorder(colWidths, '├', '┼', '┤'))
  }

  // Rows
  for (let i = 0; i < rows.length; i++) {
    lines.push(formatRow(rows[i], colWidths))
  }

  // Bottom border
  lines.push(formatBorder(colWidths, '└', '┴', '┘'))

  // Footer
  if (footer) {
    lines.push('')
    lines.push(footer)
  }

  return lines.join('\n')
}

/**
 * Format a single row
 */
function formatRow(cells: string[], widths: number[]): string {
  const parts: string[] = []

  for (let i = 0; i < widths.length; i++) {
    const cell = cells[i] || ''
    const stripped = stripAnsi(cell)
    const padded = stripped.padEnd(widths[i])
    // Truncate if too long
    const final = padded.length > widths[i] 
      ? padded.substring(0, widths[i] - 1) + '…'
      : padded
    parts.push(final)
  }

  return '│ ' + parts.join(' │ ') + ' │'
}

/**
 * Format a border line
 */
function formatBorder(widths: number[], left: string, mid: string, right: string): string {
  const segments = widths.map(w => '─'.repeat(w + 2))
  return left + segments.join(mid) + right
}

/**
 * Strip ANSI codes and our custom tags from text
 */
function stripAnsi(text: string): string {
  // Remove ANSI escape codes
  let result = text.replace(/\x1b\[[0-9;]*m/g, '')
  // Remove our {tag}...{/} format
  result = result.replace(/\{[^}]+\}/g, '')
  return result
}

/**
 * Simple table format (no borders) for compact output
 */
export function formatSimpleTable(
  rows: string[][],
  headers?: string[]
): string {
  if (rows.length === 0) return '(no results)'

  const allRows = headers ? [headers, ...rows] : rows
  const colCount = Math.max(...allRows.map(r => r.length))
  const colWidths: number[] = []

  for (let col = 0; col < colCount; col++) {
    let maxWidth = 0
    for (const row of allRows) {
      const cell = stripAnsi(row[col] || '')
      maxWidth = Math.max(maxWidth, cell.length)
    }
    colWidths.push(Math.min(maxWidth, 40))
  }

  const lines: string[] = []

  if (headers) {
    lines.push(formatSimpleRow(headers, colWidths))
    lines.push(colWidths.map(w => '─'.repeat(w)).join('  '))
  }

  for (const row of rows) {
    lines.push(formatSimpleRow(row, colWidths))
  }

  return lines.join('\n')
}

function formatSimpleRow(cells: string[], widths: number[]): string {
  return cells
    .map((cell, i) => {
      const stripped = stripAnsi(cell || '')
      return stripped.padEnd(widths[i] || 0)
    })
    .join('  ')
}

/**
 * Format key-value pairs (for single record display)
 */
export function formatKeyValue(data: Record<string, unknown>): string {
  const maxKeyLen = Math.max(...Object.keys(data).map(k => k.length))

  return Object.entries(data)
    .map(([key, value]) => {
      const paddedKey = key.padEnd(maxKeyLen)
      const displayValue = formatValue(value)
      return `${paddedKey}  ${displayValue}`
    })
    .join('\n')
}

function formatValue(value: unknown): string {
  if (value === null || value === undefined) return '—'
  if (typeof value === 'boolean') return value ? 'Yes' : 'No'
  if (typeof value === 'number') return value.toLocaleString()
  if (value instanceof Date) return value.toLocaleDateString()
  if (Array.isArray(value)) return value.join(', ')
  return String(value)
}
