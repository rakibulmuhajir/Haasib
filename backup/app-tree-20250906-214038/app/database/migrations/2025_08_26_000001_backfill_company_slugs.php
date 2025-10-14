<?php
// database/migrations/2025_08_26_000001_backfill_company_slugs.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        // Backfill null/empty slugs
        $rows = DB::table('auth.companies')->select('id','name','slug')->get();
        $used = DB::table('auth.companies')->pluck('slug')->filter()->all();
        $used = array_fill_keys($used, true);

        foreach ($rows as $r) {
            if ($r->slug) continue;
            $base = Str::slug($r->name ?? '') ?: Str::slug(Str::uuid());
            $slug = $base; $i = 1;
            while (isset($used[$slug])) $slug = $base.'-'.$i++;
            DB::table('auth.companies')->where('id', $r->id)->update(['slug' => $slug]);
            $used[$slug] = true;
        }

        // Add a unique index if you want it enforced at DB level
        DB::statement('create unique index if not exists companies_slug_unique on auth.companies (slug)');
    }

    public function down(): void
    {
        // Optional: drop the unique index
        DB::statement('drop index if exists companies_slug_unique');
    }
};
