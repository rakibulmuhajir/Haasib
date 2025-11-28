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
        Schema::table('auth.users', function (Blueprint $table) {
            if (! Schema::hasColumn('auth.users', 'two_factor_secret')) {
                $table->text('two_factor_secret')->after('password')->nullable();
            }
            if (! Schema::hasColumn('auth.users', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')->after('two_factor_secret')->nullable();
            }
            if (! Schema::hasColumn('auth.users', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->after('two_factor_recovery_codes')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auth.users', function (Blueprint $table) {
            $drops = [];
            foreach (['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'] as $col) {
                if (Schema::hasColumn('auth.users', $col)) {
                    $drops[] = $col;
                }
            }
            if (! empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};
