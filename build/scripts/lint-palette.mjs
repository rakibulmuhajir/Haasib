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
import { join, dirname, relative, sep } from 'node:path'
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
    return { total, offenders, scanned: files.length, files }
}

/**
 * -- Dead slots -------------------------------------------------------------
 *
 * `<template #cell-status>` inside `<LedgerRegister>` compiles clean, renders
 * clean and does nothing, if LedgerRegister never defined a `cell-status`
 * slot. Vue's slot mechanism is just a named prop: an unrecognised name is
 * simply never read, so the content that was meant to be a StatusBadge chip
 * never reaches the page, and the fallback content the component itself
 * chose -- usually the raw cell value, lowercase and unstyled -- renders
 * instead. There is no warning, no error and no failed build. Eleven of
 * these shipped before anyone noticed one, because "it rendered something"
 * looked like success at every stage short of actually reading the pixels.
 *
 * This is not a regex over file text like the rules above -- a slot name is
 * only wrong relative to the specific component it was written against, so
 * it needs real (if small) tag tracking: which component element is this
 * `<template #x>` sitting directly inside, and what slots does that
 * component's own template actually declare with `<slot>`.
 */

// Elements Vue/HTML never expects a closing tag for. Tracking them onto the
// open-element stack would desync every ancestor above them the moment a
// page writes `<input>` instead of `<input />`.
const VOID_ELEMENTS = new Set([
    'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
    'link', 'meta', 'param', 'source', 'track', 'wbr',
])

// Blank out a region character-for-character (keeping newlines, so every
// later line number still lines up) rather than deleting it, so `<script>`
// bodies and HTML comments can never be mistaken for template markup -- a
// commented-out `<template #old-slot>` or a `<slot>` mentioned in a code
// comment must not feed either side of this rule.
function blank(text) {
    return text.replace(/[^\n]/g, ' ')
}

function stripNonTemplate(source) {
    return source
        .replace(/<!--[\s\S]*?-->/g, blank)
        .replace(/<script\b[\s\S]*?<\/script>/gi, blank)
}

function lineAt(source, index) {
    let line = 1
    for (let i = 0; i < index; i++) if (source[i] === '\n') line++
    return line
}

// A hand-rolled tag scanner, not a regex, because a component's opening tag
// routinely spans a dozen lines of props (see LedgerRegister call sites) and
// an attribute value can itself contain `>` (a comparison inside a bound
// expression). Walking the string and tracking quote state is the only way
// to find the real end of a tag.
function* iterateTags(source) {
    let i = 0
    const n = source.length
    while (i < n) {
        if (source[i] !== '<') {
            i++
            continue
        }
        const start = i
        let j = i + 1
        let quote = null
        while (j < n) {
            const c = source[j]
            if (quote) {
                if (c === quote) quote = null
            } else if (c === '"' || c === "'") {
                quote = c
            } else if (c === '>') {
                break
            }
            j++
        }
        yield { text: source.slice(start, j + 1), start }
        i = j + 1
    }
}

const OPEN_TAG = /^<([A-Za-z][\w.-]*)/
const CLOSE_TAG = /^<\/\s*([A-Za-z][\w.-]*)/

// `<template #status="...">`, `<template v-slot:status>` and the bare
// `<template v-slot>` / `<template #default>` default-slot spellings.
// `#[expr]` / `v-slot:[expr]` (a dynamic slot NAME at the use site, not the
// definition site) is left unmatched on purpose -- there is no static value
// to check that against, and guessing would risk the false positive this
// rule exists to avoid.
function templateSlotName(attrs) {
    let m = attrs.match(/(?:^|\s)#([\w-]+)/)
    if (m) return m[1]
    m = attrs.match(/(?:^|\s)v-slot:([\w-]+)/)
    if (m) return m[1]
    if (/(?:^|\s)v-slot\b(?!:)/.test(attrs)) return 'default'
    return null
}

// Walks one file's template and returns every `<template #x>` found as a
// direct child of a PascalCase element, paired with that element's tag name.
function findSlotUsages(source) {
    const usages = []
    const stack = []
    for (const { text, start } of iterateTags(stripNonTemplate(source))) {
        const closeMatch = CLOSE_TAG.exec(text)
        if (closeMatch) {
            const name = closeMatch[1]
            for (let k = stack.length - 1; k >= 0; k--) {
                if (stack[k] === name) {
                    stack.length = k
                    break
                }
            }
            continue
        }
        const openMatch = OPEN_TAG.exec(text)
        if (!openMatch) continue
        const name = openMatch[1]
        const selfClosing = /\/\s*>$/.test(text)
        const attrs = text.slice(openMatch[0].length, selfClosing ? -2 : -1)

        if (name === 'template') {
            const slotName = templateSlotName(attrs)
            if (slotName) {
                const parent = stack[stack.length - 1]
                if (parent && /^[A-Z]/.test(parent)) {
                    usages.push({ line: lineAt(source, start), slotName, component: parent })
                }
            }
        }

        if (!selfClosing && !VOID_ELEMENTS.has(name.toLowerCase())) stack.push(name)
    }
    return usages
}

// import Foo from '@/components/Foo.vue'  and  import { Button } from
// '@/components/ui/button' -- the two shapes every page in this repo uses to
// bring a component into scope. Anything else (dynamic imports, globally
// registered components) is out of reach and left alone.
function findImports(source) {
    const imports = new Map()
    const defaultRe = /import\s+([A-Za-z_$][\w$]*)\s+from\s+['"]([^'"]+)['"]/g
    for (const m of source.matchAll(defaultRe)) imports.set(m[1], { path: m[2], named: null })
    const namedRe = /import\s*\{([^}]+)\}\s*from\s+['"]([^'"]+)['"]/g
    for (const m of source.matchAll(namedRe)) {
        for (const part of m[1].split(',')) {
            const piece = part.trim()
            if (!piece) continue
            const [orig, alias] = piece.split(/\s+as\s+/).map((s) => s.trim())
            imports.set(alias || orig, { path: m[2], named: orig })
        }
    }
    return imports
}

// '@/x/y' -> resources/js/x/y ; './x' or '../x' -> resolved against the
// importing file's own directory ; a bare specifier ('vue', 'lucide-vue-next')
// is a package, not a file this rule can read, so it resolves to nothing.
function resolveImportBase(importPath, fromDir) {
    if (importPath.startsWith('@/')) return join(ROOT, 'resources/js', importPath.slice(2))
    if (importPath.startsWith('.')) return join(fromDir, importPath)
    return null
}

// Follows a barrel file's `export { default as Button } from './Button.vue'`
// (or the unaliased `export { default } from ...`) one hop to the real
// component. ui/button, ui/select and the rest of shadcn's primitives are
// all imported this way, and without this step every one of them would be
// unresolvable and silently skipped -- correct, but it would leave the rule
// blind to the components people actually reach for most.
function followBarrel(indexPath, dir, exportedName) {
    if (!existsSync(indexPath)) return null
    const src = readFileSync(indexPath, 'utf8')
    const patterns = [
        new RegExp(`export\\s*\\{\\s*default\\s+as\\s+${exportedName}\\s*\\}\\s*from\\s*['"](\\.[^'"]+)['"]`),
        exportedName === 'default'
            ? /export\s*\{\s*default\s*\}\s*from\s*['"](\.[^'"]+)['"]/
            : null,
    ].filter(Boolean)
    for (const re of patterns) {
        const m = src.match(re)
        if (!m) continue
        const target = join(dir, m[1])
        const candidate = target.endsWith('.vue') ? target : `${target}.vue`
        if (existsSync(candidate)) return candidate
    }
    return null
}

function resolveComponentFile(importInfo, fromDir) {
    const base = resolveImportBase(importInfo.path, fromDir)
    if (!base) return null
    if (base.endsWith('.vue')) return existsSync(base) ? base : null
    if (existsSync(`${base}.vue`)) return `${base}.vue`
    if (existsSync(base) && statSync(base).isDirectory()) {
        const exportedName = importInfo.named ?? 'default'
        for (const idx of ['index.ts', 'index.js']) {
            const found = followBarrel(join(base, idx), base, exportedName)
            if (found) return found
        }
    }
    return null
}

const componentInfoCache = new Map()
const unresolvedComponents = new Set()

// What a single component's own template promises to accept: the literal
// slot names it names, the literal prefixes of any dynamically-named slots
// (LedgerRegister's `cell-`, `header-`, `total-`), and whether it forwards
// slots wholesale -- a dynamic `<slot :name="expr">` with no literal prefix
// at all (bare variable, `$slots` re-forwarding, anything unparseable) means
// the component's real accepted set cannot be known statically, so it is
// marked as accepting anything rather than risked as a false positive.
function getComponentSlotInfo(absPath) {
    if (componentInfoCache.has(absPath)) return componentInfoCache.get(absPath)
    let info
    try {
        const source = stripNonTemplate(readFileSync(absPath, 'utf8'))
        const names = new Set()
        const prefixes = []
        let hasSlot = false
        let acceptsAnything = false
        for (const { text } of iterateTags(source)) {
            const m = /^<slot\b/.exec(text)
            if (!m) continue
            hasSlot = true
            const inner = text.slice(4, /\/\s*>$/.test(text) ? -2 : -1)
            const dynamic = inner.match(/(?::|v-bind:)name\s*=\s*["']([^"']*)["']/)
            if (dynamic) {
                const literal = dynamic[1].match(/^`([^$`]*)\$\{/)
                if (literal && literal[1]) prefixes.push(literal[1])
                else acceptsAnything = true
                continue
            }
            const literalName = inner.match(/(?:^|\s)name\s*=\s*["']([^"']*)["']/)
            names.add(literalName ? literalName[1] : 'default')
        }
        info = { hasSlot, acceptsAnything, names, prefixes }
    } catch {
        info = null
    }
    componentInfoCache.set(absPath, info)
    return info
}

function slotIsOffered(slotName, info) {
    if (info.acceptsAnything) return true
    if (info.names.has(slotName)) return true
    return info.prefixes.some((p) => slotName.startsWith(p))
}

function scanDeadSlots(files) {
    const offenders = []
    let total = 0

    for (const file of files) {
        if (!file.endsWith('.vue')) continue
        const source = readFileSync(file, 'utf8')
        const usages = findSlotUsages(source)
        if (!usages.length) continue

        const imports = findImports(source)
        const findings = []

        for (const { line, slotName, component } of usages) {
            const importInfo = imports.get(component)
            if (!importInfo) continue // not an imported component (built-in, global, unresolvable) -- skip, don't guess

            const absPath = resolveComponentFile(importInfo, dirname(file))
            if (!absPath) {
                unresolvedComponents.add(`${component} (${importInfo.path})`)
                continue
            }

            const info = getComponentSlotInfo(absPath)
            if (!info || !info.hasSlot) continue // no <slot> at all: nothing to prove against

            if (!slotIsOffered(slotName, info)) {
                findings.push({ line, slotName, component })
            }
        }

        if (!findings.length) continue
        const rel = relative(ROOT, file).split(sep).join('/')
        offenders.push({ file: rel, count: findings.length, findings })
        total += findings.length
    }

    offenders.sort((a, b) => b.count - a.count)
    return { total, offenders }
}

/**
 * -- Grammar ratchets -----------------------------------------------------
 *
 * Colour was never the only way a page could render undecided. A page that
 * hand-writes a <table>, or formats its own currency, has re-decided something
 * the base components already decided, and it will drift the moment the base
 * changes. These count the remaining places that happens.
 *
 * They ratchet exactly like the palette count: the number may fall, never
 * rise. Some are deliberately non-zero. `ui/table` is not a violation of taste
 * -- those primitives now carry the register grammar themselves -- but a page
 * on LedgerRegister gets sorting, banding, density, the stacked narrow layout
 * and the totals row for free, so the number should keep falling.
 */
const GRAMMAR = [
    {
        key: 'rawTable',
        label: 'hand-written <table> elements',
        /*
         * Only the two components that ARE the table are allowed one -- plus
         * the Umrah voucher, whose tables are not in its Vue template at all.
         * They are inside the HTML string it hands to a print window, which
         * has no Vue, no components and no stylesheet beyond the one string
         * it is given (resources/js/lib/printSheet.ts). A <table> is the only
         * thing that can be written there, so counting them taught nothing
         * and held the ratchet permanently off zero.
         */
        allow: [
            'resources/js/components/LedgerRegister.vue',
            'resources/js/components/ui/table/Table.vue',
            'modules/Umrah/Resources/js/pages/Umrah/Vouchers/Show.vue',
        ],
        pattern: /<table[\s>]/g,
        fix: 'Use LedgerRegister (or the ui/table primitives) instead of writing a table.',
    },
    {
        key: 'uiTable',
        label: 'pages importing ui/table directly',
        allow: ['resources/js/components/ui/table'],
        pattern: /from ['"][^'"]*components\/ui\/table['"]/g,
        fix: 'Prefer LedgerRegister: it brings sorting, banding, density and the narrow-screen layout with it.',
    },
    {
        key: 'dataTableShim',
        label: 'pages still on the DataTable shim',
        // The shim is gone -- every page imports LedgerRegister directly now.
        // The rule stays as a tripwire: it costs nothing and it catches the
        // day someone reintroduces a second table component by that name.
        allow: [],
        pattern: /from ['"]@\/components\/DataTable\.vue['"]/g,
        fix: 'Import LedgerRegister directly; DataTable exists only to retire.',
    },
    {
        key: 'directionAsSeverity',
        label: 'figures coloured by which way they point',
        allow: ['resources/js/pages/Design/Index.vue'],
        /*
         * A ternary choosing between the good colour and the bad one.
         *
         * This is the single most common way the grammar gets broken, and it
         * always looks reasonable at the call site: net capital positive is
         * green, negative is red; money in is green, money out is red. The
         * result is a product where red means both "you are owed this" and
         * "this is overdue", and the reader has no way to tell which.
         *
         * Colour answers "does this need attention". The sign, the column and
         * the label answer "which way did it go". Some matches here are
         * legitimate -- a genuine pass/fail like "do these two figures
         * reconcile" is a state, not a direction -- which is why this ratchets
         * down rather than failing at zero.
         */
        pattern: /status-(?:success|info)[^'"`]*['"`][^)\n]{0,80}:[^)\n]{0,80}status-critical|status-critical[^'"`]*['"`][^)\n]{0,80}:[^)\n]{0,80}status-(?:success|info)/g,
        fix: 'Ink for the figure, sign for the direction, amber only when someone has to act.',
    },
    {
        key: 'moneyAsText',
        label: 'figures rendered as text instead of MoneyText',
        allow: ['resources/js/components', 'resources/js/pages/Design/Index.vue'],
        /*
         * A page-local `formatMoney` helper interpolated into the template.
         *
         * It looks harmless -- the figure comes out with a currency symbol and
         * two decimals -- but it is a string, so it does not get tabular
         * figures, it does not get the one negative convention, it does not
         * right-align, and it cannot be told apart from a label by anything
         * downstream. Two columns of these will not line up on the decimal.
         */
        /*
         * `formatCurrency` was missing from this list until it was noticed
         * that the count had reached zero while 42 pages still interpolated
         * figures. It is the same helper under another name -- most of them
         * are literally `const formatCurrency = (a, c) => formatMoneyText(a, c)`
         * -- and naming only some of its aliases made the ratchet report a
         * sweep that had not happened. Match the shape, not the vocabulary.
         *
         * currencySymbol is excepted because it returns a glyph, not a figure.
         * `{{ currencySymbol(code) }}` prints "Rs" beside a label and has no
         * amount to give MoneyText. Without the exception the cheapest way to
         * pass this rule is to rename the function, which is a worse name
         * bought to satisfy a linter -- so the exception lives here instead.
         */
        pattern: /\{\{\s*(?:format)?(?:Money|money|Currency|currency|Amount|amount)[A-Za-z]*\(/g,
        // Matched text containing one of these is a glyph, not a figure.
        // Kept as a post-filter rather than a negative lookahead: the
        // lookahead reads as if it should work and does not, because
        // `currency` alone satisfies the alternation and `[A-Za-z]*` then
        // swallows `Symbol`. Filtering the match is what the rule means.
        exclude: /currencySymbol|currencySign/,
        fix: 'Render the figure with <MoneyText :amount :currency /> so alignment, sign and scale stay one decision.',
    },
    {
        key: 'moneyAsFixed',
        label: 'figures printed as a bare .toFixed(2)',
        allow: ['resources/js/components', 'resources/js/lib'],
        /*
         * The third way a figure escapes MoneyText, after formatMoney and
         * formatCurrency. There is no helper to grep for here -- just a number
         * with two decimals glued on, which is why it survived both earlier
         * rules. Every occurrence found when this was written was money: a
         * subtotal, a tax line, a debit, a credit, an allocated amount.
         *
         * .toFixed(2) on a non-money quantity is legitimate, so this ratchets
         * rather than failing at zero.
         */
        pattern: /\{\{[^}]*\.toFixed\(2\)/g,
        fix: 'Render it with <MoneyText :amount :currency /> instead of gluing two decimals onto a number.',
    },
    {
        key: 'statusAsText',
        label: 'record states printed as raw strings',
        allow: ['resources/js/components', 'resources/js/pages/Design/Index.vue'],
        /*
         * A `.status` property interpolated straight into the page, which puts
         * the database's own word on screen -- `partially_paid`, `unposted` --
         * and gives the reader no rule, no weight and no strike to read it by.
         *
         * Deliberately narrow: only a dotted property named exactly `status`.
         * A flash message called `status`, a `statusConfig.label` and a
         * server-sent `gl_status_label` are all something else and are left
         * alone.
         */
        pattern: /\{\{\s*[A-Za-z_][A-Za-z0-9_.]*\.status\s*\}\}/g,
        fix: 'Pass it to <StatusBadge :status /> so the word, the tone and the strike come from one vocabulary.',
    },
    {
        key: 'handRolledMoney',
        label: 'hand-rolled currency formatting',
        allow: ['resources/js/components/MoneyText.vue', 'resources/js/lib'],
        // Intl currency formatting written inline, rather than going through
        // MoneyText, which owns the negative convention and the tabular figures.
        pattern: /style:\s*['"]currency['"]/g,
        fix: 'Render figures through MoneyText so alignment, sign and scale stay one decision.',
    },
    {
        key: 'deadSlot',
        label: 'dead template slots (named for a slot the component does not offer)',
        allow: [],
        // No `pattern` -- scanGrammar() special-cases this key and calls
        // scanDeadSlots() instead, because "is this slot name real" cannot be
        // answered by a regex over one file; it requires reading the target
        // component's own template.
        custom: scanDeadSlots,
        detail: true,
        fix: 'Check the component being slotted into -- rename the slot to one it actually declares with <slot name="...">, or add that slot to the component if the content is meant to land there.',
    },
]

function scanGrammar(files) {
    const results = {}

    for (const rule of GRAMMAR) {
        if (rule.custom) {
            results[rule.key] = rule.custom(files)
            continue
        }

        let total = 0
        const offenders = []

        for (const file of files) {
            const rel = relative(ROOT, file).split(sep).join('/')
            if (rule.allow.some((a) => rel === a || rel.startsWith(a + '/'))) continue
            let matches = readFileSync(file, 'utf8').match(rule.pattern)
            if (!matches) continue
            if (rule.exclude) matches = matches.filter((m) => !rule.exclude.test(m))
            if (!matches.length) continue
            offenders.push({ file: rel, count: matches.length })
            total += matches.length
        }

        offenders.sort((a, b) => b.count - a.count)
        results[rule.key] = { total, offenders }
    }

    return results
}

function readBaseline() {
    if (!existsSync(BASELINE_FILE)) return null
    return JSON.parse(readFileSync(BASELINE_FILE, 'utf8'))
}

function writeBaseline(total, fileCount, grammarCounts) {
    writeFileSync(
        BASELINE_FILE,
        `${JSON.stringify(
            {
                total,
                files: fileCount,
                grammar: grammarCounts,
                note: 'May only decrease. Run --update after removing violations.',
            },
            null,
            2,
        )}\n`,
    )
}

const mode = process.argv[2]
const { total, offenders, scanned, files } = scan()
const grammar = scanGrammar(files)
const grammarCounts = () =>
    Object.fromEntries(GRAMMAR.map((r) => [r.key, grammar[r.key].total]))

if (mode === '--report') {
    console.log(`Hardcoded palette utilities: ${total} across ${offenders.length} files (${scanned} scanned)\n`)
    for (const { file, count } of offenders) {
        console.log(`${String(count).padStart(5)}  ${file}`)
    }
    for (const rule of GRAMMAR) {
        const found = grammar[rule.key]
        console.log(`\n${rule.label}: ${found.total} across ${found.offenders.length} files`)
        if (rule.detail) {
            for (const { file, findings } of found.offenders) {
                for (const { line, slotName, component } of findings) {
                    console.log(`       ${file}:${line}  #${slotName} on <${component}>`)
                }
            }
            continue
        }
        for (const { file, count } of found.offenders.slice(0, 12)) {
            console.log(`${String(count).padStart(5)}  ${file}`)
        }
        if (found.offenders.length > 12) {
            console.log(`        ... and ${found.offenders.length - 12} more`)
        }
    }
    process.exit(0)
}

if (mode === '--update') {
    writeBaseline(total, offenders.length, grammarCounts())
    console.log(`Baseline set to ${total} palette violations across ${offenders.length} files.`)
    for (const rule of GRAMMAR) console.log(`  ${rule.label}: ${grammar[rule.key].total}`)
    process.exit(0)
}

const baseline = readBaseline()

if (!baseline) {
    writeBaseline(total, offenders.length, grammarCounts())
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

/* Grammar ratchets are checked after the palette one, and a rise in any of
   them fails the run the same way. A fall is reported but does not fail --
   unlike the palette count, these move constantly as pages are converted, and
   failing a build for making progress would only teach everyone to run
   --update blindly. */
let grammarFailed = false
const drops = []

for (const rule of GRAMMAR) {
    const current = grammar[rule.key].total
    const base = baseline.grammar?.[rule.key]

    if (base === undefined) continue

    if (current > base) {
        console.error(
            `Grammar ratchet FAILED — ${rule.label}\n\n` +
                `  baseline  ${base}\n` +
                `  current   ${current}  (+${current - base})\n\n` +
                `  ${rule.fix}\n`,
        )
        grammarFailed = true
    } else if (current < base) {
        drops.push(`  ${rule.label}: ${base} -> ${current}`)
    }
}

if (grammarFailed) {
    console.error(`Run 'node scripts/lint-palette.mjs --report' to see where.`)
    process.exit(1)
}

console.log(`Palette ratchet OK — ${total} violations, holding at baseline.`)

if (drops.length) {
    console.log(`\nGrammar ratchets improved:\n${drops.join('\n')}`)
    console.log(`\nLock it in with: node scripts/lint-palette.mjs --update`)
} else {
    console.log(
        `Grammar ratchets OK — ` +
            GRAMMAR.map((r) => `${r.key} ${grammar[r.key].total}`).join(', ') +
            `.`,
    )
}
