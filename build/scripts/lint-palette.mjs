#!/usr/bin/env node
/**
 * Palette ratchet.
 *
 * Roughly half of Haasib's Vue templates bypass the design token layer and
 * reach straight for Tailwind's stock palette (text-zinc-500, bg-green-500).
 * Those utilities are invisible to the ledger skin: retheming a token does
 * nothing to a hardcoded colour, so every one of them is a hole the skin
 * leaks through.
 *
 * This script does not try to fix them. It stops the pile growing while it is
 * being dismantled. The baseline may only ever go down. Adding a violation
 * fails; removing violations and forgetting to lower the baseline also fails,
 * so the number stays honest.
 *
 *   node scripts/lint-palette.mjs           check against the baseline
 *   node scripts/lint-palette.mjs --report  list offenders, worst file first
 *   node scripts/lint-palette.mjs --update  write the current count as baseline
 */

import { readFileSync, writeFileSync, existsSync, readdirSync, statSync } from 'node:fs'
import { join, relative, sep } from 'node:path'
import { fileURLToPath } from 'node:url'

const ROOT = join(fileURLToPath(new URL('.', import.meta.url)), '..')
const BASELINE_FILE = join(ROOT, 'scripts', 'palette-baseline.json')

const SCAN_DIRS = ['resources/js', 'modules']
const SKIP_DIRS = new Set(['node_modules', 'vendor', 'dist', 'build', '.git'])

/**
 * Tailwind's stock colour ramps. Anything numbered from these is a violation
 * in a template — the token layer is meant to be the only source of colour.
 */
const RAMPS = [
    'slate', 'gray', 'zinc', 'neutral', 'stone',
    'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald',
    'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple',
    'fuchsia', 'pink', 'rose',
].join('|')

const PROPS = 'bg|text|border|ring|outline|fill|stroke|from|to|via|decoration|divide|accent|caret|shadow|placeholder'

// Matches text-zinc-500, dark:hover:bg-green-500/20, etc.
const VIOLATION = new RegExp(
    `\\b(?:[a-z-]+:)*(?:${PROPS})-(?:${RAMPS})-\\d{2,3}(?:\\/\\d{1,3})?\\b`,
    'g',
)

/**
 * `bg-white` and friends are the same hole by another name. They carry no ramp
 * number, so the pattern above walked straight past them, and a card set to
 * bg-white stayed white on the dark theme — a colour the skin cannot reach, just
 * like text-zinc-500. Tokens: bg-surface-raised for a card, text-text-primary for
 * ink, and *-primary-foreground for text sitting on a filled control.
 */
const ABSOLUTE = new RegExp(
    // No \b here: the pattern is written with explicit lookarounds so a short
    // utility can never match inside a longer one (bg-white vs bg-white-ish).
    `(?<![a-z0-9-])(?:[a-z-]+:)*(?:${PROPS})-(?:white|black)(?:/[0-9]{1,3})?(?![a-z0-9-])`,
    'g',
)

/**
 * Files allowed to name raw colours. Keep this list short and justified —
 * every entry is a place the skin cannot reach.
 */
const ALLOWED = [
    // Chart series need literal colours; they are data encodings, not chrome.
    // Add paths here ONLY with a comment saying why the token layer can't serve.
]

function isAllowed(relPath) {
    const p = relPath.split(sep).join('/')
    return ALLOWED.some((a) => p === a || p.startsWith(`${a}/`))
}

function walk(dir, out = []) {
    if (!existsSync(dir)) return out
    for (const entry of readdirSync(dir)) {
        if (SKIP_DIRS.has(entry)) continue
        const full = join(dir, entry)
        if (statSync(full).isDirectory()) walk(full, out)
        // .ts as well as .vue: cva variant files such as ui/badge/index.ts hold
        // class strings too, and a .vue-only scan cannot see them.
        else if (entry.endsWith('.vue') || entry.endsWith('.ts')) out.push(full)
    }
    return out
}

function scan() {
    const files = SCAN_DIRS.flatMap((d) => walk(join(ROOT, d)))
    const offenders = []
    let total = 0

    for (const file of files) {
        const rel = relative(ROOT, file)
        if (isAllowed(rel)) continue
        const source = readFileSync(file, 'utf8')
        const matches = [
            ...(source.match(VIOLATION) ?? []),
            ...(source.match(ABSOLUTE) ?? []),
        ]
        if (!matches.length) continue
        offenders.push({ file: rel.split(sep).join('/'), count: matches.length })
        total += matches.length
    }

    offenders.sort((a, b) => b.count - a.count)
    return { total, offenders, scanned: files.length }
}

function readBaseline() {
    if (!existsSync(BASELINE_FILE)) return null
    return JSON.parse(readFileSync(BASELINE_FILE, 'utf8'))
}

function writeBaseline(total, files) {
    writeFileSync(
        BASELINE_FILE,
        `${JSON.stringify({ total, files, note: 'May only decrease. Run --update after removing violations.' }, null, 2)}\n`,
    )
}

const mode = process.argv[2]
const { total, offenders, scanned } = scan()

if (mode === '--report') {
    console.log(`Hardcoded palette utilities: ${total} across ${offenders.length} files (${scanned} scanned)\n`)
    for (const { file, count } of offenders) {
        console.log(`${String(count).padStart(5)}  ${file}`)
    }
    process.exit(0)
}

if (mode === '--update') {
    writeBaseline(total, offenders.length)
    console.log(`Baseline set to ${total} violations across ${offenders.length} files.`)
    process.exit(0)
}

const baseline = readBaseline()

if (!baseline) {
    writeBaseline(total, offenders.length)
    console.log(`No baseline found. Wrote ${total} violations across ${offenders.length} files.`)
    process.exit(0)
}

if (total > baseline.total) {
    console.error(
        `Palette ratchet FAILED.\n\n` +
        `  baseline  ${baseline.total}\n` +
        `  current   ${total}  (+${total - baseline.total})\n\n` +
        `New hardcoded palette utilities were added. Use design tokens instead:\n` +
        `  text-zinc-500   -> text-muted-foreground\n` +
        `  bg-green-500    -> bg-status-success\n` +
        `  border-gray-200 -> border-border\n` +
        `  text-red-600    -> text-status-critical  (adverse only; an ordinary\n` +
        `                     outflow is text-amount-outflow, which is ink)\n\n` +
        `Run 'node scripts/lint-palette.mjs --report' to see where.`,
    )
    process.exit(1)
}

if (total < baseline.total) {
    console.error(
        `Palette ratchet: ${baseline.total - total} violations removed — nice.\n` +
        `Lower the baseline to lock the gain in:\n\n` +
        `  node scripts/lint-palette.mjs --update\n`,
    )
    process.exit(1)
}

console.log(`Palette ratchet OK — ${total} violations, holding at baseline.`)
