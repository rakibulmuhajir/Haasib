<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pgsql')->table('public.roles', function (Blueprint $table) {
            $table->uuid('team_id')->nullable()->index()->after('guard_name');
        });

        // Add composite index for name + team_id for team-scoped roles
        Schema::connection('pgsql')->table('public.roles', function (Blueprint $table) {
            $table->index(['name', 'team_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql')->table('public.roles', function (Blueprint $table) {
            $table->dropIndex(['name', 'team_id']);
            $table->dropColumn('team_id');
        });
    }
};