# A→Z Development & Deployment Plan

A practical, solo‑dev friendly roadmap for a **Laravel modular monolith** using **Postgres (module-per-schema + RLS)**, **Inertia + Vue 3 + Vite** for a snappy web UI, and a **versioned /api/v1** for your mobile app in 3–6 months. CLI lives in the same codebase so domain logic isn’t duplicated.

---

## 0) Goals

* **Speed**: sub-100 ms server responses for common pages; SSR + client hydration via Inertia.
* **Safety**: hard multi-tenant isolation with Postgres RLS, not just app-level checks.
* **Sane scope**: one repo, one deployable, clear module seams.
* **Future-proof**: real `/api/v1` now, mobile later without re-architecture.

---

## 1) Architecture Overview

* **App**: Laravel 11, PHP 8.3, Octane (Swoole or RoadRunner), Redis (cache/queue), Horizon, Inertia + Vue 3 + Vite.
* **DB**: Postgres 16+. Single database. **Schemas per module**: `auth`, `billing`, `crm`, etc.
* **Tenancy**: single DB table space. Every row has `company_id` (UUID). Enforced by **RLS**.
* **API**: `/api/v1` in the same app. Auth via **Sanctum** (SPA + token for mobile).
* **CLI**: First pass as **Artisan commands** (fastest). Optional: extract to **Laravel Zero** later.
* **Edge**: Cloudflare in front, object storage (S3-compatible) for user uploads.

```
[Client] —HTTP/HTTPS—> [Nginx] -> [Laravel (Octane)] -> [Postgres + Redis]
                                       |-> Inertia/Vue (SSR)
                                       |-> /api/v1 (Sanctum)
                                       |-> Artisan CLI / Queues (Horizon)
```

---

## 2) Repo & Module Layout

```
/ app
  /Domain
    /Auth
    /Billing
    /CRM
  /Http
    /Controllers
    /Middleware
  /Models
  /Policies
  /Services
/database
  /migrations         # prefixed by schema & module
  /seeders
/resources/js         # Vue 3 + Inertia pages/components
/routes
  api.php             # /api/v1 routes
  web.php             # web routes (Inertia)
/config
/tests                # Pest tests
```

* **Naming**: tables qualified with schema, e.g. `billing.invoices`, `crm.contacts`.
* **Migrations** grouped per module; each creates schema if missing and sets RLS.

---

## 3) Environments

* **Local**: Laravel Sail (Docker) with Postgres + Redis. Mailpit for email. MinIO for S3.
* **Staging**: same stack, smaller instances, seeded test tenants.
* **Prod**: region close to Pakistan (e.g., **ap-south-1** or **ap-southeast-1**). Nginx + Octane workers, Redis, Postgres managed or self-hosted.

---

## 4) Database Conventions

* **Schemas per module**: `auth`, `billing`, `crm`, `ops`, etc.
* **Columns**: every table that stores tenant data has `company_id uuid not null`.
* **Keys**: primary keys are UUIDs. Index `(company_id, created_at)` on event-like tables; unique with partial indexes where needed.
* **Timestamps**: `created_at`, `updated_at`, soft deletes where useful.

### Example Migration (schema + table + RLS)

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('create schema if not exists billing');

        Schema::create('billing.invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->index();
            $table->uuid('customer_id');
            $table->integer('amount_cents');
            $table->string('currency', 3)->default('USD');
            $table->string('status')->index();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });

        // RLS: isolate by company
        DB::statement('alter table billing.invoices enable row level security');
        DB::statement(<<<SQL
            create policy invoices_tenant_isolation on billing.invoices
            using (company_id = current_setting('app.current_company', true)::uuid)
            with check (company_id = current_setting('app.current_company', true)::uuid);
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('billing.invoices');
        // keep schema; dropping it is optional
    }
};
```

### Session-scoped Tenant Context Middleware

```php
namespace App\Http\Middleware;

use Closure; use Illuminate\Support\Facades\DB;

class SetTenantContext
{
    public function handle($request, Closure $next)
    {
        if ($user = $request->user()) {
            DB::statement("set local app.current_user = ?", [$user->id]);
            DB::statement("set local app.current_company = ?", [$user->company_id]);
        }
        return $next($request);
    }
}
```

* Register it globally or on routes that hit tenant data. With Octane, this remains safe because `set local` scopes to the transaction/request.

---

## 5) Laravel App Setup (Step‑by‑Step)

1. **New project**

```bash
composer create-project laravel/laravel app && cd app
php artisan key:generate
```

2. **Sail + services**

```bash
php artisan sail:install  # choose: postgres, redis
./vendor/bin/sail up -d
```

3. **Auth scaffolding (Breeze, Inertia + Vue + TS)**

```bash
composer require laravel/breeze --dev
php artisan breeze:install vue --ssr --typescript
npm install && npm run dev
```

4. **Sanctum** for SPA + token auth

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

5. **Octane + Horizon**

```bash
composer require laravel/octane laravel/horizon
php artisan octane:install   # choose swoole or roadrunner
php artisan horizon:install
```

6. **Queues & cache**: set Redis in `config/cache.php`, `config/queue.php`.
7. **Middleware**: register `SetTenantContext` after auth middleware.
8. **Policies**: gate admin vs member; restrict destructive actions.
9. **Observability**:

   * Dev: Laravel Telescope.
   * Prod: Sentry or Bugsnag; structured JSON logs.
10. **File storage**: S3-compatible (e.g., MinIO locally, Cloudflare R2/S3 in prod).

---

## 6) API v1 Design

* **Routes**: `/api/v1/...` in `routes/api.php` with `auth:sanctum`.
* **Resources**: use Eloquent API Resources; consistent response envelope `{ data, meta }`.
* **Versioning**: path-based (`/v1` now, freeze shapes), deprecate with headers later.
* **Rate limits**: per key and per IP. Example 60 req/min default, higher for trusted apps.
* **Docs**: generate OpenAPI using `l5-swagger` or `scribe` and host `/docs` behind auth.
* **Pagination**: `?page[size]=25&page[number]=2`, include `meta.total`, `meta.has_more`.
* **Errors**: JSON `{ error: { code, message, details } }` with standard HTTP codes.

---

## 7) Web UI (Inertia + Vue)

* **Inertia pages** for each module (e.g., `resources/js/Pages/Billing/Invoices/Index.vue`).
* **Layout**: shell with left nav, top bar, global toasts.
* **Forms**: server-side validation + client hints; optimistic updates when safe.
* **Tables**: server-driven filters/sort; keep queries indexed by `(company_id, ...)`.

---

## 8) Module Development Loop (repeat per module)

1. **Model the data**: table(s) in the module schema with `company_id`, add indexes.
2. **Migrate + RLS**: enable policy as shown above.
3. **Domain services** in `App/Domain/<Module>`; keep controllers thin.
4. **Web UI**: Inertia pages for CRUD and reports.
5. **API**: Resource controllers under `/api/v1/<module>`; API Resources for output.
6. **CLI**: Artisan commands calling the same services (no duplicated logic).
7. **Tests**: Pest unit + feature; a tenant fixture factory that sets `company_id`.
8. **Perf pass**: verify queries use indexes; cache hot lists in Redis.

---

## 9) Performance & Scale

* Turn on **OPcache**, **config/route/view cache**, **Octane** workers.
* Keep N+1 in check with `->with()` and `->select()`; prefer pagination.
* Long-running tasks to queues; shrink payloads; compress responses (gzip/br).
* Consider **pgbouncer** if connections get heavy; tune Postgres shared buffers, work mem.

---

## 10) Security Hardening

* Enforce HTTPS, HSTS, sane CORS for `/api`.
* CSRF protection on web routes (Inertia already aligned).
* Secrets via environment vars; rotate API tokens; audit logs for admin actions.
* Validate file uploads; set strict Content Security Policy headers.

---

## 11) CI/CD

* **Branching**: `main` (prod), `develop` (staging), feature branches.
* **Checks**: Pint (format), PHPStan (static analysis), Pest tests, Vue unit tests, build.
* **GitHub Actions** (sample):

```yaml
name: ci
on: [push]
jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3', extensions: mbstring, pdo_pgsql, redis }
      - run: composer install --no-interaction --prefer-dist
      - run: cp .env.example .env && php artisan key:generate
      - run: php vendor/bin/pint -v
      - run: php vendor/bin/phpstan analyse
      - run: php vendor/bin/pest
      - uses: actions/setup-node@v4
        with: { node-version: '20' }
      - run: npm ci && npm run build
```

* **Deploy**: separate workflow to build assets, push Docker image or rsync code, run `php artisan migrate --force`, cache warmup, Horizon restart.

---

## 12) Deployment (Prod)

* **Compute**: VPS or cloud in **ap-south-1 (Mumbai)** or **ap-southeast-1 (Singapore)**.
* **Web**: Nginx reverse proxy. App via **Octane** with Swoole, supervised by `systemd`.
* **DB**: Managed Postgres if possible; automatic nightly backups; point-in-time restore if offered.
* **Redis**: dedicated instance. **Horizon** for queues with `systemd` or Supervisor.
* **TLS & CDN**: Cloudflare proxy + Let’s Encrypt on origin; cache static assets; set long `Cache-Control` for built assets.

---

## 13) Backups & DR

* Nightly `pg_dump` to object storage (7 daily, 4 weekly, 6 monthly retention).
* Encrypt backups, test restore quarterly.
* `.env` and app secrets stored in your password manager and a sealed vault.

---

## 14) Rollout Timeline (8 weeks, realistic solo pace)

* **Week 1**: Project setup, auth, tenant model, SetTenantContext, schemas, RLS scaffolding.
* **Week 2**: First module (e.g., CRM): DB + RLS + services + web CRUD.
* **Week 3**: CRM API v1 endpoints + tokens + docs + tests.
* **Week 4**: CLI commands for CRM; queues + Horizon; telemetry in place.
* **Week 5**: Billing schema + invoicing basics; Stripe/Cashier if needed.
* **Week 6**: Billing API v1; finalise pagination, errors, rate limits.
* **Week 7**: Perf pass (Octane, indexes), security hardening, backup plan.
* **Week 8**: Staging soak test, seed demo tenants, production cutover.

---

## 15) Ready-to-use Snippets

### Base Model helper (auto set company\_id on create)

```php
namespace App\Models; use Illuminate\Database\Eloquent\Model;

abstract class TenantModel extends Model
{
    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (auth()->check() && empty($model->company_id)) {
                $model->company_id = auth()->user()->company_id;
            }
        });
    }
}
```

### Example API Resource

```php
use Illuminate\Http\Resources\Json\JsonResource;
class InvoiceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount_cents,
            'currency' => $this->currency,
            'status' => $this->status,
            'issued_at' => optional($this->issued_at)->toIso8601String(),
        ];
    }
}
```

### Rate Limiter example (in `AppServiceProvider`)

```php
use Illuminate\Support\Facades\RateLimiter; use Illuminate\Http\Request;
RateLimiter::for('api', function (Request $request) {
    $key = optional($request->user())->id ?: $request->ip();
    return Limit::perMinute(60)->by($key);
});
```

### Nginx (Octane) sketch

```
location / {
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_pass http://127.0.0.1:8000; # Octane server
}
```

---

## 16) What to skip (for v1 sanity)

* No microservices, no separate CLI service, no CQRS/event-sourcing adventures.
* No per-tenant databases unless a big enterprise contract mandates it.
* No GraphQL until REST v1 is stable and mobile ships.

---

## 17) Definition of Done (per module)

* ✅ DB schema + RLS policy + indexes
* ✅ Domain services + tests
* ✅ Web CRUD (Inertia/Vue) + validation
* ✅ API v1 endpoints + docs + rate limits
* ✅ CLI commands
* ✅ Telemetry + dashboards + alerts

---

## 18) Nice-to-haves (after v1)

* Background reporting with CSV/Excel exports
* Full-text search via Postgres `tsvector`
* WebSockets (Laravel WebSockets) for real-time updates
* Feature flags for risky changes

---

You now have a concrete, start-to-finish plan that avoids jargon where possible and includes copy‑pasteable pieces where it matters. Build the first module, prove the loop, and the rest is rinse and repeat.
