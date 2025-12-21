# Quick Dev Reference

## 🚨 Before Any Change
1. Check schema contract: `docs/contracts/{schema}-schema.md`
2. Read relevant: `AI_PROMPTS/{topic}_REMEDIATION.md`

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
# → http://localhost:5180
```

## 📚 Key Docs
- Schemas: `docs/contracts/`
- Patterns: `AI_PROMPTS/`
- UX: `docs/frontend-experience-contract.md`
- Specs: `docs/ui-screen-specifications.md`
