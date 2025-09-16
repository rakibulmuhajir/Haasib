# A→Z Development & Deployment Plan (v2)

A practical, solo‑dev friendly roadmap for a **Laravel modular monolith** using **Postgres (module-per-schema + RLS)**, **Inertia + Vue 3 + Vite** for a snappy web UI, and a **versioned /api/v1** for your mobile app in 3–6 months. CLI lives in the same codebase so domain logic isn’t duplicated.

---

## 0) Goals

* **Speed**: sub-100 ms server responses for common pages; SSR + client hydration via Inertia.
* **Safety**: hard multi-tenant isolation with Postgres RLS, not just app-level checks.
* **Sane scope**: one repo, one deployable, clear module seams.
* **Future-proof**: real `/api/v1` now, mobile later without re-architecture.

---

## 1) Architecture Overview

* **App**: Laravel 12, PHP 8.3, Octane (Swoole or RoadRunner), Redis (cache/queue), Horizon, Inertia + Vue 3 + Vite.
* **DB**: Postgres 16+. Single database. **Schemas per module**: `auth`, `billing`, `crm`, etc.
* **Tenancy**: single DB table space. Every row has `company_id` (UUID). Enforced by **RLS**.
* **API**: `/api/v1` in the same app. Auth via **Sanctum** (SPA + token for mobile).
* **CLI**: DevOps tasks run through a command bus/palette (Artisan commands deprecated).
* **Edge**: Cloudflare in front, object storage (S3-compatible) for user uploads.

```
[Client] —HTTP/HTTPS—> [Nginx] -> [Laravel (Octane)] -> [Postgres + Redis]
                                       |-> Inertia/Vue (SSR)
                                       |-> /api/v1 (Sanctum)
                                       |-> Command bus / Queues (Horizon)
```

---

### 1A) Accounting Core & Multi-company Tenancy

* **Double-entry ledger core**: add `ledger.ledger_accounts`, `ledger.journal_entries` (header), `ledger.journal_lines` (debit/credit). All financial features post balanced entries via domain services. Invoices/bills are immutable; changes via credit notes or reversing entries.
* **Multi-company users**: support `company_user` pivot and an **active company** switcher. Update `SetTenantContext` to derive `app.current_company` from the active company, not just `users.company_id`. Jobs/CLI accept `--company=<uuid>` and set context before work.
* **Payments driver layer**: strategy pattern for Stripe now, local gateways (JazzCash/Easypaisa) later, with webhook reconciliation and alerts.

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

* **Local**: Laravel Sail (optional), or native stack, with Postgres + Redis. Mailpit for email. MinIO for S3.
* **Staging**: same stack, smaller instances, seeded test tenants.
* **Prod**: region close to Pakistan (e.g., **ap-south-1** or **ap-southeast-1**). Nginx + Octane workers, Redis, Postgres managed or self-hosted.

---

## 4) Database Conventions

* **Schemas per module**: `auth`, `billing`, `crm`, `ops`, etc.
* **Columns**: every table that stores tenant data has `company_id uuid not null`.
* **Keys**: primary keys are UUIDs. Index `(company_id, created_at)` on event-like tables; unique with partial indexes where needed.
* **Timestamps**: `created_at`, `updated_at`, soft deletes where useful.
* **Auditability**: tables representing user-creatable resources should have `created_by_user_id uuid nullable` and `updated_by_user_id uuid nullable` columns, with foreign keys to `users.id` and `onDelete('set null')`. This provides a clear audit trail.

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

* Register it globally or on routes that hit tenant data. With Octane, this remains safe because `set local` is request-scoped.

### Per-request transaction middleware (writes-only)

Use a transaction per HTTP request that **mutates** data so partial failures don’t leak. Do not apply to streaming/SSE/WebSockets. Queue jobs are already wrapped per job when you call `DB::transaction` inside services.

```php
namespace App\Http\Middleware;
use Closure; use Illuminate\Support\Facades\DB;

class TransactionPerRequest
{
    public function handle($request, Closure $next)
    {
        if (in_array($request->method(), ['POST','PUT','PATCH','DELETE'])) {
            return DB::transaction(fn () => $next($request));
        }
        return $next($request);
    }
}
```

* Register after `SetTenantContext`. Keep idempotency keys for POST (see API conventions).

---

### 4A) Constraints & Money Precision

* **DB constraints**: CHECKs for amounts (`>= 0`), valid statuses, and FK integrity. Postgres-first migrations only (purge MySQLisms like `AUTO_INCREMENT`).
* **Money math**: store minor units per currency (e.g., cents) and use a library such as `brick/money` for calculations and formatting.
* **Idempotency**: enforce `Idempotency-Key` on all mutating endpoints touching money.

---

## 5) Laravel App Setup (Step‑by‑Step)

1. **New project**

```bash
composer create-project laravel/laravel app && cd app
php artisan key:generate
```

2. **Sail + services (optional)**

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
7. **Middleware**: register `SetTenantContext` and `TransactionPerRequest` after auth.
8. **Policies**: gate admin vs member; restrict destructive actions.
9. **Observability**:

   * Dev: Laravel Telescope (local/staging only).
   * Prod: **Sentry** for errors + performance traces; structured JSON logs.
   * Uptime: external monitor hitting `/health` every 1 min (Better Stack, Uptime Kuma, etc.).
   * Add a `/health` route returning DB, Redis, queue, and app version.
10. **File storage**: S3-compatible (e.g., MinIO locally, Cloudflare R2/S3 in prod).

---

### 5A) Local Dev Without Docker (because you hate it)

Pick one path and ignore the rest.

**Option 1: Laravel Herd (simple, Mac or Windows)**

1. Install **Laravel Herd** (official) which bundles PHP, Nginx, and useful tools.
2. Enable Postgres and Redis via your OS package manager:

   * **macOS (Homebrew)**: `brew install postgresql@16 redis node` then `brew services start postgresql@16 redis`.
   * **Windows**: Install **Postgres 16** (EnterpriseDB installer) and **Redis for Windows** (Memurai or Redis-on-WSL). Install **Node 20+** from nodejs.org.
3. Create DB and user:

   ```sh
   createuser -s appuser
   createdb appdb -O appuser
   psql -c "alter user appuser with password 'secret';"
   ```
4. In `.env`: set `DB_CONNECTION=pgsql`, `DB_DATABASE=appdb`, `DB_USERNAME=appuser`, `DB_PASSWORD=secret`, `REDIS_HOST=127.0.0.1`.
5. Install deps and run:

   ```sh
   composer install
   php artisan key:generate
   php artisan migrate
   npm ci && npm run dev
   php artisan octane:start --watch
   ```

**Option 2: Native stack (no Herd, no Docker)**

* **macOS (Homebrew)**

  ```sh
  brew install php@8.3 composer postgresql@16 redis node
  brew services start php@8.3 postgresql@16 redis
  ```
* **Windows (WSL2 Ubuntu)**

  ```sh
  wsl --install -d Ubuntu
  sudo apt update && sudo apt install -y php8.3 php8.3-fpm php8.3-xml php8.3-pgsql php8.3-redis unzip git redis postgresql nodejs npm
  ```
* **Linux (Ubuntu/Debian)**

  ```sh
  sudo apt update && sudo apt install -y php8.3 php8.3-fpm php8.3-xml php8.3-pgsql php8.3-redis unzip git redis postgresql nodejs npm
  sudo systemctl enable --now postgresql redis
  ```
* Create DB/user and set `.env` as shown above, then run `composer install`, `php artisan migrate`, `npm run dev`, and `php artisan octane:start`.

**Option 3: Valet (macOS) for pretty `*.test` URLs**

1. `composer global require laravel/valet && valet install`
2. From your project folder: `valet link app && valet open`

**RLS quick start locally**

1. Add the **SetTenantContext** middleware so each request sets `app.current_company`.
2. In each migration: `alter table <schema>.<table> enable row level security;` and create a policy using `current_setting('app.current_company', true)`.
3. Seed a test company and users; verify you cannot read another company’s rows even via `psql` when the setting is different.

**Quality-of-life**

* Install **Pint** and **PHPStan** for quick feedback.
* Use **Telescope** only in local/staging.
* Add `make` scripts or npm scripts: `make dev`, `make test`, `make lint`.

---

### 5C) Onboarding & Billing Flow (Week 1 add-on)

* Self-serve wizard: create company, seed chart of accounts, Stripe Checkout session, set first user as owner.
* Persist `stripe_customer_id` and `subscription_id` on the company; background reconciliation job and alerts on failed webhooks.

---

## 6) API v1 Design

* **Routes**: `/api/v1/...` in `routes/api.php` with `auth:sanctum`.
* **Conventions**:

  * **IDs**: UUID strings.
  * **Fields**: `snake_case` keys.
  * **Time**: ISO‑8601 UTC (`2025-08-19T12:34:56Z`).
  * **Pagination**: `?page[number]=1&page[size]=25` plus `{ meta: { total, current_page, per_page }, links: { next, prev } }`.
  * **Filtering**: `?filter[status]=paid&filter[q]=search`.
  * **Sorting**: `?sort=-created_at,amount_cents` (minus for desc).
  * **Errors**: `{ error: { code, message, details } }` with standard HTTP codes.
  * **Idempotency**: Accept `Idempotency-Key` on POST/PUT to avoid double writes.
* **Resources**: use Eloquent API Resources; keep responses in `{ data, meta }` envelope.
* **Rate limits**: default 60 req/min per user/IP; custom buckets for webhooks or internal apps.
* **Docs**: generate OpenAPI with `l5-swagger` or `scribe` and host `/docs` behind auth.
* **Security**: Sanctum tokens for first‑party clients; CSRF on web routes; strict CORS for `/api`.
* **Caching**: `ETag` and `If-None-Match` on common GETs.
* **Health**: `/health` returns `{ status: 'ok', version, services: { db, redis, queue } }`.

---

### API Lifecycle & Deprecation

* **Versioning**: path-based `/v1`; publish deprecation headers for breaking changes and keep a 90-day grace window.
* **Structured error codes**: e.g., `INVOICE_NOT_FOUND`, `PAYMENT_DUPLICATE` alongside human messages.

### Webhooks & Reconciliation

* Handle Stripe/local gateway webhooks with retries; reconcile payments daily and flag mismatches.

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
3. **Domain services** in `App/Domain/<Module>`; keep controllers thin. Extract complex model lifecycles (e.g., `draft` → `sent` → `paid`) into dedicated **State Machine** classes.
4. **Web UI**: Inertia pages for CRUD and reports.
5. **API**: Resource controllers under `/api/v1/<module>`; API Resources for output.
6. **CLI**: Command bus/palette invoking the same services (no duplicated logic).
7. **Tests**: Pest unit + feature; a tenant fixture factory that sets `company_id`.
8. **Perf pass**: verify queries use indexes; cache hot lists in Redis.

---

## 9) Performance & Scale

* Turn on **OPcache**, **config/route/view cache**, **Octane** workers.
* Keep N+1 in check with `->with()` and `->select()`; prefer pagination.
* Long-running tasks to queues; shrink payloads; compress responses (gzip/br).
* Consider **pgbouncer** if connections get heavy; tune Postgres shared buffers, work mem.

---

### 9A) Caching Strategy

* Redis-tagged caches for tax rates, currency tables, and chart-of-accounts trees; explicit invalidation when these change.

### 9B) Reporting & Search

* Use Postgres **materialized views** or summary tables for P\&L, balance sheet, aging; refresh on schedule.
* For application-level summary tables (like `accounts_receivable`), use **scheduled commands** (e.g., a nightly `ar:update-aging` command) to prevent data from becoming stale.

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

### Pre-deploy backup hook & zero-downtime

* CI step to `pg_dump` with checksums to object storage before running migrations.
* Two-phase migrations: add nullable/new tables first; backfill; enforce non-null in a later deploy.
* Zero-downtime release (atomic symlink switch); use maintenance page only for hard migrations.

---

## 12) Deployment (Prod)

* **Region**: pick close latency to Pakistan: **ap-south-1 (Mumbai)** or **ap-southeast-1 (Singapore)**.
* **Compute**: VPS or cloud in that region. Nginx reverse proxy. App via **Octane** with Swoole, supervised by `systemd`.
* **DB**: Managed Postgres if possible; automatic nightly backups; point-in-time restore if offered.
* **Redis**: dedicated instance. **Horizon** for queues with `systemd` or Supervisor.
* **TLS & CDN (Cloudflare)**:

  * Proxy DNS through Cloudflare, enable **Brotli**, **HTTP/3 + 0‑RTT**, **Early Hints**.
  * **Cache Rules**: cache `/build/*` (hashed assets) for 1 year; **bypass** `/api/*`, `/health`, `/horizon`, `/telescope`.
  * **Security**: enable WAF, Bot Fight Mode, and strict TLS. Set **HSTS** at the edge.
  * **Origin**: use Cloudflare Origin Certs; set real IP headers; long `Cache-Control` for built assets.

---

### 12A) Maintenance window note

* If maintenance mode is required, show a friendly 503 page and keep webhooks processing via a bypass route/worker.

---

## 13) Backups & DR

* Nightly `pg_dump` to object storage (7 daily, 4 weekly, 6 monthly retention).
* Encrypt backups, test restore **weekly** into a staging DB.
* **RLS validation drill**: after restore, set `app.current_company` to two different UUIDs and assert cross-company reads are denied.
* `.env` and app secrets stored in your password manager and a sealed vault.

---

## 14) Rollout Timeline (revised)

* **Week 0**: Local env, first RLS-enabled table, end-to-end auth, onboarding skeleton.
* **Week 1**: Tenant model, SetTenantContext, schemas, RLS policies, onboarding wizard, COA seed.
* **Week 2**: Ledger core (accounts, journal entries/lines) + tests for balanced postings.
* **Week 3**: First business module (e.g., CRM or Invoicing): DB + RLS + services + web CRUD.
* **Week 4**: API v1 for the module + OpenAPI docs + idempotency; queue jobs with company context.
* **Week 5**: Billing/payments integration (Stripe driver) + webhook reconciliation; tax rates + PK GST.
* **Week 6**: Reports v1 (aging, simple P\&L) via materialized views; search indexes.
* **Week 7**: Perf pass (Octane, indexes), metrics/alerts, backup/restore drill with balance verification.
* **Week 8**: Staging soak, seed demo tenants, zero-downtime production cutover.
* **Optional Weeks 9–12**: Mobile-ready API polish, local payment drivers, i18n.

---

## 15) Ready-to-use Snippets) Ready-to-use Snippets

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
use Illuminate\Support\Facades\RateLimiter; use Illuminate\Http\Request; use Illuminate\Cache\RateLimiting\Limit;
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

* ✅ DB schema in correct Postgres **module schema** + **RLS policy** + indexes + CHECK/FK constraints
* ✅ Domain services + tests (unit + happy-path feature); money ops wrapped in explicit `DB::transaction`
* ✅ Web CRUD (Inertia/Vue) + server validation + flash/errors
* ✅ API v1 endpoints + **OpenAPI** docs + **rate limits** + **idempotency on all writes** + structured error codes
* ✅ CLI commands using the same services; jobs/commands set `app.current_company`
* ✅ Complex model statuses managed by a dedicated **State Machine** class
* ✅ Long-running data sync operations (e.g., A/R updates from invoices) are offloaded to **queued jobs**
* ✅ **Audit trail** for create/update/void actions on financial entities; immutable docs with credit notes
* ✅ `/health` reflects module deps; **metrics/alerts** wired (queue depth, p95, DB slow queries)
* ✅ **Reconciliation** jobs and alerts for payment webhooks; **caching** updated and invalidated correctly
* ✅ Backup includes new tables; **reporting views** updated; Cloudflare cache rules updated for new assets

---

## 18) Nice-to-haves (after v1)) Nice-to-haves (after v1)

* Background reporting with CSV/Excel exports
* Full-text search via Postgres `tsvector`
* WebSockets (Laravel WebSockets) for real-time updates
* Feature flags for risky changes

---

You now have the improved plan with transactions, strict API conventions, Cloudflare tuning, weekly restore drills, and a tighter Definition of Done. Build the first module, prove the loop, and the rest is rinse and repeat.

---
