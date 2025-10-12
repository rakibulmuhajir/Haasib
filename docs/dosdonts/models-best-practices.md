# Models Dos and Don'ts

## Highlighted Missteps
- **Applied `HasUuids` to a pivot without an `id` column** – Laravel tried to generate a UUID for a non-existent primary key on `auth.company_user`, breaking inserts.
- **Persisted `$this->settings` with no backing column** – `User::setSetting()` attempted to update a column that the `auth.users` table does not provide.
- **Called `request()` / `auth()` without guards in CLI contexts** – Queue workers and console commands lack HTTP helpers, leading to undefined-function or null-pointer errors.
- **Left relationships pointing at phantom columns** – `JournalTransaction::reference()` expected `reference_type`/`reference_id`, but the migration never created them, so every call crashed at runtime.

## Do This Instead
- Let composite pivots extend `Model` without UUID traits; declare `$incrementing = false` and `$keyType = 'string'` only when the table actually has a surrogate key.
- Keep model accessors/mutators aligned with the schema. Add JSON columns (via migration) before storing structured settings, or move ad-hoc state to a dedicated `settings` relation/table.
- Audit every `$fillable`, `$casts`, and relationship against the latest migrations; delete or feature-flag code paths that depend on columns still on the roadmap.
- Wrap HTTP-only helpers behind feature detection: `if (function_exists('request') && request())` before reading IPs or sessions; inject dependencies where possible.
- Centralize audit hooks in observers or events to avoid repeating request/auth checks across models.
- Prefer value objects or DTOs for complex state changes; models should stay thin and persistence-focused.

## Spatie Laravel Permission Requirements

### HasRoles Trait - MANDATORY

**CRITICAL**: Any User model that uses permission checking (`$user->hasPermissionTo()`) MUST include the `HasRoles` trait:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles, /* other traits */;
}
```

**Common Error**: `BadMethodCallException: Call to undefined method App\Models\User::hasPermissionTo()`
**Solution**: Add the `HasRoles` trait to the User model.

### Permission System Dependencies

Before using permission checking, verify these exist:

1. **Database Tables**: `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`
2. **Configuration**: `config/permission.php` table names match actual schema
3. **Custom Models**: If using UUIDs, create custom Permission/Role models extending Spatie base classes

### Setup Workflow

```php
// 1. Create permissions first
use Spatie\Permission\Models\Permission;

Permission::firstOrCreate(['name' => 'customers.create', 'guard_name' => 'web']);

// 2. Test permission checking (should return false, not error)
$user = new User();
$canCreate = $user->hasPermissionTo('customers.create');
```

### Troubleshooting Pattern

| Error | Cause | Fix |
|-------|--------|-----|
| `hasPermissionTo()` undefined | Missing `HasRoles` trait | Add trait to User model |
| `Undefined table: auth.permissions` | Wrong table names in config | Update `config/permission.php` |
| `UUID operator does not exist` | Permission models lack UUID config | Create custom models with UUID support |
| `column team_id does not exist` | Teams feature enabled | Set `'teams' => false` in config |

## Quick Checklist
- [ ] Traits match actual columns (UUIDs, timestamps, soft deletes).
- [ ] Accessors/mutators correspond to real database fields.
- [ ] No direct reliance on global helpers inside jobs/CLI without guards.
- [ ] Pivot models stay free of conflicting primary-key traits.
- [ ] Domain rules live in actions/services, not inside Eloquent events.
- [ ] **User models have `HasRoles` trait if using `hasPermissionTo()`**.
- [ ] **Permission tables exist before using permission checking**.
- [ ] **Config table names match actual database schema**.
