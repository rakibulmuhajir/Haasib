# Command Palette Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the blue terminal palette with a sentence-with-blanks palette in the Ledger design. Every command, core or module, is declared once in PHP and resolved on the server.

**Architecture:**
- **Server.** A command is a small PHP class: verb, aliases, a sentence template with `{slots}`, and a target CommandBus action. It also has a `resolve()` step that turns slot texts into params and a plain card.
  - Core and each module's manifest contribute commands. `PaletteCatalog` publishes them alongside generic commands built from the remaining CommandBus actions.
  - `POST /api/palette/resolve` runs `resolve()`.
- **Browser.** It only splits typed text into slot texts, drives the blanks with Enter/Tab, and calls resolve, then the existing preview, then the existing execute.

**Tech Stack:** Laravel 12 + Pest, Vue 3 `<script setup lang="ts">`, Shadcn-vue (reka-ui), Vitest.

**Spec:** `docs/superpowers/specs/2026-10-21-palette-redesign-design.md`. Read it first. Its §9 overrides the earlier sections where they differ.

**Paths** are relative to `build/` unless they start with `docs/`.

## Global Constraints

- **Branch** `palette-v2` off `main`. Commit after each task (see `parallel-agents-clobber-uncommitted-work`).
- **Tests are opt-in.** Every task writes its tests. A **Run** step is done only when the user has said tests may run. Otherwise skip it and report "written, not run". Run one test process at a time and only the named file. Never run `migrate:fresh` or `--env=testing`.
- **Vue:** `<script setup lang="ts">` only. Use Shadcn components from `resources/js/components/ui/*`, with no raw `<input>` or `<button>`. Use `MoneyText.vue` for every amount and `DateTimeText.vue` for every date.
- **Network:** `fetch` is allowed only to `/api/palette/*`, as today, with the same headers that `useQuickEntry.ts` sends (`X-Company-Slug`, CSRF, `X-Idempotency-Key` on execute).
- **Ledger law 3:** money going out is ink, never red. Red (`critical`) is used only for a missing or not-found blank.
- **Copy** is a few words: "Amount needed", "Not found", "Posted", "Nothing to undo", "Ready". Keep the existing quick-entry error texts word for word.
- **Never shown to the user:** debit/credit, account codes in the card, `--flags`, `entity.verb` keys.
- **Core independence:** core never imports FuelStation or Umrah. `tests/Feature/Modules/CoreIndependenceTest.php` stays green, so no `'fuel_station'` or `'amanat'` string literals in `app/` or `modules/Accounting`.
- **Execute is unchanged:** don't change `/api/palette/execute`, `CommandController` or `PalettePreview`'s allow-list.
- **One word per entity:** vendor, customer, company. Never supplier or buyer in new copy.

## Review Focus

1. **A name that contains a number**, such as `pay 7 star traders 5000`. The vendor must be "7 star traders" and the amount 5000. Rule: the amount is the *last* amount-shaped token before an anchor or the end. Pinned in Task 5.
2. **Switching company while the palette is open, or between opens.** The catalog must be refetched. A fuel company's `card charge` must never appear for a general company. Pinned in Task 6.
3. **Pressing Enter twice fast on a ready entry** must post exactly once. Enter is ignored while a post is running, and the idempotency key is fixed per resolved entry. Pinned in Task 6.
4. **A resolve response that arrives after the user has typed more** must be dropped and never applied to newer text. Pinned in Task 6.
5. **Editing a slot's text after picking from choices.** The picked id must clear, so the old record isn't posted under new text. Pinned in Task 6.

---

### Task 1: Command model, slot parsing, registry and catalog (server)

**Files:**
- Create: `app/Support/Palette/Commands/PaletteCommand.php`, `Slot.php`, `SlotInput.php`, `Resolution.php`, `ResolveContext.php`, `AmountParser.php`, `DateParser.php`, `PaletteCommandRegistry.php`, `CorePaletteCommands.php` (an empty list for now)
- Modify: `app/Support/Modules/ModuleManifest.php`, adding `paletteCommands(): array` with a default of `[]`, next to `commandPrefixes()`
- Modify: `app/Support/Palette/PaletteCatalog.php`. Return `['sentences' => …, 'commands' => …]` from a new `payload(User, ?Company): array`. Keep `for()` as it is for the old palette until Task 9.
- Modify: `app/Http/Controllers/Api/PaletteCatalogController.php`, so it returns `payload()`
- Test: `tests/Unit/Palette/AmountParserTest.php`, `tests/Unit/Palette/DateParserTest.php`, `tests/Feature/Palette/PaletteCommandRegistryTest.php`

**Interfaces (Produces):**
```php
abstract class PaletteCommand {
    abstract public function name(): string;          // 'pay', 'bank charge', 'add customer'
    public function aliases(): array { return []; }   // ['paid']
    abstract public function label(): string;         // 'Pay a vendor'
    abstract public function sentence(): string;      // 'Pay {vendor} {amount} from {account?} on {date?}'
    /** @return array<string, Slot> keyed by the slot names used in sentence() */
    abstract public function slots(): array;
    abstract public function target(): string;        // CommandBus key; its permission gates the command
    public function resolve(SlotInput $in, ResolveContext $ctx): Resolution;  // default: Resolution::error('Not supported')
    public function opens(): ?string { return null; } // '/{company}/bill-payments/{id}' | 'redirect' | null
    public function undo(): ?string { return null; }  // 'bill_payment.void'
    public function formUrl(): ?string { return null; } // set → Enter opens this page instead of resolving
    public function query(): bool { return false; }   // true → shows a card or a table, never executes
    final public function toCatalog(Company $company): array;
}
final class Slot { // readonly kind: 'amount'|'date'|'text'|'search'|'account'|'choice'; ?entity; ?accountKind: 'bank'|'cash_or_bank'|'expense'; array options
    public static function amount(): self; date(): self; text(): self; search(string $entity): self;
    public static function account(string $kind): self; choice(array $options): self; }
final class SlotInput { public function text(string $slot): ?string; public function value(string $slot): ?string; } // built from ['slot' => ['text' => ?, 'value' => ?]]
final class Resolution {
    public static function ready(string $key, array $params, array $card, string $summary, array $notes = []): self;
    public static function pending(array $slots): self;   // slot name => SlotState array (below)
    public static function error(string $message): self;
    public function toArray(): array;
}
// SlotState arrays: ['state'=>'resolved','value'=>id,'label'=>name] | ['state'=>'choices','prompt'=>..,'choices'=>[{value,label,meta?}]]
//                   ['state'=>'not_found','message'=>..] | ['state'=>'missing']
final class ResolveContext { public Company $company; public User $user; public string $today; public array $defaults;
    public static function make(): self; } // current company + user; $defaults = PaletteDefaultsController::defaultsFor($company)
final class CorePaletteCommands { /** @return list<PaletteCommand> */ public static function all(): array; }
final class AmountParser { public static function parse(string $text): ?float; }  // '50000','50,000','50k','1.5 lakh','2 lac'
final class DateParser { /** @return array{ok:true,date:string}|array{ok:false,message:string} */ public static function resolve(string $text, string $today): array; }
final class PaletteCommandRegistry { /** @return list<PaletteCommand> */ public function for(User $user, ?Company $company): array; }
```

`toCatalog()` returns:

```
{name, aliases, label, sentence, key, slots:[{name, kind, optional, entity?, options?}], generic:false, form_url, query, opens, undo, previewable}
```

- `optional` is true when the sentence writes `{name?}`.
- `previewable` is `array_key_exists(target(), PalettePreview::COMMANDS)`.
- `form_url` is `formUrl()` with `{company}` replaced by the company slug.

- [ ] **Step 1: Write the failing tests.**
  - **AmountParser:** `'50000'→50000.0`, `'50,000'→50000.0`, `'50k'→50000.0`, `'1.5 lakh'→150000.0`, `'2 lac'→200000.0`, `'abc'→null`, `'0'→null`. Copy any further cases from `tests/js/palette/quick-entry.spec.ts`'s `parseAmount` block.
  - **DateParser:** copy every case from the `resolveDate` block of `tests/js/palette/quick-entry.spec.ts`, including its error messages, with `today = '2026-10-16'`.
  - **Registry:**
    - a test-only command registered through a fake manifest that is enabled for the company is listed in `payload()['sentences']`;
    - the same manifest disabled → it is not listed;
    - a user lacking `target()`'s permission → not listed;
    - a command whose `slots()` throws is skipped and does not break the others.
- [ ] **Step 2: Run** `php artisan test tests/Unit/Palette tests/Feature/Palette/PaletteCommandRegistryTest.php`. Expected: FAIL (classes missing).
- [ ] **Step 3: Implement.**
  - Port `AmountParser` and `DateParser` from `parseAmount` and `resolveDate` / `extractDate` in `resources/js/palette/quick-entry.ts`.
  - The Registry gathers `CorePaletteCommands::all()` plus `paletteCommands()` of each `ModuleRegistry::enabledFor($company)`. It then filters with `app(CommandBus::class)->permissionFor($cmd->target())` and `$user->hasCompanyPermission()`, and logs and skips any that throw (`palette_command_skip`).
  - Extract the body of `PaletteDefaultsController::__invoke` into `public static function defaultsFor(Company $company): array`, and have both the controller and `ResolveContext` use it.
- [ ] **Step 4: Run** the same command. Expected: PASS. Also run `tests/Feature/Palette/PaletteCatalogTest.php` and `tests/Feature/Modules/CoreIndependenceTest.php`. Expected: PASS.
- [ ] **Step 5: Commit** "Palette commands: declared sentence commands, registry, amount and date parsing".

### Task 2: Generic commands and the resolve endpoint

**Files:**
- Create: `app/Support/Palette/Commands/GenericCommand.php`, which wraps one existing catalog entry
- Create: `app/Http/Controllers/Api/PaletteResolveController.php`, `app/Http/Requests/PaletteResolveRequest.php`
- Modify: `routes/api.php`, adding `POST /palette/resolve` with the catalog route's middleware (`['web','auth','identify.company','throttle:60,1']`) and a throttle of 120/min, because resolve fires as you type
- Modify: `PaletteCatalog::payload()`, so `sentences` also lists `GenericCommand`s for every catalog entry whose `key` no declared command `target()`s
- Test: `tests/Feature/Palette/PaletteResolveTest.php`

**Interfaces:**
- **Consumes:** Task 1 types.
- **Produces:** `POST /api/palette/resolve`.
  - Request: `{command: string, slots: {[name]: {text?: string, value?: string}}}`. `command` is a declared `name()` or a generic `key`.
  - Response: `Resolution::toArray()`, which is `{status:'ready'|'pending'|'error', slots?, key?, params?, card?, summary?, notes?, message?}`.
  - An unknown command, or one the user can't see, returns 404 `{status:'error', message:'Unknown command'}`.
- **Card shape** (used by every later task):

  ```
  {who?: string, what?: string, amount?: number, currency: string, from?: string, to?: string, date?: string, note?: string}
  ```

  `from` and `to` are account *names*, which the browser matches against preview `balances[].account_name`.

**GenericCommand rules:**
- `name()` is the catalog `key`, and `label()` is its `description`.
- `sentence()` is the description followed by `{field}` for each `args` entry and `{field?}` for each `flags` entry.
- Slot kinds come from `inputType`: `search` → `Slot::search(searchEntity)`, `select` → `Slot::choice(enum)`, `number` → amount, `date` → date, anything else → text.
- `resolve()`:
  - search slots go through `PaletteSearchSources::search()`. One hit or an exact label match resolves it, several hits give choices, and none gives `not_found`.
  - an empty required slot is `missing`;
  - `params` are the field name → resolved value or text;
  - `card` is `{what: label}`.
- `formUrl()` is the entry's `form_url` when `form` is true.
- `toCatalog()` sets `generic: true`.

- [ ] **Step 1: Write the failing tests.**
  - `vendor.create` with `{name:{text:'Ali Traders'}}` gives `status:'ready'`, `key:'vendor.create'` and `params.name === 'Ali Traders'`.
  - An empty required slot gives `status:'pending'` and `slots.name.state === 'missing'`.
  - An unknown command gives 404.
  - A fuel key on a general company gives 404.
  - `sentences` for a general company contains `vendor.create` with `generic: true`.
- [ ] **Step 2: Run** `php artisan test tests/Feature/Palette/PaletteResolveTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the controller: find the command in `PaletteCommandRegistry::for()` plus the generics, then call `resolve(new SlotInput($request->validated('slots', [])), ResolveContext::make())`. Validation lives in the FormRequest: `command` is `required|string|max:100`, `slots` is `array`, and `slots.*.text` / `slots.*.value` are `nullable|string|max:500`.
- [ ] **Step 4: Run** the same file. Expected: PASS.
- [ ] **Step 5: Commit** "Palette resolve endpoint and generic commands".

### Task 3: The money commands, at parity with quick entry

**Files:**
- Create in `app/Support/Palette/Commands/Money/`: `DepositCommand`, `WithdrawCommand`, `TransferCommand`, `BankChargeCommand`, `ReceiveCommand`, `PayCommand`, `SpendCommand`, `SaleCommand`, plus `Accounts.php` (shared account picking and fallbacks)
- Modify: `CorePaletteCommands::all()` to list them
- Test: `tests/Feature/Palette/MoneyCommandsTest.php`

**Interfaces:**
- **Consumes:** Tasks 1–2.
- **Produces:** the commands below. The `Accounts` helper holds the ported `matchAccounts`, `pickAccount`, `defaultCash`, `defaultBank`, `configured`, `settlementFallback`, `bankFallback` and `splitChargeTail`, with the same behaviour and the same message strings as `resources/js/palette/quick-entry.ts:380-700`.

| name | aliases | sentence | target | opens | undo |
|---|---|---|---|---|---|
| `deposit` | | `Deposit {amount} into {bank?} on {date?}` | `bank_transaction.create` | journal | `bank_transaction.reverse` |
| `withdraw` | | `Withdraw {amount} from {bank?} on {date?}` | `bank_transaction.create` | journal | `bank_transaction.reverse` |
| `transfer` | | `Transfer {amount} from {from} to {to} on {date?}` | `bank_transaction.create` | journal | `bank_transaction.reverse` |
| `bank charge` | `bank charges` | `Bank charge {amount} on {bank?} for {note?} on {date?}` | `bank_transaction.create` | journal | `bank_transaction.reverse` |
| `receive` | `received`, `rcv` | `Receive {amount} from {customer} into {account?} on {date?}` | `payment.create` | `/{company}/payments/{id}` | `payment.void` |
| `pay` | `paid` | `Pay {vendor} {amount} from {account?} on {date?}` | `bill_payment.create` | `/{company}/bill-payments/{id}` | `bill_payment.void` |
| `spend` | `expense`, `exp` | `Spend {amount} on {what} from {account?} on {date?}` | `expense.create` | journal | `expense.reverse` |
| `sale` | | `Sale {amount} for {note?} on {date?}` | `sale.create` | redirect | `sale.void` |

- **"journal" opens** `/{company}/journals/{reversal_id}`, exactly as `useQuickEntry.ts:255-275` does today. Return `opens()` as `'/{company}/journals/{id}'` and let the browser keep today's id choice.
- **Params, summary and notes** for each command must be exactly what `resolveQuickEntry` builds for the matching kind. This includes `method: cash|bank_transfer`, the oldest-first bill allocations and the advance note for `pay`, and "Choose two different banks".
- **Slot meaning:**
  - `what` on `spend` is an expense-account pick that offers all expense accounts when nothing matches (`offerAllWhenNone`), and it is also the `description`;
  - `note` is free text;
  - `bank`, `from` and `to` are `Slot::account('bank')`;
  - `account` is `Slot::account('cash_or_bank')`.
- **Cards:**
  - `pay`: `{who: vendor, amount, from: account, date}`;
  - `receive`: `{who: customer, amount, to: account, date}`;
  - `transfer`: `{amount, from, to, date}`;
  - `spend`: `{what: expense account name, amount, from, date}`;
  - `deposit`: `{amount, from: cash, to: bank}`, and `withdraw` the other way round;
  - `bank charge`: `{what:'Bank charge', amount, from: bank, note}`;
  - `sale`: `{what:'Sale', amount, to: account, note}`.

  All cards include `currency` and `date` (ISO).

- [ ] **Step 1: Write the failing tests.**
  - Port **every** `resolveQuickEntry` case from `tests/js/palette/quick-entry.spec.ts` into Pest against `/api/palette/resolve`. Seed the same accounts, customers, vendors and open bills the spec's `defaults`/`lookups` describe, and assert the same `key`, `params`, summary text, notes and error messages.
  - Add: `pay` with no vendor text gives `slots.vendor.state === 'missing'`; `receive` with a customer name matching two customers gives `slots.customer.state === 'choices'`.
- [ ] **Step 2: Run** `php artisan test tests/Feature/Palette/MoneyCommandsTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the eight classes and `Accounts`. Look customers and vendors up through `PaletteSearchSources::search('customer'|'vendor', …)` with `pickParty`'s rules: an exact label wins, a single hit wins.
- [ ] **Step 4: Run** the same file, then `tests/Feature/Palette/PalettePreviewTest.php`. Expected: PASS.
- [ ] **Step 5: Commit** "Palette money commands at parity with quick entry".

### Task 4: Records, documents, lookups and module commands

**Files:**
- Create in `app/Support/Palette/Commands/Core/`: `AddCustomerCommand`, `AddVendorCommand`, `AddItemCommand`, `AddAccountCommand`, `InvoiceCommand`, `BillCommand`, `CreditCommand`, `BalanceCommand`, `ReportCommand`, `VoidInvoiceCommand`, `VoidBillCommand`
- Create: `app/Http/Controllers/Api/PaletteFindController.php`, `GET /api/palette/find?q=` (same middleware)
- Create: `modules/FuelStation/Palette/CardChargeCommand.php`, `modules/FuelStation/Palette/CloseDayCommand.php`
- Modify: `CorePaletteCommands::all()`, and `FuelStationModule::paletteCommands()`
- Test: `tests/Feature/Palette/CoreCommandsTest.php`, `tests/Feature/Palette/PaletteFindTest.php`, `tests/Feature/Palette/FuelPaletteCommandsTest.php`

**Commands:**

| name | sentence | behaviour |
|---|---|---|
| `add customer` / `add vendor` / `add item` | `Add customer {name}`, and so on, plus one slot per *other required* rule of the target `*.create` action | executes the action. Card `{what:'New customer', who:name}`. Opens the record's show page |
| `add account` | `Add account` | `formUrl` = the accounts create page (account needs type and code) |
| `invoice` (alias `inv`) / `bill` / `credit` | `Invoice {customer?}` / `Bill {vendor?}` / `Credit {customer?}` | `formUrl` = `/{company}/invoices/create`, `/bills/create` or `/credit-notes/create`. Add `?customer_id=` / `?vendor_id=` only if that create page already reads the parameter; check the controller and leave it off if not |
| `balance` | `Balance {account}` | `query()`. The card is `{what: account name, amount: current balance}` (reuse `PalettePreview`'s private `balance()` by making it `public static`). Never executes |
| `report` | `Report {report}` | `Slot::choice` of the report pages that exist under `/{company}/reports/*` in `routes/web.php`. Enter opens the page |
| `void invoice` / `void bill` | `Void invoice {invoice}` / `Void bill {bill}` | targets `invoice.void` / `bill.void`. Card `{what:'Void', who: number}` |
| fuel `card charge` | `Card charge {channel} {amount} on {date?}` | targets `fuel.card_charge.record`. `channel` is a choice of the company's card channels, the same source the card-charge page uses. `amount` also accepts `2%`, which maps to `percent` |
| fuel `close day` | `Close day` | `formUrl` = `route('fuel.daily-close.create', …)` |

- **`find` and bare text:** `GET /api/palette/find?q=` returns `{results:[{group:'Customers'|'Vendors'|'Invoices'|'Bills', value, label, meta?, url}]}`, up to 5 per group, from `PaletteSearchSources`. Pages are matched in the browser (Task 6).
- `go` is browser-only (Task 6). `undo` is browser-only (Task 6).

- [ ] **Step 1: Write the failing tests.**
  - `add vendor` with `{name:{text:'Ali'}}` gives ready with `key:'vendor.create'`.
  - `invoice` appears in `sentences` with a `form_url` ending `/invoices/create`.
  - `balance` gives ready and `query:true`, with a card amount equal to the seeded account balance.
  - find `q=ali` returns the seeded vendor under `Vendors`.
  - A general company's `sentences` has no `card charge` or `close day`, and a fuel company's does.
  - `card charge` with amount `2%` sends `percent: 2` and no `amount`.
- [ ] **Step 2: Run** the three files. Expected: FAIL.
- [ ] **Step 3: Implement.** The fuel commands live only under `modules/FuelStation/` (core independence).
- [ ] **Step 4: Run** the three files plus `tests/Feature/Modules/CoreIndependenceTest.php`. Expected: PASS.
- [ ] **Step 5: Commit** "Palette records, documents, lookups and fuel commands".

### Task 5: Sentence matcher (browser)

**Files:**
- Create: `resources/js/palette/v2/types.ts`, `resources/js/palette/v2/sentence.ts`
- Test: `tests/js/palette/sentence.spec.ts`

**Interfaces (Produces):**
```ts
// types.ts: mirrors Task 1/2 JSON exactly
export interface SlotSpec { name: string; kind: 'amount'|'date'|'text'|'search'|'account'|'choice'; optional: boolean; entity?: string; options?: string[] }
export interface CatalogCommand { name: string; aliases: string[]; label: string; sentence: string; key: string; slots: SlotSpec[]; generic: boolean; form_url: string|null; query: boolean; opens: string|null; undo: string|null; previewable: boolean }
export type SlotState = { state: 'resolved'; value: string; label: string } | { state: 'choices'; prompt: string; choices: Option[] } | { state: 'not_found'; message: string } | { state: 'missing' }
export interface Card { who?: string; what?: string; amount?: number; currency: string; from?: string; to?: string; date?: string; note?: string }
export type ResolveResponse = { status: 'ready'; key: string; params: Record<string, unknown>; card: Card; summary: string; notes: string[] } | { status: 'pending'; slots: Record<string, SlotState> } | { status: 'error'; message: string }
export interface Option { value: string; label: string; meta?: string }

// sentence.ts
export type Token = { type: 'word'; text: string } | { type: 'slot'; name: string; optional: boolean }
export function parseTemplate(sentence: string): Token[]
export interface Match { command: CatalogCommand; verbText: string; slotTexts: Record<string, string> }
export function matchCommand(text: string, commands: CatalogCommand[]): Match | null
export function suggestCommands(text: string, commands: CatalogCommand[], scores: Record<string, number>): CatalogCommand[]
```

**Matching algorithm.** This is the part the signatures don't settle:
1. Lower-case the text. The verb is the longest `name` or alias, with whole words, that the text starts with. If there is no verb, return `null`.
2. Take a date phrase out first, from anywhere in the text: `today|yesterday|tomorrow`, or `on <d> <mon>`, `on d/m`, `on d-m-y`. Use the same regexes as `quick-entry.ts` `extractDate`. It fills the `date` slot.
3. Walk the template tokens after the verb. A `word` token (`from`, `to`, `into`, `on`, `for`) is an anchor. Each slot takes the input text between the previous anchor found in the input and the next anchor found in the input, or the end.
4. When two slots sit next to each other with no anchor between them (`{vendor} {amount}`), the `amount` slot takes the **last** amount-shaped run in that span, and the other slot gets the rest. Amount-shaped means `/^\d[\d,]*(\.\d+)?\s*(k|lakh|lac)?%?$/` over one or two tokens.
5. Trim every slot text. Leave a slot out of `slotTexts` when its text is empty.

`suggestCommands`:
- With empty text, return the top 8 non-generic commands by `scores[name]`.
- Otherwise rank: verb-prefix matches on declared commands, then label word matches on declared commands, then generic commands whose label contains every typed word. Within each group, rank by `scores`. Return 8.

- [ ] **Step 1: Write the failing tests.**
  - `pay ali 1000 from cash` → `{vendor:'ali', amount:'1000', account:'cash'}`.
  - `pay 7 star traders 5000` → `{vendor:'7 star traders', amount:'5000'}` (Review Focus 1).
  - `paid ali 1000 yesterday` matches `pay` with `date:'yesterday'`.
  - `bank charge 500 meezan for sms` → `{amount:'500', bank:'meezan', note:'sms'}`.
  - `transfer 100000 from meezan to hbl` fills `from` and `to`.
  - `pay` alone → `slotTexts` is `{}`.
  - `xyz` → `null`.
  - `suggestCommands('', …)` contains no generic command.
  - Port every `parseQuickEntry` case from `tests/js/palette/quick-entry.spec.ts`, adjusted to the new sentences (for example `received 15000 from al noor` → receive `{amount:'15000', customer:'al noor'}`).
- [ ] **Step 2: Run** `npx vitest run tests/js/palette/sentence.spec.ts`. Expected: FAIL.
- [ ] **Step 3: Implement** `sentence.ts`. It has no Vue and no network.
- [ ] **Step 4: Run** the same command. Expected: PASS.
- [ ] **Step 5: Commit** "Palette sentence matcher".

### Task 6: Palette state composable

**Files:**
- Create: `resources/js/palette/v2/blanks.ts` (the pure part), `resources/js/composables/palette/usePalette.ts`
- Test: `tests/js/palette/blanks.spec.ts`, `tests/js/palette/usePalette.spec.ts` (mock `fetch` with `vi.fn`)

**Interfaces (Produces):**
```ts
// blanks.ts
export interface Blank { name: string; spec: SlotSpec; text: string; picked: Option | null; state: SlotState | null }
export interface BlankSet { command: CatalogCommand; blanks: Blank[]; current: number; flagMissing: boolean }
export function fromMatch(m: Match): BlankSet                 // current = first required blank with no text, else last blank
export function setText(s: BlankSet, i: number, text: string): BlankSet   // clears picked and state when the text changes (Review Focus 5)
export function pick(s: BlankSet, i: number, o: Option): BlankSet
export function applyResolve(s: BlankSet, r: ResolveResponse): BlankSet
export function nextRequired(s: BlankSet): number | null       // first required blank whose state is not 'resolved'
export function tab(s: BlankSet, dir: 1 | -1): BlankSet        // through all blanks, wraps
export type EnterOutcome = 'pick' | 'advance' | 'post' | 'open' | 'flag'
export function enterOutcome(s: BlankSet, ready: boolean, choicesOpen: boolean): EnterOutcome

// usePalette.ts: what the components bind to
export function usePalette(): {
  text: Ref<string>; blanks: Ref<BlankSet | null>; suggestions: Ref<CatalogCommand[]>; choices: Ref<Option[]>; choiceIndex: Ref<number>
  card: Ref<Card | null>; balances: Ref<Array<{ account_name: string; before: number; after: number }>>
  status: Ref<'idle' | 'working' | 'ready' | 'missing' | 'error' | 'posting'>; message: Ref<string | null>
  result: Ref<{ message: string; openUrl: string | null; canUndo: boolean; undone: boolean } | null>
  findResults: Ref<Array<{ group: string; label: string; meta?: string; url: string }>>
  onKeydown(e: KeyboardEvent): void; open(): void; close(): void; undo(): Promise<void>
}
```

**Behaviour:**
- **Enter:**
  - with choices open → `pick`;
  - with `form_url` → `open`;
  - when ready and not `query` → `post`;
  - when `query` and ready → `open` if there's a URL, otherwise nothing;
  - when the current blank has text → `advance` to `nextRequired`;
  - when nothing required remains but the entry isn't ready → `flag`, which sets `flagMissing`, moves `current` to the first missing blank, and sets status `missing` with the message "`<Slot> needed`".
- **Catalog:** `GET /api/palette/catalog` on `open()`, cached in a module-level `Map` keyed by company slug. Refetch when the slug differs (Review Focus 2).
- **Resolve** is debounced by 150 ms after any text change, carries a request sequence number, and drops a response whose number isn't the latest (Review Focus 4). When the response is ready and `previewable`, call `POST /api/palette/preview` the same way `useQuickEntry.ts` does and fill `balances`.
- **Post:**
  - The idempotency key is created when the entry becomes ready and kept until its text changes.
  - While `status === 'posting'`, Enter does nothing (Review Focus 3).
  - On success: `recordCommandUse(command.name)` (existing `frecency.ts`), then set `result` with the summary as the message, clear the text, and keep the palette open.
  - The open URL uses `useQuickEntry.ts`'s current rules (the `sale.void` redirect, the journal reversal id).
- **Undo:** posts the command's `undo` key with the id, as `useQuickEntry.ts:320-345` does. It works only for this session's last result, so `undo` typed with no result gives "Nothing to undo". Ctrl/⌘ Z also undoes when the input is empty and a result is showing.
- **Esc:** an open choice list closes; otherwise non-empty text clears; otherwise the palette closes.
- **↑** on an empty input recalls the last posted text.
- **Bare text** (no verb match, 2+ characters): debounced `GET /api/palette/find`. Pages are matched in the browser against the sidebar nav items, imported from wherever `AppSidebar.vue` gets them. `go <page>` filters pages only.

- [ ] **Step 1: Write the failing tests.**
  - **blanks.spec:**
    - `enterOutcome` for each branch above;
    - `setText` after `pick` clears `picked`;
    - `nextRequired` skips optional blanks;
    - `tab(-1)` from 0 wraps to the last blank.
  - **usePalette.spec:**
    - two resolves where the first resolves last → only the second is applied;
    - two Enters while posting → `fetch` called once for execute;
    - open with slug A, then B → catalog fetched twice;
    - `undo` with no result → message "Nothing to undo".
- [ ] **Step 2: Run** `npx vitest run tests/js/palette/blanks.spec.ts tests/js/palette/usePalette.spec.ts`. Expected: FAIL.
- [ ] **Step 3: Implement** both files.
- [ ] **Step 4: Run** the same command. Expected: PASS.
- [ ] **Step 5: Commit** "Palette state: blanks, resolve, preview, post, undo".

### Task 7: The new palette UI

**Files:**
- Create in `resources/js/components/palette/v2/`: `Palette.vue`, `PaletteSentence.vue`, `PaletteList.vue`, `PaletteCard.vue`, `PaletteResult.vue`, `PaletteFooter.vue`
- Modify: `resources/js/app.ts:135`, to render `Palette` instead of `CommandPalette`, with the same visibility wiring (`usePaletteVisibility`) and the same `company-switched` handling
- Test: `tests/js/palette/PaletteCard.spec.ts` (`@vue/test-utils`, if it is already a dev dependency; otherwise test through `blanks.ts` only and note it)

**Layout and states:**
- **Container:** Shadcn `Dialog`, centred, top third, `max-w-[600px]`. Behind it, a backdrop at the token overlay.
- **`PaletteSentence`:** the verb in body weight 600, followed by blanks as inline Shadcn `Input`s, auto-sized to their text, with the literal anchor words between them. There are four blank states, all from tokens:
  - empty: dashed `--border`, placeholder = slot name;
  - current: accent outline;
  - filled: quiet fill;
  - missing (when `flagMissing`): the `critical` border.

  Amounts and dates in blanks use the Figure font (`font-mono`). Before a verb is matched, it is one plain input with the placeholder "Type a command or search…".
- **`PaletteList`:** suggestions (label, plus the verb hint in muted text), or the choices for the current blank, or find results grouped by `group`. Arrow keys move through it, the hovered row follows the mouse, and the selected row scrolls into view.
- **`PaletteCard`:** a definition grid (`DefinitionList.vue`, if it fits). Rows in order: Paying/Receiving/What (`who` or `what`), Amount (`MoneyText`, large), From / To (name plus `before → after` from `balances` matched by `account_name`, shown muted), Date (`DateTimeText`), Note. Notes from the resolve go under it in muted text. There are no Dr/Cr lines.
- **`PaletteResult`:** `✓ {message}` plus Shadcn `Button` variant `link` for "Open" and "Undo". It shows "Undone" after an undo.
- **`PaletteFooter`:** key hints `Enter` next/post, `Tab` edit, `Esc` back, and on the right `status`: `n of m`, `Ready`, the missing message, or `Posting…`.
- Query commands that return a table (generic `list`/`view`): render rows in `LedgerRegister.vue` inside the dialog body, reusing `resources/js/palette/table.ts` to shape them.
- **Dark mode** comes from tokens only. No hex values in the new components.

- [ ] **Step 1: Write the failing test:** PaletteCard with a card `{who:'Ali Traders', amount:1000, currency:'PKR', from:'Cash in hand', date:'2026-10-21'}` and a balance `{account_name:'Cash in hand', before:45200, after:44200}` renders "Ali Traders", "1,000.00" and "45,200", and does not contain "Dr" or "Cr".
- [ ] **Step 2: Run** `npx vitest run tests/js/palette/PaletteCard.spec.ts`. Expected: FAIL.
- [ ] **Step 3: Implement** the six components and the `app.ts` swap.
- [ ] **Step 4: Run** the same command, then `node scripts/lint-palette.mjs`. Expected: PASS and no lint errors.
- [ ] **Step 5: Ask the user** to open the app (`php artisan octane:start --server=frankenphp --port=9001 --watch` plus `npm run dev`) and try `pay`, `receive 500 from …` and `transfer` with a missing account. Wait for their word before Task 8.
- [ ] **Step 6: Commit** "New palette UI: sentence with blanks in the Ledger design".

### Task 8: Remove the old palette

**Files:**
- Delete:
  - `resources/js/components/palette/CommandPalette.vue`, `PaletteHeader.vue` and `PaletteOutput.vue`, if nothing in v2 imports them;
  - `resources/js/composables/palette/useCommandPalette.ts` and `useQuickEntry.ts`;
  - `resources/js/palette/schemas.ts`, `grammar.ts`, `parser.ts`, `quick-entry.ts`, `quick-actions.ts`, `help.ts`, `shortcuts.ts`, `autocomplete.ts`, `scaffold.ts` and `formatter.ts`, each only once grep shows no import outside the deleted set;
  - `tests/js/palette/quick-entry.spec.ts` and `tests/js/palette/catalog.spec.ts`. Their cases now live in the Task 3 and Task 5 tests. Before deleting, check by count that every case was ported.
- Delete: `app/Http/Controllers/PaletteSuggestionsController.php` and its route, if no v2 code calls `/api/palette/suggestions`
- Modify: `PaletteCatalog`. Drop the old `for()` return shape if nothing reads `commands` any more. Keep `commands` if `GenericCommand` is still built from it.
- Move `resources/js/palette/v2/*` and `components/palette/v2/*` up one level and fix the imports.
- Test: no new tests. The suites of Tasks 1–7 must still pass.

- [ ] **Step 1:** For each file to delete, run `grep -rn "<basename without extension>" resources/js tests/js`. Only delete files whose remaining hits are inside the delete set.
- [ ] **Step 2:** Delete the files, move v2 up a level, and fix the imports.
- [ ] **Step 3: Run** `npx vue-tsc --noEmit`, then `npx vitest run tests/js/palette`, then `php artisan test tests/Feature/Palette`. Expected: no type errors, and PASS.
- [ ] **Step 4: Commit** "Remove the old terminal palette".
