<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE umrah.visa_groups DROP CONSTRAINT IF EXISTS visa_groups_transport_mode_check');
        DB::statement("ALTER TABLE umrah.visa_groups ADD CONSTRAINT visa_groups_transport_mode_check CHECK (transport_mode IN ('none', 'standard_bus', 'specialized'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE umrah.visa_groups SET transport_mode = 'standard_bus', transport_required = true WHERE transport_mode = 'none'");
        DB::statement('ALTER TABLE umrah.visa_groups DROP CONSTRAINT IF EXISTS visa_groups_transport_mode_check');
        DB::statement("ALTER TABLE umrah.visa_groups ADD CONSTRAINT visa_groups_transport_mode_check CHECK (transport_mode IN ('standard_bus', 'specialized'))");
    }
};
