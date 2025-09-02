<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Normalize locales to BCP 47: replace underscore with hyphen (e.g., en_AE -> en-AE)
        try { DB::statement("update auth.companies set locale = replace(locale, '_', '-') where locale like '%_%'"); } catch (\Throwable $e) { /* ignore */ }

        // Set default locale to en-AE
        try { DB::statement("alter table auth.companies alter column locale set default 'en-AE'"); } catch (\Throwable $e) { /* ignore */ }

        // Helpful indexes for FK columns
        try { DB::statement('create index if not exists companies_base_currency_index on auth.companies (base_currency)'); } catch (\Throwable $e) { /* ignore */ }
        try { DB::statement('create index if not exists companies_language_index on auth.companies (language)'); } catch (\Throwable $e) { /* ignore */ }
        try { DB::statement('create index if not exists companies_locale_index on auth.companies (locale)'); } catch (\Throwable $e) { /* ignore */ }

        // Add foreign keys (wrapped for idempotence and cross-env tolerance)
        try { DB::statement("alter table auth.companies add constraint companies_base_currency_fk foreign key (base_currency) references currencies(code)"); } catch (\Throwable $e) { /* ignore */ }
        try { DB::statement("alter table auth.companies add constraint companies_language_fk foreign key (language) references languages(code)"); } catch (\Throwable $e) { /* ignore */ }
        try { DB::statement("alter table auth.companies add constraint companies_locale_fk foreign key (locale) references locales(tag)"); } catch (\Throwable $e) { /* ignore */ }
    }

    public function down(): void
    {
        // Drop the FKs if present
        try { DB::statement('alter table auth.companies drop constraint if exists companies_base_currency_fk'); } catch (\Throwable $e) { /* ignore */ }
        try { DB::statement('alter table auth.companies drop constraint if exists companies_language_fk'); } catch (\Throwable $e) { /* ignore */ }
        try { DB::statement('alter table auth.companies drop constraint if exists companies_locale_fk'); } catch (\Throwable $e) { /* ignore */ }

        // Optionally revert default (leaving as-is is typically fine)
        // try { DB::statement("alter table auth.companies alter column locale set default 'en_AE'"); } catch (\Throwable $e) { /* ignore */ }

        // Drop helper indexes
        try { DB::statement('drop index if exists companies_base_currency_index'); } catch (\Throwable $e) { /* ignore */ }
        try { DB::statement('drop index if exists companies_language_index'); } catch (\Throwable $e) { /* ignore */ }
        try { DB::statement('drop index if exists companies_locale_index'); } catch (\Throwable $e) { /* ignore */ }
    }
};

