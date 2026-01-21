/**
 * Format text with semantic color tags
 * Supports: {success}, {error}, {warning}, {accent}, {primary}, {secondary}, {link:url}
 */

export function formatText(text: string): string {
  if (!text || typeof text !== 'string') return ''

  let result = escapeHtml(text)

  // Semantic color tags
  result = result.replace(/\{success\}(.*?)\{\/\}/g, '<span class="fmt-success">$1</span>')
  result = result.replace(/\{error\}(.*?)\{\/\}/g, '<span class="fmt-error">$1</span>')
  result = result.replace(/\{warning\}(.*?)\{\/\}/g, '<span class="fmt-warning">$1</span>')
  result = result.replace(/\{accent\}(.*?)\{\/\}/g, '<span class="fmt-accent">$1</span>')
  result = result.replace(/\{primary\}(.*?)\{\/\}/g, '<span class="fmt-primary">$1</span>')
  result = result.replace(/\{secondary\}(.*?)\{\/\}/g, '<span class="fmt-secondary">$1</span>')

  // Text formatting
  result = result.replace(/\{bold\}(.*?)\{\/\}/g, '<strong>$1</strong>')
  result = result.replace(/\{dim\}(.*?)\{\/\}/g, '<span class="fmt-dim">$1</span>')
  result = result.replace(/\{code\}(.*?)\{\/\}/g, '<code class="fmt-code">$1</code>')

  // Links - sanitize URLs
  result = result.replace(/\{link:(.*?)\}(.*?)\{\/\}/g, (match, url, text) => {
    const sanitizedUrl = sanitizeUrl(url)
    return `<a href="${sanitizedUrl}" class="fmt-link" target="_blank" rel="noopener noreferrer">${text}</a>`
  })

  return result
}

/**
 * Basic HTML escape to prevent XSS
 */
function escapeHtml(text: string): string {
  const div = document.createElement('div')
  div.textContent = text
  return div.innerHTML
}

/**
 * Sanitize URL to prevent javascript: and data: URLs
 */
function sanitizeUrl(url: string): string {
  const trimmed = url.trim().toLowerCase()

  // Block dangerous protocols
  if (
    trimmed.startsWith('javascript:') ||
    trimmed.startsWith('data:') ||
    trimmed.startsWith('vbscript:')
  ) {
    return '#'
  }

  // Allow http, https, mailto, relative paths
  if (
    trimmed.startsWith('http://') ||
    trimmed.startsWith('https://') ||
    trimmed.startsWith('mailto:') ||
    trimmed.startsWith('/') ||
    trimmed.startsWith('./')
  ) {
    return url
  }

  // Default to relative path
  return `/${url}`
}

/**
 * Helper to format money
 */
export function formatMoney(amount: number | string, currency = 'USD'): string {
  const num = typeof amount === 'string' ? parseFloat(amount) : amount
  if (isNaN(num)) return String(amount)

  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currencyDisplay: 'narrowSymbol',
    currency,
  }).format(num)
}

/**
 * Helper to format date
 */
export function formatDate(date: string | Date): string {
  const d = typeof date === 'string' ? new Date(date) : date
  if (isNaN(d.getTime())) return String(date)

  return new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  }).format(d)
}
