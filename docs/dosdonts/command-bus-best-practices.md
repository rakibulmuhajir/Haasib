# Command Bus Dos and Don'ts

## Highlighted Missteps
- **Actions manually instantiated other actions** – `new CreateCompany()` inside `RegisterUser` bypassed the IoC container and any middleware (transactions, logging) the command bus could provide.
- **Lacked transactional boundaries** – Commands called each other and opened new transactions manually, risking orphaned writes when one step failed.

## Do This Instead
- Treat actions/commands as container-managed. Resolve dependencies through constructor injection so middleware (transactional, logging, authorization) can be applied consistently.
- Use Laravel's bus/Jobs or a custom command bus to dispatch domain commands. Example: `Bus::dispatch(new RegisterUserCommand(...))`.
- Prefer wrapping multi-step workflows in a single command or service that owns the transaction, instead of nested `DB::beginTransaction()` calls scattered across actions.
- Separate synchronous vs asynchronous commands; give jobs clear DTO payloads rather than array hashes.
- Register command handlers explicitly so they can be tested in isolation and decorated with middleware.

## Quick Checklist
- [ ] Commands resolved via the container, not `new`.
- [ ] One transaction per workflow, owned by the outer command.
- [ ] Command contracts (DTOs) are explicit and typed.
- [ ] Middleware/pipeline handles cross-cutting concerns (logging, metrics, auth).
- [ ] Unit tests cover command handler behavior without the bus.
