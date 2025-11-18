# Services Dos and Don'ts

## Why Service Layer Matters
- Provide a stable façade over domain actions so controllers, console commands, and jobs share orchestration logic.
- Centralize cross-cutting concerns (transactions, authorization, caching) instead of duplicating them in each entry point.

## Highlighted Risks to Avoid
- **Directly new-ing domain actions** – bypasses the container and any middleware (logging, transactions) configured for actions.
- **Letting services mutate global state** – everything should be explicit inputs/outputs; avoid reads from `request()`, `session()`, or globals inside services.
- **Leaving controllers wired to methods that don't exist** – e.g., exposing `AuthService::changePassword()` before implementing it ensures runtime fatals when the endpoint is exercised.
- **Overloading services with controller-only responsibilities** – response formatting, HTTP error codes, and view selection belong in the presentation layer, not here.
- **Silently depending on an authenticated user** – exports that call `auth()->user()` explode in CLI/queue contexts where no guard is set.
- **Adding new permission checks without updating the service matrix** – ask `canAccessCompany($user, $company, 'view_modules')` only after that string is actually handled inside the service.
- **Shadowing method parameters inside loops** – reusing `$user` inside `foreach ($users as $user)` clobbers the argument you meant to audit with.
- **Querying pivot filters on the related table** – `->where('module_id', …)` against a `belongsToMany` relation hits the wrong table; use `wherePivot()` instead.
- **Dispatching to queues that aren’t configured** – calling `dispatch(...)->onConnection('accounting_queue')` fails if the queue connection is actually named `accounting`.

## Do This Instead
- Resolve dependencies through constructor injection so the framework can decorate services/actions with middleware.
- Keep services focused on orchestration: validate inputs (or expect DTOs), call domain actions/repositories, emit events, and return typed results.
- Wrap multi-step workflows in transactions owned by the service when atomicity is required; surface failures as domain exceptions.
- Design narrow contracts: `UserService::registerUser(RegisterUserData $data): User` reads better and is easier to test than accepting unstructured arrays.
- Make services stateless—no caching of mutable entities or storing request-specific data on the instance.
- Accept the acting user/request explicitly (or allow `?User`) so CLI jobs and background tasks can reuse the same code paths safely.
- Prefer `wherePivot()` / `withPivot()` helpers when filtering many-to-many relationships from services or commands.
- Cross-check queue names and connections: the string passed to `onConnection()`/`onQueue()` must match entries present in `queue.connections` and `queue.queues`.
- Keep the public API audited: when controllers expect `AuthService::changePassword()` (or similar), add the implementation—and a test—before wiring the endpoint.
- Cover services with unit/integration tests that mock domain actions; this guards against regressions while keeping controllers thin.

## Quick Checklist
- [ ] Dependencies injected via constructor (no `new` or `app()` inside methods).
- [ ] Service methods accept DTOs or validated data, return domain objects/DTOs.
- [ ] Transactions initiated only where needed and scoped tightly.
- [ ] No references to HTTP/session helpers or other global state.
- [ ] Service responsibilities documented so controllers/commands call the right abstraction.
