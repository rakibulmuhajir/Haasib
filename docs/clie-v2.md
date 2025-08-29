Here’s the **complete `cli.md`** with your patch baked in: palette-first UX, bottom “status-bar” command strip with expand/hide/fullscreen, mini-grammar, structured API, idempotency, and the dev/superadmin xterm console kept separate.

```markdown
# Haasib Command Palette & Dev Console — Functional Spec (v1)

> Primary UX: **Command Palette (CMD-K)** for all business CRUD and reports.
> Secondary UX: **xterm.js Dev/Superadmin Console** for ops-only tools.

---

## 0) Product goals

- **Speed**: keyboard-first, sub-300 ms command→result p50
- **Safety**: server validation, transactions, idempotency, audit trail
- **Learnability**: natural verbs, lightweight grammar, inline prompts
- **Tenancy**: every action scoped to the active company (server-enforced)

---

## 1) Shell & Interaction Model

### 1.1 Command Strip (status-bar style)
- A persistent strip pinned to the **bottom** of the app, like an old browser status bar.
- Left: chevron prompt, hint text. Middle: subdued history chip when idle.
- Right: **three controls**
  1) **▭** expand to **half-screen**
  2) **—** hide/collapse to the thin strip
  3) **⛶** expand to **fullscreen**
- The strip remembers the last size the user chose.

### 1.2 Hotkeys
- **CMD/CTRL + K**: toggle palette (strip raises to the last chosen size)
- **ESC**: close palette or shrink to strip
- **↑ / ↓**: cycle command history when the input is empty
- **TAB**: autocomplete tokens; second TAB shows candidates inline

### 1.3 Accessibility
- `role="dialog"` with focus trap
- Results are announced via `aria-live="polite"`
- All actions reachable by keyboard; visible focus ring
- 44px touch targets on the three right-side controls

---

## 2) Mini-Grammar

> Keep it tiny. Let verbs carry intent. Fill the rest with prompts and autocomplete.

**Shape**
```

<verb> \[subject] \[amount] \[date] \[flags]

```

**BNF-ish**
```

<verb>      ::= "invoice" | "payment" | "bill" | "pay" | "customer" | "vendor" | ... <subject>   ::= free text | quoted string | ID (e.g. INV-123) | alias <amount>    ::= number | currency-formatted (1,500.00 | \$1500 | 1.5k) <date>      ::= natural date ("today" | "tomorrow" | "30 days" | "Mar 15") <flags>     ::= (--key value)\* | (-k value)\*

```

**Heuristics**
- First token maps to **action** via synonyms (e.g. “bill customer” → `invoice.create`)
- First numeric token becomes **amount** unless already assigned
- Strings after known keywords bind accordingly: `for`, `to`, `on`, `due`
- Quoted text stays intact: `"Acme Middle East"`
- Locale-aware parsing for currency and dates

**Universal Flags (optional)**
```

\--amount,-a   --date,-d   --customer,-c   --vendor,-v
\--account     --ref       --notes         --draft     --help,-h

````

---

## 3) Command Registry (client)

A typed registry powering autocomplete, prompting, preview, and submission.

```ts
// resources/js/commands/registry.ts
export type CommandCtx = {
  raw: string
  action: string                 // canonical id, e.g. 'invoice.create'
  params: Record<string, any>    // parsed pieces
  missing: string[]              // which params to collect
  idemKey: string                // client-generated idempotency key
}

export type CommandDef = {
  id: string                     // 'invoice.create'
  label: string                  // 'invoice'
  aliases: string[]              // ['invoice','bill customer','create invoice','send invoice']
  needs: Array<'customer'|'amount'|'due'|'description'|'invoice'|'vendor'|'account'>
  promptIfNeeded: (ctx: CommandCtx) => Promise<CommandCtx>
  preview?: (ctx: CommandCtx) => Promise<{ totals: any; gl?: any[] }>
  executeAction: string          // server action string, e.g. 'invoice.create'
  rbac: string[]                 // e.g. ['ledger.postJournal','invoice.create']
}
````

Examples (abbreviated):

```ts
export const registry: CommandDef[] = [
  {
    id: 'invoice.create',
    label: 'invoice',
    aliases: ['invoice','bill customer','create invoice','send invoice'],
    needs: ['customer','amount','due','description'],
    async promptIfNeeded(ctx){ /* open inline mini-fields for missing pieces */ return ctx },
    async preview(ctx){ /* compute totals, taxes */ return { totals: {/*...*/} } },
    executeAction: 'invoice.create',
    rbac: ['ledger.postJournal']
  },
  {
    id: 'payment.create',
    label: 'payment',
    aliases: ['payment','record payment','got paid','customer paid'],
    needs: ['invoice','amount','method','date'],
    async promptIfNeeded(ctx){ return ctx },
    executeAction: 'payment.create',
    rbac: ['ledger.postJournal']
  }
]
```

---

## 4) Parser (client)

```ts
// resources/js/commands/parser.ts
import { hash } from '@/lib/idempotency'
import { normalizeMoney, normalizeDate } from '@/lib/nlp'

export function parse(input: string){
  const tokens = tokenize(input)                // respects quotes
  const action = canonicalVerb(tokens[0])       // synonyms → canonical
  const params: Record<string, any> = {}

  // greedy, intent-first heuristics
  extractAmount(tokens, params)
  extractDate(tokens, params)
  extractSubject(tokens, params)                // matches customers/vendors/invoices via Fuse
  extractFlags(tokens, params)

  const missing = computeMissing(action, params)
  const idemKey = hash(`${action}:${JSON.stringify(params)}`)
  return { raw: input, action, params, missing, idemKey }
}
```

---

## 5) Client wiring (Vue 3 + Inertia)

* Bottom **CommandPalette** component mounted in the main layout
* **Fuse.js** index built from the command registry plus lightweight entity catalogs
* **Web Worker** builds/updates the Fuse index off the main thread
* **Optimistic UI**: show “INV-003 created” with link; reconcile with server response
* **Idempotency**: send `X-Idempotency-Key` header per execution

Minimal shell:

```vue
<!-- resources/js/Components/CommandPalette.vue -->
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import Fuse from 'fuse.js'
import { registry } from '@/commands/registry'
import { parse } from '@/commands/parser'
import { apiPost } from '@/lib/api'

const open = ref(false)
const q = ref('')
let fuse: Fuse<any>

onMounted(() => {
  const items = registry.flatMap(c => [{ type:'command', label:c.label, id:c.id, aliases:c.aliases }])
  fuse = new Fuse(items, { keys: ['label','aliases'], includeScore:true, threshold:0.35 })
  window.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); toggle() }
  })
})

function toggle(){ open.value = !open.value }

async function run(input: string){
  const ctx = parse(input)
  const def = registry.find(r => r.id === ctx.action)
  const filled = def ? await def.promptIfNeeded(ctx) : ctx
  const preview = def?.preview ? await def.preview(filled) : null
  // show inline preview if available; on confirm:
  await apiPost('/api/commands', filled.params, {
    headers: { 'X-Action': filled.action, 'X-Idempotency-Key': filled.idemKey }
  })
}
</script>
```

---

## 6) Backend contract (Laravel)

### 6.1 Route

```
POST /api/commands
Headers:
  Authorization: Bearer <sanctum or session>
  X-Action: <canonical action, e.g. invoice.create>
  X-Idempotency-Key: <uuid/sha256>

Body:
  { "params": { ... } }
```

### 6.2 Responses

* `201 { ok: true, message, data, redirect? }`
* `422 { ok: false, code, errors }`  // validation failures
* `403 { ok: false, code }`          // authz
* `409 { ok: false, code: "IDEMPOTENT_REPLAY" }` // duplicate key

### 6.3 Controller & Bus

```php
// routes/api.php
Route::middleware(['auth:sanctum','verified'])
  ->post('/commands', [\App\Http\Controllers\CommandController::class, 'execute']);

// app/Http/Controllers/CommandController.php
public function execute(\App\Http\Requests\CommandRequest $req, \App\Support\CommandBus $bus) {
    $action = $req->header('X-Action');
    $params = $req->validated()['params'] ?? [];
    return DB::transaction(function () use ($req, $action, $params, $bus) {
        $this->ensureIdempotent($req);
        $result = $bus->dispatch($action, $params, auth()->user());
        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'company_id' => tenant_company_id(),
            'action' => $action,
            'params' => $params,
            'result' => $result,
        ]);
        return response()->json(['ok' => true] + $result, 201);
    });
}
```

```php
// app/Support/CommandBus.php
class CommandBus {
  public function dispatch(string $action, array $params, \App\Models\User $user){
    // Policy + tenancy checks happen inside actions
    return match($action) {
      'invoice.create' => app(\App\Actions\Invoice\Create::class)->handle($params, $user),
      'payment.create' => app(\App\Actions\Payment\Create::class)->handle($params, $user),
      'bill.create'    => app(\App\Actions\Bill\Create::class)->handle($params, $user),
      default => throw new \InvalidArgumentException('Unknown action')
    };
  }
}
```

```php
// Idempotency sketch (table: idempotency_keys: key,user_id,company_id,created_at)
private function ensureIdempotent(Request $req){
  $key = $req->header('X-Idempotency-Key');
  abort_if(!$key, 409, 'Missing idempotency key');
  $exists = \App\Models\IdempotencyKey::where([
      'key' => $key,
      'user_id' => auth()->id(),
      'company_id' => tenant_company_id(),
  ])->exists();
  abort_if($exists, 409, 'IDEMPOTENT_REPLAY');
  \App\Models\IdempotencyKey::create([
      'key' => $key, 'user_id' => auth()->id(), 'company_id' => tenant_company_id(),
  ]);
}
```

**Validation & AuthZ**

* Each Action object validates `params` with laravel rules and enforces **policies** (`company.manageMembers`, `ledger.postJournal`, etc.).
* Tenancy is resolved from active company middleware; actions must include `company_id` in writes.

---

## 7) Dev/Superadmin Console (xterm.js)

* Route: `/super/console`, **Gate**: `user.isSuperAdmin()` or policy; not visible otherwise.
* Do not expose a raw shell in prod. Provide a **curated REPL** that maps admin verbs to internal services:

  * `queue:stats`, `import:bank path=...`, `report:reindex`, `tenants:list`, `user:impersonate`
* Optionally wire to node-pty in **dev only**. Never for end users.

---

## 8) Autocomplete & Suggestions

* **Fuse.js** index sources:

  * command labels + aliases
  * customers/vendors/accounts lightweight catalogs
  * recent invoices/bills (ids + titles) for quick binding
* Context boosts:

  * After `invoice`, rank customers over commands
  * After `payment`, rank unpaid invoices
* TAB cycles candidates; double-TAB lists options inline

Examples:

```
> inv<TAB>
invoice    invoices

> pay<TAB>
payment    pay

> invoice
Customer: acme<TAB>
  Acme Corp
  Acme Industries
  Acme Services
```

---

## 9) Natural Language & Smart Prompts

**Synonyms that map to actions**

* Invoices: `invoice`, `create invoice`, `bill customer`, `send invoice`, `invoice client`
* Payments: `payment`, `log payment`, `record payment`, `got paid`, `customer paid`
* Expenses/Bills: `bill`, `expense`, `create bill`, `pay for`, `spent on`

Prompted flow examples:

```
> invoice
Who's the customer? (type to search or create new)
> Acme Corp
How much?
> 1500
When is it due? (e.g., "30 days", "March 15", "net 30")
> 30 days
Description? (optional)
> Consulting services
```

```
> payment
Which invoice? (showing unpaid invoices)
  [1] INV-001: Acme Corp - $1,500 (due in 5 days)
  [2] INV-002: Beta LLC - $800 (overdue 2 days)
> 1
How much did they pay? (default: $1,500)
> 1500
Payment method? (check/bank/cash/card)
> bank
```

---

## 10) Core Command Set (verbs)

> These are discoverable via search and suggestions; no namespaces.

### Company & Setup

```
setup, company, users, switch
```

### Money In (AR)

```
invoice, bill-customer, payment, customers, aging
```

### Money Out (AP)

```
bill, expense, pay, vendors, owed
```

### Banking

```
accounts, import, reconcile, transfer, balance
```

### Reporting

```
dashboard, profit, balance-sheet, cash-flow, taxes
```

### System

```
help, history, templates, schedule, export
```

---

## 11) Flags & Speed Examples

```
invoice -c "Acme Corp" -a 1500 -d "30 days" --ref "Consulting Q1"
payment -a 1500 --ref INV-001 --method bank
bill -v "Office Depot" -a 89.50 --account supplies
```

---

## 12) Conversation Queries

```
What do I owe?
Who owes me money?
How much cash do I have?
How are we doing?
What's overdue?
What's coming up?
Show me last month
Tax time
```

---

## 13) Templates & Shortcuts

```
monthly rent            # run a saved template
utilities               # utility bill template
retainer invoice        # retainer template

save template "monthly rent"   # save the last command as a template
templates                      # list templates
```

---

## 14) Error Handling & Guardrails

* Fuzzy corrections: `invocie → invoice?`
* Anomaly warnings: “That payment is much higher than usual. Continue?”
* Required-context failures:

  * Missing company context ⇒ **422** with hint to switch company
  * Not a member of company ⇒ **403**
* `--draft` to save without posting GL
* **Open period lock**: forbid postings to closed periods

---

## 15) Security, Tenancy, Audit

* Policies per action; **Gate::before** allows superadmin
* Every write inside `DB::transaction`
* Idempotency on all writes; 24h retention per user+company
* Audit each command: raw string, parsed params, actor, tenant, results

---

## 16) Testing

* **Parser golden tests**: strings → `{action, params, missing}`
* **Contract tests** per action: params → domain result + GL rows
* **Performance probes**: log `keydown→suggestions` and `enter→posted`; alert if p75 regresses
* **E2E**: happy-path palette flows for invoice/payment/bill

---

## 17) Rollout

1. Ship palette with **invoice/payment/bill** + reports
2. Add templates/history and anomaly checks
3. Introduce xterm **/super/console** behind superadmin

---

## 18) Appendix: Example Workflows

### Creating Things

```
invoice          # New customer invoice
bill             # New vendor bill
expense          # Quick expense
customer         # Add customer
vendor           # Add vendor
account          # Add bank/GL account
```

### Money Movement

```
payment          # Customer paid me
pay              # I paid vendor/bill
transfer         # Move money between accounts
deposit          # Record deposit
withdrawal       # Record withdrawal
```

### Viewing Data

```
dashboard        # Main overview
customers        # Customer list
vendors          # Vendor list
invoices         # Invoice list
bills            # Bill list
transactions     # Recent transactions
balance          # Account balances
```

### Reports & Analysis

```
aging            # AR aging (who owes me)
owed             # AP aging (what I owe)
profit           # P&L statement
balance-sheet    # Balance sheet
cash-flow        # Cash flow
taxes            # Tax summary
```

---

## 19) Why this wins

* **One verb = one intent**, fast to learn and type
* **Autocomplete + prompts** do the heavy lifting, not the user
* **Same structured API** powers palette and the future xterm console

```

---

If you want the receipts, here are the existing sections this merges and polishes: core verbs and examples, natural phrases and smart prompts, flags and autocomplete, templates, error handling, multi-company, integrations, and overall rationale. :contentReference[oaicite:0]{index=0} :contentReference[oaicite:1]{index=1} :contentReference[oaicite:2]{index=2} :contentReference[oaicite:3]{index=3} :contentReference[oaicite:4]{index=4} :contentReference[oaicite:5]{index=5} :contentReference[oaicite:6]{index=6}

There. A real plan instead of terminal cosplay. Now you can wire it without tripping over yourself.
::contentReference[oaicite:7]{index=7}
```
