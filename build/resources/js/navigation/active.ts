import type { NavGroup, NavItem } from '@/types'
import { toUrl } from '@/lib/utils'

/** Longest path wins, so Stock Movements does not also select Stock Overview. */
export function activeNavHref(groups: NavGroup[], currentUrl: string): string | undefined {
  const path = currentUrl.split(/[?#]/)[0].replace(/\/$/, '')
  const leaves = (items: NavItem[]): NavItem[] => items.flatMap(item => [item, ...leaves(item.children ?? [])])
  return groups.flatMap(group => leaves(group.items))
    .filter(item => item.href)
    .map(item => toUrl(item.href!))
    .filter(href => {
      // View-only users enter Daily Close through History; detail pages still belong there.
      const parent = href.endsWith('/fuel/daily-close/history') ? href.replace(/\/history$/, '') : href
      return path === href || (parent.split('/').filter(Boolean).length > 1 && path.startsWith(`${parent}/`))
    })
    .sort((a, b) => b.length - a.length)[0]
}
