<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE umrah.vouchers DROP CONSTRAINT IF EXISTS vouchers_status_check');
        DB::statement("UPDATE umrah.vouchers SET status = CASE WHEN status = 'issued' THEN 'approved' ELSE 'draft' END WHERE status NOT IN ('draft', 'approved')");
        DB::statement("ALTER TABLE umrah.vouchers ADD CONSTRAINT vouchers_status_check CHECK (status IN ('draft', 'approved'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE umrah.vouchers DROP CONSTRAINT IF EXISTS vouchers_status_check');
        DB::statement("UPDATE umrah.vouchers SET status = 'issued' WHERE status = 'approved'");
        DB::statement("ALTER TABLE umrah.vouchers ADD CONSTRAINT vouchers_status_check CHECK (status IN ('draft', 'issued', 'cancelled'))");
    }
};
