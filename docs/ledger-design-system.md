# The Ledger Design System

How every page in Haasib is supposed to look, why, and which component you reach for so you never have to decide again.

This is the working document for anyone adding a screen. If you are about to write a `<table>`, format a currency figure, or print a status word, read the relevant section first — there is already a component that has made that decision, and a lint rule that will fail your build if you make it again.

Related reading: [`docs/theming.md`](theming.md) for how tokens and skins are wired, [`docs/frontend-experience-contract.md`](frontend-experience-contract.md) for interaction and copy standards.

---

## 1. The idea in one paragraph

Haasib is an accounting application, and accounting has a visual grammar that predates software: ruled paper, banded registers, tabular figures that line up on the decimal, one column for money in and another for money out, a double rule under a total. The app is built on that grammar rather than on a generic dashboard aesthetic. The reference is `artifacts/dashboard-ledger.html`. Everything below exists so that a page renders correctly by default and a developer never has to reproduce the grammar by hand.

**The central rule: no element renders undecided.** If you find yourself choosing an alignment, a colour, or a date format on a page, you are working around the system rather than in it. Find the primitive, or extend it.

---

## 2. The six laws

These apply to every skin, every module, and every page. They are house policy, not theme preference.

**1. Rules, not elevation.** Structure is drawn with borders. Shadow is reserved for things that genuinely float above the page — dialog, popover, dropdown, sheet, toast. A card with a drop shadow is a card that has misunderstood the medium.

**2. Three type roles, strictly separated.**

| Role | Family | Used for |
|---|---|---|
| Display | Zilla Slab (serif) | Page titles, major conclusions |
| Body | Public Sans (sans) | Navigation, controls, forms, prose |
| Figure | IBM Plex Mono | Amounts, references, IDs, metadata, column headers |

The serif never appears on a button, a field label, or a table cell.

**3. Direction is not severity.** This is the law most often broken. Money leaving the company is ordinary — it is ink with a minus sign, not red. Colour means:

- **Ink** — ordinary, whichever way it points.
- **Amber (`attention`)** — a human needs to do something.
- **Red (`critical`)** — genuinely adverse: overdue, failed, destructive.

A genuine pass/fail *is* a state and may use success/critical — "do these two figures reconcile" is a real yes-or-no. "Did money go out" is not.

**4. Every state carries a non-colour indicator.** A label, a rule weight, or a strike-through. Colour only ever reinforces something already legible without it. `StatusBadge` is a rule, a weight and a word before it is a colour.

**5. Amounts obey one contract.** Tabular figures, right-aligned, aligned on the decimal, non-wrapping, one negative convention. This is why `MoneyText` is mandatory.

**6. Spacing rhythm: 4 / 8 / 12 / 16 / 24 / 40px.**

---

## 3. Tokens

Two layers, both in `build/resources/css/app.css`.

**Layer 1 — semantic accounting tokens**, defined under `[data-skin="ledger"]`. These are the vocabulary you use:

```
--surface-canvas   --surface-raised   --surface-sunken   --surface-band
--rule-subtle      --rule-default     --rule-emphasis
--text-primary     --text-secondary   --text-tertiary    --text-metadata
--status-info      --status-attention --status-critical  --status-success
--status-*-soft    --status-*-contrast
--amount-inflow    --amount-outflow   --amount-estimated --amount-overdue
--rule-w-hair (1px)  --rule-w-base (1.5px)  --rule-w-strong (2.5px)
```

**Layer 2 — generic shadcn tokens** (`--background`, `--border`, `--accent`, …) which *reference* layer 1. This is what makes stock shadcn components inherit the ledger look without being rewritten.

In Vue, use the Tailwind utilities generated from these: `bg-surface-band`, `border-rule-default`, `text-text-secondary`, `text-status-attention`. **Never write a raw palette utility** — `bg-green-500`, `text-red-600`, `#19212e`. The palette lint fails on it.

### One skin, on purpose

`build/config/skins.php` registers exactly one skin, `ledger`, applied as a static attribute on `<html>`. The stock theme was removed deliberately: a second theme is somewhere a half-converted page can hide ("it looks fine on default"). The machinery for adding a skin later is intact — one entry in that config plus one token block in `app.css`.

Dark appearance is a separate axis and still exists (`.dark[data-skin="ledger"]`). Every colour you add needs both.

---

## 4. The components

All live in `build/resources/js/components/`.

### `MoneyText.vue` — every currency figure, without exception

The single way an amount reaches the screen. It separates three things that pages habitually confuse:

- `direction` decides the **sign**
- `tone` decides the **colour**
- `scale` decides the **size**

```vue
<MoneyText :amount="invoice.balance" :currency="invoice.currency" />
```

| Prop | Type | Default | Notes |
|---|---|---|---|
| `amount` | `number \| string \| null` | — | Required |
| `currency` | `string` | — | Required. ISO code |
| `locale` | `string` | `'en-US'` | Use `'en-PK'` on documents |
| `direction` | `'auto' \| 'inflow' \| 'outflow'` | `'auto'` | |
| `tone` | `'default' \| 'estimated' \| 'overdue' \| 'muted'` | `'default'` | |
| `negative` | `'minus' \| 'parens'` | `'minus'` | |
| `scale` | `'default' \| 'conclusion'` | `'default'` | `conclusion` is the large hero figure |
| `showCurrency` | `boolean` | `true` | Turn off inside a column already headed with the symbol |
| `dashZero` | `boolean` | `false` | Render zero as `—` |
| `baseAmount` / `baseCurrency` | | | Shows the base-currency equivalent as quiet metadata |
| `fractionDigits` | `number` | currency default | `0` for whole rupees |

> **Watch the vocabulary mismatch.** `direction` takes `inflow`/`outflow`. The register's column `kind` takes `in`/`out`. They are different words for adjacent ideas and passing one where the other belongs is a type error, not a silent bug — but it is the most common one on this codebase.

### `StatusBadge.vue` — every record state

```vue
<StatusBadge :status="bill.status" />
```

Takes the raw server value. `resources/js/lib/status.ts` normalises casing and separators (`PartiallyPaid`, `partially-paid`, `PARTIALLY_PAID` all resolve), then maps it to a label, a tone, and optionally a strike.

**Adding a state:** add an entry to `statusMeta` in `lib/status.ts`. An unrecognised value is never dropped — it is titlecased and shown neutral — so a missing key degrades quietly rather than breaking. That also means missing keys are easy to miss; if you add a status to a model, add it here in the same change.

Labels are plain language. `posted` reads "Recorded", because posting is a bookkeeping verb and the glossary explains it. States that no longer count (`void`, `reversed`, `superseded`, `cancelled`, `deleted`) carry `struck: true` — that strike is the non-colour indicator required by law 4.

### `LedgerRegister.vue` — every table

The banded register. Owns alignment, banding, mono uppercase headers, hover, the double-ruled total row and the density contract.

```vue
<LedgerRegister
  :data="rows"
  :columns="columns"
  key-field="id"
  clickable
  @row-click="open"
>
  <template #empty>Nothing recorded for this period.</template>
  <template #cell-amount="{ row }">
    <MoneyText :amount="row.amount" :currency="currency" />
  </template>
</LedgerRegister>
```

**Column kinds** carry alignment, typeface and formatting so you never set them per page:

```ts
type ColumnKind = 'text' | 'date' | 'ref' | 'in' | 'out' | 'amount' | 'status'
```

`in` and `out` are the separate money-in / money-out columns from the mockup. **They are deliberately identical ink.** The heading says which way the money went; colouring them would repeat the heading and break law 3.

| Prop | Default | Notes |
|---|---|---|
| `data`, `columns` | — | Required |
| `keyField` | `'id'` | A key name, or `(row, index) => string` |
| `expanded` | — | `(row, index) => boolean`; drives the `row-detail` slot |
| `banded` | `true` | Green-bar banding is the default now |
| `hoverable` | `true` | Hover is an outline, not a fill |
| `clickable` | `false` | Only set it when rows actually go somewhere |
| `density` | — | `comfortable` \| `compact` \| `print` |
| `sprockets` | `false` | Continuous-feed margin holes |
| `totals` / `totalsLabel` | — | Double-ruled footer |
| `loading`, `pagination`, `title`, `description` | | |

**Slots:** `cell-<key>`, `total-<key>`, `row-detail`, `empty`, `header`.
**Events:** `row-click`, `sort`, `page-change`.

### `DataTable.vue` — a shim, not a component

`DataTable` now forwards to `LedgerRegister`. It exists so ~46 pages inherited the register grammar without 46 edits landing at once. **New code imports `LedgerRegister` directly.** The `dataTableShim` ratchet counts the remaining importers so the number stays visible.

One behaviour deliberately differs from the old `DataTable`: `striped` used to default to false, so almost every table rendered unbanded. Banding is now on by default; the prop still turns it off.

### `LedgerDocument.vue` — every document

One sheet, parameterised. Used by invoices, bills, credit notes, payments and vendor credits, on screen and in print.

```vue
<LedgerDocument
  doc-type="Vendor Credit"
  :doc-number="credit.credit_number"
  :issuer="issuer"
  :bill-to="creditTo"
  bill-to-label="Credit to"
  :dates="documentDates"
  :lines="documentLines"
  grand-total-label="Credit total"
  :grand-total-amount="credit.amount"
  :currency="currency"
  locale="en-PK"
  :overprint="overprint"
  :show-quantity="false"
/>
```

| Prop | Notes |
|---|---|
| `docType` | What it is called: "Invoice", "Bill" |
| `docNumber` | |
| `issuer` | `DocumentIssuer` — the party who **sent** it, with optional `logoUrl` |
| `billTo` / `billToLabel` | The party who **receives** it |
| `shipTo` / `shipToLabel` | |
| `dates` | `{ label, value }[]` — value is a pre-formatted string |
| `lines` | `DocumentLine[]`: `description`, `detail?`, `quantity?`, `unit?`, `unitPrice?`, `amount` |
| `totals` | `DocumentTotal[]`: `label`, `amount`, `sign?`, `muted?` — subtotal, tax, discount |
| `grandTotalLabel` / `grandTotalAmount` | Required |
| `amountDueLabel` / `amountDueAmount` | What is still owed after part-payment |
| `currency`, `locale` | |
| `overprint` | `'Draft'`, `'Void'` — the diagonal stamp |
| `showQuantity` | `false` when the document has no line quantities |

**Slots:** `logo`, `masthead`, `parties-extra`, `terms`, `footer`.

**Party direction is the thing people get wrong.** On a document *we* issued (invoice, credit note) the issuer is our company letterhead. On a document issued *to* us (bill, vendor credit) the issuer is the vendor and we are the receiving party. Copy from `bills/Show.vue` for the second case, `credit-notes/Show.vue` for the first.

The letterhead itself comes from the server: `app(CompanyLetterhead::class)->forCompany($company)` in the controller's Inertia payload. If a document page renders without an identity block, that call is missing.

### `Derivation.vue` — arithmetic that reaches a conclusion

The dashboard hero. Signed rows in a fixed-width gutter, a double strike rule, then the answer at conclusion scale. This is what makes a page *state* something rather than list eight cards.

```ts
export interface DerivationLine {
    label: string
    amount: number | string | null | undefined
    sign?: '+' | '−' | null
    estimated?: boolean
}

lines: DerivationLine[]
totalLabel: string
totalAmount: number | string | null | undefined
currency: string
locale?: string   // 'en-US'
```

The amounts are as wide as `MoneyText`'s on purpose — a derivation is fed straight from a controller payload, where a figure arrives as a decimal string as often as a number, and narrowing here would only push a cast onto every call site. Both are passed through to `MoneyText`, which owns the parsing.

`sign: null` is the opening line — neither added nor subtracted. `estimated: true` marks a line as not-yet-fact (italic, muted).

### `MetaChip.vue` — the small beige markers

"3 min", "7 days", "15 days late", "ADD-ON". Mono, 10px, uppercase.

```vue
<MetaChip tone="attention">7 days</MetaChip>
<MetaChip tone="neutral" bare>{{ role }}</MetaChip>
```

`tone`: `neutral` | `attention` (beige, the default deadline look) | `late` (solid red, passed) | `info` | `success`. `bare` drops the wash and keeps only the text — for folio refs and IDs.

### `DateTimeText.vue` — every date

```vue
<DateTimeText :value="row.applied_at" mode="date" />
```

`mode`: `date` | `datetime` | `time`. For a pre-formatted string (e.g. `LedgerDocument`'s `dates` prop), use `formatDateTime(value, { mode: 'date' })` from `@/lib/datetime`.

### Others worth knowing

`PageShell` (page frame, title, breadcrumbs, `#actions` slot) · `TotalRow` · `DefinitionList` · `Explain` (glossary popover) · `RelatedActions` (context-aware action cluster) · `EmptyState` · `InlineEditable` + `useInlineEdit()`.

---

## 5. Printing

A printed document leaves the browser, so it cannot reference CSS custom properties. It is built as a standalone HTML string. There are two paths and **one sheet each**, kept deliberately parallel:

| Path | Sheet | Used by |
|---|---|---|
| Browser print / iframe | `resources/js/lib/printSheet.ts` | Umrah voucher `printVoucher()` |
| Server-side PDF (dompdf) | `resources/views/print/sheet.blade.php` | `umrah::vouchers.pdf` |

`printSheet.ts` exports `printFontFaces()` (self-hosted woff2, absolute to origin because the iframe resolves relative URLs against its own empty document) and `printBaseCss()` (palette as `:root` custom properties, page box, table rules, title, section marker, footer note).

The Blade sheet carries the **same values as literal colour**, because dompdf resolves no custom properties. Each rule names the token it stands for in a comment. These two files are the only place print colour is allowed to live — if you are adding a hex value to a print template, you are recreating the bug where the voucher printed in ink and olive while its own PDF printed in blue and grey.

Two things do not carry to the PDF: dompdf ships DejaVu and cannot embed woff2, so the three type roles are held there by weight, size and letter-spacing rather than family.

**Print is always paper.** The dark theme is a screen affordance. A voucher handed to a pilgrim at an airport is ink on white whatever the agent's laptop was set to, so the print sheets deliberately do not follow appearance.

---

## 6. Enforcement

Two scripts, both run from `build/`.

### `node scripts/lint-palette.mjs`

A **ratchet**: each count may fall, never rise. Baseline in `scripts/palette-baseline.json`. Record a new low with `node scripts/lint-palette.mjs --update` — only after the rest of the verification passes.

| Key | Fails on | Current |
|---|---|---|
| *(palette)* | raw palette utilities and hex colours | **0** |
| `moneyAsText` | `{{ formatMoney(…) }}` instead of `MoneyText` | **0** |
| `statusAsText` | `{{ x.status }}` instead of `StatusBadge` | **0** |
| `handRolledMoney` | local `Intl.NumberFormat` currency formatting | **0** |
| `uiTable` | importing `components/ui/table` outside `LedgerRegister` | **1** |
| `rawTable` | a hand-written `<table>` | **6** |
| `dataTableShim` | pages still importing `DataTable` | **46** |
| `directionAsSeverity` | a figure coloured by which way it points | **8** |

**Three of these counts are non-zero on purpose** — read this before "fixing" them:

- `rawTable 6` — all inside the Umrah `voucherHtml()` print builder, which string-builds standalone HTML for an iframe. A Vue component genuinely cannot render there.
- `directionAsSeverity 8` — audited; all eight are genuine pass/fail states, which law 3 permits.
- `dataTableShim 46` — those pages already render the register grammar through the shim. Retiring it is import-renaming with real regression risk (the shim's `keyField` is narrower than the register's) and no visual change. Low priority.

The `uiTable 1` is real: `FuelStation/Onboarding/Index.vue`.

New rules are silently skipped until baselined, so adding a rule and running `--update` in the same pass records whatever the current state is. Tune a new pattern against real `grep` output first — the `statusAsText` pattern is deliberately narrow (a dotted property named exactly `status`) so it does not catch Laravel flash messages, `statusConfig.label`, or server-sent `gl_status_label`.

### `node scripts/lint-nav.mjs`

**The menu is frozen.** 7 definitions, snapshot in `scripts/nav-snapshot.json`. The sidebar-to-header move was allowed to change how groups are *laid out* — a group is a caption in a sidebar and a dropdown trigger in a header — but never the items themselves. Do not add, remove, rename or merge menu items as part of design work.

### The verification quad

Run all four from `build/` before recording a baseline or committing:

```bash
npx vue-tsc --noEmit 2>&1 | grep -v "^resources/js/actions/" | grep -cE "error TS"   # expect 10
node scripts/lint-palette.mjs
node scripts/lint-nav.mjs
npx vite build
```

**10 is the correct typecheck count**, not zero. Those ten are pre-existing and live in `company/Settings.vue`, `company/Show.vue` (×3), `onboarding/BankAccounts.vue`, `partners/Create.vue` (×2), `partners/Edit.vue`, `partners/Show.vue` (×2). If you see 11, you added one. `resources/js/actions/` is generated and excluded.

Add `php artisan test` (expect **96 passed, 483 assertions**) whenever a controller changes, and `php artisan view:cache` when a Blade template changes — it compiles every view and catches syntax errors the test suite will not.

---

## 7. Recipes

### A list page

1. Controller sends rows plus `company.base_currency`.
2. Import `LedgerRegister`, `MoneyText`, `StatusBadge`, `DateTimeText`.
3. Declare columns with a `kind` on each. Do not add alignment classes — the kind carries it.
4. Slot in `MoneyText` for amounts, `StatusBadge` for states, `DateTimeText` for dates.
5. Set `clickable` **only** if rows navigate somewhere. A pointer cursor on a dead-end row is a promise the page cannot keep.
6. Write a `#empty` slot that says what would appear here and, if useful, offers the action that creates one.

### A document page

1. Controller adds `'letterhead' => app(CompanyLetterhead::class)->forCompany($company)` to the payload.
2. Build `issuer` and the receiving party — check the direction (§4).
3. Map records to `DocumentLine[]`. A document with no line items (a credit, a payment) uses its reason or description as the single line and passes `:show-quantity="false"` rather than padding a quantity of 1.
4. Add an `overprint` computed for draft/void.
5. Put terms or notes in the `#terms` slot.

### A dashboard block

`Derivation` for a conclusion → `MetaChip` + item list for what needs attention → `LedgerRegister` for what has been happening. Vocabulary from the mockup: *Where you stand*, *What needs you*, *What's been happening*, *Free to commit*, *Start something*.

### Adding a colour

You almost certainly should not. Check whether an existing semantic token means what you mean. If it genuinely does not, add it to **both** the light and dark ledger blocks in `app.css`, give it a semantic name (what it means, not what colour it is), and never reference it as a raw value.

---

## 8. The reference page

`resources/js/pages/Design/Index.vue` renders the live spec: the type scale, the status vocabulary, the register with both money columns and an expandable row, the derivation block, the chips.

It renders **real components**, not a copy of them. It used to hand-write the register it documented, which meant anyone reading the reference implementation would copy raw table markup — and it needed two lint exemptions to do it. If you extend the system, extend this page in the same change, and do not reintroduce an exemption for it. A spec page that reimplements the thing it documents is a spec page that will drift away from it.

---

## 9. Things that are still open

- `FuelStation/Onboarding/Index.vue` — the last page importing `ui/table` directly, and a long one.
- No demo company has a logo, so `DocumentIssuer.logoUrl` has never rendered with real data.
- Role-based dashboard content is deliberately out of scope; the dashboard is built for the owner view and role gating is a later pass over the same components.
- `demo-crescent-fuel` has 592 journal entries and 96 stock movements but no invoices, payments or customers. Invoices exist only in `demo-meridian-trading`. Seed before verifying any page that lists them — a table nobody has viewed populated is a table nobody has verified.
