import { ref } from 'vue'

/**
 * How often this person opened each menu page, per company -- for the Favorites menu.
 * A per-viewer convenience: it lives in this browser only, and the menu works without it.
 */
const KEY = 'nav-visits'
type Counts = Record<string, Record<string, number>>

function load(): Counts {
  try {
    return JSON.parse(localStorage.getItem(KEY) || '{}') as Counts
  } catch {
    return {}
  }
}

const counts = ref<Counts>(typeof window === 'undefined' ? {} : load())

export function recordVisit(slug: string, href: string): void {
  const forCompany = { ...(counts.value[slug] ?? {}) }
  forCompany[href] = (forCompany[href] ?? 0) + 1
  counts.value = { ...counts.value, [slug]: forCompany }
  try {
    localStorage.setItem(KEY, JSON.stringify(counts.value))
  } catch {
    // storage blocked: favorites just stay as they are
  }
}

/** The most visited of the given hrefs, most visited first; never-visited ones left out. */
export function mostVisited(slug: string, hrefs: string[], limit: number): string[] {
  const forCompany = counts.value[slug] ?? {}
  return hrefs
    .filter((href) => (forCompany[href] ?? 0) > 0)
    .sort((a, b) => (forCompany[b] ?? 0) - (forCompany[a] ?? 0))
    .slice(0, limit)
}
