# Command Palette Redesign

**Status:** design, awaiting approval · 2026-10-21
**Paths** are relative to `build/` unless they start with `docs/`.

## 1. Why

The palette we have today is the oldest build: a blue terminal look, built from about 1,400 lines of hand-written CSS in `resources/js/components/palette/CommandPalette.vue`. It works, but:

- **It reads as a developer tool.** Commands follow `entity verb --flag` grammar.
- **It feels dated** next to the Ledger design system, so the owner doesn't like it.
- **It is really two systems in one box:**
  - the generic CommandBus catalog (`invoice create …`);
  - a hard-coded quick-entry parser (`resources/js/palette/quick-entry.ts`, eight kinds such as `paid 8000 to …`).

  A module can only reach the first one.

Most daily users are an accounting team that needs it to feel as plain as a search box. Power users use it too, and need it to be fast. One box has to serve both.

## 2. Goals

1. **Sentence with blanks.** A command becomes an English sentence with blanks in it. For example: `Pay [vendor] [amount] from [account] on [date] [note]`.
2. **Enter-first.**
   - Enter fills the current blank and moves to the next *required* blank.
   - When nothing required is missing, the preview is already showing, and Enter posts.
   - If Enter is pressed while a required blank is still empty, nothing posts. Every missing required blank turns red, and the cursor goes to the first one.
3. **Tab / Shift+Tab** move between all blanks, optional ones included, so a default can be changed or a note added.
4. **Typing it all at once works.** `pay ali 1000 from cash` fills the blanks while you type and lands on the preview. This is the power-user path, and nobody has to learn it.
5. **Plain preview.** It shows who, what, how much, from or into where (with the balance before → after when we have it), and when. **It never shows debit/credit lines.**
6. **Standard English command words**, the same for every company (§5).
7. **Plug and play.** A module declares its commands in one place. They appear for companies that have the module turned on and the permission to use them. Adding a module never touches palette code.
8. **Ledger look.** It uses Shadcn components and the existing tokens, works in light and dark, and follows `docs/ledger-design-system.md`.

## 3. Non-goals

- No Urdu or other languages, and no per-company custom words.
- No typing of line items or other nested arrays. A command that needs them opens its page (the existing `form` / `form_url` behaviour).
- No change to how `/api/palette/execute` and `CommandController` authorise or run commands.
- No new AI or fuzzy natural-language understanding. Matching is driven by the declared sentence and stays predictable.

## 4. Anti-goals: it fails if any of these happen, even when it technically works

- Debit/credit, account codes or `--flags` show up anywhere an accountant looks.
- A name is guessed. A name matching several records must come back as choices, and one matching none must come back as an error. This is the current quick-entry rule, and it stays.
- Enter posts something the user hasn't seen previewed.
- Money going out is shown in red. Ledger law 3: direction is not severity.
- A module's commands leak to a company that doesn't have the module. `CoreIndependenceTest` must stay green.
- UI copy runs longer than a few words, for example "Amount needed" or "Posted".

## 5. Command words

The first word is always a verb, the way people say it out loud.

| Kind | Words | Runs |
|---|---|---|
| Money | `pay` | `bill_payment.create` (vendor; open-bill choice as today) |
| | `receive` | `payment.create` (customer) |
| | `spend` | `expense.create` |
| | `transfer` | `bank_transaction.create` kind transfer |
| | `deposit`, `withdraw`, `bank charge`, `sale` | as today's quick entry *(kept: my call, they already exist)* |
| Documents | `invoice`, `bill`, `credit` *(no quotes feature exists yet; `quote` waits for it)* | the matching create action, or its form page when it needs line items |
| Records | `add customer`, `add vendor`, `add item`, `add account` | the `*.create` actions |
| Look up | `find` (or a bare name), `balance`, `report`, `go` | search, account balance, report pages, page navigation |
| Fix | `void`, `undo` | existing void/reverse actions; `undo` = undo the last posting |
| Modules | their own (fuel: `card charge`, `close day`) | declared by the module |

**Rules:**
- **One word per thing:** company, vendor, customer (see `one-word-per-entity`).
- **Aliases** are a hidden power-user extra, matched but never shown as the main word. Examples: `inv`, `exp`, `rcv`, and the old `paid`, `received` and `expense`.
- **Text with no verb is a search** across customers, vendors, invoices, bills and pages. Choosing a result opens it.

## 6. How it works

### 6.1 A command is a declared sentence (server)

Today a palette command *is* a CommandBus key. That can't express `pay`, `deposit` and `withdraw` sharing one action with different fixed params. So a **palette command** becomes its own small declaration that *points at* a CommandBus action:

```php
PaletteCommand::make('pay')
    ->label('Pay a vendor')
    ->aliases(['paid'])
    ->sentence('Pay {vendor} {amount} from {account?} on {date?} {note?}')
    ->slot('vendor', Slot::search('vendor'))
    ->slot('account', Slot::account('cash_or_bank')->defaultFrom('pay_account_id'))
    ->slot('date', Slot::date()->default('today'))
    ->runs('bill_payment.create', fn (array $s) => [ /* slots → action params */ ])
    ->preview()                          // dry-run through PalettePreview
    ->summary('Paid {vendor} {amount}')  // the line shown after posting
    ->opens('/{company}/bill-payments/{id}')
    ->undo('bill_payment.void');
```

- **Slot types:**
  - `search(entity)` resolves through the existing `PaletteSearchSources` registry;
  - `amount`;
  - `account(kind)`;
  - `date`, which takes today, yesterday, tomorrow, or "14 oct" against the company date, as now;
  - `choice(enum)`;
  - `text`.
- **`?` marks an optional slot.** Required slots come from the sentence, and the action's `rules()` stay the final judge on execute.
- **Keywords in the sentence** (`from`, `to`, `into`, `on`, `for`) are the anchors the matcher uses when someone types it all at once.

### 6.2 Collection: plug and play

- `ModuleManifest` gains `paletteCommands(): array`, with a default of `[]`. Core supplies its own list from a core provider (my call: `app/Support/Palette/CorePaletteCommands.php`). Each module returns its list from its manifest.
- `PaletteCatalog::for($user, $company)` becomes the one collector. It:
  1. gathers declared commands from core and from every manifest where `isEnabledFor($company)`;
  2. drops any whose target action's `permission()` the user lacks;
  3. adds a **generic command** for every remaining CommandBus action that no declared command points at. The generic command is built from `rules()` as now, with a sentence auto-made from the description (for example `Create item category {name}`).

  So a brand-new module action still works in the palette with zero effort, and declaring a sentence just makes it nicer.
- The catalog is sent once when the palette opens. The client keeps it until the company changes or the page reloads. Permission and module changes show on the next open.
- One bad declaration is skipped and logged and never breaks the catalog. This is the existing behaviour, kept.
- **Phase-out of the old path:** `DescribesPalette` stays for the generic commands' hints, so nothing breaks while migrating. The hand-written `schemas.ts` and the `quick-entry.ts` kinds are replaced by declared commands and then deleted.

### 6.3 Matching (client)

- **One matcher, driven by the sentences** from the catalog. It replaces both `parser.ts`/`grammar.ts` and `quick-entry.ts`.
- **The first word** picks the command (frecency breaks ties). Then tokens fill slots in sentence order, with the keyword anchors placing them.
- **A number** fills the next empty amount slot. A date word fills the date slot.
- **Names** resolve through search. Several matches show as choices under the blank, and no match marks the blank red with "Not found".
- **Partial input is normal.** Whatever didn't match becomes the blanks still to fill.

### 6.4 Preview and post

- When every required blank is filled, the client asks `POST /api/palette/preview` for previewable commands, as today.
- The response gains a **card**: `who`, `amount`, `account` with before → after, `date`, `note`. The existing journal-line output stays for tests and debugging but is not rendered.
- A non-previewable command builds its card from the slot values alone, with no balance line.
- Enter posts through `/api/palette/execute` with the existing idempotency key.
- **After posting:**
  - one line appears: `✓ Paid Ali Traders 1,000 · Open · Undo`;
  - the input clears and the palette **stays open** for the next entry;
  - Esc closes it.

### 6.5 Screen

The approved mockup is option A. The files are in `.superpowers/brainstorm/1074-1792548952/content/` (`enter-flow.html`, `preview-v2.html`).

- **Overlay:** a centred dialog using Shadcn `Dialog`. It is the floating element, so it gets a shadow. The input row sits on top.
- **Input row:** the verb in body weight 600, then the blanks as inline chips with four states:
  - empty (dashed);
  - current (accent outline);
  - filled (quiet);
  - missing (critical).

  Amounts and dates in the chips use the Figure font (Plex Mono).
- **Under the input** comes one of these:
  - the suggestion list (commands, or choices for the current blank);
  - the preview card;
  - the result line.
- **Footer:** key hints (`Enter` next/post, `Tab` edit, `Esc` back) and the state (`2 of 4`, `Ready`, `Amount needed`).
- **Empty state:** recent and frequent commands (the existing `frecency.ts`), plus `↑` to recall the last command.

### 6.6 Keys

| Key | Does |
|---|---|
| Ctrl/⌘ K, and the header search | Open |
| Enter | Pick the suggestion → next required blank → post (when ready) |
| Tab / Shift+Tab | Next / previous blank, optional ones included |
| ↑ / ↓ | Move through suggestions; `↑` on an empty input recalls the last command |
| Esc | Leave the blank → clear the input → close |
| Ctrl/⌘ Z after a post | Undo it, same as the Undo button |

## 7. Constraints

These are repo rules from `CLAUDE.md`, plus what this design must keep:

- Vue is `<script setup lang="ts">`. Use Shadcn components and no raw `<input>` or `<button>`. Use no `fetch`/axios except where the palette already talks to `/api/palette/*`. Those JSON endpoints are not Inertia pages.
- Core never imports FuelStation or Umrah.
- The idempotency, undo and preview allow-list rules in `PalettePreview` all still hold.
- Write tests but **don't run them unasked**. Use one test process at a time. Never run `migrate:fresh`.
- Every quick-entry line that works today must still work, with the same result.

## 8. Left to the builder

- **How `CommandPalette.vue` is split** into components, and whether to use reka-ui's listbox/combobox under Shadcn or a small custom list.
- **The exact PHP builder API names** and where the per-module command lists live inside each module.
- **How generic sentences are worded** from action descriptions.
- **The order of migration:** which declared commands go first, and when `schemas.ts`/`quick-entry.ts` are deleted. My suggestion: build the new shell and matcher with the eight quick-entry commands first, then the documents and records, then module commands, then delete the old files.
- **Animation and small spacing details** within the Ledger system.

## 9. Settled after builder review

1. **Commands resolve on the server, not in the browser.** *(Revised while planning; supersedes the declarative-mapping idea.)* The browser only splits the typed text into slot texts using the sentence. It sends `{command, slots}` to a new `POST /api/palette/resolve`, which returns each slot's state (resolved / choices / not found / missing) and, when all required slots are resolved, the `key`, `params` and the plain `card`. The browser then calls the existing preview and execute endpoints unchanged. Why: `pay` settles open bills oldest-first, `bank charge` splits bank from note, accounts fall back through a chain — that is logic, not a mapping, and keeping it in PHP means a module adds commands with PHP only, no module JavaScript. Each command is a small PHP class (§6.1's fluent example becomes a class with the same pieces).
2. **Defaults and fallbacks live in the command's PHP resolve step**, ported exactly from `resolveQuickEntry` (setting if still valid and of the right kind → cash drawer → default bank), with the same error texts ("Add a bank account first", etc.).
3. **`quick-entry.ts` is the spec to port, not just a reference.**
   - Its behaviours become slot types:
     - `text` for the expense "what" (free text, no lookup);
     - a bank-prefix + note slot for `bank charge`;
     - an open-bill choice after the vendor for `pay`;
     - `redirect` opening for `sale`;
     - a date phrase allowed anywhere in the line.
   - **Parity check:**
     - every case in `tests/js/palette/quick-entry.spec.ts` is ported: parsing cases to the new matcher's vitest, resolving cases to a Pest test of `/api/palette/resolve`, none dropped;
     - `tests/Feature/Accounting/QuickEntryDefaultsTest.php` and `tests/Feature/Palette/*` stay green.
4. **Required slots per command are exactly what today's quick entry requires** (the `usage` strings in `quick-entry.ts`). Anything in `[brackets]` there is optional. The amount is always required.
5. **Module gating:**
   - Declared commands come only through the manifest that returns them, so they are gated by `isEnabledFor`.
   - `commandPrefixes()` stays as the gate for generic commands.
   - Core declares commands only for core actions.
   - Read `tests/Feature/Modules/CoreIndependenceTest.php` before adding the manifest method.
6. **The preview card is built on the server, per previewable command.**
   - Each declared command names its card fields. The account balance comes from the dry-run's line on that account.
   - A transfer shows both accounts, "From A → To B", each with before → after.
7. **Undo covers only this session's last post**, the same as today's Undo button. The execute response already returns the id that the undo key needs. After a reload, `undo` says "Nothing to undo".
8. **How generic commands rank and show:**
   - They rank below declared commands and never appear in the empty state.
   - They show only once the typed text matches their words.
   - Query commands (`list`, `view`, reports) keep their table output, restyled into the Ledger system.
9. **`quick-actions.ts`, `shortcuts.ts`, `help.ts` and `PaletteSuggestionsController`** fold into the new catalog and matcher. The builder decides when to delete them, once nothing calls them.

## 10. Calls I made (check these)

- I kept `deposit`, `withdraw`, `bank charge` and `sale` as words. `paid`, `received` and `expense` become hidden aliases.
- Declared commands are separate from CommandBus actions (§6.1), and undeclared actions still get a generic command.
- The palette stays open after a post.
- The catalog is cached on the client per open/company.
