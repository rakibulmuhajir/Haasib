<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('umrah.agents', 'user_id')) {
            return;
        }

        Schema::table('umrah.agents', function (Blueprint $table) {
            $table->uuid('user_id')->nullable();

            $table->foreign('user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->unique(['company_id', 'user_id']);
            $table->index(['company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('umrah.agents', 'user_id')) {
            return;
        }

        Schema::table('umrah.agents', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'user_id']);
            $table->dropUnique(['company_id', 'user_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
