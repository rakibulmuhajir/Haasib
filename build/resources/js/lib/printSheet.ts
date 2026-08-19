/**
 * The print sheet.
 *
 * A printed document leaves the browser, so it cannot reference the app's CSS
 * custom properties -- it is built as a standalone HTML string and handed to an
 * iframe or to dompdf. That is why the Umrah voucher grew its own copy of the
 * palette in hex, and why it would have gone on looking like the app only for
 * as long as nobody changed a token.
 *
 * This module is the one copy. The values below are the ledger skin resolved to
 * static colour, each named for the token it stands in for, so a drift between
 * paper and screen is one grep away instead of invisible.
 *
 * Print is always paper. The dark theme is a screen affordance -- a voucher
 * handed to a pilgrim at an airport is ink on white whatever the agent had
 * their laptop set to -- so this sheet deliberately does not follow it.
 */

/** Ledger tokens, resolved. The comment on each line is the token it mirrors. */
const PALETTE = [
    ['--ink', 'hsl(219 29% 14%)'],          // --text-primary, --rule-emphasis
    ['--ink-soft', 'hsl(215 11% 40%)'],     // --text-secondary
    ['--band', 'hsl(88 33% 90%)'],          // --surface-band
    ['--rule', 'hsl(90 7% 84%)'],           // --rule-default
    ['--mark', 'hsl(4 63% 45%)'],           // the ledger's one accent red
    ['--rule-w-hair', '1px'],
    ['--rule-w-base', '1.5px'],
    ['--rule-w-strong', '2.5px'],
]

/**
 * Self-hosted faces, absolute to the current origin because the iframe resolves
 * relative URLs against its own (empty) document.
 */
export function printFontFaces(): string {
    const origin = window.location.origin
    const face = (family: string, weight: number, file: string) =>
        `@font-face { font-family: "${family}"; font-style: normal; font-weight: ${weight}; font-display: swap; src: url("${origin}/fonts/${file}.woff2") format("woff2"); }`

    return [
        face('Public Sans', 400, 'public-sans-latin-400-normal'),
        face('Public Sans', 500, 'public-sans-latin-500-normal'),
        face('Public Sans', 600, 'public-sans-latin-600-normal'),
        face('Public Sans', 700, 'public-sans-latin-700-normal'),
        face('Zilla Slab', 600, 'zilla-slab-latin-600-normal'),
        face('Zilla Slab', 700, 'zilla-slab-latin-700-normal'),
        face('IBM Plex Mono', 400, 'ibm-plex-mono-latin-400-normal'),
        face('IBM Plex Mono', 500, 'ibm-plex-mono-latin-500-normal'),
        face('IBM Plex Mono', 600, 'ibm-plex-mono-latin-600-normal'),
    ].join('\n')
}

/**
 * Everything a printed document gets before it says anything about itself:
 * the faces, the palette, the page box, and the grammar's three type roles
 * applied to the elements every document has -- a ruled title, mono uppercase
 * column headers, hairline-ruled cells, a banded section marker, a footer note.
 *
 * A document adds only what is particular to it. If it is restating the table
 * rules here, it has stopped being a document and started being a stylesheet.
 */
export function printBaseCss(): string {
    const vars = PALETTE.map(([name, value]) => `    ${name}: ${value};`).join('\n')

    return `${printFontFaces()}
:root {
${vars}
}
@page { margin: 10mm; size: A4; }
* { box-sizing: border-box; }
body {
    color: var(--ink);
    font-family: "Public Sans", Helvetica, Arial, sans-serif;
    font-size: 9px;
    line-height: 1.25;
    margin: 0;
}

/* Figures line up on the decimal wherever they land. */
table { border-collapse: collapse; font-variant-numeric: tabular-nums; width: 100%; }
th, td {
    border: var(--rule-w-hair) solid var(--rule);
    font-size: 8px;
    padding: 3px 4px;
    text-align: left;
    vertical-align: top;
}
th {
    background: none;
    border-bottom: var(--rule-w-base) solid var(--ink);
    color: var(--ink-soft);
    font-family: "IBM Plex Mono", ui-monospace, monospace;
    font-size: 7px;
    font-weight: 500;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

/* The serif appears on the title and the section markers and nowhere else. */
.document-title {
    border-bottom: var(--rule-w-strong) solid var(--ink);
    border-top: var(--rule-w-base) solid var(--ink);
    font-family: "Zilla Slab", Georgia, serif;
    font-size: 16px;
    font-weight: 700;
    letter-spacing: .4px;
    margin: 4px 0;
    padding: 3px;
    text-align: center;
    text-transform: uppercase;
}
.section-title {
    background: var(--band);
    border: var(--rule-w-hair) solid var(--ink);
    font-family: "Zilla Slab", Georgia, serif;
    font-size: 10px;
    font-weight: 700;
    margin-top: 5px;
    padding: 2px 4px;
    text-align: center;
    text-transform: uppercase;
}

.label {
    color: var(--ink-soft);
    display: block;
    font-family: "IBM Plex Mono", ui-monospace, monospace;
    font-size: 7px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.secondary { color: var(--ink-soft); font-size: 8px; }
.primary { font-weight: 700; }
.footer-note {
    border-top: var(--rule-w-hair) solid var(--ink);
    font-size: 8px;
    margin-top: 6px;
    padding-top: 4px;
}`
}
