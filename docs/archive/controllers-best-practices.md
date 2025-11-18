# Controllers Dos and Don'ts

## Common Pitfalls Observed
- **Business logic leaked into Eloquent models and actions** – Controllers were forced to work around models that reached for `request()`/`session()` directly, making HTTP flows brittle.
- **No validation layer between HTTP and domain actions** – Without request objects or form requests, controllers risk forwarding unvalidated data into migrations or domain services.
- **Returned fields or relationships that don’t exist** – e.g. reading `$module->order` or `$company->currency` when only `menu_order`/`base_currency` are present leads to broken API contracts and “column not found” errors.
- **Forgetting to rename validation inputs when schemas change** – accepting `currency` in requests after the column became `base_currency` means writes silently do nothing.
- **Leaning on dynamic properties the model never exposes** – returning `$user->abilities` (without a mutator/accessor) or `$company->pivot->is_active` (when the relation never loads it) produces `null` in real responses or triggers attribute errors.
- **Using relationship query builders like collections** – calling `map()` on `$user->tokens()` (a builder) throws; always resolve to a collection first.
- **Filtering through the wrong column on pivot relations** – `modules()->where('module_id', …)` fails because the column lives on the pivot (`auth.company_modules`), not the `modules` table—use `wherePivot()` instead.

## Do This Instead
- Keep controllers as thin coordinators. Delegate validation to Form Requests, hand off work to application services/command handlers, and return resources/DTOs.
- Inject dependencies (actions, services) through the constructor; avoid resolving them at call time with `new`.
- Never rely on globals from inside the domain layer. Controllers set context (current company, user) explicitly and pass it along.
- Normalize error handling. Convert domain exceptions into HTTP responses via exception handlers or response macros.
- Resolve pivot/relationship data before formatting – use `$relation->get()->map()` or eager-loaded collections, not raw builders.
- Cross-check response payloads with the schema – if services expose `base_currency`, don't guess `currency` in the controller.
- Keep request validation in sync with schema name changes – when the DB uses `base_currency`, the validator needs to as well.
- Verify attributes exist before exposing them – add accessors (e.g. `getAbilitiesAttribute`) or adjust the response to available data.
- Cover controller flows with feature tests that exercise validation + authorization boundaries.

## Quick Checklist
- [ ] Form Request handles validation + authorization.
- [ ] Controller delegates to a service/action, not Eloquent directly.
- [ ] No `request()` calls outside the controller layer.
- [ ] Responses returned via resources or typed DTOs.
- [ ] Feature tests ensure 200/4xx/5xx branches behave as expected.
