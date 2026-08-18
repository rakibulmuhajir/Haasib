# Theming

How Haasib's colours, type and spacing are decided, and how to add a theme without breaking the ones that already work.

> This file previously documented a PrimeVue "Blue Whale" system at `stack/resources/js/styles/themes/blue-whale.css`. That stack, that path and that composable no longer exist. Anything you remember from it is gone.

---

## Two independent axes

They are often confused. They are not the same thing and they compose freely.

| Axis | Set by | Carried on `<html>` | Values |
| --- | --- | --- | --- |
| **Appearance** | the reader | `class="dark"` | `light` · `dark` · `system` |
| **Skin** | the reader (preview) or the product | `data-skin="…"` | `default` · `ledger` · … |

Every skin must work in both appearances. `default` × `dark` and `ledger` × `dark` are separate token blocks, and adding a skin means writing both.

## A skin is a palette, not a second design system

This is the load-bearing decision, so it is worth stating plainly.

A skin restates **colour**, and may restate **radius**, **type family** and **resting density**. It does *not* get to re-decide what red means, whether focus is visible, whether a button floats, or whether a state may be signalled by colour alone. That grammar is house policy. It lives under the bare `[data-skin]` selector in `app.css` and applies to every skin at once.

The reason is verification cost, not purity. Haasib has 193 pages, 20 document states and a full interaction-state matrix per component. Forking the grammar per theme multiplies that surface by the number of themes, and the failures it produces are the quiet kind — a theme that drops a non-colour indicator ships an accessibility bug no screenshot catches, and a theme that renders ordinary outflows in red turns a normal register into a page of alarms.

**One grammar, many palettes.** The realistic future themes are palette-shaped anyway: high-contrast for accessibility, print, and a per-company brand accent if the customer portal is ever white-labelled.

### The grammar, in full

1. **Rules, not elevation.** Borders and spacing separate things. Shadow is reserved for surfaces that genuinely float — dialog, popover, dropdown, sheet, toast. Nothing else.
2. **Three type roles.** Display serif → page titles and major conclusions only. Sans → navigation, controls, forms, table content, body. Mono → tables, references, IDs, metadata. The serif never appears on a button, a field label or a table cell.
3. **Direction is not severity.** An ordinary outflow — rent, salaries, a supplier payment — is **ink with a minus sign**, never red. Red means adverse, overdue, error or destructive, and nothing else.
4. **Every state has a non-colour indicator** — a label, an icon, a rule weight. Colour only ever reinforces.
5. **Amounts obey one contract.** Tabular figures, decimal-aligned, non-wrapping, one negative convention throughout.
6. **Spacing rhythm is 4 / 8 / 12 / 16 / 24 / 40px** and recurs predictably.

---

## Where everything lives

| File | What it holds |
| --- | --- |
| `resources/css/app.css` | All tokens. `:root` / `.dark` defaults, each skin's block, the grammar rules, density contracts, type roles. |
| `config/skins.php` | The skin registry — id, label, description, and the pre-paint page ground. **The only list.** |
| `resources/views/app.blade.php` | Applies the skin and its ground before first paint, from the registry. |
| `app/Http/Middleware/HandleInertiaRequests.php` | Shares the registry (`skins`) and `skinPreview` to the front end. |
| `resources/js/composables/useSkin.ts` | Reads and sets `data-skin`; persists to `localStorage`. |
| `resources/js/composables/useAppearance.ts` | Light / dark / system. |
| `resources/js/composables/useAppearanceToggle.ts` | The same, plus what `system` currently resolves to — what a sun/moon control needs. |
| `resources/js/components/SkinPreviewToggle.vue` | Local-only corner switch that cycles the registry. |
| `resources/js/pages/Design/Index.vue` | The `/design` playground. Local only. |
| `scripts/lint-palette.mjs` | The ratchet that keeps hardcoded colours out. |

---

## The token layer

Two layers, on purpose.

```
Layer 1 — semantic, ours          Layer 2 — generic, shadcn's
--surface-canvas          ←──────  --background
--surface-raised          ←──────  --card, --popover
--surface-sunken          ←──────  --muted
--rule-default            ←──────  --border, --input
--text-primary            ←──────  --foreground
--text-secondary          ←──────  --muted-foreground
--status-critical         ←──────  --destructive
--focus-ring              ←──────  --ring
```

Layer 2 *references* layer 1. Accounting meaning stays independent of the component library's vocabulary: if shadcn renames a token, one line is remapped rather than a palette.

### Layer 1 reference

| Group | Tokens |
| --- | --- |
| Surfaces | `--surface-canvas` `--surface-raised` `--surface-sunken` `--surface-band` |
| Rules | `--rule-subtle` `--rule-default` `--rule-emphasis` |
| Text | `--text-primary` `--text-secondary` `--text-tertiary` `--text-quaternary` `--text-metadata` |
| Status | `--status-info` `--status-attention` `--status-critical` `--status-success` |
| Status contrast | `--status-*-contrast` — the ink that sits **on** a status fill |
| Amounts | `--amount-inflow` `--amount-outflow` `--amount-estimated` `--amount-overdue` |
| Focus | `--focus-ring` |
| Type | `--display-family` `--mono-family` |

Each is exposed as a Tailwind utility: `bg-surface-raised`, `text-text-metadata`, `border-rule-subtle`, `text-amount-outflow`, `bg-status-success text-status-success-contrast`.

**`--status-*-contrast` is not decoration.** A green chip stays green in both appearances, so its label cannot inherit `--foreground` — it would flip out from under the fill and vanish. `text-white` was the same bug wearing a Tailwind name. Every status fill names the ink that goes on it.

---

## Adding a skin

Three steps. Nothing else should be necessary; if it is, the grammar has leaked into a skin and belongs back under `[data-skin]`.

**1. Register it** in `config/skins.php`:

```php
'high-contrast' => [
    'label' => 'High contrast',
    'description' => 'Maximum separation for low-vision readers.',
    'ground' => [
        'light' => 'hsl(0 0% 100%)',
        'dark'  => 'hsl(0 0% 0%)',
    ],
],
```

`ground` is painted inline in `<head>`, before the stylesheet arrives, so a short page does not show default white above and below the app. **It must match that skin's `--surface-canvas`.** Nothing enforces this — change them together.

**2. Write the light block** in `app.css`, next to `[data-skin="ledger"]`. Restate every layer-1 token in the reference table above, then the layer-2 tokens that need it (`--radius`, `--sidebar-*`, `--chart-*`). Copy the ledger block as a starting point; it is complete.

```css
[data-skin="high-contrast"] {
    --surface-canvas: hsl(0 0% 100%);
    /* … */
}
```

**3. Write the dark block**, matching ledger's selector shape:

```css
.dark[data-skin="high-contrast"],
.dark [data-skin="high-contrast"],
[data-skin="high-contrast"].dark {
    /* … */
}
```

That is the whole job. The registry drives the pre-paint script, the ground CSS, the Inertia payload and the preview switch; the grammar, density contracts, radius normalisation, focus ring and reduced-motion handling already apply through `[data-skin]`.

If a skin genuinely needs an exception to the grammar — say a marketing theme that wants its cards to float — override it in that skin's own block, deliberately and in one place, with a comment saying why.

---

## Density

Selected by the **work**, not the person. A reconciliation screen is compact because reconciliation is dense work, not because an accountant is looking at it. There is no persona switch and there will not be one.

| `data-density` | Row | Field | Use |
| --- | --- | --- | --- |
| `comfortable` | 44px | 40px | Default. Owner-facing pages, touch targets. |
| `compact` | 32px | 32px | Reconciliation, categorisation, journals, long registers. |
| `print` | 26px | 26px | Report and document output. |

Components take a `density` prop that sets `data-density` on their root. Any skin resets to `comfortable` unless it says otherwise.

---

## Writing components that survive a reskin

**Use tokens.** Never a Tailwind palette utility, never a raw hex.

```vue
<!-- no -->
<div class="bg-white text-zinc-500 border-gray-200">
<div class="bg-[#1c2c52] text-white">

<!-- yes -->
<div class="bg-surface-raised text-text-secondary border-rule-subtle">
<div class="bg-primary text-primary-foreground">
```

`node scripts/lint-palette.mjs` enforces this. It is a **ratchet**: the baseline in `scripts/palette-baseline.json` may only decrease, and it currently sits at **0**. Run `--report` to list violations, `--update` to lower the baseline after clearing some.

The ratchet catches numbered ramps (`text-zinc-500`) *and* the absolute colours (`bg-white`, `text-black`), which is a fix worth knowing about: for a long time it only matched the numbered form, so files full of `bg-white` passed while rendering pure white cards on the dark theme.

### Four traps, all of which have already bitten

**`@theme inline` needs a `var()` fallback.** Layer-1 tokens are defined only inside a skin's block; `@theme` is global. Without a fallback, `text-status-success` on an unskinned page resolves to nothing and the colour silently disappears. Every mapping in `app.css` carries one.

**`@theme inline` does not emit custom properties.** Hand-written CSS in a component cannot say `var(--color-text-metadata)` — it resolves to nothing. That is why layer 1 is *also* declared for real in `:root`. In scoped CSS, use the layer-1 name: `color: var(--text-metadata)`.

**Tailwind v4 only generates classes it finds in source.** A class assembled at runtime — `` `text-status-${level}` `` — is never generated and silently produces no colour. Write the full class names out, or map through a lookup object containing literal strings.

**Skins go on `<html>`, not a wrapper.** reka-ui portals dialogs, popovers, dropdowns and toasts straight to `document.body`. A skin scoped to page content leaves every overlay in the app unskinned.

---

## Verifying a skin

Run through this before calling one done. It is the same list Tier 0 gates on.

**System**
- `/design` — every component looks native in every state: default, hover, focus, active, selected, disabled, loading, error, warning, success, read-only, empty. Disabled and read-only are not optional; a financial app is full of posted, immutable and calculated values.
- Financial fields are visibly distinguishable: user-entered · system-calculated · posted · estimated · foreign-currency · base-currency-equivalent.
- Toggle the skin on a real page with real data, not just the playground.

**Accessibility**
- Tab the whole page — focus visible on every control.
- Contrast at AA for ink/paper and each status against its `-contrast` token, in both appearances.
- Every state still has its non-colour indicator.
- `prefers-reduced-motion` suppresses animation.
- Touch targets meet minimum size at `comfortable`.

**Robustness**
- Long labels and long currency values: no overflow, no wrap in amounts.
- RTL smoke test — Urdu is RTL — and a text-expansion pass.
- Negative, zero and null amounts render correctly.
- 200% zoom and a 320px viewport with no document-wide horizontal scroll.
