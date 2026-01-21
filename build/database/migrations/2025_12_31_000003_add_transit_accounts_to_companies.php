<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS transit_loss_account_id UUID");
        DB::statement("ALTER TABLE auth.companies ADD COLUMN IF NOT EXISTS transit_gain_account_id UUID");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS transit_loss_account_id");
        DB::statement("ALTER TABLE auth.companies DROP COLUMN IF EXISTS transit_gain_account_id");
    }
};
