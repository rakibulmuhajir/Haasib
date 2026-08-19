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
    return { total, offenders, scanned: files.length, files }
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
        allow: ['resources/js/components/DataTable.vue'],
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
]

function scanGrammar(files) {
    const results = {}

    for (const rule of GRAMMAR) {
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
