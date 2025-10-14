<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Postgres-first approach using raw SQL to avoid doctrine/dbal dependency
        try {
            // Add a temporary UUID column if it doesn't exist
            DB::statement("alter table sessions add column if not exists user_id_uuid uuid");
            DB::statement("create index if not exists sessions_user_id_uuid_index on sessions (user_id_uuid)");

            // Best-effort migrate values only if they already look like UUIDs; otherwise keep null
            try {
                DB::statement(<<<SQL
                    update sessions
                    set user_id_uuid = nullif(user_id::text,'')::uuid
                    where user_id is not null
                      and user_id::text ~* '^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'
                SQL);
            } catch (\Throwable $e) { /* ignore cast failures on non-Pg drivers */ }

            // Drop old index if present
            try { DB::statement('drop index if exists sessions_user_id_index'); } catch (\Throwable $e) { /* ignore */ }

            // Drop the old column if it exists and has a different type
            try { DB::statement('alter table sessions drop column if exists user_id'); } catch (\Throwable $e) { /* ignore */ }

            // Rename new column to user_id and add index + FK
            DB::statement("alter table sessions rename column user_id_uuid to user_id");
            DB::statement("create index if not exists sessions_user_id_index on sessions (user_id)");
            // Add FK (nullable, on delete set null)
            try {
                DB::statement("alter table sessions add constraint sessions_user_id_fkey foreign key (user_id) references users(id) on delete set null");
            } catch (\Throwable $e) { /* ignore if already exists */ }
        } catch (\Throwable $e) {
            // Fallback no-op for non-Postgres drivers
        }
    }

    public function down(): void
    {
        // Down migration reverts to nullable bigint (implementation is environment-specific).
        // We'll safely drop the FK and change type back using a temporary column.
        try {
            try { DB::statement('alter table sessions drop constraint if exists sessions_user_id_fkey'); } catch (\Throwable $e) { /* ignore */ }
            DB::statement('alter table sessions add column if not exists user_id_bigint bigint');
            // We cannot safely convert UUIDs back to bigint; leave nulls
            try { DB::statement('drop index if exists sessions_user_id_index'); } catch (\Throwable $e) { /* ignore */ }
            try { DB::statement('alter table sessions drop column if exists user_id'); } catch (\Throwable $e) { /* ignore */ }
            DB::statement('alter table sessions rename column user_id_bigint to user_id');
            DB::statement('create index if not exists sessions_user_id_index on sessions (user_id)');
        } catch (\Throwable $e) {
            // ignore
        }
    }
};

