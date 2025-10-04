<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // No changes needed - we'll keep using the special UUID approach
        // This migration is now just a placeholder to mark the decision
        // to stick with the current approach

        // The current approach using a special UUID for system roles is actually
        // more aligned with how the Spatie permission package works with teams
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No changes needed
    }
};