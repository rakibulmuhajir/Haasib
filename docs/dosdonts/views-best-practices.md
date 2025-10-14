# Views Dos and Don'ts

## Common Risks to Avoid
- **Embedding business logic in Blade templates** – Makes it hard to unit-test and often mirrors the same mistakes we saw in models reaching for `request()`/`session()` globally.
- **Leaking raw models into views** – Views accidentally trigger lazy loads or expose internal attributes (e.g., JSON columns used for settings) that were never meant for customers.

## Do This Instead
- Pass only prepared view models / resources to templates. Map Eloquent models to dedicated presenters or DTOs before rendering.
- Keep Blade simple: conditionals, loops, formatting. Push any branching or heavy computation back into controllers or view composers.
- Use components/layouts to enforce consistent modules (navigation, company switchers) rather than inline copying logic.
- Escape all dynamic output with `{{ }}` by default; reach for `{!! !!}` only when sanitized HTML is guaranteed.
- Snapshot feature/UI tests cover critical pages so refactors that move logic out of views stay safe.

## Quick Checklist
- [ ] Views receive DTOs/resources, not raw collections that can be mutated.
- [ ] No service container calls inside Blade.
- [ ] Shared data provided via view composers or Inertia props.
- [ ] Localization handled with `@lang`/`__()` helpers.
- [ ] UI covered by feature or browser tests.
