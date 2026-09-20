# Quick Dev Reference

## 🚨 Before Any Change
1. Check schema contract: `docs/contracts/{schema}-schema.md`
2. Read relevant: `AI_PROMPTS/{topic}_REMEDIATION.md`

## 🧪 Tests Are Opt-In — Do Not Run Them Unattended
**Write the tests. Do not run them without being asked.**

The suite is slow (full run ~13 min, `npm run build` ~3 min). Running it
repeatedly is the single biggest waste of time in this repo.

- Finish the task, then **ask whether to run the tests** and wait for an answer.
- No full-suite sweeps, no "just to be safe" re-runs, no baseline comparisons
  unless explicitly asked for.
- **Never run two test processes at once** — they share the `haasib_test`
  database and clobber each other with "relation already exists".
- When asked to run them, run only the suites the change actually touches:
  `php artisan test tests/Feature/{Module}`
- State plainly that tests were written but not run. Don't claim verification
  that didn't happen.

## ❌ Never Do
```php
$table->id()                           // → uuid('id')->primary()
Schema::create('customers')            // → 'acct.customers' (schema prefix)
session('active_company_id')           // → app(CurrentCompany::class)->get()
Route::get('/resource', ...)           // → /{company}/resource
new Service()                          // → Bus::dispatch()
$request->validate([...])              // → FormRequest
```

```vue
<input>, <button>                      // → Shadcn components
export default { data() }              // → <script setup lang="ts">
fetch(), axios                         // → Inertia forms
```

## ✅ Required Patterns
```php
// Routes
Route::get('/{company}/resource', ...)->middleware(['auth', 'identify.company']);

// Company context
$company = app(CurrentCompany::class)->get();

// Auth
$this->hasCompanyPermission(Permissions::RESOURCE_ACTION);

// UUID models
protected $keyType = 'string';
public $incrementing = false;
```

## 📐 Schemas
- `auth` - users, companies, permissions
- `acct` - financial, customers, invoices
- `hsp` - hospitality
- `crm` - marketing
- `audit` - logs

## 🎯 Common Edits

### Add Column
1. Update schema contract
2. Migration with RLS
3. Add to model `$fillable`/`$casts`

### Inline Edit
Use `useInlineEdit()` composable + `<InlineEditable>` component
Only for: simple fields, no calculations, no side effects

### Error Handling
- Validation: inline errors
- Server errors: Sonner toast
- See `AI_PROMPTS/toast.md`

## 🔐 RBAC Quick
```bash
# 1. Add to app/Constants/Permissions.php
# 2. php artisan rbac:sync-permissions
# 3. Update config/role-permissions.php
# 4. php artisan rbac:sync-role-permissions
```

## 🛠️ Dev Server
```bash
php artisan octane:start --server=frankenphp --port=9001 --watch
npm run dev
# → http://localhost:9001  (app; Vite serves assets on 5173)
```

## 📚 Key Docs
- **Test commands: `TEST_COMMANDS.md`** — how to run them, and the known pre-existing failures
- **Design system: `docs/ledger-design-system.md`** — read before building any page
- Tokens/skins: `docs/theming.md`
- Schemas: `docs/contracts/`
- Patterns: `AI_PROMPTS/`
- UX: `docs/frontend-experience-contract.md`
- Specs: `docs/ui-screen-specifications.md`
