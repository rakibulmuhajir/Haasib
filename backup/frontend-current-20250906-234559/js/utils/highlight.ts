export function escapeHtml(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/\"/g, '&quot;')
}

export function highlight(text: string, needle: string): string {
  const n = (needle || '').trim()
  if (!n) return escapeHtml(text)
  const lower = text.toLowerCase()
  const idx = lower.indexOf(n.toLowerCase())
  if (idx === -1) return escapeHtml(text)
  const before = escapeHtml(text.slice(0, idx))
  const match = escapeHtml(text.slice(idx, idx + n.length))
  const after = escapeHtml(text.slice(idx + n.length))
  return `${before}<span class="underline decoration-dotted">${match}</span>${after}`
}
