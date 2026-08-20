#!/usr/bin/env node
/**
 * Navigation freeze.
 *
 * The menu is settled. It was arrived at by people who use this application to
 * do their jobs, and it is not a thing a redesign gets to rearrange on its way
 * past. Moving from the sidebar to the header changed how groups are laid out --
 * a group is a caption in a sidebar and a dropdown trigger in a header, so the
 * grouping had to change or an entire module collapsed into one menu. What that
 * work was never allowed to touch is the items themselves.
 *
 * This script draws the line where the agreement drew it. It extracts every
 * `title` and `href` literal from every nav definition and compares the set
 * against a committed snapshot. Regrouping passes. Renaming an item, removing
 * one, or pointing one somewhere else fails, and says which.
 *
 * Group labels are deliberately not snapshotted: those are layout.
 *
 *   node scripts/lint-nav.mjs           check against the snapshot
 *   node scripts/lint-nav.mjs --update  re-record it (only with a decision behind it)
 */
import { readFileSync, writeFileSync, existsSync } from 'node:fs'
import { globSync } from 'node:fs'
import { dirname, join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const snapshotPath = join(root, 'scripts', 'nav-snapshot.json')

const navFiles = [
    'resources/js/navigation/coreNav.ts',
    ...globSync('modules/*/Resources/js/nav.ts', { cwd: root }),
].map((p) => p.split('\\').join('/'))

/**
 * Titles and hrefs, as written. Template literals keep their `${slug}` holes --
 * comparing the source form is the point, since the interpolated value depends
 * on which company you are in.
 */
function extract(source) {
    const titles = [...source.matchAll(/\btitle:\s*(['"`])((?:\\.|(?!\1).)*)\1/g)].map((m) => m[2])
    const hrefs = [...source.matchAll(/\bhref:\s*(['"`])((?:\\.|(?!\1).)*)\1/g)].map((m) => m[2])
    return { titles: titles.sort(), hrefs: hrefs.sort() }
}

const current = {}
for (const file of navFiles) {
    const full = join(root, file)
    if (!existsSync(full)) continue
    current[file] = extract(readFileSync(full, 'utf8'))
}

if (process.argv.includes('--update')) {
    writeFileSync(snapshotPath, `${JSON.stringify(current, null, 2)}\n`)
    console.log(`nav snapshot written: ${relative(root, snapshotPath)}`)
    process.exit(0)
}

if (!existsSync(snapshotPath)) {
    console.error('No nav snapshot. Run: node scripts/lint-nav.mjs --update')
    process.exit(1)
}

const snapshot = JSON.parse(readFileSync(snapshotPath, 'utf8'))
const problems = []

const diff = (file, kind, before, after) => {
    const removed = before.filter((v) => !after.includes(v))
    const added = after.filter((v) => !before.includes(v))
    for (const v of removed) problems.push(`${file}: ${kind} removed or renamed — ${v}`)
    for (const v of added) problems.push(`${file}: ${kind} added — ${v}`)
}

for (const file of Object.keys(snapshot)) {
    if (!current[file]) {
        problems.push(`${file}: nav definition is gone`)
        continue
    }
    diff(file, 'title', snapshot[file].titles, current[file].titles)
    diff(file, 'href', snapshot[file].hrefs, current[file].hrefs)
}

for (const file of Object.keys(current)) {
    if (!snapshot[file]) problems.push(`${file}: new nav definition, not in the snapshot`)
}

if (problems.length) {
    console.error('Navigation changed:\n')
    for (const p of problems) console.error(`  ${p}`)
    console.error(
        '\nMenu items are settled. If this change was actually decided, re-record it:\n' +
            '  node scripts/lint-nav.mjs --update\n',
    )
    process.exit(1)
}

console.log(`nav: ${Object.keys(current).length} definitions unchanged`)
