# Frontend visual sweep — brief for Luna

**Date:** 2026-08-23
**Scope:** Look at pages. Do they look right, do things overlap, are the styles wrong.
**Not in scope:** behaviour, correctness, permissions, arithmetic. If a number is wrong but sits neatly, that is someone else's bug. If a number is right but overlaps the column beside it, that is yours.

---

## Why this pass exists

The codebase has automated ratchets that currently all read zero:

```
rawTable 0, uiTable 0, dataTableShim 0, directionAsSeverity 6, moneyAsText 0,
moneyAsFixed 0, statusAsText 0, statusSlotAsText 0, handRolledMoney 0, deadSlot 0
```

Those prove every page *imports the right primitives*. They prove nothing about whether the result *looks* right. A page can use `MoneyText`, `StatusBadge` and the shared table on every element and still be unreadable — columns colliding at 1280px, a header wrapping onto three lines, a card that runs under the sidebar, two panels with different padding sitting side by side.

**That gap is the whole job.** Do not re-check what lint already covers. Look at what lint cannot see.

---

## The yardstick

The app uses one design system — "ledger paper". Read `docs/ledger-design-system.md` if you want the full version. The six rules a page can visibly break:

1. **Rules, not elevation.** Shadows belong only on things that genuinely float: dialog, popover, dropdown, sheet, toast. A shadow on a card or a table is wrong.
2. **Three type roles.** Serif (Zilla Slab) for page titles and major conclusions **only**. Sans (Public Sans) for navigation, controls, forms, body. Mono (IBM Plex Mono) for figures, references, IDs, column headers. **A serif on a button, a field label or a table cell is a defect.**
3. **Direction is not severity.** An ordinary outflow is ink with a minus sign. Amber means needs attention. Red means adverse — overdue, error, destructive. **Red on an ordinary negative number is a defect.** A cancellation that made money is negative and must not be red.
4. **Every state has a non-colour indicator** — a label, a rule weight, a strike. Never colour alone.
5. **Amounts:** tabular figures, decimal-aligned, right-aligned, non-wrapping, one negative convention throughout.
6. **Spacing rhythm 4 / 8 / 12 / 16 / 24 / 40px.** Two panels side by side with visibly different padding is a defect.

---

## What to look for

Six defect classes, in the order they are worth your time:

| # | Class | What it looks like |
|---|---|---|
| 1 | **Overlap / collision** | Text over text, a control under a sticky header, a table column running into the next, content beneath the sidebar or a footer bar |
| 2 | **Overflow** | Horizontal scrollbar on the whole page (a table may scroll inside its own container — the page body may not), a figure wrapping mid-number, a long buyer name pushing a column off-screen |
| 3 | **Inconsistency between sibling pages** | Invoices and Bills are the same kind of page — do they align amounts the same way, use the same status treatment, the same column order, the same empty state? Same for the four `*/Show.vue` detail pages |
| 4 | **Wrong style for the role** | Serif on a button or table cell · shadow on a static card · red on an ordinary negative · a status as bare lowercase text instead of a chip |
| 5 | **Cramped or unbalanced layout** | Mismatched padding between adjacent panels, a two-column grid that collapses badly, a heading with no breathing room, a lone control stranded in whitespace |
| 6 | **Empty and long states** | What does the page look like with zero rows? With a 40-character customer name? With a 10-digit amount? |

---

## Viewports

Check each page at **1280px** first (the common case). Then for anything on the priority list, also:

- **375px** — phone. Tables should become cards or scroll inside themselves.
- **768px** — tablet, the width where two-column grids usually break.
- **1920px** — does the content stretch absurdly wide or stay in a sensible measure?
- **200% zoom at 1280px** — the accessibility case, and where overlap shows up first.

Also check **dark theme** on anything you flag, and give **Urdu / RTL** a smoke test on two or three pages — mirrored layout is where alignment assumptions break.

---

## Priority list

202 page files exist. Do not walk all of them. Work down this list and stop when you run out of time — it is ordered by likelihood of finding something real.

### Tier 1 — biggest and most complex (size correlates with layout risk)

| Page | Why suspect |
|---|---|
| `FuelStation/DailyClose/Create.vue` | 135k — by far the largest page in the app |
| `FuelStation/Onboarding/Index.vue` | 116k — multi-step wizard |
| `company/Show.vue` | 95k |
| `Umrah/Groups/Create.vue` | 83k |
| `Umrah/Groups/Show.vue` | 72k |
| `Umrah/Vouchers/Create.vue` | 54k |
| `Umrah/Vouchers/Show.vue` | 50k — known to be 1243 lines and scheduled for a rebuild; expect problems and report them anyway so the rebuild has a list |

### Tier 2 — sibling pages that must agree with each other

Walk these as **sets**, comparing them against one another rather than judging each alone. Divergence between siblings is the single most common finding.

- **Registers:** `invoices/Index` · `bills/Index` · `payments/Index` · `credit-notes/Index` · `vendor-credits/Index` · `customers/Index` · `vendors/Index`
- **Detail pages:** `customers/Show` · `vendors/Show` · `bills/Show` · `Employees/Show` · `fiscal-years/Show`
- **Settings pages:** `settings/*` · `company/Settings` · `tax/Settings` · `FuelStation/Settings/Index` · `Umrah/Settings/*`

### Tier 3 — newly built this session, never seen by a human

The Umrah ticketing feature shipped today with tests but **no visual review at all**. Four pages:

- `Umrah/Tickets/Index.vue` — the bookings register
- `Umrah/Tickets/Create.vue` — the booking form
- `Umrah/Tickets/Show.vue` — booking detail
- `Umrah/Tickets/CancelDialog.vue` — a shadcn dialog with a live cost preview

Specific things to check here:
- The cancellation cost preview goes **negative** when the supplier returned more than the buyer got back. That is a **gain**. Confirm it is ink with a minus sign and **not red**.
- Sign in as an **agent** (not owner/manager). Supplier cost, commission, the bill link and the supplier name must be **absent** — not blank, not zero, not greyed. Confirm the layout doesn't leave a hole where they were.
- The three new reports: `ticket-sales`, `ticket-supplier-reconciliation`, `ticket-cancellations`, under Umrah → Reports.

### Tier 4 — flagged in code review, visual state unknown

- `bills/Index.vue` — the `#mobile-card` slot hand-rolls its own Badge instead of using `StatusBadge`. **Check at 375px specifically.**
- `Periods/Show.vue` — a period-level Badge sits outside a cell slot; may be misplaced.
- `FuelStation/Sales/Form.vue` — error display indexes `[0]` into a string, so a validation error may render as a single character.
- `FuelStation/FuelReceipts/Index.vue` — believed dead/unreachable. If you can reach it, that is itself the finding.
- `stock/*` — stock movements historically painted ordinary outflow red. Confirm that is gone.
- `FuelStation/DailyClose` history — variance was once blue for favourable and red for adverse; both should now be ink with the sign carrying direction.

---

## Known already — do not re-report

These are logged and scheduled. Note them only if they look *worse* than described:

- `Umrah/Vouchers/Show.vue` is oversized and awaiting a rebuild onto the shared document component.
- ~81 hand-rolled card footers and ~34 hand-rolled loading spinners exist across the app instead of the shared components. You will see minor inconsistency in footers and spinners; it is known. **A footer that actually overlaps or breaks layout is still worth reporting.**
- `AmountInput.vue` carries its own hardcoded currency config.
- The `Derivation` component (signed rows, double strike rule, large conclusion figure) is built but not yet adopted on the dashboard.

---

## How to report a finding

One finding per entry. Keep it short:

```
PAGE:      Umrah/Tickets/Show.vue
VIEWPORT:  768px, light theme
WHAT:      The passenger name column runs under the fare column
EXPECTED:  Columns stay separate; long names truncate or wrap within their cell
SCREENSHOT: <attach>
SEVERITY:  blocks reading / looks wrong / minor polish
```

Three severities only:

- **Blocks reading** — you cannot get the information off the page. Fix before deploy.
- **Looks wrong** — readable but visibly breaks the design system. Fix soon.
- **Minor polish** — a few pixels, slight inconsistency. Batch it.

If something looks wrong but you are not sure it breaks a rule, **report it anyway and say you are unsure.** A false positive costs a minute to dismiss; a missed collision ships.

---

## Housekeeping

Earlier QA left records in the system that should be cleaned up when convenient: `UGR-00005`, `UPM-00019`, `UPM-00017`, `URF-00001` through `URF-00005`.

---

## Running the app

```bash
cd build
php artisan octane:start --server=frankenphp --port=9001 --watch
npm run dev
```

Then `http://localhost:9001`. Vite serves assets on 5173 — both must be running.
