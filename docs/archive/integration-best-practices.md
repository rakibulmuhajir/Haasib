# Integration Dos and Don'ts

## Pitfalls We Hit
- **Double-prefixed API routes** – Registering routes with `Route::prefix('api/v1')` *and* adding another `Route::prefix('v1')` inside the route file yielded `/api/v1/v1/...`, breaking every documented endpoint.
- **Referencing middleware aliases that were never registered** – Wiring routes behind `middleware('superadmin')` without calling `$router->aliasMiddleware()` made the entire section unreachable.
- **Relying on optional Redis extensions without guards** – `Redis::COMPRESSION_LZ4` or other constants throw fatals when the PHP Redis extension wasn’t compiled with that feature.

## Do This Instead
- Decide on the prefix once. Either register it in the service provider *or* in the routes file, but not both. Feature tests should assert the final URL (`/api/v1/...`).
- Whenever you reference a middleware alias, register it in your service provider (or `Kernel`) before shipping. Add a smoke test that hits at least one route behind that middleware to guarantee boot-time wiring is correct.
- Feature-detect optional Redis capabilities (compression, JSON serializer) before using them in config files; provide graceful fallbacks.
- Keep configuration/bootstrap changes in sync with the README and sample requests so integrators see the exact paths and guards required.

## Quick Checklist
- [ ] Route prefixes applied in a single place (provider *or* routes file).
- [ ] Custom middleware aliases registered during boot.
- [ ] High-value routes covered by smoke/feature tests (happy-path + 403 + 404).
- [ ] README/API docs updated when paths or middleware change.
