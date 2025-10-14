# Modules Architecture & Scaffolding Guide

> **ARCHIVE NOTICE**: This scaffolding guide targets the legacy module tooling. The active workspace uses the `stack/modules/*` structure and Constitution v2.2.0 guardrails; treat this file as historical reference only.

This guide defines how custom business modules live inside the `modules/` directory, how they register themselves with Laravel, and how to scaffold new modules (including CLI parity) using the `module:make` artisan command.

## 1. Directory Layout

```
laravel-root/
├── app/                 # Framework + shared app code
├── modules/
│   └── <ModuleName>/
│       ├── Domain/      # Actions, Services, Policies, Jobs specific to module
│       ├── Database/
│       │   ├── migrations/
│       │   └── seeders/
│       ├── Http/
│       │   ├── Controllers/
│       │   └── Middleware/
│       ├── CLI/
│       │   ├── Commands/            # `php artisan` commands
│       │   └── Palette/             # palette entity/verb metadata + parser snippets
│       ├── Providers/               # ModuleServiceProvider + RouteServiceProvider
│       ├── Resources/
│       │   ├── lang/
│       │   └── views/
│       ├── routes/
│       │   ├── web.php
│       │   └── api.php
│       ├── Tests/
│       │   ├── Feature/
│       │   └── Unit/
│       └── module.json              # metadata (slug, version, schema, permissions)
├── config/modules.php               # global registry
└── ...
```

### Naming Conventions
- Module namespaces: `Modules\\<ModuleName>`.
- Domain classes: `Modules\\<ModuleName>\\Domain\\...`.
- CLI commands: `Modules\\<ModuleName>\\CLI\\Commands` (auto-discovered by provider).
- Palette metadata: `Modules/<ModuleName>/CLI/Palette/registry.php` returning `CommandDef[]` fragments merged into the global palette registry.

## 2. Module Registry (`config/modules.php`)

Each module registers metadata used by service providers and the company-module toggle system.

```php
return [
    'modules' => [
        'invoicing' => [
            'name' => 'Invoicing',
            'namespace' => 'Modules\\Invoicing',
            'provider' => Modules\Invoicing\Providers\ModuleServiceProvider::class,
            'schema' => 'acct',               // defaults to obfuscated acct schema
            'routes' => [
                'web' => true,
                'api' => true,
            ],
            'cli' => [
                'commands' => true,
                'palette' => true,
            ],
            'permissions' => [
                'invoices.view',
                'invoices.create',
                'invoices.post',
                'payments.allocate',
            ],
        ],
    ],
];
```

The ModuleServiceProvider reads this config and registers migrations, translations, views, command bus actions, palette metadata, and CLI commands conditionally (e.g., company has module enabled).

## 3. Scaffolding Command: `php artisan module:make {name}`

### Command Responsibilities
- Create the module directory structure (`modules/<Name>`).
- Generate default files:
  - `Providers/ModuleServiceProvider.php`
  - `Providers/RouteServiceProvider.php`
  - `Database/migrations/.gitkeep`
  - `routes/web.php`, `routes/api.php`
  - `CLI/Commands/<Name>Command.php` (example command stub)
  - `CLI/Palette/registry.php` stub exporting an empty command-def array
  - `Domain/Actions/.gitkeep`, `Domain/Services/.gitkeep`
  - `Tests/Feature/<Name>FeatureTest.php`, `Tests/Unit/<Name>UnitTest.php`
  - `module.json` with slug, version, schema hint (defaults to `acct`).
- Add an entry to `config/modules.php` (respecting alphabetical order and slug casing).
- Register the module’s command bus placeholder: create `Domain/Actions/RegisterActions.php` returning an array of action => class mappings and include it during provider boot.
- Publish translation & language stubs (`Resources/lang/en/messages.php`, `Resources/lang/ar/messages.php`).

### Command Options
- `--schema=`: override the default schema (`acct`).
- `--cli-only`: generate service provider + CLI assets without web/api scaffolding (for devops modules).
- `--force`: overwrite existing module directory.

### CLI Palette Integration
`module:make` creates a `CLI/Palette/registry.php` stub:

```php
<?php

use App\Palette\Contracts\CommandDef;

return [
    [
        'id' => 'example.action',
        'label' => 'example',
        'aliases' => ['example'],
        'needs' => [],
        'executeAction' => 'example.action',
        'rbac' => [],
    ],
];
```

The ModuleServiceProvider collects these fragments and merges them into the global registry when the module is enabled.

## 4. Module Enabling & Company Toggles

1. **System registry**: `core.modules` table stores module definitions.
2. **Company toggle**: `core.company_modules` tracks which modules are active per company.
3. **Service provider guard**: each ModuleServiceProvider checks the current company context (`ServiceContext->getCompanyId()`) and only bootstraps routes/migrations/CLI if the module is active.
4. **CLI visibility**: palette registry filters commands by active modules; disabled modules hide their verbs and return 404 when invoked.

## 5. Command Bus Registration Flow

1. Module exposes an `Actions/registry.php` map: `['invoices.create' => Actions\InvoiceCreate::class]`.
2. ModuleServiceProvider merges that map into `CommandBus::extend()` during boot.
3. Actions live in `Modules/<Name>/Domain/Actions` and extend the base `App\Support\CommandAction` (enforcing ServiceContext injection, audit logging, idempotency).
4. Controllers and CLI commands use the same bus entry:

```php
CommandBus::dispatch('invoices.create', $params, $context);
```

## 6. CLI Command Wiring

- Artisan commands in `CLI/Commands` are auto-loaded via the ModuleServiceProvider `commands()` method.
- Palette metadata from `CLI/Palette/registry.php` is merged into the global command palette.
- Parser fragments (optional) can live under `CLI/Palette/parser.php` returning arrays of alias/regex handlers; the provider publishes them to the client-side build step.

## 7. Testing Expectations

- `Tests/Feature` should include CLI + HTTP parity tests using the command bus.
- `Tests/Unit` cover domain services/actions.
- When the scaffolding command runs, it optionally adds the new module to the Playwright CLI probe suite (if CLI support requested).

---

## 8. Workflow Summary

1. Run `php artisan module:make Payments`.
2. Adjust generated `module.json` (slug, permissions, schema).
3. Implement domain actions, services, migrations.
4. Update `CLI/Palette/registry.php` with verbs and ensure `Domain/Actions` register the same command bus IDs.
5. Run tests and update docs/trackers per the Lightbearer playbook.

---

## 9. Open Tasks

- Implement `module:make` artisan command (new console command + stubs).
- Build ModuleServiceProvider base class with helpers for command bus/CLI registration.
- Update the company-module toggle system to read from `config/modules.php` and reflect module metadata.
- Extend CLI palette build step to consume module registry fragments at compile time.
