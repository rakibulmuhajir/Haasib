<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('acct.industry_coa_packs')
            ->where('code', 'umrah')
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('acct.industry_coa_packs')
            ->where('code', 'umrah')
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }
};
