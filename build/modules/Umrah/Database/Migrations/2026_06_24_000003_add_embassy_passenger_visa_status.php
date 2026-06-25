<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE umrah.passengers DROP CONSTRAINT IF EXISTS passengers_visa_status_check');
        DB::statement("ALTER TABLE umrah.passengers ADD CONSTRAINT passengers_visa_status_check
            CHECK (visa_status IN ('pending', 'received', 'submitted', 'embassy', 'approved', 'rejected', 'delivered'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE umrah.passengers SET visa_status = 'submitted' WHERE visa_status = 'embassy'");
        DB::statement('ALTER TABLE umrah.passengers DROP CONSTRAINT IF EXISTS passengers_visa_status_check');
        DB::statement("ALTER TABLE umrah.passengers ADD CONSTRAINT passengers_visa_status_check
            CHECK (visa_status IN ('pending', 'received', 'submitted', 'approved', 'rejected', 'delivered'))");
    }
};
